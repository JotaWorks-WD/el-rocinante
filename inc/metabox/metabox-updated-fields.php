<?php
/**
 * Metabox — Updated Date (opt-in "Updated" line on posts and pages)
 *
 * A side box, "Updated date", on every public post type except attachment
 * (filter roci_updated_date_post_types): a "Show updated date" checkbox and an
 * optional "What changed" line. Nothing shows until the box is ticked.
 *
 * Also the reader and the renderer, kept beside the fields they read:
 *   roci_post_updated( $post_id )      — array( show, iso, date, what )
 *   roci_updated_date_line( $post_id ) — echoes the line (and the note), or
 *                                        nothing while the box is unticked.
 * The parent calls the renderer in template-parts/entry-meta.php (posts) and
 * page.php (default pages); a child calls it from its own templates.
 *
 * The date is always the REAL last-modified date. Structured data never reads
 * this box: the BlogPosting's dateModified is the real modified time whether
 * or not the line shows.
 *
 * File:    inc/metabox/metabox-updated-fields.php
 * Version: 1.0.0
 * Updated: 2026-11-18
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// FIELDS
// ============================================================

/**
 * roci_updated_date_post_types()
 *
 * The post types that get the "Updated date" box: every public post type
 * except attachment, filtered by roci_updated_date_post_types.
 *
 * NOT MEMOISED, and only called from the rwmb_meta_boxes callback below, so
 * the filter is read at meta box time — a child's add_filter() in its
 * functions.php is always seen.
 *
 * @return string[] Post type names.
 */
function roci_updated_date_post_types() {
    $types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
    $types = apply_filters( 'roci_updated_date_post_types', $types );

    return is_array( $types ) ? array_values( array_filter( $types, 'is_string' ) ) : array();
}

add_filter( 'rwmb_meta_boxes', function( $meta_boxes ) {

    $post_types = roci_updated_date_post_types();

    if ( ! $post_types ) {
        return $meta_boxes;
    }

    $meta_boxes[] = array(
        'id'         => 'roci-updated-date',
        'title'      => __( 'Updated date', 'rocinante' ),
        'post_types' => $post_types,
        'context'    => 'side',
        'priority'   => 'default',
        'fields'     => array(
            array(
                'id'   => 'roci_post_show_updated',
                'type' => 'checkbox',
                'name' => __( 'Show updated date', 'rocinante' ),
                'desc' => __( 'Tick only after a real content change (not a typo fix). Shows "Updated {date}" under the title, using the date this was last saved. Search engines always get the real last-modified date.', 'rocinante' ),
            ),
            array(
                'id'   => 'roci_post_what_changed',
                'type' => 'text',
                'name' => __( 'What changed', 'rocinante' ),
                'desc' => __( 'Optional, one short line, shown with the updated date only.', 'rocinante' ),
            ),
        ),
    );

    return $meta_boxes;
} );


// ============================================================
// READER + RENDERER
// ============================================================

/**
 * roci_post_updated()
 *
 * The "Updated" data for a post. Everything but 'show' is '' while the box is
 * unticked, so a caller can never print a date the editor did not ask for.
 *
 * @param  int $post_id Post ID; 0 = the current post.
 * @return array        array( 'show' => bool, 'iso' => string (ISO 8601),
 *                      'date' => string (the site's date format),
 *                      'what' => string (trimmed, tags stripped) ).
 */
function roci_post_updated( $post_id = 0 ) {
    $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
    $show    = $post_id && '1' === (string) get_post_meta( $post_id, 'roci_post_show_updated', true );

    return array(
        'show' => $show,
        'iso'  => $show ? (string) get_the_modified_date( 'c', $post_id ) : '',
        'date' => $show ? (string) get_the_modified_date( get_option( 'date_format' ), $post_id ) : '',
        'what' => $show ? trim( wp_strip_all_tags( (string) get_post_meta( $post_id, 'roci_post_what_changed', true ) ) ) : '',
    );
}

/**
 * roci_updated_date_line()
 *
 * Echoes the updated line for a post, or nothing while its box is unticked:
 *
 *   <span class="entry-updated">Updated <time class="updated" datetime="…">…</time></span>
 *   <p class="entry-changed"><span class="entry-changed-label">What changed:</span> …</p>
 *
 * The span is inline, so it sits next to the published date in the entry
 * meta; the note is a block under it, printed only with a non-empty "What
 * changed". The class names follow the parent's entry markup (entry-meta,
 * entry-date); the parent ships no styles for them.
 *
 * @param int $post_id Post ID; 0 = the current post.
 */
function roci_updated_date_line( $post_id = 0 ) {
    $updated = roci_post_updated( $post_id );

    if ( ! $updated['show'] || '' === $updated['date'] ) {
        return;
    }

    echo '<span class="entry-updated">';
    printf(
        /* translators: %s: the date the post was last updated. */
        esc_html__( 'Updated %s', 'rocinante' ),
        '<time class="updated" datetime="' . esc_attr( $updated['iso'] ) . '">' . esc_html( $updated['date'] ) . '</time>'
    );
    echo '</span>';

    if ( '' !== $updated['what'] ) {
        echo '<p class="entry-changed"><span class="entry-changed-label">' . esc_html__( 'What changed:', 'rocinante' ) . '</span> ' . esc_html( $updated['what'] ) . '</p>';
    }
}
