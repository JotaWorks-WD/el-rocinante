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
 * WHY A TOKEN AT ALL. The field is echoed raw, with no URL rewriting
 * anywhere in the path, so a hardcoded domain freezes into the database
 * and survives a launch. {{home}} resolves at render time instead, which
 * means the same paste is correct on staging and on the live domain
 * without a search-replace.
 *
 * File:    inc/schema/schema-tokens.php
 * Version: 1.0.0
 * Updated: 2026-09-04
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
