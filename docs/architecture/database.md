# Database And Query Architecture

WordPress posts, post meta, terms, and `WP_Query` are the default model for normal editorial content and template loops. Custom tables are preferred when the data is not naturally post-shaped or needs stable indexed lookup paths.

## Existing Custom Table Owners

- `iss-graph`: entity index, entity names, entity identifiers, entity evidence references, entity relations, editorial relation signals, search index, person facts, and organization facts.
- `iss-occurrences`: public programme occurrence projection and recurrence series for indexed calendar/timeline queries.
- `iss-wf-import`: archive objects, archive collections, collection members, assertions, evidence, and source snapshots.

See `source-of-truth.md` before deciding which storage layer is canonical for a surface.

Programme calendar/timeline queries are served from `iss-occurrences`. The old
hidden `iss_calendar_item` post type is not an active storage or query layer.
WordPress source posts enter the occurrence projection only through explicit
`iss_timeline_enabled` opt-in meta; missing toggle meta means hidden.
Public occurrence rows store `entity_id` and `location_entity_id` when a graph
entity exists, so graph/search/relation features can join on stable identity
without making the calendar renderer query graph tables. `source_post_id` and
`source_post_type` remain the sync, delete, and editor-routing source metadata.
Legacy programme meta/options such as `iss_timeline_item_id`,
`iss_exhibition_source`, `iss_exhibition_type`, `_iss_legacy_archive_term_slug`,
and `iss_calendar_*` mapping options are invalid drift, not compatibility inputs.

## Rules

- Use `WP_Query` for ordinary public post loops and editor-authored content lists.
- Use plugin-owned custom tables plus prepared `$wpdb` SQL for projections, search indexes, graph relations, archive/reporting data, joins, aggregates, relevance scoring, and high-volume lookups.
- Install custom tables with `dbDelta()`, schema version options, `$wpdb->prefix`, and indexes that match real read paths.
- Access custom tables through service classes. Do not build raw SQL in theme templates or block markup.
- Use `$wpdb->prepare()` for dynamic values. Keep dynamic table names limited to service-owned table-name methods.
- Add custom tables only when WordPress storage is the wrong shape or makes query logic fragile.
