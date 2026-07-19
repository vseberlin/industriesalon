# Current Handoff

Updated: 2026-07-09

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Schöneweide Atlas first load now uses a combined
  `/wp-json/iss-register/v1/atlas-bootstrap` payload. The browser no longer
  starts separate cold `/atlas` and `/atlas-context` place-model builds.
- The Atlas context contract has its own transient, and the bootstrap response
  warms it for the unfiltered Atlas view.
- The Schöneweide page now uses lighter display WebP assets for heavy static
  media and a lighter theme-owned static map display image. Originals are still
  present and untouched.
- The matching uploads deploy artifact is:
  `ops/uploads/2026-07-09-schoneweide-display-webp.tar.gz`, with manifest and
  SHA256 sidecar in the same directory.

## Places And Interactive Atlas Direction

- The accepted migration plan is
  `docs/architecture/places-editorial-atlas-restructure-plan.md`.
- The end-to-end local pilot is implemented. `iss-content` now registers the
  unchanged `register_place` CPT and Place format; `iss-editorial` stores typed
  Place JSON; the theme owns enabled dossier rendering with legacy fallback.
- Post 12899 (`Kino Spreehöfe`) is enabled with nine sections and six epochs.
  `wp iss-register place-editorial-check` proves the document/projection hash
  match. Editorial save rebuilds epoch/state projections and hides the legacy
  epoch metabox only for enabled Places.
- TouchTable crawler/review/story and feedback runtime plus nine dead partials
  are retired. The local retirement migration backed up and removed 184 source
  rows; live TouchTable plus SQL backup remains the recovery source.
- Atlas bootstrap is 58,535 bytes / 9,526 bytes gzipped for 76 Places, with
  versioned caches, ETag/304, lazy detail, and near-viewport startup. The
  absolute local warm TTFB remains about 170–260 ms despite the payload win.
- Next bounded action: curator-check the 12899 editor/save/preview content.
  After review, migrate further Places in batches. Do not remove the register
  plugin until graph actor/identity migration, Atlas ownership transfer to
  `iss-frontend`, and a stable observation period are complete.

## Preserve

- Keep the interactive Atlas source modular for now:
  `themes/industriesalon/assets/js/atlas/*.js` plus
  `themes/industriesalon/assets/js/schoneweide.js`. Do not introduce a bundle
  unless the project is ready to add a build step.
- `industriesalon-schoeneweide-register` owns `register_place`, Atlas REST
  payloads, and register caches. The theme owns the Schöneweide page template,
  map image assets, and visual presets.
- The optimized upload WebPs are display assets referenced directly by the
  file-backed Schöneweide template. They are not Media Library attachments.

## Staging State

- Code and uploads artifact are deployed on staging.
- Staging repo was fast-forwarded to
  `5682aec Optimize Schoneweide Atlas load`.
- The artifact
  `ops/uploads/2026-07-09-schoneweide-display-webp.tar.gz` was checksum-verified
  on staging and extracted into `/var/www/html`.
- No SQL artifact is required for this checkpoint: no DB-backed template,
  attachment rows, or content rows were changed.

## Verified Locally

- `page-schoneweide` template authority is `theme`.
- New optimized media URLs return `200` locally.
- Upload artifact checksum passes.
- Browser pass on `/schoneweide/`: initial image transfer dropped from about
  15.7 MB to about 4.9 MB; full-scroll image transfer is about 5.5 MB; the old
  6.8 MB hall JPEG is no longer requested; Atlas reaches ready state with 74
  markers.
- `npm run lint:js`, PHP lint, PHPStan, and `git diff --check` passed.
- PHPCS still reports three warnings in existing Atlas cache/model patterns:
  one `tax_query` warning and two direct-DB warnings for transient cleanup. No
  PHPCS errors.

## Verified On Staging

- `/schoneweide/` returns `200` and includes the Atlas bootstrap URL plus the
  optimized WebP references.
- `/wp-json/iss-register/v1/atlas-bootstrap` returns `200` with 76 places and
  3 context payloads; warm checks returned in about 110-150 ms.
- Representative optimized upload and theme map WebP URLs return `200`.
- All eight optimized upload WebPs are present in the WordPress container with
  expected byte sizes.

## Commit State

- Local and `origin/main` are aligned at
  `5682aec Optimize Schoneweide Atlas load` before the staging closeout doc
  update.
