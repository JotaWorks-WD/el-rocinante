<?php
/**
 * Search — Search Results Template
 *
 * Displays results for the site search query. Children inherit this unless they
 * define their own search.php.
 *
 * Built from overridable parts (v1.2.0):
 *   template-parts/listing-header.php — the "Search Results for: …" <h1>.
 *   template-parts/content-search.php — one item per result, with a "Read More"
 *     whose screen-reader-only " about {title}" makes its accessible name
 *     unique out of context (WCAG 2.4.4).
 *   template-parts/content-none.php — the empty state (no second search form;
 *     the one below the header serves both branches).
 * Seams: roci_listing_classes, roci_pagination_args, roci_after_loop, and
 * roci_search_form_classes on the form itself.
 *
 * File:    search.php
 * Version: 1.2.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

$roci_classes = roci_get_listing_classes( 'search' );

get_header(); ?>

<main id="main-content" class="site-main">
    <div class="<?php echo roci_class_attr( $roci_classes['wrapper'] ); ?>">

        <?php get_template_part( 'template-parts/listing-header', null, array( 'context' => 'search' ) ); ?>

        <?php get_search_form(); ?>

        <?php if ( have_posts() ) : ?>
            <div class="<?php echo roci_class_attr( $roci_classes['results'] ); ?>">
                <?php
                while ( have_posts() ) :
                    the_post();
                    get_template_part( 'template-parts/content', 'search' );
                endwhile;
                ?>
            </div>

            <?php the_posts_pagination( roci_get_pagination_args( 'search' ) ); ?>

        <?php else : ?>
            <?php get_template_part( 'template-parts/content', 'none', array( 'context' => 'search' ) ); ?>
        <?php endif; ?>

        <?php do_action( 'roci_after_loop', 'search' ); ?>

    </div>
</main>

<?php get_footer(); ?>
