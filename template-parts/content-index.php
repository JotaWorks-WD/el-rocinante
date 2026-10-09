<?php
/**
 * Content — index listing item
 *
 * The loop item for the posts index (index.php, listing branch). Since
 * v7.1.0 the posts index lists excerpts, not full the_content().
 * Loaded by get_template_part( 'template-parts/content', 'index' ).
 *
 * The parent's item markup lives in template-parts/content.php, which this
 * file loads, so all three contexts share one item until a child overrides
 * one. Override THIS file to change only the index route's item; override
 * content.php to change every context at once.
 *
 * File:    template-parts/content-index.php
 * Version: 1.0.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

get_template_part( 'template-parts/content' );
