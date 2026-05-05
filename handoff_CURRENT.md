# Handoff Current

## Status
- `committed`

## Date / Window
- Date: `2026-05-05`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD at start of this pass: `dc02779`

## What Was Done This Session
- Rebuilt the live `/schoneweide/` page around a simpler theme-owned Atlas surface instead of the earlier heavy multi-panel Atlas app:
  - kept the page in `themes/industriesalon`
  - replaced the old Atlas template shell in `themes/industriesalon/templates/page-schoneweide.html`
  - switched to the standard landing-page hero/cover structure used on other pages
  - added a quieter hero-side note instead of a bespoke Atlas product intro
- Reworked the Schöneweide Atlas stage into one fixed composition:
  - left filter column
  - right Leaflet map surface extending to the container edge
  - popup info card positioned inside the map, top-right
  - lower live story-card strip fed from the same place dataset
- Simplified the page skin in `themes/industriesalon/assets/css/oberschoeneweide-atlas.css`:
  - removed the previous glassy Atlas-UI treatment
  - reused the shared hero/button/card language already defined globally
  - matched map height to the filter column via the page grid instead of ad hoc height hacks
  - added the Leaflet surface, control, attribution, and marker skin needed for the larger map stage
- Replaced the previous Schöneweide page JS in `themes/industriesalon/assets/js/schoneweide.js` with a smaller runtime:
  - loads the existing `/wp-json/iss-register/v1/atlas` payload
  - renders role and era filters
  - renders markers as real Leaflet markers from geographic coordinates
  - opens/closes the popup card
  - renders six live story cards from current place data
- Switched the live Atlas map from the temporary static SVG path to a real Leaflet basemap:
  - vendored `Leaflet 1.9.4` locally under `themes/industriesalon/assets/vendor/leaflet/`
  - added a bounded CARTO light raster tile layer for the Schöneweide area
  - kept marker rendering, filtering, and popup behavior in the theme-owned runtime
  - removed the no-longer-used temporary theme map image assets before commit
- Cleaned the Schöneweide enqueue path in `themes/industriesalon/functions.php`:
  - kept the existing theme CSS/JS entrypoints
  - enqueued local Leaflet CSS/JS only on `/schoneweide/`
  - reduced the page script config back to the Atlas REST payload URL only
- Fixed template authority so the rebuilt file-backed page actually renders live:
  - page `13251` (`/schoneweide/`) was still assigned to `schoneweide-alt`
  - a stale DB `wp_template` row `page-schoneweide` (`ID 13794`) was still overriding the disk template
  - reset `_wp_page_template` on page `13251` to `default`
  - deleted DB template post `13794`

## Verification
- Active theme rechecked with WP-CLI: `industriesalon`
- Active relevant plugin rechecked with WP-CLI: `industriesalon-schoeneweide-register`
- `themes/industriesalon/functions.php` passed `php -l` inside the `wpcli` container
- `themes/industriesalon/assets/js/schoneweide.js` passed `node --check`
- Live route authority rechecked:
  - `/schoneweide/` now serves `iss-schoneweide-atlas-page`
  - live HTML shows the new disk template markers:
    - `iss-schoneweide-atlas__map-canvas`
    - `iss-schoneweide-atlas-hero-note`
    - `data-iss-schoneweide-stories`
  - localized frontend config now only exposes:
    - `placesUrl`
- Live headless browser verification completed:
  - `/schoneweide/` renders a Leaflet container with zoom controls and attribution
  - headless DOM confirmed loaded `https://{s}.basemaps.cartocdn.com/light_all/...` tiles
  - headless DOM confirmed live `iss-schoneweide-atlas__leaflet-marker` nodes on the map

## Important Notes
- The main blocker was not CSS but WordPress template authority:
  - the page was not using the theme file until the page meta + DB template override were cleared
- The live Atlas now depends on third-party raster tiles:
  - basemap provider: `CARTO`
  - attribution required: `OpenStreetMap` + `CARTO`
- `/home/vladimir/wp/themes/devs/web-img/map.json` was inspected only as a reference:
  - it is a MapLibre/Mapbox vector style, not directly usable by the current Leaflet raster setup
  - it would require a real vector-tile source/key plus a renderer switch to `MapLibre GL JS`
- I did not complete a manual click-through QA after the Leaflet/CARTO switch. The page still needs one real browser pass for:
  - marker density and readability at default zoom
  - popup overlap on desktop/mobile
  - lower story-card rhythm

## Current Worktree
- Clean after committing this Atlas/theme snapshot.

## Next Recommended Steps
- Open `/schoneweide/` in a real browser and do a direct visual QA pass on the live Leaflet map.
- Decide whether the current CARTO basemap is sufficient or whether the project should fund a proper `MapLibre GL JS` migration for the custom `map.json` style.
- Decide whether the lower “Geschichten aus Schöneweide” strip should stay auto-selected from live data or become an explicitly curated subset.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
