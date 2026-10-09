<?php
/**
 * Blog Shortcodes — Reusable Body Module Engine
 *
 * Seven shortcodes for editorial blog/post body content.
 * Theme-agnostic: no client-specific labels or icons in render logic.
 * Child themes opt in by calling roci_register_blog_shortcodes()
 * from their own functions.php. This file is required by the parent
 * so the functions are available, but registration is left to the child.
 *
 * File:    inc/blog-shortcodes.php
 * Version: 1.2.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 *
 * Public API:
 *   roci_blog_icon( $name )          — Inline SVG by name (check|external-link|info, plus any
 *                                      child icon added through the roci_blog_icons filter)
 *   roci_register_blog_shortcodes() — Registers all seven shortcodes with WordPress
 *
 * v1.2.0: the icon set is filterable (roci_blog_icons) and neutral. anchor,
 *   helm-wheel and wave left the parent; a child that uses them registers them
 *   through the filter. [roci_expect]'s default icon is now 'check' (was
 *   'anchor'); [roci_notes]'s stays 'info'.
 *
 * Shortcodes registered by roci_register_blog_shortcodes():
 *   [roci_image id="" caption="" alt=""]
 *   [roci_pair id1="" id2="" caption1="" caption2="" alt1="" alt2=""]
 *   [roci_quote cite=""]...[/roci_quote]
 *   [roci_expect title="" level=""]...[/roci_expect]
 *   [roci_notes title=""]...[/roci_notes]
 *   [roci_stats stat1="" stat2="" stat3="" stat4=""]
 *   [roci_related ids="" heading="" level=""]
 *
 * Accessibility attributes (v1.1.0):
 *   alt / alt1 / alt2 — OMITTED: the image's Media Library alt is used (and a
 *                      missing one is flagged under WP_DEBUG by jw_picture()).
 *                      PRESENT, even empty: used exactly as written — alt=""
 *                      marks the image decorative. A caption is never reused
 *                      as alt (it would be read twice).
 *   level            — Heading level for [roci_expect] / [roci_related]:
 *                      2–6, default 3. Anything else falls back to 3; 1 is
 *                      refused because the page title is the <h1>.
 *
 * Child Config:
 *   Define roci_blog_config() in the child theme returning an array.
 *   Missing keys always fall back to parent defaults. Example:
 *
 *   function roci_blog_config() {
 *       return [
 *           'expect' => [ 'label' => 'What to Expect', 'icon' => 'check' ],
 *           'notes'  => [ 'label' => 'Note',           'icon' => 'info'   ],
 *       ];
 *   }
 *
 *   Icon values accept a named icon string (see roci_blog_icon())
 *   or a raw SVG string. Raw SVGs are developer-authored config and
 *   are output directly without additional escaping.
 */


// ============================================================
// ICON HELPERS
// ============================================================

/**
 * roci_blog_icon()
 *
 * Returns an inline SVG string for the given icon name.
 * All icons use stroke="currentColor" so child SCSS controls color.
 * Every icon is decorative: aria-hidden="true" plus focusable="false" (v1.1.2),
 * so legacy IE/Edge never makes the <svg> a tab stop. No <title>.
 * Returns empty string for unknown names.
 *
 * THE PARENT SET IS NEUTRAL (v1.2.0): check, external-link, info. The nautical
 * icons that used to sit here (anchor, helm-wheel, wave) carried an industry
 * and broke the content-agnostic rule; a child that wants them registers them
 * itself through the roci_blog_icons filter.
 *
 * FILTER — roci_blog_icons( $icons ): name => SVG markup. A child adds or
 * replaces icons; every icon it supplies keeps aria-hidden="true"
 * focusable="false" (CONVENTIONS → "Icon markup"). Not memoized, so a late
 * add_filter() still works. A non-array return is ignored (the parent set is
 * used), and a non-string value counts as an unknown name.
 *
 * @param  string $name  check | external-link | info, plus any child-registered name
 * @return string        SVG markup or empty string.
 */
