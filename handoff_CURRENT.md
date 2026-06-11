# Current Handoff

Updated: 2026-06-11

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch: `main`. Do not push unless explicitly requested.
- Local `main` contains the programme occurrence/refactor commits, origin staging documentation commits, and the local Ausstellung availability hardening follow-up.
- Programme/calendar is on the first-party `iss-occurrences` projection. `iss-occurrences` owns `wp_iss_occurrences` and `wp_iss_occurrence_series`; `iss-programm` renders calendar/timeline/browser blocks; theme owns public skins/templates.
- `/kalender/` starts in month mode, defaults to `Alle`, uses explicit opt-in semantics, and groups recurring Führungen with `Termine anzeigen`.
- `/ausstellungen/` uses `industriesalon/ausstellungen-browser`, a WP_Query-based availability browser separate from occurrence/timeline data. Filters are `Aktuell`, `Dauer`, `Digital`, and `Archiv`; public visibility still uses `iss_timeline_enabled`.
- `Archiv` requires an explicit past `iss_end_date`; open-ended exhibitions stay out of archive until editors add an end date.
- Dauer and Digital Ausstellungen are availability-only and no longer sync into `iss_occurrences`; temporary exhibition run dates remain eligible for calendar rows when explicitly enabled.
- Ausstellung editors can now classify `Sonderausstellung`, `Dauerausstellung`, or `Digitale Ausstellung` in the existing `iss-content-model` editor panel. The native `ausstellung_typ` taxonomy UI stays hidden, but the taxonomy is exposed to REST so the editor panel can save the existing term contract.
- Local Ausstellung availability data is audit-clean: `Kinder im Werk` and `Frauen im Werk für Fernmeldewesen` are `digitaleausstellungen` through `2026-12-31`; `Ostberliner Zeitreisen - Fotografien von Kurt Schwarz` and `Die laufende Produktion` are drafts pending later review.
- Local backup before the data cleanup: `ops/content-backups/2026-06-11-before-ausstellung-availability-cleanup.sql`; replay artifact: `ops/sql/2026-06-11-ausstellung-availability-cleanup.sql`.
- `refactor.md` records the gradual `Entity / Relation / Occurrence / View` refactor direction and the phased path through `iss-core` and `iss-frontend`.
- `iss-graph` now has a central entity-kind registry for current storage kinds, canonical target aliases, owner plugins, post-type mappings, and legacy aliases. Current stored values such as `ausstellung`, `veranstaltung`, `fuehrung`, `projekt`, `page`, and `archivbeitrag` remain stable; canonical aliases include `exhibition`, `event`, `tour`, and `project`.
- `iss-graph` exposes the first read-only `/wp-json/iss/v1` facade for contract, entities, entity detail, occurrences, search, the programme timeline compatibility view, and the tour-slot read view. It delegates to existing graph, occurrence, search, programme timeline, and tour-slot services; older plugin routes remain active.
- Search is the first old-vs-new facade audit surface. `wp iss-graph facade-search-compare` compares `/iss-search/v1/search` and `/iss/v1/search` result signatures before search consumers switch route.
- The public header search modal is now the first actual facade consumer and reads from `/wp-json/iss/v1/search`. The full search page and legacy `/iss-search/v1/search` route remain active.
- Occurrences are now covered by the same old-vs-new audit pattern. `wp iss-graph facade-occurrences-compare` compares direct `iss_occurrences_query()` output against `/iss/v1/occurrences` result signatures before programme consumers switch routes.
- The public timeline query frontend is now the first occurrence-facing view consumer on the facade and reads from `/wp-json/iss/v1/timeline`. The legacy `/iss-programm/v1/timeline` route remains active and delegates to the same renderer.
- The public tour calendar slot reader now reads from `/wp-json/iss/v1/tour-slots`. The legacy `/is-tours/v1/slots` read route remains active; booking submissions remain on `/is-tours/v1/book`.
- Entities are now covered by the same old-vs-new audit pattern. `wp iss-graph facade-entities-compare` compares direct graph service output against `/iss/v1/entities` list/detail responses before entity/profile consumers switch routes.
- `iss-core` and `iss-frontend` exist as active local scaffold plugins only. They expose helper conventions and do not own CPTs, REST routes, renderers, CSS, or domain scripts yet.
- Legacy hidden-calendar code has been removed from active runtime paths; the old `iss_calendar_item` CPT/query layer is not active storage or query code.

