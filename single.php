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
 * File:    single.php
 * Version: 1.2.0
 * Updated: 2026-10-06
 *
 * @package ElRocinante
 */

get_header(); ?>

<main id="main-content" class="site-main">
    <?php while ( have_posts() ) : the_post(); ?>
        <?php $roci_title_mode = roci_entry_title_mode( 'single', get_the_ID() ); ?>
        <?php if ( 'none' !== $roci_title_mode ) : ?>
            <header class="entry-header">
                <h1 class="entry-title<?php echo ( 'hidden' === $roci_title_mode ) ? ' screen-reader-text' : ''; ?>"><?php the_title(); ?></h1>
            </header>
        <?php endif; ?>
        <div class="entry-content">
            <?php the_content(); ?>
        </div>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>