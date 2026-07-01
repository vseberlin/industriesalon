# Current Handoff

Updated: 2026-07-01

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- `/kalender/` calendar-booking polish is implemented locally on the active
  theme-backed template. The existing `industriesalon/timeline-query` contract
  remains the owner; occurrence reads still use `/iss/v1/timeline` and booking
  writes still flow through `/iss-payments/v1/request`.
- `plugins/iss-frontend/modules/programme/assets/timeline-query.js` now
  progressively enhances the existing month/day bridge into month buttons plus
  a visible day grid while keeping the original controls as source of truth.
- `themes/industriesalon/assets/css/page-kalender.css` owns the booking-style
  two-panel calendar page skin; `plugins/iss-frontend/modules/programme/assets/timeline.css`
  only adds structural hooks for the grid.
- The grouped `Termin wählen` popup now uses the same booking-selector
  direction: `plugins/iss-frontend/modules/programme/assets/programm.js`
  preselects the first bookable day, and
  `themes/industriesalon/assets/css/tour-calendar-skin.css` owns the wider
  two-column modal/slot-panel skin with stacked mobile fallback.
- Native landing JSON editor UAT polish is implemented locally for the shared
  `iss-editorial` canvas.
- Section delete moves gestures into persisted `deleted_sections`; editors can
  restore after `Aktualisieren`, and admins can purge deleted sections.
- `plugins/iss-editorial/assets/dnd.js` owns shared canvas drag/drop: section
  reorder by card/handle drag, ArrowUp/ArrowDown handle fallback, and palette
  drag-to-add.
- `plugins/iss-editorial/assets/ui.js` owns JSON editor modal/panel primitives.
  Section editing now opens a structured panel modal documented in
  `docs/project/json-editor-ux-redesign-sow.md`.
- CSS layer 0 is now explicit: `themes/industriesalon/assets/css/tokens.css`
  owns the `--iss-*` token contract, and
  `docs/architecture/css-layering-adr.md` defines the token -> base ->
  primitives/patterns -> renderer/skin -> page-exception order.
- First-party public plugin CSS is now part of the same contract as a token
  consumer with fallbacks; admin plugin CSS stays scoped to wp-admin and outside
  the public renderer stack.
- Native landing `statement` gesture has started the migration-positive CSS
  pass: `statement.lead` owns the former lead/statement look,
  `statement.leitfrage` owns the typografisch guiding-question treatment, and
  JSON statement output no longer carries `iss-front-schoneweide-statement`.
  Stored `statement.callout` values are mapped to `statement.leitfrage`.
- Native landing `feature` treatment vocabulary now uses user-facing labels:
  `feature.media-panel` = Bild mit Infokasten, `feature.media-text` = Bild
  neben Text, and `feature.image-overlay` = Titel auf Bild. Stored
  `feature.microblocks` values are mapped to `feature.image-overlay`.
- `feature.media-text` supports compact, balanced, and wide text/image ratios;
  `/salon-vermietung/` “Der Ort” should migrate into this treatment rather than
  keep `iss-rental-story` as a page-local 50/50 variant.
- The custom JSON preview button and native WordPress preview button both save
  current JSON to preview autosave before opening preview.
- Signed `iss-editorial` preview URL args let the landing renderer prefer
  preview autosave data, so preview respects unsaved reorder changes.
- Landing section links and gateway item targets use a published Pages dropdown,
  store optional `page_id`, and the theme resolves current permalinks at render.
- Landing format now exposes canonical `fliesstext` for text-only prose and
  `feature.media-text` for reusable image-beside-text sections.

## Preserve

- Keep landing pages as native WordPress `page` posts; do not add a
  `landing_page` CPT.
- Keep public landing rendering in the theme and editor/storage contracts in
  `iss-editorial` / `iss-content`.
- Keep `fliesstext` text-only; use `feature.media-text` for image/text and
  `feature.image-overlay` for title-on-image sections. Facts remain facts; the
  treatment decides presentation.
- Leave unrelated local untracked files out of Git unless explicitly requested:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- UAT `/kalender/` with real booking paths: month selection, day selection,
  grouped `Termin wählen`, popup slot selection, direct `Buchen`, empty day
  state, and form submit.
- UAT the JSON canvas drag/drop helper and redesigned section modal panels.
- During JSON gesture polish, migrate landing/front-page CSS gesture by
  gesture toward renderer/treatment/skin selectors. Continue with `gateway`
  and remaining `feature` styles; `statement.lead` / `statement.leitfrage` and
  `feature.image-overlay` are already split onto treatment vocabulary.
- Split picker-heavy adapters out of `plugins/iss-editorial/assets/admin.js`
  after modal/drag-drop UAT; archive object, media, gateway item media, and
  album/source sheet import are the first extraction candidates.
- Restrict landing treatment controls to admins before client handover.
- Before enabling more pages, browser-check `/`, `/about/`, `/verein/`,
  `/salon-vermietung/`, and `/sammlungen/`; disabled or empty JSON must keep
  existing template/post-content output.

## Verified Locally

- `docker compose run --rm wpcli eval 'var_dump(get_block_template("industriesalon//page-kalender", "wp_template")->source ?? null);' --allow-root` -> `theme`
- `node --check plugins/iss-frontend/modules/programme/assets/timeline-query.js`
- `node --check plugins/iss-frontend/modules/programme/assets/programm.js`
- `npx stylelint plugins/iss-frontend/modules/programme/assets/timeline.css themes/industriesalon/assets/css/page-kalender.css themes/industriesalon/assets/css/timeline-skin.css themes/industriesalon/assets/css/tour-calendar-skin.css`
- Playwright Chromium and Firefox on `http://192.168.2.31:8082/kalender/`:
  July 2 day-grid click selected the day, updated the count to `2 Einträge`,
  used the red selected-day accent, and had no horizontal overflow. Chromium
  mobile 390px also had no horizontal overflow.
- Playwright Chromium/Firefox grouped `Termin wählen` popup: popup opens with
  `Elektropolis`, preselects Saturday, 11 July 2026, shows one slot, has no
  horizontal overflow, and clicking the slot opens the booking form with
  `intent=booking` and populated `slot_id`.
- `node --check plugins/iss-editorial/assets/admin.js`
- `node --check plugins/iss-editorial/assets/dnd.js`
- `node --check plugins/iss-editorial/assets/ui.js`
- PHP lint for changed PHP files.
- `bash tools/phpcs-target.sh` for changed PHP files.
- `npx eslint` for changed editor JavaScript.
- `npx stylelint` for changed admin CSS.
- `git diff --check`
- Chromium and Firefox Playwright fixtures confirmed card/handle drag reorder
  and the redesigned modal panel shell.
- WP runtime probes confirmed signed preview URLs and preview autosave ordering
  for the landing renderer.

## Commit State

- No commit requested or made for this checkpoint.
- No staging/live deploy performed in this checkpoint.
