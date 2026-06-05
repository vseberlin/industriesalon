# Plugin Map

Compact ownership map for first-party plugins.

This file is boundary documentation, not an exhaustive API reference. It may
become stale in small details as plugins evolve; when a plugin contract changes,
update the affected row instead of expanding this into broad implementation
documentation.

| Plugin | Owns | Depends on | Exposes | Must not own | Important files |
| --- | --- | --- | --- | --- | --- |
| `iss-content-model` | shared CPT/editor/data contracts, Video CPT block data | WordPress CPT/block APIs | dynamic blocks and editor support | public page skins owned by theme | `includes/`, `blocks/`, `assets/` |
| `industriesalon-steuerung` | persistent institution facts: visit, address, contact, notices | WordPress options/meta/admin APIs | `industriesalon/field`, visit/contact render helpers | page-specific presentation | main plugin file, admin/readme files |
| `industriesalon-schoeneweide-register` | `register_place` structured data, epochs, state projection, tools | WordPress CPT/meta, register tables/services | register blocks, admin tools, CLI guardrails | theme dossier composition | `includes/register-data/`, `includes/admin-tools.php`, `includes/cli.php` |
| `iss-wf-import` | archive ingest, normalization, projection, objects, collections, assertions, evidence | custom tables, source snapshots, WP posts | archive blocks/services, import/admin tools | graph-wide entity authority | `includes/*service.php`, `docs/` |
| `iss-graph` | entities, names, relations, profiles, SQL search projection | custom graph/search tables, WP posts | search provider, graph/profile services | theme search UI composition | `includes/core.php`, `includes/search-*` |
| `iss-relations` | relation queries and relation-aware blocks | WP posts/meta/terms, graph/register data where needed | related-content blocks/renderers | unrelated card skins | `includes/`, `blocks/` |
| `iss-newsletter` | adapter between theme blocks and The Newsletter Plugin | The Newsletter Plugin tables/forms | `iss/newsletter-form` integration | parallel newsletter system | `iss-newsletter.php`, `blocks/` |

## Schema Behavior

- Custom tables must be plugin-owned, versioned with schema options, installed with `dbDelta()`, and accessed through service classes.
- Migration or projection state should be explicit and documented through artifacts or service methods, not hidden template behavior.
