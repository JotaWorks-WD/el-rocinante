<?php
/**
 * FAQ Accordion — a page's questions and answers (its FAQPage JSON-LD is
 * printed in <head>; this part's call is the fallback, see THE SCHEMA)
 *
 * Any template renders it with one call:
 *
 *     get_template_part( 'template-parts/faq' );
 *     get_template_part( 'template-parts/faq', null, array(
 *         'heading' => 'Before You Book', // optional — the H2
 *         'id'      => 'faq',             // optional — an id on the <section>, for an anchor
 *         'post_id' => 123,               // optional — default: the queried page
 *         'section' => 'On the Tour',     // optional (v1.1.0) — only that section's items
 *     ) );
 *
 * SECTIONS (v1.1.0). Each FAQ item may carry an optional Section
 * (faq_section). The 'section' arg picks which items this render shows:
 *   absent      — every item, exactly as v1.0.0 (the output is byte-identical);
 *   'Name'      — only that section (trimmed, case-insensitive), under its own
 *                 H2 — the section's name, see THE HEADING;
 *   ''          — only the items with no section (the "main" FAQ).
 * Tested with array_key_exists(), so '' is a filter, not "absent".
 *
 * ⚠ THE PARITY CONTRACT. The FAQPage always publishes EVERY item (see THE
 * SCHEMA), so a page that renders by section must render every section and
 * then the unsectioned remainder — or it publishes questions it does not
 * show. roci_get_faq_sections() makes that the easy loop:
 *
 *     foreach ( roci_get_faq_sections() as $section ) {
 *         get_template_part( 'template-parts/faq', null, array(
 *             'section' => $section,
 *             'id'      => sanitize_title( $section ), // optional anchor
 *         ) );
 *     }
 *     get_template_part( 'template-parts/faq', null, array( 'section' => '' ) );
 *
 * A section anchor without the faq- prefix can never collide with the ids
 * this part generates (see IDS below).
 *
 * THE DATA is the parent's jw_faq_items group — post meta on the page itself
 * (Pages → {page} → "FAQ Items"), on every type roci_get_faq_post_types()
 * returns — read through roci_get_faq_items(), which keeps only rows with both
 * a question and an answer.
 *
 * RENDERS NOTHING when there is no complete pair: no section, no heading, no
 * script, no schema.
 *
 * THE SCHEMA. The FAQPage JSON-LD covers EVERY complete item of the post (all
 * sections) and prints once per post per request (jw_faq_schema()). Since El
 * Rocinante 7.8.0 header.php prints it in <head> on a singular route, so the
 * jw_faq_schema() call at the end of this part prints nothing for the queried
 * page. The call stays as the fallback: on a non-singular route, or with a
 * post_id arg naming another post, the first render that prints anything
 * publishes that post's FAQPage after its markup. (Keep the page's own Schema
 * JSON-LD field free of FAQ markup; that would be a second block.)
 *
 * THE HEADING — an H2 that names the section — resolves in this order, the
 * last non-empty value winning:
 *   1. __( 'Frequently Asked Questions' );
 *   2. $args['heading'];
 *   3. the page's FAQ Heading field (post meta jw_faq_heading);
 *   4. apply_filters( 'roci_faq_heading', $heading, $context ) — the last word.
 *      $context = array( 'post_id' => int, 'source' => 'default' | 'arg' | 'field' | 'section' ),
 *      so a callback can supply a site default only when nothing more specific
 *      set one: `if ( 'default' !== $context['source'] ) return $heading;`.
 *      Return plain text; it is escaped here.
 *
 * A NAMED SECTION (v1.1.0) has its own order: default → the section's name
 * (source 'section', spelled as its first item stores it) → $args['heading']
 * → the filter. The FAQ Heading field is NOT consulted: one page-level field
 * would otherwise print the same H2 over every section. It still applies to
 * an absent or '' section.
 *
 * THE SCRIPT. dist/js/faq-accordion.js is REGISTERED by
 * el_rocinante_enqueue_assets() and ENQUEUED HERE, so only a page that renders
 * an accordion loads it. This runs in the body, before wp_footer(), so the
 * footer script still prints.
 *
 * ⚠ THE ARIA CONTRACT — the script and this markup depend on each other:
 *
 *   section.roci-faq                      ← the script's and the styles' scope
 *     div.faq-accordion                   ← the script's container query
 *       div.faq-accordion__item
 *         h3.faq-accordion__header
 *           button.faq-accordion__button  ← the script's button query
 *             [id]                        ← the panel's aria-labelledby target
 *             [aria-expanded="false"]     ← read AND written by the script; styled
 *             [aria-controls="{panel}"]   ← the ONLY way the script finds its panel
 *         div.faq-accordion__panel
 *           [id] [role="region"] [aria-labelledby="{button}"]
 *           [hidden]                      ← written by the script; the no-JS state
 *
 * Without the script the panels stay closed.
 *
 * ⚠ .roci-faq IS LOAD-BEARING. The parent's styles (components/_faq.scss, all
 * under :where(.roci-faq)) and script reach only markup that carries it. A
 * child that overrides this part keeps it, or ships its own styles and script.
 *
 * IDS ARE UNIQUE PER RENDER: faq-title / faq-item-{n} / faq-panel-{n} on the
 * first render, faq-2-… on the next. A file-scope static is not valid PHP in a
 * template part, so the counter is a global.
 *
 * THE ANSWER is a plain textarea: wpautop() turns its blank lines into
 * paragraphs, then wp_kses_post() filters it (inline links survive).
 *
 * Override this part (child-first, like every parent part) only for structure
 * the arguments, the field and the filter cannot express.
 *
 * File:    template-parts/faq.php
 * Version: 1.1.1
 * Updated: 2026-10-21
 *
 * @package ElRocinante
 */

