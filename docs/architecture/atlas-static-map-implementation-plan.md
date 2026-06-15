# Atlas And Static Maps Implementation Plan

Date: 2026-06-15

## Purpose

This document records the audit and implementation plan for the Atlas and static
map stack. The goal is long-term stability: clear ownership, no parallel config
roots, no duplicate place model, and clean separation between place resolution,
static map rendering, and the interactive Atlas app.

This is a planning document. Completed implementation history belongs in
`CHANGELOG.md`; current operational state belongs in `handoff_CURRENT.md`.

## Current Shape

The intended ownership split is already visible:

- `industriesalon-schoeneweide-register` owns `register_place`, place state,
  eras, actors, coordinates, interactive Atlas REST payloads, and register
  caches.
- `iss-relations` owns relation and place selection: `source`, `placeIds`,
  `current`, `related`, `route`, and graph/taxonomy lookup.
- `iss-frontend/modules/static-maps` is intended to own static map frontend
  rendering.
- The theme owns page composition, CSS skins, static map image assets, marker
  JSON, block patterns, and visual preset definitions.

The direction is correct, but the implementation is still transitional.
`iss-frontend` has top-level static map render entry points, while many low-level
map helpers still live in `iss-relations/includes/blocks.php`: marker lookup,
marker position resolution, crop/focus math, rotation/projection helpers, stage
renderers, panel/body renderers, and spine strip projection.

The interactive Atlas is separate from static map strips and currently lives in
the register plugin. It uses one Atlas block, one REST-backed runtime, Leaflet,
external raster tiles, overlays, filters, and place/detail UI.

## Main Risks

The highest long-term risk is duplicate truth:

- duplicate place models
- duplicated map defaults
- duplicated marker sources
- duplicated Atlas payloads
- parallel static and interactive map configuration roots

The second risk is implicit WordPress object/meta shape becoming the only
architecture contract. WordPress remains the editorial shell, storage layer,
publishing surface, and access-control layer; plugin-owned contracts should make
place, relation, graph, archive, and map behavior explicit and testable.

The third risk is UI convenience code hardening into data architecture. Static
marker JSON, Gutenberg block attributes, editor preview state, CSS presets, and
theme assets are presentation projections. They must not become canonical data
models.

## Audit Findings

- The source direction is mostly right: register data -> relation/place
  resolution -> frontend render -> theme skin/assets.
- `iss-relations` has grown into a mixed surface: related cards, place source
  contracts, static maps, spine strips, asymmetric fields, graph/editorial signal
  editor controls, and preview UI.
- The current static map split is incomplete because render ownership is
  documented as `iss-frontend`, while render internals still live in
  `iss-relations`.
- Static marker JSON is valid and useful, but it is derived projection data. It
  needs a tracked generator/runbook or explicit manual-maintenance rules with a
  strict coverage audit.
- The current `iss-relations map-block-audit` verifies block source/preset shape
  and marker JSON readability, but it does not yet verify that selected/current
  places resolve to marker coordinates.
- Static map usage in templates is small and traceable. First-class surfaces are
  `iss/related-place-map`, `iss/atlas-slice`, and `iss/spine-strip`.
  `iss/atlas-strip` and `iss/asymmetric-split-field` are not current public
  template surfaces.
- A current in-progress regression was identified:
  `iss_relations_resolve_block_posts()` references an undefined `$block`.

## Target Boundaries

Use one data contract and split responsibilities by layer:

- `iss-core`: shared contracts, diagnostics, capability registration, version
  checks, and low-level helpers. No rendering and no domain-specific UI.
- `iss-content`: editorial CPT/editor contracts for events, projects,
  exhibitions, publications, videos, tours, and programme-facing source content.
  It exposes clean read contracts, not map or graph logic.
- `iss-archive`: archive objects, archive sets, imports, evidence, source
  references, media/object metadata, and archive query contracts. It is a data
  and repository layer, not a page renderer.
- `industriesalon-schoeneweide-register`: canonical places, buildings, eras,
  actors, coordinates, Atlas payloads, and place caches.
- `iss-graph`: normalized cross-domain relation index connecting places, archive
  objects, videos, projects, events, publications, tours, actors, and themes. It
  is queryable and exportable; it does not render UI.
