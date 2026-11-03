<?php
/**
 * Forms — the private submissions CPT (roci_submission) and its admin
 *
 * Every accepted submission is stored here before it is mailed, so a mail
 * failure never loses a message. NO FRONT END: not public, not queryable, no
 * rewrite, no REST, out of search and the sitemaps. Admins only — every
 * capability maps to manage_options — and nobody can "Add new".
 *
 * STORED PER SUBMISSION (and nothing else — no IP, no user agent, no token):
 *   post_title          "{name} — {form label}" (or the email, or the label)
 *   post_date           when it arrived
 *   _roci_form_id       the form's id
 *   _roci_form_values   field id => array( 'label' => …, 'value' => … ), as
 *                       submitted after sanitising
 *   _roci_form_name     } the role-mapped values, ALSO as their own keys, so
 *   _roci_form_email    } the admin list can sort and the privacy exporter /
 *   _roci_form_site     } eraser can match on the email
 *   _roci_form_status   new | read | held (no security check could run)
 *   _roci_form_mailed   '1' | '0'
 *   _roci_form_source   the page URL it was sent from
 *
 * ⚠ THE ONE SERIALISED VALUE — A REASONED EXCEPTION to parent CLAUDE.md §12.3
 *   (option-per-field, no serialised blobs), approved by the owner
 *   2026-11-03 (decision D12). A submission is an immutable record whose
 *   field set differs per form and per site; one meta row per field would
 *   scatter a single message over a variable number of keys that nothing
 *   queries. Everything that IS queried — form, name, email, site, status —
 *   has its own key above.
 *
 * ADMIN: Submissions menu (dashicons-email-alt). List columns Date · Form ·
 * Name · Email · Site · Status; a Form filter; Date / Form / Status sortable;
 * "Mark as read" row + bulk actions. Opening one marks it read; its screen is
 * a read-only box of label → value (no editor, no title field). Notices: a
 * Turnstile key reminder and a missing-recipient warning on these screens.
 *
 * File:    inc/forms/forms-cpt.php
 * Version: 1.0.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * roci_register_submission_cpt()
 */
function roci_register_submission_cpt() {
    if ( ! roci_forms_enabled() ) {
        return;
    }

    $admin = 'manage_options';

    register_post_type( 'roci_submission', array(
        'labels'              => array(
            'name'               => __( 'Form submissions', 'rocinante' ),
            'singular_name'      => __( 'Form submission', 'rocinante' ),
            'menu_name'          => __( 'Submissions', 'rocinante' ),
            'all_items'          => __( 'All submissions', 'rocinante' ),
            'edit_item'          => __( 'Submission', 'rocinante' ),
            'view_item'          => __( 'View submission', 'rocinante' ),
            'search_items'       => __( 'Search submissions', 'rocinante' ),
            'not_found'          => __( 'No submissions yet.', 'rocinante' ),
            'not_found_in_trash' => __( 'No submissions in the trash.', 'rocinante' ),
        ),
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'show_in_admin_bar'   => false,
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-email-alt',
        'menu_position'       => 26,
        'supports'            => false,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'can_export'          => false,
        'map_meta_cap'        => true,
        'capabilities'        => array(
            'create_posts'           => 'do_not_allow',
            'edit_posts'             => $admin,
            'edit_others_posts'      => $admin,
            'edit_published_posts'   => $admin,
            'edit_private_posts'     => $admin,
            'publish_posts'          => $admin,
            'read_private_posts'     => $admin,
            'delete_posts'           => $admin,
            'delete_others_posts'    => $admin,
            'delete_published_posts' => $admin,
            'delete_private_posts'   => $admin,
        ),
    ) );
}
add_action( 'init', 'roci_register_submission_cpt' );

/**
 * roci_store_submission()
 *
 * Store one accepted submission. Returns its ID, or 0 on failure.
 *
 * @param  array  $form
 * @param  array  $values field id => sanitised value
 * @param  bool   $held   No security check could run.
 * @param  string $source The page URL.
 * @return int
 */
