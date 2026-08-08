<?php
/**
 * Theme Settings — Sanitize Callbacks
 *
 * File:    inc/theme-settings/settings-sanitize.php
 * Version: 1.5.0
 * Updated: 2026-08-08
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


function roci_sanitize_design( $input ) {
    /*
     * MUST STAY IN SYNC with $color_groups in tabs/tab-design.php. This loop
     * rebuilds the option from scratch and writes '' for every key it lists,
     * and the return replaces the stored row wholesale — so a key rendered by
     * the tab but missing here is wiped on the first save, and a key listed
     * here but not rendered is blanked on every save.
     *
     * Canonical fifteen, hyphenated to match the $color-* SCSS interface,
     * plus the two parent-only background extras.
     */
    $color_fields = array(
        'color-primary', 'color-primary-light', 'color-primary-dark',
        'color-primary-darker', 'color-primary-pale',
        'color-secondary', 'color-secondary-light', 'color-secondary-dark',
        'color-tertiary', 'color-tertiary-accent',
        'color-accent', 'color-eyebrow',
        'color-white', 'color-black', 'color-grey',
        'background', 'background-alt',
    );
    $sanitized = array();
    foreach ( $color_fields as $field ) {
        $sanitized[ $field ] = isset( $input[ $field ] ) ? sanitize_hex_color( $input[ $field ] ) : '';
    }
    $sanitized['body_font']      = isset( $input['body_font'] )      ? sanitize_text_field( $input['body_font'] )      : '';
    $sanitized['heading_font']   = isset( $input['heading_font'] )   ? sanitize_text_field( $input['heading_font'] )   : '';
    $sanitized['base_font_size'] = isset( $input['base_font_size'] ) ? sanitize_text_field( $input['base_font_size'] ) : '';
    $sanitized['header_style']   = isset( $input['header_style'] )   ? sanitize_text_field( $input['header_style'] )   : 'solid';
    $sanitized['sticky_header']  = isset( $input['sticky_header'] )  ? '1' : '0';
    $sanitized['button_style']   = isset( $input['button_style'] )   ? sanitize_text_field( $input['button_style'] )   : 'rounded';
    return $sanitized;
}

function roci_sanitize_business( $input ) {

    /*
     * MUST STAY IN SYNC with tabs/tab-business.php. This function rebuilds the
     * option from scratch and the return replaces the stored row wholesale — a
     * key rendered by the tab but missing here is wiped on the first save, and a
     * key listed here but not rendered is blanked on every save. Same contract
     * roci_sanitize_design() carries.
     */

    /*
     * TYPE — validated against roci_business_types(), NEVER a hardcoded list.
     *
     * Reading the function is what lets a child's roci_business_types filter
     * actually work: a duplicated list here would render a child-added type in
     * the selector and then reject it on save, silently resetting to 'general'.
     * That is precisely the roci_social_platforms bug below, where the tab
     * dispatches a filter the sanitiser never consults.
     *
     * An unknown, unset or empty value resolves to 'general' — the neutral
     * vertical, whose schema type is the LocalBusiness the parent emitted before
     * this map existed. There is no "no type" state.
     */
    $roci_valid_types = array_keys( roci_business_types() );
    $roci_type        = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : '';
    if ( ! in_array( $roci_type, $roci_valid_types, true ) ) {
        $roci_type = 'general';
    }

    /*
     * COORDINATES — stored as a NUMERIC STRING, or '' when absent/invalid.
     *
     * The empty string is load-bearing and must not become 0. Latitude 0 is the
     * equator and longitude 0 is the Greenwich meridian: both are valid, both
     * are falsy, and the emit guard in header.php therefore tests '' !== rather
     * than truthiness. Collapsing "not set" and "zero" into one value here would
     * make that distinction unrecoverable downstream.
     *
     * Out-of-range and non-numeric input is DISCARDED rather than clamped —
     * clamping a typo to 90 would assert a coordinate nobody entered. abs()
     * covers both signs; the ranges are the real ones, not conventions.
     */
    $roci_coord_bounds = array( 'latitude' => 90, 'longitude' => 180 );
    $roci_coords       = array();
    foreach ( $roci_coord_bounds as $roci_coord_key => $roci_coord_max ) {
        $roci_raw = isset( $input[ $roci_coord_key ] ) ? trim( (string) $input[ $roci_coord_key ] ) : '';
        $roci_coords[ $roci_coord_key ] = ( '' !== $roci_raw && is_numeric( $roci_raw ) && abs( floatval( $roci_raw ) ) <= $roci_coord_max )
            ? (string) floatval( $roci_raw )
            : '';
    }

    return array(
        'type'     => $roci_type,
        'name'     => isset( $input['name'] )     ? sanitize_text_field( $input['name'] )     : '',
        'description' => isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '',
        'latitude'    => $roci_coords['latitude'],
        'longitude'   => $roci_coords['longitude'],
        'phone'    => isset( $input['phone'] )    ? sanitize_text_field( $input['phone'] )    : '',
        'email'    => isset( $input['email'] )    ? sanitize_email( $input['email'] )         : '',
        'street'   => sanitize_text_field( $input['street'] ?? '' ),
        'locality' => sanitize_text_field( $input['locality'] ?? '' ),
        'region'   => sanitize_text_field( $input['region'] ?? '' ),
        'postal'   => sanitize_text_field( $input['postal'] ?? '' ),
        'country'  => sanitize_text_field( $input['country'] ?? '' ),
        'whatsapp' => isset( $input['whatsapp'] ) ? sanitize_text_field( $input['whatsapp'] ) : '',
        'maps_url' => isset( $input['maps_url'] ) ? esc_url_raw( $input['maps_url'] )         : '',
        'schema_image' => isset( $input['schema_image'] ) ? esc_url_raw( $input['schema_image'] )      : '',
        'price_range'  => isset( $input['price_range'] )  ? sanitize_text_field( $input['price_range'] ) : '',
    );
}

