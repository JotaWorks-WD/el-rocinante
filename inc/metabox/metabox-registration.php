<?php
/**
 * Metabox — Post Type Registration API
 *
 * Provides the registration functions child themes use to opt their
 * custom post types into the parent theme's SEO, Schema, and FAQ meta
 * boxes.
 *
 * The parent theme is content-agnostic and never names client-specific
 * CPT slugs in its own code (see CLAUDE.md section 12.4). This file
 * exposes the three extension points that child themes call to extend
 * the parent's meta boxes to their own CPTs.
 *
 * Usage from a child theme (e.g. in functions.php):
 *
 *     add_action( 'after_setup_theme', function() {
 *         roci_get_seo_post_types( array( 'tour', 'charter' ) );
 *         roci_get_schema_post_types( array( 'tour', 'charter' ) );
 *         roci_get_faq_post_types( 'tour' );
 *     } );
 *
 * Each function accepts either a single slug or an array of slugs.
 * Duplicates are silently ignored. Called with no argument, each
 * function returns the current list of post types.
 *
 * Internally, each function uses a static variable to accumulate slugs
 * across calls. This is a single-request lifecycle — the list resets
 * on every page load and rebuilds from defaults plus whatever child
 * themes register during that request.
 *
 * File:    inc/metabox/metabox-registration.php
 * Version: 1.0.0
 * Updated: 2026-05-24
 *
 * @package ElRocinante
 */

// ============================================================
// SEO META BOX — POST TYPE REGISTRATION
// ============================================================

/**
 * Get or extend the list of post types the SEO meta box appears on.
 *
 * Defaults to post and page. Child themes can extend this list by
 * calling the function with one or more post type slugs.
 *
 * @param  string|array|null $add Optional. A single post type slug, or
 *                                an array of slugs, to add to the list.
 *                                Omit to read the current list.
 * @return array                  The current list of post types,
 *                                deduplicated.
 */
function roci_get_seo_post_types( $add = null ) {
    static $types = array( 'post', 'page' );

    if ( $add !== null ) {
        if ( is_array( $add ) ) {
            $types = array_merge( $types, $add );
        } else {
            $types[] = $add;
        }
        $types = array_values( array_unique( $types ) );
    }

    return $types;
}


// ============================================================
// SCHEMA META BOX — POST TYPE REGISTRATION
// ============================================================

/**
 * Get or extend the list of post types the Schema meta box appears on.
 *
 * Defaults to post and page. Child themes can extend this list by
 * calling the function with one or more post type slugs.
 *
 * @param  string|array|null $add Optional. A single post type slug, or
 *                                an array of slugs, to add to the list.
 *                                Omit to read the current list.
 * @return array                  The current list of post types,
 *                                deduplicated.
 */
function roci_get_schema_post_types( $add = null ) {
    static $types = array( 'post', 'page' );

    if ( $add !== null ) {
        if ( is_array( $add ) ) {
            $types = array_merge( $types, $add );
        } else {
            $types[] = $add;
        }
        $types = array_values( array_unique( $types ) );
    }

    return $types;
}


// ============================================================
// FAQ META BOX — POST TYPE REGISTRATION
// ============================================================

/**
 * Get or extend the list of post types the FAQ meta box appears on.
 *
 * Defaults to post and page. Child themes can extend this list by
 * calling the function with one or more post type slugs.
 *
 * @param  string|array|null $add Optional. A single post type slug, or
 *                                an array of slugs, to add to the list.
 *                                Omit to read the current list.
 * @return array                  The current list of post types,
 *                                deduplicated.
 */
function roci_get_faq_post_types( $add = null ) {
    static $types = array( 'post', 'page' );

    if ( $add !== null ) {
        if ( is_array( $add ) ) {
            $types = array_merge( $types, $add );
        } else {
            $types[] = $add;
        }
        $types = array_values( array_unique( $types ) );
    }

    return $types;
}