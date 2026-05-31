<?php
/**
 * Archive Suppression — Default WP archive 404 enforcement
 *
 * Suppresses author, date, and default taxonomy (category + tag) archives
 * by forcing a 404 response via pre_get_posts. Each type is independently
 * filterable so child themes can re-enable per-site.
 *
 * Filters dispatched:
 *   roci_suppress_author_archive   — bool, default true
 *   roci_suppress_date_archive     — bool, default true
 *   roci_suppress_taxonomy_archive — bool, default true (covers category + tag)
 *
 * CPT archives are unaffected — those are controlled per-CPT via
 * has_archive at registration.
 *
 * File:    inc/archive-suppression.php
 * Version: 1.0.0
 * Updated: 2026-05-31
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// SUPPRESS DEFAULT ARCHIVES
// ============================================================

/**
 * Suppress default WP archives by default.
 *
 * Author, date, and default taxonomy (category + tag) archives all 404
 * unless a child theme re-enables them via the corresponding filter.
 *
 * CPT archives are unaffected — those are controlled per-CPT via
 * has_archive at registration.
 *
 * Filters (each defaults to true; return false to re-enable that archive):
 * - roci_suppress_author_archive
 * - roci_suppress_date_archive
 * - roci_suppress_taxonomy_archive  (covers category and tag together)
 *
 * @param WP_Query $query The main query object.
 */
function roci_suppress_default_archives( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $suppress = false;

    if ( $query->is_author() && apply_filters( 'roci_suppress_author_archive', true ) ) {
        $suppress = true;
    } elseif ( $query->is_date() && apply_filters( 'roci_suppress_date_archive', true ) ) {
        $suppress = true;
    } elseif ( ( $query->is_category() || $query->is_tag() ) && apply_filters( 'roci_suppress_taxonomy_archive', true ) ) {
        $suppress = true;
    }

    if ( $suppress ) {
        $query->set_404();
        status_header( 404 );
        nocache_headers();
    }
}
add_action( 'pre_get_posts', 'roci_suppress_default_archives' );
