<?php
/**
 * Form — the markup of one roci-forms form
 *
 * Rendered by roci_render_form() (inc/forms/forms-render.php) for
 * [roci_form id="…"]. Override THIS PART in a child for different structure;
 * the args are documented on roci_render_form(). Keep every id, the ARIA
 * wiring and the field names (roci_fields[…], roci_form_id, roci_js,
 * roci_website): the handler and dist/js/forms.js depend on them.
 *
 * ACCESSIBILITY (WCAG 2.2 AA):
 *   - every control has a <label for>; required is said IN TEXT —
 *     "(required)" in the label — plus aria-required; never an asterisk alone
 *   - help text and the error both join the control's aria-describedby;
 *     an invalid control carries aria-invalid="true"
 *   - errors: a summary at the top of the form (heading + a link to each
 *     field, and any form-level message), then each message above its field
 *   - focus WITHOUT JavaScript: the form posts to "#roci-form-{id}", and the
 *     summary / the success message carry tabindex="-1" + autofocus, so the
 *     reply lands on them. Nothing here needs JS to work.
 *   - WITH JavaScript (8.0.1) forms.js submits in the background and draws
 *     the summary, the field errors and the success message IN PLACE, from
 *     the same classes and ids as below: ⚠ a child override of this part
 *     must keep them: the root id and the roci_form_id input (without them
 *     forms.js uses the page POST), roci-form-{id}-{field} on each control
 *     and its -help id (without them a field's error shows in the summary
 *     only), .roci-form__field around each control, .roci-form__actions
 *     around the submit.
 *   - novalidate: every message is the server's, in the site's words, not a
 *     browser bubble
 *   - the honeypot is hidden from sight AND from assistive tech
 *     (aria-hidden, tabindex="-1"), so no person ever fills it in
 *
 * ASSETS: forms.js always; Cloudflare's Turnstile script only when the keys
 * are configured — both enqueued HERE, so only a page that shows a form
 * loads them (the parent's one-script-per-component rule, CLAUDE.md §8).
 *
 * BEM, styled by the child (the parent ships structure only,
 * components/_forms.scss): .roci-form (--has-errors, --sent) · __note ·
 * __errors · __errors-title · __errors-list · __field (--{type}, --invalid)
 * · __label · __required · __help · __error · __input / __select /
 * __textarea · __hp · __turnstile · __actions · __submit · __success
 *
 * File:    template-parts/form.php
 * Version: 1.0.1
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$roci_form    = isset( $args['form'] ) ? $args['form'] : null;
$roci_state   = isset( $args['state'] ) ? $args['state'] : 'form';
$roci_values  = isset( $args['values'] ) && is_array( $args['values'] ) ? $args['values'] : array();
$roci_errors  = isset( $args['errors'] ) && is_array( $args['errors'] ) ? $args['errors'] : array();
$roci_classes = isset( $args['classes'] ) ? $args['classes'] : roci_get_form_classes( '' );
$roci_key     = isset( $args['site_key'] ) ? (string) $args['site_key'] : '';

if ( ! $roci_form ) {
    return;
}

$roci_id         = 'roci-form-' . $roci_form['id'];
$roci_form_notes =isset( $roci_errors['_form'] ) ? (array) $roci_errors['_form'] : array();
unset( $roci_errors['_form'] );

wp_enqueue_script( 'el-rocinante-forms' );

// ------------------------------------------------------------ SENT ---------
if ( 'sent' === $roci_state ) : ?>
    <div class="<?php echo roci_class_attr( array_merge( $roci_classes['form'], array( 'roci-form--sent' ) ) ); ?>" id="<?php echo esc_attr( $roci_id ); ?>">
        <p class="<?php echo roci_class_attr( $roci_classes['success'] ); ?>" id="<?php echo esc_attr( $roci_id . '-success' ); ?>" tabindex="-1" autofocus data-roci-focus><?php echo esc_html( $roci_form['success_message'] ); ?></p>
    </div>
    <?php
    return;
endif;

if ( $roci_key ) {
    wp_enqueue_script( 'el-rocinante-turnstile' );
}

$roci_has_errors = $roci_errors || $roci_form_notes;
$roci_form_class = $roci_classes['form'];
if ( $roci_has_errors ) {
    $roci_form_class[] = 'roci-form--has-errors';
}
$roci_any_required = (bool) array_filter( wp_list_pluck( $roci_form['fields'], 'required' ) );
?>
<form class="<?php echo roci_class_attr( $roci_form_class ); ?>" id="<?php echo esc_attr( $roci_id ); ?>" method="post" action="<?php echo esc_url( $args['action'] ); ?>" novalidate>

    <?php if ( $roci_has_errors ) : ?>
        <div class="roci-form__errors" id="<?php echo esc_attr( $roci_id . '-errors' ); ?>" tabindex="-1" autofocus data-roci-focus>
            <h2 class="roci-form__errors-title"><?php echo esc_html( roci_form_errors_title( count( $roci_errors ) + count( $roci_form_notes ) ) ); ?></h2>
            <ul class="roci-form__errors-list">
                <?php foreach ( $roci_errors as $roci_field_id => $roci_message ) : ?>
                    <li><a href="#<?php echo esc_attr( $roci_id . '-' . $roci_field_id ); ?>"><?php echo esc_html( $roci_message ); ?></a></li>
                <?php endforeach; ?>
                <?php foreach ( $roci_form_notes as $roci_message ) : ?>
                    <li><?php echo esc_html( $roci_message ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ( $roci_any_required ) : ?>
        <p class="roci-form__note"><?php esc_html_e( 'Fields marked (required) must be filled in.', 'rocinante' ); ?></p>
    <?php endif; ?>

    <?php foreach ( $roci_form['fields'] as $roci_field ) : ?>
        <?php
        $roci_fid      = $roci_id . '-' . $roci_field['id'];
        $roci_invalid  = isset( $roci_errors[ $roci_field['id'] ] );
        $roci_value    = isset( $roci_values[ $roci_field['id'] ] ) ? (string) $roci_values[ $roci_field['id'] ] : '';
        $roci_describe = array();
        if ( '' !== $roci_field['help'] ) {
            $roci_describe[] = $roci_fid . '-help';
        }
        if ( $roci_invalid ) {
            $roci_describe[] = $roci_fid . '-error';
        }
        $roci_field_class = array( 'roci-form__field', 'roci-form__field--' . $roci_field['type'] );
        if ( $roci_invalid ) {
            $roci_field_class[] = 'roci-form__field--invalid';
        }
        $roci_control_atts = ' id="' . esc_attr( $roci_fid ) . '" name="roci_fields[' . esc_attr( $roci_field['id'] ) . ']"'
            . ( $roci_field['required'] ? ' aria-required="true"' : '' )
            . ( $roci_invalid ? ' aria-invalid="true"' : '' )
            . ( $roci_describe ? ' aria-describedby="' . esc_attr( implode( ' ', $roci_describe ) ) . '"' : '' )
            . ( '' !== $roci_field['autocomplete'] ? ' autocomplete="' . esc_attr( $roci_field['autocomplete'] ) . '"' : '' );
        ?>
        <div class="<?php echo roci_class_attr( $roci_field_class ); ?>">
            <label class="roci-form__label" for="<?php echo esc_attr( $roci_fid ); ?>">
                <?php echo esc_html( $roci_field['label'] ); ?>
                <?php if ( $roci_field['required'] ) : ?>
                    <span class="roci-form__required"><?php esc_html_e( '(required)', 'rocinante' ); ?></span>
                <?php endif; ?>
            </label>

            <?php if ( '' !== $roci_field['help'] ) : ?>
                <p class="roci-form__help" id="<?php echo esc_attr( $roci_fid . '-help' ); ?>"><?php echo esc_html( $roci_field['help'] ); ?></p>
            <?php endif; ?>

            <?php if ( $roci_invalid ) : ?>
                <p class="roci-form__error" id="<?php echo esc_attr( $roci_fid . '-error' ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Error:', 'rocinante' ); ?> </span><?php echo esc_html( $roci_errors[ $roci_field['id'] ] ); ?></p>
            <?php endif; ?>

            <?php if ( 'textarea' === $roci_field['type'] ) : ?>
                <textarea class="roci-form__textarea"<?php echo $roci_control_atts; ?> rows="6" maxlength="<?php echo esc_attr( $roci_field['maxlength'] ); ?>"><?php echo esc_textarea( $roci_value ); ?></textarea>
            <?php elseif ( 'select' === $roci_field['type'] ) : ?>
                <select class="roci-form__select"<?php echo $roci_control_atts; ?>>
                    <option value=""><?php esc_html_e( 'Choose…', 'rocinante' ); ?></option>
                    <?php foreach ( $roci_field['options'] as $roci_opt_value => $roci_opt_label ) : ?>
                        <option value="<?php echo esc_attr( $roci_opt_value ); ?>" <?php selected( $roci_value, (string) $roci_opt_value ); ?>><?php echo esc_html( $roci_opt_label ); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else : ?>
                <input class="roci-form__input" type="<?php echo esc_attr( $roci_field['type'] ); ?>"<?php echo $roci_control_atts; ?> value="<?php echo esc_attr( $roci_value ); ?>" maxlength="<?php echo esc_attr( $roci_field['maxlength'] ); ?>"<?php echo 'email' === $roci_field['type'] ? ' spellcheck="false"' : ''; ?>>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="roci-form__hp" aria-hidden="true">
        <label for="<?php echo esc_attr( $roci_id . '-website' ); ?>"><?php esc_html_e( 'Leave this field empty', 'rocinante' ); ?></label>
        <input type="text" id="<?php echo esc_attr( $roci_id . '-website' ); ?>" name="roci_website" value="" tabindex="-1" autocomplete="off">
    </div>

    <?php if ( $roci_key ) : ?>
        <div class="roci-form__turnstile">
            <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $roci_key ); ?>" data-action="<?php echo esc_attr( substr( str_replace( '_', '-', $roci_form['id'] ), 0, 32 ) ); ?>"></div>
        </div>
    <?php endif; ?>

    <input type="hidden" name="roci_form_id" value="<?php echo esc_attr( $roci_form['id'] ); ?>">
    <input type="hidden" name="roci_js" value="">

    <div class="roci-form__actions">
        <button type="submit" class="<?php echo roci_class_attr( $roci_classes['submit'] ); ?>"><?php echo esc_html( $roci_form['submit_label'] ); ?></button>
    </div>
</form>
