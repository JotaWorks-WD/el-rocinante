<?php
/**
 * Content — archive listing item
 *
 * The loop item for archive.php — category, tag, author, date, taxonomy and post-type archives.
 * Loaded by get_template_part( 'template-parts/content', 'archive' ).
 *
 * The parent's item markup lives in template-parts/content.php, which this
 * file loads, so all three contexts share one item until a child overrides
 * one. Override THIS file to change only the archive route's item; override
 * content.php to change every context at once.
 *
 * File:    template-parts/content-archive.php
 * Version: 1.0.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

get_template_part( 'template-parts/content' );
