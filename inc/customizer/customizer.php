<?php
/**
 * Customizer — Pages Admin Panel
 *
 * Registers the Pages submenu under Theme Settings.
 * Acts as a container only — no sections, no settings.
 * Child themes populate this panel via roci_register_page_section().
 *
 * File:    inc/customizer/customizer.php
 * Version: 1.0.2
 * Updated: 2026-05-10
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// REGISTRY — stores sections registered by child themes
// ============================================================

global $roci_page_sections;
$roci_page_sections = array();


// ============================================================
// HELPER — roci_register_page_section()
//
// Child themes call this to register a tab inside the Pages panel.
//
// IMPORTANT: Always wrap the call in after_setup_theme at priority 20
// to ensure the parent theme has fully loaded before registering.
//
// Usage (in child theme inc/customizer/pages/your-page.php):
//
//   function fpp_register_home_section() {
//       roci_register_page_section( array(
//           'tab_id'    => 'home',                // unique slug for this tab
//           'tab_label' => 'Home',                // label shown in the tab nav
//           'option'    => 'fpp_page_home',       // wp option key — use client prefix
//           'group'     => 'fpp_page_home_group', // settings group — use client prefix
//           'sanitize'  => 'fpp_sanitize_home',   // sanitize callback function name
//           'render'    => 'fpp_render_home_tab', // render callback — outputs form fields
//       ) );
//   }
//   add_action( 'after_setup_theme', 'fpp_register_home_section', 20 );
//
// The render callback receives no arguments.
// Use get_option( 'fpp_page_home', array() ) inside it to read saved values.
// All keys (option, group, sanitize, render) should use the client prefix — never roci_.
// ============================================================

function roci_register_page_section( array $args ) {

    global $roci_page_sections;

    $defaults = array(
        'tab_id'    => '',
        'tab_label' => '',
        'option'    => '',
        'group'     => '',
        'sanitize'  => '',
        'render'    => '',
    );

    $section = wp_parse_args( $args, $defaults );

    // Bail if required keys are missing
    if ( empty( $section['tab_id'] ) || empty( $section['render'] ) ) {
        return;
    }

    $roci_page_sections[ $section['tab_id'] ] = $section;
}


// ============================================================
// REGISTER SETTINGS — runs on admin_init
// Loops registered sections and registers each option + group
// ============================================================

function roci_register_page_settings() {

    global $roci_page_sections;

    foreach ( $roci_page_sections as $section ) {
        if ( empty( $section['option'] ) || empty( $section['group'] ) ) {
            continue;
        }

        $sanitize = ! empty( $section['sanitize'] ) && function_exists( $section['sanitize'] )
            ? $section['sanitize']
            : null;

        register_setting(
            $section['group'],
            $section['option'],
            $sanitize ? array( 'sanitize_callback' => $sanitize ) : array()
        );
    }
}
add_action( 'admin_init', 'roci_register_page_settings' );


// ============================================================
// REGISTER SUBMENU — Pages under Theme Settings
// ============================================================

function roci_add_pages_menu() {
    add_submenu_page(
        'roci-theme-settings',
        __( 'Pages', 'rocinante' ),
        __( 'Pages', 'rocinante' ),
        'manage_options',
        'roci-pages',
        'roci_pages_panel'
    );
}
add_action( 'admin_menu', 'roci_add_pages_menu' );


// ============================================================
// RENDER — Pages panel shell
// Tabs driven entirely by registered sections
// ============================================================

function roci_pages_panel() {

    global $roci_page_sections;

    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';

    // Default to first registered tab
    if ( empty( $active_tab ) && ! empty( $roci_page_sections ) ) {
        $active_tab = array_key_first( $roci_page_sections );
    }

    ?>
    <div class="wrap roci-settings-wrap">

        <h1><?php _e( 'Pages', 'rocinante' ); ?> <span style="font-size:13px;color:#666;font-weight:400;">El Rocinante</span></h1>

        <?php if ( empty( $roci_page_sections ) ) : ?>

            <p><?php _e( 'No page sections registered. Child themes add sections via <code>roci_register_page_section()</code>.', 'rocinante' ); ?></p>

        <?php else : ?>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $roci_page_sections as $tab_id => $section ) : ?>
                    <a href="?page=roci-pages&tab=<?php echo esc_attr( $tab_id ); ?>"
                       class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $section['tab_label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="roci-tab-content" style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:24px;">
                <?php if ( isset( $roci_page_sections[ $active_tab ] ) ) : ?>
                    <form method="post" action="options.php">
                        <?php
                        $section = $roci_page_sections[ $active_tab ];
                        settings_fields( $section['group'] );

                        if ( function_exists( $section['render'] ) ) {
                            call_user_func( $section['render'] );
                        }

                        submit_button( __( 'Save Settings', 'rocinante' ) );
                        ?>
                    </form>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>
    <?php
}