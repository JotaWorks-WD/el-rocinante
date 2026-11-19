<?php
/**
 * Maintenance Mode — a "back soon" page for visitors, the live site for admins
 *
 * ALWAYS LOADED, INERT WHILE OFF. The switch is Theme Settings → Maintenance
 * → Maintenance mode (since 8.9.0 its own option, roci_maintenance[enabled],
 * '1' | ''), with an optional one-line message (roci_maintenance[message],
 * '' = the standby default). Both keys live in roci_sanitize_maintenance(),
 * which rebuilds the option from scratch. 8.5.0–8.8.x kept them in roci_seo;
 * roci_maintenance_migrate() moves them once.
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
 * 1.2.0 (El Rocinante 8.9.0): its own Theme Settings tab, "Maintenance"
 * (roci_settings_tabs + roci_settings_tab_maintenance, after SEO), option
 * roci_maintenance (enabled, message), the purge hooks on that option, the
 * one-time move from roci_seo (roci_maintenance_migrate()), and the
 * read-only "Standby status" rows (roci_standby_status()). The fallback page's
 * logo takes the snapshot's logo_w / logo_h.
 *
 * 1.3.0 (El Rocinante 8.9.1): the status panel's one-click buttons —
 * "Install roci-standby" (core Theme_Upgrader, GitHub main.zip renamed to
 * roci-standby for this install only, never activated) and "Install / Update
 * drop-in" (WP_Filesystem copy via a temp file, Version read back) — on
 * admin-post, manage_options + nonce, result notice after the redirect;
 * hidden with a note under DISALLOW_FILE_MODS or a non-direct file system.
 * The Default theme row tells "not in wp-config.php" (roci_wp_config_defines())
 * from "set to something else".
 *
 * 1.3.1 (El Rocinante 8.11.1): every Standby status row carries 'action' and
 * 'button' ('' when the row has no install button). The Default theme, Brand
 * snapshot and Active theme rows had neither, so the tab's wp_list_pluck()
 * raised "Undefined array key "action"" (twice on a normal site). Output
 * unchanged.
 *
 * File:    inc/maintenance-mode.php
 * Version: 1.3.1
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
 * Internal. The on/off value of a stored or submitted roci_maintenance array
 * (1.2.0, El Rocinante 8.9.0; was a roci_seo array).
 *
 * @param  mixed $value A roci_maintenance value.
 * @return bool
 */
function roci_maintenance_flag( $value ) {
    return is_array( $value ) && isset( $value['enabled'] ) && '1' === (string) $value['enabled'];
}

/**
 * roci_maintenance_mode_on()
 *
 * @return bool True while Theme Settings → Maintenance → Maintenance mode is on.
 */
function roci_maintenance_mode_on() {
    return roci_maintenance_flag( get_option( 'roci_maintenance', array() ) );
}

/**
 * roci_maintenance_message()
 *
 * Internal. The custom one-line message, or '' for the standby default.
 *
 * @return string
 */
