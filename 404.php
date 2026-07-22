<?php
/**
 * 404 — Not Found Template
 *
 * Fallback template for requests that resolve to no post. Children inherit this
 * unless they define their own 404.php.
 *
 * File:    404.php
 * Version: 1.0.0
 * Updated: 2026-07-22
 *
 * @package ElRocinante
 */
get_header(); ?>

<main id="main-content" class="site-main">
    <div class="u-container">
        <div class="error-404">
            <h1><?php esc_html_e( '404 — Page Not Found', 'rocinante' ); ?></h1>
            <p><?php esc_html_e( 'The page you are looking for does not exist or has been moved.', 'rocinante' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <?php esc_html_e( 'Back to Home', 'rocinante' ); ?>
            </a>
        </div>
    </div>
</main>

<?php get_footer(); ?>
