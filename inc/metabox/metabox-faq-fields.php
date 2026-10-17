<?php
/**
 * Metabox — FAQ Field Group
 *
 * Registers a cloneable FAQ group field (question + answer pairs)
 * on posts and pages. Intended to expand to custom post types
 * registered by child themes via roci_get_faq_post_types().
 *
 * Field data is read through roci_get_faq_items() (inc/helpers.php), by
 * the parent's template-parts/faq.php for the accordion and by
 * jw_faq_schema() for the JSON-LD.
 *
 * v1.3.0 adds the optional per-page FAQ Heading (jw_faq_heading) at the top
 * of the same box — see roci_faq_heading_field() below.
 *
 * Note: collapsible groups require Meta Box Pro — not used here.
 *
 * File:    inc/metabox/metabox-faq-fields.php
 * Version: 1.3.0
 * Updated: 2026-10-17
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


// ============================================================
// METABOX — PER-PAGE FAQ HEADING
// ============================================================

/**
 * Add the optional FAQ Heading field to the top of the FAQ Items box.
 *
 * Stored as post meta jw_faq_heading. template-parts/faq.php uses it as the
 * section's H2 when set; blank keeps the default (see the part's docblock for
 * the full order and the roci_faq_heading filter).
 *
 * ⚠ PRIORITY 30, AND SKIPPED WHEN THE BOX ALREADY HAS THE FIELD. A child may
 *   still inject its own jw_faq_heading field into this box at priority 20
 *   (the reference child does, from before the parent owned the part). The
 *   parent then adds nothing, so the edit screen never shows two inputs with
 *   one name. Once the child drops its injector, this one takes over the SAME
 *   meta key, so every heading already saved survives. Do not lower the
 *   priority below the child's.
 *
 * ⚠ NO 'std'. The field must save empty: a saved default would make every
 *   page override itself with a string the editor never chose.
 *
 * Inherits the box's post_types (roci_get_faq_post_types()), so it lands on
 * exactly the types that carry FAQ items.
 *
 * @param  array $meta_boxes Meta boxes registered so far.
 * @return array
 */
function roci_faq_heading_field( $meta_boxes ) {

    foreach ( $meta_boxes as &$box ) {

        if ( ! isset( $box['id'] ) || 'jw_faq_group' !== $box['id'] ) {
            continue;
        }

        $fields = isset( $box['fields'] ) && is_array( $box['fields'] ) ? $box['fields'] : array();

        foreach ( $fields as $field ) {
            if ( isset( $field['id'] ) && 'jw_faq_heading' === $field['id'] ) {
                break 2; // already present — a child registered it
            }
        }

        array_unshift(
            $fields,
            array(
                'id'   => 'jw_faq_heading',
                'name' => __( 'FAQ Heading', 'rocinante' ),
                'type' => 'text',
                'size' => 80,
                'desc' => __( 'Optional. The heading above this page\'s questions. Leave blank for the default.', 'rocinante' ),
            )
        );

        $box['fields'] = $fields;
        break;
    }

    unset( $box );

    return $meta_boxes;
}
add_filter( 'rwmb_meta_boxes', 'roci_faq_heading_field', 30 );