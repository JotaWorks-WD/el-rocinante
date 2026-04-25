<?php get_header(); ?>

<main id="main-content" class="site-main">
    <div class="container">
        <div class="error-404">
            <h1><?php esc_html_e( '404 — Page Not Found', 'rocinante' ); ?></h1>
            <p><?php esc_html_e( 'The page you are looking for does not exist or has been moved.', 'rocinante' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-primary">
                <?php esc_html_e( 'Back to Home', 'rocinante' ); ?>
            </a>
        </div>
    </div>
</main>

<?php get_footer(); ?>