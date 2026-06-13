# Entity Model Draft

Status: draft architecture direction.

This document defines the intended entity-centered model for identity,
aliases, identifiers, evidence, history, indexing, and cross-domain relations.
It does not replace WordPress CPTs. CPTs remain the editorial and rendering
containers; entities provide the shared identity layer across plugins.

## Problem

The project currently has useful but overlapping relation systems:

- editorial post-to-place links in `iss-relations`
- graph entities, names, relations, profiles, and search in `iss-graph`
- archive object/source/provenance projections in `iss-wf-import`
- register place facts, epochs, states, and actor facets in
  `industriesalon-schoeneweide-register`
- exhibition and timeline composition in `iss-content-model`

The missing contract is not another relation table. The missing contract is a
stable entity identity model that every plugin can project into and every
frontend/editor tool can query.

## Core Principle

Every public object that can be referred to should have an entity row.

The entity row answers:

- What is this thing?
- What names, aliases, spellings, and historical labels point to it?
- Which local and external identifiers point to it?
- Which facts and relations are currently accepted?
- Which source or evidence supports each name, identifier, fact, or relation?
- How should it be indexed for curator search and public search?

The WordPress post answers:

- How is this thing edited in WordPress?
- Which template renders it?
- Which public body content, blocks, and featured media belong to it?
- Which CPT-specific admin controls are needed?

## Entity Kinds

Entity kinds should be broad and stable. They are not page layouts.

| Kind | Typical source | Notes |
| --- | --- | --- |
| `place` | `register_place` | Built places, sites, addresses, districts, bridges, halls. |
| `organization` | graph profile, register, archive source | AEG, WF, KWO, TRO, ASML, institutions, companies. |
| `person` | graph profile, archive source, content admin | Workers, architects, founders, witnesses, authors. |
| `archive_object` | `archivobjekt` | Physical/digital archive objects, photos, drawings, documents. |
| `archive_collection` | `archivsammlung` | Curated or source-derived archive collections. |
| `content` | post/page-like CPTs | Fallback only for public referable material without a more specific kind. |
| `publication` | `publication` | Public publication entity. |
| `video` | `video` | Public video entity. |
| `event` | `veranstaltung` | Public event entity. |
| `exhibition` | `ausstellung` | Public exhibition entity. |
| `project` | `projekt` | Public project entity. |
| `tour` | `fuehrung` | Public guided-route entity. |

Hidden import records, snapshots, and candidate matches are not automatically
public entities. They can become evidence or identifiers for public entities.

`content` must not become a trash-bucket kind. Prefer explicit kinds whenever a
post type has a stable editorial model. Source plugins should not introduce
arbitrary entity kinds without registering the kind, owner, identifier rules,
and projection behavior in `iss-graph`.

Current implementation note: `iss-graph` owns the entity-kind registry in
`includes/entity-kinds.php`. The registry records canonical target kinds,
storage kinds, owner plugins, post-type mappings, and legacy aliases. Existing
storage values such as `ausstellung`, `veranstaltung`, `fuehrung`, `projekt`,
`page`, and `archivbeitrag` remain valid until a separate migration explicitly
renames rows; the canonical layer exposes the programme-facing aliases
`exhibition`, `event`, `tour`, and `project`.

The first `offer` bridge is contract-only. It does not add an offer post type,
table, route, or storage kind. `fuehrung` maps to `offer/tour`; `veranstaltung`
maps to `offer` subtypes through existing editor-owned meta:
`_iss_event_format` maps to `event`, `lecture`, `discussion`, `reading`, or
`presentation`, and `_iss_event_layout=fest` maps to `special_opening`.

## Existing Canonical Base

`iss-graph` should remain the canonical entity service. It already owns:

- entity index
- entity names
- entity relations
- person and organization facts
- SQL search projection
- graph-backed profiles

The target model should extend this service rather than introducing a parallel
entity plugin.

## Proposed Storage Contract

### Existing Tables

| Table | Status | Purpose |
| --- | --- | --- |
| `wp_iss_entity_index` | keep | One row per canonical entity. |
| `wp_iss_entity_names` | keep, extend usage | Canonical names, aliases, historical names, source labels. |
| `wp_iss_entity_identifiers` | Phase 1 | Stable local/external handles for deterministic resolution. |
| `wp_iss_entity_relations` | keep | Entity-to-entity relations with family/type/role/source/weight. |
| `wp_iss_search_index` | keep | Denormalized public/editor search projection. |
| `wp_iss_person_facts` | keep | Person-specific structured facts. |
| `wp_iss_organization_facts` | keep | Organization-specific structured facts. |
| `wp_iss_entity_evidence_refs` | Phase 1 | Lightweight evidence pointers to source rows, URLs, assertions, or notes. |

