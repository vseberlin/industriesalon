# Industriesalon Schöneweide Register

Current plugin scope:

- local `register_place` posts as the only source of truth
- REST endpoints under `iss-register/v1`
- the Atlas REST payload as the public place-inclusion authority; the frontend
  validates usable IDs, coordinates, and permalinks but does not duplicate an
  area-name allowlist
- separate interactive Atlas extents: a stable Schöneweide core for initial and
  reset composition, plus a navigation boundary derived from payload places
- additive atlas phase 1:
  - taxonomy `atlas_era` for explicit editorial eras on `register_place`
  - CPT `atlas_story` for curated narrative overlays
  - backward-compatible `/atlas` payload with legacy era buckets preserved
  - separate `/atlas-context` endpoint for future era/story-aware atlas UI
