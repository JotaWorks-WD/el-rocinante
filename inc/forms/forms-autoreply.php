<?php
/**
 * Forms — visitor confirmation emails (auto-replies), v8.2.0
 *
 * After a submission is ACCEPTED and STORED, the visitor can get a short
 * confirmation email. Each form controls it in its declaration on roci_forms
 * (normalised by forms-registry.php):
 *
 *   'autoreply_enabled'  => true,                 // default false
 *   'autoreply_subject'  => 'Thanks, {name}',
 *   'autoreply_body'     => "Hi {name},\n\n…",    // plain text; blank lines = paragraphs
 *   'reply_window'       => 'one working day',    // {reply_window}
 *   'autoreply_variants' => array(                // first match wins, else the default
 *       array(
 *           'field'           => 'platform',      // a field id of this form
 *           'values'          => array( 'wix', 'shopify' ), // that field's option VALUES
 *           'subject'         => '…',
 *           'body'            => '…',
 *           'success_message' => '…',             // optional: the on-page message too
 *       ),
 *   ),
 *
 * MERGE TAGS — the only four; anything else in braces is left as typed:
 *   {name}          the FIRST WORD of the name-role field, letters only, ≤ 40
 *                   characters; anything that is not a plain word (a URL, an
 *                   address, digits) → "there"
 *   {site_name}     the site title
 *   {form_title}    the form's label
 *   {reply_window}  the form's reply_window (default "one working day")
 *
 * ⚠ ABUSE SAFETY — an auto-reply can be aimed at a stranger's inbox, so:
 *   - it is sent ONLY from the 'sent' end of roci_process_form_submission(),
 *     after every check passed and the submission was stored: never for the
 *     honeypot, a validation error, the rate limit or a failed Turnstile;
 *     never for a HELD (unverified, no-JS) submission either;
 *   - the visitor's own text is NEVER echoed: no message, no URL, no free
 *     text — only {name}, filtered as above; the copy is the site's, in code;
 *   - at most ONE per email address per form per 24 hours (a transient keyed
 *     on a hash of the address — the address itself is not stored there).
 *
 * SENDING: wp_mail() (so the mail module's SMTP and site sender apply), From
 * the site, Reply-To the form's notification recipient (so a reply reaches the
 * business). multipart/alternative: a plain HTML version (the site name, the
 * paragraphs, no images, no links) with the plain text as AltBody.
 *
 * STORED on the submission: _roci_autoreply_status sent | failed | skipped,
 * _roci_autoreply_reason (the error, or why it was skipped),
 * _roci_autoreply_variant (-1 = the default). Shown in Submissions → Mail.
 *
 * PREVIEW: Theme Settings → Mail lists each form's auto-replies (read-only —
 * the copy lives in code) with "Send me a preview": every variant plus the
 * default, to the form's notification recipient, "[Preview]" in the subject.
 * Also `wp roci mail-preview <form> [<to>]`.
 *
 * File:    inc/forms/forms-autoreply.php
 * Version: 1.0.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Seconds an address waits before the same form will auto-reply to it again. */
const ROCI_AUTOREPLY_WINDOW = 86400;


// ============================================================
// WHICH REPLY
// ============================================================

/**
 * roci_form_autoreply_pick()
 *
 * The reply for these values: the first variant whose field's value is in its
 * values list, else the default. Works whether or not auto-replies are on, so
 * a variant's on-page success_message applies either way.
 *
 * @param  array $form   The normalised form.
 * @param  array $values field id => sanitised value.
 * @return array{variant: int, subject: string, body: string, success_message: string}
 */
