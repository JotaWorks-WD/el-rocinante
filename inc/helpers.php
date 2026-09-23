<?php
/**
 * Helper Functions — Image, Video & FAQ Output
 *
 * Reusable output helpers for generating semantic HTML for images,
 * video embeds, and FAQ schema. Called directly from page templates
 * and template parts throughout El Rocinante and child themes.
 *
 * File:    inc/helpers.php
 * Version: 1.8.0
 * Updated: 2026-09-23
 *
 * @package ElRocinante
 *
 * Functions:
 *   jw_get_webp_url()              — Resolves WebP URL for a given attachment ID + size
 *   jw_picture()                   — Standard content image as <picture> tag
 *   jw_hero_picture()              — Art-directed hero <picture> (desktop + mobile crop)
 *   jw_logo_dimensions()           — Intrinsic [w, h] of a logo attachment, SVG-aware
 *   jw_bunny_video()               — Clean Bunny Stream iframe embed
 *   jw_faq_schema()                — FAQPage JSON-LD schema output from jw_faq_items field
 *   jw_link_atts()                 — Returns target + rel attribute string for external links
 *   jw_wysiwyg_body()              — Expands shortcodes + targets external links inside stored wysiwyg HTML
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
 * Outputs a <picture> tag for a standard content image (no art direction).
 * Width and height attributes are pulled from attachment metadata
 * automatically to prevent Cumulative Layout Shift (CLS).
 *
 * RESPONSIVE (v1.6.0): the <img> carries srcset + sizes built from the sizes
 * WordPress generated, and the WebP <source> carries the matching candidates
 * whose .webp sibling exists (falling back to the single WebP URL when none
 * do). sizes is "100vw" for eager images and WordPress's default for lazy
 * ones. src / width / height are unchanged, so srcset-unaware browsers
 * behave exactly as before.
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
 *                                'eager' ALSO adds fetchpriority="high" and
 *                                data-no-lazy="1" to the <img>, so the LCP image is
 *                                fetched first and skipped by plugin lazy-loaders
 *                                (LiteSpeed et al.). 'lazy' output is unchanged.
 *                                ⚠ So pass 'eager' for the ONE above-the-fold image
 *                                per page only — several fetchpriority="high" images
 *                                compete with each other and cancel the benefit.
 * @param  string|null $sizes     OPTIONAL (v1.8.0). A `sizes` value describing how
 *                                wide the image actually renders, e.g.
 *                                "(min-width: 48em) calc((100vw - 184px) / 3), calc(100vw - 40px)".
 *                                Used for BOTH the <img> and the WebP <source>.
 *                                Omit or pass null (or '') and the computed default
 *                                applies exactly as before: "100vw" for eager, WP's
 *                                default for lazy. WP's default assumes the image
 *                                renders at its full intrinsic width, which is wrong
 *                                for any card in a grid — pass this there.
 * @return string                 HTML output.
 */
