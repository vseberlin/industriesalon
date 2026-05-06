# Atlas Phase 1

## Goal

Add explicit editorial atlas structure without breaking the current public atlas.

Phase 1 introduces:

- taxonomy `atlas_era`
- CPT `atlas_story`
- additive REST context for stories and eras

It does **not** replace the current `register_place` atlas contract.

## Ownership

- `register_place`
  - canonical map anchor
  - coordinates, public place text, area/status/role
  - optional explicit era assignment via `atlas_era`

- `atlas_story`
  - curated narrative layer
  - linked to places through `iss-relations`
  - grouped by `atlas_era`

- `register_source_item`
  - source ingest and review only
  - not part of the public atlas contract

## Backward Compatibility

The current atlas frontend reads legacy era fields from `/wp-json/iss-register/v1/atlas`:

- `era_id`
- `era_label`
- `era_short_label`
- `era_caption`

These fields stay stable in phase 1.

If a place has explicit `atlas_era` terms, the backend maps them back to the existing
legacy era buckets so the current JS keeps working unchanged.

New fields added to the place payload are additive:

- `era_slug`
- `era_name`
- `era_source`
- `explicit_era_slugs`

## REST Contract

### Existing

`GET /wp-json/iss-register/v1/atlas`

- unchanged route
- returns current atlas place collection
- explicit eras override inference when present
- legacy era fields stay intact for frontend compatibility

### New

`GET /wp-json/iss-register/v1/atlas-context`

Optional query parameter:

- `era=<slug>`

Response shape:

- `eras`
  - semantic era definitions with legacy atlas mapping
  - place and story counts
- `stories`
  - published `atlas_story` records
  - filtered by `era` when requested

Story payload fields:

- `id`
- `slug`
- `title`
- `excerpt`
- `permalink`
- `featured_image_url`
- `era_slugs`
- `era_legacy_ids`
- `place_ids`

## Era Vocabulary

Stable editorial slugs:

- `kaiserzeit`
- `weimar`
- `ns-zeit`
- `nachkriegszeit`
- `ddr`
- `nach-1990`

These slugs are the long-term editorial contract.

## Upgrade Path

Phase 1 keeps inferred eras as fallback.

Later phases can:

1. add story-driven atlas UI
2. switch primary filtering from legacy era buckets to semantic era slugs
3. remove inference once editorial assignments are complete
