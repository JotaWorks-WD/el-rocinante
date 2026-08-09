<?php
/**
 * Single — Single Post Template
 *
 * Serves a single post of any post type that ships no more specific template.
 * Children inherit this unless they define their own single.php.
 *
 * File:    single.php
 * Version: 1.0.0
 * Updated: 2026-08-09
 *
 * @package ElRocinante
 */

get_header(); ?>

<main id="main-content" class="site-main">
    <?php while ( have_posts() ) : the_post(); ?>
        <?php the_content(); ?>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>