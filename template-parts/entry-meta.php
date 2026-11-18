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
 * THE UPDATED DATE (v1.1.0, El Rocinante 8.4.0): when the post's "Updated
 * date" box is ticked, a decorative separator and roci_updated_date_line()
 * follow the published date — "Updated {date}", then the "What changed" note
 * when it is filled. Unticked, the markup is exactly as before.
 *
 * OVERRIDE THIS PART to change the line (author, reading time —
 * roci_reading_time() gives the minutes). An override that wants the updated
 * date calls roci_updated_date_line() itself. Never fork single.php for it.
 *
 * File:    template-parts/entry-meta.php
 * Version: 1.1.0
 * Updated: 2026-11-18
 *
 * @package ElRocinante
 */

if ( 'post' !== get_post_type() ) {
    return;
}
?>
<div class="entry-meta">
    <time class="entry-date published" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
<?php if ( roci_post_updated()['show'] ) : ?>
    <span class="entry-meta-sep" aria-hidden="true">·</span>
    <?php roci_updated_date_line(); ?>
<?php endif; ?>
</div>
