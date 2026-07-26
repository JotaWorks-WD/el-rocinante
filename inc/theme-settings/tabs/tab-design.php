<?php
/**
 * Theme Settings — Design Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-design.php
 * Version: 2.0.0
 * Updated: 2026-07-26
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_design_group' );

/*
 * TWO SOURCES, AND THEY MUST NOT BE MERGED.
 *
 * $mirror is the canonical palette and drives everything VISIBLE — it is what
 * every consumer actually resolves, so it is the only honest thing to display.
 *
 * $design is the raw stored option and drives only the hidden inputs that carry
 * values back through a save. It is never displayed.
 *
 * The palette value must NEVER reach a submitting input. If it did, the next
 * save of this tab would persist a code-supplied colour into roci_design as if a
 * human had typed it — freezing a copy that then drifts as the child's filter
 * moves on, which is precisely the two-sources problem the palette resolver
 * exists to prevent.
 */
$design = get_option( 'roci_design', array() );
$mirror = roci_brand_palette();

/*
 * Keys are the CANONICAL $color-* names with the $ dropped, hyphens intact, so
 * the option vocabulary and the SCSS interface are spelled identically — no
 * translation layer, no drift. Fifteen canonical slots plus two parent-only
 * background extras the canonical set does not cover.
 *
 * THIS LIST AND $color_fields IN settings-sanitize.php MUST STAY IN SYNC. The
 * sanitiser rebuilds the option from scratch on every save and writes '' for
 * any key it does not list, so a key present here and missing there is silently
 * wiped the first time anyone saves this tab.
 */
$color_groups = array(
    array(
        'title'  => __( 'Primary', 'rocinante' ),
        'fields' => array(
            'color-primary'        => __( 'Primary', 'rocinante' ),
            'color-primary-light'  => __( 'Primary Light', 'rocinante' ),
            'color-primary-dark'   => __( 'Primary Dark', 'rocinante' ),
            'color-primary-darker' => __( 'Primary Darker', 'rocinante' ),
            'color-primary-pale'   => __( 'Primary Pale', 'rocinante' ),
        ),
    ),
    array(
        'title'  => __( 'Secondary', 'rocinante' ),
        'fields' => array(
            'color-secondary'       => __( 'Secondary', 'rocinante' ),
            'color-secondary-light' => __( 'Secondary Light', 'rocinante' ),
            'color-secondary-dark'  => __( 'Secondary Dark', 'rocinante' ),
        ),
    ),
    array(
        'title'  => __( 'Tertiary', 'rocinante' ),
        'fields' => array(
            'color-tertiary'        => __( 'Tertiary', 'rocinante' ),
            'color-tertiary-accent' => __( 'Tertiary Accent', 'rocinante' ),
        ),
    ),
    array(
        'title'  => __( 'Accent & Eyebrow', 'rocinante' ),
        'fields' => array(
            'color-accent'  => __( 'Accent', 'rocinante' ),
            'color-eyebrow' => __( 'Eyebrow', 'rocinante' ),
        ),
    ),
    array(
        'title'  => __( 'Neutrals', 'rocinante' ),
        'fields' => array(
            'color-black' => __( 'Black', 'rocinante' ),
            'color-grey'  => __( 'Grey', 'rocinante' ),
            'color-white' => __( 'White', 'rocinante' ),
        ),
    ),
    array(
        // Parent-only extras. Not part of the canonical fifteen — the canonical
        // vocabulary has no surface/background concept — but kept because they
        // describe something real the standard does not cover.
        'title'  => __( 'Backgrounds', 'rocinante' ),
        'fields' => array(
            'background'     => __( 'Background', 'rocinante' ),
            'background-alt' => __( 'Alternate Background', 'rocinante' ),
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
             * ALL FIELDS ARE LOCKED. Brand is code-owned across the network, so
             * every colour here is a static display — no picker, no editing, no
             * per-field detection. The dashboard reports what the brand IS; it
             * does not own it. An editable mode is a deliberate future
             * per-client build, not a toggle hiding in this loop.
             *
             * DISPLAY reads the palette. roci_brand_palette() seeds from this
             * same option and then applies the child filter, so $roci_mirrored
             * is the value every consumer actually resolves — the Fauxlders
             * highlight, the Brand Colors scheme, both brand readers. Showing
             * anything else would put a number on screen that nothing uses.
             *
             * The two background keys are not in the palette at all (they are
             * parent-only extras outside the canonical fifteen), so they fall
             * back to the stored value rather than rendering permanently empty.
             */
            $roci_display = '' !== $roci_mirrored ? $roci_mirrored : $roci_stored;

            /*
             * DISPLAY-ONLY HEX GATE. sanitize_hex_color() returns null for
             * anything that is not a bare hex, which is exactly what we want
             * before interpolating into a style attribute: esc_attr() stops the
             * value escaping the attribute but happily passes ';' and ':'
             * through, so an unvalidated value could inject further CSS
             * declarations. A non-hex value (a filter returning rgb(), a named
             * colour, a var()) renders as "not set" here while continuing to
             * work everywhere else — a display limitation, deliberately, and
             * never applied to the stored value below.
             */
            $roci_swatch = $roci_display ? sanitize_hex_color( $roci_display ) : null;
            ?>
            <div class="roci-color-field">
                <span class="roci-color-label"><?php echo esc_html( $label ); ?></span>

                <?php if ( $roci_swatch ) : ?>
                    <span class="roci-swatch" style="background-color:<?php echo esc_attr( $roci_swatch ); ?>"></span>
                    <code class="roci-hex"><?php echo esc_html( $roci_swatch ); ?></code>
                    <p class="roci-note"><?php esc_html_e( 'Set in code', 'rocinante' ); ?></p>
                <?php else : ?>
                    <span class="roci-swatch roci-swatch--empty"></span>
                    <code class="roci-hex roci-hex--empty"><?php esc_html_e( 'Not set', 'rocinante' ); ?></code>
                    <p class="roci-note"><?php esc_html_e( 'Not set in code', 'rocinante' ); ?></p>
                <?php endif; ?>

                <?php
                /*
                 * LOAD-BEARING, NOT VESTIGIAL. DO NOT REMOVE.
                 *
                 * roci_sanitize_design() rebuilds roci_design from scratch on
                 * every save and writes '' for any key absent from $_POST, and
                 * its return replaces the stored row wholesale. This tab also
                 * carries six editable NON-colour fields — the two fonts, base
                 * font size, header style, sticky header, button style — sharing
                 * one form and one Save button. So an admin who edits a font and
                 * saves triggers a full roci_design write while every colour
                 * field is static. Without this input the colour keys would be
                 * absent from that POST and all seventeen stored colours would be
                 * wiped, silently, with the swatches still showing the filter's
                 * colours so nothing on screen would look wrong.
                 *
                 * hidden rather than readonly: a readonly text input still
                 * renders the empty box this presentation exists to remove.
                 *
                 * It carries $roci_stored, NOT $roci_display. Persisting the
                 * palette value would freeze a copy into the option that then
                 * drifts as the child's filter moves on — the two-sources bug the
                 * palette resolver exists to prevent.
                 */
                ?>
                <input type="hidden"
                       name="roci_design[<?php echo esc_attr( $key ); ?>]"
                       value="<?php echo esc_attr( $roci_stored ); ?>">
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