function roci_maintenance_message() {
    $value = get_option( 'roci_maintenance', array() );

    return ( is_array( $value ) && isset( $value['message'] ) ) ? trim( (string) $value['message'] ) : '';
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
 * On update_option_roci_maintenance: purge the whole page cache when the
 * on/off value changed, and only then.
 *
 * @param mixed $old The stored value before the save.
 * @param mixed $new The saved value.
 */
function roci_maintenance_purge_on_change( $old, $new ) {
    if ( roci_maintenance_flag( $old ) !== roci_maintenance_flag( $new ) ) {
        do_action( 'litespeed_purge_all' );
    }
}
add_action( 'update_option_roci_maintenance', 'roci_maintenance_purge_on_change', 10, 2 );

/**
 * roci_maintenance_purge_on_add()
 *
 * On add_option_roci_maintenance (the tab's first save): the option did not
 * exist, so it was off; purge when it is saved on.
 *
 * @param string $option The option name.
 * @param mixed  $value  The saved value.
 */
function roci_maintenance_purge_on_add( $option, $value ) {
    roci_maintenance_purge_on_change( array(), $value );
}
add_action( 'add_option_roci_maintenance', 'roci_maintenance_purge_on_add', 10, 2 );

/**
 * roci_maintenance_migrate()
 *
 * One time, 8.5.0–8.8.x → 8.9.0. On admin_init PRIORITY 1: before
 * roci_register_settings() (priority 10) attaches the sanitizers, so neither
 * write below is re-sanitized (re-running roci_sanitize_seo() over a stored
 * row would turn seo_preview '0' into '1'). If roci_maintenance does not exist
 * yet and roci_seo still holds maintenance_enabled or maintenance_message,
 * copy them into roci_maintenance (enabled, message), then remove them from
 * roci_seo. Every other roci_seo key is untouched. No cache purge: the
 * purge-on-change hooks and the llms.txt purge are unhooked for the two
 * writes (the switch's value does not change). Runs at most once: after it,
 * roci_maintenance exists.
 */
function roci_maintenance_migrate() {
    if ( false !== get_option( 'roci_maintenance', false ) ) {
        return;
    }

    $seo = get_option( 'roci_seo', array() );

    if ( ! is_array( $seo ) || ! ( array_key_exists( 'maintenance_enabled', $seo ) || array_key_exists( 'maintenance_message', $seo ) ) ) {
        return;
    }

    $value = array(
        'enabled' => ( isset( $seo['maintenance_enabled'] ) && '1' === (string) $seo['maintenance_enabled'] ) ? '1' : '',
        'message' => isset( $seo['maintenance_message'] ) ? sanitize_text_field( $seo['maintenance_message'] ) : '',
    );
    unset( $seo['maintenance_enabled'], $seo['maintenance_message'] );

    $quiet = array(
        array( 'add_option_roci_maintenance', 'roci_maintenance_purge_on_add', 10, 2 ),
        array( 'update_option_roci_seo', 'roci_llms_txt_purge', 10, 1 ),
        array( 'add_option_roci_seo', 'roci_llms_txt_purge', 10, 1 ),
    );
    foreach ( $quiet as $hook ) {
        remove_action( $hook[0], $hook[1], $hook[2] );
    }

    add_option( 'roci_maintenance', $value, '', false );
    update_option( 'roci_seo', $seo );

    foreach ( $quiet as $hook ) {
        if ( function_exists( $hook[1] ) ) {
            add_action( $hook[0], $hook[1], $hook[2], $hook[3] );
        }
    }
}
add_action( 'admin_init', 'roci_maintenance_migrate', 1 );


// ============================================================
// THE MAINTENANCE TAB (8.9.0) — through roci_settings_tabs
// ============================================================

/**
 * roci_maintenance_settings_tab()
 *
 * On roci_settings_tabs: the "Maintenance" tab, right after SEO (appended if
 * there is no SEO tab). The panel is roci_maintenance_settings_tab_panel()
 * on roci_settings_tab_maintenance; the setting is registered in
 * roci_register_settings(). The filter's two-part contract (CONVENTIONS).
 *
 * @param  array $tabs slug => label.
 * @return array
 */
function roci_maintenance_settings_tab( $tabs ) {
    $tabs = is_array( $tabs ) ? $tabs : array();
    $out  = array();

    foreach ( $tabs as $slug => $label ) {
        $out[ $slug ] = $label;
        if ( 'seo' === $slug ) {
            $out['maintenance'] = __( 'Maintenance', 'rocinante' );
        }
    }
    if ( ! isset( $out['maintenance'] ) ) {
        $out['maintenance'] = __( 'Maintenance', 'rocinante' );
    }

    return $out;
}
add_filter( 'roci_settings_tabs', 'roci_maintenance_settings_tab' );

/**
 * roci_maintenance_settings_tab_panel()
 *
 * On roci_settings_tab_maintenance: the tab's panel, inside the Theme
 * Settings form (tabs/tab-maintenance.php).
 */
function roci_maintenance_settings_tab_panel() {
    include get_template_directory() . '/inc/theme-settings/tabs/tab-maintenance.php';
}
add_action( 'roci_settings_tab_maintenance', 'roci_maintenance_settings_tab_panel' );

/**
 * roci_standby_status()
 *
 * Internal. The Maintenance tab's read-only "Standby status" rows. Each row:
 * label, state ('ok' | 'missing' | 'outdated' | 'warn'), detail (plain text),
 * fix ('' when nothing to do), action + button (the install button's
 * admin-post action and label; '' when the row has none — EVERY row carries
 * both keys since 8.11.1), plus 'swatches' / 'logo' on the snapshot row.
 *
 * @return array[]
 */
function roci_standby_status() {
    $rows  = array();
    $theme = wp_get_theme( 'roci-standby' );
    $has   = $theme->exists();
    $ver   = $has ? (string) $theme->get( 'Version' ) : '';

    $rows[] = array(
        'label'  => __( 'roci-standby theme', 'rocinante' ),
        'state'  => $has ? 'ok' : 'missing',
        /* translators: %s: version number. */
        'detail' => $has ? sprintf( __( 'Installed, version %s.', 'rocinante' ), $ver ) : __( 'Not installed.', 'rocinante' ),
        'fix'    => $has ? '' : __( 'Install roci-standby with Git Updater (don\'t activate it).', 'rocinante' ),
        'action' => $has ? '' : 'roci_install_standby',
        'button' => $has ? '' : __( 'Install roci-standby', 'rocinante' ),
    );

    // 8.9.1: core always defines WP_DEFAULT_THEME (its own default when
    // wp-config.php does not), so "not set" is read from wp-config.php itself.
    $default   = defined( 'WP_DEFAULT_THEME' ) ? (string) WP_DEFAULT_THEME : '';
    $in_config = roci_wp_config_defines( 'WP_DEFAULT_THEME' );
    if ( 'roci-standby' === $default ) {
        /* translators: %s: the WP_DEFAULT_THEME value. */
        $d_detail = sprintf( __( 'Set to %s.', 'rocinante' ), $default );
    } elseif ( false === $in_config || '' === $default ) {
        $d_detail = __( 'Not set (WordPress falls back to its built-in default).', 'rocinante' );
    } else {
        /* translators: %s: the WP_DEFAULT_THEME value. */
        $d_detail = sprintf( __( 'Set to %s.', 'rocinante' ), $default );
    }
    $rows[] = array(
        'label'  => __( 'Default theme (WP_DEFAULT_THEME)', 'rocinante' ),
        'state'  => 'roci-standby' === $default ? 'ok' : 'missing',
        'detail' => $d_detail,
        'fix'    => 'roci-standby' === $default ? '' : __( 'Add define( \'WP_DEFAULT_THEME\', \'roci-standby\' ); to wp-config.php.', 'rocinante' ),
        'action' => '',
        'button' => '',
    );

    $dropin   = trailingslashit( WP_CONTENT_DIR ) . 'php-error.php';
    $d_ver    = is_readable( $dropin ) ? (string) get_file_data( $dropin, array( 'Version' => 'Version' ) )['Version'] : '';
    $t_file   = $has ? trailingslashit( $theme->get_stylesheet_directory() ) . 'php-error.php' : '';
    $t_ver    = ( '' !== $t_file && is_readable( $t_file ) ) ? (string) get_file_data( $t_file, array( 'Version' => 'Version' ) )['Version'] : '';
    $d_state  = ! is_readable( $dropin ) ? 'missing' : ( ( '' !== $t_ver && $d_ver !== $t_ver ) ? 'outdated' : 'ok' );
    $rows[]   = array(
        'label'  => __( 'Fatal-error page (wp-content/php-error.php)', 'rocinante' ),
        'state'  => $d_state,
        'detail' => 'missing' === $d_state
            ? __( 'Not in wp-content/.', 'rocinante' )
            /* translators: 1: the drop-in's version, 2: the theme's copy's version. */
            : sprintf( __( 'Version %1$s (theme copy: %2$s).', 'rocinante' ), '' !== $d_ver ? $d_ver : '?', '' !== $t_ver ? $t_ver : '?' ),
        'fix'    => 'ok' === $d_state ? '' : __( 'Copy php-error.php from the roci-standby theme folder into wp-content/.', 'rocinante' ),
        'action' => ( 'ok' !== $d_state && $has ) ? 'roci_install_dropin' : '',
        'button' => ( 'ok' !== $d_state && $has ) ? ( 'missing' === $d_state ? __( 'Install drop-in', 'rocinante' ) : __( 'Update drop-in', 'rocinante' ) ) : '',
    );

    $snap   = get_option( 'roci_brand_snapshot', false );
    $is_arr = is_array( $snap );
    $swatch = array();
    if ( $is_arr ) {
        foreach ( array( 'surface', 'text', 'heading', 'muted', 'accent' ) as $key ) {
            if ( isset( $snap[ $key ] ) && preg_match( '/^#[0-9a-f]{6}$/i', (string) $snap[ $key ] ) ) {
                $swatch[ $key ] = strtolower( (string) $snap[ $key ] );
            }
        }
    }
    $rows[] = array(
        'label'    => __( 'Brand snapshot', 'rocinante' ),
        'state'    => $is_arr ? 'ok' : 'missing',
        'detail'   => $is_arr ? __( 'Saved. The standby page uses these colours and logo.', 'rocinante' ) : __( 'Not saved yet.', 'rocinante' ),
        'fix'      => $is_arr ? '' : __( 'Open any wp-admin screen once to save it.', 'rocinante' ),
        'action'   => '',
        'button'   => '',
        'swatches' => $swatch,
        'logo'     => ( $is_arr && isset( $snap['logo_url'] ) && preg_match( '#^https?://#i', (string) $snap['logo_url'] ) ) ? (string) $snap['logo_url'] : '',
    );

    if ( 'roci-standby' === get_stylesheet() ) {
        $rows[] = array(
            'label'  => __( 'Active theme', 'rocinante' ),
            'state'  => 'warn',
            'detail' => __( 'Standby theme is active: visitors see the standby page.', 'rocinante' ),
            'fix'    => '',
            'action' => '',
            'button' => '',
        );
    }

    return $rows;
}

/**
 * roci_wp_config_defines()
 *
 * Internal (8.9.1). Whether wp-config.php itself defines $name: its source
 * with comments stripped (so a commented-out line does not count). Core's
 * search order: ABSPATH, then one level up when that is not another install.
 * null when the file cannot be read.
 *
 * @param  string $name
 * @return bool|null
 */
function roci_wp_config_defines( $name ) {
    $file = '';
    if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
        $file = ABSPATH . 'wp-config.php';
    } elseif ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
        $file = dirname( ABSPATH ) . '/wp-config.php';
    }
    if ( '' === $file || ! is_readable( $file ) ) {
        return null;
    }

    $code = '';
    foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
        if ( is_array( $token ) ) {
            if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
                continue;
            }
            $code .= $token[1];
        } else {
            $code .= $token;
        }
    }

    return (bool) preg_match( '/\bdefine\s*\(\s*[\'"]' . preg_quote( $name, '/' ) . '[\'"]/i', $code );
}


