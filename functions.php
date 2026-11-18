<?php
/**
 * Functions — Core Theme Setup
 *
 * Registers theme support, nav menus, enqueues styles/scripts,
 * loads includes, and outputs analytics/integration scripts.
 *
 * File:    functions.php
 * Version: 1.23.0
 * Updated: 2026-11-18
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
        'footer' => __( 'Footer Navigation', 'rocinante' ),
    ) );

}
add_action( 'after_setup_theme', 'el_rocinante_setup' );


// ============================================================
// LOADER OVERLAY
// ============================================================
//
// OPT-IN, AND INERT BY DEFAULT. The parent ships the markup and the CSS;
// a child turns the feature on with
//
//     add_theme_support( 'roci-loader' );
//
// in its own after_setup_theme callback. Without that call nothing renders
// and nothing is enqueued — a site that does not want a loading screen
// carries no trace of one. This mirrors the roci-tour-layout bundle gate
// (inc/layout-bundles/), which is the precedent for feature opt-in here.
//
// PRIORITY 5, NOT THE DEFAULT 10. The overlay must be the first element in
// the body so the fixed #site-header cannot paint over it, and priority 5
// puts it ahead of anything a plugin or child hooks at 10.
//
// ⚠ THE PARENT SUPPLIES NO DISMISSAL. It renders the overlay and styles it;
// removing it is the child's job: the parent enqueues no front-end
// JavaScript unconditionally (see §8 of CLAUDE.md; its scripts — the FAQ's and
// the forms module's — load only with the part that renders them), and ships
// none for this feature. A child that opts in WITHOUT shipping dismissal JS gets
// an overlay that never goes away. The reference child's dismissal lives in
// its own front-end script.

function el_rocinante_render_loader() {

    if ( ! current_theme_supports( 'roci-loader' ) ) {
        return;
    }

    get_template_part( 'template-parts/loader' );
}
add_action( 'wp_body_open', 'el_rocinante_render_loader', 5 );

// THE LOADER'S CRITICAL CSS, IN <head> (v8.4.1). It used to print inside
// <body>, just above the overlay — a <style> element there is invalid HTML and
// the W3C validator rejects it. Same rules, same values, now printed on
// wp_head at PRIORITY 7, just before the stylesheets (8), so the overlay is
// styled from its first paint exactly as before (earlier, if anything).
//
// ⚠ A HAND-COPIED DUPLICATE OF components/_loader.scss (plus base/_base.scss's
// root size), every selector in :where() so it has ZERO specificity: it only
// fills the gap until the external sheet (same values) arrives, and any child
// override still wins. Change a value in the SCSS and change it here — nothing
// warns. :where(html){font-size:62.5%} is deliberate: the loader sizes are rem.
// The 8s failsafe's clock starts when this copy first styles the overlay; the
// external sheet's identical declaration does not restart it.
//
// ⚠ THE NO-JS ESCAPE NEEDS ITS EXTRA `html`. In the body it beat the sheet's
// #loader.roci-loader{display:flex} (1,1,0) on source order. In the head it
// comes BEFORE the sheet, so it wins on specificity instead: html
// #loader.roci-loader is (1,1,1). No !important. <noscript> holding a <style>
// is valid inside <head>.
function el_rocinante_loader_head_css() {

    if ( ! current_theme_supports( 'roci-loader' ) ) {
        return;
    }
    ?>
<style id="roci-loader-critical">:where(html){font-size:62.5%}:where(#loader.roci-loader){position:fixed;inset:0;z-index:var(--z-tooltip,700);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2rem;background-color:var(--color-surface,#fff);opacity:1;transition:opacity .4s ease;animation:roci-loader-failsafe .4s ease 8s forwards}@keyframes roci-loader-failsafe{to{opacity:0;visibility:hidden;pointer-events:none}}:where(#loader.roci-loader[hidden]){display:none}:where(#loader.roci-loader.is-dismissed){opacity:0;pointer-events:none}:where(.roci-loader__logo){max-width:18rem;height:auto}:where(.roci-loader__spinner){width:4rem;height:4rem;border:3px solid var(--color-border,#e5e5e5);border-top-color:var(--color-action,#333);border-radius:50%;animation:roci-loader-spin .8s linear infinite}@keyframes roci-loader-spin{to{transform:rotate(360deg)}}@media (prefers-reduced-motion:reduce){:where(#loader.roci-loader){transition:none}:where(.roci-loader__spinner){animation:none}}</style>
<noscript><style id="roci-loader-noscript">html #loader.roci-loader{display:none}</style></noscript>
    <?php
}
add_action( 'wp_head', 'el_rocinante_loader_head_css', 7 );


// ============================================================
// SKIP LINK
// ============================================================
//
// The first focusable element on every page, on every child: header.php is
// the parent's and a child never overrides it. Targets #main-content, the id
// every <main> in the parent and its children carries.
//
// PRIORITY 1 — AHEAD OF THE LOADER (5) AND OF ANY PLUGIN OR CHILD (10). It
// cannot be written inline after wp_body_open() in header.php: the loader
// attaches INSIDE that call, so inline markup would land after it, and after
// any focusable plugin output (a consent banner, say). The link is visually
// hidden and out of flow until focused, so sitting ahead of the overlay
// changes nothing the loader's own priority note protects.
//
// HTML ONLY. The visibility comes from .screen-reader-text and its :focus
// reveal in base/_utilities.scss; no JavaScript is involved.

function el_rocinante_render_skip_link() {
    ?>
<a class="screen-reader-text" href="#main-content"><?php esc_html_e( 'Skip to content', 'rocinante' ); ?></a>
    <?php
}
add_action( 'wp_body_open', 'el_rocinante_render_skip_link', 1 );


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

    // THE FAQ SCRIPT (v7.6.0) — REGISTERED, NEVER ENQUEUED
    // HERE. template-parts/faq.php enqueues it only when it renders an
    // accordion, so no other page requests it. FOOTER, so the part (which runs
    // in the body, before wp_footer()) can still enqueue it, and the script
    // runs after the markup it binds. The rule this keeps: the parent enqueues
    // no front-end JavaScript unconditionally (CONVENTIONS hard rules).
    wp_register_script(
        'el-rocinante-faq-accordion',
        get_template_directory_uri() . '/dist/js/faq-accordion.js',
        array(),
        filemtime( get_template_directory() . '/dist/js/faq-accordion.js' ),
        true
    );

    // THE THEME TOGGLE (v8.4.0) — REGISTERED, NEVER ENQUEUED HERE.
    // inc/dark-mode.php enqueues it only on a roci-dark-mode site whose colour
    // mode is "toggle". Footer: it binds to markup already on the page.
    wp_register_script(
        'el-rocinante-theme-toggle',
        get_template_directory_uri() . '/dist/js/theme-toggle.js',
        array(),
        filemtime( get_template_directory() . '/dist/js/theme-toggle.js' ),
        true
    );

    // THE FORMS MODULE'S SCRIPTS (v8.0.0) — registered only when the child
    // opted in to roci-forms, enqueued only by template-parts/form.php when
    // it renders a form. forms.js is enhancement only (the form works without
    // it). Turnstile is Cloudflare's own script, loaded on form pages only and
    // only when the keys are configured (owner decision D13, 2026-11-03).
    if ( roci_forms_enabled() ) {
        wp_register_script(
            'el-rocinante-forms',
            get_template_directory_uri() . '/dist/js/forms.js',
            array(),
            filemtime( get_template_directory() . '/dist/js/forms.js' ),
            true
        );
        // The background submit's endpoint and strings (8.0.1). No nonce:
        // the endpoint is public, and a nonce in cached HTML goes stale (D4).
        // headerSelector (8.0.2): the header forms.js measures for its scroll
        // offset when the site sets no html { scroll-padding-top }.
        wp_localize_script( 'el-rocinante-forms', 'rociForms', array(
            'endpoint'       => esc_url_raw( rest_url( 'roci/v1/form/' ) ),
            'sending'        => __( 'Sending…', 'rocinante' ),
            'errorPrefix'    => __( 'Error:', 'rocinante' ),
            'headerSelector' => (string) apply_filters( 'roci_forms_header_selector', '#site-header, .site-header' ),
        ) );
        wp_register_script(
            'el-rocinante-turnstile',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            array(),
            null,
            array(
                'in_footer' => true,
                'strategy'  => 'defer',
            )
        );
    }

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

// NO hentry ON THE FRONT END (v8.4.0). Core adds hentry to every post_class()
// output, and hentry is the hAtom microformat: a parser that reads it expects
// entry-title, updated and author markup the parent does not print, and
// reports the entry as broken. The structured data is JSON-LD instead.
//
// Admin is untouched (the list tables and the block editor use post_class()
// too). IDEMPOTENT: a child running the same filter is harmless, since a
// second array_diff() on an array without hentry changes nothing.
function el_rocinante_post_classes( $classes ) {

    if ( is_admin() ) {
        return $classes;
    }

    return array_values( array_diff( (array) $classes, array( 'hentry' ) ) );
}
add_filter( 'post_class', 'el_rocinante_post_classes' );


// ============================================================
// PRE-PAINT SCRIPT — the js class on <html>
// ============================================================
//
// One tiny inline script in <head>, before any stylesheet, that adds the
// class "js" to <html>. CSS uses html.js to arm controls that need
// JavaScript and leaves the no-JS layout standing without it.
//
// ONE STATEMENT PER LINE OF $roci_lines. Anything else that must run before
// first paint is added as a further entry, not a second script tag.
// classList.add() is idempotent, so a child that adds "js" itself as well
// changes nothing.
//
// PRIORITY 1, NEVER 0 — priority 0 is reserved for the hero preload
// (roci_hero_preload()). Stylesheets print at 8, so 1 is still pre-paint.
//
// data-no-optimize="1" keeps LiteSpeed Cache from deferring or combining it:
// a deferred copy runs after paint, which defeats the point. wp_head fires
// on the front end only, so this never prints in the admin.

function el_rocinante_pre_paint_script() {

    $roci_lines = array(
        "r.classList.add('js');",
    );

    // Dark mode (v8.4.0): the saved toggle choice, ahead of first paint. The
    // function exists only when the child declares roci-dark-mode, and it
    // returns '' unless the colour mode is "toggle", so every other site's
    // script is byte-identical.
    if ( function_exists( 'roci_dark_mode_pre_paint' ) ) {
        $roci_dark_line = roci_dark_mode_pre_paint();
        if ( '' !== $roci_dark_line ) {
            $roci_lines[] = $roci_dark_line;
        }
    }

    wp_print_inline_script_tag(
        '(function(d){var r=d.documentElement;' . implode( '', $roci_lines ) . '})(document);',
        array(
            'id'               => 'roci-pre-paint',
            'data-no-optimize' => '1',
        )
    );
}
add_action( 'wp_head', 'el_rocinante_pre_paint_script', 1 );


// ============================================================
// DARK MODE — opt-in via add_theme_support( 'roci-dark-mode' ) (v8.4.0)
// ============================================================
//
// THE PARENT SHIPS THE MECHANISM, THE CHILD SHIPS THE DARK VALUES. Without
// the flag inc/dark-mode.php is never loaded, so nothing it hooks runs and a
// site's output is unchanged.
//
// Loaded on after_setup_theme at priority 100, not at file scope: a child
// usually declares theme support in its own after_setup_theme callback, which
// has not run while this file loads. 100 is after every callback at the
// default 10. The file's hooks (language_attributes, wp_head,
// wp_enqueue_scripts, the settings save) all fire later than that.

function el_rocinante_load_dark_mode() {

    if ( ! current_theme_supports( 'roci-dark-mode' ) ) {
        return;
    }

    require_once get_template_directory() . '/inc/dark-mode.php';
}
add_action( 'after_setup_theme', 'el_rocinante_load_dark_mode', 100 );

/**
 * roci_get_theme_toggle()
 *
 * The visitor's light / dark toggle button, for a child's nav. ALWAYS
 * DEFINED, so a child may call it unconditionally: it returns '' without the
 * roci-dark-mode flag, and '' unless Theme Settings → Design → Colour mode is
 * "toggle". The markup is built by inc/dark-mode.php.
 *
 * @param  string $class Extra class(es) for the button.
 * @return string
 */
