# Archive Overhaul Audit

## Purpose

This document is the review plan for a full archive-system rewrite.

It is not a patch plan for the current `iss-wf-import` plugin.
It assumes:

- the archive will grow well beyond `10,000` objects
- archive media will grow beyond what `wp_postmeta` array blobs can handle cleanly
- preservation, structured data, and presentation must become separate layers
- WordPress should remain the editorial/public shell, not the canonical archive database

## Current Snapshot

### Active owners in this repo

- `plugins/iss-wf-import`
  - currently presents itself as `ISS Archive`
  - owns `archivbeitrag`, `archivsammlung`, `archivobjekt`
  - stores canonical-looking archive structure mostly in CPT meta
- `plugins/iss-relations`
  - owns cross-post place relations and taxonomy indexing
  - currently centered on `register_place`
- `plugins/industriesalon-schoeneweide-register`
  - owns `register_place`
  - already contains one precedent for moving canonical structured data out of postmeta:
    `wp_iss_register_place_epochs`
- `plugins/iss-publications`
  - owns `publication` as editorial/publication surface

### Current live scale

- `archivobjekt`: about `3048`
- `archivsammlung`: `6`
- `archivbeitrag`: `107`
- archive-owned attachments flagged: about `3990`

### Current storage pressure

The current archive model is already stressing `wp_postmeta`.

Examples:

- `iss_archive_collection_items`
  - `6` rows
  - average about `98k` chars
  - max about `470k` chars
- `iss_archive_object_images`
  - `3047` rows
  - average about `627` chars
- `iss_archive_object_tags`
  - `2868` rows
  - average about `1076` chars
- `iss_archive_object_events`
  - `2868` rows
  - average about `366` chars
- `iss_related_archive_objects`
  - `902` rows

This is already enough to justify a model rewrite before growth to `10k+`.

## Main Findings

### 1. The archive is still a CPT-plus-meta system

`iss-wf-import` currently stores archive structure in:

- post types
- taxonomies
- many serialized/REST array meta fields

Key examples:

- `iss_archive_object_images`
- `iss_archive_object_tags`
- `iss_archive_object_collections`
- `iss_archive_object_events`
- `iss_archive_object_places`
- `iss_related_archive_objects`
- `iss_archive_collection_items`

This is not a robust canonical archive model.

### 2. Canonical facts and public/editor projections are mixed

The current plugin does all of these at once:

- canonical record storage
- importer logic
- media ownership flags
- public rendering blocks
- editor-side review helpers
- collection sequencing UI

That coupling makes reimporting, publishing, and schema evolution harder than necessary.

### 3. Collections are structurally the clearest failure point

Ordered collection membership is stored in one large meta blob:

- membership
- order
- page labels
- title overrides
- caption overrides
- source URL traces

This should be a table, not a serialized field.

### 4. Media provenance is duplicated and under-normalized

Archive object image data currently mixes:

- source URLs
- preview URLs
- attachment IDs
- preview attachment IDs
- owner
- creator
- rights
- type
- `is_main`

This happens inside per-object arrays instead of a canonical media schema.

### 5. Reimport is not a first-class canonical workflow

The system still contains import-shaped metadata:

- source site
- source kind
- external IDs
- source URL
- manifest URL
- rights holder/status
- hash and last synced timestamps

But these are stored on posts, not in a source snapshot / ingestion model.

That makes replay, diffing, and provenance review weaker than they should be.

### 6. Archive relations are split across multiple systems

Relations currently live in different ways:

- archive-object meta arrays
- `iss-relations` taxonomy index for place links
- `register_place` runtime data
- publication/exhibition/tour rendering logic

This is workable for a small system, but not for a real cross-domain archive graph.

### 7. Place transformation is more structured historically than in the present

`register_place` now has structured historical epoch rows in a custom table.

But present-day state is still comparatively flat:

- `current_status`
- `current_use_type`
- `owner`
- `operator`
- `developer`
- `tenant`
- one prose current-use field

The public model still cannot cleanly express:

- mixed use plus redevelopment
- public access state
- confidence of current claim
- last verification date
- contradictions between sources

### 8. One place can still have multiple “truths”

The current register model still mixes:

- legacy `status`
- normalized current status inference
- current prose
- taxonomy mirrors
- epoch rows

