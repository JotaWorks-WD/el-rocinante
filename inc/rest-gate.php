<?php
/**
 * REST Gate — Field and Folder Data in Post Responses
 *
 * Strips Meta Box field values and folder term IDs from post, page,
 * attachment and CPT REST responses for requesters without the capability.
 *
 * File:    inc/rest-gate.php
 * Version: 1.0.0
 * Updated: 2026-10-08
 *
 * @package ElRocinante
 */

/**
 * Hook the gate onto every post type that is in the REST API.
 *
 * REGISTRY-DRIVEN, NOT A LIST. get_post_types( show_in_rest ) is read at
 * rest_api_init, after every CPT has registered on init, so a child's CPTs are
 * covered with no child code — and so is attachment, whose responses carry the
 * media folder IDs. Priority 99 so CPTs registered late on rest_api_init itself
 * are also picked up.
 */
function roci_gate_rest_register() {
	foreach ( get_post_types( array( 'show_in_rest' => true ) ) as $type ) {
		add_filter( "rest_prepare_{$type}", 'roci_gate_rest_post_fields', 99, 3 );
	}
}
add_action( 'rest_api_init', 'roci_gate_rest_register', 99 );

/**
 * Remove meta_box and the folder term IDs from a post response.
 *
 * WHY THIS EXISTS. Meta Box's REST extension adds every field of every box to
 * post responses under a `meta_box` key, via register_rest_field(), with no
 * permission check and no post_password check on read. An anonymous caller
 * could therefore read every field value — the internal ones (focus keyword,
 * raw robots/canonical, popup IDs) along with the public ones — and every
 * field of a password-protected post, whose content core itself withholds.
 * Separately, the #12 gate (roci_gate_folder_taxonomy_rest_reads()) closed the
 * folder taxonomies' own routes, but core still embeds each post's folder term
 * IDs in its response, plus a wp:term link naming the folder route, so folder
 * membership stayed readable anonymously.
 *
 * Because it runs on every show_in_rest post type and strips the whole key,
 * every field a child registers is covered too, with no child code and no
 * per-field opt-out to forget.
 *
 * READS ONLY. Writes go through core's update routes, which already require
 * edit_post; nothing here touches them. An editor's own requests carry the
 * REST nonce, so they pass both checks and get the full response.
 *
 * @param  WP_REST_Response $response The response object.
 * @param  WP_Post          $post     The post being prepared.
 * @param  WP_REST_Request  $request  The request.
 * @return WP_REST_Response
 */
function roci_gate_rest_post_fields( $response, $post, $request ) {

	if ( ! $response instanceof WP_REST_Response ) {
		return $response;
	}

	$data = $response->get_data();

	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		unset( $data['meta_box'] );
	}

	// Same capability as the #12 gate on the taxonomy routes, so the two agree.
	if ( ! current_user_can( 'upload_files' ) ) {

		$folder_taxonomies = roci_get_folder_taxonomies();

		foreach ( $folder_taxonomies as $taxonomy ) {
			$tax_obj = get_taxonomy( $taxonomy );
			if ( ! $tax_obj ) {
				continue;
			}
			// Keyed the way core keys it: rest_base, falling back to the name.
			$base = ! empty( $tax_obj->rest_base ) ? $tax_obj->rest_base : $taxonomy;
			unset( $data[ $base ] );
		}

		// Core adds one wp:term link per taxonomy, carrying the taxonomy name
		// as an attribute. Remove only the folder ones, by href.
		$links = $response->get_links();
		if ( ! empty( $links['https://api.w.org/term'] ) ) {
			foreach ( $links['https://api.w.org/term'] as $link ) {
				if ( isset( $link['attributes']['taxonomy'] )
					&& in_array( $link['attributes']['taxonomy'], $folder_taxonomies, true ) ) {
					$response->remove_link( 'https://api.w.org/term', $link['href'] );
				}
			}
		}
	}

	$response->set_data( $data );

	return $response;
}
