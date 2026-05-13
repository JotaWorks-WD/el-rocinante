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
 * Version: 1.2.0
 * Updated: 2026-05-13
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
		'name'              => _x( 'Media Folders',          'taxonomy general name',  'rocinante' ),
		'singular_name'     => _x( 'Media Folder',           'taxonomy singular name', 'rocinante' ),
		'search_items'      => __( 'Search Media Folders',   'rocinante' ),
		'all_items'         => __( 'All Media Folders',      'rocinante' ),
		'parent_item'       => __( 'Parent Folder',          'rocinante' ),
		'parent_item_colon' => __( 'Parent Folder:',         'rocinante' ),
		'edit_item'         => __( 'Edit Media Folder',      'rocinante' ),
		'update_item'       => __( 'Update Media Folder',    'rocinante' ),
		'add_new_item'      => __( 'Add New Media Folder',   'rocinante' ),
		'new_item_name'     => __( 'New Media Folder Name',  'rocinante' ),
		'menu_name'         => __( 'Media Folders',          'rocinante' ),
	);

	register_taxonomy( 'roci_media_folder', 'attachment', array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => false,
	) );
}
add_action( 'init', 'roci_register_media_folder_taxonomy' );


/**
 * Register the roci_page_folder taxonomy on pages.
 */
function roci_register_page_folder_taxonomy() {

	$labels = array(
		'name'              => _x( 'Page Folders',          'taxonomy general name',  'rocinante' ),
		'singular_name'     => _x( 'Page Folder',           'taxonomy singular name', 'rocinante' ),
		'search_items'      => __( 'Search Page Folders',   'rocinante' ),
		'all_items'         => __( 'All Page Folders',      'rocinante' ),
		'parent_item'       => __( 'Parent Folder',         'rocinante' ),
		'parent_item_colon' => __( 'Parent Folder:',        'rocinante' ),
		'edit_item'         => __( 'Edit Page Folder',      'rocinante' ),
		'update_item'       => __( 'Update Page Folder',    'rocinante' ),
		'add_new_item'      => __( 'Add New Page Folder',   'rocinante' ),
		'new_item_name'     => __( 'New Page Folder Name',  'rocinante' ),
		'menu_name'         => __( 'Page Folders',          'rocinante' ),
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
