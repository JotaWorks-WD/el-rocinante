<?php
/**
 * Media — SVG Upload Support
 *
 * Allows administrators to upload SVG files to the Media Library.
 * Provided at the parent level so every child site inherits it.
 *
 * The feature is gated two ways. It only takes effect for users who
 * can manage_options — lower roles cannot upload SVGs. And it is
 * wrapped in the 'roci_allow_svg_uploads' filter (default true) so a
 * child theme can opt out entirely with:
 *
 *     add_filter( 'roci_allow_svg_uploads', '__return_false' );
 *
 * Two WordPress filters are required. upload_mimes registers the SVG
 * mime type so the extension is accepted. wp_check_filetype_and_ext
 * then corrects WordPress's real-file mime sniffing, which otherwise
 * rejects an SVG (delivered as text/xml) even when the mime is
 * whitelisted — without the second filter, uploads still fail.
 *
 * File:    inc/media/svg-support.php
 * Version: 1.0.0
 * Updated: 2026-07-13
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// ============================================================
// CAPABILITY + OPT-OUT GATE
// ============================================================

/**
 * Whether SVG uploads should be enabled for the current request.
 *
 * Returns true only when the current user can manage_options AND the
 * 'roci_allow_svg_uploads' filter has not been forced to false. Child
 * themes disable the feature entirely with:
 *
 *     add_filter( 'roci_allow_svg_uploads', '__return_false' );
 *
 * @return bool
 */
function roci_svg_uploads_enabled() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }
    return (bool) apply_filters( 'roci_allow_svg_uploads', true );
}


// ============================================================
// REGISTER SVG MIME TYPE
// ============================================================

add_filter( 'upload_mimes', function( $mimes ) {
    if ( ! roci_svg_uploads_enabled() ) {
        return $mimes;
    }
    $mimes['svg']  = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    return $mimes;
} );


// ============================================================
// CORRECT MIME SNIFFING
// ============================================================

add_filter( 'wp_check_filetype_and_ext', function( $data, $file, $filename, $mimes ) {
    if ( ! roci_svg_uploads_enabled() ) {
        return $data;
    }

    $ext = isset( $data['ext'] ) ? $data['ext'] : '';
    if ( '' === $ext ) {
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    }

    if ( 'svg' === $ext || 'svgz' === $ext ) {
        $data['type'] = 'image/svg+xml';
        $data['ext']  = $ext;
    }

    return $data;
}, 10, 4 );
