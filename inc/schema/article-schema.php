<?php
/**
 * Article Schema — BlogPosting JSON-LD and article:* meta for single posts
 *
 * Builds the BlogPosting node header.php prints on a single post (under the
 * `<!-- Schema JSON-LD — Article -->` banner), and the article:published_time /
 * article:modified_time values it prints in the Open Graph block. Content-
 * agnostic: every value comes from the post, its featured image, its author
 * and the site's existing organisation node.
 *
 * Where it applies: is_singular() on a post type in
 * roci_article_schema_post_types() (default array( 'post' )).
 *
 * The node:
 *   headline          the post title, tags stripped, entities decoded
 *   datePublished     the post's local time, ISO 8601 with offset
 *   dateModified      the REAL modified time, always — whatever a child shows
 *                     on the page ("Updated" lines are display only)
 *   author            a Person: the post author's display name (+ url when
 *                     the roci_article_author filter supplies one)
 *   publisher         a REFERENCE to the site-level node, { "@id": home/#organization,
 *                     "name" } when Theme Settings → Business → name is set
 *                     (that is the gate header.php's LocalBusiness block uses,
 *                     so the @id always resolves on the page); otherwise an
 *                     Organization with the site name and home URL
 *   image             the featured image (ImageObject, url + width + height),
 *                     omitted when the post has none
 *   mainEntityOfPage  a WebPage whose @id is the page's canonical
 *
 * Filters:
 *   roci_article_schema_post_types( array( 'post' ) ) — which post types get it.
 *   roci_article_author( $author, $post_id ) — the author node; return an
 *     array (e.g. add 'url' => an About page), or an empty value to omit it.
 *   roci_article_schema( $data, $post_id ) — the whole node, last word;
 *     extend it, or return false / array() to print nothing.
 * None is memoized.
 *
 * A post whose own Schema JSON-LD field already carries an Article-family
 * @type (Article, BlogPosting, NewsArticle, TechArticle) gets no automatic
 * node, so a hand-pasted one is never doubled. header.php makes that test with
 * roci_schema_has_article_type().
 *
 * File:    inc/schema/article-schema.php
 * Version: 1.0.0
 * Updated: 2026-11-04
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * roci_article_schema_post_types()
 *
 * The post types that get the BlogPosting node and the article:* meta.
 *
 * @return string[] Post type names.
 */
function roci_article_schema_post_types() {
    $types = apply_filters( 'roci_article_schema_post_types', array( 'post' ) );

    return is_array( $types ) ? array_values( array_filter( $types, 'is_string' ) ) : array();
}


/**
 * roci_is_article_route()
 *
 * True on a singular route whose post type is in
 * roci_article_schema_post_types(). The one test for the JSON-LD and the
 * article:* meta, so the two always appear together.
 *
 * @return bool
 */
function roci_is_article_route() {
    return is_singular() && in_array( get_post_type( get_queried_object_id() ), roci_article_schema_post_types(), true );
}


/**
 * roci_article_times()
 *
 * The published and modified times of a post, ISO 8601 with the site's
 * offset ('c'). Modified is the real post_modified, never a display choice.
 *
 * @param  int $post_id Post ID.
 * @return array        array( 'published' => string, 'modified' => string ); '' when unknown.
 */
function roci_article_times( $post_id ) {
    $published = get_post_time( 'c', false, $post_id );
    $modified  = get_post_modified_time( 'c', false, $post_id );

    return array(
        'published' => is_string( $published ) ? $published : '',
        'modified'  => is_string( $modified ) ? $modified : '',
    );
}


/**
 * roci_schema_has_article_type()
 *
 * Internal. True when a pasted Schema JSON-LD string declares an Article-
 * family @type anywhere in it (a string or an array value), so the automatic
 * node steps aside.
 *
 * @param  string $json The page's roci_schema_json value (raw or expanded).
 * @return bool
 */