// ============================================================
// ONE-CLICK INSTALLS (8.9.1) — the status panel's buttons
// ============================================================

/**
 * roci_standby_zip_url()
 *
 * Internal. The roci-standby main-branch zip on GitHub (public repo).
 *
 * @return string
 */
function roci_standby_zip_url() {
    return 'https://github.com/JotaWorks-WD/roci-standby/archive/refs/heads/main.zip';
}

/**
 * roci_standby_can_modify_files()
 *
 * Internal. true when the buttons may run: file changes allowed
 * (DISALLOW_FILE_MODS off, through core's wp_is_file_mod_allowed()) and
 * WP_Filesystem would write directly (no FTP credentials). Else a WP_Error
 * whose message the panel shows beside the manual steps.
 *
 * @return true|WP_Error
 */
function roci_standby_can_modify_files() {
    $allowed = function_exists( 'wp_is_file_mod_allowed' ) ? wp_is_file_mod_allowed( 'roci_standby' ) : ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
    if ( ! $allowed ) {
        return new WP_Error( 'roci_file_mods', __( 'File changes are turned off on this site (DISALLOW_FILE_MODS).', 'rocinante' ) );
    }
    if ( ! function_exists( 'get_filesystem_method' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( 'direct' !== get_filesystem_method( array(), WP_CONTENT_DIR ) ) {
        return new WP_Error( 'roci_fs_credentials', __( 'This server needs FTP details for WordPress to change files.', 'rocinante' ) );
    }
    return true;
}

/**
 * roci_standby_rename_source()
 *
 * Internal. upgrader_source_selection for OUR install only: the GitHub zip
 * extracts as roci-standby-main, the theme must live in roci-standby. Any
 * other upgrader ($upgrader_obj !== $own) gets $source back untouched.
 *
 * @param  string|WP_Error $source
 * @param  string          $remote_source
 * @param  object          $upgrader_obj
 * @param  object          $own The Theme_Upgrader this install created.
 * @return string|WP_Error
 */
function roci_standby_rename_source( $source, $remote_source, $upgrader_obj, $own ) {
    if ( $upgrader_obj !== $own || is_wp_error( $source ) ) {
        return $source;
    }

    global $wp_filesystem;
    $want = trailingslashit( $remote_source ) . 'roci-standby/';

    if ( trailingslashit( $source ) === $want ) {
        return $source;
    }
    if ( ! $wp_filesystem || ! $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $want ), true ) ) {
        return new WP_Error( 'roci_standby_rename', __( 'Could not rename the downloaded folder to roci-standby.', 'rocinante' ) );
    }
    return $want;
}

