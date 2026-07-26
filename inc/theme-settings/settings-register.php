<?php
/**
 * Theme Settings — Register Settings, Menu & Scripts
 *
 * Also contains the roci_setting() front-end helper, the canonical brand store
 * roci_brand_palette(), and the two readers that resolve through it —
 * roci_admin_brand_accent() ("what colour") and roci_has_brand_accent()
 * ("is one configured at all").
 *
 * File:    inc/theme-settings/settings-register.php
 * Version: 1.4.0
 * Updated: 2026-07-26
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// REGISTER SETTINGS
// ============================================================

function roci_register_settings() {

    register_setting( 'roci_design_group',       'roci_design',       array( 'sanitize_callback' => 'roci_sanitize_design'       ) );
    register_setting( 'roci_business_group',     'roci_business',     array( 'sanitize_callback' => 'roci_sanitize_business'     ) );
    register_setting( 'roci_social_group',       'roci_social',       array( 'sanitize_callback' => 'roci_sanitize_social'       ) );
    register_setting( 'roci_seo_group',          'roci_seo',          array( 'sanitize_callback' => 'roci_sanitize_seo'          ) );
    register_setting( 'roci_integrations_group', 'roci_integrations', array( 'sanitize_callback' => 'roci_sanitize_integrations' ) );
    register_setting( 'roci_footer_group',       'roci_footer',       array( 'sanitize_callback' => 'roci_sanitize_footer'       ) );

    // Site Identity — registers WP core options directly
    register_setting( 'roci_identity_group', 'blogname',        array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'roci_identity_group', 'blogdescription', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'roci_identity_group', 'site_icon',       array( 'sanitize_callback' => 'absint'              ) );

}
add_action( 'admin_init', 'roci_register_settings' );


// ============================================================
// REGISTER ADMIN MENU
// ============================================================

function roci_add_settings_menu() {
    add_menu_page(
        __( 'Theme Settings', 'rocinante' ),
        __( 'Theme Settings', 'rocinante' ),
        'manage_options',
        'roci-theme-settings',
        'roci_settings_page',
        'dashicons-admin-customizer',
        3
    );
}
add_action( 'admin_menu', 'roci_add_settings_menu' );


// ============================================================
// ENQUEUE COLOR PICKER & ADMIN SCRIPTS
// ============================================================

function roci_settings_enqueue( $hook ) {
    if ( strpos( $hook, 'roci' ) === false ) return;

    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_media();
    wp_enqueue_script(
        'roci-settings-js',
        get_template_directory_uri() . '/dist/js/theme-settings.js',
        array( 'jquery', 'wp-color-picker' ),
        filemtime( get_template_directory() . '/dist/js/theme-settings.js' ),
        true
    );
}
add_action( 'admin_enqueue_scripts', 'roci_settings_enqueue' );


// ============================================================
// HELPER — GET SETTING
// Usage: roci_setting('business', 'name')
//        roci_setting('design', 'primary_color', '#000000')
// ============================================================

function roci_setting( $group, $key, $default = '' ) {
    $options = get_option( 'roci_' . $group, array() );
    return isset( $options[ $key ] ) && $options[ $key ] !== '' ? $options[ $key ] : $default;
}


// ============================================================
// HELPER — BRAND PALETTE (canonical brand store)
// Usage: roci_brand_palette()
// Filter: 'roci_brand_palette'
// ============================================================

/**
 * The site's brand palette — the single source of brand truth.
 *
 * Every brand consumer resolves through this: roci_admin_brand_accent() and
 * roci_has_brand_accent() below, the Brand Colors admin scheme in
 * inc/admin/brand-scheme.php, and the read-only mirror on the Design tab. If
 * a new consumer needs a brand colour, it reads this — not get_option() and
 * not roci_setting().
 *
 * Keys are the eleven stored Design-tab colour keys, verbatim, matching both
 * tab-design.php's $color_groups and roci_sanitize_design()'s $color_fields.
 * Every key is always present; an unconfigured slot is an empty string, never
 * missing, so callers can index without isset() gymnastics.
 *
 * Default source is the roci_design option, so a site that fills the Design tab
 * behaves exactly as it did before this resolver existed.
 *
 * THE FILTER IS THE POINT. A code-owned child supplies its brand here in about
 * four lines and never touches the dashboard:
 *
 *     add_filter( 'roci_brand_palette', function ( $palette ) {
 *         $palette['primary']   = '#1a2e35';
 *         $palette['secondary'] = '#4894a2';
 *         return $palette;
 *     } );
 *
 * That single registration switches on every brand consumer at once, which is
 * why it replaced the previous arrangement where a child had to remember to
 * register 'roci_admin_brand_accent' and 'roci_has_brand_accent' as a matched
 * pair or its scheme would silently never appear.
 *
 * FAILS CLOSED. A child that supplies nothing gets an all-empty palette: the
 * Design pickers stay blank, roci_has_brand_accent() stays false, and the admin
 * scheme never registers. Nothing renders a wrong colour; features are absent
 * rather than broken.
 *
 * @return array  Eleven-key map of design colour key => hex string (or '').
 */
