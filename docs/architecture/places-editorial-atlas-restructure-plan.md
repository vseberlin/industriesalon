# Places Editorial And Atlas Restructure Plan

Status: accepted direction; end-to-end pilot implemented locally, wider
migration and final ownership removal pending review.

## Implementation Checkpoint — 2026-07-19

The first operational slice is complete:

- `iss-content` registers the unchanged `register_place` CPT and the shared
  `place` editorial format;
- `iss-editorial` supports typed epoch fields and Place dry-run/import
  commands;
- post 12899 is enabled locally with nine JSON sections and six deterministic
  epoch projections;
- the theme renders enabled Place JSON and preserves legacy fallback for all
  other Places;
- enabled Place saves rebuild epoch/state projections, while their legacy epoch
  metabox is unavailable;
- TouchTable crawler/review/story runtime, feedback runtime, and nine dead
  partials are removed; the bounded retirement migration backed up then removed
  184 local snapshot rows;
- the Atlas bootstrap is compact and cached, exposes `ETag`/conditional
  responses, loads full Place detail on selection, and defers application
  startup until near the viewport;
- local Atlas bootstrap measurement is 58,535 bytes uncompressed and 9,526
  bytes gzipped for 76 Places, versus the 245,413-byte baseline;
- CPT ownership has moved, but the legacy register plugin still temporarily
  owns structured Place facts, projections, actor rows, and interactive Atlas
  PHP. Do not delete it yet.

Remaining phases are deliberately bounded: curator/editor review and wider
Place batches, graph actor/identity migration, moving interactive Atlas runtime
to `iss-frontend`, then one stable observation period before removing
compatibility tables or the old plugin. The local warm TTFB remains roughly
170–260 ms; payload and cache targets pass, but the absolute 120 ms target does
not, so future work should measure WordPress bootstrap separately rather than
reintroducing request-time JSON decoding.

This plan replaces the accumulated ownership model around
`industriesalon-schoeneweide-register` with the repository's established
content, editorial, graph, relation, projection, frontend, and theme
boundaries.

The change is a staged migration, not a new Place system. Keep the
`register_place` CPT, post IDs, public URLs, coordinates, relations, and current
Atlas behavior while their owners are corrected.

Read this plan with:

- `docs/architecture/content-model.md`
- `docs/architecture/editorial-platform.md`
- `docs/architecture/entity-model.md`
- `docs/architecture/relations.md`
- `docs/architecture/database.md`
- `docs/architecture/plugin-map.md`
- `docs/architecture/static-map-rendering.md`
- `docs/architecture/atlas-static-map-implementation-plan.md`
- `docs/agent/no-parallel-systems.md`

## Decision

`register_place` is an ordinary editorial CPT, not an application platform.

The permanent target is:

- `iss-content` owns the Place CPT, geographic facts, validation, and Place
  editorial-format registration;
- `iss-editorial` owns versioned Place JSON storage, validation, autosave,
  preview, and the shared composition interface;
- the theme owns Place dossier composition, JSON gesture rendering, public
  HTML, and visual skins;
- `iss-graph` owns shared entity identity, names, identifiers, organizations,
  people, and semantic entity relations;
- `iss-relations` continues to own editorial Place relations, including ordered
  Führung stations and their station-specific metadata;
- `iss-frontend` owns the interactive Atlas application, its compact public
  projection contract, REST delivery, cache lifecycle, and client behavior;
- the theme continues to own Atlas visual skins, map artwork, calibrated map
  assets, presets, and presentation variables;
- archive, Event Drop, Sets, and established intake services own reviewed
  incoming material and provenance;
- TouchTable crawling, Elementor/CSS parsing, and its source-review pipeline
  are retired from WordPress runtime.

Do not create a replacement `iss-places`, `iss-register-import`,
`iss-register-research`, or second Atlas plugin unless a later concrete
requirement proves that the established owners cannot hold the behavior.

## Why The Current Boundary Is Wrong

The register plugin combines responsibilities with different lifecycles:

- permanent Place profiles and geographic facts;
- a Place-specific Gutenberg exception and metabox editor;
- epochs and current-state projections;
- a private industrial-actor registry;
- graph and relation bridges;
- interactive Atlas contracts, caches, rendering, and asset registration;
- TouchTable crawling, Elementor extraction, snapshots, and review;
- external image discovery and candidate review;
- feedback capture;
- geocoding, repair, export, and CLI guardrails.

