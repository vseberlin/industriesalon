# Archive Phase 1 Schema

## Purpose

This document defines the exact phase-1 schema for moving collection structure out of serialized postmeta.

Phase 1 covers only:

1. `wp_iss_archive_collections`
2. `wp_iss_archive_collection_members`

It is based on `archive-contract.md` and the current live archive runtime.

## Why Collections First

The collection blob is the clearest current structural failure.

Current live shape:

1. `6` collection posts
2. `899` object-member rows in `iss_archive_collection_items`
3. `3` child-collection rows in `iss_archive_collection_children`
4. `6` collection source-ref rows in `iss_archive_collection_source_ids`
5. `844` object-member rows already use `page_label`
6. `0` unresolved source-only object rows
7. `0` unresolved source-only child rows
8. `0` duplicate object rows within a collection
9. `0` duplicate child-collection rows within a collection

One current collection is genuinely mixed:

1. `fotoalbum-labor-konstruktions-und-versuchswerk-oberspree-1946`
2. `52` object members
3. `3` child collections

Because that mixed case already exists, phase 1 needs:

1. a real `collection_type`
2. one member table that can store both object rows and child-collection rows

## Phase-1 Design Decisions

### 1. Two tables only

Phase 1 does not introduce a broader entity model.

It uses:

1. one collection table
2. one collection-members table

### 2. Child collections live in the member table

There is no separate `collection_children` table in phase 1.

Reason:

1. the migration target is intentionally narrow
2. current child rows are ordered members of a parent collection
3. storing object rows and child rows in one ordered member stream keeps rendering and verification simpler

### 3. Collection-level source refs stay row-local JSON in phase 1

`iss_archive_collection_source_ids` moves out of postmeta, but not yet into its own table.

In phase 1 it becomes:

1. `source_refs_json` on `wp_iss_archive_collections`

Reason:

1. the urgent problem is collection membership, not collection-source faceting
2. there are only `6` current source-ref entries total
3. no public query path currently needs one row per source ref

This is a deliberate phase-1 compromise, not the final source model.

### 4. No database foreign keys in phase 1

Use WordPress-style application cleanup, not DB-level foreign keys.

Reason:

1. it matches existing custom-table precedent in this repo
2. it avoids fragile delete behavior across posts and custom tables
3. rollback is simpler

## Controlled Values

These values must be treated as controlled vocabularies from day one.

### `collection_type`

Allowed values:

1. `album`
2. `collection`
3. `hybrid`

Mapping rule for migration:

1. `album` if object-member rows > `0` and child rows = `0`
2. `collection` if object-member rows = `0` and child rows > `0`
3. `hybrid` if object-member rows > `0` and child rows > `0`

Expected current result:

1. `5` rows as `album`
2. `1` row as `hybrid`

### `member_kind`

Allowed values:

1. `object`
2. `collection`

### `member_role`

Allowed values:

1. `album_item`
2. `child_collection`

## Table DDL

### `wp_iss_archive_collections`

```sql
CREATE TABLE {$wpdb->prefix}iss_archive_collections (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    collection_post_id bigint(20) unsigned NOT NULL,
    collection_key varchar(191) NOT NULL,
    collection_type varchar(40) NOT NULL DEFAULT 'collection',
    title text NOT NULL,
    summary text NOT NULL,
    source_system varchar(100) NOT NULL DEFAULT '',
    source_id varchar(191) NOT NULL DEFAULT '',
    source_url text NOT NULL,
    source_refs_json longtext NOT NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY collection_post_id (collection_post_id),
    UNIQUE KEY collection_key (collection_key),
    KEY collection_type (collection_type),
    KEY source_lookup (source_system, source_id)
) {$charset_collate};
```

### `wp_iss_archive_collection_members`

```sql
CREATE TABLE {$wpdb->prefix}iss_archive_collection_members (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    collection_id bigint(20) unsigned NOT NULL,
    member_kind varchar(20) NOT NULL,
    member_role varchar(40) NOT NULL,
    member_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
    member_source_id varchar(191) NOT NULL DEFAULT '',
    member_source_url text NOT NULL,
    member_title text NOT NULL,
    member_slug varchar(200) NOT NULL DEFAULT '',
    title_override text NOT NULL,
    caption_override text NOT NULL,
    page_label varchar(100) NOT NULL DEFAULT '',
    position int(11) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY collection_id (collection_id),
    KEY collection_position (collection_id, position, id),
    KEY member_post (member_kind, member_post_id),
    KEY member_source (member_kind, member_source_id),
    KEY member_role (member_role)
) {$charset_collate};
```

## Column Intent

### `wp_iss_archive_collections`

1. `collection_post_id`
   current WP shell post ID for `archivsammlung`

2. `collection_key`
   stable local key for canonical lookup

3. `collection_type`
   controlled value: `album`, `collection`, or `hybrid`

4. `title`
   canonical collection title for the archive layer

5. `summary`
   canonical short summary in phase 1, sourced from the current shell excerpt

6. `source_system`
   primary source system key from collection-level provenance

7. `source_id`
   primary external collection ID if one exists

