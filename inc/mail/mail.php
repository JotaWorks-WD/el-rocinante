<?php
/**
 * Mail — authenticated SMTP for wp_mail(), with no plugin (v8.1.0)
 *
 * Every El Rocinante site sends its mail (form notifications and anything else
 * through wp_mail()) through an authenticated SMTP provider — Brevo first, but
 * nothing here is Brevo-specific. Credentials are wp-config.php constants, per
 * site, NEVER in the database, a repo or the admin:
 *
 *   define( 'ROCI_SMTP_HOST',   'smtp-relay.brevo.com' );
 *   define( 'ROCI_SMTP_PORT',   587 );
 *   define( 'ROCI_SMTP_USER',   '…@smtp-brevo.com' );
 *   define( 'ROCI_SMTP_PASS',   '…' );              // the provider's SMTP key
 *   define( 'ROCI_SMTP_SECURE', 'tls' );            // 'tls' | 'ssl' | ''
 *   define( 'ROCI_SMTP_DISABLED', true );           // optional: leave wp_mail alone
 *
 * THE STATE (roci_mail_state()), checked in this order:
 *   'plugin'    a known SMTP plugin is active (Post SMTP, WP Mail SMTP,
 *               FluentSMTP; filter roci_mail_smtp_plugins) — it handles mail,
 *               this module does nothing
 *   'disabled'  ROCI_SMTP_DISABLED is true — nothing touched
 *   'off'       HOST, USER or PASS is missing — WordPress's default mail runs,
 *               unchanged
 *   'active'    phpmailer_init switches PHPMailer to authenticated SMTP, and
 *               the From address / name come from Theme Settings → Mail
 * Only 'active' changes anything — the sender included.
 *
 * THE SENDER: Theme Settings → Mail → From email / From name, applied on
 * wp_mail_from / wp_mail_from_name ONLY when the value is still WordPress's
 * default (wordpress@{site} / "WordPress"): a caller that set its own From
 * keeps it. The From domain must be verified with the provider (SPF / DKIM);
 * the Mail tab warns when it does not match the site's domain.
 *
 * THE RECIPIENT (roci_mail_recipient(), used by the forms module and the
 * test email): Theme Settings → Mail → Form notifications to → the 8.0.x
 * Integrations → Forms → Send Submissions To (kept, so no site changes
 * silently) → Theme Settings → Business → Email → admin_email.
 *
 * ⚠ THE PASSWORD IS NEVER OUTPUT OR LOGGED. No status, notice, CLI line or
 *   stored error carries it (roci_mail_scrub() removes it from any provider
 *   message too), and the user is always masked (roci_mail_mask()).
 *
 * Also here: the test email (roci_mail_send_test(), the Mail tab's button and
 * `wp roci mail-test [<to>]`), `wp roci mail-status`, and
 * roci_mail_send_capturing() — wp_mail() plus the wp_mail_failed error text,
 * which the forms module stores on each submission.
 *
 * File:    inc/mail/mail.php
 * Version: 1.0.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// CONFIG + STATE
// ============================================================

/**
 * roci_mail_config()
 *
 * The SMTP constants, read once per call (cheap). Never stored, never output.
 *
 * @return array{host: string, port: int, user: string, pass: string, secure: string, disabled: bool}
 */
function roci_mail_config() {
    $c = function ( $name ) {
        return defined( $name ) ? constant( $name ) : null;
    };

    $secure = strtolower( trim( (string) $c( 'ROCI_SMTP_SECURE' ) ) );
    $secure = in_array( $secure, array( 'tls', 'ssl' ), true ) ? $secure : '';
    $port   = (int) $c( 'ROCI_SMTP_PORT' );

    if ( $port <= 0 ) {
        $port = 'ssl' === $secure ? 465 : 587;
    }

    return array(
        'host'     => trim( (string) $c( 'ROCI_SMTP_HOST' ) ),
        'port'     => $port,
        'user'     => trim( (string) $c( 'ROCI_SMTP_USER' ) ),
        'pass'     => (string) $c( 'ROCI_SMTP_PASS' ),
        'secure'   => $secure,
        'disabled' => (bool) $c( 'ROCI_SMTP_DISABLED' ),
    );
}

/**
 * roci_mail_smtp_plugins()
 *
 * The SMTP plugins this module steps aside for: plugin basename => name.
 * Filter roci_mail_smtp_plugins to add one.
 *
 * @return array<string, string>
 */
function roci_mail_smtp_plugins() {
    return (array) apply_filters( 'roci_mail_smtp_plugins', array(
        'post-smtp/postman-smtp.php'       => 'Post SMTP',
        'wp-mail-smtp/wp_mail_smtp.php'    => 'WP Mail SMTP',
        'wp-mail-smtp-pro/wp_mail_smtp.php' => 'WP Mail SMTP Pro',
        'fluent-smtp/fluent-smtp.php'      => 'FluentSMTP',
    ) );
}

