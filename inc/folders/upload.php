<?php
/**
 * Folder upload handler.
 * Assigns attachments to a roci_media_folder term when a target folder
 * is provided via the upload POST payload (Plupload multipart_params,
 * wired in commit 2).
 *
 * @package El_Rocinante
 * @version 2.8.8
 * Updated: 2026-05-15
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

	$terms = get_terms( array(
		'taxonomy'   => 'roci_media_folder',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	$folders = array();
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$folders[] = array(
				'id'   => (int) $term->term_id,
				'name' => $term->name,
			);
		}
	}

	wp_localize_script( 'roci-upload-picker', 'rociUploadPicker', array(
		'folders'    => $folders,
		'label'      => __( 'Upload to folder', 'rocinante' ),
		'helperText' => __( 'Choose a folder before uploading. Leave blank for unassigned.', 'rocinante' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_upload_picker_enqueue' );
