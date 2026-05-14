<?php
/**
 * Folder System — Create
 *
 * "+ New Folder" button, inline modal, and AJAX endpoint for term creation.
 * The button itself is rendered inline by the filter dropdown functions in
 * filters.php; this file owns the modal HTML, the AJAX handler, and the
 * JS that drives both.
 *
 *   roci_build_folder_options_for_select() — shared option-list helper
 *   roci_render_new_folder_modal()         — modal HTML via admin_footer
 *   roci_ajax_create_folder()              — wp_ajax_roci_create_folder
 *   roci_enqueue_admin_folders_js()        — enqueues admin-folders.js
 *
 * File:    inc/folders/create.php
 * Version: 1.2.2
 * Updated: 2026-05-14
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// NEW FOLDER — SHARED HELPER
// ============================================================

/**
 * Build a flat ordered option array for a folder <select>.
 *
 * Used both for the initial JS localization pass and when rebuilding
 * selects after a folder is created via AJAX. Terms are ordered by
 * parent so children follow their parent; em-dash indentation reflects
 * depth. Does NOT include a leading "All Folders" or "No Parent" option
 * — callers prepend those contextually.
 *
 * @param  string $taxonomy  'roci_media_folder' or 'roci_page_folder'.
 * @return array             [ [ 'value' => int, 'label' => string ], ... ]
 */
function roci_build_folder_options_for_select( $taxonomy ) {

	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'orderby'    => 'parent',
		'order'      => 'ASC',
	) );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$options = array();

	foreach ( $terms as $term ) {
		$depth     = count( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) );
		$indent    = $depth > 0 ? str_repeat( "\u{2014} ", $depth ) : '';
		$options[] = array(
			'value' => $term->term_id,
			'label' => $indent . $term->name,
		);
	}

	return $options;
}


// ============================================================
// NEW FOLDER — MODAL HTML
// ============================================================

/**
 * Render the "+ New Folder" modal in the admin page footer.
 *
 * One modal serves both upload.php (roci_media_folder) and
 * edit.php?post_type=page (roci_page_folder). The parent dropdown
 * is rendered via PHP at page load since the screen — and therefore
 * the taxonomy — is known. The JS rebuilds both selects from the
 * option list returned by the AJAX handler after each creation.
 *
 * Registered on admin_footer-upload.php (fires for both list and grid mode)
 * AND admin_footer (for edit.php?post_type=page). The static flag prevents
 * double-rendering on upload.php where both hooks fire.
 */