function jw_picture( $attachment_id, $size = 'full', $alt = null, $class = '', $loading = 'lazy', $sizes = null ) {

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

    // Eager means LCP: raise its fetch priority and opt it out of plugin
    // lazy-loaders. '' for lazy, so lazy output stays byte-identical.
    //
    // ⚠ IT RIDES ON THE $dims ECHO, NOT THE loading LINE, DELIBERATELY. That
    // line already ends in a PHP closing tag, whose trailing newline PHP
    // swallows either way, so appending '' there cannot move a byte. Echoing
    // it after loading="…" instead would put a new closing tag where a literal
    // double quote ended the line, PHP would eat that newline, and the `>`
    // would jump up onto the loading line on every lazy image in the network.
    //
    // ⚠ NEVER WRITE A LITERAL PHP CLOSING TAG INSIDE A // COMMENT HERE. A
    // single-line comment ends at the closing tag as well as at a newline, so
    // PHP drops out of code mode mid-comment and prints the rest of the
    // function as raw text. v6.25.0 did exactly that in this comment: this
    // assignment and ob_start() never ran, and every jw_picture() call leaked
    // text into the page. Spell it out in words.
    $priority = ( 'eager' === $loading ) ? ' fetchpriority="high" data-no-lazy="1"' : '';

    // RESPONSIVE CANDIDATES, from the sizes WordPress generated. Eager images
    // are heroes that span the viewport, so they declare 100vw; lazy images
    // take WordPress's own sizes value for the requested size. src, width and
    // height are untouched, so a browser that ignores srcset behaves as before.
    // Both helpers return false when there is nothing to offer (a single size,
    // an SVG), and then no srcset or sizes attribute is emitted at all.
    //
    // A CALLER-SUPPLIED $sizes WINS (v1.8.0), because only the template knows
    // the grid the image sits in. null or an empty string falls through to the
    // computed default, which is exactly the pre-1.8.0 value, so every existing
    // call renders byte-identical markup. The same value then feeds the WebP
    // <source> below via $sizes_attr, so the two can never disagree.
    $srcset     = wp_get_attachment_image_srcset( $attachment_id, $size );
    $sizes      = ( is_string( $sizes ) && '' !== trim( $sizes ) )
        ? trim( $sizes )
        : ( ( 'eager' === $loading ) ? '100vw' : wp_get_attachment_image_sizes( $attachment_id, $size ) );
    $sizes_attr = $sizes ? ' sizes="' . esc_attr( $sizes ) . '"' : '';
    $responsive = $srcset ? ' srcset="' . esc_attr( $srcset ) . '"' . $sizes_attr : '';

    // WEBP SOURCE: the same candidates, keeping only those whose .webp sibling
    // exists on disk — the same extension swap and file_exists() test
    // jw_get_webp_url() applies to the single URL. Built only when the
    // REQUESTED size has a .webp (the condition that renders the <source> at
    // all), so the list always contains that size and can never offer the
    // browser nothing but small candidates. No survivors -> today's single URL.
    $webp_srcset = '';

    if ( $webp_src && $srcset ) {
        $upload_dir = wp_upload_dir();
        // Scheme-agnostic, because srcset URLs can be forced to https while the
        // upload baseurl is not; a scheme mismatch would drop every candidate.
        $base_url   = preg_replace( '#^https?:#i', '', $upload_dir['baseurl'] );
        $kept       = [];

        foreach ( explode( ', ', $srcset ) as $candidate ) {
            $parts = preg_split( '/\s+/', trim( $candidate ) );

            if ( 2 !== count( $parts ) ) {
                continue;
            }

            list( $candidate_url, $descriptor ) = $parts;
            $candidate_webp = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $candidate_url );

            // Unchanged means the candidate is already .webp — keep it, as
            // jw_get_webp_url() returns an already-WebP original as-is.
            if ( $candidate_webp !== $candidate_url ) {
                $webp_path = str_replace( $base_url, $upload_dir['basedir'], preg_replace( '#^https?:#i', '', $candidate_webp ) );

                if ( ! file_exists( $webp_path ) ) {
                    continue;
                }
            }

            $kept[] = $candidate_webp . ' ' . $descriptor;
        }

        $webp_srcset = implode( ', ', $kept );
    }

    // sizes rides on the <source> too: a <source> with width descriptors and
    // no sizes of its own defaults to 100vw, which would undo the saving on
    // every lazy image. A single-URL fallback carries no descriptor and so
    // takes no sizes.
    $webp_srcset_attr = $webp_srcset ? esc_attr( $webp_srcset ) : esc_url( (string) $webp_src );
    $webp_sizes_attr  = $webp_srcset ? $sizes_attr : '';

    ob_start();
    ?>
    <picture>
        <?php if ( $webp_src ) : ?>
            <source type="image/webp" srcset="<?php echo $webp_srcset_attr; ?>"<?php echo $webp_sizes_attr; ?>>
        <?php endif; ?>
        <img
            src="<?php echo esc_url( $img_src ); ?>"
            alt="<?php echo $alt; ?>"
            <?php echo $class; ?>
            <?php echo $dims . $responsive . $priority; ?>
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
 * RESPONSIVE (v1.6.0): the mobile <img> carries srcset + sizes from the
 * mobile attachment's generated sizes; the desktop <source> carries the
 * desktop attachment's candidates whose .webp sibling exists (single URL
 * when none do, or when the full-size desktop .webp is missing). sizes is
 * "100vw" for eager, WordPress's default for lazy — same rules as
 * jw_picture().
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
 *                             'eager' adds fetchpriority="high" + data-no-lazy="1",
 *                             exactly as jw_picture() does.
 * @return string              HTML output.
 */