function roci_store_submission( $form, $values, $held, $source ) {
    $list = array();
    foreach ( $form['fields'] as $id => $field ) {
        $list[ $id ] = array(
            'label' => $field['label'],
            'value' => isset( $values[ $id ] ) ? (string) $values[ $id ] : '',
        );
    }

    $name  = roci_form_role_value( $form, $values, 'name' );
    $email = roci_form_role_value( $form, $values, 'email' );
    $site  = roci_form_role_value( $form, $values, 'site' );

    if ( '' !== $name ) {
        /* translators: 1: sender name, 2: form label. */
        $title = sprintf( __( '%1$s — %2$s', 'rocinante' ), $name, $form['label'] );
    } else {
        $title = '' !== $email ? $email : $form['label'];
    }

    // wp_insert_post() expects slashed data and unslashes it (meta included).
    $post_id = wp_insert_post( wp_slash( array(
        'post_type'   => 'roci_submission',
        'post_status' => 'publish',
        'post_title'  => wp_strip_all_tags( $title ),
        'meta_input'  => array(
            '_roci_form_id'     => $form['id'],
            '_roci_form_values' => $list,
            '_roci_form_name'   => $name,
            '_roci_form_email'  => $email,
            '_roci_form_site'   => $site,
            '_roci_form_status' => $held ? 'held' : 'new',
            '_roci_form_mailed' => '0',
            '_roci_form_source' => esc_url_raw( $source ),
        ),
    ) ), true );

    return is_wp_error( $post_id ) ? 0 : (int) $post_id;
}

/**
 * roci_submission_statuses()
 *
 * Status value => admin label.
 *
 * @return array<string, string>
 */
function roci_submission_statuses() {
    return array(
        'new'  => __( 'New', 'rocinante' ),
        'read' => __( 'Read', 'rocinante' ),
        'held' => __( 'Held — unverified', 'rocinante' ),
    );
}


// ------------------------------------------------------------
// The list screen
// ------------------------------------------------------------

/**
 * Columns: Date · Form · Name · Email · Site · Status.
 */
function roci_submission_columns( $columns ) {
    return array(
        'cb'          => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
        'title'       => __( 'Submission', 'rocinante' ),
        'roci_date'   => __( 'Date', 'rocinante' ),
        'roci_form'   => __( 'Form', 'rocinante' ),
        'roci_name'   => __( 'Name', 'rocinante' ),
        'roci_email'  => __( 'Email', 'rocinante' ),
        'roci_site'   => __( 'Site', 'rocinante' ),
        'roci_status' => __( 'Status', 'rocinante' ),
    );
}
add_filter( 'manage_roci_submission_posts_columns', 'roci_submission_columns' );

/**
 * One column's cell.
 */
function roci_submission_column( $column, $post_id ) {
    switch ( $column ) {
        case 'roci_date':
            echo esc_html( get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post_id ) );
            break;

        case 'roci_form':
            $form_id = (string) get_post_meta( $post_id, '_roci_form_id', true );
            $form    = roci_get_form( $form_id );
            echo esc_html( $form ? $form['label'] : $form_id );
            break;

        case 'roci_name':
            echo esc_html( (string) get_post_meta( $post_id, '_roci_form_name', true ) );
            break;

        case 'roci_email':
            $email = (string) get_post_meta( $post_id, '_roci_form_email', true );
            if ( is_email( $email ) ) {
                echo '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
            }
            break;

        case 'roci_site':
            $site = (string) get_post_meta( $post_id, '_roci_form_site', true );
            if ( '' !== $site ) {
                echo '<a href="' . esc_url( $site ) . '" target="_blank" rel="noopener noreferrer nofollow">' . esc_html( preg_replace( '#^https?://#i', '', $site ) ) . '</a>';
            }
            break;

        case 'roci_status':
            $statuses = roci_submission_statuses();
            $status   = (string) get_post_meta( $post_id, '_roci_form_status', true );
            echo esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status );
            if ( '0' === (string) get_post_meta( $post_id, '_roci_form_mailed', true ) ) {
                echo '<br><span style="color:#b32d2e;">' . esc_html__( 'Email not sent', 'rocinante' ) . '</span>';
            }
            break;
    }
}
add_action( 'manage_roci_submission_posts_custom_column', 'roci_submission_column', 10, 2 );

/**
 * Sortable: Date, Form, Status.
 */
function roci_submission_sortable( $columns ) {
    $columns['roci_date']   = 'date';
    $columns['roci_form']   = 'roci_form';
    $columns['roci_status'] = 'roci_status';

    return $columns;
}
add_filter( 'manage_edit-roci_submission_sortable_columns', 'roci_submission_sortable' );

/**
 * The Form filter dropdown.
 */
