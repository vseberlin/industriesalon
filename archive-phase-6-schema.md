# Archive Phase 6 Schema

## Purpose

This document defines the exact phase-6 schema for source provenance, source snapshots, and import-run logging.

Phase 6 covers:

1. `wp_iss_archive_sources`
2. `wp_iss_archive_source_records`
3. `wp_iss_archive_source_snapshots`
4. `wp_iss_archive_import_runs`

Phase 5 in `audit-v2.md` is the query/browser rewrite and has no schema file of its own. This file therefore uses the audit numbering, not the simple next integer after phase 4.

## Why This Slice

Archive object identity is already canonicalized enough to support provenance tables:

1. object core rows exist
2. media rows exist
3. relation rows exist

What was still missing before phase 6:

1. no canonical source registry
2. no stable source-record identity table
3. no immutable payload snapshots
4. no import-run log
5. no real trace from a canonical object write back to fetched source input

## Current Live Shape

At implementation time:

1. `3044` unique source identities are backfilled into canonical source records
2. those identities collapse onto `3` canonical sources:
   `museum-digital`
   `wf-gallery`
   `local-curated`
3. `4` duplicate local `archivobjekt` posts remain, sharing museum-digital source IDs with another local post
4. snapshot/import proof after live verification:
   `1` snapshot row
   `2` import-run rows

Current source-identity profile from the canonical object table:

1. `2861` `museum-digital-object`
2. `178` `wf_gallery_object`
3. `8` `museum_digital_object`
4. `1` `local-curated-object`

Phase 6 intentionally normalizes those source-kind variants into canonical source rows instead of preserving every legacy string as a separate source.

## Phase-6 Design Decisions

### 1. One canonical source row per real upstream source

Current canonical source rows are controlled, not free strings:

1. `museum-digital`
2. `wf-gallery`
3. `local-curated`

Legacy source-kind variants such as `museum_digital_object` and `museum-digital-object` both resolve to the same canonical source row.

### 2. One source-record row per unique source identity

The unique key is:

1. canonical source
2. stable record identifier

That means duplicate local posts do not create duplicate source-record rows. They are treated as local duplicate residue, not as separate upstream identities.

### 3. Snapshot payloads are immutable and deduplicated by hash

If the same fetched source payload is seen again:

1. the same source-record row is reused
2. the same snapshot row is reused
3. a new import-run row can still be created

### 4. Dry-run and import both write provenance

Dry-run now stores:

1. import run
2. source snapshot
3. source-record touch

Import additionally:

1. marks the snapshot as applied
2. writes the snapshot hash to post meta
3. writes the applied timestamp to post meta

### 5. Object-side provenance first

Phase 6 currently canonicalizes source provenance for `archivobjekt` only.

Collections still keep their own source fields in the phase-1 collection tables, but they are not yet mirrored into `source_records`.

## Table DDL

### `wp_iss_archive_sources`

```sql
CREATE TABLE {$wpdb->prefix}iss_archive_sources (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    source_key varchar(100) NOT NULL,
    label varchar(191) NOT NULL,
    source_kind varchar(100) NOT NULL DEFAULT '',
    base_url text NOT NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY source_key (source_key),
    KEY source_kind (source_kind)
) {$charset_collate};
```

### `wp_iss_archive_source_records`

```sql
CREATE TABLE {$wpdb->prefix}iss_archive_source_records (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    source_id bigint(20) unsigned NOT NULL,
    object_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
    record_identifier varchar(191) NOT NULL,
    record_url text NOT NULL,
    record_url_hash char(64) NOT NULL DEFAULT '',
    record_kind varchar(100) NOT NULL DEFAULT 'archive_object',
    source_system varchar(100) NOT NULL DEFAULT '',
    source_external_id varchar(191) NOT NULL DEFAULT '',
    source_modified_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    last_snapshot_id bigint(20) unsigned NOT NULL DEFAULT 0,
    last_import_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
    first_seen_at datetime NOT NULL,
    last_seen_at datetime NOT NULL,
    last_imported_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY source_record_lookup (source_id, record_identifier),
    KEY object_post_id (object_post_id),
    KEY record_kind (record_kind),
    KEY source_external_id (source_external_id),
    KEY record_url_hash (record_url_hash)
) {$charset_collate};
```

### `wp_iss_archive_source_snapshots`

```sql
CREATE TABLE {$wpdb->prefix}iss_archive_source_snapshots (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    source_record_id bigint(20) unsigned NOT NULL,
    snapshot_hash char(64) NOT NULL,
    payload_json longtext NOT NULL,
    parser_version varchar(100) NOT NULL DEFAULT '',
    payload_format varchar(40) NOT NULL DEFAULT 'json',
    fetched_at datetime NOT NULL,
    content_modified_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    import_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
    applied_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
    applied_action varchar(40) NOT NULL DEFAULT '',
    applied_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY source_snapshot_lookup (source_record_id, snapshot_hash),
    KEY source_record_id (source_record_id),
    KEY import_run_id (import_run_id),
    KEY applied_post_id (applied_post_id),
    KEY fetched_at (fetched_at)
) {$charset_collate};
```

### `wp_iss_archive_import_runs`

```sql
CREATE TABLE {$wpdb->prefix}iss_archive_import_runs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    source_id bigint(20) unsigned NOT NULL,
    started_at datetime NOT NULL,
    finished_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    mode varchar(40) NOT NULL DEFAULT '',
    profile_key varchar(100) NOT NULL DEFAULT '',
    selection_key varchar(40) NOT NULL DEFAULT '',
    family_filter varchar(191) NOT NULL DEFAULT '',
    seed_url text NOT NULL,
    requested_object_id bigint(20) unsigned NOT NULL DEFAULT 0,
    limit_count int(11) NOT NULL DEFAULT 0,
    offset_count int(11) NOT NULL DEFAULT 0,
    stats_json longtext NOT NULL,
    errors_json longtext NOT NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY source_id (source_id),
    KEY started_at (started_at),
    KEY mode (mode)
) {$charset_collate};
```

