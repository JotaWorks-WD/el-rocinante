<?php
/**
 * Loader Screen — Loading Overlay Partial
 *
 * Full-screen overlay shown on initial page load and removed once the page
 * is ready. Rendered only when the active theme declares
 * add_theme_support( 'roci-loader' ) — see el_rocinante_render_loader() in
 * functions.php, which fires this on wp_body_open at priority 5 so the
 * overlay is the first element in the body.
 *
 * MARKUP ONLY. Styling is the parent's (Build/scss/components/_loader.scss,
 * compiled into dist/css/style.css so it lands before any child sheet and
 * there is no flash of unstyled "loading" text). DISMISSAL IS THE CHILD'S —
 * the parent ships no front-end JavaScript. #loader is the agreed hook; a
 * child toggles .is-dismissed on it and then hides it.
 *
 * ⚠ THE LOGO IS THE SITE IDENTITY custom_logo, NOT loader_logo. Earlier
 * revisions read get_theme_mod( 'loader_logo' ), which NO control anywhere
 * registers — so the value was always empty and the partial rendered a
 * broken <img src="">. The logo is now resolved from the real Site Logo and
 * the <img> is omitted entirely when none is set, leaving a spinner-only
 * overlay rather than a broken image.
 *
 * File:    template-parts/loader.php
 * Version: 1.2.0
 * Updated: 2026-09-23
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$roci_loader_logo_id  = (int) get_theme_mod( 'custom_logo', 0 );
$roci_loader_logo_url = '';

if ( $roci_loader_logo_id ) {
	$roci_loader_logo_url = wp_get_attachment_image_url( $roci_loader_logo_id, 'full' );

	// SVG logos resolve unreliably through the image-size pipeline because
	// they carry no dimension metadata; the bare attachment URL is the
	// fallback. Same two-step the schema logo uses.
	if ( ! $roci_loader_logo_url ) {
		$roci_loader_logo_url = wp_get_attachment_url( $roci_loader_logo_id );
	}
}

// WIDTH/HEIGHT RESERVE THE LOGO'S BOX, which stops the spinner being pushed
// down when the logo lands. .roci-loader__logo (components/_loader.scss) sets
// max-width and height:auto but no CSS width, so the width attribute is the
// used width (capped by max-width) and the height follows its ratio even
// before the file has loaded. jw_logo_dimensions() reads raster metadata or,
// for an SVG, the file's own width/height or viewBox. Empty on failure, in
// which case no width/height is printed rather than a wrong one.
$roci_loader_logo_dims = $roci_loader_logo_id ? jw_logo_dimensions( $roci_loader_logo_id ) : array();
$roci_loader_logo_size = $roci_loader_logo_dims
	? ' width="' . (int) $roci_loader_logo_dims[0] . '" height="' . (int) $roci_loader_logo_dims[1] . '"'
	: '';

// ABOVE THE FOLD BY DEFINITION: it is the only image visible until the loader
// is dismissed. loading="eager" plus data-no-lazy="1" keeps plugin lazy-loaders
// (LiteSpeed et al.) from swapping it for a placeholder. No fetchpriority —
// the page's hero image keeps the high-priority slot.
?>
<div id="loader" class="roci-loader" role="status" aria-live="polite">

	<?php if ( $roci_loader_logo_url ) : ?>
		<img
			class="roci-loader__logo"
			src="<?php echo esc_url( $roci_loader_logo_url ); ?>"
			alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"<?php echo $roci_loader_logo_size; ?> data-no-lazy="1" loading="eager" decoding="async"
		>
	<?php endif; ?>

	<div class="roci-loader__spinner" aria-hidden="true"></div>

	<span class="screen-reader-text"><?php esc_html_e( 'Loading…', 'rocinante' ); ?></span>

</div>
