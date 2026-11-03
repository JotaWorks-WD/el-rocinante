<?php
/**
 * Forms — the submission handler
 *
 * A form posts to ITS OWN PAGE (the action is the current URL), and
 * roci_handle_form_post() picks the POST up on template_redirect — no
 * admin-post.php round trip, and it works with JavaScript off.
 *
 *   1. form id known?                       no  → ignore (WordPress renders the page)
 *   2. honeypot (roci_website) filled?      yes → silent fake success: nothing stored or mailed
 *   3. fields valid?                        no  → errors
 *   4. rate limit (5 / form / 10 min)?      hit → form error
 *   5. Turnstile (when keys are set):
 *        token present                      → verify with Cloudflare; fail → form error
 *        no token, JS ran (roci_js = 1)     → form error ("complete the check")
 *        no token, no JS                    → HELD: stored + mailed "[Unverified]" (decision D2)
 *      keys not set → skipped (honeypot + rate limit only; decision D3)
 *   errors → the page renders in THIS POST response with the values and the
 *            messages (roci_forms_request_state()); POST is never page-cached
 *   success → store (forms-cpt.php), mail, count it, roci_form_submitted,
 *            303 → ?roci_sent={id}#roci-form-{id}-success
 *
 * NO NONCE (owner decision D4, 2026-11-03). A nonce printed into page-cached
 * HTML goes stale after 12–24 h and breaks the form for every visitor; for an
 * anonymous contact form CSRF has no victim worth protecting, and Turnstile's
 * single-use, server-verified token is the anti-automation check. Because the
 * GET markup is identical for everyone, a page with a form needs no
 * page-cache exclusion.
 *
 * ⚠ FIELD NAMES ARE NAMESPACED: roci_fields[{id}], roci_form_id, roci_js,
 *   roci_website. WordPress reads its public query vars from POST too, so a
 *   bare input named "name" or "s" would hijack the request.
 *
 * Filters: roci_form_rate_limit( array( 'count' => 5, 'window' => 600 ), $form_id ),
 * roci_form_recipient( $email, $form_id ), roci_form_email_subject( $subject,
 * $form_id, $values ). Action: roci_form_submitted( $form_id, $values, $post_id ).
 *
 * PRIVACY: Turnstile's siteverify gets the secret and the token only — no
 * remoteip (decision D11). The client IP is used only as a hashed transient
 * key for the rate limit and expires with the window.
 *
 * File:    inc/forms/forms-handler.php
 * Version: 1.0.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * roci_handle_form_post()
 *
 * The POST, on template_redirect (front end only).
 */