function roci_get_theme_toggle( $class = '' ) {
    return function_exists( 'roci_dark_mode_toggle_markup' ) ? roci_dark_mode_toggle_markup( $class ) : '';
}

/**
 * roci_theme_toggle()
 *
 * Echoes roci_get_theme_toggle(). Nothing without the flag or outside
 * "toggle" mode.
 *
 * @param string $class Extra class(es) for the button.
 */
function roci_theme_toggle( $class = '' ) {
    echo roci_get_theme_toggle( $class ); // Escaped where it is built.
}


// ============================================================
// 404 LOG — opt-in via add_theme_support( 'roci-404-log' ) (v8.4.0)
// ============================================================
//
// Inert without the flag: inc/404-log/ is never loaded, so there is no
// table, query, cron event or menu. Loaded at after_setup_theme 100 for the
// same reason as dark mode above.

function el_rocinante_load_404_log() {

    if ( ! current_theme_supports( 'roci-404-log' ) ) {
        return;
    }

    require_once get_template_directory() . '/inc/404-log/404-log.php';
}
add_action( 'after_setup_theme', 'el_rocinante_load_404_log', 100 );


// ============================================================
// VISITOR STATS — opt-in via add_theme_support( 'roci-stats' ) (v8.4.0)
// ============================================================
//
// Inert without the flag: inc/stats/ is never loaded, so there are no
// tables, script, route, widget or cron event. Loaded at after_setup_theme
// 100, like the modules above.