$roci_faq_post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
$roci_faq_post_id = $roci_faq_post_id ? $roci_faq_post_id : ( is_singular() ? get_queried_object_id() : (int) get_the_ID() );

// THE SECTION — absent: every item; '': unsectioned only; a name: that section.
$roci_faq_has_section = is_array( $args ) && array_key_exists( 'section', $args );
$roci_faq_section     = $roci_faq_has_section ? trim( (string) $args['section'] ) : '';
$roci_faq_items       = roci_get_faq_items( $roci_faq_post_id, $roci_faq_has_section ? $roci_faq_section : null );

if ( ! $roci_faq_items ) {
    return;
}

$roci_faq_named = '' !== $roci_faq_section;

global $roci_faq_instance;
$roci_faq_instance = isset( $roci_faq_instance ) ? (int) $roci_faq_instance + 1 : 1;
$roci_faq_prefix   = ( $roci_faq_instance > 1 ) ? 'faq-' . $roci_faq_instance : 'faq';

// THE HEADING — default → arg → field → filter; a named section: default →
// section name → arg → filter, no field (see the docblock).
$roci_faq_heading = __( 'Frequently Asked Questions', 'rocinante' );
$roci_faq_source  = 'default';

if ( $roci_faq_named ) {
    $roci_faq_heading = $roci_faq_items[0]['section'];
    $roci_faq_source  = 'section';
}

if ( isset( $args['heading'] ) && '' !== trim( (string) $args['heading'] ) ) {
    $roci_faq_heading = trim( (string) $args['heading'] );
    $roci_faq_source  = 'arg';
}

if ( ! $roci_faq_named ) {
    $roci_faq_field = roci_get_field( 'jw_faq_heading', $roci_faq_post_id );
    if ( is_string( $roci_faq_field ) && '' !== trim( $roci_faq_field ) ) {
        $roci_faq_heading = trim( $roci_faq_field );
        $roci_faq_source  = 'field';
    }
}

$roci_faq_heading = (string) apply_filters(
    'roci_faq_heading',
    $roci_faq_heading,
    array(
        'post_id' => $roci_faq_post_id,
        'source'  => $roci_faq_source,
    )
);

$roci_faq_section_id = isset( $args['id'] ) ? sanitize_html_class( (string) $args['id'] ) : '';

wp_enqueue_script( 'el-rocinante-faq-accordion' );
?>
<section class="faq roci-faq"<?php echo $roci_faq_section_id ? ' id="' . esc_attr( $roci_faq_section_id ) . '"' : ''; ?> aria-labelledby="<?php echo esc_attr( $roci_faq_prefix . '-title' ); ?>">
    <div class="faq__inner">

        <h2 id="<?php echo esc_attr( $roci_faq_prefix . '-title' ); ?>" class="faq__heading"><?php echo esc_html( $roci_faq_heading ); ?></h2>

        <div class="faq-accordion" id="<?php echo esc_attr( $roci_faq_prefix . '-accordion' ); ?>">

            <?php foreach ( $roci_faq_items as $roci_faq_i => $roci_faq_item ) :
                $roci_faq_item_id  = $roci_faq_prefix . '-item-' . $roci_faq_i;
                $roci_faq_panel_id = $roci_faq_prefix . '-panel-' . $roci_faq_i;
                ?>
            <div class="faq-accordion__item">

                <h3 class="faq-accordion__header">
                    <button
                        class="faq-accordion__button"
                        type="button"
                        id="<?php echo esc_attr( $roci_faq_item_id ); ?>"
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr( $roci_faq_panel_id ); ?>"
                    >
                        <span><?php echo esc_html( $roci_faq_item['question'] ); ?></span>
                    </button>
                </h3>

                <div
                    id="<?php echo esc_attr( $roci_faq_panel_id ); ?>"
                    class="faq-accordion__panel"
                    role="region"
                    aria-labelledby="<?php echo esc_attr( $roci_faq_item_id ); ?>"
                    hidden
                >
                    <div class="faq-accordion__body">
                        <?php echo wp_kses_post( wpautop( $roci_faq_item['answer'] ) ); ?>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>

        </div>

    </div>
</section>
<?php
jw_faq_schema( $roci_faq_post_id );
