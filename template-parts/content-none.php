<?php
/**
 * Content None — the empty state for 404 and every no-results branch
 *
 * Loaded by get_template_part( 'template-parts/content', 'none',
 * array( 'context' => … ) ) from 404.php and from the no-results branch of
 * index.php, archive.php and search.php. Holds the context's copy, the
 * search form and a link home.
 *
 * search context: no search form here. search.php already prints one above
 * the results on every search, so a second would duplicate it.
 *
 * Override this part, not the four templates, to change the empty state.
 *
 * File:    template-parts/content-none.php
 * Version: 1.0.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

$roci_context = roci_listing_context( isset( $args['context'] ) ? $args['context'] : '' );

$roci_copy = array(
    '404'     => __( 'The page you are looking for does not exist or has been moved.', 'rocinante' ),
    'search'  => __( 'No results found. Try a different search.', 'rocinante' ),
    'archive' => __( 'No posts found.', 'rocinante' ),
    'index'   => __( 'No posts found.', 'rocinante' ),
);
?>
<div class="no-results">
    <p><?php echo esc_html( $roci_copy[ $roci_context ] ); ?></p>
    <?php if ( 'search' !== $roci_context ) : ?>
        <?php get_search_form(); ?>
    <?php endif; ?>
    <p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <?php esc_html_e( 'Back to Home', 'rocinante' ); ?>
        </a>
    </p>
</div>
