# Archive Overhaul Audit v2

## Status

This document is the authoritative archive rewrite plan.

It consolidates the strategic direction from `audit.md` with the narrower implementation review from `audit-correction.md`.

Use this file for implementation planning.

## Core Decision

Rewrite the archive around canonical custom tables and service-layer access.

Do not keep extending the current CPT-plus-meta model in `iss-wf-import`.
Do not start phase 1 with a universal entity framework.

The first implementation must solve the current structural failures with the smallest durable canonical core:

1. collections out of serialized postmeta
2. object core metadata out of postmeta
3. media references and provenance out of object image arrays
4. relations out of scattered meta arrays
5. source snapshots and idempotent reimport
6. place-state unification later, after the object/archive core is stable
7. assertions and evidence only where they are actually needed

## Goals

The rewrite must support:

1. archive growth well beyond `10,000` objects and media
2. separation of preservation, canonical structured data, and public presentation
3. stable public/editorial routes during migration
4. fast faceted querying without `meta_query` over serialized blobs
5. replayable imports and reimports with provenance
6. explicit field ownership between source data, editorial overrides, and derived values

## What Stays True From the Original Audit

Keep these strategic decisions:

1. WordPress remains the editorial/public shell, not the canonical archive database
2. canonical archive data moves out of `wp_postmeta`
3. raw source snapshots are preserved
4. WordPress attachments are not canonical archive masters
5. `archivobjekt`, `archivsammlung`, and `archivbeitrag` slugs stay stable during migration
6. `publication`, `ausstellung`, `fuehrung`, `veranstaltung`, and `archivbeitrag` remain editorial surfaces
7. archive facts should not be duplicated into publication, tour, or exhibition blobs

## Main Correction to the Original Audit

Do not begin with the full abstract model from `audit.md`.

Phase 1 should not start with:

1. a universal `archive_entity` spine
2. a full assertion/evidence system
3. a split `media_asset` / `media_file` / `media_variant` / `media_link` graph
4. first-class person, organization, event, and concept entities everywhere
5. a full cross-domain graph UI

Those may still be valid later. They are deferred, not rejected.

## Current Structural Failures

The current archive system is blocked by a few concrete issues:

1. collection membership lives in large serialized postmeta blobs
2. object metadata is stored as many array meta fields instead of typed records
3. object media mixes provenance, preview URLs, attachment IDs, rights, and ordering inside per-object arrays
4. relations are spread across archive meta, `iss-relations`, place runtime logic, and editorial renderers
5. import/reimport state is stored on posts instead of in a source/snapshot model
6. place history is becoming more structured than present-day place state

The clearest first migration target is still:

`collections out of postmeta`

## Target Operating Model

### Layer 1: Preservation

Purpose:

1. keep raw source payloads
2. keep archive media masters
3. keep checksums and source provenance
4. keep replayable source snapshots

Rules:

1. preservation storage is outside normal WordPress uploads
2. source payloads are immutable snapshots
3. WordPress may publish derivatives, but does not own archive masters

### Layer 2: Canonical Structured Archive

Purpose:

1. store normalized archive records in typed tables
2. support querying across archive, collection, source, time, place, and relation axes
3. provide one service-layer contract for reads and writes

Rules:

1. no public template, block, REST route, or admin screen writes directly to SQL
2. all canonical access goes through archive services
3. new archive structure must not be added to postmeta

### Layer 3: Editorial and Public Projection

Purpose:

1. keep stable public routes
2. support Gutenberg editing and public rendering
3. host interpretation, curation, publications, exhibitions, and tours

Rules:

1. WordPress posts are projection and editorial shells
2. they may cache or mirror canonical data, but do not own it
3. editorial prose stays in WordPress

## Plugin Boundary Direction

### Keep

1. `iss-publications` as publication owner
2. `industriesalon-schoeneweide-register` as current place/editor shell owner
3. `iss-relations` as editorial linking UI and helper layer

### Change

1. `iss-wf-import` should stop acting as the canonical archive database
2. introduce `iss-archive-core` as the canonical archive owner
3. keep public compatibility and rendering adapters during migration

### Do Not Over-Split Too Early

Start with one canonical archive core and internal modules.

`iss-archive-public` and `iss-archive-editor` may become separate plugins later, but should not be required to start phase 1 unless the boundary proves itself in code.

## Canonical v1 Table Strategy

Phase 1 should use direct typed tables with clear ownership.

### Required early tables

