<?php
/**
 * Page Template — Fallback for Unassigned Pages
 *
 * Serves any WordPress Page that has no named page template assigned in
 * Page Attributes. Pages WITH a template assigned never reach this file —
 * WordPress resolves the child theme's pages/page-{slug}.php first.
 *
 * WHY THIS FILE EXISTS. Before it, an unassigned Page fell all the way
 * through the hierarchy to index.php. That is the documented deploy footgun:
 * a Home page set as the static front page in Settings → Reading but left on
 * Template = "Default" renders through index.php with the body class
 * page-template-default, and the site looks broken while every stylesheet is
 * loading correctly. Terminating the fallthrough here does not fix the
 * missing assignment — it gives the route a real, structured template
 * instead of the catch-all.
 *
 * Structure mirrors index.php deliberately: same <main> wrapper, same loop.
 * A child that wants a designed default overrides this file; a child that
 * ships one template per real page never needs to.
 *
 * File:    page.php
 * Version: 1.0.0
 * Updated: 2026-08-07
 *
 * @package ElRocinante
 */

get_header(); ?>

<main id="main-content" class="site-main">

    <?php
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
            the_content();
        endwhile;
    endif;
    ?>

</main>

<?php get_footer(); ?>
