# Handoff Current (Theme)

## Status
- `committed`

## Date / Window
- Date: `2026-04-30`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD before final handoff commit: `7ed1f92`

## What Was Done This Session
- Recovered disk authority for remaining theme templates by flushing DB overrides and syncing `page-ueber-uns` back to file before deleting its DB copy.
- Normalized confusing template names and removed redundant archive templates:
  - `page-ausstellungen.html`
  - `page-publikationen.html`
  - `fuehrungen-landing.html`
  - removed `archive-projekt.html` and `archive-veranstaltung.html`
- Made the off-canvas menu durable against Gutenberg header breakage:
  - shell lives in `assets/menu-shell.html`
  - rendered globally from `functions.php`
  - trigger remains in `parts/header.html`
  - `assets/js/header.js` now binds to the global shell
  - `style.css` got menu sizing/layout refinements plus filemtime cache-busting
- Added new Verein landing page support in the live theme:
  - `templates/page-verein.html`
  - scoped `iss-verein-*` styles in `assets/css/patterns.css`
- Repaired the rebuilt Archiv landing after style drift:
  - added missing `iss-archive-*` selectors for the newer mixed-layout template
  - fixed dark-section text color collisions
  - removed the inherited `section--alt` red top line from the object panel
  - restored padding on the dark exhibitions band
  - removed the extra red halo from the Zeitzeugen lead

## Runtime Verification Snapshot
- Active theme: `industriesalon`
- `page-verein` template is registered
- `page-verein.html` parses in WordPress
- `page-archiv` is disk-backed, not DB-backed
- archive template/style mismatch check returned zero missing archive selectors

## Open Item
- `page-verein.html` is ready, but no local `/verein/` page exists yet in this database snapshot.
- `industriesalon-schoeneweide-register` still needs a later architecture pass to remove or refactor the remaining legacy Atlas/register app surface and keep plugin scope focused on local data, review, feedback, and research-only interfaces.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/themes/industriesalon/handoff_CURRENT.md`
