<?php
/**
 * Folder System — Filters
 *
 * Wires up folder filter dropdowns in the admin list views and the media
 * picker modal. Includes:
 *
 *   - restrict_manage_posts dropdowns for upload.php and edit.php?post_type=page
 *   - request filter that translates custom folder query vars into WP's standard taxonomy/term vars
 *   - ajax_query_attachments_args filter for the media picker modal
 *   - JS enqueue (media-folder-filter.js) for the modal folder filter UI
 *
 * File:    inc/folders/filters.php
 * Version: 1.2.2
 * Updated: 2026-05-13
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// ADMIN LIST — MEDIA FOLDER FILTER (upload.php list view)
// ============================================================

/**
 * Render the folder <select> in the Media Library list-view toolbar.
 *
 * wp_dropdown_categories() handles parent/child indentation natively
 * because the taxonomy is hierarchical — no custom tree-walk needed.
 *
 * @param string $post_type  Current list-table post type.
 * @param string $which      Toolbar position. WP_Media_List_Table uses 'bar'
 *                           (not 'top'/'bottom' like WP_Posts_List_Table).
 */
function roci_media_folder_filter_dropdown( $post_type, $which ) {

	if ( 'attachment' !== $post_type || 'bar' !== $which ) {
		return;
	}

	$selected = isset( $_GET['roci_media_folder'] ) ? absint( $_GET['roci_media_folder'] ) : 0;

	echo '<label class="screen-reader-text" for="roci-media-folder-filter">'
		. esc_html__( 'Filter by Media Folder', 'rocinante' )
		. '</label>';

	wp_dropdown_categories( array(
		'show_option_all' => __( 'All Folders', 'rocinante' ),
		'taxonomy'        => 'roci_media_folder',
		'name'            => 'roci_media_folder',
		'id'              => 'roci-media-folder-filter',
		'orderby'         => 'name',
		'selected'        => $selected,
		'show_count'      => false,
		'hide_empty'      => false,
		'hierarchical'    => true,
		'value_field'     => 'term_id',
	) );

	echo ' <button type="button" class="button" id="roci-new-folder-btn">'
		. esc_html__( '+ New Folder', 'rocinante' )
		. '</button>';
}
add_action( 'restrict_manage_posts', 'roci_media_folder_filter_dropdown', 10, 2 );


// ============================================================
// ADMIN LIST — PAGE FOLDER FILTER (edit.php?post_type=page)
// ============================================================

/**
 * Render the folder <select> in the Pages list-view toolbar.
 *
 * @param string $post_type  Current list-table post type.
 * @param string $which      Toolbar position: 'top' or 'bottom'.
 */
function roci_page_folder_filter_dropdown( $post_type, $which ) {

	if ( 'page' !== $post_type || 'top' !== $which ) {
		return;
	}

	$selected = isset( $_GET['roci_page_folder'] ) ? absint( $_GET['roci_page_folder'] ) : 0;

	echo '<label class="screen-reader-text" for="roci-page-folder-filter">'
		. esc_html__( 'Filter by Page Folder', 'rocinante' )
		. '</label>';

	wp_dropdown_categories( array(
		'show_option_all' => __( 'All Folders', 'rocinante' ),
		'taxonomy'        => 'roci_page_folder',
		'name'            => 'roci_page_folder',
		'id'              => 'roci-page-folder-filter',
		'orderby'         => 'name',
		'selected'        => $selected,
		'show_count'      => false,
		'hide_empty'      => false,
		'hierarchical'    => true,
		'value_field'     => 'term_id',
	) );

	echo ' <button type="button" class="button" id="roci-new-folder-btn">'
		. esc_html__( '+ New Folder', 'rocinante' )
		. '</button>';
}
add_action( 'restrict_manage_posts', 'roci_page_folder_filter_dropdown', 10, 2 );


// ============================================================
// REQUEST FILTER — TRANSLATE FOLDER QUERY VARS
// ============================================================

/**
 * Translate custom folder query vars into WP's standard taxonomy+term vars.
 *
 * The `request` filter runs after WP parses the URL but before WP_Query
 * builds its SQL, so $vars['taxonomy'] / $vars['term'] are always honoured —
 * including include_children behaviour, which WP handles natively for
 * hierarchical taxonomies when queried this way.
 *
 * @param  array $vars  Parsed query vars.
 * @return array
 */
