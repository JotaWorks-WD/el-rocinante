<?php
/**
 * Forms — the background submit: POST /wp-json/roci/v1/form/{id}
 *
 * forms.js (8.0.1) sends a form here with fetch() when JavaScript is
 * available, so a submission never reloads the page; the page POST
 * (forms-handler.php) stays exactly as it was for everyone else and is the
 * fallback whenever this call fails.
 *
 * SAME PIPELINE: roci_process_form_submission() — honeypot, fields, rate
 * limit, Turnstile, store, mail, roci_form_submitted. Only the transport
 * differs. The browser ran JavaScript by definition, so a missing Turnstile
 * token is "complete the check", never "held".
 *
 * BODY: the form itself as multipart/form-data (new FormData( form )) — the
 * same names as the page POST (roci_fields[…], roci_website,
 * cf-turnstile-response) plus roci_source, the page URL (kept only when it is
 * on this site; else the referer, else the home URL).
 *
 * REPLY (JSON):
 *   200  { ok: true,  form, message, classes: { success: [] } }
 *        — also for the honeypot, so a bot cannot tell
 *   422  { ok: false, form, errors: { field id: message }, notes: [ … ],
 *          summary: "There are 2 problems with your message" }
 *   404  { ok: false, code: "roci_form_unknown" }
 *
 * PUBLIC AND UNCACHEABLE: no auth (an anonymous contact form; no nonce, like
 * the page POST — decision D4). Every reply sends no-cache headers,
 * X-LiteSpeed-Cache-Control: no-cache and LiteSpeed's
 * litespeed_control_set_nocache, so neither the browser nor the page cache
 * ever stores one. Registered only when the child opted in to roci-forms.
 *
 * File:    inc/forms/forms-rest.php
 * Version: 1.0.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * roci_forms_register_rest()
 *
 * The route, only when roci-forms is on.
 */
function roci_forms_register_rest() {
    if ( ! roci_forms_enabled() ) {
        return;
    }

    register_rest_route( 'roci/v1', '/form/(?P<id>[a-z0-9_-]+)', array(
        'methods'             => 'POST',
        'callback'            => 'roci_forms_rest_submit',
        'permission_callback' => '__return_true',
        'args'                => array(
            'id' => array(
                'sanitize_callback' => 'sanitize_key',
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'roci_forms_register_rest' );

/**
 * roci_forms_rest_submit()
 *
 * @param  WP_REST_Request $request
 * @return WP_REST_Response
 */
function roci_forms_rest_submit( $request ) {
    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'roci-forms: background submission' );

    $form = roci_get_form( (string) $request['id'] );

    if ( ! $form ) {
        return roci_forms_rest_response( array( 'ok' => false, 'code' => 'roci_form_unknown' ), 404 );
    }

    // The REST server has already unslashed the body params.
    $params = (array) $request->get_body_params();

    $outcome = roci_process_form_submission(
        $form,
        array(
            'fields'  => isset( $params['roci_fields'] ) && is_array( $params['roci_fields'] ) ? $params['roci_fields'] : array(),
            'website' => isset( $params['roci_website'] ) && is_scalar( $params['roci_website'] ) ? (string) $params['roci_website'] : '',
            'token'   => isset( $params['cf-turnstile-response'] ) && is_scalar( $params['cf-turnstile-response'] ) ? (string) $params['cf-turnstile-response'] : '',
        ),
        true,
        roci_forms_rest_source( isset( $params['roci_source'] ) && is_scalar( $params['roci_source'] ) ? (string) $params['roci_source'] : '' )
    );

    if ( 'error' === $outcome['status'] ) {
        return roci_forms_rest_response( array(
            'ok'      => false,
            'form'    => $form['id'],
            'errors'  => (object) $outcome['errors'],
            'notes'   => array_values( $outcome['notes'] ),
            'summary' => roci_form_errors_title( count( $outcome['errors'] ) + count( $outcome['notes'] ) ),
        ), 422 );
    }

    // 'sent' and 'spam' look identical from outside.
    $classes = roci_get_form_classes( $form['id'] );

    return roci_forms_rest_response( array(
        'ok'      => true,
        'form'    => $form['id'],
        'message' => $form['success_message'],
        'classes' => array( 'success' => array_values( $classes['success'] ) ),
    ), 200 );
}

/**
 * roci_forms_rest_response()
 *
 * (internal) A JSON reply that nothing may cache.
 *
 * @param  array $data
 * @param  int   $status
 * @return WP_REST_Response
 */
function roci_forms_rest_response( $data, $status ) {
    $response = new WP_REST_Response( $data, $status );
    $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
    $response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );

    return $response;
}

/**
 * roci_forms_rest_source()
 *
 * (internal) The page a background submission came from, for the stored
 * submission and the email: the posted roci_source when it is on this site,
 * else the referer when it is, else the home URL. Fragment and roci_sent
 * removed.
 *
 * @param  string $posted
 * @return string
 */
function roci_forms_rest_source( $posted ) {
    $home = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

    foreach ( array( $posted, (string) wp_get_raw_referer() ) as $candidate ) {
        $url = esc_url_raw( trim( $candidate ), array( 'http', 'https' ) );

        if ( '' === $url || strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== $home ) {
            continue;
        }

        $url = strtok( $url, '#' );

        return remove_query_arg( 'roci_sent', $url );
    }

    return home_url( '/' );
}
