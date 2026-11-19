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
 *   author            { "@id" } — a reference to the author's Person node
 *                     (8.4.0), printed beside the BlogPosting in one @graph:
 *                     the post author's display name (+ url when the
 *                     roci_article_author filter supplies one) and an @id —
 *                     the url exactly as given + #person
 *                     (https://example.com/about/#person), else
 *                     home/#/person/{user_nicename}
 *   publisher         a REFERENCE to the site-level node, { "@id": home/#organization,
 *                     "name" } when Theme Settings → Business → name is set
 *                     (that is the gate header.php's LocalBusiness block uses,
 *                     so the @id always resolves on the page); otherwise an
 *                     Organization with the site name and home URL
 *   image             the featured image (ImageObject, url + width + height),
 *                     omitted when the post has none
 *   mainEntityOfPage  a WebPage whose @id is the page's canonical
 *   isPartOf          { "@id" } — the WebSite node (8.11.0), home/#website by
 *                     default; only when header.php printed that node on this
 *                     request (roci_website_schema_id()), so it always resolves
 *
 * Filters:
 *   roci_article_schema_post_types( array( 'post' ) ) — which post types get it.
 *   roci_article_author( $author, $post_id ) — the author node; return an
 *     array (e.g. add 'url' => an About page), or an empty value to omit it.
 *     Runs first; an '@id' set here is kept.
 *   roci_article_person_node( $person, $post_id ) — the Person node after its
 *     @id is set (8.4.0); an empty return means no author at all.
 *   roci_article_schema( $data, $post_id ) — the BlogPosting node, last word
 *     on it (its author is the @id reference); extend it, or return
 *     false / array() to print nothing.
 * None is memoized.
 *
 * A post whose own Schema JSON-LD field already carries an Article-family
 * @type (Article, BlogPosting, NewsArticle, TechArticle) gets no automatic
 * node, so a hand-pasted one is never doubled. header.php makes that test with
 * roci_schema_has_article_type().
 *
 * File:    inc/schema/article-schema.php
 * Version: 1.2.0
 * Updated: 2026-11-19
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
 * roci_website_schema_id()
 *
 * Internal (8.11.0). The @id of the WebSite node header.php printed on this
 * request, for the BlogPosting's isPartOf. header.php records it right after
 * roci_website_schema runs: the node's @id when the node printed with a
 * string @id, '' when the filter emptied it (nothing printed) or dropped the
 * @id. Read with no argument.
 *
 * null = not known yet: the WebSite block has not run on this request (a
 * child calling roci_get_article_schema() before wp_head, or outside a page
 * request). isPartOf is then left out rather than pointing at a node that
 * may never print.
 *
 * @param  string|null $set The printed node's @id ('' for none); null to read.
 * @return string|null
 */
function roci_website_schema_id( $set = null ) {
    static $id = null;

    if ( null !== $set ) {
        $id = (string) $set;
    }

    return $id;
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
 * roci_article_schema_parts()
 *
 * Internal (8.4.0). Builds the BlogPosting node and the author's Person node
 * together, each filter running once: roci_article_author, then
 * roci_article_person_node, then roci_article_schema (BlogPosting only, its
 * author an @id reference — that filter's contract is unchanged).
 *
 * @param  int    $post_id   Post ID.
 * @param  string $canonical The page's canonical URL, for mainEntityOfPage.
 * @return array             array( 'article' => array, 'person' => array );
 *                           either may be array().
 */
function roci_article_schema_parts( $post_id, $canonical = '' ) {
    $post_id = (int) $post_id;
    $post    = $post_id ? get_post( $post_id ) : null;

    if ( ! $post || ! in_array( $post->post_type, roci_article_schema_post_types(), true ) ) {
        return array( 'article' => array(), 'person' => array() );
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

    // Author — a Person from the post author, filterable. roci_article_author
    // runs FIRST and unchanged: a child may replace the node outright there.
    $author_name = trim( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) );
    $author      = '' !== $author_name ? array( '@type' => 'Person', 'name' => $author_name ) : array();
    $author      = apply_filters( 'roci_article_author', $author, $post_id );

    // THE PERSON GETS AN @id (8.4.0), so the BlogPosting can reference it and
    // other nodes can point at the same person. One the filter already set is
    // kept. Otherwise: the author's url exactly as given (trimmed) + #person,
    // else home/#/person/{user_nicename} (the user ID when there is no
    // nicename). roci_article_person_node has the last word on the node; an
    // empty return means no author at all, exactly as an empty author did.
    $person = array();

    if ( is_array( $author ) && ! empty( $author ) ) {
        if ( empty( $author['@id'] ) ) {
            $author_url = isset( $author['url'] ) ? trim( (string) $author['url'] ) : '';

            if ( '' !== $author_url ) {
                $author['@id'] = $author_url . '#person';
            } else {
                $nicename      = (string) get_the_author_meta( 'user_nicename', (int) $post->post_author );
                $author['@id'] = home_url( '/#/person/' . ( '' !== $nicename ? $nicename : (string) (int) $post->post_author ) );
            }
        }

        $person = apply_filters( 'roci_article_person_node', $author, $post_id );
        $person = is_array( $person ) ? $person : array();
    }

    if ( ! empty( $person ) ) {
        if ( ! empty( $person['@id'] ) && is_string( $person['@id'] ) ) {
            $data['author'] = array( '@id' => $person['@id'] );
        } else {
            // A person node with its @id removed by the filter cannot be
            // referenced, so it stays inline as before 8.4.0 — never a
            // reference to a node that is not on the page.
            $data['author'] = $person;
            $person         = array();
        }
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

    // isPartOf (8.11.0) — the post belongs to the site: a reference to the
    // WebSite node header.php printed on this page, by the @id it printed
    // with (home/#website unless roci_website_schema changed it). Only when
    // that node DID print, so the reference always resolves; added before
    // roci_article_schema, so a child may still change or remove it.
    $website_id = roci_website_schema_id();

    if ( is_string( $website_id ) && '' !== $website_id ) {
        $data['isPartOf'] = array( '@id' => $website_id );
    }

    $data = apply_filters( 'roci_article_schema', $data, $post_id );

    return array(
        'article' => is_array( $data ) ? $data : array(),
        'person'  => $person,
    );
}


/**
 * roci_get_article_schema()
 *
 * The BlogPosting node for a post, filtered, or array() for none. Since 8.4.0
 * its author is an @id reference; the Person node it points at comes from
 * roci_article_schema_parts().
 *
 * @param  int    $post_id   Post ID.
 * @param  string $canonical The page's canonical URL (header.php's), for
 *                           mainEntityOfPage; '' falls back to the permalink.
 * @return array             The node, or array().
 */
function roci_get_article_schema( $post_id, $canonical = '' ) {
    $parts = roci_article_schema_parts( $post_id, $canonical );

    return $parts['article'];
}


/**
 * roci_article_schema_json()
 *
 * The JSON for a <script type="application/ld+json">, script-safe ("</"
 * rewritten to "<\/", as every other parent schema block), or '' for none.
 *
 * With an author (8.4.0): { "@context", "@graph": [ BlogPosting, Person ] },
 * the BlogPosting without its own @context. Without one: the BlogPosting
 * alone, as before. roci_article_schema returning empty prints nothing.
 *
 * @param  int    $post_id   Post ID.
 * @param  string $canonical The page's canonical URL.
 * @return string
 */
function roci_article_schema_json( $post_id, $canonical = '' ) {
    $parts = roci_article_schema_parts( $post_id, $canonical );
    $data  = $parts['article'];

    if ( empty( $data ) ) {
        return '';
    }

    if ( ! empty( $parts['person'] ) ) {
        unset( $data['@context'] );

        $data = array(
            '@context' => 'https://schema.org',
            '@graph'   => array( $data, $parts['person'] ),
        );
    }

    $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

    return is_string( $json ) ? str_replace( '</', '<\/', $json ) : '';
}
