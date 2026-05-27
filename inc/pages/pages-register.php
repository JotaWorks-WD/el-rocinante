<?php
/**
 * Pages — Navigation Grouping
 *
 * Registers the Pages submenu under Theme Settings as a navigation
 * grouping only. Child themes register their own MB Pro Settings Pages
 * with parent => 'roci-pages' to nest under this entry. Each child
 * page owns its own option_name storage independently.
 *
 * File:    inc/pages/pages-register.php
 * Version: 2.0.0
 * Updated: 2026-05-27
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// REGISTER PAGES SUBMENU
// ============================================================

function roci_register_pages_submenu() {
    add_submenu_page(
        'roci-theme-settings',
        __( 'Pages', 'rocinante' ),
        __( 'Pages', 'rocinante' ),
        'manage_options',
        'roci-pages',
        'roci_pages_landing_screen'
    );
}
add_action( 'admin_menu', 'roci_register_pages_submenu', 11 );


// ============================================================
// LANDING SCREEN
// ============================================================

function roci_pages_landing_screen() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Pages', 'rocinante' ); ?></h1>
        <p><?php esc_html_e( 'Select a page from the submenu to edit its content. Pages are registered by the active child theme.', 'rocinante' ); ?></p>
    </div>
    <?php
}
