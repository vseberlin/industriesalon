# Current Handoff

Updated: 2026-06-13

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch: `main`; backend knowledge-graph refactor checkpoint is closed locally. The occurrence/payment cleanup, commerce hardening, and plugin domain rename checkpoints are local-only until explicitly pushed.
- GitHub repo `vseberlin/industriesalon` is private. `origin/main` currently includes the greenfield refactor slices through the rendered Ausstellung availability browser consumer; this backend closeout checkpoint remains local until pushed.
- Staging is the current live working target, not a production release gate. If it breaks, it can be rebuilt from Git plus the known SQL/data artifacts.
- Domain plugin ownership is now `iss-content` for CPT/editor contracts and Führung module data, `iss-occurrences` for dated occurrence projection, `iss-frontend` for programme/timeline/browser rendering, `iss-commerce-lite` for SuperSaaS slot reads plus booking/order request intake, `iss-archive` for archive runtime, and `iss-graph` for graph/search/contracts. The theme owns public templates/skins.
- Occurrence rows are source-post keyed only. Schema v7 removes retired `entity_id` and `location_entity_id` columns/indexes; graph-facing occurrence reads translate entity filters to source post filters at the `iss-graph` facade boundary and compute entity IDs on read.
- Recurring tour grouping now runs inside the `iss-occurrences` query service with paged grouped SQL. `iss-frontend` remains a renderer/query consumer and no longer fetches all occurrence rows to group recurring tours in PHP.
- `iss-content/includes/timeline-sync.php` is absent; the old content-model path is retired. Ausstellung permanence is read from the editor-owned `ausstellung_typ` taxonomy helper, and retired `iss_is_permanent` meta is cleaned/guarded as drift.
- `iss-commerce-lite` now stores public booking/order requests in the existing `wp_iss_payments_lite_requests`; the old capped `is_tours_booking_requests` and `iss_publication_order_requests` options are migration inputs only.
- `iss-commerce-lite` production request intake is now operationally visible through `Tools > ISS Anfragen` and `wp iss-commerce-lite verify`. Public writes require REST nonce by default, enforce honeypot/rate-limit/body-size/submit-timing checks, and reject duplicate request hashes persistently.
- Commerce-lite accepts `onsite` request capture by default. Online settlement methods such as Mollie must be added by an explicit provider integration before the server accepts them.
- `/wp-json/iss/v1` is the read-only facade boundary. Active public reads are contract, entities, entity detail, entity relations, entity-scoped occurrences, occurrences, search, timeline, availability, and tour-slots.
- Public consumers already on the facade: header search, timeline query reads, tour-slot reads, and the progressively enhanced Ausstellung availability browser. Booking writes stay on `/is-tours/v1/book`.
- `/ausstellungen/` uses the dedicated `industriesalon/ausstellungen-browser` and WP_Query availability filters. Dauer/Digital Ausstellungen are availability-only and do not sync into occurrence rows.
- Ausstellung editors now have a self-scoped `availability` editorial-signal surface. It affects only automatic Ausstellung browser ordering/visibility: `pin`, `feature`, and `boost` move entries up; `suppress` removes them from automatic browser results.
- The offer bridge is contract-only: `fuehrung` maps to `offer/tour`; `veranstaltung` maps to `offer` subtypes from existing `_iss_event_format` and `_iss_event_layout` editor meta. The local checkpoint expands accepted event subtypes without renaming CPTs or storage rows.
- The local public Offer consumer pass centralizes Offer subtype public labels in `iss-graph` and consumes them in header search result labels, shared related-card kickers, and timeline-card badges. This does not change editor storage or redesign the UI.
- The local tour Offer catalog guard is available as `wp iss-content tours-drift-check` so published `fuehrung` catalog posts must remain visible in the catalog query, resolve as `offer/tour`, use known catalog groups, and keep the expected catalog renderer shell. The old `wp iss-fuehrungen drift-check` alias remains for compatibility.
- The local facade consumer guard adds `wp iss-graph facade-consumer-audit` and default drift check `facade-consumer-contract` for header search, timeline reads, tour-slot reads, Ausstellung availability reads, and the allowed tour booking write.
- The local frontend view guard adds `wp iss-graph view-contract-audit` and default drift check `frontend-view-contract` for the main calendar, front-page programme, Ausstellung, Führung, and Veranstaltung template projections.
- The local occurrence recurrence guard extends `wp iss-occurrences drift-check` so active public SuperSaaS-generated tour occurrences must resolve through the service-owned series table back to their parent `fuehrung`.
- SuperSaaS series/tag source resolution and imported title/tag/fallback metadata now read from the service-owned series table. The retired `iss_occurrences_series_map` and `iss_occurrences_source_map` options are migrated into table columns, deleted, and guarded by occurrence drift.
- The local editor UX audit found no editor-visible occurrence/calendar/programme CPT. Editors continue to work in parent objects; SuperSaaS sync remains an operational Tools page.
- Facade payloads now carry schema intent: occurrence payloads are Event-emitting records, while Ausstellung availability payloads are non-Event CreativeWork availability records.
- The graph hygiene data artifacts for alias replay, KWO/AEG, and Industriesalon/WF are reviewed and applied locally/staging-side. Local alias replay is clean after sync.
- `iss-core` remains helper conventions plus the one-time active-plugin basename migrator for this domain rename. `iss-frontend` now owns shared frontend helpers and the programme renderer module.
- No SQL/uploads transfer artifact is required for this checkpoint. Occurrence graph-column cleanup, retired occurrence/options cleanup, retired Ausstellung permanent meta cleanup, payments request-option migration, and old active plugin basename migration are handled by plugin schema/runtime installers.

