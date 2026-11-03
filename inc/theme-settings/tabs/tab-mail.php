<?php
/**
 * Theme Settings — Mail Tab (v8.1.0)
 *
 * Included by settings-page.php inside roci_settings_page(). Option row
 * roci_mail: from_name, from_email, recipient. The SMTP credentials are
 * wp-config.php constants and are NEVER shown here (inc/mail/mail.php); this
 * tab shows the read-only status line instead, with the user masked.
 *
 * THE TEST BUTTON sits inside the settings form (the page has one form), so it
 * posts to admin-post.php with formaction and its own nonce field; the settings
 * form's fields ride along and are ignored there. It sends with the SAVED
 * settings — save first.
 *
 * File:    inc/theme-settings/tabs/tab-mail.php
 * Version: 1.0.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_mail_group' );

$mail      = get_option( 'roci_mail', array() );
$mail      = is_array( $mail ) ? $mail : array();
$from_name = isset( $mail['from_name'] ) ? $mail['from_name'] : '';
$from      = isset( $mail['from_email'] ) ? $mail['from_email'] : '';
$recipient = isset( $mail['recipient'] ) ? $mail['recipient'] : '';
$status    = roci_mail_status();
$result    = ! empty( $_GET['roci_mail_tested'] ) ? roci_mail_take_test_result() : null;
$effective = roci_mail_recipient();
$host      = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );

$status_class = array(
    'active'   => 'notice-success',
    'plugin'   => 'notice-info',
    'disabled' => 'notice-warning',
    'off'      => 'notice-warning',
);
?>

<?php settings_errors( 'roci_mail' ); // A top-level menu page does not print these by itself. ?>

<?php if ( $result ) : ?>
    <div class="notice <?php echo $result['ok'] ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $result['message'] ); ?></p></div>
<?php endif; ?>

<h2 class="roci-section-title"><?php esc_html_e( 'Status', 'rocinante' ); ?></h2>
<div class="notice <?php echo esc_attr( $status_class[ $status['state'] ] ); ?> inline">
    <p><strong><?php echo esc_html( $status['text'] ); ?></strong></p>
    <?php if ( 'plugin' === $status['state'] ) : ?>
        <p><?php esc_html_e( 'The From and SMTP settings below are not used while that plugin is active. Deactivate it to let El Rocinante send this site\'s mail.', 'rocinante' ); ?></p>
    <?php elseif ( 'active' !== $status['state'] ) : ?>
        <p><?php esc_html_e( 'The From settings below take effect only while El Rocinante\'s SMTP is active.', 'rocinante' ); ?></p>
    <?php endif; ?>
</div>
<p class="roci-note"><?php esc_html_e( 'SMTP credentials live in wp-config.php only (ROCI_SMTP_HOST, ROCI_SMTP_PORT, ROCI_SMTP_USER, ROCI_SMTP_PASS, ROCI_SMTP_SECURE) and are never stored or shown here.', 'rocinante' ); ?></p>

<h2 class="roci-section-title"><?php esc_html_e( 'Sender', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_mail_from_name"><?php esc_html_e( 'From Name', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_mail[from_name]" id="roci_mail_from_name" class="regular-text" value="<?php echo esc_attr( $from_name ); ?>" placeholder="<?php echo esc_attr( wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ) ); ?>">
            <p class="roci-note"><?php esc_html_e( 'The name people see in their inbox, e.g. the business name.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_mail_from_email"><?php esc_html_e( 'From Email', 'rocinante' ); ?></label></th>
        <td>
            <input type="email" name="roci_mail[from_email]" id="roci_mail_from_email" class="regular-text" value="<?php echo esc_attr( $from ); ?>" placeholder="<?php echo esc_attr( 'hello@' . preg_replace( '/^www\./', '', $host ) ); ?>">
            <p class="roci-note"><?php esc_html_e( 'An address on a domain you have verified with your mail provider (SPF and DKIM). It replaces WordPress\'s default wordpress@… sender only; mail that sets its own From keeps it.', 'rocinante' ); ?></p>
            <?php if ( '' !== $from && ! roci_mail_domain_matches( $from ) ) : ?>
                <div class="notice notice-warning inline"><p>
                    <?php
                    echo esc_html( sprintf(
                        /* translators: 1: the From domain, 2: the site's host. */
                        __( 'The From domain (%1$s) is not this site\'s domain (%2$s). That is fine only if %1$s is verified in your mail provider (SPF and DKIM); otherwise messages may land in spam or be rejected.', 'rocinante' ),
                        substr( strrchr( $from, '@' ), 1 ),
                        $host
                    ) );
                    ?>
                </p></div>
            <?php endif; ?>
            <?php if ( '' === $from && 'active' === $status['state'] ) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'No From email set: mail goes out as wordpress@…, which most providers reject. Set one on a verified domain.', 'rocinante' ); ?></p></div>
            <?php endif; ?>
        </td>
    </tr>
</table>

<h2 class="roci-section-title"><?php esc_html_e( 'Notifications', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_mail_recipient"><?php esc_html_e( 'Form Notifications To', 'rocinante' ); ?></label></th>
        <td>
            <input type="email" name="roci_mail[recipient]" id="roci_mail_recipient" class="regular-text" value="<?php echo esc_attr( $recipient ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Where form submissions are emailed (unless a form names its own recipient in code). Separate from the From address. Empty → the earlier Forms setting, then Business → Email, then the site admin email.', 'rocinante' ); ?></p>
            <p class="roci-note">
                <?php
                echo esc_html( '' !== $effective
                    /* translators: %s: email address. */
                    ? sprintf( __( 'Notifications currently go to: %s', 'rocinante' ), $effective )
                    : __( 'No valid notification address is set anywhere.', 'rocinante' ) );
                ?>
            </p>
        </td>
    </tr>
</table>

<h2 class="roci-section-title"><?php esc_html_e( 'Test', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_mail_test_to"><?php esc_html_e( 'Send a Test Email', 'rocinante' ); ?></label></th>
        <td>
            <?php wp_nonce_field( 'roci_mail_test', '_roci_mail_test_nonce' ); ?>
            <input type="email" name="roci_mail_test_to" id="roci_mail_test_to" class="regular-text" value="" placeholder="<?php echo esc_attr( $effective ); ?>">
            <button type="submit" class="button" name="action" value="roci_mail_test" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formnovalidate><?php esc_html_e( 'Send test email', 'rocinante' ); ?></button>
            <p class="roci-note"><?php esc_html_e( 'Sends through wp_mail() with the SAVED settings (save first). Empty address → the notification address. Shows the provider\'s error if it fails.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>
