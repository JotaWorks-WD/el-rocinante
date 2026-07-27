<?php
/**
 * Folder System — Admin Brand Accent Bridge
 *
 * Bridges the site's brand accent into the Fauxlders admin UI.
 *
 * WHY THIS FILE EXISTS. --folders-highlight is declared at
 * Build/scss/admin/_folder-tokens.scss:19 as #{$color-primary}, compiling to
 * the neutral placeholder #000, and is read by 33 rules across the admin
 * sheet. The promoted --color-* layer a child actually fills is emitted by
 * Build/scss/base/_tokens.scss into dist/css/style.css, which is enqueued on
 * wp_enqueue_scripts and is therefore absent from every admin screen. A
 * child's brand cannot reach Fauxlders through the cascade — the two
 * stylesheets never appear on the same page. PHP is the only channel that
 * crosses that boundary, and this file is it.
 *
 * The SCSS is deliberately left alone: the compiled #000 stays as the
 * fallback, and this override lands on top of it at runtime.
 *
 * File:    inc/folders/branding.php
 * Version: 1.0.1
 * Updated: 2026-07-27
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// ============================================================
// ADMIN BRAND ACCENT — inline --folders-highlight override
// ============================================================

/**
 * Append a :root block overriding --folders-highlight to the admin sheet.
 *
 * SELF-GATING. The 'roci-admin-folders' handle is registered only by the two
 * enqueue call sites that already gate themselves —
 * roci_enqueue_media_folder_js() (filters.php:417, gated on
 * wp_script_is( 'media-views', 'enqueued' ), i.e. any screen where the
 * wp.media modal can be opened) and roci_enqueue_sidebar_assets()
 * (sidebar.php:435, gated on get_current_screen() against the folder
 * registry). Testing the handle with wp_style_is() inherits both gates for
 * free, so this emits on exactly the screens where Fauxlders renders and
 * nowhere else. Do not add a third copy of that detection logic here — it
 * would drift from the two that already exist.
 *
 * PRIORITY 20. wp_add_inline_style() silently returns false if the handle is
 * not yet registered, so this must run after both enqueue callbacks —
 * roci_enqueue_sidebar_assets() at the default priority 10 and
 * roci_enqueue_media_folder_js() at 15. Anything that raises either of those
 * to 20 or beyond must raise this one with it.
 *
 * CASCADE. wp_add_inline_style() prints its CSS immediately after the
 * stylesheet it attaches to. This :root block and the compiled one in
 * admin-folders.css therefore carry identical specificity, and this one wins
 * on source order alone — no !important is needed, and none is used. If the
 * accent is ever empty this emits nothing and the compiled #000 stands.
 *
 * ESCAPING. The value is escaped at this output site rather than sanitised in
 * the helper, so that a filter returning any valid CSS colour keeps working.
 * esc_attr() neutralises the characters that could break out of the <style>
 * element WordPress wraps this in.
 */
function roci_enqueue_admin_brand_accent() {

	if ( ! wp_style_is( 'roci-admin-folders', 'enqueued' ) ) {
		return;
	}

	$accent = roci_admin_brand_accent();

	if ( ! $accent ) {
		return;
	}

	// esc_attr(), NOT sanitize_hex_color(). sanitize_hex_color() returns null
	// for anything that is not a bare hex, so a filter legitimately returning
	// rgb(), a named colour or a var() would blank the declaration and take the
	// highlight with it. esc_attr() still neutralises the < and " needed to
	// break out of the <style> wrapper, while permitting any valid CSS colour.
	wp_add_inline_style(
		'roci-admin-folders',
		':root{--folders-highlight:' . esc_attr( $accent ) . ';}'
	);
}
add_action( 'admin_enqueue_scripts', 'roci_enqueue_admin_brand_accent', 20 );
