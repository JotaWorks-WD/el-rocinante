<?php
/**
 * Metabox — Schema Field Group
 *
 * Registers the Schema JSON-LD field group for posts and pages.
 * Paste complete JSON-LD into the textarea — script tags are added automatically.
 *
 * The field supports the {{home}} authoring token, expanded at render time
 * by inc/schema/schema-tokens.php. Registration only — this file holds no
 * validation: the live check is in the SEO Health panel and the emit-time
 * failsafe is in header.php.
 *
 * File:    inc/metabox/metabox-schema-fields.php
 * Version: 1.3.0
 * Updated: 2026-09-04
 *
 * @package ElRocinante
 */


// ============================================================
// METABOX — SCHEMA FIELD GROUP
// ============================================================

add_filter( 'rwmb_meta_boxes', function( $meta_boxes ) {

    $meta_boxes[] = array(
        'title'      => __( 'Schema Settings', 'rocinante' ),
        'id'         => 'roci_schema_fields',
        'post_types' => roci_get_schema_post_types(),
        'context'    => 'normal',
        'priority'   => 'default',
        'fields'     => array(

            array(
                'id'         => 'roci_schema_json',
                'name'       => __( 'Schema JSON-LD', 'rocinante' ),
                'type'       => 'textarea',
                'desc'       => __( 'Paste your complete JSON-LD schema here. Do not include &lt;script&gt; tags — those are added automatically. Use {{home}} for the site URL, e.g. {{home}}/#organization — it resolves automatically and stays correct after launch. The SEO Health panel shows whether it is valid JSON; an invalid paste is not emitted on the front end.', 'rocinante' ),
                'rows'       => 15,
                'attributes' => array(
                    'id'          => 'roci_schema_json',
                    'placeholder' => '{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "Business Name"
}',
                ),
            ),

        ),
    );

    return $meta_boxes;

} );