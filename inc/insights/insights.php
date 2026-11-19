<?php
/**
 * Insights — visitor numbers and missing-page reports in one admin menu
 *
 * LOADED ONLY WHEN THE CHILD DECLARES roci-stats AND / OR roci-404-log
 * (el_rocinante_load_insights(), functions.php). Display only: the tables,
 * the stats beacon and the 404 logger are the modules' own and untouched.
 *
 * THE MENU — top level "Insights" (dashicons-chart-area), right after
 * Dashboard, for roci_insights_capability() (default manage_options):
 *   Overview  (roci-insights)          both modules' summaries, side by side
 *   Visitors  (roci-insights-visitors) roci-stats only: 7 / 30 / 90 days
 *   404 log   (roci-insights-404-log)  roci-404-log only: the list table,
 *                                      moved from Theme Settings → 404 log;
 *                                      the old slug roci-404-log redirects
 *                                      here with its query args.
 *
 * DASHBOARD — "Visitors" (replaces stats.php's widget, same id) and "404s
 * this week", each for the capability and only with its module.
 *
 * LOOK — WordPress-native: white cards with a #c3c4c7 border and 4px radius,
 * the admin colour scheme's --wp-admin-theme-color for accents and focus,
 * inline-SVG charts (role="img" + <title> + a visually hidden table). One
 * small stylesheet, roci_insights_styles(), printed by the screens and the
 * widgets themselves, so only where they render. Changes always carry a word
 * and an arrow, never colour alone.
 *
 * File:    inc/insights/insights.php
 * Version: 1.0.0
 * Updated: 2026-11-19
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// WHO, WHICH MODULES
// ============================================================

/**
 * roci_insights_capability()
 *
 * The capability for the Insights menu and widgets. Filter
 * roci_insights_capability (a non-string or empty return falls back).
 *
 * @return string
 */
function roci_insights_capability() {
    $cap = apply_filters( 'roci_insights_capability', 'manage_options' );

    return ( is_string( $cap ) && '' !== $cap ) ? $cap : 'manage_options';
}

/**
 * roci_insights_has()
 *
 * Internal. Whether a module is on: 'stats' or '404'.
 *
 * @param  string $module
 * @return bool
 */
function roci_insights_has( $module ) {
    return current_theme_supports( 'stats' === $module ? 'roci-stats' : 'roci-404-log' );
}

/**
 * roci_insights_404_ready()
 *
 * Internal. The 404 table exists at its version.
 *
 * @return bool
 */
function roci_insights_404_ready() {
    return defined( 'ROCI_404_LOG_DB_VERSION' ) && ROCI_404_LOG_DB_VERSION === get_option( 'roci_404_log_db_version' );
}


// ============================================================
// THE MENU + THE OLD SLUG
// ============================================================

/**
 * roci_insights_menu()
 *
 * On admin_menu: Insights, after Dashboard (position 2.1), and its screens.
 */
function roci_insights_menu() {
    $cap = roci_insights_capability();

    if ( ! current_user_can( $cap ) ) {
        return;
    }

    add_menu_page( __( 'Insights', 'rocinante' ), __( 'Insights', 'rocinante' ), $cap, 'roci-insights', 'roci_insights_overview_screen', 'dashicons-chart-area', 2.1 );
    add_submenu_page( 'roci-insights', __( 'Insights overview', 'rocinante' ), __( 'Overview', 'rocinante' ), $cap, 'roci-insights', 'roci_insights_overview_screen' );

    if ( roci_insights_has( 'stats' ) ) {
        add_submenu_page( 'roci-insights', __( 'Visitors', 'rocinante' ), __( 'Visitors', 'rocinante' ), $cap, 'roci-insights-visitors', 'roci_insights_visitors_screen' );
    }

    if ( roci_insights_has( '404' ) && function_exists( 'roci_404_log_screen' ) ) {
        $hook = add_submenu_page( 'roci-insights', __( '404 log', 'rocinante' ), __( '404 log', 'rocinante' ), $cap, 'roci-insights-404-log', 'roci_404_log_screen' );
        if ( $hook ) {
            add_action( 'load-' . $hook, 'roci_404_log_load' );
        }
    }
}
add_action( 'admin_menu', 'roci_insights_menu' );

/**
 * roci_insights_404_url()
 *
 * Internal. The 404 log screen's URL, with optional query args.
 *
 * @param  array $args
 * @return string
 */
