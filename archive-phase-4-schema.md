# Archive Phase 4 Schema

## Purpose

This document defines the exact phase-4 schema for moving archive object relation rows out of scattered object meta arrays.

Phase 4 covers only:

1. `wp_iss_archive_relations`

It does not yet introduce a generic entity graph, assertion layer, or normalized source/place/person tables.

## Why This Slice

Current archive object relations still live in several separate meta arrays plus a local place-link mirror owned by `iss-relations`.

Live shape at implementation time:

1. `3048` archive object posts verified
2. `3047` objects with at least one canonical relation row
3. `31223` total relation rows
4. family counts:
   `20671` `tag`
   `4838` `collection`
   `2327` `event`
   `1790` `object`
   `813` `person`
   `774` `place`
   `8` `local_place`
   `2` `series`

This is already too large and too fragmented to keep querying through raw per-post meta arrays only.

## Phase-4 Design Decisions

### 1. One row per current relation item

Phase 4 mirrors the current relation-item model into one canonical table.

It does not yet attempt the final archive knowledge-graph model.

### 2. Keep relation families typed

The table stores one row shape, but relation semantics are still separated by `relation_family` and `relation_source`.

This keeps current archive behavior queryable without inventing a generic triple system too early.

### 3. Mirror local place links without replacing `iss-relations`

`iss-relations` still owns editorial place-link authoring and taxonomy indexing.

Phase 4 adds a canonical archive-side mirror of `iss_related_places` so archive-object lookups by local place no longer depend only on the taxonomy mirror.

### 4. Preserve legacy projection shapes

The service projects canonical rows back into the current legacy array shapes for:

1. verification parity
2. safe table-first reads with fallback
3. incremental migration without breaking existing editor/runtime code

## Table DDL

```sql
CREATE TABLE {$wpdb->prefix}iss_archive_relations (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    object_id bigint(20) unsigned NOT NULL,
    relation_family varchar(40) NOT NULL,
    relation_source varchar(40) NOT NULL DEFAULT 'source',
    relation_type varchar(60) NOT NULL DEFAULT '',
    target_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
    target_source_id varchar(191) NOT NULL DEFAULT '',
    target_name text NOT NULL,
    source_url text NOT NULL,
    note text NOT NULL,
    relation_context varchar(191) NOT NULL DEFAULT '',
    relation_weight int(11) NOT NULL DEFAULT 0,
    relation_label text NOT NULL,
    event_source_id bigint(20) unsigned NOT NULL DEFAULT 0,
    event_type_id bigint(20) unsigned NOT NULL DEFAULT 0,
    event_type_name varchar(191) NOT NULL DEFAULT '',
    time_label varchar(191) NOT NULL DEFAULT '',
    time_start varchar(40) NOT NULL DEFAULT '',
    time_end varchar(40) NOT NULL DEFAULT '',
    people_source_id bigint(20) unsigned NOT NULL DEFAULT 0,
    people_name text NOT NULL,
    place_source_id bigint(20) unsigned NOT NULL DEFAULT 0,
    place_name text NOT NULL,
    place_latitude decimal(10,7) NOT NULL DEFAULT 0.0000000,
    place_longitude decimal(10,7) NOT NULL DEFAULT 0.0000000,
    position int(11) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY object_family_position (object_id, relation_family, position, id),
    KEY family_target_post (relation_family, target_post_id),
    KEY family_target_source (relation_family, target_source_id),
    KEY relation_source (relation_source),
    KEY relation_type (relation_type),
    KEY place_source_id (place_source_id)
) {$charset_collate};
```

## Implemented Family Keys

Current `relation_family` values:

1. `tag`
2. `collection`
3. `series`
4. `place`
5. `person`
6. `event`
7. `object`
8. `local_place`

Current `relation_source` values:

1. `source_named_ref`
2. `source_event`
3. `source_object`
4. `editorial_place`

Current `local_place` `relation_type` values:

1. `primary`
2. `venue`
3. `stop`
4. `subject`
5. `related`

`object` relation types are still source-defined and not normalized further in phase 4. Live examples include sequence-style values such as `sequence_prev` and `sequence_next`.

## Column Intent

1. `object_id`
   canonical parent archive object row

2. `relation_family`
   coarse relation bucket used for projection and query paths

3. `relation_source`
   whether the row came from source metadata, source object relations, or local editorial place links

4. `relation_type`
   family-specific subtype or role key