function roci_form_autoreply_pick( $form, $values ) {
    $variants = isset( $form['autoreply_variants'] ) ? (array) $form['autoreply_variants'] : array();

    foreach ( $variants as $i => $variant ) {
        $value = isset( $values[ $variant['field'] ] ) ? (string) $values[ $variant['field'] ] : '';

        if ( '' !== $value && in_array( $value, $variant['values'], true ) ) {
            return array(
                'variant'         => (int) $i,
                'subject'         => $variant['subject'],
                'body'            => $variant['body'],
                'success_message' => $variant['success_message'],
            );
        }
    }

    return array(
        'variant'         => -1,
        'subject'         => isset( $form['autoreply_subject'] ) ? $form['autoreply_subject'] : '',
        'body'            => isset( $form['autoreply_body'] ) ? $form['autoreply_body'] : '',
        'success_message' => '',
    );
}

/**
 * roci_form_success_message_for()
 *
 * The on-page success message for a variant index (-1 or unknown → the form's
 * own success_message).
 *
 * @param  array $form
 * @param  int   $variant
 * @return string
 */
function roci_form_success_message_for( $form, $variant ) {
    $variants = isset( $form['autoreply_variants'] ) ? (array) $form['autoreply_variants'] : array();

    if ( $variant >= 0 && isset( $variants[ $variant ] ) && '' !== $variants[ $variant ]['success_message'] ) {
        return $variants[ $variant ]['success_message'];
    }

    return $form['success_message'];
}


// ============================================================
// MERGE TAGS
// ============================================================

/**
 * roci_form_autoreply_first_name()
 *
 * {name}: the first word of the name-role field, as LETTERS ONLY (plus an
 * inner hyphen or apostrophe), at most 40 characters. Anything that is not a
 * plain word — a URL, an address, digits, markup — gives "there", so the tag
 * can never carry a link or a payload into someone's inbox.
 *
 * @param  array $form
 * @param  array $values
 * @return string
 */
function roci_form_autoreply_first_name( $form, $values ) {
    $raw   = roci_form_role_value( $form, $values, 'name' );
    $raw   = trim( wp_strip_all_tags( (string) $raw ) );
    $parts = preg_split( '/\s+/u', $raw );
    $first = isset( $parts[0] ) ? (string) $parts[0] : '';

    if ( '' === $first || ! preg_match( "/^\\p{L}+(?:['’\\-]\\p{L}+)*$/u", $first ) ) {
        return __( 'there', 'rocinante' );
    }

    // 40 CHARACTERS, not bytes (a /u regex works without mbstring, and never
    // splits an accented letter).
    return preg_match( '/^.{1,40}/us', $first, $m ) ? $m[0] : __( 'there', 'rocinante' );
}

/**
 * roci_form_autoreply_tags()
 *
 * @param  array $form
 * @param  array $values
 * @return array<string, string>
 */
function roci_form_autoreply_tags( $form, $values ) {
    $window = isset( $form['reply_window'] ) && '' !== $form['reply_window'] ? $form['reply_window'] : __( 'one working day', 'rocinante' );

    return array(
        '{name}'         => roci_form_autoreply_first_name( $form, $values ),
        '{site_name}'    => wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
        '{form_title}'   => (string) $form['label'],
        '{reply_window}' => (string) $window,
    );
}

/**
 * roci_form_autoreply_render()
 *
 * One reply, merged: subject (one line), plain text, and the HTML version.
 *
 * @param  array  $form
 * @param  array  $values
 * @param  string $subject Template.
 * @param  string $body    Template (plain text; blank lines separate paragraphs).
 * @return array{subject: string, text: string, html: string}
 */
function roci_form_autoreply_render( $form, $values, $subject, $body ) {
    $tags    = roci_form_autoreply_tags( $form, $values );
    $subject = trim( preg_replace( '/[\r\n]+/', ' ', strtr( (string) $subject, $tags ) ) );
    $text    = trim( strtr( str_replace( "\r\n", "\n", (string) $body ), $tags ) );

    $paras = array_filter( array_map( 'trim', preg_split( "/\n\s*\n/", $text ) ), 'strlen' );
    $html  = '';
    foreach ( $paras as $para ) {
        $html .= '<p style="margin:0 0 16px;">' . nl2br( esc_html( $para ), false ) . "</p>\n";
    }

    $site = esc_html( $tags['{site_name}'] );
    $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html( $subject ) . '</title></head>'
        . '<body style="margin:0;padding:24px;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#222;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;padding:24px 28px;border-radius:6px;">'
        . '<p style="margin:0 0 20px;font-size:18px;font-weight:bold;">' . $site . '</p>'
        . $html
        . '</div></body></html>';

    return array( 'subject' => $subject, 'text' => $text, 'html' => $html );
}


