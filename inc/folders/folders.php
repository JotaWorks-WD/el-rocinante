<?php
/**
 * Folder System — Entry Point
 *
 * Loads all folder-system sub-files in dependency order.
 * This file is the single require_once target in functions.php;
 * adding a new phase (tree-view, bulk-actions, etc.) means adding
 * one more require_once here rather than touching functions.php.
 *
 *   taxonomies.php — register roci_media_folder and roci_page_folder
 *   counts.php     — roci_get_folder_count, roci_get_unassigned_count, roci_get_all_count
 *   filters.php    — list-view dropdowns, pre_get_posts, media modal filter, JS
 *   create.php     — "+ New Folder" modal, AJAX endpoint, JS
 *   sidebar.php    — folder-tree sidebar, unassigned filter, JS enqueue
 *
 * File:    inc/folders/folders.php
 * Version: 1.5.0
 * Updated: 2026-05-15
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

require_once get_template_directory() . '/inc/folders/taxonomies.php';
require_once get_template_directory() . '/inc/folders/counts.php';
require_once get_template_directory() . '/inc/folders/filters.php';
require_once get_template_directory() . '/inc/folders/create.php';
require_once get_template_directory() . '/inc/folders/sidebar.php';