## Current Server State

- Staging `iss-graph` migration/backfill was applied on 2026-06-10. Backup/rollback directory: `/srv/industriesalon/stage/backups/20260610-graph-migration/`; server action note: `/home/vladimir/server-actions/2026-06-10-graph-migration.md`. Final `wp iss-graph verify` and full `wp iss-graph drift-check` passed after reconciling post `17980` through `wp iss-relations sync --post_id=17980`.
- Staging has the corrected `Frauen im Werk für Fernmeldewesen` transfer applied. `/ausstellungen/frauen-in-werk/` returns `200`; post `26287`, WebP attachment rows, Archivset `27`, six set members, and the `archive_material` link are present. Backup/rollback directory: `/srv/industriesalon/stage/backups/20260610-frauen-in-werk-transfer/`.
- Applied Ausstellung content live on staging: `/ausstellungen/kinder-im-wf/`, `/ausstellungen/kinder-im-werk/`, and `/ausstellungen/frauen-in-werk/`. The refreshed upload artifact was extracted into the shared uploads bind mount and all 91 manifest files were verified.
- Staging Docker Engine patch packages, OpenSSL security packages, public plugin updates, and nginx hardening passes from 2026-06-10 are complete; no remaining apt upgrades were reported after the Docker patch update.

## Current Risk

- Staging does not automatically have the new programme occurrence/refactor checkpoint. It will need code merge plus target-side occurrence schema/backfill/sync checks before relying on it.
- Database state changed locally during programme verification: occurrence schema v3 installed, graph backfill applied, `iss-core` and `iss-frontend` activated, and `wp iss-occurrences sync` resynced source rows under the Ausstellung availability boundary.
- Occurrence drift depends on graph entity health. If graph entities drift, `wp iss-occurrences drift-check` should fail even if the calendar visually renders.
- `/wp-json/iss/v1` is currently a facade boundary, not a storage owner or route migration. Keep future consumers behind old-vs-new comparisons until the route contract is covered by a reusable verifier.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk files are live.
- Staging graph tables are migrated and drift-clean; rerun `wp iss-graph migrate` plus `wp iss-graph drift-check` after future content artifact imports that create or change graph-backed posts.

## Next Action

- Keep the local Ausstellung availability SQL artifact paired with this code checkpoint if transferring to staging/production.
- Next local refactor work should use `iss-core` or `iss-frontend` only for helpers with proven reuse; keep domain code in the current owning plugins until extraction has a stable contract.
- Run `wp iss-graph facade-check` before wiring any consumer to the `/iss/v1` facade.
- Run `wp iss-graph facade-search-compare` before switching any additional search UI, block, or API consumer from `/iss-search/v1/search` to `/iss/v1/search`.
- Run `wp iss-graph facade-occurrences-compare` before switching programme/calendar UI, blocks, or API consumers to `/iss/v1/occurrences`.
- Run `wp iss-graph facade-timeline-compare` before switching any additional rendered timeline consumer from `/iss-programm/v1/timeline` to `/iss/v1/timeline`.
- Run `wp iss-graph facade-tour-slots-compare` before switching any additional tour-slot reader from `/is-tours/v1/slots` to `/iss/v1/tour-slots`.
- Run `wp iss-graph facade-entities-compare` before switching entity/profile UI, blocks, or API consumers to `/iss/v1/entities`.
- Before deploy or staging transfer, run `wp iss-occurrences verify`, `wp iss-occurrences drift-check`, and `wp iss-graph drift-check` on the target.
- Apply programme SQL/data artifacts only with the matching code checkpoint and after a database backup.

## Verified

