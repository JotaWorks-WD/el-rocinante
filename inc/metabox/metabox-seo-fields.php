<?php
/**
 * Metabox — SEO Field Group
 *
 * Registers the SEO Settings metabox on pages and posts.
 * Includes target query, meta title/description, canonical,
 * robots, OG image, and conditionally the preview/health panels.
 *
 * File:    metabox-seo-fields.php
 * Version: 1.2.1
 * Updated: 2026-05-27
 *
 * @package ElRocinante
 */

// ============================================================
// METABOX — SEO FIELD GROUP
// ============================================================

add_filter( 'rwmb_meta_boxes', function( $meta_boxes ) {

    $show_preview     = roci_setting( 'seo', 'seo_preview', '1' );
    $default_og_image = roci_setting( 'seo', 'default_og_image', '' );

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

        // Page Slug
        array(
            'id'                => 'roci_slug',
            'name'              => __( 'Page Slug', 'rocinante' ),
            'type'              => 'text',
            'desc'              => __( 'Overrides the default WordPress permalink slug for this page. Use lowercase, hyphens only, no spaces.', 'rocinante' ),
            'size'              => 80,
            'sanitize_callback' => 'sanitize_title',
            'attributes'        => array(
                'id' => 'roci_slug',
            ),
        ),

        // Canonical URL
        array(
            'id'         => 'roci_canonical',
            'name'       => __( 'Canonical URL', 'rocinante' ),
            'type'       => 'url',
            'desc'       => __( 'Leave blank to use the default page URL.', 'rocinante' ),
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
        'post_types' => roci_get_seo_post_types(),
        'context'    => 'normal',
        'priority'   => 'high',
        'fields'     => $fields,
    );

    return $meta_boxes;

} );


// ============================================================
// SLUG FIELD — pre-populate with current post_name
// ============================================================
//
// Uses the display-time filter (rwmb_{field_id}_field_meta), NOT the
// save-time filter (rwmb_{field_id}_value). The save-time filter has
// an edge case under Gutenberg/REST saves where MB Pro can pass the
// $field configuration array as the value when $_POST[field_id] is
// missing — which, without a type guard, would persist the entire
// serialized field config to wp_postmeta. The display-time filter is
// the correct hook for pre-populating the edit form and cannot write
// to the database by construction.

add_filter( 'rwmb_roci_slug_field_meta', function( $value, $field, $saved ) {
    if ( ! $value && ! $saved ) {
        $post_id = 0;
        if ( isset( $_GET['post'] ) ) {
            $post_id = absint( $_GET['post'] );
        } elseif ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
            $post_id = $GLOBALS['post']->ID;
        }
        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post && $post->post_name ) {
                return $post->post_name;
            }
        }
    }
    return $value;
}, 10, 3 );


// ============================================================
// SLUG FIELD — sync post_name on save
// ============================================================

function roci_save_slug_field( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( ! in_array( $post->post_type, roci_get_seo_post_types(), true ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( ! isset( $_POST['roci_slug'] ) ) {
        return;
    }

    $new_slug = sanitize_title( wp_unslash( $_POST['roci_slug'] ) );

    if ( ! $new_slug || $new_slug === $post->post_name ) {
        return;
    }

    remove_action( 'save_post', 'roci_save_slug_field', 20 );

    wp_update_post( [
        'ID'        => $post_id,
        'post_name' => $new_slug,
    ] );

    add_action( 'save_post', 'roci_save_slug_field', 20, 2 );
}

add_action( 'save_post', 'roci_save_slug_field', 20, 2 );