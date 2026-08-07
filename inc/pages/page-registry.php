<?php
/**
 * Pages — Page Identifier Registry
 *
 * Provides the filter hook child themes use to declare which page
 * identifiers exist on their site, plus the diagnostic that reports a read
 * against an identifier nobody registered.
 *
 * WHAT PROBLEM THIS SOLVES. The page identifier is the first argument to
 * roci_get_setting() / roci_get_setting_raw() — the string that completes
 * '{prefix}{page}' to name a wp_options row. It has always been a bare
 * hand-typed literal at every call site, with no validation of any kind. A
 * typo therefore names an option row that does not exist, get_option()
 * returns its default, and the reader returns $default — so every field on
 * that page blanks out SILENTLY, one call at a time, with no notice, no log
 * entry and no PHP error. The reader cannot distinguish "wrong page name"
 * from "editor has not filled this field yet". This registry makes the
 * first case say so.
 *
 * The parent is content-agnostic and owns no pages of its own (see CLAUDE.md
 * section 12.4), so the registry's default is an EMPTY array. Every value in
 * it comes from a child.
 *
 * Usage from a child theme (e.g. in functions.php, at load time):
 *
 *     function mychild_register_pages( $pages ) {
 *         return array_merge( $pages, array( 'home', 'about', 'contact' ) );
 *     }
 *     add_filter( 'roci_registered_pages', 'mychild_register_pages' );
 *
 * A child that registers nothing is completely unaffected: an empty registry
 * disables validation rather than failing every read. Opting in is per child.
 *
 * File:    inc/pages/page-registry.php
 * Version: 1.0.0
 * Updated: 2026-08-07
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// THE REGISTRY
// ============================================================

/**
 * Get the list of page identifiers registered on this site.
 *
 * Child themes contribute their own via the `roci_registered_pages` filter,
 * appending to the incoming array rather than replacing it:
 *
 *     function mychild_register_pages( $pages ) {
 *         return array_merge( $pages, array( 'home', 'discover' ) );
 *     }
 *     add_filter( 'roci_registered_pages', 'mychild_register_pages' );
 *
 * The values are the RESOLVED IDENTIFIER STRINGS the readers actually
 * receive — 'discover', not the name of a constant that happens to hold it.
 * A child defining `const MYCHILD_PAGE_DISCOVER = 'discover'` registers the
 * constant, which PHP evaluates to the string before the filter ever sees
 * it. Registering constant NAMES would compare 'discover' against
 * 'MYCHILD_PAGE_DISCOVER' and never match.
 *
 * EMPTY DEFAULT, AND IT IS LOAD-BEARING. The parent declares no pages, so
 * an unhooked filter returns array(). That is the "this child has not opted
 * in" signal, and roci_validate_page_identifier() treats it as "validate
 * nothing" rather than "every identifier is wrong". Without that, adding
 * this file would make an unmigrated child report every read on the site.
 *
 * ⚠ REGISTER AT LOAD TIME — NOT INSIDE A TEMPLATE OR A LATE HOOK. The
 * filter dispatches ONCE per request, on the first call, and the result is
 * cached in a static for the rest of the request. An add_filter() that runs
 * after that first call is a silent no-op: no error, no warning, the pages
 * simply never appear in the registry. A bare add_filter() in the child's
 * functions.php is always early enough, since WordPress includes the child's
 * functions.php before the parent's and both run long before any template
 * reads a setting. This is the same trap CLAUDE.md section 12.5 documents
 * for roci_get_seo_post_types() and its two siblings, and it fails the same
 * quiet way.
 *
 * @return array List of registered page identifier strings. Empty when no
 *               child has opted in.
 */
function roci_registered_pages() {
    static $pages = null;

    if ( null === $pages ) {
        $pages = apply_filters( 'roci_registered_pages', array() );
    }

    return $pages;
}


// ============================================================
// STRICT MODE GATE
// ============================================================

