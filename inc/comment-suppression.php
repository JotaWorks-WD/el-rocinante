<?php
/**
 * Comment Suppression — Network-wide comment disabling
 *
 * Disables WordPress comments, pingbacks and trackbacks across every site
 * in the family. Graduated to the parent from the standalone "Disable
 * Comments Clean" plugin (v3.14, JotaWorks) that previously ran on Fish
 * Potrero only, so every child — current and future — inherits it without
 * a per-site plugin install.
 *
 * Five mechanisms, because no single one covers the whole surface:
 *
 *   1. remove_post_type_support() on admin_init strips the Discussion
 *      meta box and the Comments column from every registered post type.
 *   2. comments_open / pings_open return false, which closes the front
 *      end regardless of the per-post comment_status column.
 *   3. comments_array returns empty, hiding comments already in the
 *      database rather than only preventing new ones.
 *   4. The core comment-reply script is dequeued and deregistered.
 *   5. cancel_comment_reply_link is emptied.
 *
 * (1) alone leaves existing posts open, because comment_status is stored
 * per post and post type support is not consulted on the front end. (2)
 * alone leaves existing comments rendering. Both are needed.
 *
 * NO OPT-OUT FILTER — deliberate, not an oversight. Every site in the
 * family wants comments gone, including Fish Potrero, the only one with a
 * blog. This intentionally departs from inc/archive-suppression.php, which
 * dispatches a filter per archive type. If per-site re-enable is ever
 * wanted, add the filter then; do not add one speculatively.
 *
 * NOTE: add_theme_support( 'html5', ... ) in functions.php still lists
 * 'comment-form' and 'comment-list'. Those only choose markup format for
 * output that no longer happens, so they are left in place.
 *
 * File:    inc/comment-suppression.php
 * Version: 1.0.0
 * Updated: 2026-08-18
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// STRIP COMMENT SUPPORT FROM EVERY POST TYPE
// ============================================================

/**
 * Remove 'comments' and 'trackbacks' support from all registered post types.
 *
 * Runs on admin_init, which fires after init, so custom post types
 * registered by a child theme are already present in get_post_types().
 * This is what removes the Discussion meta box from the editor and the
 * comment bubble column from the post list tables.
 *
 * Admin-side only by design — the front end is closed by the
 * comments_open / pings_open filters below, which do not depend on post
 * type support.
 */
function roci_remove_comment_support() {
    foreach ( get_post_types() as $post_type ) {
        if ( post_type_supports( $post_type, 'comments' ) ) {
            remove_post_type_support( $post_type, 'comments' );
            remove_post_type_support( $post_type, 'trackbacks' );
        }
    }
}
add_action( 'admin_init', 'roci_remove_comment_support' );


// ============================================================
// CLOSE COMMENTS & PINGS ON THE FRONT END
// ============================================================

// Overrides the per-post comment_status / ping_status columns, so posts
// created before this shipped are closed too without a database pass.
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );


// ============================================================
// HIDE EXISTING COMMENTS
// ============================================================

// Closing comments stops new ones; it does not hide the ones already
// stored. Emptying the array is what suppresses historical comments.
add_filter( 'comments_array', '__return_empty_array', 20 );


// ============================================================
// DROP THE COMMENT-REPLY SCRIPT
// ============================================================

/**
 * Dequeue and deregister the core comment-reply script.
 *
 * Dequeue and deregister are separate operations: deregistering removes
 * the handle from the registry but does not pull an already-queued handle
 * out of the queue, so a theme or plugin that enqueued it earlier in the
 * request would still print it. Both calls are needed.
 *
 * Priority 100 so this runs after the wp_enqueue_scripts callbacks in the
 * parent and both children, all of which sit at the default 10.
 */
function roci_remove_comment_reply_script() {
    wp_dequeue_script( 'comment-reply' );
    wp_deregister_script( 'comment-reply' );
}
add_action( 'wp_enqueue_scripts', 'roci_remove_comment_reply_script', 100 );


// ============================================================
// SUPPRESS THE CANCEL-REPLY LINK
// ============================================================

// Emptied via the filter rather than removed via remove_action():
// cancel_comment_reply_link exists in core only as a filter
// (wp-includes/comment-template.php), and core registers no action of
// that name, so a remove_action() call would be a silent no-op.
add_filter( 'cancel_comment_reply_link', '__return_empty_string', 20 );
