<?php
/**
 * Theme Settings — Site Identity Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-identity.php
 * Version: 1.1.1
 * Updated: 2026-05-10
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_identity_group' );

// Read from WP core options — same as Customizer
$site_title  = get_option( 'blogname', '' );
$tagline     = get_option( 'blogdescription', '' );
$site_icon   = get_option( 'site_icon', 0 );
$icon_url    = $site_icon ? wp_get_attachment_image_url( $site_icon, 'thumbnail' ) : '';

// Custom logo — WP stores attachment ID as theme mod
$logo_id  = get_theme_mod( 'custom_logo', 0 );
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
?>

<p class="roci-sync-note">&#8505; These settings are synced with Appearance → Customize → Site Identity. Changes here update both locations automatically.</p>

<h2 class="roci-section-title"><?php _e( 'Site Information', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_blogname"><?php _e( 'Site Title', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="blogname" id="roci_blogname" class="regular-text" value="<?php echo esc_attr( $site_title ); ?>">
            <p class="roci-note"><?php _e( 'Used in browser tab, SEO title tags, and schema markup.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_blogdescription"><?php _e( 'Tagline', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="blogdescription" id="roci_blogdescription" class="regular-text" value="<?php echo esc_attr( $tagline ); ?>">
            <p class="roci-note"><?php _e( 'Displayed in some themes and used in schema markup.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<h2 class="roci-section-title"><?php _e( 'Logo & Icon', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label><?php _e( 'Site Logo', 'rocinante' ); ?></label></th>
        <td>
            <div class="roci-media-wrap">
                <img src="<?php echo esc_url( $logo_url ); ?>" class="roci-media-preview <?php echo $logo_url ? 'has-image' : ''; ?>" id="roci_logo_preview">
                <input type="hidden" name="custom_logo_id" id="roci_logo_id" value="<?php echo esc_attr( $logo_id ); ?>">
                <input type="hidden" name="custom_logo_url" id="roci_logo_url" value="<?php echo esc_url( $logo_url ); ?>">
                <button type="button" class="button button-small roci-logo-upload"><?php _e( 'Select Logo', 'rocinante' ); ?></button>
                <?php if ( $logo_url ) : ?>
                    <button type="button" class="button button-small roci-logo-remove"><?php _e( 'Remove', 'rocinante' ); ?></button>
                <?php endif; ?>
            </div>
            <p class="roci-note"><?php _e( 'Recommended: SVG or PNG with transparent background.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label><?php _e( 'Site Icon (Favicon)', 'rocinante' ); ?></label></th>
        <td>
            <div class="roci-media-wrap">
                <img src="<?php echo esc_url( $icon_url ); ?>" class="roci-media-preview <?php echo $icon_url ? 'has-image' : ''; ?>" id="roci_icon_preview">
                <input type="hidden" name="site_icon" id="roci_site_icon_id" value="<?php echo esc_attr( $site_icon ); ?>">
                <button type="button" class="button button-small roci-icon-upload"><?php _e( 'Select Icon', 'rocinante' ); ?></button>
                <?php if ( $icon_url ) : ?>
                    <button type="button" class="button button-small roci-icon-remove"><?php _e( 'Remove', 'rocinante' ); ?></button>
                <?php endif; ?>
            </div>
            <p class="roci-note"><?php _e( 'Square PNG or SVG, minimum 512×512px. WordPress generates all favicon sizes automatically.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<?php
// Handle custom_logo separately — it's a theme mod not a WP option
// We process it via a custom action after options.php saves
?>
<script>
jQuery(document).ready(function($) {

    // Logo upload
    var logoFrame;
    $('.roci-logo-upload').on('click', function(e) {
        e.preventDefault();
        if ( logoFrame ) { logoFrame.open(); return; }
        logoFrame = wp.media({ title: 'Select Logo', multiple: false, library: { type: 'image' } });
        logoFrame.on('select', function() {
            var att = logoFrame.state().get('selection').first().toJSON();
            $('#roci_logo_id').val(att.id);
            $('#roci_logo_url').val(att.url);
            $('#roci_logo_preview').attr('src', att.url).addClass('has-image');
        });
        logoFrame.open();
    });

    $('.roci-logo-remove').on('click', function(e) {
        e.preventDefault();
        $('#roci_logo_id').val('');
        $('#roci_logo_url').val('');
        $('#roci_logo_preview').attr('src', '').removeClass('has-image');
    });

    // Icon upload
    var iconFrame;
    $('.roci-icon-upload').on('click', function(e) {
        e.preventDefault();
        if ( iconFrame ) { iconFrame.open(); return; }
        iconFrame = wp.media({ title: 'Select Site Icon', multiple: false, library: { type: 'image' } });
        iconFrame.on('select', function() {
            var att = iconFrame.state().get('selection').first().toJSON();
            $('#roci_site_icon_id').val(att.id);
            $('#roci_icon_preview').attr('src', att.url).addClass('has-image');
        });
        iconFrame.open();
    });

    $('.roci-icon-remove').on('click', function(e) {
        e.preventDefault();
        $('#roci_site_icon_id').val('');
        $('#roci_icon_preview').attr('src', '').removeClass('has-image');
    });

});
</script>
