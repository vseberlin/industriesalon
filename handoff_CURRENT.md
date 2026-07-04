# Current Handoff

Updated: 2026-07-04

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Führung JSON is the active presentation path for hero/stage, narrative
  gestures, and `atlas_map`; the legacy tour hero-gallery block/path was
  removed. Booking, prices, dates, facts, route stations, graph relations, and
  related cards remain outside JSON gesture bodies.
- The Führung route constructor is relation-owned. Editors unlock a route,
  edit draft stations, can restore draft deletions, and explicitly
  publish-and-lock before canonical relation rows and graph/map projections
  update. Normal WordPress updates no longer carry stale route station fields.
- Route stations for `elektropolis-tour` and `familienrallye` were restored from
  backup and captured in narrow SQL artifacts under `ops/sql/2026-07-04-*`.
- Booking CTAs for Führung and timeline slots use the shared occurrence calendar
  and commerce request/payment modal. Führung has structured
  `booking_price_cents`; `price_note` stays editorial display copy.
- Shared JSON gesture vocabulary was reduced: `image_wall` and `vollbild` are
  legacy aliases for `galerie` layouts (`wall` and `viewport`), and `massstab`
  is a legacy alias for `facts`. `material` means files and links only.

## Preserve

- Public presentation stays in the theme; format registration and editor
  contracts stay in `iss-content` / `iss-editorial`; route relations and Atlas
  map contracts stay in `iss-relations`; static map/frontend behavior stays in
  `iss-frontend`.
- Do not reintroduce template fallback presentation for enabled Führung JSON.
  Rebuild fallbacks later only as explicit, cloned hard-fallback paths.
- Do not move booking, occurrence slots, commerce amounts, route stations, or
  graph projection state into JSON gesture bodies.
- CSS work must be migration-positive: drain old/page-specific selectors into
  tokens, primitives, renderer contracts, skins, or scoped compatibility. Do not
  hide structural issues with a second parallel selector system.

## Next Action

- Browser-UAT the committed Führung checkpoint on representative tours:
  Electropolis, Familienrallye, and one tour without full route/stage data.
  Check hero stage height, gallery aspect ratio, booking modal, Atlas route map,
  draft route preview, and mobile overflow.
- After staging deploy, apply/review only the intentional route-station SQL
  artifacts if the target needs the restored station state. No upload artifact
  is part of this checkpoint.
- Continue the active TODO follow-ups for JSON editor maintainability:
  extract picker-heavy code from `plugins/iss-editorial/assets/admin.js` and
  keep gesture vocabulary changes in the registry/renderer contract, not CSS.

## Verified

- PHP lint for touched `iss-content`, `iss-editorial`, route/booking, relation,
  occurrence, frontend, and theme renderer files.
- JS syntax/lint for `plugins/iss-editorial/assets/admin.js`,
  `plugins/iss-editorial/assets/set-media-picker.js`, and route/media frontend
  scripts where touched.
- Stylelint for `themes/industriesalon/assets/css/single-tour.css`.
- `git diff --check`.
- WP-CLI registry/alias checks confirmed exposed formats use `galerie`/`facts`
  while old `image_wall`, `vollbild`, and `massstab` payloads normalize to the
  canonical sections.
- WP-CLI route/booking checks covered route draft behavior, route-station
  restore/projection, and occurrence-backed booking slot adapters.

## Commit State

- Code, docs, templates, CSS, JS, and narrow SQL restore artifacts are intended
  for the checkpoint commit.
- No uploads artifact was created or required.
- Confirm final commit/push state with Git after closeout.