function el_rocinante_load_stats() {

    if ( ! current_theme_supports( 'roci-stats' ) ) {
        return;
    }

    require_once get_template_directory() . '/inc/stats/stats.php';
}
add_action( 'after_setup_theme', 'el_rocinante_load_stats', 100 );


// ============================================================
// INDEXNOW — opt-in via add_theme_support( 'roci-indexnow' ) (v8.4.0)
// ============================================================
//
// Inert without the flag: inc/indexnow.php is never loaded, so there is no
// key, key file or ping. Loaded at after_setup_theme 100, like the modules
// above.

function el_rocinante_load_indexnow() {

    if ( ! current_theme_supports( 'roci-indexnow' ) ) {
        return;
    }

    require_once get_template_directory() . '/inc/indexnow.php';
}
add_action( 'after_setup_theme', 'el_rocinante_load_indexnow', 100 );


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


// ============================================================
// REMOVE WORDPRESS DEFAULT SEO OUTPUT
// ============================================================

remove_action( 'wp_head', 'rel_canonical' );
remove_action( 'wp_head', 'wp_robots', 1 ); // header.php's ROBOTS block re-applies blog_public = 0 (noindex) — remove neither without the other.


// ============================================================
// DOCUMENT <title> — override with custom meta title field
// ============================================================

