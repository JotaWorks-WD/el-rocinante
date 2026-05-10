<?php
/**
 * Theme Settings — Page Renderer
 *
 * Outputs the admin settings page shell (tabs, form wrapper,
 * shared styles, AJAX logo script). Tab content is delegated
 * to the individual files in tabs/.
 *
 * File:    inc/theme-settings/settings-page.php
 * Version: 1.1.1
 * Updated: 2026-05-10
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// SETTINGS PAGE OUTPUT
// ============================================================

function roci_settings_page() {

    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'identity';

    $tabs = apply_filters( 'roci_settings_tabs', array(
        'identity'     => __( 'Site Identity', 'rocinante' ),
        'design'       => __( 'Design', 'rocinante' ),
        'business'     => __( 'Business', 'rocinante' ),
        'social'       => __( 'Social', 'rocinante' ),
        'seo'          => __( 'SEO', 'rocinante' ),
        'integrations' => __( 'Integrations', 'rocinante' ),
        'footer'       => __( 'Footer', 'rocinante' ),
    ) );

    ?>
    <div class="wrap roci-settings-wrap">

        <h1><?php _e( 'Theme Settings', 'rocinante' ); ?> <span style="font-size:13px;color:#666;font-weight:400;">El Rocinante</span></h1>

        <style>
            .roci-settings-wrap .nav-tab-wrapper { margin-bottom: 20px; }
            .roci-settings-wrap .roci-tab-content { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 24px; }
            .roci-settings-wrap h2.roci-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #888; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; margin: 28px 0 16px; }
            .roci-settings-wrap h2.roci-section-title:first-child { margin-top: 0; }
            .roci-settings-wrap .form-table th { width: 220px; }
            .roci-settings-wrap .roci-color-row { display: flex; gap: 24px; flex-wrap: wrap; }
            .roci-settings-wrap .roci-color-field { display: flex; flex-direction: column; gap: 6px; }
            .roci-settings-wrap .roci-color-field label { font-size: 12px; color: #555; font-weight: 600; }
            .roci-settings-wrap .wp-color-result { border-radius: 4px; }
            .roci-settings-wrap .roci-media-wrap { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
            .roci-settings-wrap .roci-media-preview { max-height: 60px; width: auto; border-radius: 4px; border: 1px solid #ddd; display: none; }
            .roci-settings-wrap .roci-media-preview.has-image { display: block; }
            .roci-settings-wrap .button-small { font-size: 12px; }
            .roci-settings-wrap .roci-note { font-size: 12px; color: #888; margin-top: 4px; }
            .roci-settings-wrap .roci-sync-note { font-size: 12px; color: #2271b1; margin-top: 4px; font-style: italic; }
        </style>

        <nav class="nav-tab-wrapper">
            <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
                <a href="?page=roci-theme-settings&tab=<?php echo esc_attr( $tab_id ); ?>"
                   class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $tab_label ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="roci-tab-content">
            <form method="post" action="options.php">
                <?php

                switch ( $active_tab ) :

                    case 'identity':
                        include __DIR__ . '/tabs/tab-identity.php';
                        break;

                    case 'design':
                        include __DIR__ . '/tabs/tab-design.php';
                        break;

                    case 'business':
                        include __DIR__ . '/tabs/tab-business.php';
                        break;

                    case 'social':
                        include __DIR__ . '/tabs/tab-social.php';
                        break;

                    case 'seo':
                        include __DIR__ . '/tabs/tab-seo.php';
                        break;

                    case 'integrations':
                        include __DIR__ . '/tabs/tab-integrations.php';
                        break;

                    case 'footer':
                        include __DIR__ . '/tabs/tab-footer.php';
                        break;

                    default:
                        do_action( 'roci_settings_tab_' . $active_tab );
                        break;

                endswitch;

                submit_button( __( 'Save Settings', 'rocinante' ) );
                ?>
            </form>
        </div>

    </div>


    <?php
    // Handle custom_logo save — theme mod requires set_theme_mod(), not options.php
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('form').on('submit', function() {
            var logoId = $('#roci_logo_id').val();
            $.post(ajaxurl, {
                action: 'roci_save_custom_logo',
                logo_id: logoId,
                nonce: '<?php echo wp_create_nonce( "roci_save_logo" ); ?>'
            });
        });
    });
    </script>

    <?php
}