function roci_insights_404_url( $args = array() ) {
    return admin_url( 'admin.php?' . http_build_query( array_merge( array( 'page' => 'roci-insights-404-log' ), $args ) ) );
}

/**
 * roci_insights_old_slug_redirect()
 *
 * On admin_menu, last: admin.php?page=roci-404-log (Theme Settings → 404 log
 * until 8.10.0) → the Insights 404 log, every other query arg kept (dates,
 * kind, sort, paging). admin_menu, not admin_init: core checks the page slug
 * against the registered menu right after admin_menu and would wp_die() on
 * the retired slug before admin_init runs. The target checks the capability.
 */
function roci_insights_old_slug_redirect() {
    global $pagenow;

    if ( 'admin.php' !== $pagenow || ! isset( $_GET['page'] ) || 'roci-404-log' !== $_GET['page'] || ! roci_insights_has( '404' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- a redirect, no action.
        return;
    }

    $args = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
    unset( $args['page'] );

    wp_safe_redirect( roci_insights_404_url( $args ) );
    exit;
}
add_action( 'admin_menu', 'roci_insights_old_slug_redirect', PHP_INT_MAX );


// ============================================================
// DATA (reads only)
// ============================================================

/**
 * roci_insights_stats_series()
 *
 * $n days of stats, oldest first, ending $back days before today (site
 * time); a day with no row is 0 / 0 (a gap stays visible as a 0 day).
 *
 * @param  int $n
 * @param  int $back 0 = ending today; $n = the previous period.
 * @return array Each array( 'day' => Y-m-d, 'views' => int, 'uniques' => int ).
 */
function roci_insights_stats_series( $n, $back = 0 ) {
    global $wpdb;

    $end  = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . (int) $back . ' days' ) );
    $days = array();

    for ( $i = $n - 1; $i >= 0; $i-- ) {
        $d          = gmdate( 'Y-m-d', strtotime( $end . ' -' . $i . ' days' ) );
        $days[ $d ] = array( 'day' => $d, 'views' => 0, 'uniques' => 0 );
    }

    if ( function_exists( 'roci_stats_ready' ) && roci_stats_ready() ) {
        $table = roci_stats_table( 'daily' );
        $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT day, views, uniques FROM {$table} WHERE day BETWEEN %s AND %s", array_key_first( $days ), $end ), ARRAY_A ); // phpcs:ignore WordPress.DB

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
 * roci_insights_stats_summary()
 *
 * Totals for a series and the one before it.
 *
 * @param  int $n
 * @return array days, views, visitors, avg (views per day, 1 dp),
 *               prev_views, prev_visitors.
 */
function roci_insights_stats_summary( $n ) {
    $days = roci_insights_stats_series( $n );
    $prev = roci_insights_stats_series( $n, $n );

    $views = array_sum( wp_list_pluck( $days, 'views' ) );

    return array(
        'days'          => $days,
        'views'         => $views,
        'visitors'      => array_sum( wp_list_pluck( $days, 'uniques' ) ),
        'avg'           => round( $views / max( 1, $n ), 1 ),
        'prev_views'    => array_sum( wp_list_pluck( $prev, 'views' ) ),
        'prev_visitors' => array_sum( wp_list_pluck( $prev, 'uniques' ) ),
    );
}

/**
 * roci_insights_change()
 *
 * A period-on-period change, as words and an arrow (never colour alone).
 *
 * @param  int $now
 * @param  int $before
 * @return array dir (up | down | flat | new), pct (int|null), text, arrow.
 */
function roci_insights_change( $now, $before ) {
    $now    = (int) $now;
    $before = (int) $before;

    if ( $now === $before ) {
        return array( 'dir' => 'flat', 'pct' => 0, 'text' => __( 'No change', 'rocinante' ), 'arrow' => '→' );
    }
    if ( 0 === $before ) {
        return array( 'dir' => 'new', 'pct' => null, 'text' => __( 'Up from none', 'rocinante' ), 'arrow' => '↑' );
    }

    $pct = (int) round( ( $now - $before ) / $before * 100 );

    if ( $now > $before ) {
        /* translators: %s: a percentage, e.g. "12%". */
        return array( 'dir' => 'up', 'pct' => $pct, 'text' => sprintf( __( 'Up %s', 'rocinante' ), '+' . number_format_i18n( $pct ) . '%' ), 'arrow' => '↑' );
    }
    /* translators: %s: a percentage, e.g. "-12%". */
    return array( 'dir' => 'down', 'pct' => $pct, 'text' => sprintf( __( 'Down %s', 'rocinante' ), '−' . number_format_i18n( abs( $pct ) ) . '%' ), 'arrow' => '↓' );
}

/**
 * roci_insights_domain()
 *
 * Internal. A URL's host without "www.", or '' when it has none.
 *
 * @param  string $url
 * @return string
 */
function roci_insights_domain( $url ) {
    $host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );

    return strtolower( preg_replace( '/^www\./i', '', $host ) );
}

