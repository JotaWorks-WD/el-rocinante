<?php
/**
 * Forms — privacy: retention, export, erase, policy guide text
 *
 * RETENTION: a daily WP-Cron event, roci_forms_cleanup, PERMANENTLY deletes
 * submissions older than Theme Settings → Integrations → Forms → "Delete
 * Submissions After" (default 730 days, decision D7; 0 keeps them forever).
 * Scheduled only while the module is on; cleared when it is off. On a
 * low-traffic site WP-Cron runs on visits, so a server cron calling
 * wp-cron.php is more punctual.
 *
 * EXPORT / ERASE: registered with WordPress's own privacy tools (Tools →
 * Export Personal Data / Erase Personal Data), matching submissions on the
 * email-role field (_roci_form_email). Erasing deletes the submission
 * outright — a message has no part worth keeping without its sender.
 *
 * POLICY TEXT: a suggested paragraph in Settings → Privacy → the policy
 * guide (wp_add_privacy_policy_content()), so each site's policy can say
 * what the forms store and that Cloudflare Turnstile runs.
 *
 * File:    inc/forms/forms-privacy.php
 * Version: 1.0.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * roci_forms_schedule_cleanup()
 *
 * Keep the daily event in step with the opt-in.
 */
function roci_forms_schedule_cleanup() {
    $next = wp_next_scheduled( 'roci_forms_cleanup' );

    if ( roci_forms_enabled() && ! $next ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'roci_forms_cleanup' );
    } elseif ( ! roci_forms_enabled() && $next ) {
        wp_clear_scheduled_hook( 'roci_forms_cleanup' );
    }
}
add_action( 'init', 'roci_forms_schedule_cleanup' );

/**
 * roci_forms_cleanup()
 *
 * Delete submissions past the retention period, 200 per run (the rest go on
 * the next run). 0 days = keep everything.
 */
function roci_forms_cleanup() {
    $days = roci_forms_retention_days();

    if ( ! roci_forms_enabled() || 0 === $days ) {
        return;
    }

    $ids = get_posts( array(
        'post_type'        => 'roci_submission',
        'post_status'      => 'any',
        'numberposts'      => 200,
        'fields'           => 'ids',
        'suppress_filters' => true,
        'date_query'       => array(
            array(
                'before'    => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
                'column'    => 'post_date_gmt',
                'inclusive' => true,
            ),
        ),
    ) );

    foreach ( $ids as $id ) {
        wp_delete_post( (int) $id, true );
    }
}
add_action( 'roci_forms_cleanup', 'roci_forms_cleanup' );

/**
 * roci_forms_submissions_for_email()
 *
 * (internal) Submission IDs whose email-role field matches, one page.
 *
 * @param  string $email
 * @param  int    $page  1-based.
 * @return int[]
 */
function roci_forms_submissions_for_email( $email, $page = 1 ) {
    return array_map( 'intval', get_posts( array(
        'post_type'        => 'roci_submission',
        'post_status'      => 'any',
        'numberposts'      => 50,
        'paged'            => max( 1, (int) $page ),
        'fields'           => 'ids',
        'suppress_filters' => true,
        'meta_query'       => array(
            array(
                'key'   => '_roci_form_email',
                'value' => sanitize_email( $email ),
            ),
        ),
    ) ) );
}

/**
 * Register the exporter and the eraser (module on only).
 */
function roci_forms_register_exporter( $exporters ) {
    if ( roci_forms_enabled() ) {
        $exporters['roci-forms'] = array(
            'exporter_friendly_name' => __( 'Form submissions', 'rocinante' ),
            'callback'               => 'roci_forms_export_personal_data',
        );
    }

    return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'roci_forms_register_exporter' );

function roci_forms_register_eraser( $erasers ) {
    if ( roci_forms_enabled() ) {
        $erasers['roci-forms'] = array(
            'eraser_friendly_name' => __( 'Form submissions', 'rocinante' ),
            'callback'             => 'roci_forms_erase_personal_data',
        );
    }

    return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'roci_forms_register_eraser' );

/**
 * The exporter: every submission sent with this email, as label → value.
 *
 * @param  string $email
 * @param  int    $page
 * @return array{data: array, done: bool}
 */
function roci_forms_export_personal_data( $email, $page = 1 ) {
    $ids  = roci_forms_submissions_for_email( $email, $page );
    $data = array();

    foreach ( $ids as $id ) {
        $values = get_post_meta( $id, '_roci_form_values', true );
        $items  = array(
            array( 'name' => __( 'Date', 'rocinante' ), 'value' => get_the_date( 'c', $id ) ),
            array( 'name' => __( 'Form', 'rocinante' ), 'value' => (string) get_post_meta( $id, '_roci_form_id', true ) ),
        );

        foreach ( is_array( $values ) ? $values : array() as $row ) {
            $items[] = array(
                'name'  => isset( $row['label'] ) ? (string) $row['label'] : '',
                'value' => isset( $row['value'] ) ? (string) $row['value'] : '',
            );
        }

        $data[] = array(
            'group_id'    => 'roci-forms',
            'group_label' => __( 'Form submissions', 'rocinante' ),
            'item_id'     => 'roci-submission-' . $id,
            'data'        => $items,
        );
    }

    return array(
        'data' => $data,
        'done' => count( $ids ) < 50,
    );
}

/**
 * The eraser: deletes every submission sent with this email. Always reads
 * page 1 — the rows it just deleted are gone, so the next batch moves up.
 *
 * @param  string $email
 * @param  int    $page  Unused (see above).
 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
 */
function roci_forms_erase_personal_data( $email, $page = 1 ) {
    $ids     = roci_forms_submissions_for_email( $email, 1 );
    $removed = false;

    foreach ( $ids as $id ) {
        if ( wp_delete_post( $id, true ) ) {
            $removed = true;
        }
    }

    return array(
        'items_removed'  => $removed,
        'items_retained' => false,
        'messages'       => array(),
        'done'           => count( $ids ) < 50,
    );
}

/**
 * The suggested privacy-policy text (Settings → Privacy → policy guide).
 */
function roci_forms_privacy_policy_text() {
    if ( ! roci_forms_enabled() || ! function_exists( 'wp_add_privacy_policy_content' ) ) {
        return;
    }

    $days = roci_forms_retention_days();

    $text = '<p>' . esc_html__( 'When you send a form on this site, the details you enter (for example your name, email address and message) are stored on this website and emailed to us so we can reply. We do not store your IP address.', 'rocinante' ) . '</p>'
        . '<p>' . esc_html__( 'Forms are protected by Cloudflare Turnstile, which checks that a real person is sending them. To do this, Cloudflare processes technical information from your browser.', 'rocinante' ) . '</p>'
        . '<p>' . ( $days
            /* translators: %d: number of days. */
            ? esc_html( sprintf( __( 'Form submissions are deleted automatically after %d days, or sooner on request.', 'rocinante' ), $days ) )
            : esc_html__( 'Form submissions are kept until you ask us to delete them.', 'rocinante' ) ) . '</p>';

    wp_add_privacy_policy_content( __( 'Forms (El Rocinante)', 'rocinante' ), wp_kses_post( $text ) );
}
add_action( 'admin_init', 'roci_forms_privacy_policy_text' );
