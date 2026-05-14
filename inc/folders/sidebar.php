<?php
/**
 * Folder System — Sidebar
 *
 * Renders the left folder-tree sidebar on:
 *   - upload.php (Media Library — list view and grid view)
 *   - edit.php?post_type=page (Pages list)
 *
 * NOT rendered inside the modal media picker; the existing toolbar dropdown
 * in dist/js/folders/media-folder-filter.js remains the only filter mechanism there.
 *
 * Includes a pre_get_posts hook that handles ?roci_no_folder=1, the
 * "Unassigned Files" sentinel used by the sidebar's virtual entry.
 * Grid-view unassigned filtering is handled in filters.php via the
 * ajax_query_attachments_args filter.
 *
 * File:    inc/folders/sidebar.php
 * Version: 1.5.0
 * Updated: 2026-05-14
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// SIDEBAR — EARLY COLLAPSE STATE (admin_head inline script)
// ============================================================

/**
 * Output a tiny inline script in <head> that reads the localStorage
 * collapsed key and immediately applies 'roci-sidebar-is-collapsed' to
 * <html> — before first paint — so the sidebar renders at the saved
 * width with no visible flicker.
 */
function roci_sidebar_early_state_script() {

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	if ( 'upload' !== $screen->base && 'edit-page' !== $screen->id ) {
		return;
	}

	$key = ( 'upload' === $screen->base )
		? 'roci_sidebar_collapsed_media'
		: 'roci_sidebar_collapsed_pages';
	?>
	<script>
	( function () {
		try {
			if ( localStorage.getItem( <?php echo wp_json_encode( $key ); ?> ) === '1' ) {
				document.documentElement.classList.add( 'roci-sidebar-is-collapsed' );
			}
		} catch ( e ) {}
	} )();
	</script>
	<?php
}
add_action( 'admin_head', 'roci_sidebar_early_state_script' );


// ============================================================
// SIDEBAR — "UNASSIGNED" PRE_GET_POSTS HOOK (LIST VIEW)
// ============================================================

/**
 * Apply a NOT EXISTS tax_query when ?roci_no_folder=1 is in the request.
 *
 * Covers both upload.php (Media Library) and edit.php?post_type=page
 * (Pages list). The AJAX / grid-view counterpart lives in
 * roci_media_folder_modal_ajax_filter() in filters.php.
 *
 * @param WP_Query $query  The main admin query.
 */
function roci_pre_get_posts_no_folder( $query ) {

	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( empty( $_GET['roci_no_folder'] ) ) {
		return;
	}

	global $pagenow;

	if ( 'upload.php' === $pagenow ) {
		$query->set( 'tax_query', array(
			array(
				'taxonomy' => 'roci_media_folder',
				'operator' => 'NOT EXISTS',
			),
		) );
	} elseif ( 'edit.php' === $pagenow
		&& ! empty( $_GET['post_type'] )
		&& 'page' === sanitize_key( wp_unslash( $_GET['post_type'] ) )
	) {
		$query->set( 'tax_query', array(
			array(
				'taxonomy' => 'roci_page_folder',
				'operator' => 'NOT EXISTS',
			),
		) );
	}
}
add_action( 'pre_get_posts', 'roci_pre_get_posts_no_folder' );


// ============================================================
// SIDEBAR — RECURSIVE TREE RENDERER
// ============================================================

/**
 * Recursively render one level of the folder tree as <li> items.
 *
 * Each item emits a .roci-item-row div containing:
 *   - A chevron <button> (parents) or a gap spacer (leaf nodes)
 *   - A dashicons folder icon
 *   - An anchor linking to the filtered list view
 *
 * Children are nested as a hidden .roci-folder-children <ul>.
 * JavaScript in folders-sidebar.js handles toggle and grid-view filtering.
 *
 * @param array  $children       Map of parent_id => WP_Term[].
 * @param int    $active_term_id Currently filtered term ID (0 = none).
 * @param int    $parent_id      Term ID of the level currently being rendered.
 * @param string $folder_url_key Query var name used in filter links.
 * @param string $base_url       Base admin URL without folder query vars.
 * @param string $taxonomy       Folder taxonomy slug — passed to roci_get_folder_count().
 */
