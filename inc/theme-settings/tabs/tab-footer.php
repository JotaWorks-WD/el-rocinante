<?php
/**
 * Theme Settings — Footer Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-footer.php
 * Version: 1.1.1
 * Updated: 2026-05-10
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_footer_group' );
$footer      = get_option( 'roci_footer', array() );
$footer_logo = isset( $footer['logo_url'] ) ? $footer['logo_url'] : '';
?>
<h2 class="roci-section-title"><?php _e( 'Footer Content', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_footer_tagline"><?php _e( 'Footer Tagline', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_footer[tagline]" id="roci_footer_tagline" class="regular-text" value="<?php echo esc_attr( isset( $footer['tagline'] ) ? $footer['tagline'] : '' ); ?>">
            <p class="roci-note"><?php _e( 'Displayed below the logo/brand name in the footer. Uses Apalu font if set.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_footer_blurb"><?php _e( 'Footer Blurb', 'rocinante' ); ?></label></th>
        <td>
            <textarea name="roci_footer[blurb]" id="roci_footer_blurb" class="large-text" rows="4"><?php echo esc_textarea( isset( $footer['blurb'] ) ? $footer['blurb'] : '' ); ?></textarea>
            <p class="roci-note"><?php _e( 'Short description displayed in the footer brand column.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label><?php _e( 'Footer Logo', 'rocinante' ); ?></label></th>
        <td>
            <div class="roci-media-wrap">
                <img src="<?php echo esc_url( $footer_logo ); ?>" class="roci-media-preview <?php echo $footer_logo ? 'has-image' : ''; ?>" id="roci_footer_logo_preview">
                <input type="hidden" name="roci_footer[logo_url]" id="roci_footer_logo_url" value="<?php echo esc_url( $footer_logo ); ?>">
                <button type="button" class="button button-small roci-media-upload" data-target="roci_footer_logo_url" data-preview="roci_footer_logo_preview"><?php _e( 'Select Logo', 'rocinante' ); ?></button>
                <?php if ( $footer_logo ) : ?>
                    <button type="button" class="button button-small roci-media-remove" data-target="roci_footer_logo_url" data-preview="roci_footer_logo_preview"><?php _e( 'Remove', 'rocinante' ); ?></button>
                <?php endif; ?>
            </div>
            <p class="roci-note"><?php _e( 'Optional separate logo for the footer. Falls back to site logo if empty.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>
