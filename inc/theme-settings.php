<?php
/**
 * Theme Settings — Admin Settings Page
 *
 * Registers and renders the El Rocinante Theme Settings page
 * in the WordPress admin. Replaces all Customizer panels.
 * Settings stored as option arrays: get_option('roci_{tab}').
 * Use roci_setting('group', 'key') helper throughout the theme.
 *
 * Tabs: Site Identity, Design, Business, Social, SEO,
 *       Integrations, Footer
 *
 * File:    inc/theme-settings.php
 * Version: 1.0.0
 * Updated: 2026-05-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// REGISTER SETTINGS
// ============================================================

function roci_register_settings() {

    register_setting( 'roci_identity_group',     'roci_identity',     array( 'sanitize_callback' => 'roci_sanitize_identity'     ) );
    register_setting( 'roci_design_group',       'roci_design',       array( 'sanitize_callback' => 'roci_sanitize_design'       ) );
    register_setting( 'roci_business_group',     'roci_business',     array( 'sanitize_callback' => 'roci_sanitize_business'     ) );
    register_setting( 'roci_social_group',       'roci_social',       array( 'sanitize_callback' => 'roci_sanitize_social'       ) );
    register_setting( 'roci_seo_group',          'roci_seo',          array( 'sanitize_callback' => 'roci_sanitize_seo'          ) );
    register_setting( 'roci_integrations_group', 'roci_integrations', array( 'sanitize_callback' => 'roci_sanitize_integrations' ) );
    register_setting( 'roci_footer_group',       'roci_footer',       array( 'sanitize_callback' => 'roci_sanitize_footer'       ) );

}
add_action( 'admin_init', 'roci_register_settings' );


// ============================================================
// SANITIZE CALLBACKS
// ============================================================

function roci_sanitize_identity( $input ) {
    return array(
        'site_logo'  => isset( $input['site_logo'] )  ? esc_url_raw( $input['site_logo'] )          : '',
        'site_icon'  => isset( $input['site_icon'] )  ? esc_url_raw( $input['site_icon'] )          : '',
        'site_title' => isset( $input['site_title'] ) ? sanitize_text_field( $input['site_title'] ) : '',
        'tagline'    => isset( $input['tagline'] )    ? sanitize_text_field( $input['tagline'] )    : '',
    );
}

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
    return array(
        'ga_id'                => isset( $input['ga_id'] )                ? sanitize_text_field( $input['ga_id'] )                : '',
        'gtm_id'               => isset( $input['gtm_id'] )               ? sanitize_text_field( $input['gtm_id'] )               : '',
        'fb_pixel_id'          => isset( $input['fb_pixel_id'] )          ? sanitize_text_field( $input['fb_pixel_id'] )          : '',
        'custom_head_script'   => isset( $input['custom_head_script'] )   ? wp_kses_post( $input['custom_head_script'] )          : '',
        'custom_footer_script' => isset( $input['custom_footer_script'] ) ? wp_kses_post( $input['custom_footer_script'] )        : '',
    );
}

function roci_sanitize_footer( $input ) {
    return array(
        'tagline'  => isset( $input['tagline'] )  ? sanitize_text_field( $input['tagline'] )      : '',
        'blurb'    => isset( $input['blurb'] )    ? sanitize_textarea_field( $input['blurb'] )    : '',
        'logo_url' => isset( $input['logo_url'] ) ? esc_url_raw( $input['logo_url'] )             : '',
    );
}


// ============================================================
// REGISTER ADMIN MENU
// ============================================================

function roci_add_settings_menu() {
    add_menu_page(
        __( 'Theme Settings', 'rocinante' ),
        __( 'Theme Settings', 'rocinante' ),
        'manage_options',
        'roci-theme-settings',
        'roci_settings_page',
        'dashicons-admin-customizer',
        3
    );
}
add_action( 'admin_menu', 'roci_add_settings_menu' );


// ============================================================
// ENQUEUE COLOR PICKER & ADMIN SCRIPTS
// ============================================================

function roci_settings_enqueue( $hook ) {
    if ( $hook !== 'toplevel_page_roci-theme-settings' ) return;

    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_media();
    wp_enqueue_script(
        'roci-settings-js',
        get_template_directory_uri() . '/dist/js/theme-settings.js',
        array( 'jquery', 'wp-color-picker' ),
        filemtime( get_template_directory() . '/dist/js/theme-settings.js' ),
        true
    );
}
add_action( 'admin_enqueue_scripts', 'roci_settings_enqueue' );


// ============================================================
// HELPER — GET SETTING
// Usage: roci_setting('business', 'name')
//        roci_setting('design', 'primary_color', '#000000')
// ============================================================

function roci_setting( $group, $key, $default = '' ) {
    $options = get_option( 'roci_' . $group, array() );
    return isset( $options[ $key ] ) && $options[ $key ] !== '' ? $options[ $key ] : $default;
}


// ============================================================
// SETTINGS PAGE OUTPUT
// ============================================================

function roci_settings_page() {

    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'identity';

    $tabs = apply_filters( 'roci_settings_tabs', array(
        'identity'     => __( 'Site Identity', 'rocinante' ),
        'design'       => __( 'Design', 'rocinante' ),
        'business'     => __( 'Business', 'rocinante' ),
        'social'       => __( 'Social', 'rocinante' ),
        'seo'          => __( 'SEO', 'rocinante' ),
        'integrations' => __( 'Integrations', 'rocinante' ),
        'footer'       => __( 'Footer', 'rocinante' ),
    ) );

    ?>
    <div class="wrap roci-settings-wrap">

        <h1><?php _e( 'Theme Settings', 'rocinante' ); ?> <span style="font-size:13px;color:#666;font-weight:400;">El Rocinante</span></h1>

        <style>
            .roci-settings-wrap .nav-tab-wrapper { margin-bottom: 20px; }
            .roci-settings-wrap .roci-tab-content { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 24px; }
            .roci-settings-wrap h2.roci-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #888; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; margin: 28px 0 16px; }
            .roci-settings-wrap h2.roci-section-title:first-child { margin-top: 0; }
            .roci-settings-wrap .form-table th { width: 220px; }
            .roci-settings-wrap .roci-color-row { display: flex; gap: 24px; flex-wrap: wrap; }
            .roci-settings-wrap .roci-color-field { display: flex; flex-direction: column; gap: 6px; }
            .roci-settings-wrap .roci-color-field label { font-size: 12px; color: #555; font-weight: 600; }
            .roci-settings-wrap .wp-color-result { border-radius: 4px; }
            .roci-settings-wrap .roci-media-wrap { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
            .roci-settings-wrap .roci-media-preview { max-height: 60px; width: auto; border-radius: 4px; border: 1px solid #ddd; display: none; }
            .roci-settings-wrap .roci-media-preview.has-image { display: block; }
            .roci-settings-wrap .button-small { font-size: 12px; }
            .roci-settings-wrap .roci-note { font-size: 12px; color: #888; margin-top: 4px; }
        </style>

        <nav class="nav-tab-wrapper">
            <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
                <a href="?page=roci-theme-settings&tab=<?php echo esc_attr( $tab_id ); ?>"
                   class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $tab_label ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="roci-tab-content">
            <form method="post" action="options.php">
                <?php

                switch ( $active_tab ) :

                    // ============================================================
                    case 'identity':
                    // ============================================================
                        settings_fields( 'roci_identity_group' );
                        $identity = get_option( 'roci_identity', array() );
                        $logo_url = isset( $identity['site_logo'] ) ? $identity['site_logo'] : '';
                        $icon_url = isset( $identity['site_icon'] ) ? $identity['site_icon'] : '';
                        ?>
                        <h2 class="roci-section-title"><?php _e( 'Logo & Icon', 'rocinante' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e( 'Site Logo', 'rocinante' ); ?></label></th>
                                <td>
                                    <div class="roci-media-wrap">
                                        <img src="<?php echo esc_url( $logo_url ); ?>" class="roci-media-preview <?php echo $logo_url ? 'has-image' : ''; ?>" id="roci_logo_preview">
                                        <input type="hidden" name="roci_identity[site_logo]" id="roci_logo_url" value="<?php echo esc_url( $logo_url ); ?>">
                                        <button type="button" class="button button-small roci-media-upload" data-target="roci_logo_url" data-preview="roci_logo_preview"><?php _e( 'Select Logo', 'rocinante' ); ?></button>
                                        <?php if ( $logo_url ) : ?>
                                            <button type="button" class="button button-small roci-media-remove" data-target="roci_logo_url" data-preview="roci_logo_preview"><?php _e( 'Remove', 'rocinante' ); ?></button>
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
                                        <input type="hidden" name="roci_identity[site_icon]" id="roci_icon_url" value="<?php echo esc_url( $icon_url ); ?>">
                                        <button type="button" class="button button-small roci-media-upload" data-target="roci_icon_url" data-preview="roci_icon_preview"><?php _e( 'Select Icon', 'rocinante' ); ?></button>
                                        <?php if ( $icon_url ) : ?>
                                            <button type="button" class="button button-small roci-media-remove" data-target="roci_icon_url" data-preview="roci_icon_preview"><?php _e( 'Remove', 'rocinante' ); ?></button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="roci-note"><?php _e( 'Square PNG or SVG, minimum 512x512px.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <h2 class="roci-section-title"><?php _e( 'Site Information', 'rocinante' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="roci_site_title"><?php _e( 'Site Title', 'rocinante' ); ?></label></th>
                                <td>
                                    <input type="text" name="roci_identity[site_title]" id="roci_site_title" class="regular-text" value="<?php echo esc_attr( isset( $identity['site_title'] ) ? $identity['site_title'] : get_bloginfo( 'name' ) ); ?>">
                                    <p class="roci-note"><?php _e( 'Used in SEO title tags and schema markup.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="roci_tagline"><?php _e( 'Tagline', 'rocinante' ); ?></label></th>
                                <td>
                                    <input type="text" name="roci_identity[tagline]" id="roci_tagline" class="regular-text" value="<?php echo esc_attr( isset( $identity['tagline'] ) ? $identity['tagline'] : get_bloginfo( 'description' ) ); ?>">
                                </td>
                            </tr>
                        </table>
                        <?php
                        break;

                    // ============================================================
                    case 'design':
                    // ============================================================
                        settings_fields( 'roci_design_group' );
                        $design = get_option( 'roci_design', array() );

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

                        <h2 class="roci-section-title"><?php _e( 'Colors', 'rocinante' ); ?></h2>
                        <?php foreach ( $color_groups as $group ) : ?>
                            <p style="font-weight:600;margin-bottom:8px;"><?php echo esc_html( $group['title'] ); ?></p>
                            <div class="roci-color-row" style="margin-bottom:24px;">
                                <?php foreach ( $group['fields'] as $key => $label ) : ?>
                                    <div class="roci-color-field">
                                        <label for="roci_color_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
                                        <input type="text"
                                               name="roci_design[<?php echo esc_attr( $key ); ?>]"
                                               id="roci_color_<?php echo esc_attr( $key ); ?>"
                                               class="roci-color-picker"
                                               value="<?php echo esc_attr( isset( $design[ $key ] ) ? $design[ $key ] : '' ); ?>"
                                               data-default-color="">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <h2 class="roci-section-title"><?php _e( 'Typography', 'rocinante' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="roci_body_font"><?php _e( 'Body Font', 'rocinante' ); ?></label></th>
                                <td>
                                    <input type="text" name="roci_design[body_font]" id="roci_body_font" class="regular-text" value="<?php echo esc_attr( isset( $design['body_font'] ) ? $design['body_font'] : '' ); ?>">
                                    <p class="roci-note"><?php _e( 'Google Font name e.g. "DM Sans". Leave blank to use theme default.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="roci_heading_font"><?php _e( 'Heading Font', 'rocinante' ); ?></label></th>
                                <td>
                                    <input type="text" name="roci_design[heading_font]" id="roci_heading_font" class="regular-text" value="<?php echo esc_attr( isset( $design['heading_font'] ) ? $design['heading_font'] : '' ); ?>">
                                    <p class="roci-note"><?php _e( 'Google Font name e.g. "Playfair Display". Leave blank to use theme default.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="roci_base_font_size"><?php _e( 'Base Font Size', 'rocinante' ); ?></label></th>
                                <td>
                                    <input type="text" name="roci_design[base_font_size]" id="roci_base_font_size" class="small-text" value="<?php echo esc_attr( isset( $design['base_font_size'] ) ? $design['base_font_size'] : '' ); ?>">
                                    <p class="roci-note"><?php _e( 'e.g. 17px. Leave blank to use theme default.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <h2 class="roci-section-title"><?php _e( 'Header', 'rocinante' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="roci_header_style"><?php _e( 'Header Style', 'rocinante' ); ?></label></th>
                                <td>
                                    <select name="roci_design[header_style]" id="roci_header_style">
                                        <option value="solid" <?php selected( isset( $design['header_style'] ) ? $design['header_style'] : 'solid', 'solid' ); ?>><?php _e( 'Solid', 'rocinante' ); ?></option>
                                        <option value="transparent" <?php selected( isset( $design['header_style'] ) ? $design['header_style'] : 'solid', 'transparent' ); ?>><?php _e( 'Transparent (overlays hero)', 'rocinante' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="roci_sticky_header"><?php _e( 'Sticky Header', 'rocinante' ); ?></label></th>
                                <td>
                                    <input type="checkbox" name="roci_design[sticky_header]" id="roci_sticky_header" value="1" <?php checked( isset( $design['sticky_header'] ) ? $design['sticky_header'] : '0', '1' ); ?>>
                                    <label for="roci_sticky_header"><?php _e( 'Enable sticky header on scroll', 'rocinante' ); ?></label>
                                    <p class="roci-note"><?php _e( 'Outputs body class <code>has-sticky-nav</code>. Sticky behavior is implemented per child theme.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <h2 class="roci-section-title"><?php _e( 'Buttons', 'rocinante' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="roci_button_style"><?php _e( 'Button Style', 'rocinante' ); ?></label></th>
                                <td>
                                    <select name="roci_design[button_style]" id="roci_button_style">
                                        <option value="rounded" <?php selected( isset( $design['button_style'] ) ? $design['button_style'] : 'rounded', 'rounded' ); ?>><?php _e( 'Rounded', 'rocinante' ); ?></option>
                                        <option value="sharp"   <?php selected( isset( $design['button_style'] ) ? $design['button_style'] : 'rounded', 'sharp' ); ?>><?php _e( 'Sharp', 'rocinante' ); ?></option>
                                        <option value="pill"    <?php selected( isset( $design['button_style'] ) ? $design['button_style'] : 'rounded', 'pill' ); ?>><?php _e( 'Pill', 'rocinante' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php
                        break;

                    // ============================================================
                    case 'business':
                    // ============================================================
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
                        <?php
                        break;

                    // ============================================================
                    case 'social':
                    // ============================================================
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
                        <?php
                        break;

                    // ============================================================
                    case 'seo':
                    // ============================================================
                        settings_fields( 'roci_seo_group' );
                        $seo        = get_option( 'roci_seo', array() );
                        $default_og = isset( $seo['default_og_image'] ) ? $seo['default_og_image'] : '';
                        ?>
                        <h2 class="roci-section-title"><?php _e( 'SEO Defaults', 'rocinante' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="roci_default_meta_desc"><?php _e( 'Default Meta Description', 'rocinante' ); ?></label></th>
                                <td>
                                    <textarea name="roci_seo[default_meta_description]" id="roci_default_meta_desc" class="large-text" rows="3" maxlength="160"><?php echo esc_textarea( isset( $seo['default_meta_description'] ) ? $seo['default_meta_description'] : '' ); ?></textarea>
                                    <p class="roci-note"><?php _e( 'Used when no page-specific meta description is set. Max 160 characters.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e( 'Default OG Image', 'rocinante' ); ?></label></th>
                                <td>
                                    <div class="roci-media-wrap">
                                        <img src="<?php echo esc_url( $default_og ); ?>" class="roci-media-preview <?php echo $default_og ? 'has-image' : ''; ?>" id="roci_og_preview">
                                        <input type="hidden" name="roci_seo[default_og_image]" id="roci_og_image_url" value="<?php echo esc_url( $default_og ); ?>">
                                        <button type="button" class="button button-small roci-media-upload" data-target="roci_og_image_url" data-preview="roci_og_preview"><?php _e( 'Select Image', 'rocinante' ); ?></button>
                                        <?php if ( $default_og ) : ?>
                                            <button type="button" class="button button-small roci-media-remove" data-target="roci_og_image_url" data-preview="roci_og_preview"><?php _e( 'Remove', 'rocinante' ); ?></button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="roci-note"><?php _e( 'Recommended 1200x630px WebP. Used when no page-specific OG image is set.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="roci_seo_preview_toggle"><?php _e( 'SEO Preview Panel', 'rocinante' ); ?></label></th>
                                <td>
                                    <input type="checkbox" name="roci_seo[seo_preview]" id="roci_seo_preview_toggle" value="1" <?php checked( isset( $seo['seo_preview'] ) ? $seo['seo_preview'] : '1', '1' ); ?>>
                                    <label for="roci_seo_preview_toggle"><?php _e( 'Show Google, Facebook and Twitter preview panels on page/post edit screens', 'rocinante' ); ?></label>
                                </td>
                            </tr>
                        </table>
                        <?php
                        break;

                    // ============================================================
                    case 'integrations':
                    // ============================================================
                        settings_fields( 'roci_integrations_group' );
                        $integrations = get_option( 'roci_integrations', array() );
                        ?>
                        <h2 class="roci-section-title"><?php _e( 'Analytics', 'rocinante' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="roci_ga_id"><?php _e( 'Google Analytics ID', 'rocinante' ); ?></label></th>
                                <td><input type="text" name="roci_integrations[ga_id]" id="roci_ga_id" class="regular-text" value="<?php echo esc_attr( isset( $integrations['ga_id'] ) ? $integrations['ga_id'] : '' ); ?>" placeholder="G-XXXXXXXXXX"></td>
                            </tr>
                            <tr>
                                <th><label for="roci_gtm_id"><?php _e( 'Google Tag Manager ID', 'rocinante' ); ?></label></th>
                                <td><input type="text" name="roci_integrations[gtm_id]" id="roci_gtm_id" class="regular-text" value="<?php echo esc_attr( isset( $integrations['gtm_id'] ) ? $integrations['gtm_id'] : '' ); ?>" placeholder="GTM-XXXXXXX"></td>
                            </tr>
                            <tr>
                                <th><label for="roci_fb_pixel"><?php _e( 'Facebook Pixel ID', 'rocinante' ); ?></label></th>
                                <td><input type="text" name="roci_integrations[fb_pixel_id]" id="roci_fb_pixel" class="regular-text" value="<?php echo esc_attr( isset( $integrations['fb_pixel_id'] ) ? $integrations['fb_pixel_id'] : '' ); ?>" placeholder="XXXXXXXXXXXXXXXXXX"></td>
                            </tr>
                        </table>

                        <h2 class="roci-section-title"><?php _e( 'Custom Scripts', 'rocinante' ); ?></h2>
                        <p class="roci-note" style="margin-bottom:16px;"><?php _e( 'Use these fields for any third-party embed codes not covered above — booking systems, widgets, tracking pixels, iCal feeds, etc.', 'rocinante' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="roci_custom_head"><?php _e( 'Custom &lt;head&gt; Script', 'rocinante' ); ?></label></th>
                                <td>
                                    <textarea name="roci_integrations[custom_head_script]" id="roci_custom_head" class="large-text" rows="6"><?php echo esc_textarea( isset( $integrations['custom_head_script'] ) ? $integrations['custom_head_script'] : '' ); ?></textarea>
                                    <p class="roci-note"><?php _e( 'Output in &lt;head&gt;. Include full &lt;script&gt; tags.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="roci_custom_footer"><?php _e( 'Custom Footer Script', 'rocinante' ); ?></label></th>
                                <td>
                                    <textarea name="roci_integrations[custom_footer_script]" id="roci_custom_footer" class="large-text" rows="6"><?php echo esc_textarea( isset( $integrations['custom_footer_script'] ) ? $integrations['custom_footer_script'] : '' ); ?></textarea>
                                    <p class="roci-note"><?php _e( 'Output before &lt;/body&gt;. Include full &lt;script&gt; tags.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <?php do_action( 'roci_integrations_extra' ); ?>
                        <?php
                        break;

                    // ============================================================
                    case 'footer':
                    // ============================================================
                        settings_fields( 'roci_footer_group' );
                        $footer      = get_option( 'roci_footer', array() );
                        $footer_logo = isset( $footer['logo_url'] ) ? $footer['logo_url'] : '';
                        ?>
                        <h2 class="roci-section-title"><?php _e( 'Footer Content', 'rocinante' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="roci_footer_tagline"><?php _e( 'Footer Tagline', 'rocinante' ); ?></label></th>
                                <td>
                                    <input type="text" name="roci_footer[tagline]" id="roci_footer_tagline" class="regular-text" value="<?php echo esc_attr( isset( $footer['tagline'] ) ? $footer['tagline'] : '' ); ?>">
                                    <p class="roci-note"><?php _e( 'Displayed below the logo/brand name in the footer. Uses Apalu font if set.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="roci_footer_blurb"><?php _e( 'Footer Blurb', 'rocinante' ); ?></label></th>
                                <td>
                                    <textarea name="roci_footer[blurb]" id="roci_footer_blurb" class="large-text" rows="4"><?php echo esc_textarea( isset( $footer['blurb'] ) ? $footer['blurb'] : '' ); ?></textarea>
                                    <p class="roci-note"><?php _e( 'Short description displayed in the footer brand column.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e( 'Footer Logo', 'rocinante' ); ?></label></th>
                                <td>
                                    <div class="roci-media-wrap">
                                        <img src="<?php echo esc_url( $footer_logo ); ?>" class="roci-media-preview <?php echo $footer_logo ? 'has-image' : ''; ?>" id="roci_footer_logo_preview">
                                        <input type="hidden" name="roci_footer[logo_url]" id="roci_footer_logo_url" value="<?php echo esc_url( $footer_logo ); ?>">
                                        <button type="button" class="button button-small roci-media-upload" data-target="roci_footer_logo_url" data-preview="roci_footer_logo_preview"><?php _e( 'Select Logo', 'rocinante' ); ?></button>
                                        <?php if ( $footer_logo ) : ?>
                                            <button type="button" class="button button-small roci-media-remove" data-target="roci_footer_logo_url" data-preview="roci_footer_logo_preview"><?php _e( 'Remove', 'rocinante' ); ?></button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="roci-note"><?php _e( 'Optional separate logo for the footer. Falls back to site logo if empty.', 'rocinante' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        <?php
                        break;

                    // ============================================================
                    default:
                        do_action( 'roci_settings_tab_' . $active_tab );
                        break;

                endswitch;

                submit_button( __( 'Save Settings', 'rocinante' ) );
                ?>
            </form>
        </div>

    </div>
    <?php
}