/**
 * roci_mail_active_smtp_plugin()
 *
 * The name of an active SMTP plugin, or ''. Reads the active-plugin lists
 * (site and network) — no admin include needed, so it works on the front end
 * and in WP-CLI.
 *
 * @return string
 */
function roci_mail_active_smtp_plugin() {
    $active = (array) get_option( 'active_plugins', array() );

    if ( function_exists( 'is_multisite' ) && is_multisite() ) {
        $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
    }

    foreach ( roci_mail_smtp_plugins() as $basename => $name ) {
        if ( in_array( $basename, $active, true ) ) {
            return (string) $name;
        }
    }

    return '';
}

/**
 * roci_mail_state()
 *
 * 'plugin' | 'disabled' | 'off' | 'active' — see the file header.
 *
 * @return string
 */
function roci_mail_state() {
    if ( '' !== roci_mail_active_smtp_plugin() ) {
        return 'plugin';
    }

    $config = roci_mail_config();

    if ( $config['disabled'] ) {
        return 'disabled';
    }

    if ( '' === $config['host'] || '' === $config['user'] || '' === $config['pass'] ) {
        return 'off';
    }

    return 'active';
}

/**
 * roci_mail_mask()
 *
 * A login, masked for display: "ab•••@smtp-brevo.com" for an email-style
 * login, "ab•••yz" otherwise, "•••" when it is short.
 *
 * @param  string $user
 * @return string
 */
function roci_mail_mask( $user ) {
    $user  = (string) $user;
    $local = $user;
    $tail  = '';

    if ( false !== strpos( $user, '@' ) ) {
        list( $local, $domain ) = explode( '@', $user, 2 );
        $tail = '@' . $domain;
    }

    $len = function_exists( 'mb_strlen' ) ? mb_strlen( $local ) : strlen( $local );

    if ( $len <= 4 ) {
        return '•••' . $tail;
    }

    $sub = function ( $s, $start, $length = null ) {
        return function_exists( 'mb_substr' ) ? mb_substr( $s, $start, $length ) : substr( $s, $start, $length );
    };

    return $sub( $local, 0, 2 ) . '•••' . ( '' === $tail ? $sub( $local, -2 ) : '' ) . $tail;
}

/**
 * roci_mail_scrub()
 *
 * Remove the password (and the unmasked user) from any text before it is
 * shown, stored or printed — a provider's error message included.
 *
 * @param  string $text
 * @return string
 */
function roci_mail_scrub( $text ) {
    $text   = (string) $text;
    $config = roci_mail_config();

    if ( '' !== $config['pass'] ) {
        $text = str_replace( $config['pass'], '[hidden]', $text );
    }
    if ( '' !== $config['user'] ) {
        $text = str_replace( $config['user'], roci_mail_mask( $config['user'] ), $text );
    }

    return $text;
}

/**
 * roci_mail_status()
 *
 * The one status line, for the Mail tab and `wp roci mail-status`.
 *
 * @return array{state: string, text: string}
 */
function roci_mail_status() {
    $state  = roci_mail_state();
    $config = roci_mail_config();

    switch ( $state ) {
        case 'plugin':
            /* translators: %s: the SMTP plugin's name. */
            $text = sprintf( __( 'Handed to %s: that plugin sends this site\'s mail, so El Rocinante\'s SMTP is off.', 'rocinante' ), roci_mail_active_smtp_plugin() );
            break;

        case 'disabled':
            $text = __( 'Disabled: ROCI_SMTP_DISABLED is set, so WordPress\'s default mail runs unchanged.', 'rocinante' );
            break;

        case 'off':
            $text = __( 'Off: ROCI_SMTP_HOST, ROCI_SMTP_USER and ROCI_SMTP_PASS are not all defined in wp-config.php, so WordPress\'s default mail runs unchanged.', 'rocinante' );
            break;

        default:
            $secure = '' === $config['secure'] ? __( 'no encryption', 'rocinante' ) : strtoupper( $config['secure'] );
            /* translators: 1: host:port, 2: TLS / SSL / no encryption, 3: the masked user. */
            $text = sprintf( __( 'SMTP active: %1$s (%2$s), user %3$s.', 'rocinante' ), $config['host'] . ':' . $config['port'], $secure, roci_mail_mask( $config['user'] ) );
    }

    return array( 'state' => $state, 'text' => $text );
}


// ============================================================
// SENDING
// ============================================================

