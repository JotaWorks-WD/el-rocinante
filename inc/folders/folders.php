<?php
/**
 * Folder System — Entry Point + Public Registration API
 *
 * Public API:
 *   roci_register_folder_type( $post_type, $taxonomy_slug, $taxonomy_args )
 *
 * Display helpers:
 *   roci_folder_display_name( $term_or_name )   — the ONLY term-name decode point
 *   roci_format_folder_option_label( $term, $taxonomy )
 *   roci_sort_folder_children_alphabetically( &$children ) — chooser sibling sort
 *
 * Registry helpers:
 *   roci_get_folder_registry()
 *   roci_get_folder_taxonomy_for_post_type( $post_type )
 *   roci_get_post_type_for_folder_taxonomy( $taxonomy_slug )
 *   roci_get_folder_taxonomies()                — every folder taxonomy name
 *
 * Loads all folder-system sub-files in dependency order.
 * This file is the single require_once target in functions.php;
 * adding a new phase means adding one more require_once here
 * rather than touching functions.php.
 *
 * Listed below in require order:
 *
 *   taxonomies.php — register roci_media_folder, roci_page_folder, roci_post_folder
 *   counts.php     — roci_get_folder_count, roci_get_unassigned_count, roci_get_all_count
 *   filters.php    — list-view dropdowns, pre_get_posts, media modal filter, JS
 *   create.php     — "+ New Folder" modal, AJAX endpoint, JS
 *   upload.php     — assign attachments to a folder term from the upload POST payload
 *   move.php       — move/bulk-move/bulk-delete AJAX, generic CPT drag-handle column
 *   order.php      — sibling sort order in term meta, reorder AJAX, reorder JS
 *   sidebar.php    — folder-tree sidebar, unassigned filter, JS enqueue
 *   branding.php   — brand accent -> --folders-highlight inline admin override
 *
 * File:    inc/folders/folders.php
 * Version: 2.15.0
 * Updated: 2026-07-30
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
 * Convert a stored folder term name into its display form.
 *
 * THIS IS THE ONE PLACE FOLDER NAMES ARE DECODED. Do not call
 * html_entity_decode() on a term name anywhere else, and do not pass a value
 * through this function twice.
 *
 * WordPress stores term names HTML-encoded. wp_insert_term() runs the name
 * through sanitize_term( …, 'db' ), which fires the pre_term_name filters
 * core registers in wp-includes/default-filters.php — sanitize_text_field,
 * wp_filter_kses, and _wp_specialchars at priority 30. So a folder the user
 * names "Rods & Reels" is written to wp_terms.name as "Rods &amp; Reels",
 * and get_term()/get_terms() return that encoded string verbatim because
 * their default filter context is 'raw'.
 *
 * Escaping that value again at the render site produced "&amp;amp;" — the
 * bug this function exists to prevent. Every read site must decode here and
 * escape exactly once at output (esc_html/esc_attr in PHP, textContent or an
 * equivalent encoder in JS). Decode-then-escape is the correct pair; dropping
 * the escape would turn a cosmetic defect into an XSS hole.
 *
 * @param  WP_Term|string $term_or_name  Folder term, or a raw stored name.
 * @return string                        Decoded, still-unescaped display name.
 */
function roci_folder_display_name( $term_or_name ) {
	$name = is_object( $term_or_name ) ? $term_or_name->name : $term_or_name;
	return html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' );
}

/**
 * Sort every sibling bucket of a parent_id => WP_Term[] map alphabetically.
 *
 * FOR CHOOSER DROPDOWNS ONLY. The sidebar tree (sidebar.php) is deliberately
 * NOT a caller: it stays on the hand-sortable roci_folder_order drag order
 * from roci_get_folder_order_query_args(). Only the <select>-style choosers —
 * where a user is hunting for a known folder name rather than navigating a
 * curated structure — sort A-Z.
 *
 * Sorts by the DECODED name from roci_folder_display_name(), not the raw
 * column. A get_terms( orderby => 'name' ) would sort what the DB stores, so
 * "Logo &amp; Branding" would sort under "&amp;" rather than under the "&"
 * the user actually sees. Sorting in PHP is the only way to order by what is
 * rendered.
 *
 * Hierarchy is untouched: this reorders the CONTENTS of each bucket, never
 * moves a term between buckets. Callers walk the map depth-first afterwards
 * and compute depth/indent as before, so parents still nest their children —
 * only sibling order within each level changes.
 *
 * @param array &$children  Map of parent_id => WP_Term[], sorted in place.
 */
function roci_sort_folder_children_alphabetically( &$children ) {
	foreach ( $children as &$bucket ) {
		usort( $bucket, function ( $a, $b ) {
			return strnatcasecmp(
				roci_folder_display_name( $a ),
				roci_folder_display_name( $b )
			);
		} );
	}
	unset( $bucket );
}

/**
 * Format a folder term name with its item count for display in <select> options.
 *
 * Single source of truth used by the list-view filter dropdowns, the grid-view
 * JS localisation, and the AJAX-refreshed option list after folder creation.
 * All three render paths call this so counts can never drift out of sync.
 *
 * The name is decoded via roci_folder_display_name(); the returned string is
 * unescaped and every caller is responsible for escaping it once at output.
 *
 * @param  WP_Term $term      The folder term.
 * @param  string  $taxonomy  Folder taxonomy slug.
 * @return string             e.g. "My Folder (5)"
 */
function roci_format_folder_option_label( $term, $taxonomy ) {
	return sprintf(
		'%s (%d)',
		roci_folder_display_name( $term ),
		(int) roci_get_folder_count( $term, $taxonomy )
	);
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
 * Return every folder taxonomy name.
 *
 * The Media folder taxonomy plus every registry-registered CPT folder taxonomy
 * — page and post, and any CPT a child opts in with roci_register_folder_type().
 * roci_media_folder is prepended rather than looked up because it belongs to the
 * Media system and is deliberately not in the CPT registry.
 *
 * The same merge expression is currently inlined at five call sites
 * (counts.php:171-174, create.php:251-254, order.php:65-68, :121-124, :252-255).
 * Those are deliberately left alone for now — converging them is its own change.
 * New callers should use this helper.
 *
 * @return string[]
 */
function roci_get_folder_taxonomies() {
	return array_merge(
		array( 'roci_media_folder' ),
		array_values( roci_get_folder_registry() )
	);
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
			// BUG #6 REMNANT FIX. This key was absent, and register_taxonomy()
			// defaults 'public' to TRUE — so every CPT folder taxonomy created
			// through this path got a live front-end archive at its query var,
			// while the three explicitly registered ones in taxonomies.php were
			// corrected to false in v1.6.1 and this path was missed.
			// Archive exposure only; the REST half is handled separately by the
			// permission gate in taxonomies.php, which is registry-driven and so
			// already covers taxonomies registered here.
			'public'            => false,
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
require_once get_template_directory() . '/inc/folders/branding.php';


// ============================================================
// DEFAULT FOLDER TYPE REGISTRATIONS
// ============================================================

// Pages and Posts ship as built-in folder-enabled types.
// Their taxonomies are explicitly registered in taxonomies.php;
// roci_register_folder_type() detects them at init and skips
// the auto-registration path.
roci_register_folder_type( 'page', 'roci_page_folder' );
roci_register_folder_type( 'post', 'roci_post_folder' );
