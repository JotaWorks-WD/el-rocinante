<?php
/**
 * Admin — "Brand Colors" Admin Colour Scheme
 *
 * Registers a WordPress admin colour scheme that recolours only the
 * brand-bearing admin surfaces — links, primary buttons, form focus, the
 * current admin-menu item, admin-bar hover — and leaves WP's neutral chrome
 * alone. Users opt in per-account at Users → Profile → Admin Color Scheme.
 *
 * TWO PHP ARRIVAL POINTS, AND THEY ARE NOT INTERCHANGEABLE.
 *
 *   1. LITERAL HEX AT REGISTRATION. wp_admin_css_color()'s $colors (the
 *      profile-page preview swatches) and $icon_colors (consumed by
 *      wp_color_scheme_settings() and emitted as the _wpColorScheme JS object)
 *      are PHP arrays, never CSS. A custom property cannot reach either, so the
 *      brand value is baked in as literal hex here.
 *
 *   2. A :root CUSTOM PROPERTY. The scheme stylesheet reads
 *      var(--roci-brand-accent), so one compiled file serves every child with
 *      its own brand and no recompile. That property is injected below.
 *
 * WHY A SCHEME AND NOT AN OVERLAY. WordPress resolves a single 'colors' style
 * handle per request, from the current user's 'admin_color' option, in
 * wp_style_loader_src() (wp-includes/script-loader.php). Our $url therefore
 * REPLACES the default scheme's stylesheet for users who select us — it does
 * not layer on top of it. The compiled sheet is written against the unaided
 * wp-admin.css baseline for that reason; see Build/scss/admin/_brand-scheme.scss.
 *
 * TWO BRAND COLOURS. Primary drives structural chrome (admin menu, admin bar)
 * via --roci-brand-structural; Secondary drives highlight surfaces (links,
 * primary buttons, form focus) via --roci-brand-accent. Both resolve from
 * roci_brand_palette(), the canonical store in inc/theme-settings/settings-register.php.
 *
 * File:    inc/admin/brand-scheme.php
 * Version: 1.2.0
 * Updated: 2026-07-26
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The scheme's key.
 *
 * Referenced twice — by wp_admin_css_color() below and by the per-user gate in
 * roci_print_brand_scheme_inline_css(). They must never drift, hence a constant
 * rather than two string literals.
 */
if ( ! defined( 'ROCI_BRAND_SCHEME_KEY' ) ) {
	define( 'ROCI_BRAND_SCHEME_KEY', 'roci-brand' );
}


// ============================================================
// AVAILABILITY SEAM + SECOND BRAND COLOUR
// ============================================================

/**
 * Whether to offer the Brand Colors scheme on this site.
 *
 * Separates two questions that roci_has_brand_accent() alone would conflate:
 * "does a brand exist?" (colour truth) and "should this particular feature be
 * offered?" (availability).
 *
 * Since roci_has_brand_accent() now reads the filtered palette, a child that
 * supplies its brand purely in code — typically because it wants the Fauxlders
 * folder highlight branded — would ALSO cause a new option to appear in every
 * administrator's profile screen, as a side effect of a filter it registered
 * for an unrelated reason. That is surprising from the child's side even though
 * it is correct from the gate's side.
 *
 * This seam is the cheap fix. A child keeps its branded folders and declines
 * the scheme with one line:
 *
 *     add_filter( 'roci_offer_brand_scheme', '__return_false' );
 *
 * Defaults to roci_has_brand_accent(), so the common case stays zero-config and
 * nothing changes for a site that never touches this filter.
 *
 * @return bool  True when the scheme should be registered and injected.
 */
function roci_offer_brand_scheme() {

	return apply_filters( 'roci_offer_brand_scheme', roci_has_brand_accent() );
}

