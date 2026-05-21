<?php
/**
 * Folder System — Move (attachments + CPT items)
 *
 * AJAX endpoints for single/bulk attachment moves and CPT item moves.
 * Provides the generic drag-handle column for CPT list tables.
 * Single enqueue callback handles both the Media grid (upload.php) and
 * all CPT list screens registered via roci_register_folder_type().
 *
 *   roci_ajax_move_attachment()            — wp_ajax_roci_move_attachment (Media single)
 *   roci_ajax_bulk_move_attachments()      — wp_ajax_roci_bulk_move_attachments
 *   roci_ajax_bulk_undo_move_attachments() — wp_ajax_roci_bulk_undo_move_attachments
 *   roci_ajax_bulk_delete_attachments()    — wp_ajax_roci_bulk_delete_attachments
 *   roci_ajax_move_item_to_folder()        — wp_ajax_roci_move_item_to_folder (CPT)
 *   roci_folder_drag_column_filter()       — manage_{post_type}_posts_columns
 *   roci_folder_drag_column_render()       — manage_{post_type}_posts_custom_column
 *   roci_enqueue_dragdrop_assets()         — enqueues drag JS for Media + CPT list screens
 *
 * File:    inc/folders/move.php
 * Version: 1.4.0
 * Updated: 2026-05-21
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// MOVE ATTACHMENT — AJAX HANDLER
// ============================================================

/**
 * Reassign an attachment to a different roci_media_folder term (or unassigned).
 *
 * Security chain:
 *   1. Nonce verification (dies on failure, HTTP 403).
 *   2. attachment_id validated as a real attachment post.
 *   3. edit_post capability check for the specific attachment.
 *   4. target_term validated — either '__unassigned__' sentinel or a real term ID
 *      in roci_media_folder via roci_validate_folder_term() from upload.php.
 *   5. Previous terms captured before the move for the success response.
 *   6. wp_set_object_terms() with append=false replaces all folder assignments.
 *
 * Error HTTP codes are regularised (400/403/500) — does not follow the
 * 200-with-success-false pattern used by create.php.
 *
 * Success response payload gives both previous and new term arrays so the
 * client can compute count deltas without a separate lookup.
 */
function roci_ajax_move_attachment() {

	check_ajax_referer( 'roci_move_attachment', 'nonce' );

	// ── Validate attachment ──────────────────────────────────────────────
	$attachment_id = absint( isset( $_POST['attachment_id'] ) ? $_POST['attachment_id'] : 0 );
	if ( ! $attachment_id || get_post_type( $attachment_id ) !== 'attachment' ) {
		wp_send_json_error( __( 'Invalid attachment.', 'rocinante' ), 400 );
	}

	// ── Capability check ─────────────────────────────────────────────────
	if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
		wp_send_json_error( __( 'You do not have permission to move this attachment.', 'rocinante' ), 403 );
	}

	// ── Validate target term ─────────────────────────────────────────────
	$target_term  = isset( $_POST['target_term'] ) ? sanitize_text_field( wp_unslash( $_POST['target_term'] ) ) : '';
	$terms_to_set = array();

	if ( '__unassigned__' === $target_term ) {
		$terms_to_set = array();
	} else {
		$term_id = absint( $target_term );
		if ( ! $term_id || ! roci_validate_folder_term( $term_id, 'roci_media_folder' ) ) {
			wp_send_json_error( __( 'Invalid target fauxlder.', 'rocinante' ), 400 );
		}
		$terms_to_set = array( $term_id );
	}

	// ── Capture previous terms before the move ───────────────────────────
	$previous = wp_get_object_terms( $attachment_id, 'roci_media_folder', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $previous ) ) {
		$previous = array();
	}

	// ── Perform the move ─────────────────────────────────────────────────
	// append=false: replaces all existing roci_media_folder assignments,
	// matching the same semantics used by roci_assign_upload_folder() in upload.php.
	$result = wp_set_object_terms( $attachment_id, $terms_to_set, 'roci_media_folder', false );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message(), 500 );
	}

	wp_send_json_success( array(
		'attachment_id'  => $attachment_id,
		'previous_terms' => array_map( 'intval', $previous ),
		'new_terms'      => array_map( 'intval', $terms_to_set ),
	) );
}
add_action( 'wp_ajax_roci_move_attachment', 'roci_ajax_move_attachment' );


