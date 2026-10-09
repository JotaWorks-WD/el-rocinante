<?php
/**
 * 404 — Not Found Template
 *
 * Fallback template for requests that resolve to no post. Children inherit this
 * unless they define their own 404.php.
 *
 * Built from overridable parts (v1.1.0):
 *   template-parts/listing-header.php — the <h1>, under
 *     roci_entry_title_mode( '404' ).
 *   template-parts/content-none.php — the copy, the search form and the link
 *     home, inside the 'results' container.
 * Seams: roci_listing_classes and roci_after_loop (after the content).
 *
 * File:    404.php
 * Version: 1.1.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

$roci_classes = roci_get_listing_classes( '404' );

get_header(); ?>

<main id="main-content" class="site-main">
    <div class="<?php echo roci_class_attr( $roci_classes['wrapper'] ); ?>">

        <?php get_template_part( 'template-parts/listing-header', null, array( 'context' => '404' ) ); ?>

        <div class="<?php echo roci_class_attr( $roci_classes['results'] ); ?>">
            <?php get_template_part( 'template-parts/content', 'none', array( 'context' => '404' ) ); ?>
        </div>

        <?php do_action( 'roci_after_loop', '404' ); ?>

    </div>
</main>

<?php get_footer(); ?>
