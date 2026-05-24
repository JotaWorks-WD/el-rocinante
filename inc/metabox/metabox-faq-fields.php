<?php
/**
 * Metabox — FAQ Field Group
 *
 * Registers a cloneable FAQ group field (question + answer pairs)
 * on posts and pages. Intended to expand to custom post types
 * registered by child themes via roci_get_faq_post_types().
 *
 * Field data is consumed by jw_faq_schema() in inc/helpers.php
 * for JSON-LD output, and by template-parts/faq.php in the
 * child theme for front-end accordion rendering.
 *
 * Note: collapsible groups require Meta Box Pro — not used here.
 *
 * File:    inc/metabox/metabox-faq-fields.php
 * Version: 1.2.2
 * Updated: 2026-05-24
 *
 * @package ElRocinante
 */


// ============================================================
// METABOX — FAQ FIELD GROUP
// ============================================================

add_filter( 'rwmb_meta_boxes', function( $meta_boxes ) {

    $meta_boxes[] = array(
        'title'      => __( 'FAQ Items', 'rocinante' ),
        'id'         => 'jw_faq_group',
        'post_types' => roci_get_faq_post_types(),
        'context'    => 'normal',
        'priority'   => 'default',
        'fields'     => array(

            array(
                'id'         => 'jw_faq_items',
                'type'       => 'group',
                'clone'      => true,
                'sort_clone' => true,
                'add_button' => __( '+ Add FAQ Item', 'rocinante' ),
                'fields'     => array(

                    array(
                        'id'         => 'faq_question',
                        'name'       => __( 'Question', 'rocinante' ),
                        'type'       => 'text',
                        'size'       => 80,
                        'attributes' => array(
                            'placeholder' => __( 'Example: What is your cancellation policy?', 'rocinante' ),
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