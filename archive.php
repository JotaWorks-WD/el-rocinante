<?php
/**
 * Archive — Archive Page Template
 *
 * Displays posts for category, tag, author, date, taxonomy and post-type
 * archives.
 *
 * Built from overridable parts (v1.2.0):
 *   template-parts/listing-header.php — the <h1> (the full title chain:
 *     category, tag, author, date by granularity, taxonomy term, post-type,
 *     the_archive_title() as the last resort) and the archive description.
 *   template-parts/content-archive.php — one item per post, with a "Read More"
 *     whose screen-reader-only " about {title}" makes its accessible name
 *     unique out of context (WCAG 2.4.4).
 *   template-parts/content-none.php — the empty state.
 * Seams: roci_listing_classes, roci_pagination_args, roci_after_loop.
 *
 * File:    archive.php
 * Version: 1.2.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

$roci_classes = roci_get_listing_classes( 'archive' );

get_header(); ?>

<main id="main-content" class="site-main">
    <div class="<?php echo roci_class_attr( $roci_classes['wrapper'] ); ?>">

        <?php get_template_part( 'template-parts/listing-header', null, array( 'context' => 'archive' ) ); ?>

        <?php if ( have_posts() ) : ?>
            <div class="<?php echo roci_class_attr( $roci_classes['results'] ); ?>">
                <?php
                while ( have_posts() ) :
                    the_post();
                    get_template_part( 'template-parts/content', 'archive' );
                endwhile;
                ?>
            </div>

            <?php the_posts_pagination( roci_get_pagination_args( 'archive' ) ); ?>

        <?php else : ?>
            <?php get_template_part( 'template-parts/content', 'none', array( 'context' => 'archive' ) ); ?>
        <?php endif; ?>

        <?php do_action( 'roci_after_loop', 'archive' ); ?>

    </div>
</main>

<?php get_footer(); ?>
