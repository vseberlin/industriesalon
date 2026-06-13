# Greenfield Refactor Plan

This is the repo-owned plan for the gradual Industriesalon refactor. It records direction and sequence only; it is not a request to rename plugins or move public routes in one pass.

## Architecture Direction

- Model the system as `Entity / Relation / Occurrence / View`.
- Treat the calendar as occurrence-only. It should show dated programme occurrences, not broad availability states.
- Treat Dauer and Digital Ausstellungen as exhibition availability, not calendar events.
- Keep public presentation in the active theme.
- Keep plugins responsible for data contracts, services, integrations, and dynamic block data.

## Phased Path

1. Stabilize the current programme/calendar checkpoint on the occurrence projection.
2. Build a dedicated Ausstellung availability browser that is separate from calendar and timeline occurrence queries.
3. Add `iss-core` for infrastructure conventions only.
4. Add `iss-frontend` for shared frontend runtime helpers only.
5. Defer domain plugin extraction until the boundaries are proven by reused services and stable contracts.
6. Harden shared contracts in place before migrations: start with the `iss-graph` entity-kind registry, then use drift checks to prove current rows match the contract.

## Current Checkpoint

- Phases 1-4 are committed on `origin/main`. Staging is the current live working target and can be rebuilt from Git plus the known SQL/data artifacts when needed.
- Ausstellung availability classification remains the `ausstellung_typ` taxonomy, now exposed through editor-owned controls in `iss-content-model`.
- `iss_timeline_enabled` remains the public visibility switch for Ausstellung overview browsers.
- The graph entity hygiene guardrail now exists as the read-only `wp iss-graph entity-hygiene-audit` command, and the first local review is recorded in `docs/project/graph-entity-hygiene-review-2026-06-12.md`. The code-only alias backfill boundary is implemented: generated organization abbreviations/official names stay on organization entities, with persisted replay deferred.
- The reviewed alias replay, KWO/AEG organization seeds, and Industriesalon/WF curation artifacts are now applied locally as well as on staging; alias replay dry-run is clean.
- `iss-graph` now has a central entity-kind registry for canonical kinds, current storage kinds, owner plugins, post-type mappings, and legacy aliases. It keeps current rows such as `ausstellung`, `veranstaltung`, `fuehrung`, `projekt`, `page`, and `archivbeitrag` stable while exposing the canonical target names `exhibition`, `event`, `tour`, and `project`.
- `iss-graph` now exposes the first contract-only `offer` bridge without renaming CPTs or storage rows: `fuehrung` maps to `offer/tour`, while `veranstaltung` maps to `offer` subtypes from existing `_iss_event_format` and `_iss_event_layout` editor meta.
- Offer subtype public labels are centralized in `iss-graph` and advertised through `/iss/v1/contract`. Header search result labels, shared related-card kickers, and timeline-card badges consume those labels without exposing contract internals to editors or users.
- `iss-graph` now exposes the first read-only `/wp-json/iss/v1` facade: contract, entities, entity detail, entity relations, entity-scoped occurrences, occurrences, search, the programme timeline projection, the Ausstellung availability read view, and the tour-slot read view. It delegates to existing graph, occurrence, search, programme timeline, Ausstellung availability, and tour-slot services and does not remove or rename older plugin routes.
- The first public consumers are switched to `/iss/v1`: header search, timeline query reads, tour-slot reads, and the progressively enhanced Ausstellung availability browser. Booking writes remain on `/is-tours/v1/book`.
- `iss-graph` editorial signals now have separate `related`, `search`, and `availability` surfaces. Availability signals are self-scoped to Ausstellung posts and affect only automatic browser ordering/visibility.
- The 2026-06-13 editor UX audit is recorded in `docs/project/kg-editor-ux-audit-2026-06-13.md`: no occurrence/calendar/programme CPT is editor-visible, so no editor menu removal is needed in this slice.

## Current Boundary Notes

