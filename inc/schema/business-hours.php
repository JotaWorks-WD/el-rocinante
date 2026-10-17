<?php
/**
 * Business Hours — Day Map, Reader and openingHoursSpecification Builder
 *
 * The weekly opening hours for the site-level LocalBusiness schema, stored as
 * roci_business['hours'] by the Business settings tab.
 *
 * ONE SOURCE, THREE CONSUMERS: roci_business_days() is the day map the Business
 * settings tab renders its seven rows from, roci_sanitize_business() whitelists
 * the stored day keys from (MUST read this function, never a local list of
 * seven), and roci_get_business_hours() normalises the stored row against. Keep
 * the three in agreement via THIS map.
 *
 * READ PATH: roci_get_business_hours() (filterable via roci_business_hours) →
 * roci_group_business_hours() (consecutive identical days merged into runs) →
 * roci_business_hours_spec() (the schema.org node list). header.php reads the
 * spec; a child that later prints "Mon–Fri 08:00–17:00" reads the SAME grouping,
 * so the visible hours and the structured data cannot disagree.
 *
 * NO FRONT-END DISPLAY IS SHIPPED. No shortcode, template part or CSS: the
 * parent emits the hours in structured data only.
 *
 * File:    inc/schema/business-hours.php
 * Version: 1.0.0
 * Updated: 2026-10-17
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The day map, Monday first.
 *
 * Keyed by the STORAGE key — the key within roci_business['hours'], and the one
 * the Business tab's input names are built from. Each entry carries:
 *
 *   label  Admin-facing day name. Translated, so the map is only ever built at
 *          call time (admin render, save, schema assembly). Never call this
 *          function at file scope, where __() would run before the text domain
 *          loads.
 *   schema The schema.org DayOfWeek member the day emits as. NOT translatable —
 *          a translated enumeration URL is an invalid one.
 *
 * ⚠ KEYS ARE THE STORED CONTRACT AND MUST NOT BE RENAMED. A key change orphans
 * every day already saved under the old one.
 *
 * ORDER IS MEANINGFUL: the grouping walks the week in declaration order, so
 * only days adjacent here can merge into one run.
 *
 * Deliberately NOT filterable. The week is fixed; there is no eighth day a
 * child could register.
 *
 * @return array Map of day key => array( label, schema ).
 */
function roci_business_days() {
    return array(
        'mon' => array( 'label' => __( 'Monday', 'rocinante' ),    'schema' => 'https://schema.org/Monday' ),
        'tue' => array( 'label' => __( 'Tuesday', 'rocinante' ),   'schema' => 'https://schema.org/Tuesday' ),
        'wed' => array( 'label' => __( 'Wednesday', 'rocinante' ), 'schema' => 'https://schema.org/Wednesday' ),
        'thu' => array( 'label' => __( 'Thursday', 'rocinante' ),  'schema' => 'https://schema.org/Thursday' ),
        'fri' => array( 'label' => __( 'Friday', 'rocinante' ),    'schema' => 'https://schema.org/Friday' ),
        'sat' => array( 'label' => __( 'Saturday', 'rocinante' ),  'schema' => 'https://schema.org/Saturday' ),
        'sun' => array( 'label' => __( 'Sunday', 'rocinante' ),    'schema' => 'https://schema.org/Sunday' ),
    );
}

/**
 * The day states, in select order.
 *
 * ONE SOURCE, THREE CONSUMERS: the Business tab renders each day's state select
 * from it, roci_sanitize_business() whitelists the saved state against its
 * keys, and roci_get_business_hours() does the same on read. Never a local list.
 *
 *   ''      Not set — publishes nothing for the day
 *   open    Open — the opens / closes times apply
 *   closed  Closed — publishes nothing for the day
 *   24h     Open 24 hours — emitted as 00:00–23:59
 *
 * ⚠ KEYS ARE THE STORED CONTRACT AND MUST NOT BE RENAMED; the grouping tests
 * 'open' and '24h' by value. Labels are translated, so call this at call time
 * only, never at file scope. Not filterable: a new state would need emit rules
 * the grouping does not have.
 *
 * @return array Map of state key => admin-facing label.
 */
function roci_business_hours_states() {
    return array(
        ''       => __( 'Not set', 'rocinante' ),
        'open'   => __( 'Open', 'rocinante' ),
        'closed' => __( 'Closed', 'rocinante' ),
        '24h'    => __( 'Open 24 hours', 'rocinante' ),
    );
}

