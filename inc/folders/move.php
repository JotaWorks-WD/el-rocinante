<?php
/**
 * Folder System — Move Attachment (single + bulk)
 *
 * AJAX endpoints for reassigning one or many attachments to a folder or to
 * unassigned. Mirrors the structure of create.php.
 *
 *   roci_ajax_move_attachment()          — wp_ajax_roci_move_attachment (single)
 *   roci_ajax_bulk_move_attachments()    — wp_ajax_roci_bulk_move_attachments
 *   roci_ajax_bulk_undo_move_attachments() — wp_ajax_roci_bulk_undo_move_attachments
 *   roci_enqueue_dragdrop_assets()       — enqueues dist/js/folders/folders-dragdrop.js
 *
 * File:    inc/folders/move.php
 * Version: 1.3.0
 * Updated: 2026-05-20
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
// DRAG-DROP — ENQUEUE ASSETS
// ============================================================

/**
 * Enqueue folders-dragdrop.js on the Media Library screen.
 *
 * Only loads on upload.php — drag-drop in the modal media picker and
 * list-view drag-drop are out of scope for Phase 6.
 *
 * Depends on 'media-views' so wp.media.view.Attachment.Library is
 * available for the drag-source extension when the script runs.
 *
 * @param string $hook_suffix  Current admin page hook suffix.
 */
function roci_enqueue_dragdrop_assets( $hook_suffix ) {

	if ( 'upload.php' !== $hook_suffix ) {
		return;
	}

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
}
add_action( 'admin_enqueue_scripts', 'roci_enqueue_dragdrop_assets' );