// ============================================================
// BULK MOVE — AJAX HANDLER
// ============================================================

/**
 * Bulk-reassign multiple attachments to a folder (or unassigned).
 *
 * Reuses the roci_move_attachment nonce so no separate localisation is needed.
 * Each attachment is validated and moved individually; partial success is
 * allowed — moved and failed IDs are both returned. previous_assignments maps
 * each moved ID to its prior term array (or null for previously unassigned)
 * so the client can pass it back to roci_ajax_bulk_undo_move_attachments.
 *
 * @param int[]  $_POST['attachment_ids']  Array of attachment post IDs.
 * @param string $_POST['target_term']     '__unassigned__' or numeric term ID.
 */
function roci_ajax_bulk_move_attachments() {

	check_ajax_referer( 'roci_move_attachment', 'nonce' );

	// ── Validate & sanitize attachment IDs ──────────────────────────────
	$raw_ids = isset( $_POST['attachment_ids'] ) ? (array) $_POST['attachment_ids'] : array();
	if ( empty( $raw_ids ) ) {
		wp_send_json_error( __( 'No attachments specified.', 'rocinante' ), 400 );
	}
	$attachment_ids = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );
	if ( empty( $attachment_ids ) ) {
		wp_send_json_error( __( 'Invalid attachment IDs.', 'rocinante' ), 400 );
	}

	// ── Validate target term ─────────────────────────────────────────────
	$target_raw   = isset( $_POST['target_term'] ) ? sanitize_text_field( wp_unslash( $_POST['target_term'] ) ) : '';
	$terms_to_set = array();
	$target_name  = '';
	$target_id    = 0;

	if ( '__unassigned__' === $target_raw ) {
		$terms_to_set = array();
	} else {
		$target_id = absint( $target_raw );
		if ( ! $target_id || ! roci_validate_folder_term( $target_id, 'roci_media_folder' ) ) {
			wp_send_json_error( __( 'Invalid target fauxlder.', 'rocinante' ), 400 );
		}
		$terms_to_set = array( $target_id );
		$term = get_term( $target_id, 'roci_media_folder' );
		if ( $term && ! is_wp_error( $term ) ) {
			$target_name = $term->name;
		}
	}

	// ── Move each attachment ─────────────────────────────────────────────
	$moved        = array();
	$failed       = array();
	$prev_assign  = array();

	foreach ( $attachment_ids as $id ) {
		if ( get_post_type( $id ) !== 'attachment' ) {
			$failed[] = $id;
			continue;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			$failed[] = $id;
			continue;
		}

		$previous = wp_get_object_terms( $id, 'roci_media_folder', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $previous ) ) {
			$previous = array();
		}

		$result = wp_set_object_terms( $id, $terms_to_set, 'roci_media_folder', false );
		if ( is_wp_error( $result ) ) {
			$failed[] = $id;
			continue;
		}

		$moved[]              = $id;
		$prev_assign[ $id ]   = empty( $previous ) ? null : array_map( 'intval', $previous );
	}

	wp_send_json_success( array(
		'moved'                => $moved,
		'failed'               => $failed,
		'target_name'          => $target_name,
		'previous_assignments' => $prev_assign,
	) );
}
add_action( 'wp_ajax_roci_bulk_move_attachments', 'roci_ajax_bulk_move_attachments' );


// ============================================================
// BULK UNDO — AJAX HANDLER
// ============================================================

/**
 * Reverse a bulk move using the previous_assignments map from the forward move.
 *
 * Accepts a JSON-encoded assignments object: { "id": [term_ids] | null }.
 * null means the attachment was previously unassigned. Each attachment is
 * moved back to its individual prior state, supporting mixed-origin undos.
 *
 * @param string $_POST['assignments']  JSON: { "50": [12], "51": null, ... }
 */
