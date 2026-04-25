<?php

// ============================================================
// METABOX — SEO FIELD GROUP
// ============================================================

add_filter( 'rwmb_meta_boxes', function( $meta_boxes ) {

    $show_preview     = get_theme_mod( 'roci_seo_preview', '1' );
    $default_og_image = get_theme_mod( 'roci_default_og_image', '' );

    $fields = array(

        // Target Search Query
        array(
            'id'         => 'roci_target_query',
            'name'       => __( 'Target Search Query', 'rocinante' ),
            'type'       => 'text',
            'desc'       => __( 'The primary search query this page is targeting. Used to verify title, description, and slug alignment.', 'rocinante' ),
            'size'       => 80,
            'attributes' => array(
                'id' => 'roci_target_query',
            ),
        ),

        // Meta Title
        array(
            'id'         => 'roci_meta_title',
            'name'       => __( 'Meta Title', 'rocinante' ),
            'type'       => 'text',
            'desc'       => __( 'Recommended 50-60 characters. Leave blank to use post title.', 'rocinante' ),
            'size'       => 80,
            'attributes' => array(
                'maxlength' => 60,
                'id'        => 'roci_meta_title',
            ),
        ),

        // Meta Description
        array(
            'id'         => 'roci_meta_description',
            'name'       => __( 'Meta Description', 'rocinante' ),
            'type'       => 'textarea',
            'desc'       => __( 'Recommended 150-160 characters.', 'rocinante' ),
            'rows'       => 3,
            'attributes' => array(
                'maxlength' => 160,
                'id'        => 'roci_meta_description',
            ),
        ),

        // Canonical URL
        array(
            'id'   => 'roci_canonical',
            'name' => __( 'Canonical URL', 'rocinante' ),
            'type' => 'url',
            'desc' => __( 'Leave blank to use the default page URL.', 'rocinante' ),
            'attributes' => array(
                'id' => 'roci_canonical',
            ),
        ),

        // Robots
        array(
            'id'      => 'roci_robots',
            'name'    => __( 'Robots', 'rocinante' ),
            'type'    => 'select',
            'options' => array(
                'index, follow'     => 'Index, Follow (default)',
                'noindex, follow'   => 'No Index, Follow',
                'index, nofollow'   => 'Index, No Follow',
                'noindex, nofollow' => 'No Index, No Follow',
            ),
            'std' => 'index, follow',
        ),

        // OG Image
        array(
            'id'               => 'roci_og_image',
            'name'             => __( 'OG Image', 'rocinante' ),
            'type'             => 'image_advanced',
            'desc'             => __( 'Recommended 1200x630px WebP. Leave blank to use featured image or site default.', 'rocinante' ),
            'max_file_uploads' => 1,
            'force_delete'     => false,
        ),

    );

    // Attach preview and health panels if enabled
    if ( $show_preview ) {
        $fields[] = roci_seo_preview_html( $default_og_image );
        $fields[] = roci_seo_health_html( $default_og_image );
    }

    $meta_boxes[] = array(
        'title'      => __( 'SEO Settings', 'rocinante' ),
        'id'         => 'roci_seo_fields',
        'post_types' => array( 'post', 'page' ),
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => $fields,
    );

    return $meta_boxes;

} );