function jw_hero_picture( $desktop_id, $mobile_id, $alt = null, $class = '', $loading = 'eager' ) {

    if ( ! $desktop_id || ! $mobile_id ) {
        return '';
    }

    // Prefer WebP, fall back to standard URL for each crop. $desktop_webp is
    // kept separately because the desktop srcset below is only built when
    // the full-size .webp exists.
    $desktop_webp = jw_get_webp_url( $desktop_id, 'full' );
    $desktop_src  = $desktop_webp ?: wp_get_attachment_image_url( $desktop_id, 'full' );
    $mobile_src   = jw_get_webp_url( $mobile_id, 'full' )  ?: wp_get_attachment_image_url( $mobile_id, 'full' );

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

    // Same rule as jw_picture(): eager gains fetchpriority + data-no-lazy,
    // lazy stays byte-identical. Rides on the $dims echo for the reason given
    // in jw_picture().
    $priority = ( 'eager' === $loading ) ? ' fetchpriority="high" data-no-lazy="1"' : '';

    // RESPONSIVE CANDIDATES — same rules as jw_picture(), applied per crop.
    // The <img> is the MOBILE crop, so its srcset and sizes come from the
    // mobile attachment. Eager declares 100vw; lazy takes WordPress's sizes.
    $srcset     = wp_get_attachment_image_srcset( $mobile_id, 'full' );
    $sizes      = ( 'eager' === $loading ) ? '100vw' : wp_get_attachment_image_sizes( $mobile_id, 'full' );
    $sizes_attr = $sizes ? ' sizes="' . esc_attr( $sizes ) . '"' : '';
    $responsive = $srcset ? ' srcset="' . esc_attr( $srcset ) . '"' . $sizes_attr : '';

    // The desktop <source> offers the DESKTOP attachment's candidates, kept
    // only where the .webp sibling exists (same test as jw_picture()). Built
    // only when the full-size desktop .webp exists, so the list always holds
    // the full size; otherwise the <source> keeps today's single URL, WebP or
    // not, exactly as before.
    $desktop_srcset      = wp_get_attachment_image_srcset( $desktop_id, 'full' );
    $desktop_sizes       = ( 'eager' === $loading ) ? '100vw' : wp_get_attachment_image_sizes( $desktop_id, 'full' );
    $desktop_webp_srcset = '';

    if ( $desktop_webp && $desktop_srcset ) {
        $upload_dir = wp_upload_dir();
        $base_url   = preg_replace( '#^https?:#i', '', $upload_dir['baseurl'] );
        $kept       = [];

        foreach ( explode( ', ', $desktop_srcset ) as $candidate ) {
            $parts = preg_split( '/\s+/', trim( $candidate ) );

            if ( 2 !== count( $parts ) ) {
                continue;
            }

            list( $candidate_url, $descriptor ) = $parts;
            $candidate_webp = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $candidate_url );

            if ( $candidate_webp !== $candidate_url ) {
                $webp_path = str_replace( $base_url, $upload_dir['basedir'], preg_replace( '#^https?:#i', '', $candidate_webp ) );

                if ( ! file_exists( $webp_path ) ) {
                    continue;
                }
            }

            $kept[] = $candidate_webp . ' ' . $descriptor;
        }

        $desktop_webp_srcset = implode( ', ', $kept );
    }

    // type="image/webp" shares the srcset line on purpose: the line must end
    // in a literal character, not in a closing tag, or PHP swallows the
    // newline after the sizes echo (the trap documented in jw_picture()).
    $desktop_srcset_attr = $desktop_webp_srcset ? esc_attr( $desktop_webp_srcset ) : esc_url( $desktop_src );
    $desktop_sizes_attr  = ( $desktop_webp_srcset && $desktop_sizes ) ? ' sizes="' . esc_attr( $desktop_sizes ) . '"' : '';

    ob_start();
    ?>
    <picture>
        <!-- Desktop crop: activates at 768px and above -->
        <source
            media="(min-width: 768px)"
            srcset="<?php echo $desktop_srcset_attr; ?>"<?php echo $desktop_sizes_attr; ?> type="image/webp"
        >
        <!-- Mobile crop: default source, no media query -->
        <img
            src="<?php echo esc_url( $mobile_src ); ?>"
            alt="<?php echo $alt; ?>"
            <?php echo $class; ?>
            <?php echo $dims . $responsive . $priority; ?>
            loading="<?php echo esc_attr( $loading ); ?>"
        >
    </picture>
    <?php
    return ob_get_clean();
}


/**
 * jw_logo_dimensions()
 *
 * Returns the intrinsic [ width, height ] of a logo attachment as ints, so a
 * hand-built <img> can carry width/height attributes even when the logo is an
 * SVG. WordPress stores width/height metadata for raster images only; an SVG
 * upload has none, which is why logos were rendering unsized.
 *
 * Resolution order:
 *   1. wp_get_attachment_metadata() width/height, when both are present.
 *   2. For a .svg file: the root <svg> element's width and height attributes,
 *      when both are plain numbers (unitless or px).
 *   3. Otherwise the root <svg>'s viewBox, using its 3rd and 4th values.
 *
 * Values are rounded to ints. Any failure (no attachment, unreadable file,
 * .svgz, no usable attribute) returns an empty array, and callers then emit
 * no width/height at all rather than a wrong one.
 *
 * Only the first 8 KB of an SVG is read: the root <svg> tag always sits at the
 * top of the file, and a full read of a large logo on every request buys
 * nothing. Cached per request in a static, because the same logo is typically
 * asked for twice per page (loader + nav).
 *
 * Usage:
 *   $dims = jw_logo_dimensions( (int) get_theme_mod( 'custom_logo' ) );
 *   if ( $dims ) { list( $w, $h ) = $dims; }
 *
 * @param  int   $attachment_id  Attachment ID of the logo.
 * @return int[]                 [ width, height ], or [] on failure.
 */
