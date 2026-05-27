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
 * Version: 1.0.1
 * Updated: 2026-05-27
 *
 * @package ElRocinante
 */

// ============================================================
// SEO META BOX — POST TYPE REGISTRATION
// ============================================================

/**
 * Get the list of post types that receive the SEO meta box.
 *
 * Child themes extend the list via the `roci_seo_post_types` filter:
 *
 *     add_filter( 'roci_seo_post_types', function( $types ) {
 *         $types[] = 'my_cpt';
 *         return $types;
 *     } );
 *
 * The $add parameter is deprecated — prefer the filter. Calls passing
 * $add still work but emit no deprecation notice yet (planned removal
 * in a subsequent commit).
 *
 * @param array|string|null $add Deprecated. Use the roci_seo_post_types filter instead.
 * @return array Post type slugs receiving the SEO meta box.
 */
function roci_get_seo_post_types( $add = null ) {
    static $types = null;

    if ( null === $types ) {
        $types = apply_filters( 'roci_seo_post_types', array( 'post', 'page' ) );
    }

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
 * Get the list of post types that receive the Schema meta box.
 *
 * Child themes extend the list via the `roci_schema_post_types` filter:
 *
 *     add_filter( 'roci_schema_post_types', function( $types ) {
 *         $types[] = 'my_cpt';
 *         return $types;
 *     } );
 *
 * The $add parameter is deprecated — prefer the filter. Calls passing
 * $add still work but emit no deprecation notice yet (planned removal
 * in a subsequent commit).
 *
 * @param array|string|null $add Deprecated. Use the roci_schema_post_types filter instead.
 * @return array Post type slugs receiving the Schema meta box.
 */
function roci_get_schema_post_types( $add = null ) {
    static $types = null;

    if ( null === $types ) {
        $types = apply_filters( 'roci_schema_post_types', array( 'post', 'page' ) );
    }

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
 * Get the list of post types that receive the FAQ meta box.
 *
 * Child themes extend the list via the `roci_faq_post_types` filter:
 *
 *     add_filter( 'roci_faq_post_types', function( $types ) {
 *         $types[] = 'my_cpt';
 *         return $types;
 *     } );
 *
 * The $add parameter is deprecated — prefer the filter. Calls passing
 * $add still work but emit no deprecation notice yet (planned removal
 * in a subsequent commit).
 *
 * @param array|string|null $add Deprecated. Use the roci_faq_post_types filter instead.
 * @return array Post type slugs receiving the FAQ meta box.
 */
function roci_get_faq_post_types( $add = null ) {
    static $types = null;

    if ( null === $types ) {
        $types = apply_filters( 'roci_faq_post_types', array( 'post', 'page' ) );
    }

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