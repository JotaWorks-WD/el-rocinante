<?php
/**
 * Folder System — Sort Order
 *
 * Manages the persistent sort order for folder terms via term meta.
 * Provides a shared query-args helper, a lazy migration function, and an
 * AJAX endpoint that lets the client commit a new sibling order after drag.
 *
 *   roci_get_folder_order_query_args()    — shared get_terms sort args
 *   roci_maybe_initialize_folder_order()  — lazy term-meta seed (idempotent)
 *   roci_ajax_reorder_folders()           — wp_ajax_roci_reorder_folders
 *   roci_enqueue_reorder_assets()         — enqueues dist/js/folders/folders-reorder.js
 *
 * File:    inc/folders/order.php
 * Version: 1.2.0
 * Updated: 2026-05-16
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// SORT ORDER — SHARED QUERY ARGS
// ============================================================

/**
 * Return the get_terms args that sort folders by roci_folder_order meta.
 *
 * Single source of truth — all callers merge this into their own args so
 * the sort strategy can be changed in one place.
 *
 * @return array
 */
function roci_get_folder_order_query_args() {
	return array(
		'meta_key' => 'roci_folder_order',
		'orderby'  => 'meta_value_num',
		'order'    => 'ASC',
	);
}


// ============================================================
// SORT ORDER — LAZY MIGRATION
// ============================================================

/**
 * Seed roci_folder_order term meta for any terms that don't have it yet.
 *
 * Called from roci_get_folder_tree_html() before the primary get_terms
 * query. On the first ever call it iterates all roci_media_folder terms
 * sorted by name and assigns order values spaced by 10 (10, 20, 30…).
 * Subsequent calls return immediately via the option flag.
 *
 * Mirrors the fast-path pattern used by roci_maybe_recount_folder_terms()
 * in counts.php — same option-flag guard, same early-return shape.
 */
function roci_maybe_initialize_folder_order() {

	if ( get_option( '_roci_folder_order_initialized_v1' ) ) {
		return;
	}

	$terms = get_terms( array(
		'taxonomy'   => 'roci_media_folder',
		'orderby'    => 'name',
		'order'      => 'ASC',
		'hide_empty' => false,
	) );

	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $index => $term ) {
			if ( '' === get_term_meta( $term->term_id, 'roci_folder_order', true ) ) {
				update_term_meta( $term->term_id, 'roci_folder_order', ( $index + 1 ) * 10 );
			}
		}
	}

	update_option( '_roci_folder_order_initialized_v1', 1, false );
}


// ============================================================
// REORDER FOLDERS — AJAX HANDLER
// ============================================================

/**
 * Persist a new sibling order for roci_media_folder terms.
 *
 * Security chain:
 *   1. Nonce verification (dies on failure).
 *   2. manage_categories capability check.
 *   3. parent_id validated as a real roci_media_folder term (if non-zero).
 *   4. ordered_term_ids validated: non-empty, no duplicates, each a real term,
 *      each term's parent matches submitted parent_id, list is complete (all
 *      children of the parent must be present — no partial reorders).
 *   5. Loop and update roci_folder_order meta.
 *
 * Error HTTP codes: 400 (bad input), 403 (capability/nonce), 500 (db error).
 */
