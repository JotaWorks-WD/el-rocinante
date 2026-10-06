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
 * `.entry-content` is the prose contract: the parent's base typography restores list markers and link underlines inside it.
 *
 * File:    index.php
 * Version: 1.1.0
 * Updated: 2026-10-06
 *
 * @package ElRocinante
 */

get_header(); ?>

<main id="main-content" class="site-main">

    <?php
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
            ?>
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
            <?php
        endwhile;
    endif;
    ?>

</main>

<?php get_footer(); ?>