<?php
/**
 * 404 Log — the admin list table
 *
 * Loaded only on Theme Settings → 404 log (roci_404_log_load()). One row per
 * URL over the chosen dates: SUM(hits), MIN(first_seen), MAX(last_seen), the
 * kind, and the latest non-empty referrer (a second query for the rows on the
 * page). Filters: date from / to (default the last 7 days, site time) and
 * kind (all / 404 / suppressed). Sortable by hits and last seen; paginated.
 * Bulk "Delete" removes every row of the chosen URLs, all days.
 *
 * File:    inc/404-log/class-roci-404-log-table.php
 * Version: 1.0.0
 * Updated: 2026-11-18
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Roci_404_Log_Table extends WP_List_Table {

    /** Rows per page. */
    const PER_PAGE = 50;

    public function __construct() {
        parent::__construct( array(
            'singular' => 'roci_404_url',
            'plural'   => 'roci_404_urls',
            'ajax'     => false,
        ) );
    }

    // ---- The request's filters ----------------------------------------------

    /**
     * The filters from the request, validated, with defaults.
     *
     * @return array from, to (Y-m-d), kind ('' | '404' | 'suppressed'),
     *               orderby ('hits' | 'last_seen'), order ('ASC' | 'DESC').
     */
    public static function filters() {
        $today = current_time( 'Y-m-d' );
        $from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';     // phpcs:ignore WordPress.Security.NonceVerification
        $kind  = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';                  // phpcs:ignore WordPress.Security.NonceVerification
        $by    = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';            // phpcs:ignore WordPress.Security.NonceVerification
        $order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        $valid = function ( $d ) {
            return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && checkdate( (int) substr( $d, 5, 2 ), (int) substr( $d, 8, 2 ), (int) substr( $d, 0, 4 ) );
        };

        $to   = $valid( $to ) ? $to : $today;
        $from = $valid( $from ) ? $from : gmdate( 'Y-m-d', strtotime( $today . ' -6 days' ) );

        if ( $from > $to ) {
            list( $from, $to ) = array( $to, $from );
        }

        return array(
            'from'    => $from,
            'to'      => $to,
            'kind'    => in_array( $kind, array( '404', 'suppressed' ), true ) ? $kind : '',
            'orderby' => in_array( $by, array( 'hits', 'last_seen' ), true ) ? $by : 'hits',
            'order'   => 'ASC' === $order ? 'ASC' : 'DESC',
        );
    }

    /**
     * The WHERE clause for the filters, prepared.
     *
     * @param  array $f filters().
     * @return string
     */
    public static function where( $f ) {
        global $wpdb;

        $sql = $wpdb->prepare( 'WHERE day BETWEEN %s AND %s', $f['from'], $f['to'] );

        if ( '' !== $f['kind'] ) {
            $sql .= $wpdb->prepare( ' AND kind = %s', $f['kind'] );
        }

        return $sql;
    }

    /**
     * The page's aggregate query (also shown by the verification harness).
     *
     * @param  array $f      filters().
     * @param  int   $offset
     * @return string
     */
    public static function items_sql( $f, $offset = 0 ) {
        global $wpdb;

        $table   = roci_404_log_table();
        $orderby = 'last_seen' === $f['orderby'] ? 'last_seen' : 'hits';

        return "SELECT url_hash, MAX(url) AS url, MAX(kind) AS kind, SUM(hits) AS hits, MIN(first_seen) AS first_seen, MAX(last_seen) AS last_seen
            FROM {$table} " . self::where( $f ) . "
            GROUP BY url_hash
            ORDER BY {$orderby} {$f['order']}, url_hash ASC
            LIMIT " . (int) self::PER_PAGE . ' OFFSET ' . (int) $offset;
    }

    // ---- WP_List_Table ----------------------------------------------------------

    public function get_columns() {
        return array(
            'cb'         => '<input type="checkbox">',
            'url'        => __( 'URL', 'rocinante' ),
            'hits'       => __( 'Hits', 'rocinante' ),
            'kind'       => __( 'Kind', 'rocinante' ),
            'first_seen' => __( 'First seen', 'rocinante' ),
            'last_seen'  => __( 'Last seen', 'rocinante' ),
            'referrer'   => __( 'Last referrer', 'rocinante' ),
        );
    }

    protected function get_sortable_columns() {
        return array(
            'hits'      => array( 'hits', true ),
            'last_seen' => array( 'last_seen', true ),
        );
    }

    protected function get_bulk_actions() {
        return array( 'delete' => __( 'Delete', 'rocinante' ) );
    }

    public function no_items() {
        esc_html_e( 'No 404s for these dates.', 'rocinante' );
    }

    public function prepare_items() {
        global $wpdb;

        $f      = self::filters();
        $table  = roci_404_log_table();
        $paged  = max( 1, $this->get_pagenum() );
        $offset = ( $paged - 1 ) * self::PER_PAGE;

        $total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT url_hash) FROM {$table} " . self::where( $f ) ); // phpcs:ignore WordPress.DB
        $rows  = $wpdb->get_results( self::items_sql( $f, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB
        $rows  = is_array( $rows ) ? $rows : array();

        // The latest non-empty referrer per URL, for this page's rows only.
        if ( $rows ) {
            $hashes = wp_list_pluck( $rows, 'url_hash' );
            $in     = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
            $refs   = $wpdb->get_results( // phpcs:ignore WordPress.DB
                $wpdb->prepare(
                    "SELECT url_hash, referrer FROM {$table} " . self::where( $f ) . " AND referrer <> '' AND url_hash IN ({$in}) ORDER BY last_seen ASC", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
                    $hashes
                ),
                ARRAY_A
            );
            $latest = array();
            foreach ( (array) $refs as $ref ) {
                $latest[ $ref['url_hash'] ] = $ref['referrer']; // ascending, so the last write wins
            }
            foreach ( $rows as $i => $row ) {
                $rows[ $i ]['referrer'] = isset( $latest[ $row['url_hash'] ] ) ? $latest[ $row['url_hash'] ] : '';
            }
        }

        $this->items           = $rows;
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
        $this->set_pagination_args( array(
            'total_items' => $total,
            'per_page'    => self::PER_PAGE,
        ) );
    }

    /**
     * Deletes every row of the given URLs.
     *
     * @param  array $hashes url_hash values from the form.
     * @return int           URLs deleted.
     */
    public function delete_hashes( $hashes ) {
        global $wpdb;

        $hashes = array_values( array_filter( array_map( 'strval', (array) $hashes ), function ( $h ) {
            return (bool) preg_match( '/^[a-f0-9]{32}$/', $h );
        } ) );

        if ( ! $hashes ) {
            return 0;
        }

        $table = roci_404_log_table();
        $in    = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE url_hash IN ({$in})", $hashes ) ); // phpcs:ignore WordPress.DB

        return count( $hashes );
    }

    // ---- Columns ------------------------------------------------------------------

    protected function column_cb( $item ) {
        return '<input type="checkbox" name="url_hash[]" value="' . esc_attr( $item['url_hash'] ) . '">';
    }

    protected function column_url( $item ) {
        // The stored value is REQUEST_URI — path from the domain root + query —
        // so it goes on the site's scheme + host, not on home_url() (which
        // would double a subfolder install's path). Opens in a new tab.
        $site = wp_parse_url( home_url() );
        $href = ( isset( $site['scheme'] ) ? $site['scheme'] : 'https' ) . '://' . ( isset( $site['host'] ) ? $site['host'] : '' ) . ( isset( $site['port'] ) ? ':' . $site['port'] : '' ) . $item['url'];

        return '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer"><code>' . esc_html( $item['url'] ) . '</code></a>';
    }

    protected function column_kind( $item ) {
        return 'suppressed' === $item['kind'] ? esc_html__( 'Suppressed archive', 'rocinante' ) : esc_html__( '404', 'rocinante' );
    }

    protected function column_referrer( $item ) {
        return '' === $item['referrer'] ? '—' : '<span style="word-break:break-all">' . esc_html( $item['referrer'] ) . '</span>';
    }

    protected function column_default( $item, $column ) {
        return isset( $item[ $column ] ) ? esc_html( (string) $item[ $column ] ) : '';
    }

    // ---- Filters bar ------------------------------------------------------------------

    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        $f = self::filters();
        ?>
        <div class="alignleft actions">
            <label for="roci-404-from"><?php esc_html_e( 'From', 'rocinante' ); ?></label>
            <input type="date" id="roci-404-from" name="date_from" value="<?php echo esc_attr( $f['from'] ); ?>">
            <label for="roci-404-to"><?php esc_html_e( 'To', 'rocinante' ); ?></label>
            <input type="date" id="roci-404-to" name="date_to" value="<?php echo esc_attr( $f['to'] ); ?>">
            <label for="roci-404-kind" class="screen-reader-text"><?php esc_html_e( 'Kind', 'rocinante' ); ?></label>
            <select id="roci-404-kind" name="kind">
                <option value="" <?php selected( $f['kind'], '' ); ?>><?php esc_html_e( 'All kinds', 'rocinante' ); ?></option>
                <option value="404" <?php selected( $f['kind'], '404' ); ?>><?php esc_html_e( '404', 'rocinante' ); ?></option>
                <option value="suppressed" <?php selected( $f['kind'], 'suppressed' ); ?>><?php esc_html_e( 'Suppressed archive', 'rocinante' ); ?></option>
            </select>
            <?php submit_button( __( 'Filter', 'rocinante' ), '', 'filter_action', false ); ?>
        </div>
        <?php
    }
}
