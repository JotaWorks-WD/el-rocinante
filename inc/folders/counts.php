<?php
/**
 * Folder System — Count Helpers
 *
 * Three helpers used by the sidebar tree and dropdown filter to show item
 * counts next to folder names without running per-folder COUNT queries.
 *
 *   roci_get_folder_count()    — direct-item count for a single term
 *   roci_get_unassigned_count() — items with NO folder in the given taxonomy
 *   roci_get_all_count()        — total items for the taxonomy's post type
 *
 * IMPORTANT — status scope:
 *   WordPress maintains term_taxonomy.count via _update_post_term_count():
 *     attachment  → 'inherit' status only  (excludes trash, not 'publish')
 *     page        → 'publish' status only  (excludes trash, draft, pending)
 *   roci_get_unassigned_count() and roci_get_all_count() use the same
 *   status subsets so that All-count = folder-sum + unassigned-count holds.
 *   If you later need counts that include drafts/pending pages, fall back to
 *   a scoped $wpdb->get_var() query and adjust the caching accordingly.
 *
 * File:    inc/folders/counts.php
 * Version: 1.0.0
 * Updated: 2026-05-14
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// COUNTS — PER-FOLDER
// ============================================================

/**
 * Return the direct item count for a folder term.
 *
 * Uses term_taxonomy.count which WordPress keeps in sync automatically.
 * Direct items only — child-term items are NOT rolled up into the parent.
 * Trashed items excluded (WP excludes non-inherit/non-publish from the count).
 *
 * The $taxonomy parameter is accepted for API symmetry with the other helpers
 * and to allow a future override (e.g. a scoped query if term.count diverges
 * from what's visible on screen), but is not used in the current implementation.
 *
 * @param WP_Term $term      The folder term object (must have ->count populated).
 * @param string  $taxonomy  Taxonomy slug — reserved for future fallback logic.
 * @return int
 */
function roci_get_folder_count( $term, $taxonomy ) {
	return (int) $term->count;
}


// ============================================================
// COUNTS — UNASSIGNED
// ============================================================

/**
 * Count items that have no term assigned in the given folder taxonomy.
 *
 * Runs a WP_Query with a NOT EXISTS tax_query. SQL_CALC_FOUND_ROWS is used
 * (posts_per_page=1, no_found_rows=false) so only one row is fetched while
 * the DB engine still counts all matching rows. Result is cached in a static
 * array so multiple calls within the same request (sidebar + dropdown) hit
 * the cache after the first execution.
 *
 * Status scope matches term_taxonomy.count:
 *   roci_media_folder → 'inherit'   (all non-trashed attachments)
 *   roci_page_folder  → 'publish'   (published pages only)
 *
 * @param string $taxonomy  'roci_media_folder' or 'roci_page_folder'.
 * @return int
 */
function roci_get_unassigned_count( $taxonomy ) {

	static $cache = array();

	if ( isset( $cache[ $taxonomy ] ) ) {
		return $cache[ $taxonomy ];
	}

	$post_type = ( 'roci_media_folder' === $taxonomy ) ? 'attachment' : 'page';
	$status    = ( 'attachment' === $post_type ) ? 'inherit' : 'publish';

	$q = new WP_Query( array(
		'post_type'              => $post_type,
		'post_status'            => $status,
		'posts_per_page'         => 1,
		'no_found_rows'          => false,
		'fields'                 => 'ids',
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'tax_query'              => array(
			array(
				'taxonomy' => $taxonomy,
				'operator' => 'NOT EXISTS',
			),
		),
	) );

	$cache[ $taxonomy ] = (int) $q->found_posts;
	return $cache[ $taxonomy ];
}


// ============================================================
// COUNTS — ALL FILES
// ============================================================

/**
 * Count all items for the post type associated with a folder taxonomy.
 *
 * Delegates to wp_count_attachments() or wp_count_posts(), both of which
 * are internally cached by WordPress (object cache or static transient).
 * Returns only the status subset that term_taxonomy.count uses:
 *   roci_media_folder → $counts->inherit
 *   roci_page_folder  → $counts->publish
 *
 * @param string $taxonomy  'roci_media_folder' or 'roci_page_folder'.
 * @return int
 */
function roci_get_all_count( $taxonomy ) {

	if ( 'roci_media_folder' === $taxonomy ) {
		$counts = wp_count_attachments();
		return isset( $counts->inherit ) ? (int) $counts->inherit : 0;
	}

	$counts = wp_count_posts( 'page' );
	return isset( $counts->publish ) ? (int) $counts->publish : 0;
}
