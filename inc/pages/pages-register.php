<?php
/**
 * Pages — Navigation Grouping
 *
 * Registers the Pages submenu under Theme Settings as a navigation
 * grouping only. Child themes register their own MB Pro Settings Pages
 * with parent => 'roci-pages' to nest under this entry. Each child
 * page owns its own option_name storage independently.
 *
 * 2.2.0 (El Rocinante 8.4.0): a tab strip across the top of the landing and
 * every page screen ("All pages" + one tab per screen, plain links), shown
 * with two or more screens.
 *
 * 2.2.1 (El Rocinante 8.4.3): the strip uses its own roci-pages-tabs classes
 * and a small admin <style>, no longer core's nav-tab classes, which Meta Box
 * Settings Page's tab script also binds to (it snapped back to "All pages").
 *
 * File:    inc/pages/pages-register.php
 * Version: 2.2.1
 * Updated: 2026-11-19
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// REGISTER PAGES SUBMENU
// ============================================================

function roci_register_pages_submenu() {
    add_submenu_page(
        'roci-theme-settings',
        __( 'Pages', 'rocinante' ),
        __( 'Pages', 'rocinante' ),
        'manage_options',
        'roci-pages',
        'roci_pages_landing_screen'
    );
}
add_action( 'admin_menu', 'roci_register_pages_submenu', 11 );


// ============================================================
// LANDING SCREEN
// ============================================================

function roci_pages_landing_screen() {
    global $submenu;

    $child_pages = array();

    if ( ! empty( $submenu['roci-pages'] ) ) {
        foreach ( $submenu['roci-pages'] as $entry ) {
            if ( isset( $entry[2] ) && 'roci-pages' !== $entry[2] ) {
                $child_pages[] = $entry;
            }
        }
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Pages', 'rocinante' ); ?></h1>
        <?php if ( empty( $child_pages ) ) : ?>
            <p><?php esc_html_e( 'Select a page from the submenu to edit its content. Pages are registered by the active child theme.', 'rocinante' ); ?></p>
        <?php else : ?>
            <p><?php esc_html_e( 'Select a page to edit its content:', 'rocinante' ); ?></p>
            <ul>
                <?php foreach ( $child_pages as $entry ) : ?>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $entry[2] ) ); ?>"><?php echo esc_html( $entry[0] ); ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}


// ============================================================
// TAB STRIP (v2.2.0, El Rocinante 8.4.0)
// ============================================================

/**
 * roci_pages_tab_entries()
 *
 * The page screens under roci-pages the current user can open, in $submenu
 * order — the landing screen's enumeration (roci-pages itself skipped) plus a
 * capability check on each entry's own capability (index 1).
 *
 * @param  array $entries $submenu['roci-pages'].
 * @return array          Each array( 'slug' => string, 'label' => string ).
 */
function roci_pages_tab_entries( $entries ) {
    $tabs = array();

    foreach ( (array) $entries as $entry ) {
        if ( ! isset( $entry[2] ) || 'roci-pages' === $entry[2] ) {
            continue;
        }
        if ( empty( $entry[1] ) || ! current_user_can( $entry[1] ) ) {
            continue;
        }

        $tabs[] = array(
            'slug'  => (string) $entry[2],
            'label' => trim( wp_strip_all_tags( (string) $entry[0] ) ),
        );
    }

    return $tabs;
}

/**
 * roci_pages_tab_strip_html()
 *
 * The strip: "All pages" (the roci-pages landing), then one tab per page
 * screen. '' unless $current is the landing or one of the screens, and '' with
 * fewer than two screens (a one-page site has nothing to switch between).
 *
 * Its own classes (roci-pages-tabs, __link, is-active), never core's
 * nav-tab-wrapper / nav-tab / nav-tab-active: Meta Box Settings Page's tab
 * script binds to those, and since this strip prints first it took "All
 * pages" for its first tab and re-activated it after every load (8.4.3).
 * Styled by roci_pages_tab_strip_css().
 *
 * Each tab is a plain link (a page load). Each Meta Box settings page keeps
 * its own form and its own option row; true in-page tabs stay ruled out,
 * because they would share one form across several option_names.
 *
 * @param  string $current The ?page= slug being viewed.
 * @param  array  $entries $submenu['roci-pages'].
 * @return string
 */