function roci_render_new_folder_modal() {
	static $done = false;
	if ( $done ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	if ( 'upload' === $screen->base ) {
		$taxonomy = 'roci_media_folder';
	} elseif ( 'edit-page' === $screen->id ) {
		$taxonomy = 'roci_page_folder';
	} else {
		return;
	}

	$done = true;

	?>
	<div id="roci-folder-backdrop"
	     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:159900;"
	     aria-hidden="true"></div>

	<div id="roci-folder-modal"
	     style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:24px 28px;z-index:160000;min-width:340px;max-width:480px;width:90%;box-shadow:0 4px 24px rgba(0,0,0,.25);border-radius:3px;"
	     role="dialog" aria-modal="true" aria-labelledby="roci-folder-modal-title">

		<h2 id="roci-folder-modal-title"
		    style="margin-top:0;padding-bottom:12px;border-bottom:1px solid #dcdcde;font-size:1.1em;">
			<?php esc_html_e( 'Create New Folder', 'rocinante' ); ?>
		</h2>

		<form id="roci-folder-form">

			<p style="margin:16px 0 12px;">
				<label for="roci-folder-name"
				       style="display:block;margin-bottom:4px;font-weight:600;">
					<?php esc_html_e( 'Folder Name', 'rocinante' ); ?>
					<span style="color:#d63638;" aria-hidden="true">*</span>
				</label>
				<input type="text"
				       id="roci-folder-name"
				       class="regular-text"
				       style="width:100%;"
				       autocomplete="off">
			</p>

			<p style="margin-bottom:16px;">
				<label for="roci-folder-parent"
				       style="display:block;margin-bottom:4px;font-weight:600;">
					<?php esc_html_e( 'Parent Folder', 'rocinante' ); ?>
				</label>
				<?php
				wp_dropdown_categories( array(
					'show_option_none'  => __( '— No Parent —', 'rocinante' ),
					'option_none_value' => '0',
					'taxonomy'          => $taxonomy,
					'name'              => 'roci_folder_parent',
					'id'                => 'roci-folder-parent',
					'orderby'           => 'name',
					'selected'          => 0,
					'show_count'        => false,
					'hide_empty'        => false,
					'hierarchical'      => true,
					'value_field'       => 'term_id',
				) );
				?>
			</p>

			<p id="roci-folder-error"
			   style="display:none;color:#d63638;margin-bottom:12px;padding:8px 10px;background:#fcf0f1;border-left:3px solid #d63638;border-radius:2px;"></p>

			<p style="margin:0;">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Create Folder', 'rocinante' ); ?>
				</button>
				&nbsp;
				<button type="button" id="roci-folder-cancel" class="button">
					<?php esc_html_e( 'Cancel', 'rocinante' ); ?>
				</button>
			</p>

		</form>
	</div>
	<?php
}
add_action( 'admin_footer-upload.php', 'roci_render_new_folder_modal' );
add_action( 'admin_footer',            'roci_render_new_folder_modal' );


// ============================================================
// NEW FOLDER — AJAX HANDLER
// ============================================================

/**
 * Create a new folder term via AJAX.
 *
 * Security chain:
 *   1. Nonce verification (dies on failure).
 *   2. manage_categories capability check.
 *   3. Taxonomy validated against an explicit allowlist — POST data is
 *      never trusted for taxonomy selection.
 *   4. Folder name sanitized and required non-empty.
 *   5. Parent term validated against the same taxonomy if provided.
 *
 * One unified endpoint handles both taxonomies because the logic is
 * identical; only the taxonomy slug varies.
 *
 * Success response includes a rebuilt option list so the JS can refresh
 * both the filter dropdown and the modal parent dropdown without a reload.
 */
function roci_ajax_create_folder() {

	check_ajax_referer( 'roci_create_folder', 'nonce' );

	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_send_json_error( __( 'You do not have permission to create folders.', 'rocinante' ), 403 );
	}

	$allowed  = array( 'roci_media_folder', 'roci_page_folder' );
	$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';
	if ( ! in_array( $taxonomy, $allowed, true ) ) {
		wp_send_json_error( __( 'Invalid taxonomy.', 'rocinante' ), 400 );
	}

	$name = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';
	if ( '' === $name ) {
		wp_send_json_error( __( 'Folder name is required.', 'rocinante' ) );
	}

	$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
	if ( $parent > 0 && ! term_exists( $parent, $taxonomy ) ) {
		wp_send_json_error( __( 'Selected parent folder does not exist.', 'rocinante' ) );
	}

	$result = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	$term = get_term( $result['term_id'], $taxonomy );

	wp_send_json_success( array(
		'term'    => array(
			'term_id' => $term->term_id,
			'name'    => $term->name,
			'parent'  => $term->parent,
		),
		'options' => roci_build_folder_options_for_select( $taxonomy ),
	) );
}
add_action( 'wp_ajax_roci_create_folder', 'roci_ajax_create_folder' );


// ============================================================
// NEW FOLDER — ENQUEUE JS
// ============================================================

/**
 * Enqueue admin-folders.js on the Media Library and Pages list screens.
 *
 * Kept separate from roci_enqueue_media_folder_js() which targets post
 * editor screens for the media picker modal filter. This script only
 * needs to run on the two admin-list screens that have the + New Folder
 * button. Uses get_current_screen() rather than hook suffix alone so we
 * can distinguish edit.php?post_type=page from other edit.php screens.
 *
 * @param string $hook_suffix  Current admin page hook suffix.
 */
function roci_enqueue_admin_folders_js( $hook_suffix ) {

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	if ( 'upload' === $screen->base ) {
		$taxonomy         = 'roci_media_folder';
		$filter_select_id = 'roci-media-folder-filter';
	} elseif ( 'edit-page' === $screen->id ) {
		$taxonomy         = 'roci_page_folder';
		$filter_select_id = 'roci-page-folder-filter';
	} else {
		return;
	}

	wp_enqueue_script(
		'roci-admin-folders',
		get_template_directory_uri() . '/dist/js/admin-folders.js',
		array(),
		'1.2.0',
		true
	);

	wp_localize_script( 'roci-admin-folders', 'rociAdminFolders', array(
		'nonce'          => wp_create_nonce( 'roci_create_folder' ),
		'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
		'taxonomy'       => $taxonomy,
		'filterSelectId' => $filter_select_id,
		'i18n'           => array(
			'allFolders'     => __( 'All Folders', 'rocinante' ),
			'noParent'       => __( '— No Parent —', 'rocinante' ),
			'nameRequired'   => __( 'Folder name is required.', 'rocinante' ),
			'requestFailed'  => __( 'Request failed. Please try again.', 'rocinante' ),
			'newFolderLabel' => __( '+ New Folder', 'rocinante' ),
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_enqueue_admin_folders_js' );
