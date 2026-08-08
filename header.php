<!DOCTYPE html>
<?php
/**
 * Header Template — Site Head & Navigation
 *
 * Outputs the full <head> block including SEO meta, OG tags,
 * Twitter Card, schema JSON-LD, and the primary navigation.
 * Nav output is controlled per child theme via do_action('roci_nav').
 * Site-level business JSON-LD is filterable via apply_filters('roci_schema_data');
 * its @type resolves from roci_business_types() (inc/schema/business-types.php).
 *
 * File:    header.php
 * Version: 1.6.0
 * Updated: 2026-08-08
 *
 * @package ElRocinante
 */
?>
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
    $roci_site_name  = roci_setting( 'business', 'name', get_bloginfo( 'name' ) );
    $roci_title      = $roci_meta_title ? $roci_meta_title : get_the_title();

    // --------------------------------------------------------
    // META DESCRIPTION
    // --------------------------------------------------------
    $roci_meta_desc    = roci_get_field( 'roci_meta_description', $roci_post_id );
    $roci_default_desc = roci_setting( 'seo', 'default_meta_description' );
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
    $roci_og_image_url    = '';
    $roci_og_image_att_id = 0; // attachment ID of the image that won the URL race (0 = none / site default)

    // 1. OG Image field (Metabox)
    $roci_og_image_field = roci_get_field( 'roci_og_image', $roci_post_id );
    if ( $roci_og_image_field ) {
        $roci_og_image_ids = array_keys( $roci_og_image_field );
        if ( ! empty( $roci_og_image_ids ) ) {
            $roci_og_image_att_id = $roci_og_image_ids[0];
            $roci_og_image_url    = wp_get_attachment_image_url( $roci_og_image_att_id, 'full' );
        }
    }

    // 2. Featured Image fallback
    if ( ! $roci_og_image_url && has_post_thumbnail( $roci_post_id ) ) {
        $roci_og_image_att_id = get_post_thumbnail_id( $roci_post_id );
        $roci_og_image_url    = get_the_post_thumbnail_url( $roci_post_id, 'full' );
    }

    // 3. Site default fallback (Theme Settings → SEO)
    if ( ! $roci_og_image_url ) {
        $roci_og_image_att_id = 0; // site default is a bare URL — no attachment
        $roci_og_image_url    = roci_setting( 'seo', 'default_og_image' );
    }

    // --------------------------------------------------------
    // OG IMAGE ALT
    // Priority: typed field → attachment alt of the winning image
    //           → meta description → meta title
    // --------------------------------------------------------
    $roci_og_image_alt = roci_get_field( 'roci_og_image_alt', $roci_post_id );

    if ( ! $roci_og_image_alt && $roci_og_image_att_id ) {
        $roci_og_image_alt = get_post_meta( $roci_og_image_att_id, '_wp_attachment_image_alt', true );
    }
    if ( ! $roci_og_image_alt ) {
        $roci_og_image_alt = $roci_description;
    }
    if ( ! $roci_og_image_alt ) {
        $roci_og_image_alt = $roci_title;
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
    <meta property="og:image:alt" content="<?php echo esc_attr( $roci_og_image_alt ); ?>">
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
        $roci_biz_name     = roci_setting( 'business', 'name' );
        $roci_biz_phone    = roci_setting( 'business', 'phone' );
        $roci_biz_email    = roci_setting( 'business', 'email' );
        $roci_biz_street   = roci_setting( 'business', 'street' );
        $roci_biz_locality = roci_setting( 'business', 'locality' );
        $roci_biz_region   = roci_setting( 'business', 'region' );
        $roci_biz_postal   = roci_setting( 'business', 'postal' );
        $roci_biz_country  = roci_setting( 'business', 'country' );
        $roci_biz_schema_image = roci_setting( 'business', 'schema_image' );
        $roci_biz_price_range  = roci_setting( 'business', 'price_range' );
        $roci_biz_description  = roci_setting( 'business', 'description' );
        $roci_biz_latitude     = roci_setting( 'business', 'latitude' );
        $roci_biz_longitude    = roci_setting( 'business', 'longitude' );

        /*
         * @type resolved from the business type map, not hardcoded.
         *
         * inc/schema/business-types.php is the single source; the Business tab
         * and the settings sanitiser read the same function, so a child adding a
         * vertical through roci_business_types gets it here for free.
         *
         * An unset or unrecognised slug falls back to LocalBusiness — what this
         * file emitted before the map existed, so a site that has never opened
         * the Business tab is unchanged.
         */
        $roci_business_type_map = roci_business_types();
        $roci_biz_type          = roci_setting( 'business', 'type', 'general' );
        $roci_schema_type       = isset( $roci_business_type_map[ $roci_biz_type ]['schema'] )
            ? $roci_business_type_map[ $roci_biz_type ]['schema']
            : 'LocalBusiness';

        $roci_same_as = array_values( array_filter( [
            roci_setting( 'social', 'facebook' ),
            roci_setting( 'social', 'instagram' ),
            roci_setting( 'social', 'whatsapp' ),
            roci_setting( 'social', 'tiktok' ),
            roci_setting( 'social', 'youtube' ),
            roci_setting( 'social', 'linkedin' ),
            roci_setting( 'social', 'twitter' ),
            roci_setting( 'social', 'tripadvisor' ),
        ] ) );

        if ( $roci_biz_name ) :
            $roci_address_parts = array_filter( [
                'streetAddress'   => $roci_biz_street,
                'addressLocality' => $roci_biz_locality,
                'addressRegion'   => $roci_biz_region,
                'postalCode'      => $roci_biz_postal,
                'addressCountry'  => $roci_biz_country,
            ] );

            $roci_local_schema = [
                '@context'  => 'https://schema.org',
                '@type'     => $roci_schema_type,
                '@id'       => home_url( '/#organization' ),
                'name'      => $roci_biz_name,
                'url'       => home_url( '/' ),
                'telephone' => $roci_biz_phone,
                'email'     => $roci_biz_email,
                'sameAs'    => $roci_same_as,
            ];

            // Only emit image when a Schema Image is set (blank = key omitted by design).
            if ( $roci_biz_schema_image ) {
                $roci_local_schema['image'] = $roci_biz_schema_image;
            }

            // Only emit priceRange when set (blank = key omitted).
            if ( $roci_biz_price_range ) {
                $roci_local_schema['priceRange'] = $roci_biz_price_range;
            }

            // Only emit a PostalAddress node when at least one address part is set.
            if ( $roci_address_parts ) {
                $roci_local_schema['address'] = [ '@type' => 'PostalAddress' ] + $roci_address_parts;
            }

            /*
             * GEO — both coordinates or neither.
             *
             * TWO DELIBERATE DEPARTURES FROM THE address PATTERN ABOVE.
             *
             * 1. The guard is '' !== , not truthiness. Latitude 0 is the equator
             *    and longitude 0 is the Greenwich meridian — both valid, both
             *    falsy. Every other field here is a non-empty string, so if(...)
             *    has always been safe; for these two it is not.
             *
             * 2. Both parts are required, where address emits on ANY one part.
             *    A GeoCoordinates node carrying one coordinate is not partial
             *    data, it is wrong data — it points at a real place that is not
             *    this one. Half-populated emits nothing.
             *
             * THE CAST HAPPENS INSIDE THE GUARD, AND THE ORDER MATTERS. The
             * stored value is a numeric STRING so that '' stays distinguishable
             * from '0'; the test is therefore on the string, and only a value
             * that has already passed it is cast. Casting first would turn ''
             * into 0.0 and silently place the site at Null Island.
             *
             * Casting at all is so the JSON-LD carries unquoted numbers —
             * "latitude": 10.4406, not "10.4406". schema.org accepts Number or
             * Text for these, but Number is the conventional form.
             */
            $roci_geo_parts = array();
            if ( '' !== $roci_biz_latitude ) {
                $roci_geo_parts['latitude'] = (float) $roci_biz_latitude;
            }
            if ( '' !== $roci_biz_longitude ) {
                $roci_geo_parts['longitude'] = (float) $roci_biz_longitude;
            }
            if ( 2 === count( $roci_geo_parts ) ) {
                $roci_local_schema['geo'] = [ '@type' => 'GeoCoordinates' ] + $roci_geo_parts;
            }

            // Only emit description when set (blank = key omitted, as above).
            if ( '' !== $roci_biz_description ) {
                $roci_local_schema['description'] = $roci_biz_description;
            }

            /*
             * roci_schema_data — the site-level JSON-LD, filtered before encode.
             *
             * Receives the fully assembled LocalBusiness array: the eight base
             * keys plus whichever of image / priceRange / address survived their
             * gates above. A child may modify it, add to it, or replace it
             * outright — change '@type' to the vertical it actually is, add
             * openingHoursSpecification / geo / aggregateRating, swap a value
             * the Business tab cannot express — and return it. Whatever comes
             * back is what gets encoded.
             *
             * Returning the array unchanged is the default: with no listener
             * this dispatch is transparent and the emitted JSON-LD is identical
             * to what the parent built. Nothing downstream re-reads the
             * settings, so the filter is the last word.
             *
             * This is the only extension point for site-level structured data.
             * Before it existed, a child that needed a different '@type' had to
             * override header.php — which silently drops the whole <head> SEO,
             * OG, Twitter and per-page schema block with it. See CLAUDE.md §5b.
             */
            $roci_local_schema = apply_filters( 'roci_schema_data', $roci_local_schema );
        ?>
    <!-- Schema JSON-LD — Site Level (Theme Settings) -->
    <script type="application/ld+json">
    <?php echo wp_json_encode( $roci_local_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); ?>
    </script>
        <?php endif; ?>
    <?php endif; ?>

    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header id="site-header" class="site-header">
    <?php do_action( 'roci_nav' ); ?>
</header>