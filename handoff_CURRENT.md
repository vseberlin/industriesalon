# Handoff Current

## Status
- `ready_for_next_session`

## Date / Window
- Date: `2026-04-30`
- Timezone: `Europe/Berlin`

## Scope
- Worked in:
  - `themes/industriesalon`
  - `plugins/iss-content-model`
  - `plugins/iss-programm`
  - `plugins/industriesalon-notices`

## What Was Done
- Extended the existing `ausstellung` content model instead of creating a second CPT:
  - added taxonomies `ausstellung_typ`, `sammlungsbereich`, `industrieort`
  - seeded the agreed default terms
  - removed the visible duplicate `Dauerausstellung` checkbox path from admin
  - exposed taxonomy-derived values in the single exhibition meta block
- Fixed exhibition timeline syncing so archive and landing filters can actually find `ausstellung` posts:
  - `ausstellung` timeline sync now defaults to enabled like `veranstaltung`
  - `Dauerausstellung` can sync without manually entered dates
  - permanent exhibitions get fallback running dates for timeline use
  - taxonomy selection keeps legacy `iss_is_permanent` synced
- Reworked the shared `iss-programm` filter layer for exhibitions:
  - taxonomy UI now supports multi-select
  - added preset-button support for the quick landing overview
  - added a shared filter reset button
  - optimized `past` / `Archiv` exhibition queries by bypassing the heavy mixed-date fallback when the query is clearly `ausstellung` only
- Turned `/ausstellungen/` into a narrative archive landing in `themes/industriesalon/templates/archive-ausstellung.html`:
  - quick overview with preset filters at the top
  - Elektropolis context section
  - full-width panorama transition band
  - Kaiserzeit 4-card panel strip
  - DDR 4-card panel strip in `Nach 1945`
  - `Ausstellung im Raum`
  - rebuilt `Draußen` upper block in the `Woran wir arbeiten` language from `Über uns`
  - route cards below kept as a separate lower layer
  - closing research/archive filter section reframed as a research register
- Extracted the older workstation deep-dive into a reusable theme pattern:
  - created `themes/industriesalon/patterns/iss-section-ausstellung-workstation.html`
  - registered `industriesalon/ausstellung-workstation`
  - later removed that section from the landing because it became redundant once the Kaiserzeit/DDR card systems were in place
- Improved landing performance:
  - identified image payload, not normal filter queries, as the main page-load issue
  - reduced the three biggest static offenders by switching template references to generated smaller variants:
    - hero
    - panorama
    - outside/tour image
  - removed the expensive live Kaiserzeit image sepia filter and replaced it with a cheaper warm overlay
- Wired the archive hero note to `industriesalon-notices`:
  - replaced the hardcoded archive hero note with `industriesalon/notice-banner`
  - added a dedicated plugin area `ausstellungen_banner`
  - confirmed the block wiring works, but it renders empty until an active notice uses that new area
- Deleted the stale DB `wp_template` override for `archive-ausstellung` so the theme file is authoritative again

## Verification
- Active theme remained `industriesalon`
- `themes/industriesalon/templates/archive-ausstellung.html` still parses to `6` top-level blocks after the landing rebuilds
- PHP lint passed in container for:
  - `plugins/iss-content-model/includes/admin.php`
  - `plugins/iss-content-model/includes/meta.php`
  - `plugins/iss-content-model/includes/timeline-sync.php`
  - `plugins/iss-programm/includes/timeline-render.php`
  - `plugins/iss-programm/includes/timeline-query.php`
  - `plugins/industriesalon-notices/industriesalon-notices.php`
- `node --check` passed for `plugins/iss-programm/assets/timeline-query.js`
- Timeline filter timings at end of session:
  - quick `Dauerausstellung` preset query: about `4.3 ms` per run
  - full `all` exhibition archive query: about `4.4 ms` per run
  - optimized `past` exhibition archive query: about `2.9 ms` per run after the dedicated `ausstellung` fast path

## Important Notes
- The hero banner on `/ausstellungen/` is now plugin-driven via area `ausstellungen_banner`
- For that banner to appear, an active notice must exist with:
  - `Bereich = Ausstellungen (Banner)`
  - `Skin = Landing`
  - `Hinweis anzeigen = on`
  - `Sichtbar für = Öffentlich`
- Current `selected` notice scope is singular-object based and does not behave like a page picker for the `ausstellung` archive
- The biggest remaining frontend cost is still static image payload if the page is expanded further, not the filter/query logic

## Suggested Next Step
1. Create a real active notice for `ausstellungen_banner` in admin and confirm the hero slot output on `/ausstellungen/`.
2. If the route cards in `Draußen` still need adjustment, continue there first; that was the last active visual refinement area at session end.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
