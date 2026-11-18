<?php
/**
 * IndexNow — tell search engines the moment a page is published or updated
 *
 * OPT-IN: loaded only when the child declares
 *
 *     add_theme_support( 'roci-indexnow' );
 *
 * (el_rocinante_load_indexnow(), functions.php). Without it nothing here runs.
 *
 * THE KEY: option roci_indexnow_key, 32 lowercase hex characters, generated
 * once the first time it is needed (autoload off). The key file /{key}.txt is
 * served from parse_request, priority 0, the llms.txt pattern ($wp->request,
 * no rewrite rule): text/plain, the key exactly, no-cache. Only while
 * blog_public is on.
 *
 * THE PING. wp_after_insert_post queues the post; the queue is evaluated on
 * shutdown, after every save in the request — Meta Box fields included — so
 * the robots field read is the one just saved. A post is pinged only when:
 *     it is not a revision or an autosave        (else: silent)
 *     its status is publish                      (else: silent)
 *     blog_public is on                          (else: logged "blog_public 0")
 *     its type is viewable                       (else: logged "not viewable")
 *     it has no password                         (else: logged "password")
 *     roci_effective_robots() has no noindex     (else: logged "noindex")
 *     the URL was not pinged in the last 5 min   (else: logged "debounced")
 * blog_public is tested before the robots, which would otherwise report a
 * discouraged site's every post as "noindex".
 *
 * ⚠ BLOCK EDITOR: a publish there is two requests — the post (REST), then the
 *   meta boxes. The ping goes out at the end of the first, so a robots value
 *   changed in the same click is read as it was before; the second request's
 *   ping is then "debounced". Set noindex before publishing, or save twice.
 *
 * The request: POST https://api.indexnow.org/indexnow, JSON { host, key,
 * keyLocation, urlList }, non-blocking, 2s timeout. roci_indexnow_urls(
 * $urls, $post_id ) lets a child add related URLs (a listing page, say); the
 * list is de-duplicated and each URL debounced on its own.
 *
 * THE LOG: option roci_indexnow_log (autoload off), the last 20 entries,
 * each array( time (site), url, result ). From the terminal:
 *     wp option get roci_indexnow_log --format=json
 *
 * TURNING IT OFF FOR GOOD. Removing the flag stops pings and the key file.
 * To remove what it stored:
 *     wp option delete roci_indexnow_key roci_indexnow_log
 *
 * File:    inc/indexnow.php
 * Version: 1.0.0
 * Updated: 2026-11-18
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// THE KEY
// ============================================================

/**
 * roci_indexnow_key()
 *
 * The site's key, generated and stored on first call.
 *
 * @return string 32 lowercase hex characters.
 */
function roci_indexnow_key() {
    $key = (string) get_option( 'roci_indexnow_key', '' );

    if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
        $key = bin2hex( random_bytes( 16 ) );
        update_option( 'roci_indexnow_key', $key, false );
    }

    return $key;
}

/**
 * roci_indexnow_serve_key()
 *
 * On parse_request, priority 0. Matches only a 32-hex-character .txt path
 * before it reads the option, so other requests cost nothing; never
 * generates a key.
 *
 * @param WP $wp
 */
function roci_indexnow_serve_key( $wp ) {
    if ( ! isset( $wp->request ) || ! preg_match( '/^([a-f0-9]{32})\.txt$/', (string) $wp->request, $m ) ) {
        return;
    }
    if ( ! get_option( 'blog_public' ) || $m[1] !== get_option( 'roci_indexnow_key' ) ) {
        return;
    }

    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'roci indexnow key' );
    status_header( 200 );
    header( 'Content-Type: text/plain; charset=utf-8' );

    echo $m[1]; // 32 hex characters.
    exit;
}
add_action( 'parse_request', 'roci_indexnow_serve_key', 0 );


// ============================================================
// THE QUEUE
// ============================================================

/**
 * roci_indexnow_queue()
 *
 * Internal. The post IDs saved in this request.
 *
 * @param  int|null $add A post ID to add; null to read.
 * @return int[]
 */
function roci_indexnow_queue( $add = null ) {
    static $queue = array();

    if ( null !== $add ) {
        $queue[ (int) $add ] = (int) $add;
    }

    return array_values( $queue );
}

