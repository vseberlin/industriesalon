# Current Handoff

Updated: 2026-06-29

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- SuperSaaS timeline sync now uses a staging/workbench layer:
  `wp_iss_supersaas_slots` stores imported slots from the public schedule and
  `Salonbelegung`, while `wp_iss_occurrence_series` remains the canonical
  series mapping table.
- The sync workbench can search/sort slots, show descriptions, map/remap or
  ignore Salonbelegung event rows, and map recurring event series to one
  canonical Veranstaltung source.
- Repair-Café was collapsed to one canonical Veranstaltung (`26813`): generated
  duplicate event shells were moved to trash, `event:repair-cafe` maps to that
  post, and 12 dated SuperSaaS event occurrences now project from it. Replay
  artifact: `ops/sql/2026-06-29-repair-cafe-canonical-event-series.sql`.
- Programme timelines use the existing `industriesalon/timeline-query`
  renderer. Teaser/upcoming grouped Führung rows now group by source post and
  open a centralized month-grouped slot picker once a group has 2+ dates.
  Elektropolis therefore renders as one front-page row with `Termin wählen`
  instead of a long inline occurrence list.
- The active homepage is still DB-backed: `front-page` is a custom
  `wp_template` override. The client front-page experiment remains review
  state; rollback baseline is
  `ops/sql/2026-06-29-frontpage-baseline.sql`.
- The front-page hero upload family is packaged as
  `ops/uploads/2026-06-29-frontpage-hero-media.tar.gz` and was extracted on
  staging after deployment.

## Preserve

- Keep the data/presentation boundary: `iss-occurrences` owns occurrence
  storage/import/mapping, `iss-frontend` owns the timeline and picker renderer,
  theme CSS owns the visual skin, and `iss-commerce-lite` owns request writes.
- Do not create another booking/calendar storage layer. The picker reuses
  existing SuperSaaS occurrence rows and `.js-is-tour-slot-trigger` booking
  flow.
- Do not delete the front-page DB override while the client is testing variants.
- Leave unrelated local untracked files out of Git unless explicitly requested:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- If moving the local Repair-Café canonical content state to another target,
  review and replay
  `ops/sql/2026-06-29-repair-cafe-canonical-event-series.sql` after code deploy.
- Remaining SuperSaaS audit warnings are not blockers: unmapped inert series
  currently have zero occurrence rows, and `Stadtrallye für Erwachsene` is
  mapped but has no future SuperSaaS rows.
- Continue the separate Veranstaltung public-booking render TODO when the
  timeline/SuperSaaS checkpoint is settled.

## Verified Locally

- `php -l` for edited PHP files in `iss-content`, `iss-frontend`, and
  `iss-occurrences`.
- `node --check` for edited timeline JS and block editor JS.
- `git diff --check`.
- SQL artifact replayed locally through the Docker WP-CLI container.
- `wp iss-occurrences sync`, `verify`, `drift-check`, and `supersaas-audit`.
- Focused DB checks confirmed Repair-Café: canonical post `26813` published,
  duplicate posts `26805`, `26808`, `26810`, and `26812` in trash, 12 mapped
  staged slots, and 12 public SuperSaaS event occurrences.
- Server-rendered front-page timeline check: 1 picker trigger, 0 inline grouped
  occurrence details, German month labels.
- Playwright desktop/mobile checks for `/`: Elektropolis picker opens, shows
  64 dates with 25 bookable slots and 39 disabled sold-out slots, hands off to
  the existing booking modal, and has no mobile horizontal overflow.

## Verified On Staging

- Staging repo `/srv/industriesalon/stage/repo` fast-forwarded to `befc31e`.
- `ops/uploads/2026-06-29-frontpage-hero-media.tar.gz` checksum verified and
  extracted into staging uploads through the WordPress container.
- Front-page hero image
  `/wp-content/uploads/2026/06/2021-04-22-Sven-Bock-Aussen-Industriesalon-05-hero-scaled.webp`
  returns HTTP 200.
- `wp iss-occurrences sync`, `verify`, `drift-check`, and `supersaas-audit`
  completed on staging after deployment. One stale 2022 WP-origin occurrence
  row with the old `veranstaltung` source type was removed after a staging DB
  backup; final `verify` and `drift-check` passed.
- Staging DB backup before occurrence sync:
  `/srv/industriesalon/stage/backups/stage-db-before-12466ac-occurrence-sync-20260629-204357.sql.gz`.

## Commit State

- Final shared checkpoint is `561b41a`
  (`Document staging front-page hero deploy`), with the upload artifact commit
  directly before it at `befc31e`.
- Local repo is at `561b41a`; staging runtime was verified after extracting the
  `befc31e` upload artifact.
