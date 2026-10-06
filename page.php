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
 * This is the SINGULAR content template: one Page, one <h1>. It no longer
 * mirrors index.php — since v1.2.0 index.php is the LISTING template (one
 * listing <h1>, per-post <h2> links). A child that wants a designed default
 * overrides this file; a child that ships one template per real page never
 * needs to.
 *
 * `.entry-content` is the prose contract: the parent's base typography restores list markers and link underlines inside it.
 *
 * The entry title is an <h1> (index: one listing <h1>, per-post <h2>); a child controls it with the roci_entry_title_mode filter ('visible' | 'hidden' | 'none').
 *
 * File:    page.php
 * Version: 1.2.0
 * Updated: 2026-10-06
 *
 * @package ElRocinante
 */

get_header(); ?>

<main id="main-content" class="site-main">

    <?php
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
            $roci_title_mode = roci_entry_title_mode( 'page', get_the_ID() );
            ?>
            <?php if ( 'none' !== $roci_title_mode ) : ?>
                <header class="entry-header">
                    <h1 class="entry-title<?php echo ( 'hidden' === $roci_title_mode ) ? ' screen-reader-text' : ''; ?>"><?php the_title(); ?></h1>
                </header>
            <?php endif; ?>
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
            <?php
        endwhile;
    endif;
    ?>

</main>

<?php get_footer(); ?>
