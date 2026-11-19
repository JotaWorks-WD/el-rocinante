<?php
/**
 * Maintenance Mode — a "back soon" page for visitors, the live site for admins
 *
 * ALWAYS LOADED, INERT WHILE OFF. The switch is Theme Settings → SEO →
 * Maintenance mode (roci_seo[maintenance_enabled], '1' | ''), with an optional
 * one-line message (roci_seo[maintenance_message], '' = the standby default).
 * Both keys live in roci_sanitize_seo(), which rebuilds the option from
 * scratch.
 *
 * WHILE ON, on template_redirect priority 0 (front end only), a viewer who
 * cannot bypass gets HTTP 503, Retry-After: 3600, X-Robots-Tag: noindex,
 * no-cache headers and LiteSpeed no-cache, then the page, then exit. Bypass is
 * a capability, roci_maintenance_bypass_capability (default manage_options):
 * administrators keep seeing and working on the live site.
 *
 * THE PAGE, first that exists:
 *   (a) roci_standby_render(), if already defined;
 *   (b) the roci-standby theme's renderer, get_theme_root()
 *       . '/roci-standby/inc/standby-page.php' (the theme need not be
 *       active, only installed);
 *   (c) roci_maintenance_fallback_page(), the parent's own minimal page.
 * Always with array( 'context' => 'maintenance' ). A custom message reaches
 * (a) and (b) through the roci_standby_message filter, added for this request
 * only, and (c) directly.
 *
 * UNTOUCHED WHILE ON: wp-admin, wp-login.php, the REST API, admin-ajax and
 * cron (none of them reaches template_redirect; each is also guarded).
 * /llms.txt and the IndexNow key file still serve: they answer on
 * parse_request, before template_redirect. Everything else on the front end,
 * feeds, sitemaps and robots.txt included, gets the 503 page.
 *
 * WHILE ON, for administrators: an admin-bar node "Maintenance mode is ON"
 * (red, from a tiny inline style printed only when the node shows) on the
 * front end and in wp-admin, and a notice without a dismiss button on the
 * Dashboard and Theme Settings. IndexNow skips every ping, logged
 * 'maintenance'.
 *
 * Turning it on or off purges LiteSpeed's whole page cache
 * (litespeed_purge_all), so no cached page outlives the switch either way.
 *
 * File:    inc/maintenance-mode.php
 * Version: 1.0.0
 * Updated: 2026-11-19
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// THE SETTING
// ============================================================

/**
 * roci_maintenance_flag()
 *
 * Internal. The on/off value of a stored or submitted roci_seo array.
 *
 * @param  mixed $seo A roci_seo value.
 * @return bool
 */
function roci_maintenance_flag( $seo ) {
    return is_array( $seo ) && isset( $seo['maintenance_enabled'] ) && '1' === (string) $seo['maintenance_enabled'];
}

/**
 * roci_maintenance_mode_on()
 *
 * @return bool True while Theme Settings → SEO → Maintenance mode is on.
 */
function roci_maintenance_mode_on() {
    return roci_maintenance_flag( get_option( 'roci_seo', array() ) );
}

/**
 * roci_maintenance_message()
 *
 * Internal. The custom one-line message, or '' for the standby default.
 *
 * @return string
 */
function roci_maintenance_message() {
    $seo = get_option( 'roci_seo', array() );

    return ( is_array( $seo ) && isset( $seo['maintenance_message'] ) ) ? trim( (string) $seo['maintenance_message'] ) : '';
}

/**
 * roci_maintenance_can_bypass()
 *
 * Internal. Whether the current user sees the live site while it is on.
 * The capability is roci_maintenance_bypass_capability (default
 * manage_options); a non-string or empty return falls back to the default.
 *
 * @return bool
 */
function roci_maintenance_can_bypass() {
    $cap = apply_filters( 'roci_maintenance_bypass_capability', 'manage_options' );
    $cap = ( is_string( $cap ) && '' !== $cap ) ? $cap : 'manage_options';

    return is_user_logged_in() && current_user_can( $cap );
}

/**
 * roci_maintenance_purge_on_change()
 *
 * On update_option_roci_seo: purge the whole page cache when the on/off
 * value changed, and only then.
 *
 * @param mixed $old The stored value before the save.
 * @param mixed $new The saved value.
 */
