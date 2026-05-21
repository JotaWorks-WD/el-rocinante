<?php
/**
 * Folder System — Move Post to Folder (generic, registry-driven)
 *
 * Unified AJAX endpoint for reassigning any registered folder-enabled post
 * to a different taxonomy term (or to unassigned). Provides the generic
 * drag-handle column filter and render callbacks that roci_register_folder_type()
 * hooks onto each registered post type. Enqueues the shared list-table drag JS.
 *
 *   roci_ajax_move_item_to_folder()   — wp_ajax_roci_move_item_to_folder
 *   roci_folder_drag_column_filter()  — manage_{post_type}_posts_columns
 *   roci_folder_drag_column_render()  — manage_{post_type}_posts_custom_column
 *   roci_enqueue_dragdrop_assets()    — enqueues dist/js/folders/folders-list-dragdrop.js
 *
 * Per-post-type hooks (column filter/action) are NOT registered here;
 * they are wired by roci_register_folder_type() in folders.php so that
 * any post type added to the registry automatically receives them.
 *
 * File:    inc/folders/page-move.php
 * Version: 2.1.0
 * Updated: 2026-05-21
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// MOVE ITEM — UNIFIED AJAX HANDLER
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
 *
 * Success response includes previous and new term arrays so the client
 * can compute count deltas and support Undo without a separate lookup.
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
// DRAG HANDLE COLUMN — GENERIC (shared across all registered post types)
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
 * the per-post-type JS file can target the correct element:
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
// DRAG-DROP — ENQUEUE ASSETS (registry-driven)
// ============================================================

/**
 * Enqueue the unified list-table drag JS on the matching screen.
 *
 * Enqueues dist/js/folders/folders-list-dragdrop.js once per registered
 * post type under a unique handle (roci-dragdrop-{post_type}), passing
 * per-post-type config via the rociDragDrop global. WordPress loads the
 * same file URL once from cache; each handle carries its own inline config.
 *
 * Config values derived from the post_type slug:
 *   handleClass   — 'roci-{slug_hyphen}-drag-handle'
 *   datasetAttr   — camelCase slug + 'Id' (e.g. 'pageId', 'postId', 'tourId')
 *   dragType      — 'text/x-roci-{slug_hyphen}'
 *   filterKey     — 'roci_{slug_underscore}_folder'
 *   columnClass   — 'column-taxonomy-roci_{slug_underscore}_folder'
 *   bodyDragClass — 'roci-dragging-{slug_hyphen}'
 *
 * Note: dist/js/folders/folders-dragdrop.js handles the Media grid view
 * (upload.php, Backbone). This function is scoped to edit.php list tables.
 *
 * @param string $hook_suffix  Current admin page hook suffix (unused).
 */
function roci_enqueue_dragdrop_assets( $hook_suffix ) {

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
