# Current Handoff

Updated: 2026-06-30

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Native landing JSON editor guardrails are implemented locally for the shared
  `iss-editorial` canvas.
- Section delete now moves gestures into persisted `deleted_sections`; editors
  can restore after `Aktualisieren`, and admins can purge deleted sections.
- The landing editor preview button saves the current JSON to the existing
  autosave meta before opening WordPress preview.
- Landing section links and gateway item targets use a published Pages dropdown,
  store optional `page_id`, and the theme resolves current permalinks at render.
- Landing format now exposes canonical `fliesstext` for text-only prose and
  `feature.media-text` for the reusable 50/50 image-text pattern.

## Preserve

- Keep landing pages as native WordPress `page` posts; do not add a
  `landing_page` CPT.
- Keep public landing rendering in the theme and editor/storage contracts in
  `iss-editorial` / `iss-content`.
- Keep `fliesstext` text-only; use `feature.media-text` for image/text and
  `feature.microblocks` for the front-page overlay microblock variant.
- Leave unrelated local untracked files out of Git unless explicitly requested:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- Add drag/drop ordering for landing gestures, keeping Hoch/Runter as fallback.
- Restrict landing treatment controls to admins before client handover.
- Before enabling more pages, browser-check `/`, `/about/`, `/verein/`,
  `/salon-vermietung/`, and `/sammlungen/`; disabled or empty JSON must keep
  existing template/post-content output.

## Verified Locally

- `node --check plugins/iss-editorial/assets/admin.js`
- PHP lint for changed PHP files.
- `bash tools/phpcs-target.sh` for changed PHP files.
- `npx stylelint` for changed landing/admin CSS.
- `git diff --check`
- WP runtime probes confirmed soft-delete storage, page-link sanitization,
  `fliesstext` rendering, and `feature.media-text` rendering.
- Local front-page smoke on `http://localhost:8082/` still renders the landing
  wrapper with 9 landing sections.

## Commit State

- Commit requested for this checkpoint.
- No staging/live deploy performed in this checkpoint.
