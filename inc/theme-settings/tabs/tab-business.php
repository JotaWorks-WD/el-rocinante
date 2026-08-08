<?php
/**
 * Theme Settings — Business Tab
 *
 * Included by settings-page.php inside roci_settings_page().
 *
 * File:    inc/theme-settings/tabs/tab-business.php
 * Version: 1.6.0
 * Updated: 2026-08-08
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_business_group' );

/*
 * THIS TAB AND roci_sanitize_business() IN settings-sanitize.php MUST STAY IN
 * SYNC. The sanitiser rebuilds the option from scratch on every save and returns
 * it wholesale, so a key rendered here but missing there is SILENTLY WIPED the
 * first time anyone saves this tab — no error, no warning, the field simply
 * empties. A key listed there but not rendered here is blanked on every save for
 * the same reason: an unrendered field submits nothing.
 *
 * Add a field here and add it there, in the same commit. Always.
 */
$business = get_option( 'roci_business', array() );

/*
 * The type map is the single source for the selector below, for the sanitiser's
 * validation, and for header.php's @type resolution. Read the function — never
 * copy the list — so a child's roci_business_types filter reaches all three.
 */
$roci_business_type_map = roci_business_types();
$roci_biz_type          = isset( $business['type'] ) && '' !== $business['type'] ? $business['type'] : 'general';
?>
<h2 class="roci-section-title"><?php esc_html_e( 'Business Type', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_biz_type"><?php esc_html_e( 'Business Type', 'rocinante' ); ?></label></th>
        <td>
            <select name="roci_business[type]" id="roci_biz_type">
                <?php foreach ( $roci_business_type_map as $roci_type_slug => $roci_type_def ) : ?>
                    <option value="<?php echo esc_attr( $roci_type_slug ); ?>" <?php selected( $roci_biz_type, $roci_type_slug ); ?>>
                        <?php echo esc_html( $roci_type_def['label'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="roci-note"><?php esc_html_e( 'Sets the schema.org type for this site\'s structured data. Choose the closest match to what the business actually is.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<h2 class="roci-section-title"><?php esc_html_e( 'Business Information', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_biz_name"><?php esc_html_e( 'Business Name', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[name]" id="roci_biz_name" class="regular-text" value="<?php echo esc_attr( isset( $business['name'] ) ? $business['name'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_description"><?php esc_html_e( 'Business Description', 'rocinante' ); ?></label></th>
        <td>
            <textarea name="roci_business[description]" id="roci_biz_description" class="large-text" rows="4"><?php echo esc_textarea( isset( $business['description'] ) ? $business['description'] : '' ); ?></textarea>
            <p class="roci-note"><?php esc_html_e( 'Optional. One or two sentences describing the business, used in the structured data. Leave blank to omit.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_biz_phone"><?php esc_html_e( 'Phone Number', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[phone]" id="roci_biz_phone" class="regular-text" value="<?php echo esc_attr( isset( $business['phone'] ) ? $business['phone'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_email"><?php esc_html_e( 'Email Address', 'rocinante' ); ?></label></th>
        <td><input type="email" name="roci_business[email]" id="roci_biz_email" class="regular-text" value="<?php echo esc_attr( isset( $business['email'] ) ? $business['email'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_street"><?php esc_html_e( 'Street Address', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[street]" id="roci_biz_street" class="large-text" value="<?php echo esc_attr( isset( $business['street'] ) ? $business['street'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_locality"><?php esc_html_e( 'City / Locality', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[locality]" id="roci_biz_locality" class="large-text" value="<?php echo esc_attr( isset( $business['locality'] ) ? $business['locality'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_region"><?php esc_html_e( 'State / Province / Region', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[region]" id="roci_biz_region" class="large-text" value="<?php echo esc_attr( isset( $business['region'] ) ? $business['region'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_postal"><?php esc_html_e( 'Postal Code', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[postal]" id="roci_biz_postal" class="large-text" value="<?php echo esc_attr( isset( $business['postal'] ) ? $business['postal'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_country"><?php esc_html_e( 'Country (2-letter ISO, e.g. CR)', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[country]" id="roci_biz_country" class="large-text" value="<?php echo esc_attr( isset( $business['country'] ) ? $business['country'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_latitude"><?php esc_html_e( 'Latitude (decimal, e.g. 10.4406)', 'rocinante' ); ?></label></th>
        <td><input type="text" name="roci_business[latitude]" id="roci_biz_latitude" class="regular-text" value="<?php echo esc_attr( isset( $business['latitude'] ) ? $business['latitude'] : '' ); ?>"></td>
    </tr>
    <tr>
        <th><label for="roci_biz_longitude"><?php esc_html_e( 'Longitude (decimal, e.g. -85.7920)', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_business[longitude]" id="roci_biz_longitude" class="regular-text" value="<?php echo esc_attr( isset( $business['longitude'] ) ? $business['longitude'] : '' ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Both coordinates are required — a single one emits nothing. Decimal degrees only, not degrees/minutes/seconds. Anything out of range (-90 to 90, -180 to 180) is discarded on save.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_biz_whatsapp"><?php esc_html_e( 'WhatsApp Number', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_business[whatsapp]" id="roci_biz_whatsapp" class="regular-text" value="<?php echo esc_attr( isset( $business['whatsapp'] ) ? $business['whatsapp'] : '' ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Include country code, no spaces or symbols. e.g. 50688887777', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_biz_maps"><?php esc_html_e( 'Google Maps Embed URL', 'rocinante' ); ?></label></th>
        <td>
            <input type="url" name="roci_business[maps_url]" id="roci_biz_maps" class="large-text" value="<?php echo esc_attr( isset( $business['maps_url'] ) ? $business['maps_url'] : '' ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Paste the src URL from your Google Maps embed code.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <?php $roci_biz_schema_img = isset( $business['schema_image'] ) ? $business['schema_image'] : ''; ?>
    <tr>
        <th><label><?php esc_html_e( 'Schema Image (LocalBusiness)', 'rocinante' ); ?></label></th>
        <td>
            <div class="roci-media-wrap">
                <img src="<?php echo esc_url( $roci_biz_schema_img ); ?>" class="roci-media-preview <?php echo $roci_biz_schema_img ? 'has-image' : ''; ?>" id="roci_biz_schema_image_preview">
                <input type="hidden" name="roci_business[schema_image]" id="roci_biz_schema_image" value="<?php echo esc_url( $roci_biz_schema_img ); ?>">
                <button type="button" class="button button-small roci-media-upload" data-target="roci_biz_schema_image" data-preview="roci_biz_schema_image_preview"><?php esc_html_e( 'Select Image', 'rocinante' ); ?></button>
                <?php if ( $roci_biz_schema_img ) : ?>
                    <button type="button" class="button button-small roci-media-remove" data-target="roci_biz_schema_image" data-preview="roci_biz_schema_image_preview"><?php esc_html_e( 'Remove', 'rocinante' ); ?></button>
                <?php endif; ?>
            </div>
            <p class="roci-note"><?php esc_html_e( 'Raster image (PNG/JPG/WebP) for the site\'s LocalBusiness structured data. Recommended for rich results.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_biz_price_range"><?php esc_html_e( 'Price Range', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_business[price_range]" id="roci_biz_price_range" class="large-text" value="<?php echo esc_attr( isset( $business['price_range'] ) ? $business['price_range'] : '' ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Optional. e.g. $$, $$$, or a range like $150–$400. Leave blank if not applicable.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<?php
/*
 * PER-TYPE FIELD GROUPS — ALWAYS RENDERED, VISIBILITY TOGGLED BY JS ALONE.
 *
 * ⚠ NEVER WRAP THESE IN A PHP CONDITIONAL ON THE SELECTED TYPE. A field that is
 * not rendered is not submitted, and roci_sanitize_business() rebuilds the whole
 * option row from $_POST — so an unrendered field is stored as ''. Rendering
 * only the active type's group would therefore DESTROY every other type's saved
 * values the moment anyone pressed Save, silently, with no error. Switch to
 * Restaurant, save, switch back to Lodging: the lodging fields are gone.
 *
 * Every group is in the DOM on every request and submits on every save.
 * dist/js/theme-settings.js hides the inactive ones by ADDING .is-hidden, and
 * .roci-type-group carries no hiding of its own — so if that script fails to
 * load, every group is visible. That is the safe failure direction: the admin
 * sees fields that do not apply, rather than losing data they cannot see.
 *
 * Only types with a non-empty 'fields' array get a group. general, tourism and
 * restaurant currently declare none, so they render nothing here — correct,
 * since there is nothing to show or hide for them.
 */
foreach ( $roci_business_type_map as $roci_type_slug => $roci_type_def ) :

    if ( empty( $roci_type_def['fields'] ) ) {
        continue;
    }
    ?>
    <div class="roci-type-group" data-type="<?php echo esc_attr( $roci_type_slug ); ?>">

        <h2 class="roci-section-title">
            <?php
            /* translators: %s: business type label, e.g. "Lodging / Rental". */
            printf( esc_html__( '%s Details', 'rocinante' ), esc_html( $roci_type_def['label'] ) );
            ?>
        </h2>

        <?php
        /*
         * FIELDS ARE GATED ON THE MAP'S DECLARATION, NOT ON THE SELECTED TYPE.
         *
         * This is NOT the hazard described above and must not be confused with
         * it. in_array( …, $roci_type_def['fields'] ) asks "does this vertical
         * declare this field", which is a static property of the map — it
         * evaluates identically on every request regardless of what the admin
         * has selected. Every group still renders every field it declares, on
         * every request, and every one of them still submits.
         *
         * The forbidden thing is gating on $roci_biz_type, the SAVED value.
         * That is what would stop a field submitting and let a save blank it.
         *
         * Gating this way also means a child that registers a vertical
         * declaring 'numberOfRooms' gets this input for free, with no edit here.
         */
        ?>
        <table class="form-table">

            <?php if ( in_array( 'numberOfRooms', $roci_type_def['fields'], true ) ) : ?>
            <tr>
                <th><label for="roci_biz_number_of_rooms"><?php esc_html_e( 'Number of Rooms', 'rocinante' ); ?></label></th>
                <td>
                    <input type="number" name="roci_business[numberOfRooms]" id="roci_biz_number_of_rooms" class="small-text" min="0" step="1" value="<?php echo esc_attr( isset( $business['numberOfRooms'] ) ? $business['numberOfRooms'] : '' ); ?>">
                    <p class="roci-note"><?php esc_html_e( 'Number of guest rooms or units. Leave blank to omit — a blank field emits nothing, it does not emit zero.', 'rocinante' ); ?></p>
                </td>
            </tr>
            <?php endif; ?>

            <?php if ( in_array( 'petsAllowed', $roci_type_def['fields'], true ) ) : ?>
            <tr>
                <th><label for="roci_biz_pets_allowed"><?php esc_html_e( 'Pets Allowed', 'rocinante' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="roci_business[petsAllowed]" id="roci_biz_pets_allowed" value="1" <?php checked( isset( $business['petsAllowed'] ) ? $business['petsAllowed'] : '0', '1' ); ?>>
                        <?php esc_html_e( 'Pets are allowed', 'rocinante' ); ?>
                    </label>
                    <p class="roci-note"><?php esc_html_e( 'Unlike the other fields, this one always emits: ticked publishes "pets allowed", unticked publishes "pets not allowed". There is no blank state — a checkbox cannot express one.', 'rocinante' ); ?></p>
                </td>
            </tr>
            <?php endif; ?>

        </table>

        <?php
        /*
         * PHASE 4 PLACEHOLDER — amenities only.
         *
         * amenityFeature is a repeatable array of LocationFeatureSpecification
         * objects, which the flat one-key-one-value pattern above cannot carry.
         * Phase 4 replaces this paragraph, and whatever it ships must be added
         * to roci_sanitize_business() in the same commit — see the sync warning
         * at the top of this file.
         */
        if ( in_array( 'amenities', $roci_type_def['fields'], true ) ) :
            ?>
            <p class="roci-note"><?php esc_html_e( 'Amenities — repeatable field, Phase 4. Not built yet.', 'rocinante' ); ?></p>
            <?php
        endif;
        ?>

    </div>
    <?php

endforeach;
?>
