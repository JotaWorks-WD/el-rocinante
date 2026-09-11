<?php
/**
 * Folder Upload Handler
 *
 * Assigns attachments to a roci_media_folder term when a target folder
 * is provided via the upload POST payload (wired via Plupload multipart_params).
 *
 * Also owns roci_get_upload_picker_folders(), the single source for the picker's
 * folder list — read here at page load and again by roci_ajax_create_folder()
 * (inc/folders/create.php) to refresh the rendered <select> after a create.
 *
 * File:    inc/folders/upload.php
 * Version: 2.10.0
 * Updated: 2026-09-11
 *
 * @package ElRocinante
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validate that a term ID exists in a given taxonomy.
 *
 * @param int    $term_id  Term ID to validate.
 * @param string $taxonomy Taxonomy slug.
 * @return bool
 */
function roci_validate_folder_term( $term_id, $taxonomy ) {
	$term_id = absint( $term_id );
	if ( ! $term_id ) {
		return false;
	}
	$term = get_term( $term_id, $taxonomy );
	return ( $term && ! is_wp_error( $term ) );
}

/**
 * Assign newly uploaded attachment to a folder if specified.
 *
 * @param int $attachment_id The new attachment post ID.
 */
function roci_assign_upload_folder( $attachment_id ) {
	if ( empty( $_POST['roci_target_folder'] ) ) {
		return;
	}
	$term_id = absint( $_POST['roci_target_folder'] );
	if ( ! $term_id ) {
		return;
	}
	if ( ! roci_validate_folder_term( $term_id, 'roci_media_folder' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
		return;
	}
	wp_set_object_terms( $attachment_id, $term_id, 'roci_media_folder', false );
}
add_action( 'add_attachment', 'roci_assign_upload_folder' );

/**
 * Build the nested folder list the upload picker renders.
 *
 * THE ONE SOURCE for that list. Called at page load by
 * roci_upload_picker_enqueue() below, and again by roci_ajax_create_folder()
 * (inc/folders/create.php) so a newly created folder can be pushed into the
 * already-rendered <select> without a page reload. Both callers must get the
 * identical shape or the dropdown would visibly change form after a create.
 *
 * ⚠ NESTED AS OF v2.10.0 — THIS IS A DELIBERATE REVERSAL, NOT A DRIFT.
 * This function was flat by design until now, and its previous docblock said
 * so. Hierarchy is reintroduced per owner decision (bug #16): parent folders
 * ARE valid assignment targets, so the picker has to show which folder sits
 * under which. The A-Z flattening in archive #R4/#5 is what is being reversed;
 * do not "restore" the flat form as a consistency fix.
 *
 * SIBLINGS STILL SORT A-Z. That half of #R4/#5 is retained and is the reason
 * this still does NOT adopt roci_get_folder_terms_with_depth()
 * (filters.php:517): that helper carries roci_get_folder_order_query_args(),
 * so it orders by the sidebar's hand-sorted drag order, and switching to it
 * would silently undo v5.8.0. It also keys on term_id where this array's
 * consumer reads f.id. Consolidating the two is a separate decision.
 *
 * The recipe is the one already proven twice on this site — the list-view
 * filter (roci_render_folder_select_dropdown, filters.php:56) and the grid
 * filter (roci_get_folder_terms_for_js, filters.php:392): bucket by parent,
 * sort each sibling bucket A-Z on the DECODED name, then walk depth-first
 * prefixing one em-dash per level. The one departure is the label: those two
 * call roci_format_folder_option_label() and append a " (N)" count, which
 * this picker has never shown and does not gain here.
 *
 * ⚠ THE PREFIX IS A LITERAL U+2014, NEVER "&mdash;". The chain is
 * roci_folder_display_name() decode here → escapeHtml() in upload-picker.js →
 * innerHTML. An HTML entity would survive PHP untouched and then be escaped
 * into a visible literal "&mdash;", and it would re-break the &-decode that
 * archive #R1 fixed. str_repeat() is applied AFTER the decode for the same
 * reason — prefixing first would feed the em-dash through html_entity_decode().
 *
 * str_repeat( …, 0 ) returns '', so depth-0 parents get no prefix and need no
 * special-casing.
 *
 * Sorted in PHP on the DECODED name, not by get_terms( orderby => 'name' ).
 * WordPress stores term names HTML-encoded, so the SQL sort ordered
 * "Logo &amp; Branding" by the literal "&amp;" — filed under "a", nowhere
 * near the "&" on screen. No sort args on get_terms() at all, which also
 * keeps roci_get_folder_order_query_args()'s meta_key INNER JOIN out of the
 * query — that join had hidden any folder missing roci_folder_order.
 *
 * Names are returned DECODED. upload-picker.js runs each one through its own
 * escapeHtml() before injecting it as innerHTML, so it must receive a decoded
 * value or the escape lands on top of the DB's stored encoding and renders
 * "&amp;amp;". wp_localize_script() won't decode it for us — it only touches
 * top-level scalars, and this array is nested.
 *
 * ⚠ A TERM WHOSE PARENT ID NAMES A MISSING TERM IS UNREACHABLE by the walk and
 * will not render. Both reference dropdowns above have the identical exposure,
 * so this matches them rather than diverging; WordPress reparents children on
 * term delete, so it should not arise. Worth knowing if a folder ever vanishes
 * from every chooser at once.
 *
 * @return array  [ [ 'id' => int, 'name' => string ], … ]
 *                Depth-first, siblings A-Z, names decoded and em-dash indented.
 */
function roci_get_upload_picker_folders() {

	$terms = get_terms( array(
		'taxonomy'   => 'roci_media_folder',
		'hide_empty' => false,
	) );

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	// Index by parent for the depth-first walk, then sort each sibling bucket
	// A-Z. Sorting the buckets rather than the flat list is what keeps the
	// alphabetical order from scattering children away from their parents.
	$children = array();
	foreach ( $terms as $term ) {
		$children[ $term->parent ][] = $term;
	}

	roci_sort_folder_children_alphabetically( $children );

	$folders = array();

	$walk = function ( $parent_id, $depth ) use ( &$walk, &$children, &$folders ) {
		if ( empty( $children[ $parent_id ] ) ) {
			return;
		}
		foreach ( $children[ $parent_id ] as $term ) {
			$folders[] = array(
				'id'   => (int) $term->term_id,
				'name' => str_repeat( "\u{2014} ", $depth ) . roci_folder_display_name( $term ),
			);
			$walk( $term->term_id, $depth + 1 );
		}
	};

	$walk( 0, 0 );

	return $folders;
}

/**
 * Enqueue picker assets and localize folder data on relevant admin screens.
 *
 * @param string $hook Current admin page hook.
 */
function roci_upload_picker_enqueue( $hook ) {
	$is_media_screen = in_array( $hook, array( 'upload.php', 'media-new.php' ), true );
	$is_post_edit    = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
	if ( ! $is_media_screen && ! $is_post_edit ) {
		return;
	}

	wp_enqueue_script(
		'roci-upload-picker',
		get_template_directory_uri() . '/dist/js/folders/upload-picker.js',
		array(),
		roci_asset_version( '/dist/js/folders/upload-picker.js' ),
		true
	);

	wp_enqueue_script(
		'roci-wp-media-refresh-shim',
		get_template_directory_uri() . '/dist/js/folders/wp-media-refresh-shim.js',
		array( 'media-views' ),
		roci_asset_version( 'dist/js/folders/wp-media-refresh-shim.js' ),
		true
	);

	wp_localize_script( 'roci-upload-picker', 'rociUploadPicker', array(
		'folders'    => roci_get_upload_picker_folders(),
		'label'      => __( 'Upload to fauxlder', 'rocinante' ),
		'helperText' => __( 'Choose a fauxlder before uploading. Leave blank for unassigned.', 'rocinante' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_upload_picker_enqueue' );