This is a warning sign for the larger archive model as well:
the system needs a clear line between source claim, normalized record, and public presentation.

## Rewrite Goals

The replacement system should meet these goals:

1. Preserve raw source evidence without making raw payloads public runtime.
2. Normalize archive objects, collections, places, media, and relations into typed canonical tables.
3. Let WordPress posts become projections and editorial shells, not canonical data rows.
4. Support large-scale faceted querying across:
   - archive
   - collection
   - temporal
   - place transformation
   - source
   - relation/knowledge graph
5. Support safe reimport and diffing without destroying editorial curation.
6. Keep current public slugs stable where useful:
   - `archivobjekt`
   - `archivsammlung`
   - `archivbeitrag`

## Proposed Target Architecture

### Layer 1: Preservation

Purpose:

- keep raw source payloads
- keep raw binary/media masters
- keep source provenance and checksums
- keep reimportable snapshots

Canonical units:

- source system
- source record
- source snapshot
- source media file

This layer must be append-friendly and replayable.

### Layer 2: Structured Archive Data

Purpose:

- store normalized archive entities
- model temporal, spatial, collection, and relation axes directly
- support fast querying and graph-like traversal without using meta blobs

Canonical units:

- object
- collection
- place
- person
- organization
- event
- concept / taxonomy term
- media asset
- relation
- assertion
- evidence link

### Layer 3: Editorial / Public Projections

Purpose:

- expose stable WordPress routes
- render public pages
- support Gutenberg/editor workflows
- host longform interpretation like publications and exhibitions

Units here can stay as WP posts:

- `publication`
- `ausstellung`
- `fuehrung`
- `archivbeitrag`
- optional projection posts for `archivobjekt` and `archivsammlung`

## Recommended Plugin Split

### `iss-archive-core`

New canonical archive owner.

Responsibilities:

- custom tables
- archive services
- import/reimport pipeline
- provenance and snapshot store
- media asset model
- canonical query services
- archive REST API

### `iss-archive-public`

Public projection/runtime adapter.

Responsibilities:

- WP projection posts if needed
- public routes and controllers
- archive browser endpoints for the frontend
- render adapters to theme-owned templates/blocks

### `iss-archive-editor`

Editor/review tooling.

Responsibilities:

- review queues
- source diff UI
- mapping UI
- collection ordering UI
- conflict resolution UI

### `iss-relations`

Keep, but widen purpose.

Current role is good:

- dedicated plugin
- not theme-owned
- reusable across `register_place`, `fuehrung`, `publication`, `veranstaltung`, `ausstellung`, `post`, `page`

New role:

- editorial links from WP content to canonical archive entities
- not just place links
- support object / collection / place / person / source relations

### `iss-publications`

Keep as editorial/publication owner.

Publications should consume canonical archive entities, not replace them.

## Canonical Schema Proposal

This is the minimum typed model I would build.

### Identity spine

#### `archive_entity`

Shared identity table for all typed entities.

Columns:

- `id`
- `entity_uuid`
- `entity_type`
- `slug`
- `status`
- `created_at`
- `updated_at`

Entity types:

- `object`
- `collection`
- `place`
- `person`
- `org`
- `event`
- `source`
- `media_asset`
- `concept`

#### `archive_identifier`

External IDs and local stable IDs.

Columns:

- `id`
- `entity_id`
- `scheme`
- `identifier_value`
- `is_primary`
- `note`

Examples:

- museum-digital object id
- DDB id
- Europeana id
- legacy WP post id
- internal corpus id

### Objects

#### `archive_object`

Canonical object record.

Columns:

- `entity_id`
- `title`
- `object_type`
- `inventory_number`
- `summary`
- `description`
- `material`
- `dimensions`
- `year_label`
- `sort_year_start`
- `sort_year_end`
- `rights_holder`
- `rights_status`
- `institution_name`
- `canonical_source_record_id`

### Collections

#### `archive_collection`

Canonical collection/album/corpus record.

Columns:

- `entity_id`
- `title`
- `summary`
- `description`
- `collection_type`
- `parent_collection_id`

#### `archive_collection_member`

Ordered collection membership.

Columns:

- `id`
- `collection_entity_id`
- `member_entity_id`
- `member_type`
- `position`
- `page_label`
- `title_override`
- `caption_override`
- `member_role`
- `source_record_id`

