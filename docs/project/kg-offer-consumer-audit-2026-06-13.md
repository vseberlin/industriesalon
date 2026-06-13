# Knowledge Graph Offer Consumer Audit - 2026-06-13

Scope: local public consumer pass for the contract-only `offer` bridge over
legacy `fuehrung` and `veranstaltung` objects.

## Checked Surfaces

- Header search modal reads `/wp-json/iss/v1/search` and renders the existing
  `type_label` field.
- Related content/cards render through the shared `iss-relations` card helpers.
- Timeline and programme cards render through `iss-frontend` occurrence-backed
  rows.
- Tour booking reads stay on `/wp-json/iss/v1/tour-slots`; booking writes stay
  on `/wp-json/is-tours/v1/book`.

## Change

- `iss-graph` now owns public labels for Offer subtypes in the same registry
  that owns subtype keys.
- `/wp-json/iss/v1/contract` advertises `offer_subtypes` with subtype keys,
  canonical labels, public labels, and subtype sources.
- `/wp-json/iss/v1/search` uses the Offer contract label for search
  `type_label` when a result is an Offer-backed tour/event.
- Related-card kickers use the Offer contract label for `veranstaltung` and
  fallback `fuehrung` cards. Existing tour badges still win when editors set
  them.
- Timeline card badges use the Offer contract label after existing tour
  taxonomy labels.

## Decision

- This is not the later UI polish pass. No layout, animation, copy hierarchy, or
  search interaction redesign was included.
- Consumers should not duplicate subtype-label maps. If another public consumer
  needs Offer labels, it should call the graph contract helper or read
  `/iss/v1/contract`.
