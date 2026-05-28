<?php
/**
 * Loader Screen — Loading Overlay Partial
 *
 * Displays the full-screen loading overlay during page transitions.
 *
 * File:    template-parts/loader.php
 * Version: 1.0.0
 * Updated: 2026-05-28
 *
 * @package ElRocinante
 */
?>
<div id="loader">
    <img src="<?php echo esc_url( get_theme_mod( 'loader_logo' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
    <p>LOADING...</p>
</div>