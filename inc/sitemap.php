<?php
/**
 * Sitemap Filters
 *
 * Restricts the WordPress XML sitemap to posts, pages, and whatever
 * post types a child theme declares via `roci_sitemap_post_types`.
 * Removes users and unwanted taxonomies, and (1.2.0) leaves out any post or
 * page whose roci_robots starts with "noindex" (roci_sitemap_skip_noindex()).
 *
 * File:    inc/sitemap.php
 * Version: 1.2.0
 * Updated: 2026-11-19
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

/**
 * roci_sitemap_skip_noindex() — 1.2.0 (El Rocinante 8.11.2)
 *
 * Leave noindexed posts and pages out of the XML sitemap: any entry whose
 * roci_robots meta STARTS WITH "noindex" ("noindex, follow", "noindex,
 * nofollow"). Entries with no roci_robots row, or any other value, stay.
 *
 * The clause is NOT REGEXP '^noindex' OR NOT EXISTS. Not NOT LIKE: core wraps a
 * LIKE value in % on both sides, which would test "contains", not "starts
 * with". With an OR that includes NOT EXISTS, core makes every meta JOIN a LEFT
 * JOIN and groups by post ID, so a post without the meta is never lost and
 * never listed twice.
 *
 * MERGED, never replaced: an existing meta_query (a child's own sitemap rule)
 * is kept, ANDed with this clause. Priority 20, so a child callback at the
 * default 10 that SETS meta_query outright runs first and is merged too.
 *
 * Core applies this filter to both the URL list and the page count
 * (WP_Sitemaps_Posts::get_url_list() and get_max_num_pages() share
 * get_posts_query_args()), so pagination follows. Which post types are listed
 * is still wp_sitemaps_post_types + roci_sitemap_post_types, above.
 *
 * A site with no noindexed entry gets the same URLs in the same order (ID
 * ascending, core's), so the same sitemap.
 *
 * The robots field itself: inc/metabox/metabox-seo-fields.php ('roci_robots';
 * the per-page rule in roci_effective_robots()). Not consulted here: the
 * search / 404 and blog_public overrides, which never apply to a listed post
 * (and core serves no sitemap at all while blog_public is 0).
 *
 * @param  array  $args      WP_Query args for this sitemap page.
 * @param  string $post_type The post type being listed.
 * @return array
 */
function roci_sitemap_skip_noindex( $args, $post_type ) {
    $clause = array(
        'relation' => 'OR',
        array(
            'key'     => 'roci_robots',
            'compare' => 'NOT EXISTS',
        ),
        array(
            'key'     => 'roci_robots',
            'value'   => '^noindex',
            'compare' => 'NOT REGEXP',
        ),
    );

    $existing = ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) ? $args['meta_query'] : array();

    $args['meta_query'] = empty( $existing )
        ? array( $clause )
        : array(
            'relation' => 'AND',
            $existing,
            $clause,
        );

    return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'roci_sitemap_skip_noindex', 20, 2 );
