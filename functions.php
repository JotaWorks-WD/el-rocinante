<?php
/**
 * Functions — Core Theme Setup
 *
 * Registers theme support, nav menus, enqueues styles/scripts,
 * loads includes, and outputs analytics/integration scripts.
 *
 * File:    functions.php
 * Version: 1.0.0
 * Updated: 2026-05-03
 *
 * @package ElRocinante
 */


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
// BODY CLASSES — design setting hooks
// ============================================================

function el_rocinante_body_classes( $classes ) {

    $header_style  = roci_setting( 'design', 'header_style', 'solid' );
    $sticky_header = roci_setting( 'design', 'sticky_header', '0' );

    if ( $header_style === 'transparent' ) {
        $classes[] = 'has-transparent-nav';
    }

    if ( $sticky_header === '1' ) {
        $classes[] = 'has-sticky-nav';
    }

    return $classes;
}
add_filter( 'body_class', 'el_rocinante_body_classes' );


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
// THEME SETTINGS PAGE
// ============================================================

require_once get_template_directory() . '/inc/theme-settings.php';


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
// OUTPUT ANALYTICS & INTEGRATIONS
// ============================================================

function el_rocinante_analytics() {

    $ga_id       = roci_setting( 'integrations', 'ga_id' );
    $gtm_id      = roci_setting( 'integrations', 'gtm_id' );
    $fb_pixel    = roci_setting( 'integrations', 'fb_pixel_id' );
    $head_script = roci_setting( 'integrations', 'custom_head_script' );

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
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?php echo esc_attr( $gtm_id ); ?>');</script>
    <?php endif;

    if ( $fb_pixel ) : ?>
        <!-- Facebook Pixel -->
        <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
        document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init','<?php echo esc_attr( $fb_pixel ); ?>');
        fbq('track','PageView');
        </script>
    <?php endif;

    if ( $head_script ) :
        echo $head_script;
    endif;

}
add_action( 'wp_head', 'el_rocinante_analytics' );


// ============================================================
// OUTPUT CUSTOM FOOTER SCRIPT
// ============================================================

function el_rocinante_footer_scripts() {
    $footer_script = roci_setting( 'integrations', 'custom_footer_script' );
    if ( $footer_script ) {
        echo $footer_script;
    }
}
add_action( 'wp_footer', 'el_rocinante_footer_scripts' );