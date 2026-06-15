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
  related-place map bodies, atlas slices, atlas strips, and spine strips.
- The theme owns page composition, CSS skins, map image assets, static marker
  JSON, and map preset definitions.

## Dependency Direction

Rendering must flow in one direction:

1. Theme or editor content declares a block and visual preset.
2. `iss-relations` resolves place items from the block source contract.
3. `iss-frontend` renders the frontend map markup from resolved places and map
   config.
4. The theme CSS/assets skin the output.

Do not make `iss-frontend` query related places directly, and do not make
`industriesalon-schoeneweide-register` render static editorial strips.

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
- Keep low-level projection helpers temporary only when moving them would create
  unnecessary churn.
- Retire legacy helper calls from `iss-relations` in small slices after the
  delegated frontend renderer is proven.
- Add drift checks that read the same map block contract instead of duplicating
  defaults in scan scripts.

## Drift Check

Use the contract-driven CLI check after editor/template changes that touch
static map blocks:

```bash
docker compose run --rm wpcli iss-relations map-block-audit --allow-root
```

The audit scans DB content plus theme templates for static map blocks with
ambiguous source settings, unknown presets, or unreadable marker JSON.
