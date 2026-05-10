<?php
/**
 * Theme Settings — Loader
 *
 * Requires all theme-settings sub-files in dependency order.
 * Replace inc/theme-settings.php with this file in functions.php.
 *
 * File:    inc/theme-settings/settings-loader.php
 * Version: 1.1.1
 * Updated: 2026-05-10
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/settings-sanitize.php';
require_once __DIR__ . '/settings-register.php';
require_once __DIR__ . '/settings-page.php';
require_once __DIR__ . '/settings-ajax.php';
