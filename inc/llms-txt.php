<?php
/**
 * llms.txt — an opt-in /llms.txt built from the site's own pages and posts
 *
 * OFF BY DEFAULT. Theme Settings → SEO → llms.txt ("Serve /llms.txt") turns it
 * on. When on, a request for /llms.txt gets a plain-text Markdown file in the
 * llmstxt.org shape:
 *
 *   # {site name}
 *
 *   > {Business → Description, when set}
 *
 *   ## Pages
 *
 *   - [Title](permalink): meta description
 *
 *   ## Posts
 *   …
 *
 * Or, when "Custom llms.txt" is filled, exactly that text instead.
 *
 * NEVER SERVED WHILE blog_public IS 0 ("Discourage search engines"), the same
 * staging switch the robots meta obeys. A physical llms.txt in the site root
 * always wins: the web server answers it before WordPress loads.
 *
 * NO REWRITE RULE, so there is nothing to flush and nothing to collide with a
 * child's own rewrites. The request is caught on parse_request — see
 * roci_llms_txt_maybe_serve(). Anything else falls through to WordPress,
 * which 404s /llms.txt exactly as before.
 *
 * Filters:
 *   roci_llms_post_types( array( 'page', 'post' ) ) — the sections, in order.
 *     Each type gets its own "## {plural label}" section.
 *   roci_llms_max_links( 40 ) — the total number of links across sections.
 *   roci_llms_txt( $text ) — the final text, last word.
 * None is memoized.
 *
 * File:    inc/llms-txt.php
 * Version: 1.0.0
 * Updated: 2026-11-18
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// THE GATE
// ============================================================

/**
 * roci_llms_txt_enabled()
 *
 * True when the site serves /llms.txt: the setting is on AND search engines
 * are not discouraged.
 *
 * @return bool
 */
function roci_llms_txt_enabled() {
    return '1' === (string) roci_setting( 'seo', 'llms_enabled', '' ) && (bool) get_option( 'blog_public' );
}


// ============================================================
// THE CONTENT
// ============================================================

/**
 * roci_llms_txt_post_types()
 *
 * @return string[] Registered post types to list, in section order.
 */
function roci_llms_txt_post_types() {
    $types = apply_filters( 'roci_llms_post_types', array( 'page', 'post' ) );
    $types = is_array( $types ) ? array_values( array_filter( $types, 'is_string' ) ) : array();

    return array_values( array_filter( $types, 'post_type_exists' ) );
}

/**
 * roci_llms_txt_clean()
 *
 * Internal. Plain text on one line: tags stripped, entities decoded, every
 * run of whitespace (line breaks included) collapsed to one space.
 *
 * @param  string $text
 * @return string
 */
