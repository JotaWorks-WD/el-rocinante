<?php
/**
 * Forms — rendering: [roci_form id="…"] and the form part's args
 *
 * The shortcode (registered on init, only when roci-forms is on) renders
 * template-parts/form.php — the markup lives in that PART, so a child that
 * needs different structure overrides the part, never this file. The part
 * receives everything it prints in $args (see roci_render_form()).
 *
 * THE THREE STATES:
 *   'form'  the empty form (GET) — identical for every visitor, so cacheable
 *   'error' the form re-filled with the visitor's values and the errors —
 *           only ever in the POST response (forms-handler.php), never cached
 *   'sent'  the success message — GET ?roci_sent={form id}, after the
 *           handler's 303 redirect; no personal data
 *
 * Classes: roci_form_classes( $classes, $form_id ), slot-based like the
 * parent's search form — 'form', 'submit', 'success'. A dropped slot gets
 * its default back; every class goes through sanitize_html_class().
 *
 * With JavaScript the 'error' and 'sent' states are drawn in place by
 * forms.js from the background submit's JSON (8.0.1, forms-rest.php), using
 * the same classes, ids and strings as the part.
 *
 * 1.1.0 (8.0.1) — roci_form_errors_title(), the summary heading shared with
 * the REST response.
 *
 * 1.2.0 (8.2.0) — the 'sent' state shows a matched auto-reply variant's
 * success_message (?roci_v={index}, set by the handler's redirect).
 *
 * File:    inc/forms/forms-render.php
 * Version: 1.2.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * roci_forms_register_shortcode()
 *
 * [roci_form id="…"] — only when the child opted in.
 */
function roci_forms_register_shortcode() {
    if ( roci_forms_enabled() ) {
        add_shortcode( 'roci_form', 'roci_form_shortcode' );
    }
}
add_action( 'init', 'roci_forms_register_shortcode' );

/**
 * roci_form_shortcode()
 *
 * @param  array|string $atts id — a form declared on roci_forms.
 * @return string
 */
function roci_form_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'id' => '' ), $atts, 'roci_form' );

    return roci_render_form( $atts['id'] );
}

/**
 * roci_forms_request_state()
 *
 * (internal) The POST result for this request, kept between the handler
 * (template_redirect) and the render (the shortcode, later in the same
 * request). Set by the handler on a failed submission only.
 *
 * @param  array|null $set array( 'form' => id, 'values' => …, 'errors' => … ) to store.
 * @return array|null
 */
function roci_forms_request_state( $set = null ) {
    static $state = null;

    if ( null !== $set ) {
        $state = $set;
    }

    return $state;
}

/**
 * roci_forms_current_url()
 *
 * (internal) The current page's URL without the success flag — the form's
 * action and the handler's redirect target, so the visitor stays on the page
 * that holds the form.
 *
 * @return string
 */
function roci_forms_current_url() {
    // Built on home_url(), never the Host header (a cached page must not carry
    // a client-supplied host in its form action). REQUEST_URI's path already
    // includes the install's subdirectory, so that prefix is removed first.
    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
    $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
    $base = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

    if ( '' !== $base && 0 === strpos( $path, $base ) ) {
        $path = substr( $path, strlen( $base ) );
    }

    $url = home_url( '' !== $path ? $path : '/' );
    $qs  = (string) wp_parse_url( $uri, PHP_URL_QUERY );

    if ( '' !== $qs ) {
        $url .= '?' . $qs;
    }

    return remove_query_arg( array( 'roci_sent', 'roci_v' ), $url );
}

/**
 * roci_form_errors_title()
 *
 * The error summary's heading — one string for the page (the part) and the
 * background submit (forms-rest.php), so both say the same thing.
 *
 * @param  int $count Number of problems (field errors + form-level messages).
 * @return string
 */
function roci_form_errors_title( $count ) {
    $count = max( 1, (int) $count );

    /* translators: %d: number of problems with the form. */
    return sprintf( _n( 'There is %d problem with your message', 'There are %d problems with your message', $count, 'rocinante' ), $count );
}

/**
 * roci_get_form_classes()
 *
 * The form's class slots, through roci_form_classes.
 *
 * @param  string $form_id
 * @return array{form: string[], submit: string[], success: string[]}
 */
function roci_get_form_classes( $form_id ) {
    $defaults = array(
        'form'    => array( 'roci-form' ),
        'submit'  => array( 'roci-form__submit' ),
        'success' => array( 'roci-form__success' ),
    );

    return roci_resolve_class_slots(
        apply_filters( 'roci_form_classes', $defaults, $form_id ),
        $defaults
    );
}

/**
 * roci_render_form()
 *
 * One form as HTML, through template-parts/form.php. '' for an unknown id
 * (plus a WP_DEBUG comment).
 *
 * The part's $args:
 *   'form'     the normalised form (roci_get_form())
 *   'state'    'form' | 'error' | 'sent'
 *   'values'   field id => submitted value ('error' only; else empty)
 *   'errors'   field id => message, plus '_form' => form-level messages[]
 *   'action'   the form's action URL, with its #roci-form-{id} fragment
 *   'classes'  roci_get_form_classes()
 *   'site_key' the Turnstile site key, or '' when not configured
 *
 * @param  string $id
 * @return string
 */
function roci_render_form( $id ) {
    $form = roci_get_form( $id );

    if ( ! $form ) {
        return jw_debug_comment( sprintf( '[roci_form] no form "%s" is declared on roci_forms (or roci-forms is not enabled).', $id ) );
    }

    $posted = roci_forms_request_state();
    $state  = 'form';
    $values = array();
    $errors = array();

    if ( is_array( $posted ) && $posted['form'] === $form['id'] ) {
        $state  = 'error';
        $values = $posted['values'];
        $errors = $posted['errors'];
    } elseif ( isset( $_GET['roci_sent'] ) && sanitize_key( wp_unslash( $_GET['roci_sent'] ) ) === $form['id'] ) {
        $state = 'sent';

        // 8.2.0: a matched auto-reply variant's own on-page message.
        if ( isset( $_GET['roci_v'] ) && is_numeric( $_GET['roci_v'] ) ) {
            $form['success_message'] = roci_form_success_message_for( $form, (int) $_GET['roci_v'] );
        }
    }

    $keys = roci_forms_turnstile_keys();

    $html = '';
    foreach ( $form['notices'] as $notice ) {
        $html .= jw_debug_comment( $notice );
    }

    ob_start();
    get_template_part( 'template-parts/form', null, array(
        'form'     => $form,
        'state'    => $state,
        'values'   => $values,
        'errors'   => $errors,
        'action'   => roci_forms_current_url() . '#roci-form-' . $form['id'],
        'classes'  => roci_get_form_classes( $form['id'] ),
        'site_key' => $keys ? $keys['site'] : '',
    ) );

    return $html . ob_get_clean();
}