/**
 * roci_standby_install_theme()
 *
 * Internal. Installs roci-standby from roci_standby_zip_url() with core's
 * Theme_Upgrader (renamed to roci-standby by roci_standby_rename_source(),
 * hooked for this install only). NEVER activates it; Git Updater keeps it
 * updated from then on (its GitHub Theme URI header). On any failure a
 * roci-standby folder this call created is removed, so nothing half-done is
 * left.
 *
 * @return true|WP_Error
 */
function roci_standby_install_theme() {
    $can = roci_standby_can_modify_files();
    if ( is_wp_error( $can ) ) {
        return $can;
    }
    if ( wp_get_theme( 'roci-standby' )->exists() ) {
        return new WP_Error( 'roci_standby_exists', __( 'roci-standby is already installed.', 'rocinante' ) );
    }
    if ( ! class_exists( 'Theme_Upgrader' ) ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    $dest     = trailingslashit( get_theme_root() ) . 'roci-standby';
    $skin     = new WP_Ajax_Upgrader_Skin();
    $upgrader = new Theme_Upgrader( $skin );
    $rename   = function ( $source, $remote_source, $upgrader_obj = null ) use ( $upgrader ) {
        return roci_standby_rename_source( $source, $remote_source, $upgrader_obj, $upgrader );
    };

    add_filter( 'upgrader_source_selection', $rename, 10, 3 );
    $result = $upgrader->install( roci_standby_zip_url() );
    remove_filter( 'upgrader_source_selection', $rename, 10 );

    if ( true !== $result ) {
        global $wp_filesystem;
        if ( $wp_filesystem && $wp_filesystem->is_dir( $dest ) ) {
            $wp_filesystem->delete( $dest, true );
        }
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $errors = method_exists( $skin, 'get_errors' ) ? $skin->get_errors() : null;
        return ( $errors instanceof WP_Error && $errors->has_errors() ) ? $errors : new WP_Error( 'roci_standby_install', __( 'The install did not complete.', 'rocinante' ) );
    }

    if ( function_exists( 'wp_clean_themes_cache' ) ) {
        wp_clean_themes_cache();
    }
    return true;
}

/**
 * roci_standby_install_dropin()
 *
 * Internal. Copies themes/roci-standby/php-error.php to
 * WP_CONTENT_DIR/php-error.php with WP_Filesystem: first to a temporary file
 * beside it (checked), then moved over the old one, so a failed copy leaves
 * the old drop-in in place. Verified by reading the Version header back.
 *
 * @return true|WP_Error True, or the error.
 */
function roci_standby_install_dropin() {
    $can = roci_standby_can_modify_files();
    if ( is_wp_error( $can ) ) {
        return $can;
    }

    $theme = wp_get_theme( 'roci-standby' );
    if ( ! $theme->exists() ) {
        return new WP_Error( 'roci_standby_missing', __( 'Install roci-standby first.', 'rocinante' ) );
    }

    $src = trailingslashit( $theme->get_stylesheet_directory() ) . 'php-error.php';
    if ( ! is_readable( $src ) ) {
        return new WP_Error( 'roci_dropin_source', __( 'The roci-standby theme has no php-error.php.', 'rocinante' ) );
    }
    $want = (string) get_file_data( $src, array( 'Version' => 'Version' ) )['Version'];

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! WP_Filesystem() ) {
        return new WP_Error( 'roci_fs', __( 'Could not access the file system.', 'rocinante' ) );
    }

    global $wp_filesystem;
    $dest = trailingslashit( WP_CONTENT_DIR ) . 'php-error.php';
    $tmp  = $dest . '.roci-new';

    if ( ! $wp_filesystem->copy( $src, $tmp, true, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 ) ) {
        $wp_filesystem->delete( $tmp );
        return new WP_Error( 'roci_dropin_copy', __( 'Could not copy php-error.php into wp-content/. The old file is unchanged.', 'rocinante' ) );
    }
    if ( (string) get_file_data( $tmp, array( 'Version' => 'Version' ) )['Version'] !== $want ) {
        $wp_filesystem->delete( $tmp );
        return new WP_Error( 'roci_dropin_verify', __( 'The copied file did not match the theme\'s. The old file is unchanged.', 'rocinante' ) );
    }
    if ( ! $wp_filesystem->move( $tmp, $dest, true ) ) {
        $wp_filesystem->delete( $tmp );
        return new WP_Error( 'roci_dropin_move', __( 'Could not replace wp-content/php-error.php. The old file is unchanged.', 'rocinante' ) );
    }

    clearstatcache( true, $dest );
    if ( function_exists( 'opcache_invalidate' ) ) {
        @opcache_invalidate( $dest, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- optional.
    }
    $got = (string) get_file_data( $dest, array( 'Version' => 'Version' ) )['Version'];
    if ( $got !== $want ) {
        /* translators: 1: version read back, 2: expected version. */
        return new WP_Error( 'roci_dropin_readback', sprintf( __( 'wp-content/php-error.php reads version %1$s, expected %2$s.', 'rocinante' ), $got, $want ) );
    }
    return true;
}

/**
 * roci_standby_notice_set() / roci_standby_notice_take()
 *
 * Internal. The result notice, kept per user for one redirect (60s).
 */
function roci_standby_notice_set( $type, $message ) {
    set_transient( 'roci_standby_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => (string) $message ), 60 );
}
function roci_standby_notice_take() {
    $key    = 'roci_standby_notice_' . get_current_user_id();
    $notice = get_transient( $key );
    if ( $notice ) {
        delete_transient( $key );
    }
    return is_array( $notice ) ? $notice : null;
}

/**
 * roci_standby_action_url()
 *
 * Internal. The nonce'd admin-post.php link for a button.
 *
 * @param  string $action roci_install_standby | roci_install_dropin.
 * @return string
 */
function roci_standby_action_url( $action ) {
    return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action );
}

/**
 * roci_standby_install_handler() / roci_standby_dropin_handler()
 *
 * admin-post handlers: manage_options only, nonce-checked, then the result
 * notice and a redirect back to the Maintenance tab.
 */
function roci_standby_install_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'rocinante' ), 403 );
    }
    check_admin_referer( 'roci_install_standby' );

    $result = roci_standby_install_theme();
    if ( is_wp_error( $result ) ) {
        roci_standby_notice_set( 'error', $result->get_error_message() );
    } else {
        roci_standby_notice_set( 'success', __( 'roci-standby is installed (not activated). Git Updater keeps it updated from now on.', 'rocinante' ) );
    }
    wp_safe_redirect( roci_maintenance_settings_url() );
    exit;
}
add_action( 'admin_post_roci_install_standby', 'roci_standby_install_handler' );

