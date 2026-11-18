<?php
/**
 * Visitor Stats — daily views and visitors, no cookies (roci-stats)
 *
 * OPT-IN: loaded only when the child declares
 *
 *     add_theme_support( 'roci-stats' );
 *
 * (el_rocinante_load_stats(), functions.php). Without it nothing here runs:
 * no tables, script, route, widget or cron event.
 *
 * COUNTED IN THE BROWSER, because the page cache answers most page views
 * without running PHP. dist/js/stats.js (footer, logged-out visitors only)
 * sends one POST to roci/v1/hit per page load; it stays silent for a
 * logged-in body, the admin bar, navigator.webdriver and a prerender.
 *
 * THE ROUTE (public, no nonce — a cached page carries a stale one) answers 204
 * with no-cache headers whatever happens, so it reveals nothing. It counts
 * nothing for an empty or bot-looking User-Agent, or past 120 hits per
 * visitor per hour.
 *
 * VISITORS WITHOUT COOKIES OR STORED IPs. A visitor is
 *     sha256( wp_salt( 'auth' ) . day . IP . User-Agent )
 * with the day in site time, so the same person tomorrow is a new hash: a
 * daily rotation by design. Only the hash is stored, in roci_stats_seen, and
 * only until the day after. The IP is roci_forms_client_ip() — REMOTE_ADDR,
 * or Cloudflare's header only when the site defines ROCI_FORMS_TRUST_CF_IP.
 *
 * PER HIT: INSERT IGNORE the hash into roci_stats_seen (a new row = a new
 * visitor), then one upsert on roci_stats_daily: views + 1, uniques + 1 when
 * the hash was new.
 *
 * DASHBOARD: a "Visitors" widget (manage_options) — today's views and
 * visitors, the last 7 days as an inline SVG bar chart, and the same numbers
 * as a screen-reader table.
 *
 * RETENTION: a daily cron event, roci_stats_prune, drops seen rows older
 * than yesterday and, when roci_stats_retention_days is above 0 (default 0,
 * keep forever), daily rows older than that.
 *
 * TURNING IT OFF FOR GOOD. Removing the flag stops everything but leaves the
 * tables, the version option and the event. To remove them (cPanel terminal,
 * in the site's folder):
 *     wp cron event unschedule roci_stats_prune
 *     wp db query "DROP TABLE IF EXISTS $(wp db prefix)roci_stats_daily, $(wp db prefix)roci_stats_seen"
 *     wp option delete roci_stats_db_version
 *
 * File:    inc/stats/stats.php
 * Version: 1.0.0
 * Updated: 2026-11-18
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The tables' schema version. Bump with any column change; dbDelta upgrades. */
define( 'ROCI_STATS_DB_VERSION', '1.0.0' );

/** Hits counted per visitor hash per hour; the rest are answered and dropped. */
define( 'ROCI_STATS_RATE_LIMIT', 120 );


// ============================================================
// TABLES
// ============================================================

/**
 * roci_stats_table()
 *
 * @param  string $which 'daily' or 'seen'.
 * @return string        The table name with the site's prefix.
 */
function roci_stats_table( $which ) {
    global $wpdb;

    return $wpdb->prefix . ( 'seen' === $which ? 'roci_stats_seen' : 'roci_stats_daily' );
}

/**
 * roci_stats_schema()
 *
 * Both CREATE TABLE statements, in dbDelta's format.
 *
 * @return string[]
 */
function roci_stats_schema() {
    global $wpdb;

    $collate = $wpdb->get_charset_collate();
    $daily   = roci_stats_table( 'daily' );
    $seen    = roci_stats_table( 'seen' );

    return array(
        "CREATE TABLE {$daily} (
  day date NOT NULL,
  views int(10) unsigned NOT NULL DEFAULT 0,
  uniques int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day)
) {$collate};",
        "CREATE TABLE {$seen} (
  day date NOT NULL,
  hash char(64) NOT NULL,
  PRIMARY KEY  (day,hash)
) {$collate};",
    );
}

/**
 * roci_stats_ready()
 *
 * @return bool True once the tables exist at this version.
 */
