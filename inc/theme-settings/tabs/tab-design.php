<?php
/**
 * Theme Settings — Design Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-design.php
 * Version: 1.2.0
 * Updated: 2026-07-26
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_design_group' );

/*
 * TWO SOURCES, AND THEY MUST NOT BE MERGED.
 *
 * $design is the STORED option and is the only thing that feeds the `value`
 * attribute below. $mirror is the canonical palette, which equals the stored
 * option on a dashboard-configured site but carries a code-supplied brand on a
 * child that fills roci_brand_palette in PHP. It feeds `data-default-color`
 * only.
 *
 * The mirrored value must NEVER reach `value`. If it did, the next save of this
 * tab would persist an inherited colour into roci_design as if a human had typed
 * it — freezing a copy that then drifts as the child's filter moves on, which is
 * precisely the two-sources problem the palette resolver exists to prevent.
 */
$design = get_option( 'roci_design', array() );
$mirror = roci_brand_palette();

$color_groups = array(
    array(
        'title'  => __( 'Primary', 'rocinante' ),
        'fields' => array(
            'primary'        => __( 'Primary', 'rocinante' ),
            'primary_accent' => __( 'Primary Accent', 'rocinante' ),
        ),
    ),
    array(
        'title'  => __( 'Secondary', 'rocinante' ),
        'fields' => array(
            'secondary'        => __( 'Secondary', 'rocinante' ),
            'secondary_accent' => __( 'Secondary Accent', 'rocinante' ),
        ),
    ),
    array(
        'title'  => __( 'Tertiary', 'rocinante' ),
        'fields' => array(
            'tertiary'        => __( 'Tertiary', 'rocinante' ),
            'tertiary_accent' => __( 'Tertiary Accent', 'rocinante' ),
        ),
    ),
    array(
        'title'  => __( 'Neutrals', 'rocinante' ),
        'fields' => array(
            'black' => __( 'Black', 'rocinante' ),
            'grey'  => __( 'Grey', 'rocinante' ),
            'white' => __( 'White', 'rocinante' ),
        ),
    ),
    array(
        'title'  => __( 'Backgrounds', 'rocinante' ),
        'fields' => array(
            'background'     => __( 'Background', 'rocinante' ),
            'background_alt' => __( 'Alternate Background', 'rocinante' ),
        ),
    ),
);
?>

