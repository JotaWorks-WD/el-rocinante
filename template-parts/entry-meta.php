<?php
/**
 * Entry Meta — the meta line under a single entry's <h1>
 *
 * Loaded by single.php (v1.4.0) with
 * get_template_part( 'template-parts/entry-meta', null, array( 'context' => 'single' ) ),
 * inside the entry header right after the <h1> (on its own when the title
 * mode is 'none').
 *
 * THE MINIMAL DEFAULT: on a `post` only, the published date as a <time>. Any
 * other post type that falls through to single.php gets nothing, exactly as
 * before 8.3.0. No author, no categories and no links: a category or author
 * link would point at an archive the parent 404s by default
 * (inc/archive-suppression.php).
 *
 * OVERRIDE THIS PART to change the line (author, updated date, reading time —
 * roci_reading_time() gives the minutes). Never fork single.php for it.
 *
 * File:    template-parts/entry-meta.php
 * Version: 1.0.0
 * Updated: 2026-11-04
 *
 * @package ElRocinante
 */

if ( 'post' !== get_post_type() ) {
    return;
}
?>
<div class="entry-meta">
    <time class="entry-date published" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
</div>
