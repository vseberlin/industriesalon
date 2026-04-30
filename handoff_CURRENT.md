# Handoff Current

## Status
- `committed`

## Date / Window
- Date: `2026-04-30`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD before final handoff commit: `7ed1f92`

## What Was Done This Session
- Continued the `industriesalon` theme cleanup and authority recovery:
  - flushed remaining DB `wp_template` overrides
  - synced `page-ueber-uns` back to disk before removing its DB override
  - removed stale template/archive files that were not used anymore
- Normalized landing-template naming in the active theme:
  - `archive-ausstellung.html` -> `page-ausstellungen.html`
  - `archive-publication.html` -> `page-publikationen.html`
  - `page-a-fuhrungen.html` -> `fuehrungen-landing.html`
  - removed redundant `archive-projekt.html` and `archive-veranstaltung.html`
- Fixed the `/publikationen/` slug collision in `plugins/iss-publications/includes/cpt-publication.php` by disabling the CPT archive while preserving single publication URLs.
- Hardened the off-canvas menu against Gutenberg header edits:
  - moved shell/overlay markup out of the editable header part into `assets/menu-shell.html`
  - rendered that shell from `functions.php`
  - updated `parts/header.html`, `assets/js/header.js`, and `style.css`
  - refined drawer layout, bottom contact row, spacing, and live stylesheet cache-busting
- Added a new theme-native Verein landing template:
  - `themes/industriesalon/templates/page-verein.html`
  - scoped styles in `themes/industriesalon/assets/css/patterns.css`
  - structure: hero, quick-entry grid, longread, action cards, organisation block, legal/documents register
- Repaired the rebuilt Archiv landing styling:
  - matched the newer archive template markup to missing `iss-archive-*` selectors
  - restored discovery lead, dark exhibition band, company cards, research list, and CTA styling
  - resolved remaining dark-section text collisions and removed the stray `section--alt` red rule from the object panel

## Verification
- Active theme remained `industriesalon`
- `page-verein.html` is recognized by WordPress as `page-verein`
- `page-verein.html` parses successfully in WordPress
- `page-archiv` has no DB `wp_template` override
- archive template/style mismatch check returned `0` missing `iss-archive-*` selectors

## Important Notes
- There is still no local page with slug `verein` in this DB snapshot, so `page-verein.html` is ready but not routable until that page exists.
- This commit includes the current outstanding `themes/industriesalon` worktree plus the earlier `iss-publications` route fix because the user asked to `commit all`.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
