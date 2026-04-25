<?php

// ============================================================
// SITEMAP FILTERS
// Posts and pages only — remove everything else
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

// Keep only posts and pages
// To add a custom post type per client, add it to this array
add_filter( 'wp_sitemaps_post_types', function( $post_types ) {
    $allowed = array( 'post', 'page' );
    foreach ( $post_types as $key => $post_type ) {
        if ( ! in_array( $key, $allowed ) ) {
            unset( $post_types[ $key ] );
        }
    }
    return $post_types;
} );