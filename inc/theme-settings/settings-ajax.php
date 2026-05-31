<?php
/**
 * Theme Settings — AJAX Handlers
 *
 * File:    inc/theme-settings/settings-ajax.php
 * Version: 1.1.2
 * Updated: 2026-05-31
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// AJAX — SAVE CUSTOM LOGO THEME MOD
// ============================================================

function roci_ajax_save_custom_logo() {
    check_ajax_referer( 'roci_save_logo', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    if ( ! isset( $_POST['logo_id'] ) ) {
        wp_send_json_error( 'logo_id not provided' );
    }
    $logo_id = absint( $_POST['logo_id'] );
    if ( $logo_id ) {
        set_theme_mod( 'custom_logo', $logo_id );
    } else {
        remove_theme_mod( 'custom_logo' );
    }
    wp_die();
}
add_action( 'wp_ajax_roci_save_custom_logo', 'roci_ajax_save_custom_logo' );