This table replaces `iss_archive_collection_items` and `iss_archive_collection_children`.

### Place and transformation

#### `archive_place`

Canonical place/site identity.

Columns:

- `entity_id`
- `title`
- `address_label`
- `lat`
- `lng`
- `geometry_ref`
- `place_kind`

#### `archive_timespan`

Reusable timespan record.

Columns:

- `id`
- `start_year`
- `end_year`
- `start_precision`
- `end_precision`
- `is_open_ended`
- `display_label`
- `circa_flag`

#### `archive_place_state`

This should unify historical epochs and today-state.

Columns:

- `id`
- `place_entity_id`
- `timespan_id`
- `state_kind`
- `status_key`
- `use_type_key`
- `public_access_key`
- `phase_name`
- `summary`
- `owner_entity_id`
- `operator_entity_id`
- `developer_entity_id`
- `tenant_entity_id`
- `confidence_key`
- `checked_at`
- `is_current`

This replaces the split between:

- epoch table
- flat `Heute` fields
- free-text current status inference

### Sources and reimport

#### `archive_source`

Source authority/system.

Columns:

- `entity_id`
- `source_kind`
- `label`
- `base_url`

Examples:

- museum-digital
- DDB
- Europeana
- WF Museum
- local editorial import

#### `archive_source_record`

One logical record in one source.

Columns:

- `id`
- `source_entity_id`
- `record_identifier`
- `record_url`
- `record_kind`
- `first_seen_at`
- `last_seen_at`

#### `archive_source_snapshot`

Immutable payload snapshots.

Columns:

- `id`
- `source_record_id`
- `snapshot_hash`
- `payload_json`
- `parser_version`
- `fetched_at`
- `content_modified_at`

#### `archive_import_run`

Operational import log.

Columns:

- `id`
- `source_entity_id`
- `started_at`
- `finished_at`
- `mode`
- `stats_json`
- `errors_json`

### Assertions and evidence

#### `archive_assertion`

For disputed or sourced facts.

Columns:

- `id`
- `subject_entity_id`
- `predicate_key`
- `value_type`
- `value_json`
- `timespan_id`
- `confidence_key`
- `editorial_status`

#### `archive_evidence`

Links assertions to snapshots and citations.

Columns:

- `id`
- `assertion_id`
- `source_snapshot_id`
- `citation_label`
- `quote_excerpt`
- `locator`

Use this where one source says one thing and another says something else.

### Relations and graph

#### `archive_relation`

Typed entity-to-entity relations.

Columns:

- `id`
- `subject_entity_id`
- `object_entity_id`
- `relation_type`
- `timespan_id`
- `weight`
- `note`
- `source_record_id`

This should absorb:

- object-object relations
- object-place relations
- object-people relations
- collection-object membership edges where a generic edge is enough
- editorial story/context edges where useful

### Media

#### `archive_media_asset`

The intellectual media unit.

Columns:

- `entity_id`
- `label`
- `asset_kind`
- `description`

#### `archive_media_file`

The actual file or remote master.

Columns:

- `id`
- `asset_entity_id`
- `storage_kind`
- `storage_path`
- `source_url`
- `checksum_sha256`
- `mime_type`
- `byte_size`
- `width`
- `height`
- `duration_ms`
- `original_filename`
- `rights_holder`
- `rights_status`
- `creator_label`
- `owner_label`

#### `archive_media_variant`

Derivative/preview/IIIF/OCR/transcript variants.

Columns:

- `id`
- `media_file_id`
- `variant_kind`
- `storage_path`
- `url`
- `mime_type`
- `width`
- `height`

Variant kinds:

- `master`
- `preview`
- `thumb`
- `web`
- `iiif`
- `ocr_text`
- `transcript_pdf`

#### `archive_media_link`

Links media to archive entities.

Columns:

- `id`
- `asset_entity_id`
- `linked_entity_id`
- `role_key`
- `position`
- `caption_override`
- `is_primary`

Roles:

- `primary`
- `preview`
- `detail`
- `page_scan`
- `document`
- `context`

## Query Model

The archive query layer should support direct faceting on:

- source
- collection
- field
- family
- context
- decade
- year range
- place
- place state
- use type
- rights status
- relation type

Do not query serialized `meta_value` for these concerns.

Build indexed tables and service-layer query builders.

## Media and Provenance Rules

### Preservation rules

