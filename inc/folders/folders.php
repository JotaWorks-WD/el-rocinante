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
 *   filters.php    — list-view dropdowns, pre_get_posts, media modal filter, JS
 *   create.php     — "+ New Folder" modal, AJAX endpoint, JS
 *
 * File:    inc/folders/folders.php
 * Version: 1.2.0
 * Updated: 2026-05-13
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/folders/taxonomies.php';
require_once get_template_directory() . '/inc/folders/filters.php';
require_once get_template_directory() . '/inc/folders/create.php';
