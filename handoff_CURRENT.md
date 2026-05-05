# Handoff Current

## Status
- `committed`

## Date / Window
- Date: `2026-05-05`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD before final commit: `d74f0e7`

## What Was Done This Session
- Added the first shared place relation layer across Atlas, events, tours, posts, and future archive content:
  - new plugin `plugins/iss-relations`
  - relation meta source of truth `iss_related_places`
  - hidden query/index taxonomy `iss_place_ref`
  - editor metabox and sync helpers
  - dynamic block `iss/related-content`
- Mirrored the WF archive and museum-digital material into local archive entities instead of leaving them as remote-only HTML:
  - new plugin `plugins/iss-wf-import`
  - local CPTs:
    - `archivbeitrag`
    - `archivsammlung`
    - `archivobjekt`
  - localized media import, provenance meta, collection/object relations, and repair tooling for empty museum-digital stubs
  - public routes and theme templates for archive collections and archive objects
- Extended external archive/image discovery:
  - kept Wikimedia Commons import flow in `industriesalon-schoeneweide-register`
  - added Deutsche Digitale Bibliothek as discovery-only source
  - added Europeana API-backed discovery using the local key
  - kept DDB/Europeana as review/discovery sources, not direct local import paths
- Refactored the active theme/plugin UI ownership so the theme owns presentation and plugins keep structure/runtime:
  - split page/single-specific CSS out of the overloaded shared pattern sheet
  - added dedicated page and single stylesheet bundles in `themes/industriesalon/assets/css`
  - normalized global CTA/button/link styling onto one industrial theme system
  - moved timeline/calendar/tour/facts visual skins out of plugin CSS and into theme-owned skin files
  - normalized `/ausstellungen/` and `/veranstaltungen/` cards onto one shared card family in `cards.css`
  - removed the last exhibition preset HTML hack by rendering preset buttons from the timeline-query block/plugin
- Created local recovery artifacts for the now much richer content/media state:
  - DB dump
  - WXR export
  - uploads archive
  - checksum manifest under `/home/vladimir/wp/backups`

## Verification
- Active theme confirmed: `industriesalon`
- Active plugins rechecked in the final CSS/UI pass:
  - `iss-programm`
  - `iss-fuehrungen`
- Live route checks were repeatedly verified during the session on:
  - `/ausstellungen/`
  - `/veranstaltungen/`
  - `/kalender/`
  - `/archivsammlungen/`
  - `/archivobjekte/`
- Shared-card normalization was verified live:
  - `/ausstellungen/` `Draussen` route cards and `/veranstaltungen/` `Formate` cards now both render `iss-small-card iss-small-card--split`
- Timeline-query preset buttons on `/ausstellungen/` are now plugin-rendered instead of raw page HTML
- Archive object stub repair completed successfully so the local museum-digital mirror no longer consists of empty shell posts

## Important Notes
- `plugins/iss-relations` and `plugins/iss-wf-import` are now real local infrastructure in this repo; they are not temporary experiments.
- The new CSS split is theme-owned:
  - page/single bundles live in `themes/industriesalon/assets/css`
  - plugin CSS for `iss-programm` and `iss-fuehrungen` was reduced toward structure/state only
- The remaining visible placeholder banner content on `/ausstellungen/` is unrelated to the card/template cleanup; it comes from the notice/banner content layer.
- Backup snapshot from this session exists in `/home/vladimir/wp/backups` with timestamp `2026-05-05_12-53`.

## Next Recommended Steps
- Continue editorial curation on top of the new relation/archive layer:
  - review archive/object place suggestions
  - approve obvious links into `iss_related_places`
- Run a calmer responsive QA pass across the newly split page/single stylesheets and remove any remaining selectors that still belong in the theme skin rather than shared pattern CSS.
- Resolve the remaining notice/banner placeholder content if `/ausstellungen/` stays a public-facing landing.
- If external discovery stays important, tune DDB/Europeana ranking and provider filtering rather than adding more ad hoc source-specific UI.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
