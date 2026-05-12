# Archive Phase 2 Schema

## Purpose

This document defines the exact phase-2 schema for moving archive object core scalars out of postmeta.

Phase 2 covers only:

1. `wp_iss_archive_objects`

It does not move:

1. `iss_archive_object_images`
2. object relationship arrays
3. attachment projection IDs
4. archive browser query logic

## Why This Slice

The current archive object model still stores basic identity and descriptive fields in many single-value meta rows.

Live footprint at implementation time:

1. `3048` `archivobjekt` posts
2. `3048` canonical rows after backfill
3. year labels are mostly simple single years such as `1953`, `1954`, `1961`
4. decade labels are mostly simple buckets such as `1950er`, `1960er`

This makes a narrow scalar table viable before media and relation normalization.

## Phase-2 Design Decisions

### 1. Keep one table only

Phase 2 does not add a generic entity framework.

It adds one canonical object-core table and keeps media and relation arrays in legacy meta until later phases.

### 2. Keep display labels and derived keys together

Some current meta values are human labels, not normalized keys.

Examples:

1. `iss_archive_object_type` stores labels such as `Foto`, `Objekt`, `Bild`
2. `iss_archive_decade` stores labels such as `1950er`

So phase 2 stores:

1. raw label fields for rendering and corpus building
2. normalized key fields for indexed lookup

### 3. Attachment IDs stay projection-level

`iss_archive_primary_attachment_id` and `iss_archive_preview_attachment_id` are not copied into the canonical object table in phase 2.

Reason:

1. they belong to WordPress delivery/projection
2. media normalization happens in phase 3

### 4. Year sort columns stay conservative

`sort_year_start` and `sort_year_end` are derived only when the current label supports it.

Rules:

1. if `year_label` contains one four-digit year, use that year for both start and end
2. if `year_label` contains two four-digit years, use them as start and end
3. otherwise, if `decade_label` matches `1950er`-style input, derive decade start and end
4. otherwise store `0` / `0`

This avoids inventing false precision.

## Table DDL

```sql
CREATE TABLE {$wpdb->prefix}iss_archive_objects (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    object_post_id bigint(20) unsigned NOT NULL,
    object_key varchar(191) NOT NULL,
    title text NOT NULL,
    summary text NOT NULL,
    description longtext NOT NULL,
    source_system varchar(100) NOT NULL DEFAULT '',
    source_id varchar(191) NOT NULL DEFAULT '',
    source_url text NOT NULL,
    source_url_hash char(64) NOT NULL DEFAULT '',
    inventory_number varchar(191) NOT NULL DEFAULT '',
    object_type_label text NOT NULL,
    object_type_key varchar(191) NOT NULL DEFAULT '',
    creator_label text NOT NULL,
    material text NOT NULL,
    dimensions text NOT NULL,
    rights_status varchar(191) NOT NULL DEFAULT '',
    rights_holder text NOT NULL,
    json_url text NOT NULL,
    md_object_id bigint(20) unsigned NOT NULL DEFAULT 0,
    md_manifest_url text NOT NULL,
    md_image_url text NOT NULL,
    md_image_rights varchar(191) NOT NULL DEFAULT '',
    md_image_owner text NOT NULL,
    md_metadata_rights_status varchar(191) NOT NULL DEFAULT '',
    md_metadata_rights_holder text NOT NULL,
    md_institution_id bigint(20) unsigned NOT NULL DEFAULT 0,
    institution_name text NOT NULL,
    year_label varchar(191) NOT NULL DEFAULT '',
    decade_label varchar(100) NOT NULL DEFAULT '',
    decade_key varchar(100) NOT NULL DEFAULT '',
    sort_year_start int(11) NOT NULL DEFAULT 0,
    sort_year_end int(11) NOT NULL DEFAULT 0,
    content_hash varchar(191) NOT NULL DEFAULT '',
    last_imported_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY object_post_id (object_post_id),
    UNIQUE KEY object_key (object_key),
    KEY source_lookup (source_system, source_id),
    KEY source_url_hash (source_url_hash),
    KEY inventory_lookup (inventory_number),
    KEY object_type_key (object_type_key),
    KEY decade_key (decade_key),
    KEY md_object_id (md_object_id),
    KEY year_sort (sort_year_start, sort_year_end)
) {$charset_collate};
```

## Column Intent

1. `object_post_id`
   current `archivobjekt` shell post ID

2. `object_key`
   stable canonical lookup key

