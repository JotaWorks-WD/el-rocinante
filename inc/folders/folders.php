<?php
/**
 * Folder System — Entry Point + Public Registration API
 *
 * Public API:
 *   roci_register_folder_type( $post_type, $taxonomy_slug, $taxonomy_args )
 *
 * Registry helpers:
 *   roci_get_folder_registry()
 *   roci_get_folder_taxonomy_for_post_type( $post_type )
 *   roci_get_post_type_for_folder_taxonomy( $taxonomy_slug )
 *
 * Loads all folder-system sub-files in dependency order.
 * This file is the single require_once target in functions.php;
 * adding a new phase means adding one more require_once here
 * rather than touching functions.php.
 *
 *   taxonomies.php — register roci_media_folder, roci_page_folder, roci_post_folder
 *   counts.php     — roci_get_folder_count, roci_get_unassigned_count, roci_get_all_count
 *   filters.php    — list-view dropdowns, pre_get_posts, media modal filter, JS
 *   create.php     — "+ New Folder" modal, AJAX endpoint, JS
 *   sidebar.php    — folder-tree sidebar, unassigned filter, JS enqueue
 *
 * File:    inc/folders/folders.php
 * Version: 2.11.0
 * Updated: 2026-05-21
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// UTILITY HELPERS
// ============================================================

/**
 * Return a cache-busting version string for a theme asset.
 *
 * Uses the file's modification time so the ?ver= query string changes
 * automatically whenever the file changes — no manual version tracking needed.
 * Falls back to '1.0.0' if the file cannot be found.
 *
 * @param string $relative_path  Path relative to the theme root (e.g. 'dist/js/folders/folders-sidebar.js').
 * @return string|int  filemtime integer on success, '1.0.0' on failure.
 */
function roci_asset_version( $relative_path ) {
	$abs = get_template_directory() . '/' . ltrim( $relative_path, '/' );
	return file_exists( $abs ) ? filemtime( $abs ) : '1.0.0';
}

/**
 * Format a folder term name with its item count for display in <select> options.
 *
 * Single source of truth used by the list-view filter dropdowns, the grid-view
 * JS localisation, and the AJAX-refreshed option list after folder creation.
 * All three render paths call this so counts can never drift out of sync.
 *
 * @param  WP_Term $term      The folder term.
 * @param  string  $taxonomy  Folder taxonomy slug.
 * @return string             e.g. "My Folder (5)"
 */
function roci_format_folder_option_label( $term, $taxonomy ) {
	return sprintf( '%s (%d)', $term->name, (int) roci_get_folder_count( $term, $taxonomy ) );
}


// ============================================================
// FOLDER TYPE REGISTRY
// ============================================================

/** Internal registry: post_type => taxonomy_slug for all registered CPT folder types. */
global $_roci_folder_registry;
$_roci_folder_registry = array();

/**
 * Return the full CPT folder registry (post_type => taxonomy_slug).
 *
 * @return array
 */
function roci_get_folder_registry() {
	global $_roci_folder_registry;
	return is_array( $_roci_folder_registry ) ? $_roci_folder_registry : array();
}

/**
 * Return the folder taxonomy slug for a given post type, or null if not registered.
 *
 * @param  string      $post_type  Post type slug.
 * @return string|null
 */
function roci_get_folder_taxonomy_for_post_type( $post_type ) {
	$registry = roci_get_folder_registry();
	return isset( $registry[ $post_type ] ) ? $registry[ $post_type ] : null;
}

/**
 * Return the post type that owns a given folder taxonomy, or null if not found.
 *
 * @param  string      $taxonomy_slug  Folder taxonomy slug.
 * @return string|null
 */
function roci_get_post_type_for_folder_taxonomy( $taxonomy_slug ) {
	foreach ( roci_get_folder_registry() as $post_type => $tax ) {
		if ( $tax === $taxonomy_slug ) {
			return $post_type;
		}
	}
	return null;
}

