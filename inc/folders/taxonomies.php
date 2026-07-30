<?php
/**
 * Folder System — Taxonomy Registration
 *
 * Registers three hierarchical taxonomies used throughout the folder system:
 *
 *   roci_media_folder — on 'attachment'; appears in Media Library
 *   roci_page_folder  — on 'page'; appears in the Pages list
 *   roci_post_folder  — on 'post'; appears in the Posts list
 *
 * All are hierarchical so parent/child nesting works natively via
 * WordPress's built-in term management UI — no custom tree needed.
 *
 * All three set show_in_rest => true and that is deliberate, but REST reads are
 * gated behind upload_files by roci_gate_folder_taxonomy_rest_reads() at the
 * foot of this file — show_in_rest and public are independent switches, and
 * public => false does not restrict the REST API.
 *
 * File:    inc/folders/taxonomies.php
 * Version: 1.7.1
 * Updated: 2026-07-30
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
		'public'                => false,
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
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => false,
	) );
}
add_action( 'init', 'roci_register_page_folder_taxonomy' );


/**
 * Register the roci_post_folder taxonomy on posts.
 */
function roci_register_post_folder_taxonomy() {

	$labels = array(
		'name'              => _x( 'Post Fauxlders',          'taxonomy general name',  'rocinante' ),
		'singular_name'     => _x( 'Post Fauxlder',           'taxonomy singular name', 'rocinante' ),
		'search_items'      => __( 'Search Post Fauxlders',   'rocinante' ),
		'all_items'         => __( 'All Post Fauxlders',      'rocinante' ),
		'parent_item'       => __( 'Parent Fauxlder',         'rocinante' ),
		'parent_item_colon' => __( 'Parent Fauxlder:',        'rocinante' ),
		'edit_item'         => __( 'Edit Post Fauxlder',      'rocinante' ),
		'update_item'       => __( 'Update Post Fauxlder',    'rocinante' ),
		'add_new_item'      => __( 'Add New Post Fauxlder',   'rocinante' ),
		'new_item_name'     => __( 'New Post Fauxlder Name',  'rocinante' ),
		'menu_name'         => __( 'Post Fauxlders',          'rocinante' ),
	);

	register_taxonomy( 'roci_post_folder', 'post', array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => false,
	) );
}
add_action( 'init', 'roci_register_post_folder_taxonomy' );


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


// ============================================================
// REST — READ PERMISSION GATE
// ============================================================

/**
 * Require the upload_files capability to READ folder terms over the REST API.
 *
 * WHY THIS EXISTS. All folder taxonomies register show_in_rest => true, and
 * that is deliberate — the route stays available to authenticated callers and
 * to the block editor. But show_in_rest and public are independent switches:
 * public => false governs front-end query and archive behaviour and has no
 * bearing on REST. WP_REST_Terms_Controller applies a capability test only when
 * the request asks for context=edit; for the default context=view it returns
 * true, so an unauthenticated caller could read the entire folder tree at
 * /wp-json/wp/v2/roci_media_folder. Confirmed live. This closes that without
 * withdrawing the route.
 *
 * WHY rest_endpoints RATHER THAN THE OBVIOUS ALTERNATIVES. register_taxonomy()
 * has no permission_callback argument to pass, so the choice is where to
 * intercept. rest_{$taxonomy}_query was rejected outright: it runs AFTER the
 * permission check, so the best it could return is 200 with an empty array, and
 * it never fires for single-term reads — /wp/v2/roci_media_folder/123 goes
 * through get_item(), so an anonymous caller could still walk the tree by ID.
 * That would have looked fixed while leaving the hole open. Subclassing via
 * rest_controller_class is the most idiomatic layer and remains the escalation
 * path, but it means a new class and three registration edits for what one
 * filter achieves, and it couples us to a core class signature. rest_endpoints
 * hands us the route map with each handler's methods and permission_callback
 * before dispatch, which is precise enough to wrap reads and leave writes alone.
 *
 * WRITES ARE UNTOUCHED BY CONSTRUCTION, not by intention: only handlers whose
 * methods include GET are wrapped, so create/update/delete keep the
 * manage_terms / edit_terms / delete_terms checks core already applies.
 *
 * upload_files is the capability because it is the honest question here — "may
 * this user touch media at all". It covers administrators today and any future
 * editor or author with media access, while excluding subscribers.
 *
 * @param  array $endpoints Route map: route => list of handlers.
 * @return array
 */