3. `title`, `summary`, `description`
   canonical object-core text mirrored from the shell post

4. `source_system`, `source_id`, `source_url`
   canonical current source identity fields for phase 2

5. `source_url_hash`
   indexed exact-lookup helper for source URL fallback matching

6. `inventory_number`
   searchable scalar inventory identifier

7. `object_type_label`, `object_type_key`
   raw type label plus normalized key for lookup

8. `creator_label`, `material`, `dimensions`
   current descriptive scalar fields

9. `rights_status`, `rights_holder`
   current object-level rights summary

10. `json_url`, `md_object_id`, `md_manifest_url`
    source/import identity fields from museum-digital shaped metadata

11. `md_image_url`, `md_image_rights`, `md_image_owner`
    current main-image source fields, still scalar in phase 2

12. `md_metadata_rights_status`, `md_metadata_rights_holder`
    current metadata-rights fields, preserved exactly as strings

13. `md_institution_id`, `institution_name`
    current source institution identity

14. `year_label`, `decade_label`, `decade_key`
    current year/dekade labels plus derived key

15. `sort_year_start`, `sort_year_end`
    conservative derived sort bounds

16. `content_hash`
    current `_iss_wf_source_hash` value when present

17. `last_imported_at`
    current `_iss_wf_last_synced_gmt` value when present

18. `created_at`, `updated_at`
    canonical row timestamps

## Migration Mapping

### WordPress shell post fields

1. `post_title` -> `title`
2. `post_excerpt` -> `summary`
3. `post_content` -> `description`

### Common source meta

1. `iss_archive_source_kind` -> `source_system`
2. `iss_archive_source_external_id` -> `source_id`
3. `iss_source_url` -> `source_url`
4. `_iss_wf_source_hash` -> `content_hash`
5. `_iss_wf_last_synced_gmt` -> `last_imported_at`

### Object scalar meta

1. `iss_archive_inventory_number` -> `inventory_number`
2. `iss_archive_object_type` -> `object_type_label`
3. derived `sanitize_title(object_type_label)` -> `object_type_key`
4. `iss_archive_creator` -> `creator_label`
5. `iss_archive_material` -> `material`
6. `iss_archive_dimensions` -> `dimensions`
7. `iss_archive_rights_status` -> `rights_status`
8. `iss_archive_rights_holder` -> `rights_holder`
9. `iss_archive_json_url` -> `json_url`
10. `iss_md_object_id` -> `md_object_id`
11. `iss_md_manifest_url` -> `md_manifest_url`
12. `iss_md_image_url` -> `md_image_url`
13. `iss_md_image_rights` -> `md_image_rights`
14. `iss_md_image_owner` -> `md_image_owner`
15. `iss_md_metadata_rights_status` -> `md_metadata_rights_status`
16. `iss_md_metadata_rights_holder` -> `md_metadata_rights_holder`
17. `iss_md_institution_id` -> `md_institution_id`
18. `iss_md_institution_name` -> `institution_name`
19. `iss_archive_year` -> `year_label`
20. `iss_archive_decade` -> `decade_label`
21. derived `sanitize_title(decade_label)` -> `decade_key`

## Read Path in Phase 2

Table-first reads now cover only safe scalar usage:

1. object card caption fallback uses `creator_label`
2. archive browser result meta uses `year_label`
3. place-suggestion corpus uses `inventory_number`
4. place-suggestion corpus uses `object_type_label`
5. importer identity lookup uses canonical table first for:
   `md_object_id`, `inventory_number`, `source_url`

Legacy fallback remains in place for those reads if no canonical row exists.

## Explicit Non-Goals in Phase 2

Do not move these yet:

1. `iss_archive_object_images`
2. `iss_archive_object_tags`
3. `iss_archive_object_collections`
4. `iss_archive_object_series`
5. `iss_archive_object_events`
6. `iss_archive_object_places`
7. `iss_archive_object_people`
8. `iss_related_archive_objects`
9. attachment projection fields
10. archive browser `meta_query` replacement

## Verification

Use:

```bash
docker compose run --rm wpcli iss-archive objects-verify --allow-root
```

Current acceptance result:

1. `Success: Verified 3048 archive objects.`

Supporting row count:

```sql
SELECT COUNT(*) FROM wp_iss_archive_objects;
```

Expected current result:

1. `3048`

## Compatibility Rule

Do not delete legacy object meta in phase 2.

The object table is canonical for the migrated scalar slice, but legacy meta must remain until phase 5 query migration and later cleanup.
