<footer id="site-footer" class="site-footer">
    <div class="container">

        <div class="row">
            <div class="col-md-6">
                <p class="footer-copy">
                    &copy; <?php echo date( 'Y' ); ?> 
                    <?php bloginfo( 'name' ); ?>. 
                    <?php esc_html_e( 'All rights reserved.', 'rocinante' ); ?>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <?php
                if ( has_nav_menu( 'footer' ) ) :
                    wp_nav_menu( array(
                        'theme_location' => 'footer',
                        'container'      => false,
                        'menu_class'     => 'footer-nav d-flex gap-3 justify-content-md-end',
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