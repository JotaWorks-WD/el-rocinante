<?php
/**
 * Business Types — Schema Type Taxonomy
 *
 * Business type taxonomy for the schema system.
 *
 * ONE SOURCE, THREE CONSUMERS: the Business settings tab (renders the type
 * selector + decides which field groups exist), the settings sanitizer
 * (whitelists which keys persist — MUST read this function, not a duplicate,
 * or child-added types render but are silently wiped on save, repeating the
 * roci_social_platforms bug), and header.php schema assembly (resolves @type
 * + emits type-specific fields). Keep these three in agreement via THIS map.
 *
 * Filterable: children may add types via roci_business_types. Any consumer
 * that lists types MUST read this function so the filter reaches all three.
 *
 * File:    inc/schema/business-types.php
 * Version: 1.0.1
 * Updated: 2026-08-08
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The business type taxonomy.
 *
 * Each entry is keyed by a storage slug — the value saved as
 * roci_business['type'] — and carries three things:
 *
 *   label  Admin-facing name, shown in the type selector. Translated — the map
 *          is only ever built at call time (admin render, schema assembly), so
 *          __() runs long after the text domain is loaded. Never call this
 *          function at file scope, where it would run too early to translate.
 *   schema The schema.org @type the slug resolves to. NOT translatable — these
 *          are schema.org identifiers, and a translated one is an invalid one.
 *          The slug is deliberately not the schema type either: it decouples the
 *          admin vocabulary from schema.org's, so a mapping can change without
 *          a data migration.
 *   fields Type-specific field keys this vertical adds on top of the universal
 *          set. An empty array means the type contributes no extra fields and
 *          differs from the others only by @type.
 *
 * Verticals are generic by design — CLAUDE.md §12.4 forbids client names or
 * client vocabulary in the parent.
 *
 * @return array Map of slug => array( label, schema, fields ).
 */
function roci_business_types() {
    return apply_filters( 'roci_business_types', array(
        'general'    => array( 'label' => __( 'General Business', 'rocinante' ),   'schema' => 'LocalBusiness',     'fields' => array() ),
        'lodging'    => array( 'label' => __( 'Lodging / Rental', 'rocinante' ),   'schema' => 'LodgingBusiness',   'fields' => array( 'numberOfRooms', 'petsAllowed', 'amenities' ) ),
        'tourism'    => array( 'label' => __( 'Tourism / Activity', 'rocinante' ), 'schema' => 'TouristAttraction', 'fields' => array() ),
        'restaurant' => array( 'label' => __( 'Restaurant', 'rocinante' ),         'schema' => 'Restaurant',        'fields' => array() ),
    ) );
}
