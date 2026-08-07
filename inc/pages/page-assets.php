<?php
/**
 * Pages — Per-Page Asset Loader
 *
 * Resolves the current route to a bundle identifier, then conditionally
 * enqueues the matching stylesheet from the ACTIVE CHILD THEME's dist/css/.
 * A bundle that exists loads; one that does not costs nothing.
 *
 * OWNERSHIP MODEL — the parent owns the MECHANISM, the child owns the FILES.
 * Everything in this file is parent-side: the identifier resolver, the
 * existence guard, the handle namespace and the dependency contract. A child
 * needs no enqueue code of its own — it ships dist/css/{slug}.css and the
 * file loads on the matching route. Nothing in a child registers, filters or
 * opts in.
 *
 * WHY PARENT-SIDE. get_stylesheet_directory() resolves to the active CHILD
 * even when called from parent code, so a parent function can reach child
 * assets. The inverse buys nothing: a child-side loader would have no
 * capability this lacks, and would have to be written once per child.
 *
 * File:    inc/pages/page-assets.php
 * Version: 1.0.0
 * Updated: 2026-08-07
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// IDENTIFIER RESOLVER
// ============================================================

/**
 * Resolve the current route to a per-page asset slug.
 *
 * Returns the bundle basename WITHOUT extension — 'page-about',
 * 'page-tide-weather', 'page-404'. The 'page-' prefix is KEPT: it is part of
 * the compiled filename convention already on disk in every repo in the
 * network, so the derived slug and the shipped file agree with no renaming.
 *
 * Note this is deliberately NOT the same string as the settings identifier
 * read by roci_get_setting() / roci_get_setting_raw(), which strips the
 * 'page-' prefix ('tide-weather', not 'page-tide-weather'). The two
 * conventions are one derivation apart and must not be conflated — see
 * CLAUDE.md section 12.6 for the settings-key rule.
 *
 * RESOLUTION ORDER
 *
 *   1. A singular route carrying an assigned page template resolves from that
 *      template's filename. This covers every named page template a child
 *      ships, including the homepage.
 *   2. Otherwise, route-named fallbacks for the templates WordPress resolves
 *      by hierarchy rather than by assignment.
 *   3. Otherwise false — no bundle is claimed.
 *
 * THE FRONT PAGE IS NOT EXCLUDED, AND THAT IS DELIBERATE. A common form of
 * this pattern early-returns on is_front_page(), on the assumption that the
 * homepage has its own dedicated bundle outside the convention. That
 * assumption does not hold here: this network routes its homepage through a
 * named template (pages/page-home.php, assigned via Page Attributes) rather
 * than front-page.php, precisely so the homepage behaves like every other
 * page. It resolves to 'page-home' and loads dist/css/page-home.css like any
 * other route. Do not "restore" an is_front_page() exclusion — it would
 * silently disable the homepage bundle on any child that ships one.
 *
 * An unassigned Page returns false rather than falling back to a per-ID slug.
 * Per-ID bundles ('post-1234.css') are not a convention this network uses,
 * and emitting them would invite a bundle keyed to a database ID that does
 * not survive a content migration.
 *
 * @return string|false  Bundle basename without extension, or false.
 */