function roci_stats_ready() {
    return ROCI_STATS_DB_VERSION === get_option( 'roci_stats_db_version' );
}

/**
 * roci_stats_install()
 *
 * On admin_init: create or upgrade both tables when the stored version
 * differs.
 */
function roci_stats_install() {
    if ( roci_stats_ready() ) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( roci_stats_schema() );

    update_option( 'roci_stats_db_version', ROCI_STATS_DB_VERSION, false );
}
add_action( 'admin_init', 'roci_stats_install' );


// ============================================================
// THE SCRIPT
// ============================================================

/**
 * roci_stats_enqueue()
 *
 * dist/js/stats.js in the footer, on the front end, for logged-out visitors
 * only, once the tables exist. The endpoint is passed in the page, which a
 * page cache may store: it holds no nonce, so a cached copy never goes stale.
 */
function roci_stats_enqueue() {
    if ( is_user_logged_in() || ! roci_stats_ready() ) {
        return;
    }

    wp_enqueue_script(
        'el-rocinante-stats',
        get_template_directory_uri() . '/dist/js/stats.js',
        array(),
        filemtime( get_template_directory() . '/dist/js/stats.js' ),
        true
    );
    wp_localize_script( 'el-rocinante-stats', 'rociStats', array(
        'endpoint' => esc_url_raw( rest_url( 'roci/v1/hit' ) ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'roci_stats_enqueue' );


// ============================================================
// THE ROUTE
// ============================================================

/**
 * roci_stats_register_rest()
 */
function roci_stats_register_rest() {
    register_rest_route( 'roci/v1', '/hit', array(
        'methods'             => 'POST',
        'callback'            => 'roci_stats_rest_hit',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'roci_stats_register_rest' );

/**
 * roci_stats_is_bot()
 *
 * Internal. True for an empty User-Agent or one that names a crawler,
 * headless browser, monitor or HTTP library.
 *
 * @param  string $ua
 * @return bool
 */
function roci_stats_is_bot( $ua ) {
    return '' === trim( $ua ) || (bool) preg_match( '/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|gtmetrix|preview|monitor|uptime|curl|wget|python|http-client/i', $ua );
}

/**
 * roci_stats_over_limit()
 *
 * Internal. Counts the hit against the visitor's hourly allowance; true once
 * it is used up. The window starts at the first hit and is not extended by
 * later ones.
 *
 * @param  string $hash The visitor hash.
 * @return bool
 */
function roci_stats_over_limit( $hash ) {
    $key   = 'roci_stats_rl_' . substr( $hash, 0, 40 );
    $state = get_transient( $key );
    $now   = time();
    $state = is_array( $state ) && isset( $state['n'], $state['t'] ) ? $state : array( 'n' => 0, 't' => $now );

    if ( $state['n'] >= ROCI_STATS_RATE_LIMIT ) {
        return true;
    }

    $state['n']++;
    set_transient( $key, $state, max( 1, HOUR_IN_SECONDS - ( $now - $state['t'] ) ) );

    return false;
}

/**
 * roci_stats_count()
 *
 * Internal. Records one view by the visitor $hash on $day: a new hash in
 * roci_stats_seen is a new visitor. One upsert on roci_stats_daily.
 *
 * @param string $day  Y-m-d, site time.
 * @param string $hash The visitor hash.
 */
function roci_stats_count( $day, $hash ) {
    global $wpdb;

    $seen  = roci_stats_table( 'seen' );
    $daily = roci_stats_table( 'daily' );

    $new = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$seen} (day, hash) VALUES (%s, %s)", $day, $hash ) ); // phpcs:ignore WordPress.DB
    $new = $new > 0 ? 1 : 0;

    $wpdb->query( // phpcs:ignore WordPress.DB
        $wpdb->prepare(
            "INSERT INTO {$daily} (day, views, uniques) VALUES (%s, 1, %d)
             ON DUPLICATE KEY UPDATE views = views + 1, uniques = uniques + VALUES(uniques)",
            $day,
            $new
        )
    );
}

/**
 * roci_stats_rest_hit()
 *
 * The beacon's endpoint. Always 204, never cached.
 *
 * @param  WP_REST_Request $request
 * @return WP_REST_Response
 */
function roci_stats_rest_hit( $request ) {
    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'roci stats hit' );

    $response = new WP_REST_Response( null, 204 );
    $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
    $response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );

    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ), 0, 512 ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- hashed, never stored.

    if ( ! roci_stats_ready() || roci_stats_is_bot( $ua ) ) {
        return $response;
    }

    $ip   = function_exists( 'roci_forms_client_ip' ) ? roci_forms_client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    $day  = current_time( 'Y-m-d' );
    $hash = hash( 'sha256', wp_salt( 'auth' ) . $day . $ip . $ua );

    if ( roci_stats_over_limit( $hash ) ) {
        return $response;
    }

    roci_stats_count( $day, $hash );

    return $response;
}


// ============================================================
// DASHBOARD WIDGET
// ============================================================

/**
 * roci_stats_last_days()
 *
 * The last $n days in site time, oldest first, each with its views and
 * uniques; a day with no row is 0 / 0.
 *
 * @param  int $n
 * @return array Each array( 'day' => Y-m-d, 'views' => int, 'uniques' => int ).
 */
function roci_stats_last_days( $n = 7 ) {
    global $wpdb;

    $today = current_time( 'Y-m-d' );
    $days  = array();

    for ( $i = $n - 1; $i >= 0; $i-- ) {
        $d          = gmdate( 'Y-m-d', strtotime( $today . ' -' . $i . ' days' ) );
        $days[ $d ] = array( 'day' => $d, 'views' => 0, 'uniques' => 0 );
    }

    if ( roci_stats_ready() ) {
        $table = roci_stats_table( 'daily' );
        $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT day, views, uniques FROM {$table} WHERE day BETWEEN %s AND %s", array_key_first( $days ), $today ), ARRAY_A ); // phpcs:ignore WordPress.DB

        foreach ( (array) $rows as $row ) {
            if ( isset( $days[ $row['day'] ] ) ) {
                $days[ $row['day'] ]['views']   = (int) $row['views'];
                $days[ $row['day'] ]['uniques'] = (int) $row['uniques'];
            }
        }
    }

    return array_values( $days );
}

/**
 * roci_stats_chart_svg()
 *
 * The 7-day chart: per day a views bar (currentColor, faint) with the
 * visitors bar inside it (the admin theme colour), the day under it and the
 * views count above. role="img" with a <title>; the numbers are also in the
 * widget's screen-reader table.
 *
 * @param  array $days roci_stats_last_days().
 * @return string
 */
function roci_stats_chart_svg( $days ) {
    $w     = 40;           // one day's column
    $plot  = 100;          // bar area height
    $top   = 16;           // room for the count above a bar
    $max   = max( 1, max( wp_list_pluck( $days, 'views' ) ) );
    $width = $w * count( $days );

    $svg  = '<svg role="img" aria-labelledby="roci-stats-chart-title" viewBox="0 0 ' . $width . ' ' . ( $top + $plot + 20 ) . '" width="100%" style="max-width:' . ( $width * 1.6 ) . 'px;display:block;overflow:visible">';
    $svg .= '<title id="roci-stats-chart-title">' . esc_html__( 'Views and visitors, last 7 days', 'rocinante' ) . '</title>';

    foreach ( array_values( $days ) as $i => $d ) {
        $x  = $i * $w;
        $vh = round( $plot * $d['views'] / $max, 1 );
        $uh = round( $plot * $d['uniques'] / $max, 1 );
        $by = $top + $plot;

        $svg .= '<rect x="' . ( $x + 6 ) . '" y="' . ( $by - $vh ) . '" width="' . ( $w - 12 ) . '" height="' . $vh . '" fill="currentColor" fill-opacity="0.2"/>';
        $svg .= '<rect x="' . ( $x + 12 ) . '" y="' . ( $by - $uh ) . '" width="' . ( $w - 24 ) . '" height="' . $uh . '" style="fill:var(--wp-admin-theme-color,#2271b1)"/>';
        $svg .= '<text x="' . ( $x + $w / 2 ) . '" y="' . ( $by - $vh - 4 ) . '" text-anchor="middle" font-size="10" fill="currentColor">' . (int) $d['views'] . '</text>';
        $svg .= '<text x="' . ( $x + $w / 2 ) . '" y="' . ( $by + 14 ) . '" text-anchor="middle" font-size="10" fill="currentColor">' . esc_html( date_i18n( 'D', strtotime( $d['day'] . ' 12:00:00' ) ) ) . '</text>';
    }

    return $svg . '</svg>';
}

/**
 * roci_stats_widget()
 *
 * The widget body.
 */
function roci_stats_widget() {
    $days  = roci_stats_last_days( 7 );
    $today = end( $days );
    ?>
    <p style="font-size:14px;margin-top:0">
        <?php
        /* translators: 1: views today, 2: visitors today. */
        echo esc_html( sprintf( __( 'Today: %1$s views · %2$s visitors', 'rocinante' ), number_format_i18n( $today['views'] ), number_format_i18n( $today['uniques'] ) ) );
        ?>
    </p>
    <?php echo roci_stats_chart_svg( $days ); // Built from integers and escaped strings. ?>
    <p style="margin-bottom:0">
        <span aria-hidden="true" style="display:inline-block;width:10px;height:10px;background:currentColor;opacity:.2"></span> <?php esc_html_e( 'Views', 'rocinante' ); ?>
        &nbsp; <span aria-hidden="true" style="display:inline-block;width:10px;height:10px;background:var(--wp-admin-theme-color,#2271b1)"></span> <?php esc_html_e( 'Visitors', 'rocinante' ); ?>
    </p>
    <table class="screen-reader-text">
        <caption><?php esc_html_e( 'Views and visitors, last 7 days', 'rocinante' ); ?></caption>
        <thead><tr><th scope="col"><?php esc_html_e( 'Day', 'rocinante' ); ?></th><th scope="col"><?php esc_html_e( 'Views', 'rocinante' ); ?></th><th scope="col"><?php esc_html_e( 'Visitors', 'rocinante' ); ?></th></tr></thead>
        <tbody>
        <?php foreach ( $days as $d ) : ?>
            <tr><th scope="row"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $d['day'] . ' 12:00:00' ) ) ); ?></th><td><?php echo (int) $d['views']; ?></td><td><?php echo (int) $d['uniques']; ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * roci_stats_dashboard()
 *
 * On wp_dashboard_setup, for manage_options only.
 */