function roci_blog_icon( $name ) {
    $icons = [
        'check' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"'
            . ' fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<polyline points="20 6 9 17 4 12"/>'
            . '</svg>',

        'external-link' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"'
            . ' fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>'
            . '<polyline points="15 3 21 3 21 9"/>'
            . '<line x1="10" y1="14" x2="21" y2="3"/>'
            . '</svg>',

        'info' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"'
            . ' fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<circle cx="12" cy="12" r="10"/>'
            . '<line x1="12" y1="8" x2="12.01" y2="8"/>'
            . '<line x1="12" y1="12" x2="12" y2="16"/>'
            . '</svg>',
    ];

    $filtered = apply_filters( 'roci_blog_icons', $icons );
    if ( is_array( $filtered ) ) {
        $icons = $filtered;
    }

    return ( isset( $icons[ $name ] ) && is_string( $icons[ $name ] ) ) ? $icons[ $name ] : '';
}


/**
 * roci_blog_icon_markup()
 *
 * Internal. Resolves a config icon value to output-ready SVG markup.
 * Config values may be a known icon name or a raw SVG string.
 * Falls back to $default_name if the value is empty or unrecognized.
 *
 * @param  string $cfg_value    Icon name or raw SVG from child config.
 * @param  string $default_name Fallback icon name passed to roci_blog_icon().
 * @return string               SVG markup.
 */
function roci_blog_icon_markup( $cfg_value, $default_name ) {
    if ( ! $cfg_value ) {
        return roci_blog_icon( $default_name );
    }
    // Treat any string containing a tag character as a raw SVG.
    if ( false !== strpos( $cfg_value, '<' ) ) {
        return $cfg_value;
    }
    $icon = roci_blog_icon( $cfg_value );
    return $icon ? $icon : roci_blog_icon( $default_name );
}


// ============================================================
// CONFIG READER
// ============================================================

/**
 * roci_blog_cfg()
 *
 * Internal. Returns the child config array, cached per request.
 * Missing keys fall through to defaults in each render function.
 *
 * @return array
 */
function roci_blog_cfg() {
    static $cfg = null;
    if ( null === $cfg ) {
        $cfg = function_exists( 'roci_blog_config' ) ? (array) roci_blog_config() : [];
    }
    return $cfg;
}


/**
 * roci_blog_heading_level()
 *
 * Internal. Validates a shortcode `level` attribute to an int 2–6; anything
 * else (missing, 1, 7, non-numeric) returns 3, the historical level. 1 is
 * refused because the page title is the page's <h1>.
 *
 * @param  mixed $level Raw attribute value.
 * @return int          2–6.
 */
function roci_blog_heading_level( $level ) {
    $level = (int) $level;
    return ( $level >= 2 && $level <= 6 ) ? $level : 3;
}


// ============================================================
// SHORTCODE RENDER FUNCTIONS
// ============================================================

/**
 * roci_sc_image()
 *
 * Render for [roci_image id="" caption=""].
 * Full-width editorial image via jw_picture() at large size.
 * Outputs nothing if id is absent or jw_picture() returns empty.
 *
 * @param  array $atts  Shortcode attributes.
 * @return string       HTML output.
 */
function roci_sc_image( $atts ) {
    // alt defaults to null: OMITTED -> Media Library alt; PRESENT (even "") -> used as-is.
    $atts = shortcode_atts( [
        'id'      => '',
        'caption' => '',
        'alt'     => null,
    ], $atts, 'roci_image' );

    $id = absint( $atts['id'] );
    if ( ! $id ) {
        return '';
    }

    $picture = jw_picture( $id, 'large', $atts['alt'] );
    if ( ! $picture ) {
        return '';
    }

    $caption = trim( $atts['caption'] );

    ob_start();
    ?>
    <figure class="roci-image">
        <?php echo $picture; ?>
        <?php if ( $caption ) : ?>
            <figcaption><?php echo esc_html( $caption ); ?></figcaption>
        <?php endif; ?>
    </figure>
    <?php
    return ob_get_clean();
}