function roci_render_sidebar_tree_level( $children, $active_term_id, $parent_id, $folder_url_key, $base_url, $taxonomy ) {

	if ( empty( $children[ $parent_id ] ) ) {
		return;
	}

	foreach ( $children[ $parent_id ] as $term ) {

		$has_children = ! empty( $children[ $term->term_id ] );
		$is_active    = ( $active_term_id === $term->term_id );

		$li_class = 'roci-folder-item';
		if ( $has_children ) {
			$li_class .= ' has-children';
		}
		if ( $is_active ) {
			$li_class .= ' is-active';
		}

		$link = add_query_arg( $folder_url_key, $term->term_id, $base_url );
		?>
		<li class="<?php echo esc_attr( $li_class ); ?>" data-term="<?php echo esc_attr( $term->term_id ); ?>">
			<div class="roci-item-row">
				<?php if ( $has_children ) : ?>
				<button class="roci-chevron" type="button"
				        aria-label="<?php esc_attr_e( 'Toggle subfolders', 'rocinante' ); ?>">
					<span class="dashicons dashicons-arrow-right" aria-hidden="true"></span>
				</button>
				<?php else : ?>
				<span class="roci-chevron-gap" aria-hidden="true"></span>
				<?php endif; ?>
				<span class="dashicons dashicons-category roci-folder-icon" aria-hidden="true"></span>
				<a class="roci-folder-link" href="<?php echo esc_url( $link ); ?>">
					<?php echo esc_html( $term->name ); ?>
					<span class="roci-folder-count">(<?php echo roci_get_folder_count( $term, $taxonomy ); ?>)</span>
				</a>
			</div>
			<?php if ( $has_children ) : ?>
			<ul class="roci-folder-children" role="group">
				<?php roci_render_sidebar_tree_level( $children, $active_term_id, $term->term_id, $folder_url_key, $base_url, $taxonomy ); ?>
			</ul>
			<?php endif; ?>
		</li>
		<?php
	}
}


// ============================================================
// SIDEBAR — TREE INNER HTML (shared by page render + AJAX refresh)
// ============================================================

/**
 * Return the inner HTML for <ul class="roci-folder-tree"> as a string.
 *
 * Renders the "All Files" and "Unassigned Files" virtual entries, the
 * divider (when real folders exist), and the recursive folder tree.
 * Called by both roci_render_folders_sidebar_html() for the initial page
 * render and by the AJAX create-folder handler so JS can replace the tree
 * contents without rebuilding nodes client-side.
 *
 * @param string $taxonomy        Folder taxonomy slug.
 * @param string $folder_url_key  Query var used in filter links.
 * @param string $base_url        Base admin URL (without folder params).
 * @param int    $active_term_id  Currently active term (0 = none / All Files).
 * @param bool   $is_unassigned   Whether the "Unassigned Files" entry is active.
 * @return string
 */
function roci_get_folder_tree_html( $taxonomy, $folder_url_key, $base_url, $active_term_id = 0, $is_unassigned = false ) {

	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}

	$children = array();
	foreach ( $terms as $term ) {
		$children[ $term->parent ][] = $term;
	}

	$is_all_active  = ( ! $is_unassigned && 0 === $active_term_id );
	$unassigned_url = add_query_arg( 'roci_no_folder', '1', $base_url );

	ob_start();
	?>
	<li class="roci-folder-item roci-item-virtual<?php echo $is_all_active ? ' is-active' : ''; ?>"
	    data-term="__all__"
	    role="treeitem">
		<div class="roci-item-row">
			<span class="roci-chevron-gap" aria-hidden="true"></span>
			<a href="<?php echo esc_url( $base_url ); ?>">
				<?php esc_html_e( 'All Files', 'rocinante' ); ?>
				<span class="roci-folder-count">(<?php echo roci_get_all_count( $taxonomy ); ?>)</span>
			</a>
		</div>
	</li>

	<li class="roci-folder-item roci-item-virtual<?php echo $is_unassigned ? ' is-active' : ''; ?>"
	    data-term="__unassigned__"
	    role="treeitem">
		<div class="roci-item-row">
			<span class="roci-chevron-gap" aria-hidden="true"></span>
			<a href="<?php echo esc_url( $unassigned_url ); ?>">
				<?php esc_html_e( 'Unassigned Files', 'rocinante' ); ?>
				<span class="roci-folder-count">(<?php echo roci_get_unassigned_count( $taxonomy ); ?>)</span>
			</a>
		</div>
	</li>

	<?php if ( ! empty( $terms ) ) : ?>
	<li class="roci-sidebar-divider" role="separator" aria-hidden="true"></li>
	<?php endif; ?>

	<?php roci_render_sidebar_tree_level( $children, $active_term_id, 0, $folder_url_key, $base_url, $taxonomy ); ?>
	<?php
	return ob_get_clean();
}


// ============================================================
// SIDEBAR — MAIN RENDERER
// ============================================================

/**
 * Render the full sidebar including the virtual "All Files" and
 * "Unassigned Files" entries and the nested folder tree.
 *
 * @param string $taxonomy        Folder taxonomy slug.
 * @param string $folder_url_key  Query var name used in filter links.
 * @param string $base_url        Base admin URL (without folder params).
 */
