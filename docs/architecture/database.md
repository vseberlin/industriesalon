# Database And Query Architecture

WordPress posts, post meta, terms, and `WP_Query` are the default model for normal editorial content and template loops. Custom tables are preferred when the data is not naturally post-shaped or needs stable indexed lookup paths.

## Existing Custom Table Owners

- `iss-graph`: entity index, entity names, entity identifiers, entity evidence references, entity relations, editorial relation signals, search index, person facts, and organization facts.
- `iss-wf-import`: archive objects, archive collections, collection members, assertions, evidence, and source snapshots.

See `source-of-truth.md` before deciding which storage layer is canonical for a surface.

## Rules

- Use `WP_Query` for ordinary public post loops and editor-authored content lists.
- Use plugin-owned custom tables plus prepared `$wpdb` SQL for projections, search indexes, graph relations, archive/reporting data, joins, aggregates, relevance scoring, and high-volume lookups.
- Install custom tables with `dbDelta()`, schema version options, `$wpdb->prefix`, and indexes that match real read paths.
- Access custom tables through service classes. Do not build raw SQL in theme templates or block markup.
- Use `$wpdb->prepare()` for dynamic values. Keep dynamic table names limited to service-owned table-name methods.
- Add custom tables only when WordPress storage is the wrong shape or makes query logic fragile.
