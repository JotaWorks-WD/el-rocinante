<?php
/**
 * Helper Functions — Image, Video & FAQ Output
 *
 * Reusable output helpers for generating semantic HTML for images,
 * video embeds, and FAQ schema. Called directly from page templates
 * and template parts throughout El Rocinante and child themes.
 *
 * File:    inc/helpers.php
 * Version: 1.1.0
 * Updated: 2026-05-07
 *
 * @package ElRocinante
 *
 * Functions:
 *   jw_get_webp_url()    — Resolves WebP URL for a given attachment ID + size
 *   jw_picture()         — Standard content image as <picture> tag
 *   jw_hero_picture()    — Art-directed hero <picture> (desktop + mobile crop)
 *   jw_bunny_video()     — Clean Bunny Stream iframe embed
 *   jw_faq_schema()      — FAQPage JSON-LD schema output from jw_faq_items field
 *
 * Future expansion:
 *   If this file grows significantly, split into:
 *   inc/helpers/images.php, inc/helpers/video.php, inc/helpers/faq.php, etc.
 *   and convert this file into a loader using require_once.
 *   Template function calls require no changes when refactoring.
 */


// ============================================================
// IMAGE HELPERS
// ============================================================

/**
 * jw_get_webp_url()
 *
 * Returns the WebP URL for a given attachment ID and size.
 * Swaps the file extension of the WordPress-generated URL to .webp,
 * then confirms the file actually exists on the filesystem before returning.
 * Returns null if not found — callers handle null gracefully.
 *
 * NOTE: The file_exists() call hits the filesystem on every page load.
 * If traffic scales, wrap in a transient keyed to "{$id}_{$size}_webp"
 * with a long expiry (e.g. 30 days). Not needed for Fish Potrero or BPC yet.
 *
 * @param  int    $attachment_id  WordPress attachment ID.
 * @param  string $size           WordPress image size slug. Default: 'full'.
 * @return string|null            WebP URL if found, null if not.
 */
function jw_get_webp_url( $attachment_id, $size = 'full' ) {

    $src = wp_get_attachment_image_url( $attachment_id, $size );

    if ( ! $src ) {
        return null;
    }

    // Swap extension to .webp.
    $webp_url = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $src );

    // If the URL didn't change, the original was already .webp — return as-is.
    if ( $webp_url === $src ) {
        return $src;
    }

    // Resolve filesystem path and confirm the file exists.
    $upload_dir = wp_upload_dir();
    $webp_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $webp_url );

    if ( ! file_exists( $webp_path ) ) {
        return null;
    }

    return $webp_url;
}


/**
 * jw_picture()
 *
 * Outputs a <picture> tag for a standard content image (single WebP,
 * no art direction). Width and height attributes are pulled from attachment
 * metadata automatically to prevent Cumulative Layout Shift (CLS).
 *
 * Usage:
 *   echo jw_picture( $attachment_id, 'large', 'Alt text', 'venue__image', 'lazy' );
 *
 * @param  int    $attachment_id  WordPress attachment ID.
 * @param  string $size           WordPress image size slug. Default: 'full'.
 * @param  string $alt            Alt text. Default: '' (decorative).
 * @param  string $class          CSS class on the <img> tag. Default: ''.
 * @param  string $loading        'lazy' or 'eager'. Default: 'lazy'.
 *                                Pass 'eager' for above-the-fold / LCP images.
 * @return string                 HTML output.
 */