/**
 * Normalise one time value to 24-hour 'HH:MM', or '' when it is not a time.
 *
 * Accepts what type="time" submits ('08:00', or '08:00:00' with a step under a
 * minute) and what a text fallback in an old browser is likely to carry
 * ('8:00'). Seconds are dropped; the hour is zero-padded.
 *
 * ⚠ '24:00' IS REJECTED. It is not a valid schema.org Time. A close at midnight
 * is entered as '00:00', which the overnight rule then reads correctly.
 *
 * Pure: no WordPress reads, so the save path and the harness share it.
 *
 * @param mixed $raw A submitted or stored time.
 * @return string 'HH:MM' or ''.
 */
function roci_sanitize_business_time( $raw ) {

    if ( ! is_scalar( $raw ) ) {
        return '';
    }

    if ( ! preg_match( '/^(\d{1,2}):(\d{2})(:\d{2})?$/', trim( (string) $raw ), $roci_m ) ) {
        return '';
    }

    $roci_hour   = (int) $roci_m[1];
    $roci_minute = (int) $roci_m[2];

    if ( $roci_hour > 23 || $roci_minute > 59 ) {
        return '';
    }

    return sprintf( '%02d:%02d', $roci_hour, $roci_minute );
}

/**
 * The business hours, one normalised row per day, Monday first.
 *
 * Always returns all seven day keys, each carrying:
 *
 *   state     '' (not set) | 'open' | 'closed' | '24h'
 *   opens     'HH:MM', or '' unless state is 'open'
 *   closes    'HH:MM', or '' unless state is 'open'
 *   label     the translated day name, from roci_business_days()
 *   overnight true when an open day closes after midnight (closes < opens)
 *
 * A site that has never saved the Business tab on 7.5.0 has no 'hours' key, and
 * gets seven unset days — so no openingHoursSpecification is emitted.
 *
 * ⚠ array() DEFAULT PLUS AN is_array() BELT. roci_setting() hands back its
 * default for an unset key, and that default is '' unless told otherwise — the
 * amenities trap. A string here would turn every day lookup below into a string
 * offset.
 *
 * FILTERABLE: roci_business_hours receives the STORED day rows (keyed mon … sun,
 * each array( state, opens, closes )) BEFORE normalisation, so a code-owned
 * child can supply its hours without the dashboard and they still pass through
 * the same rules as saved ones. Whatever comes back reaches the schema and any
 * later display at once. Not memoized.
 *
 * Filtered rows never pass the save path, so they are normalised here: the
 * state is checked against roci_business_hours_states(), and an 'open' day
 * without two distinct valid times reads as not set.
 *
 * @return array Map of day key => array( state, opens, closes, label, overnight ).
 */
function roci_get_business_hours() {

    $roci_stored = roci_setting( 'business', 'hours', array() );
    if ( ! is_array( $roci_stored ) ) {
        $roci_stored = array();
    }

    $roci_stored = apply_filters( 'roci_business_hours', $roci_stored );
    if ( ! is_array( $roci_stored ) ) {
        $roci_stored = array();
    }

    $roci_hours = array();

    foreach ( roci_business_days() as $roci_day_key => $roci_day ) {

        $roci_row   = ( isset( $roci_stored[ $roci_day_key ] ) && is_array( $roci_stored[ $roci_day_key ] ) ) ? $roci_stored[ $roci_day_key ] : array();
        $roci_state = ( isset( $roci_row['state'] ) && is_string( $roci_row['state'] ) ) ? $roci_row['state'] : '';
        $roci_state = array_key_exists( $roci_state, roci_business_hours_states() ) ? $roci_state : '';

        $roci_opens  = '';
        $roci_closes = '';

        if ( 'open' === $roci_state ) {
            $roci_opens  = roci_sanitize_business_time( isset( $roci_row['opens'] ) ? $roci_row['opens'] : '' );
            $roci_closes = roci_sanitize_business_time( isset( $roci_row['closes'] ) ? $roci_row['closes'] : '' );

            if ( '' === $roci_opens || '' === $roci_closes || $roci_opens === $roci_closes ) {
                $roci_state  = '';
                $roci_opens  = '';
                $roci_closes = '';
            }
        }

        $roci_hours[ $roci_day_key ] = array(
            'state'     => $roci_state,
            'opens'     => $roci_opens,
            'closes'    => $roci_closes,
            'label'     => $roci_day['label'],
            // 'HH:MM' compares correctly as a string.
            'overnight' => ( 'open' === $roci_state && $roci_closes < $roci_opens ),
        );
    }

    return $roci_hours;
}

