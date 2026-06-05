# Relations

Relation logic should be shared instead of rebuilt per route.

## Owners

- `iss-relations` owns relation queries and relation-aware blocks.
- `iss-graph` owns cross-domain entity relations, names, profiles, and graph search projection.
- `industriesalon-schoeneweide-register` owns structured place relation inputs where they are part of the register data model.

## Rules

- Extend existing shared relation contracts before adding a page-specific relation system.
- Keep data relationships in plugins and visible related-content composition in the theme unless a plugin already owns the renderer.
- Preserve source/provenance where imported or projected relationships are involved.
- Use custom tables for graph/projection relation lookup when `WP_Query` or meta queries would be fragile or slow.
