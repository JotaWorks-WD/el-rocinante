<?php
/**
 * Theme Settings — SEO Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-seo.php
 * Version: 1.1.1
 * Updated: 2026-05-10
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_seo_group' );
$seo        = get_option( 'roci_seo', array() );
$default_og = isset( $seo['default_og_image'] ) ? $seo['default_og_image'] : '';
?>
<h2 class="roci-section-title"><?php _e( 'SEO Defaults', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_default_meta_desc"><?php _e( 'Default Meta Description', 'rocinante' ); ?></label></th>
        <td>
            <textarea name="roci_seo[default_meta_description]" id="roci_default_meta_desc" class="large-text" rows="3" maxlength="160"><?php echo esc_textarea( isset( $seo['default_meta_description'] ) ? $seo['default_meta_description'] : '' ); ?></textarea>
            <p class="roci-note"><?php _e( 'Used when no page-specific meta description is set. Max 160 characters.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label><?php _e( 'Default OG Image', 'rocinante' ); ?></label></th>
        <td>
            <div class="roci-media-wrap">
                <img src="<?php echo esc_url( $default_og ); ?>" class="roci-media-preview <?php echo $default_og ? 'has-image' : ''; ?>" id="roci_og_preview">
                <input type="hidden" name="roci_seo[default_og_image]" id="roci_og_image_url" value="<?php echo esc_url( $default_og ); ?>">
                <button type="button" class="button button-small roci-media-upload" data-target="roci_og_image_url" data-preview="roci_og_preview"><?php _e( 'Select Image', 'rocinante' ); ?></button>
                <?php if ( $default_og ) : ?>
                    <button type="button" class="button button-small roci-media-remove" data-target="roci_og_image_url" data-preview="roci_og_preview"><?php _e( 'Remove', 'rocinante' ); ?></button>
                <?php endif; ?>
            </div>
            <p class="roci-note"><?php _e( 'Recommended 1200x630px WebP. Used when no page-specific OG image is set.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_seo_preview_toggle"><?php _e( 'SEO Preview Panel', 'rocinante' ); ?></label></th>
        <td>
            <input type="checkbox" name="roci_seo[seo_preview]" id="roci_seo_preview_toggle" value="1" <?php checked( isset( $seo['seo_preview'] ) ? $seo['seo_preview'] : '1', '1' ); ?>>
            <label for="roci_seo_preview_toggle"><?php _e( 'Show Google, Facebook and Twitter preview panels on page/post edit screens', 'rocinante' ); ?></label>
        </td>
    </tr>
</table>