- `iss-relations`: thin relation and place-selection resolver. It owns source
  contracts such as `current`, `manual`, `related`, and `route`, plus editor
  controls for those selections. It does not render static maps.
- `iss-frontend`: reusable frontend renderers and public components. For this
  plan, it owns static map rendering. It may render archive/programme/list
  components from plugin-owned read contracts, but it must not own archive,
  occurrence, graph, or place data.
- Theme: page composition, templates, CSS, visual presets, block patterns, static
  map image assets, and brand skin.

Do not create a new plugin or second Atlas app.

## Data Contracts

Map and relation surfaces should consume explicit DTO-like arrays rather than
raw `WP_Post` objects wherever practical at plugin boundaries.

Place contracts should include:

- canonical ID
- slug
- title
- short label
- coordinates, if available
- map marker projection, if available
- address or location label, if relevant
- type/category
- active era/state metadata, if relevant
- public URL
- excerpt/teaser
- thumbnail/media reference, if relevant

Relation result contracts should distinguish:

- selected entity
- related entities
- route/order
- relation reason/type
- source used to resolve the relation
- confidence or editorial/manual status, if needed later

Apply this first to Atlas and static-map boundaries. Do not turn this into a
broad unrelated DTO refactor.

## Static Map Target

Static maps should follow one deterministic path:

```text
theme/editor block attributes
  -> iss-relations source contract
  -> resolved place DTOs
  -> iss-frontend static map renderer
  -> theme CSS/assets/presets
```

`iss-relations` should register blocks and resolve place selections, but should
not own marker lookup, crop math, projection math, static stage rendering, panel
rendering, or body markup. Those belong in `iss-frontend/modules/static-maps`.

The static renderer should be deterministic and testable. Given the same
resolved place DTOs, marker JSON, image asset, and preset, it should produce the
same HTML and should not perform hidden relation lookups during rendering.

Keep as first-class static surfaces:

- `iss/related-place-map`
- `iss/atlas-slice`
- `iss/spine-strip`

Remove or explicitly mark as experimental until there is a public consumer:

- `iss/atlas-strip`
- `iss/asymmetric-split-field`

## Interactive Atlas Target

The interactive Atlas should remain one public block and one REST-backed app.
Split internals into modules:

- `atlas-config`: reads bootstrap configuration and provider settings
- `atlas-store`: owns loaded payload, selected place, filters, routes, and UI
  state
- `atlas-map`: Leaflet adapter only; no domain filtering logic
- `atlas-places`: search, list, filters, grouping, and selection UI
- `atlas-detail`: detail panel and popup content
- `atlas-layout`: embedded, fullscreen, and kiosk/touch behavior
- `atlas-provider`: tile provider adapter and attribution handling

The map adapter should receive commands such as:

- `setViewport(preset)`
- `focusPlace(placeId)`
- `showPlaces(placeIds)`
- `showRoute(routeId)`
- `resize()`

Filtering and place selection should live in the store/UI modules, not inside
Leaflet event code.

## Leaflet And Provider Decision

Leaflet remains the right map engine for this product.

Reasons:

- the Atlas is a bounded 2D editorial map
- it needs markers, overlays, popups, filters, and place focus
- the dataset is modest
- Leaflet is stable, lightweight, easy to vendor, and already integrated
- fullscreen/viewport switching is manageable with a layout adapter and
  `map.invalidateSize()`

Do not adopt MapLibre now. Reconsider it only if the Atlas later needs custom
vector basemap styling, pitch/bearing, offline vector tiles, large animated
layers, or heavy point decluttering.

Tile providers should be hidden behind a provider adapter:

1. MapTiler when key and style are configured.
2. Carto or another no-key provider as fallback.
3. Local/static fallback for deterministic kiosk/offline cases.

No tile URL should be hardcoded directly in runtime JS. Provider configuration
should come from PHP bootstrap config or a theme map manifest.

## Fullscreen, Viewports, And Kiosk

Fullscreen and touch/kiosk modes do not require a second Atlas app. They require
a stronger layout module.

Recommended layout modes:

- `embedded`
- `fullscreen`
- `kiosk`

Recommended view presets:

- `overview`
- `oberschoeneweide`
- `niederschoeneweide`
- `nalepastrasse`
- `selected-place`
- `route`

