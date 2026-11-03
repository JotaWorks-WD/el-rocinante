<?php
/**
 * Forms — the registry: forms are declared in code by the child
 *
 * A child declares its forms on the roci_forms filter, at FILE SCOPE in its
 * functions.php (the result is memoised per request — a filter added after
 * the first read is ignored, the same trap as the post-type providers):
 *
 *   add_filter( 'roci_forms', function ( $forms ) {
 *       $forms['contact'] = array(
 *           'label'           => 'Contact form',          // admin list + email subject
 *           'fields'          => array(
 *               array( 'id' => 'name',    'type' => 'text',     'label' => 'Your name', 'required' => true, 'role' => 'name' ),
 *               array( 'id' => 'email',   'type' => 'email',    'label' => 'Email',     'required' => true, 'role' => 'email' ),
 *               array( 'id' => 'site',    'type' => 'url',      'label' => 'Website',   'role' => 'site', 'help' => 'If you have one.' ),
 *               array( 'id' => 'topic',   'type' => 'select',   'label' => 'Topic',     'options' => array( 'a' => 'Option A', 'b' => 'Option B' ) ),
 *               array( 'id' => 'message', 'type' => 'textarea', 'label' => 'Message',   'required' => true ),
 *           ),
 *           'submit_label'    => 'Send',
 *           'success_message' => 'Thanks — your message is on its way.',
 *           'recipient'       => '',   // '' → Integrations → Forms, then Business email
 *           'subject'         => '',   // '' → "[Site] New {label} from {name}"
 *       );
 *       return $forms;
 *   } );
 *
 * FIELD KEYS: id ([a-z0-9_], unique in the form), type (text | email | url |
 * select | textarea — nothing else), label, required (bool), help, options
 * (select only: value => label, required for a select), autocomplete,
 * role (name | email | site — feeds the admin columns, the export / erase
 * match and Reply-To; one field per role), maxlength (default 200, textarea
 * 5000).
 *
 * The parent ships NO forms (content-agnostic, parent CLAUDE.md §12.4).
 * An invalid field is dropped, and in WP_DEBUG the form prints a
 * jw_debug_comment() saying why; a form left with no fields is dropped.
 *
 * File:    inc/forms/forms-registry.php
 * Version: 1.0.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * roci_forms_field_types()
 *
 * The supported field types.
 *
 * @return string[]
 */
function roci_forms_field_types() {
    return array( 'text', 'email', 'url', 'select', 'textarea' );
}

/**
 * roci_get_forms()
 *
 * Every form the child declared, normalised. Memoised per request; array()
 * when the module is off.
 *
 * Each form: label, fields (normalised), submit_label, success_message,
 * recipient, subject, notices (WP_DEBUG messages about dropped fields).
 *
 * @return array<string, array>
 */