5. `target_post_id`
   current local WordPress target when the relation points to a local post, currently used for `object` and `local_place`

6. `target_source_id`
   current remote/source-system target identifier for named refs and source object links

7. `target_name`
   current source/display label for named refs or local place title snapshot

8. `source_url`
   preserved source URL field from legacy named-ref rows

9. `note`
   preserved free-text note field from current named-ref or event rows

10. `relation_context`, `relation_weight`, `relation_label`
    preserved context/weight/label fields from current object-object or local place relations

11. `event_source_id`, `event_type_id`, `event_type_name`
    current source event identity and type fields

12. `time_label`, `time_start`, `time_end`
    current event time fields kept in current string shape

13. `people_source_id`, `people_name`
    current event-attached person fields

14. `place_source_id`, `place_name`, `place_latitude`, `place_longitude`
    current event-attached place fields

15. `position`
    stable row order derived from the legacy array index

16. `created_at`, `updated_at`
    canonical row timestamps

## Migration Mapping

Legacy sources:

1. `iss_archive_object_tags`
2. `iss_archive_object_collections`
3. `iss_archive_object_series`
4. `iss_archive_object_places`
5. `iss_archive_object_people`
6. `iss_archive_object_events`
7. `iss_related_archive_objects`
8. `iss_related_places`

Per-family mapping:

1. `iss_archive_object_tags` -> `relation_family = tag`, `relation_source = source_named_ref`
2. `iss_archive_object_collections` -> `relation_family = collection`, `relation_source = source_named_ref`
3. `iss_archive_object_series` -> `relation_family = series`, `relation_source = source_named_ref`
4. `iss_archive_object_places` -> `relation_family = place`, `relation_source = source_named_ref`
5. `iss_archive_object_people` -> `relation_family = person`, `relation_source = source_named_ref`
6. `iss_archive_object_events` -> `relation_family = event`, `relation_source = source_event`
7. `iss_related_archive_objects` -> `relation_family = object`, `relation_source = source_object`
8. `iss_related_places` -> `relation_family = local_place`, `relation_source = editorial_place`

Important compatibility note:

`iss_archive_object_collections` remains mirrored here even though phase 1 already introduced canonical collection membership rows. In phase 4 this source-side object relation is still preserved because current archive object data and current archive suggestion/corpus logic still read it.

## Read Path in Phase 4

Table-first reads now cover:

1. archive object corpus building in `iss-wf-import/includes/suggestions.php` for tags, collections, and series
2. `iss-relations` admin station-object choices for local places
3. `iss-relations` related-post queries for `archivobjekt` by local place

Write/sync coverage now includes:

1. full archive-object relation backfill
2. archive-object save sync
3. archive-object delete cleanup
4. museum-digital importer sync after save
5. `iss_related_places` meta-change sync for archive objects

Fallback behavior remains in place:

1. `iss-relations` still falls back to the hidden taxonomy mirror if canonical local place rows are missing
2. legacy object meta arrays remain stored and are still the compatibility source during migration

## Explicit Non-Goals in Phase 4

Do not do these yet:

1. normalize tags, people, places, or events into their own canonical tables
2. replace `iss-relations` editorial authoring
3. remove the taxonomy mirror used by `iss-relations`
4. delete legacy relation meta arrays
5. introduce a generic `archive_entity` / triple-store model
6. build archive browser faceting on top of relations yet

## Verification

Use:

```bash
docker compose run --rm wpcli iss-archive relations-verify --allow-root
```

Current acceptance result:

1. `Success: Verified 3048 archive objects with 31223 relation rows.`

Supporting checks:

```sql
SELECT COUNT(*) AS rows_count, COUNT(DISTINCT object_id) AS object_count
FROM wp_iss_archive_relations;
```

Expected current result:

1. `rows_count = 31223`
2. `object_count = 3047`

```sql
SELECT relation_family, COUNT(*) AS count_rows
FROM wp_iss_archive_relations
GROUP BY relation_family
ORDER BY relation_family;
```

Expected current result:

1. `collection = 4838`
2. `event = 2327`
3. `local_place = 8`
4. `object = 1790`
5. `person = 813`
6. `place = 774`
7. `series = 2`
8. `tag = 20671`

## Compatibility Rule

Do not delete legacy relation meta in phase 4.

The canonical relation table owns the migrated row slice, but legacy arrays and the `iss-relations` taxonomy mirror must remain until later place/entity normalization and editorial bridge work is complete.