// ============================================================
// SENDING
// ============================================================

/**
 * roci_form_autoreply_rate_key()
 *
 * (internal) The 24h transient key for an address + form: a hash, never the
 * address.
 *
 * @param  string $email
 * @param  string $form_id
 * @return string
 */
function roci_form_autoreply_rate_key( $email, $form_id ) {
    return 'roci_ar_' . md5( wp_hash( strtolower( trim( (string) $email ) ) . '|' . $form_id ) );
}

/**
 * roci_form_autoreply_deliver()
 *
 * (internal) Send one rendered reply as multipart/alternative through
 * wp_mail(): HTML body, plain-text AltBody (set on phpmailer_init for this
 * send only). Reply-To the notification recipient when there is one.
 *
 * @param  string $to
 * @param  array  $rendered roci_form_autoreply_render()
 * @param  string $reply_to
 * @return array{ok: bool, error: string}
 */
function roci_form_autoreply_deliver( $to, $rendered, $reply_to ) {
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    if ( is_email( $reply_to ) ) {
        $headers[] = 'Reply-To: <' . $reply_to . '>';
    }

    $alt = function ( $phpmailer ) use ( $rendered ) {
        $phpmailer->AltBody = $rendered['text'];
    };

    add_action( 'phpmailer_init', $alt, 99 );
    $result = roci_mail_send_capturing( $to, $rendered['subject'], $rendered['html'], $headers );
    remove_action( 'phpmailer_init', $alt, 99 );

    return $result;
}

/**
 * roci_form_send_autoreply()
 *
 * The visitor's confirmation, called ONLY for an accepted, stored submission
 * (roci_process_form_submission()'s 'sent' end). Records the outcome on the
 * submission.
 *
 * @param  array $form
 * @param  array $values
 * @param  int   $post_id
 * @param  bool  $held    No security check could run — never auto-reply.
 * @return string sent | failed | skipped
 */
function roci_form_send_autoreply( $form, $values, $post_id, $held ) {
    $pick = roci_form_autoreply_pick( $form, $values );

    $skip = function ( $reason ) use ( $post_id, $pick ) {
        roci_form_record_autoreply( $post_id, 'skipped', $reason, $pick['variant'] );
        return 'skipped';
    };

    if ( empty( $form['autoreply_enabled'] ) ) {
        return $skip( __( 'auto-reply off for this form', 'rocinante' ) );
    }

    if ( $held ) {
        return $skip( __( 'held: no security check could run', 'rocinante' ) );
    }

    $email = roci_form_role_value( $form, $values, 'email' );

    if ( ! is_email( $email ) ) {
        return $skip( __( 'no valid visitor email', 'rocinante' ) );
    }

    if ( '' === trim( $pick['subject'] ) || '' === trim( $pick['body'] ) ) {
        return $skip( __( 'no subject or body for this reply', 'rocinante' ) );
    }

    $key = roci_form_autoreply_rate_key( $email, $form['id'] );

    if ( false !== get_transient( $key ) ) {
        return $skip( __( 'already sent to this address in the last 24 hours', 'rocinante' ) );
    }

    $rendered = roci_form_autoreply_render( $form, $values, $pick['subject'], $pick['body'] );
    $result   = roci_form_autoreply_deliver( $email, $rendered, roci_form_recipient_for( $form ) );

    if ( $result['ok'] ) {
        set_transient( $key, 1, ROCI_AUTOREPLY_WINDOW );
        roci_form_record_autoreply( $post_id, 'sent', '', $pick['variant'] );
        return 'sent';
    }

    roci_form_record_autoreply( $post_id, 'failed', $result['error'], $pick['variant'] );
    return 'failed';
}