function roci_sanitize_social( $input ) {
    $platforms = array(
        'facebook', 'instagram', 'whatsapp', 'tiktok',
        'youtube', 'linkedin', 'twitter', 'tripadvisor',
    );
    $sanitized = array();
    foreach ( $platforms as $platform ) {
        $sanitized[ $platform ] = isset( $input[ $platform ] ) ? esc_url_raw( $input[ $platform ] ) : '';
    }
    if ( isset( $input['custom'] ) && is_array( $input['custom'] ) ) {
        foreach ( $input['custom'] as $key => $url ) {
            $sanitized['custom'][ sanitize_key( $key ) ] = esc_url_raw( $url );
        }
    }
    return $sanitized;
}

function roci_sanitize_seo( $input ) {
    return array(
        'default_meta_description' => isset( $input['default_meta_description'] ) ? sanitize_textarea_field( $input['default_meta_description'] ) : '',
        'default_og_image'         => isset( $input['default_og_image'] )         ? esc_url_raw( $input['default_og_image'] )                     : '',
        'seo_preview'              => isset( $input['seo_preview'] )              ? '1'                                                           : '0',
    );
}

function roci_sanitize_integrations( $input ) {
    // These fields output raw <script> verbatim into the head/footer, so an
    // admin with unfiltered_html stores them unfiltered. Lower-privileged
    // users fall back to wp_kses_post() and can never inject scripts.
    $can_raw = current_user_can( 'unfiltered_html' );

    return array(
        'ga_id'                => isset( $input['ga_id'] )                ? sanitize_text_field( $input['ga_id'] )                : '',
        'gtm_id'               => isset( $input['gtm_id'] )               ? sanitize_text_field( $input['gtm_id'] )               : '',
        'fb_pixel_id'          => isset( $input['fb_pixel_id'] )          ? sanitize_text_field( $input['fb_pixel_id'] )          : '',
        'custom_head_script'   => isset( $input['custom_head_script'] )   ? ( $can_raw ? $input['custom_head_script'] : wp_kses_post( $input['custom_head_script'] ) )   : '',
        'custom_footer_script' => isset( $input['custom_footer_script'] ) ? ( $can_raw ? $input['custom_footer_script'] : wp_kses_post( $input['custom_footer_script'] ) ) : '',
    );
}

function roci_sanitize_footer( $input ) {
    return array(
        'tagline'  => isset( $input['tagline'] )  ? sanitize_text_field( $input['tagline'] )      : '',
        'blurb'    => isset( $input['blurb'] )    ? sanitize_textarea_field( $input['blurb'] )    : '',
        'logo_url' => isset( $input['logo_url'] ) ? esc_url_raw( $input['logo_url'] )             : '',
    );
}
