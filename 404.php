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
 * Seams: roci_listing_classes (incl. the v1.2.0 inner slot; results_tag is
 * ignored here — the results container holds the empty state), roci_after_loop
 * (after the content), roci_empty_state_copy, and — in the header part —
 * roci_listing_title and roci_listing_header_classes.
 *
 * File:    404.php
 * Version: 1.2.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

$roci_classes = roci_get_listing_classes( '404' );

get_header(); ?>

<main id="main-content" class="site-main">
    <div class="<?php echo roci_class_attr( $roci_classes['wrapper'] ); ?>">

        <?php roci_listing_inner_open( $roci_classes ); get_template_part( 'template-parts/listing-header', null, array( 'context' => '404' ) ); ?>

        <<?php echo tag_escape( $roci_classes['results_tag'] ); ?><?php echo roci_listing_results_atts( $roci_classes ); ?>>
            <?php get_template_part( 'template-parts/content', 'none', array( 'context' => '404' ) ); ?>
        </<?php echo tag_escape( $roci_classes['results_tag'] ); ?>>

        <?php do_action( 'roci_after_loop', '404' ); roci_listing_inner_close( $roci_classes ); ?>

    </div>
</main>

<?php get_footer(); ?>
