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
 * File:    single.php
 * Version: 1.3.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

get_header(); ?>

<main id="main-content" class="site-main">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <?php $roci_title_mode = roci_entry_title_mode( 'single', get_the_ID() ); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <?php if ( 'none' !== $roci_title_mode ) : ?>
                    <header class="entry-header">
                        <h1 class="entry-title<?php echo ( 'hidden' === $roci_title_mode ) ? ' screen-reader-text' : ''; ?>"><?php the_title(); ?></h1>
                    </header>
                <?php endif; ?>
                <div class="entry-content">
                    <?php the_content(); ?>
                    <?php wp_link_pages(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
