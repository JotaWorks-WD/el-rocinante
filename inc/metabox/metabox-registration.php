<?php
/**
 * Metabox — Post Type Registration API
 *
 * Provides the filter hooks child themes use to opt their custom post
 * types into the parent theme's SEO, Schema, and FAQ meta boxes.
 *
 * The parent theme is content-agnostic and never names client-specific
 * CPT slugs in its own code (see CLAUDE.md section 12.4). This file
 * exposes the three extension points child themes hook into to extend
 * the parent's meta boxes to their own CPTs.
 *
 * Usage from a child theme (e.g. in functions.php):
 *
 *     add_filter( 'roci_seo_post_types', function( $types ) {
 *         $types[] = 'my_cpt';
 *         return $types;
 *     } );
 *
 * Use roci_schema_post_types and roci_faq_post_types for the other two
 * boxes. Each function dispatches its filter once per request and caches
 * the result in a static variable.
 *
 * File:    inc/metabox/metabox-registration.php
 * Version: 1.0.2
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
 * @return array Post type slugs receiving the SEO meta box.
 */
function roci_get_seo_post_types() {
    static $types = null;

    if ( null === $types ) {
        $types = apply_filters( 'roci_seo_post_types', array( 'post', 'page' ) );
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
 * @return array Post type slugs receiving the Schema meta box.
 */
function roci_get_schema_post_types() {
    static $types = null;

    if ( null === $types ) {
        $types = apply_filters( 'roci_schema_post_types', array( 'post', 'page' ) );
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
 * @return array Post type slugs receiving the FAQ meta box.
 */
function roci_get_faq_post_types() {
    static $types = null;

    if ( null === $types ) {
        $types = apply_filters( 'roci_faq_post_types', array( 'post', 'page' ) );
    }

    return $types;
}