### Identifier And Evidence Tables

Phase 1 adds explicit identifiers and lightweight evidence references.

```text
wp_iss_entity_identifiers
- id
- entity_id
- namespace
- value
- normalized_value
- url
- label
- confidence
- source_system
- source_ref
- is_primary
- created_at
- updated_at
```

Example namespaces:

- `wp_post`
- `register_id`
- `archive_object_id`
- `archive_source_id`
- `archive_collection_id`
- `gnd`
- `wikidata`
- `geonames`
- `url`
- `legacy_slug`

Evidence can start as a lightweight reference table rather than a full generic
assertion engine:

```text
wp_iss_entity_evidence_refs
- id
- entity_id
- target_kind
- target_id
- source_system
- source_ref
- source_url
- label
- note
- confidence
- status
- created_at
- updated_at
```

`target_kind` examples:

- `entity`
- `name`
- `identifier`
- `relation`
- `fact`
- `history`

Evidence rows must point to a specific target when possible. Evidence for a
name should reference a name row, evidence for an identifier should reference an
identifier row, evidence for a relation should reference a relation row, and
evidence for a fact should reference a fact row or source payload. Avoid generic
entity-level evidence rows that only say evidence exists somewhere.

The archive plugin already owns richer archive assertions/evidence. Do not
duplicate that model immediately. The graph evidence reference should be able to
point to archive assertions, source snapshots, source URLs, register source
links, or manual editorial notes.

## Aliases And Names

Aliases must be first-class records, not buried in post titles or import text.

Recommended `name_type` values:

| Name type | Meaning |
| --- | --- |
| `canonical` | Preferred public name for the entity. |
| `historical` | Name valid during a known historical period. |
| `abbreviation` | Short form, such as WF or KWO. |
| `alternative` | Known alternative label. |
| `source_label` | Name as found in an archive/import source. |

Other more specific values such as `official`, `display`, `transliteration`,
or `typo` may remain in existing data, but early editor workflows should start
with the conservative set above.

Name rows should carry:

- language where known
- validity years where known
- source/evidence reference where possible
- primary flag only for the preferred canonical/display row

Search normalization should index common German variants:

- `Schoeneweide`, `Schoneweide`, `Schöneweide`
- `fuer`, `fur`, `für`
- `ae`, `oe`, `ue`, `ss`
- punctuation-stripped company forms
- common abbreviations such as `WF`, `KWO`, `TRO`, `AEG`

Alias matches must not auto-merge entities by themselves. Deterministic external
identifier matches may auto-link. Fuzzy alias matches should create candidate
matches for review.

Known organization abbreviations and official names are organization identity
aliases, not generic content aliases. Backfill may use them on `organization`
entities, but archive objects, posts, publications, videos, places, events,
exhibitions, projects, and tours that merely mention `WF`, `KWO`, `TRO`,
`AEG`, or similar organization names should keep those terms in titles/search
text or source evidence rather than gaining identity aliases to themselves.
Run the dry-run before replaying generated aliases:

```bash
wp iss-graph sync-aliases --dry-run --limit=25
```

## Resolver Contract

Source plugins must not create public entities directly from raw labels.

All label-based sources should pass through `iss-graph` first:

1. Reuse an exact entity ID, accepted source identifier, or WordPress post
   identifier when one already exists.
2. Auto-link only strict accepted name matches where one entity has a
   primary/canonical/official exact normalized name.
3. Treat historical names, source labels, and fuzzy search matches as candidate
   evidence, not automatic identity.
4. If the source still needs a target row, create or update a hidden
   source-scoped placeholder entity owned by `iss-graph`, then attach the
   source label and identifier as evidence for later review.

Current resolver-backed label entry point:

```php
iss_graph_resolve_or_create_named_entity(string $kind, string $label, array $overrides = [], array $args = []): ?array
```

Existing `ISS_Graph_Service::find_or_create_named_entity()` delegates to this
resolver path for backward compatibility. Direct `upsert_entity()` remains
valid for deterministic post/profile projections where the owning CPT supplies
the identity.

## Identifiers

Identifiers are stable handles. They are stronger than aliases and should drive
deduplication when reliable.

Examples:

- WordPress post ID
- register place ID
- archive object source ID
- archive collection ID
- legacy slug
- GND ID
- Wikidata ID
- source URL

Rules:

- A namespace/value pair should point to one canonical entity.
- Multiple identifiers may point to the same entity.
- Conflicting identifier assignments require a review state, not silent
  overwrite.
