<?php
/**
 * Folder Upload Handler
 *
 * Assigns attachments to a roci_media_folder term when a target folder
 * is provided via the upload POST payload (wired via Plupload multipart_params).
 *
 * File:    inc/folders/upload.php
 * Version: 2.8.17
 * Updated: 2026-07-28
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

	$terms = get_terms( array(
		'taxonomy'   => 'roci_media_folder',
		'hide_empty' => false,
	) );

	$folders = array();
	if ( ! is_wp_error( $terms ) ) {

		// Sorted in PHP on the DECODED name, not by get_terms( orderby => 'name' ).
		// WordPress stores term names HTML-encoded, so the SQL sort ordered
		// "Logo &amp; Branding" by the literal "&amp;" — filed under "a", nowhere
		// near the "&" on screen. Same strnatcasecmp + roci_folder_display_name()
		// pairing as roci_sort_folder_children_alphabetically(); this picker is
		// flat, so it sorts the term list directly rather than a children map.
		usort( $terms, function ( $a, $b ) {
			return strnatcasecmp(
				roci_folder_display_name( $a ),
				roci_folder_display_name( $b )
			);
		} );

		foreach ( $terms as $term ) {
			// Decoded here, not in JS: upload-picker.js runs the name through
			// its own escapeHtml() before injecting it as innerHTML, so it must
			// receive a decoded value or the escape lands on top of the DB's
			// stored encoding. wp_localize_script() won't decode it for us —
			// it only touches top-level scalars, and $folders is nested.
			$folders[] = array(
				'id'   => (int) $term->term_id,
				'name' => roci_folder_display_name( $term ),
			);
		}
	}

	wp_localize_script( 'roci-upload-picker', 'rociUploadPicker', array(
		'folders'    => $folders,
		'label'      => __( 'Upload to fauxlder', 'rocinante' ),
		'helperText' => __( 'Choose a fauxlder before uploading. Leave blank for unassigned.', 'rocinante' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_upload_picker_enqueue' );
