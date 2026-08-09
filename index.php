<?php
/**
 * Index — Final Template-Hierarchy Fallback
 *
 * WordPress's required catch-all: the template that serves any route no more
 * specific template claims first. Children inherit this unless they define
 * their own index.php.
 *
 * Structure is mirrored by page.php deliberately — same <main> wrapper, same
 * loop — so that terminating an unassigned Page's fallthrough one step earlier
 * changes no rendered output. See page.php's docblock.
 *
 * File:    index.php
 * Version: 1.0.0
 * Updated: 2026-08-09
 *
 * @package ElRocinante
 */

get_header(); ?>

<main id="main-content" class="site-main">

    <?php
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
            the_content();
        endwhile;
    endif;
    ?>

</main>

<?php get_footer(); ?>