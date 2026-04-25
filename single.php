<?php get_header(); ?>

<main id="main-content" class="site-main">
    <?php while ( have_posts() ) : the_post(); ?>
        <?php the_content(); ?>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>