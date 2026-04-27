# Handoff Current

## Status
- `ready_for_next_session`

## Date / Window
- Date: 2026-04-27
- Timezone: Europe/Berlin

## Branch / Commit
- Branch: `master`
- Current HEAD before final handoff commit: `b462dc2`

## What Was Done This Session
- Kept scope inside `themes/industriesalon` and `plugins/*` and verified active theme/plugin state (`industriesalon`, `iss-programm`, `iss-content-model`).
- Continued the timeline/program refactor onto a single active block path:
  - removed active `timeline`, `timeline-sections`, and `timeline-latest` registrations/files
  - kept `industriesalon/timeline-query` as the main timeline block
  - added `industriesalon/program-cards` as a lean card renderer on top of the shared timeline query layer
- Added `iss-content-model` and wired local CPT content into the existing timeline contract:
  - CPTs: `veranstaltung`, `ausstellung`, `projekt`, `team_member`
  - timeline sync for `veranstaltung` and opted-in `ausstellung`
  - theme templates for the new CPTs
- Added filterable timeline infrastructure:
  - normalized query payload
  - shared REST endpoint
  - per-instance filters for time, month, item type, post type, taxonomy
  - count/empty-state and load-more support
- Fixed severe local page delays caused by overlap-aware timeline queries:
  - `program-cards` now defaults to point-only upcoming queries
  - `timeline-query` now defaults to point-only upcoming queries
  - shared query layer now only enables running-range overlap when `ausstellung` is actually in scope
  - normal tour/event timelines now stay on the fast branch even if the overlap toggle is on
- Confirmed the slow test page was DB-content based, not template-file based:
  - the page used `pages.html`
  - the performance issue came from the block query path, not from the page being stored in DB
- Rebuilt the live `Über uns` page onto the theme-owned `page-ueber-uns` template:
  - merged the previous template draft, live page copy, and local `register.md` notes
  - added/registered `ISS Recognition Split`
  - added reversible `iss-flex-split--reverse` support for alternating tall-media sections
  - deleted the stale DB `wp_template` override after syncing the useful content into the file-backed template
  - assigned the live page to `page-ueber-uns` and changed its slug from `/test/` to `/ueber-uns/`
- Refined the About page rhythm and visuals:
  - taller hero treatment (`80vh` block / `100svh` page hero)
  - wide opener image band
  - asymmetric `Was wir tun` lead card
  - compact archive fact row
  - stronger founding/person callout
  - featured lead team card before the remaining team grid
  - darker closing CTA with one primary action and quieter text links

## Runtime Verification Snapshot
- Active theme: `industriesalon` (`1.1.0`).
- Active plugins checked: `iss-fuehrungen`, `iss-content-model`, `iss-programm`, `saas-api`, others active as listed by WP-CLI.
- Container PHP lint passed for changed `iss-programm` files and new `iss-content-model` PHP files during implementation.
- JS syntax checks passed for:
  - `plugins/iss-programm/blocks/timeline-query/index.js`
  - `plugins/iss-programm/blocks/program-cards/index.js`
  - `plugins/iss-content-model/blocks/content-meta/index.js`
- Performance checks:
  - front page HTTP response stayed fast (`~0.2s`)
  - test page with `program-cards` dropped from about `23.8s` to about `0.17s`
  - shared query runtime for normal tour/event cases now stays around `65–112 ms`
- Theme/template checks:
  - `page-ueber-uns` now resolves from theme source, not a DB override
  - `/ueber-uns/` returns `200`
  - old `/test/` route now returns `404`
  - live About page output confirms the new hero, archive split, recognition copy, and lead-team section

## Open Item
- Browser/frontend QA was reported fine for the checked timeline/program and About-page routes.
- If a real production archive/listing later needs ongoing exhibitions mixed into general upcoming timelines, re-check query cost on production data size.
- If the About page should use different featured imagery for opener vs hero vs pillars, that is now an editorial/content task rather than a layout blocker.

## Suggested Next Step
1. On staging/production, spot-check the new archive/single CPT templates and the `Über uns` page with real content/images.
2. If desired, add real dedicated archive/listing treatment for `team_member` beyond the About-page lead/grid usage.
3. If a real production archive/listing later needs ongoing exhibitions mixed into general upcoming timelines, re-check query cost on production data size.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`.
