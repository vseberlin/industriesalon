# Current Handoff

Updated: 2026-07-03

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Single Führung rendering now uses the shared JSON gesture direction:
  `bildbuehne` owns hero image/text/gallery only, while booking, dates, facts,
  route stations, Atlas map fallback, and related cards remain outside that
  gesture body.
- Shared `gestures.css` is the renderer-contract layer for JSON gestures.
  Single-tour CSS now consumes scoped gesture variables and route-specific
  overrides instead of page-only one-off rules where this checkpoint touched
  the surface.
- The compact hero gallery uses shared viewport-gallery JavaScript from
  `iss-frontend`; clicks update the defined hero viewport rather than only the
  thumbnail strip.
- `iss/atlas-map` is registered as the shared static-map gesture/block.
  Registered variants include `place-locator`, `map-only`, and `tour-route`.
- Führung JSON exposes `atlas_map` with treatment `atlas-map.tour-route`. The
  `single-fuehrung` template keeps an `iss/atlas-map` fallback block and
  suppresses it when enabled Führung JSON contains an `atlas_map` section.
- The `tour-route` Atlas variant uses internal marker-box ratio/crop fitting:
  far-left/far-right and top/bottom markers define the crop with registry-owned
  padding, and the route line is drawn by the shared static-map renderer.
- Landing JSON has an initial `atlas_map` gesture path, but current landing
  source/behavior is intentionally tracked as follow-up before broad use.

## Preserve

- Do not move booking, date selection, prices, duration, meeting point, route
  stations, or commerce behavior into JSON gesture bodies.
- Keep map fit/source/ratio/line details registry-owned internal renderer
  options. Editors should choose stable gestures/treatments, not raw map
  fitting knobs.
- Keep public presentation in the theme, format registration in `iss-content`,
  relation and map contracts in `iss-relations`, and static map rendering in
  `iss-frontend`.
- Keep legacy map blocks as fallback/delegation paths while draining old slice
  and place-map variants into registered gesture variants.

## Next Action

- Add the TODO-listed regression check for marker-box fit complexity so route
  map marker padding is tested directly.
- Sort out landing `atlas_map` behavior: decide when landing pages use
  current-page relations, manual place recipes, or a dedicated source picker.
- Migrate or create a real Führung `bildbuehne` / `atlas_map` JSON section and
  browser-check desktop/mobile on staging.
- Continue the calendar/intake and broader JSON editor UAT follow-ups listed in
  `TODO.md`.

## Verified

- PHP syntax:
  - `plugins/iss-frontend/modules/static-maps/includes/render.php`
  - `plugins/iss-relations/includes/blocks.php`
  - `plugins/iss-content/includes/editorial.php`
  - `themes/industriesalon/includes/tours-render.php`
- PHPCS target checks for the touched PHP/template map and Führung files.
- ESLint for the related-content block editor and shared gallery scripts.
- Stylelint for `gestures.css` and `single-tour.css`.
- `wp iss-relations static-map-contract-check`.
- Browser metrics for `/fuehrungen/elektropolis-tour/`:
  - atlas route gesture present
  - route line present
  - no horizontal overflow
  - marker fit lands around `x=8..92` and `y=12..87.9`
  - desktop route block reduced to roughly 336px high
  - mobile route block stacks without horizontal overflow
- `git diff --check`.

## Commit State

- This checkpoint is code/docs/template/CSS/JS only.
- No SQL artifact or uploads artifact was created.
- Commit/push status should be confirmed from Git after closeout.
