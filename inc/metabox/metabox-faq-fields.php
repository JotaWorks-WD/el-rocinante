<?php
/**
 * Metabox — FAQ Field Group
 *
 * Registers a cloneable FAQ group field (question + answer pairs)
 * on posts and pages. Intended to expand to custom post types
 * such as 'tour' and 'property' when those are registered.
 *
 * Field data is consumed by jw_faq_schema() in inc/helpers.php
 * for JSON-LD output, and by template-parts/faq.php in the
 * child theme for front-end accordion rendering.
 *
 * File:    inc/metabox/metabox-faq-fields.php
 * Version: 1.0.0
 * Updated: 2026-05-07
 *
 * @package ElRocinante
 */


// ============================================================
// METABOX — FAQ FIELD GROUP
// ============================================================

add_filter( 'rwmb_meta_boxes', function( $meta_boxes ) {

    // Post types that show the FAQ metabox.
    // Add 'tour', 'property', etc. here when those CPTs are registered.
    $post_types = apply_filters( 'jw_faq_post_types', array( 'post', 'page' ) );

    $meta_boxes[] = array(
        'title'      => __( 'FAQ Items', 'rocinante' ),
        'id'         => 'jw_faq_group',
        'post_types' => $post_types,
        'context'    => 'normal',
        'priority'   => 'default',
        'fields'     => array(

            array(
                'id'          => 'jw_faq_items',
                'type'        => 'group',
                'clone'       => true,
                'sort_clone'  => true,
                'collapsible' => true,
                'group_title' => array( 'field' => 'faq_question' ),
                'add_button'  => __( '+ Add FAQ Item', 'rocinante' ),
                'fields'      => array(

                    array(
                        'id'         => 'faq_question',
                        'name'       => __( 'Question', 'rocinante' ),
                        'type'       => 'text',
                        'size'       => 80,
                        'attributes' => array(
                            'placeholder' => __( 'e.g. What is included in the tour?', 'rocinante' ),
                        ),
                    ),

                    array(
                        'id'         => 'faq_answer',
                        'name'       => __( 'Answer', 'rocinante' ),
                        'type'       => 'textarea',
                        'rows'       => 4,
                        'attributes' => array(
                            'placeholder' => __( 'Enter the answer here...', 'rocinante' ),
                        ),
                    ),

                ),
            ),

        ),
    );

    return $meta_boxes;

} );