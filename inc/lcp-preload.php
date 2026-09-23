<?php
/**
 * LCP Preload — <link rel="preload"> for the current page's hero image
 *
 * Emits, at the very top of wp_head(), a preload for the page's LCP hero so the
 * browser starts fetching it with the first bytes of HTML instead of
 * discovering it below the head, the loader and the nav markup.
 *
 * WHICH IMAGE: a child decides, through the roci_lcp_image filter. The parent's
 * own templates render no hero, so its default is "none" and a site that does
 * not hook the filter emits nothing. A child returns a descriptor for the
 * current request:
 *
 *   add_filter( 'roci_lcp_image', function ( $hero, $context ) {
 *       // jw_picture() hero:
 *       return array( 'id' => 123, 'size' => 'large' );
 *       // jw_hero_picture() hero (art-directed desktop + mobile crops):
 *       return array( 'id' => 123, 'mobile_id' => 456 );
 *   }, 10, 2 );
 *
 * The descriptor must describe EXACTLY the call the template makes: same
 * attachment, same size, same helper. Only EAGER heroes belong here — the
 * preload always uses fetchpriority="high" and the eager sizes value, which is
 * what jw_picture( …, 'eager' ) / jw_hero_picture() render. A lazy image
 * preloaded would be a download the page never asked for early.
 *
 * ⚠ THE PRELOAD IS BUILT FROM THE SAME FUNCTIONS AS THE MARKUP. imagesrcset and
 * imagesizes come from jw_picture_sources() / jw_hero_picture_sources(), the
 * functions jw_picture() and jw_hero_picture() themselves call, and are escaped
 * the same way. If they differed by a single byte the browser would treat the
 * preload as a different resource and download the hero twice. Never
 * hand-assemble either value here.
 *
 * WHICH VARIANT. jw_picture() renders <picture><source type="image/webp">
 * <img></picture>; a WebP-capable browser uses the <source>. So the preload
 * mirrors the <source> (with type="image/webp") whenever one renders, and
 * mirrors the <img> otherwise. When originals are native WebP the two srcsets
 * are identical strings, so this is also exactly the <img>'s srcset.
 *
 * jw_hero_picture() is art-directed: the desktop <source> applies at
 * (min-width: 768px) and the mobile <img> below it. That emits TWO preloads,
 * each gated by the matching media query, so the browser still fetches only
 * one of them.
 *
 * File:    inc/lcp-preload.php
 * Version: 1.0.0
 * Updated: 2026-09-23
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }


/**
 * Print the hero preload for the current request, if the child names one.
 *
 * PRIORITY 0. It must precede every stylesheet (printed at wp_head priority 8)
 * and the child's own font preload at priority 1. A child's functions.php is
 * loaded BEFORE the parent's, so a parent hook at the same priority 1 would
 * run after the child's and land below the font preload.
 *
 * @return void
 */
function roci_hero_preload() {

    if ( is_admin() || is_feed() ) {
        return;
    }

    $context = array(
        'object_id' => get_queried_object_id(),
    );

    /**
     * Filter: the current page's LCP hero, or null for none.
     *
     * @param array|null $hero    null (no hero), or array( 'id' => int,
     *                            'size' => string ) for a jw_picture() hero, or
     *                            array( 'id' => int, 'mobile_id' => int ) for a
     *                            jw_hero_picture() hero. Optional 'sizes' mirrors
     *                            a caller-supplied jw_picture() $sizes.
     * @param array      $context array( 'object_id' => queried object ID ).
     */
    $hero = apply_filters( 'roci_lcp_image', null, $context );

    if ( ! is_array( $hero ) || empty( $hero['id'] ) ) {
        return;
    }

    $links = array();

    if ( ! empty( $hero['mobile_id'] ) ) {

        // Art-directed hero — mirrors jw_hero_picture() exactly.
        $sources = jw_hero_picture_sources( (int) $hero['id'], (int) $hero['mobile_id'], 'eager' );

        if ( ! $sources ) {
            return;
        }

        // Desktop: the <source media="(min-width: 768px)" type="image/webp">.
        $links[] = $sources['desktop_webp_srcset']
            ? ' media="(min-width: 768px)" type="image/webp" imagesrcset="' . esc_attr( $sources['desktop_webp_srcset'] ) . '"'
                . ( $sources['desktop_sizes'] ? ' imagesizes="' . esc_attr( $sources['desktop_sizes'] ) . '"' : '' )
            : ' media="(min-width: 768px)" type="image/webp" href="' . esc_url( $sources['desktop_src'] ) . '"';

        // Mobile: the <img>, which applies whenever the desktop media query
        // does not — the exact complement, so one and only one is fetched.
        $links[] = $sources['srcset']
            ? ' media="not all and (min-width: 768px)" imagesrcset="' . esc_attr( $sources['srcset'] ) . '"'
                . ( $sources['sizes'] ? ' imagesizes="' . esc_attr( $sources['sizes'] ) . '"' : '' )
            : ' media="not all and (min-width: 768px)" href="' . esc_url( $sources['mobile_src'] ) . '"';

    } else {

        // Standard hero — mirrors jw_picture( id, size, …, 'eager', sizes ).
        $size    = ! empty( $hero['size'] ) ? (string) $hero['size'] : 'full';
        $sizes   = isset( $hero['sizes'] ) ? $hero['sizes'] : null;
        $sources = jw_picture_sources( (int) $hero['id'], $size, 'eager', $sizes );

        if ( ! $sources ) {
            return;
        }

        if ( $sources['webp_src'] ) {
            // The <source type="image/webp"> renders, so that is what a
            // WebP-capable browser uses. Same attr choices as jw_picture():
            // candidate list with sizes, or the single URL without.
            $links[] = $sources['webp_srcset']
                ? ' type="image/webp" imagesrcset="' . esc_attr( $sources['webp_srcset'] ) . '"'
                    . ( $sources['sizes'] ? ' imagesizes="' . esc_attr( $sources['sizes'] ) . '"' : '' )
                : ' type="image/webp" href="' . esc_url( (string) $sources['webp_src'] ) . '"';
        } else {
            // No <source>: the <img> is what renders.
            $links[] = $sources['srcset']
                ? ' imagesrcset="' . esc_attr( $sources['srcset'] ) . '"'
                    . ( $sources['sizes'] ? ' imagesizes="' . esc_attr( $sources['sizes'] ) . '"' : '' )
                : ' href="' . esc_url( $sources['img_src'] ) . '"';
        }
    }

    // No href alongside imagesrcset, deliberately: a browser that does not
    // support imagesrcset would fetch the href, which may not be the candidate
    // it later picks from the srcset — a double download. Without an href it
    // simply ignores the preload and loads the image normally.
    foreach ( $links as $attrs ) {
        echo '<link rel="preload" as="image" fetchpriority="high"' . $attrs . '>' . "\n";
    }
}
add_action( 'wp_head', 'roci_hero_preload', 0 );