function roci_submission_filter_dropdown( $post_type ) {
    if ( 'roci_submission' !== $post_type ) {
        return;
    }

    $current = isset( $_GET['roci_form'] ) ? sanitize_key( wp_unslash( $_GET['roci_form'] ) ) : '';
    ?>
    <label class="screen-reader-text" for="roci-form-filter"><?php esc_html_e( 'Filter by form', 'rocinante' ); ?></label>
    <select name="roci_form" id="roci-form-filter">
        <option value=""><?php esc_html_e( 'All forms', 'rocinante' ); ?></option>
        <?php foreach ( roci_get_forms() as $form ) : ?>
            <option value="<?php echo esc_attr( $form['id'] ); ?>" <?php selected( $current, $form['id'] ); ?>><?php echo esc_html( $form['label'] ); ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}
add_action( 'restrict_manage_posts', 'roci_submission_filter_dropdown' );

/**
 * The list query: the Form filter and the meta sorts.
 *
 * @param WP_Query $query
 */
function roci_submission_list_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || 'roci_submission' !== $query->get( 'post_type' ) ) {
        return;
    }

    // The filter is a meta_query, so it never collides with a meta sort below.
    $form = isset( $_GET['roci_form'] ) ? sanitize_key( wp_unslash( $_GET['roci_form'] ) ) : '';
    if ( '' !== $form ) {
        $query->set( 'meta_query', array(
            array(
                'key'   => '_roci_form_id',
                'value' => $form,
            ),
        ) );
    }

    $orderby = $query->get( 'orderby' );
    if ( 'roci_form' === $orderby || 'roci_status' === $orderby ) {
        $query->set( 'meta_key', 'roci_form' === $orderby ? '_roci_form_id' : '_roci_form_status' );
        $query->set( 'orderby', 'meta_value' );
    }
}
add_action( 'pre_get_posts', 'roci_submission_list_query' );

/**
 * Row actions: drop Quick Edit and View; add Mark as read.
 */