function roci_ajax_bulk_undo_move_attachments() {

	check_ajax_referer( 'roci_move_attachment', 'nonce' );

	$raw = isset( $_POST['assignments'] ) ? sanitize_text_field( wp_unslash( $_POST['assignments'] ) ) : '';
	// JSON decode — sanitize_text_field strips slashes, safe for this use.
	$assignments = json_decode( $raw, true );
	if ( ! is_array( $assignments ) || empty( $assignments ) ) {
		wp_send_json_error( __( 'Invalid assignments.', 'rocinante' ), 400 );
	}

	$moved  = array();
	$failed = array();

	foreach ( $assignments as $attachment_id => $prev_terms ) {
		$id = absint( $attachment_id );
		if ( ! $id || get_post_type( $id ) !== 'attachment' ) {
			$failed[] = $id;
			continue;
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			$failed[] = $id;
			continue;
		}

		// null → previously unassigned → move back to unassigned (empty array).
		$terms_to_set = ( is_array( $prev_terms ) && ! empty( $prev_terms ) )
			? array_values( array_filter( array_map( 'absint', $prev_terms ) ) )
			: array();

		$result = wp_set_object_terms( $id, $terms_to_set, 'roci_media_folder', false );
		if ( is_wp_error( $result ) ) {
			$failed[] = $id;
			continue;
		}
		$moved[] = $id;
	}

	wp_send_json_success( array(
		'moved'  => $moved,
		'failed' => $failed,
	) );
}
add_action( 'wp_ajax_roci_bulk_undo_move_attachments', 'roci_ajax_bulk_undo_move_attachments' );


// ============================================================
// BULK DELETE — AJAX HANDLER
// ============================================================

/**
 * Permanently delete multiple attachments.
 *
 * Reuses the roci_move_attachment nonce (same AJAX context — same page,
 * same user session). Each attachment is validated and deleted individually;
 * partial success is allowed. wp_delete_attachment( $id, true ) skips the
 * Trash — the second arg forces permanent deletion.
 *
 * Capability checked per-attachment (not a blanket current_user_can) to match
 * the pattern established in roci_ajax_bulk_move_attachments().
 *
 * No Undo — permanent delete has no inverse operation.
 *
 * @param int[]  $_POST['attachment_ids']  Array of attachment post IDs.
 */
function roci_ajax_bulk_delete_attachments() {

	check_ajax_referer( 'roci_move_attachment', 'nonce' );

	// ── Validate & sanitize attachment IDs ──────────────────────────────
	$raw_ids = isset( $_POST['attachment_ids'] ) ? (array) $_POST['attachment_ids'] : array();
	if ( empty( $raw_ids ) ) {
		wp_send_json_error( __( 'No attachments specified.', 'rocinante' ), 400 );
	}
	$attachment_ids = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );
	if ( empty( $attachment_ids ) ) {
		wp_send_json_error( __( 'Invalid attachment IDs.', 'rocinante' ), 400 );
	}

	// ── Delete each attachment ───────────────────────────────────────────
	$deleted = array();
	$failed  = array();

	foreach ( $attachment_ids as $id ) {
		if ( get_post_type( $id ) !== 'attachment' ) {
			$failed[] = $id;
			continue;
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			$failed[] = $id;
			continue;
		}

		// true = force delete (skip Trash).
		$result = wp_delete_attachment( $id, true );
		if ( false === $result || null === $result ) {
			$failed[] = $id;
			continue;
		}

		$deleted[] = $id;
	}

	wp_send_json_success( array(
		'deleted' => $deleted,
		'failed'  => $failed,
	) );
}
add_action( 'wp_ajax_roci_bulk_delete_attachments', 'roci_ajax_bulk_delete_attachments' );


// ============================================================
// MOVE ITEM — UNIFIED CPT AJAX HANDLER
// ============================================================

/**
 * Reassign a post to a different folder term (or unassigned).
 *
 * Handles any post type registered via roci_register_folder_type().
 * The taxonomy is derived server-side from the post's actual post_type
 * via the registry — the client never specifies a taxonomy directly.
 *
 * Security chain:
 *   1. Nonce verification (dies on failure, HTTP 403).
 *   2. post_id validated as a real post.
 *   3. Post type validated against the registry (only folder-enabled types).
 *   4. edit_post capability check for the specific post.
 *   5. target_term validated — either '__unassigned__' sentinel or a real
 *      term ID in the derived taxonomy via roci_validate_folder_term().
 *   6. No-op detection: returns early if already in the target folder.
 *   7. Previous terms captured before the move for the success response.
 *   8. wp_set_object_terms() with append=false replaces all folder assignments.
 */
