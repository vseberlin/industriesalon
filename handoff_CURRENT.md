# Current Handoff

Updated: 2026-06-27

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Editorial admin simplification SOW is now the durable planning artifact at
  `docs/project/editorial-admin-simplification-sow.md`; it rejects a global
  admin reskin and defines one shared ISS editorial dashboard model with one
  normal editing authority per concept.
- Editorial gesture/skin cleanup is locally complete and committed at
  `0b6c45b Normalize editorial gestures and skins`.
- Local DB-backed editorial JSON was migrated for the included domains:
  Ausstellung, Projekt, Rückblick, and publication/photoalbum.
- Runtime code no longer carries the removed compatibility layer for the old
  included-domain gesture/skin names:
  `quellenauszug`, `bildstrecke`, `projekt_rail`, `aside`,
  `frauen-im-werk`, `kinder-im-werk`, `industrieakte`, and
  `blueprint-matrix`.
- Canonical runtime vocabulary now uses:
  - gestures such as `kapitel`, `fliesstext`, `zitat`, `galerie`,
    `objektfokus`, `material`, `massstab`, `programm`, and `upload_intake`;
  - skins such as `typografisch`, `dossier`, `quellenbuehne`,
    `objektalbum`, `bildmatrix`, `buehne`, and `chronik`;
  - `features.rail` for project rail behavior instead of authored
    `projekt_rail` sections.
- Veranstaltungen now collapse default skins to `typografisch`, `buehne`, and
  `chronik`; `chronik` is no longer an authored Veranstaltung gesture.
- Führungen were explicitly excluded and still keep their existing
  `fuehrung` JSON/content contracts, including `image_wall` where currently
  used.

## Transfer Artifacts

- Rollback/reference snapshot before the local migration:
  `ops/sql/2026-06-27-editorial-vocabulary-pre-migration.sql`.
- Deployable normalized JSON artifact for the local migration:
  `ops/sql/2026-06-27-editorial-vocabulary-normalized-json.sql`.
- Apply the code before applying the normalized SQL artifact on another target.
  Confirm the target has the referenced posts/media rows before replaying SQL.

## Preserve

- Keep theme-owned public rendering; plugins own data/contracts and editor
  registry rules.
- Do not reintroduce skin aliases or hidden legacy section compatibility unless
  a target migration failure proves a specific rollback need.
- Keep Führungen out of this cleanup until they get their own renderer/data
  audit.
- Do not revert unrelated local untracked files:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- First executable editorial-admin slice: inventory/classify current
  edit-screen fields, blocks, panels, metaboxes, save paths, and list-table
  columns by the SOW states, then choose Projekt or Ausstellung as the first
  reference dashboard conversion.
- Push or otherwise exchange the local commits when the local checkpoint
  should become shared through GitHub `main`.
- On any target DB that should receive this vocabulary migration, deploy code
  first, review/apply
  `ops/sql/2026-06-27-editorial-vocabulary-normalized-json.sql`, then spot
  check representative Ausstellung, Projekt, Rückblick, and photoalbum routes.
- Continue the remaining architecture follow-ups from
  `docs/architecture/gesture-skin-consolidation.md`: shape promotion beyond
  Veranstaltungen, `programm` projection decision, and possible wider
  `bildmatrix` reuse outside publications.

## Verified

- For the editorial-admin SOW slice: `git diff --check` passed.
- `git fetch origin --prune`; before this SOW exit commit, local
  `HEAD=3c46a55`, `origin/main=053ac70`, branch was ahead by 12 and not
  pushed.
- DB legacy stored-slug check returned `0` after migration.
- Representative local routes returned `200` and rendered new skin classes:
  - `/ausstellungen/rohren-fur-die-republik/` -> `quellenbuehne`
  - `/ausstellungen/frauen-in-werk/` -> `objektalbum`
  - `/ausstellungen/kinder-im-werk/` -> `objektalbum`
  - `/projekte/walk-of-fame-schoeneweide/` -> `dossier`
  - `/publikationen/fotoalbum-labor-konstruktions-und-versuchswerk-oberspree-1946/` -> `bildmatrix`
- Passed verification for the cleanup slice:
  - PHP syntax checks for touched plugin/theme PHP files;
  - `node --check plugins/iss-editorial/assets/admin.js`;
  - `git diff --check`;
  - `bash tools/phpcs-target.sh` for touched PHP files;
  - `bash tools/phpstan-target.sh` for touched PHP files;
  - `npx stylelint` for touched CSS files.
- Full `npm run lint:css` still fails only on an unrelated pre-existing
  spacing issue in `themes/industriesalon/assets/css/atlas-app.css:962`.
