<?php
/**
 * Metabox — SEO Field Group
 *
 * Registers the SEO Settings metabox on pages and posts.
 * Includes target query, meta title/description, canonical,
 * robots, OG image, and conditionally the preview/health panels.
 *
 * File:    metabox-seo-fields.php
 * Version: 1.3.2
 * Updated: 2026-07-08
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
                'maxlength' => 80,
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

        // OG Image Alt Text
        array(
            'id'         => 'roci_og_image_alt',
            'name'       => __( 'OG Image Alt Text', 'rocinante' ),
            'type'       => 'text',
            'desc'       => __( 'Alt text for the social share image. Auto-fills from the image\'s own alt text when available; set manually here for the site-default image.', 'rocinante' ),
            'size'       => 80,
            'attributes' => array(
                'id' => 'roci_og_image_alt',
            ),
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
// SLUG FIELD — guard the meta write (save-time value filter)
// ============================================================
//
// The display-time filter above was originally chosen to dodge the
// save-time filter, because MB Pro passes the $field CONFIG ARRAY as the
// value on REST/Gutenberg saves where the input is absent — and an
// UNGUARDED save-time filter would let that serialized config persist to
// wp_postmeta. But avoiding the hook left the meta write itself
// unguarded: MB Pro still serializes the config array into the roci_slug
// meta upstream (confirmed via DB — the stored value was the
// "autocomplete...datalist..." config cruft). The is_string() guard in
// roci_save_slug_field() only protects the $_POST read, not the meta.
//
// This filter closes that gap at the actual write point. rwmb_{id}_value
// fires before MB Pro stores the value; is_string() rejects the config
// array fallback so it can never reach wp_postmeta. Verified against the
// Meta Box docs: rwmb_{$field_id}_value fires before save with args
// ( $new, $field, $old, $object_id ) — register 4. See CLAUDE.md 13.1.

add_filter( 'rwmb_roci_slug_value', function( $new, $field, $old, $object_id ) {
    // MB Pro passes the $field CONFIG ARRAY as the value on REST/Gutenberg
    // saves where the input is absent. Reject anything that isn't a real
    // scalar string so the serialized config can never persist to meta.
    if ( ! is_string( $new ) ) {
        return is_string( $old ) ? $old : '';
    }
    $new = trim( $new );
    return sanitize_title( $new );
}, 10, 4 );


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
    // Must be present AND a real scalar string. On REST/Gutenberg/no-input
    // saves MB Pro passes the field config ARRAY as the value; is_string()
    // rejects that fallback so it can never reach sanitize_title(). (Fixes
    // the post_name config-array corruption — see CLAUDE.md 13.1.)
    if ( ! isset( $_POST['roci_slug'] ) || ! is_string( $_POST['roci_slug'] ) ) {
        return;
    }

    $raw = trim( wp_unslash( $_POST['roci_slug'] ) );

    // Empty submission means "no override intended" — leave post_name alone
    // rather than nuking an existing slug.
    if ( $raw === '' ) {
        return;
    }

    $new_slug = sanitize_title( $raw );

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