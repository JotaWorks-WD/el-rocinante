<?php
/**
 * Theme Settings — Business Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-business.php
 * Version: 1.1.1
 * Updated: 2026-05-10
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_business_group' );
$business = get_option( 'roci_business', array() );
?>
<h2 class="roci-section-title"><?php _e( 'Business Information', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_biz_name"><?php _e( 'Business Name', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[name]" id="roci_biz_name" class="regular-text" value="<?php echo esc_attr( isset( $business['name'] ) ? $business['name'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_phone"><?php _e( 'Phone Number', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[phone]" id="roci_biz_phone" class="regular-text" value="<?php echo esc_attr( isset( $business['phone'] ) ? $business['phone'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_email"><?php _e( 'Email Address', 'rocinante' ); ?></label></th>
        <td><input type="email" name="roci_business[email]" id="roci_biz_email" class="regular-text" value="<?php echo esc_attr( isset( $business['email'] ) ? $business['email'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_address"><?php _e( 'Business Address', 'rocinante' ); ?></label></th>
        <td><textarea name="roci_business[address]" id="roci_biz_address" class="large-text" rows="3"><?php echo esc_textarea( isset( $business['address'] ) ? $business['address'] : '' ); ?></textarea></td>
    </tr>
    <tr>
        <th><label for="roci_biz_whatsapp"><?php _e( 'WhatsApp Number', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_business[whatsapp]" id="roci_biz_whatsapp" class="regular-text" value="<?php echo esc_attr( isset( $business['whatsapp'] ) ? $business['whatsapp'] : '' ); ?>">
            <p class="roci-note"><?php _e( 'Include country code, no spaces or symbols. e.g. 50688887777', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_biz_maps"><?php _e( 'Google Maps Embed URL', 'rocinante' ); ?></label></th>
        <td>
            <input type="url" name="roci_business[maps_url]" id="roci_biz_maps" class="large-text" value="<?php echo esc_attr( isset( $business['maps_url'] ) ? $business['maps_url'] : '' ); ?>">
            <p class="roci-note"><?php _e( 'Paste the src URL from your Google Maps embed code.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>