function roci_schema_has_article_type( $json ) {
    if ( ! is_string( $json ) || '' === trim( $json ) ) {
        return false;
    }

    return (bool) preg_match( '#"@type"\s*:\s*(?:\[[^\]]*)?"(?:Article|BlogPosting|NewsArticle|TechArticle)"#', html_entity_decode( $json, ENT_QUOTES, 'UTF-8' ) );
}


/**
 * roci_get_article_schema()
 *
 * The BlogPosting node for a post, filtered, or array() for none.
 *
 * @param  int    $post_id   Post ID.
 * @param  string $canonical The page's canonical URL (header.php's), for
 *                           mainEntityOfPage; '' falls back to the permalink.
 * @return array             The node, or array().
 */
function roci_get_article_schema( $post_id, $canonical = '' ) {
    $post_id = (int) $post_id;
    $post    = $post_id ? get_post( $post_id ) : null;

    if ( ! $post || ! in_array( $post->post_type, roci_article_schema_post_types(), true ) ) {
        return array();
    }

    $headline = trim( wp_strip_all_tags( html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) ) );
    $times    = roci_article_times( $post_id );
    $page_url = '' !== (string) $canonical ? (string) $canonical : (string) get_permalink( $post_id );

    $data = array(
        '@context' => 'https://schema.org',
        '@type'    => 'BlogPosting',
    );

    if ( '' !== $headline ) {
        $data['headline'] = $headline;
    }
    if ( '' !== $times['published'] ) {
        $data['datePublished'] = $times['published'];
    }
    if ( '' !== $times['modified'] ) {
        $data['dateModified'] = $times['modified'];
    }

    // Author — a Person from the post author, filterable.
    $author_name = trim( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) );
    $author      = '' !== $author_name ? array( '@type' => 'Person', 'name' => $author_name ) : array();
    $author      = apply_filters( 'roci_article_author', $author, $post_id );

    if ( is_array( $author ) && ! empty( $author ) ) {
        $data['author'] = $author;
    }

    // Publisher — a reference to the site-level node when header.php prints it
    // (the same Business → name gate), else a minimal Organization.
    $biz_name = trim( (string) roci_setting( 'business', 'name' ) );

    if ( '' !== $biz_name ) {
        $data['publisher'] = array(
            '@id'  => home_url( '/#organization' ),
            'name' => $biz_name,
        );
    } else {
        $data['publisher'] = array(
            '@type' => 'Organization',
            'name'  => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ),
            'url'   => home_url( '/' ),
        );
    }

    // Image — the featured image, omitted when there is none.
    $thumb_id = (int) get_post_thumbnail_id( $post_id );

    if ( $thumb_id ) {
        $src = wp_get_attachment_image_src( $thumb_id, 'full' );

        if ( is_array( $src ) && ! empty( $src[0] ) ) {
            $image = array(
                '@type' => 'ImageObject',
                'url'   => $src[0],
            );

            if ( ! empty( $src[1] ) && ! empty( $src[2] ) ) {
                $image['width']  = (int) $src[1];
                $image['height'] = (int) $src[2];
            }

            $data['image'] = $image;
        }
    }

    if ( '' !== $page_url ) {
        $data['mainEntityOfPage'] = array(
            '@type' => 'WebPage',
            '@id'   => $page_url,
        );
    }

    $data = apply_filters( 'roci_article_schema', $data, $post_id );

    return is_array( $data ) ? $data : array();
}


/**
 * roci_article_schema_json()
 *
 * The node encoded for a <script type="application/ld+json">, script-safe
 * ("</" rewritten to "<\/", as every other parent schema block), or '' for none.
 *
 * @param  int    $post_id   Post ID.
 * @param  string $canonical The page's canonical URL.
 * @return string
 */
function roci_article_schema_json( $post_id, $canonical = '' ) {
    $data = roci_get_article_schema( $post_id, $canonical );

    if ( empty( $data ) ) {
        return '';
    }

    $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

    return is_string( $json ) ? str_replace( '</', '<\/', $json ) : '';
}
