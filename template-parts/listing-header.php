<?php
/**
 * Listing Header — the listing <h1> for index, archive, search and 404
 *
 * Rendered by index.php (listing branch), archive.php, search.php and 404.php
 * through get_template_part( 'template-parts/listing-header', null,
 * array( 'context' => … ) ). It owns each route's <h1> logic, moved here
 * unchanged from the templates, and the archive description.
 *
 * roci_entry_title_mode( $context, $post_id ) decides the <h1>:
 *   'visible' — rendered as below.
 *   'hidden'  — rendered with .screen-reader-text, still the page's <h1>.
 *   'none'    — not rendered, and roci_before_listing_title does not fire;
 *               the child renders its own <h1>. The archive description
 *               still renders; the <header> is omitted only when it would be
 *               empty.
 * $post_id is the Posts page on the posts index and 0 everywhere else.
 *
 * do_action( 'roci_before_listing_title', $context ) fires immediately before
 * the <h1> — the slot for an eyebrow line.
 *
 * v1.1.0 seams, both defaulting to the v1.0.0 markup byte for byte:
 *   roci_listing_title( null, $context ) — a string replaces the <h1>'s
 *     contents (through wp_kses_post()); null runs the per-context logic below.
 *   roci_listing_header_classes( $classes, $context ) — classes on the
 *     <header>, the <h1> and the description (roci_get_listing_header_classes()).
 *     'header' => [] omits the <header> element itself; its contents then
 *     print directly in the listing's container.
 *
 * Reach for those filters first; override this part only for structure they
 * cannot express. Never override the four templates for the heading.
 *
 * File:    template-parts/listing-header.php
 * Version: 1.1.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

$roci_context = roci_listing_context( isset( $args['context'] ) ? $args['context'] : '' );

$roci_posts_page_id = ( 'index' === $roci_context && is_home() ) ? (int) get_option( 'page_for_posts' ) : 0;
$roci_title_mode    = roci_entry_title_mode( $roci_context, $roci_posts_page_id );
$roci_has_desc      = 'archive' === $roci_context && get_the_archive_description();

if ( 'none' === $roci_title_mode && ! $roci_has_desc ) {
    return;
}

$roci_header_classes = roci_get_listing_header_classes( $roci_context );
$roci_title_classes  = $roci_header_classes['title'];

if ( 'hidden' === $roci_title_mode ) {
    $roci_title_classes[] = 'screen-reader-text';
}
?>
<?php if ( $roci_header_classes['header'] ) : ?>
<header class="<?php echo roci_class_attr( $roci_header_classes['header'] ); ?>">
<?php endif; ?>
    <?php if ( 'none' !== $roci_title_mode ) : ?>
        <?php do_action( 'roci_before_listing_title', $roci_context ); ?>
        <h1<?php echo roci_class_attribute( $roci_title_classes ); ?>>
            <?php
            $roci_custom_title = apply_filters( 'roci_listing_title', null, $roci_context );

            if ( is_string( $roci_custom_title ) ) {
                echo wp_kses_post( $roci_custom_title );
            } else {
                switch ( $roci_context ) {
                    case 'archive':
                        if ( is_category() ) {
                            single_cat_title();
                        } elseif ( is_tag() ) {
                            single_tag_title();
                        } elseif ( is_author() ) {
                            echo esc_html( get_the_author() );
                        } elseif ( is_date() ) {
                            // By granularity (archive.php v1.1.1): a single "F Y" label
                            // misnamed year and day archives.
                            if ( is_year() ) {
                                echo esc_html( get_the_date( 'Y' ) );
                            } elseif ( is_month() ) {
                                echo esc_html( get_the_date( 'F Y' ) );
                            } else {
                                echo esc_html( get_the_date() ); // is_day(): the site date format
                            }
                        } elseif ( is_tax() ) {
                            // Custom-taxonomy term archives (archive.php v1.1.1) — they used
                            // to fall to post_type_archive_title(), which prints nothing there.
                            single_term_title();
                        } elseif ( is_post_type_archive() ) {
                            post_type_archive_title();
                        } else {
                            // Last resort, so the <h1> is never empty.
                            the_archive_title();
                        }
                        break;

                    case 'search':
                        printf(
                            /* translators: %s: the search query. */
                            esc_html__( 'Search Results for: %s', 'rocinante' ),
                            '<span>' . get_search_query() . '</span>' // get_search_query() escapes by default.
                        );
                        break;

                    case '404':
                        esc_html_e( '404 — Page Not Found', 'rocinante' );
                        break;

                    default: // index: the Posts page's own title, or the site name.
                        echo esc_html( $roci_posts_page_id ? get_the_title( $roci_posts_page_id ) : get_bloginfo( 'name' ) );
                }
            }
            ?>
        </h1>
    <?php endif; ?>
    <?php if ( $roci_has_desc ) : ?>
        <div<?php echo roci_class_attribute( $roci_header_classes['description'] ); ?>>
            <?php the_archive_description(); ?>
        </div>
    <?php endif; ?>
<?php if ( $roci_header_classes['header'] ) : ?>
</header>
<?php endif; ?>
