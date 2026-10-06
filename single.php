<?php
/**
 * Single — Single Post Template
 *
 * Serves a single post of any post type that ships no more specific template.
 * Children inherit this unless they define their own single.php.
 *
 * `.entry-content` is the prose contract: the parent's base typography restores list markers and link underlines inside it.
 *
 * File:    single.php
 * Version: 1.1.0
 * Updated: 2026-10-06
 *
 * @package ElRocinante
 */

get_header(); ?>

<main id="main-content" class="site-main">
    <?php while ( have_posts() ) : the_post(); ?>
        <div class="entry-content">
            <?php the_content(); ?>
        </div>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>