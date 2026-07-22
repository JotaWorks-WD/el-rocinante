<?php
/**
 * Footer — Site Footer Template
 *
 * Closes the document. Renders the copyright line and the optional 'footer' nav
 * menu, then fires wp_footer(). Children inherit this unless they define their
 * own footer.php. Contains no action hooks.
 *
 * File:    footer.php
 * Version: 1.0.0
 * Updated: 2026-07-22
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
                <?php
                if ( has_nav_menu( 'footer' ) ) :
                    wp_nav_menu( array(
                        'theme_location' => 'footer',
                        'container'      => false,
                        'menu_class'     => 'footer-nav u-flex u-gap-medium u-justify-end-md',
                        'fallback_cb'    => false,
                        'depth'          => 1,
                    ) );
                endif;
                ?>
            </div>
        </div>

    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
