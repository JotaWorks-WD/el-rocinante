<?php
/**
 * Folder System — Move Attachment
 *
 * AJAX endpoint for reassigning a single attachment to a different folder
 * (or to unassigned). Mirrors the structure of create.php.
 *
 *   roci_ajax_move_attachment()   — wp_ajax_roci_move_attachment
 *   roci_enqueue_dragdrop_assets() — enqueues dist/js/folders/folders-dragdrop.js
 *
 * File:    inc/folders/move.php
 * Version: 1.0.0
 * Updated: 2026-05-16
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
			wp_send_json_error( __( 'Invalid target folder.', 'rocinante' ), 400 );
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
