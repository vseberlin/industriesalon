# Current Handoff

Updated: 2026-06-11

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch: `reconcile/programme-origin-docs`, a temporary local reconciliation branch. Do not push unless explicitly requested.
- This branch merges local programme occurrence commits `48e7595` and `a1b6504` with origin staging documentation commits `76257e7` and `8c58801`.
- Programme/calendar is on the first-party `iss-occurrences` projection. `iss-occurrences` owns `wp_iss_occurrences` and `wp_iss_occurrence_series`; `iss-programm` renders calendar/timeline/browser blocks; theme owns public skins/templates.
- `/kalender/` starts in month mode, defaults to `Alle`, uses explicit opt-in semantics, and groups recurring Führungen with `Termine anzeigen`.
- `/ausstellungen/` uses `industriesalon/ausstellungen-browser`, a WP_Query-based availability browser separate from occurrence/timeline data. Filters are `Aktuell`, `Dauer`, `Digital`, and `Archiv`; public visibility still uses `iss_timeline_enabled`.
- Dauer and Digital Ausstellungen are availability-only and no longer sync into `iss_occurrences`; temporary exhibition run dates remain eligible for calendar rows when explicitly enabled.
- `refactor.md` records the gradual `Entity / Relation / Occurrence / View` refactor direction and the phased path through `iss-core` and `iss-frontend`.
- `iss-core` and `iss-frontend` exist as active local scaffold plugins only. They expose helper conventions and do not own CPTs, REST routes, renderers, CSS, or domain scripts yet.
- Legacy hidden-calendar code has been removed from active runtime paths; the old `iss_calendar_item` CPT/query layer is not active storage or query code.

## Current Server State

- Staging `iss-graph` migration/backfill was applied on 2026-06-10. Backup/rollback directory: `/srv/industriesalon/stage/backups/20260610-graph-migration/`; server action note: `/home/vladimir/server-actions/2026-06-10-graph-migration.md`. Final `wp iss-graph verify` and full `wp iss-graph drift-check` passed after reconciling post `17980` through `wp iss-relations sync --post_id=17980`.
- Staging has the corrected `Frauen im Werk für Fernmeldewesen` transfer applied. `/ausstellungen/frauen-in-werk/` returns `200`; post `26287`, WebP attachment rows, Archivset `27`, six set members, and the `archive_material` link are present. Backup/rollback directory: `/srv/industriesalon/stage/backups/20260610-frauen-in-werk-transfer/`.
- Applied Ausstellung content live on staging: `/ausstellungen/kinder-im-wf/`, `/ausstellungen/kinder-im-werk/`, and `/ausstellungen/frauen-in-werk/`. The refreshed upload artifact was extracted into the shared uploads bind mount and all 91 manifest files were verified.
- Staging Docker Engine patch packages, OpenSSL security packages, public plugin updates, and nginx hardening passes from 2026-06-10 are complete; no remaining apt upgrades were reported after the Docker patch update.

## Current Risk

- This reconciliation branch is local and temporary. If it becomes the exchange branch, rerun checks after the merge commit and decide explicitly whether to push.
- Staging does not automatically have the new programme occurrence/refactor checkpoint. It will need code merge plus target-side occurrence schema/backfill/sync checks before relying on it.
- Database state changed locally during programme verification: occurrence schema v3 installed, graph backfill applied, `iss-core` and `iss-frontend` activated, and `wp iss-occurrences sync` resynced source rows under the Ausstellung availability boundary.
- Occurrence drift depends on graph entity health. If graph entities drift, `wp iss-occurrences drift-check` should fail even if the calendar visually renders.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk files are live.
- Staging graph tables are migrated and drift-clean; rerun `wp iss-graph migrate` plus `wp iss-graph drift-check` after future content artifact imports that create or change graph-backed posts.

## Next Action

- If this reconciliation branch is kept, run the final verification set on it and commit the merge.
- If the programme checkpoint must remain strictly local and unmerged, abandon this branch and return to `main`.
- Before deploy or staging transfer, run `wp iss-occurrences verify`, `wp iss-occurrences drift-check`, and `wp iss-graph drift-check` on the target.
- Apply programme SQL/data artifacts only with the matching code checkpoint and after a database backup.

## Verified

- Pre-merge local programme checkpoint `a1b6504`: PHP lint, JS syntax, targeted ESLint, PHPCS, PHPStan, and `git diff --check` passed.
- Pre-merge local programme checkpoint: `wp iss-occurrences sync`, `wp iss-occurrences verify`, `wp iss-occurrences drift-check`, and `wp iss-graph drift-check` passed.
- Pre-merge local programme checkpoint: `/`, `/kalender/`, `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`, and `/archiv/` returned `200`; `/is-tours/v1/slots?tag=ELEKTRO` returned `source:"occurrences"`.
- Pre-merge local programme checkpoint: template authority for `page-ausstellungen` and `page-kalender` was `source=theme`; direct DB check found no `dauerausstellung` or `digitaleausstellungen` rows in `wp_iss_occurrences`.
- Pre-merge local programme checkpoint: Playwright desktop/mobile checks on the configured local host had no console errors and no mobile horizontal overflow on changed pages.
- Origin staging commits verified Frauen transfer and graph migration on staging; details are preserved in `CHANGELOG.md`.
