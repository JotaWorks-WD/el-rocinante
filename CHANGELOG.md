# El Rocinante Changelog

All notable changes to the El Rocinante parent theme are recorded here. Entries are newest-first. Dates are omitted for entries migrated from the style.css rolling block, which did not record them.

---

## [2.13.2] — 2026-06-09
Fix `jw_link_atts()`: `mailto:` and `tel:` now return `''` instead of new-tab attributes. These are protocol links that open the mail client or phone dialer — `target="_blank"` is meaningless and can flash an orphan blank tab. They fall through to the existing `return ''` at the end of the function. `wa.me` and cross-host `http(s)` links are unchanged.

## [2.13.1] — 2026-06-09
Changelog migrated from style.css rolling block to CHANGELOG.md. style.css now carries only its Version header field.

## [2.13.0]
Add `jw_link_atts()` to `helpers.php`: returns `target="_blank" rel="noopener noreferrer"` for external links (mailto/tel/wa.me/cross-host http), empty string for internal/same-host. Host comparison via `home_url()` so it works per-client across all child themes with no config.

## [2.12.15]
Doc-hygiene pass: `functions.php` + `settings-page.php` docblock version sync; `roci_settings_page()` filter declaration added.

## [2.12.14]
Archive suppression: author, date, and default taxonomy archives now 404 by default (filterable per type for child theme opt-out).

## [2.12.13]
CLAUDE.md convention blocks added (version tracking, Fauxlders, audit verification); root `style.css` changelog wiped and reset for rolling-6 convention.
