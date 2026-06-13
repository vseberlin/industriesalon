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

- Phases 1-4 are committed on `origin/main` and have passed the first staging validation pass as of 2026-06-12.
- Ausstellung availability classification remains the `ausstellung_typ` taxonomy, now exposed through editor-owned controls in `iss-content-model`.
- `iss_timeline_enabled` remains the public visibility switch for Ausstellung overview browsers.
- The graph entity hygiene guardrail now exists as the read-only `wp iss-graph entity-hygiene-audit` command, and the first local review is recorded in `docs/project/graph-entity-hygiene-review-2026-06-12.md`. The code-only alias backfill boundary is implemented: generated organization abbreviations/official names stay on organization entities, with persisted replay deferred.
- `iss-graph` now has a central entity-kind registry for canonical kinds, current storage kinds, owner plugins, post-type mappings, and legacy aliases. It keeps current rows such as `ausstellung`, `veranstaltung`, `fuehrung`, `projekt`, `page`, and `archivbeitrag` stable while exposing the canonical target names `exhibition`, `event`, `tour`, and `project`.
- `iss-graph` now exposes the first contract-only `offer` bridge without renaming CPTs or storage rows: `fuehrung` maps to `offer/tour`, while `veranstaltung` maps to `offer` subtypes from existing `_iss_event_format` and `_iss_event_layout` editor meta.
- `iss-graph` now exposes the first read-only `/wp-json/iss/v1` facade: contract, entities, entity detail, entity relations, occurrences, search, the programme timeline compatibility view, and the tour-slot read view. It delegates to existing graph, occurrence, search, programme timeline, and tour-slot services and does not remove or rename older plugin routes.
- The first public consumers are switched to `/iss/v1`: header search, timeline query reads, and tour-slot reads. Booking writes remain on `/is-tours/v1/book`.

## Current Boundary Notes

- `iss-occurrences` owns occurrence projection and programme/calendar query readiness.
- `iss-programm` renders programme and browser blocks from stable data APIs.
- `iss-content-model` owns CPT/editor meta contracts.
- `saas-api` owns SuperSaaS settings, sync, and the read-only `/iss/v1/tour-slots` facade view. Booking writes stay with `/is-tours/v1/book`.
- The theme owns templates, public composition, visual skin, and route-level page structure.
- `wp iss-graph drift-check --checks=entity-kind-contract` verifies stored entity kinds and post-backed entity mappings against the registry.
- `wp iss-graph drift-check --checks=public-object-contract` verifies published public object entity coverage, legacy storage-kind mapping, accepted `wp_post` identifiers, and required offer subtypes for `fuehrung` / `veranstaltung`.
- `wp iss-graph drift-check --checks=entity-relations-contract` verifies the nested relation facade can return outgoing/incoming public graph relations with the expected response shape.
- `/wp-json/iss/v1` is a facade boundary for the greenfield contract, not a new storage owner.
- `/wp-json/iss/v1` entity responses now expose additive `contract_kind`, `subtype`, and `contract` fields; old `kind`, `canonical_kind`, and `storage_kind` remain stable for existing consumers.
- `/wp-json/iss/v1/entities/{id}/relations` exposes existing graph relations with outgoing, incoming, both, family, source-system, and limit filters; it does not create relation storage.
- `wp iss-graph facade-check` verifies the `/iss/v1` route contract before any consumer is switched to the facade.
- `wp iss-graph facade-search-compare` compares the search service callback with `/iss/v1/search`.
- `wp iss-graph facade-occurrences-compare` compares direct `iss_occurrences_query()` output with `/iss/v1/occurrences` before any programme consumer switches routes.
- `wp iss-graph facade-entities-compare` compares direct graph service output with `/iss/v1/entities` list/detail responses before any entity consumer switches routes.
- `wp iss-graph facade-entity-relations-compare` compares direct graph relation service output with `/iss/v1/entities/{id}/relations` before any relation consumer switches routes.
- The public header search modal is the first consumer switched to the facade; it now reads from `/wp-json/iss/v1/search`.
- `wp iss-graph facade-timeline-compare` compares the timeline service callback with `/iss/v1/timeline`, and the public timeline query frontend now reads from that facade view.
- `wp iss-graph facade-tour-slots-compare` compares the tour-slot service callback with `/iss/v1/tour-slots`, and the public tour calendar slot reader now reads from that facade view while booking submissions stay on `/is-tours/v1/book`.
- Retired read routes are no longer registered: `/iss-search/v1/search`, `/iss-programm/v1/timeline`, and `/is-tours/v1/slots`.
- `wp iss-graph drift-check --checks=facade-route-contract` verifies the final facade route boundary: required `/iss/v1` read routes, expected booking write route, retired read-route absence, and no retired read-route literals in active first-party source.
- `wp iss-graph entity-hygiene-audit` inventories duplicate normalized names and focus-term ambiguity in the existing graph tables. It is a review aid, not a drift failure or merge tool.
- Current graph hygiene review found generated `entity_alias_backfill` aliases, not trusted identifiers, as the main ambiguity source for `WF`, `KWO`, and `AEG` on non-organization entities.
- `wp iss-graph sync-aliases --dry-run` previews generated alias changes before replay; non-dry-run alias sync should wait for a reviewed data artifact on shared targets.