function jw_logo_dimensions( $attachment_id ) {

    static $cache = [];

    $attachment_id = (int) $attachment_id;

    if ( ! $attachment_id ) {
        return [];
    }

    if ( isset( $cache[ $attachment_id ] ) ) {
        return $cache[ $attachment_id ];
    }

    $dims     = [];
    $metadata = wp_get_attachment_metadata( $attachment_id );

    if ( is_array( $metadata ) && ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
        $dims = [ (int) round( $metadata['width'] ), (int) round( $metadata['height'] ) ];
    } else {
        $file = get_attached_file( $attachment_id );

        // .svg only: .svgz is gzipped and would need decoding first.
        if ( $file && preg_match( '/\.svg$/i', $file ) && is_readable( $file ) ) {
            $svg = file_get_contents( $file, false, null, 0, 8192 );

            if ( $svg && preg_match( '/<svg\b[^>]*>/i', $svg, $tag ) ) {
                // Leading \s keeps these from matching stroke-width and the like.
                $num = '\s*=\s*["\']\s*([0-9]*\.?[0-9]+)\s*(?:px)?\s*["\']';
                $w   = preg_match( '/\swidth' . $num . '/i', $tag[0], $wm ) ? (float) $wm[1] : 0;
                $h   = preg_match( '/\sheight' . $num . '/i', $tag[0], $hm ) ? (float) $hm[1] : 0;

                if ( $w <= 0 || $h <= 0 ) {
                    $w = 0;
                    $h = 0;

                    if ( preg_match( '/\sviewBox\s*=\s*["\']([^"\']+)["\']/i', $tag[0], $vb ) ) {
                        $parts = preg_split( '/[\s,]+/', trim( $vb[1] ) );

                        if ( 4 === count( $parts ) ) {
                            $w = (float) $parts[2];
                            $h = (float) $parts[3];
                        }
                    }
                }

                if ( $w > 0 && $h > 0 ) {
                    $dims = [ (int) round( $w ), (int) round( $h ) ];
                }
            }
        }
    }

    // A result that rounds to zero is no result.
    if ( $dims && ( $dims[0] < 1 || $dims[1] < 1 ) ) {
        $dims = [];
    }

    $cache[ $attachment_id ] = $dims;

    return $dims;
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

    // ⚠ DECODE BEFORE ENCODING. faq_answer is a Meta Box textarea, which Meta
    // Box sanitises with wp_kses_post() on save, so every bare & is STORED as
    // &amp; — and wp_json_encode() would publish the entity literally. Each
    // string is decoded here, before it is placed in the array, so the encoder
    // escapes whatever the decode produces. Same per-value rule as
    // roci_schema_json_for_output() in inc/schema/schema-tokens.php. The
    // stored values, and what editors see, are unchanged.
    foreach ( $items as $item ) {
        $question = isset( $item['faq_question'] ) ? trim( html_entity_decode( (string) $item['faq_question'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '';
        $answer   = isset( $item['faq_answer'] )   ? trim( html_entity_decode( (string) $item['faq_answer'],   ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '';

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

    // SCRIPT-SAFE. JSON_UNESCAPED_SLASHES leaves "</" as-is, and a decoded
    // answer can now contain a literal </script>, which would close the tag
    // early. "<\/" is a valid JSON escape that parses back to the same string.
    // wp_json_encode() returns false on failure; that case emits nothing,
    // rather than an empty script tag.
    $json = wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

    if ( ! is_string( $json ) || '' === $json ) {
        return;
    }

    echo '<script type="application/ld+json">' . str_replace( '</', '<\/', $json ) . '</script>' . "\n";
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
// WYSIWYG BODY OUTPUT
// ============================================================

/**
 * jw_wysiwyg_body()
 *
 * Prepares a stored wysiwyg field for output: expands shortcodes, then adds
 * new-tab attributes to EXTERNAL links inside the markup. Returns the HTML;
 * the caller echoes it.
 *
 * THE COMPANION TO jw_link_atts(), FOR THE CASE IT CANNOT REACH. That helper
 * takes one URL the theme owns and returns attributes for one anchor the
 * theme is writing. This one takes a blob of author-written HTML and fixes
 * the anchors INSIDE it, which is the only way to reach a link an editor
 * pasted into a wysiwyg field. Same external test, same output attributes —
 * deliberately, so a hand-written link and a pasted one behave identically.
 *
 * ⚠ THIS IS NOT A GLOBAL FILTER, AND MUST NOT BECOME ONE. It is called
 * explicitly at the output site, matching how jw_link_atts() is used
 * everywhere in this network. Hooking `the_content` or `wp_targeted_link_rel`
 * would silently change every link on every site the parent touches — a far
 * larger blast radius than the field this was written for.
 *
 * WHY NOT core's wp_targeted_link_rel(): it only adds `rel` to anchors that
 * ALREADY carry a target, which is the opposite problem. The links this fixes
 * have neither.
 *
 * NO wpautop(). The field is a wysiwyg — TinyMCE stores <p> tags in the value
 * — so wpautop() would find paragraphs already there and change nothing.
 * (A TEXTAREA field is the opposite case and does need it; see Fish Potrero's
 * template-parts/tour/specs.php and tour/pricing.php, where the pairing is
 * wpautop( esc_html() ) and both halves are load-bearing.)
 *
 * Regex rather than DOMDocument is deliberate: this rewrites opening <a> tags
 * only and never reads structure, DOMDocument would need mangling guards for
 * a fragment, and it is the same approach core takes in wp_targeted_link_rel().
 *
 * Three cases are left exactly as authored:
 *   - Anchors that already declare a target — the author made a choice.
 *   - Non-http(s) hrefs — relative, fragment, mailto:, tel:. Same reasoning
 *     jw_link_atts() documents: these open a client, not a navigable page.
 *   - Same-host http(s) links.
 *
 * An existing rel is MERGED, not replaced — `rel="nofollow"` becomes
 * `rel="nofollow noopener noreferrer"`. Emitting a second rel attribute
 * instead would produce a duplicate the browser resolves by taking the first,
 * silently dropping whichever one mattered.
 *
 * Usage:
 *   echo jw_wysiwyg_body( $body );
 *
 * @param  string $html  Raw stored wysiwyg HTML.
 * @return string        Shortcode-expanded HTML with external links targeted.
 */
function jw_wysiwyg_body( $html ) {

    if ( ! is_string( $html ) || '' === trim( $html ) ) {
        return '';
    }

    $html = do_shortcode( $html );

    // Nothing to rewrite — skip the regex entirely on the common case.
    if ( false === stripos( $html, '<a ' ) ) {
        return $html;
    }

    $home_host = wp_parse_url( home_url(), PHP_URL_HOST );

    if ( ! $home_host ) {
        return $html;
    }

    return preg_replace_callback(
        '#<a\s[^>]*>#i',
        function ( $matches ) use ( $home_host ) {

            $tag = $matches[0];

            // Author already chose a target — leave the anchor alone.
            if ( preg_match( '#\starget\s*=#i', $tag ) ) {
                return $tag;
            }

            if ( ! preg_match( '#\shref\s*=\s*("|\')(.*?)\1#i', $tag, $href ) ) {
                return $tag;
            }

            // The stored href is entity-encoded (&amp; in query strings);
            // decode before parsing so the host reads correctly.
            $url = trim( html_entity_decode( $href[2], ENT_QUOTES, 'UTF-8' ) );

            if ( ! preg_match( '#^https?://#i', $url ) ) {
                return $tag;
            }

            $link_host = wp_parse_url( $url, PHP_URL_HOST );

            if ( ! $link_host || $link_host === $home_host ) {
                return $tag;
            }

            // Merge into an existing rel rather than emitting a second one.
            if ( preg_match( '#\srel\s*=\s*("|\')(.*?)\1#i', $tag, $rel ) ) {

                $tokens = preg_split( '#\s+#', trim( $rel[2] ), -1, PREG_SPLIT_NO_EMPTY );
                $tokens = array_unique( array_merge( (array) $tokens, array( 'noopener', 'noreferrer' ) ) );

                $tag    = str_replace(
                    $rel[0],
                    ' rel="' . esc_attr( implode( ' ', $tokens ) ) . '"',
                    $tag
                );
                $inject = ' target="_blank"';

            } else {
                $inject = ' target="_blank" rel="noopener noreferrer"';
            }

            // Insert before the closing bracket, preserving a self-closing slash.
            return preg_replace( '#\s*(/?)>$#', $inject . '$1>', $tag, 1 );
        },
        $html
    );
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