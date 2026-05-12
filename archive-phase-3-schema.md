# Archive Phase 3 Schema

## Purpose

This document defines the exact phase-3 schema for moving archive object media rows out of object image arrays.

Phase 3 covers only:

1. `wp_iss_archive_media`

It does not yet split media into separate asset, file, variant, or rights tables.

## Why This Slice

Current archive object media still lives in `iss_archive_object_images`, one array row per media item.

Each row currently mixes:

1. source media ID
2. source URL
3. preview URL
4. local attachment IDs
5. label
6. owner / creator / rights labels
7. media type
8. primary-image flag

Live shape at implementation time:

1. `3047` object posts with media rows
2. `3051` total media rows
3. `3047` objects with one primary row
4. media type counts:
   `3045` `image`
   `4` `text`
   `2` `audio`
5. storage profile counts:
   `2862` `remote`
   `189` `hybrid`

This is already structured enough for a first canonical table.

## Phase-3 Design Decisions

### 1. One row per current media item

Phase 3 mirrors the current image-array item model into a real table.

It does not yet attempt the final preservation model.

### 2. Keep projection attachment IDs in the row

Attachment IDs remain in the canonical row in phase 3.

Reason:

1. current frontend media rendering depends on them
2. the later asset/file/variant split is deferred
3. phase 3 is meant to stabilize row-level querying before deeper media normalization

### 3. Add derived `role_key` and `storage_kind`

Current rows only have `is_main` and implicit storage behavior.

Phase 3 derives:

1. `role_key = primary` when `is_main` is true, otherwise `supplemental`
2. `storage_kind = remote`, `attachment`, or `hybrid`

This makes current behavior queryable without changing what editors or templates see.

### 4. Preserve legacy row shape through projection

The service projects canonical rows back into the current legacy array shape for:

1. object media block rendering
2. verification parity
3. safe fallback during migration

## Table DDL

```sql
CREATE TABLE {$wpdb->prefix}iss_archive_media (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    object_id bigint(20) unsigned NOT NULL,
    position int(11) NOT NULL DEFAULT 0,
    role_key varchar(40) NOT NULL DEFAULT 'supplemental',
    storage_kind varchar(40) NOT NULL DEFAULT 'remote',
    source_media_id bigint(20) unsigned NOT NULL DEFAULT 0,
    source_url text NOT NULL,
    source_url_hash char(64) NOT NULL DEFAULT '',
    preview_url text NOT NULL,
    preview_url_hash char(64) NOT NULL DEFAULT '',
    attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
    preview_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
    original_filename text NOT NULL,
    label text NOT NULL,
    owner_label text NOT NULL,
    creator_label text NOT NULL,
    rights_label text NOT NULL,
    media_type varchar(40) NOT NULL DEFAULT 'image',
    is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY object_position (object_id, position, id),
    KEY role_key (role_key),
    KEY storage_kind (storage_kind),
    KEY media_type (media_type),
    KEY source_media_id (source_media_id),
    KEY source_url_hash (source_url_hash),
    KEY attachment_id (attachment_id),
    KEY preview_attachment_id (preview_attachment_id)
) {$charset_collate};
```

## Column Intent

1. `object_id`
   canonical parent row from `wp_iss_archive_objects`

2. `position`
   stable row order derived from the legacy array index

3. `role_key`
   current primary/supplemental semantics

4. `storage_kind`
   derived delivery profile: `remote`, `attachment`, or `hybrid`

5. `source_media_id`
   current source system media ID from the legacy row

6. `source_url`, `preview_url`
   canonical remote media URLs preserved exactly

7. `source_url_hash`, `preview_url_hash`
   lookup/index helpers for exact URL matching

8. `attachment_id`, `preview_attachment_id`
   current WordPress delivery attachments

9. `original_filename`
   current row filename snapshot

10. `label`
    current media label/caption text

11. `owner_label`, `creator_label`, `rights_label`
    current row-level rights/provenance labels

12. `media_type`
    current row type such as `image`, `text`, or `audio`

13. `is_primary`
    stored current main-image flag

14. `created_at`, `updated_at`
    canonical row timestamps

## Migration Mapping

Legacy source:

1. `iss_archive_object_images`

Per-row mapping:

1. `source_id` -> `source_media_id`
2. `source_url` -> `source_url`
3. `preview_url` -> `preview_url`
4. `attachment_id` -> `attachment_id`
5. `preview_attachment_id` -> `preview_attachment_id`
6. `filename` -> `original_filename`
7. `label` -> `label`
8. `owner` -> `owner_label`
9. `creator` -> `creator_label`
10. `rights` -> `rights_label`
11. `type` -> `media_type`
12. `is_main` -> `is_primary`
13. array index -> `position`

Derived mapping:

1. `is_primary = 1` -> `role_key = primary`
2. otherwise -> `role_key = supplemental`
3. remote URLs only -> `storage_kind = remote`
4. attachment IDs only -> `storage_kind = attachment`
5. both remote URLs and attachment IDs -> `storage_kind = hybrid`

## Read Path in Phase 3

Table-first reads now cover:

1. `archive-object-media` block rendering

Legacy fallback remains in place if canonical media rows are missing while legacy meta still exists.

## Explicit Non-Goals in Phase 3

Do not do these yet:

1. split media into asset/file/variant/link tables
2. replace attachment projection semantics
3. move object-level primary attachment meta
4. normalize row-level rights into separate entities
5. rewrite browser queries around media
6. delete legacy `iss_archive_object_images`

## Verification

Use:

```bash
docker compose run --rm wpcli iss-archive media-verify --allow-root
```

Current acceptance result:

1. `Success: Verified 3047 archive objects with 3051 media rows.`

Supporting checks:

```sql
SELECT COUNT(*) FROM wp_iss_archive_media;
```

Expected current result:

1. `3051`

```sql
SELECT COUNT(DISTINCT object_id) FROM wp_iss_archive_media;
```

Expected current result:

1. `3047`

## Compatibility Rule

Do not delete legacy `iss_archive_object_images` in phase 3.

The canonical media table owns the migrated row slice, but legacy meta must remain until later browser/query cleanup and data-retirement work.