This is accumulation, not a reason to rewrite working Place content. The
existing layered register read path—repository, contracts, services,
guardrails, and routes—is useful migration protection and should be preserved
until each consumer has moved.

## Verified Local Baseline

These measurements were taken on 2026-07-19. Re-run them before implementation
because content and code can change.

### Content and projections

- 84 `register_place` posts: 83 published and one draft.
- 186 rows in `wp_iss_register_place_epochs`.
- 269 rows in `wp_iss_register_place_states`.
- 62 rows in `wp_iss_register_place_industry_actors`; 60 belong to currently
  published Places.
- All 84 Places have explicit `current_status` and `current_use_type`.
- 77 Places have `atlas_era` terms, with 104 assignments.
- Four Places have public/archive image-group data, ten have current-image
  groups, and one has document-image data.

### Obsolete runtime

- `includes/partials/` contains nine unreferenced files and about 612 lines.
- `register_feedback` has zero local rows and no repository consumer.
- `atlas_story` has zero local posts and the current Atlas context has zero
  stories.
- `register_source_item` contains 184 private TouchTable snapshots:
  182 `new`, two `ignored`, and none `linked`.
- The live TouchTable installation remains available on the server and can be
  recovered from live data or SQL backup. Therefore the crawler, snapshot
  pipeline, Elementor parser, and review UI are obsolete runtime, not canonical
  preservation.

Before deleting snapshot rows on another environment, count them and verify
that no environment-specific accepted decisions exist. A target database
backup and compact count/status inventory are sufficient; do not preserve the
crawler as an active plugin merely because snapshot rows exist.

### PHP loading

- The plugin contains 42 PHP files and about 471 KB of PHP source.
- Its current bootstrap has at least 28 unconditional project-file includes.
- Skipping the plugin reduced local WP-CLI peak memory by about 2 MiB. This is
  directional evidence, not a production request benchmark.

The target is not merely fewer files. Ordinary public requests must load only
the small Place registration/read contract they need. Admin, CLI, intake, and
interactive Atlas code must be conditional.

### Interactive Atlas loading

- `/wp-json/iss-register/v1/atlas-bootstrap` returned 245,413 uncompressed
  bytes for 76 visible Places.
- The `places` portion was about 236 KB; Atlas context was about 2.3 KB.
- Warm local response time was about 170–230 ms.
- The local response did not include compression, `ETag`, or useful public
  cache headers.
- Atlas/Leaflet CSS and JavaScript source totalled about 299 KB uncompressed
  before map imagery and raster tiles.
- Each initial Place record carried 48 fields. Large initial-only costs
  included epoch summaries, actor relations, related tours, overlapping
  summaries, and secondary detail content.

The endpoint and asset measurements are acceptance baselines, not permanent
contract requirements.

## Canonical Data Boundaries

### WordPress Place profile

The `register_place` post remains the editorial and public profile. Keep these
as structured Place facts outside the narrative JSON:

- title, slug, publish state, excerpt, featured image, and revisions;
- address;
- latitude and longitude;
- coordinate accuracy/provenance;
- operational area;
- public versus route-only visibility;
- current status and current-use classification;
- construction period;
- original name;
- monument status and official monument-record URL;
- stable post-to-entity mapping;
- fields still required for deterministic Atlas filtering or routing.

Editors should manage these facts inside the same custom Place editor through
a compact `Ortsdaten` panel. A unified interface does not imply one storage
blob.

### Graph identity and semantic relations

The graph entity is canonical for shared identity, aliases, identifiers,
organizations, people, and semantic relations. The WordPress post remains the
editorial profile; it is not disposable projection data.

- Map `register_id` into the graph identifier namespace.
- Keep WordPress post IDs as implementation references and existing relation
  keys.
- Migrate TRO, NAG, WF, KWO, BAE, and ADMOS to ordinary graph organizations.
- Replace private actor rows with graph relations carrying explicit roles and,
  where supported by the accepted graph contract, temporal bounds/evidence.
- Move actor colors and era-specific Atlas labels into Atlas configuration or
  theme skin data; they are not organization identity.
