<?php
/**
 * Index — Final Template-Hierarchy Fallback
 *
 * WordPress's required catch-all: the template that serves any route no more
 * specific template claims first. Children inherit this unless they define
 * their own index.php.
 *
 * This is the LISTING template, and since v1.2.0 it no longer mirrors
 * page.php. The parent ships page.php and single.php, so every singular route
 * is claimed before this file; what reaches it is the posts index (is_home(),
 * on a child with no home.php) and any other unclaimed non-singular route. A
 * defensive singular branch gives one <h1>, as page.php does, in case a child
 * ever shadows page.php or single.php with something that falls back here.
 *
 * Listing branch (v1.3.0), built from overridable parts:
 *   template-parts/listing-header.php — the listing <h1> (the Posts page's own
 *     title, or the site name), under roci_entry_title_mode( 'index' ).
 *   template-parts/content-index.php — one item per post: h2.entry-title link,
 *     date, EXCERPT and Read More. The posts index printed full the_content()
 *     before v1.3.0; no live site reaches this branch.
 *   template-parts/content-none.php — the empty state.
 * Seams: roci_listing_classes, roci_pagination_args, roci_after_loop.
 *
 * `.entry-content` is the prose contract: the parent's base typography restores list markers and link underlines inside it.
 *
 * File:    index.php
 * Version: 1.3.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

get_header();
?>

<main id="main-content" class="site-main">

    <?php if ( ! is_singular() ) : ?>
        <?php $roci_classes = roci_get_listing_classes( 'index' ); ?>
        <div class="<?php echo roci_class_attr( $roci_classes['wrapper'] ); ?>">

            <?php get_template_part( 'template-parts/listing-header', null, array( 'context' => 'index' ) ); ?>

            <?php if ( have_posts() ) : ?>
                <div class="<?php echo roci_class_attr( $roci_classes['results'] ); ?>">
                    <?php
                    while ( have_posts() ) :
                        the_post();
                        get_template_part( 'template-parts/content', 'index' );
                    endwhile;
                    ?>
                </div>

                <?php the_posts_pagination( roci_get_pagination_args( 'index' ) ); ?>

            <?php else : ?>
                <?php get_template_part( 'template-parts/content', 'none', array( 'context' => 'index' ) ); ?>
            <?php endif; ?>

            <?php do_action( 'roci_after_loop', 'index' ); ?>

        </div>
    <?php else : ?>
        <?php
        while ( have_posts() ) :
            the_post();
            $roci_title_mode = roci_entry_title_mode( 'index', get_the_ID() );
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <?php if ( 'none' !== $roci_title_mode ) : ?>
                    <header class="entry-header">
                        <h1 class="entry-title<?php echo ( 'hidden' === $roci_title_mode ) ? ' screen-reader-text' : ''; ?>"><?php the_title(); ?></h1>
                    </header>
                <?php endif; ?>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php
        endwhile;
        ?>
    <?php endif; ?>

</main>

<?php get_footer(); ?>
