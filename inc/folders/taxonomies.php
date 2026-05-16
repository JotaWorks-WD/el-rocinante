<?php
/**
 * Folder System — Taxonomy Registration
 *
 * Registers two hierarchical taxonomies used throughout the folder system:
 *
 *   roci_media_folder — on 'attachment'; appears in Media Library
 *   roci_page_folder  — on 'page'; appears in the Pages list
 *
 * Both are hierarchical so parent/child nesting works natively via
 * WordPress's built-in term management UI — no custom tree needed.
 *
 * File:    inc/folders/taxonomies.php
 * Version: 1.5.0
 * Updated: 2026-05-16
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// TAXONOMY REGISTRATION
// ============================================================

/**
 * Register the roci_media_folder taxonomy on attachments.
 */
function roci_register_media_folder_taxonomy() {

	$labels = array(
		'name'              => _x( 'Media Fauxlders',          'taxonomy general name',  'rocinante' ),
		'singular_name'     => _x( 'Media Fauxlder',           'taxonomy singular name', 'rocinante' ),
		'search_items'      => __( 'Search Media Fauxlders',   'rocinante' ),
		'all_items'         => __( 'All Media Fauxlders',      'rocinante' ),
		'parent_item'       => __( 'Parent Fauxlder',          'rocinante' ),
		'parent_item_colon' => __( 'Parent Fauxlder:',         'rocinante' ),
		'edit_item'         => __( 'Edit Media Fauxlder',      'rocinante' ),
		'update_item'       => __( 'Update Media Fauxlder',    'rocinante' ),
		'add_new_item'      => __( 'Add New Media Fauxlder',   'rocinante' ),
		'new_item_name'     => __( 'New Media Fauxlder Name',  'rocinante' ),
		'menu_name'         => __( 'Media Fauxlders',          'rocinante' ),
	);

	register_taxonomy( 'roci_media_folder', 'attachment', array(
		'labels'                => $labels,
		'hierarchical'          => true,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'show_in_rest'          => true,
		'query_var'             => true,
		'rewrite'               => false,
		// _update_post_term_count only counts post_status='inherit' attachments,
		// which can leave counts stale when attachments are unattached (post_parent=0).
		// _update_generic_term_count counts all objects in term_relationships regardless
		// of status, keeping counts accurate for the Media Library use case.
		'update_count_callback' => '_update_generic_term_count',
	) );
}
add_action( 'init', 'roci_register_media_folder_taxonomy' );


/**
 * Register the roci_page_folder taxonomy on pages.
 */
function roci_register_page_folder_taxonomy() {

	$labels = array(
		'name'              => _x( 'Page Fauxlders',          'taxonomy general name',  'rocinante' ),
		'singular_name'     => _x( 'Page Fauxlder',           'taxonomy singular name', 'rocinante' ),
		'search_items'      => __( 'Search Page Fauxlders',   'rocinante' ),
		'all_items'         => __( 'All Page Fauxlders',      'rocinante' ),
		'parent_item'       => __( 'Parent Fauxlder',         'rocinante' ),
		'parent_item_colon' => __( 'Parent Fauxlder:',        'rocinante' ),
		'edit_item'         => __( 'Edit Page Fauxlder',      'rocinante' ),
		'update_item'       => __( 'Update Page Fauxlder',    'rocinante' ),
		'add_new_item'      => __( 'Add New Page Fauxlder',   'rocinante' ),
		'new_item_name'     => __( 'New Page Fauxlder Name',  'rocinante' ),
		'menu_name'         => __( 'Page Fauxlders',          'rocinante' ),
	);

	register_taxonomy( 'roci_page_folder', 'page', array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => false,
	) );
}
add_action( 'init', 'roci_register_page_folder_taxonomy' );


// ============================================================
// ATTACHMENT JS DATA
// ============================================================

/**
 * Expose roci_media_folder term IDs on attachment JS data so client-side
 * code can identify a deleted attachment's folder for accurate sidebar
 * count decrement.
 */
add_filter( 'wp_prepare_attachment_for_js', 'roci_expose_attachment_folder', 10, 2 );
function roci_expose_attachment_folder( $response, $attachment ) {
	$terms = wp_get_object_terms( $attachment->ID, 'roci_media_folder', array( 'fields' => 'ids' ) );
	$response['roci_media_folder'] = is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
	return $response;
}