function jw_picture( $attachment_id, $size = 'full', $alt = '', $class = '', $loading = 'lazy' ) {

    if ( ! $attachment_id ) {
        return '';
    }

    $img_src = wp_get_attachment_image_url( $attachment_id, $size );

    if ( ! $img_src ) {
        return '';
    }

    $webp_src = jw_get_webp_url( $attachment_id, $size );

    // Pull width + height from attachment metadata for CLS prevention.
    $metadata = wp_get_attachment_metadata( $attachment_id );
    $width    = '';
    $height   = '';

    if ( $metadata ) {
        if ( $size === 'full' ) {
            $width  = isset( $metadata['width'] )  ? (int) $metadata['width']  : '';
            $height = isset( $metadata['height'] ) ? (int) $metadata['height'] : '';
        } elseif ( isset( $metadata['sizes'][ $size ] ) ) {
            $width  = (int) $metadata['sizes'][ $size ]['width'];
            $height = (int) $metadata['sizes'][ $size ]['height'];
        }
    }

    $alt     = esc_attr( $alt );
    $class   = $class ? ' class="' . esc_attr( $class ) . '"' : '';
    $loading = in_array( $loading, [ 'lazy', 'eager' ], true ) ? $loading : 'lazy';
    $dims    = ( $width && $height ) ? ' width="' . $width . '" height="' . $height . '"' : '';

    ob_start();
    ?>
    <picture>
        <?php if ( $webp_src ) : ?>
            <source type="image/webp" srcset="<?php echo esc_url( $webp_src ); ?>">
        <?php endif; ?>
        <img
            src="<?php echo esc_url( $img_src ); ?>"
            alt="<?php echo $alt; ?>"
            <?php echo $class; ?>
            <?php echo $dims; ?>
            loading="<?php echo esc_attr( $loading ); ?>"
        >
    </picture>
    <?php
    return ob_get_clean();
}


/**
 * jw_hero_picture()
 *
 * Outputs an art-directed <picture> tag for hero sections.
 * Desktop crop activates at 768px and above via media query.
 * Mobile crop is the default <img> — displayed below 768px.
 *
 * Both uploads should be WebP. Falls back to standard URL if WebP
 * is not found for either source.
 *
 * Usage:
 *   echo jw_hero_picture( $desktop_id, $mobile_id, 'Hero alt text', 'hero__img' );
 *
 * @param  int    $desktop_id  Attachment ID of the desktop crop.
 * @param  int    $mobile_id   Attachment ID of the mobile crop.
 * @param  string $alt         Alt text. Default: ''.
 * @param  string $class       CSS class on the <img> tag. Default: ''.
 * @param  string $loading     'lazy' or 'eager'. Default: 'eager' (heroes are LCP).
 * @return string              HTML output.
 */
function jw_hero_picture( $desktop_id, $mobile_id, $alt = '', $class = '', $loading = 'eager' ) {

    if ( ! $desktop_id || ! $mobile_id ) {
        return '';
    }

    // Prefer WebP, fall back to standard URL for each crop.
    $desktop_src = jw_get_webp_url( $desktop_id, 'full' ) ?: wp_get_attachment_image_url( $desktop_id, 'full' );
    $mobile_src  = jw_get_webp_url( $mobile_id, 'full' )  ?: wp_get_attachment_image_url( $mobile_id, 'full' );

    if ( ! $desktop_src || ! $mobile_src ) {
        return '';
    }

    // Dimensions from mobile attachment — it's the <img> default source.
    $metadata = wp_get_attachment_metadata( $mobile_id );
    $width    = isset( $metadata['width'] )  ? (int) $metadata['width']  : '';
    $height   = isset( $metadata['height'] ) ? (int) $metadata['height'] : '';

    $alt     = esc_attr( $alt );
    $class   = $class ? ' class="' . esc_attr( $class ) . '"' : '';
    $loading = in_array( $loading, [ 'lazy', 'eager' ], true ) ? $loading : 'eager';
    $dims    = ( $width && $height ) ? ' width="' . $width . '" height="' . $height . '"' : '';

    ob_start();
    ?>
    <picture>
        <!-- Desktop crop: activates at 768px and above -->
        <source
            media="(min-width: 768px)"
            srcset="<?php echo esc_url( $desktop_src ); ?>"
            type="image/webp"
        >
        <!-- Mobile crop: default source, no media query -->
        <img
            src="<?php echo esc_url( $mobile_src ); ?>"
            alt="<?php echo $alt; ?>"
            <?php echo $class; ?>
            <?php echo $dims; ?>
            loading="<?php echo esc_attr( $loading ); ?>"
        >
    </picture>
    <?php
    return ob_get_clean();
}


