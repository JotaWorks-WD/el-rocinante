<?php
/**
 * Theme Settings — Register Settings, Menu & Scripts
 *
 * Also contains the roci_setting() front-end helper and the
 * roci_admin_brand_accent() brand-accent reader built on top of it.
 *
 * File:    inc/theme-settings/settings-register.php
 * Version: 1.2.0
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
// HELPER — ADMIN BRAND ACCENT
// Usage: roci_admin_brand_accent()
// Filter: 'roci_admin_brand_accent'
// ============================================================

/**
 * Return the site's brand accent colour as a CSS-ready hex string.
 *
 * Reads Theme Settings → Design → Primary. That option row does not exist
 * until a human saves the Design tab, so on an untouched install this falls
 * back to '#000' — the exact value abstracts/_variables.scss:65 compiles into
 * both --folders-highlight (admin) and --color-action (front end). An unfilled
 * roci_design therefore renders identically to today.
 *
 * The stored value is passed through unmodified. roci_setting() may return a
 * three-digit '#000' or a six-digit picker value and CSS accepts either, so
 * there is deliberately no length check, normalisation or padding here.
 * Callers must escape at their own output site.
 *
 * This helper is NOT Fauxlders-specific. Any admin-side surface that needs the
 * brand accent — the folder token bridge in inc/folders/branding.php today,
 * a dashboard colour scheme later — reads it from here.
 *
 * @return string  Hex colour string, e.g. '#000' or '#4894a2'.
 */
function roci_admin_brand_accent() {

    $accent = roci_setting( 'design', 'primary' );

    if ( ! $accent ) {
        $accent = '#000';
    }

    return apply_filters( 'roci_admin_brand_accent', $accent );
}