function roci_handle_form_post() {
    if ( ! roci_forms_enabled() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['roci_form_id'] ) ) {
        return;
    }

    $form = roci_get_form( sanitize_key( wp_unslash( $_POST['roci_form_id'] ) ) );

    if ( ! $form ) {
        return;
    }

    // Never cache this response, whatever happens next.
    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'roci-forms: form submission' );

    $sent_url = add_query_arg( 'roci_sent', $form['id'], roci_forms_current_url() ) . '#roci-form-' . $form['id'] . '-success';

    // 2. Honeypot — a bot gets the same success a person does, and nothing else.
    if ( ! empty( $_POST['roci_website'] ) ) {
        wp_safe_redirect( $sent_url, 303 );
        exit;
    }

    // 3. Fields.
    $raw    = isset( $_POST['roci_fields'] ) && is_array( $_POST['roci_fields'] ) ? wp_unslash( $_POST['roci_fields'] ) : array();
    $result = roci_validate_form( $form, $raw );
    $errors = $result['errors'];
    $notes  = array();

    // 4. Rate limit.
    if ( roci_form_rate_limited( $form['id'] ) ) {
        $notes[] = __( 'Too many messages in a short time. Please try again in a few minutes.', 'rocinante' );
    }

    // 5. Turnstile — only once everything else passes, so a token is not
    // spent on a submission that fails anyway.
    $held = false;
    $keys = roci_forms_turnstile_keys();

    if ( $keys && ! $errors && ! $notes ) {
        $token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
        $js    = ! empty( $_POST['roci_js'] );

        if ( '' !== $token ) {
            if ( ! roci_verify_turnstile( $token, $keys['secret'] ) ) {
                $notes[] = __( 'The security check did not pass. Please complete it again, then send.', 'rocinante' );
            }
        } elseif ( $js ) {
            $notes[] = __( 'Please complete the security check, then send.', 'rocinante' );
        } else {
            $held = true; // No JavaScript, so no check could run (decision D2).
        }
    }

    if ( $errors || $notes ) {
        $errors['_form'] = $notes;
        roci_forms_request_state( array(
            'form'   => $form['id'],
            'values' => $result['values'],
            'errors' => $errors,
        ) );
        return; // The page renders with the errors, in this POST response.
    }

    $post_id = roci_store_submission( $form, $result['values'], $held, roci_forms_current_url() );

    if ( $post_id ) {
        $mailed = roci_send_form_notification( $form, $result['values'], $post_id, $held );
        update_post_meta( $post_id, '_roci_form_mailed', $mailed ? '1' : '0' );
    }

    roci_form_rate_count( $form['id'] );

    /**
     * After a submission is stored (and mailed, or not). $post_id is 0 when
     * the store failed.
     */
    do_action( 'roci_form_submitted', $form['id'], $result['values'], (int) $post_id );

    wp_safe_redirect( $sent_url, 303 );
    exit;
}
add_action( 'template_redirect', 'roci_handle_form_post', 1 );

/**
 * roci_validate_form()
 *
 * Every declared field, read from the posted roci_fields[], sanitised by
 * type and checked: required, maxlength, a valid email, a valid web address
 * (https:// added when the scheme is missing), a select value among its
 * options. Undeclared keys are ignored.
 *
 * @param  array $form The normalised form.
 * @param  array $raw  roci_fields[] (unslashed).
 * @return array{values: array<string, string>, errors: array<string, string>}
 */
function roci_validate_form( $form, $raw ) {
    $values = array();
    $errors = array();

    foreach ( $form['fields'] as $id => $field ) {
        $value = isset( $raw[ $id ] ) && is_scalar( $raw[ $id ] ) ? (string) $raw[ $id ] : '';
        $value = 'textarea' === $field['type'] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );

        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );

        if ( '' === $value ) {
            if ( $field['required'] ) {
                $errors[ $id ] = 'select' === $field['type']
                    /* translators: %s: the field's label. */
                    ? sprintf( __( 'Choose an option for "%s".', 'rocinante' ), $field['label'] )
                    /* translators: %s: the field's label. */
                    : sprintf( __( 'Fill in "%s".', 'rocinante' ), $field['label'] );
            }
            $values[ $id ] = '';
            continue;
        }

        if ( $length > $field['maxlength'] ) {
            /* translators: %d: the maximum number of characters. */
            $errors[ $id ] = sprintf( __( 'Keep this under %d characters.', 'rocinante' ), $field['maxlength'] );
        }

        switch ( $field['type'] ) {
            case 'email':
                if ( ! is_email( $value ) ) {
                    $errors[ $id ] = __( 'Enter a valid email address, like name@example.com.', 'rocinante' );
                } else {
                    $value = sanitize_email( $value );
                }
                break;

            case 'url':
                $url = preg_match( '#^https?://#i', $value ) ? $value : 'https://' . $value;
                $host = (string) wp_parse_url( $url, PHP_URL_HOST );
                if ( ! filter_var( $url, FILTER_VALIDATE_URL ) || false === strpos( $host, '.' ) ) {
                    $errors[ $id ] = __( 'Enter a valid web address, like example.com.', 'rocinante' );
                } else {
                    $value = esc_url_raw( $url, array( 'http', 'https' ) );
                }
                break;

            case 'select':
                if ( ! array_key_exists( $value, $field['options'] ) ) {
                    /* translators: %s: the field's label. */
                    $errors[ $id ] = sprintf( __( 'Choose an option for "%s".', 'rocinante' ), $field['label'] );
                    $value         = '';
                }
                break;
        }

        $values[ $id ] = $value;
    }

    return array( 'values' => $values, 'errors' => $errors );
}

