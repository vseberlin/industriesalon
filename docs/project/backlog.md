# Backlog

Large future work that should not live in root `TODO.md`.

## Archive And Graph Pipeline

- Formalize archive/graph flow as `ingest -> normalize -> project -> enrich -> provenance`.
- Keep `iss-archive` as the local archive runtime owner for archive posts, objects, collections, assertions, evidence, and source snapshots.
- Keep `iss-graph` as the shared cross-domain entity layer for people, organizations, places, profiles, relations, and public search.
- Define one canonical normalized-record contract between source adapters and projectors.
- Let ingest runs stop after source capture and normalization before projection.
- Keep projection typed:
  - archive projection stays in archive services
  - public entity/name/relation/profile projection stays in graph services
  - register chronology/state projection stays in the register plugin
- Extend enrichment additively and keep source-derived facts traceable.
- Consider a future `iss-ingest` split only after the normalized contract is proven across more than one source.

## Archive Media Authority

- Keep high-resolution archive masters outside WordPress media-library authority.
- Store WordPress attachments as runtime previews/screens, not canonical masters.
- Prefer canonical preview media in public/archive rendering.
- Add a backfill path before removing legacy archive source attachments from Media Library.
- Preserve legacy WordPress archive attachments as fallback until preview-first rendering is proven.
- Track archive media provenance separately: source/master location, preview URL or attachment, rights metadata, and storage kind.
- Package archive media migrations as paired SQL/uploads artifacts only after representative archive objects render from the preview-first contract.

## Touchtable And Register Workflows

- Turn the current Touchtable review/match layer into a deliberate promote workflow for `register_place`.
- Decide which source fields can overwrite curated editorial fields and which stay source-only.
- Add a reviewed Touchtable media workflow with source/rights metadata and public exposure only after review.

## Calendar And Timeline

- Add a stronger next-generation timeline/calendar render for program-style pages, especially `Veranstaltungen`.
- Simplify the `industriesalon/timeline-query` editor contract so base scope, defaults, visible filters, and preset state have clearer ownership.

## Search And Public Facade

- Keep `/wp-json/iss/v1` as the public read facade for graph/search/occurrence/timeline/availability/booking-slot consumers.
- Keep booking/order/inquiry writes outside the read facade on `/iss-payments/v1/request`.
- If another public consumer needs Offer labels, read them from the graph contract helper or `/iss/v1/contract`.