function roci_translate_folder_query_var( $vars ) {
	if ( ! is_admin() ) {
		return $vars;
	}

	global $pagenow;

	if ( 'upload.php' === $pagenow && ! empty( $_GET['roci_media_folder'] ) ) {
		$folder_id = absint( $_GET['roci_media_folder'] );
		if ( $folder_id > 0 ) {
			$term = get_term( $folder_id, 'roci_media_folder' );
			if ( $term && ! is_wp_error( $term ) ) {
				$vars['taxonomy'] = 'roci_media_folder';
				$vars['term']     = $term->slug;
			}
		}
	}

	if ( 'edit.php' === $pagenow && ! empty( $_GET['roci_page_folder'] ) ) {
		$folder_id = absint( $_GET['roci_page_folder'] );
		if ( $folder_id > 0 ) {
			$term = get_term( $folder_id, 'roci_page_folder' );
			if ( $term && ! is_wp_error( $term ) ) {
				$vars['taxonomy'] = 'roci_page_folder';
				$vars['term']     = $term->slug;
			}
		}
	}

	return $vars;
}
add_filter( 'request', 'roci_translate_folder_query_var' );


// ============================================================
// MEDIA MODAL — AJAX FILTER
// ============================================================

/**
 * Apply the folder filter when the media picker modal queries attachments.
 *
 * The media modal sends requests to wp-admin/admin-ajax.php?action=query-attachments
 * (not the REST API). WordPress's AJAX handler whitelists known query keys and
 * strips anything else before building the WP_Query — so we must intercept here
 * via ajax_query_attachments_args and read from $_REQUEST['query'] directly.
 *
 * include_children => true is required so selecting a parent folder
 * also returns attachments inside its child terms.
 *
 * @param  array $args  WP_Query args already assembled by wp_ajax_query_attachments().
 * @return array
 */
function roci_media_folder_modal_ajax_filter( $args ) {

	if ( empty( $_REQUEST['query']['roci_media_folder'] ) ) {
		return $args;
	}

	$folder = absint( $_REQUEST['query']['roci_media_folder'] );
	if ( ! $folder ) {
		return $args;
	}

	$args['tax_query'] = array(
		array(
			'taxonomy'         => 'roci_media_folder',
			'field'            => 'term_id',
			'terms'            => $folder,
			'include_children' => true,
		),
	);

	return $args;
}
add_filter( 'ajax_query_attachments_args', 'roci_media_folder_modal_ajax_filter' );


// ============================================================
// MEDIA MODAL — ENQUEUE JS + LOCALIZE TERMS
// ============================================================

/**
 * Build the roci_media_folder term list for JS consumption.
 *
 * Returns a flat array ordered by parent so children follow their parent.
 * Depth is computed via get_ancestors() and converted to em-dash indentation
 * so the JS can render a visually hierarchical <select> without a tree-walk.
 *
 * @return array  [ [ 'term_id' => int, 'name' => string ], ... ]
 */
function roci_get_folder_terms_for_js() {

	$terms = get_terms( array(
		'taxonomy'   => 'roci_media_folder',
		'hide_empty' => false,
		'orderby'    => 'parent',
		'order'      => 'ASC',
	) );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$data = array();

	foreach ( $terms as $term ) {
		$depth  = count( get_ancestors( $term->term_id, 'roci_media_folder', 'taxonomy' ) );
		$indent = $depth > 0 ? str_repeat( "\u{2014} ", $depth ) : '';
		$data[] = array(
			'term_id' => $term->term_id,
			'name'    => $indent . $term->name,
		);
	}

	return $data;
}


/**
 * Enqueue the media modal folder filter on relevant admin screens.
 *
 * post.php / post-new.php — Featured Image picker + Insert Media dialog.
 * upload.php              — Media Library grid view.
 *
 * The script depends on 'media-views', which WordPress loads on all three
 * screens either automatically (upload.php) or via wp_enqueue_media()
 * (post edit screens).
 *
 * @param string $hook_suffix  Current admin page hook suffix.
 */
function roci_enqueue_media_folder_js( $hook_suffix ) {

	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'upload.php' ), true ) ) {
		return;
	}

	wp_enqueue_script(
		'roci-media-folder-filter',
		get_template_directory_uri() . '/dist/js/media-folder-filter.js',
		array( 'media-views' ),
		'1.0.0',
		true
	);

	wp_localize_script( 'roci-media-folder-filter', 'rociMediaFolders', array(
		'terms'    => roci_get_folder_terms_for_js(),
		'allLabel' => __( 'All Folders', 'rocinante' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_enqueue_media_folder_js' );
