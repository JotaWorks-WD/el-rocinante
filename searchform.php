<?php
/**
 * Search Form — get_search_form() Template
 *
 * WordPress picks this file up automatically for every get_search_form()
 * call, so it is the single source for the search markup across the parent
 * and every child. Before it lived here, the parent's own search template fell
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
 * Version: 1.2.0
 * Updated: 2026-10-09
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

/**
 * Classes on the form, the input and the button (v1.2.0).
 *
 * The defaults are the classes this form has always carried, so output is
 * unchanged when nothing hooks the filter. A child whose design system uses
 * different names returns its own instead of forking the template:
 *
 *   add_filter( 'roci_search_form_classes', function ( $classes ) {
 *       $classes['input']  = array( 'site-search__input' );
 *       $classes['button'] = array( 'site-search__submit' );
 *       return $classes;
 *   } );
 *
 * A key the filter drops gets its default back; every class is passed
 * through sanitize_html_class().
 */
$roci_search_class_defaults = array(
	'form'   => array( 'search-form' ),
	'input'  => array( 'form__input', 'search-form__input' ),
	'button' => array( 'btn', 'btn--secondary', 'search-form__submit' ),
);
$roci_search_classes = roci_resolve_class_slots(
	apply_filters( 'roci_search_form_classes', $roci_search_class_defaults ),
	$roci_search_class_defaults
);
?>

<form role="search" method="get" class="<?php echo roci_class_attr( $roci_search_classes['form'] ); ?>" action="<?php echo esc_url( home_url( '/' ) ); ?>">

	<label class="screen-reader-text" for="<?php echo esc_attr( $roci_search_id ); ?>">
		<?php esc_html_e( 'Search this site', 'rocinante' ); ?>
	</label>

	<input
		type="search"
		id="<?php echo esc_attr( $roci_search_id ); ?>"
		class="<?php echo roci_class_attr( $roci_search_classes['input'] ); ?>"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php echo esc_attr( $roci_search_placeholder ); ?>"
	>

	<button type="submit" class="<?php echo roci_class_attr( $roci_search_classes['button'] ); ?>">
		<?php esc_html_e( 'Search', 'rocinante' ); ?>
	</button>

</form>
