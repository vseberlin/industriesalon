# Industriesalon Theme Changelog

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
