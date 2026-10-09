<?php
/**
 * Helper Functions — Image, Video & FAQ Output
 *
 * Reusable output helpers for generating semantic HTML for images,
 * video embeds, and FAQ schema. Called directly from page templates
 * and template parts throughout El Rocinante and child themes.
 *
 * File:    inc/helpers.php
 * Version: 1.18.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 *
 * Functions:
 *   jw_get_webp_url()              — Resolves WebP URL for a given attachment ID + size
 *   jw_picture()                   — Standard content image as <picture> tag
 *   jw_picture_sources()           — jw_picture()'s URLs/srcset/sizes, shared with the LCP preload
 *   jw_hero_picture()              — Art-directed hero <picture> (desktop + mobile crop)
 *   jw_hero_picture_sources()      — jw_hero_picture()'s URLs/srcset/sizes, shared with the LCP preload
 *   jw_logo_dimensions()           — Intrinsic [w, h] of a logo attachment, SVG-aware
 *   jw_bunny_video()               — Clean Bunny Stream iframe embed (always titled)
 *   jw_debug_comment()             — WP_DEBUG-only HTML comment flagging a content problem
 *   jw_faq_schema()                — FAQPage JSON-LD schema output from jw_faq_items field
 *   jw_link_atts()                 — Returns target + rel attribute string for external links
 *   jw_new_tab_note()              — SR-only "(opens in a new tab)" for links that open a new tab
 *   roci_is_external_link()        — (internal) The one external-link test behind the three link helpers
 *   roci_is_svg_attachment()       — (internal) The one SVG test behind the image helpers (mime image/svg+xml)
 *   roci_current_atts()            — Returns aria-current for the link to the page being viewed
 *   roci_current_path()            — (internal) Normalised path of the current page, once per request
 *   roci_normalize_url_path()      — (internal) Normalises a URL path for comparison
 *   roci_get_page_url()            — An internal page's URL, by assigned template then slug ('' on a miss)
 *   jw_wysiwyg_body()              — Expands shortcodes + targets external links inside stored wysiwyg HTML
 *   roci_sanitize_object_position() — Whitelists a CSS object-position value (strict)
 *   roci_get_hero_focus()          — Resolves the sanitized hero focal point for a post
 *   roci_entry_title_mode()        — How the parent's templates render the page's <h1> (filterable)
 *   roci_listing_context()         — (internal) Normalises a listing context to index / archive / search / 404
 *   roci_get_listing_classes()     — Listing container classes + results_tag (roci_listing_classes)
 *   roci_listing_results_atts()    — (internal) class + role attributes of the results container
 *   roci_listing_inner_open()      — (internal) The optional 'inner' <div> open / close
 *   roci_listing_inner_close()
 *   roci_listing_pagination()      — (internal) the_posts_pagination() in the optional 'pagination' <div>
 *   roci_get_listing_header_classes() — Listing <header> / <h1> / description classes (roci_listing_header_classes)
 *   roci_listing_shows_search_form() — Whether search.php prints its top form (roci_listing_search_form)
 *   roci_get_pagination_args()     — the_posts_pagination() args for a listing (roci_pagination_args)
 *   roci_resolve_class_slots()     — (internal) Validates + sanitises a filtered slot => classes map
 *   roci_class_attr()              — (internal) Escaped class="" value from a class list
 *   roci_class_attribute()         — (internal) Whole ' class="…"' attribute, or '' when empty
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

    // An SVG is never WebP (v1.14.0). Core hands back the bare .svg URL, which
    // the extension swap below leaves unchanged — and "unchanged" reads as
    // "already WebP". Answer before the size pipeline is touched.
    if ( roci_is_svg_attachment( $attachment_id ) ) {
        return null;
    }

    $src = wp_get_attachment_image_url( $attachment_id, $size );

    if ( ! $src ) {
        return null;
    }

    // Swap extension to .webp.
    $webp_url = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $src );

    // If the URL didn't change, the original was not jpg/png/gif. Only a real
    // .webp original is returned as-is (v1.15.0): before, ANY unchanged URL —
    // an AVIF, a BMP — was returned as the "WebP URL" and rendered under
    // <source type="image/webp">, which a WebP-but-not-AVIF browser picks and
    // then cannot decode.
    if ( $webp_url === $src ) {
        return preg_match( '/\.webp$/i', $src ) ? $src : null;
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
 * SVG (v1.14.0): an SVG attachment renders as a plain <img> inside <picture> —
 * no WebP <source>, no srcset/sizes, $size ignored — with width/height read
 * from the file by jw_logo_dimensions(). Raster output is unchanged.
 *
 * Usage:
 *   echo jw_picture( $attachment_id, 'large', 'Alt text', 'venue__image', 'lazy' );
 *
 * @param  int    $attachment_id  WordPress attachment ID.
 * @param  string $size           WordPress image size slug. Default: 'full'.
 * @param  string|null $alt        Alt text. Omit or pass null to fall back to the
 *                                media library alt (_wp_attachment_image_alt). Pass ''
 *                                for decorative images (intentional empty alt).
 *                                MISSING alt (null passed AND no media-library alt)
 *                                still renders alt="", but with WP_DEBUG on an HTML
 *                                comment after the picture names the attachment, so
 *                                it can be fixed (v1.11.0). A whitespace-only library
 *                                alt counts as missing.
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

    // URLs, srcset, sizes and the WebP candidate list come from
    // jw_picture_sources() — the SAME function roci_hero_preload() uses, so a
    // <link rel="preload"> for this image can never disagree with the markup
    // it preloads (v1.9.0). Nothing below changes a byte of the output.
    $sources = jw_picture_sources( $attachment_id, $size, $loading, $sizes );

    if ( ! $sources ) {
        return '';
    }

    $img_src  = $sources['img_src'];
    $webp_src = $sources['webp_src'];

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

    // SVG (v1.14.0): WordPress stores no width/height for an SVG, so size it
    // from the file itself — the same reader the loader logo uses. Raster
    // sources never carry the 'svg' flag, so this cannot run for them.
    if ( ( ! $width || ! $height ) && ! empty( $sources['svg'] ) ) {
        $svg_dims = jw_logo_dimensions( $attachment_id );

        if ( $svg_dims ) {
            list( $width, $height ) = $svg_dims;
        }
    }

    // null means alt was not passed — fall back to the media library value.
    // '' passed explicitly is INTENTIONAL decorative and is never flagged; null
    // with no library alt is MISSING — it still renders alt="" (no visual
    // change) and is flagged below in a WP_DEBUG-only comment. trim() makes a
    // whitespace-only library alt count as missing instead of rendering alt=" ".
    $roci_alt_missing = false;
    if ( null === $alt ) {
        $alt              = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
        $roci_alt_missing = ( '' === $alt );
    }
    $alt     = esc_attr( $alt );
    $class   = $class ? ' class="' . esc_attr( $class ) . '"' : '';
    $loading = $sources['loading'];
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

    // Attribute strings, built from the shared sources exactly as before —
    // see jw_picture_sources() for how srcset, sizes and the WebP list are
    // resolved.
    //
    // sizes rides on the <source> too: a <source> with width descriptors and
    // no sizes of its own defaults to 100vw, which would undo the saving on
    // every lazy image. A single-URL fallback carries no descriptor and so
    // takes no sizes.
    $sizes_attr       = $sources['sizes'] ? ' sizes="' . esc_attr( $sources['sizes'] ) . '"' : '';
    $responsive       = $sources['srcset'] ? ' srcset="' . esc_attr( $sources['srcset'] ) . '"' . $sizes_attr : '';
    $webp_srcset_attr = $sources['webp_srcset'] ? esc_attr( $sources['webp_srcset'] ) : esc_url( (string) $webp_src );
    $webp_sizes_attr  = $sources['webp_srcset'] ? $sizes_attr : '';

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
    // The missing-alt flag is echoed HERE, inside the PHP block that already
    // follows the picture, so the bytes around the closing picture tag never
    // move: with WP_DEBUG off jw_debug_comment() returns '' and the output is
    // identical to v1.10.0.
    if ( $roci_alt_missing ) {
        echo jw_debug_comment( sprintf( "jw_picture: attachment %d has no alt text. Set it in the Media Library, or pass '' if the image is decorative.", (int) $attachment_id ) );
    }
    return ob_get_clean();
}


/**
 * jw_picture_sources()
 *
 * Resolves everything jw_picture() needs to know about an image's URLs,
 * without building any markup: the <img> src, the WebP URL, the normalised
 * loading value, srcset, sizes, and the WebP candidate list for the <source>.
 *
 * WHY IT IS ITS OWN FUNCTION (v1.9.0). roci_hero_preload() emits a
 * <link rel="preload" imagesrcset imagesizes> for the page's hero, and those
 * two values must be BYTE-IDENTICAL to what the hero's <source>/<img> render,
 * or the browser downloads the image twice. The only way to guarantee that is
 * for both to come from one function. jw_picture() and the preload both call
 * this; neither assembles the values itself.
 *
 * The body is moved verbatim from jw_picture() 1.8.0 — same calls, same order,
 * same fallbacks — so jw_picture()'s output is unchanged.
 *
 * @param  int         $attachment_id  Attachment ID.
 * @param  string      $size           Image size slug.
 * @param  string      $loading        'lazy' or 'eager' (anything else -> 'lazy').
 * @param  string|null $sizes          Caller-supplied sizes, or null for the default.
 * @return array  Empty when the image cannot be resolved; otherwise the keys
 *                img_src, webp_src (string|null), loading, srcset (string|false),
 *                sizes (string|false), webp_srcset ('' when there is no list).
 */