/**
 * Is page-identifier validation active on this install?
 *
 * THIS FUNCTION IS THE SEAM. Today it answers one question — is WP_DEBUG on
 * — and that is the whole of it. It exists as a named predicate rather than
 * an inline check so that everything which might later change about WHEN
 * validation runs changes HERE, in one place, without touching a single
 * caller:
 *
 *   - an explicit ROCI_STRICT_PAGES constant, for a staging install where
 *     WP_DEBUG is off but strict checking is still wanted;
 *   - a filter, so a child can opt in or out on its own terms;
 *   - a hard-fail mode that escalates from notice to wp_die() in a
 *     controlled environment.
 *
 * None of those exist yet and none should be added speculatively. The point
 * is that adding one later is a change to this function alone.
 *
 * The defined() guard on WP_DEBUG is mandatory, not defensive noise.
 * WP_DEBUG is declared in wp-config.php and is not guaranteed to exist; a
 * bare reference to an undefined constant is an E_WARNING that evaluates to
 * its own name on PHP 7 and a fatal Error on PHP 8. Neither is acceptable in
 * a diagnostic whose entire job is to not disturb the page it is watching.
 *
 * @return bool True when validation should report.
 */
function roci_strict_pages_enabled() {
    return defined( 'WP_DEBUG' ) && WP_DEBUG;
}


// ============================================================
// THE DIAGNOSTIC
// ============================================================

/**
 * Report a read against an unregistered page identifier.
 *
 * THIS FUNCTION REPORTS. IT DOES NOT ACT.
 *
 * That distinction is the single most important property of this file, and
 * it is enforced by having nothing to return. Callers invoke it and then
 * proceed to their normal lookup unconditionally — they do not branch on it,
 * cannot branch on it, and their return values are byte-identical to what
 * they were before this function existed. A page whose identifier is
 * unregistered still reads exactly as it always did. Validation sits BESIDE
 * the read, never in its way.
 *
 * Do not "improve" this by returning a bool and having the readers bail
 * early on false. That would convert a diagnostic into a behaviour change,
 * and it would break every site whose child has not yet opted in — the
 * failure mode being an entire page of blank fields, which is precisely the
 * silent breakage this exists to expose.
 *
 * It is silent in three cases, in this order:
 *
 *   1. Strict mode off (production, WP_DEBUG unset or false).
 *   2. Registry empty — the child has not opted in, so there is nothing to
 *      validate against and every identifier would otherwise be flagged.
 *   3. The identifier is registered, which is the normal path.
 *
 * _doing_it_wrong() is the deliberate vehicle. It emits an E_USER_NOTICE
 * through core's own wp_doing_it_wrong_trigger_error filter, so it respects
 * WP_DEBUG a second time, is visible in a debug log, and CANNOT be fatal.
 * A diagnostic that could white-screen a client site would be worse than the
 * silent bug it replaces.
 *
 * @param string $page    The page identifier the reader received.
 * @param string $context Name of the calling reader, for the message.
 *                        Callers pass __FUNCTION__.
 * @return void  Nothing. Callers must not depend on a return value.
 */
function roci_validate_page_identifier( $page, $context = '' ) {

    // 1. Production, or debugging off. Say nothing.
    if ( ! roci_strict_pages_enabled() ) {
        return;
    }

    $roci_registered = roci_registered_pages();

    // 2. No child has opted in. Nothing to validate against.
    if ( empty( $roci_registered ) ) {
        return;
    }

    // 3. Strict comparison: the registry holds strings, and a loose match
    //    would let 0 == 'home' pass on some PHP versions.
    if ( ! in_array( $page, $roci_registered, true ) ) {
        _doing_it_wrong(
            esc_html( $context ? $context : __FUNCTION__ ),
            sprintf(
                /* translators: %s: the unregistered page identifier. */
                esc_html__( 'Unregistered page identifier: "%s". Register it via the roci_registered_pages filter.', 'rocinante' ),
                esc_html( $page )
            ),
            '6.2.0'
        );
    }
}
