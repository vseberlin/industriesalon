# Industriesalon Theme Changelog

## 2026-04-28
- Added a new front-page research/object contrast section for the Teilchendetektor:
  - pattern: `patterns/iss-section-object-highlight.html`
  - registration: `functions.php`
  - insertion: `templates/front-page.html`
  - styles: `assets/css/patterns.css`
- Added theme-owned detector assets for frontend use:
  - `assets/img/teilchendetektor/teilchen.png`
  - `assets/img/teilchendetektor/teilchen-back.png`
  - `assets/img/teilchendetektor/note.png`
- Extended the detector section to show the back side of the object next to the handwritten note.
- Updated `templates/page-ueber-uns.html` team query loop from 3 to 4 columns.
- Tightened `Über uns` team-card spacing in `assets/css/ueber-uns.css` for a cleaner 4-up row.
- Removed the page-wide `iss-page--compact` wrapper class from the file-backed `Über uns` template.

## 2026-04-27
- Restored front-page disk authority after syncing current live content back into `templates/front-page.html` and removing the DB `front-page` template override.
- Replaced remaining front-page discovery pills with arrow/text links:
  - `In kürzen` cards now use `Weiter`
  - `Projekte` / `Über uns` media-text rows now use `Weiter`
- Added a semantic accent scheme layer in `style.css`:
  - wrapper classes `.iss-scheme-red|blue|green|yellow|brown`
  - default shared accent surfaces now follow `--iss-accent`
- Added top-level CSS documentation for scheme wrappers and default CTA usage.
- Refactored reusable pattern files to prefer common color switching and low-chrome discovery CTAs:
  - generic discovery CTAs now default to `Mehr` arrow links
  - transactional request/inquiry actions remain buttons
  - removed fixed decorative red/yellow modifiers from generic reusable patterns where they blocked wrapper-based color switching
- Added reusable `.iss-action-link` helper for default discovery CTAs.

## 2026-04-26
- Moved publication page rendering into native Gutenberg theme templates:
  - `templates/archive-publication.html`
  - `templates/single-publication.html`
- Added reusable editorial pattern for publication intros:
  - `patterns/iss-publications-intro.html`
- Registered the new publications intro pattern in `functions.php`.
- Added publications layout support for the new `iss-publications` plugin:
  - publication feature layout in `assets/css/patterns.css`
  - publication archive/single layout styles in `assets/css/patterns.css`
- Included section tint/alt styling refinements in `style.css`.
- Added revised publications/payment architecture proposal:
  - `publications-commerce-refactor-revised.md`

## 2026-04-25
- Added reference-inspired multi-panel off-canvas header menu:
  - panel tabs: `Navigation`, `Kalender`, `Info`, `Suche`
  - panel markup and search form in `parts/header.html`
  - panel switching logic in `assets/js/header.js`
  - tab/panel styles in `assets/css/patterns.css`
- Committed as `3e20381` (`feat(theme): add multi-panel off-canvas header menu`).
- Added post layout selector for posts in Gutenberg (`standard`, `image`, `short`) via `_iss_post_layout`.
- Added frontend body class mapping for post layout variants (`iss-post-layout-*`).
- Implemented layout-specific single post hero behavior:
  - `standard`: image stays caged in container
  - `image`: full-width hero with viewport cap (prevents oversize >100vh)
  - `short`: compact hero/content mode without large hero image
- Improved single-post content styling in `assets/css/patterns.css`:
  - refined headings/paragraph/list rhythm
  - improved figure/caption/quote/table rendering
  - better responsive handling for align left/right/wide/full content
- Removed redundant single-post image/align overrides from `assets/css/overrides.css`.
- Purged `wp_template` DB overrides for `front-page`, `hero-page`, and `single` so disk templates are authoritative.
