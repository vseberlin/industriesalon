# Industriesalon Schöneweide Register

Current plugin scope:

- dynamic block `iss-register/register-app`
- local `register_place` posts as the only source of truth
- REST endpoints under `iss-register/v1`
- frontend app mounted in block context
- additive atlas phase 1:
  - taxonomy `atlas_era` for explicit editorial eras on `register_place`
  - CPT `atlas_story` for curated narrative overlays
  - backward-compatible `/atlas` payload with legacy era buckets preserved
  - separate `/atlas-context` endpoint for future era/story-aware atlas UI