- Do not continuously recreate graph organizations from free-text owner,
  operator, developer, or tenant strings after the editor has entity pickers.

### Editorial relations

Do not collapse ordered editorial relations into generic graph relations.

`iss-relations` remains canonical for:

- ordered Führung stations;
- station title and teaser;
- station object/story references;
- explicit editorial related-Place selections;
- the existing relation save API and route behavior.

The Place editor may embed relation controls, but they must save through the
existing owning API, as the Führung JSON editor already does.

### Media and provenance

Place JSON stores typed media/object references, not copied attachment URLs or
duplicated archive records.

- WordPress attachments remain the media delivery objects.
- Attachment/archive/intake owners retain rights and provenance.
- Section placement determines editorial role such as historical epoch,
  present-day image, gallery, or material.
- Do not reproduce the old `archive_images`, `current_images`, and
  `document_images` arrays inside an unrelated new JSON shape without a
  migration decision.
- Event Drop contribution intake remains the public contribution path.

## Place JSON Contract

The Place format extends the existing ordered `iss-editorial` engine. It does
not introduce another editor framework, JSON column, CPT, or public renderer.

Initial gesture vocabulary:

- `intro`: optional editorial opening/lead;
- `epoche`: repeatable historical phase;
- `gegenwart`: present-day narrative;
- `galerie`: shared public media gesture;
- `material`: shared documents, links, and object references;
- `upload_intake`: shared contribution gesture.

Conceptual document:

```json
{
  "schema_version": 1,
  "skin": "ortsdossier",
  "variant": "standard",
  "features": [],
  "sections": [
    {
      "type": "intro",
      "title": "Ort auf einen Blick",
      "body": "..."
    },
    {
      "type": "epoche",
      "title": "Gasanstalt Oberspree",
      "start_year": 1898,
      "end_year": 1927,
      "era_key": "kaiserzeit",
      "function_key": "infrastructure",
      "body": "...",
      "media_refs": [123],
      "source_refs": []
    },
    {
      "type": "gegenwart",
      "title": "Kino und Gewerbestandort",
      "body": "...",
      "media_refs": []
    }
  ],
  "deleted_sections": []
}
```

The stored document must not contain:

- address or coordinates copied from Place facts;
- graph organization names copied for display convenience;
- ordered relation rows owned by `iss-relations`;
- Atlas filter results, caches, map coordinates, or presentation payloads;
- template layout such as fact-band position, sidebar order, Atlas placement,
  timeline direction, card count, or responsive behavior.

The shared engine currently does not expose every typed epoch field shown
above. Extend its existing registered-field/sanitization behavior minimally;
do not hide an arbitrary Place JSON editor inside one generic `body` field.
Any new typed field must have server validation, editor controls, normalized
read-model output, and migration coverage.

## Epoch And State Cutover

There must never be two editable epoch authorities.

### Before Place JSON enablement

- `wp_iss_register_place_epochs` remains the epoch write model.
- `wp_iss_register_place_states` remains a derived projection.
- Candidate JSON is disabled and read-only for public rendering.

### Candidate migration

- Import existing epoch rows into disabled `epoche` sections.
- Compare normalized values, ordering, media, source references, and current
  flags.
- Do not dual-write from both the legacy metabox and JSON editor.

### After per-Place enablement

- Enabled, valid Place JSON becomes the editorial epoch authority for that
  Place.
- Saving JSON rebuilds the existing epoch and state projections
  deterministically.
- The legacy epoch editor is removed for enabled Places.
- Disabled Places continue on the legacy write path until migrated.

### Projection policy

Keep the state table during the first migration. It is valid derived storage
and currently protects Atlas query cost.

It may later be replaced by a more compact Atlas projection after measured
parity. Never delete it merely because fields overlap, and never replace it
with request-time decoding of all Place JSON documents.

Required checks:

- one current state per published Place;
- normalized per-Place epoch/state hashes;
- rebuild idempotence;
- no projection rows for deleted/unpublished Places unless explicitly needed;
- Atlas filter parity before and after rebuild;
- projection rebuild available through a bounded CLI command.

## Public Place Rendering

The theme owns the dossier.

- Add a theme Place read/render helper consuming
  `iss_editorial_get_read_model()` for enabled documents.