/**
 * Register a post type for Fauxlder support.
 *
 * Adds the post type to the folder infrastructure registry so the sidebar,
 * drag-handle column, AJAX move endpoint, filter dropdown, organize toggle,
 * and count helpers all activate automatically.
 *
 * Also hooks the per-taxonomy term-creation order callback and, when the
 * taxonomy does not already exist, schedules a generic registration at
 * init priority 20 (after CPTs typically registered at priority 10 are ready).
 *
 * Called by the parent theme for 'page' and 'post'. Child themes can opt
 * additional CPTs in with a single line — no parent-theme edits needed:
 *
 *   roci_register_folder_type( 'tour', 'roci_tour_folder' );
 *
 * Pass $taxonomy_args to override any register_taxonomy() label or argument.
 * Explicitly pre-registering the taxonomy in the child theme before calling
 * this function is also supported; the auto-registration guard will skip it.
 *
 * @param string $post_type      Post type slug (e.g. 'post', 'tour').
 * @param string $taxonomy_slug  Folder taxonomy slug (e.g. 'roci_post_folder').
 * @param array  $taxonomy_args  Optional overrides merged into register_taxonomy() args.
 */
function roci_register_folder_type( $post_type, $taxonomy_slug, $taxonomy_args = array() ) {

	global $_roci_folder_registry;
	if ( ! is_array( $_roci_folder_registry ) ) {
		$_roci_folder_registry = array();
	}

	$_roci_folder_registry[ $post_type ] = $taxonomy_slug;

	// Per-post-type drag-handle column hooks (callbacks defined in move.php,
	// which is require_once'd before this function is first called).
	add_filter( 'manage_' . $post_type . '_posts_columns',       'roci_folder_drag_column_filter' );
	add_action( 'manage_' . $post_type . '_posts_custom_column', 'roci_folder_drag_column_render', 10, 2 );

	// Term-creation order assignment (callback defined in order.php).
	add_action( 'created_' . $taxonomy_slug, 'roci_assign_default_folder_order' );

	// Auto-register the taxonomy when it has not been explicitly declared
	// (the typical child-theme CPT use case). Uses priority 20 so CPTs
	// registered at the default priority 10 are already available for
	// get_post_type_object() label resolution.
	add_action( 'init', function () use ( $post_type, $taxonomy_slug, $taxonomy_args ) {

		if ( taxonomy_exists( $taxonomy_slug ) ) {
			return; // Explicit parent-theme registration takes precedence.
		}

		$pt_label = ucwords( str_replace( array( '-', '_' ), ' ', $post_type ) );
		$defaults  = array(
			'labels'            => array(
				'name'              => sprintf( _x( '%s Fauxlders',        'taxonomy general name',  'rocinante' ), $pt_label ),
				'singular_name'     => sprintf( _x( '%s Fauxlder',         'taxonomy singular name', 'rocinante' ), $pt_label ),
				'search_items'      => sprintf( __( 'Search %s Fauxlders', 'rocinante' ),              $pt_label ),
				'all_items'         => sprintf( __( 'All %s Fauxlders',    'rocinante' ),              $pt_label ),
				'parent_item'       => __( 'Parent Fauxlder',              'rocinante' ),
				'parent_item_colon' => __( 'Parent Fauxlder:',             'rocinante' ),
				'edit_item'         => sprintf( __( 'Edit %s Fauxlder',    'rocinante' ),              $pt_label ),
				'update_item'       => sprintf( __( 'Update %s Fauxlder',  'rocinante' ),              $pt_label ),
				'add_new_item'      => sprintf( __( 'Add New %s Fauxlder', 'rocinante' ),              $pt_label ),
				'new_item_name'     => sprintf( __( 'New %s Fauxlder Name','rocinante' ),              $pt_label ),
				'menu_name'         => sprintf( __( '%s Fauxlders',        'rocinante' ),              $pt_label ),
			),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => false,
		);

		register_taxonomy( $taxonomy_slug, $post_type, array_merge( $defaults, $taxonomy_args ) );

	}, 20 );
}


// ============================================================
// FILE LOADS
// ============================================================

require_once get_template_directory() . '/inc/folders/taxonomies.php';
require_once get_template_directory() . '/inc/folders/counts.php';
require_once get_template_directory() . '/inc/folders/filters.php';
require_once get_template_directory() . '/inc/folders/create.php';
require_once get_template_directory() . '/inc/folders/upload.php';
require_once get_template_directory() . '/inc/folders/move.php';
require_once get_template_directory() . '/inc/folders/order.php';
require_once get_template_directory() . '/inc/folders/sidebar.php';


// ============================================================
// DEFAULT FOLDER TYPE REGISTRATIONS
// ============================================================

// Pages and Posts ship as built-in folder-enabled types.
// Their taxonomies are explicitly registered in taxonomies.php;
// roci_register_folder_type() detects them at init and skips
// the auto-registration path.
roci_register_folder_type( 'page', 'roci_page_folder' );
roci_register_folder_type( 'post', 'roci_post_folder' );
