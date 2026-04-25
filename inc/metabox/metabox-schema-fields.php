<?php

// ============================================================
// METABOX — SCHEMA FIELD GROUP
// ============================================================

add_filter( 'rwmb_meta_boxes', function( $meta_boxes ) {

    $meta_boxes[] = array(
        'title'      => __( 'Schema Settings', 'rocinante' ),
        'id'         => 'roci_schema_fields',
        'post_types' => array( 'post', 'page' ),
        'context'    => 'normal',
        'priority'   => 'default',
        'fields'     => array(

            array(
                'id'         => 'roci_schema_json',
                'name'       => __( 'Schema JSON-LD', 'rocinante' ),
                'type'       => 'textarea',
                'desc'       => __( 'Paste your complete JSON-LD schema here. Do not include &lt;script&gt; tags — those are added automatically.', 'rocinante' ),
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