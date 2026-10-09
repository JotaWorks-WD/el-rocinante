<?php
/**
 * Content — one listing item (the fallback loop item)
 *
 * The parent's listing templates render each post with
 * get_template_part( 'template-parts/content', $context ), where $context is
 * 'index', 'archive' or 'search'. WordPress loads content-{context}.php when
 * it exists — child first, then parent — and this file otherwise. The
 * parent's three context parts load this file, so:
 *   - override content-{context}.php to change ONE route's item;
 *   - override content.php to change every route a context part has not
 *     been overridden for.
 *
 * The item: <article post_class()>, h2.entry-title > a, the date, the
 * excerpt, and a "Read More" whose screen-reader-only " about {title}" gives
 * it a unique accessible name out of context (WCAG 2.4.4).
 *
 * File:    template-parts/content.php
 * Version: 1.0.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
    <div class="post-meta">
        <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
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
