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
- Its main `WP_Query` receives a bounded SQL-provider page through
  `posts_pre_query`, with complete totals. It no longer builds a 1,000-ID
  candidate list or repeats register visibility as a WordPress metadata query.
  Normal editorial queries and the existing search templates remain native.
- `includes/search-query.php` owns the shared prepared match/count predicate:
  current published, password-free sources; public index rows; tour-only place
  exclusion; and active, unexpired editorial search signals. Public pages remain
  fresh when those facts change; WordPress post-query caches cannot represent
  that combined invalidation boundary. Post/meta/term reads are primed in batches.
- The page provider caps each result set at 100 and uses an offset plus exact
  total, preserving relevance order and reaching matches beyond the former cap.
  The legacy list facade keeps its bounded response shape. If the optional
  provider fails, the full WordPress route falls back to the SQL owner.
- `tests/e2e/bin/public-search-query.php` exercises pagination and stale-index
  privacy with connection-local tables; `public-search.spec.js` covers native
  desktop/no-JavaScript navigation. The October 2026 local EXPLAIN retains
  the search scan required by substring matching and existing primary/signal/
  metadata indexes; no additional index or index rebuild is justified by it.
- Meilisearch may be deployed as optional infrastructure, but it must not become the canonical content store.

## Rules

- Prefer the existing `iss-graph` search provider before introducing a parallel search system.
- Search indexes belong in plugin-owned custom tables with explicit projection/rebuild paths.
- External search services must be convenience layers, not requirements for core site navigation.
- Frontend search UI belongs in the theme when it is a public composition concern.
- See `docs/runbooks/meilisearch.md` before changing Meilisearch service or index behavior.
