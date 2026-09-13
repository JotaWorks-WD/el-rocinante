<?php
/**
 * Sitemap Filters
 *
 * Restricts the WordPress XML sitemap to posts, pages, and whatever
 * post types a child theme declares via `roci_sitemap_post_types`.
 * Removes users and unwanted taxonomies.
 *
 * File:    inc/sitemap.php
 * Version: 1.1.0
 * Updated: 2026-09-13
 *
 * @package ElRocinante
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// SITEMAP FILTERS
// Posts, pages, and child-declared post types — remove everything else
// ============================================================

// Remove authors/users from sitemap
add_filter( 'wp_sitemaps_add_provider', function( $provider, $name ) {
    if ( $name === 'users' ) return false;
    return $provider;
}, 10, 2 );

// Remove unwanted taxonomies
add_filter( 'wp_sitemaps_taxonomies', function( $taxonomies ) {
    unset( $taxonomies['category'] );
    unset( $taxonomies['post_tag'] );
    unset( $taxonomies['post_format'] );
    return $taxonomies;
} );

/**
 * Keep only the allowed post types.
 *
 * The allowlist defaults to core's `post` and `page`. A child theme adds its
 * own CPTs with the `roci_sitemap_post_types` filter — do NOT hardcode a
 * client's post types into the default array below, which ships to every site
 * on the network:
 *
 *     add_filter( 'roci_sitemap_post_types', function( $types ) {
 *         return array_merge( $types, array( 'charter', 'tour' ) );
 *     } );
 *
 * A CPT must ALSO be `public => true` to appear — this filter can only narrow
 * what WP core already offers, never widen it. Core builds the provider list
 * from `get_post_types( array( 'public' => true ) )`, so naming a private post
 * type here is a silent no-op rather than an error.
 *
 * Unlike the `roci_get_seo_post_types()` family, nothing here is statically
 * cached: the filter fires on each sitemap request, so a late `add_filter()`
 * still takes effect.
 */
add_filter( 'wp_sitemaps_post_types', function( $post_types ) {
    $allowed = apply_filters( 'roci_sitemap_post_types', array( 'post', 'page' ) );
    foreach ( $post_types as $key => $post_type ) {
        if ( ! in_array( $key, $allowed ) ) {
            unset( $post_types[ $key ] );
        }
    }
    return $post_types;
} );