- `iss-occurrences` owns occurrence projection and programme/calendar query readiness.
- SuperSaaS series/tag source resolution and imported title/tag/fallback metadata live in the service-owned series table; the retired `iss_occurrences_series_map` and `iss_occurrences_source_map` options are migration-only and drift-guarded.
- `wp iss-occurrences drift-check` verifies active public SuperSaaS generated occurrences resolve through the service-owned series table back to linked parent posts; retired mapping helpers are not the recurrence contract.
- `wp iss-fuehrungen drift-check` verifies the public tour Offer catalog stays aligned with the `offer/tour` graph contract, includes published tours, uses known catalog groups, and keeps the expected renderer shell.
- `iss-programm` renders programme and browser blocks from stable data APIs.
- `iss-content-model` owns CPT/editor meta contracts.
- `saas-api` owns SuperSaaS settings, sync, and the read-only `/iss/v1/tour-slots` facade view. Booking writes stay with `/is-tours/v1/book`.
- The theme owns templates, public composition, visual skin, and route-level page structure.
- `wp iss-graph drift-check --checks=entity-kind-contract` verifies stored entity kinds and post-backed entity mappings against the registry.
- `wp iss-graph drift-check --checks=public-object-contract` verifies published public object entity coverage, legacy storage-kind mapping, accepted `wp_post` identifiers, and required offer subtypes for `fuehrung` / `veranstaltung`.
- `wp iss-graph drift-check --checks=entity-relations-contract` verifies the nested relation facade can return outgoing/incoming public graph relations with the expected response shape.
- `wp iss-graph drift-check --checks=availability-contract` verifies the Ausstellung availability facade can return the four existing browser filters plus a search scenario with the expected structured and rendered response shape.
- `wp iss-graph drift-check --checks=editorial-signals` now requires reason, author, and valid expiry metadata for every active editorial signal; the old active `related/feature` grandfather exception is removed after the explicit cleanup artifact.
- The Ausstellung availability browser consumes active self-scoped `availability` signals: `pin`, `feature`, and `boost` move entries up; `suppress` removes them from automatic browser results. Search and related signals stay separate.
- `/wp-json/iss/v1` is a facade boundary for the greenfield contract, not a new storage owner.
- `/wp-json/iss/v1` entity responses now expose additive `contract_kind`, `subtype`, and `contract` fields; old `kind`, `canonical_kind`, and `storage_kind` remain stable for existing consumers.
- `/wp-json/iss/v1/entities/{id}/relations` exposes existing graph relations with outgoing, incoming, both, family, source-system, and limit filters; it does not create relation storage.
- `/wp-json/iss/v1/entities/{id}/occurrences` exposes existing occurrence rows scoped to a graph entity. It does not create occurrence storage or an editor-visible occurrence CPT.
- `/wp-json/iss/v1/search` results expose `result_kind`, `entity_id`, `contract_kind`, `subtype`, and `contract` so consumers can distinguish entity-backed public objects from plain post results.
- `/wp-json/iss/v1/availability` exposes existing `iss-programm` Ausstellung availability-browser data as structured JSON and server-rendered card HTML over the editor-owned `ausstellung_typ` and `iss_timeline_enabled` contracts; it does not create availability storage or duplicate card rendering in JavaScript.
- Facade payloads carry schema intent rather than rendering JSON-LD themselves: occurrences are Event-emitting records, while availability records are non-Event CreativeWork availability records.
- `wp iss-graph facade-check` verifies the `/iss/v1` route contract before any consumer is switched to the facade.
- `wp iss-graph facade-search-compare` compares the search service callback with `/iss/v1/search`.
- `wp iss-graph facade-occurrences-compare` compares direct `iss_occurrences_query()` output with `/iss/v1/occurrences` before any programme consumer switches routes.
- `wp iss-graph facade-entities-compare` compares direct graph service output with `/iss/v1/entities` list/detail responses before any entity consumer switches routes.
- `wp iss-graph facade-entity-relations-compare` compares direct graph relation service output with `/iss/v1/entities/{id}/relations` before any relation consumer switches routes.
- `wp iss-graph facade-entity-occurrences-compare` compares direct occurrence-service output with `/iss/v1/entities/{id}/occurrences` before any entity-scoped occurrence consumer is added.
- `wp iss-graph facade-availability-compare` compares the `iss-programm` availability provider callback with `/iss/v1/availability`, including the rendered HTML hash, before extending availability consumers.
- The public header search modal is the first consumer switched to the facade; it now reads from `/wp-json/iss/v1/search`.
- `wp iss-graph facade-timeline-compare` compares the timeline service callback with `/iss/v1/timeline`, and the public timeline query frontend now reads from that facade view.
- `wp iss-graph facade-tour-slots-compare` compares the tour-slot service callback with `/iss/v1/tour-slots`, and the public tour calendar slot reader now reads from that facade view while booking submissions stay on `/is-tours/v1/book`.
- Retired read routes are no longer registered: `/iss-search/v1/search`, `/iss-programm/v1/timeline`, and `/is-tours/v1/slots`.
- `wp iss-graph drift-check --checks=facade-route-contract` verifies the final facade route boundary: required `/iss/v1` read routes, expected booking write route, retired read-route absence, and no retired read-route literals in active first-party source.
- `wp iss-graph facade-consumer-audit` and drift check `facade-consumer-contract` verify known public consumers still expose the facade read routes, with `/is-tours/v1/book` as the explicit booking write exception.
- `wp iss-graph view-contract-audit` and drift check `frontend-view-contract` verify the main public views keep the DOCX projection split: calendar/front-page programme are occurrence views, Ausstellungen are availability views, Führungen are tour Offer views with occurrence-backed public dates, and Veranstaltungen are occurrence-backed event views.
- `wp iss-graph entity-hygiene-audit` inventories duplicate normalized names and focus-term ambiguity in the existing graph tables. It is a review aid, not a drift failure or merge tool.
- Current graph hygiene review found generated `entity_alias_backfill` aliases, not trusted identifiers, as the main ambiguity source for `WF`, `KWO`, and `AEG` on non-organization entities.
- `wp iss-graph sync-aliases --dry-run` previews generated alias changes before replay; non-dry-run alias sync should wait for a reviewed data artifact on shared targets.