/**
 * roci_mail_phpmailer()
 *
 * phpmailer_init: authenticated SMTP from the constants — only when 'active'.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
 */
function roci_mail_phpmailer( $phpmailer ) {
    if ( 'active' !== roci_mail_state() ) {
        return;
    }

    $config = roci_mail_config();

    $phpmailer->isSMTP();
    $phpmailer->Host       = $config['host'];
    $phpmailer->Port       = $config['port'];
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = $config['user'];
    $phpmailer->Password   = $config['pass'];
    $phpmailer->SMTPSecure = $config['secure'];
    $phpmailer->Timeout    = (int) apply_filters( 'roci_mail_timeout', 15 );
    $phpmailer->SMTPDebug  = 0; // Never echo the SMTP conversation (it carries the login).
}
add_action( 'phpmailer_init', 'roci_mail_phpmailer' );

/**
 * roci_mail_default_from_email()
 *
 * WordPress's own default sender, built the way wp_mail() builds it:
 * wordpress@{network home host, without www.}.
 *
 * @return string
 */
function roci_mail_default_from_email() {
    $host = (string) wp_parse_url( network_home_url(), PHP_URL_HOST );

    if ( 0 === strpos( $host, 'www.' ) ) {
        $host = substr( $host, 4 );
    }

    return 'wordpress@' . strtolower( $host );
}

/**
 * Theme Settings → Mail values, trimmed.
 *
 * @param  string $key from_email | from_name | recipient
 * @return string
 */
function roci_mail_setting( $key ) {
    return trim( (string) roci_setting( 'mail', $key, '' ) );
}

/**
 * wp_mail_from: the site's From email, replacing ONLY the WordPress default.
 *
 * @param  string $from
 * @return string
 */
function roci_mail_from( $from ) {
    if ( 'active' !== roci_mail_state() || strtolower( trim( (string) $from ) ) !== roci_mail_default_from_email() ) {
        return $from;
    }

    $email = roci_mail_setting( 'from_email' );

    return is_email( $email ) ? $email : $from;
}
add_filter( 'wp_mail_from', 'roci_mail_from', 20 );

/**
 * wp_mail_from_name: the site's From name, replacing ONLY "WordPress".
 *
 * @param  string $name
 * @return string
 */
function roci_mail_from_name( $name ) {
    if ( 'active' !== roci_mail_state() || 'WordPress' !== (string) $name ) {
        return $name;
    }

    $setting = roci_mail_setting( 'from_name' );

    return '' !== $setting ? $setting : $name;
}
add_filter( 'wp_mail_from_name', 'roci_mail_from_name', 20 );

/**
 * roci_mail_recipient()
 *
 * Where site notifications go (forms, the test email): Mail → Form
 * notifications to → Integrations → Forms (8.0.x) → Business → Email →
 * admin_email. '' only when none of them is a valid address.
 *
 * @return string
 */
function roci_mail_recipient() {
    $integrations = get_option( 'roci_integrations', array() );

    $candidates = array(
        roci_mail_setting( 'recipient' ),
        is_array( $integrations ) && isset( $integrations['forms_recipient'] ) ? (string) $integrations['forms_recipient'] : '',
        (string) roci_setting( 'business', 'email', '' ),
        (string) get_option( 'admin_email' ),
    );

    foreach ( $candidates as $email ) {
        $email = sanitize_email( $email );
        if ( is_email( $email ) ) {
            return $email;
        }
    }

    return '';
}

/**
 * roci_mail_domain_matches()
 *
 * Whether an address's domain is the site's domain (or a parent of it:
 * bookings@fishpotrero.com on www.fishpotrero.com or staging.fishpotrero.com).
 *
 * @param  string $email
 * @return bool
 */
function roci_mail_domain_matches( $email ) {
    $domain = strtolower( (string) substr( strrchr( (string) $email, '@' ), 1 ) );
    $host   = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

    if ( '' === $domain || '' === $host ) {
        return false;
    }

    return $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain;
}

/**
 * roci_mail_send_capturing()
 *
 * wp_mail(), plus the error text WordPress reports on wp_mail_failed (the
 * PHPMailer message), scrubbed of the password.
 *
 * @return array{ok: bool, error: string}
 */
function roci_mail_send_capturing( $to, $subject, $message, $headers = '', $attachments = array() ) {
    $error   = '';
    $capture = function ( $wp_error ) use ( &$error ) {
        if ( is_object( $wp_error ) && method_exists( $wp_error, 'get_error_message' ) ) {
            $error = (string) $wp_error->get_error_message();
        }
    };

    add_action( 'wp_mail_failed', $capture );
    $ok = (bool) wp_mail( $to, $subject, $message, $headers, $attachments );
    remove_action( 'wp_mail_failed', $capture );

    if ( ! $ok && '' === $error ) {
        $error = __( 'wp_mail() returned false without an error message.', 'rocinante' );
    }

    return array( 'ok' => $ok, 'error' => $ok ? '' : roci_mail_scrub( $error ) );
}