/**
 * roci_form_rate_key()
 *
 * (internal) The transient key for this client + form: a hash, never the IP.
 * '' when no IP can be read (the limit is then skipped).
 *
 * @param  string $form_id
 * @return string
 */
function roci_form_rate_key( $form_id ) {
    $ip = roci_forms_client_ip();

    return '' === $ip ? '' : 'roci_rl_' . md5( wp_hash( $ip . '|' . $form_id ) );
}

/**
 * roci_form_rate_settings()
 *
 * (internal) The limit for a form, through roci_form_rate_limit (decision
 * D14: 5 per 10 minutes).
 *
 * @param  string $form_id
 * @return array{count: int, window: int}
 */
function roci_form_rate_settings( $form_id ) {
    $limit = apply_filters( 'roci_form_rate_limit', array( 'count' => 5, 'window' => 10 * MINUTE_IN_SECONDS ), $form_id );

    return array(
        'count'  => isset( $limit['count'] ) ? max( 1, (int) $limit['count'] ) : 5,
        'window' => isset( $limit['window'] ) ? max( 60, (int) $limit['window'] ) : 10 * MINUTE_IN_SECONDS,
    );
}

/**
 * roci_form_rate_limited()
 *
 * Whether this client has used up the form's window. Only SENT submissions
 * count — a visitor fixing errors is never locked out.
 *
 * @param  string $form_id
 * @return bool
 */
function roci_form_rate_limited( $form_id ) {
    $key = roci_form_rate_key( $form_id );

    if ( '' === $key ) {
        return false;
    }

    $hit = get_transient( $key );

    return is_array( $hit ) && (int) $hit['n'] >= roci_form_rate_settings( $form_id )['count'];
}

/**
 * roci_form_rate_count()
 *
 * (internal) Count one sent submission. A FIXED window from the first one:
 * the transient keeps its original expiry.
 *
 * @param string $form_id
 */
function roci_form_rate_count( $form_id ) {
    $key = roci_form_rate_key( $form_id );

    if ( '' === $key ) {
        return;
    }

    $limit = roci_form_rate_settings( $form_id );
    $hit   = get_transient( $key );
    $hit   = is_array( $hit ) ? $hit : array( 'n' => 0, 'start' => time() );

    $hit['n']++;
    set_transient( $key, $hit, max( 1, $limit['window'] - ( time() - (int) $hit['start'] ) ) );
}

/**
 * roci_verify_turnstile()
 *
 * Cloudflare's server-side check of a Turnstile token. Secret + token only
 * (no remoteip). A network error, a non-200 or success !== true is a FAIL
 * (decision D3: an unreachable Cloudflare is treated as a failed check).
 *
 * @param  string $token
 * @param  string $secret
 * @return bool
 */
function roci_verify_turnstile( $token, $secret ) {
    $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
        'timeout' => 10,
        'body'    => array(
            'secret'   => $secret,
            'response' => $token,
        ),
    ) );

    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return false;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    return is_array( $body ) && ! empty( $body['success'] );
}

/**
 * roci_form_role_value()
 *
 * (internal) The submitted value of the field carrying a role, or ''.
 *
 * @param  array  $form
 * @param  array  $values
 * @param  string $role name | email | site
 * @return string
 */
function roci_form_role_value( $form, $values, $role ) {
    $id = isset( $form['roles'][ $role ] ) ? $form['roles'][ $role ] : '';

    return ( '' !== $id && isset( $values[ $id ] ) ) ? (string) $values[ $id ] : '';
}

