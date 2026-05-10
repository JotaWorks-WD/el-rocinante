<?php
/**
 * Theme Settings — Social Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-social.php
 * Version: 1.1.1
 * Updated: 2026-05-10
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_social_group' );
$social = get_option( 'roci_social', array() );

$platforms = apply_filters( 'roci_social_platforms', array(
    'facebook'    => 'Facebook',
    'instagram'   => 'Instagram',
    'whatsapp'    => 'WhatsApp',
    'tiktok'      => 'TikTok',
    'youtube'     => 'YouTube',
    'linkedin'    => 'LinkedIn',
    'twitter'     => 'X (Twitter)',
    'tripadvisor' => 'TripAdvisor',
) );
?>
<h2 class="roci-section-title"><?php _e( 'Social Profiles', 'rocinante' ); ?></h2>
<p class="roci-note" style="margin-bottom:16px;"><?php _e( 'Child themes can add additional platforms via the <code>roci_social_platforms</code> filter.', 'rocinante' ); ?></p>
<table class="form-table">
    <?php foreach ( $platforms as $key => $label ) : ?>
        <tr>
            <th><label for="roci_social_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td><input type="url" name="roci_social[<?php echo esc_attr( $key ); ?>]" id="roci_social_<?php echo esc_attr( $key ); ?>" class="large-text" value="<?php echo esc_attr( isset( $social[ $key ] ) ? $social[ $key ] : '' ); ?>"></td>
        </tr>
    <?php endforeach; ?>
</table>