/**
 * roci_mail_send_test()
 *
 * One plain test email through wp_mail(), to $to or the notification
 * recipient. Never includes a credential.
 *
 * @param  string $to
 * @return array{ok: bool, to: string, message: string}
 */
function roci_mail_send_test( $to = '' ) {
    $to = sanitize_email( (string) $to );
    $to = is_email( $to ) ? $to : roci_mail_recipient();

    if ( ! is_email( $to ) ) {
        return array( 'ok' => false, 'to' => '', 'message' => __( 'No valid address to send the test to.', 'rocinante' ) );
    }

    $site   = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
    $status = roci_mail_status();

    /* translators: %s: site name. */
    $subject = sprintf( __( '[%s] Test email', 'rocinante' ), $site );
    $body    = implode( "\n", array(
        /* translators: %s: site URL. */
        sprintf( __( 'This is a test email from %s.', 'rocinante' ), home_url( '/' ) ),
        '',
        /* translators: %s: the mail status line. */
        sprintf( __( 'Mail status: %s', 'rocinante' ), $status['text'] ),
        /* translators: %s: date and time. */
        sprintf( __( 'Sent: %s', 'rocinante' ), wp_date( 'Y-m-d H:i:s T' ) ),
    ) );

    $result = roci_mail_send_capturing( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );

    return array(
        'ok'      => $result['ok'],
        'to'      => $to,
        'message' => $result['ok']
            /* translators: %s: recipient address. */
            ? sprintf( __( 'Test email sent to %s.', 'rocinante' ), $to )
            /* translators: 1: recipient address, 2: the error text. */
            : sprintf( __( 'The test email to %1$s failed: %2$s', 'rocinante' ), $to, $result['error'] ),
    );
}


// ============================================================
// THE TEST BUTTON (Theme Settings → Mail)
// ============================================================

/**
 * admin-post.php?action=roci_mail_test — nonce + manage_options; the result is
 * kept for this user for a minute and shown on the Mail tab.
 */
function roci_mail_handle_test() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do that.', 'rocinante' ), 403 );
    }

    check_admin_referer( 'roci_mail_test', '_roci_mail_test_nonce' );

    $to     = isset( $_POST['roci_mail_test_to'] ) ? sanitize_email( wp_unslash( $_POST['roci_mail_test_to'] ) ) : '';
    $result = roci_mail_send_test( $to );

    set_transient( 'roci_mail_test_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

    wp_safe_redirect( admin_url( 'admin.php?page=roci-theme-settings&tab=mail&roci_mail_tested=1' ) );
    exit;
}
add_action( 'admin_post_roci_mail_test', 'roci_mail_handle_test' );

/**
 * roci_mail_take_test_result()
 *
 * (internal) This user's last test result, once.
 *
 * @return array|null
 */
function roci_mail_take_test_result() {
    $key    = 'roci_mail_test_' . get_current_user_id();
    $result = get_transient( $key );

    if ( false === $result ) {
        return null;
    }

    delete_transient( $key );

    return is_array( $result ) ? $result : null;
}


// ============================================================
// WP-CLI
// ============================================================

/**
 * `wp roci mail-test [<to>]` — send a test email; print success or the error.
 *
 * @param array $args
 */
function roci_mail_cli_test( $args ) {
    $result = roci_mail_send_test( isset( $args[0] ) ? $args[0] : '' );

    WP_CLI::log( roci_mail_status()['text'] );

    if ( $result['ok'] ) {
        WP_CLI::success( $result['message'] );
    } else {
        WP_CLI::error( $result['message'] );
    }
}

/**
 * `wp roci mail-status` — the same status line as Theme Settings → Mail.
 */
function roci_mail_cli_status() {
    $status = roci_mail_status();

    WP_CLI::log( $status['text'] );

    $from = roci_mail_setting( 'from_email' );
    /* translators: %s: email address or "(not set)". */
    WP_CLI::log( sprintf( __( 'From email: %s', 'rocinante' ), '' !== $from ? $from : __( '(not set)', 'rocinante' ) ) );
    /* translators: %s: email address or "(none)". */
    WP_CLI::log( sprintf( __( 'Notifications to: %s', 'rocinante' ), '' !== roci_mail_recipient() ? roci_mail_recipient() : __( '(none)', 'rocinante' ) ) );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'roci mail-test', 'roci_mail_cli_test' );
    WP_CLI::add_command( 'roci mail-status', 'roci_mail_cli_status' );
}
