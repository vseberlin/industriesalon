# Changelog

## 2026-04-27
- Restored front-page file authority by deleting the live `wp_template` override after syncing current content back to `themes/industriesalon/templates/front-page.html`.
- Reworked front-page discovery CTAs:
  - `In kürzen` cards now use `Weiter` arrow links
  - `Projekte` / `Über uns` media-text CTAs now use `Weiter` text links aligned to the microblock edge
- Added a semantic accent scheme layer for the active theme:
  - wrapper classes `.iss-scheme-red|blue|green|yellow|brown`
  - shared default accent surfaces now read from `--iss-accent`
- Documented scheme usage and default CTA guidance in `themes/industriesalon/style.css`.
- Refactored reusable Industriesalon patterns toward a common CTA/color-switch model:
  - generic discovery CTAs now default to `Mehr` arrow links
  - fixed decorative red/yellow modifiers were removed from generic reusable patterns where they blocked wrapper-based scheme switching
  - explicit request/inquiry actions remain buttons
- Updated plugin discovery CTA defaults:
  - `plugins/iss-fuehrungen/includes/template-tags.php` now outputs `Mehr`
  - `plugins/v1/saas-api/iss-timeline` default CTA label/placeholder now uses `Mehr`
- Committed pre-existing deletions under `plugins/v1/saas-api` and `plugins/v1/saas-api-v1.zip` as part of the user-requested `commit all` snapshot.

## 2026-04-26
- Replaced front-page `Projekte` Custom HTML/PNG microblock icons with theme SVG mask icons in `themes/industriesalon/templates/front-page.html`.
- Applied the same icon cleanup to the front-page `Über uns` media-text section.
- Refactored both front-page media-text sections to an overlay variant using `iss-media-text--overlay-heading`:
  - moved heading blocks inside `.iss-media-text__media`
  - added scoped admin/reuse comments in `themes/industriesalon/assets/css/patterns.css`
- Updated overlay media-card styling in `themes/industriesalon/assets/css/patterns.css`:
  - full-bleed image fill inside the card
  - shared hero/card gradient via `--iss-hero-overlay`
  - fixed rounded-corner clipping across card, overlay, figure, and image
  - disabled pointer capture on overlay heading to avoid blocking header/menu clicks
- Reverted temporary muted-red theme token changes after request.
- Repeatedly diagnosed and removed Gutenberg-created DB template overrides that were taking precedence over theme files:
  - removed `wp_template` `front-page` overrides
  - removed `wp_template_part` `header` overrides
- Synced the current live front-page DB template content back into `themes/industriesalon/templates/front-page.html` so user text corrections are preserved on disk.
- Normalized malformed recovered icon markup while syncing the live front-page template back to disk.

## 2026-04-25
- Committed full repository snapshot state as `6a95f9a` (`chore: snapshot full repository state`).
- Implemented multi-panel off-canvas header menu in active theme `industriesalon` (reference-inspired):
  - tab switcher panels: `Navigation`, `Kalender`, `Info`, `Suche`
  - updated files:
    - `themes/industriesalon/parts/header.html`
    - `themes/industriesalon/assets/js/header.js`
    - `themes/industriesalon/assets/css/patterns.css`
  - committed as `3e20381` (`feat(theme): add multi-panel off-canvas header menu`)
- Refactored `industriesalon-schoeneweide-register` to server-rendered PHP partial layout with JS enhancement only.
- Switched block registration to plugin-root `block.json` and completed block attributes/supports metadata.
- Removed legacy iframe/app-shell dependency by deleting:
  - `assets/register-app.html`
  - `assets/js/register-frontend.js`
  - `assets/css/register-shell.css`
- Added reusable render helpers for featured cards, places cards, then-now cards, and local marker map:
  - `includes/render-register-featured.php`
  - `includes/render-register-list.php`
  - `includes/render-register-map.php`
- Rewrote `assets/js/register-frontend-app.js` to use only local embedded source payload for places/filtering/tabs/detail/research/map rendering.
- Kept feedback submit endpoint and updated backend validation for image contributions (allow `image_attachment_id` or `source_url`, with rights confirmation).
- Expanded mapped place payload fields (`operator`, `developer`, `tenant`, `source_links`) for research/detail usage.
- Created/updated register test page at `http://192.168.2.31:8082/register-test/` (ID `12940`).
- Synced repository with remote and applied a follow-up rollback to skip the risky theme runtime bundle (`functions.php` + injected filter helper) while keeping safe plugin/CSS updates.
- Added post layout selector for Gutenberg posts via `_iss_post_layout` meta (`standard`, `image`, `short`) and frontend body classes.
- Implemented single-post layout variants in theme CSS:
  - `standard`: hero image stays caged in container,
  - `image`: full-width hero with explicit viewport cap (prevents >100vh growth),
  - `short`: compact title/content with hidden large hero.
- Improved single-post content typography/media behavior in `patterns.css` (headings, lists, figures, captions, quotes, tables, responsive alignment handling).
- Purged database template overrides for active theme (`front-page`, `hero-page`, `single`) so disk templates are authoritative.
- Removed local SQL dump copies (`backup.sql`, `db_2026-04-06_10-36.sql`, `backups/db_*.sql`) while leaving live DB volume data untouched.

## 2026-04-24
- Refactored runtime program stack to CPT-first rendering in `iss-programm`.
- Removed stale legacy dynamic block/runtime code from `saas-api`.
- Fixed calendar/timeline source conflicts by enforcing linked-content mapping persistence.
- Timeline mapping save now updates series map + source map and propagates links across series entries.
- Slot/title output now prefers linked content and exposes `content_url` for consumers.
- Removed redirect-based workaround; behavior now depends on stable CPT mapping.
