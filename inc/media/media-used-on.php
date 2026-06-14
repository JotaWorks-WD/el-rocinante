<?php
/**
 * Media — "Used On" Column
 *
 * Adds a "Used On" column to the Media list table (upload.php?mode=list)
 * showing which MB Pro Settings Pages reference each attachment. Gives
 * devs an at-a-glance signal for where an image is deployed and flags
 * accidental reuse. Does not fire on the grid/thumbnail view.
 *
 * Fork-safe design: the option-name prefix is resolved through
 * roci_page_option_prefix() (filterable via 'roci_page_option_prefix'),
 * and page labels are resolved from the live mb_settings_pages filter
 * rather than any hardcoded slug → title map. No child-specific prefix
 * or page vocabulary is embedded in this file — it works identically
 * for every child theme that registers Settings Pages via MB Pro.
 *
 * File:    inc/media/media-used-on.php
 * Version: 1.0.1
 * Updated: 2026-06-14
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// SCAN HELPER
// ============================================================

/**
 * Return the option_names of all page-settings blobs that reference
 * a given attachment ID.
 *
 * Queries wp_options ONCE per request (static cache) for all rows whose
 * option_name begins with the active page-option prefix, then scans each
 * blob in memory for every subsequent call. Two field shapes are walked:
 *
 *   Shape A — top-level bare int (any field name):
 *       $blob['hero_photo'] = 38
 *
 *   Shape B — 'photo' key inside a clone-group row (depth ≤ 2):
 *       $blob['fleet_cards'][0]['photo'] = 38
 *
 * For Shape A every top-level numeric value is a candidate — field names
 * are not checked because they vary per page and per child theme. For
 * Shape B every top-level array is treated as a potential clone group and
 * its rows are checked for a numeric 'photo' key; 'photo' is the contract
 * for the nested-image sub-key (per CLAUDE.md known-facts). No other
 * sub-keys and no depth > 2 are walked.
 *
 * Both shapes gate on wp_attachment_is_image() to reject coincidental
 * integers (stored counts, years, sort orders, etc.) that happen to equal
 * the attachment ID but are not image references. The function also
 * short-circuits at the top if the target attachment is not itself an
 * image — non-image attachments cannot appear in image fields.
 *
 * @param  int      $attachment_id  Attachment post ID to look for.
 * @return string[]                 Flat list of option_names containing the ID.
 */
function roci_pages_using_attachment( $attachment_id ) {

    $attachment_id = (int) $attachment_id;

    // Short-circuit: non-image attachments cannot appear in image fields.
    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return array();
    }

    static $page_options = null;

    if ( $page_options === null ) {
        global $wpdb;
        $prefix       = roci_page_option_prefix();
        $like         = $wpdb->esc_like( $prefix ) . '%';
        $rows         = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            ),
            ARRAY_A
        );
        $page_options = array();
        foreach ( $rows as $row ) {
            $blob = maybe_unserialize( $row['option_value'] );
            if ( is_array( $blob ) ) {
                $page_options[ $row['option_name'] ] = $blob;
            }
        }
    }

    $found = array();

    foreach ( $page_options as $option_name => $blob ) {
        $hit = false;

        foreach ( $blob as $value ) {
            if ( $hit ) {
                break;
            }

            // Shape A: bare numeric value at the top level of the blob.
            if ( is_numeric( $value )
                && (int) $value === $attachment_id
                && wp_attachment_is_image( (int) $value )
            ) {
                $hit = true;
                break;
            }

            // Shape B: clone-group array — each row may carry a 'photo' key.
            if ( is_array( $value ) ) {
                foreach ( $value as $row ) {
                    if ( is_array( $row )
                        && isset( $row['photo'] )
                        && is_numeric( $row['photo'] )
                        && (int) $row['photo'] === $attachment_id
                        && wp_attachment_is_image( (int) $row['photo'] )
                    ) {
                        $hit = true;
                        break;
                    }
                }
            }
        }

        if ( $hit ) {
            $found[] = $option_name;
        }
    }

    return $found;
}


// ============================================================
// LABEL RESOLVER
// ============================================================

/**
 * Resolve the display label for a page-settings option_name.
 *
 * Reads the live mb_settings_pages filter — MB Pro's own registration
 * hook — and finds the config entry whose 'option_name' matches exactly.
 * Returns that entry's 'menu_title', which is the same string the admin
 * submenu shows, so the column label reads naturally to the user.
 *
 * Falls back to a humanized version of the option-name suffix (the part
 * after the active prefix) when no registered config matches. This
 * handles stale wp_options rows whose Settings Page registration has
 * been removed: the column still renders something legible rather than
 * a raw database key or a fatal.
 *
 * @param  string $option_name  Full wp_options key (e.g. 'roci_page_home').
 * @return string               Human-readable label for display.
 */
function roci_label_for_page_option( $option_name ) {

    $configs = apply_filters( 'mb_settings_pages', array() );
    foreach ( $configs as $config ) {
        if ( isset( $config['option_name'] )
            && $config['option_name'] === $option_name
            && ! empty( $config['menu_title'] )
        ) {
            return $config['menu_title'];
        }
    }

    // Fallback: strip the prefix and humanize the remaining slug.
    $prefix = roci_page_option_prefix();
    $suffix = ( strpos( $option_name, $prefix ) === 0 )
        ? substr( $option_name, strlen( $prefix ) )
        : $option_name;

    return ucwords( str_replace( array( '-', '_' ), ' ', $suffix ) );
}


// ============================================================
// COLUMN REGISTRATION
// ============================================================

add_filter( 'manage_media_columns', function( $columns ) {
    $columns['roci_used_on'] = __( 'Used On', 'rocinante' );
    return $columns;
} );

add_action( 'manage_media_custom_column', function( $column_name, $post_id ) {
    if ( 'roci_used_on' !== $column_name ) {
        return;
    }
    $matches = roci_pages_using_attachment( (int) $post_id );
    if ( empty( $matches ) ) {
        echo '&mdash;';
        return;
    }
    $labels = array_map( 'roci_label_for_page_option', $matches );
    echo esc_html( implode( ', ', $labels ) );
}, 10, 2 );
