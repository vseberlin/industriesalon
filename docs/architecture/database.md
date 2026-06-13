# Database And Query Architecture

WordPress posts, post meta, terms, and `WP_Query` are the default model for normal editorial content and template loops. Custom tables are preferred when the data is not naturally post-shaped or needs stable indexed lookup paths.

## Existing Custom Table Owners

- `iss-graph`: entity index, entity names, entity identifiers, entity evidence references, entity relations, editorial relation signals, search index, person facts, and organization facts.
- `iss-occurrences`: public programme occurrence projection and recurrence series for indexed calendar/timeline queries.
- `iss-commerce-lite`: lightweight public booking/order request intake, review/export/status handling, and notification state for low-volume operational handling. The request table keeps the existing `wp_iss_payments_lite_requests` name for compatibility.
- `iss-archive`: archive objects, archive collections, collection members, assertions, evidence, and source snapshots.

See `source-of-truth.md` before deciding which storage layer is canonical for a surface.

Programme calendar/timeline queries are served from `iss-occurrences`. The old
hidden `iss_calendar_item` post type is not an active storage or query layer.
WordPress source posts enter the occurrence projection only through explicit
`iss_programme_enabled` opt-in meta; missing toggle meta means hidden.
Ausstellung overview/browser visibility is separate and uses
`iss_public_overview_enabled`, so Dauer/Digital exhibitions can remain visible
in Ausstellung overviews without becoming programme occurrences unless editors
also opt them into the programme.
Public occurrence rows depend on `source_post_id` and `source_post_type` for
calendar identity, sync, delete, and editor routing. Occurrence rows must not
store graph IDs; graph-facing routes translate entity requests to source post
filters at the facade boundary.
Open-ended occurrence ranges use `ends_at = NULL` plus `is_open_ended = 1`; far
future sentinel dates such as `2099-12-31` are invalid drift.
Editorial WordPress source providers are `veranstaltung`, `ausstellung`, and
opt-in `projekt`. `fuehrung` occurrence rows are external SuperSaaS slot
projections linked back to parent Führung posts, not editorial date rows.
Legacy programme meta/options such as `iss_timeline_item_id`,
`iss_exhibition_source`, `iss_exhibition_type`, `_iss_legacy_archive_term_slug`,
`iss_is_permanent`, retired `iss_timeline_enabled`, and `iss_calendar_*`
mapping options are invalid drift, not compatibility inputs.

Commerce-lite request rows are stored in the existing `wp_iss_payments_lite_requests`.
The old capped `is_tours_booking_requests` and `iss_publication_order_requests`
options are migration inputs only and are deleted after schema install.
Online settlement is not implied by a stored request row; supported payment
methods default to onsite/request capture until a provider integration is
explicitly added.

## Rules

- Use `WP_Query` for ordinary public post loops and editor-authored content lists.
- Use plugin-owned custom tables plus prepared `$wpdb` SQL for projections, search indexes, graph relations, archive/reporting data, joins, aggregates, relevance scoring, and high-volume lookups.
- Install custom tables with `dbDelta()`, schema version options, `$wpdb->prefix`, and indexes that match real read paths.
- Access custom tables through service classes. Do not build raw SQL in theme templates or block markup.
- Use `$wpdb->prepare()` for dynamic values. Keep dynamic table names limited to service-owned table-name methods.
- Add custom tables only when WordPress storage is the wrong shape or makes query logic fragile.
