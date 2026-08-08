<?php
/**
 * Theme Settings — Social Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-social.php
 * Version: 1.2.0
 * Updated: 2026-08-08
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_social_group' );
$social = get_option( 'roci_social', array() );

/*
 * The platform list is read from the shared getter, never declared here.
 *
 * It used to be an inline apply_filters() in this file, which is exactly why
 * the filter did not work: roci_sanitize_social() and header.php could not
 * reach the filtered result, so both carried hardcoded copies and a
 * child-registered platform was wiped on save. inc/schema/social-platforms.php
 * is now the single source all three read.
 */
$platforms = roci_social_platforms();
?>
<h2 class="roci-section-title"><?php esc_html_e( 'Social Profiles', 'rocinante' ); ?></h2>
<p class="roci-note" style="margin-bottom:16px;"><?php _e( 'Child themes can add additional platforms via the <code>roci_social_platforms</code> filter.', 'rocinante' ); ?></p>
<table class="form-table">
    <?php foreach ( $platforms as $key => $label ) : ?>
        <tr>
            <th><label for="roci_social_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td><input type="url" name="roci_social[<?php echo esc_attr( $key ); ?>]" id="roci_social_<?php echo esc_attr( $key ); ?>" class="large-text" value="<?php echo esc_attr( isset( $social[ $key ] ) ? $social[ $key ] : '' ); ?>"></td>
        </tr>
    <?php endforeach; ?>
</table>