function roci_llms_txt_clean( $text ) {
    $text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );

    return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * roci_llms_txt_ids()
 *
 * Internal. One post type's IDs in listing order: published and not
 * password-protected. Pages: the static front page first, then menu_order,
 * then title. Every other type: newest first. Robots are checked by the
 * caller, which stops at the cap.
 *
 * suppress_filters stays on (get_posts()' default), so a child's
 * pre_get_posts — a search tweak, say — never reshapes this list.
 *
 * @param  string $type
 * @return int[]
 */
function roci_llms_txt_ids( $type ) {
    $args = array(
        'post_type'        => $type,
        'post_status'      => 'publish',
        'has_password'     => false,
        'numberposts'      => -1,
        'fields'           => 'ids',
        'no_found_rows'    => true,
    );

    if ( 'page' === $type ) {
        $args['orderby'] = array( 'menu_order' => 'ASC', 'title' => 'ASC' );
    } else {
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
    }

    $ids = array_map( 'intval', (array) get_posts( $args ) );

    if ( 'page' === $type && 'page' === get_option( 'show_on_front' ) ) {
        $front = (int) get_option( 'page_on_front' );

        if ( $front && in_array( $front, $ids, true ) ) {
            $ids = array_merge( array( $front ), array_values( array_diff( $ids, array( $front ) ) ) );
        }
    }

    return $ids;
}

/**
 * roci_llms_txt_build()
 *
 * The automatic file. A post is listed only when roci_effective_robots() for
 * it contains no "noindex" — called outside the main query, so it is the
 * page's own field, then blog_public.
 *
 * @return string
 */
function roci_llms_txt_build() {
    $name = roci_llms_txt_clean( roci_setting( 'business', 'name', get_bloginfo( 'name' ) ) );
    $desc = roci_llms_txt_clean( roci_setting( 'business', 'description', '' ) );
    $cap  = (int) apply_filters( 'roci_llms_max_links', 40 );

    $out = array( '# ' . $name );

    if ( '' !== $desc ) {
        $out[] = '';
        $out[] = '> ' . $desc;
    }

    $count = 0;

    foreach ( roci_llms_txt_post_types() as $type ) {
        if ( $count >= $cap ) {
            break;
        }

        $lines = array();

        foreach ( roci_llms_txt_ids( $type ) as $id ) {
            if ( $count >= $cap ) {
                break;
            }
            if ( false !== strpos( (string) roci_effective_robots( $id ), 'noindex' ) ) {
                continue;
            }

            $title = roci_llms_txt_clean( get_the_title( $id ) );
            $url   = (string) get_permalink( $id );

            if ( '' === $title || '' === $url ) {
                continue;
            }

            $meta = roci_llms_txt_clean( roci_get_field( 'roci_meta_description', $id ) );

            $lines[] = '- [' . $title . '](' . $url . ')' . ( '' !== $meta ? ': ' . $meta : '' );
            $count++;
        }

        if ( $lines ) {
            $object = get_post_type_object( $type );
            $label  = ( $object && ! empty( $object->labels->name ) ) ? $object->labels->name : $type;

            $out[] = '';
            $out[] = '## ' . roci_llms_txt_clean( $label );
            $out[] = '';
            $out   = array_merge( $out, $lines );
        }
    }

    return implode( "\n", $out ) . "\n";
}

/**
 * roci_llms_txt_text()
 *
 * The text /llms.txt serves: the custom text when set, as-is, else the
 * automatic build — then roci_llms_txt, last word.
 *
 * @return string
 */
function roci_llms_txt_text() {
    $override = (string) roci_setting( 'seo', 'llms_override', '' );
    $text     = '' !== trim( $override ) ? $override : roci_llms_txt_build();

    return (string) apply_filters( 'roci_llms_txt', $text );
}


// ============================================================
// SERVING
// ============================================================

/**
 * roci_llms_txt_maybe_serve()
 *
 * On parse_request, priority 0. $wp->request is the path WordPress has just
 * parsed, relative to the home path, slashes trimmed — so "llms.txt" matches
 * /llms.txt on a root install and /blog/llms.txt on a site whose home is
 * /blog/. It runs before the main query, the template and every wp_head
 * callback, so the file costs one settings read and its own queries.
 *
 * (With plain permalinks there are no rewrite rules, $wp->request stays empty
 * and nothing is served; the server's own 404 answers before WordPress
 * anyway.)
 *
 * @param WP $wp
 */
function roci_llms_txt_maybe_serve( $wp ) {
    if ( ! isset( $wp->request ) || 'llms.txt' !== $wp->request || ! roci_llms_txt_enabled() ) {
        return;
    }

    $text = roci_llms_txt_text();

    // A page cache must never keep a stale copy (the settings save also purges
    // it), and the file itself is not a search result.
    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'roci llms.txt' );
    status_header( 200 );
    header( 'Content-Type: text/plain; charset=utf-8' );
    header( 'X-Robots-Tag: noindex, follow' );

    echo $text; // Plain text, served as text/plain; nothing here is HTML.
    exit;
}
add_action( 'parse_request', 'roci_llms_txt_maybe_serve', 0 );

/**
 * roci_llms_txt_purge()
 *
 * On every save of Theme Settings → SEO: ask LiteSpeed to drop any cached
 * /llms.txt. A do_action() nothing listens to when LiteSpeed is absent.
 */
function roci_llms_txt_purge() {
    do_action( 'litespeed_purge_url', home_url( '/llms.txt' ) );
}
add_action( 'add_option_roci_seo', 'roci_llms_txt_purge' );
add_action( 'update_option_roci_seo', 'roci_llms_txt_purge' );