- Pre-merge local programme checkpoint `a1b6504`: PHP lint, JS syntax, targeted ESLint, PHPCS, PHPStan, and `git diff --check` passed.
- Pre-merge local programme checkpoint: `wp iss-occurrences sync`, `wp iss-occurrences verify`, `wp iss-occurrences drift-check`, and `wp iss-graph drift-check` passed.
- Pre-merge local programme checkpoint: `/`, `/kalender/`, `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`, and `/archiv/` returned `200`; `/is-tours/v1/slots?tag=ELEKTRO` returned `source:"occurrences"`.
- Pre-merge local programme checkpoint: template authority for `page-ausstellungen` and `page-kalender` was `source=theme`; direct DB check found no `dauerausstellung` or `digitaleausstellungen` rows in `wp_iss_occurrences`.
- Pre-merge local programme checkpoint: Playwright desktop/mobile checks on the configured local host had no console errors and no mobile horizontal overflow on changed pages.
- Reconciliation branch follow-up: Ausstellung browser filters return `aktuell=18`, `dauer=14`, `digital=1`, and `archiv=6`; `Archiv` no longer includes `Frauen im Werk für Fernmeldewesen`.
- `wp iss-programm ausstellungen-audit --strict` passes with 0 warnings after the local data cleanup.
- Ausstellung browser filters after cleanup: `aktuell=17`, `dauer=14`, `digital=3`, `archiv=6`.
- `wp iss-occurrences verify` reports `public_occurrences=52` and `public_graph_occurrences=52`; `wp iss-occurrences drift-check` and `wp iss-graph drift-check` passed.
- Ausstellung editor classification pass: PHP lint, targeted ESLint, PHPCS, PHPStan, and `git diff --check` passed; REST exposes `ausstellung_typ` on Ausstellung posts; `/ausstellungen/?ausstellung_filter=digital` returns the three digital Ausstellungen.
- Origin staging commits verified Frauen transfer and graph migration on staging; details are preserved in `CHANGELOG.md`.
- Graph entity-kind registry and `/iss/v1` facade pass PHP lint, targeted PHPCS, targeted PHPStan, `git diff --check`, `wp iss-graph verify`, full `wp iss-graph drift-check --limit=25`, and `wp iss-occurrences drift-check --limit=25`.
- `/wp-json/iss/v1/contract`, `/entities`, `/entities/{id}`, `/occurrences`, `/search`, `/timeline`, and `/tour-slots` returned `200` in WP-CLI REST smoke checks and HTTP curl checks on local port `8082`.
- `wp iss-graph facade-check --limit=2` passes and checks `/iss/v1/contract`, `/entities`, `/entities/{id}`, `/occurrences`, `/search`, `/timeline`, and `/tour-slots` through WordPress.
- `wp iss-graph facade-search-compare --limit=5` passes for default queries `salon`, `schoeneweide`, and `ausstellung`; each matched provider, count, and result signatures between legacy search and the facade.
- Homepage markup on the configured local site host renders the header search modal with `data-endpoint` pointing to `/wp-json/iss/v1/search`; `/kalender/` renders the same endpoint.
- `wp iss-graph facade-occurrences-compare --limit=5` passes for default scenarios `upcoming`, `all`, and `event`; each matched direct occurrence service output against the `/iss/v1/occurrences` facade.
- `wp iss-graph facade-timeline-compare --limit=5` passes for default scenarios `upcoming`, `month`, and `event`; each matched legacy `/iss-programm/v1/timeline` metadata and rendered HTML hash against `/iss/v1/timeline`.
- `/kalender/` inline frontend config now points `window.ISS_TIMELINE.restUrl` to `/wp-json/iss/v1/timeline`; a JSON POST to that facade returns timeline HTML and pagination metadata.
- `wp iss-graph facade-tour-slots-compare --tag=ELEKTRO` passes for default scenarios `tag` and `nomap`; `ELEKTRO` matched legacy `/is-tours/v1/slots` against `/iss/v1/tour-slots` with `source=occurrences` and 3 slots.
- `/fuehrungen/` inline frontend config now points `window.IS_TOUR_CALENDAR.restUrl` to `/wp-json/iss/v1/tour-slots`; `window.IS_TOUR_CALENDAR.bookUrl` still points to `/wp-json/is-tours/v1/book`.
- `wp iss-graph facade-entities-compare --limit=5` passes for default scenarios `list`, `archive_object`, and `search`; each matched graph service output against `/iss/v1/entities` and detail output for the first returned entity.
