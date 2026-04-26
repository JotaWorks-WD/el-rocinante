<?php

// ============================================================
// THEME SETUP
// ============================================================

function el_rocinante_setup() {

    load_theme_textdomain( 'rocinante', get_template_directory() . '/languages' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'site-icon' );

    add_theme_support( 'custom-logo', array(
        'height'      => 100,
        'width'       => 300,
        'flex-height' => true,
        'flex-width'  => true,
    ) );

    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ) );

    register_nav_menus( array(
        'primary'   => __( 'Primary Navigation', 'rocinante' ),
        'secondary' => __( 'Secondary Navigation', 'rocinante' ),
        'footer'    => __( 'Footer Navigation', 'rocinante' ),
        'mobile'    => __( 'Mobile Navigation', 'rocinante' ),
        'offcanvas' => __( 'Offcanvas Navigation', 'rocinante' ),
    ) );

}
add_action( 'after_setup_theme', 'el_rocinante_setup' );


// ============================================================
// ENQUEUE STYLES & SCRIPTS
// ============================================================

function el_rocinante_enqueue_assets() {

    wp_enqueue_style(
        'el-rocinante-style',
        get_template_directory_uri() . '/dist/css/style.css',
        array(),
        filemtime( get_template_directory() . '/dist/css/style.css' )
    );

    wp_enqueue_script(
        'bootstrap-js',
        get_template_directory_uri() . '/dist/js/bootstrap.bundle.min.js',
        array(),
        '5.2.3',
        true
    );

}
add_action( 'wp_enqueue_scripts', 'el_rocinante_enqueue_assets' );


// ============================================================
// REMOVE WORDPRESS JUNK FROM HEAD
// ============================================================

remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_filter( 'wp_robots', 'wp_robots_max_image_preview', 9 );


// ============================================================
// REMOVE WORDPRESS DEFAULT SEO OUTPUT
// ============================================================

remove_action( 'wp_head', 'rel_canonical' );
remove_action( 'wp_head', 'wp_robots', 1 );


// ============================================================
// BOOTSTRAP NAVWALKER
// ============================================================

require_once get_template_directory() . '/inc/class-wp-bootstrap-navwalker.php';


// ============================================================
// METABOX FIELD DEFINITIONS
// ============================================================

require_once get_template_directory() . '/inc/metabox/metabox-fields.php';


// ============================================================
// SITEMAP FILTERS
// ============================================================

require_once get_template_directory() . '/inc/sitemap.php';


// ============================================================
// CUSTOM FIELD WRAPPER (Metabox)
// ============================================================

function roci_get_field( $field_id, $object_id = null ) {
    if ( ! function_exists( 'rwmb_meta' ) ) {
        return '';
    }
    if ( $object_id === null ) {
        $object_id = get_the_ID();
    }
    return rwmb_meta( $field_id, array(), $object_id );
}


// ============================================================
// CUSTOMIZER SETTINGS
// ============================================================

