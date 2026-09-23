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
 * MARKUP, PLUS A ZERO-SPECIFICITY COPY OF ITS CRITICAL CSS (v1.3.0). Styling
 * is the parent's (Build/scss/components/_loader.scss, compiled into
 * dist/css/style.css so it lands before any child sheet and there is no flash
 * of unstyled "loading" text). The same rules are also inlined just above the
 * markup, wrapped in :where(), for the case where that sheet arrives after
 * first paint — see the note beside the <style> block. DISMISSAL IS THE CHILD'S —
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
 * Version: 1.3.0
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

// CRITICAL LOADER CSS, INLINED (v1.3.0). Under LiteSpeed Guest Optimization the
// external stylesheet can land after first paint. The logo carries its real
// width/height (2508 x 2100 on some sites), so until max-width arrived it
// painted at intrinsic size and then snapped: Lighthouse mobile blamed CLS on
// .roci-loader__logo. The block below is the compiled loader rules from
// dist/css/style.css with IDENTICAL values, so the first paint already matches.
//
// ⚠ EVERY SELECTOR IS WRAPPED IN :where(), WHICH IS WHAT MAKES THIS SAFE. This
// style sits in the body, after the head stylesheets, so at equal specificity
// it would beat them — including any child theme's loader override. :where()
// has zero specificity, so it only fills the gap before the external sheet
// loads; the external rules (same values) and any override then win. A browser
// without :where() drops these rules and falls back to today's behaviour.
//
// ⚠ :where(html){font-size:62.5%} IS DELIBERATE, NOT SCOPE CREEP. The loader
// sizes are rem, and the 10px root also comes from the external sheet. Without
// it, 18rem is 288px on first paint and 180px once the sheet lands, which is
// still a shift. It is the parent's own root value, at zero specificity.
//
// ⚠ A HAND-COPIED DUPLICATE OF components/_loader.scss (plus base/_base.scss's
// root size). The SCSS is still the source and still ships in the external
// sheet. Change a value there and it must change here too — nothing warns.
?>
<style id="roci-loader-critical">:where(html){font-size:62.5%}:where(#loader.roci-loader){position:fixed;inset:0;z-index:var(--z-tooltip,700);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2rem;background-color:var(--color-surface,#fff);opacity:1;transition:opacity .4s ease}:where(#loader.roci-loader[hidden]){display:none}:where(#loader.roci-loader.is-dismissed){opacity:0;pointer-events:none}:where(.roci-loader__logo){max-width:18rem;height:auto}:where(.roci-loader__spinner){width:4rem;height:4rem;border:3px solid var(--color-border,#e5e5e5);border-top-color:var(--color-action,#333);border-radius:50%;animation:roci-loader-spin .8s linear infinite}@keyframes roci-loader-spin{to{transform:rotate(360deg)}}@media (prefers-reduced-motion:reduce){:where(#loader.roci-loader){transition:none}:where(.roci-loader__spinner){animation:none}}</style>
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
