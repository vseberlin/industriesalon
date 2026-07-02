# Current Handoff

Updated: 2026-07-02

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Latest pushed/deployed checkpoint: current `main` HEAD.
- Template-sync commit: `cc910a6 Sync Fuehrungen page template override`.
- GitHub `main` and staging repo `/srv/industriesalon/stage/repo` were
  fast-forwarded through this handoff checkpoint.
- The local DB-saved `page-fuehrungen` template was synced back to
  `themes/industriesalon/templates/page-fuehrungen.html` and the local
  `page-fuehrungen` DB override was flushed. Local and staging now report
  `page-fuehrungen source=theme`.
- Führung `bildbuehne` support is deployed: the format is registered in
  `iss-content`, editable/sanitized through `iss-editorial`, and rendered by
  the theme in the `single-fuehrung` hero scaffold.
- No real staging/local Führung post currently has migrated `bildbuehne`
  content; the staging Elektropolis route renders without
  `iss-tour-has-stage-gesture`.

## Preserve

- Keep `single-fuehrung` as the template scaffold for logo-aligned left text,
  center/full-bleed visual stage, right booking rail, route/map/related
  placement, and fallback hero.
- Keep `bildbuehne` editorial only: image/gallery/title/body. Do not move
  booking or transactional controls into JSON gestures.
- Keep public rendering in the theme, format registration in `iss-content`, and
  storage/editor behavior in `iss-editorial`.

## Next Action

- Create or migrate at least one real Führung `bildbuehne` section in content,
  then browser-check desktop/mobile on staging.
- Continue the existing calendar and JSON editor UAT follow-ups listed in
  `TODO.md`.

## Verified

- Local:
  - `page-fuehrungen` DB template content matched the disk template before the
    override was flushed.
  - `page-fuehrungen source=theme` after flush.
  - `/fuehrungen/` returned `200`, rendered
    `Führungen durch die Industriekultur`, and no longer rendered the
    `iss-tours-booking__surface` section.
  - `git diff --check`.
- Staging:
  - Repo fast-forwarded to `cc910a6` for code/template deploy; final handoff
    checkpoint was deployed afterward.
  - `front-page source=theme`; `page-fuehrungen source=theme`.
  - PHP lint passed for:
    - `plugins/iss-content/includes/editorial.php`
    - `plugins/iss-editorial/includes/storage.php`
    - `themes/industriesalon/includes/tours-render.php`
  - Führung registry check:
    `bildbuehne,intro,kapitel,leitfrage,zitat,galerie,image_wall,material,schluss`
  - `/fuehrungen/` returned `200`, rendered
    `Führungen durch die Industriekultur`, and no longer rendered the
    `iss-tours-booking__surface` section.
  - `/fuehrungen/elektropolis/` redirects to `/fuehrungen/elektropolis-tour/`
    and returns `200` with tour markup.

## Commit State

- Local `main`, `origin/main`, and staging repo HEAD are aligned at the current
  handoff checkpoint.
- Staging still has an intentional uncommitted `TODO.md` note about the
  Repair-Cafe staging artifact/old Apache stack; it was preserved during
  deployment.
- No SQL or upload artifact was created for this code/docs/template deploy.
