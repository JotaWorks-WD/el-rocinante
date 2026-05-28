<?php
/**
 * Theme Settings — Sanitize Callbacks
 *
 * File:    inc/theme-settings/settings-sanitize.php
 * Version: 1.1.2
 * Updated: 2026-05-28
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


function roci_sanitize_design( $input ) {
    $color_fields = array(
        'primary', 'primary_accent',
        'secondary', 'secondary_accent',
        'tertiary', 'tertiary_accent',
        'black', 'grey', 'white',
        'background', 'background_alt',
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
    return array(
        'name'     => isset( $input['name'] )     ? sanitize_text_field( $input['name'] )        : '',
        'phone'    => isset( $input['phone'] )    ? sanitize_text_field( $input['phone'] )       : '',
        'email'    => isset( $input['email'] )    ? sanitize_email( $input['email'] )            : '',
        'address'  => isset( $input['address'] )  ? sanitize_textarea_field( $input['address'] ) : '',
        'whatsapp' => isset( $input['whatsapp'] ) ? sanitize_text_field( $input['whatsapp'] )    : '',
        'maps_url' => isset( $input['maps_url'] ) ? esc_url_raw( $input['maps_url'] )            : '',
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