// ============================================================
// VIDEO HELPERS
// ============================================================

/**
 * jw_bunny_video()
 *
 * Outputs a clean Bunny Stream iframe embed.
 * No YouTube chrome, no related videos, no third-party branding.
 *
 * Find your Library ID in Bunny Stream dashboard → Library → Settings.
 * Find the Video ID in the video detail page URL or video list.
 *
 * The wrapper div outputs with class "jw-video-embed" by default.
 * Add the following to your SCSS for a responsive 16:9 ratio:
 *
 *   .jw-video-embed {
 *       position: relative;
 *       width: 100%;
 *       padding-top: 56.25%;
 *       overflow: hidden;
 *
 *       iframe {
 *           position: absolute;
 *           top: 0; left: 0;
 *           width: 100%;
 *           height: 100%;
 *           border: 0;
 *       }
 *   }
 *
 * Usage:
 *   echo jw_bunny_video( '123456', 'abc123-def456' );
 *   echo jw_bunny_video( '123456', 'abc123-def456', $poster_id, 'venue__video' );
 *
 * @param  string $library_id  Bunny Stream Library ID.
 * @param  string $video_id    Bunny Stream Video ID.
 * @param  int    $poster_id   Optional. WordPress attachment ID for poster image. Default: 0.
 * @param  string $class       CSS class on the wrapper <div>. Default: 'jw-video-embed'.
 * @return string              HTML output.
 */
function jw_bunny_video( $library_id, $video_id, $poster_id = 0, $class = '' ) {

    if ( ! $library_id || ! $video_id ) {
        return '';
    }

    $embed_url = 'https://iframe.mediadelivery.net/embed/' . esc_attr( $library_id ) . '/' . esc_attr( $video_id );

    // Append poster image URL if a WordPress attachment ID was provided.
    if ( $poster_id ) {
        $poster_url = wp_get_attachment_image_url( $poster_id, 'full' );
        if ( $poster_url ) {
            $embed_url .= '?poster=' . urlencode( $poster_url );
        }
    }

    $wrapper_class = $class ? esc_attr( $class ) : 'jw-video-embed';

    ob_start();
    ?>
    <div class="<?php echo $wrapper_class; ?>">
        <iframe
            src="<?php echo esc_url( $embed_url ); ?>"
            loading="lazy"
            allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
            allowfullscreen
        ></iframe>
    </div>
    <?php
    return ob_get_clean();
}


// ============================================================
// FAQ HELPERS
// ============================================================

/**
 * jw_faq_schema()
 *
 * Reads the jw_faq_items cloneable group for the current post
 * and outputs a FAQPage JSON-LD schema block.
 * Outputs nothing if no FAQ items are found.
 *
 * Called inside template-parts/faq.php in the child theme.
 * Do not call directly in page templates — let the partial handle it.
 *
 * @param  int|null $post_id  Post ID. Defaults to current post.
 * @return void               Echoes directly — do not echo the return value.
 */
function jw_faq_schema( $post_id = null ) {

    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    $items = rwmb_meta( 'jw_faq_items', array(), $post_id );

    if ( empty( $items ) || ! is_array( $items ) ) {
        return;
    }

    $entities = array();

    foreach ( $items as $item ) {
        $question = isset( $item['faq_question'] ) ? trim( $item['faq_question'] ) : '';
        $answer   = isset( $item['faq_answer'] )   ? trim( $item['faq_answer'] )   : '';

        if ( ! $question || ! $answer ) {
            continue;
        }

        $entities[] = array(
            '@type'          => 'Question',
            'name'           => $question,
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => $answer,
            ),
        );
    }

    if ( empty( $entities ) ) {
        return;
    }

    $schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $entities,
    );

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}