<?php
/**
 * Schema Tokens — {{home}} expansion and JSON-LD validation
 *
 * The per-page schema field (roci_schema_json) is author-pasted free text
 * stored in postmeta. Two things have to happen to it before it can be
 * trusted on a page, and both live here so there is exactly one copy of
 * each: token expansion, and a validity check.
 *
 * ONE SOURCE, TWO CONSUMERS: header.php expands and validates before
 * emitting the block, and the SEO Health panel mirrors the same expansion
 * client-side before parsing. Neither keeps its own copy of the rule.
 *
 * A THIRD STEP, OUTPUT-ONLY (v1.1.0): roci_schema_json_for_output() decodes
 * the HTML entities kses stores in the field (& saved as &amp;) and makes the
 * JSON script-safe. It runs after validation and has no client-side mirror,
 * because it never changes whether a paste is valid.
 *
 * WHY A TOKEN AT ALL. The field is echoed raw, with no URL rewriting
 * anywhere in the path, so a hardcoded domain freezes into the database
 * and survives a launch. {{home}} resolves at render time instead, which
 * means the same paste is correct on staging and on the live domain
 * without a search-replace.
 *
 * File:    inc/schema/schema-tokens.php
 * Version: 1.1.0
 * Updated: 2026-09-23
 *
 * @package ElRocinante
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }


/**
 * Expand authoring tokens in a per-page schema string.
 *
 * {{home}} becomes the site URL with NO trailing slash, so the token
 * composes with a path or fragment the way an author expects:
 * {{home}}/#organization → https://example.com/#organization. Leaving the
 * slash on would produce a doubled one.
 *
 * @param  string $json Raw field value, possibly containing tokens.
 * @return string       Expanded string; '' for a non-string input.
 */
function roci_expand_schema_tokens( $json ) {

    if ( ! is_string( $json ) ) {
        return '';
    }

    return str_replace( '{{home}}', untrailingslashit( home_url() ), $json );
}


/**
 * Is a per-page schema string valid JSON once its tokens are expanded?
 *
 * ⚠ THE ORDER IS LOAD-BEARING, AND IT IS THE WHOLE POINT OF THIS
 * FUNCTION. Validation runs on the EXPANDED string, never on the raw
 * field. {{home}} is not valid JSON on its own, so checking the raw value
 * would reject every correctly tokenized paste — the exact case the token
 * exists to serve.
 *
 * Non-strings return false rather than raising: MB Pro passes the field
 * CONFIG ARRAY as a value on some REST/Gutenberg saves (see CLAUDE.md
 * §13.1), and an empty field is simply nothing to validate. Neither is an
 * error condition.
 *
 * @param  string $json Raw field value, possibly containing tokens.
 * @return bool         True when the expanded string parses as JSON.
 */
function roci_schema_json_is_valid( $json ) {

    if ( ! is_string( $json ) || '' === trim( $json ) ) {
        return false;
    }

    json_decode( roci_expand_schema_tokens( $json ) );

    return JSON_ERROR_NONE === json_last_error();
}


/**
 * Make an EXPANDED, VALID per-page schema string safe and literal for output.
 *
 * WHY THIS EXISTS. roci_schema_json is a Meta Box textarea, and Meta Box
 * sanitises textareas with wp_kses_post() on save. kses normalises entities,
 * so every bare & an author types is STORED as &amp; — and header.php echoes
 * the field raw, so JSON-LD shipped "Charters &amp; Tours". JSON-LD is read
 * literally; the entity is wrong data. The stored value (what editors see in
 * the field) is deliberately left alone; this runs at output only.
 *
 * ⚠ ENTITIES ARE DECODED PER STRING VALUE, NOT ACROSS THE RAW TEXT. Decoding
 * the whole string would turn an &quot; inside a JSON string into a bare " and
 * break the JSON, silently dropping a paste that is valid today. Instead the
 * JSON is parsed, each string value is decoded, and the result is re-encoded,
 * so the encoder escapes any decoded quote correctly. Objects are decoded as
 * objects (not assoc arrays) so an empty {} stays {} rather than becoming [].
 *
 * SCRIPT-SAFE. Output sits inside <script type="application/ld+json">. A
 * decoded value could now contain a literal </script>, which would close the
 * tag early. Every "</" is rewritten to "<\/" — a valid JSON escape that
 * parses back to the same string.
 *
 * Re-encoding normalises formatting (pretty-printed, slashes and Unicode
 * unescaped); keys, order and values are otherwise unchanged. The final
 * string is re-checked with json_decode(); any failure returns '', and the
 * caller treats '' exactly like an invalid paste — skipped, as before.
 *
 * @param  string $json Expanded schema string (tokens already replaced).
 * @return string       Output-ready JSON, or '' if it cannot be produced.
 */
function roci_schema_json_for_output( $json ) {

    if ( ! is_string( $json ) || '' === trim( $json ) ) {
        return '';
    }

    $data = json_decode( $json );

    if ( JSON_ERROR_NONE !== json_last_error() ) {
        return '';
    }

    // Recursive walk as a closure, so no second function name is added.
    $decode = function ( $value ) use ( &$decode ) {
        if ( is_string( $value ) ) {
            return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }

        if ( is_array( $value ) ) {
            foreach ( $value as $key => $item ) {
                $value[ $key ] = $decode( $item );
            }
            return $value;
        }

        if ( is_object( $value ) ) {
            foreach ( get_object_vars( $value ) as $key => $item ) {
                // An empty-string key cannot be written back as a property.
                if ( '' === $key ) {
                    continue;
                }
                $value->$key = $decode( $item );
            }
            return $value;
        }

        return $value;
    };

    $output = wp_json_encode( $decode( $data ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

    if ( ! is_string( $output ) || '' === $output ) {
        return '';
    }

    $output = str_replace( '</', '<\/', $output );

    json_decode( $output );

    if ( JSON_ERROR_NONE !== json_last_error() ) {
        return '';
    }

    return $output;
}
