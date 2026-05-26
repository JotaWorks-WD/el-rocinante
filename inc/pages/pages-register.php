<?php
/**
 * Pages — MB Pro Settings Page Registration
 *
 * Registers a tabbed Pages Settings Page under Theme Settings.
 * Tabs are contributed by child themes via the roci_pages_tabs filter.
 * Meta boxes attach to specific tabs via the 'tab' parameter in
 * their MB Pro meta box config.
 *
 * File:    inc/pages/pages-register.php
 * Version: 1.0.0
 * Updated: 2026-05-26
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// REGISTER PAGES SETTINGS PAGE
// ============================================================

function roci_register_pages_settings( $pages ) {

    $pages[] = array(
        'id'          => 'roci-pages',
        'option_name' => 'roci_pages_data',
        'menu_title'  => __( 'Pages', 'rocinante' ),
        'page_title'  => __( 'Pages', 'rocinante' ),
        'parent'      => 'roci-theme-settings',
        'capability'  => 'manage_options',
        'columns'     => 1,
        'icon_url'    => 'dashicons-admin-page',
        'position'    => 4,
        'tabs'        => apply_filters( 'roci_pages_tabs', array() ),
    );

    return $pages;
}
add_filter( 'mb_settings_pages', 'roci_register_pages_settings' );


// ============================================================
// LANDING REDIRECT — auto-select first tab, or show empty state
// ============================================================

function roci_pages_landing_redirect() {

    if ( ! is_admin() ) {
        return;
    }

    if ( ! isset( $_GET['page'] ) || 'roci-pages' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }

    if ( isset( $_GET['tab'] ) && '' !== $_GET['tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }

    $tabs = apply_filters( 'roci_pages_tabs', array() );

    if ( ! empty( $tabs ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=roci-pages&tab=' . urlencode( array_key_first( $tabs ) ) ) );
        exit;
    }

    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-info"><p>' . esc_html__( 'No page sections registered. Child themes add tabs via the roci_pages_tabs filter.', 'rocinante' ) . '</p></div>';
    } );
}
add_action( 'admin_init', 'roci_pages_landing_redirect' );
