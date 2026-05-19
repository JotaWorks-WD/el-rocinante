<?php
/**
 * Folder System — Move Page to Folder
 *
 * AJAX endpoint for reassigning a single page to a different roci_page_folder
 * term (or to unassigned). Adds a drag-handle column to the pages list table
 * and enqueues the client-side drag/drop script.
 *
 *   roci_ajax_move_page_to_folder()       — wp_ajax_roci_move_page_to_folder
 *   roci_add_page_drag_column()           — manage_page_posts_columns filter
 *   roci_render_page_drag_column()        — manage_page_posts_custom_column action
 *   roci_enqueue_page_dragdrop_assets()   — enqueues dist/js/folders/folders-page-dragdrop.js
 *
 * File:    inc/folders/page-move.php
 * Version: 1.0.0
 * Updated: 2026-05-19
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// MOVE PAGE — AJAX HANDLER
// ============================================================

/**
 * Reassign a page to a different roci_page_folder term (or unassigned).
 *
 * Security chain:
 *   1. Nonce verification (dies on failure, HTTP 403).
 *   2. page_id validated as a real page post.
 *   3. edit_post capability check for the specific page.
 *   4. target_term validated — either '__unassigned__' sentinel or a real term ID
 *      in roci_page_folder via roci_validate_folder_term() from upload.php.
 *   5. No-op detection: if page is already in the target folder, returns early.
 *   6. Previous terms captured before the move for the success response (Undo support).
 *   7. wp_set_object_terms() with append=false replaces all folder assignments.
 *
 * Success response payload includes previous and new term arrays so the
 * client can compute count deltas and support Undo without a separate lookup.
 * target_folder_name is resolved server-side so toast messages are authoritative.
 */
function roci_ajax_move_page_to_folder() {

	check_ajax_referer( 'roci_move_page', 'nonce' );

	// ── Validate page ────────────────────────────────────────────────────
	$page_id = absint( isset( $_POST['page_id'] ) ? $_POST['page_id'] : 0 );
	if ( ! $page_id || get_post_type( $page_id ) !== 'page' ) {
		wp_send_json_error( __( 'Invalid page.', 'rocinante' ), 400 );
	}

	// ── Capability check ─────────────────────────────────────────────────
	if ( ! current_user_can( 'edit_post', $page_id ) ) {
		wp_send_json_error( __( 'You do not have permission to move this page.', 'rocinante' ), 403 );
	}

	// ── Validate target term ─────────────────────────────────────────────
	$target_raw   = isset( $_POST['target_term'] ) ? sanitize_text_field( wp_unslash( $_POST['target_term'] ) ) : '';
	$terms_to_set = array();
	$term_id      = 0;

	if ( '__unassigned__' === $target_raw ) {
		$terms_to_set = array();
	} else {
		$term_id = absint( $target_raw );
		if ( ! $term_id || ! roci_validate_folder_term( $term_id, 'roci_page_folder' ) ) {
			wp_send_json_error( __( 'Invalid target fauxlder.', 'rocinante' ), 400 );
		}
		$terms_to_set = array( $term_id );
	}

	// ── Capture previous terms before the move ───────────────────────────
	$previous = wp_get_object_terms( $page_id, 'roci_page_folder', array( 'fields' => 'ids' ) );
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
	$result = wp_set_object_terms( $page_id, $terms_to_set, 'roci_page_folder', false );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message(), 500 );
	}

	// ── Resolve folder name for the toast message ────────────────────────
	$target_folder_name = '';
	if ( $term_id ) {
		$term = get_term( $term_id, 'roci_page_folder' );
		if ( $term && ! is_wp_error( $term ) ) {
			$target_folder_name = $term->name;
		}
	}

	wp_send_json_success( array(
		'no_change'          => false,
		'page_id'            => $page_id,
		'page_title'         => get_the_title( $page_id ),
		'previous_terms'     => array_map( 'intval', $previous ),
		'new_terms'          => array_map( 'intval', $terms_to_set ),
		'target_folder_name' => $target_folder_name,
	) );
}
add_action( 'wp_ajax_roci_move_page_to_folder', 'roci_ajax_move_page_to_folder' );


// ============================================================
// DRAG HANDLE COLUMN — PAGES LIST TABLE
// ============================================================

/**
 * Insert the drag-handle column immediately after the checkbox column.
 *
 * @param  array $columns  Existing column slugs => header HTML.
 * @return array
 */
function roci_add_page_drag_column( $columns ) {
	$new = array();
	foreach ( $columns as $key => $value ) {
		$new[ $key ] = $value;
		if ( 'cb' === $key ) {
			$new['roci_drag_handle'] = '';
		}
	}
	return $new;
}
add_filter( 'manage_page_posts_columns', 'roci_add_page_drag_column' );


/**
 * Render the drag-handle cell for each page row.
 *
 * The handle is a span with draggable="true" so the HTML5 drag API fires
 * from this element. data-page-id is picked up by folders-page-dragdrop.js
 * on dragstart. aria-hidden keeps screen readers from announcing a
 * decorative icon that has no navigation purpose.
 *
 * @param string $column_name  Current column slug.
 * @param int    $post_id      Current page ID.
 */
function roci_render_page_drag_column( $column_name, $post_id ) {
	if ( 'roci_drag_handle' !== $column_name ) {
		return;
	}
	echo '<span class="roci-page-drag-handle" draggable="true" data-page-id="' . esc_attr( $post_id ) . '" aria-hidden="true">'
		. '<span class="dashicons dashicons-move"></span>'
		. '</span>';
}
add_action( 'manage_page_posts_custom_column', 'roci_render_page_drag_column', 10, 2 );


// ============================================================
// DRAG-DROP — ENQUEUE ASSETS
// ============================================================

/**
 * Enqueue folders-page-dragdrop.js on the Pages list screen.
 *
 * No dependency on 'media-views' — the pages list table is plain PHP output
 * and the drag source is a native HTML element, not a Backbone view.
 *
 * @param string $hook_suffix  Current admin page hook suffix (unused).
 */
function roci_enqueue_page_dragdrop_assets( $hook_suffix ) {

	$screen = get_current_screen();
	if ( ! $screen || 'edit-page' !== $screen->id ) {
		return;
	}

	wp_enqueue_script(
		'roci-page-dragdrop',
		get_template_directory_uri() . '/dist/js/folders/folders-page-dragdrop.js',
		array(),
		roci_asset_version( 'dist/js/folders/folders-page-dragdrop.js' ),
		true
	);

	wp_localize_script( 'roci-page-dragdrop', 'rociPageDragDrop', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'roci_move_page' ),
		'i18n'    => array(
			'moved'           => __( 'Moved "%s" to %s', 'rocinante' ),
			'movedUnassigned' => __( 'Removed "%s" from folder', 'rocinante' ),
			'undo'            => __( 'Undo', 'rocinante' ),
			'undone'          => __( 'Undone.', 'rocinante' ),
			'error'           => __( 'Move failed. Please try again.', 'rocinante' ),
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_enqueue_page_dragdrop_assets' );