function roci_stats_dashboard() {
    if ( current_user_can( 'manage_options' ) ) {
        wp_add_dashboard_widget( 'roci_stats_widget', __( 'Visitors', 'rocinante' ), 'roci_stats_widget' );
    }
}
add_action( 'wp_dashboard_setup', 'roci_stats_dashboard' );


// ============================================================
// RETENTION
// ============================================================

/**
 * roci_stats_schedule()
 *
 * On init: the daily prune, scheduled once.
 */
function roci_stats_schedule() {
    if ( ! wp_next_scheduled( 'roci_stats_prune' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'roci_stats_prune' );
    }
}
add_action( 'init', 'roci_stats_schedule' );

/**
 * roci_stats_prune()
 *
 * Seen rows older than yesterday go (their hashes can never match again);
 * daily rows go only when roci_stats_retention_days is above 0.
 */
function roci_stats_prune() {
    global $wpdb;

    if ( ! roci_stats_ready() ) {
        return;
    }

    $today     = current_time( 'Y-m-d' );
    $yesterday = gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) );

    $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . roci_stats_table( 'seen' ) . ' WHERE day < %s', $yesterday ) ); // phpcs:ignore WordPress.DB

    $keep = (int) apply_filters( 'roci_stats_retention_days', 0 );

    if ( $keep > 0 ) {
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . roci_stats_table( 'daily' ) . ' WHERE day < %s', gmdate( 'Y-m-d', strtotime( $today . ' -' . $keep . ' days' ) ) ) ); // phpcs:ignore WordPress.DB
    }
}
add_action( 'roci_stats_prune', 'roci_stats_prune' );
