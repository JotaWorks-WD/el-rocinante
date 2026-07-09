<?php
/**
 * Helper Functions — Image, Video & FAQ Output
 *
 * Reusable output helpers for generating semantic HTML for images,
 * video embeds, and FAQ schema. Called directly from page templates
 * and template parts throughout El Rocinante and child themes.
 *
 * File:    inc/helpers.php
 * Version: 1.3.0
 * Updated: 2026-07-09
 *
 * @package ElRocinante
 *
 * Functions:
 *   jw_get_webp_url()              — Resolves WebP URL for a given attachment ID + size
 *   jw_picture()                   — Standard content image as <picture> tag
 *   jw_hero_picture()              — Art-directed hero <picture> (desktop + mobile crop)
 *   jw_bunny_video()               — Clean Bunny Stream iframe embed
 *   jw_faq_schema()                — FAQPage JSON-LD schema output from jw_faq_items field
 *   jw_link_atts()                 — Returns target + rel attribute string for external links
 *   roci_sanitize_object_position() — Whitelists a CSS object-position value (strict)
 *   roci_get_hero_focus()          — Resolves the sanitized hero focal point for a post
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
 * with a long expiry (e.g. 30 days). Premature for low-traffic sites.
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
 * @param  string|null $alt        Alt text. Omit or pass null to fall back to the
 *                                media library alt (_wp_attachment_image_alt). Pass ''
 *                                for decorative images (intentional empty alt).
 * @param  string $class          CSS class on the <img> tag. Default: ''.
 * @param  string $loading        'lazy' or 'eager'. Default: 'lazy'.
 *                                IMPORTANT: Pass 'eager' for above-the-fold / LCP images
 *                                such as hero sections. Failure to do so will delay the
 *                                Largest Contentful Paint and hurt Core Web Vitals scores.
 *                                jw_hero_picture() already defaults to 'eager' — this
 *                                function intentionally keeps 'lazy' as default since most
 *                                callers use it for content images below the fold.
 * @return string                 HTML output.
 */
