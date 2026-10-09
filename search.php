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
 *   template-parts/content-none.php — the empty state. No second search form
 *     there while the one below the header shows; roci_listing_search_form
 *     (v1.3.0) returning false drops the top form, and the empty state then
 *     prints it instead.
 * Seams: roci_listing_classes (incl. the v1.3.0 inner / pagination /
 * results_tag slots), roci_pagination_args, roci_after_loop,
 * roci_listing_search_form, roci_search_form_classes on the form itself, and
 * — in the header part — roci_listing_title and roci_listing_header_classes.
 *
 * File:    search.php
 * Version: 1.3.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

$roci_classes = roci_get_listing_classes( 'search' );

get_header(); ?>

<main id="main-content" class="site-main">
    <div class="<?php echo roci_class_attr( $roci_classes['wrapper'] ); ?>">

        <?php roci_listing_inner_open( $roci_classes ); get_template_part( 'template-parts/listing-header', null, array( 'context' => 'search' ) ); ?>

        <?php if ( roci_listing_shows_search_form( 'search' ) ) { get_search_form(); } ?>

        <?php if ( have_posts() ) : ?>
            <<?php echo tag_escape( $roci_classes['results_tag'] ); ?><?php echo roci_listing_results_atts( $roci_classes ); ?>>
                <?php
                while ( have_posts() ) :
                    the_post();
                    get_template_part( 'template-parts/content', 'search' );
                endwhile;
                ?>
            </<?php echo tag_escape( $roci_classes['results_tag'] ); ?>>

            <?php roci_listing_pagination( 'search', $roci_classes ); ?>

        <?php else : ?>
            <?php get_template_part( 'template-parts/content', 'none', array( 'context' => 'search' ) ); ?>
        <?php endif; ?>

        <?php do_action( 'roci_after_loop', 'search' ); roci_listing_inner_close( $roci_classes ); ?>

    </div>
</main>

<?php get_footer(); ?>