- Deleted or replaced identifiers should be retained as historical rows when
  they are still present in source data.
- Every identifier namespace must define a trust level before it can drive
  automatic linking.

Recommended namespace trust levels:

| Trust level | Behavior |
| --- | --- |
| `trusted_auto_link` | Exact namespace/value matches may link automatically. |
| `trusted_review` | Exact matches create high-confidence candidates for review. |
| `suggest_only` | Matches only suggest candidates and never auto-link. |

## Evidence And History

History should mostly be modeled through dated names, dated facts, dated
relations, and evidence references.

Use separate narrative history only when there is an actual event-like claim
that cannot be represented as a dated fact or relation.

Examples:

- A place has a historical name from 1949 to 1990.
- An organization operated a place from 1952 to 1990.
- A person worked at an organization during an approximate period.
- An archive object depicts a place, but the identification is uncertain.

Each accepted claim should be able to answer:

- source system
- source reference or URL
- confidence
- accepted/review/pending/rejected state
- optional note explaining ambiguity

## Plugin Ownership

| Plugin | Entity responsibility |
| --- | --- |
| `iss-graph` | Canonical entity index, names, identifiers, entity relations, evidence references, graph search projection, resolver APIs. |
| `iss-relations` | Editor-facing post-to-place relation source. Projects accepted place links into graph and taxonomy indexes. |
| `iss-wf-import` | Archive object/source/collection/assertion authority. Creates or updates archive entities, source-label aliases, identifiers, and graph projections. |
| `industriesalon-schoeneweide-register` | Place fact authority: geo, address, status, epochs, states, source links, register tools. Creates or updates place entities. |
| `iss-content-model` | Public CPT contracts and exhibition/timeline composition. Creates content entities but does not own graph-wide identity rules. |
| Theme | Presentation only. Reads entity-aware block output; does not write entity storage. |

## Relation Ownership

Relation source and relation index are different things.

| Relation | Canonical write source | Derived indexes/projections |
| --- | --- | --- |
| public post to register place | `iss-relations` `iss_related_places` | `iss_place_ref`, graph `place` relations, archive `local_place` projection where relevant |
| place to organization/person | `iss-graph`, with register bridge inputs | search projection, profile blocks |
| content to person/organization | `iss-graph` content admin | search projection, entity relation blocks |
| video transcript mentions | `iss-graph` transcript bridge, derived from `video.post_content` | pending entity evidence refs for curator review |
| archive object to imported place/person/object/event/collection | `iss-wf-import` | archive relation table, graph projection, search projection |
| archive object to local register place | `iss-relations` source | archive `local_place`, graph `place`, taxonomy index |
| exhibition to chapters/archive browser/timeline | `iss-content-model` | theme/block rendering only |
| place to industry actor | `iss-graph` organization relation, recommended | register actor table or Atlas facet should be derived unless explicitly retained as source |

Industry actors should become organization entities resolved through the graph
service. The existing register actor table can remain a source/projection during
migration, but Atlas should eventually read place-to-organization graph
relations instead of maintaining a parallel organization concept.

## Indexing Contract

The search index should be rebuilt from entity data, not only post fields.

Index inputs:

- canonical entity title
- all accepted aliases and source labels
- identifiers
- post title, slug, excerpt, and selected meta
- related place/person/organization labels
- archive source titles and source IDs
- register address and district fields
- accepted historical names

Search outputs should support:

- public frontend search
- admin/curator entity resolver fields
- relation pickers
- archive object linking
- exhibition material selection
- duplicate detection and merge review

The resolver should return both the entity and its backing post/profile when
available.

## Later Editor Workflow

The curator should not have to browse the full archive manually.

This is a later workflow target, not Phase 1. Do not start implementation with a
large curator workspace, broad merge UI, advanced evidence editor, or automatic
inference system.

Target workflow:

1. Search for an entity by canonical name, alias, source label, or identifier.
2. Open the entity workspace.
3. Review aliases, identifiers, evidence, related places, people,
   organizations, archive objects, publications, videos, exhibitions, and
   projects.
4. Resolve candidate matches and uncertain source labels.
5. Add the entity or selected related material to an exhibition, story, archive
   collection, publication, or related-content block.

Relation and material pickers should search entities first, then expose the
linked posts/archive objects. They should not force editors to start from a raw
post list.

## Video Transcript Bridge

Video transcripts remain normal `video` CPT body content owned by
`iss-content-model`. They are already useful for full-text search, but relation
building needs a graph projection.

The transcript bridge in `iss-graph` derives reviewable evidence only:

