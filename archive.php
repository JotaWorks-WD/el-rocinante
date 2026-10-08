<?php
/**
 * Archive — Archive Page Template
 *
 * Displays posts for category, tag, author, date, and post-type archives.
 *
 * Each "Read More" carries a screen-reader-only " about {title}", so its
 * accessible name is unique and makes sense out of context (WCAG 2.4.4).
 *
 * File:    archive.php
 * Version: 1.1.1
 * Updated: 2026-10-08
 *
 * @package ElRocinante
 */
get_header(); ?>

<main id="main-content" class="site-main">
    <div class="u-container">

        <header class="archive-header">
            <h1>
                <?php
                if ( is_category() ) {
                    single_cat_title();
                } elseif ( is_tag() ) {
                    single_tag_title();
                } elseif ( is_author() ) {
                    echo esc_html( get_the_author() );
                } elseif ( is_date() ) {
                    // By granularity (v1.1.1): a single "F Y" label misnamed
                    // year and day archives.
                    if ( is_year() ) {
                        echo esc_html( get_the_date( 'Y' ) );
                    } elseif ( is_month() ) {
                        echo esc_html( get_the_date( 'F Y' ) );
                    } else {
                        echo esc_html( get_the_date() ); // is_day(): the site date format
                    }
                } elseif ( is_tax() ) {
                    // Custom-taxonomy term archives (v1.1.1) — they used to fall
                    // to post_type_archive_title(), which prints nothing there.
                    single_term_title();
                } elseif ( is_post_type_archive() ) {
                    post_type_archive_title();
                } else {
                    // Last resort, so the <h1> is never empty.
                    the_archive_title();
                }
                ?>
            </h1>
            <?php if ( get_the_archive_description() ) : ?>
                <div class="archive-description">
                    <?php the_archive_description(); ?>
                </div>
            <?php endif; ?>
        </header>

        <?php if ( have_posts() ) : ?>
            <div class="archive-posts">
                <?php while ( have_posts() ) : the_post(); ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <div class="post-meta">
                            <time datetime="<?php echo get_the_date( 'c' ); ?>"><?php echo get_the_date(); ?></time>
                        </div>
                        <div class="post-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                        <a href="<?php the_permalink(); ?>" class="read-more">
                            <?php esc_html_e( 'Read More', 'rocinante' ); ?><span class="screen-reader-text"> <?php
                                /* translators: %s: post title */
                                printf( esc_html__( 'about %s', 'rocinante' ), esc_html( get_the_title() ) );
                            ?></span>
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination(); ?>

        <?php else : ?>
            <p><?php esc_html_e( 'No posts found.', 'rocinante' ); ?></p>
        <?php endif; ?>

    </div>
</main>

<?php get_footer(); ?>