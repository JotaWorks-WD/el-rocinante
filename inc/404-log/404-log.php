<?php
/**
 * 404 Log — which missing URLs visitors hit, per day (roci-404-log)
 *
 * OPT-IN: loaded only when the child declares
 *
 *     add_theme_support( 'roci-404-log' );
 *
 * (el_rocinante_load_404_log(), functions.php). Without it nothing here runs:
 * no table, no query, no cron event, no menu.
 *
 * WHAT IT RECORDS. One row per URL per day in {prefix}roci_404_log: the
 * request path + query, hits, the last referrer, first / last seen (site
 * time), and kind — '404' for a missing URL, 'suppressed' for an archive
 * inc/archive-suppression.php turned into a 404 (its roci_archive_suppressed
 * action). Logged on template_redirect, front end only; skipped for admin,
 * AJAX, REST, cron and anyone who can manage_options. One query per 404:
 * INSERT … ON DUPLICATE KEY UPDATE on the (day, url_hash) key.
 *
 * ⚠ LITESPEED: every 404 response sends litespeed_control_set_nocache while
 *   this module is on, so a repeat 404 is not answered from the page cache
 *   and still reaches PHP to be counted. No per-site 404 TTL setting needed.
 *
 * SCREEN: Insights → 404 log since 8.10.0 (the menu, summary cards and styles
 * are inc/insights/insights.php's; the old Theme Settings slug roci-404-log
 * redirects there), for roci_insights_capability() — one row per URL over a
 * date range (default the last 7 days), filters, sorting, bulk delete
 * (inc/404-log/class-roci-404-log-table.php).
 *
 * RETENTION: a daily cron event, roci_404_log_prune, deletes rows older than
 * roci_404_log_retention_days (default 90).
 *
 * THE TABLE is created / upgraded with dbDelta on admin_init when the stored
 * roci_404_log_db_version differs from ROCI_404_LOG_DB_VERSION. Until then
 * the logger does nothing (no failed inserts on a site no admin has visited).
 *
 * TURNING IT OFF FOR GOOD. Removing the flag stops everything, but leaves the
 * table, the version option and the scheduled event behind. To remove them
 * (cPanel terminal, in the site's folder):
 *     wp cron event unschedule roci_404_log_prune
 *     wp db query "DROP TABLE IF EXISTS $(wp db prefix)roci_404_log"
 *     wp option delete roci_404_log_db_version
 * An event left scheduled is harmless (no handler is hooked), just untidy.
 *
 * File:    inc/404-log/404-log.php
 * Version: 1.1.0
 * Updated: 2026-11-19
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The table's schema version. Bump with any column change; dbDelta upgrades. */
define( 'ROCI_404_LOG_DB_VERSION', '1.0.0' );


// ============================================================
// THE TABLE
// ============================================================

/**
 * roci_404_log_table()
 *
 * @return string The table name, with the site's prefix.
 */
function roci_404_log_table() {
    global $wpdb;

    return $wpdb->prefix . 'roci_404_log';
}

/**
 * roci_404_log_schema()
 *
 * The CREATE TABLE statement, in dbDelta's format (two spaces after
 * PRIMARY KEY, one key per line).
 *
 * @return string
 */
function roci_404_log_schema() {
    global $wpdb;

    $table   = roci_404_log_table();
    $collate = $wpdb->get_charset_collate();

    return "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  day date NOT NULL,
  url_hash char(32) NOT NULL,
  url varchar(2000) NOT NULL DEFAULT '',
  kind varchar(20) NOT NULL DEFAULT '404',
  hits int(10) unsigned NOT NULL DEFAULT 1,
  referrer varchar(2000) NOT NULL DEFAULT '',
  first_seen datetime NOT NULL,
  last_seen datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY day_url (day,url_hash),
  KEY day (day)
) {$collate};";
}

/**
 * roci_404_log_install()
 *
 * On admin_init: create or upgrade the table when the stored version is not
 * this file's.
 */
function roci_404_log_install() {
    if ( ROCI_404_LOG_DB_VERSION === get_option( 'roci_404_log_db_version' ) ) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( roci_404_log_schema() );

    update_option( 'roci_404_log_db_version', ROCI_404_LOG_DB_VERSION, false );
}
add_action( 'admin_init', 'roci_404_log_install' );


// ============================================================
// LOGGING
// ============================================================

/**
 * roci_404_log_suppressed()
 *
 * Internal. Whether this request's 404 came from archive suppression. Set by
 * the roci_archive_suppressed action, read by the logger.
 *
 * @param  bool|null $set true to mark the request; null to read.
 * @return bool
 */
function roci_404_log_suppressed( $set = null ) {
    static $suppressed = false;

    if ( true === $set ) {
        $suppressed = true;
    }

    return $suppressed;
}

/**
 * roci_404_log_mark_suppressed()
 *
 * On roci_archive_suppressed.
 */
function roci_404_log_mark_suppressed() {
    roci_404_log_suppressed( true );
}
add_action( 'roci_archive_suppressed', 'roci_404_log_mark_suppressed' );

/**
 * roci_404_log_clip()
 *
 * Internal. A request value as stored: unslashed, control characters
 * removed, at most 2000 characters.
 *
 * @param  string $value
 * @return string
 */
function roci_404_log_clip( $value ) {
    $value = preg_replace( '/[\x00-\x1F\x7F]/', '', wp_unslash( (string) $value ) );

    return substr( $value, 0, 2000 );
}

/**
 * roci_404_log_should_record()
 *
 * Internal. False for anything that is not a visitor's front-end request.
 *
 * @return bool
 */
