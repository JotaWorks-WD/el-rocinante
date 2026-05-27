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
 * Version: 2.1.0
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
    global $submenu;

    $child_pages = array();

    if ( ! empty( $submenu['roci-pages'] ) ) {
        foreach ( $submenu['roci-pages'] as $entry ) {
            if ( isset( $entry[2] ) && 'roci-pages' !== $entry[2] ) {
                $child_pages[] = $entry;
            }
        }
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Pages', 'rocinante' ); ?></h1>
        <?php if ( empty( $child_pages ) ) : ?>
            <p><?php esc_html_e( 'Select a page from the submenu to edit its content. Pages are registered by the active child theme.', 'rocinante' ); ?></p>
        <?php else : ?>
            <p><?php esc_html_e( 'Select a page to edit its content:', 'rocinante' ); ?></p>
            <ul>
                <?php foreach ( $child_pages as $entry ) : ?>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $entry[2] ) ); ?>"><?php echo esc_html( $entry[0] ); ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}
