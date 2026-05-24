<?php
/**
 * Metabox — Read Wrappers
 *
 * Provides parent-theme read wrappers for MB Pro data sources. Templates
 * call these wrappers instead of MB Pro's underlying functions directly,
 * giving the codebase one layer of insulation from upstream API changes.
 *
 * Read wrappers in El Rocinante:
 *
 *   - roci_get_field( $field_id, $object_id )       — postmeta (see helpers)
 *   - roci_get_setting( $page, $field, $default )   — MB Pro Settings Pages
 *   - roci_setting( $tab, $key, $default )          — legacy Theme Settings
 *
 * This file currently houses roci_get_setting() only. The other two
 * wrappers live in their existing locations for backward compatibility
 * and may be consolidated here in a future refactor.
 *
 * File:    inc/metabox/metabox-readers.php
 * Version: 1.0.0
 * Updated: 2026-05-24
 *
 * @package ElRocinante
 */

// ============================================================
// SETTINGS PAGE — READ WRAPPER
// ============================================================

/**
 * Read a field value from an MB Pro Settings Page.
 *
 * The canonical API for reading per-page content fields stored in the
 * Pages submenu under Theme Settings. All page templates in all child
 * themes should read through this wrapper — never call rwmb_meta()
 * directly for Settings Page reads.
 *
 * Option name convention: the wrapper internally prefixes the page slug
 * with 'roci_page_' to produce the actual wp_options row name. So a
 * call like roci_get_setting( 'home', 'hero_headline' ) reads from the
 * wp_options row named 'roci_page_home'. Child themes that register
 * page sections via roci_register_page_section() must use the same
 * convention so reads and writes line up.
 *
 * Defensive behavior: if MB Pro is not active (rwmb_meta() does not
 * exist), the wrapper returns $default instead of fataling. This lets
 * templates render gracefully during plugin deactivation or local dev
 * environments where MB Pro may not be installed.
 *
 * Usage:
 *
 *     $headline = roci_get_setting( 'home', 'hero_headline' );
 *     $image    = roci_get_setting( 'home', 'hero_image', 0 );
 *     $tours    = roci_get_setting( 'home', 'featured_tours', array() );
 *
 * For clone groups (repeating field sets), the returned value is an
 * array of arrays, one per clone. Iterate with foreach in the template.
 *
 * @param  string $page    Page slug matching the template filename
 *                         (e.g. 'home', 'charters', 'tours'). For
 *                         multi-word templates, hyphens are dropped and
 *                         lowercased (see CLAUDE.md section 12.6).
 * @param  string $field   Field ID within the page's Settings Page,
 *                         matching the 'id' key in the field config.
 * @param  mixed  $default Value to return if the field is empty, null,
 *                         or MB Pro is not active. Default: empty string.
 * @return mixed           The field value, or $default if not set.
 */
function roci_get_setting( $page, $field, $default = '' ) {

    // Guard: MB Pro not active. Templates should still render.
    if ( ! function_exists( 'rwmb_meta' ) ) {
        return $default;
    }

    // Internal storage convention: page slugs are prefixed with
    // 'roci_page_' to namespace the wp_options row names. This keeps
    // our option names from colliding with WordPress core options,
    // other plugins, or future legacy Theme Settings migration keys.
    $option_name = 'roci_page_' . $page;

    $value = rwmb_meta(
        $field,
        array( 'object_type' => 'setting' ),
        $option_name
    );

    // MB Pro returns empty string, null, or false for unset fields
    // depending on field type. Normalize to $default in all three cases
    // so callers can rely on a single check.
    if ( $value === '' || $value === null || $value === false ) {
        return $default;
    }

    return $value;
}