function roci_brand_palette() {

    $stored = get_option( 'roci_design', array() );

    $keys = array(
        'primary', 'primary_accent',
        'secondary', 'secondary_accent',
        'tertiary', 'tertiary_accent',
        'black', 'grey', 'white',
        'background', 'background_alt',
    );

    $palette = array();
    foreach ( $keys as $key ) {
        $palette[ $key ] = isset( $stored[ $key ] ) ? $stored[ $key ] : '';
    }

    return apply_filters( 'roci_brand_palette', $palette );
}


// ============================================================
// HELPER — ADMIN BRAND ACCENT
// Usage: roci_admin_brand_accent()
// Filter: 'roci_admin_brand_accent'
// ============================================================

/**
 * Return the site's brand accent colour as a CSS-ready hex string.
 *
 * Reads the PRIMARY slot of roci_brand_palette(), which defaults to Theme
 * Settings → Design → Primary and can be supplied in code by a child. That slot
 * is empty until something fills it, so on an unconfigured install this falls
 * back to '#000' — the exact value abstracts/_variables.scss:65 compiles into
 * both --folders-highlight (admin) and --color-action (front end). An unfilled
 * palette therefore renders identically to a pre-bridge install.
 *
 * The value is passed through unmodified. The store may hold a three-digit
 * '#000' or a six-digit picker value and CSS accepts either, so there is
 * deliberately no length check, normalisation or padding here. Callers must
 * escape at their own output site.
 *
 * This helper is NOT Fauxlders-specific. Any admin-side surface that needs the
 * brand accent — the folder token bridge in inc/folders/branding.php and the
 * Brand Colors scheme in inc/admin/brand-scheme.php today — reads it from here.
 *
 * Its own filter is retained and still runs LAST, so anything already hooking
 * 'roci_admin_brand_accent' keeps overriding the palette exactly as before.
 *
 * @return string  Hex colour string, e.g. '#000' or '#4894a2'.
 */
function roci_admin_brand_accent() {

    $palette = roci_brand_palette();
    $accent  = isset( $palette['primary'] ) ? $palette['primary'] : '';

    if ( ! $accent ) {
        $accent = '#000';
    }

    return apply_filters( 'roci_admin_brand_accent', $accent );
}


// ============================================================
// HELPER — IS A BRAND ACCENT CONFIGURED?
// Usage: roci_has_brand_accent()
// Filter: 'roci_has_brand_accent'
// ============================================================

/**
 * Whether a brand accent has been DELIBERATELY configured for this site.
 *
 * This answers "should brand-dependent admin features activate at all?" —
 * a different question from roci_admin_brand_accent()'s "what colour is it?".
 *
 * roci_admin_brand_accent() cannot answer it. That helper defaults an unset
 * option to '#000', so an untouched install and a site whose brand really is
 * black are indistinguishable through it. That collapse is correct for its own
 * job — the Fauxlders bridge wants a usable colour either way — but a feature
 * that must stay invisible until a brand exists needs this predicate instead.
 *
 * Reads the PRIMARY slot of roci_brand_palette() raw: empty or missing is
 * false, and any non-empty value is true, INCLUDING a deliberate '#000'.
 *
 * BECAUSE IT READS THE PALETTE, A CHILD SUPPLYING ITS BRAND IN CODE NOW COUNTS
 * AS CONFIGURED. That is the intended behaviour and it repairs a real trap:
 * before the palette resolver existed this read the option directly, so a child
 * that supplied its brand through the 'roci_admin_brand_accent' filter had a
 * genuine brand, an empty option row, and a predicate that said "no brand" —
 * meaning its admin scheme silently never registered and it had to know to
 * register a second filter as well. One 'roci_brand_palette' registration now
 * switches everything on together.
 *
 * Note the consequence, which is deliberate: a brand supplied purely in code
 * makes brand-dependent admin features activate with no dashboard input at all.
 * A child that wants the brand but NOT a particular feature opts out of that
 * feature specifically — see roci_offer_brand_scheme() in
 * inc/admin/brand-scheme.php, which exists exactly so "has a brand" and "wants
 * the colour scheme offered" stay separable questions.
 *
 * @return bool  True when a brand accent is configured.
 */
function roci_has_brand_accent() {

    $palette = roci_brand_palette();
    $has     = isset( $palette['primary'] ) && '' !== $palette['primary'];

    return apply_filters( 'roci_has_brand_accent', $has );
}