# El Rocinante — Conventions & Divergences from Standard WordPress

> **Purpose:** El Rocinante deliberately diverges from standard WordPress conventions in several
> areas. This document records how and why, so future work — new child themes, contractors, or
> fresh Claude Code sessions — doesn't wrongly assume vanilla WordPress behavior.
>
> Companion to `CHANGELOG.md`, which records *what changed when*. This records *how the theme
> works* and *why it is built this way*.

---

## Philosophy

- **Lean, no page builder.** No Gutenberg blocks, no Elementor. Content is field-driven (Metabox)
  and rendered by PHP templates. This keeps markup clean, fast, and fully controlled.
- **The theme is the SEO infrastructure.** All `<head>` meta tags, Open Graph, Twitter Card, and
  schema JSON-LD are written by hand in `header.php`. No SEO plugin owns this output. Metabox is
  field *storage* only — it has zero front-end output of its own.
- **On-page SEO is tier 3.** Priority order: (1) technical correctness — schema, complete meta,
  heading structure, canonical; (2) core web vitals / speed / efficiency; (3) on-page copy and
  keywords. Naming and content decisions follow this hierarchy.
- **Reusable system across clients.** Shared mechanism lives in the parent; per-client
  presentation (brand, colors, copy, CPTs) lives in the child. A pattern is promoted to the
  parent only after it is validated on a real child site.

---

## Template naming

### Current parent-theme state

The parent currently ships only the standard WordPress fallback templates:

```
index.php     — catch-all fallback
single.php    — individual posts (with the theme's custom meta/schema)
archive.php   — CPT and taxonomy archives
search.php    — search results
404.php       — not-found page
header.php    — <head> SEO block + primary nav hook
footer.php    — closing markup
```

There are no `page-home.php`, `home.php`, or `page-blog.php` files in the parent. Those are
currently child-theme files (being built in Fish Potrero).

### Planned convention (intent, not current parent state)

The intent is for `page-home.php` and `page-blog.php` to become parent-provided base templates
that every child inherits and extends — since every site has a homepage and almost always a blog.
Once promoted to the parent, the naming conventions will be:

- **`page-home.php`** — site homepage template, assigned to a static Page with the slug `home`
  in Settings → Reading. Deliberately NOT `front-page.php`. Routing through the page-template
  system gives full control over meta, schema, and field reads via `roci_get_setting()`. `front-
  page.php` bypasses that system and is intentionally absent.
- **`home.php`** — WordPress's reserved blog-index template, repurposed as a **noindexed blog
  archive**: the full paginated post list, `noindex,follow` enforced via `is_home()`. This is NOT
  the primary blog landing — it is the deep-pagination destination that `page-blog.php` links to.
  Using `home.php` here gives free WP pagination without custom rewrite rules.
- **`page-blog.php`** — named template "Blog Landing". The indexable blog landing page: custom
  hero + intro + N most recent posts + "View All" link pointing to the `home.php` paginated
  archive + CTA. Full meta/schema control via `roci_get_setting()`.

### Blog permalink structure

Individual blog posts use a **custom permalink base** configured per child site (e.g.
`/guanacaste-fishing-guide/{post-slug}/` on Fish Potrero), not WordPress's default post
permalinks. This gives the URL a keyword-rich path segment without requiring a CPT.

---

## Meta & schema

All `<head>` SEO output is written by the theme in `header.php`. The theme reads field values
from Metabox and constructs the HTML itself — there is no SEO plugin delegating this.

### Meta tags

| Tag | Per-post source | Site-wide fallback |
|---|---|---|
| `<meta name="description">` | `roci_meta_description` field | `roci_setting('seo', 'default_meta_description')` |
| `<link rel="canonical">` | `roci_canonical` field | `get_permalink()` |
| `<meta name="robots">` | `roci_robots` field | `index, follow` |
| OG title / Twitter title | `roci_meta_title` field | `get_the_title()` |
| OG image | `roci_og_image` field → featured image → `roci_setting('seo', 'default_og_image')` | — |

### Schema outputs

Three distinct schema blocks are output by the theme:

1. **Page-level JSON-LD** — `header.php` echoes the raw JSON stored in the `roci_schema_json`
   Metabox field. The admin enters valid JSON-LD directly into that field; the theme outputs it
   as-is inside a `<script type="application/ld+json">` tag. No schema library; no template.
2. **LocalBusiness JSON-LD** — auto-generated on the front page only, built from Theme Settings
   fields (business name, phone, email, address, social URLs). Assembled in `header.php` via
   `roci_setting('business', ...)` and `roci_setting('social', ...)` calls.
3. **FAQPage JSON-LD** — output by `jw_faq_schema()` in `inc/helpers.php`, called from
   `template-parts/faq.php` in the child theme. Reads the `jw_faq_items` cloneable group from
   postmeta and builds the FAQPage schema from it.

---

## Fields & content

Page content is registered as Metabox field groups. Templates read values through three read
wrappers — never through MB Pro's underlying functions directly.

### Read wrappers

**`roci_get_field( $field_id, $object_id = null )`** (`functions.php`)
Reads `wp_postmeta`. Wraps `rwmb_meta()`. Used for per-post fields: SEO meta title, canonical
URL, robots, OG image, schema JSON, FAQ items. `$object_id` defaults to `get_the_ID()`.

```php
$title = roci_get_field( 'roci_meta_title' );
$canon = roci_get_field( 'roci_canonical', $post_id );
```