- parses video body paragraphs and leading timecodes
- matches exact accepted entity names for places, people, and organizations
- stores one pending `entity` evidence reference per mentioned target entity on
  the video entity
- includes matched names, transcript status, snippets, seconds, and anchors in
  the evidence note/source URL
- exposes pending candidates on the Video CPT edit screen
- stores accepted/dismissed decisions separately as `video_transcript_review`
  evidence so transcript resyncs do not recreate already reviewed suggestions

It must not create accepted graph relations automatically. Curators or later
review tools can promote selected transcript evidence to relations such as
`video mentions person`, `video discusses organization`, or `video references
place`.

Promotion rules:

- person and organization mentions become normal `content_admin` graph
  relations
- place mentions are promoted through `iss-relations` place meta so taxonomy,
  graph, and archive place projections keep their existing source of truth
- dismissed mentions remain rejected review evidence, not deleted history

Rebuild command:

```bash
wp iss-graph sync-video-transcripts
```

## Read APIs

Add or formalize APIs around the graph service:

```php
iss_graph_get_entity_kind_registry(): array
iss_graph_get_canonical_entity_kind(string $entity_kind): string
iss_graph_get_entity_kind_for_post_type(string $post_type): string
iss_graph_get_canonical_entity_kind_for_post_type(string $post_type): string
iss_graph_get_entity_for_post(int $post_id): ?array
iss_graph_get_or_create_entity_for_post(int $post_id, string $kind = ''): ?array
iss_graph_get_entity_by_identifier(string $namespace, string $value): ?array
iss_graph_resolve_entity(array $args): array
iss_graph_resolve_or_create_named_entity(string $kind, string $label, array $overrides = [], array $args = []): ?array
iss_graph_search_entities(array $args): array
iss_graph_get_entity_names(int $entity_id, array $args = []): array
iss_graph_get_entity_identifiers(int $entity_id, array $args = []): array
iss_graph_get_entity_relations(int $entity_id, array $args = []): array
iss_graph_replace_entity_projection(string $source_system, int $entity_id, array $payload): void
```

Source plugins should call graph APIs. Theme templates should not query graph
tables directly.

`iss-graph` exposes a read-only facade under `/wp-json/iss/v1` for the
greenfield contract shape. It delegates to current graph, search, occurrence,
programme timeline, Ausstellung availability, and tour-slot services:

```text
GET /wp-json/iss/v1/contract
GET /wp-json/iss/v1/entities
GET /wp-json/iss/v1/entities/{id}
GET /wp-json/iss/v1/entities/{id}/relations
GET /wp-json/iss/v1/occurrences
GET /wp-json/iss/v1/search
GET|POST /wp-json/iss/v1/timeline
GET /wp-json/iss/v1/availability
GET /wp-json/iss/v1/tour-slots
```

The facade is public-read only. Entity responses expose public entities and
public relations; the nested entity-relations route exposes existing graph
relations with outgoing, incoming, family, source-system, and limit filters.
Occurrence responses are served from `iss-occurrences` when that plugin is
active; search responses delegate to the existing search service. The timeline
route is registered by `iss-programm` and delegates to the existing rendered
timeline REST callback. The availability route is registered by `iss-programm`
and delegates to the existing Ausstellung availability browser query helpers.
It supports the browser filters plus search and returns both structured items
and server-rendered `html` / `is_empty` fields so public clients do not duplicate
card markup.
The tour-slots route is registered by `saas-api` and delegates to the
occurrence-backed slot adapter. The retired read routes `/iss-search/v1/search`,
`/iss-programm/v1/timeline`, and `/is-tours/v1/slots` are no longer registered.
Booking submissions stay outside the read-only facade on `/is-tours/v1/book`.

Entity list and detail responses include additive contract fields:
`contract_kind`, `subtype`, and `contract`. Existing `kind`, `canonical_kind`,
and `storage_kind` fields remain the stable identity/storage boundary for
current consumers.

Run `wp iss-graph drift-check --checks=facade-route-contract --limit=25` to
verify the final facade boundary: required read routes, the booking write route,
retired read-route absence, and active first-party source references.

Run `wp iss-graph drift-check --checks=public-object-contract --limit=25` to
verify published public object coverage before relying on the contract bridge.
It checks expected graph entity coverage, legacy storage-kind mapping,
accepted `wp_post` identifiers, and required offer subtypes.

Run `wp iss-graph drift-check --checks=entity-relations-contract --limit=25`
to verify the nested relation facade can return outgoing/incoming public graph
relations with the expected response shape.

Run `wp iss-graph drift-check --checks=availability-contract --limit=25` to
verify the Ausstellung availability facade can return the four existing browser
filters plus a search scenario with the expected structured and rendered
response shape.

