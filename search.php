<?php get_header(); ?>

<main id="main-content" class="site-main">
    <div class="container">

        <header class="search-header">
            <h1>
                <?php
                printf(
                    esc_html__( 'Search Results for: %s', 'rocinante' ),
                    '<span>' . get_search_query() . '</span>'
                );
                ?>
            </h1>
        </header>

        <?php get_search_form(); ?>

        <?php if ( have_posts() ) : ?>
            <div class="search-results">
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
                            <?php esc_html_e( 'Read More', 'rocinante' ); ?>
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination(); ?>

        <?php else : ?>
            <p><?php esc_html_e( 'No results found. Try a different search.', 'rocinante' ); ?></p>
        <?php endif; ?>

    </div>
</main>

<?php get_footer(); ?>