function roci_maintenance_purge_on_change( $old, $new ) {
    if ( roci_maintenance_flag( $old ) !== roci_maintenance_flag( $new ) ) {
        do_action( 'litespeed_purge_all' );
    }
}
add_action( 'update_option_roci_seo', 'roci_maintenance_purge_on_change', 10, 2 );

/**
 * roci_maintenance_purge_on_add()
 *
 * On add_option_roci_seo (the tab's first save): the option did not exist,
 * so it was off; purge when it is saved on.
 *
 * @param string $option The option name.
 * @param mixed  $value  The saved value.
 */
function roci_maintenance_purge_on_add( $option, $value ) {
    roci_maintenance_purge_on_change( array(), $value );
}
add_action( 'add_option_roci_seo', 'roci_maintenance_purge_on_add', 10, 2 );


// ============================================================
// THE FRONT END
// ============================================================

/**
 * roci_maintenance_intercept()
 *
 * On template_redirect, priority 0. Inert while off or for a bypass user.
 */
function roci_maintenance_intercept() {
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }
    if ( ! roci_maintenance_mode_on() || roci_maintenance_can_bypass() ) {
        return;
    }

    status_header( 503 );
    header( 'Retry-After: 3600' );
    header( 'X-Robots-Tag: noindex' );
    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'roci maintenance' );

    roci_maintenance_render();
    exit;
}
add_action( 'template_redirect', 'roci_maintenance_intercept', 0 );

/**
 * roci_maintenance_standby_file()
 *
 * Internal. The roci-standby theme's renderer file, installed or not.
 *
 * @return string
 */
function roci_maintenance_standby_file() {
    return get_theme_root() . '/roci-standby/inc/standby-page.php';
}

/**
 * roci_maintenance_render()
 *
 * Prints the page: the standby renderer when it is defined or installed,
 * else the parent's fallback. Does not exit.
 */
function roci_maintenance_render() {
    $message = roci_maintenance_message();

    if ( '' !== $message ) {
        add_filter( 'roci_standby_message', function ( $parts ) use ( $message ) {
            $parts         = is_array( $parts ) ? $parts : array();
            $parts['text'] = $message;
            return $parts;
        } );
    }

    if ( ! function_exists( 'roci_standby_render' ) && is_readable( roci_maintenance_standby_file() ) ) {
        require_once roci_maintenance_standby_file();
    }

    if ( function_exists( 'roci_standby_render' ) ) {
        roci_standby_render( array( 'context' => 'maintenance' ) );
        return;
    }

    roci_maintenance_fallback_page( $message );
}

/**
 * roci_maintenance_fallback_page()
 *
 * Internal. The parent's own page, used only when roci-standby is not
 * installed: site name, "We'll be right back", the line, noindex, a tiny
 * inline style (WCAG AA in light and dark). No logo, no JS.
 *
 * @param string $message The custom line, or '' for the default.
 */
function roci_maintenance_fallback_page( $message = '' ) {
    $name = trim( html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) );
    $lang = (string) get_bloginfo( 'language' );
    $lang = preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/', $lang ) ? $lang : 'en';
    $head = __( 'We\'ll be right back', 'rocinante' );
    $line = '' !== $message ? $message : __( 'We are doing some scheduled maintenance. Please check back soon.', 'rocinante' );

    if ( ! headers_sent() ) {
        header( 'Content-Type: text/html; charset=utf-8' );
    }
    ?>
