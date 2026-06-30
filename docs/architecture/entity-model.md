# Entity Model

Status: current architecture contract.

The entity model gives the site one identity layer across content, archive,
register, search, relations, and public facades. It does not replace WordPress
CPTs. CPTs remain the editorial containers; entities provide shared identity,
names, identifiers, relations, evidence references, and search projection.

## Core Boundary

- WordPress posts answer: how this object is edited, which template renders it,
  which public body/media belongs to it, and which CPT controls editors need.
- Graph entities answer: what the object is, which names/aliases/identifiers
  point to it, which facts or relations are accepted, and which evidence backs
  them.
- Occurrence rows answer: when a programme item happens. They are not identity
  rows and must not store graph IDs.
- Public views answer: how a page, block, or route renders current data.

This keeps the refactor model as `Entity / Relation / Occurrence / View`.

## Entity Kinds

Entity kinds are broad and stable, not page layouts.

| Kind | Typical source | Notes |
| --- | --- | --- |
| `place` | `register_place` | Built places, sites, addresses, districts, bridges, halls. |
| `organization` | graph profile, register, archive source | AEG, WF, KWO, TRO, institutions, companies. |
| `person` | graph profile, archive source, content admin | Workers, architects, witnesses, authors. |
| `archive_object` | archive object runtime | Physical/digital archive objects, photos, drawings, documents. |
| `archive_collection` | archive collections | Curated or source-derived collections. |
| `publication` | `publication` | Public publication entity. |
| `video` | `video` | Public video entity. |
| `event` | `veranstaltung` | Public event entity. |
| `exhibition` | `ausstellung` | Public exhibition entity. |
| `project` | `projekt` | Public project entity. |
| `tour` | `fuehrung` | Public guided-route entity. |
| `content` | post/page-like fallback | Use only when no more specific stable kind exists. |

`iss-graph` owns the registry in `includes/entity-kinds.php`. Existing storage
values such as `ausstellung`, `veranstaltung`, `fuehrung`, and `projekt` remain
valid until an explicit row migration changes them; the contract layer exposes
canonical names such as `exhibition`, `event`, `tour`, and `project`.

## Storage Owners

`iss-graph` owns:

- `wp_iss_entity_index`
- `wp_iss_entity_names`
- `wp_iss_entity_identifiers`
- `wp_iss_entity_evidence_refs`
- `wp_iss_entity_relations`
- `wp_iss_search_index`
- person and organization fact tables

`iss-archive` owns richer archive assertions, source snapshots, objects, and
collection membership. Graph evidence references may point to archive rows, but
must not duplicate the archive assertion model.

`iss-occurrences` owns programme occurrence and series tables. Occurrences use
`source_post_id` / `source_post_type` for identity back to editorial objects;
graph-facing routes translate entity requests to source-post filters at the
facade boundary.

## Names, Aliases, And Identifiers

Aliases are first-class entity-name records, not hidden post-title parsing.

Recommended `name_type` values:

- `canonical`
- `historical`
- `abbreviation`
- `alternative`
- `source_label`

Identifiers are deterministic handles in namespaces such as `wp_post`,
`register_id`, `archive_object_id`, `archive_collection_id`, `gnd`, `wikidata`,
`geonames`, `url`, and `legacy_slug`.

Known organization abbreviations such as `WF`, `KWO`, `TRO`, and `AEG` must not
be generated as identity aliases on unrelated content objects. Use the graph
hygiene/audit commands before replaying alias changes.

## Relations And Evidence

Entity relations describe accepted connections between graph entities.
Evidence references should point to the specific target they support whenever
possible: a name, identifier, relation, fact, or source payload. Avoid generic
entity-level evidence rows that only say evidence exists somewhere.

## Offer Contract

The Offer bridge is contract-only. It does not add an Offer CPT, storage table,
or public route.

- `fuehrung` maps to `offer/tour`.
- `veranstaltung` maps to Offer subtypes from existing event meta.
- Public labels for Offer subtypes are graph-owned.
- Header search, related cards, and timeline cards should consume graph-owned
  subtype labels rather than duplicating local maps.

## Public Facade

`/wp-json/iss/v1` is the public read facade for the greenfield contract. Active
read surfaces include:

- contract
- entities and entity detail
- entity relations
- occurrences
- entity-scoped occurrences
- search
- timeline
- availability
- tour slots

The facade delegates to existing graph, search, occurrence, frontend timeline,
Ausstellung availability, and booking-slot services. It does not create storage.
Booking writes stay outside the read facade on `/iss-payments/v1/request`.

Facade payloads carry schema intent instead of rendering JSON-LD directly:
occurrence payloads are Event-emitting records, while overview-only Ausstellung
availability records are non-Event CreativeWork-style availability records.

## Exhibition And Programme Boundary

Calendar/programme views consume occurrence rows. Ausstellung overview views
consume Ausstellung availability data.

- Programme projection uses `iss_programme_enabled`.
- Ausstellung overview visibility uses `iss_public_overview_enabled`.
- Dauer/Digital Ausstellungen can appear in overviews without being programme
  occurrences.
- Dauer/Digital Ausstellungen may opt into programme explicitly; open-ended
  programme rows use `ends_at = NULL` plus `is_open_ended = 1`.

## Verification

Use these checks before changing graph/facade/occurrence contracts:

```bash
wp iss-graph drift-check --limit=25
wp iss-graph facade-check --limit=2
wp iss-graph facade-search-compare
wp iss-graph facade-occurrences-compare
wp iss-graph facade-entity-occurrences-compare
wp iss-graph facade-availability-compare
wp iss-graph facade-booking-slots-compare
wp iss-occurrences drift-check --limit=25
wp iss-content tours-drift-check --limit=25
wp iss-frontend ausstellungen-audit --strict
```