/**
 * roci_form_record_autoreply()
 *
 * (internal) Store an auto-reply outcome on its submission.
 *
 * @param int    $post_id
 * @param string $status  sent | failed | skipped
 * @param string $reason
 * @param int    $variant
 */
function roci_form_record_autoreply( $post_id, $status, $reason, $variant ) {
    if ( ! $post_id ) {
        return;
    }

    update_post_meta( $post_id, '_roci_autoreply_status', $status );
    update_post_meta( $post_id, '_roci_autoreply_variant', (int) $variant );

    if ( '' === $reason ) {
        delete_post_meta( $post_id, '_roci_autoreply_reason' );
    } else {
        update_post_meta( $post_id, '_roci_autoreply_reason', wp_slash( sanitize_text_field( roci_mail_scrub( $reason ) ) ) );
    }
}


// ============================================================
// PREVIEW (Theme Settings → Mail, WP-CLI)
// ============================================================

/**
 * roci_form_autoreply_replies()
 *
 * Every reply a form can send: the default first, then each variant, with a
 * label. For the preview and the Mail tab.
 *
 * @param  array $form
 * @return array<int, array{variant: int, label: string, subject: string, body: string, success_message: string}>
 */
function roci_form_autoreply_replies( $form ) {
    $replies = array( array(
        'variant'         => -1,
        'label'           => __( 'Default', 'rocinante' ),
        'subject'         => $form['autoreply_subject'],
        'body'            => $form['autoreply_body'],
        'success_message' => $form['success_message'],
    ) );

    foreach ( (array) $form['autoreply_variants'] as $i => $variant ) {
        $replies[] = array(
            'variant'         => (int) $i,
            /* translators: 1: variant number, 2: field id, 3: comma-separated values. */
            'label'           => sprintf( __( 'Variant %1$d: %2$s = %3$s', 'rocinante' ), $i + 1, $variant['field'], implode( ', ', $variant['values'] ) ),
            'subject'         => $variant['subject'],
            'body'            => $variant['body'],
            'success_message' => '' !== $variant['success_message'] ? $variant['success_message'] : $form['success_message'],
        );
    }

    return $replies;
}

/**
 * roci_form_autoreply_sample_values()
 *
 * (internal) Stand-in values for a preview: {name} reads "Ana".
 *
 * @param  array $form
 * @return array
 */
function roci_form_autoreply_sample_values( $form ) {
    $values = array();

    if ( isset( $form['roles']['name'] ) ) {
        $values[ $form['roles']['name'] ] = 'Ana Example';
    }

    return $values;
}

/**
 * roci_form_send_autoreply_preview()
 *
 * Send every reply of a form (default + variants) to $to or the form's
 * notification recipient, "[Preview]" in each subject. No 24h limit, nothing
 * stored. Sent whether or not the form's auto-reply is switched on.
 *
 * @param  string $form_id
 * @param  string $to
 * @return array{ok: bool, sent: int, total: int, to: string, message: string}
 */