## Controlled Values

Current canonical source registry:

1. `museum-digital`
   `source_kind = remote_catalog`
   `base_url = https://berlin.museum-digital.de`
2. `wf-gallery`
   `source_kind = legacy_gallery`
3. `local-curated`
   `source_kind = editorial_local`

Current `record_kind` values:

1. `archive_object`

Current `mode` values used by the museum-digital importer:

1. `dry-run`
2. `import`

## Column Intent

### `sources`

1. `source_key`
   canonical source identity used across records, snapshots, and import runs

2. `label`
   editor/developer-readable source label

3. `source_kind`
   controlled upstream type such as `remote_catalog`

4. `base_url`
   canonical source root

### `source_records`

1. `object_post_id`
   current linked local `archivobjekt` post when one exists

2. `record_identifier`
   stable identity inside the source

3. `record_url`
   canonical source record URL

4. `record_url_hash`
   exact URL lookup helper

5. `record_kind`
   current local canonical entity family, currently `archive_object`

6. `source_system`
   legacy source-kind snapshot from the linked local post

7. `source_external_id`
   source-native object identifier

8. `source_modified_at`
   upstream modified timestamp when known

9. `last_snapshot_id`
   most recent snapshot used for this local identity

10. `last_import_run_id`
    most recent import run applied to this source record

11. `first_seen_at`, `last_seen_at`, `last_imported_at`
    local observation and apply timestamps

### `source_snapshots`

1. `snapshot_hash`
   immutable dedupe key for the stored payload

2. `payload_json`
   stored payload envelope containing:
   fetched payload
   seed payload
   effective payload
   normalized record

3. `parser_version`
   parser build that created the normalized record

4. `payload_format`
   current fetched-source label such as `json`

5. `content_modified_at`
   upstream modified timestamp from the payload when known

6. `import_run_id`
   run row that first or most recently used the snapshot

7. `applied_post_id`, `applied_action`, `applied_at`
   canonical write trace for the applied import path

### `import_runs`

1. `source_id`
   owning source row

2. `started_at`, `finished_at`
   run timing

3. `mode`
   `dry-run` or `import`

4. `profile_key`, `selection_key`, `family_filter`
   actual importer scope controls

5. `seed_url`, `requested_object_id`, `limit_count`, `offset_count`
   exact request context

6. `stats_json`, `errors_json`
   persisted run summary and failure rows

## Migration Mapping

Backfilled local source-record input comes from existing `archivobjekt` post/meta fields:

1. `iss_archive_source_kind`
2. `iss_archive_source_external_id`
3. `iss_source_url`
4. `iss_source_site`
5. `iss_source_modified_gmt`
6. `_iss_wf_last_synced_gmt`

Phase-6 importer capture input comes from the live museum-digital command:

1. fetched payload
2. seed payload
3. effective payload after identity merge
4. normalized record
5. CLI mode/profile/selection context

Important compatibility rule:

Existing object meta remains stored and still drives fallback/runtime compatibility. Phase 6 does not delete or replace those fields yet.

## Implemented Read/Write Path

Canonical source-record backfill now runs for archive objects on plugin load.

Canonical importer provenance now runs in `iss-archive import-museum-digital`:

1. start import run
2. fetch payload
3. build snapshot envelope
4. write or reuse source snapshot
5. on import mode, save/update local object
6. sync canonical object/media/relation/source rows
7. mark snapshot as applied
8. finalize import run summary

Post-import state now also writes:

1. `_iss_wf_source_hash`
2. `_iss_wf_last_synced_gmt`

## Explicit Non-Goals in Phase 6

Do not do these yet:

1. backfill raw source snapshots for all historical objects by refetching everything
2. move collection provenance into `source_records`
3. deduplicate the 4 duplicate local posts automatically
4. preserve snapshots outside MySQL yet
5. add editor override policies on top of snapshot replay
6. rewrite archive browser queries to use provenance tables

## Verification

Verifier:

```bash
docker compose run --rm wpcli iss-archive sources-verify --allow-root
```

Current acceptance result:

1. `Success: Verified 3044 unique source identities across 3 sources, 1 snapshots, and 2 import runs. Duplicate local posts: 4.`

Live importer proof:

```bash
docker compose run --rm wpcli iss-archive import-museum-digital --object-id=86046 --mode=dry-run --limit=1 --allow-root
docker compose run --rm wpcli iss-archive import-museum-digital --object-id=86046 --mode=import --limit=1 --allow-root
```

Observed result:

1. dry-run created the first snapshot and first import-run row
2. import reused the same snapshot hash
3. import marked that snapshot as applied to local post `18000`
4. post `18000` now stores the same snapshot hash in `_iss_wf_source_hash`

Supporting count checks:

```sql
SELECT COUNT(*) FROM wp_iss_archive_sources;
SELECT COUNT(*) FROM wp_iss_archive_source_records;
SELECT COUNT(*) FROM wp_iss_archive_source_snapshots;
SELECT COUNT(*) FROM wp_iss_archive_import_runs;
```

Expected current results:

1. `sources = 3`
2. `source_records = 3044`
3. `source_snapshots = 1`
4. `import_runs = 2`

## Compatibility Rule

Phase 6 does not make source provenance the only runtime contract yet.

The new source tables are canonical for:

1. source registry
2. source identity
3. fetched payload snapshots
4. importer run history

Legacy object meta remains required until later query/runtime cleanup and duplicate-object review are complete.
