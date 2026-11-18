<?php
/**
 * Dark Mode — the opt-in mechanism (roci-dark-mode)
 *
 * Loaded ONLY when the child declares add_theme_support( 'roci-dark-mode' )
 * (el_rocinante_load_dark_mode(), functions.php). The parent ships the
 * mechanism; the child ships the dark VALUES, a roci-dark-roles mixin in its
 * own SCSS emitted inside the two selectors of the contract below.
 *
 * Theme Settings → Design → Colour mode (roci_design['color_mode']):
 *   off     light only. data-color-mode="off", color-scheme "light".
 *   auto    follow the visitor's device (prefers-color-scheme). The default.
 *   toggle  follow the device, plus a visitor toggle (roci_theme_toggle())
 *           whose choice is saved in localStorage 'roci-theme' and applied
 *           before first paint.
 *
 * THE SELECTOR CONTRACT — dark is active on:
 *   :root[data-theme="dark"]
 *   @media (prefers-color-scheme: dark) {
 *     :root:not([data-theme="light"]):not([data-color-mode="off"])
 *   }
 * The child's dark roles go inside exactly these two (Build
 * components/_theme-toggle.scss documents it too).
 *
 * Output, on top of the stylesheet:
 *   <html … data-color-mode="{mode}">            language_attributes
 *   <meta name="color-scheme" content="…">       wp_head, priority 1
 *   the saved choice in the pre-paint script     toggle only
 *   dist/js/theme-toggle.js, footer              toggle only
 *   the toggle button                            toggle only, through
 *                                                roci_theme_toggle() (functions.php,
 *                                                always defined)
 *
 * File:    inc/dark-mode.php
 * Version: 1.0.0
 * Updated: 2026-11-18
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// THE MODE
// ============================================================

/**
 * roci_color_modes()
 *
 * The three modes and their admin labels. One list for the Design tab and
 * the readers; the sanitiser repeats the three keys (it runs without this
 * file on a site without the flag).
 *
 * @return array slug => label
 */
function roci_color_modes() {
    return array(
        'off'    => __( 'Off — light only', 'rocinante' ),
        'auto'   => __( 'Auto — follow the visitor\'s device', 'rocinante' ),
        'toggle' => __( 'Toggle — follow the device, plus a visitor toggle', 'rocinante' ),
    );
}

/**
 * roci_color_mode()
 *
 * The site's colour mode: 'off', 'auto' or 'toggle'. Anything else, or
 * nothing saved, is 'auto'.
 *
 * @return string
 */
function roci_color_mode() {
    $mode = (string) roci_setting( 'design', 'color_mode', 'auto' );

    return array_key_exists( $mode, roci_color_modes() ) ? $mode : 'auto';
}


// ============================================================
// <html> AND <head>
// ============================================================

/**
 * roci_dark_mode_html_attribute()
 *
 * Adds data-color-mode="{mode}" to <html>, server-side, so the CSS contract
 * can tell "off" apart before any script runs.
 *
 * @param  string $output The language_attributes() string.
 * @return string
 */
function roci_dark_mode_html_attribute( $output ) {
    return $output . ' data-color-mode="' . esc_attr( roci_color_mode() ) . '"';
}
add_filter( 'language_attributes', 'roci_dark_mode_html_attribute' );

/**
 * roci_dark_mode_color_scheme_meta()
 *
 * The color-scheme meta, early in <head>: "light dark" lets the browser draw
 * its own UI (scrollbars, form controls, the blank canvas) dark when the page
 * is; "light" when the mode is off. Priority 1 — 0 is the hero preload's.
 */
function roci_dark_mode_color_scheme_meta() {
    $content = 'off' === roci_color_mode() ? 'light' : 'light dark';

    echo '<meta name="color-scheme" content="' . esc_attr( $content ) . '">' . "\n";
}
add_action( 'wp_head', 'roci_dark_mode_color_scheme_meta', 1 );

