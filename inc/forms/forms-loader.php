<?php
/**
 * Forms (roci-forms) — loader, gate and shared helpers
 *
 * The parent's forms module (v8.0.0). OPT-IN: a child turns it on with
 *
 *     add_theme_support( 'roci-forms' );
 *
 * in its own after_setup_theme callback. Without that call NOTHING runs — no
 * CPT, no shortcode, no assets, no cron, no settings section, no privacy
 * hooks. The files below only DEFINE functions; every hook checks
 * roci_forms_enabled() when it runs.
 *
 * ⚠ WHY THE GATE IS CHECKED AT RUN TIME, NOT HERE. The parent's functions.php
 *   loads after the child's and BEFORE after_setup_theme fires, so at file
 *   load current_theme_supports( 'roci-forms' ) is always false. The
 *   roci-loader and roci-tour-layout gates follow the same rule.
 *
 * THE MODULE, file by file:
 *   forms-loader.php    the gate, the Turnstile key reader, the client IP, the
 *                       Integrations → Forms settings section
 *   forms-registry.php  the roci_forms filter → roci_get_forms() / roci_get_form()
 *   forms-render.php    [roci_form id="…"], the part's args, roci_form_classes
 *   forms-handler.php   the POST: honeypot, rate limit, Turnstile, validation,
 *                       store, mail, redirect — roci_process_form_submission(),
 *                       the one pipeline
 *   forms-rest.php      the background submit (8.0.1): POST roci/v1/form/{id},
 *                       the same pipeline, JSON back, never cached
 *   forms-cpt.php       the private roci_submission CPT and its admin screens
 *   forms-privacy.php   retention cron, personal-data exporter / eraser, the
 *                       privacy-policy guide text
 *   template-parts/form.php        the markup (override the part, not this)
 *   dist/js/forms.js               enhancement only — the background submit,
 *                                  in-place errors / success, focus, "Sending…"
 *   Build: components/_forms.scss  structural rules under :where(.roci-form)
 *
 * A CHILD:
 *   1. add_theme_support( 'roci-forms' );            (after_setup_theme)
 *   2. add_filter( 'roci_forms', … );                (file scope — see forms-registry.php)
 *   3. [roci_form id="contact"] where the form goes  (content, or do_shortcode())
 *   4. styles the .roci-form BEM classes; may add its button class through
 *      roci_form_classes
 *   5. on the server: ROCI_TURNSTILE_SITE_KEY + ROCI_TURNSTILE_SECRET_KEY in
 *      wp-config.php, working SMTP, and Theme Settings → Business → Email
 *
 * CACHING: the GET markup carries NO per-visitor value (no nonce, no
 * timestamp; the Turnstile token is made in the browser), POST responses are
 * never page-cached, and the success page (?roci_sent=…) holds no personal
 * data — so a page with a form needs NO page-cache exclusion. Owner decision
 * D4 (2026-11-03): no nonce. See forms-handler.php.
 *
 * 1.1.0 (8.1.0) — the recipient field moved to Theme Settings → Mail; this
 * section now links there (an 8.0.x value is still honoured).
 *
 * File:    inc/forms/forms-loader.php
 * Version: 1.1.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * roci_forms_enabled()
 *
 * Whether the child has opted in. The ONE place the support string is read.
 * Meaningful from after_setup_theme on (see the file header).
 *
 * @return bool
 */
function roci_forms_enabled() {
    return current_theme_supports( 'roci-forms' );
}

/**
 * roci_forms_turnstile_keys()
 *
 * The Cloudflare Turnstile keys, from wp-config.php constants ONLY —
 * ROCI_TURNSTILE_SITE_KEY and ROCI_TURNSTILE_SECRET_KEY. Never stored in the
 * database, a repo or a setting. Both must be non-empty strings, or the
 * module runs without Turnstile (honeypot + rate limit) and says so in the
 * admin (decision D3).
 *
 * @return array{site: string, secret: string}|array{} Both keys, or array() when not configured.
 */
function roci_forms_turnstile_keys() {
    $site   = defined( 'ROCI_TURNSTILE_SITE_KEY' ) ? trim( (string) ROCI_TURNSTILE_SITE_KEY ) : '';
    $secret = defined( 'ROCI_TURNSTILE_SECRET_KEY' ) ? trim( (string) ROCI_TURNSTILE_SECRET_KEY ) : '';

    return ( '' !== $site && '' !== $secret ) ? array( 'site' => $site, 'secret' => $secret ) : array();
}

/**
 * roci_forms_client_ip()
 *
 * The client IP for the rate limit — never stored (it is hashed into a
 * 10-minute transient key). REMOTE_ADDR by default; Cloudflare's
 * CF-Connecting-IP header only when the site defines ROCI_FORMS_TRUST_CF_IP
 * as true, because the header is client-supplied unless the site really sits
 * behind Cloudflare's proxy (decision D10).
 *
 * @return string A valid IP, or '' when none can be read.
 */