/**
 * Group the week into runs of consecutive days with identical hours.
 *
 * Walks Monday → Sunday in roci_business_days() order. An 'open' or '24h' day
 * joins the current run when the previous day is in it with the same state and
 * the same times; any other day ends the run. Closed and unset days produce
 * nothing.
 *
 * NO WEEK WRAP. A Sunday + Monday pair with the same hours is two runs — valid
 * schema, just less compact — because the brief asks for consecutive days
 * within the week as written.
 *
 * '24h' resolves to opens '00:00', closes '23:59' here (Google's documented
 * form for "open all day"), so the spec builder and any display read one shape.
 * A 24h run keeps state '24h', so a display can say "Open 24 hours" instead.
 *
 * Pure apart from the translated day map: give it roci_get_business_hours()'s
 * shape (state / opens / closes per day key); unknown keys are ignored.
 *
 * @param array $hours Map of day key => array( state, opens, closes, … ).
 * @return array List of array( 'days' => day keys, 'state', 'opens', 'closes' ).
 */
function roci_group_business_hours( $hours ) {

    $hours      = is_array( $hours ) ? $hours : array();
    $roci_runs  = array();
    $roci_index = -1;    // index of the open run in $roci_runs, -1 for none

    foreach ( array_keys( roci_business_days() ) as $roci_day_key ) {

        $roci_row   = ( isset( $hours[ $roci_day_key ] ) && is_array( $hours[ $roci_day_key ] ) ) ? $hours[ $roci_day_key ] : array();
        $roci_state = isset( $roci_row['state'] ) ? $roci_row['state'] : '';

        if ( '24h' === $roci_state ) {
            $roci_opens  = '00:00';
            $roci_closes = '23:59';
        } elseif ( 'open' === $roci_state ) {
            $roci_opens  = roci_sanitize_business_time( isset( $roci_row['opens'] ) ? $roci_row['opens'] : '' );
            $roci_closes = roci_sanitize_business_time( isset( $roci_row['closes'] ) ? $roci_row['closes'] : '' );

            if ( '' === $roci_opens || '' === $roci_closes || $roci_opens === $roci_closes ) {
                $roci_index = -1;
                continue;
            }
        } else {
            $roci_index = -1;
            continue;
        }

        if ( $roci_index >= 0
            && $roci_runs[ $roci_index ]['state'] === $roci_state
            && $roci_runs[ $roci_index ]['opens'] === $roci_opens
            && $roci_runs[ $roci_index ]['closes'] === $roci_closes
        ) {
            $roci_runs[ $roci_index ]['days'][] = $roci_day_key;
            continue;
        }

        $roci_runs[] = array(
            'days'   => array( $roci_day_key ),
            'state'  => $roci_state,
            'opens'  => $roci_opens,
            'closes' => $roci_closes,
        );
        $roci_index = count( $roci_runs ) - 1;
    }

    return $roci_runs;
}

/**
 * The openingHoursSpecification node list, or array() when no day is open.
 *
 * One OpeningHoursSpecification per run from roci_group_business_hours().
 * dayOfWeek is ALWAYS a list of schema.org DayOfWeek URLs, even for one day:
 * one shape for every consumer and every roci_schema_data callback.
 *
 * An overnight run (closes < opens) is emitted as stored, on the opening day —
 * Google's documented form for hours past midnight.
 *
 * ⚠ array_values() IS NOT DECORATIVE, on the list and on every dayOfWeek:
 * wp_json_encode() emits a JSON list only for a zero-indexed sequential array
 * (the amenities lesson).
 *
 * The caller adds the key only when this returns non-empty, so a site with no
 * hours emits no "openingHoursSpecification": [].
 *
 * @param array $hours Map of day key => array( state, opens, closes, … ).
 * @return array List of OpeningHoursSpecification nodes.
 */
function roci_business_hours_spec( $hours ) {

    $roci_days  = roci_business_days();
    $roci_nodes = array();

    foreach ( roci_group_business_hours( $hours ) as $roci_run ) {

        $roci_day_urls = array();
        foreach ( $roci_run['days'] as $roci_day_key ) {
            $roci_day_urls[] = $roci_days[ $roci_day_key ]['schema'];
        }

        $roci_nodes[] = array(
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => array_values( $roci_day_urls ),
            'opens'     => $roci_run['opens'],
            'closes'    => $roci_run['closes'],
        );
    }

    return array_values( $roci_nodes );
}
