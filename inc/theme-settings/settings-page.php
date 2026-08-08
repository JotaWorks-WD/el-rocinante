<?php
/**
 * Theme Settings — Page Renderer
 *
 * Outputs the admin settings page shell (tabs, form wrapper,
 * shared styles, AJAX logo script). Tab content is delegated
 * to the individual files in tabs/.
 *
 * File:    inc/theme-settings/settings-page.php
 * Version: 1.2.1
 * Updated: 2026-08-08
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// SETTINGS PAGE OUTPUT
// ============================================================

/**
 * Render the Theme Settings admin page.
 *
 * Outputs the tab nav, single form wrapper, active tab content,
 * and submit button. Tab content is delegated to tabs/*.
 *
 * Filters dispatched:
 * - roci_settings_tabs — array of tab_id => label pairs; child themes
 *   may add tabs by appending to this array.
 */
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

        <h1><?php esc_html_e( 'Theme Settings', 'rocinante' ); ?> <span style="font-size:13px;color:#666;font-weight:400;">El Rocinante</span></h1>

        <style>
            .roci-settings-wrap .nav-tab-wrapper { margin-bottom: 20px; }
            .roci-settings-wrap .roci-tab-content { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 24px; }
            .roci-settings-wrap h2.roci-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #888; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; margin: 28px 0 16px; }
            .roci-settings-wrap h2.roci-section-title:first-child { margin-top: 0; }
            .roci-settings-wrap .form-table th { width: 220px; }
            .roci-settings-wrap .roci-color-row { display: flex; gap: 24px; flex-wrap: wrap; }
            .roci-settings-wrap .roci-color-field { display: flex; flex-direction: column; gap: 6px; }
            .roci-settings-wrap .roci-color-field label,
            .roci-settings-wrap .roci-color-label { font-size: 12px; color: #555; font-weight: 600; }
            /* Locked colour display — the Design tab's fields are static swatches,
               not pickers. The border is unconditional on purpose: color-white is
               #ffffff on a white admin background and would otherwise render as
               nothing at all. */
            .roci-settings-wrap .roci-swatch { display: block; width: 56px; height: 28px; border-radius: 4px; border: 1px solid #c3c4c7; }
            .roci-settings-wrap .roci-swatch--empty { background: repeating-linear-gradient(45deg, #f6f7f7, #f6f7f7 4px, #e6e7e8 4px, #e6e7e8 8px); border-style: dashed; }
            .roci-settings-wrap .roci-hex { font-family: Consolas, Monaco, monospace; font-size: 12px; color: #50575e; background: none; padding: 0; }
            .roci-settings-wrap .roci-hex--empty { color: #a7aaad; font-style: italic; }
            .roci-settings-wrap .roci-media-wrap { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
            .roci-settings-wrap .roci-media-preview { max-height: 60px; width: auto; border-radius: 4px; border: 1px solid #ddd; display: none; }
            .roci-settings-wrap .roci-media-preview.has-image { display: block; }
            .roci-settings-wrap .button-small { font-size: 12px; }
            .roci-settings-wrap .roci-note { font-size: 12px; color: #888; margin-top: 4px; }
            .roci-settings-wrap .roci-sync-note { font-size: 12px; color: #2271b1; margin-top: 4px; font-style: italic; }
            /* Per-type field groups (Business tab). The hide rule is scoped to
               .is-hidden, which ONLY dist/js/theme-settings.js adds — the base
               .roci-type-group has no hiding, so with JS unavailable every group
               renders visible. Do not add display:none to the base class: the
               groups must stay submittable, and a group hidden before JS runs
               would be a group the admin cannot see but can still wipe. */
            .roci-settings-wrap .roci-type-group.is-hidden { display: none; }
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
            var $logoField = $('#roci_logo_id');
            if ( ! $logoField.length ) return; // Site Identity tab not active — nothing to save
            $.post(ajaxurl, {
                action: 'roci_save_custom_logo',
                logo_id: $logoField.val(),
                nonce: '<?php echo wp_create_nonce( "roci_save_logo" ); ?>'
            });
        });
    });
    </script>

    <?php
}