function roci_form_send_autoreply_preview( $form_id, $to = '' ) {
    $form = roci_get_form( $form_id );

    if ( ! $form ) {
        /* translators: %s: form id. */
        return array( 'ok' => false, 'sent' => 0, 'total' => 0, 'to' => '', 'message' => sprintf( __( 'No form "%s" is declared.', 'rocinante' ), $form_id ) );
    }

    $to = sanitize_email( (string) $to );
    $to = is_email( $to ) ? $to : roci_form_recipient_for( $form );

    if ( ! is_email( $to ) ) {
        return array( 'ok' => false, 'sent' => 0, 'total' => 0, 'to' => '', 'message' => __( 'No valid address to send the preview to.', 'rocinante' ) );
    }

    $values  = roci_form_autoreply_sample_values( $form );
    $replies = array_filter( roci_form_autoreply_replies( $form ), function ( $reply ) {
        return '' !== trim( $reply['subject'] ) && '' !== trim( $reply['body'] );
    } );
    $sent    = 0;
    $errors  = array();

    foreach ( $replies as $reply ) {
        $rendered            = roci_form_autoreply_render( $form, $values, $reply['subject'], $reply['body'] );
        $rendered['subject'] = __( '[Preview]', 'rocinante' ) . ' ' . $rendered['subject'] . ' (' . $reply['label'] . ')';
        $result              = roci_form_autoreply_deliver( $to, $rendered, roci_form_recipient_for( $form ) );

        if ( $result['ok'] ) {
            $sent++;
        } else {
            $errors[] = $reply['label'] . ': ' . $result['error'];
        }
    }

    $total = count( $replies );

    if ( 0 === $total ) {
        /* translators: %s: form label. */
        return array( 'ok' => false, 'sent' => 0, 'total' => 0, 'to' => $to, 'message' => sprintf( __( '%s has no auto-reply copy to preview.', 'rocinante' ), $form['label'] ) );
    }

    $message = $errors
        /* translators: 1: sent count, 2: total, 3: address, 4: errors. */
        ? sprintf( __( 'Preview: %1$d of %2$d sent to %3$s. Failed: %4$s', 'rocinante' ), $sent, $total, $to, implode( ' | ', $errors ) )
        /* translators: 1: total, 2: address. */
        : sprintf( _n( 'Preview: %1$d email sent to %2$s.', 'Preview: %1$d emails sent to %2$s.', $total, 'rocinante' ), $total, $to );

    return array( 'ok' => ! $errors, 'sent' => $sent, 'total' => $total, 'to' => $to, 'message' => $message );
}

/**
 * admin-post.php?action=roci_mail_preview&roci_form={id} — the Mail tab's
 * "Send me a preview". Nonce + manage_options; the result is shown on the tab.
 */
function roci_form_autoreply_handle_preview() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do that.', 'rocinante' ), 403 );
    }

    check_admin_referer( 'roci_mail_preview', '_roci_mail_preview_nonce' );

    $form_id = isset( $_GET['roci_form'] ) ? sanitize_key( wp_unslash( $_GET['roci_form'] ) ) : '';
    $result  = roci_form_send_autoreply_preview( $form_id );

    set_transient( 'roci_mail_test_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

    wp_safe_redirect( admin_url( 'admin.php?page=roci-theme-settings&tab=mail&roci_mail_tested=1' ) );
    exit;
}
add_action( 'admin_post_roci_mail_preview', 'roci_form_autoreply_handle_preview' );

/**
 * The Mail tab's "Auto-replies" section (on roci_mail_tab_extra): per form,
 * on / off, the reply window, and each reply's subject, body and on-page
 * message as read-only text, plus "Send me a preview".
 */