/**
 * Return the site's SECONDARY brand colour, for the scheme's highlight surfaces.
 *
 * Reads the 'secondary' slot of roci_brand_palette(). Primary drives structural
 * chrome (admin menu, admin bar); this drives the highlight surfaces (links,
 * primary buttons, form focus).
 *
 * THE FALLBACK IS #2271b1, NOT PRIMARY AND NOT #000, and the choice is
 * deliberate. #2271b1 is WordPress's own stock accent for exactly the surfaces
 * Secondary owns — links and .button-primary in the unaided wp-admin.css
 * baseline this scheme builds on. So a site that sets Primary and leaves
 * Secondary blank gets branded chrome with stock-WordPress links and buttons,
 * which reads as an intentional one-colour scheme. Falling back to Primary
 * instead would paint every surface one colour and look like the second colour
 * had failed to apply; falling back to #000 would look broken outright.
 *
 * It is also the same value the stylesheet carries as its own var() fallback,
 * so the PHP-supplied and CSS-fallback paths agree rather than diverging.
 *
 * @return string  Hex colour string.
 */
function roci_admin_brand_secondary() {

	$palette   = roci_brand_palette();
	$secondary = isset( $palette['color-secondary'] ) ? $palette['color-secondary'] : '';

	if ( ! $secondary ) {
		$secondary = '#2271b1';
	}

	return apply_filters( 'roci_admin_brand_secondary', $secondary );
}


// ============================================================
// REGISTER THE SCHEME
// ============================================================

/**
 * Register "Brand Colors" in Users → Profile → Admin Color Scheme.
 *
 * GATED ON roci_offer_brand_scheme(), which defaults to roci_has_brand_accent()
 * and lets a child decline the scheme while keeping its brand. It is NOT gated
 * on roci_admin_brand_accent(): that defaults to '#000' and so is never falsy,
 * which would register the scheme on every site including ones with no brand at
 * all — offering the user a choice that paints their admin black. The predicate
 * behind the gate distinguishes "unset" from "deliberately black"; only the
 * second should ship a scheme.
 *
 * Suppression is total: on an unconfigured site wp_admin_css_color() is never
 * called, so "Brand Colors" does not appear in the profile picker at all. If a
 * user had already selected it and the brand is later cleared, core degrades
 * gracefully on its own — wp_style_loader_src()'s
 * `! isset( $_wp_admin_css_colors[ $color ] )` branch falls back to 'modern'.
 *
 * admin_init is where core registers its own schemes, and it fires before both
 * the profile screen renders and the style handles resolve, so it is early
 * enough for both consumers.
 */
function roci_register_brand_color_scheme() {

	if ( ! roci_offer_brand_scheme() ) {
		return;
	}

	$primary   = roci_admin_brand_accent();
	$secondary = roci_admin_brand_secondary();

	wp_admin_css_color(
		ROCI_BRAND_SCHEME_KEY,
		__( 'Brand Colors', 'rocinante' ),

		// get_template_directory_uri(), NOT get_stylesheet_directory_uri(): the
		// file ships in the PARENT, and the stylesheet variant resolves to the
		// active CHILD, 404ing on exactly the sites that want the scheme.
		//
		// NO CACHE-BUSTING IS POSSIBLE HERE, and that is not an oversight.
		// wp_admin_css_color() takes no version argument, and appending our own
		// ?ver= does not survive: wp_style_loader_src() merges the core 'colors'
		// handle's query args OVER this URL, so the sheet is versioned by the
		// WordPress core version, never by our file's mtime. Consequence: after
		// editing admin-brand-scheme.css, a hard refresh is required before the
		// change appears. If a CSS edit "isn't applying", check that before
		// debugging the cascade — it is almost always just the cache.
		get_template_directory_uri() . '/dist/css/admin-brand-scheme.css',

		// Profile-page preview swatches: two neutral chrome darks followed by
		// BOTH brand colours, the shape core's own Ectoplasm scheme uses
		// (two chrome, two accents). Literal hex — the picker is PHP-rendered
		// markup and cannot read a custom property.
		array( '#1d2327', '#2c3338', $primary, $secondary ),

		// Menu icon colours, consumed by wp_color_scheme_settings() and printed
		// as the _wpColorScheme JS object. Also literal hex, same reason.
		//
		// 'focus' takes Secondary — it is the highlight slot, and Secondary owns
		// the highlight surfaces everywhere else in this scheme.
		//
		// 'current' STAYS WHITE and deliberately does not take Primary. The
		// stylesheet paints the current menu item's BACKGROUND with Primary, so
		// a Primary-coloured icon would sit on a Primary-coloured background and
		// disappear. White is the legible pairing and matches what core's own
		// dark-menu schemes do.
		array(
			'base'    => '#a7aaad',
			'focus'   => $secondary,
			'current' => '#fff',
		)
	);
}
add_action( 'admin_init', 'roci_register_brand_color_scheme' );


