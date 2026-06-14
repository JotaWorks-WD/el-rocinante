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
 * Version: 1.0.0
 * Updated: 2026-06-14
 *
 * @package ElRocinante
 *
 * Public API:
 *   roci_blog_icon( $name )          — Inline SVG by name (anchor|helm-wheel|external-link|wave|info)
 *   roci_register_blog_shortcodes() — Registers all seven shortcodes with WordPress
 *
 * Shortcodes registered by roci_register_blog_shortcodes():
 *   [roci_image id="" caption=""]
 *   [roci_pair id1="" id2="" caption1="" caption2=""]
 *   [roci_quote cite=""]...[/roci_quote]
 *   [roci_expect title=""]...[/roci_expect]
 *   [roci_notes title=""]...[/roci_notes]
 *   [roci_stats stat1="" stat2="" stat3="" stat4=""]
 *   [roci_related ids="" heading=""]
 *
 * Child Config:
 *   Define roci_blog_config() in the child theme returning an array.
 *   Missing keys always fall back to parent defaults. Example:
 *
 *   function roci_blog_config() {
 *       return [
 *           'expect' => [ 'label' => 'What to Expect', 'icon' => 'anchor' ],
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
 * Returns empty string for unknown names.
 *
 * @param  string $name  anchor | helm-wheel | external-link | wave | info
 * @return string        SVG markup or empty string.
 */
function roci_blog_icon( $name ) {
    $icons = [
        'anchor' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"'
            . ' fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="12" cy="5" r="2"/>'
            . '<line x1="12" y1="7" x2="12" y2="19"/>'
            . '<path d="M5 12a7 7 0 0 0 14 0"/>'
            . '<line x1="5" y1="12" x2="2" y2="12"/>'
            . '<line x1="19" y1="12" x2="22" y2="12"/>'
            . '</svg>',

        'helm-wheel' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"'
            . ' fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="12" cy="12" r="3"/>'
            . '<circle cx="12" cy="12" r="9"/>'
            . '<line x1="12" y1="3" x2="12" y2="9"/>'
            . '<line x1="12" y1="15" x2="12" y2="21"/>'
            . '<line x1="3" y1="12" x2="9" y2="12"/>'
            . '<line x1="15" y1="12" x2="21" y2="12"/>'
            . '<line x1="5.64" y1="5.64" x2="9.17" y2="9.17"/>'
            . '<line x1="14.83" y1="14.83" x2="18.36" y2="18.36"/>'
            . '<line x1="18.36" y1="5.64" x2="14.83" y2="9.17"/>'
            . '<line x1="9.17" y1="14.83" x2="5.64" y2="18.36"/>'
            . '</svg>',

        'external-link' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"'
            . ' fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>'
            . '<polyline points="15 3 21 3 21 9"/>'
            . '<line x1="10" y1="14" x2="21" y2="3"/>'
            . '</svg>',

        'wave' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"'
            . ' fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M2 12c1.5-2 3-2 4.5 0s3 2 4.5 0 3-2 4.5 0 3 2 4.5 0"/>'
            . '<path d="M2 17c1.5-2 3-2 4.5 0s3 2 4.5 0 3-2 4.5 0 3 2 4.5 0"/>'
            . '</svg>',

        'info' =>
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"'
            . ' fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"'
            . ' stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="12" cy="12" r="10"/>'
            . '<line x1="12" y1="8" x2="12.01" y2="8"/>'
            . '<line x1="12" y1="12" x2="12" y2="16"/>'
            . '</svg>',
    ];

    return $icons[ $name ] ?? '';
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
    $atts = shortcode_atts( [
        'id'      => '',
        'caption' => '',
    ], $atts, 'roci_image' );

    $id = absint( $atts['id'] );
    if ( ! $id ) {
        return '';
    }

    $picture = jw_picture( $id, 'large' );
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
    $atts = shortcode_atts( [
        'id1'      => '',
        'id2'      => '',
        'caption1' => '',
        'caption2' => '',
    ], $atts, 'roci_pair' );

    $id1  = absint( $atts['id1'] );
    $id2  = absint( $atts['id2'] );
    $pic1 = $id1 ? jw_picture( $id1, 'large' ) : '';
    $pic2 = $id2 ? jw_picture( $id2, 'large' ) : '';

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
    ], $atts, 'roci_expect' );

    $cfg   = roci_blog_cfg();
    $label = $atts['title'] ? trim( $atts['title'] ) : ( $cfg['expect']['label'] ?? 'What to Expect' );
    $icon  = roci_blog_icon_markup( $cfg['expect']['icon'] ?? '', 'anchor' );

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
        <h3 class="roci-expect__heading"><?php echo esc_html( $label ); ?></h3>
        <ul>
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

    ob_start();
    ?>
    <div class="roci-notes">
        <div class="roci-notes__header">
            <span class="roci-notes__icon" aria-hidden="true"><?php echo $icon; ?></span>
            <span class="roci-notes__label"><?php echo esc_html( $label ); ?></span>
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

    ob_start();
    ?>
    <div class="roci-stats">
        <?php foreach ( $cells as $cell ) : ?>
            <div class="roci-stats__cell">
                <span class="roci-stats__label"><?php echo esc_html( $cell['label'] ); ?></span>
                <span class="roci-stats__value"><?php echo esc_html( $cell['value'] ); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
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
    ], $atts, 'roci_related' );

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
            <h3 class="roci-related__heading"><?php echo esc_html( $heading ); ?></h3>
        <?php endif; ?>
        <div class="roci-related__strip">
            <?php foreach ( $posts as $roci_post ) : ?>
                <?php
                $pto        = get_post_type_object( $roci_post->post_type );
                $type_label = $pto ? $pto->labels->singular_name : $roci_post->post_type;
                $thumbnail  = get_the_post_thumbnail( $roci_post->ID, 'medium' );
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
