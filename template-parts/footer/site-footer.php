<?php
/**
 * Site Footer — the visible footer markup
 *
 * Moved out of footer.php at v7.1.0 so a child can replace the footer's
 * markup without owning wp_footer() or the document close. footer.php loads
 * this part, then fires wp_footer() and closes </body></html>.
 *
 * Override THIS part, not footer.php. A child footer.php that drops
 * wp_footer() breaks every plugin and the admin bar silently.
 *
 * Renders the copyright line and the optional 'footer' nav menu. The menu
 * sits in a <nav aria-label="Footer"> landmark, printed only when a menu is
 * assigned — so there is never an empty landmark. The label names the nav,
 * not its role (a screen reader announces "Footer, navigation").
 *
 * The menu's <ul> carries role="list": the parent's global list-style: none
 * strips its markers, and WebKit (Safari + VoiceOver) then drops its list
 * semantics unless the role is explicit.
 *
 * The copyright line is ONE translatable string with placeholders (v1.0.1),
 * so a translation can reorder the year and the name. The year comes from
 * wp_date() (the site's timezone, not the server's), and every value is
 * escaped. The rendered English text is unchanged.
 *
 * File:    template-parts/footer/site-footer.php
 * Version: 1.0.1
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */
?>
<footer id="site-footer" class="site-footer">
    <div class="u-container">

        <div class="u-row">
            <div class="u-col-half">
                <p class="footer-copy">
                    <?php
                    echo sprintf(
                        /* translators: 1: the current year, 2: the site name. */
                        esc_html__( '&copy; %1$s %2$s. All rights reserved.', 'rocinante' ),
                        esc_html( wp_date( 'Y' ) ),
                        esc_html( get_bloginfo( 'name', 'display' ) )
                    );
                    ?>
                </p>
            </div>
            <div class="u-col-half u-text-right-md">
                <?php if ( has_nav_menu( 'footer' ) ) : ?>
                    <nav class="footer-navigation" aria-label="<?php esc_attr_e( 'Footer', 'rocinante' ); ?>">
                        <?php
                        wp_nav_menu( array(
                            'theme_location' => 'footer',
                            'container'      => false,
                            'menu_class'     => 'footer-nav u-flex u-gap-medium u-justify-end-md',
                            'fallback_cb'    => false,
                            'depth'          => 1,
                            'items_wrap'     => '<ul id="%1$s" class="%2$s" role="list">%3$s</ul>',
                        ) );
                        ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>

    </div>
</footer>
