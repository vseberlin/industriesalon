# Relations

Relation logic should be shared instead of rebuilt per route.

For the broader identity, alias, evidence, and indexing direction, see
`docs/architecture/entity-model.md`.

## Owners

- `iss-relations` owns relation queries and relation-aware blocks.
- `iss-graph` owns cross-domain entity relations, names, profiles, and graph search projection.
- `industriesalon-schoeneweide-register` owns structured place relation inputs where they are part of the register data model.

## Editor Surfaces

Authoring screens may present relation controls inside a domain editor, but the
relation rows remain owned by `iss-relations`. The Führung JSON editor's
`Route / Stationen` panel is the first example: it saves `iss_related_places`
rows through `/iss-relations/v1/posts/{id}/places` and maps route stations to
`role=stop`, ordered `weight`, optional `route_title` / `route_teaser`, and
optional station object/story links. The panel replaces the generic
`Verknüpfte Orte` metabox only on Führung edit screens.

## Rules

- Extend existing shared relation contracts before adding a page-specific relation system.
- Keep data relationships in plugins and visible related-content composition in the theme unless a plugin already owns the renderer.
- Preserve source/provenance where imported or projected relationships are involved.
- Use custom tables for graph/projection relation lookup when `WP_Query` or meta queries would be fragile or slow.
