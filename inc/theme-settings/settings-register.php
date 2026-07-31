<?php
/**
 * Theme Settings — Register Settings, Menu & Scripts
 *
 * Also contains the roci_setting() front-end helper, the canonical brand store
 * roci_brand_palette(), and the two readers that resolve through it —
 * roci_admin_brand_accent() ("what colour") and roci_has_brand_accent()
 * ("is one configured at all").
 *
 * File:    inc/theme-settings/settings-register.php
 * Version: 1.5.2
 * Updated: 2026-07-31
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// REGISTER SETTINGS
// ============================================================

function roci_register_settings() {

    register_setting( 'roci_design_group',       'roci_design',       array( 'sanitize_callback' => 'roci_sanitize_design'       ) );
    register_setting( 'roci_business_group',     'roci_business',     array( 'sanitize_callback' => 'roci_sanitize_business'     ) );
    register_setting( 'roci_social_group',       'roci_social',       array( 'sanitize_callback' => 'roci_sanitize_social'       ) );
    register_setting( 'roci_seo_group',          'roci_seo',          array( 'sanitize_callback' => 'roci_sanitize_seo'          ) );
    register_setting( 'roci_integrations_group', 'roci_integrations', array( 'sanitize_callback' => 'roci_sanitize_integrations' ) );
    register_setting( 'roci_footer_group',       'roci_footer',       array( 'sanitize_callback' => 'roci_sanitize_footer'       ) );

    // Site Identity — registers WP core options directly
    register_setting( 'roci_identity_group', 'blogname',        array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'roci_identity_group', 'blogdescription', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'roci_identity_group', 'site_icon',       array( 'sanitize_callback' => 'absint'              ) );

}
add_action( 'admin_init', 'roci_register_settings' );


// ============================================================
// REGISTER ADMIN MENU
// ============================================================

function roci_add_settings_menu() {
    add_menu_page(
        __( 'Theme Settings', 'rocinante' ),
        __( 'Theme Settings', 'rocinante' ),
        'manage_options',
        'roci-theme-settings',
        'roci_settings_page',
        'dashicons-admin-customizer',
        3
    );
}
add_action( 'admin_menu', 'roci_add_settings_menu' );


// ============================================================
// ENQUEUE COLOR PICKER & ADMIN SCRIPTS
// ============================================================

function roci_settings_enqueue( $hook ) {
    if ( strpos( $hook, 'roci' ) === false ) return;

    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_media();
    wp_enqueue_script(
        'roci-settings-js',
        get_template_directory_uri() . '/dist/js/theme-settings.js',
        array( 'jquery', 'wp-color-picker' ),
        filemtime( get_template_directory() . '/dist/js/theme-settings.js' ),
        true
    );
}
add_action( 'admin_enqueue_scripts', 'roci_settings_enqueue' );


// ============================================================
// HELPER — GET SETTING
// Usage: roci_setting('business', 'name')
//        roci_setting('design', 'header_style', 'solid')
//
// For BRAND COLOURS read roci_brand_palette() below, not this — the palette
// resolver is the canonical store and applies the child filter. Colour keys in
// roci_design are the canonical hyphenated names ('color-primary', not
// 'primary'); legacy spellings are translated on read by the migration above.
// ============================================================

function roci_setting( $group, $key, $default = '' ) {
    $options = get_option( 'roci_' . $group, array() );
    return isset( $options[ $key ] ) && $options[ $key ] !== '' ? $options[ $key ] : $default;
}


// ============================================================
// MIGRATION — legacy roci_design keys -> canonical hyphenated
// ============================================================

/**
 * Translate pre-canonical roci_design keys to their canonical names on read.
 *
 * The Design tab's colour keys were renamed to match the canonical $color-*
 * SCSS interface exactly ('primary' -> 'color-primary', 'background_alt' ->
 * 'background-alt', and so on). Any value saved before that rename is stored
 * under the old key and would otherwise be orphaned — present in the database,
 * invisible to every reader.
 *
 * This runs on the 'option_roci_design' filter rather than as a one-time
 * upgrade routine, deliberately. It is NON-DESTRUCTIVE: the stored row is never
 * rewritten, so a rollback needs no counter-migration and a half-applied
 * migration is not a state that can exist. It also covers every reader at once
 * — roci_setting(), roci_brand_palette(), and the tab's own get_option() call
 * all pass through get_option() and so all see translated keys with no
 * per-reader change. The cost is a small array walk per read.
 *
 * The canonical key always wins: if both spellings are present, the legacy one
 * is ignored rather than overwriting a newer value. Once the tab is saved once,
 * the row holds only canonical keys and this becomes a no-op pass-through.
 *
 * Non-colour keys in the same option — body_font, heading_font, base_font_size,
 * header_style, sticky_header, button_style — are untouched by design.
 *
 * @param  mixed $value  The stored option value.
 * @return mixed         The value with legacy colour keys translated.
 */
