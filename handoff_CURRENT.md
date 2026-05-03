# Handoff Current

## Status
- `committed`

## Date / Window
- Date: `2026-05-03`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD before final commit: `d88fd8b`

## What Was Done This Session
- Preserved manager-authored DB template overrides without touching disk templates:
  - exported the live custom `wp_template` copies for `industriesalon`
  - committed them under `themes/industriesalon/db-template-copies/2026-05-03-susanne-changes`
  - commit: `d88fd8b` message `susanne changes`
- Restored disk template authority by deleting the live DB overrides for:
  - `front-page`
  - `page-veranstaltungen`
  - `page-ausstellungen`
  - `page-fuehrungen`
  - `page-projekte`
  - `page`
  - `page-publikationen`
- Continued the `industriesalon-steuerung` cleanup:
  - removed obsolete editor block registrations:
    - `industriesalon/status`
    - `industriesalon/exceptions`
    - `industriesalon/prices`
    - `industriesalon/mission-statement`
  - kept the editor block surface to:
    - `industriesalon/field`
    - `industriesalon/hours`
    - `industriesalon/visit-info`
    - `industriesalon/contact`
    - `industriesalon/faq`
- Changed opening-hour semantics so exceptional open-day cases remain semantically `open` and carry their detail via `kind`.
- Rebuilt `industriesalon/visit-info` into a real info panel driven by plugin data:
  - editable `kicker`, `title`, `intro`
  - rows for:
    - address
    - Besuchszeiten
    - Bürozeiten
    - ÖPNV
    - Barrierefreiheit
  - panel controls for:
    - accent color
    - light/dark surface
- Removed the old visitor-card-based `visit-info` render path and related dead helper methods.
- Added theme styling in `themes/industriesalon/assets/css/patterns.css` so the plugin panel uses the existing `iss-info-panel` pattern language.
- Tightened the panel visual direction:
  - white light surface
  - smaller radius
  - soft elevation
  - no visible border
  - Maps action now uses `iss-action-link`

## Verification
- Active theme confirmed: `industriesalon`
- DB template overrides for the target pages were flushed successfully.
- `get_block_template(...)->source` now resolves to `theme` for the restored page templates.
- `industriesalon-steuerung.php` passes PHP syntax check through container `php -l`.
- `assets/blocks.js` passes `node --check`.
- Runtime registration check confirms only the intended dynamic blocks remain:
  - `industriesalon/field`
  - `industriesalon/hours`
  - `industriesalon/visit-info`
  - `industriesalon/contact`
  - `industriesalon/faq`
- Runtime render check confirms `industriesalon/visit-info` now emits:
  - `iss-info-panel`
  - `iss-control-info-panel`
  - icon-led rows
  - right-aligned Maps action link
  - accessibility badge slot

## Important Notes
- The accessibility/certification badge asset was not wired yet.
- A legacy uploads candidate exists (`logo-pikto_500_100_c.jpg`) but was not confirmed as the correct mark.
- Next step after asset upload:
  - replace the inline placeholder accessibility symbol in `industriesalon-steuerung.php`
  - use the uploaded media-library image for the `Barrierefreiheit geprüft` badge
- Unrelated untracked theme files still exist and were intentionally left alone:
  - `themes/industriesalon/templates-backup.zip`
  - `themes/industriesalon/templates/page-das-museum.html`
  - `themes/industriesalon/templates/page-videos.html`

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