- never treat a WP attachment as the canonical master
- WP attachments may exist as public/editor derivatives only
- preserve external source URL even after local download
- store file checksum for every local master
- keep variant lineage explicit

### Rights rules

- separate image rights from metadata rights
- separate holder from status
- store provenance per media file, not only per object

### Variant rules

- one master may produce many variants
- previews should not overwrite master references
- IIIF/preview/thumb should be modeled explicitly

## Reimport Model

Reimport must become a primary workflow again.

Rules:

- every source fetch creates a snapshot
- every normalization run is versioned
- every write to canonical tables is diffable
- editor overrides must be distinguishable from source-owned values
- replay from snapshots must be possible after parser changes

Field ownership classes:

- `source_owned`
- `editor_owned`
- `derived`
- `conflicted`

## Publishing Model

### Archive objects and collections

Options:

- keep `archivobjekt` and `archivsammlung` as public projection posts
- or replace them with custom routes over canonical entities

Recommendation:

- keep public WP routes during migration for compatibility
- stop treating those posts as canonical records

### Archive writing

`archivbeitrag` should remain a true editorial CPT.

It can link to canonical archive entities, but it should not be the database of record for those entities.

### Publications, tours, exhibitions

These should reference canonical entities through a link layer.

Do not duplicate archive facts into:

- publication meta
- exhibition chapter blobs
- tour-specific archive object meta

## Migration Plan

### Phase 0: Guardrails and inventory

- freeze new archive-structure additions to `wp_postmeta`
- document current meta keys and counts
- inventory public templates and route dependencies
- identify all consumers:
  - archive browser
  - collection pages
  - publication renderers
  - exhibition blocks
  - tour/place relations

### Phase 1: Introduce archive core tables

- add new archive-core plugin
- create identity, object, collection, collection-member, source, snapshot, media, relation tables
- build service layer and repositories
- no public behavior change yet

### Phase 2: Migrate collections first

- migrate `iss_archive_collection_items`
- migrate `iss_archive_collection_children`
- migrate `iss_archive_collection_source_ids`
- build canonical collection-member services
- dual-read collection pages until stable

Reason:

- collections are the clearest large-blob failure
- migration impact is easy to verify

### Phase 3: Migrate object media and provenance

- replace `iss_archive_object_images`
- move primary/preview attachment logic into media projection adapters
- keep attachment backfill compatibility while canonical media moves to tables

### Phase 4: Migrate object structured metadata

- tags
- collections
- series
- events
- place relations
- people relations
- object-object relations

At the end of this phase, object meta blobs should no longer be canonical.

### Phase 5: Unify place transformation

- add canonical `place_state`
- migrate register epochs into it
- migrate current-state meta into it
- keep `register_place` as editorial/public shell
- expose one coherent place contract everywhere

### Phase 6: Rebuild query/browser layer

- replace browser queries over CPT/meta with canonical archive services
- build stable archive REST contracts
- add paging, facets, and source-aware filters

### Phase 7: Rebuild import/reimport

- replace direct post-writing import logic with:
  - source record
  - snapshot
  - normalization
  - diff
  - apply

### Phase 8: Shrink compatibility shell

- keep public post types/slugs where necessary
- stop writing canonical archive data into postmeta
- convert old meta fields into read-only compatibility projections

## Review Questions

These decisions need explicit review before implementation.

### A. Should canonical archive entities remain projected into WP posts?

Recommendation:

- yes during migration
- maybe no for some entity types later

### B. Should persons and organizations become first-class canonical entities in phase 1?

Recommendation:

- yes for schema
- migration can lag behind objects and collections

### C. Should `iss-relations` become generic editorial-to-entity linking?

Recommendation:

- yes
- keep plugin ownership
- stop treating place-only indexing as the long-term limit

### D. Should we keep archive taxonomies as canonical?

Recommendation:

- no
- move canonical classifications into core tables
- mirror to WP taxonomies only if editor discovery still needs them

### E. Should WordPress attachments remain the canonical archive media record?

Recommendation:

- no

## Immediate Next Step

If this review direction is accepted, the next document should be:

`archive-schema.md`

with:

- exact table DDL
- indexes
- field ownership rules
- projection contract for `archivobjekt`, `archivsammlung`, `register_place`, `publication`
- migration mappings from current meta keys to canonical tables
