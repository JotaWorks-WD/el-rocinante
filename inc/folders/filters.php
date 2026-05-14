<?php
/**
 * Folder System — Filters
 *
 * Wires up folder filter dropdowns in the admin list views and the media
 * picker modal. Includes:
 *
 *   - restrict_manage_posts dropdowns for upload.php and edit.php?post_type=page
 *   - admin_init hook that converts numeric folder query vars (term_id) to slug so WP's auto-registered taxonomy query var builds the correct tax_query
 *   - ajax_query_attachments_args filter for the media picker modal
 *   - JS enqueue (media-folder-filter.js) that injects a separate folder filter
 *     into the AttachmentsBrowser toolbar (after the type + date filters)
 *
 * File:    inc/folders/filters.php
 * Version: 1.5.1
 * Updated: 2026-05-14
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

	$selected = 0;
	if ( ! empty( $_GET['roci_media_folder'] ) ) {
		$val = sanitize_text_field( wp_unslash( $_GET['roci_media_folder'] ) );
		if ( is_numeric( $val ) ) {
			$selected = absint( $val );
		} else {
			$term = get_term_by( 'slug', $val, 'roci_media_folder' );
			if ( $term ) {
				$selected = $term->term_id;
			}
		}
	}

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

	$selected = 0;
	if ( ! empty( $_GET['roci_page_folder'] ) ) {
		$val = sanitize_text_field( wp_unslash( $_GET['roci_page_folder'] ) );
		if ( is_numeric( $val ) ) {
			$selected = absint( $val );
		} else {
			$term = get_term_by( 'slug', $val, 'roci_page_folder' );
			if ( $term ) {
				$selected = $term->term_id;
			}
		}
	}

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
 * Convert numeric folder query vars (term_id) to the term's slug.
 *
 * WordPress auto-registers every hierarchical custom taxonomy as a query var
 * and converts it to a tax_query using the value as a slug. By replacing the
 * numeric term_id with the slug here (before WP parses query vars), WP builds
 * a single, correct tax_query — including include_children for free. Setting
 * taxonomy+term vars separately would create a second conflicting tax_query.
 */
function roci_translate_folder_query_var() {
	if ( ! is_admin() ) {
		return;
	}

	global $pagenow;

	if ( 'upload.php' === $pagenow && ! empty( $_GET['roci_media_folder'] ) ) {
		$val = sanitize_text_field( wp_unslash( $_GET['roci_media_folder'] ) );
		if ( is_numeric( $val ) ) {
			$term = get_term( absint( $val ), 'roci_media_folder' );
			if ( $term && ! is_wp_error( $term ) ) {
				$_GET['roci_media_folder']     = $term->slug;
				$_REQUEST['roci_media_folder'] = $term->slug;
			}
		}
	}

	if ( 'edit.php' === $pagenow && ! empty( $_GET['roci_page_folder'] ) ) {
		$val = sanitize_text_field( wp_unslash( $_GET['roci_page_folder'] ) );
		if ( is_numeric( $val ) ) {
			$term = get_term( absint( $val ), 'roci_page_folder' );
			if ( $term && ! is_wp_error( $term ) ) {
				$_GET['roci_page_folder']     = $term->slug;
				$_REQUEST['roci_page_folder'] = $term->slug;
			}
		}
	}
}
add_action( 'admin_init', 'roci_translate_folder_query_var', 1 );


// ============================================================
// MEDIA MODAL — AJAX FILTER
// ============================================================

/**
 * Apply the folder filter when the media library queries attachments via AJAX.
 *
 * wp_ajax_query_attachments() passes all recognised query keys through to
 * WP_Query. Because roci_media_folder is a registered taxonomy, WP_Query
 * treats a bare integer value (e.g. 29) as a term SLUG — no slug matches,
 * returns nothing. Fix: pull the term_id out of the query array, unset the
 * raw key so WP_Query never sees it, then inject a proper tax_query.
 *
 * include_children => true mirrors list-view behaviour: selecting a parent
 * folder also surfaces attachments inside its child terms.
 *
 * @param  array $query  WP_Query args assembled by wp_ajax_query_attachments().
 * @return array
 */
function roci_media_folder_modal_ajax_filter( $query ) {

	if ( empty( $query['roci_media_folder'] ) ) {
		return $query;
	}

	$term_id = (int) $query['roci_media_folder'];
	if ( ! $term_id ) {
		return $query;
	}

	// Critical: remove the raw key before WP_Query sees it, or WP will
	// treat the integer as a slug query and override our tax_query.
	unset( $query['roci_media_folder'] );

	$query['tax_query'] = array(
		array(
			'taxonomy'         => 'roci_media_folder',
			'field'            => 'term_id',
			'terms'            => $term_id,
			'include_children' => true,
		),
	);

	return $query;
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
 * Enqueue the media folder filter script on relevant admin screens.
 *
 * post.php / post-new.php — Featured Image picker + Insert Media dialog.
 * upload.php              — Media Library, both list view and grid view.
 *                           Grid view injects the dropdown + button via the
 *                           Backbone AttachmentsBrowser extension in the JS;
 *                           list view renders them via PHP (restrict_manage_posts).
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
		'1.5.0',
		true
	);

	wp_enqueue_style(
		'roci-admin-folders',
		get_template_directory_uri() . '/dist/css/admin-folders.css',
		array( 'wp-admin' ),
		'1.1.1'
	);

	wp_localize_script( 'roci-media-folder-filter', 'rociMediaFolders', array(
		'terms'    => roci_get_folder_terms_for_js(),
		'allLabel' => __( 'All Folders', 'rocinante' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_enqueue_media_folder_js' );
