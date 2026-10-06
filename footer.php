<?php
/**
 * Footer — Site Footer Template
 *
 * Closes the document. Renders the copyright line and the optional 'footer' nav
 * menu, then fires wp_footer(). Children inherit this unless they define their
 * own footer.php. Contains no action hooks.
 *
 * The menu sits in a <nav aria-label="Footer"> landmark, printed only when a
 * menu is assigned — so there is never an empty landmark. The label names the
 * nav, not its role (a screen reader announces "Footer, navigation").
 *
 * The menu's <ul> carries role="list" (v1.1.1): the parent's global
 * list-style: none strips its markers, and WebKit (Safari + VoiceOver) then
 * drops its list semantics unless the role is explicit.
 *
 * File:    footer.php
 * Version: 1.1.1
 * Updated: 2026-10-06
 *
 * @package ElRocinante
 */
?>
<footer id="site-footer" class="site-footer">
    <div class="u-container">

        <div class="u-row">
            <div class="u-col-half">
                <p class="footer-copy">
                    &copy; <?php echo date( 'Y' ); ?>
                    <?php bloginfo( 'name' ); ?>.
                    <?php esc_html_e( 'All rights reserved.', 'rocinante' ); ?>
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

<?php wp_footer(); ?>
</body>
</html>
