# Current Handoff

Updated: 2026-07-01

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Latest local commit: `e818c37 Add Fuehrung image stage gesture`.
- The Führung JSON format now exposes `bildbuehne` as the explicit hero-stage
  gesture. It is registered in `iss-content`, editable/sanitized through
  `iss-editorial`, and consumed by the theme in the existing
  `single-fuehrung` hero scaffold.
- When a `bildbuehne` section exists, the theme consumes its title, body, and
  media into the first viewport hero. The gesture is not rendered again in the
  body.
- Booking, dates, facts, route, Atlas map, and related cards remain outside the
  JSON gesture body and continue to render through their existing template /
  module contracts.
- The current local Elektropolis runtime test section was restored; no
  temporary `bildbuehne` content remains in post `12183`.

## Preserve

- Keep `single-fuehrung` as the template scaffold for logo-aligned left text,
  center/full-bleed visual stage, right booking rail, route/map/related
  placement, and fallback hero.
- Keep `bildbuehne` editorial only: image/gallery/title/body. Do not move
  booking or transactional controls into JSON gestures.
- Keep public rendering in the theme, format registration in `iss-content`, and
  storage/editor behavior in `iss-editorial`.

## Next Action

- Push/deploy `e818c37`, then create or migrate at least one real Führung
  `bildbuehne` section in content and browser-check desktop/mobile.
- Continue the existing calendar and JSON editor UAT follow-ups listed in
  `TODO.md` when this Führung slice is deployed.

## Verified Locally

- PHP lint:
  - `themes/industriesalon/includes/tours-render.php`
  - `plugins/iss-content/includes/editorial.php`
  - `plugins/iss-editorial/includes/storage.php`
- `node --check plugins/iss-editorial/assets/admin.js`
- `npx stylelint themes/industriesalon/assets/css/single-tour.css`
- `git diff --check`
- Runtime registry check:
  `bildbuehne,intro,kapitel,leitfrage,zitat,galerie,image_wall,material,schluss`
- Temporary local Elektropolis `bildbuehne` smoke:
  Chromium and Firefox desktop/mobile had no horizontal overflow, rendered the
  stage in the hero, did not duplicate it as a body section, and kept the
  booking rail outside the gesture.
- Restore check after smoke: local Elektropolis page no longer contains
  `iss-tour-has-stage-gesture`, `iss-tour-hero-gallery--stage`, or the temporary
  stage copy.

## Commit State

- Local `main` is ahead of `origin/main` by 1 commit:
  `e818c37 Add Fuehrung image stage gesture`.
- The checkpoint has not been pushed.
- No SQL or upload artifact was created for this code/docs-only commit.
