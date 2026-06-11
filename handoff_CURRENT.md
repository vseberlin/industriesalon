# Current Handoff

Updated: 2026-06-11

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch: `main`.
- Work is local only; do not push unless explicitly requested.
- Local `main` has diverged from `origin/main`: local `HEAD` was ahead by `48e7595`, while `origin/main` has staging commits `76257e7` and `8c58801` not merged into this dirty checkpoint.
- Programme/calendar is now on the new first-party `iss-occurrences` projection, with a second local cleanup checkpoint prepared.
- `iss-occurrences` owns `wp_iss_occurrences` and `wp_iss_occurrence_series` as the public programme projection for calendar/timeline queries.
- Calendar/timeline rendering stays in `iss-programm` and theme surfaces; graph/search identity stays in `iss-graph`.
- Occurrence schema is now v3 with `entity_id` and `location_entity_id`. Public occurrence rows are graph-linked while `source_post_id` / `source_post_type` remain sync, delete, and editor-routing metadata.
- SuperSaaS Führungen sync through `saas-api/includes/supersaas-sync.php` into occurrence rows. Unlinked/unpublished SuperSaaS slots are deleted from the public projection.
- `/kalender/` starts in month mode, defaults to `Alle`, uses strict public opt-in semantics, and groups recurring Führungen with `Termine anzeigen`.
- `/ausstellungen/` now uses `industriesalon/ausstellungen-browser`, a WP_Query-based availability browser separate from occurrence/timeline data. Filters are `Aktuell`, `Dauer`, `Digital`, and `Archiv`; public visibility still uses `iss_timeline_enabled`.
- Dauer and Digital Ausstellungen are availability-only and no longer sync into `iss_occurrences`; temporary exhibition run dates remain eligible for calendar rows when explicitly enabled.
- `refactor.md` records the gradual `Entity / Relation / Occurrence / View` refactor direction and the phased path through `iss-core` and `iss-frontend`.
- `iss-core` and `iss-frontend` exist as active local scaffold plugins only. They expose helper conventions and do not own CPTs, REST routes, renderers, CSS, or domain scripts yet.
- Legacy hidden-calendar code has been removed from active runtime paths; the old `iss_calendar_item` CPT/query layer is not an active storage or query layer.

## Current Risk

- The worktree still contains untracked duplicate audit drafts: `programm-audit.md` and `programm-audit-with-peer-review (1).md`. They were present before this checkpoint and are not part of the commit.
- Occurrence drift now depends on graph entity health. If graph entities drift, `wp iss-occurrences drift-check` should fail even if the calendar visually renders.
- Every direct occurrence write path must preserve `entity_id`; the SuperSaaS adapter has been fixed, but future direct service calls need the same discipline.
- Database state was changed locally: occurrence schema v3 installed, graph backfill applied, `iss-core` and `iss-frontend` activated, and `wp iss-occurrences sync` resynced source rows under the new Ausstellung availability boundary. Staging/production need explicit migration/backfill/sync before relying on this state.
- Local and origin are diverged; fetch/merge needs deliberate handling after this local commit because the worktree started dirty and origin advanced.

## Next Action

- Do not push until the local-vs-origin divergence is resolved deliberately.
- If exchanging with staging, merge or rebase only after inspecting the two origin-only staging commits against this local programme checkpoint.
- Before deploy or staging transfer, run `wp iss-occurrences verify`, `wp iss-occurrences drift-check`, and `wp iss-graph drift-check` on the target.
- Apply the `ops/sql/2026-06-11-*` migration artifacts only with the matching code checkpoint and after a database backup.

## Verified

- PHP lint passed for touched PHP files.
- Node syntax checks and targeted ESLint passed for touched JS files.
- PHPCS passed for touched PHP files.
- PHPStan passed for touched PHP files.
- `git diff --check` passed.
- `wp plugin list` loaded cleanly; `iss-core` and `iss-frontend` activated locally.
- `wp iss-occurrences sync` reports `sources=46 supersaas_created=0 supersaas_updated=9 supersaas_unlinked=1 supersaas_inactivated=26 supersaas_backfilled=4 supersaas_errors=0 graph_backfilled=0`.
- `wp iss-occurrences verify` reports `public_occurrences=63` and `public_graph_occurrences=63`.
- `wp iss-occurrences drift-check` passed.
- `wp iss-graph drift-check` passed.
- Public smoke checks returned `200` for `/`, `/kalender/`, `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`, and `/archiv/`.
- `/is-tours/v1/slots?tag=ELEKTRO` returns `source:"occurrences"`.
- Template authority for `page-ausstellungen` and `page-kalender` is `source=theme`.
- Direct DB check found no `dauerausstellung` or `digitaleausstellungen` rows in `wp_iss_occurrences`.
- Playwright verified desktop `/kalender/`, `/ausstellungen/`, `/ausstellungen/?ausstellung_filter=dauer`, and `/fuehrungen/` with no console/page errors on configured host `http://192.168.2.31:8082`.
- Playwright mobile checks for `/kalender/`, `/ausstellungen/?ausstellung_filter=digital`, and `/fuehrungen/` had no console errors and no horizontal overflow.
