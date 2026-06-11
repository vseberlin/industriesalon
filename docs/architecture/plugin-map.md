# Plugin Map

Compact ownership map for first-party plugins.

This file is boundary documentation, not an exhaustive API reference. It may
become stale in small details as plugins evolve; when a plugin contract changes,
update the affected row instead of expanding this into broad implementation
documentation.

| Plugin | Owns | Depends on | Exposes | Must not own | Important files |
| --- | --- | --- | --- | --- | --- |
| `iss-content-model` | shared CPT/editor/data contracts, Ausstellung date/timeline flags, Video CPT block data | WordPress CPT/block APIs | dynamic blocks and editor support | public page skins owned by theme | `includes/`, `blocks/`, `assets/` |
| `iss-core` | shared infrastructure conventions: capabilities, admin grouping labels, schema option naming, debug logging, drift-result shapes | WordPress plugin/admin APIs | small helper functions | CPTs, REST routes, public renderers, assets, domain storage | `iss-core.php` |
| `iss-frontend` | shared frontend runtime helper conventions: REST URL/config handoff, dialog/disclosure attributes, datepicker asset registration | WordPress enqueue APIs | small helper functions | theme CSS, public layout, domain-specific scripts | `iss-frontend.php` |
| `industriesalon-steuerung` | persistent institution facts: visit, address, contact, notices | WordPress options/meta/admin APIs | `industriesalon/field`, visit/contact render helpers | page-specific presentation | main plugin file, admin/readme files |
| `industriesalon-schoeneweide-register` | `register_place` structured data, epochs, state projection, tools | WordPress CPT/meta, register tables/services | register blocks, admin tools, CLI guardrails | theme dossier composition | `includes/register-data/`, `includes/admin-tools.php`, `includes/cli.php` |
| `iss-wf-import` | archive ingest, normalization, projection, objects, collections, assertions, evidence | custom tables, source snapshots, WP posts | archive blocks/services, import/admin tools | graph-wide entity authority | `includes/*service.php`, `docs/` |
| `iss-graph` | entities, names, identifiers, evidence refs, relations, editorial relation signals, profiles, transcript evidence bridge, SQL search projection | custom graph/search tables, WP posts | resolver, resolver-backed label creation, search provider, graph/profile services, editorial signal controls | theme search UI composition | `includes/core.php`, `includes/search-*`, `includes/editorial-signals-*` |
| `iss-occurrences` | public programme occurrence projection, recurrence series, indexed programme query API, graph entity pointers for occurrence rows | WP source CPTs, SuperSaaS API sync data, `iss-graph` entity resolver when available | `iss_occurrences_query()`, source/SuperSaaS sync helpers, CLI verify/sync/drift checks | editorial content, public skins, graph/search authority | `includes/service.php`, `includes/providers.php`, `includes/cli.php` |
| `saas-api` | SuperSaaS settings, API fetch, hourly occurrence sync, tour-slot REST adapter | SuperSaaS API, `iss-occurrences`, `fuehrung` CPT | `/is-tours/v1/slots`, `iss_supersaas_sync_occurrences()` | hidden calendar CPT storage, public skins, programme query authority | `saas-api.php`, `includes/supersaas-sync.php` |
| `iss-programm` | programme/timeline rendering and dynamic query blocks fed by occurrence or editorial query APIs, including the Ausstellung availability browser | `iss-occurrences`, WordPress post/query APIs | `industriesalon/timeline-query`, `industriesalon/ausstellungen-browser`, tour calendar/date blocks | source CPT contracts, occurrence storage, theme visual skin | `includes/`, `blocks/`, `assets/` |
| `iss-relations` | relation queries and relation-aware blocks | WP posts/meta/terms, graph/register data where needed | related-content blocks/renderers | unrelated card skins | `includes/`, `blocks/` |
| `iss-newsletter` | adapter between theme blocks and The Newsletter Plugin | The Newsletter Plugin tables/forms | `iss/newsletter-form` integration | parallel newsletter system | `iss-newsletter.php`, `blocks/` |

## Schema Behavior

- Custom tables must be plugin-owned, versioned with schema options, installed with `dbDelta()`, and accessed through service classes.
- Migration or projection state should be explicit and documented through artifacts or service methods, not hidden template behavior.
