<?php
/**
 * Theme Settings — Integrations Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-integrations.php
 * Version: 1.1.1
 * Updated: 2026-05-10
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_integrations_group' );
$integrations = get_option( 'roci_integrations', array() );
?>
<h2 class="roci-section-title"><?php _e( 'Analytics', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_ga_id"><?php _e( 'Google Analytics ID', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_integrations[ga_id]" id="roci_ga_id" class="regular-text" value="<?php echo esc_attr( isset( $integrations['ga_id'] ) ? $integrations['ga_id'] : '' ); ?>" placeholder="G-XXXXXXXXXX"></td>
    </tr>
    <tr>
        <th><label for="roci_gtm_id"><?php _e( 'Google Tag Manager ID', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_integrations[gtm_id]" id="roci_gtm_id" class="regular-text" value="<?php echo esc_attr( isset( $integrations['gtm_id'] ) ? $integrations['gtm_id'] : '' ); ?>" placeholder="GTM-XXXXXXX"></td>
    </tr>
    <tr>
        <th><label for="roci_fb_pixel"><?php _e( 'Facebook Pixel ID', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_integrations[fb_pixel_id]" id="roci_fb_pixel" class="regular-text" value="<?php echo esc_attr( isset( $integrations['fb_pixel_id'] ) ? $integrations['fb_pixel_id'] : '' ); ?>" placeholder="XXXXXXXXXXXXXXXXXX"></td>
    </tr>
</table>

<h2 class="roci-section-title"><?php _e( 'Custom Scripts', 'rocinante' ); ?></h2>
<p class="roci-note" style="margin-bottom:16px;"><?php _e( 'Use these fields for any third-party embed codes not covered above — booking systems, widgets, tracking pixels, iCal feeds, etc.', 'rocinante' ); ?></p>
<table class="form-table">
    <tr>
        <th><label for="roci_custom_head"><?php _e( 'Custom &lt;head&gt; Script', 'rocinante' ); ?></label></th>
        <td>
            <textarea name="roci_integrations[custom_head_script]" id="roci_custom_head" class="large-text" rows="6"><?php echo esc_textarea( isset( $integrations['custom_head_script'] ) ? $integrations['custom_head_script'] : '' ); ?></textarea>
            <p class="roci-note"><?php _e( 'Output in &lt;head&gt;. Include full &lt;script&gt; tags.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_custom_footer"><?php _e( 'Custom Footer Script', 'rocinante' ); ?></label></th>
        <td>
            <textarea name="roci_integrations[custom_footer_script]" id="roci_custom_footer" class="large-text" rows="6"><?php echo esc_textarea( isset( $integrations['custom_footer_script'] ) ? $integrations['custom_footer_script'] : '' ); ?></textarea>
            <p class="roci-note"><?php _e( 'Output before &lt;/body&gt;. Include full &lt;script&gt; tags.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<?php do_action( 'roci_integrations_extra' ); ?>
