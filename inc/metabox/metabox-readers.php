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
 * Version: 1.1.0
 * Updated: 2026-06-14
 *
 * @package ElRocinante
 */

// ============================================================
// SETTINGS PAGE — READ WRAPPER
// ============================================================

/**
 * Resolve the option-name prefix for page settings.
 *
 * Returns the string prepended to the page slug when building the
 * wp_options row name for MB Pro Settings Pages. Defaults to the parent
 * theme's own 'roci_page_'. Child themes that register their Settings
 * Pages under a different namespace override via:
 *
 *     add_filter( 'roci_page_option_prefix', function() {
 *         return 'fpp_page_';
 *     } );
 *
 * @return string
 */
function roci_page_option_prefix() {
    return apply_filters( 'roci_page_option_prefix', 'roci_page_' );
}


/**
 * Read a field value from an MB Pro Settings Page.
 *
 * The canonical API for reading per-page content fields stored in the
 * Pages submenu under Theme Settings. All page templates in all child
 * themes should read through this wrapper — never call rwmb_meta()
 * directly for Settings Page reads.
 *
 * Option name convention: the wrapper builds the option name via
 * roci_page_option_prefix() — default 'roci_page_' — appended with the
 * page slug. So roci_get_setting( 'home', 'hero_headline' ) reads from
 * 'roci_page_home' by default. Child themes that register MB Pro Settings
 * Pages under a different prefix must override 'roci_page_option_prefix'
 * via add_filter() so their prefix matches their option_name registration.
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

    // Build the option name from the filterable prefix (default 'roci_page_').
    // Children override 'roci_page_option_prefix' to supply their namespace.
    $option_name = roci_page_option_prefix() . $page;

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


/**
 * Read a raw field value from an MB Pro Settings Page.
 *
 * Returns the bare stored value exactly as it sits in wp_options — a
 * scalar, attachment ID, or primitive — without routing through
 * rwmb_meta(). This contrasts with roci_get_setting(), which pipes the
 * value through MB Pro's field hydration layer and returns an expanded
 * data structure (e.g. a full image array for image fields, resolved
 * group data for clone groups).
 *
 * WHY TWO READERS
 *
 * MB Pro's rwmb_meta() hydration is convenient when a template needs
 * full image metadata (URL, width, height, alt, etc.) in one call. But
 * many templates — and all child-theme raw readers (e.g. FP's
 * fpp_setting()) — expect a bare attachment ID that they pass to
 * wp_get_attachment_image() or jw_picture() themselves. Passing an
 * already-hydrated array to those functions produces incorrect output.
 * roci_get_setting_raw() is the drop-in replacement for those raw child
 * readers: same get_option() path, same isset() && !== '' empty-value
 * semantics, same result shape. A forking dev should use this reader
 * anywhere the child theme was previously calling its own raw wrapper
 * (fpp_setting(), etc.) so the migration is a straight substitution.
 *
 * WHEN TO USE EACH
 *
 *   roci_get_setting()     — when the template wants MB Pro's hydrated
 *                            field data (full image arrays, expanded
 *                            groups). Requires MB Pro to be active.
 *
 *   roci_get_setting_raw() — when the template expects a bare scalar or
 *                            attachment ID. Works whether or not MB Pro
 *                            is active; reads directly from wp_options.
 *
 * This function shares the 'roci_page_option_prefix' filter with
 * roci_get_setting(), so child-prefix overrides apply automatically —
 * no extra wiring needed in the child.
 *
 * Usage:
 *
 *     $image_id = roci_get_setting_raw( 'home', 'hero_image' );
 *     $headline = roci_get_setting_raw( 'home', 'hero_headline', 'Default text' );
 *
 * Empty-value semantics: returns $default when the field key is absent
 * OR when the stored value is exactly '' (empty string). Does NOT treat
 * null or false as empty — this is intentionally narrower than
 * roci_get_setting()'s broader (=== '' || null || false) check, and
 * matches the behavior of raw get_option-based child readers exactly.
 *
 * @param  string $page    Page slug matching the template filename
 *                         (e.g. 'home', 'charters', 'tours').
 * @param  string $field   Field ID within the page's Settings Page.
 * @param  mixed  $default Value to return if the field is absent or ''.
 *                         Default: empty string.
 * @return mixed           The raw stored value, or $default if not set.
 */
function roci_get_setting_raw( $page, $field, $default = '' ) {
    $option_name = roci_page_option_prefix() . $page;
    $options     = get_option( $option_name, array() );
    return isset( $options[ $field ] ) && $options[ $field ] !== ''
        ? $options[ $field ]
        : $default;
}