function roci_pages_tab_strip_html( $current, $entries ) {
    $tabs = roci_pages_tab_entries( $entries );

    if ( count( $tabs ) < 2 ) {
        return '';
    }
    if ( 'roci-pages' !== $current && ! in_array( $current, wp_list_pluck( $tabs, 'slug' ), true ) ) {
        return '';
    }

    array_unshift( $tabs, array(
        'slug'  => 'roci-pages',
        'label' => __( 'All pages', 'rocinante' ),
    ) );

    $html = '<div class="wrap"><nav class="roci-pages-tabs" aria-label="' . esc_attr__( 'Page settings', 'rocinante' ) . '">';

    foreach ( $tabs as $tab ) {
        $active = $tab['slug'] === $current;
        $html  .= '<a href="' . esc_url( admin_url( 'admin.php?page=' . $tab['slug'] ) ) . '" class="roci-pages-tabs__link' . ( $active ? ' is-active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $tab['label'] ) . '</a>';
    }

    return $html . '</nav></div>';
}

/**
 * roci_pages_tab_strip_css()
 *
 * The strip's admin-only <style>, printed with it and only when it prints.
 * Looks like core's tabs: a row of links over a bottom border, the active one
 * joined to the page. Focus ring in the admin colour scheme's theme colour
 * (--wp-admin-theme-color, core's default blue as the fallback); every link is
 * at least 24x24px.
 *
 * @return string
 */
function roci_pages_tab_strip_css() {
    return '<style id="roci-pages-tabs-css">'
        . '.roci-pages-tabs{display:flex;flex-wrap:wrap;align-items:flex-end;gap:0 .5em;margin:0 0 1em;padding:9px 0 0;border-bottom:1px solid #c3c4c7}'
        . '.roci-pages-tabs__link{box-sizing:border-box;display:inline-flex;align-items:center;min-width:24px;min-height:24px;margin:0 0 -1px;padding:5px 10px;border:1px solid #c3c4c7;background:#dcdcde;color:#50575e;font-size:14px;font-weight:600;line-height:1.71428571;text-decoration:none;white-space:nowrap}'
        . '.roci-pages-tabs__link:hover,.roci-pages-tabs__link:focus{background:#fff;color:#3c434a}'
        . '.roci-pages-tabs__link.is-active{background:#f0f0f1;border-bottom-color:#f0f0f1;color:#000}'
        . '.roci-pages-tabs__link:focus{box-shadow:none;outline:0}'
        . '.roci-pages-tabs__link:focus-visible{position:relative;z-index:1;outline:2px solid var(--wp-admin-theme-color,#2271b1);outline-offset:1px}'
        . '</style>';
}

/**
 * roci_pages_tab_strip()
 *
 * Prints the strip on all_admin_notices: inside #wpbody-content, before the
 * screen's own .wrap (its <h1> and the settings form), on every admin screen —
 * Meta Box settings pages and the landing alike. Core's script that moves
 * notices under the first heading only moves div.notice / .updated / .error,
 * so the strip stays where it is printed. (in_admin_header prints above
 * #wpbody, outside the content column.)
 */
function roci_pages_tab_strip() {
    global $submenu;

    if ( empty( $_GET['page'] ) || empty( $submenu['roci-pages'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only screen slug.
        return;
    }

    $html = roci_pages_tab_strip_html( sanitize_text_field( wp_unslash( $_GET['page'] ) ), $submenu['roci-pages'] ); // phpcs:ignore WordPress.Security.NonceVerification -- read-only screen slug.

    if ( '' === $html ) {
        return;
    }

    echo roci_pages_tab_strip_css() . $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS; the markup is escaped in the builder.
}
add_action( 'all_admin_notices', 'roci_pages_tab_strip' );
