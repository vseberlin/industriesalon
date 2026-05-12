# Archive Phase 7 Schema

## Purpose

This document defines the exact phase-7 schema for a unified `register_place` state model.

Phase 7 covers:

1. `wp_iss_register_place_states`

It does not replace the current editor UI in one shot.

For this phase:

1. existing place meta remains the editor shell for present-day fields
2. the `Zeitschichten` editor remains the editor shell for historical phases
3. the new table becomes the canonical read model used by entities and contracts

## Why This Slice

Before phase 7 the register had two disconnected read sources:

1. current place fields came directly from scattered `register_place` post meta
2. historical phase rows came from `wp_iss_register_place_epochs`
3. contracts stitched them together late in the read path

That left three structural problems:

1. no single canonical state stream for a place
2. no stable place-level read model that spans history and present
3. every richer consumer still had to know too much about legacy field boundaries

Phase 7 fixes that by introducing one normalized state table behind `register_place`.

## Phase-7 Design Decisions

### 1. Read model first, not editor rewrite first

The phase does not replace the existing editor model yet.

Reason:

1. the epoch editor just landed and still needs proving in live editorial use
2. the current place screen already owns many present-day fields
3. phase 7 is about contract and data ownership, not UI churn

### 2. One table for both historical and current states

There is no separate `current_state` table.

Reason:

1. current and historical slices are both temporal state rows
2. consumers need one ordered stream plus a clean current-state pointer
3. future editor refactors should target one state model, not parallel tables

### 3. The current row is derived, not manually edited in this phase

The `current` state row is built from current `register_place` fields such as:

1. `current_use`
2. `current_status`
3. `current_use_type`
4. `source_summary`
5. `source_links`
6. current public images

Reason:

1. it keeps current editor workflows intact
2. it proves the normalized read model before a UI rewrite
3. it avoids introducing two live editors for the same present-day content

### 4. Historical rows are normalized from epoch rows

Historical place-state rows are derived from `wp_iss_register_place_epochs`.

Reason:

1. epochs are already the canonical editor-owned history input
2. phase 7 should unify reads, not fork historical editing
3. the epoch table remains the historical write boundary for now

## Table DDL

### `wp_iss_register_place_states`

```sql
CREATE TABLE {$wpdb->prefix}iss_register_place_states (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    place_post_id bigint(20) unsigned NOT NULL,
    state_key varchar(191) NOT NULL,
    state_kind varchar(40) NOT NULL DEFAULT 'historical',
    state_label varchar(255) NOT NULL,
    summary text NOT NULL,
    era_slug varchar(100) NOT NULL,
    function_key varchar(100) NOT NULL,
    start_year smallint(6) DEFAULT NULL,
    end_year smallint(6) DEFAULT NULL,
    sort_order int(11) NOT NULL DEFAULT 0,
    is_current tinyint(1) NOT NULL DEFAULT 0,
    current_status_key varchar(100) NOT NULL DEFAULT '',
    current_use_type_key varchar(100) NOT NULL DEFAULT '',
    source_summary text NOT NULL,
    source_links_json longtext NOT NULL,
    media_ids_json longtext NOT NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY place_state_key (place_post_id, state_key),
    KEY place_kind (place_post_id, state_kind),
    KEY place_current (place_post_id, is_current),
    KEY era_function (era_slug, function_key),
    KEY chronology (start_year, end_year)
) {$charset_collate};
```

## Controlled Values

### `state_kind`

Allowed values:

1. `historical`
2. `current`

### `state_key`

Current controlled key patterns:

1. `current`
2. `epoch:<epoch-id>` normalized through the runtime key sanitizer

### `current_status_key`

This row-local key uses the normalized current-status vocabulary already exposed by the register Atlas contracts.

It is only populated for the `current` row.

### `current_use_type_key`

This row-local key uses the normalized current-use-type vocabulary already exposed by the register Atlas contracts.

It is only populated for the `current` row.

## Column Intent

1. `place_post_id`
   owning `register_place` post

2. `state_key`
   stable row identity within one place

3. `state_kind`
   distinguishes historical rows from the derived present-day row

4. `state_label`
   human-readable phase label or `Aktuelle Situation`

5. `summary`
   compact narrative for that state row

6. `era_slug`
   editorial era assignment used by current contracts

7. `function_key`
   normalized function classification for the row

8. `start_year` and `end_year`
   temporal bounds when known

9. `is_current`
   marks the present-day row and preserves any epoch-level current flag

10. `current_status_key`
    normalized present-day status key for the current row

11. `current_use_type_key`
    normalized present-day use-type key for the current row

12. `source_summary`
    compact source note attached to the row

13. `source_links_json`
    normalized source URLs attached to the row

14. `media_ids_json`
    normalized attachment IDs attached to the row

## Read/Write Boundary After Phase 7

Write boundaries:

1. present-day shell fields still write to `register_place` meta
2. historical editor rows still write to `wp_iss_register_place_epochs`

Read boundary:

1. entities and contracts should consume `wp_iss_register_place_states` as the canonical unified state model

## Verification

Phase 7 adds a dedicated verification command:

1. `docker compose run --rm wpcli iss-register place-state-check --allow-root`

That command must confirm:

1. the table exists
2. every published `register_place` has state rows
3. every published `register_place` has exactly one canonical current row