function roci_gate_folder_taxonomy_rest_reads( $endpoints ) {

	foreach ( roci_get_folder_taxonomies() as $taxonomy ) {

		$tax_obj = get_taxonomy( $taxonomy );
		if ( ! $tax_obj || empty( $tax_obj->show_in_rest ) ) {
			continue;
		}

		// rest_base defaults to the taxonomy name, but roci_register_folder_type()
		// merges arbitrary $taxonomy_args, so a child can override it. Reading it
		// from the object costs one line; assuming the slug would silently fail to
		// gate any taxonomy that had set one.
		$base   = ! empty( $tax_obj->rest_base ) ? $tax_obj->rest_base : $taxonomy;
		$prefix = '/wp/v2/' . $base;

		foreach ( $endpoints as $route => $handlers ) {

			// Prefix match rather than exact keys, so the id route is covered
			// without hardcoding core's ID regex. The boundary guard stops
			// /wp/v2/roci_post_folder from also matching a hypothetical
			// /wp/v2/roci_post_folders.
			if ( 0 !== strpos( $route, $prefix ) ) {
				continue;
			}
			$tail = substr( $route, strlen( $prefix ) );
			if ( '' !== $tail && '/' !== $tail[0] ) {
				continue;
			}

			foreach ( $handlers as $index => $handler ) {

				// GET DETECTION MUST HANDLE THREE SHAPES. This is what the first
				// cut of the gate got wrong, and the failure was silent: it
				// tested empty( $handler['methods']['GET'] ), which only works
				// once WP has normalised methods into array( 'GET' => true ).
				// At rest_endpoints the value can still be the raw thing passed
				// to register_rest_route() — the WP_REST_Server::READABLE
				// constant, a plain string like 'GET', or a comma string like
				// 'GET, POST'. Indexing a string by 'GET' does not evaluate the
				// way the array form does, so the guard fired for every handler,
				// nothing was ever wrapped, and anonymous reads kept returning
				// 200 while the code looked correct. Normalise instead of
				// assuming.
				$methods = isset( $handler['methods'] ) ? $handler['methods'] : null;

				if ( is_string( $methods ) ) {
					$has_get = ( false !== stripos( $methods, 'GET' ) );
				} elseif ( is_array( $methods ) ) {
					// Either array( 'GET' => true ) or a list array( 'GET', 'POST' ).
					$has_get = ! empty( $methods['GET'] )
						|| in_array( 'GET', $methods, true )
						|| in_array( 'GET', array_keys( $methods ), true );
				} else {
					$has_get = false;
				}

				if ( ! $has_get ) {
					continue; // reads only
				}

				$original = isset( $handler['permission_callback'] )
					? $handler['permission_callback']
					: null;

				$endpoints[ $route ][ $index ]['permission_callback'] = function ( $request ) use ( $original ) {

					// Capability FIRST, before delegating. On the single-term
					// route the original check can tell "no such term" apart
					// from "term exists", so running it first would leak
					// existence to a caller who is not allowed to know.
					if ( ! current_user_can( 'upload_files' ) ) {
						return new WP_Error(
							'roci_rest_forbidden',
							__( 'You are not allowed to view fauxlders.', 'rocinante' ),
							array( 'status' => rest_authorization_required_code() )
						);
					}

					// Delegate so core's own rules still apply on top of ours —
					// context=edit still needs edit_terms, and so on.
					return $original ? call_user_func( $original, $request ) : true;
				};
			}
		}
	}

	return $endpoints;
}
// No priority argument needed: rest_endpoints fires during rest_api_init, long
// after the init-priority-20 auto-registrations in roci_register_folder_type(),
// so roci_get_folder_taxonomies() is fully populated by the time this runs.
add_filter( 'rest_endpoints', 'roci_gate_folder_taxonomy_rest_reads' );