function roci_get_forms() {
    static $forms = null;

    if ( null !== $forms ) {
        return $forms;
    }

    // Before after_setup_theme the opt-in cannot be known yet: answer "none"
    // WITHOUT caching it, so a too-early call never freezes the empty list.
    if ( ! did_action( 'after_setup_theme' ) ) {
        return array();
    }

    $forms = array();

    if ( ! roci_forms_enabled() ) {
        return $forms;
    }

    $declared = apply_filters( 'roci_forms', array() );

    foreach ( is_array( $declared ) ? $declared : array() as $id => $form ) {
        $id = sanitize_key( (string) $id );

        if ( '' === $id || ! is_array( $form ) ) {
            continue;
        }

        $notices = array();
        $fields  = array();
        $roles   = array();

        foreach ( isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array() as $field ) {
            $field = roci_forms_normalise_field( $field, $notices );

            if ( ! $field ) {
                continue;
            }
            if ( isset( $fields[ $field['id'] ] ) ) {
                $notices[] = sprintf( 'roci_forms: field "%s" in form "%s" is declared twice; the second was dropped.', $field['id'], $id );
                continue;
            }
            if ( '' !== $field['role'] ) {
                if ( isset( $roles[ $field['role'] ] ) ) {
                    $notices[] = sprintf( 'roci_forms: role "%s" in form "%s" is already on "%s"; dropped from "%s".', $field['role'], $id, $roles[ $field['role'] ], $field['id'] );
                    $field['role'] = '';
                } else {
                    $roles[ $field['role'] ] = $field['id'];
                }
            }

            $fields[ $field['id'] ] = $field;
        }

        if ( ! $fields ) {
            continue;
        }

        $label = isset( $form['label'] ) && '' !== trim( (string) $form['label'] ) ? trim( (string) $form['label'] ) : $id;

        $forms[ $id ] = array(
            'id'              => $id,
            'label'           => $label,
            'fields'          => $fields,
            'roles'           => $roles,
            'submit_label'    => isset( $form['submit_label'] ) && '' !== trim( (string) $form['submit_label'] ) ? trim( (string) $form['submit_label'] ) : __( 'Send', 'rocinante' ),
            'success_message' => isset( $form['success_message'] ) && '' !== trim( (string) $form['success_message'] ) ? trim( (string) $form['success_message'] ) : __( 'Thank you — your message has been sent.', 'rocinante' ),
            'recipient'       => isset( $form['recipient'] ) ? sanitize_email( (string) $form['recipient'] ) : '',
            'subject'         => isset( $form['subject'] ) ? trim( (string) $form['subject'] ) : '',
            'notices'         => $notices,
        );
    }

    return $forms;
}

/**
 * roci_get_form()
 *
 * One normalised form, or null.
 *
 * @param  string $id
 * @return array|null
 */
function roci_get_form( $id ) {
    $forms = roci_get_forms();
    $id    = sanitize_key( (string) $id );

    return isset( $forms[ $id ] ) ? $forms[ $id ] : null;
}

/**
 * roci_forms_normalise_field()
 *
 * (internal) One declared field → the normalised shape, or null (with a
 * notice) when it cannot be used.
 *
 * @param  mixed    $field
 * @param  string[] $notices Collected WP_DEBUG notices (by reference).
 * @return array|null
 */
function roci_forms_normalise_field( $field, &$notices ) {
    if ( ! is_array( $field ) ) {
        return null;
    }

    $id   = isset( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : '';
    $type = isset( $field['type'] ) ? (string) $field['type'] : 'text';

    if ( '' === $id ) {
        $notices[] = 'roci_forms: a field has no id and was dropped.';
        return null;
    }
    if ( ! in_array( $type, roci_forms_field_types(), true ) ) {
        $notices[] = sprintf( 'roci_forms: field "%s" has unsupported type "%s" and was dropped.', $id, $type );
        return null;
    }

    $options = array();
    if ( 'select' === $type ) {
        foreach ( isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array() as $value => $label ) {
            $value = (string) $value;
            if ( '' !== $value ) {
                $options[ $value ] = (string) $label;
            }
        }
        if ( ! $options ) {
            $notices[] = sprintf( 'roci_forms: select "%s" has no options and was dropped.', $id );
            return null;
        }
    }

    $role = isset( $field['role'] ) ? (string) $field['role'] : '';
    $role = in_array( $role, array( 'name', 'email', 'site' ), true ) ? $role : '';

    $autocomplete = array( 'email' => 'email', 'url' => 'url' );
    if ( 'name' === $role ) {
        $autocomplete['text'] = 'name';
    }

    $max = 'textarea' === $type ? 5000 : 200;

    return array(
        'id'           => $id,
        'type'         => $type,
        'label'        => isset( $field['label'] ) && '' !== trim( (string) $field['label'] ) ? trim( (string) $field['label'] ) : $id,
        'required'     => ! empty( $field['required'] ),
        'help'         => isset( $field['help'] ) ? trim( (string) $field['help'] ) : '',
        'options'      => $options,
        'autocomplete' => isset( $field['autocomplete'] ) ? sanitize_text_field( (string) $field['autocomplete'] ) : ( isset( $autocomplete[ $type ] ) ? $autocomplete[ $type ] : '' ),
        'role'         => $role,
        'maxlength'    => isset( $field['maxlength'] ) && absint( $field['maxlength'] ) ? absint( $field['maxlength'] ) : $max,
    );
}