function jw_picture( $attachment_id, $size = 'full', $alt = null, $class = '', $loading = 'lazy' ) {

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

    // null means alt was not passed — fall back to the media library value.
    if ( null === $alt ) {
        $alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
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
 * @param  string|null $alt     Alt text. Omit or pass null to fall back to the
 *                              media library alt of the mobile attachment. Pass ''
 *                              for decorative images (intentional empty alt).
 * @param  string $class       CSS class on the <img> tag. Default: ''.
 * @param  string $loading     'lazy' or 'eager'. Default: 'eager' (heroes are LCP).
 * @return string              HTML output.
 */
function jw_hero_picture( $desktop_id, $mobile_id, $alt = null, $class = '', $loading = 'eager' ) {

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

    // null means alt was not passed — fall back to the mobile attachment's media library value.
    if ( null === $alt ) {
        $alt = (string) get_post_meta( $mobile_id, '_wp_attachment_image_alt', true );
    }
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


// ============================================================
// LINK HELPERS
// ============================================================

/**
 * jw_link_atts()
 *
 * Returns the target and rel attribute string for a link, with a leading
 * space so it drops cleanly inline into an <a> tag. Returns the string
 * ' target="_blank" rel="noopener noreferrer"' for external URLs,
 * or '' for all other URLs.
 *
 * Returns new-tab attributes when:
 *   - The URL contains 'wa.me' (WhatsApp short links)
 *   - It is an http(s) URL whose host does not match the host of home_url()
 *
 * Returns '' when:
 *   - The URL begins with 'mailto:' or 'tel:' — these open the mail client
 *     or phone dialer, not a navigable page; target="_blank" is meaningless.
 *   - Relative URLs, fragment-only strings, and same-host http(s) URLs.
 *
 * Usage:
 *   <a href="<?php echo esc_url( $url ); ?>"<?php echo jw_link_atts( $url ); ?>>Link text</a>
 *
 * @param  string $url  The href value to evaluate.
 * @return string       Attribute string with leading space, or empty string.
 */
function jw_link_atts( $url ) {

    if ( ! $url ) {
        return '';
    }

    // WhatsApp short links.
    if ( false !== strpos( $url, 'wa.me' ) ) {
        return ' target="_blank" rel="noopener noreferrer"';
    }

    // For http(s) URLs, compare hosts against the current site.
    if ( preg_match( '#^https?://#i', $url ) ) {
        $link_host = parse_url( $url, PHP_URL_HOST );
        $home_host = parse_url( home_url(), PHP_URL_HOST );

        if ( $link_host && $home_host && $link_host !== $home_host ) {
            return ' target="_blank" rel="noopener noreferrer"';
        }
    }

    return '';
}


// ============================================================
// HERO FOCUS HELPERS — roci-tour-layout bundle
// ============================================================
//
// Support helpers for the Hero Display meta box registered by the
// tour-layout bundle (inc/layout-bundles/tour-layout.php). These are
// pure/read functions with no side effects and register nothing, so they
// are safe to define unconditionally here — a site that has not opted
// into 'roci-tour-layout' simply never calls them. The child template
// calls roci_get_hero_focus() when rendering the hero.

/**
 * roci_sanitize_object_position()
 *
 * Strict whitelist for a CSS object-position value. This value is written
 * into an inline style attribute downstream, so the sanitizer is
 * deliberately narrow: it accepts ONLY one or two space-separated tokens,
 * each of which is a positional keyword (top|center|bottom|left|right) or
 * a signed number with a % or px unit. Anything else — extra tokens, bare
 * numbers, function calls, CSS injection attempts, empty input — collapses
 * to the safe default 'center center'.
 *
 * PASS (returned normalized, lowercased):
 *   "center 15%"   -> "center 15%"
 *   "center center"-> "center center"
 *   "center 0%"    -> "center 0%"
 *   "center 100%"  -> "center 100%"
 *   "center 25%"   -> "center 25%"
 *   "top"          -> "top"
 *   "left top"     -> "left top"
 *
 * FAIL (all return 'center center'):
 *   "red; content:x"   (keyword not in whitelist + stray punctuation)
 *   ""                 (empty)
 *   "url(evil)"        (function call)
 *   "expression(1)"    (function call)
 *   "100"              (bare number, no unit or keyword)
 *
 * @param  mixed  $value Raw value to sanitize.
 * @return string        A valid object-position, or 'center center'.
 */
function roci_sanitize_object_position( $value ) {

    $default = 'center center';

    if ( ! is_string( $value ) ) {
        return $default;
    }

    $value = trim( $value );

    if ( $value === '' ) {
        return $default;
    }

    // One or two whitespace-separated tokens, nothing more.
    $tokens = preg_split( '/\s+/', $value );

    if ( ! $tokens || count( $tokens ) < 1 || count( $tokens ) > 2 ) {
        return $default;
    }

    // Each token: a positional keyword, or a signed number with %/px unit.
    $token_pattern = '/^(?:top|center|bottom|left|right|-?\d+(?:\.\d+)?(?:%|px))$/i';

    foreach ( $tokens as $token ) {
        if ( ! preg_match( $token_pattern, $token ) ) {
            return $default;
        }
    }

    return strtolower( implode( ' ', $tokens ) );
}


/**
 * roci_get_hero_focus()
 *
 * Resolves the hero focal point for a post into a sanitized CSS
 * object-position string, ready to drop into an inline style. Reads the
 * roci_hero_focus select; if it is the '__custom__' sentinel, substitutes
 * the roci_hero_focus_custom text field instead. The result always passes
 * through roci_sanitize_object_position(), so callers can output it
 * directly (still esc_attr() at the point of output) and are guaranteed a
 * valid value — unset or invalid data resolves to 'center center'.
 *
 * This is the API the tour-layout child template calls. It is safe to call
 * even when Meta Box is inactive: roci_get_field() returns '' in that case,
 * which the sanitizer maps to the default.
 *
 * Usage (child hero template):
 *   $focus = roci_get_hero_focus( $post_id );
 *   printf( ' style="object-position:%s;"', esc_attr( $focus ) );
 *
 * @param  int|null $post_id Post ID. Defaults to the current post.
 * @return string           Sanitized object-position value.
 */
function roci_get_hero_focus( $post_id = null ) {

    $focus = roci_get_field( 'roci_hero_focus', $post_id );

    if ( $focus === '__custom__' ) {
        $focus = roci_get_field( 'roci_hero_focus_custom', $post_id );
    }

    return roci_sanitize_object_position( $focus );
}