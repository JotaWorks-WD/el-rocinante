<?php
/**
 * Metabox Field Loaders
 *
 * Loads all Meta Box field definition files for El Rocinante.
 * Add new field files here as post types and components are registered.
 *
 * File:    inc/metabox-fields.php
 * Version: 1.1.0
 * Updated: 2026-05-07
 *
 * @package ElRocinante
 */


// ============================================================
// METABOX FIELD LOADERS
// ============================================================

require_once get_template_directory() . '/inc/metabox/metabox-seo-fields.php';
require_once get_template_directory() . '/inc/metabox/metabox-seo-preview.php';
require_once get_template_directory() . '/inc/metabox/metabox-seo-health.php';
require_once get_template_directory() . '/inc/metabox/metabox-schema-fields.php';
require_once get_template_directory() . '/inc/metabox/metabox-faq-fields.php';