// ============================================================
// BRAND ACCENT — :root injection, gated per user
// ============================================================

/**
 * Print the :root block the scheme stylesheet reads.
 *
 * THIS GATE IS PER-USER-SCHEME AND MUST STAY THAT WAY. It is deliberately NOT
 * the gate used by the Fauxlders bridge in inc/folders/branding.php, and the
 * two must not be "unified" by a later cleanup — they answer different
 * questions and neither can answer the other's:
 *
 *   Fauxlders  — wp_style_is( 'roci-admin-folders', 'enqueued' )
 *                asks "is the Fauxlders sheet on THIS SCREEN?"  Varies by
 *                screen, identical for every user.
 *
 *   This file  — get_user_option( 'admin_color' ) === ROCI_BRAND_SCHEME_KEY
 *                asks "has THIS USER chosen our scheme?"  Varies by user,
 *                identical on every screen.
 *
 * Using the Fauxlders gate here would inject brand colour into the admin of
 * every user, including one who deliberately picked Midnight. Note also that
 * wp_style_is( 'colors', 'enqueued' ) is useless as a gate: the 'colors' handle
 * is always enqueued, carrying whichever scheme's URL the current user
 * resolved to.
 *
 * ORDERING. admin_head fires after admin_print_styles in wp-admin/admin-header.php,
 * so this <style> is emitted after the scheme's <link> and wins on source order
 * at equal specificity. No !important is used or needed.
 * wp_add_inline_style( 'colors', … ) was considered and rejected: the 'colors'
 * handle is registered with src = true and resolved at print time, and its
 * inline-style behaviour in that state could not be verified without a live
 * install, whereas the admin_head ordering is provable from core's own action
 * sequence.
 *
 * ESCAPING. esc_attr(), NOT sanitize_hex_color() — mirroring the shipped
 * Fauxlders emitter for the same reason recorded there: sanitize_hex_color()
 * returns null for anything that is not a bare hex, so a filter legitimately
 * returning rgb(), a named colour or a var() would blank the declaration and
 * take the whole scheme with it. esc_attr() still neutralises the < and " that
 * could break out of the <style> element.
 */
function roci_print_brand_scheme_inline_css() {

	if ( ! roci_offer_brand_scheme() ) {
		return;
	}

	if ( get_user_option( 'admin_color' ) !== ROCI_BRAND_SCHEME_KEY ) {
		return;
	}

	$structural = roci_admin_brand_accent();     // Primary — admin menu, admin bar
	$accent     = roci_admin_brand_secondary();  // Secondary — links, buttons, focus

	if ( ! $structural || ! $accent ) {
		return;
	}

	// TWO properties now, and the naming is load-bearing.
	//
	// --roci-brand-accent KEEPS its name and its meaning — the highlight colour —
	// and is now fed by Secondary rather than Primary. Every highlight consumer
	// in the stylesheet goes on resolving unchanged; renaming it would have meant
	// rewriting all of them for no behavioural gain.
	//
	// --roci-brand-structural is new and carries Primary. Only the admin menu and
	// admin bar read it.
	printf(
		'<style id="roci-brand-scheme-inline">:root{--roci-brand-structural:%s;--roci-brand-accent:%s;}</style>' . "\n",
		esc_attr( $structural ),
		esc_attr( $accent )
	);
}
add_action( 'admin_head', 'roci_print_brand_scheme_inline_css' );
