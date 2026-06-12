# Search

Public search should degrade gracefully and avoid external-service dependence for core site behavior.

## Current Direction

- `iss-graph` owns the SQL search provider and denormalized search projection.
- `/wp-json/iss/v1/search` is a read-only facade over the same provider, not a
  second index or route migration.
- `wp iss-graph facade-search-compare` verifies that the underlying search
  service callback and the facade response stay aligned for representative
  queries.
- The public header search modal now uses `/wp-json/iss/v1/search`; the retired
  `/iss-search/v1/search` route is no longer registered.
- `wp iss-graph drift-check --checks=facade-route-contract --limit=25` guards
  against re-registering retired read routes or reintroducing old route literals
  in active first-party source.
- The Meili provider is reserved but not required in the current runtime.
- Native WordPress search remains the full-search fallback route unless deliberately redesigned.
- Meilisearch may be deployed as optional infrastructure, but it must not become the canonical content store.

## Rules

- Prefer the existing `iss-graph` search provider before introducing a parallel search system.
- Search indexes belong in plugin-owned custom tables with explicit projection/rebuild paths.
- External search services must be convenience layers, not requirements for core site navigation.
- Frontend search UI belongs in the theme when it is a public composition concern.
- See `docs/runbooks/meilisearch.md` before changing Meilisearch service or index behavior.
