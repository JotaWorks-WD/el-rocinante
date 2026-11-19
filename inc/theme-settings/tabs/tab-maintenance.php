<?php
/**
 * Theme Settings — Maintenance Tab
 *
 * Rendered by roci_maintenance_settings_tab_panel() on
 * roci_settings_tab_maintenance (the tab is added to roci_settings_tabs by
 * inc/maintenance-mode.php, after SEO), inside the Theme Settings form.
 *
 * Two parts:
 *   1. Maintenance mode — option roci_maintenance (enabled, message), moved
 *      here from the SEO tab in 8.9.0. MUST STAY IN SYNC with
 *      roci_sanitize_maintenance(), which rebuilds the option from scratch.
 *   2. Standby status — read-only rows from roci_standby_status(): each row
 *      shows a dashicon AND a word (OK / Missing / Out of date / Warning), so
 *      the state is never colour-only, plus one "how to fix" line when it
 *      fails. Nothing here is submitted.
 *
 * 1.1.0 (El Rocinante 8.9.1): "Install roci-standby" and "Install / Update
 * drop-in" buttons on their rows (nonce'd admin-post.php links; the panel
 * sits inside the settings form, so no nested form), the result notice after
 * the redirect, and one note instead of the buttons when files cannot be
 * changed from here (DISALLOW_FILE_MODS, or FTP credentials needed).
 *
 * 1.1.1 (El Rocinante 8.11.1): the "any install button?" test reads the rows
 * with array_column(), so a row without 'action' raises no PHP warning. Output
 * unchanged.
 *
 * File:    inc/theme-settings/tabs/tab-maintenance.php
 * Version: 1.1.1
 * Updated: 2026-11-19
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

settings_fields( 'roci_maintenance_group' );
$maintenance         = get_option( 'roci_maintenance', array() );
$maintenance         = is_array( $maintenance ) ? $maintenance : array();
$maintenance_enabled = isset( $maintenance['enabled'] ) ? (string) $maintenance['enabled'] : '';
$maintenance_message = isset( $maintenance['message'] ) ? (string) $maintenance['message'] : '';

$roci_states = array(
    'ok'       => array( 'dashicons-yes-alt', __( 'OK', 'rocinante' ), '#00a32a' ),
    'missing'  => array( 'dashicons-dismiss', __( 'Missing', 'rocinante' ), '#d63638' ),
    'outdated' => array( 'dashicons-update', __( 'Out of date', 'rocinante' ), '#d63638' ),
    'warn'     => array( 'dashicons-warning', __( 'Warning', 'rocinante' ), '#996800' ),
);
?>
<h2 class="roci-section-title" id="roci-maintenance"><?php esc_html_e( 'Maintenance mode', 'rocinante' ); ?></h2>
<table class="form-table">
    <tr>
        <th><label for="roci_maintenance_enabled"><?php esc_html_e( 'Maintenance mode', 'rocinante' ); ?></label></th>
        <td>
            <input type="checkbox" name="roci_maintenance[enabled]" id="roci_maintenance_enabled" value="1" <?php checked( $maintenance_enabled, '1' ); ?>>
            <label for="roci_maintenance_enabled"><?php esc_html_e( 'Turn on maintenance mode', 'rocinante' ); ?></label>
            <p class="roci-note"><?php esc_html_e( 'Visitors see a "back soon" page (HTTP 503). Logged-in administrators see the live site. Turn it off when you are done.', 'rocinante' ); ?></p>
            <p class="roci-note"><?php esc_html_e( 'Turning it on or off purges the page cache.', 'rocinante' ); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="roci_maintenance_message"><?php esc_html_e( 'Message', 'rocinante' ); ?></label></th>
        <td>
            <input type="text" name="roci_maintenance[message]" id="roci_maintenance_message" class="large-text" maxlength="200" value="<?php echo esc_attr( $maintenance_message ); ?>">
            <p class="roci-note"><?php esc_html_e( 'Optional, one line, shown under "We\'re making a few improvements". Leave empty for the default.', 'rocinante' ); ?></p>
        </td>
    </tr>
</table>

<h2 class="roci-section-title" id="roci-standby-status"><?php esc_html_e( 'Standby status', 'rocinante' ); ?></h2>
<?php
// 1.1.0 (8.9.1): the result of an install button, kept for one redirect.
$roci_notice = roci_standby_notice_take();
if ( $roci_notice ) :
    ?>
    <div class="notice notice-<?php echo 'error' === $roci_notice['type'] ? 'error' : 'success'; ?> inline"><p><?php echo esc_html( $roci_notice['message'] ); ?></p></div>
<?php endif; ?>
<p class="roci-note"><?php esc_html_e( 'The safety net behind maintenance mode and crashes. Every row should say OK.', 'rocinante' ); ?></p>
<?php
$roci_rows     = roci_standby_status();
$roci_can_fix  = roci_standby_can_modify_files();
// 1.1.1: array_column(), not wp_list_pluck() — a row without an 'action' key
// is skipped instead of raising "Undefined array key" (roci_standby_status()
// now gives every row one; this keeps the tab quiet if a row ever lacks it).
$roci_has_btns = (bool) array_filter( array_column( $roci_rows, 'action' ) );
if ( $roci_has_btns && is_wp_error( $roci_can_fix ) ) :
    ?>
    <p class="roci-note"><?php echo esc_html( $roci_can_fix->get_error_message() . ' ' . __( 'One-click install is not available here, so follow the steps below by hand.', 'rocinante' ) ); ?></p>
<?php endif; ?>
<style>
    .roci-standby-status td .dashicons { vertical-align: text-bottom; margin-right: 4px; }
    .roci-standby-status .roci-swatches { display: flex; flex-wrap: wrap; gap: 12px; margin: 8px 0 0; padding: 0; list-style: none; }
    .roci-standby-status .roci-swatch { display: inline-block; width: 20px; height: 20px; margin-right: 6px; vertical-align: middle; border: 1px solid #8c8f94; border-radius: 3px; }
    .roci-standby-status .roci-logo-thumb { display: block; max-height: 48px; width: auto; margin-top: 8px; }
</style>
<table class="widefat striped roci-standby-status">
    <tbody>
    <?php foreach ( $roci_rows as $roci_row ) : $roci_state = isset( $roci_states[ $roci_row['state'] ] ) ? $roci_states[ $roci_row['state'] ] : $roci_states['missing']; ?>
        <tr>
            <th scope="row"><?php echo esc_html( $roci_row['label'] ); ?></th>
            <td>
                <span class="dashicons <?php echo esc_attr( $roci_state[0] ); ?>" style="color:<?php echo esc_attr( $roci_state[2] ); ?>" aria-hidden="true"></span><strong><?php echo esc_html( $roci_state[1] ); ?></strong>
                <?php echo esc_html( $roci_row['detail'] ); ?>
                <?php if ( '' !== $roci_row['fix'] ) : ?>
                    <p class="roci-note"><?php echo esc_html( $roci_row['fix'] ); ?></p>
                <?php endif; ?>
                <?php if ( ! empty( $roci_row['action'] ) && true === $roci_can_fix ) : ?>
                    <p><a class="button" href="<?php echo esc_url( roci_standby_action_url( $roci_row['action'] ) ); ?>"><?php echo esc_html( $roci_row['button'] ); ?></a></p>
                <?php endif; ?>
                <?php if ( ! empty( $roci_row['swatches'] ) ) : ?>
                    <ul class="roci-swatches">
                        <?php foreach ( $roci_row['swatches'] as $roci_key => $roci_hex ) : ?>
                            <li><span class="roci-swatch" style="background:<?php echo esc_attr( $roci_hex ); ?>" aria-hidden="true"></span><?php echo esc_html( $roci_key ); ?> <code><?php echo esc_html( $roci_hex ); ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ( ! empty( $roci_row['logo'] ) ) : ?>
                    <img class="roci-logo-thumb" src="<?php echo esc_url( $roci_row['logo'] ); ?>" alt="<?php esc_attr_e( 'Logo in the snapshot', 'rocinante' ); ?>">
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