function roci_migrate_design_keys( $value ) {

    if ( ! is_array( $value ) ) {
        return $value;
    }

    /*
     * The legacy keys 'primary_accent' and 'secondary_accent' are deliberately
     * ABSENT from this map. They have no canonical slot — the canonical
     * vocabulary expresses variation tonally (-light/-dark/-darker/-pale) and
     * has no unqualified "accent of primary" concept — so there is nowhere
     * truthful to carry them. Mapping them onto -light would silently reassign
     * a value the admin chose for one meaning to a slot that means another.
     * They are dropped: any stored value under those keys is left orphaned in
     * the row rather than being given a wrong home.
     */
    $map = array(
        'primary'         => 'color-primary',
        'secondary'       => 'color-secondary',
        'tertiary'        => 'color-tertiary',
        'tertiary_accent' => 'color-tertiary-accent',
        'black'           => 'color-black',
        'grey'            => 'color-grey',
        'white'           => 'color-white',
        'background_alt'  => 'background-alt',
    );

    foreach ( $map as $legacy => $canonical ) {
        if ( ! isset( $value[ $legacy ] ) ) {
            continue;
        }
        if ( ! isset( $value[ $canonical ] ) || '' === $value[ $canonical ] ) {
            $value[ $canonical ] = $value[ $legacy ];
        }
        unset( $value[ $legacy ] );
    }

    return $value;
}
add_filter( 'option_roci_design', 'roci_migrate_design_keys' );


// ============================================================
// HELPER — BRAND PALETTE (canonical brand store)
// Usage: roci_brand_palette()
// Filter: 'roci_brand_palette'
// ============================================================

/**
 * The site's brand palette — the single source of brand truth.
 *
 * Every brand consumer resolves through this: roci_admin_brand_accent() and
 * roci_has_brand_accent() below, the Brand Colors admin scheme in
 * inc/admin/brand-scheme.php, and the read-only mirror on the Design tab. If
 * a new consumer needs a brand colour, it reads this — not get_option() and
 * not roci_setting().
 *
 * Keys are the CANONICAL FIFTEEN $color-* names with the leading $ dropped and
 * hyphens intact, so the palette, the Design-tab keys and the SCSS interface are
 * spelled identically. Every key is always present; an unconfigured slot is an
 * empty string, never missing, so callers can index without isset() gymnastics.
 *
 * The Design tab additionally renders 'background' and 'background-alt' as
 * parent-only extras. Those are deliberately NOT in the palette: the canonical
 * set has no surface concept, and the palette is the contract a child fills.
 *
 * Default source is the roci_design option, so a site that fills the Design tab
 * behaves exactly as it did before this resolver existed.
 *
 * THE FILTER IS THE POINT. A code-owned child supplies its brand here in about
 * four lines and never touches the dashboard:
 *
 *     add_filter( 'roci_brand_palette', function ( $palette ) {
 *         $palette['color-primary']   = '#1a2e35';
 *         $palette['color-secondary'] = '#4894a2';
 *         return $palette;
 *     } );
 *
 * That single registration switches on every brand consumer at once, which is
 * why it replaced the previous arrangement where a child had to remember to
 * register 'roci_admin_brand_accent' and 'roci_has_brand_accent' as a matched
 * pair or its scheme would silently never appear.
 *
 * FAILS CLOSED. A child that supplies nothing gets an all-empty palette: the
 * Design pickers stay blank, roci_has_brand_accent() stays false, and the admin
 * scheme never registers. Nothing renders a wrong colour; features are absent
 * rather than broken.
 *
 * @return array  Fifteen-key map of canonical colour key => hex string (or '').
 */