function roci_submission_row_actions( $actions, $post ) {
    if ( 'roci_submission' !== $post->post_type ) {
        return $actions;
    }

    unset( $actions['inline hide-if-no-js'], $actions['view'] );

    if ( 'read' !== get_post_meta( $post->ID, '_roci_form_status', true ) ) {
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=roci_submission_read&post=' . $post->ID ), 'roci_submission_read_' . $post->ID );
        $actions['roci_read'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Mark as read', 'rocinante' ) . '</a>';
    }

    return $actions;
}
add_filter( 'post_row_actions', 'roci_submission_row_actions', 10, 2 );

/**
 * The Mark as read row action (admin-post.php, nonced, admins only).
 */
function roci_submission_mark_read_action() {
    $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

    check_admin_referer( 'roci_submission_read_' . $post_id );

    if ( ! $post_id || 'roci_submission' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( esc_html__( 'You cannot change this submission.', 'rocinante' ) );
    }

    update_post_meta( $post_id, '_roci_form_status', 'read' );

    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=roci_submission' ) );
    exit;
}
add_action( 'admin_post_roci_submission_read', 'roci_submission_mark_read_action' );

/**
 * Bulk actions: drop Edit; add Mark as read (Move to Trash stays).
 */
function roci_submission_bulk_actions( $actions ) {
    unset( $actions['edit'] );
    $actions['roci_mark_read'] = __( 'Mark as read', 'rocinante' );

    return $actions;
}
add_filter( 'bulk_actions-edit-roci_submission', 'roci_submission_bulk_actions' );

/**
 * Handle the Mark as read bulk action (core has checked the nonce).
 */
function roci_submission_handle_bulk( $redirect, $action, $ids ) {
    if ( 'roci_mark_read' !== $action ) {
        return $redirect;
    }

    foreach ( (array) $ids as $id ) {
        if ( current_user_can( 'edit_post', $id ) ) {
            update_post_meta( (int) $id, '_roci_form_status', 'read' );
        }
    }

    return $redirect;
}
add_filter( 'handle_bulk_actions-edit-roci_submission', 'roci_submission_handle_bulk', 10, 3 );


// ------------------------------------------------------------
// The single screen — read-only
// ------------------------------------------------------------

/**
 * The read-only details box; opening a New submission marks it Read.
 *
 * @param WP_Post $post
 */
function roci_submission_meta_boxes( $post ) {
    add_meta_box( 'roci-submission-details', __( 'Submission', 'rocinante' ), 'roci_submission_details_box', 'roci_submission', 'normal', 'high' );

    if ( 'new' === get_post_meta( $post->ID, '_roci_form_status', true ) ) {
        update_post_meta( $post->ID, '_roci_form_status', 'read' );
    }
}
add_action( 'add_meta_boxes_roci_submission', 'roci_submission_meta_boxes' );

/**
 * The details: label → value (emails and URLs as links), then the form, the
 * page it came from, the status and whether it was mailed. Escaped; nothing
 * here is editable.
 *
 * @param WP_Post $post
 */
function roci_submission_details_box( $post ) {
    $values   = get_post_meta( $post->ID, '_roci_form_values', true );
    $values   = is_array( $values ) ? $values : array();
    $form_id  = (string) get_post_meta( $post->ID, '_roci_form_id', true );
    $form     = roci_get_form( $form_id );
    $statuses = roci_submission_statuses();
    $status   = (string) get_post_meta( $post->ID, '_roci_form_status', true );
    $source   = (string) get_post_meta( $post->ID, '_roci_form_source', true );
    ?>
    <table class="widefat striped" style="border:0;">
        <tbody>
            <?php foreach ( $values as $row ) : ?>
                <?php
                $value = isset( $row['value'] ) ? (string) $row['value'] : '';
                $label = isset( $row['label'] ) ? (string) $row['label'] : '';
                ?>
                <tr>
                    <th scope="row" style="width:220px;"><?php echo esc_html( $label ); ?></th>
                    <td>
                        <?php if ( is_email( $value ) ) : ?>
                            <a href="<?php echo esc_url( 'mailto:' . $value ); ?>"><?php echo esc_html( $value ); ?></a>
                        <?php elseif ( preg_match( '#^https?://#i', $value ) ) : ?>
                            <a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html( $value ); ?></a>
                        <?php elseif ( '' === $value ) : ?>
                            —
                        <?php else : ?>
                            <?php echo nl2br( esc_html( $value ) ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p>
        <strong><?php esc_html_e( 'Form:', 'rocinante' ); ?></strong> <?php echo esc_html( $form ? $form['label'] : $form_id ); ?>
        · <strong><?php esc_html_e( 'Status:', 'rocinante' ); ?></strong> <?php echo esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status ); ?>
        · <strong><?php esc_html_e( 'Email:', 'rocinante' ); ?></strong> <?php echo '1' === (string) get_post_meta( $post->ID, '_roci_form_mailed', true ) ? esc_html__( 'sent', 'rocinante' ) : esc_html__( 'not sent', 'rocinante' ); ?>
    </p>
    <?php if ( '' !== $source ) : ?>
        <p><strong><?php esc_html_e( 'Sent from:', 'rocinante' ); ?></strong> <a href="<?php echo esc_url( $source ); ?>"><?php echo esc_html( $source ); ?></a></p>
    <?php endif; ?>
    <?php if ( 'held' === $status ) : ?>
        <p><?php esc_html_e( 'Held: the visitor\'s browser did not run JavaScript, so the security check could not run. It may be spam.', 'rocinante' ); ?></p>
    <?php endif; ?>
    <?php
}

/**
 * Admin notices on the Submissions screens: no Turnstile keys; no recipient.
 */
function roci_submission_admin_notices() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( ! roci_forms_enabled() || ! $screen || 'roci_submission' !== $screen->post_type ) {
        return;
    }

    if ( ! roci_forms_turnstile_keys() ) {
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'Cloudflare Turnstile is not configured: forms are protected by a honeypot and a rate limit only. Add ROCI_TURNSTILE_SITE_KEY and ROCI_TURNSTILE_SECRET_KEY to wp-config.php.', 'rocinante' ) . '</p></div>';
    }

    $missing = array();
    foreach ( roci_get_forms() as $form ) {
        if ( '' === roci_form_recipient_for( $form ) ) {
            $missing[] = $form['label'];
        }
    }

    if ( $missing ) {
        /* translators: %s: comma-separated form labels. */
        echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'No valid email to send these forms to: %s. Submissions are stored but not emailed. Set Theme Settings → Business → Email.', 'rocinante' ), implode( ', ', $missing ) ) ) . '</p></div>';
    }
}
add_action( 'admin_notices', 'roci_submission_admin_notices' );
