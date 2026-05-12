# Archive Phase 5 Browser

## Purpose

Phase 5 moves the expensive archive-browser summary layer off ad-hoc `WP_Query` loops and onto the canonical archive tables, without changing the public route or the result ordering that editors already know.

This phase covers the `archive-object-browser` block in `iss-wf-import`.

## Why This Slice

Before phase 5, the browser had two different costs:

1. the paged result query
2. repeated summary queries for stats and filter options

The result query was already a clean `WP_Query` over `archivobjekt` plus taxonomies. The real hotspot was the summary layer:

1. total-object count
2. classified-object count
3. per-source counts
4. taxonomy term option lists

Those values were built with repeated `WP_Query` and `get_terms()` calls.

## What Changed

Phase 5 introduces `ISS_WF_Import_Archive_Browser_Service` in:

1. `plugins/iss-wf-import/includes/archive-browser-service.php`

It now owns:

1. taxonomy option loading for browser filters
2. grouped source counts
3. total/classified browser stats
4. browser verification via `wp iss-archive browser-verify`

The browser block now reads term options and stats through the service.

## Compatibility Decision

Paged result lists intentionally stay on native `WP_Query`.

Reason:

1. public browser ordering is WordPress-native and tie-sensitive on `post_date`
2. reproducing that exactly in hand-written SQL would mean copying WordPress query internals for no real gain
3. the real performance win is in the summary/facet layer, not the already-simple paged result query

So phase 5 is a hybrid by design:

1. results and pagination stay native
2. counts and facets become canonical

## Canonical Reads

The service reads canonical object rows from:

1. `wp_iss_archive_objects`

It combines those rows with post/taxonomy joins to compute:

1. object totals
2. classified totals
3. source-grouped counts
4. term option counts for:
   `archiv_themenfeld`
   `archiv_objektfamilie`
   `archiv_kontext`

For `archiv_quelle`, phase 5 keeps object-browser-specific counts in the service output instead of the older cross-CPT taxonomy totals.

That means the browser now shows source counts that match archive-object results rather than all posts sharing the source taxonomy.

## Verification

New verifier:

1. `docker compose run --rm wpcli iss-archive browser-verify --allow-root`

It checks:

1. six representative query states
2. first-page result IDs against native `WP_Query`
3. browser stats against legacy behavior
4. filter-term availability and count parity

Verified live at implementation time:

1. `3048` published archive objects
2. query states:
   `default`
   `source`
   `search`
   `field`
   `family`
   `context`

Result:

1. `Success: Verified 6 browser query states against legacy query behavior.`

## Boundary After Phase 5

After this phase:

1. collections are canonical
2. object core is canonical
3. media is canonical
4. relations are canonical
5. provenance is canonical
6. archive-browser summaries are canonical

Still not done:

1. browser results are not yet served from a dedicated archive query API
2. place-state unification is still separate work
3. collection/object/public projections still depend on mixed legacy rendering layers
