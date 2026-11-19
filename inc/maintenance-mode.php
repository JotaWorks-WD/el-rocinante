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
 * 1.1.0 (El Rocinante 8.8.0): the page carries the brand. roci_standby_render()
 * gets 'brand' => roci_brand_snapshot() (inc/theme-settings/
 * settings-register.php), and the built-in fallback page shares the standby
 * page's new copy ("We're making a few improvements"), layout (logo → heading
 * → line → contact button → "© year name") and contrast-checked colours
 * (roci_brand_page_colors(), roci_contrast_ratio(); the same algorithm as
 * roci-standby's roci_standby_colors()).
 *
 * File:    inc/maintenance-mode.php
 * Version: 1.1.0
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

    $brand = roci_brand_snapshot();

    if ( function_exists( 'roci_standby_render' ) ) {
        roci_standby_render( array( 'context' => 'maintenance', 'brand' => $brand ) );
        return;
    }

    roci_maintenance_fallback_page( $message, $brand );
}

/**
 * roci_contrast_ratio()
 *
 * Internal. WCAG 2 contrast ratio of two '#rrggbb' colours (1 to 21).
 *
 * @param  string $a
 * @param  string $b
 * @return float
 */
function roci_contrast_ratio( $a, $b ) {
    $lum = static function ( $hex ) {
        $out = array();
        foreach ( array( 1, 3, 5 ) as $i ) {
            $c     = hexdec( substr( $hex, $i, 2 ) ) / 255;
            $out[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
        }
        return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
    };
    $la = $lum( $a );
    $lb = $lum( $b );
    return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * roci_brand_page_colors()
 *
 * Internal. The fallback page's colours from a brand snapshot, every pair
 * contrast-checked. THE SAME ALGORITHM AS roci-standby's
 * roci_standby_colors() (the parent cannot rely on that file being present,
 * and that file cannot call this one): keep both in step.
 *   text on surface ≥ 4.5, else the default text / surface pair;
 *   heading / muted on surface ≥ 4.5, else text (a missing muted is text);
 *   accent_text on accent ≥ 4.5, else #000 / #fff (the higher, if it
 *   passes), else the default button pair;
 *   accent against surface ≥ 3, else a 2px border in the text colour;
 *   hover = accent mixed 10% toward black, kept only while it still passes.
 * No valid colour → the neutral defaults ('brand' false).
 *
 * @param  mixed $brand
 * @return array
 */
function roci_brand_page_colors( $brand ) {
    $default = array( 'surface' => '#ffffff', 'text' => '#1d1d1f', 'muted' => '#555555', 'accent' => '#0b57d0', 'accent_text' => '#ffffff' );
    $hex     = static function ( $v ) {
        $v = is_string( $v ) ? strtolower( trim( $v ) ) : '';
        return preg_match( '/^#[0-9a-f]{6}$/', $v ) ? $v : '';
    };
    $mix     = static function ( $v ) {
        $out = '#';
        foreach ( array( 1, 3, 5 ) as $i ) {
            $out .= sprintf( '%02x', (int) round( hexdec( substr( $v, $i, 2 ) ) * 0.9 ) );
        }
        return $out;
    };
    $b = array();
    foreach ( array( 'surface', 'text', 'heading', 'muted', 'accent', 'accent_text' ) as $k ) {
        $b[ $k ] = ( is_array( $brand ) && isset( $brand[ $k ] ) ) ? $hex( $brand[ $k ] ) : '';
    }
    $in_use = '' !== implode( '', $b );

    if ( ! $in_use ) {
        $c = $default + array( 'heading' => $default['text'] );
    } else {
        $c = array(
            'surface' => '' !== $b['surface'] ? $b['surface'] : $default['surface'],
            'text'    => '' !== $b['text'] ? $b['text'] : $default['text'],
        );
        if ( roci_contrast_ratio( $c['text'], $c['surface'] ) < 4.5 ) {
            $c = array( 'surface' => $default['surface'], 'text' => $default['text'] );
        }
        $c['heading'] = '' !== $b['heading'] ? $b['heading'] : $c['text'];
        if ( roci_contrast_ratio( $c['heading'], $c['surface'] ) < 4.5 ) {
            $c['heading'] = $c['text'];
        }
        $c['muted'] = '' !== $b['muted'] ? $b['muted'] : $c['text'];
        if ( roci_contrast_ratio( $c['muted'], $c['surface'] ) < 4.5 ) {
            $c['muted'] = $c['text'];
        }
        $c['accent']      = '' !== $b['accent'] ? $b['accent'] : $default['accent'];
        $c['accent_text'] = '' !== $b['accent_text'] ? $b['accent_text'] : $default['accent_text'];
        if ( roci_contrast_ratio( $c['accent_text'], $c['accent'] ) < 4.5 ) {
            $white = roci_contrast_ratio( '#ffffff', $c['accent'] );
            $black = roci_contrast_ratio( '#000000', $c['accent'] );
            if ( max( $white, $black ) >= 4.5 ) {
                $c['accent_text'] = $white >= $black ? '#ffffff' : '#000000';
            } else {
                $c['accent']      = $default['accent'];
                $c['accent_text'] = $default['accent_text'];
            }
        }
    }

    $c['border'] = roci_contrast_ratio( $c['accent'], $c['surface'] ) < 3;
    $c['hover']  = $mix( $c['accent'] );
    if ( roci_contrast_ratio( $c['accent_text'], $c['hover'] ) < 4.5 || ( ! $c['border'] && roci_contrast_ratio( $c['hover'], $c['surface'] ) < 3 ) ) {
        $c['hover'] = $c['accent'];
    }
    $c['brand'] = $in_use;

    return $c;
}

/**
 * roci_maintenance_fallback_page()
 *
 * Internal. The parent's own page, used only when roci-standby is not
 * installed. Since 8.8.0 the same copy, layout and colours as the standby
 * page: logo (or name) → "We're making a few improvements" → the line (the
 * custom message, else the default; roci_standby_message filters both) → the
 * contact button (WhatsApp → phone → email, the parent's helpers) → "© year
 * name". Colours from roci_brand_page_colors(); system fonts, no JS, noindex.
 *
 * @param string $message The custom line, or '' for the default.
 * @param array  $brand   roci_brand_snapshot(), or array() for neutral.
 */
function roci_maintenance_fallback_page( $message = '', $brand = array() ) {
    $brand = is_array( $brand ) ? $brand : array();
    $col   = roci_brand_page_colors( $brand );
    $name  = ( isset( $brand['name'] ) && '' !== trim( (string) $brand['name'] ) ) ? trim( (string) $brand['name'] ) : trim( html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) );
    $logo  = ( isset( $brand['logo_url'] ) && preg_match( '#^https?://#i', (string) $brand['logo_url'] ) ) ? (string) $brand['logo_url'] : '';
    $lang  = (string) get_bloginfo( 'language' );
    $lang  = preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/', $lang ) ? $lang : 'en';

    $parts = array(
        'heading' => __( 'We\'re making a few improvements', 'rocinante' ),
        'text'    => '' !== $message ? $message : __( 'We\'ll be back shortly. Thanks for your patience.', 'rocinante' ),
    );
    $filtered = apply_filters( 'roci_standby_message', $parts, 'maintenance' );
    foreach ( array( 'heading', 'text' ) as $key ) {
        if ( is_array( $filtered ) && isset( $filtered[ $key ] ) && is_string( $filtered[ $key ] ) ) {
            $parts[ $key ] = $filtered[ $key ];
        }
    }

    $button = array();
    $email  = sanitize_email( (string) roci_setting( 'business', 'email' ) );
    if ( '' !== roci_whatsapp_url() ) {
        $button = array( roci_whatsapp_url(), __( 'Message us on WhatsApp', 'rocinante' ) );
    } elseif ( '' !== roci_tel_href( roci_phone_number() ) ) {
        $button = array( roci_tel_href( roci_phone_number() ), __( 'Call', 'rocinante' ) . ' ' . roci_phone_number() );
    } elseif ( is_email( $email ) ) {
        $button = array( 'mailto:' . $email, __( 'Email', 'rocinante' ) . ' ' . $email );
    }

    $vars = '--bg:' . $col['surface'] . ';--fg:' . $col['text'] . ';--hd:' . $col['heading'] . ';--mu:' . $col['muted'] . ';--ac:' . $col['accent'] . ';--at:' . $col['accent_text'] . ';--ah:' . $col['hover'];
    $root = $col['brand']
        ? ':root{color-scheme:light;' . $vars . '}'
        : ':root{color-scheme:light dark;' . $vars . '}@media (prefers-color-scheme:dark){:root{--bg:#121212;--fg:#f2f2f2;--hd:#f2f2f2;--mu:#b3b3b3;--ac:#8ab4f8;--at:#121212;--ah:#7ca2df}}';

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
<title><?php echo esc_html( '' !== $name ? $parts['heading'] . ' – ' . $name : $parts['heading'] ); ?></title>
<style>
<?php echo $root; // Built from validated #rrggbb values only. ?>

*{box-sizing:border-box}
html,body{margin:0}
body{display:flex;flex-direction:column;min-height:100vh;background:var(--bg);color:var(--fg);font:1.0625rem/1.6 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;text-align:center}
main{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;max-width:36rem;margin:0 auto;padding:4rem 1rem 2rem}
.logo{display:block;width:auto;max-width:min(18rem,100%);height:auto;margin:0 auto 2rem}
.name{margin:0 0 2rem;font-weight:600;font-size:1.25rem;color:var(--hd)}
h1{margin:0 0 .75rem;font-size:clamp(1.75rem,5vw,2.5rem);line-height:1.2;color:var(--hd)}
p{margin:0 0 1.5rem}
.mu{color:var(--mu)}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:.625rem 1.5rem;border:<?php echo $col['border'] ? '2px solid var(--fg)' : '0'; ?>;border-radius:.5rem;background:var(--ac);color:var(--at);font-weight:600;text-decoration:none}
.btn:hover{background:var(--ah)}
.btn:focus-visible{outline:2px solid var(--fg);outline-offset:3px}
footer{padding:1.5rem 1rem;font-size:.875rem;color:var(--mu)}
footer p{margin:0}
</style>
</head>
<body class="roci-maintenance">
<main>
<?php if ( '' !== $logo ) : ?>
<img class="logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>">
<?php elseif ( '' !== $name ) : ?>
<p class="name"><?php echo esc_html( $name ); ?></p>
<?php endif; ?>
<h1><?php echo esc_html( $parts['heading'] ); ?></h1>
<p class="mu"><?php echo esc_html( $parts['text'] ); ?></p>
<?php if ( $button ) : ?>
<p><a class="btn" href="<?php echo esc_url( $button[0] ); ?>"><?php echo esc_html( $button[1] ); ?></a></p>
<?php endif; ?>
</main>
<footer><p>&copy; <?php echo esc_html( wp_date( 'Y' ) . ( '' !== $name ? ' ' . $name : '' ) ); ?></p></footer>
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
