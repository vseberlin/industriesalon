# Search

Public search should degrade gracefully and avoid external-service dependence for core site behavior.

## Current Direction

- `iss-graph` owns the SQL search provider and denormalized search projection.
- The Meili provider is reserved but not required in the current runtime.
- Native WordPress search remains the full-search fallback route unless deliberately redesigned.
- Meilisearch may be deployed as optional infrastructure, but it must not become the canonical content store.

## Rules

- Prefer the existing `iss-graph` search provider before introducing a parallel search system.
- Search indexes belong in plugin-owned custom tables with explicit projection/rebuild paths.
- External search services must be convenience layers, not requirements for core site navigation.
- Frontend search UI belongs in the theme when it is a public composition concern.
- See `docs/runbooks/meilisearch.md` before changing Meilisearch service or index behavior.