// ⚠ THE POST ID IS RESOLVED EXACTLY AS header.php RESOLVES $roci_post_id (see
// "WHICH POST OWNS THIS <head>" there), and the two must stay in step. With a
// bare get_the_ID() the posts page took the FIRST POST'S meta title as its
// <title>. Posts page -> page_for_posts; singular -> get_queried_object_id();
// everything else -> 0, NO post (v7.0.0): get_the_ID() there is the first
// post in the loop, so a search page took the first result's meta title.
// With 0 the filter returns $title untouched and core builds the title.
add_filter( 'pre_get_document_title', function( $title ) {
    $posts_page_id = (int) get_option( 'page_for_posts' );

    if ( is_home() && ! is_front_page() && $posts_page_id ) {
        $post_id = $posts_page_id;
    } elseif ( is_singular() ) {
        $post_id = get_queried_object_id();
    } else {
        $post_id = 0;
    }

    if ( ! $post_id ) {
        return $title;
    }
    $meta_title = roci_get_field( 'roci_meta_title', $post_id );
    return $meta_title ? $meta_title : $title;
} );


// ============================================================
// BUSINESS SCHEMA SYSTEM
// ============================================================
//
// Loaded ahead of the settings system because the Business and Social tabs, the
// settings sanitizers and header.php's schema assembly all resolve their lists
// from roci_business_types(), roci_social_platforms() and (v7.5.0)
// roci_business_days() in business-hours.php. Every require in this
// file completes before any template renders or any admin screen is built, so
// the ordering is belt-and-braces rather than load-bearing — but it puts the
// definitions above their consumers, which is how it reads.

require_once get_template_directory() . '/inc/schema/business-types.php';
require_once get_template_directory() . '/inc/schema/social-platforms.php';
require_once get_template_directory() . '/inc/schema/business-hours.php';
require_once get_template_directory() . '/inc/schema/schema-tokens.php';

// BlogPosting JSON-LD + article:* meta on single posts (v8.3.0); printed by
// header.php, filterable (roci_article_schema_post_types, roci_article_author,
// roci_article_schema).
require_once get_template_directory() . '/inc/schema/article-schema.php';


// ============================================================
// THEME SETTINGS PAGE
// ============================================================

require_once get_template_directory() . '/inc/theme-settings/settings-loader.php';


// ============================================================
// PAGES SETTINGS PAGE
// ============================================================

require_once get_template_directory() . '/inc/pages/pages-register.php';