1. `wp_iss_archive_collections`
2. `wp_iss_archive_collection_members`
3. `wp_iss_archive_objects`
4. `wp_iss_archive_media`
5. `wp_iss_archive_relations`
6. `wp_iss_archive_sources`
7. `wp_iss_archive_source_records`
8. `wp_iss_archive_source_snapshots`
9. `wp_iss_archive_import_runs`

### Deferred tables

1. `wp_iss_archive_places`
2. `wp_iss_archive_place_states`
3. `wp_iss_archive_people`
4. `wp_iss_archive_organizations`
5. `wp_iss_archive_assertions`
6. `wp_iss_archive_evidence`
7. `wp_iss_archive_identifiers`

## v1 Canonical Data Model

### Collections

`wp_iss_archive_collections`

Minimum fields:

1. `id`
2. `wp_post_id`
3. `collection_key`
4. `title`
5. `summary`
6. `collection_type`
7. `source_system`
8. `source_id`
9. `created_at`
10. `updated_at`

`wp_iss_archive_collection_members`

Minimum fields:

1. `id`
2. `collection_id`
3. `object_id`
4. `object_wp_post_id`
5. `position`
6. `page_label`
7. `title_override`
8. `caption_override`
9. `member_role`
10. `source_url`
11. `source_id`
12. `created_at`
13. `updated_at`

### Objects

`wp_iss_archive_objects`

Minimum fields:

1. `id`
2. `wp_post_id`
3. `object_key`
4. `inventory_number`
5. `source_system`
6. `source_id`
7. `source_url`
8. `title`
9. `object_type_key`
10. `year_label`
11. `sort_year_start`
12. `sort_year_end`
13. `summary`
14. `description`
15. `material`
16. `dimensions`
17. `rights_status`
18. `rights_holder`
19. `institution_name`
20. `content_hash`
21. `last_imported_at`
22. `created_at`
23. `updated_at`

### Media

`wp_iss_archive_media`

Minimum fields:

1. `id`
2. `object_id`
3. `wp_attachment_id`
4. `role_key`
5. `position`
6. `storage_kind`
7. `master_path`
8. `source_url`
9. `preview_url`
10. `checksum_sha256`
11. `mime_type`
12. `width`
13. `height`
14. `original_filename`
15. `caption`
16. `creator_label`
17. `owner_label`
18. `rights_status`
19. `rights_holder`
20. `is_primary`
21. `created_at`
22. `updated_at`

This stays intentionally simple in v1.

Do not split media into a larger asset/file/variant/link model until the first migration is stable and there is a concrete need.

### Relations

`wp_iss_archive_relations`

Minimum fields:

1. `id`
2. `from_type`
3. `from_id`
4. `to_type`
5. `to_id`
6. `relation_type`
7. `date_from`
8. `date_to`
9. `note`
10. `source_system`
11. `source_id`
12. `confidence_key`
13. `created_at`
14. `updated_at`

Allowed types should start small and controlled:

1. `archive_object`
2. `archive_collection`
3. `register_place`
4. `place_state`
5. `publication`
6. `ausstellung`
7. `fuehrung`
8. `veranstaltung`
9. `person`
10. `organization`

### Sources and Reimport

`wp_iss_archive_sources`

Minimum fields:

1. `id`
2. `source_key`
3. `label`
4. `source_kind`
5. `base_url`
6. `created_at`
7. `updated_at`

`wp_iss_archive_source_records`

Minimum fields:

1. `id`
2. `source_id`
3. `record_identifier`
4. `record_url`
5. `record_kind`
6. `first_seen_at`
7. `last_seen_at`
8. `created_at`
9. `updated_at`

`wp_iss_archive_source_snapshots`

Minimum fields:

1. `id`
2. `source_record_id`
3. `snapshot_hash`
4. `payload_json`
5. `parser_version`
6. `fetched_at`
7. `content_modified_at`
8. `created_at`

`wp_iss_archive_import_runs`

Minimum fields:

1. `id`
2. `source_id`
3. `started_at`
4. `finished_at`
5. `mode`
6. `stats_json`
7. `errors_json`

## Controlled Vocabulary Rule

The rewrite must not replace meta chaos with free-string table chaos.

The following values need controlled vocabularies from day one:

1. `object_type_key`
2. `collection_type`
3. `role_key`
4. `relation_type`
5. `rights_status`
6. `source_kind`
7. `public_access_key`
8. `status_key`
9. `function_key`
10. `confidence_key`

Phase 1 does not require dedicated vocabulary tables yet, but it does require one maintained registry source, either:

1. PHP config dictionaries
2. dedicated lookup tables

