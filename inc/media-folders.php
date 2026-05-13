<?php
/**
 * Media & Page Folder Taxonomies
 *
 * Registers two hierarchical taxonomies and wires up folder filter
 * dropdowns in the admin list views and the media picker modal.
 * A "+ New Folder" button in the filter row opens an inline modal
 * so editors can create folders without leaving the page.
 *
 *   roci_media_folder — on 'attachment'; appears in Media Library
 *   roci_page_folder  — on 'page'; appears in the Pages list
 *
 * Both are hierarchical so parent/child nesting works natively via
 * WordPress's built-in term management UI — no custom tree needed.
 *
 * Intentionally NOT included in v1:
 *   - Drag-and-drop tree view
 *   - Bulk-move items between folders
 *   - Rename / delete folder UI (use Media → Media Folders for now)
 *   - Front-end folder output of any kind
 *
 * File:    inc/media-folders.php
 * Version: 1.1.2
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


/**
 * Filter the Media Library list-view query when a folder is selected.
 *
 * include_children => true means selecting "Charters" also surfaces
 * attachments assigned to "Charters > Bushwacker" and any other children.
 *
 * @param WP_Query $query
 */
function roci_filter_media_by_folder( $query ) {

    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || 'upload' !== $screen->base ) {
        return;
    }

    $folder = isset( $_GET['roci_media_folder'] ) ? absint( $_GET['roci_media_folder'] ) : 0;
    if ( ! $folder ) {
        return;
    }

    $query->set( 'tax_query', array(
        array(
            'taxonomy'         => 'roci_media_folder',
            'field'            => 'term_id',
            'terms'            => $folder,
            'include_children' => true,
        ),
    ) );
}
add_action( 'pre_get_posts', 'roci_filter_media_by_folder' );


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


/**
 * Filter the Pages list-view query when a folder is selected.
 *
 * @param WP_Query $query
 */
function roci_filter_pages_by_folder( $query ) {

    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $screen = get_current_screen();
    // Screen ID is 'edit-page' (not base 'edit') when post_type=page.
    if ( ! $screen || 'edit-page' !== $screen->id ) {
        return;
    }

    $folder = isset( $_GET['roci_page_folder'] ) ? absint( $_GET['roci_page_folder'] ) : 0;
    if ( ! $folder ) {
        return;
    }

    $query->set( 'tax_query', array(
        array(
            'taxonomy'         => 'roci_page_folder',
            'field'            => 'term_id',
            'terms'            => $folder,
            'include_children' => true,
        ),
    ) );
}
add_action( 'pre_get_posts', 'roci_filter_pages_by_folder' );


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
 */
function roci_render_new_folder_modal() {

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
add_action( 'admin_footer', 'roci_render_new_folder_modal' );


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
        '1.0.0',
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