## Current Risk

- No production target is in scope for the current workflow.
- Request notification mail is implemented but disabled by default for staging safety. Enable it per environment only after the approved mail mode and recipient are verified.
- On first deploy to a database that still lists old plugin basenames, `iss-core` rewrites `active_plugins` to `iss-content`, `iss-frontend`, `iss-commerce-lite`, and `iss-archive`. Verify `wp plugin list` after deploy.
- Staging/live state is allowed to be disposable. Keep source truth in Git plus explicit SQL/data artifacts, then rebuild/reapply if needed.
- Staging/live remains disposable, but the reviewed graph hygiene artifacts are now applied locally too.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk files are live.
- History was rewritten on 2026-06-12. Existing secondary clones should be re-cloned or reset deliberately before use.
- `/home/vladimir/industriesalon-export` is a stale local clone and should not be used for deploy/push as-is.

## Next Action

- UI polish later, especially clean Ausstellung search/filter interaction and public view polish.
- Push the local cleanup/rename commits only when explicitly requested.
- Before any production deployment, verify the target mail mode and enable `Tools > ISS Anfragen` notifications only for an approved recipient if operational email is desired.

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
- Current local SuperSaaS series/tag source table migration verification passed: PHP syntax, PHPCS target, PHPStan target, schema migration probe, focused runtime resolver/tag probe, `wp iss-occurrences drift-check --limit=25`, `wp iss-content tours-drift-check --limit=25`, `wp iss-graph facade-check --limit=2`, `wp iss-graph facade-tour-slots-compare --limit=5`, default graph drift, and `git diff --check`.
- Current local tour Offer catalog guard verification passed: PHP syntax, PHPCS target, PHPStan target, `wp iss-content tours-drift-check --limit=25`, `wp iss-graph facade-check --limit=2`, default graph drift, and `git diff --check`.
- Current local occurrence/payment cleanup verification passed: PHP syntax, JS syntax, PHPCS target, PHPStan target, `npm run lint:js -- --quiet`, occurrence schema/option/meta/table probes, `wp iss-occurrences verify`, `wp iss-occurrences sync --source=wp`, `wp iss-occurrences drift-check --limit=25`, grouped occurrence query smoke, payments insert/cleanup probe, REST route registration probe, `wp iss-graph facade-check --limit=2`, `wp iss-graph facade-occurrences-compare --limit=5`, `wp iss-graph facade-entity-occurrences-compare --limit=5`, and `git diff --check`.
- Current local commerce-lite production-hardening verification passed: PHP syntax, JS syntax, PHPCS target, PHPStan target, `npm run lint:js -- --quiet`, `wp iss-commerce-lite verify`, v2 schema column probe, settings probe, REST route registration probe, missing-nonce guard probe, submit-timing guard probe, unsupported `mollie` rejection probe, onsite publication request insert/cleanup probe, and admin request query probe.
- Current local plugin domain rename verification passed: PHP syntax for renamed domain plugins and changed graph/core files, JS lint, PHPCS target, PHPStan target, active-plugin basename migration probe, `wp plugin list`, `wp iss-content tours-drift-check --limit=25`, `wp iss-frontend ausstellungen-audit --strict`, `wp iss-commerce-lite verify`, legacy command aliases, `wp iss-occurrences verify`, `wp iss-occurrences drift-check --limit=25`, graph facade/consumer/tour-slot/availability compares, default graph drift, and `git diff --check`.
