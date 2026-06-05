# Source Of Truth

Durable map of canonical state. Fresh runtime inspection still wins when the live system differs.

| Surface | Canonical source | Verify with | Notes |
| --- | --- | --- | --- |
| Block templates | Disk preferred, DB override possible | `wp_template` / `wp_template_part`, `get_block_template()` | Preserve useful DB edits before removing overrides |
| Editorial content | WordPress posts/blocks/meta/terms | WP admin, WP-CLI, frontend render | Editors own intentional content changes |
| Uploads | Persistent uploads storage | filesystem, media URLs, counts/sizes | Containers are disposable; uploads are not |
| Search | `iss-graph` SQL projection | provider config, search index table, frontend search | Meilisearch optional, never canonical |
| Newsletter subscribers | Newsletter plugin DB tables | plugin tables and SQL artifacts | Transfer stable data via `ops/sql` |
| Archive objects/projections | `iss-wf-import` services/tables | plugin services, table rows, source snapshots | Imported data must keep provenance |
| Graph entities/relations | `iss-graph` services/tables | graph service tables and profiles | Shared cross-domain entity layer |
| Archive masters | External/local archive authority, not WP attachment authority | archive storage and source metadata | WP media may hold previews/screens |
| Runtime logs/caches/indexes | Not canonical | service logs/cache/index commands | Rebuild or regenerate from canonical data |
| Deployment state | GitHub `main` plus machine runtime | git commit, services, logs | Record current checkpoint in handoff only |