/**
 * roci_indexnow_on_save()
 *
 * On wp_after_insert_post: queue it; decide on shutdown.
 *
 * @param int $post_id
 */
function roci_indexnow_on_save( $post_id ) {
    roci_indexnow_queue( $post_id );
}
add_action( 'wp_after_insert_post', 'roci_indexnow_on_save' );

/**
 * roci_indexnow_flush()
 *
 * On shutdown: evaluate and ping each queued post.
 */
function roci_indexnow_flush() {
    foreach ( roci_indexnow_queue() as $post_id ) {
        roci_indexnow_maybe_ping( $post_id );
    }
}
add_action( 'shutdown', 'roci_indexnow_flush' );


// ============================================================
// THE DECISION AND THE PING
// ============================================================

/**
 * roci_indexnow_log()
 *
 * Appends an entry to roci_indexnow_log, keeping the last 20.
 *
 * @param string $url
 * @param string $result 'sent' or the skip reason.
 */
function roci_indexnow_log( $url, $result ) {
    $log   = get_option( 'roci_indexnow_log', array() );
    $log   = is_array( $log ) ? $log : array();
    $log[] = array(
        'time'   => current_time( 'mysql' ),
        'url'    => (string) $url,
        'result' => (string) $result,
    );

    update_option( 'roci_indexnow_log', array_slice( $log, -20 ), false );
}

/**
 * roci_indexnow_skip_reason()
 *
 * Internal. Why a post must not be pinged: null to ping, '' to skip
 * silently (not a published post), or the reason to log.
 *
 * @param  WP_Post $post
 * @return string|null
 */
function roci_indexnow_skip_reason( $post ) {
    if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || 'publish' !== $post->post_status ) {
        return '';
    }
    if ( ! get_option( 'blog_public' ) ) {
        return 'blog_public 0';
    }
    if ( ! is_post_type_viewable( $post->post_type ) ) {
        return 'not viewable';
    }
    if ( '' !== (string) $post->post_password ) {
        return 'password';
    }
    if ( false !== strpos( (string) roci_effective_robots( $post->ID ), 'noindex' ) ) {
        return 'noindex';
    }

    return null;
}

/**
 * roci_indexnow_body()
 *
 * The JSON body for a list of URLs.
 *
 * @param  string[] $urls
 * @return string
 */
function roci_indexnow_body( $urls ) {
    $key = roci_indexnow_key();

    return (string) wp_json_encode( array(
        'host'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
        'key'         => $key,
        'keyLocation' => home_url( '/' . $key . '.txt' ),
        'urlList'     => array_values( $urls ),
    ), JSON_UNESCAPED_SLASHES );
}

/**
 * roci_indexnow_maybe_ping()
 *
 * @param int $post_id
 */
function roci_indexnow_maybe_ping( $post_id ) {
    clean_post_cache( $post_id );
    $post = get_post( $post_id );

    if ( ! $post ) {
        return;
    }

    $permalink = (string) get_permalink( $post );
    $reason    = roci_indexnow_skip_reason( $post );

    if ( '' === $reason ) {
        return;
    }
    if ( null !== $reason ) {
        roci_indexnow_log( $permalink, $reason );
        return;
    }

    $urls = apply_filters( 'roci_indexnow_urls', array( $permalink ), $post->ID );
    $urls = array_values( array_unique( array_filter( array_map( 'strval', (array) $urls ) ) ) );
    $send = array();

    foreach ( $urls as $url ) {
        if ( get_transient( 'roci_indexnow_' . md5( $url ) ) ) {
            roci_indexnow_log( $url, 'debounced' );
        } else {
            $send[] = $url;
        }
    }

    if ( ! $send ) {
        return;
    }

    wp_remote_post( 'https://api.indexnow.org/indexnow', array(
        'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
        'body'     => roci_indexnow_body( $send ),
        'blocking' => false,
        'timeout'  => 2,
    ) );

    foreach ( $send as $url ) {
        set_transient( 'roci_indexnow_' . md5( $url ), 1, 5 * MINUTE_IN_SECONDS );
        roci_indexnow_log( $url, 'sent' );
    }
}
