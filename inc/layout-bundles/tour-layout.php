<?php
/**
 * Layout Bundle — Tour Layout (roci-tour-layout)
 *
 * The parent-theme feature bundle for tour / tourism child sites. Every
 * feature here is gated behind a single theme-support flag,
 * 'roci-tour-layout'. A child opts in with one line (see
 * inc/layout-bundles/layout-bundles.php); a site that does not opt in
 * registers none of this and sees no behavior change.
 *
 * This file is the REFERENCE IMPLEMENTATION every future vertical bundle
 * follows (roci-real-estate-layout, roci-property-mgmt-layout, …). Keep
 * its shape intact: one gated bootstrap, one flag, N features registered
 * inside the gate.
 *
 * Features in this bundle:
 *   - Hero Display meta box (roci_hero_focus + roci_hero_focus_custom)
 *
 * The sanitizer (roci_sanitize_object_position) and the template-facing
 * resolution helper (roci_get_hero_focus) live in inc/helpers.php — they
 * are pure/read helpers with no side effects, safe to define unconditionally,
 * and are called by the child template only when this bundle is active.
 *
 * File:    inc/layout-bundles/tour-layout.php
 * Version: 1.0.0
 * Updated: 2026-07-09
 *
 * @package ElRocinante
 */

// ============================================================
// TOUR LAYOUT BUNDLE — SINGLE GATE
// ============================================================
//
// One gate for the whole bundle. current_theme_supports() is checked
// once here; if the child has not opted in, we return before registering
// anything. Add future tour-layout features inside this function — they
// all inherit the same 'roci-tour-layout' flag with no restructuring.
//
// Timing: this runs on after_setup_theme priority 20. The child declares
// its support no later than after_setup_theme (default priority 10) — or
// bare in its functions.php, which loads before the parent — so the flag
// is always set by the time this fires. after_setup_theme also runs
// before init, so the rwmb_meta_boxes filter registered below is in place
// before Meta Box Pro collects meta boxes on init priority 20.

function roci_tour_layout_bundle() {

    if ( ! current_theme_supports( 'roci-tour-layout' ) ) {
        return; // Not opted in — register nothing.
    }

    // --- Bundle features ---
    add_filter( 'rwmb_meta_boxes', 'roci_tour_layout_hero_display_box' );

    // Future tour-layout features register here, behind this same gate.
}
add_action( 'after_setup_theme', 'roci_tour_layout_bundle', 20 );


// ============================================================
// FEATURE — HERO DISPLAY META BOX
// ============================================================

/**
 * Register the dedicated "Hero Display" meta box.
 *
 * A standalone box (NOT appended to roci_seo_fields) attached to the same
 * post types as the SEO box via roci_get_seo_post_types(). Only ever
 * registered when the tour-layout bundle is active — see the gate above.
 *
 * The box is intentionally structured to hold more Hero Display fields
 * later; add them to the 'fields' array without touching the gate.
 *
 * @param  array $meta_boxes Existing Meta Box registrations.
 * @return array             Registrations with the Hero Display box added.
 */
function roci_tour_layout_hero_display_box( $meta_boxes ) {

    $meta_boxes[] = array(
        'title'      => __( 'Hero Display', 'rocinante' ),
        'id'         => 'roci_hero_display',
        'post_types' => roci_get_seo_post_types(),
        'context'    => 'side',
        'priority'   => 'default',
        'fields'     => array(

            // Hero Focal Point — hybrid select + custom text.
            array(
                'id'      => 'roci_hero_focus',
                'name'    => __( 'Hero Focal Point', 'rocinante' ),
                'type'    => 'select',
                'std'     => 'center center',
                'options' => array(
                    'center 0%'     => __( 'Top', 'rocinante' ),
                    'center 25%'    => __( 'Upper', 'rocinante' ),
                    'center center' => __( 'Center (default)', 'rocinante' ),
                    'center 75%'    => __( 'Lower', 'rocinante' ),
                    'center 100%'   => __( 'Bottom', 'rocinante' ),
                    '__custom__'    => __( 'Custom…', 'rocinante' ),
                ),
                'desc'    => __( 'Which part of the hero image stays in view as it crops. Choose "Custom…" to enter a precise CSS object-position value.', 'rocinante' ),
            ),

            // Custom Focal Point — shown only when "Custom…" is selected.
            array(
                'id'          => 'roci_hero_focus_custom',
                'name'        => __( 'Custom Focal Point', 'rocinante' ),
                'type'        => 'text',
                'placeholder' => 'center 15%',
                'desc'        => __( 'A CSS object-position value, e.g. "center 15%" or "left top". Invalid values fall back to "center center".', 'rocinante' ),
                'visible'     => array( 'roci_hero_focus', '=', '__custom__' ),
            ),

        ),
    );

    return $meta_boxes;
}
