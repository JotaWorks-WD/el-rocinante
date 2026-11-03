<?php
/**
 * Theme Settings — Footer Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * MUST STAY IN SYNC with roci_sanitize_footer() — the sanitiser rebuilds the
 * row from the keys it lists, so a field rendered here but not listed there is
 * wiped on save.
 *
 * v1.2.0: the footer credit (credit, credit_follow — read through
 * roci_footer_credit()), and the Tagline note no longer names a font from
 * another site.
 *
 * File:    inc/theme-settings/tabs/tab-footer.php
 * Version: 1.2.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_footer_group' );
$footer      = get_option( 'roci_footer', array() );
$footer_logo = isset( $footer['logo_url'] ) ? $footer['logo_url'] : '';
?>
<h2 class="roci-section-title"><?php esc_html_e( 'Footer Content', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_footer_tagline"><?php esc_html_e( 'Footer Tagline', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_footer[tagline]" id="roci_footer_tagline" class="regular-text" value="<?php echo esc_attr( isset( $footer['tagline'] ) ? $footer['tagline'] : '' ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Displayed below the logo or brand name in the footer.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_footer_blurb"><?php esc_html_e( 'Footer Blurb', 'rocinante' ); ?></label></th>
        <td>
            <textarea name="roci_footer[blurb]" id="roci_footer_blurb" class="large-text" rows="4"><?php echo esc_textarea( isset( $footer['blurb'] ) ? $footer['blurb'] : '' ); ?></textarea>
            <p class="roci-note"><?php esc_html_e( 'Short description displayed in the footer brand column.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label><?php esc_html_e( 'Footer Logo', 'rocinante' ); ?></label></th>
        <td>
            <div class="roci-media-wrap">
                <img src="<?php echo esc_url( $footer_logo ); ?>" class="roci-media-preview <?php echo $footer_logo ? 'has-image' : ''; ?>" id="roci_footer_logo_preview">
                <input type="hidden" name="roci_footer[logo_url]" id="roci_footer_logo_url" value="<?php echo esc_url( $footer_logo ); ?>">
                <button type="button" class="button button-small roci-media-upload" data-target="roci_footer_logo_url" data-preview="roci_footer_logo_preview"><?php esc_html_e( 'Select Logo', 'rocinante' ); ?></button>
                <?php if ( $footer_logo ) : ?>
                    <button type="button" class="button button-small roci-media-remove" data-target="roci_footer_logo_url" data-preview="roci_footer_logo_preview"><?php esc_html_e( 'Remove', 'rocinante' ); ?></button>
                <?php endif; ?>
            </div>
            <p class="roci-note"><?php esc_html_e( 'Optional separate logo for the footer. Falls back to site logo if empty.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<h2 class="roci-section-title"><?php esc_html_e( 'Footer Credit', 'rocinante' ); ?></h2>
<?php $roci_credit = isset( $footer['credit'] ) ? $footer['credit'] : 'none'; ?>
<table class="form-table">
    <tr>
        <th><label for="roci_footer_credit"><?php esc_html_e( 'Credit', 'rocinante' ); ?></label></th>
        <td>
            <select name="roci_footer[credit]" id="roci_footer_credit">
                <?php foreach ( roci_footer_credit_options() as $roci_value => $roci_label ) : ?>
                    <option value="<?php echo esc_attr( $roci_value ); ?>" <?php selected( $roci_credit, $roci_value ); ?>><?php echo esc_html( $roci_label ); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="roci-note"><?php esc_html_e( 'A credit line linking to https://jota.works, printed after the copyright line. A theme that overrides the footer part shows it only where it calls roci_footer_credit().', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Credit Link', 'rocinante' ); ?></th>
        <td>
            <label for="roci_footer_credit_follow">
                <input type="checkbox" name="roci_footer[credit_follow]" id="roci_footer_credit_follow" value="1" <?php checked( ! empty( $footer['credit_follow'] ) ); ?>>
                <?php esc_html_e( 'Followed link (no rel="nofollow")', 'rocinante' ); ?>
            </label>
            <p class="roci-note"><?php esc_html_e( 'Off by default: the credit link carries rel="nofollow".', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>