function jw_picture_sources( $attachment_id, $size = 'full', $loading = 'lazy', $sizes = null ) {

    if ( ! $attachment_id ) {
        return [];
    }

    // SVG (v1.14.0): one file, no WebP sibling, no generated sizes — so no
    // <source>, no srcset, no sizes, and $size is moot. The URL comes from
    // wp_get_attachment_url(), never the image-size pipeline. jw_picture()
    // then renders a plain <img> inside <picture> and sizes it from the file
    // ('svg' => true is the flag it reads); roci_hero_preload() falls through
    // to a plain href preload of the same URL. Raster images never reach this
    // branch, so their sources are unchanged.
    if ( roci_is_svg_attachment( $attachment_id ) ) {
        $img_src = wp_get_attachment_url( $attachment_id );

        if ( ! $img_src ) {
            return [];
        }

        return [
            'img_src'     => $img_src,
            'webp_src'    => null,
            'loading'     => in_array( $loading, [ 'lazy', 'eager' ], true ) ? $loading : 'lazy',
            'srcset'      => false,
            'sizes'       => false,
            'webp_srcset' => '',
            'svg'         => true,
        ];
    }

    $img_src = wp_get_attachment_image_url( $attachment_id, $size );

    if ( ! $img_src ) {
        return [];
    }

    $webp_src = jw_get_webp_url( $attachment_id, $size );
    $loading  = in_array( $loading, [ 'lazy', 'eager' ], true ) ? $loading : 'lazy';

    // RESPONSIVE CANDIDATES, from the sizes WordPress generated. Eager images
    // are heroes that span the viewport, so they declare 100vw; lazy images
    // take WordPress's own sizes value for the requested size. src, width and
    // height are untouched, so a browser that ignores srcset behaves as before.
    // Both helpers return false when there is nothing to offer (a single size,
    // an SVG), and then no srcset or sizes attribute is emitted at all.
    //
    // A CALLER-SUPPLIED $sizes WINS (v1.8.0), because only the template knows
    // the grid the image sits in. null or an empty string falls through to the
    // computed default, which is exactly the pre-1.8.0 value.
    $srcset = wp_get_attachment_image_srcset( $attachment_id, $size );
    $sizes  = ( is_string( $sizes ) && '' !== trim( $sizes ) )
        ? trim( $sizes )
        : ( ( 'eager' === $loading ) ? '100vw' : wp_get_attachment_image_sizes( $attachment_id, $size ) );

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

            // Unchanged means the candidate was not jpg/png/gif. Keep it only if
            // it is really .webp, as jw_get_webp_url() does (v1.15.0) — an
            // unchanged AVIF candidate is not WebP.
            if ( $candidate_webp === $candidate_url && ! preg_match( '/\.webp$/i', $candidate_url ) ) {
                continue;
            }

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

    return [
        'img_src'     => $img_src,
        'webp_src'    => $webp_src,
        'loading'     => $loading,
        'srcset'      => $srcset,
        'sizes'       => $sizes,
        'webp_srcset' => $webp_srcset,
    ];
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
 *                              media library alt of the mobile attachment, then (if
 *                              that is empty) of the desktop attachment (v1.11.0).
 *                              Pass '' for decorative images (intentional empty alt).
 *                              Missing on both crops renders alt="" and is flagged
 *                              in a WP_DEBUG-only comment, as in jw_picture().
 * @param  string $class       CSS class on the <img> tag. Default: ''.
 * @param  string $loading     'lazy' or 'eager'. Default: 'eager' (heroes are LCP).
 *                             'eager' adds fetchpriority="high" + data-no-lazy="1",
 *                             exactly as jw_picture() does.
 * @return string              HTML output.
 */
function jw_hero_picture( $desktop_id, $mobile_id, $alt = null, $class = '', $loading = 'eager' ) {

    // ART-DIRECTED SVG IS DECLINED (v1.14.0). A vector rarely needs a separate
    // mobile crop, and jw_picture() renders an SVG hero correctly. (Declined
    // when the desktop <source>'s type="image/webp" was still a literal; it is
    // conditional since v1.15.0, but SVG crops stay out of scope here.)
    // jw_hero_picture_sources() returns [] for an SVG crop too, so the preload
    // agrees. '' in production; a pointer under WP_DEBUG.
    if ( roci_is_svg_attachment( $desktop_id ) || roci_is_svg_attachment( $mobile_id ) ) {
        return jw_debug_comment( 'jw_hero_picture: SVG crops are not supported — render the SVG with jw_picture().' );
    }

    // Both crops' URLs, srcsets and sizes come from jw_hero_picture_sources()
    // — the SAME function roci_hero_preload() uses (v1.9.0), so the preload can
    // never disagree with this markup. Nothing below changes a byte of output.
    $sources = jw_hero_picture_sources( $desktop_id, $mobile_id, $loading );

    if ( ! $sources ) {
        return '';
    }

    $desktop_src = $sources['desktop_src'];
    $mobile_src  = $sources['mobile_src'];

    // Dimensions from mobile attachment — it's the <img> default source.
    $metadata = wp_get_attachment_metadata( $mobile_id );
    $width    = isset( $metadata['width'] )  ? (int) $metadata['width']  : '';
    $height   = isset( $metadata['height'] ) ? (int) $metadata['height'] : '';

    // null means alt was not passed — fall back to the mobile attachment's media
    // library value, then to the desktop crop's: both crops are the same image,
    // so an alt set on either one describes it. Missing on both is flagged below.
    $roci_alt_missing = false;
    if ( null === $alt ) {
        $alt = trim( (string) get_post_meta( $mobile_id, '_wp_attachment_image_alt', true ) );
        if ( '' === $alt && $desktop_id ) {
            $alt = trim( (string) get_post_meta( $desktop_id, '_wp_attachment_image_alt', true ) );
        }
        $roci_alt_missing = ( '' === $alt );
    }
    $alt     = esc_attr( $alt );
    $class   = $class ? ' class="' . esc_attr( $class ) . '"' : '';
    $loading = $sources['loading'];
    $dims    = ( $width && $height ) ? ' width="' . $width . '" height="' . $height . '"' : '';

    // Same rule as jw_picture(): eager gains fetchpriority + data-no-lazy,
    // lazy stays byte-identical. Rides on the $dims echo for the reason given
    // in jw_picture().
    $priority = ( 'eager' === $loading ) ? ' fetchpriority="high" data-no-lazy="1"' : '';

    // Attribute strings, built from the shared sources exactly as before —
    // see jw_hero_picture_sources() for how each crop's srcset, sizes and the
    // desktop WebP list are resolved.
    $sizes_attr = $sources['sizes'] ? ' sizes="' . esc_attr( $sources['sizes'] ) . '"' : '';
    $responsive = $sources['srcset'] ? ' srcset="' . esc_attr( $sources['srcset'] ) . '"' . $sizes_attr : '';

    // THE SRCSET LINE ENDS IN A CLOSING TAG, SO IT ECHOES ITS OWN NEWLINE
    // (v1.15.0). PHP swallows the newline right after a closing tag (the trap
    // documented in jw_picture()). The line used to end in a LITERAL
    // type="image/webp" to dodge that; the type is now conditional, so the
    // echo carries the "\n" itself. Output for a WebP desktop crop is
    // byte-identical to before: srcset, sizes, ' type="image/webp"', newline.
    // A desktop crop with no WebP (a JPEG/PNG/AVIF fallback URL) drops the
    // false type.
    $desktop_srcset_attr = $sources['desktop_webp_srcset'] ? esc_attr( $sources['desktop_webp_srcset'] ) : esc_url( $desktop_src );
    $desktop_sizes_attr  = ( $sources['desktop_webp_srcset'] && $sources['desktop_sizes'] ) ? ' sizes="' . esc_attr( $sources['desktop_sizes'] ) . '"' : '';
    $desktop_type_attr   = ! empty( $sources['desktop_is_webp'] ) ? ' type="image/webp"' : '';

    ob_start();
    ?>
    <picture>
        <!-- Desktop crop: activates at 768px and above -->
        <source
            media="(min-width: 768px)"
            srcset="<?php echo $desktop_srcset_attr; ?>"<?php echo $desktop_sizes_attr . $desktop_type_attr . "\n"; ?>
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
    // Echoed inside the existing trailing PHP block, as in jw_picture(), so
    // output is byte-identical with WP_DEBUG off.
    if ( $roci_alt_missing ) {
        echo jw_debug_comment( sprintf( "jw_hero_picture: attachments %d (mobile) and %d (desktop) have no alt text. Set it in the Media Library, or pass '' if the image is decorative.", (int) $mobile_id, (int) $desktop_id ) );
    }
    return ob_get_clean();
}


/**
 * jw_hero_picture_sources()
 *
 * jw_hero_picture()'s counterpart to jw_picture_sources(): resolves both
 * crops' URLs, the mobile <img>'s srcset + sizes, and the desktop <source>'s
 * WebP candidate list + sizes, without building markup. jw_hero_picture() and
 * roci_hero_preload() both call it, so a preload can never disagree with the
 * art-directed <picture> it preloads (v1.9.0).
 *
 * The body is moved verbatim from jw_hero_picture() 1.8.0, so that function's
 * output is unchanged.
 *
 * @param  int    $desktop_id  Attachment ID of the desktop crop.
 * @param  int    $mobile_id   Attachment ID of the mobile crop.
 * @param  string $loading     'lazy' or 'eager' (anything else -> 'eager').
 * @return array  Empty when either crop cannot be resolved; otherwise the keys
 *                desktop_src, mobile_src, loading, srcset (mobile, string|false),
 *                sizes (mobile, string|false), desktop_webp_srcset ('' when there
 *                is no list), desktop_sizes (string|false).
 */
function jw_hero_picture_sources( $desktop_id, $mobile_id, $loading = 'eager' ) {

    if ( ! $desktop_id || ! $mobile_id ) {
        return [];
    }

    // SVG crops are declined (v1.14.0) — see jw_hero_picture(). Empty sources
    // also make roci_hero_preload()'s art-directed branch emit nothing.
    if ( roci_is_svg_attachment( $desktop_id ) || roci_is_svg_attachment( $mobile_id ) ) {
        return [];
    }

    // Prefer WebP, fall back to standard URL for each crop. $desktop_webp is
    // kept separately because the desktop srcset below is only built when
    // the full-size .webp exists.
    $desktop_webp = jw_get_webp_url( $desktop_id, 'full' );
    $desktop_src  = $desktop_webp ?: wp_get_attachment_image_url( $desktop_id, 'full' );
    $mobile_src   = jw_get_webp_url( $mobile_id, 'full' )  ?: wp_get_attachment_image_url( $mobile_id, 'full' );

    if ( ! $desktop_src || ! $mobile_src ) {
        return [];
    }

    $loading = in_array( $loading, [ 'lazy', 'eager' ], true ) ? $loading : 'eager';

    // RESPONSIVE CANDIDATES — same rules as jw_picture_sources(), per crop.
    // The <img> is the MOBILE crop, so its srcset and sizes come from the
    // mobile attachment. Eager declares 100vw; lazy takes WordPress's sizes.
    $srcset = wp_get_attachment_image_srcset( $mobile_id, 'full' );
    $sizes  = ( 'eager' === $loading ) ? '100vw' : wp_get_attachment_image_sizes( $mobile_id, 'full' );

    // The desktop <source> offers the DESKTOP attachment's candidates, kept
    // only where the .webp sibling exists (same test as jw_picture_sources()).
    // Built only when the full-size desktop .webp exists, so the list always
    // holds the full size; otherwise the <source> keeps a single URL, WebP or
    // not — and since v1.15.0 it is typed image/webp only when that URL really
    // is WebP ('desktop_is_webp' below).
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

            // Same rule as jw_picture_sources() (v1.15.0): an unchanged
            // candidate is kept only if it is really .webp.
            if ( $candidate_webp === $candidate_url && ! preg_match( '/\.webp$/i', $candidate_url ) ) {
                continue;
            }

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

    return [
        'desktop_src'         => $desktop_src,
        'mobile_src'          => $mobile_src,
        'loading'             => $loading,
        'srcset'              => $srcset,
        'sizes'               => $sizes,
        'desktop_webp_srcset' => $desktop_webp_srcset,
        'desktop_sizes'       => $desktop_sizes,
        // v1.15.0: whether the desktop <source> really offers WebP — the
        // full-size .webp exists (or the original is .webp). It gates the
        // <source>'s type attribute and the art-directed preload's type, so a
        // JPEG/PNG/AVIF fallback URL is never labelled image/webp.
        'desktop_is_webp'     => (bool) $desktop_webp,
    ];
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
// DEBUG HELPERS
// ============================================================

/**
 * jw_debug_comment()
 *
 * Returns an HTML comment carrying $message when WP_DEBUG is on, '' otherwise.
 * Invisible on the page and absent in production — the parent's way of
 * flagging a CONTENT problem (missing alt text, a nameless frame) to whoever
 * is viewing source on staging, without a PHP notice that could print into
 * the layout under WP_DEBUG_DISPLAY.
 *
 * Callers echo it from the PHP block that already follows their markup, so
 * with WP_DEBUG off their output is byte-identical to before.
 *
 * Usage:
 *   echo jw_debug_comment( 'jw_picture: attachment 123 has no alt text.' );
 *
 * @param  string $message Plain-text message.
 * @return string          '<!-- message -->' under WP_DEBUG, else ''.
 */
function jw_debug_comment( $message ) {

    if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
        return '';
    }

    // A double hyphen cannot appear inside an HTML comment.
    return '<!-- ' . esc_html( str_replace( '--', '—', (string) $message ) ) . ' -->';
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
 * THE IFRAME IS ALWAYS NAMED (v1.11.0, WCAG 4.1.2). Pass $title — a short
 * description of THE VIDEO ("Sunset cruise highlights"), not of the page. If
 * it is omitted the frame falls back to "Embedded video" ONLY so it is never
 * nameless; that fallback is a weak name, and with WP_DEBUG on an HTML comment
 * after the embed says so.
 *
 * Usage:
 *   echo jw_bunny_video( '123456', 'abc123-def456', 0, '', 'Sunset cruise highlights' );
 *   echo jw_bunny_video( '123456', 'abc123-def456', $poster_id, 'venue__video', 'Venue walkthrough' );
 *
 * @param  string $library_id  Bunny Stream Library ID.
 * @param  string $video_id    Bunny Stream Video ID.
 * @param  int    $poster_id   Optional. WordPress attachment ID for poster image. Default: 0.
 * @param  string $class       CSS class on the wrapper <div>. Default: 'jw-video-embed'.
 * @param  string $title       The iframe's accessible name — describe the video.
 *                             Default '' falls back to "Embedded video" (flagged
 *                             under WP_DEBUG).
 * @return string              HTML output.
 */
function jw_bunny_video( $library_id, $video_id, $poster_id = 0, $class = '', $title = '' ) {

    if ( ! $library_id || ! $video_id ) {
        return '';
    }

    $embed_url = 'https://iframe.mediadelivery.net/embed/' . esc_attr( $library_id ) . '/' . esc_attr( $video_id );

    // Append poster image URL if a WordPress attachment ID was provided.
    if ( $poster_id ) {
        $poster_url = wp_get_attachment_image_url( $poster_id, 'full' );

        // SVG FALLBACK (v1.15.0, defensive) — the loader's two-step: where core
        // returns false for an SVG, the bare attachment URL. Runs only when the
        // first call fails, so every resolvable poster is unchanged.
        if ( ! $poster_url ) {
            $poster_url = wp_get_attachment_url( $poster_id );
        }

        if ( $poster_url ) {
            $embed_url .= '?poster=' . urlencode( $poster_url );
        }
    }

    $wrapper_class = $class ? esc_attr( $class ) : 'jw-video-embed';

    // Never a nameless frame: an empty $title falls back to a generic name and
    // is flagged under WP_DEBUG so the caller passes a real one.
    $title             = trim( (string) $title );
    $roci_missing_name = ( '' === $title );
    if ( $roci_missing_name ) {
        $title = __( 'Embedded video', 'rocinante' );
    }

    ob_start();
    ?>
    <div class="<?php echo $wrapper_class; ?>">
        <iframe
            src="<?php echo esc_url( $embed_url ); ?>"
            title="<?php echo esc_attr( $title ); ?>"
            loading="lazy"
            allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
            allowfullscreen
        ></iframe>
    </div>
    <?php
    if ( $roci_missing_name ) {
        echo jw_debug_comment( 'jw_bunny_video: no $title passed, so the frame is named "Embedded video". Pass a title that describes the video.' );
    }
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

    // Through the parent's wrapper (v1.15.1), not rwmb_meta() directly: same
    // field, same empty args, same post ID, so the value is identical while
    // Meta Box is active — and '' (so nothing is emitted) instead of a fatal
    // when it is not.
    $items = roci_get_field( 'jw_faq_items', $post_id );

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
 *   - Its host is wa.me or a subdomain of it (WhatsApp short links), with or
 *     without a scheme — matched as a HOST since v1.13.0, not a substring
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

    // The external test lives in roci_is_external_link() (v1.13.0), shared with
    // jw_new_tab_note() and jw_wysiwyg_body() so the three can never disagree.
    return roci_is_external_link( $url ) ? ' target="_blank" rel="noopener noreferrer"' : '';
}


/**
 * roci_is_external_link()
 *
 * Internal. THE one external-link test behind jw_link_atts(), jw_new_tab_note()
 * and jw_wysiwyg_body() — a link either opens a new tab everywhere or nowhere.
 * True when:
 *   - the URL's HOST is wa.me or a subdomain of it (WhatsApp short links),
 *     with or without a scheme — "https://wa.me/…", "//wa.me/…", "wa.me/…";
 *   - it is an http(s) URL whose host differs from home_url()'s host.
 * False for everything else: mailto:, tel:, relative, fragment and same-host
 * URLs, and protocol-relative URLs to any host but wa.me.
 *
 * ⚠ wa.me IS MATCHED AS A HOST, NOT A SUBSTRING (v1.13.0). jw_link_atts() used
 *   to test strpos( $url, 'wa.me' ), so any URL merely CONTAINING the letters —
 *   a same-host /kiwa.menu/ path, a mailto: address — opened in a new tab. Real
 *   WhatsApp links are unaffected: https://wa.me/… is cross-host either way.
 *
 * @param  string $url The href value to evaluate.
 * @return bool        True if the link opens a new tab.
 */
function roci_is_external_link( $url ) {

    if ( ! $url || ! is_string( $url ) ) {
        return false;
    }

    $is_http = (bool) preg_match( '#^https?://#i', $url );

    // The would-be host, for the wa.me test: from an http(s) or protocol-
    // relative URL; or, for a scheme-less "wa.me/123", its first segment. A
    // URL with any other scheme (mailto:, tel:) or a relative one has none.
    $host = '';

    if ( $is_http || 0 === strpos( $url, '//' ) ) {
        $host = (string) wp_parse_url( ( 0 === strpos( $url, '//' ) ? 'https:' : '' ) . $url, PHP_URL_HOST );
    } elseif ( ! preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $url ) && ! preg_match( '#^[/?\#.]#', $url ) ) {
        $segments = preg_split( '#[/?\#]#', $url, 2 );
        $host     = (string) $segments[0];
    }

    $host = strtolower( $host );

    if ( 'wa.me' === $host || '.wa.me' === substr( $host, -6 ) ) {
        return true;
    }

    // Cross-host http(s) — compared exactly as jw_link_atts() always did.
    if ( $is_http ) {
        $link_host = wp_parse_url( $url, PHP_URL_HOST );
        $home_host = wp_parse_url( home_url(), PHP_URL_HOST );

        return ( $link_host && $home_host && $link_host !== $home_host );
    }

    return false;
}


/**
 * roci_is_svg_attachment()
 *
 * Internal (v1.14.0). True when the attachment's stored mime type is
 * image/svg+xml — what inc/media/svg-support.php assigns on upload. The one SVG
 * test behind jw_get_webp_url(), jw_picture_sources() and the
 * jw_hero_picture() pair, so the image path detects SVG BEFORE it touches the
 * image-size pipeline.
 *
 * Mime, not file extension: it is what the upload filter sets and what core
 * keys on. .svgz is included deliberately — browsers render it from a URL;
 * only jw_logo_dimensions() declines it (no size, so the tag is unsized).
 *
 * @param  int  $attachment_id Attachment ID.
 * @return bool                True for an SVG attachment.
 */
function roci_is_svg_attachment( $attachment_id ) {
    return 'image/svg+xml' === get_post_mime_type( (int) $attachment_id );
}


/**
 * jw_new_tab_note()
 *
 * Returns a visually-hidden "(opens in a new tab)" for a link that opens a new
 * tab (WCAG 3.2.5 AAA, technique G201), or '' for one that does not. Uses the
 * same test as jw_link_atts(), so the note appears on exactly the links that
 * carry target="_blank".
 *
 * Echo it INSIDE the link, right before </a>, so it becomes part of the link's
 * accessible name:
 *
 *   <a href="<?php echo esc_url( $url ); ?>"<?php echo jw_link_atts( $url ); ?>>Label<?php echo jw_new_tab_note( $url ); ?></a>
 *
 * Visually nothing changes. A VISIBLE indicator (an icon) is optional per-site
 * design; if a site adds one, keep this note — an icon alone is not announced.
 *
 * @param  string $url The same href passed to jw_link_atts().
 * @return string      '<span class="screen-reader-text"> (opens in a new tab)</span>' or ''.
 */
function jw_new_tab_note( $url ) {

    if ( ! roci_is_external_link( $url ) ) {
        return '';
    }

    return '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'rocinante' ) . '</span>';
}


/**
 * roci_current_atts()
 *
 * The companion to jw_link_atts() for CURRENT-PAGE state. Returns
 * ' aria-current="page"' when $url is the page being viewed, '' otherwise —
 * with a leading space so it drops inline into an <a> tag, exactly like
 * jw_link_atts(). The two never both emit for one URL (this one answers only
 * for same-host links, jw_link_atts() only for external ones), so they can be
 * concatenated on the same anchor:
 *
 *   <a href="<?php echo esc_url( $url ); ?>"<?php echo jw_link_atts( $url ) . roci_current_atts( $url ); ?>>
 *
 * WHAT "CURRENT" MEANS. Resolved once per request from WordPress's query, not
 * the raw request URI: the front page, the Posts page, any singular, a CPT
 * archive, a term archive or an author archive. Search, 404 and date archives
 * have no current page. A paged archive (/page/2/) is still its archive.
 * Only the PATH is compared — scheme ignored, trailing slash normalised,
 * percent-encoding decoded. A link to another host, a non-root-relative or
 * opaque URL (mailto:, tel:, #top), or a link with a query string or fragment
 * is never current.
 *
 * SECTION MATCH (opt-in). Pass $scope = 'section' on a link that stands for a
 * whole section — a disclosure trigger such as "Charters" — and it also
 * returns ' aria-current="true"' (the spec's generic "current item in a set")
 * when the current page sits BELOW that link's path. An exact match still
 * returns "page". The home link never section-matches. It is opt-in because
 * it assumes a section's pages live under its landing page's path; where they
 * don't, it simply never matches.
 *
 * STYLE THE STATE FROM THE ATTRIBUTE. The parent ships no nav CSS. A child that
 * shows the current link visually must style it from [aria-current], never a
 * parallel class — otherwise the visual state and the announced state can
 * drift apart (WCAG 1.3.1).
 *
 * @param  string $url   The link's href — the same value passed to esc_url().
 * @param  string $scope 'page' (default, exact match only) or 'section'.
 * @return string        ' aria-current="page"', ' aria-current="true"', or ''.
 */
function roci_current_atts( $url, $scope = 'page' ) {

    $current = roci_current_path();

    if ( null === $current || ! is_string( $url ) || '' === trim( $url ) ) {
        return '';
    }

    $parts = wp_parse_url( trim( $url ) );

    // Unparseable, or a query string / fragment: not "this page".
    if ( ! is_array( $parts ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
        return '';
    }

    if ( isset( $parts['host'] ) ) {
        $home_host = wp_parse_url( home_url(), PHP_URL_HOST );

        if ( ! $home_host || strtolower( $parts['host'] ) !== strtolower( $home_host ) ) {
            return '';
        }
    } elseif ( ! isset( $parts['path'] ) || '/' !== substr( $parts['path'], 0, 1 ) ) {
        // Relative or opaque (mailto:, tel:, "contact/") — never current.
        return '';
    }

    $path = roci_normalize_url_path( isset( $parts['path'] ) ? $parts['path'] : '/' );

    if ( $path === $current ) {
        return ' aria-current="page"';
    }

    if ( 'section' === $scope && '/' !== $path && 0 === strpos( $current, $path ) ) {
        return ' aria-current="true"';
    }

    return '';
}


/**
 * roci_current_path()
 *
 * Internal. The normalised path of the page being viewed, or null when the
 * request has no "current page" (search, 404, date archives) or the main
 * query has not run yet. Resolved once per request; a resolved null is cached
 * too, but a call made before the 'wp' action is not, so a later call still
 * resolves.
 *
 * @return string|null '/path/' (root: '/'), or null.
 */
function roci_current_path() {
    static $resolved = false;
    static $path     = null;

    if ( $resolved ) {
        return $path;
    }

    if ( ! did_action( 'wp' ) ) {
        return null;
    }

    $url = null;

    if ( is_404() || is_search() ) {
        $url = null;
    } elseif ( is_front_page() ) {
        $url = home_url( '/' );
    } elseif ( is_home() ) {
        $posts_page = (int) get_option( 'page_for_posts' );
        $url        = $posts_page ? get_permalink( $posts_page ) : home_url( '/' );
    } elseif ( is_singular() ) {
        $url = get_permalink( get_queried_object_id() );
    } elseif ( is_post_type_archive() ) {
        $object = get_queried_object();
        $url    = ( $object && isset( $object->name ) ) ? get_post_type_archive_link( $object->name ) : null;
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $link = get_term_link( get_queried_object() );
        $url  = is_wp_error( $link ) ? null : $link;
    } elseif ( is_author() ) {
        $url = get_author_posts_url( get_queried_object_id() );
    }

    $parsed   = $url ? wp_parse_url( $url ) : null;
    $path     = is_array( $parsed ) ? roci_normalize_url_path( isset( $parsed['path'] ) ? $parsed['path'] : '/' ) : null;
    $resolved = true;

    return $path;
}


/**
 * roci_normalize_url_path()
 *
 * Internal. '/Fishing-Charters//boomerang' -> '/Fishing-Charters/boomerang/';
 * '' or '/' -> '/'. Percent-encoding is decoded so a non-ASCII slug compares
 * equal whichever form either side used. Case is preserved — WordPress
 * lowercases slugs itself, and folding case here could invent a match.
 *
 * @param  string $path A URL path.
 * @return string       Normalised path, always with leading and trailing slash.
 */
function roci_normalize_url_path( $path ) {
    $path = trim( rawurldecode( (string) $path ), '/' );
    $path = preg_replace( '#/+#', '/', $path );

    return '' === $path ? '/' : '/' . $path . '/';
}


// ============================================================
// INTERNAL PAGE LINKS
// ============================================================

/**
 * roci_get_page_url()
 *
 * The URL of one of this site's own pages, resolved TEMPLATE FIRST, SLUG
 * SECOND (CONVENTIONS: "Internal page links — resolve, don't hardcode"). For
 * nav, footer and CTA links, in place of a literal home_url( '/about/' ):
 *
 *   roci_get_page_url( 'pages/page-contact.php', 'contact' )
 *
 * 1. The newest PUBLISHED page whose _wp_page_template is $template. The
 *    template assignment is what the site keys on, so this survives a slug
 *    change made in wp-admin for SEO reasons.
 * 2. Otherwise the page at $slug (a full path for a child page, e.g.
 *    'about/team'), and only if it is PUBLISHED. A draft, private
 *    or trashed page is never linked.
 * 3. Otherwise ''. Never a guessed URL: the caller skips the link rather than
 *    print one that 404s.
 *
 * An empty $template skips step 1. Without that guard the meta query would
 * drop its value test and match ANY page that has a template assigned.
 *
 * Cached per request, keyed on both arguments, so a nav and a footer resolving
 * the same page cost one query. A miss is cached too.
 *
 * Pair it with roci_current_atts() and jw_link_atts() on the anchor, and skip
 * the link when it returns ''. Only the home link is home_url( '/' ).
 *
 * @param  string $template Template path relative to the theme root, e.g. 'pages/page-about.php'.
 * @param  string $slug     Fallback page path, e.g. 'about'. Optional.
 * @return string           Permalink, or '' when neither lookup finds a published page.
 */
function roci_get_page_url( $template, $slug = '' ) {
    static $cache = array();

    $template = (string) $template;
    $slug     = (string) $slug;
    $key      = $template . '|' . $slug;

    if ( isset( $cache[ $key ] ) ) {
        return $cache[ $key ];
    }

    $url = '';

    if ( '' !== $template ) {
        $ids = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'meta_key'       => '_wp_page_template',
            'meta_value'     => $template,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );

        if ( $ids ) {
            $url = (string) get_permalink( $ids[0] );
        }
    }

    if ( '' === $url && '' !== $slug ) {
        $page = get_page_by_path( $slug );

        if ( $page && 'publish' === $page->post_status ) {
            $url = (string) get_permalink( $page );
        }
    }

    $cache[ $key ] = $url;

    return $url;
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
 * (A TEXTAREA field is the opposite case and does need it: a child rendering
 * a textarea as paragraphs pairs it as wpautop( esc_html() ), and both halves
 * are load-bearing.)
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
 * EVERY ANCHOR IT TARGETS ALSO ANNOUNCES THE NEW TAB (v1.13.0, WCAG 3.2.5): the
 * jw_new_tab_note() span is appended inside the link, right before its </a>,
 * unless the anchor already contains that text. Anchors it leaves alone (an
 * existing target, same-host, non-http) get no note. The external test is
 * roci_is_external_link(), shared with jw_link_atts() and jw_new_tab_note().
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

    // Matches an opening <a> tag and, when the anchor is closed, its content and
    // its </a> — anchors do not nest, and the content may not contain another
    // opening <a>, so a match never spans two anchors. An UNCLOSED anchor still
    // matches on its opening tag alone and is rewritten exactly as before, just
    // without the note (there is no </a> to put it in front of).
    return preg_replace_callback(
        '#(<a\s[^>]*>)(?:((?:(?!<a\s).)*?)(</a>))?#is',
        function ( $matches ) {

            $tag   = $matches[1];
            $inner = isset( $matches[2] ) ? $matches[2] : '';
            $close = isset( $matches[3] ) ? $matches[3] : '';

            // Author already chose a target — leave the anchor alone.
            if ( preg_match( '#\starget\s*=#i', $tag ) ) {
                return $matches[0];
            }

            if ( ! preg_match( '#\shref\s*=\s*("|\')(.*?)\1#i', $tag, $href ) ) {
                return $matches[0];
            }

            // The stored href is entity-encoded (&amp; in query strings);
            // decode before parsing so the host reads correctly.
            $url = trim( html_entity_decode( $href[2], ENT_QUOTES, 'UTF-8' ) );

            // Only http(s) links are ever rewritten here; then the shared test
            // decides — the same one jw_link_atts() and jw_new_tab_note() use.
            if ( ! preg_match( '#^https?://#i', $url ) || ! roci_is_external_link( $url ) ) {
                return $matches[0];
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
            $tag = preg_replace( '#\s*(/?)>$#', $inject . '$1>', $tag, 1 );

            // Announce the new tab inside the link (WCAG 3.2.5), right before
            // </a> — unless the anchor already carries the note, so a field run
            // through this twice never doubles it.
            if ( '' !== $close && false === stripos( $inner, __( '(opens in a new tab)', 'rocinante' ) ) ) {
                $inner .= jw_new_tab_note( $url );
            }

            return $tag . $inner . $close;
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


// ============================================================
// ENTRY TITLE — parent content templates and the listing header
// ============================================================

/**
 * roci_entry_title_mode()
 *
 * How the parent's templates render the page's <h1>: 'visible' (default),
 * 'hidden' (visually hidden — .screen-reader-text, still in the heading
 * outline) or 'none' (the child renders its own <h1>). Filterable via
 * roci_entry_title_mode; an unknown value falls back to 'visible' so a typo
 * fails toward a correct outline, not away from it.
 *
 * Callers: page.php and single.php ('page', 'single', the entry <h1>), and
 * template-parts/listing-header.php (v1.16.0) for the listing <h1> of
 * 'index', 'archive', 'search' and '404'.
 *
 * Not memoized — the filter runs on every call, so per-post logic works.
 *
 * @param  string $template 'page', 'single', 'index', 'archive', 'search' or '404'.
 * @param  int    $post_id  The post whose title is rendered: the singular
 *                          post, the Posts page ID on the posts index, or 0
 *                          (latest-posts-on-front, archive, search, 404).
 * @return string           'visible', 'hidden' or 'none'.
 */
function roci_entry_title_mode( $template, $post_id = 0 ) {
    $mode = apply_filters(
        'roci_entry_title_mode',
        'visible',
        array(
            'template' => $template,
            'post_id'  => (int) $post_id,
        )
    );

    return in_array( $mode, array( 'visible', 'hidden', 'none' ), true ) ? $mode : 'visible';
}


// ============================================================
// LISTING TEMPLATES — class and pagination seams (v1.16.0; S1/S3/S4 v1.17.0)
// ============================================================

/**
 * roci_listing_context()
 *
 * (internal) Normalises a listing context passed to a template part, so an
 * unknown or missing value falls back to 'index' rather than emitting a class
 * or a heading built from arbitrary input.
 *
 * @param  mixed $context The context from the part's $args.
 * @return string         'index', 'archive', 'search' or '404'.
 */
function roci_listing_context( $context ) {
    return in_array( $context, array( 'index', 'archive', 'search', '404' ), true ) ? $context : 'index';
}

/**
 * roci_get_listing_classes()
 *
 * Classes for a listing template's containers. Filterable via
 * roci_listing_classes( $classes, $context ):
 *   'wrapper'     — the element inside <main> that holds the whole listing.
 *   'results'     — the element that holds the loop items (on 404, the empty
 *                   state).
 *   'inner'       — (v1.17.0) [] by default. When non-empty, a <div> printed
 *                   directly inside the wrapper, around the header, search form,
 *                   results, pagination, empty state and roci_after_loop.
 *   'pagination'  — (v1.17.0) [] by default. When non-empty, a <div> wrapped
 *                   around the_posts_pagination(). Never a <nav>: core prints
 *                   that already.
 *   'results_tag' — (v1.17.0) 'div' by default; 'ul' or 'ol' make the results
 *                   container a list with role="list", and the item part MUST
 *                   then emit <li>. Ignored on 404, whose results container
 *                   holds the empty state, not items — always a <div> there.
 *
 * The defaults keep every class these templates carried before v7.1.0, so
 * existing child CSS still matches, and the three v7.2.0 slots default to
 * "print nothing extra", so the markup is byte-identical when unfiltered.
 * '404' cannot be a class prefix that starts a CSS selector cleanly, so that
 * context's names use 'error-404'.
 *
 * A filter that drops a key gets the default back for that key; every class
 * is passed through sanitize_html_class() (roci_resolve_class_slots()), and
 * results_tag is whitelisted.
 *
 * @param  string $context 'index', 'archive', 'search' or '404'.
 * @return array           'wrapper', 'results', 'inner', 'pagination' => string[];
 *                         'results_tag' => 'div' | 'ul' | 'ol'.
 */
function roci_get_listing_classes( $context ) {
    $context = roci_listing_context( $context );

    $defaults = array(
        'index'   => array(
            'wrapper' => array( 'u-container', 'index-listing' ),
            'results' => array( 'index-results' ),
        ),
        'archive' => array(
            'wrapper' => array( 'u-container', 'archive-listing' ),
            'results' => array( 'archive-posts', 'archive-results' ),
        ),
        'search'  => array(
            'wrapper' => array( 'u-container', 'search-listing' ),
            'results' => array( 'search-results' ),
        ),
        '404'     => array(
            'wrapper' => array( 'u-container', 'error-404-listing' ),
            'results' => array( 'error-404', 'error-404-results' ),
        ),
    );

    $slots = $defaults[ $context ] + array(
        'inner'      => array(),
        'pagination' => array(),
    );

    $filtered = apply_filters( 'roci_listing_classes', $slots + array( 'results_tag' => 'div' ), $context );

    $classes = roci_resolve_class_slots( $filtered, $slots );

    $tag = ( is_array( $filtered ) && isset( $filtered['results_tag'] ) ) ? $filtered['results_tag'] : 'div';

    $classes['results_tag'] = ( '404' !== $context && in_array( $tag, array( 'ul', 'ol' ), true ) ) ? $tag : 'div';

    return $classes;
}

/**
 * roci_listing_results_atts()
 *
 * (internal) The attributes of a listing's results container: its class
 * attribute, plus role="list" when results_tag is a list (the parent's global
 * list-style: none would otherwise strip list semantics in WebKit). Printed
 * between the tag name and the closing ">", so a <div> default is unchanged.
 *
 * @param  array $classes roci_get_listing_classes() output.
 * @return string         Leading-space attribute string.
 */
function roci_listing_results_atts( $classes ) {
    $atts = ' class="' . roci_class_attr( $classes['results'] ) . '"';

    return 'div' === $classes['results_tag'] ? $atts : $atts . ' role="list"';
}

/**
 * roci_listing_inner_open() / roci_listing_inner_close()
 *
 * (internal) Print the optional 'inner' <div> (roci_listing_classes), or
 * nothing at all when the slot is empty — so an unfiltered listing's markup
 * is byte-identical to v7.1.0.
 *
 * @param array $classes roci_get_listing_classes() output.
 */
function roci_listing_inner_open( $classes ) {
    if ( $classes['inner'] ) {
        echo '<div class="' . roci_class_attr( $classes['inner'] ) . '">';
    }
}

function roci_listing_inner_close( $classes ) {
    if ( $classes['inner'] ) {
        echo '</div>';
    }
}

/**
 * roci_listing_pagination()
 *
 * (internal) the_posts_pagination() with roci_pagination_args( $context ),
 * inside the optional 'pagination' <div> (roci_listing_classes). Nothing is
 * added around core's <nav> when the slot is empty.
 *
 * @param string $context 'index', 'archive' or 'search'.
 * @param array  $classes roci_get_listing_classes() output.
 */
function roci_listing_pagination( $context, $classes ) {
    if ( $classes['pagination'] ) {
        echo '<div class="' . roci_class_attr( $classes['pagination'] ) . '">';
    }

    the_posts_pagination( roci_get_pagination_args( $context ) );

    if ( $classes['pagination'] ) {
        echo '</div>';
    }
}

/**
 * roci_get_listing_header_classes()
 *
 * (v1.17.0) Classes for template-parts/listing-header.php. Filterable via
 * roci_listing_header_classes( $classes, $context ):
 *   'header'      — the <header> element: '{context}-header' ('archive-header'
 *                   for index and archive, 'search-header', 'error-404-header').
 *                   An EXPLICIT [] omits the <header> element entirely — the
 *                   eyebrow action, the <h1> and the description then print
 *                   directly in their container.
 *   'title'       — the <h1>: array( 'entry-title' ). roci_entry_title_mode()
 *                   still adds .screen-reader-text for 'hidden'.
 *   'description' — the archive description: array( 'archive-description' ).
 *
 * A missing key falls back to its default; every class goes through
 * sanitize_html_class() (roci_resolve_class_slots()).
 *
 * @param  string $context 'index', 'archive', 'search' or '404'.
 * @return array           'header', 'title', 'description' => string[].
 */
function roci_get_listing_header_classes( $context ) {
    $context = roci_listing_context( $context );

    $header = array(
        'index'   => 'archive-header',
        'archive' => 'archive-header',
        'search'  => 'search-header',
        '404'     => 'error-404-header',
    );

    $defaults = array(
        'header'      => array( $header[ $context ] ),
        'title'       => array( 'entry-title' ),
        'description' => array( 'archive-description' ),
    );

    return roci_resolve_class_slots(
        apply_filters( 'roci_listing_header_classes', $defaults, $context ),
        $defaults
    );
}

/**
 * roci_listing_shows_search_form()
 *
 * (v1.17.0) Whether search.php prints its search form above the results.
 * Filterable via roci_listing_search_form( $show, $context ), default true.
 * When false, template-parts/content-none.php prints the form in the search
 * empty state instead, so a search page is never left without one.
 *
 * @param  string $context 'search' (the only context that prints a top form).
 * @return bool
 */
function roci_listing_shows_search_form( $context ) {
    return (bool) apply_filters( 'roci_listing_search_form', true, roci_listing_context( $context ) );
}

/**
 * roci_resolve_class_slots()
 *
 * (internal) Validates a filtered slot => class-list map against its
 * defaults: a slot the filter dropped, or returned as a non-array, gets its
 * default back, and every class is passed through sanitize_html_class(). An
 * explicit empty array is kept — that is how a slot is switched off.
 * Shared by roci_get_listing_classes(), roci_get_listing_header_classes() and
 * searchform.php.
 *
 * @param  mixed $classes  The filtered map.
 * @param  array $defaults The default map, slot => string[].
 * @return array           slot => string[], one entry per default slot.
 */
function roci_resolve_class_slots( $classes, $defaults ) {
    $classes  = is_array( $classes ) ? $classes : array();
    $resolved = array();

    foreach ( $defaults as $slot => $default ) {
        $list              = isset( $classes[ $slot ] ) && is_array( $classes[ $slot ] ) ? $classes[ $slot ] : $default;
        $resolved[ $slot ] = array_values( array_filter( array_map( 'sanitize_html_class', $list ) ) );
    }

    return $resolved;
}

/**
 * roci_get_pagination_args()
 *
 * Arguments for the_posts_pagination() on a listing template. Filterable via
 * roci_pagination_args( $args, $context ). The default is an empty array, so
 * an unfiltered site gets core's output unchanged.
 *
 * ⚠ CORE ALREADY PRINTS THE <nav> (class "navigation pagination", with its
 * own aria-label). A child that wants a styling hook wraps the call in a
 * <div>, never another <nav> — two nested navigation landmarks announce one
 * control twice.
 *
 * @param  string $context 'index', 'archive' or 'search'.
 * @return array           Arguments for the_posts_pagination().
 */
function roci_get_pagination_args( $context ) {
    $args = apply_filters( 'roci_pagination_args', array(), roci_listing_context( $context ) );

    return is_array( $args ) ? $args : array();
}

/**
 * roci_class_attr()
 *
 * (internal) Joins a class list into an escaped class="" value.
 *
 * @param  string[] $classes Class names.
 * @return string            Escaped, space-separated class list.
 */
function roci_class_attr( $classes ) {
    return esc_attr( implode( ' ', (array) $classes ) );
}

/**
 * roci_class_attribute()
 *
 * (internal, v1.17.0) A whole leading-space class="" attribute, or '' when
 * the list is empty — for elements whose class slot a filter may empty, so
 * they print no empty class="" attribute.
 *
 * @param  string[] $classes Class names.
 * @return string            ' class="…"' or ''.
 */
function roci_class_attribute( $classes ) {
    return $classes ? ' class="' . roci_class_attr( $classes ) . '"' : '';
}