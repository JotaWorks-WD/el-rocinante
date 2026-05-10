<?php
/**
 * Theme Settings — Register Settings, Menu & Scripts
 *
 * Also contains the roci_setting() front-end helper.
 *
 * File:    inc/theme-settings/settings-register.php
 * Version: 1.1.1
 * Updated: 2026-05-10
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
    if ( $hook !== 'toplevel_page_roci-theme-settings' ) return;

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