**`roci_get_setting( $page, $field, $default = '' )`** (`inc/metabox/metabox-readers.php`)
Reads MB Pro Settings Pages. Used for per-page content fields stored under the Pages submenu
(homepage hero, charter page intro, featured items, etc.). Internally prefixes the page slug with
`roci_page_` to produce the `wp_options` row name — so `roci_get_setting( 'home', 'hero_headline'
)` reads from the option named `roci_page_home`. Child themes that register their own MB Pro
Settings Pages must use this same `roci_page_{$page}` convention.

```php
$headline = roci_get_setting( 'home', 'hero_headline' );
$image    = roci_get_setting( 'home', 'hero_image', 0 );
$items    = roci_get_setting( 'home', 'featured_items', array() );  // clone group → array
```

**`roci_setting( $group, $key, $default = '' )`** (`inc/theme-settings/settings-register.php`)
Reads from the legacy custom Theme Settings admin page. Calls `get_option( 'roci_' . $group )`.
Used for site-wide settings: business info, social URLs, SEO defaults, integrations.

```php
$name = roci_setting( 'business', 'name', get_bloginfo( 'name' ) );
$ga   = roci_setting( 'integrations', 'ga_id' );
```

### Why three wrappers

Postmeta, MB Pro Settings Pages, and the legacy Theme Settings options are three distinct storage
layers with different semantics and cache profiles. One wrapper per layer keeps call sites
self-documenting — the function name tells you exactly where the data lives.

---

## Helpers (`inc/helpers.php`)

Reusable output utilities with no WordPress-context dependency. All prefixed `jw_` per the
project's naming convention (see CLAUDE.md §4). All functions return HTML strings unless noted.

```php
jw_get_webp_url( $attachment_id, $size = 'full' )
```
Resolves the WebP URL for a given attachment ID and size. Returns `null` if no WebP file exists
on disk. Used internally by the picture helpers.

```php
jw_picture( $attachment_id, $size = 'full', $alt = null, $class = '', $loading = 'lazy' )
```
Standard content image as a `<picture>` tag with WebP `<source>` + fallback `<img>`. Width and
height attributes pulled from attachment metadata for CLS prevention. Pass `$alt = ''` for
decorative images; pass `null` (or omit) to fall back to the media library alt. Use
`$loading = 'eager'` for any above-the-fold / LCP image.

```php
jw_hero_picture( $desktop_id, $mobile_id, $alt = null, $class = '', $loading = 'eager' )
```
Art-directed hero `<picture>`. Desktop crop activates at `min-width: 768px`; mobile crop is the
`<img>` default. Defaults to `eager` loading since heroes are always LCP candidates.

```php
jw_bunny_video( $library_id, $video_id, $poster_id = 0, $class = '' )
```
Clean Bunny Stream iframe embed inside a wrapper `<div>`. No YouTube chrome, no third-party
branding. Use the `.jw-video-embed` SCSS pattern for responsive 16:9 ratio.

```php
jw_faq_schema( $post_id = null )
```
Echoes a FAQPage JSON-LD `<script>` block built from the `jw_faq_items` cloneable group. Outputs
nothing if no items are found. **Echoes directly — do not `echo` the return value.** Called from
`template-parts/faq.php`, not directly in page templates.

```php
jw_link_atts( $url )
```
Returns `' target="_blank" rel="noopener noreferrer"'` for external URLs (`wa.me` + cross-host
`http(s)`), or `''` for everything else (`mailto:`, `tel:`, relative, same-host). Drop it inline
into `<a>` tags: `<a href="..."<?php echo jw_link_atts( $url ); ?>>`.

---

## Media — Fauxlders

`inc/folders/` registers a custom hierarchical folder system called **Fauxlders** — three
WordPress taxonomies that power a sidebar folder tree in the admin:

| Taxonomy slug | Applied to | Admin location |
|---|---|---|
| `roci_media_folder` | `attachment` | Media Library |
| `roci_page_folder` | `page` | Pages list |
| `roci_post_folder` | `post` | Posts list |

All three are hierarchical, so parent/child folder nesting works natively via WordPress's built-in
term management. Fauxlders is **media and content organization only** — not a general-purpose
taxonomy system for CPTs. See CLAUDE.md §15 for the design boundary.

---

## Archive suppression

Author, date, and default taxonomy (category + tag) archives return 404 by default. Enforced via
a `pre_get_posts` hook in `inc/archive-suppression.php` that calls `$query->set_404()`.

Each type is independently filterable so child themes can re-enable as needed:

```php
// Re-enable author archives for this child theme:
add_filter( 'roci_suppress_author_archive',   '__return_false' );

// Re-enable date archives:
add_filter( 'roci_suppress_date_archive',     '__return_false' );

// Re-enable category + tag archives:
add_filter( 'roci_suppress_taxonomy_archive', '__return_false' );
```

CPT archives are unaffected — those are controlled per-CPT via `has_archive` at registration.

---

## Versioning & changelog

- **Per-file `Version:` headers.** Each file tracks its own version independently. Bump the
  version in the file's docblock whenever that specific file is modified. Per-file versions are
  file-local odometers — do not normalize them across files.
- **Root `CHANGELOG.md`** holds all notable changes to El Rocinante, newest entries first.
- **`style.css`** carries only its `Version:` header field. No changelog block. The version in
  `style.css` is bumped on every commit to the theme repo; it is what Git Updater and WP admin
  display.

---

## Hard rules (summary)

- Never touch FatLabsLLC paths.
- Never navigate above the `El Rocinante/` outer workspace folder.
- Atomic commits; stop and report before committing; do not auto-push.
- Full Windows paths in `git add` commands.
- Escape on output (`esc_attr`, `esc_url`, `esc_html`). Sanitize on save.
- Never hand-edit `dist/css/` — compiled output, edit SCSS source instead.

> When any section above changes, update this doc alongside `CHANGELOG.md`.