<!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( '' !== $name ? $head . ' – ' . $name : $head ); ?></title>
<style>
:root{color-scheme:light dark;--bg:#fff;--fg:#1d1d1f;--mu:#555}
@media (prefers-color-scheme:dark){:root{--bg:#121212;--fg:#f2f2f2;--mu:#b3b3b3}}
body{margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:4rem 1rem;box-sizing:border-box;background:var(--bg);color:var(--fg);font:1.0625rem/1.6 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;text-align:center}
main{max-width:34rem}
.name{margin:0 0 2rem;font-weight:600;font-size:1.25rem}
h1{margin:0 0 .75rem;font-size:clamp(1.75rem,5vw,2.5rem);line-height:1.2}
p{margin:0 0 1rem}
.mu{color:var(--mu)}
</style>
</head>
<body class="roci-maintenance">
<main>
<?php if ( '' !== $name ) : ?>
<p class="name"><?php echo esc_html( $name ); ?></p>
<?php endif; ?>
<h1><?php echo esc_html( $head ); ?></h1>
<p class="mu"><?php echo esc_html( $line ); ?></p>
</main>
</body>
</html>
    <?php
}


// ============================================================
// ADMIN SIGNALS (while on)
// ============================================================

/**
 * roci_maintenance_settings_url()
 *
 * Internal. Theme Settings → SEO, at the Maintenance mode section.
 *
 * @return string
 */
function roci_maintenance_settings_url() {
    return admin_url( 'admin.php?page=roci-theme-settings&tab=seo#roci-maintenance' );
}

/**
 * roci_maintenance_admin_bar_shown()
 *
 * Internal. Records (true) and reports whether this request added the node,
 * so its style prints only then.
 *
 * @param  bool|null $set True to record; null to read.
 * @return bool
 */
function roci_maintenance_admin_bar_shown( $set = null ) {
    static $shown = false;

    if ( null !== $set ) {
        $shown = (bool) $set;
    }

    return $shown;
}

/**
 * roci_maintenance_admin_bar_node()
 *
 * On admin_bar_menu, priority 100: the "Maintenance mode is ON" node, front
 * end and wp-admin, for users who bypass it.
 *
 * @param WP_Admin_Bar $bar
 */
function roci_maintenance_admin_bar_node( $bar ) {
    if ( ! roci_maintenance_mode_on() || ! roci_maintenance_can_bypass() ) {
        return;
    }

    $bar->add_node( array(
        'id'     => 'roci-maintenance',
        'parent' => 'top-secondary',
        'title'  => esc_html__( 'Maintenance mode is ON', 'rocinante' ),
        'href'   => roci_maintenance_settings_url(),
    ) );

    roci_maintenance_admin_bar_shown( true );
}
add_action( 'admin_bar_menu', 'roci_maintenance_admin_bar_node', 100 );

/**
 * roci_maintenance_admin_bar_style()
 *
 * On wp_after_admin_bar_render: the node's red, only when it was added.
 * White on #b32d2e is about 6.3:1; on the hover #8a2424, about 8.9:1.
 */
function roci_maintenance_admin_bar_style() {
    if ( ! roci_maintenance_admin_bar_shown() ) {
        return;
    }

    echo '<style id="roci-maintenance-bar">'
        . '#wpadminbar #wp-admin-bar-roci-maintenance>.ab-item{background:#b32d2e;color:#fff;font-weight:600}'
        . '#wpadminbar #wp-admin-bar-roci-maintenance>.ab-item:hover,#wpadminbar #wp-admin-bar-roci-maintenance>.ab-item:focus{background:#8a2424;color:#fff}'
        . '</style>';
}
add_action( 'wp_after_admin_bar_render', 'roci_maintenance_admin_bar_style' );

/**
 * roci_maintenance_admin_notice()
 *
 * On admin_notices: a warning without a dismiss button, on the Dashboard and
 * the Theme Settings screen only, while on. Says which page visitors get.
 */
function roci_maintenance_admin_notice() {
    if ( ! roci_maintenance_mode_on() || ! roci_maintenance_can_bypass() || ! function_exists( 'get_current_screen' ) ) {
        return;
    }

    $screen = get_current_screen();

    if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'toplevel_page_roci-theme-settings' ), true ) ) {
        return;
    }

    $standby = function_exists( 'roci_standby_render' ) || is_readable( roci_maintenance_standby_file() );
    ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e( 'Maintenance mode is ON.', 'rocinante' ); ?></strong>
            <?php esc_html_e( 'Visitors see a "back soon" page (HTTP 503); you see the live site.', 'rocinante' ); ?>
            <a href="<?php echo esc_url( roci_maintenance_settings_url() ); ?>"><?php esc_html_e( 'Turn it off in Theme Settings → SEO when you are done.', 'rocinante' ); ?></a>
        </p>
        <?php if ( ! $standby ) : ?>
            <p><?php esc_html_e( 'The roci-standby theme is not installed, so visitors get the plain built-in page (no logo or contact line).', 'rocinante' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
add_action( 'admin_notices', 'roci_maintenance_admin_notice' );