What is not acceptable is letting each importer or admin form invent new strings ad hoc.

## Service Layer Rule

All canonical reads and writes must go through service classes.

Minimum services:

1. `ArchiveCollectionService`
2. `ArchiveObjectService`
3. `ArchiveMediaService`
4. `ArchiveRelationService`
5. `ArchiveSourceService`
6. `ArchiveImportService`

Responsibilities:

1. validation
2. normalization
3. projection mapping
4. dual-read fallback during migration
5. import diffing
6. ownership enforcement

## Field Ownership Model

Every canonical field belongs to one of four classes:

### `source_owned`

Imported from a source and overwritten on reimport unless protected by policy.

Examples:

1. source title
2. source description
3. source URL
4. external ID
5. raw dating label
6. raw rights label

### `editor_owned`

Written by editors and never overwritten automatically.

Examples:

1. curated summary
2. public title override
3. collection caption override
4. exhibition context
5. public editorial note

### `derived`

Produced by normalization or projection logic.

Examples:

1. `sort_year_start`
2. `sort_year_end`
3. `object_type_key`
4. slug suggestion
5. content hash
6. facet labels

### `conflicted`

Facts that require review because sources disagree or editor/source data collides.

Examples:

1. conflicting date
2. conflicting rights status
3. conflicting creator attribution
4. conflicting place relation

## Projection Rules

### `archivobjekt`

Keep as public/editorial shell during migration.

May own:

1. `post_title`
2. `post_name`
3. `post_status`
4. editorial intro
5. featured image derivative
6. SEO/editorial fields

Must not canonically own:

1. inventory number
2. source ID
3. object type
4. dating
5. rights
6. media provenance
7. collection membership
8. archive relations

### `archivsammlung`

Keep as public/editorial shell during migration.

It must stop canonically owning member order after the collection-members table is active.

### `archivbeitrag`

Keep as a true editorial CPT.

It may link to archive objects, collections, places, publications, and epochs, but must not become canonical storage for those entities.

### `register_place`

Keep as the current spatial/editorial shell.

Long term:

1. `register_place` = place shell
2. `place_states` = time-based identity and use rows
3. `archive_relations` = links to objects, media, publications, tours, and events

### Projection Mapping Rule

Default assumption:

1. one canonical archive object maps to one `archivobjekt` shell post
2. one canonical collection maps to one `archivsammlung` shell post

If a record needs more than one public/editorial projection, that must be explicit and documented, not accidental.

## Public Query Direction

The archive browser and public queries must move off serialized meta.

Initial indexed facets should be:

1. object type
2. collection
3. date or year range
4. rights status
5. source system
6. place relation

Later facets can add:

1. place state or epoch
2. person or organization
3. function or use type
4. confidence
5. source record

## Operational Requirements

The rewrite needs process infrastructure, not only tables.

At minimum, plan for jobs or commands that handle:

1. snapshot fetch and storage
2. normalization and diff generation
3. checksum generation
4. derivative preview generation
5. projection refresh
6. query index refresh
7. integrity checks and migration verification

## Migration Plan

### Phase 0: Freeze and Contract

Do not add new archive structures to postmeta.

Inventory:

1. all archive meta keys
2. all archive taxonomies
3. all public templates
4. all REST routes
5. all admin screens
6. all import paths
7. all current counts
8. all attachment ownership flags
9. all shortcode and block consumers

Deliverable:

`archive-contract.md`

It must define:

1. canonical data boundaries
2. WordPress projection boundaries
3. frozen legacy fields
4. source-owned fields
5. editor-owned fields
6. derived fields
7. stable public routes
8. compatibility rules

### Phase 1: Collection Membership Migration

Build:

1. `wp_iss_archive_collections`
2. `wp_iss_archive_collection_members`
3. `ArchiveCollectionService`

Migrate:

1. `iss_archive_collection_items`
2. `iss_archive_collection_children`
3. `iss_archive_collection_source_ids`

Strategy:

1. dual-read from table first, old meta fallback second
2. stop new collection writes to blob meta
3. keep old meta until verification passes

Success gate:

1. collection pages render unchanged
2. order is preserved
3. overrides are preserved
4. load time improves
5. no canonical collection writes go back to blob meta

### Phase 2: Object Core Migration

Build:

1. `wp_iss_archive_objects`
2. `ArchiveObjectService`

Migrate only core structured fields needed for:

1. stable identity
2. faceted browsing
3. idempotent import
4. rights handling
5. basic public display
6. relation hooks