function roci_ajax_reorder_folders() {

	check_ajax_referer( 'roci_reorder_folders', 'nonce' );

	// ── Capability check ─────────────────────────────────────────────────
	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_send_json_error( __( 'You do not have permission to reorder fauxlders.', 'rocinante' ), 403 );
	}

	// ── Validate parent ───────────────────────────────────────────────────
	$parent_id = absint( isset( $_POST['parent_id'] ) ? $_POST['parent_id'] : 0 );

	if ( $parent_id > 0 && ! roci_validate_folder_term( $parent_id, 'roci_media_folder' ) ) {
		wp_send_json_error( __( 'Invalid parent fauxlder.', 'rocinante' ), 400 );
	}

	// ── Parse and sanitize ordered_term_ids ──────────────────────────────
	$raw         = isset( $_POST['ordered_term_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ordered_term_ids'] ) ) : '';
	$ordered_ids = array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );

	if ( empty( $ordered_ids ) ) {
		wp_send_json_error( 'No term IDs provided.', 400 );
	}

	// ── Validate: no duplicates ───────────────────────────────────────────
	if ( count( $ordered_ids ) !== count( array_unique( $ordered_ids ) ) ) {
		wp_send_json_error( 'Duplicate term IDs in order list.', 400 );
	}

	// ── Validate: each ID is a real roci_media_folder term ───────────────
	foreach ( $ordered_ids as $term_id ) {
		if ( ! roci_validate_folder_term( $term_id, 'roci_media_folder' ) ) {
			wp_send_json_error( 'Invalid term in order list.', 400 );
		}
	}

	// ── Validate: each term's parent matches submitted parent_id ─────────
	foreach ( $ordered_ids as $term_id ) {
		$term = get_term( $term_id, 'roci_media_folder' );
		if ( is_wp_error( $term ) || ! $term || (int) $term->parent !== $parent_id ) {
			wp_send_json_error( 'Term parent mismatch — cannot reparent via this endpoint.', 400 );
		}
	}

	// ── Validate: submitted list contains ALL children of the parent ──────
	$all_children = get_terms( array(
		'taxonomy'   => 'roci_media_folder',
		'parent'     => $parent_id,
		'hide_empty' => false,
		'fields'     => 'ids',
	) );

	if ( is_wp_error( $all_children ) ) {
		wp_send_json_error( 'Could not fetch sibling list.', 500 );
	}

	$submitted_sorted = $ordered_ids;
	$existing_sorted  = array_map( 'intval', (array) $all_children );
	sort( $submitted_sorted );
	sort( $existing_sorted );

	if ( $submitted_sorted !== $existing_sorted ) {
		wp_send_json_error( 'Incomplete sibling list — must include all children of the parent.', 400 );
	}

	// ── Persist new order ─────────────────────────────────────────────────
	foreach ( $ordered_ids as $index => $term_id ) {
		$result = update_term_meta( $term_id, 'roci_folder_order', ( $index + 1 ) * 10 );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 500 );
		}
	}

	// ── Success response ──────────────────────────────────────────────────
	$options = roci_build_folder_options_for_select( 'roci_media_folder' );

	wp_send_json_success( array(
		'parent_id' => $parent_id,
		'ordered'   => array_map(
			function ( $id, $index ) {
				return array(
					'term_id' => $id,
					'order'   => ( $index + 1 ) * 10,
				);
			},
			$ordered_ids,
			array_keys( $ordered_ids )
		),
		'options'   => $options,
	) );
}
add_action( 'wp_ajax_roci_reorder_folders', 'roci_ajax_reorder_folders' );


// ============================================================
// NEW TERM — AUTO-ASSIGN DEFAULT ORDER
// ============================================================

/**
 * Assign a default roci_folder_order to a newly created folder term.
 *
 * Hooked to created_{taxonomy} so every term-creation path (modal, WP admin
 * UI, WP-CLI, plugin imports) triggers this — not just roci_ajax_create_folder.
 *
 * Determines the taxonomy from current_filter() so one function covers both
 * taxonomies. Finds the maximum roci_folder_order value among existing siblings
 * (same parent, same taxonomy, meta already set) and assigns max + 10, placing
 * the new folder last in its sibling group.
 *
 * Defensive guard: if the meta already exists (e.g. seeded by
 * roci_maybe_initialize_folder_order for a pre-existing term that was missed)
 * the function returns without overwriting.
 *
 * @param int $term_id  Newly created term ID.
 */
function roci_assign_default_folder_order( $term_id ) {

	$taxonomy = str_replace( 'created_', '', current_filter() );
	if ( ! in_array( $taxonomy, array( 'roci_media_folder', 'roci_page_folder' ), true ) ) {
		return;
	}

	// Defensive: don't overwrite an order that already exists.
	if ( '' !== get_term_meta( $term_id, 'roci_folder_order', true ) ) {
		return;
	}

	$term = get_term( $term_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}

	// Find the highest existing order among siblings that already have the meta.
	$siblings_with_order = get_terms( array(
		'taxonomy'   => $taxonomy,
		'parent'     => (int) $term->parent,
		'hide_empty' => false,
		'fields'     => 'ids',
		'meta_key'   => 'roci_folder_order',
		'orderby'    => 'meta_value_num',
		'order'      => 'DESC',
		'number'     => 1,
		'exclude'    => array( $term_id ),
	) );

	$max_order = 0;
	if ( ! is_wp_error( $siblings_with_order ) && ! empty( $siblings_with_order ) ) {
		$max_order = (int) get_term_meta( (int) $siblings_with_order[0], 'roci_folder_order', true );
	}

	update_term_meta( $term_id, 'roci_folder_order', $max_order + 10 );
}
add_action( 'created_roci_media_folder', 'roci_assign_default_folder_order' );
add_action( 'created_roci_page_folder',  'roci_assign_default_folder_order' );


// ============================================================
// REORDER — ENQUEUE ASSETS
// ============================================================

/**
 * Enqueue folders-reorder.js on the Media Library screen.
 *
 * Only loads on upload.php — reordering is not available in the modal
 * media picker or list-view.
 *
 * Depends on 'media-views' (consistent with folders-dragdrop.js).
 *
 * @param string $hook_suffix  Current admin page hook suffix.
 */
function roci_enqueue_reorder_assets( $hook_suffix ) {

	if ( 'upload.php' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_script(
		'roci-folders-reorder',
		get_template_directory_uri() . '/dist/js/folders/folders-reorder.js',
		array( 'media-views' ),
		roci_asset_version( 'dist/js/folders/folders-reorder.js' ),
		true
	);

	wp_localize_script( 'roci-folders-reorder', 'rociFoldersReorder', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'roci_reorder_folders' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_enqueue_reorder_assets' );
