<?php
/**
 * Layout Bundles — Vertical Feature Bundle Loader
 *
 * Loads El Rocinante's per-vertical "layout bundles." A layout bundle is
 * a named group of parent-theme features that a child theme opts into with
 * a single add_theme_support() call. Sites that do not opt in are wholly
 * unaffected — a bundle registers nothing until its flag is present.
 *
 * File:    inc/layout-bundles/layout-bundles.php
 * Version: 1.0.0
 * Updated: 2026-07-09
 *
 * @package ElRocinante
 *
 * ------------------------------------------------------------------
 * WHAT A LAYOUT BUNDLE IS
 * ------------------------------------------------------------------
 *
 * The parent theme hosts feature bundles keyed to a business vertical:
 *
 *   - roci-tour-layout          — tour / tourism sites   (this release)
 *   - roci-real-estate-layout   — real-estate sites      (future)
 *   - roci-property-mgmt-layout — property-mgmt sites     (future)
 *
 * Each bundle lives in its own file in this directory and is gated behind
 * one theme-support flag whose name matches the bundle. A bundle may
 * contain any number of features (meta boxes, enqueues, body classes,
 * template hooks, etc.). Every feature in the bundle registers behind the
 * SAME single flag — adding a second or third feature to a bundle never
 * requires a new flag or any child-side change beyond the one opt-in.
 *
 * ------------------------------------------------------------------
 * HOW A CHILD THEME OPTS IN
 * ------------------------------------------------------------------
 *
 * The child declares its vertical with one line, no later than
 * after_setup_theme (default priority). A bare call in the child's
 * functions.php also works, since the child loads before the parent:
 *
 *     add_action( 'after_setup_theme', function() {
 *         add_theme_support( 'roci-tour-layout' );
 *     } );
 *
 * That is the entire opt-in. The child inherits every feature in the
 * bundle. A child that never calls add_theme_support( 'roci-tour-layout' )
 * sees no Hero Display meta box and no behavior change of any kind.
 *
 * ------------------------------------------------------------------
 * HOW A FUTURE VERTICAL FOLLOWS THIS SHAPE
 * ------------------------------------------------------------------
 *
 * tour-layout.php is the reference implementation. To add a new vertical:
 *
 *   1. Copy inc/layout-bundles/tour-layout.php to
 *      inc/layout-bundles/real-estate-layout.php (or the new vertical).
 *   2. Swap the flag name ('roci-tour-layout' -> 'roci-real-estate-layout')
 *      and rename the bundle bootstrap function to match.
 *   3. Register the new vertical's features inside its single gated
 *      bootstrap, following the pattern in tour-layout.php.
 *   4. Add one require_once line for the new file in the loader below.
 *
 * No existing bundle is touched, and no non-opted-in site is affected.
 */

// ============================================================
// LAYOUT BUNDLE LOADERS
// ============================================================
//
// One require per vertical bundle. Each file self-gates on its own
// add_theme_support flag, so loading the file here is inert until a
// child opts in — the require costs a function definition and a single
// hook registration, nothing more.

require_once get_template_directory() . '/inc/layout-bundles/tour-layout.php';