function roci_forms_client_ip() {
    if ( defined( 'ROCI_FORMS_TRUST_CF_IP' ) && ROCI_FORMS_TRUST_CF_IP && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $ip = filter_var( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ), FILTER_VALIDATE_IP );
        if ( $ip ) {
            return $ip;
        }
    }

    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : false;

    return $ip ? $ip : '';
}

/**
 * roci_forms_retention_days()
 *
 * Theme Settings → Integrations → Forms → retention, in days. 730 by default
 * (decision D7); 0 keeps submissions forever.
 *
 * @return int
 */
function roci_forms_retention_days() {
    $days = roci_setting( 'integrations', 'forms_retention_days', '' );

    return '' === $days ? 730 : absint( $days );
}

/**
 * roci_forms_settings_section()
 *
 * The Forms section at the foot of Theme Settings → Integrations (decision
 * D9), on the tab's roci_integrations_extra action — inside the tab's own
 * form, so its fields save with roci_integrations. Rendered only when the
 * child has opted in; roci_sanitize_integrations() keeps the stored values
 * when the section is absent, so a site that turns forms off loses nothing.
 */
function roci_forms_settings_section() {
    if ( ! roci_forms_enabled() ) {
        return;
    }

    $integrations = get_option( 'roci_integrations', array() );
    $recipient    = isset( $integrations['forms_recipient'] ) ? $integrations['forms_recipient'] : '';
    $keys         = roci_forms_turnstile_keys();
    ?>
    <h2 class="roci-section-title"><?php esc_html_e( 'Forms', 'rocinante' ); ?></h2>
    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Cloudflare Turnstile', 'rocinante' ); ?></th>
            <td>
                <?php if ( $keys ) : ?>
                    <strong><?php esc_html_e( 'Configured', 'rocinante' ); ?></strong>
                <?php else : ?>
                    <strong><?php esc_html_e( 'Not configured', 'rocinante' ); ?></strong>
                    <p class="roci-note"><?php esc_html_e( 'Forms work, protected by a honeypot and a rate limit only. Add ROCI_TURNSTILE_SITE_KEY and ROCI_TURNSTILE_SECRET_KEY to wp-config.php to turn the check on.', 'rocinante' ); ?></p>
                <?php endif; ?>
                <p class="roci-note"><?php esc_html_e( 'The keys live in wp-config.php only and are never shown here.', 'rocinante' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Send Submissions To', 'rocinante' ); ?></th>
            <td>
                <?php
                // 8.1.0: the recipient moved to Theme Settings → Mail. A value
                // saved here in 8.0.x is still honoured after Mail's own field
                // (roci_mail_recipient()) and is kept on save (the sanitiser
                // keeps it when the field is absent).
                $roci_mail_url = admin_url( 'admin.php?page=roci-theme-settings&tab=mail' );
                ?>
                <p><?php esc_html_e( 'Now set in Theme Settings → Mail → Form notifications to.', 'rocinante' ); ?> <a href="<?php echo esc_url( $roci_mail_url ); ?>"><?php esc_html_e( 'Open the Mail tab', 'rocinante' ); ?></a></p>
                <?php if ( '' !== $recipient ) : ?>
                    <p class="roci-note">
                        <?php
                        /* translators: %s: email address saved in an earlier version. */
                        echo esc_html( sprintf( __( 'Saved here earlier, still used while the Mail tab\'s field is empty: %s', 'rocinante' ), $recipient ) );
                        ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><label for="roci_forms_retention_days"><?php esc_html_e( 'Delete Submissions After', 'rocinante' ); ?></label></th>
            <td>
                <input type="number" min="0" step="1" name="roci_integrations[forms_retention_days]" id="roci_forms_retention_days" class="small-text" value="<?php echo esc_attr( roci_forms_retention_days() ); ?>">
                <?php esc_html_e( 'days', 'rocinante' ); ?>
                <p class="roci-note"><?php esc_html_e( 'Older submissions are deleted permanently once a day. 0 keeps them forever. Default 730 (two years).', 'rocinante' ); ?></p>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'roci_integrations_extra', 'roci_forms_settings_section' );

require_once get_template_directory() . '/inc/forms/forms-registry.php';
require_once get_template_directory() . '/inc/forms/forms-render.php';
require_once get_template_directory() . '/inc/forms/forms-handler.php';
require_once get_template_directory() . '/inc/forms/forms-rest.php';
require_once get_template_directory() . '/inc/forms/forms-cpt.php';
require_once get_template_directory() . '/inc/forms/forms-privacy.php';