<h2 class="roci-section-title"><?php esc_html_e( 'Colors', 'rocinante' ); ?></h2>
<?php foreach ( $color_groups as $group ) : ?>
    <p style="font-weight:600;margin-bottom:8px;"><?php echo esc_html( $group['title'] ); ?></p>
    <div class="roci-color-row" style="margin-bottom:24px;">
        <?php
        foreach ( $group['fields'] as $key => $label ) :

            $roci_stored   = isset( $design[ $key ] ) ? $design[ $key ] : '';
            $roci_mirrored = isset( $mirror[ $key ] ) ? $mirror[ $key ] : '';

            /*
             * A field is "mirrored" when nothing is stored but the palette has a
             * value — i.e. the colour is supplied in code. Such a field displays
             * the inherited colour and is not editable here, because the
             * dashboard is not its owner. A field with a real stored value stays
             * a normal, editable picker.
             */
            $roci_is_mirrored = ( '' === $roci_stored && '' !== $roci_mirrored );
            ?>
            <div class="roci-color-field">
                <label for="roci_color_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
                <?php
                /*
                 * value= READS THE STORED OPTION ONLY — never $roci_mirrored.
                 *
                 * readonly, NEVER disabled, and the field is never omitted. A
                 * disabled or absent field is not submitted, and
                 * roci_sanitize_design() rebuilds the option from scratch on
                 * every save, writing '' for any key missing from $_POST — so
                 * dropping these fields would wipe all eleven stored colours the
                 * next time anyone saved this tab, including a save that touched
                 * only the font settings. readonly still submits, so the stored
                 * value round-trips untouched and a future dashboard write flows
                 * through the same name attribute.
                 */
                ?>
                <input type="text"
                       name="roci_design[<?php echo esc_attr( $key ); ?>]"
                       id="roci_color_<?php echo esc_attr( $key ); ?>"
                       class="roci-color-picker"
                       value="<?php echo esc_attr( $roci_stored ); ?>"
                       data-default-color="<?php echo esc_attr( $roci_mirrored ); ?>"
                       <?php echo $roci_is_mirrored ? 'data-roci-mirrored="1" readonly' : ''; ?>>
                <?php if ( $roci_is_mirrored ) : ?>
                    <p class="roci-note"><?php esc_html_e( 'Set in code', 'rocinante' ); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<h2 class="roci-section-title"><?php esc_html_e( 'Typography', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_body_font"><?php esc_html_e( 'Body Font', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_design[body_font]" id="roci_body_font" class="regular-text" value="<?php echo esc_attr( isset( $design['body_font'] ) ? $design['body_font'] : '' ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Google Font name e.g. "DM Sans". Leave blank to use theme default.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_heading_font"><?php esc_html_e( 'Heading Font', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_design[heading_font]" id="roci_heading_font" class="regular-text" value="<?php echo esc_attr( isset( $design['heading_font'] ) ? $design['heading_font'] : '' ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Google Font name e.g. "Playfair Display". Leave blank to use theme default.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_base_font_size"><?php esc_html_e( 'Base Font Size', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_design[base_font_size]" id="roci_base_font_size" class="small-text" value="<?php echo esc_attr( isset( $design['base_font_size'] ) ? $design['base_font_size'] : '' ); ?>">
            <p class="roci-note"><?php esc_html_e( 'e.g. 17px. Leave blank to use theme default.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<h2 class="roci-section-title"><?php esc_html_e( 'Header', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_header_style"><?php esc_html_e( 'Header Style', 'rocinante' ); ?></label></th>
        <td>
            <select name="roci_design[header_style]" id="roci_header_style">
                <option value="solid" <?php selected( isset( $design['header_style'] ) ? $design['header_style'] : 'solid', 'solid' ); ?>><?php esc_html_e( 'Solid', 'rocinante' ); ?></option>
                <option value="transparent" <?php selected( isset( $design['header_style'] ) ? $design['header_style'] : 'solid', 'transparent' ); ?>><?php esc_html_e( 'Transparent (overlays hero)', 'rocinante' ); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th><label for="roci_sticky_header"><?php esc_html_e( 'Sticky Header', 'rocinante' ); ?></label></th>
        <td>
            <input type="checkbox" name="roci_design[sticky_header]" id="roci_sticky_header" value="1" <?php checked( isset( $design['sticky_header'] ) ? $design['sticky_header'] : '0', '1' ); ?>>
            <label for="roci_sticky_header"><?php esc_html_e( 'Enable sticky header on scroll', 'rocinante' ); ?></label>
            <p class="roci-note"><?php _e( 'Outputs body class <code>has-sticky-nav</code>. Sticky behavior is implemented per child theme.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<h2 class="roci-section-title"><?php esc_html_e( 'Buttons', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_button_style"><?php esc_html_e( 'Button Style', 'rocinante' ); ?></label></th>
        <td>
            <select name="roci_design[button_style]" id="roci_button_style">
                <option value="rounded" <?php selected( isset( $design['button_style'] ) ? $design['button_style'] : 'rounded', 'rounded' ); ?>><?php esc_html_e( 'Rounded', 'rocinante' ); ?></option>
                <option value="sharp"   <?php selected( isset( $design['button_style'] ) ? $design['button_style'] : 'rounded', 'sharp' ); ?>><?php esc_html_e( 'Sharp', 'rocinante' ); ?></option>
                <option value="pill"    <?php selected( isset( $design['button_style'] ) ? $design['button_style'] : 'rounded', 'pill' ); ?>><?php esc_html_e( 'Pill', 'rocinante' ); ?></option>
            </select>
        </td>
    </tr>
</table>