// ============================================================
// PAGE IDENTIFIER REGISTRY
// ============================================================

require_once get_template_directory() . '/inc/pages/page-registry.php';


// ============================================================
// PER-PAGE ASSET LOADER
// ============================================================

require_once get_template_directory() . '/inc/pages/page-assets.php';


// ============================================================
// METABOX FIELD DEFINITIONS
// ============================================================

require_once get_template_directory() . '/inc/metabox/metabox-loader.php';


// ============================================================
// LAYOUT BUNDLES — per-vertical feature bundles (theme-support gated)
// ============================================================

require_once get_template_directory() . '/inc/layout-bundles/layout-bundles.php';


// ============================================================
// FORMS (roci-forms) — opt-in, v8.0.0
// ============================================================
//
// INERT UNLESS THE CHILD DECLARES add_theme_support( 'roci-forms' ). The files
// only define functions; every hook checks roci_forms_enabled() when it runs
// (the opt-in cannot be known at file load — see the loader's header). Forms
// are declared by the child on the roci_forms filter and embedded with
// [roci_form id="…"]. Turnstile keys are wp-config.php constants only.

require_once get_template_directory() . '/inc/forms/forms-loader.php';


// ============================================================
// MAIL — authenticated SMTP for wp_mail(), v8.1.0
// ============================================================
//
// INERT UNTIL wp-config.php DEFINES ROCI_SMTP_HOST, ROCI_SMTP_USER AND
// ROCI_SMTP_PASS (and steps aside for an active SMTP plugin, or for
// ROCI_SMTP_DISABLED). Not opt-in like the forms: every site sends mail. The
// sender and the notification recipient are Theme Settings → Mail; the
// credentials are never in the database. Also `wp roci mail-test` /
// `wp roci mail-status`.

require_once get_template_directory() . '/inc/mail/mail.php';


// ============================================================
// SITEMAP FILTERS
// ============================================================

require_once get_template_directory() . '/inc/sitemap.php';


// ============================================================
// LLMS.TXT — opt-in, Theme Settings → SEO (v8.4.0)
// ============================================================

require_once get_template_directory() . '/inc/llms-txt.php';


// ============================================================
// ARCHIVE SUPPRESSION
// ============================================================

require_once get_template_directory() . '/inc/archive-suppression.php';


// ============================================================
// COMMENT SUPPRESSION
// ============================================================

require_once get_template_directory() . '/inc/comment-suppression.php';


// ============================================================
// HELPER FUNCTIONS — image & video output
// ============================================================

require_once get_template_directory() . '/inc/helpers.php';


// ============================================================
// LCP PRELOAD — <link rel="preload"> for the page's hero image
// ============================================================
//
// Inert unless a child hooks roci_lcp_image. Must load after helpers.php: it
// builds the preload from jw_picture_sources() / jw_hero_picture_sources(), the
// same functions the hero markup uses, so the two can never disagree.

require_once get_template_directory() . '/inc/lcp-preload.php';


// ============================================================
// BLOG SHORTCODES
// ============================================================

require_once get_template_directory() . '/inc/blog-shortcodes.php';


// ============================================================
// FOLDER SYSTEM
// ============================================================

require_once get_template_directory() . '/inc/folders/folders.php';


// ============================================================
// REST GATE — meta_box and folder term IDs in post responses
// ============================================================

require_once get_template_directory() . '/inc/rest-gate.php';


// ============================================================
// MEDIA ADMIN
// ============================================================

require_once get_template_directory() . '/inc/media/media-used-on.php';
require_once get_template_directory() . '/inc/media/svg-support.php';


// ============================================================
// ADMIN CHROME
// ============================================================

require_once get_template_directory() . '/inc/admin/brand-scheme.php';


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
        // Intentionally unescaped: roci_sanitize_integrations() stores the value
        // raw only for users with the unfiltered_html capability, and runs
        // wp_kses_post() (which strips <script>) for everyone else. Wrapping
        // this in esc_html() would break the custom-scripts feature for admins.
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
        // Intentionally unescaped: roci_sanitize_integrations() stores the value
        // raw only for users with the unfiltered_html capability, and runs
        // wp_kses_post() (which strips <script>) for everyone else. Wrapping
        // this in esc_html() would break the custom-scripts feature for admins.
        echo $footer_script;
    }
}
add_action( 'wp_footer', 'el_rocinante_footer_scripts' );