/**
 * roci_insights_truncate()
 *
 * Internal. $text cut to $len characters with "…" (UTF-8 aware when the
 * multibyte functions WordPress polyfills are there).
 *
 * @param  string $text
 * @param  int    $len
 * @return string
 */
function roci_insights_truncate( $text, $len = 60 ) {
    $text   = (string) $text;
    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

    if ( $length <= $len ) {
        return $text;
    }

    return ( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $len - 1 ) : substr( $text, 0, $len - 1 ) ) . '…';
}

/**
 * roci_insights_404_summary()
 *
 * For $from..$to (site dates): hits (SUM), unique URLs, suppressed archive
 * URLs (distinct), and the most common referrer domain by hits.
 *
 * @param  string $from Y-m-d.
 * @param  string $to   Y-m-d.
 * @return array hits, urls, suppressed, domain, domain_hits.
 */
function roci_insights_404_summary( $from, $to ) {
    global $wpdb;

    $out = array( 'hits' => 0, 'urls' => 0, 'suppressed' => 0, 'domain' => '', 'domain_hits' => 0 );

    if ( ! roci_insights_404_ready() ) {
        return $out;
    }

    $table = roci_404_log_table();
    $row   = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(hits), 0) AS hits, COUNT(DISTINCT url_hash) AS urls, COUNT(DISTINCT CASE WHEN kind = 'suppressed' THEN url_hash END) AS suppressed FROM {$table} WHERE day BETWEEN %s AND %s", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB

    if ( is_array( $row ) ) {
        $out['hits']       = (int) $row['hits'];
        $out['urls']       = (int) $row['urls'];
        $out['suppressed'] = (int) $row['suppressed'];
    }

    $refs   = $wpdb->get_results( $wpdb->prepare( "SELECT referrer, SUM(hits) AS hits FROM {$table} WHERE day BETWEEN %s AND %s AND referrer <> '' GROUP BY referrer", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB
    $tally  = array();
    foreach ( (array) $refs as $ref ) {
        $domain = roci_insights_domain( $ref['referrer'] );
        if ( '' !== $domain ) {
            $tally[ $domain ] = ( isset( $tally[ $domain ] ) ? $tally[ $domain ] : 0 ) + (int) $ref['hits'];
        }
    }
    if ( $tally ) {
        arsort( $tally );
        $out['domain']      = (string) array_key_first( $tally );
        $out['domain_hits'] = (int) reset( $tally );
    }

    return $out;
}

/**
 * roci_insights_404_top()
 *
 * The most-hit URLs in $from..$to.
 *
 * @param  string $from
 * @param  string $to
 * @param  int    $limit
 * @return array Each array( 'url' => string, 'hits' => int ).
 */
function roci_insights_404_top( $from, $to, $limit = 5 ) {
    global $wpdb;

    if ( ! roci_insights_404_ready() ) {
        return array();
    }

    $table = roci_404_log_table();
    $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT MAX(url) AS url, SUM(hits) AS hits FROM {$table} WHERE day BETWEEN %s AND %s GROUP BY url_hash ORDER BY hits DESC, url_hash ASC LIMIT %d", $from, $to, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB

    return array_map( function ( $r ) {
        return array( 'url' => (string) $r['url'], 'hits' => (int) $r['hits'] );
    }, (array) $rows );
}

/**
 * roci_insights_week()
 *
 * Internal. The last 7 days, today included: array( from, to ).
 *
 * @return string[]
 */
function roci_insights_week() {
    $today = current_time( 'Y-m-d' );

    return array( gmdate( 'Y-m-d', strtotime( $today . ' -6 days' ) ), $today );
}


// ============================================================
// PIECES (shared markup)
// ============================================================

/**
 * roci_insights_styles()
 *
 * The one admin stylesheet, printed once per request by whichever Insights
 * screen or widget renders first. Admin-only; WordPress-native.
 */
function roci_insights_styles() {
    static $done = false;

    if ( $done ) {
        return;
    }
    $done = true;
    ?>
    <style id="roci-insights-css">
        .roci-ins{--roci-accent:var(--wp-admin-theme-color,#2271b1)}
        .roci-ins-cards{display:flex;flex-wrap:wrap;gap:12px;margin:12px 0}
        .roci-ins-card{flex:1 1 150px;min-width:0;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px 16px}
        .roci-ins-card__label{margin:0;color:#50575e;font-size:13px}
        .roci-ins-card__value{margin:4px 0 0;color:#1d2327;font-size:24px;font-weight:600;line-height:1.25;overflow-wrap:anywhere}
        .roci-ins-card__note{margin:4px 0 0;color:#50575e;font-size:12px}
        .roci-ins-delta{font-weight:600}
        .roci-ins-delta--up{color:#007017}
        .roci-ins-delta--down{color:#b32d2e}
        .roci-ins-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:16px;margin-top:16px}
        .roci-ins-panel{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;margin:16px 0}
        .roci-ins-grid .roci-ins-panel{margin:0}
        .roci-ins-panel h2{margin:0 0 4px;font-size:16px}
        .roci-ins-range{display:flex;flex-wrap:wrap;gap:4px;margin:12px 0}
        .roci-ins-range a{display:inline-block;min-height:24px;padding:6px 12px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;color:#1d2327;text-decoration:none}
        .roci-ins-range a[aria-current="page"]{background:var(--roci-accent);border-color:var(--roci-accent);color:#fff;font-weight:600}
        .roci-ins-chart{margin:12px 0 4px;color:#1d2327}
        .roci-ins-chart svg{display:block;width:100%;height:auto}
        .roci-ins-legend{margin:0;color:#50575e;font-size:12px}
        .roci-ins-key{display:inline-block;width:10px;height:10px;margin-right:4px;vertical-align:middle}
        .roci-ins-top{list-style:none;margin:8px 0 0;padding:0}
        .roci-ins-top li{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-top:1px solid #f0f0f1}
        .roci-ins-top li:first-child{border-top:0}
        .roci-ins-top .roci-ins-hits{flex:none;color:#50575e;font-variant-numeric:tabular-nums}
        .roci-ins-foot{margin:12px 0 0;padding-top:12px;border-top:1px solid #f0f0f1}
        .roci-ins-empty{margin:8px 0;color:#50575e}
        .roci-trunc{display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom}
        .roci-badge{display:inline-block;padding:1px 8px;border:1px solid;border-radius:999px;font-size:12px;font-weight:600;line-height:1.6;white-space:nowrap}
        .roci-badge--404{background:#fcf0f1;border-color:#e65054;color:#8a2424}
        .roci-badge--suppressed{background:#f6f7f7;border-color:#8c8f94;color:#3c434a}
        .roci-ins .wp-list-table .column-hits,.roci-ins-num{text-align:right;font-variant-numeric:tabular-nums}
        .roci-ins .wp-list-table .column-hits{width:6em}
        .roci-ins .wp-list-table .column-kind{width:8.5em}
        .roci-ins .wp-list-table .column-url code{background:none;padding:0}
        .roci-ins .tablenav .actions{display:flex;flex-wrap:wrap;align-items:center;gap:6px}
        .roci-ins-filters{margin:0;padding:0;border:0;min-width:0}
        .roci-ins .wp-list-table .column-referrer .roci-trunc{max-width:16em}
        .roci-ins a:focus-visible,.roci-ins button:focus-visible,.roci-ins input:focus-visible,.roci-ins select:focus-visible{outline:2px solid var(--roci-accent);outline-offset:2px;box-shadow:none}
    </style>
    <?php
}

/**
 * roci_insights_card()
 *
 * Internal. One summary card. $value is plain text unless $value_html is
 * true (then already escaped, e.g. roci_insights_delta()); $note may carry
 * the change markup.
 *
 * @param  string $label
 * @param  string $value
 * @param  string $note_html  Already escaped.
 * @param  bool   $value_html
 * @return string
 */
function roci_insights_card( $label, $value, $note_html = '', $value_html = false ) {
    return '<div class="roci-ins-card"><p class="roci-ins-card__label">' . esc_html( $label ) . '</p><p class="roci-ins-card__value">' . ( $value_html ? $value : esc_html( $value ) ) . '</p>'
        . ( '' !== $note_html ? '<p class="roci-ins-card__note">' . $note_html . '</p>' : '' ) . '</div>';
}

/**
 * roci_insights_delta()
 *
 * Internal. A change as "↑ Up +12%" (arrow hidden from screen readers; the
 * words carry it).
 *
 * @param  array $change roci_insights_change().
 * @return string HTML.
 */
function roci_insights_delta( $change ) {
    return '<span class="roci-ins-delta roci-ins-delta--' . esc_attr( $change['dir'] ) . '"><span aria-hidden="true">' . esc_html( $change['arrow'] ) . '</span> ' . esc_html( $change['text'] ) . '</span>';
}

/**
 * roci_insights_chart()
 *
 * Internal. The bar chart: per day a views bar (#8c8f94, 3.4:1 on white)
 * with the visitors bar inside it (the admin theme colour). role="img" with a
 * <title>; the numbers in a visually hidden table after it. Day labels thin
 * out at 30 / 90 days; counts above bars only at 7.
 *
 * @param  array  $days  roci_insights_stats_series().
 * @param  string $title
 * @return string
 */
function roci_insights_chart( $days, $title ) {
    static $n = 0;
    $n++;

    $count = count( $days );
    $col   = $count <= 7 ? 40 : ( $count <= 30 ? 20 : 8 );
    $step  = $count <= 7 ? 1 : ( $count <= 30 ? 5 : 15 );
    $plot  = 120;
    $top   = $count <= 7 ? 16 : 6;
    $max   = max( 1, max( wp_list_pluck( $days, 'views' ) ) );
    $width = $col * $count;
    $id    = 'roci-ins-chart-' . $n;

    $svg  = '<div class="roci-ins-chart"><svg role="img" aria-labelledby="' . $id . '" viewBox="0 0 ' . $width . ' ' . ( $top + $plot + 20 ) . '" preserveAspectRatio="xMidYMid meet" style="max-width:' . max( 280, $width * 1.6 ) . 'px">';
    $svg .= '<title id="' . $id . '">' . esc_html( $title ) . '</title>';

    foreach ( array_values( $days ) as $i => $d ) {
        $x   = $i * $col;
        $vh  = round( $plot * $d['views'] / $max, 1 );
        $uh  = round( $plot * $d['uniques'] / $max, 1 );
        $by  = $top + $plot;
        $pad = max( 1, round( $col * 0.15 ) );

        $svg .= '<rect x="' . ( $x + $pad ) . '" y="' . ( $by - $vh ) . '" width="' . ( $col - 2 * $pad ) . '" height="' . $vh . '" fill="#8c8f94"/>';
        $svg .= '<rect x="' . ( $x + 2 * $pad ) . '" y="' . ( $by - $uh ) . '" width="' . max( 1, $col - 4 * $pad ) . '" height="' . $uh . '" style="fill:var(--wp-admin-theme-color,#2271b1)"/>';
        if ( $count <= 7 ) {
            $svg .= '<text x="' . ( $x + $col / 2 ) . '" y="' . ( $by - $vh - 4 ) . '" text-anchor="middle" font-size="10" fill="currentColor">' . (int) $d['views'] . '</text>';
        }
        if ( 0 === ( $count - 1 - $i ) % $step ) { // label the last day, then every $step back
            $label = $count <= 7 ? date_i18n( 'D', strtotime( $d['day'] . ' 12:00:00' ) ) : date_i18n( 'j M', strtotime( $d['day'] . ' 12:00:00' ) );
            $svg  .= '<text x="' . ( $x + $col / 2 ) . '" y="' . ( $by + 14 ) . '" text-anchor="middle" font-size="10" fill="currentColor">' . esc_html( $label ) . '</text>';
        }
    }
    $svg .= '</svg></div>';

    $svg .= '<p class="roci-ins-legend"><span class="roci-ins-key" style="background:#8c8f94" aria-hidden="true"></span>' . esc_html__( 'Views', 'rocinante' )
        . ' &nbsp; <span class="roci-ins-key" style="background:var(--wp-admin-theme-color,#2271b1)" aria-hidden="true"></span>' . esc_html__( 'Visitors', 'rocinante' ) . '</p>';

    $svg .= '<table class="screen-reader-text"><caption>' . esc_html( $title ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Day', 'rocinante' ) . '</th><th scope="col">' . esc_html__( 'Views', 'rocinante' ) . '</th><th scope="col">' . esc_html__( 'Visitors', 'rocinante' ) . '</th></tr></thead><tbody>';
    foreach ( $days as $d ) {
        $svg .= '<tr><th scope="row">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $d['day'] . ' 12:00:00' ) ) ) . '</th><td>' . (int) $d['views'] . '</td><td>' . (int) $d['uniques'] . '</td></tr>';
    }

    return $svg . '</tbody></table>';
}

/**
 * roci_insights_top_list()
 *
 * Internal. The top-URLs list: the URL truncated (full in title) and its
 * hits.
 *
 * @param  array $top roci_insights_404_top().
 * @return string
 */
function roci_insights_top_list( $top ) {
    $html = '<ul class="roci-ins-top">';
    foreach ( $top as $row ) {
        $html .= '<li><code class="roci-trunc" title="' . esc_attr( $row['url'] ) . '">' . esc_html( roci_insights_truncate( $row['url'], 48 ) ) . '</code><span class="roci-ins-hits">'
            /* translators: %s: number of hits. */
            . esc_html( sprintf( _n( '%s hit', '%s hits', $row['hits'], 'rocinante' ), number_format_i18n( $row['hits'] ) ) ) . '</span></li>';
    }
    return $html . '</ul>';
}

/**
 * roci_insights_visitor_cards()
 *
 * Internal. Views, visitors, average per day, change — for roci_insights_stats_summary().
 *
 * @param  array $s
 * @param  int   $n
 * @return string
 */
function roci_insights_visitor_cards( $s, $n ) {
    $views    = roci_insights_change( $s['views'], $s['prev_views'] );
    $visitors = roci_insights_change( $s['visitors'], $s['prev_visitors'] );

    return '<div class="roci-ins-cards">'
        . roci_insights_card( __( 'Views', 'rocinante' ), number_format_i18n( $s['views'] ) )
        /* translators: the note under the Visitors total. */
        . roci_insights_card( __( 'Visitors', 'rocinante' ), number_format_i18n( $s['visitors'] ), esc_html__( 'Counted per day', 'rocinante' ) )
        . roci_insights_card( __( 'Average views per day', 'rocinante' ), number_format_i18n( $s['avg'], 1 ) )
        /* translators: %d: number of days. */
        . roci_insights_card( sprintf( __( 'Views vs the previous %d days', 'rocinante' ), $n ), roci_insights_delta( $views ), esc_html__( 'Visitors:', 'rocinante' ) . ' ' . roci_insights_delta( $visitors ), true )
        . '</div>';
}

/**
 * roci_insights_404_cards()
 *
 * Internal. Missing-page cards for roci_insights_404_summary().
 *
 * @param  array $sum
 * @param  bool  $with_domain Add the referrer-domain card.
 * @return string
 */
function roci_insights_404_cards( $sum, $with_domain = false ) {
    $html = '<div class="roci-ins-cards">'
        . roci_insights_card( __( 'Missing-page hits', 'rocinante' ), number_format_i18n( $sum['hits'] ) )
        . roci_insights_card( __( 'Unique URLs', 'rocinante' ), number_format_i18n( $sum['urls'] ) )
        . roci_insights_card( __( 'Suppressed archives', 'rocinante' ), number_format_i18n( $sum['suppressed'] ), esc_html__( 'Author, date and term archives turned off', 'rocinante' ) );

    if ( $with_domain ) {
        $html .= roci_insights_card(
            __( 'Top referrer', 'rocinante' ),
            '' !== $sum['domain'] ? $sum['domain'] : '—',
            /* translators: %s: number of hits. */
            '' !== $sum['domain'] ? esc_html( sprintf( _n( '%s hit', '%s hits', $sum['domain_hits'], 'rocinante' ), number_format_i18n( $sum['domain_hits'] ) ) ) : esc_html__( 'No referrers recorded', 'rocinante' )
        );
    }

    return $html . '</div>';
}


// ============================================================
// SCREENS
// ============================================================

/**
 * roci_insights_overview_screen()
 *
 * Insights → Overview: each module's last 7 days, side by side.
 */
function roci_insights_overview_screen() {
    if ( ! current_user_can( roci_insights_capability() ) ) {
        return;
    }
    roci_insights_styles();
    ?>
    <div class="wrap roci-ins">
        <h1><?php esc_html_e( 'Insights', 'rocinante' ); ?></h1>
        <p><?php esc_html_e( 'The last 7 days at a glance.', 'rocinante' ); ?></p>
        <div class="roci-ins-grid">
            <?php if ( roci_insights_has( 'stats' ) ) : $s = roci_insights_stats_summary( 7 ); ?>
                <section class="roci-ins-panel" aria-labelledby="roci-ins-ov-visitors">
                    <h2 id="roci-ins-ov-visitors"><?php esc_html_e( 'Visitors', 'rocinante' ); ?></h2>
                    <?php echo roci_insights_visitor_cards( $s, 7 ); // Escaped in the builders. ?>
                    <p class="roci-ins-foot"><a href="<?php echo esc_url( admin_url( 'admin.php?page=roci-insights-visitors' ) ); ?>"><?php esc_html_e( 'Open Visitors', 'rocinante' ); ?></a></p>
                </section>
            <?php endif; ?>
            <?php if ( roci_insights_has( '404' ) ) : list( $from, $to ) = roci_insights_week(); $sum = roci_insights_404_summary( $from, $to ); $top = roci_insights_404_top( $from, $to, 3 ); ?>
                <section class="roci-ins-panel" aria-labelledby="roci-ins-ov-404">
                    <h2 id="roci-ins-ov-404"><?php esc_html_e( 'Missing pages', 'rocinante' ); ?></h2>
                    <?php echo roci_insights_404_cards( $sum ); // Escaped in the builder. ?>
                    <?php if ( $top ) : ?>
                        <?php echo roci_insights_top_list( $top ); // Escaped in the builder. ?>
                    <?php else : ?>
                        <p class="roci-ins-empty"><?php esc_html_e( 'No missing pages this week.', 'rocinante' ); ?></p>
                    <?php endif; ?>
                    <p class="roci-ins-foot"><a href="<?php echo esc_url( roci_insights_404_url() ); ?>"><?php esc_html_e( 'Open 404 log', 'rocinante' ); ?></a></p>
                </section>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * roci_insights_range()
 *
 * Internal. The Visitors range from the request: 7, 30 (default) or 90.
 *
 * @return int
 */
function roci_insights_range() {
    $range = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification

    return in_array( $range, array( 7, 30, 90 ), true ) ? $range : 30;
}

/**
 * roci_insights_visitors_screen()
 *
 * Insights → Visitors: range links, the cards, the chart, the table by day.
 */
function roci_insights_visitors_screen() {
    if ( ! current_user_can( roci_insights_capability() ) ) {
        return;
    }
    roci_insights_styles();

    $n = roci_insights_range();
    $s = roci_insights_stats_summary( $n );
    ?>
    <div class="wrap roci-ins">
        <h1><?php esc_html_e( 'Visitors', 'rocinante' ); ?></h1>
        <nav class="roci-ins-range" aria-label="<?php esc_attr_e( 'Date range', 'rocinante' ); ?>">
            <?php foreach ( array( 7, 30, 90 ) as $r ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=roci-insights-visitors&range=' . $r ) ); ?>"<?php echo $r === $n ? ' aria-current="page"' : ''; ?>>
                    <?php /* translators: %d: number of days. */ echo esc_html( sprintf( __( '%d days', 'rocinante' ), $r ) ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php echo roci_insights_visitor_cards( $s, $n ); // Escaped in the builders. ?>
        <section class="roci-ins-panel" aria-labelledby="roci-ins-chart-h">
            <h2 id="roci-ins-chart-h"><?php /* translators: %d: number of days. */ echo esc_html( sprintf( __( 'Views and visitors, last %d days', 'rocinante' ), $n ) ); ?></h2>
            <?php /* translators: %d: number of days. */ echo roci_insights_chart( $s['days'], sprintf( __( 'Views and visitors, last %d days', 'rocinante' ), $n ) ); // Escaped in the builder. ?>
        </section>
        <section class="roci-ins-panel" aria-labelledby="roci-ins-table-h">
            <h2 id="roci-ins-table-h"><?php esc_html_e( 'By day', 'rocinante' ); ?></h2>
            <p class="roci-ins-card__note"><?php esc_html_e( 'A visitor is counted once per day; the same person on another day counts again. No cookies, no stored IP addresses.', 'rocinante' ); ?></p>
            <table class="widefat striped">
                <thead><tr><th scope="col"><?php esc_html_e( 'Day', 'rocinante' ); ?></th><th scope="col" class="roci-ins-num"><?php esc_html_e( 'Views', 'rocinante' ); ?></th><th scope="col" class="roci-ins-num"><?php esc_html_e( 'Visitors', 'rocinante' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( array_reverse( $s['days'] ) as $d ) : ?>
                    <tr><th scope="row"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $d['day'] . ' 12:00:00' ) ) ); ?></th><td class="roci-ins-num"><?php echo esc_html( number_format_i18n( $d['views'] ) ); ?></td><td class="roci-ins-num"><?php echo esc_html( number_format_i18n( $d['uniques'] ) ); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
    <?php
}


// ============================================================
// DASHBOARD WIDGETS
// ============================================================

/**
 * roci_insights_dashboard()
 *
 * On wp_dashboard_setup, for the capability: "Visitors" (stats; the same id
 * as the widget it replaces, so its place on the dashboard is kept) and
 * "404s this week" (404 log).
 */
function roci_insights_dashboard() {
    if ( ! current_user_can( roci_insights_capability() ) ) {
        return;
    }
    if ( roci_insights_has( 'stats' ) ) {
        wp_add_dashboard_widget( 'roci_stats_widget', __( 'Visitors', 'rocinante' ), 'roci_insights_visitors_widget' );
    }
    if ( roci_insights_has( '404' ) ) {
        wp_add_dashboard_widget( 'roci_404_widget', __( '404s this week', 'rocinante' ), 'roci_insights_404_widget' );
    }
}
add_action( 'wp_dashboard_setup', 'roci_insights_dashboard' );

/**
 * roci_insights_visitors_widget()
 *
 * Today's views and visitors, the 7-day chart, the change vs the 7 days
 * before, and a link to Insights → Visitors.
 */
function roci_insights_visitors_widget() {
    roci_insights_styles();

    $s      = roci_insights_stats_summary( 7 );
    $today  = end( $s['days'] );
    $change = roci_insights_change( $s['views'], $s['prev_views'] );
    ?>
    <div class="roci-ins">
        <div class="roci-ins-cards">
            <?php echo roci_insights_card( __( 'Views today', 'rocinante' ), number_format_i18n( $today['views'] ) ); // Escaped in the builder. ?>
            <?php echo roci_insights_card( __( 'Visitors today', 'rocinante' ), number_format_i18n( $today['uniques'] ) ); // Escaped in the builder. ?>
        </div>
        <?php echo roci_insights_chart( $s['days'], __( 'Views and visitors, last 7 days', 'rocinante' ) ); // Escaped in the builder. ?>
        <p class="roci-ins-card__note">
            <?php
            /* translators: 1: views in the last 7 days. */
            echo esc_html( sprintf( __( '%s views in the last 7 days', 'rocinante' ), number_format_i18n( $s['views'] ) ) );
            ?>
            · <?php esc_html_e( 'vs last 7 days:', 'rocinante' ); ?> <?php echo roci_insights_delta( $change ); // Escaped in the builder. ?>
        </p>
        <p class="roci-ins-foot"><a href="<?php echo esc_url( admin_url( 'admin.php?page=roci-insights-visitors&range=7' ) ); ?>"><?php esc_html_e( 'Open Insights → Visitors', 'rocinante' ); ?></a></p>
    </div>
    <?php
}

/**
 * roci_insights_404_widget()
 *
 * This week's misses: cards, the top 5 URLs, a link to the 404 log. Empty
 * state when nothing was logged.
 */
function roci_insights_404_widget() {
    roci_insights_styles();

    list( $from, $to ) = roci_insights_week();
    $sum = roci_insights_404_summary( $from, $to );
    $top = roci_insights_404_top( $from, $to, 5 );
    ?>
    <div class="roci-ins">
        <?php if ( 0 === $sum['hits'] ) : ?>
            <p class="roci-ins-empty"><?php esc_html_e( 'No missing pages this week.', 'rocinante' ); ?></p>
        <?php else : ?>
            <?php echo roci_insights_404_cards( $sum ); // Escaped in the builder. ?>
            <?php echo roci_insights_top_list( $top ); // Escaped in the builder. ?>
        <?php endif; ?>
        <p class="roci-ins-foot"><a href="<?php echo esc_url( roci_insights_404_url( array( 'date_from' => $from, 'date_to' => $to ) ) ); ?>"><?php esc_html_e( 'Open Insights → 404 log', 'rocinante' ); ?></a></p>
    </div>
    <?php
}