function roci_ajax_move_item_to_folder() {

	check_ajax_referer( 'roci_move_item_to_folder', 'nonce' );

	// ── Validate post ─────────────────────────────────────────────────────
	$post_id = absint( isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0 );
	if ( ! $post_id ) {
		wp_send_json_error( __( 'Invalid post.', 'rocinante' ), 400 );
	}

	$post_type = get_post_type( $post_id );
	$taxonomy  = roci_get_folder_taxonomy_for_post_type( $post_type );

	if ( ! $taxonomy ) {
		wp_send_json_error( __( 'Post type not supported.', 'rocinante' ), 400 );
	}

	// ── Capability check ─────────────────────────────────────────────────
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( __( 'You do not have permission to move this item.', 'rocinante' ), 403 );
	}

	// ── Validate target term ─────────────────────────────────────────────
	$target_raw   = isset( $_POST['target_term'] ) ? sanitize_text_field( wp_unslash( $_POST['target_term'] ) ) : '';
	$terms_to_set = array();
	$term_id      = 0;

	if ( '__unassigned__' === $target_raw ) {
		$terms_to_set = array();
	} else {
		$term_id = absint( $target_raw );
		if ( ! $term_id || ! roci_validate_folder_term( $term_id, $taxonomy ) ) {
			wp_send_json_error( __( 'Invalid target fauxlder.', 'rocinante' ), 400 );
		}
		$terms_to_set = array( $term_id );
	}

	// ── Capture previous terms before the move ───────────────────────────
	$previous = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
	if ( is_wp_error( $previous ) ) {
		$previous = array();
	}

	// ── No-op detection ──────────────────────────────────────────────────
	if ( '__unassigned__' === $target_raw ) {
		if ( empty( $previous ) ) {
			wp_send_json_success( array( 'no_change' => true ) );
		}
	} else {
		if ( count( $previous ) === 1 && (int) $previous[0] === $term_id ) {
			wp_send_json_success( array( 'no_change' => true ) );
		}
	}

	// ── Perform the move ─────────────────────────────────────────────────
	$result = wp_set_object_terms( $post_id, $terms_to_set, $taxonomy, false );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message(), 500 );
	}

	// ── Resolve folder name for the toast message ────────────────────────
	$target_folder_name = '';
	if ( $term_id ) {
		$term = get_term( $term_id, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$target_folder_name = $term->name;
		}
	}

	wp_send_json_success( array(
		'no_change'          => false,
		'post_id'            => $post_id,
		'post_title'         => get_the_title( $post_id ),
		'previous_terms'     => array_map( 'intval', $previous ),
		'new_terms'          => array_map( 'intval', $terms_to_set ),
		'new_folder_term_id' => $term_id,
		'new_folder_name'    => $target_folder_name,
		'target_folder_name' => $target_folder_name,
	) );
}
add_action( 'wp_ajax_roci_move_item_to_folder', 'roci_ajax_move_item_to_folder' );


// ============================================================
// DRAG HANDLE COLUMN — GENERIC (CPT list tables)
// ============================================================

/**
 * Insert the drag-handle column immediately after the checkbox column.
 *
 * Hooked to manage_{post_type}_posts_columns for each post type registered
 * via roci_register_folder_type(). The hook binding happens in folders.php.
 *
 * @param  array $columns  Existing column slugs => header HTML.
 * @return array
 */
function roci_folder_drag_column_filter( $columns ) {
	$new = array();
	foreach ( $columns as $key => $value ) {
		$new[ $key ] = $value;
		if ( 'cb' === $key ) {
			$new['roci_drag_handle'] = '';
		}
	}
	return $new;
}

/**
 * Render the drag-handle cell for each post row.
 *
 * CSS class and data attribute are derived from the post's post_type so
 * folders-list-dragdrop.js can target the correct element via config:
 *   page  → .roci-page-drag-handle  data-page-id
 *   post  → .roci-post-drag-handle  data-post-id
 *   tour  → .roci-tour-drag-handle  data-tour-id
 *
 * @param string $column_name  Current column slug.
 * @param int    $post_id      Current post ID.
 */
