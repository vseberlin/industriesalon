# Static Map Rendering Contract

Static atlas/map strips are split across three owners. Keep this direction when
cleaning related blocks or moving more renderer code.

## Owners

- `industriesalon-schoeneweide-register` owns `register_place` data, structured
  place contracts, interactive Atlas REST payloads, register caches, and register
  admin tooling.
- `iss-relations` owns relation and place selection: `source`, `placeIds`,
  `current`, `related`, `route`, graph/taxonomy lookups, and map-block source
  contracts.
- `iss-frontend` owns public frontend rendering for static map surfaces:
  related-place map bodies, Atlas maps/slices/strips, and spine strips. Its
  shared Leaflet image-viewport runtime owns fitting, pan, zoom, and resize.
- The theme owns page composition, CSS skins, the canonical map source,
  responsive derivatives, projection calibration, generated marker JSON, and
  the single visual map preset.

## Dependency Direction

Rendering must flow in one direction:

1. Theme or editor content declares a block and visual preset.
2. `iss-relations` resolves place items from the block source contract.
3. `iss-frontend` renders the frontend map markup from resolved places and map
   config.
4. The theme CSS/assets skin the output.

## Viewport Runtime

All first-class editorial map surfaces use one progressive-enhancement path:

- PHP emits the selected map image, its intrinsic dimensions, projected marker
  coordinates, and an ordinary full-image fallback.
- Leaflet uses `L.CRS.Simple` and `L.imageOverlay`; no geographic tile model is
  introduced for these image maps.
- The marker bounds determine the initial view. That fitted zoom is also the
  minimum zoom, and the fitted view is the maximum pan boundary.
- The existing route/station JavaScript continues to own active-marker and
  detail-panel behavior; Leaflet owns only the image viewport and marker layer.
- Without JavaScript, the full image, projected markers, route line, and panel
  content remain available without server-side crop or camera calculations.

The single map preset selects the canonical responsive image set and generated
marker artifact. It does not contain runtime crop, rotation, bias, scale, or
framing controls.

Do not make `iss-frontend` query related places directly, and do not make
`industriesalon-schoeneweide-register` render static editorial strips.

## Static Map DTOs

`iss-relations` normalizes static-map block selection into an explicit relation
result before handing data to `iss-frontend`:

- `source`: resolved source contract, for example `current`, `manual`,
  `related`, or `route`;
- `block_name`: resolved block name when available;
- `context_post_id`: editor/frontend context post;
- `selected_place_ids`: ordered `register_place` post IDs;
- `places`: ordered static-map place DTOs;
- `count`: number of returned place DTOs.

Each static-map place DTO is a presentation input, not canonical place storage.
It keeps compatibility keys used by existing renderers while making the boundary
explicit:

- identity: `canonical_id`, `post_id`, `place_id`, `slug`;
- display: `title`, `short_label`, `label`, `permalink`, `excerpt`;
- location: `address`, `area`, `location_label`, `coordinates`, `lat`, `lng`;
- classification: `type`, `state`;
- media: `thumbnail_id`, `thumbnail_url`;
- relation context: `source`, `role`, `weight`, route/station fields, and nested
  `relation`;
- projection placeholder: `map_marker`, which remains `null` until the frontend
  renderer resolves the theme-owned marker JSON for a specific map preset.

Renderer code should consume these DTO keys and should not depend on raw
`WP_Post` objects at the static-map boundary.

## Map Block Contracts

`iss_relations_get_map_block_contracts()` is the single source for map block
source defaults and editor fallback behavior.

Rules:

- Use the contract for PHP block registration defaults.
- Export the same contract to Gutenberg through `issRelationsSettings`.
- Render-time source resolution must use the contract.
- If a block stores non-empty `placeIds` and the contract allows
  `manual_ids_imply_manual_source`, resolve it as `manual` unless `source` is
  explicitly set.

## Cleanup Pattern

For future map cleanups:

- Move top-level public rendering into `iss-frontend`.
- Keep viewport behavior inside the shared Leaflet image runtime; do not add
  PHP focus windows, transform cameras, or page-specific CSS framing variables.
- Keep `iss-relations` limited to source contracts and ordered place DTOs.
- Add drift checks that read the same map block contract instead of duplicating
  defaults in scan scripts.

## Drift Check

Use the contract-driven CLI checks after editor/template changes that touch
static map blocks:

```bash
docker compose run --rm wpcli iss-relations map-block-audit --allow-root
docker compose run --rm wpcli iss-relations static-map-contract-check --allow-root
```

The audit scans DB content plus theme templates for static map blocks with
ambiguous source settings, unknown presets, or unreadable marker JSON.
The contract check validates public map-block defaults/source behavior and the
static-map relation result/DTO shape for first-class static map surfaces.

## Marker Provenance

`themes/industriesalon/assets/maps/schoneweide-static-markers-new.json` is a
generated projection file for the theme-owned canonical map coordinate space.
It is not canonical place data. Canonical place identity, coordinates, and
visibility stay in published `register_place` posts owned by
`industriesalon-schoeneweide-register`.

The projection chain is:

1. `schoneweide-map-canonical.png` is the source/build master and is never used
   as the normal frontend image.
2. `schoneweide-map-calibration.json` holds twelve distributed control points,
   the calibrated source checksum/dimensions, and accepted error thresholds.
3. `iss-relations` fits the affine longitude/latitude-to-image transform and
   projects every published coordinate-bearing `register_place`.
4. `schoneweide-static-markers-new.json` stores generated post IDs, titles,
   source longitude/latitude, canonical pixels, and normalized coordinates.
5. `schoneweide-map-projection.generated.json` records source, calibration,
   marker, and responsive-derivative checksums plus projection quality.

Some outlying places legitimately have normalized coordinates outside `0..1`
because the artwork does not include the full geographic extent. Generation
keeps those records so audits can distinguish known out-of-frame places from
missing projections.

Do not edit generated marker or manifest JSON by hand. After changing a
published place coordinate, the master image, calibration, or encoding recipe,
run:

```bash
tools/build-static-map-assets.sh
tools/generate-static-map-markers.sh
```

The first command deterministically produces 1024px and 2048px WebP delivery
assets. The second generates marker/provenance artifacts as the host user,
writes an ignored visual QA SVG under `assets/maps/qa/`, and runs the immutable
verification command. See `docs/runbooks/static-map-assets.md`.
