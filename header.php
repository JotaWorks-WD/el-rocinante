<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php
    // ============================================================
    // SEO — META, OG, TWITTER, SCHEMA
    // ============================================================

    $roci_post_id = get_the_ID();

    // --------------------------------------------------------
    // META TITLE
    // --------------------------------------------------------
    $roci_meta_title = roci_get_field( 'roci_meta_title', $roci_post_id );
    $roci_site_name  = get_theme_mod( 'roci_business_name', get_bloginfo( 'name' ) );
    $roci_title      = $roci_meta_title ? $roci_meta_title : get_the_title();

    // --------------------------------------------------------
    // META DESCRIPTION
    // --------------------------------------------------------
    $roci_meta_desc    = roci_get_field( 'roci_meta_description', $roci_post_id );
    $roci_default_desc = get_theme_mod( 'roci_default_meta_description', '' );
    $roci_description  = $roci_meta_desc ? $roci_meta_desc : $roci_default_desc;

    // --------------------------------------------------------
    // CANONICAL
    // --------------------------------------------------------
    $roci_canonical_field = roci_get_field( 'roci_canonical', $roci_post_id );
    $roci_canonical       = $roci_canonical_field ? $roci_canonical_field : get_permalink();

    // --------------------------------------------------------
    // ROBOTS
    // --------------------------------------------------------
    $roci_robots = roci_get_field( 'roci_robots', $roci_post_id );
    $roci_robots = $roci_robots ? $roci_robots : 'index, follow';

    // --------------------------------------------------------
    // OG IMAGE
    // Priority: OG Image field → Featured Image → Site Default
    // --------------------------------------------------------
    $roci_og_image_url = '';

    // 1. OG Image field
    $roci_og_image_field = roci_get_field( 'roci_og_image', $roci_post_id );
    if ( $roci_og_image_field ) {
        $roci_og_image_ids = array_keys( $roci_og_image_field );
        if ( ! empty( $roci_og_image_ids ) ) {
            $roci_og_image_url = wp_get_attachment_image_url( $roci_og_image_ids[0], 'full' );
        }
    }

    // 2. Featured Image fallback
    if ( ! $roci_og_image_url && has_post_thumbnail( $roci_post_id ) ) {
        $roci_og_image_url = get_the_post_thumbnail_url( $roci_post_id, 'full' );
    }

    // 3. Site default fallback
    if ( ! $roci_og_image_url ) {
        $roci_og_image_url = get_theme_mod( 'roci_default_og_image', '' );
    }

    // --------------------------------------------------------
    // SCHEMA JSON-LD
    // --------------------------------------------------------
    $roci_schema   = roci_get_field( 'roci_schema_json', $roci_post_id );
    $roci_hreflang = str_replace( '_', '-', get_locale() );
    ?>

    <!-- Meta -->
    <meta name="description" content="<?php echo esc_attr( $roci_description ); ?>">
    <meta name="robots" content="<?php echo esc_attr( $roci_robots ); ?>">
    <link rel="canonical" href="<?php echo esc_url( $roci_canonical ); ?>">

    <!-- hreflang -->
    <link rel="alternate" hreflang="<?php echo esc_attr( $roci_hreflang ); ?>" href="<?php echo esc_url( $roci_canonical ); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo esc_url( $roci_canonical ); ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="<?php echo is_single() ? 'article' : 'website'; ?>">
    <meta property="og:title" content="<?php echo esc_attr( $roci_title ); ?>">
    <meta property="og:description" content="<?php echo esc_attr( $roci_description ); ?>">
    <meta property="og:url" content="<?php echo esc_url( $roci_canonical ); ?>">
    <meta property="og:site_name" content="<?php echo esc_attr( $roci_site_name ); ?>">
    <meta property="og:locale" content="<?php echo esc_attr( get_locale() ); ?>">
    <?php if ( $roci_og_image_url ) : ?>
    <meta property="og:image" content="<?php echo esc_url( $roci_og_image_url ); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/webp">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr( $roci_title ); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr( $roci_description ); ?>">
    <meta property="twitter:url" content="<?php echo esc_url( $roci_canonical ); ?>">
    <?php if ( $roci_og_image_url ) : ?>
    <meta name="twitter:image" content="<?php echo esc_url( $roci_og_image_url ); ?>">
    <?php endif; ?>

    <?php if ( $roci_schema ) : ?>
    <!-- Schema JSON-LD — Page Level (Metabox) -->
    <script type="application/ld+json">
    <?php echo $roci_schema; ?>
    </script>
    <?php endif; ?>

    <?php if ( is_front_page() ) :
        $roci_biz_name    = get_theme_mod( 'roci_business_name', '' );
        $roci_biz_phone   = get_theme_mod( 'roci_phone', '' );
        $roci_biz_email   = get_theme_mod( 'roci_email', '' );
        $roci_biz_address = get_theme_mod( 'roci_address', '' );

        $roci_same_as = array_values( array_filter( [
            get_theme_mod( 'roci_facebook', '' ),
            get_theme_mod( 'roci_instagram', '' ),
            get_theme_mod( 'roci_tiktok', '' ),
            get_theme_mod( 'roci_youtube', '' ),
            get_theme_mod( 'roci_linkedin', '' ),
            get_theme_mod( 'roci_twitter', '' ),
            get_theme_mod( 'roci_tripadvisor', '' ),
        ] ) );

        if ( $roci_biz_name ) :
            $roci_local_schema = [
                '@context'  => 'https://schema.org',
                '@type'     => 'LocalBusiness',
                'name'      => $roci_biz_name,
                'url'       => home_url( '/' ),
                'telephone' => $roci_biz_phone,
                'email'     => $roci_biz_email,
                'address'   => [
                    '@type'         => 'PostalAddress',
                    'streetAddress' => $roci_biz_address,
                ],
                'sameAs'    => $roci_same_as,
            ];
        ?>
    <!-- Schema JSON-LD — Site Level (Customizer) -->
    <script type="application/ld+json">
    <?php echo wp_json_encode( $roci_local_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); ?>
    </script>
        <?php endif; ?>
    <?php endif; ?>

    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header id="site-header" class="site-header">
    <nav id="site-navigation" class="navbar navbar-expand-lg" aria-label="<?php esc_attr_e( 'Primary Navigation', 'rocinante' ); ?>">
        <div class="container">

            <!-- Logo / Site Name -->
            <a class="navbar-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                <?php if ( has_custom_logo() ) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <span class="site-title"><?php bloginfo( 'name' ); ?></span>
                <?php endif; ?>
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#primaryMenu"
                aria-controls="primaryMenu"
                aria-expanded="false"
                aria-label="<?php esc_attr_e( 'Toggle navigation', 'rocinante' ); ?>">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Primary Nav -->
            <div class="collapse navbar-collapse" id="primaryMenu">
                <?php
                if ( has_nav_menu( 'primary' ) ) :
                    wp_nav_menu( array(
                        'theme_location' => 'primary',
                        'container'      => false,
                        'menu_class'     => 'navbar-nav ms-auto',
                        'fallback_cb'    => false,
                        'walker'         => new WP_Bootstrap_Navwalker(),
                    ) );
                endif;
                ?>
            </div>

        </div>
    </nav>
</header>