function roci_page_asset_slug() {

    /*
     * is_singular() gates the template read for a real reason, not for tidiness.
     * On an archive, get_queried_object_id() returns a TERM id — and passing a
     * term id to get_page_template_slug() would have it looked up as a POST id.
     * A term and a post sharing a numeric id are unrelated objects, so an
     * archive could silently inherit an unrelated page's template slug.
     */
    if ( is_singular() ) {

        $roci_template = get_page_template_slug( get_queried_object_id() );

        if ( $roci_template ) {

            /*
             * sanitize_file_name() IS SECURITY-CRITICAL — do not remove it.
             * $roci_template comes from the _wp_page_template post meta, which
             * is editable through the REST API by any user who can edit the
             * page. It is therefore untrusted input that this function turns
             * into a filesystem path. Without sanitising, a crafted value
             * containing '../' is a local file inclusion. basename() alone is
             * not sufficient hardening to rely on here.
             */
            return sanitize_file_name( basename( $roci_template, '.php' ) );
        }
    }
    /*
     * NO terminal return inside the is_singular() block above — deliberate and
     * load-bearing. It is a WRAPPER, not an early-return guard: a singular route
     * with no assigned template (a blog post, a CPT single) must FALL THROUGH to
     * the route-named fallbacks below. Adding `return false;` or a `post-{ID}`
     * fallback here — the "obvious" tidy-up, and what the house pattern
     * (_wp-patterns/convention-based-per-page-assets.md) does — makes EVERY
     * branch below dead code silently: posts, 404s, search and archives would
     * all stop resolving a bundle, with no error and no warning.
     */

    /*
     * Route-named fallbacks, matching the bundle names already compiled in
     * every repo in the network (page-single.css, page-404.css, page-search.css).
     * Ordered most-specific first; the conditions are mutually exclusive in
     * practice, and the order documents intent rather than resolving overlap.
     */
    if ( is_single() ) {
        return 'page-single';
    }

    if ( is_404() ) {
        return 'page-404';
    }

    if ( is_search() ) {
        return 'page-search';
    }

    /*
     * BLOG INDEX — NO BUNDLE, BY INTENT. This is the posts-page route: the
     * template serving whatever Page is set as "Posts page" in
     * Settings → Reading, i.e. a child's home.php. It claims no bundle
     * because it is not a designed page — it is the noindexed
     * deep-pagination archive that page-blog.php links out to.
     * page-blog.php is the INDEXED blog landing and, being a named page
     * template, resolves normally above.
     *
     * Do NOT confuse this route with pages/page-home.php. That is the static
     * front page, it is singular, and it DOES resolve — to 'page-home',
     * through the assigned-template branch above. Two unrelated things whose
     * filenames both contain "home".
     *
     * Checked BEFORE is_archive() deliberately: is_home() is the specific
     * condition, and checking the general one first would let a posts-page
     * route claim 'page-archive' in setups where the two overlap.
     */
    if ( is_home() ) {
        return false;
    }

    if ( is_archive() ) {
        return 'page-archive';
    }

    return false;
}


// ============================================================
// CONDITIONAL ENQUEUE
// ============================================================

/**
 * Enqueue the current route's per-page stylesheet, if one exists.
 *
 * PRIORITY 20, NOT 10. Every existing front-end enqueue in the parent and in
 * both children registers at the default priority 10. Running at 20 puts this
 * after all of them, which guarantees the dependency handle below is already
 * registered when this resolves. Anything that moves a base stylesheet
 * enqueue to 20 or beyond must move this one with it.
 *
 * THE CHILD DIRECTORY, NOT THE PARENT'S. The path is built from
 * get_stylesheet_directory() — the active CHILD — because that is where the
 * real bundles live. This is the same rule inc/admin/brand-scheme.php:162-164
 * states for the inverse case: pick the directory function by where the FILE
 * ships, never by where the CODE lives. That file ships in the parent and so
 * uses get_template_directory_uri(); these ship in the child, so this uses
 * the stylesheet variant. Using the template variant here would resolve to
 * the parent and find nothing on exactly the sites that have a bundle.
 *
 * @return void
 */
function roci_enqueue_page_bundle() {

    $roci_slug = roci_page_asset_slug();

    if ( ! $roci_slug ) {
        return;
    }

    $roci_relative = '/dist/css/' . $roci_slug . '.css';
    $roci_abs      = get_stylesheet_directory() . $roci_relative;

    /*
     * BOTH HALVES OF THIS GUARD ARE LOAD-BEARING.
     *
     * file_exists() — enqueuing a missing file produces a 404 in the page's
     * network waterfall and, on some hosts, an HTML error page served with a
     * CSS content type.
     *
     * filesize() — and this half is the one specific to this network. The
     * per-page SCSS entry points are stubs on most sites, so their compiled
     * output is ZERO BYTES while the file itself exists. Without this check
     * every such stub would be requested and served as an empty 200: a real
     * round trip, a render-blocking stylesheet, and nothing in it. Guarding on
     * existence alone is not enough here.
     */
    if ( ! file_exists( $roci_abs ) || filesize( $roci_abs ) < 1 ) {
        return;
    }

    /*
     * Declaring the child's base sheet as a dependency is what makes the
     * cascade correct: without it, load order is registration order, and a
     * page bundle that prints BEFORE the sheet it was written to override
     * loses every equal-specificity rule in it.
     *
     * Both current children name their base handle '{stylesheet}-style'
     * (fishpotrero-style, rjk-splendor-style), so the default resolves. The
     * filter exists so a child that names its handle differently corrects it
     * in one line instead of silently losing its page styles.
     */
    $roci_dependency = apply_filters(
        'roci_page_asset_dependency',
        get_stylesheet() . '-style'
    );

    wp_enqueue_style(
        'roci-page-' . $roci_slug,
        get_stylesheet_directory_uri() . $roci_relative,
        array( $roci_dependency ),
        filemtime( $roci_abs )
    );
}
add_action( 'wp_enqueue_scripts', 'roci_enqueue_page_bundle', 20 );