function roci_folder_drag_column_render( $column_name, $post_id ) {
	if ( 'roci_drag_handle' !== $column_name ) {
		return;
	}
	$post_type    = get_post_type( $post_id );
	$handle_class = 'roci-' . $post_type . '-drag-handle';
	$data_attr    = 'data-' . $post_type . '-id';
	echo '<span class="' . esc_attr( $handle_class ) . '" draggable="true" '
		. esc_attr( $data_attr ) . '="' . esc_attr( $post_id ) . '" aria-hidden="true">'
		. '<span class="dashicons dashicons-move"></span>'
		. '</span>';
}


// ============================================================
// DRAG-DROP — ENQUEUE ASSETS (Media + CPT list tables)
// ============================================================

/**
 * Enqueue drag-drop JS on the Media Library screen and CPT list screens.
 *
 * upload.php  → folders-dragdrop.js (Media grid, Backbone, rociFoldersDragDrop).
 * edit.php    → folders-list-dragdrop.js (CPT list tables, config-driven, rociDragDrop).
 *               Enqueued once per registered post type under a unique handle;
 *               per-post-type config is passed via wp_localize_script.
 *
 * @param string $hook_suffix  Current admin page hook suffix.
 */
function roci_enqueue_dragdrop_assets( $hook_suffix ) {

	// ── Media grid (upload.php) ───────────────────────────────────────────
	if ( 'upload.php' === $hook_suffix ) {
		wp_enqueue_script(
			'roci-folders-dragdrop',
			get_template_directory_uri() . '/dist/js/folders/folders-dragdrop.js',
			array( 'media-views' ),
			roci_asset_version( 'dist/js/folders/folders-dragdrop.js' ),
			true
		);
		wp_localize_script( 'roci-folders-dragdrop', 'rociFoldersDragDrop', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'roci_move_attachment' ),
		) );
		return;
	}

	// ── CPT list tables (edit.php) ─────────────────────────────────────────
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$current_post_type = null;
	foreach ( roci_get_folder_registry() as $post_type => $taxonomy ) {
		if ( 'edit-' . $post_type === $screen->id ) {
			$current_post_type = $post_type;
			break;
		}
	}

	if ( ! $current_post_type ) {
		return;
	}

	$js_file = 'dist/js/folders/folders-list-dragdrop.js';

	if ( ! file_exists( get_template_directory() . '/' . $js_file ) ) {
		return;
	}

	// Derive per-post-type config from the slug.
	$slug_hyphen  = str_replace( '_', '-', $current_post_type );
	$parts        = explode( '-', $slug_hyphen );
	$dataset_attr = $parts[0] . implode( '', array_map( 'ucfirst', array_slice( $parts, 1 ) ) ) . 'Id';

	wp_enqueue_script(
		'roci-dragdrop-' . $current_post_type,
		get_template_directory_uri() . '/' . $js_file,
		array(),
		roci_asset_version( $js_file ),
		true
	);

	wp_localize_script( 'roci-dragdrop-' . $current_post_type, 'rociDragDrop', array(
		'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
		'nonce'         => wp_create_nonce( 'roci_move_item_to_folder' ),
		'postType'      => $current_post_type,
		'handleClass'   => 'roci-' . $slug_hyphen . '-drag-handle',
		'datasetAttr'   => $dataset_attr,
		'dragType'      => 'text/x-roci-' . $slug_hyphen,
		'filterKey'     => 'roci_' . $current_post_type . '_folder',
		'columnClass'   => 'column-taxonomy-roci_' . $current_post_type . '_folder',
		'bodyDragClass' => 'roci-dragging-' . $slug_hyphen,
		'i18n'          => array(
			'moved'           => __( 'Moved "%s" to %s',               'rocinante' ),
			'movedUnassigned' => __( 'Removed "%s" from folder',       'rocinante' ),
			'undo'            => __( 'Undo',                           'rocinante' ),
			'undone'          => __( 'Undone.',                        'rocinante' ),
			'error'           => __( 'Move failed. Please try again.', 'rocinante' ),
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_enqueue_dragdrop_assets' );