- Keep the current legacy Place output as the disabled/invalid/empty fallback
  during migration.
- Move Place-specific public HTML out of the register plugin as each template
  consumer moves.
- Fact bands, timeline composition, present-day panel, related rails,
  contribution CTA, and Atlas placement remain theme composition.
- Dynamic data helpers may remain plugin-owned contracts, but they must not
  emit the complete theme skin.
- Remove the high-priority Gutenberg override only after the shared editor has
  equivalent prose, media, revisions, preview, and fallback behavior.

The first pilot is post 12899, `Kino Spreehöfe`. It exercises identity facts,
epochs, historical media, present-day use, sources, monument data, related
content, and contribution intake.

## Interactive Atlas Target

The interactive Atlas remains one application and one public projection, not a
second content model.

Target flow:

```text
Place facts + enabled Place JSON + graph relations + editorial relations
                              |
                              | save/rebuild
                              v
                  compact Atlas projection/cache
                              |
                              v
                     Atlas REST contracts
                              |
                              v
                one interactive Atlas application
```

### Hard performance guardrail

No Atlas public request may scan all Place posts and decode all Place JSON
documents.

Projection rebuild happens on:

- relevant Place save;
- epoch/graph/relation changes that affect Atlas output;
- explicit bounded CLI rebuild;
- deployment/migration when required.

### Initial payload

The initial bootstrap contains only fields required to render and filter the
overview:

- stable ID/post ID, slug, title, and permalink;
- coordinates, address, and area;
- current status and use-type keys;
- era and actor filter keys;
- one compact summary;
- one thumbnail;
- minimal visibility/filter flags.

Fetch extended content from the existing Place detail contract only after a
visitor selects a Place:

- epoch prose and media;
- extended actor relations;
- related tours and publications;
- archival summaries;
- additional images, sources, and profile detail.

### Delivery budgets

Measure on the same local environment and representative data:

- bootstrap target: at most 60 KB uncompressed;
- compressed target: at most 15 KB where server compression is available;
- warm median TTFB target: at most 120 ms or at least 40 percent better than
  the recorded baseline;
- add deterministic `ETag` and appropriate cache headers;
- return `304 Not Modified` for matching conditional requests;
- no Atlas CSS/JavaScript on pages without an Atlas surface;
- initialize Leaflet when the Atlas approaches the viewport rather than at
  unrelated page startup;
- do not increase the current Atlas JavaScript/CSS raw byte budget during
  ownership migration.

The current endpoint may change only after consumer inventory:

- if every consumer is first-party and deploys atomically, change the existing
  bootstrap contract in one controlled release;
- if an external or independently deployed consumer exists, introduce one
  versioned compact endpoint and record the old endpoint's removal condition;
- do not maintain two permanent Atlas APIs.

Keep `wp iss-register contract-check` or its successor throughout the move.
Extend it with semantic fixtures and normalized value hashes. Do not require
the optimized payload to remain byte-identical to the current oversized JSON.

## TouchTable Retirement

Decision: retire the complete TouchTable pipeline.

Remove from permanent runtime:

- live crawler and pagination;
- remote fetch and retry behavior;
- Elementor HTML/CSS parsing;
- hotspot extraction;
- `register_source_item` creation/update code;
- review screens and promotion handlers;
- runtime Atlas-story extraction from snapshots;
- TouchTable-specific admin and scheduled hooks.

Do not create a replacement import plugin.

Before target deletion:

1. take a target database backup;
2. count source items by status and record the result;
3. confirm there are no accepted/local-only decisions that are absent from the
   live TouchTable installation;
4. record that live TouchTable plus SQL backup is the recovery source;
5. remove runtime code and then delete obsolete CPT rows through a narrow,
   reviewable migration;
6. verify Place, Atlas, search, relations, and admin load without the pipeline.

If a later one-off extraction is required, implement it as a bounded `ops/`
migration reading a supplied SQL export or explicit live endpoint. It must not
return to ordinary WordPress bootstrap.

## Other Early Retirement Candidates

These require target-environment confirmation before removal:

- the nine unreferenced legacy partials;
- `register_feedback`, because Event Drop is the active contribution path and
  the local CPT has zero rows;