8. `source_url`
   primary collection source URL if one exists

9. `source_refs_json`
   normalized JSON array from `iss_archive_collection_source_ids`

10. `created_at` and `updated_at`
    canonical row timestamps, not editorial shell dates

### `wp_iss_archive_collection_members`

1. `collection_id`
   parent row in `wp_iss_archive_collections`

2. `member_kind`
   whether the row points at an archive object or a child collection

3. `member_role`
   `album_item` for object rows, `child_collection` for child rows

4. `member_post_id`
   linked shell post ID for `archivobjekt` or `archivsammlung`

5. `member_source_id`
   legacy external member identifier

6. `member_source_url`
   legacy source URL for the member row

7. `member_title`
   fallback title snapshot if the linked post is unavailable later

8. `member_slug`
   fallback slug snapshot if the linked post is unavailable later

9. `title_override`
   editorial display override currently used by album item rows

10. `caption_override`
    editorial caption override currently used by album item rows

11. `page_label`
    album page or sequence label

12. `position`
    stable ordering integer carried over from the current meta structure

13. `created_at` and `updated_at`
    canonical row timestamps

## Collection Key Rule

`collection_key` must be deterministic.

Use this migration rule:

1. if both `iss_archive_source_kind` and `iss_archive_source_external_id` exist:
   `collection_key = <source_kind>:<source_external_id>`
2. else if the collection shell `post_name` is non-empty:
   `collection_key = wp-post:<post_name>`
3. else:
   `collection_key = wp-id:<post_id>`

This key is internal and stable.

It is not a public route.

## Migration Mapping

### Collection row mapping

For each `archivsammlung` post:

1. `collection_post_id`
   from the current WP post ID

2. `collection_key`
   from the rule above

3. `collection_type`
   derived from object-member count and child-member count

4. `title`
   from `post_title`

5. `summary`
   from `post_excerpt`

6. `source_system`
   from `iss_archive_source_kind`

7. `source_id`
   from `iss_archive_source_external_id`

8. `source_url`
   from `iss_source_url`

9. `source_refs_json`
   JSON encoding of normalized `iss_archive_collection_source_ids`

10. `created_at`
    migration timestamp in UTC

11. `updated_at`
    migration timestamp in UTC

### Object-member row mapping

For each item in `iss_archive_collection_items`:

1. `collection_id`
   parent canonical collection row ID

2. `member_kind`
   `object`

3. `member_role`
   `album_item`

4. `member_post_id`
   from `object_id`

5. `member_source_id`
   from `source_object_id`

6. `member_source_url`
   from `source_url`

7. `member_title`
   snapshot from the linked `archivobjekt` title at migration time

8. `member_slug`
   snapshot from the linked `archivobjekt` `post_name`

9. `title_override`
   from legacy `title`

10. `caption_override`
    from legacy `caption_override`

11. `page_label`
    from legacy `page_label`

12. `position`
    from legacy `position`

13. `created_at`
    migration timestamp in UTC

14. `updated_at`
    migration timestamp in UTC

### Child-collection row mapping

For each item in `iss_archive_collection_children`:

1. `collection_id`
   parent canonical collection row ID

2. `member_kind`
   `collection`

3. `member_role`
   `child_collection`

4. `member_post_id`
   from `collection_id`

5. `member_source_id`
   from `source_external_id`

6. `member_source_url`
   from `source_url`

7. `member_title`
   from legacy `title`, or fallback to child collection `post_title`

8. `member_slug`
   from legacy `slug`, or fallback to child collection `post_name`

9. `title_override`
   empty string in phase 1

10. `caption_override`
    empty string in phase 1

11. `page_label`
    empty string in phase 1

12. `position`
    from legacy `position`

13. `created_at`
    migration timestamp in UTC

14. `updated_at`
    migration timestamp in UTC

## Read and Write Contract

### Read order during migration

1. read table-backed collection row by `collection_post_id`
2. if present, read members from `wp_iss_archive_collection_members`
3. if no canonical row exists yet, fall back to legacy postmeta

### Write order during rollout

Temporary rollout sequence is allowed to be:

1. backfill tables
2. enable table-first reads with legacy fallback
3. temporarily dual-write collection editor saves to table and legacy meta
4. after verification, stop canonical writes to blob meta

Steady-state target after phase-1 acceptance:

1. collection editor writes canonical data to tables
2. legacy blob meta is read-only compatibility data until final retirement

## Cleanup Policy

### No foreign keys

Phase 1 uses no DB-level foreign keys.

### On deleting an `archivsammlung` shell post

1. delete the row from `wp_iss_archive_collections`
2. delete all rows from `wp_iss_archive_collection_members` for that collection

### On deleting an `archivobjekt` or child `archivsammlung` shell post

Do not cascade-delete member rows automatically.

Reason:

1. ordering evidence should not disappear silently
2. a broken member link is safer than silent data loss
3. fallback fields `member_title`, `member_slug`, `member_source_id`, and `member_source_url` preserve repair context

Service behavior for orphaned member posts:

1. detect missing linked post
2. log or surface the orphan in verification/admin
3. preserve the member row until explicitly repaired

## Rollback Plan

Phase 1 rollback must be additive and non-destructive.

### Hard rule

Do not delete legacy collection meta in phase 1.

### Rollback steps

1. disable table-first reads
2. return collection reads to legacy postmeta only
3. if needed, truncate `wp_iss_archive_collection_members`
4. if needed, truncate `wp_iss_archive_collections`
5. fix the migration code
6. rerun the backfill

Because the old meta stays untouched, rollback should not require public downtime or data reconstruction.

## Verification Queries

### New-table integrity checks

Expected totals after migration:

1. `6` collection rows
2. `902` member rows
3. `899` rows with `member_kind = object`
4. `3` rows with `member_kind = collection`
5. `844` rows with non-empty `page_label`

Run:

```sql
SELECT COUNT(*) AS collections
FROM wp_iss_archive_collections;
```

```sql
SELECT COUNT(*) AS members
FROM wp_iss_archive_collection_members;
```

```sql
SELECT member_kind, COUNT(*) AS rows_per_kind
FROM wp_iss_archive_collection_members
GROUP BY member_kind
ORDER BY member_kind;
```

```sql
SELECT COUNT(*) AS page_labeled_rows
FROM wp_iss_archive_collection_members
WHERE page_label <> '';
```

```sql
SELECT collection_type, COUNT(*) AS rows_per_type
FROM wp_iss_archive_collections
GROUP BY collection_type
ORDER BY collection_type;
```

Expected `collection_type` result:

1. `album = 5`
2. `hybrid = 1`

### Orphan checks

```sql
SELECT id, collection_post_id
FROM wp_iss_archive_collections
WHERE collection_post_id = 0;
```

Expected:

1. `0` rows

```sql
SELECT id, collection_id, member_kind, member_post_id
FROM wp_iss_archive_collection_members
WHERE member_post_id = 0;
```

Expected for the current dataset:

1. `0` rows

### Per-collection member counts

```sql
SELECT
    c.collection_post_id,
    c.collection_type,
    SUM(CASE WHEN m.member_kind = 'object' THEN 1 ELSE 0 END) AS object_members,
    SUM(CASE WHEN m.member_kind = 'collection' THEN 1 ELSE 0 END) AS child_members
FROM wp_iss_archive_collections c
LEFT JOIN wp_iss_archive_collection_members m
    ON m.collection_id = c.id
GROUP BY c.id, c.collection_post_id, c.collection_type
ORDER BY c.collection_post_id;
```

Expected current result:

1. one row with `52` object members and `3` child members
2. five rows with `0` child members

### Legacy-versus-table comparison command

Because the old structure is serialized PHP arrays, strict field comparison must run through WordPress, not plain SQL.

Use this verification command shape, or the implemented wrapper command:

```bash
docker compose run --rm wpcli iss-archive collections-verify --allow-root
```

Equivalent direct check:

```bash
docker compose run --rm wpcli eval '
global $wpdb;
$collection_table = $wpdb->prefix . "iss_archive_collections";
$member_table = $wpdb->prefix . "iss_archive_collection_members";
$posts = get_posts([
    "post_type" => "archivsammlung",
    "post_status" => "any",
    "numberposts" => -1,
    "fields" => "ids",
]);
$errors = [];
foreach ($posts as $post_id) {
    $legacy_items = get_post_meta($post_id, "iss_archive_collection_items", true);
    $legacy_items = is_array($legacy_items) ? array_values($legacy_items) : [];
    $legacy_children = get_post_meta($post_id, "iss_archive_collection_children", true);
    $legacy_children = is_array($legacy_children) ? array_values($legacy_children) : [];

    $table_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT m.member_kind, m.member_role, m.member_post_id, m.member_source_id, m.member_source_url, m.member_title, m.member_slug, m.title_override, m.caption_override, m.page_label, m.position
         FROM {$member_table} m
         INNER JOIN {$collection_table} c ON c.id = m.collection_id
         WHERE c.collection_post_id = %d
         ORDER BY m.position ASC, m.id ASC",
        $post_id
    ), ARRAY_A);

    $legacy_count = count($legacy_items) + count($legacy_children);
    if ($legacy_count !== count($table_rows)) {
        $errors[] = ["post_id" => $post_id, "reason" => "count_mismatch", "legacy" => $legacy_count, "table" => count($table_rows)];
    }
}
echo $errors ? wp_json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : "ok";
' --allow-root
```

Phase-1 acceptance requires this verifier to return:

`ok`

## Acceptance Gate

Phase 1 is accepted only if all of the following are true:

1. collection pages render unchanged
2. album navigation order stays identical
3. `page_label` survives exactly
4. title overrides and caption overrides survive exactly
5. child-collection cards still render
6. table counts match legacy counts
7. collection editor can save without writing new canonical structure only to blob meta

## Immediate Next Implementation Work

After this schema doc:

1. add install/migration code for the two tables
2. add `ArchiveCollectionService`
3. implement backfill
4. switch render helpers to table-first read with meta fallback
5. wire collection saves into canonical sync