/**
 * roci_dark_mode_pre_paint()
 *
 * One statement for el_rocinante_pre_paint_script() (functions.php), which
 * appends it to its own script tag — never a second tag. '' unless the mode
 * is "toggle".
 *
 * Reads localStorage 'roci-theme'. When that is missing and the older
 * 'jwd-theme' exists, its value is copied to 'roci-theme' once (jwd-theme is
 * left in place). A 'light' or 'dark' value sets data-theme on <html>.
 * try/catch: localStorage throws in some private modes, and the page must
 * still render.
 *
 * @return string JavaScript; uses the script's r (document.documentElement).
 */
function roci_dark_mode_pre_paint() {
    if ( 'toggle' !== roci_color_mode() ) {
        return '';
    }

    return "try{var s=localStorage,t=s.getItem('roci-theme');if(t===null){var j=s.getItem('jwd-theme');if(j!==null){s.setItem('roci-theme',j);t=j;}}if(t==='light'||t==='dark'){r.setAttribute('data-theme',t);}}catch(e){}";
}


// ============================================================
// THE TOGGLE
// ============================================================

/**
 * roci_dark_mode_enqueue()
 *
 * The toggle's script (registered by el_rocinante_enqueue_assets()), on a
 * "toggle" site only. The parent enqueues no front-end JS unconditionally.
 */
function roci_dark_mode_enqueue() {
    if ( 'toggle' === roci_color_mode() ) {
        wp_enqueue_script( 'el-rocinante-theme-toggle' );
    }
}
add_action( 'wp_enqueue_scripts', 'roci_dark_mode_enqueue', 20 );

/**
 * roci_dark_mode_toggle_markup()
 *
 * Internal. The toggle button's markup, or '' unless the mode is "toggle".
 * A child never calls it: roci_get_theme_toggle() / roci_theme_toggle()
 * (functions.php) always exist and delegate here when this file is loaded.
 *
 * Both action labels and both icons are in the markup; the stylesheet shows
 * the right pair from the selector contract, so it is correct on first paint
 * with no JS. The label names the ACTION ("Dark mode" while light shows), so
 * there is no aria-pressed: a pressed state beside an action label would
 * contradict it. Hidden (visibility, so no layout shift) until html.js.
 *
 * @param  string $class Extra class(es) for the button.
 * @return string
 */
function roci_dark_mode_toggle_markup( $class = '' ) {
    if ( 'toggle' !== roci_color_mode() ) {
        return '';
    }

    $classes = trim( 'roci-theme-toggle ' . (string) $class );

    // Sun / moon: 24-unit outline icons, painted with currentColor.
    $svg  = '<svg class="roci-theme-toggle__icon roci-theme-toggle__icon--%1$s" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>';
    $moon = sprintf( $svg, 'moon', '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>' );
    $sun  = sprintf( $svg, 'sun', '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>' );

    return '<button type="button" class="' . esc_attr( $classes ) . '" data-roci-theme-toggle>'
        . $moon
        . $sun
        . '<span class="screen-reader-text roci-theme-toggle__label roci-theme-toggle__label--to-dark">' . esc_html__( 'Dark mode', 'rocinante' ) . '</span>'
        . '<span class="screen-reader-text roci-theme-toggle__label roci-theme-toggle__label--to-light">' . esc_html__( 'Light mode', 'rocinante' ) . '</span>'
        . '</button>';
}


// ============================================================
// SAVE
// ============================================================

/**
 * roci_dark_mode_purge()
 *
 * The mode is in every page's HTML (data-color-mode, the meta, the script),
 * so changing it purges the whole LiteSpeed cache. A do_action() nothing
 * listens to without LiteSpeed.
 *
 * @param mixed $old The stored roci_design before the save.
 * @param mixed $new The roci_design being saved.
 */
function roci_dark_mode_purge( $old, $new ) {
    $old_mode = is_array( $old ) && isset( $old['color_mode'] ) ? $old['color_mode'] : 'auto';
    $new_mode = is_array( $new ) && isset( $new['color_mode'] ) ? $new['color_mode'] : 'auto';

    if ( $old_mode !== $new_mode ) {
        do_action( 'litespeed_purge_all' );
    }
}
add_action( 'update_option_roci_design', 'roci_dark_mode_purge', 10, 2 );
