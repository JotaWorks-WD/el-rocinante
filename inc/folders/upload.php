<?php
/**
 * Folder Upload Handler
 *
 * Assigns attachments to a roci_media_folder term when a target folder
 * is provided via the upload POST payload (wired via Plupload multipart_params).
 *
 * Also owns roci_get_upload_picker_folders(), the single source for the picker's
 * flat folder list — read here at page load and again by roci_ajax_create_folder()
 * (inc/folders/create.php) to refresh the rendered <select> after a create.
 *
 * File:    inc/folders/upload.php
 * Version: 2.9.0
 * Updated: 2026-07-30
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
 * Build the flat folder list the upload picker renders.
 *
 * THE ONE SOURCE for that list. Called at page load by
 * roci_upload_picker_enqueue() below, and again by roci_ajax_create_folder()
 * (inc/folders/create.php) so a newly created folder can be pushed into the
 * already-rendered <select> without a page reload. Both callers must get the
 * identical shape or the dropdown would visibly change form after a create.
 *
 * FLAT, not a tree. dist/js/folders/upload-picker.js emits one <option> per
 * entry with no depth, no em-dash indent and no hierarchy signal, so this
 * deliberately does NOT reuse roci_build_folder_options_for_select()
 * (create.php:56) — that builder walks the tree depth-first and bakes counts
 * and indentation into its labels, which is the wrong shape here.
 *
 * Sorted in PHP on the DECODED name, not by get_terms( orderby => 'name' ).
 * WordPress stores term names HTML-encoded, so the SQL sort ordered
 * "Logo &amp; Branding" by the literal "&amp;" — filed under "a", nowhere
 * near the "&" on screen. Same strnatcasecmp + roci_folder_display_name()
 * pairing as roci_sort_folder_children_alphabetically(); this picker is flat,
 * so it sorts the term list directly rather than a children map.
 *
 * Names are returned DECODED. upload-picker.js runs each one through its own
 * escapeHtml() before injecting it as innerHTML, so it must receive a decoded
 * value or the escape lands on top of the DB's stored encoding and renders
 * "&amp;amp;". wp_localize_script() won't decode it for us — it only touches
 * top-level scalars, and this array is nested.
 *
 * @return array  [ [ 'id' => int, 'name' => string ], … ] — flat, A-Z, decoded.
 */
function roci_get_upload_picker_folders() {

	$terms = get_terms( array(
		'taxonomy'   => 'roci_media_folder',
		'hide_empty' => false,
	) );

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	usort( $terms, function ( $a, $b ) {
		return strnatcasecmp(
			roci_folder_display_name( $a ),
			roci_folder_display_name( $b )
		);
	} );

	$folders = array();
	foreach ( $terms as $term ) {
		$folders[] = array(
			'id'   => (int) $term->term_id,
			'name' => roci_folder_display_name( $term ),
		);
	}

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