- `atlas_story`, because the local CPT and public story context are empty;
- legacy icon/color/kaufpreis/questions/website fields after every contract and
  renderer consumer has migrated.

Removing unused partial files improves maintainability but not request cost
because they are already unreferenced. Removing unconditionally loaded feedback,
TouchTable, research, and admin code reduces runtime surface.

## Implementation Phases

Each phase must be independently reviewable and deployable. Do not combine
schema deletion, public contract changes, editor cutover, and Atlas ownership
movement in one release.

### Phase 0 — Baseline and fixtures

- Inventory active plugins, blocks, REST consumers, templates, cron hooks, CLI
  commands, admin menus, target DB rows, and cache keys.
- Export normalized Place, epoch, state, actor, relation, and source-item
  counts plus schema versions.
- Capture semantic REST fixtures for summary, detail, Atlas, context, export,
  and a representative set of Places.
- Record current Atlas payload, response time, asset requests, and browser
  behavior.
- Verify current register, graph, relation, and static-map checks.
- Decide whether an uploads artifact is required for the 12899 pilot.

No production data or public contract changes occur in this phase.

### Phase 1 — Remove confirmed obsolete runtime

- Retire TouchTable crawler/snapshots/review according to the decision above.
- Remove dead partials.
- Remove feedback after target row/consumer confirmation.
- Remove or disable empty Atlas-story behavior after target confirmation.
- Guard activation backfills by schema/backfill version.
- Make admin, CLI, research, and repair loading conditional.

Acceptance: Place pages and Atlas remain behavior-compatible with the obsolete
modules absent.

### Phase 2 — Register Place with the shared editorial engine

- Add a `place` format contribution in `iss-content`.
- Add only the typed fields and gestures required by the agreed contract.
- Keep candidate JSON disabled by default.
- Add read-only dry-run/import-candidate commands.
- Integrate the compact `Ortsdaten` and existing relation controls into the
  shared editor without changing their storage owners.
- Import post 12899 as the first disabled candidate.

Acceptance: save/autosave/preview/revision behavior passes; public output still
uses the legacy path.

### Phase 3 — Theme renderer and pilot enablement

- Add the theme Place JSON renderer and template slot.
- Preserve disabled/invalid/empty fallback.
- Compare 12899 legacy and JSON output.
- Enable only 12899 after curator review.
- Create the paired database artifact and uploads artifact if required.

Acceptance: desktop/mobile rendering, no overflow, no block-validation errors,
and current Atlas output unchanged.

### Phase 4 — Epoch projection cutover

- Make enabled Place JSON the single epoch write authority.
- Rebuild epoch/state projections on save.
- Add normalized parity, idempotence, and drift checks.
- Remove the legacy epoch editor only for enabled Places.
- Dry-run remaining Place candidates; migrate in reviewed batches.

Acceptance: no Atlas filter/detail drift and exactly one current state per
published Place.

### Phase 5 — Atlas payload and cache optimization

- Introduce compact summary projection and per-Place detail cache.
- Replace full-collection full-entity transient reads.
- Use versioned invalidation rather than wildcard raw option deletion.
- Add `ETag`, cache headers, conditional response, and deferred detail fetch.
- Defer Leaflet initialization until the Atlas approaches the viewport.

Acceptance: delivery budgets above pass with the same visible/filterable Place
coverage.

### Phase 6 — Move ordinary Place ownership

- Move CPT, essential meta, validation, and public read contract to the
  `iss-content` Place module.
- Update capability-owner documentation without changing capability names.
- Move remaining Place public presentation into the theme.
- Preserve `register_place`, rewrite rules, post IDs, and URLs.
- Remove the forced Gutenberg exception after shared-editor equivalence.

Acceptance: Places edit/render without loading Atlas, TouchTable, feedback,
research, or migration code.

### Phase 7 — Consolidate graph identity and actors

- Map legacy register identifiers into graph identifiers.
- Migrate actor definitions to graph organizations.
- Migrate actor rows to reviewed semantic graph relations.
- Keep ordered editorial relations in `iss-relations`.
- Move Atlas colors/presentation labels out of entity identity.

Acceptance: graph drift checks pass, Atlas actor filters remain equivalent, and
no private actor authority remains.

### Phase 8 — Move interactive Atlas ownership

