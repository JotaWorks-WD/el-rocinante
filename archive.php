<?php get_header(); ?>

<main id="main-content" class="site-main">
    <div class="container">

        <header class="archive-header">
            <h1>
                <?php
                if ( is_category() ) {
                    single_cat_title();
                } elseif ( is_tag() ) {
                    single_tag_title();
                } elseif ( is_author() ) {
                    echo get_the_author();
                } elseif ( is_date() ) {
                    echo get_the_date( 'F Y' );
                } else {
                    post_type_archive_title();
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
                            <?php esc_html_e( 'Read More', 'rocinante' ); ?>
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