function roci_404_log_should_record() {
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
        return false;
    }
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return false;
    }
    if ( current_user_can( 'manage_options' ) ) {
        return false;
    }

    return ROCI_404_LOG_DB_VERSION === get_option( 'roci_404_log_db_version' );
}

/**
 * roci_404_log_record()
 *
 * On template_redirect. Sends LiteSpeed's no-cache on every 404 (so repeats
 * reach PHP), then counts the hit with one upsert. The referrer kept is the
 * last NON-EMPTY one: a direct visit does not wipe a known source.
 */
function roci_404_log_record() {
    global $wpdb;

    if ( ! is_404() ) {
        return;
    }

    do_action( 'litespeed_control_set_nocache', 'roci 404 log' );

    if ( ! roci_404_log_should_record() ) {
        return;
    }

    $url = roci_404_log_clip( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- stored as data, escaped on output.

    if ( '' === $url ) {
        return;
    }

    $referrer = roci_404_log_clip( isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- stored as data, escaped on output.
    $now      = current_time( 'mysql' );
    $table    = roci_404_log_table();

    $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->prepare(
            "INSERT INTO {$table} (day, url_hash, url, kind, hits, referrer, first_seen, last_seen)
             VALUES (%s, %s, %s, %s, 1, %s, %s, %s)
             ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen),
             referrer = IF(VALUES(referrer) = '', referrer, VALUES(referrer))",
            current_time( 'Y-m-d' ),
            md5( $url ),
            $url,
            roci_404_log_suppressed() ? 'suppressed' : '404',
            $referrer,
            $now,
            $now
        )
    );
}
add_action( 'template_redirect', 'roci_404_log_record' );


// ============================================================
// RETENTION
// ============================================================

/**
 * roci_404_log_schedule()
 *
 * On init: the daily prune, scheduled once.
 */
function roci_404_log_schedule() {
    if ( ! wp_next_scheduled( 'roci_404_log_prune' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'roci_404_log_prune' );
    }
}
add_action( 'init', 'roci_404_log_schedule' );

/**
 * roci_404_log_prune()
 *
 * Deletes rows whose day is older than roci_404_log_retention_days (90).
 */
function roci_404_log_prune() {
    global $wpdb;

    if ( ROCI_404_LOG_DB_VERSION !== get_option( 'roci_404_log_db_version' ) ) {
        return;
    }

    $days   = max( 1, (int) apply_filters( 'roci_404_log_retention_days', 90 ) );
    $cutoff = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . $days . ' days' ) );
    $table  = roci_404_log_table();

    $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE day < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
add_action( 'roci_404_log_prune', 'roci_404_log_prune' );


// ============================================================
// ADMIN SCREEN — Insights → 404 log (8.10.0; the menu is insights.php's)
// ============================================================

/**
 * roci_404_log_load()
 *
 * On the screen's load hook (registered by roci_insights_menu()): loads the
 * list table class and handles "Delete" (nonce + capability), then
 * redirects.
 */
function roci_404_log_load() {
    require_once get_template_directory() . '/inc/404-log/class-roci-404-log-table.php';

    $table  = new Roci_404_Log_Table();
    $action = $table->current_action();

    if ( 'delete' !== $action ) {
        return;
    }

    check_admin_referer( 'bulk-roci_404_urls' );

    if ( ! current_user_can( roci_insights_capability() ) ) {
        wp_die( esc_html__( 'You do not have permission to do that.', 'rocinante' ), 403 );
    }

    $deleted = $table->delete_hashes( isset( $_REQUEST['url_hash'] ) ? (array) wp_unslash( $_REQUEST['url_hash'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated in delete_hashes().

    $back = remove_query_arg( array( 'action', 'action2', 'url_hash', '_wpnonce', '_wp_http_referer', 'deleted' ), wp_get_referer() ? wp_get_referer() : roci_insights_404_url() );

    wp_safe_redirect( add_query_arg( 'deleted', (int) $deleted, $back ) );
    exit;
}

/**
 * roci_404_log_screen()
 *
 * The screen: summary cards for the chosen dates (hits, unique URLs,
 * suppressed, top referrer domain), the filter bar, the table, the
 * retention note.
 */
function roci_404_log_screen() {
    if ( ! current_user_can( roci_insights_capability() ) ) {
        return;
    }
    roci_insights_styles();

    $table = new Roci_404_Log_Table();
    $table->prepare_items();
    $f     = Roci_404_Log_Table::filters();
    ?>
    <div class="wrap roci-ins">
        <h1><?php esc_html_e( '404 log', 'rocinante' ); ?></h1>
        <?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
            <div class="notice notice-success is-dismissible"><p>
                <?php
                /* translators: %d: the number of URLs deleted. */
                echo esc_html( sprintf( _n( '%d URL deleted.', '%d URLs deleted.', absint( $_GET['deleted'] ), 'rocinante' ), absint( $_GET['deleted'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
                ?>
            </p></div>
        <?php endif; ?>
        <p><?php echo esc_html( sprintf( /* translators: %d: days. */ __( 'Missing URLs visitors requested, one row per URL for the dates chosen. Logged-in administrators are not counted. Rows older than %d days are removed automatically.', 'rocinante' ), max( 1, (int) apply_filters( 'roci_404_log_retention_days', 90 ) ) ) ); ?></p>
        <h2 class="screen-reader-text"><?php esc_html_e( 'Summary for the dates chosen', 'rocinante' ); ?></h2>
        <?php echo roci_insights_404_cards( roci_insights_404_summary( $f['from'], $f['to'] ), true ); // Escaped in the builder. ?>
        <form method="get">
            <input type="hidden" name="page" value="roci-insights-404-log">
            <?php $table->display(); ?>
        </form>
    </div>
    <?php
}
