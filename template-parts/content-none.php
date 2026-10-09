<?php
/**
 * Content None — the empty state for 404 and every no-results branch
 *
 * Loaded by get_template_part( 'template-parts/content', 'none',
 * array( 'context' => … ) ) from 404.php and from the no-results branch of
 * index.php, archive.php and search.php. Holds the context's copy, the
 * search form and a link home.
 *
 * search context: no search form here while search.php prints one above the
 * results — a second would duplicate it. When roci_listing_search_form
 * returns false (v1.1.0), search.php drops its top form and this part prints
 * it instead, so a search page always has exactly one.
 *
 * roci_empty_state_copy( null, $context ) (v1.1.0): a string replaces the
 * copy paragraph's text, through wp_kses_post(); null keeps the per-context
 * copy below. The form, the home link and the markup are unchanged either
 * way — a copy-only change needs no override of this part.
 *
 * Override this part, not the four templates, to change the empty state.
 *
 * File:    template-parts/content-none.php
 * Version: 1.1.0
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

$roci_custom_copy = apply_filters( 'roci_empty_state_copy', null, $roci_context );
?>
<div class="no-results">
    <p><?php echo is_string( $roci_custom_copy ) ? wp_kses_post( $roci_custom_copy ) : esc_html( $roci_copy[ $roci_context ] ); ?></p>
    <?php if ( 'search' !== $roci_context || ! roci_listing_shows_search_form( 'search' ) ) : ?>
        <?php get_search_form(); ?>
    <?php endif; ?>
    <p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <?php esc_html_e( 'Back to Home', 'rocinante' ); ?>
        </a>
    </p>
</div>