function roci_brand_palette() {

    $stored = get_option( 'roci_design', array() );

    // The canonical fifteen, spelled exactly as the $color-* SCSS interface
    // minus the leading $. Background extras are parent-only and deliberately
    // NOT part of the palette contract a child fills.
    $keys = array(
        'color-primary', 'color-primary-light', 'color-primary-dark',
        'color-primary-darker', 'color-primary-pale',
        'color-secondary', 'color-secondary-light', 'color-secondary-dark',
        'color-tertiary', 'color-tertiary-accent',
        'color-accent', 'color-eyebrow',
        'color-white', 'color-black', 'color-grey',
    );

    $palette = array();
    foreach ( $keys as $key ) {
        $palette[ $key ] = isset( $stored[ $key ] ) ? $stored[ $key ] : '';
    }

    return apply_filters( 'roci_brand_palette', $palette );
}


// ============================================================
// HELPER — ADMIN BRAND ACCENT
// Usage: roci_admin_brand_accent()
// Filter: 'roci_admin_brand_accent'
// ============================================================

/**
 * Return the site's brand accent colour as a CSS-ready hex string.
 *
 * Reads the PRIMARY slot of roci_brand_palette(), which defaults to Theme
 * Settings → Design → Primary and can be supplied in code by a child. That slot
 * is empty until something fills it, so on an unconfigured install this falls
 * back to '#000' — the exact value abstracts/_variables.scss:84 compiles into
 * both --folders-highlight (admin) and --color-action (front end). An unfilled
 * palette therefore renders identically to a pre-bridge install.
 *
 * The value is passed through unmodified. The store may hold a three-digit
 * '#000' or a six-digit picker value and CSS accepts either, so there is
 * deliberately no length check, normalisation or padding here. Callers must
 * escape at their own output site.
 *
 * This helper is NOT Fauxlders-specific. Any admin-side surface that needs the
 * brand accent — the folder token bridge in inc/folders/branding.php and the
 * Brand Colors scheme in inc/admin/brand-scheme.php today — reads it from here.
 *
 * Its own filter is retained and still runs LAST, so anything already hooking
 * 'roci_admin_brand_accent' keeps overriding the palette exactly as before.
 *
 * @return string  Hex colour string, e.g. '#000' or '#4894a2'.
 */
function roci_admin_brand_accent() {

    $palette = roci_brand_palette();
    $accent  = isset( $palette['color-primary'] ) ? $palette['color-primary'] : '';

    if ( ! $accent ) {
        $accent = '#000';
    }

    return apply_filters( 'roci_admin_brand_accent', $accent );
}


// ============================================================
// HELPER — IS A BRAND ACCENT CONFIGURED?
// Usage: roci_has_brand_accent()
// Filter: 'roci_has_brand_accent'
// ============================================================

/**
 * Whether a brand accent has been DELIBERATELY configured for this site.
 *
 * This answers "should brand-dependent admin features activate at all?" —
 * a different question from roci_admin_brand_accent()'s "what colour is it?".
 *
 * roci_admin_brand_accent() cannot answer it. That helper defaults an unset
 * option to '#000', so an untouched install and a site whose brand really is
 * black are indistinguishable through it. That collapse is correct for its own
 * job — the Fauxlders bridge wants a usable colour either way — but a feature
 * that must stay invisible until a brand exists needs this predicate instead.
 *
 * Reads the PRIMARY slot of roci_brand_palette() raw: empty or missing is
 * false, and any non-empty value is true, INCLUDING a deliberate '#000'.
 *
 * BECAUSE IT READS THE PALETTE, A CHILD SUPPLYING ITS BRAND IN CODE NOW COUNTS
 * AS CONFIGURED. That is the intended behaviour and it repairs a real trap:
 * before the palette resolver existed this read the option directly, so a child
 * that supplied its brand through the 'roci_admin_brand_accent' filter had a
 * genuine brand, an empty option row, and a predicate that said "no brand" —
 * meaning its admin scheme silently never registered and it had to know to
 * register a second filter as well. One 'roci_brand_palette' registration now
 * switches everything on together.
 *
 * Note the consequence, which is deliberate: a brand supplied purely in code
 * makes brand-dependent admin features activate with no dashboard input at all.
 * A child that wants the brand but NOT a particular feature opts out of that
 * feature specifically — see roci_offer_brand_scheme() in
 * inc/admin/brand-scheme.php, which exists exactly so "has a brand" and "wants
 * the colour scheme offered" stay separable questions.
 *
 * @return bool  True when a brand accent is configured.
 */
function roci_has_brand_accent() {

    $palette = roci_brand_palette();
    $has     = isset( $palette['color-primary'] ) && '' !== $palette['color-primary'];

    return apply_filters( 'roci_has_brand_accent', $has );
}