/**
 * roci_form_recipient_for()
 *
 * (internal) Where a form's notification goes: the form's own recipient →
 * Theme Settings → Integrations → Forms → Send Submissions To → the Business
 * email, then roci_form_recipient. '' when nothing valid is set.
 *
 * @param  array $form
 * @return string
 */
function roci_form_recipient_for( $form ) {
    $to = $form['recipient'];

    if ( ! is_email( $to ) ) {
        $to = sanitize_email( (string) roci_setting( 'integrations', 'forms_recipient' ) );
    }
    if ( ! is_email( $to ) ) {
        $to = sanitize_email( (string) roci_setting( 'business', 'email' ) );
    }

    $to = apply_filters( 'roci_form_recipient', $to, $form['id'] );

    return is_email( $to ) ? $to : '';
}

/**
 * roci_send_form_notification()
 *
 * The plain-text email to the business: every field, the page it came from,
 * and a link to the stored submission. Reply-To is the visitor (the email
 * role, re-validated, CR/LF stripped); From is left to WordPress / the SMTP
 * plugin — never the visitor's address, which would fail SPF / DMARC.
 *
 * @param  array $form
 * @param  array $values
 * @param  int   $post_id
 * @param  bool  $held    No Turnstile check could run — subject "[Unverified]".
 * @return bool  Whether wp_mail() accepted it (false with no valid recipient).
 */
function roci_send_form_notification( $form, $values, $post_id, $held ) {
    $to = roci_form_recipient_for( $form );

    if ( '' === $to ) {
        return false;
    }

    $site  = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
    $name  = roci_form_role_value( $form, $values, 'name' );
    $email = roci_form_role_value( $form, $values, 'email' );
    $who   = '' !== $name ? $name : ( '' !== $email ? $email : __( 'a visitor', 'rocinante' ) );

    if ( '' !== $form['subject'] ) {
        $subject = $form['subject'];
    } else {
        /* translators: 1: site name, 2: form label, 3: sender name or email. */
        $subject = sprintf( __( '[%1$s] New %2$s from %3$s', 'rocinante' ), $site, $form['label'], $who );
    }

    $subject = (string) apply_filters( 'roci_form_email_subject', $subject, $form['id'], $values );

    if ( $held ) {
        $subject = __( '[Unverified]', 'rocinante' ) . ' ' . $subject;
    }

    $lines = array();
    foreach ( $form['fields'] as $id => $field ) {
        $value = isset( $values[ $id ] ) && '' !== $values[ $id ] ? $values[ $id ] : '—';
        if ( 'select' === $field['type'] && isset( $field['options'][ $value ] ) ) {
            $value = $field['options'][ $value ];
        }
        $lines[] = 'textarea' === $field['type'] ? $field['label'] . ":\n" . $value . "\n" : $field['label'] . ': ' . $value;
    }

    $lines[] = '';
    $lines[] = '—';
    if ( $held ) {
        $lines[] = __( 'Unverified: the visitor\'s browser did not run JavaScript, so the security check could not run. Treat with care.', 'rocinante' );
    }
    /* translators: %s: the URL of the page the form was sent from. */
    $lines[] = sprintf( __( 'Sent from: %s', 'rocinante' ), (string) get_post_meta( $post_id, '_roci_form_source', true ) );
    /* translators: %s: the wp-admin URL of the stored submission. */
    $lines[] = sprintf( __( 'Stored at: %s', 'rocinante' ), admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ) );

    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );

    if ( is_email( $email ) ) {
        $reply_name = trim( preg_replace( '/[\r\n"<>,;]+/', ' ', $name ) );
        $headers[]  = 'Reply-To: ' . ( '' !== $reply_name ? '"' . $reply_name . '" ' : '' ) . '<' . $email . '>';
    }

    return (bool) wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
}