Each preset should define center or bounds, zoom, min/max zoom if needed, and
preferred panel state. View presets should be config, not hardcoded map behavior.

The layout module should own body scroll locking, escape/back behavior, touch hit
areas, panel state, idle/reset behavior for kiosk mode, and calls to
`map.invalidateSize()` after container changes.

## Archive And Graph Direction

Archive and graph are infrastructure layers for interpretation, not page-layout
systems.

Archive objects should remain stable records with source, provenance, metadata,
media, and object-level description. Exhibitions, publications, Atlas dossiers,
and kiosk views should consume archive objects through query contracts and
curated selections, not duplicate archive metadata into presentation blocks.

The graph layer should be the cross-domain relation index for:

- place -> archive objects
- place -> videos
- place -> events
- place -> publications
- actor -> places
- era -> places
- project -> places
- tour -> route places

The graph should support editorial/manual relations first, inferred relations
second, and algorithmic recommendations only later if needed. Editorial trust is
more important than automatic abundance.

## Editor Experience

The architecture should hide complexity from editors.

Editors should not need to understand graph internals, marker JSON, provider
configuration, or REST payloads. Their interfaces should ask simple editorial
questions:

- Which place does this belong to?
- Should the map show the current place, related places, or a manual route?
- Is this relation editorially important?
- Should this appear in Atlas, archive, publication, or event context?

Preferred controls:

- radio choices instead of free-form config where possible
- searchable entity pickers
- preview warnings for unresolved places or missing markers
- visible relation reason labels
- defaults that work without configuration
- strict audits for administrators, not ordinary editors

## Audit Requirements

The audit layer should become a first-class maintenance tool.

Minimum checks:

- PHP syntax lint for affected plugin files
- block registration sanity
- unreadable map image or marker JSON
- unknown static map preset
- selected/manual place without marker coordinates
- `source:"current"` place page without marker coordinates
- `placeIds` used with a non-manual source
- marker JSON entries that do not correspond to canonical places unless
  explicitly documented
- REST payload schema validation for Atlas
- provider config validation
- frontend smoke test for embedded and fullscreen Atlas

The audit should use the same defaults and contracts as production code. It
should not duplicate configuration values in separate scan logic.

## Clean Rewrite Sequence

Because this is a development machine and legacy compatibility is not required,
prefer a clean internal rewrite over temporary delegation layers.

1. Fix the undefined `$block` regression.
2. Freeze public block names and decide which experimental blocks are removed or
   hidden.
3. Move static marker lookup and stage rendering into
   `iss-frontend/modules/static-maps`.
4. Remove fallback rendering branches from `iss-relations` after parity is
   confirmed.
5. Split editor JS into focused modules:
   - related cards controls
   - place-source controls
   - static-map controls
   - spine-strip controls
   - editorial signal controls
6. Define stable place and relation DTO contracts for Atlas/static map
   boundaries. Done for static-map block inputs: `iss-relations` now normalizes
   block selection into a relation result with ordered static-map place DTOs
   before passing data to `iss-frontend`.
7. Add marker provenance through either a generator or a documented manual
   runbook.
8. Extend `map-block-audit` to verify real marker resolution for actual block
   usage.
9. Modularize the interactive Atlas app around config, store, map adapter, place
   UI, detail UI, layout, and provider modules. First slice is done: provider,
   config/payload loading, layout resize sync, and the Leaflet map adapter are
   separate runtime modules behind the existing public view handle. Store/state,
   place/filter UI, and detail/story/relation rendering remain in the main
   runtime for the next split.
10. Add fullscreen and kiosk modes as layout states, not as separate apps.
11. Add contract/schema tests for Atlas REST payloads and static map inputs.
12. Only after this, consider broader archive/graph API consolidation.

## Final Recommendation

Proceed with a boundary-clean internal rewrite.

Do not create a second Atlas app. Do not duplicate place data. Do not move map
logic into the theme. Do not let `iss-relations` remain a mixed rendering and
resolver plugin.

The stable long-term architecture is:

```text
WordPress editorial shell
  -> canonical plugin-owned data contracts
  -> graph/relation resolution
  -> frontend renderers and Atlas app
  -> theme composition and visual skin
```

This keeps the current WordPress investment while making the system easier to
export, test, maintain, and eventually reuse outside WordPress if needed.