- Move public Atlas PHP/runtime behavior and client code to
  `iss-frontend/modules/interactive-atlas`.
- Keep theme-owned artwork, calibrated assets, presets, and skins in the theme.
- Stop the Place owner from registering theme-dependent Atlas assets.
- Preserve one Atlas application and the accepted compact projection.

Acceptance: Atlas can be disabled without affecting Place storage, editing,
single Place pages, search, relations, or static map contracts.

### Phase 9 — Retire the old plugin

- Remove expired compatibility functions and constants after consumer search.
- Remove obsolete tables only after one stable observation period, verified
  exports, and an accepted replacement projection.
- Update plugin map, database map, content model, source-of-truth docs,
  changelog, TODO, and handoff.
- Deactivate/remove `industriesalon-schoeneweide-register` only when no
  permanent owner still depends on it.

## Verification Bundle

Select checks proportionally for each phase, but the complete migration must
cover:

```bash
docker compose run --rm wpcli iss-register contract-check --allow-root
docker compose run --rm wpcli iss-register place-state-check --allow-root
docker compose run --rm wpcli iss-relations map-block-audit --allow-root
docker compose run --rm wpcli iss-relations static-map-contract-check --allow-root
docker compose run --rm wpcli iss-graph drift-check --limit=25 --allow-root
docker compose run --rm wpcli iss-graph facade-check --limit=2 --allow-root
```

Also run:

- targeted PHP lint, PHPCS, and PHPStan;
- JavaScript lint and targeted Stylelint;
- JSON document validation and save/restore checks;
- projection rebuild twice with equal normalized output;
- REST schema and semantic fixture comparison;
- desktop and mobile browser checks for Place and Atlas;
- horizontal-overflow checks;
- Atlas search, use, era, actor, reset, detail, fullscreen, and kiosk behavior;
- endpoint byte size, response headers, median TTFB, and conditional request;
- editor check for facts, relations, epochs, media, preview, autosave, and
  revisions;
- DB row counts before/after every migration;
- paired SQL/API and uploads artifacts whenever DB-backed content references
  local media.

## Non-Goals

- Do not rename `register_place` or change its public URLs.
- Do not build another JSON editor.
- Do not make JSON the storage for query-critical geographic facts.
- Do not decode every Place JSON document during Atlas requests.
- Do not move ordered route stations into generic graph relations.
- Do not create a universal temporal ontology before a demonstrated second
  domain needs it.
- Do not rewrite the Atlas visual design during ownership migration.
- Do not preserve TouchTable as an active plugin.
- Do not drop tables in the same release that first stops writing them.
- Do not introduce a complex event bus; a few explicit owner hooks are enough.
- Do not keep permanent compatibility APIs without named consumers and a
  removal condition.

## First Bounded Implementation Slice

The next agent should implement only Phase 0 plus the non-destructive part of
Phase 1:

1. produce machine-readable baseline counts and normalized REST fixtures;
2. record Atlas payload/timing/assets using repeatable commands;
3. inventory target consumers and target TouchTable/feedback/story rows;
4. add projection/cache contract tests before changing payloads;
5. remove the nine dead partials;
6. make an explicit reviewed removal artifact for TouchTable and feedback, but
   do not delete target rows without backup evidence;
7. stop and review before adding Place JSON code.

Do not begin by moving the CPT, renaming the plugin, dropping a table, or
rewriting Atlas JavaScript.

## Completion Criteria

The restructure is complete when:

1. Places use the shared JSON editor and theme renderer.
2. Place facts remain structured and queryable outside narrative JSON.
3. Epoch JSON has one write authority and deterministic projections.
4. Atlas never decodes the Place collection at request time.
5. Atlas bootstrap meets the accepted payload/cache budget.
6. Graph owns shared identity and organizations.
7. `iss-relations` still owns ordered editorial relations.
8. TouchTable, feedback, dead partials, and obsolete research/import runtime no
   longer load.
9. A Place can edit/render with interactive Atlas disabled.
10. Interactive Atlas can run from ordinary Place/graph/relation projections
    without owning the Place CPT.
11. Existing post IDs, URLs, visibility, search, tours, static maps, and public
    Place profiles remain intact.
12. The old register plugin has no permanent responsibility and is removed.