function roci_standby_dropin_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'rocinante' ), 403 );
    }
    check_admin_referer( 'roci_install_dropin' );

    $result = roci_standby_install_dropin();
    if ( is_wp_error( $result ) ) {
        roci_standby_notice_set( 'error', $result->get_error_message() );
    } else {
        roci_standby_notice_set( 'success', __( 'wp-content/php-error.php is in place and matches the roci-standby theme.', 'rocinante' ) );
    }
    wp_safe_redirect( roci_maintenance_settings_url() );
    exit;
}
add_action( 'admin_post_roci_install_dropin', 'roci_standby_dropin_handler' );


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
.logo{display:block;max-width:min(18rem,100%);height:auto;margin:0 auto 2rem}
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
<img class="logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>"<?php echo ( isset( $brand['logo_w'], $brand['logo_h'] ) && (int) $brand['logo_w'] > 0 && (int) $brand['logo_h'] > 0 ) ? ' width="' . (int) $brand['logo_w'] . '" height="' . (int) $brand['logo_h'] . '"' : ''; ?>>
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
 * Internal. Theme Settings → Maintenance (8.9.0; was the SEO tab's section).
 *
 * @return string
 */
function roci_maintenance_settings_url() {
    return admin_url( 'admin.php?page=roci-theme-settings&tab=maintenance' );
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
            <a href="<?php echo esc_url( roci_maintenance_settings_url() ); ?>"><?php esc_html_e( 'Turn it off in Theme Settings → Maintenance when you are done.', 'rocinante' ); ?></a>
        </p>
        <?php if ( ! $standby ) : ?>
            <p><?php esc_html_e( 'The roci-standby theme is not installed, so visitors get the plain built-in page (no logo or contact line).', 'rocinante' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
add_action( 'admin_notices', 'roci_maintenance_admin_notice' );
