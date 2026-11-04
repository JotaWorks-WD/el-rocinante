<?php
/**
 * Single — Single Post Template
 *
 * Serves a single post of any post type that ships no more specific template.
 * Children inherit this unless they define their own single.php.
 *
 * `.entry-content` is the prose contract: the parent's base typography restores list markers and link underlines inside it.
 *
 * The entry title is an <h1> (index: one listing <h1>, per-post <h2>); a child controls it with the roci_entry_title_mode filter ('visible' | 'hidden' | 'none').
 *
 * v1.3.0: the entry is an <article post_class()>, the loop sits behind an
 * if ( have_posts() ) guard, and wp_link_pages() follows the_content() so a
 * post split with <!--nextpage--> reaches its later pages.
 *
 * v1.4.0 (El Rocinante 8.3.0) — SEAMS, so a child changes a single post
 * through parts and actions and never forks this template:
 *   do_action( 'roci_before_entry_title', 'single', $post_id ) — inside the
 *     entry header, immediately before the <h1>: the eyebrow slot. Does not
 *     fire when the title mode is 'none'.
 *   template-parts/entry-meta.php — the meta line, loaded with
 *     array( 'context' => 'single' ) right after the <h1> (inside the header;
 *     on its own when the title mode is 'none'). The parent's part prints the
 *     published date on a `post` only. Override the part to change it.
 *   do_action( 'roci_before_entry_content', 'single', $post_id ) — after the
 *     entry header, before .entry-content: a featured image, a summary box.
 *   do_action( 'roci_after_entry_content', 'single', $post_id ) — after
 *     .entry-content (and wp_link_pages()), still inside the <article>: an
 *     end-of-post call to action, previous / next links.
 * With nothing hooked and the part not overridden, the only change from 1.3.0
 * is the meta line on a `post`.
 *
 * File:    single.php
 * Version: 1.4.0
 * Updated: 2026-11-04
 *
 * @package ElRocinante
 */

get_header(); ?>

<main id="main-content" class="site-main">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <?php
            $roci_entry_id   = get_the_ID();
            $roci_title_mode = roci_entry_title_mode( 'single', $roci_entry_id );
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <?php if ( 'none' !== $roci_title_mode ) : ?>
                    <header class="entry-header">
                        <?php do_action( 'roci_before_entry_title', 'single', $roci_entry_id ); ?>
                        <h1 class="entry-title<?php echo ( 'hidden' === $roci_title_mode ) ? ' screen-reader-text' : ''; ?>"><?php the_title(); ?></h1>
                        <?php get_template_part( 'template-parts/entry-meta', null, array( 'context' => 'single' ) ); ?>
                    </header>
                <?php else : ?>
                    <?php get_template_part( 'template-parts/entry-meta', null, array( 'context' => 'single' ) ); ?>
                <?php endif; ?>
                <?php do_action( 'roci_before_entry_content', 'single', $roci_entry_id ); ?>
                <div class="entry-content">
                    <?php the_content(); ?>
                    <?php wp_link_pages(); ?>
                </div>
                <?php do_action( 'roci_after_entry_content', 'single', $roci_entry_id ); ?>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
