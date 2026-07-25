# El Rocinante Changelog

All notable changes to the El Rocinante parent theme are recorded here. Entries are newest-first. Dates are omitted for entries migrated from the style.css rolling block, which did not record them.

---

## [5.0.0] — 2026-07-25
Parent promotion: the theme moves from an inert skeleton to the network's architectural foundation. The parent now carries and consumes the full token architecture that Fish Potrero originally authored, so a child inherits the structure and supplies only its brand. `abstracts/_functions.scss` gains the `type-clamp()` generator — `clamp(0.8V rem, (8V/15) rem + (25V/54) vw, 1.2V rem)`, every middle term carrying a rem component so user font-size preference governs inside the fluid band as well as at the ends. New `base/_tokens.scss` emits 53 tokens on `:root`: twelve semantic colour slots named by role, two universal rgb triplets, two font-family slots, both type tiers (six display rungs derived from the theme's own h-ladder, seven text steps), and twenty-four metrics covering layout, radii, space, transitions and z-index. `base/_base.scss`, `base/_typography.scss` and `base/_utilities.scss` now read `var()` throughout; every repoint resolves to the value its hardcode produced, with one intended change — `h1`–`h6` move from fixed rems to the display-tier clamps, restoring the fluidity lost when the root flattened at v4.0.0. `body` and `p` font-size stay pinned at the literal `1.6rem`: the text tier's `--fs-4` is a clamp carrying Fish Potrero's 1.7rem body copy, not a corrected parent value, and repointing them would change what every inheriting child renders. Colour is a fillable slot rather than a value — the parent ships neutral defaults chosen to preserve its own prior rendering, and a child fills a slot by redeclaring the semantic token, its sheet loading second through the `el-rocinante-style` dependency. Four unreferenced pre-conversion placeholders retired (`$color-primary-light`, `$color-secondary-light`, `$color-secondary-dark`, `$default-font-size`); the compiled output was byte-identical before and after their removal. Fish Potrero has stripped 32 token declarations and 18 duplicated rules to inherit these instead, rendering unchanged. 360 Splendor required no change and renders unchanged on update. Major bump: the Sass API changed and the base layer's mechanism moved from compile-time substitution to runtime custom properties.

## [2.19.2] — 2026-07-08
Fix the root cause of the slug-meta corruption: added an `rwmb_roci_slug_value` save-time value filter. DB inspection confirmed MB Pro serializes the `roci_slug` field's config array into the `roci_slug` postmeta on REST/Gutenberg saves where the input is absent — the stored value was the `autocomplete…datalist…` config cruft. The v2.19.1 `is_string()` guard in `roci_save_slug_field()` only protected the `$_POST` read that syncs `post_name`; it could not stop the meta write, which happens inside MB Pro's own save pipeline before our `save_post` handler runs. The new filter fires at MB Pro's write point (`rwmb_{$field_id}_value`, before save) and rejects the non-string config-array fallback — returning the prior value, or empty — so the serialized config can never persist to meta. Filter signature verified against the Meta Box documentation. The display-time filter and the `is_string()` guard remain in place as defense in depth. See CLAUDE.md §13.1.

## [2.19.1] — 2026-07-08
Fix `roci_save_slug_field()`: the handler only guarded against `$_POST['roci_slug']` being unset, but on REST/Gutenberg/no-input save paths MB Pro substitutes the field's config array as the value — which passed the `isset()` check, was flattened by `sanitize_title()`, and wrote garbage (`autocompletefalsedatalist…`) into `post_name`. The guard now also requires `is_string()`, rejecting the config-array fallback before it can reach `sanitize_title()`. An empty string submission is treated as "no override intended" and leaves the existing `post_name` untouched. See CLAUDE.md §13.1.

## [2.19.0] — 2026-06-27
LocalBusiness schema: added two global Business-tab Theme Settings fields — Schema Image (a media upload storing a URL, mirroring the SEO Default OG Image pattern and wired by the generic `theme-settings.js`) and Price Range (text). Both are emitted into the site-level LocalBusiness JSON-LD as `image` / `priceRange` via conditional-assign guards, omitted when blank. A blank Schema Image intentionally leaves Google's optional "missing image" warning in place as a reminder.

## [2.18.0] — 2026-06-27
Head/schema upgrades: replaced the single free-text business address with five discrete PostalAddress fields (street, locality, region, postal code, country), emitted via `array_filter` so empty properties — and the whole `address` node when all are blank — are dropped. Added a per-page `og:image:alt` Meta Box field (auto-fills from the winning image's attachment alt → meta description → meta title). Raised the meta-title admin entry cap from 60 to 80 (preview/health panels renumbered; no front-end truncation). Added `@id` (`#organization`) to the site-level LocalBusiness JSON-LD node.

## [2.14.1] — 2026-06-14
Move `CONVENTIONS.md` from theme repo root to workspace root (sibling of `CLAUDE.md`). File is documentation only — no PHP, no CSS, no WP hooks affected. Removal from the theme repo keeps deployed files clean; content is preserved outside the repo.

## [2.14.0] — 2026-06-14
Add `inc/blog-shortcodes.php`: seven reusable blog body-module shortcodes (`roci_image`, `roci_pair`, `roci_quote`, `roci_expect`, `roci_notes`, `roci_stats`, `roci_related`). Icon helper `roci_blog_icon()` ships five inline SVGs (anchor, helm-wheel, external-link, wave, info). Both `roci_expect` and `roci_notes` accept a `title=""` attribute for per-instance heading override. Child themes opt in via `roci_register_blog_shortcodes()` and customise labels and icons via `roci_blog_config()`. No SCSS this pass — child themes own all output styling.

## [2.13.3] — 2026-06-09
Add `CONVENTIONS.md`: documents how El Rocinante diverges from standard WordPress — template naming convention, meta/schema architecture, read wrappers, helpers, Fauxlders, archive suppression, and versioning. Companion to `CHANGELOG.md`.

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
