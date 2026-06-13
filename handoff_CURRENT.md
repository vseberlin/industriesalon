# Current Handoff

Updated: 2026-06-13

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch: `main`; backend knowledge-graph refactor checkpoint is closed locally. Push the closeout commit only when explicitly requested.
- GitHub repo `vseberlin/industriesalon` is private. `origin/main` currently includes the greenfield refactor slices through the rendered Ausstellung availability browser consumer; this backend closeout checkpoint remains local until pushed.
- Staging is the current live working target, not a production release gate. If it breaks, it can be rebuilt from Git plus the known SQL/data artifacts.
- `iss-occurrences` owns occurrence projection; `iss-programm` owns programme/timeline/browser blocks; `saas-api` owns SuperSaaS sync and tour-slot reads; `iss-graph` owns graph/search/contracts; the theme owns public templates/skins.
- `/wp-json/iss/v1` is the read-only facade boundary. Active public reads are contract, entities, entity detail, entity relations, entity-scoped occurrences, occurrences, search, timeline, availability, and tour-slots.
- Public consumers already on the facade: header search, timeline query reads, tour-slot reads, and the progressively enhanced Ausstellung availability browser. Booking writes stay on `/is-tours/v1/book`.
- `/ausstellungen/` uses the dedicated `industriesalon/ausstellungen-browser` and WP_Query availability filters. Dauer/Digital Ausstellungen are availability-only and do not sync into occurrence rows.
- Ausstellung editors now have a self-scoped `availability` editorial-signal surface. It affects only automatic Ausstellung browser ordering/visibility: `pin`, `feature`, and `boost` move entries up; `suppress` removes them from automatic browser results.
- The offer bridge is contract-only: `fuehrung` maps to `offer/tour`; `veranstaltung` maps to `offer` subtypes from existing `_iss_event_format` and `_iss_event_layout` editor meta. The local checkpoint expands accepted event subtypes without renaming CPTs or storage rows.
- The local public Offer consumer pass centralizes Offer subtype public labels in `iss-graph` and consumes them in header search result labels, shared related-card kickers, and timeline-card badges. This does not change editor storage or redesign the UI.
- The local tour Offer catalog guard extends `wp iss-fuehrungen drift-check` so published `fuehrung` catalog posts must remain visible in the catalog query, resolve as `offer/tour`, use known catalog groups, and keep the expected catalog renderer shell.
- The local facade consumer guard adds `wp iss-graph facade-consumer-audit` and default drift check `facade-consumer-contract` for header search, timeline reads, tour-slot reads, Ausstellung availability reads, and the allowed tour booking write.
- The local frontend view guard adds `wp iss-graph view-contract-audit` and default drift check `frontend-view-contract` for the main calendar, front-page programme, Ausstellung, Führung, and Veranstaltung template projections.
- The local occurrence recurrence guard extends `wp iss-occurrences drift-check` so active public SuperSaaS-generated tour occurrences must resolve through the service-owned series table back to their parent `fuehrung`.
- SuperSaaS series/tag source resolution and imported title/tag/fallback metadata now read from the service-owned series table. The retired `iss_occurrences_series_map` and `iss_occurrences_source_map` options are migrated into table columns, deleted, and guarded by occurrence drift.
- The local editor UX audit found no editor-visible occurrence/calendar/programme CPT. Editors continue to work in parent objects; SuperSaaS sync remains an operational Tools page.
- Facade payloads now carry schema intent: occurrence payloads are Event-emitting records, while Ausstellung availability payloads are non-Event CreativeWork availability records.
- The graph hygiene data artifacts for alias replay, KWO/AEG, and Industriesalon/WF are reviewed and applied locally/staging-side. Local alias replay is clean after sync.
- `iss-core` remains helper conventions only. `iss-frontend` provides shared frontend helpers consumed by `iss-programm`.
- No SQL/uploads transfer artifact is required for this checkpoint. SuperSaaS source/series option migration is handled by the `iss-occurrences` schema installer.

## Current Risk

- No production target is in scope for the current workflow.
- Staging/live state is allowed to be disposable. Keep source truth in Git plus explicit SQL/data artifacts, then rebuild/reapply if needed.
- Staging/live remains disposable, but the reviewed graph hygiene artifacts are now applied locally too.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk files are live.
- History was rewritten on 2026-06-12. Existing secondary clones should be re-cloned or reset deliberately before use.
- `/home/vladimir/industriesalon-export` is a stale local clone and should not be used for deploy/push as-is.

## Next Action

- UI polish later, especially clean Ausstellung search/filter interaction and public view polish.
- Push the local closeout commit only when explicitly requested.

## Verified

- Staging/live was reported green for the rendered Ausstellung availability browser.
- Latest local verification for the rendered availability browser consumer passed: JS syntax, JS/CSS lint, PHP syntax, PHPCS target, PHPStan target, facade checks, availability drift, direct REST/render probes, `/ausstellungen/` HTTP probe, Playwright in-place filter/search smoke, and `git diff --check`.
- Local graph data alignment verification passed: alias replay dry-run clean, `alias-backfill-replay`, `canonical-organization-seeds`, `canonical-wf-industriesalon`, `wp iss-graph verify`, focused entity-hygiene audit, and default graph drift.
- Availability editorial-signal verification passed: PHP syntax, PHPCS target, PHPStan target, temporary pin/suppress runtime probe with cleanup, `editorial-signals`, `facade-availability-compare`, `availability-contract`, default graph drift, and `git diff --check`.
- Current local graph facade/editor UX audit verification passed: PHP syntax, PHPCS target, PHPStan target, direct REST probes, `facade-check`, facade search/occurrence/entity-occurrence/availability/entity/entity-relation compares, default graph drift, strict Ausstellung availability audit, runtime post-type audit, source Schema.org/admin-surface audit, and `git diff --check`.
- Current local Offer consumer pass verification passed: PHP syntax, PHPCS target, PHPStan target, runtime probes for contract/search/card/timeline labels, `facade-check`, `facade-search-compare`, focused graph drift, default graph drift, and `git diff --check`.
- Current local facade consumer guard verification passed: PHP syntax, PHPCS target, PHPStan target, `facade-consumer-audit`, `facade-check`, `facade-search-compare`, default graph drift, and `git diff --check`.
- Current local frontend view guard verification passed: PHP syntax, PHPCS target, PHPStan target, `view-contract-audit`, `facade-consumer-audit`, focused view/consumer drift, default graph drift, and `git diff --check`.
- Current local occurrence recurrence guard verification passed: PHP syntax, PHPCS target, PHPStan target, `wp iss-occurrences drift-check --limit=25`, default graph drift, and `git diff --check`.
- Current local service-table recurrence resolution verification passed before the metadata-table migration: PHP syntax, PHPCS target, PHPStan target, focused runtime resolver probe, `wp iss-occurrences drift-check --limit=25`, default graph drift, and `git diff --check`.
- Current local SuperSaaS series/tag source table migration verification passed: PHP syntax, PHPCS target, PHPStan target, schema migration probe, focused runtime resolver/tag probe, `wp iss-occurrences drift-check --limit=25`, `wp iss-fuehrungen drift-check --limit=25`, `wp iss-graph facade-check --limit=2`, `wp iss-graph facade-tour-slots-compare --limit=5`, default graph drift, and `git diff --check`.
- Current local tour Offer catalog guard verification passed: PHP syntax, PHPCS target, PHPStan target, `wp iss-fuehrungen drift-check --limit=25`, `wp iss-graph facade-check --limit=2`, default graph drift, and `git diff --check`.