function roci_form_autoreply_mail_section() {
    if ( ! roci_forms_enabled() ) {
        return;
    }

    $forms = roci_get_forms();
    ?>
    <h2 class="roci-section-title"><?php esc_html_e( 'Auto-replies (visitor confirmations)', 'rocinante' ); ?></h2>
    <?php if ( ! $forms ) : ?>
        <p class="roci-note"><?php esc_html_e( 'No forms are declared.', 'rocinante' ); ?></p>
        <?php return; ?>
    <?php endif; ?>
    <p class="roci-note"><?php esc_html_e( 'The wording lives in the theme\'s code; this is a read-only preview. {name} is shown as "Ana" here. Auto-replies are sent once per address per form per 24 hours, only for accepted submissions, and never repeat what the visitor wrote.', 'rocinante' ); ?></p>
    <?php wp_nonce_field( 'roci_mail_preview', '_roci_mail_preview_nonce' ); ?>
    <?php foreach ( $forms as $form ) : ?>
        <?php $values = roci_form_autoreply_sample_values( $form ); ?>
        <h3 style="margin:24px 0 8px;"><?php echo esc_html( $form['label'] ); ?> <code style="font-weight:400;"><?php echo esc_html( $form['id'] ); ?></code></h3>
        <p>
            <strong><?php echo $form['autoreply_enabled'] ? esc_html__( 'Auto-reply: on', 'rocinante' ) : esc_html__( 'Auto-reply: off', 'rocinante' ); ?></strong>
            · <?php
            /* translators: %s: e.g. "one working day". */
            echo esc_html( sprintf( __( 'Reply window: %s', 'rocinante' ), roci_form_autoreply_tags( $form, $values )['{reply_window}'] ) );
            ?>
        </p>
        <?php foreach ( roci_form_autoreply_replies( $form ) as $reply ) : ?>
            <?php $rendered = roci_form_autoreply_render( $form, $values, $reply['subject'], $reply['body'] ); ?>
            <div style="border:1px solid #dcdcde;border-radius:4px;padding:12px 16px;margin:0 0 12px;max-width:720px;">
                <p style="margin:0 0 6px;"><strong><?php echo esc_html( $reply['label'] ); ?></strong></p>
                <?php if ( '' === trim( $reply['subject'] ) || '' === trim( $reply['body'] ) ) : ?>
                    <p class="roci-note"><?php esc_html_e( 'No copy: nothing is sent for this reply.', 'rocinante' ); ?></p>
                <?php else : ?>
                    <p style="margin:0 0 6px;"><?php esc_html_e( 'Subject:', 'rocinante' ); ?> <?php echo esc_html( $rendered['subject'] ); ?></p>
                    <pre style="white-space:pre-wrap;margin:0 0 6px;font-family:inherit;background:#f6f7f7;padding:8px 10px;"><?php echo esc_html( $rendered['text'] ); ?></pre>
                <?php endif; ?>
                <p class="roci-note" style="margin:0;"><?php esc_html_e( 'On the page:', 'rocinante' ); ?> <?php echo esc_html( $reply['success_message'] ); ?></p>
            </div>
        <?php endforeach; ?>
        <p>
            <button type="submit" class="button" name="action" value="roci_mail_preview" formaction="<?php echo esc_url( add_query_arg( 'roci_form', $form['id'], admin_url( 'admin-post.php' ) ) ); ?>" formnovalidate><?php esc_html_e( 'Send me a preview', 'rocinante' ); ?></button>
            <span class="roci-note">
                <?php
                $to = roci_form_recipient_for( $form );
                /* translators: %s: email address. */
                echo esc_html( '' !== $to ? sprintf( __( 'Every reply above, to %s, with [Preview] in the subject.', 'rocinante' ), $to ) : __( 'Set a notification address first.', 'rocinante' ) );
                ?>
            </span>
        </p>
    <?php endforeach; ?>
    <?php
}
add_action( 'roci_mail_tab_extra', 'roci_form_autoreply_mail_section' );

/**
 * `wp roci mail-preview <form> [<to>]` — the same preview from the server.
 *
 * @param array $args
 */
function roci_form_autoreply_cli_preview( $args ) {
    if ( empty( $args[0] ) ) {
        WP_CLI::error( __( 'Usage: wp roci mail-preview <form> [<to>]', 'rocinante' ) );
        return;
    }

    $result = roci_form_send_autoreply_preview( sanitize_key( $args[0] ), isset( $args[1] ) ? $args[1] : '' );

    if ( $result['ok'] ) {
        WP_CLI::success( $result['message'] );
    } else {
        WP_CLI::error( $result['message'] );
    }
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'roci mail-preview', 'roci_form_autoreply_cli_preview' );
}