function roci_render_folders_sidebar_html( $taxonomy, $folder_url_key, $base_url ) {

	// Resolve the active term ID from the current request.
	// roci_translate_folder_query_var() runs at admin_init priority 1 and
	// may have already converted a numeric ID to a slug, so handle both.
	$active_term_id = 0;
	$is_unassigned  = ! empty( $_GET['roci_no_folder'] );

	if ( ! $is_unassigned && ! empty( $_GET[ $folder_url_key ] ) ) {
		$val = sanitize_text_field( wp_unslash( $_GET[ $folder_url_key ] ) );
		if ( is_numeric( $val ) ) {
			$active_term_id = absint( $val );
		} else {
			$t = get_term_by( 'slug', $val, $taxonomy );
			if ( $t ) {
				$active_term_id = $t->term_id;
			}
		}
	}

	?>
	<aside id="roci-folders-sidebar"
	       class="roci-folders-sidebar"
	       data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
	       data-folder-key="<?php echo esc_attr( $folder_url_key ); ?>"
	       aria-label="<?php esc_attr_e( 'Folders', 'rocinante' ); ?>">

		<div class="roci-sidebar-header">
			<span class="roci-sidebar-header-title" aria-hidden="true">
				<?php esc_html_e( 'Folders', 'rocinante' ); ?>
			</span>
			<button type="button"
			        class="button button-small roci-sidebar-new-folder-btn"
			        id="roci-new-folder-btn"
			        aria-label="<?php esc_attr_e( 'Create new folder', 'rocinante' ); ?>">
				<?php esc_html_e( '+ New Folder', 'rocinante' ); ?>
			</button>
			<button type="button"
			        id="roci-sidebar-toggle"
			        class="roci-sidebar-toggle"
			        aria-label="<?php esc_attr_e( 'Collapse folder sidebar', 'rocinante' ); ?>"
			        aria-expanded="true">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
			</button>
		</div>

		<ul class="roci-folder-tree" role="tree">
			<?php echo roci_get_folder_tree_html( $taxonomy, $folder_url_key, $base_url, $active_term_id, $is_unassigned ); ?>
		</ul>

	</aside>
	<?php
}


// ============================================================
// SIDEBAR — SCREEN GATE + RENDER HOOK
// ============================================================

/**
 * Render the sidebar on eligible admin screens.
 *
 * Hooked to admin_footer so it lands after the modal HTML from create.php
 * and after WP's page markup. CSS positions the sidebar as a fixed overlay
 * between the admin nav and the content area.
 *
 * The media picker modal is loaded via JavaScript (no full page request)
 * so this hook never fires inside it — no additional exclusion is needed.
 */
function roci_maybe_render_sidebar() {

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	if ( 'upload' === $screen->base ) {
		// Preserve the view mode (grid vs list) so folder links don't accidentally
		// flip the user back to grid view when they're browsing in list mode.
		$base = admin_url( 'upload.php' );
		if ( ! empty( $_GET['mode'] ) ) {
			$base = add_query_arg( 'mode', sanitize_key( wp_unslash( $_GET['mode'] ) ), $base );
		}
		roci_render_folders_sidebar_html( 'roci_media_folder', 'roci_media_folder', $base );
	} elseif ( 'edit-page' === $screen->id ) {
		roci_render_folders_sidebar_html(
			'roci_page_folder',
			'roci_page_folder',
			admin_url( 'edit.php?post_type=page' )
		);
	}
}
add_action( 'admin_footer', 'roci_maybe_render_sidebar' );


// ============================================================
// SIDEBAR — ENQUEUE ASSETS
// ============================================================

/**
 * Enqueue folders-sidebar.js on the Media Library and Pages list screens.
 *
 * @param string $hook_suffix  Current admin page hook suffix (unused — screen
 *                             is detected via get_current_screen() for clarity).
 */
function roci_enqueue_sidebar_assets( $hook_suffix ) {

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	if ( 'upload' !== $screen->base && 'edit-page' !== $screen->id ) {
		return;
	}

	// Sidebar CSS is already loaded on upload.php by roci_enqueue_media_folder_js();
	// enqueue it here too so the edit.php?post_type=page screen gets it as well.
	// WordPress deduplicates by handle, so no double-load occurs on upload.php.
	wp_enqueue_style(
		'roci-admin-folders',
		get_template_directory_uri() . '/dist/css/admin-folders.css',
		array( 'wp-admin' ),
		'2.2.0'
	);

	wp_enqueue_script(
		'roci-folders-sidebar',
		get_template_directory_uri() . '/dist/js/folders/folders-sidebar.js',
		array(),
		'2.1.0',
		true
	);

	wp_localize_script( 'roci-folders-sidebar', 'rociSidebar', array(
		'screenKey' => ( 'upload' === $screen->base ) ? 'media' : 'pages',
	) );
}
add_action( 'admin_enqueue_scripts', 'roci_enqueue_sidebar_assets' );
