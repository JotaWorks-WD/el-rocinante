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
 * on a child with no home.php) and any other unclaimed non-singular route. It
 * prints ONE listing <h1> — the Posts page's own title, or the site name when
 * the front page shows latest posts — and each post as an <article> with an
 * <h2> link. A defensive singular branch gives one <h1>, as page.php does, in
 * case a child ever shadows page.php or single.php with something that falls
 * back here.
 *
 * `.entry-content` is the prose contract: the parent's base typography restores list markers and link underlines inside it.
 *
 * The entry title is an <h1> (index: one listing <h1>, per-post <h2>); a child controls it with the roci_entry_title_mode filter ('visible' | 'hidden' | 'none').
 *
 * File:    index.php
 * Version: 1.2.0
 * Updated: 2026-10-06
 *
 * @package ElRocinante
 */

get_header();

$roci_is_listing = ! is_singular();

if ( $roci_is_listing ) {
    $roci_posts_page_id = is_home() ? (int) get_option( 'page_for_posts' ) : 0;
    $roci_listing_title = $roci_posts_page_id ? get_the_title( $roci_posts_page_id ) : get_bloginfo( 'name' );
    $roci_title_mode    = roci_entry_title_mode( 'index', $roci_posts_page_id );
}
?>

<main id="main-content" class="site-main">

    <?php if ( $roci_is_listing && 'none' !== $roci_title_mode ) : ?>
        <header class="archive-header">
            <h1 class="entry-title<?php echo ( 'hidden' === $roci_title_mode ) ? ' screen-reader-text' : ''; ?>"><?php echo esc_html( $roci_listing_title ); ?></h1>
        </header>
    <?php endif; ?>

    <?php
    if ( have_posts() ) :
        while ( have_posts() ) : the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <?php if ( $roci_is_listing ) : ?>
                    <h2 class="entry-title"><a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a></h2>
                <?php else : ?>
                    <?php $roci_title_mode = roci_entry_title_mode( 'index', get_the_ID() ); ?>
                    <?php if ( 'none' !== $roci_title_mode ) : ?>
                        <header class="entry-header">
                            <h1 class="entry-title<?php echo ( 'hidden' === $roci_title_mode ) ? ' screen-reader-text' : ''; ?>"><?php the_title(); ?></h1>
                        </header>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php
        endwhile;
    endif;
    ?>

</main>

<?php get_footer(); ?>