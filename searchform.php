<?php
/**
 * Search Form — get_search_form() Template
 *
 * WordPress picks this file up automatically for every get_search_form()
 * call, so it is the single source for the search markup across the parent
 * and every child. Before it lived here, the parent's own search.php:30 fell
 * through to core's default markup, which carries none of the theme's
 * classes — promoted from a child theme (v1.0.0) so every child inherits one
 * form instead of each shipping its own.
 *
 * ⚠ THE MARKUP IS HERE; THE STYLING IS NOT, AND THAT IS A REAL SPLIT.
 * The classes below — .form__input, .btn, .btn--secondary — are named for
 * the kits children build, NOT for anything the parent ships. The parent's
 * stylesheet has no form styling and no button component by design (see
 * CLAUDE.md 8), so on a child with no .form__* or .btn kit this renders as
 * semantically correct, unstyled markup. That is still an improvement on
 * core's default form, which is unstyled AND unclassed, but do not read
 * these class names as a promise the parent keeps.
 *
 * .screen-reader-text IS the parent's (base/_utilities.scss, v6.16.0), so
 * the label is correctly hidden everywhere with no child CSS at all.
 *
 * File:    searchform.php
 * Version: 1.1.0
 * Updated: 2026-09-11
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Unique per render: the 404 page and a no-results page each show this
// form once, but a duplicate id would break the label association if a
// template ever renders two.
$roci_search_id = wp_unique_id( 'roci-search-' );

/**
 * Placeholder text for the search input.
 *
 * ⚠ THE DEFAULT IS DELIBERATELY GENERIC, AND MUST STAY THAT WAY. The child
 * this was promoted from named its own product types here, which cannot live
 * in the parent (CLAUDE.md 12.4: zero content-specific identifiers, no
 * business verticals in code OR comments, and a pre-commit grep that expects
 * no matches — a placeholder naming a vertical is exactly what that grep is
 * for). This filter is how a child gets its own wording back without forking
 * the template:
 *
 *   add_filter( 'roci_search_placeholder', function () {
 *       return __( 'Search the catalogue…', 'childtheme' );
 *   } );
 */
$roci_search_placeholder = apply_filters(
	'roci_search_placeholder',
	__( 'Search this site…', 'rocinante' )
);
?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">

	<label class="screen-reader-text" for="<?php echo esc_attr( $roci_search_id ); ?>">
		<?php esc_html_e( 'Search this site', 'rocinante' ); ?>
	</label>

	<input
		type="search"
		id="<?php echo esc_attr( $roci_search_id ); ?>"
		class="form__input search-form__input"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php echo esc_attr( $roci_search_placeholder ); ?>"
	>

	<button type="submit" class="btn btn--secondary search-form__submit">
		<?php esc_html_e( 'Search', 'rocinante' ); ?>
	</button>

</form>