Success gate:

1. object identity stays stable
2. source IDs stay stable
3. inventory numbers become searchable without `meta_query`
4. basic facets no longer depend on serialized postmeta
5. object pages still render

### Phase 3: Media Migration

Build:

1. `wp_iss_archive_media`
2. `ArchiveMediaService`

Migrate:

1. `iss_archive_object_images`
2. primary image flags
3. preview attachment IDs
4. source image URLs
5. owner/creator/rights media fields

Success gate:

1. object media galleries render from the table
2. primary image selection stays stable
3. WordPress attachments are clearly derivative, not master records
4. source URLs are preserved
5. rights stay attached per media row

### Phase 4: Relation Migration

Build:

1. `wp_iss_archive_relations`
2. `ArchiveRelationService`

Migrate first:

1. object-object relations
2. object-place relations
3. object-collection links not already handled by collection members

Success gate:

1. related objects resolve from the relation table
2. place pages can find linked archive objects
3. archive objects can find linked places
4. relation queries no longer depend on scattered meta arrays

### Phase 5: Query and Browser Rewrite

Replace archive-browser `meta_query` logic with service-layer indexed queries.

Success gate:

1. main archive browser reads canonical tables
2. initial facets are indexed
3. paging is stable
4. public routes stay unchanged

### Phase 6: Source Snapshots and Reimport

Build:

1. `wp_iss_archive_sources`
2. `wp_iss_archive_source_records`
3. `wp_iss_archive_source_snapshots`
4. `wp_iss_archive_import_runs`
5. `ArchiveImportService`

Import flow:

1. fetch source
2. store snapshot
3. normalize
4. compare
5. apply canonical updates
6. preserve editor-owned overrides
7. write import report

Success gate:

1. the same source ID updates the same canonical record
2. reimport does not create duplicates
3. changes are visible before apply
4. source-owned and editor-owned fields do not clobber each other
5. a parser change can replay from stored snapshots

### Phase 7: Place-State Unification

After the archive object core is stable, unify historical epochs and current place state.

Build later:

1. `wp_iss_archive_places`
2. `wp_iss_archive_place_states`

Success gate:

1. one place can have many phases
2. current state is one phase, not a separate flat truth
3. place filtering can use epoch/function/status/access as first-class data
4. single place pages can present `Zeitschichten` and current state coherently

### Phase 8: Assertions and Evidence

Add only where required for disputed or source-critical facts.

Use cases:

1. conflicting dates
2. uncertain maker attribution
3. contradictory place assignment
4. source-backed ownership transitions
5. oral-history claim versus publication claim

Success gate:

1. disputed facts can be represented without polluting normal fields
2. citations and evidence are explicit where needed
3. uncertainty has a first-class representation

## Decisions Explicitly Delayed

Delay these until the earlier migrations are proven:

1. universal `archive_entity` identity spine
2. full assertion and evidence model everywhere
3. split media asset/file/variant/link model
4. first-class person/org/event entities across the whole system
5. full place-state migration
6. graph-style traversal UI

## Immediate Next Documents

### 1. `archive-contract.md`

Must define:

1. canonical data boundaries
2. WordPress projection boundaries
3. legacy meta freeze list
4. field ownership rules
5. public route stability rules
6. compatibility projections

### 2. `archive-phase-1-schema.md`

Must define exact DDL for:

1. `wp_iss_archive_collections`
2. `wp_iss_archive_collection_members`

Including:

1. indexes
2. cleanup or foreign-key policy
3. migration mapping
4. rollback plan
5. verification queries

### 3. `archive-import-contract.md`

Must define:

1. source identity
2. source record identity
3. idempotency rules
4. hashing rules
5. snapshot storage policy
6. source-owned versus editor-owned overwrite policy
7. source lifecycle rules for deleted, merged, moved, or temporarily missing records

### 4. `archive-vocabulary.md`

Must define:

1. canonical keys
2. allowed values
3. label rules
4. importer normalization rules
5. deprecation policy for old keys

## Final Recommendation

Accept the strategic direction of the original archive overhaul, but execute it as a narrow typed-table migration.

Do not begin by building a universal cultural-heritage entity graph.

Build the smallest canonical archive core that:

1. removes the current postmeta bottlenecks
2. proves the migration pattern
3. preserves public/editorial continuity
4. creates a real reimportable archive foundation

That means:

1. collections first
2. objects second
3. media third
4. relations fourth
5. query/browser rewrite fifth
6. source snapshots and reimport sixth
7. place-state unification after the archive core is working