/**
 * roci_sc_pair()
 *
 * Render for [roci_pair id1="" id2="" caption1="" caption2=""].
 * Two images side by side via jw_picture() at large size.
 * Falls back to roci-pair--single modifier if only one id is valid.
 * Outputs nothing if neither id resolves to an image.
 *
 * @param  array $atts  Shortcode attributes.
 * @return string       HTML output.
 */
function roci_sc_pair( $atts ) {
    // alt1 / alt2 default to null: OMITTED -> Media Library alt; PRESENT (even "") -> used as-is.
    $atts = shortcode_atts( [
        'id1'      => '',
        'id2'      => '',
        'caption1' => '',
        'caption2' => '',
        'alt1'     => null,
        'alt2'     => null,
    ], $atts, 'roci_pair' );

    $id1  = absint( $atts['id1'] );
    $id2  = absint( $atts['id2'] );
    $pic1 = $id1 ? jw_picture( $id1, 'large', $atts['alt1'] ) : '';
    $pic2 = $id2 ? jw_picture( $id2, 'large', $atts['alt2'] ) : '';

    if ( ! $pic1 && ! $pic2 ) {
        return '';
    }

    $caption1 = trim( $atts['caption1'] );
    $caption2 = trim( $atts['caption2'] );
    $class    = ( ! $pic1 || ! $pic2 ) ? 'roci-pair roci-pair--single' : 'roci-pair';

    ob_start();
    ?>
    <div class="<?php echo esc_attr( $class ); ?>">
        <?php if ( $pic1 ) : ?>
            <figure class="roci-pair__figure">
                <?php echo $pic1; ?>
                <?php if ( $caption1 ) : ?>
                    <figcaption><?php echo esc_html( $caption1 ); ?></figcaption>
                <?php endif; ?>
            </figure>
        <?php endif; ?>
        <?php if ( $pic2 ) : ?>
            <figure class="roci-pair__figure">
                <?php echo $pic2; ?>
                <?php if ( $caption2 ) : ?>
                    <figcaption><?php echo esc_html( $caption2 ); ?></figcaption>
                <?php endif; ?>
            </figure>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * roci_sc_quote()
 *
 * Render for [roci_quote cite=""]...[/roci_quote].
 * Pull quote with optional attribution rendered in a <cite> element.
 *
 * @param  array       $atts    Shortcode attributes.
 * @param  string|null $content Content between tags.
 * @return string               HTML output.
 */
function roci_sc_quote( $atts, $content = '' ) {
    $atts = shortcode_atts( [
        'cite' => '',
    ], $atts, 'roci_quote' );

    $content = trim( do_shortcode( $content ) );
    if ( ! $content ) {
        return '';
    }

    $cite = trim( $atts['cite'] );

    ob_start();
    ?>
    <blockquote class="roci-quote">
        <?php echo wp_kses_post( $content ); ?>
        <?php if ( $cite ) : ?>
            <cite><?php echo esc_html( $cite ); ?></cite>
        <?php endif; ?>
    </blockquote>
    <?php
    return ob_get_clean();
}


/**
 * roci_sc_expect()
 *
 * Render for [roci_expect title=""]...[/roci_expect].
 * Content is newline-separated plain text; each non-empty line becomes
 * one <li>. wpautop <br> tags are normalised back to newlines before
 * stripping HTML so the split works whether or not autop has run.
 * The title attribute overrides the config/default heading label for
 * this one instance; omit it to use the config label or "What to Expect".
 *
 * @param  array       $atts    Shortcode attributes.
 * @param  string|null $content Newline-separated list items.
 * @return string               HTML output.
 */
function roci_sc_expect( $atts, $content = '' ) {
    $atts = shortcode_atts( [
        'title' => '',
        'level' => 3,
    ], $atts, 'roci_expect' );

    $level = roci_blog_heading_level( $atts['level'] );
    $cfg   = roci_blog_cfg();
    $label = $atts['title'] ? trim( $atts['title'] ) : ( $cfg['expect']['label'] ?? 'What to Expect' );
    $icon  = roci_blog_icon_markup( $cfg['expect']['icon'] ?? '', 'check' );

    // Normalise wpautop <br> variants back to newlines before stripping all tags.
    $text  = str_replace( [ '<br>', '<br/>', '<br />' ], "\n", $content );
    $text  = strip_tags( $text );
    $lines = array_filter( array_map( 'trim', preg_split( '/\r?\n/', $text ) ), 'strlen' );

    if ( empty( $lines ) ) {
        return '';
    }

    ob_start();
    ?>
    <div class="roci-expect">
        <h<?php echo $level; ?> class="roci-expect__heading"><?php echo esc_html( $label ); ?></h<?php echo $level; ?>>
        <ul class="roci-expect__list" role="list">
            <?php foreach ( $lines as $line ) : ?>
                <li class="roci-expect__item">
                    <span class="roci-expect__icon" aria-hidden="true"><?php echo $icon; ?></span>
                    <?php echo esc_html( $line ); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * roci_sc_notes()
 *
 * Render for [roci_notes title=""]...[/roci_notes].
 * Aside/callout block with icon + label header. The title attribute
 * overrides the config label for this one instance only.
 * Content may contain paragraphs; sanitised via wp_kses_post().
 *
 * @param  array       $atts    Shortcode attributes.
 * @param  string|null $content Aside body.
 * @return string               HTML output.
 */
function roci_sc_notes( $atts, $content = '' ) {
    $atts = shortcode_atts( [
        'title' => '',
    ], $atts, 'roci_notes' );

    $content = trim( do_shortcode( $content ) );
    if ( ! $content ) {
        return '';
    }

    $cfg   = roci_blog_cfg();
    $label = $atts['title'] ? trim( $atts['title'] ) : ( $cfg['notes']['label'] ?? 'Note' );
    $icon  = roci_blog_icon_markup( $cfg['notes']['icon'] ?? '', 'info' );

    // role="note" (parenthetic content, not a landmark), named by its own label.
    // The label stays a non-heading: a callout is not a section, and a heading
    // would put every callout into the post's outline.
    $label_id = wp_unique_id( 'roci-notes-' );

    ob_start();
    ?>
    <div class="roci-notes" role="note" aria-labelledby="<?php echo esc_attr( $label_id ); ?>">
        <div class="roci-notes__header">
            <span class="roci-notes__icon" aria-hidden="true"><?php echo $icon; ?></span>
            <span class="roci-notes__label" id="<?php echo esc_attr( $label_id ); ?>"><?php echo esc_html( $label ); ?></span>
        </div>
        <div class="roci-notes__body"><?php echo wp_kses_post( $content ); ?></div>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * roci_sc_stats()
 *
 * Render for [roci_stats stat1="" stat2="" stat3="" stat4=""].
 * Each value is a "Label: Value" string split on the first colon only.
 * stat1 is the minimum required; stat2–stat4 are optional.
 * Cells with no content are silently skipped.
 *
 * @param  array $atts  Shortcode attributes.
 * @return string       HTML output.
 */
function roci_sc_stats( $atts ) {
    $atts = shortcode_atts( [
        'stat1' => '',
        'stat2' => '',
        'stat3' => '',
        'stat4' => '',
    ], $atts, 'roci_stats' );

    $cells = [];
    foreach ( [ 'stat1', 'stat2', 'stat3', 'stat4' ] as $key ) {
        $raw = trim( $atts[ $key ] );
        if ( ! $raw ) {
            continue;
        }
        $parts = explode( ':', $raw, 2 );
        $label = trim( $parts[0] );
        $value = isset( $parts[1] ) ? trim( $parts[1] ) : '';
        if ( $label || $value ) {
            $cells[] = [ 'label' => $label, 'value' => $value ];
        }
    }

    if ( empty( $cells ) ) {
        return '';
    }

    // A description list: each <div> groups one <dt> label with its <dd> value
    // (div grouping inside <dl> is valid HTML). Classes are unchanged, and the
    // cells are still the container's only children, so class-based and
    // :nth-child styling keeps matching. Both elements are always emitted so
    // every group stays well-formed even when a label or value is empty.
    ob_start();
    ?>
    <dl class="roci-stats">
        <?php foreach ( $cells as $cell ) : ?>
            <div class="roci-stats__cell">
                <dt class="roci-stats__label"><?php echo esc_html( $cell['label'] ); ?></dt>
                <dd class="roci-stats__value"><?php echo esc_html( $cell['value'] ); ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
    <?php
    return ob_get_clean();
}


/**
 * roci_sc_related()
 *
 * Render for [roci_related ids="" heading=""].
 * Fetches posts by comma-separated ID list (any post type), renders
 * as a linked card strip with thumbnail, post-type chip, and title.
 * Order follows the ids attribute. Deleted and draft IDs are silently
 * skipped (get_posts defaults to publish status).
 * Outputs nothing if no valid published posts are found.
 *
 * @param  array $atts  Shortcode attributes.
 * @return string       HTML output.
 */
function roci_sc_related( $atts ) {
    $atts = shortcode_atts( [
        'ids'     => '',
        'heading' => '',
        'level'   => 3,
    ], $atts, 'roci_related' );

    $level = roci_blog_heading_level( $atts['level'] );

    $ids = array_filter( array_map( 'absint', explode( ',', $atts['ids'] ) ) );
    if ( empty( $ids ) ) {
        return '';
    }

    $posts = get_posts( [
        'post_type'      => 'any',
        'post__in'       => $ids,
        'orderby'        => 'post__in',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ] );

    if ( empty( $posts ) ) {
        return '';
    }

    $heading = trim( $atts['heading'] );

    ob_start();
    ?>
    <div class="roci-related">
        <?php if ( $heading ) : ?>
            <h<?php echo $level; ?> class="roci-related__heading"><?php echo esc_html( $heading ); ?></h<?php echo $level; ?>>
        <?php endif; ?>
        <div class="roci-related__strip">
            <?php foreach ( $posts as $roci_post ) : ?>
                <?php
                $pto        = get_post_type_object( $roci_post->post_type );
                $type_label = $pto ? $pto->labels->singular_name : $roci_post->post_type;
                // alt="" — the thumbnail is decorative inside the card link, so the
                // link's accessible name is exactly "{Type} {Title}" rather than
                // the image alt followed by the same title again.
                $thumbnail  = get_the_post_thumbnail( $roci_post->ID, 'medium', array( 'alt' => '' ) );
                ?>
                <a href="<?php echo esc_url( get_permalink( $roci_post->ID ) ); ?>" class="roci-related__card">
                    <?php if ( $thumbnail ) : ?>
                        <div class="roci-related__thumb"><?php echo $thumbnail; ?></div>
                    <?php endif; ?>
                    <span class="roci-related__type"><?php echo esc_html( $type_label ); ?></span>
                    <span class="roci-related__title"><?php echo esc_html( get_the_title( $roci_post->ID ) ); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


// ============================================================
// REGISTRAR — called from child theme, not from the parent
// ============================================================

/**
 * roci_register_blog_shortcodes()
 *
 * Registers all seven blog body shortcodes with WordPress.
 * The parent does NOT call this — a child theme opts in by calling it
 * from its own functions.php (typically on or after 'init').
 *
 * @return void
 */
function roci_register_blog_shortcodes() {
    add_shortcode( 'roci_image',   'roci_sc_image' );
    add_shortcode( 'roci_pair',    'roci_sc_pair' );
    add_shortcode( 'roci_quote',   'roci_sc_quote' );
    add_shortcode( 'roci_expect',  'roci_sc_expect' );
    add_shortcode( 'roci_notes',   'roci_sc_notes' );
    add_shortcode( 'roci_stats',   'roci_sc_stats' );
    add_shortcode( 'roci_related', 'roci_sc_related' );
}