function el_rocinante_customizer( $wp_customize ) {

    $wp_customize->add_panel( 'roci_seo_configuration', array(
        'title'       => __( 'SEO Configuration', 'rocinante' ),
        'description' => __( 'Business information, social profiles, and SEO defaults for this site.', 'rocinante' ),
        'priority'    => 30,
    ) );

    // --------------------------------------------------------
    // BUSINESS SETTINGS
    // --------------------------------------------------------

    $wp_customize->add_section( 'roci_business_settings', array(
        'title'    => __( 'Business Settings', 'rocinante' ),
        'panel'    => 'roci_seo_configuration',
        'priority' => 10,
    ) );

    $wp_customize->add_setting( 'roci_business_name', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'roci_business_name', array(
        'label'   => __( 'Business Name', 'rocinante' ),
        'section' => 'roci_business_settings',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'roci_phone', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'roci_phone', array(
        'label'   => __( 'Phone Number', 'rocinante' ),
        'section' => 'roci_business_settings',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'roci_email', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_email',
    ) );
    $wp_customize->add_control( 'roci_email', array(
        'label'   => __( 'Email Address', 'rocinante' ),
        'section' => 'roci_business_settings',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'roci_address', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
    ) );
    $wp_customize->add_control( 'roci_address', array(
        'label'   => __( 'Business Address', 'rocinante' ),
        'section' => 'roci_business_settings',
        'type'    => 'textarea',
    ) );

    $wp_customize->add_setting( 'roci_maps_embed', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( 'roci_maps_embed', array(
        'label'       => __( 'Google Maps Embed URL', 'rocinante' ),
        'description' => __( 'Paste the src URL from your Google Maps embed code.', 'rocinante' ),
        'section'     => 'roci_business_settings',
        'type'        => 'text',
    ) );

    $wp_customize->add_setting( 'roci_whatsapp', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'roci_whatsapp', array(
        'label'       => __( 'WhatsApp Number', 'rocinante' ),
        'description' => __( 'Include country code, no spaces. e.g. 50688887777', 'rocinante' ),
        'section'     => 'roci_business_settings',
        'type'        => 'text',
    ) );

    // --------------------------------------------------------
    // SOCIAL PROFILES
    // --------------------------------------------------------

    $wp_customize->add_section( 'roci_social_profiles', array(
        'title'    => __( 'Social Profiles', 'rocinante' ),
        'panel'    => 'roci_seo_configuration',
        'priority' => 20,
    ) );

    $social_profiles = array(
        'roci_facebook'    => 'Facebook URL',
        'roci_instagram'   => 'Instagram URL',
        'roci_tiktok'      => 'TikTok URL',
        'roci_youtube'     => 'YouTube URL',
        'roci_linkedin'    => 'LinkedIn URL',
        'roci_twitter'     => 'X (Twitter) URL',
        'roci_tripadvisor' => 'TripAdvisor URL',
    );

    foreach ( $social_profiles as $key => $label ) {
        $wp_customize->add_setting( $key, array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ) );
        $wp_customize->add_control( $key, array(
            'label'   => __( $label, 'rocinante' ),
            'section' => 'roci_social_profiles',
            'type'    => 'url',
        ) );
    }

    // --------------------------------------------------------
    // SEO DEFAULTS
    // --------------------------------------------------------

    $wp_customize->add_section( 'roci_seo_defaults', array(
        'title'    => __( 'SEO Defaults', 'rocinante' ),
        'panel'    => 'roci_seo_configuration',
        'priority' => 30,
    ) );

    $wp_customize->add_setting( 'roci_default_meta_description', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
    ) );
    $wp_customize->add_control( 'roci_default_meta_description', array(
        'label'       => __( 'Default Meta Description', 'rocinante' ),
        'description' => __( 'Used when no page-specific meta description is set.', 'rocinante' ),
        'section'     => 'roci_seo_defaults',
        'type'        => 'textarea',
    ) );

    $wp_customize->add_setting( 'roci_default_og_image', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( new WP_Customize_Image_Control(
        $wp_customize,
        'roci_default_og_image',
        array(
            'label'       => __( 'Default OG Image', 'rocinante' ),
            'description' => __( 'Used when no page-specific OG image is set. Recommended 1200x630px.', 'rocinante' ),
            'section'     => 'roci_seo_defaults',
        )
    ) );

    $wp_customize->add_setting( 'roci_ga_id', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'roci_ga_id', array(
        'label'       => __( 'Google Analytics ID', 'rocinante' ),
        'description' => __( 'e.g. G-XXXXXXXXXX', 'rocinante' ),
        'section'     => 'roci_seo_defaults',
        'type'        => 'text',
    ) );

    $wp_customize->add_setting( 'roci_gtm_id', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'roci_gtm_id', array(
        'label'       => __( 'Google Tag Manager ID', 'rocinante' ),
        'description' => __( 'e.g. GTM-XXXXXXX', 'rocinante' ),
        'section'     => 'roci_seo_defaults',
        'type'        => 'text',
    ) );

    $wp_customize->add_setting( 'roci_seo_preview', array(
        'default'           => '1',
        'sanitize_callback' => 'absint',
    ) );
    $wp_customize->add_control( 'roci_seo_preview', array(
        'label'       => __( 'Enable SEO Preview Panel', 'rocinante' ),
        'description' => __( 'Show Google, Facebook and Twitter previews on page/post edit screens.', 'rocinante' ),
        'section'     => 'roci_seo_defaults',
        'type'        => 'checkbox',
    ) );

}
add_action( 'customize_register', 'el_rocinante_customizer' );


// ============================================================
// OUTPUT GOOGLE ANALYTICS & GTM
// ============================================================

function el_rocinante_analytics() {
    $ga_id  = get_theme_mod( 'roci_ga_id', '' );
    $gtm_id = get_theme_mod( 'roci_gtm_id', '' );

    if ( $ga_id ) : ?>
        <!-- Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga_id ); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo esc_attr( $ga_id ); ?>');
        </script>
    <?php endif;

    if ( $gtm_id ) : ?>
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentBefore(j,f);
        })(window,document,'script','dataLayer','<?php echo esc_attr( $gtm_id ); ?>');</script>
    <?php endif;
}
add_action( 'wp_head', 'el_rocinante_analytics' );