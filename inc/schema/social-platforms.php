<?php
/**
 * Social Platforms — Profile Link Registry
 *
 * The social platform list for the Social settings tab and the site-level
 * sameAs schema property.
 *
 * ONE SOURCE, THREE CONSUMERS: the Social settings tab (renders one URL input
 * per platform), roci_sanitize_social() (whitelists which keys persist — MUST
 * read this function, not a duplicate), and header.php's schema assembly
 * (iterates these keys to build sameAs, skipping roci_schema_sameas_exclude()).
 * Keep these three in agreement via THIS map.
 *
 * SAVED IS NOT PUBLISHED (v1.1.0). Every platform here is saved and readable
 * through roci_setting( 'social', $key ), but a key listed by
 * roci_schema_sameas_exclude() never reaches sameAs. sameAs asserts "this URL
 * is the same entity as this business"; a chat link (WhatsApp) is a contact
 * channel, not a profile, so it is excluded by default.
 *
 * ⚠ THIS FILE EXISTS BECAUSE ALL THREE ONCE DISAGREED. The filter used to be
 * dispatched inline inside tab-social.php, so no other file could reach the
 * filtered result — and both the sanitiser and header.php carried their own
 * hardcoded copies of the eight platforms. A child-registered platform
 * therefore rendered an input, accepted a URL, and was SILENTLY ERASED on save
 * (the sanitiser rebuilds the option row from scratch, so an unlisted key is
 * not merely skipped, its stored value is dropped), and would never have
 * reached sameAs even if it had saved. Do not reintroduce a local list.
 *
 * Filterable: children may add platforms via roci_social_platforms. Any
 * consumer that lists platforms MUST read this function so the filter reaches
 * all three.
 *
 * File:    inc/schema/social-platforms.php
 * Version: 1.1.0
 * Updated: 2026-10-17
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The social platform registry.
 *
 * Keyed by the STORAGE key — the key within the roci_social option row, and the
 * one the Social tab's input names are built from. The value is the admin-facing
 * label, shown only in that tab.
 *
 * ⚠ KEYS ARE THE STORED CONTRACT AND MUST NOT BE RENAMED. A key change orphans
 * every URL already saved under the old one. Note 'twitter' is deliberately
 * still 'twitter' while its label reads 'X (Twitter)' — the label moved, the
 * key did not, and it must stay that way.
 *
 * ORDER IS MEANINGFUL. header.php iterates these keys to build sameAs, so the
 * declaration order here is the emission order in the JSON-LD for every key
 * roci_schema_sameas_exclude() does not remove.
 *
 * Labels are translated; keys are not — they are storage identifiers. The map
 * is only ever built at call time (admin render, schema assembly), so __() runs
 * long after the text domain loads. Never call this function at file scope.
 *
 * @return array Map of storage key => admin-facing label.
 */
function roci_social_platforms() {
    return apply_filters( 'roci_social_platforms', array(
        'facebook'    => __( 'Facebook', 'rocinante' ),
        'instagram'   => __( 'Instagram', 'rocinante' ),
        'whatsapp'    => __( 'WhatsApp', 'rocinante' ),
        'tiktok'      => __( 'TikTok', 'rocinante' ),
        'youtube'     => __( 'YouTube', 'rocinante' ),
        'linkedin'    => __( 'LinkedIn', 'rocinante' ),
        'twitter'     => __( 'X (Twitter)', 'rocinante' ),
        'tripadvisor' => __( 'TripAdvisor', 'rocinante' ),
    ) );
}

/**
 * Platform keys that are saved but never published in sameAs.
 *
 * ONE SOURCE, TWO CONSUMERS: header.php's sameAs loop skips these keys, and the
 * Social tab prints a note under each of them saying so. Read this function in
 * both; never a local list.
 *
 * A KEY LIST, NOT URL SNIFFING. The key is the stored contract the rest of the
 * system already iterates. Matching hosts (wa.me, api.whatsapp.com,
 * chat.whatsapp.com, wa.link, …) would be a guess that a new short-link host
 * silently defeats.
 *
 * Filterable via roci_schema_sameas_exclude: a child that registers a
 * contact-channel platform through roci_social_platforms (a chat link of some
 * other kind) adds its key here the same way. Returning array() restores the
 * pre-7.5.0 output, where every saved platform reached sameAs. Not memoized.
 *
 * @return array List of roci_social_platforms() keys.
 */
function roci_schema_sameas_exclude() {
    return (array) apply_filters( 'roci_schema_sameas_exclude', array( 'whatsapp' ) );
}
