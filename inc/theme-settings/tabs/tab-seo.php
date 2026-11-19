<?php
/**
 * Theme Settings — SEO Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * 1.2.0 (El Rocinante 8.4.0): the llms.txt row — llms_enabled and
 * llms_override, served by inc/llms-txt.php.
 *
 * 1.3.0 (El Rocinante 8.5.0): the Maintenance mode section —
 * maintenance_enabled and maintenance_message, read by
 * inc/maintenance-mode.php.
 *
 * File:    inc/theme-settings/tabs/tab-seo.php
 * Version: 1.3.0
 * Updated: 2026-11-19
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_seo_group' );
$seo        = get_option( 'roci_seo', array() );
$default_og = isset( $seo['default_og_image'] ) ? $seo['default_og_image'] : '';
// llms.txt (1.2.0) — both keys must also be in roci_sanitize_seo(), which
// rebuilds the option from scratch on every save.
$llms_enabled  = isset( $seo['llms_enabled'] ) ? (string) $seo['llms_enabled'] : '';
$llms_override = isset( $seo['llms_override'] ) ? (string) $seo['llms_override'] : '';
// Maintenance mode (1.3.0) — both keys must also be in roci_sanitize_seo().
$maintenance_enabled = isset( $seo['maintenance_enabled'] ) ? (string) $seo['maintenance_enabled'] : '';
$maintenance_message = isset( $seo['maintenance_message'] ) ? (string) $seo['maintenance_message'] : '';
?>
<h2 class="roci-section-title"><?php esc_html_e( 'SEO Defaults', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_default_meta_desc"><?php esc_html_e( 'Default Meta Description', 'rocinante' ); ?></label></th>
        <td>
            <textarea name="roci_seo[default_meta_description]" id="roci_default_meta_desc" class="large-text" rows="3" maxlength="160"><?php echo esc_textarea( isset( $seo['default_meta_description'] ) ? $seo['default_meta_description'] : '' ); ?></textarea>
            <p class="roci-note"><?php esc_html_e( 'Used when no page-specific meta description is set. Max 160 characters.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label><?php esc_html_e( 'Default OG Image', 'rocinante' ); ?></label></th>
        <td>
            <div class="roci-media-wrap">
                <img src="<?php echo esc_url( $default_og ); ?>" class="roci-media-preview <?php echo $default_og ? 'has-image' : ''; ?>" id="roci_og_preview">
                <input type="hidden" name="roci_seo[default_og_image]" id="roci_og_image_url" value="<?php echo esc_url( $default_og ); ?>">
                <button type="button" class="button button-small roci-media-upload" data-target="roci_og_image_url" data-preview="roci_og_preview"><?php esc_html_e( 'Select Image', 'rocinante' ); ?></button>
                <?php if ( $default_og ) : ?>
                    <button type="button" class="button button-small roci-media-remove" data-target="roci_og_image_url" data-preview="roci_og_preview"><?php esc_html_e( 'Remove', 'rocinante' ); ?></button>
                <?php endif; ?>
            </div>
            <p class="roci-note"><?php esc_html_e( 'Recommended 1200x630px WebP. Used when no page-specific OG image is set.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_seo_preview_toggle"><?php esc_html_e( 'SEO Preview Panel', 'rocinante' ); ?></label></th>
        <td>
            <input type="checkbox" name="roci_seo[seo_preview]" id="roci_seo_preview_toggle" value="1" <?php checked( isset( $seo['seo_preview'] ) ? $seo['seo_preview'] : '1', '1' ); ?>>
            <label for="roci_seo_preview_toggle"><?php esc_html_e( 'Show Google, Facebook and Twitter preview panels on page/post edit screens', 'rocinante' ); ?></label>
        </td>
    </tr>
    <tr>
        <th><label for="roci_llms_enabled"><?php esc_html_e( 'llms.txt', 'rocinante' ); ?></label></th>
        <td>
            <input type="checkbox" name="roci_seo[llms_enabled]" id="roci_llms_enabled" value="1" <?php checked( $llms_enabled, '1' ); ?>>
            <label for="roci_llms_enabled"><?php esc_html_e( 'Serve /llms.txt', 'rocinante' ); ?></label>
            <?php if ( '1' === $llms_enabled ) : ?>
                <p class="roci-note"><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( home_url( '/llms.txt' ) ); ?></a></p>
            <?php endif; ?>
            <p class="roci-note"><?php esc_html_e( 'Off by default. When on, /llms.txt is built automatically from published pages and posts.', 'rocinante' ); ?></p>
            <p class="roci-note"><?php esc_html_e( 'A physical llms.txt file in the site root always wins. Never served while "Discourage search engines" is on (Settings → Reading).', 'rocinante' ); ?></p>
            <p><label for="roci_llms_override"><?php esc_html_e( 'Custom llms.txt (optional)', 'rocinante' ); ?></label></p>
            <textarea name="roci_seo[llms_override]" id="roci_llms_override" class="large-text code" rows="8"><?php echo esc_textarea( $llms_override ); ?></textarea>
            <p class="roci-note"><?php esc_html_e( 'Anything here replaces the whole file. Leave empty for the automatic version.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<h2 class="roci-section-title" id="roci-maintenance"><?php esc_html_e( 'Maintenance mode', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_maintenance_enabled"><?php esc_html_e( 'Maintenance mode', 'rocinante' ); ?></label></th>
        <td>
            <input type="checkbox" name="roci_seo[maintenance_enabled]" id="roci_maintenance_enabled" value="1" <?php checked( $maintenance_enabled, '1' ); ?>>
            <label for="roci_maintenance_enabled"><?php esc_html_e( 'Turn on maintenance mode', 'rocinante' ); ?></label>
            <p class="roci-note"><?php esc_html_e( 'Visitors see a "back soon" page (HTTP 503). Logged-in administrators see the live site. Turn it off when you are done.', 'rocinante' ); ?></p>
            <p class="roci-note"><?php esc_html_e( 'Turning it on or off purges the page cache.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_maintenance_message"><?php esc_html_e( 'Message', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_seo[maintenance_message]" id="roci_maintenance_message" class="large-text" maxlength="200" value="<?php echo esc_attr( $maintenance_message ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Optional, one line, shown under "We\'ll be right back". Leave empty for the default.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>