Run `wp iss-graph facade-check` before switching any consumer to `/iss/v1`.
Run `wp iss-graph facade-occurrences-compare` before switching raw
programme/calendar consumers to `/iss/v1/occurrences`. Run
`wp iss-graph facade-timeline-compare` before switching rendered timeline
consumers to `/iss/v1/timeline`. Run the tour-slot comparator before switching
tour-slot readers to `/iss/v1/tour-slots`. These comparators now compare the
facade response against the underlying services, not retired legacy routes:

```bash
wp iss-graph facade-tour-slots-compare
```

Run `wp iss-graph facade-entities-compare` before switching entity/profile
consumers to `/iss/v1/entities`.
Run `wp iss-graph facade-entity-relations-compare` before switching relation
consumers to `/iss/v1/entities/{id}/relations`.
Run `wp iss-graph facade-availability-compare` before extending availability
consumers on `/iss/v1/availability`; `/ausstellungen/` is the first progressive
browser consumer.

Run `wp iss-graph entity-hygiene-audit` before adding entity merge, split, or
reassignment tooling. It is a read-only curator preflight on the existing graph
tables: it inventories duplicate normalized names, reports focus-term matches,
and flags ambiguous aliases or likely wrong-kind records around the default
organization terms `Industriesalon Schöneweide`, `WF`, `KWO`, `TRO`, and
`AEG`. The output includes entity IDs, kinds, titles, source labels, accepted
identifiers, and stored names so a curator can decide merge, alias, suppress,
or leave separate. It must not merge entities, rewrite aliases, suppress rows,
or change identifiers.

Useful options:

```bash
wp iss-graph entity-hygiene-audit --limit=50
wp iss-graph entity-hygiene-audit --terms="WF,KWO,TRO,AEG" --format=json
```

The resolver should classify match type:

- exact trusted identifier match
- exact accepted alias match
- normalized alias match
- historical or source-label match
- fuzzy candidate
- no match / create draft recommendation

Only trusted identifier matches should auto-link without review. Alias and fuzzy
matches should create candidates unless a later owner-specific rule explicitly
allows safe linking.

## Migration Path

Phase 1 should be a thin contract that stops identity drift. It should not try
to build the full curator environment.

1. Document the relation registry: kind, owner, storage, write API, read API,
   projection, rebuild command.
2. Freeze the allowed entity kinds and their owning plugins.
3. Add `entity_identifiers` to `iss-graph`.
4. Add lightweight evidence reference storage to `iss-graph`.
5. Add the resolver service and require source plugins to call it before
   creating or linking entities.
6. Backfill one entity row for each public CPT item that should be referable.
7. Backfill aliases from titles, register fields, archive source labels, known
   abbreviations, and source snapshots.
8. Backfill identifiers from WordPress IDs, register IDs, archive IDs, legacy
   slugs, and external IDs where available.
9. Move direct raw relation reads in plugins to graph or `iss-relations` APIs.
10. Treat `iss_place_ref`, archive `local_place`, and graph place rows as
   projections with rebuild checks.
11. Decide the migration path for register industry actors toward organization
   entities and graph relations.
12. Add drift checks for source rows versus projections before building new UI.

## Drift Checks

`wp iss-graph drift-check` verifies the Phase 1 graph projections.

Minimum verification coverage:

- `iss_related_places` against `iss_place_ref`
- `iss_related_places` against graph `place` relations from
  `iss_relations_meta`
- archive object relation meta against `wp_iss_archive_relations`
- archive local place projection against `iss_related_places`
- register place entity rows against `register_place` posts
- graph organization/person relations against register bridge inputs
- search index rows against entity names and identifiers

Archive relation source projection remains covered by
`wp iss-archive relations-verify`.

## Non-Goals

- Do not replace WordPress posts or Gutenberg editing.
- Do not move public presentation into `iss-graph`.
- Do not make archive source snapshots canonical graph records.
- Do not auto-merge entities from fuzzy aliases.
- Do not create a second relation block system beside `iss-relations`.
- Do not use theme templates as relation/query storage.
- Do not build the large curator workspace in Phase 1.

## Open Decisions

- What is the exact migration path for industry actors to organization
  entities and graph relations while keeping Atlas stable?
- Should generic entity evidence remain a lightweight reference table, or should
  it eventually share a fuller assertion model with the archive plugin?
- Which content CPTs need rich aliases versus only title/slug identifiers?
- Should entity merge/split history live in graph tables or operational logs?
- Which admin screen becomes the main curator entity workspace?
