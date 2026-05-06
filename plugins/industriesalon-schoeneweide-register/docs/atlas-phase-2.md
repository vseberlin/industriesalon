# Atlas Phase 2

## Goal

Switch the public atlas UI from inferred era buckets alone to an explicit
era-aware, story-aware interface while preserving the existing map/place route.

Phase 2 keeps these guarantees:

- `/wp-json/iss-register/v1/atlas` remains the place payload
- `/wp-json/iss-register/v1/atlas-context` remains the additive context payload
- the map still renders from `register_place`
- story cards fall back to place cards when no `atlas_story` posts exist

## Frontend Contract

The public theme atlas app now consumes two endpoints:

1. `/atlas`
   - place records
   - still includes legacy era fields for compatibility

2. `/atlas-context`
   - explicit era list
   - published `atlas_story` records

The UI logic is:

- era filter scopes places first
- role filter applies inside the selected era
- story cards prefer `atlas_story`
- if no stories match, the UI falls back to curated place cards

## Fallback Rules

Phase 2 remains non-destructive by design:

- no explicit era on place -> use inferred era from phase 1
- no `atlas_story` records for era -> show place-based cards
- no context route data -> atlas still works from place payload only

## Upgrade Direction

Later phases can safely add:

- single template for `atlas_story`
- richer story-to-object and story-to-collection surfacing
- story-led map presets and ordered place sequences
