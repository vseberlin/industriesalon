# Archive Phase 8: Assertions and Evidence

Phase 8 adds a narrow assertions and evidence layer for source-critical archive facts.

## Scope

This phase does not introduce a universal graph of claims. It adds a canonical place to record disagreements between:

1. the current canonical `archivobjekt` projection
2. the last applied source snapshot already stored in `wp_iss_archive_source_snapshots`

That keeps normal object fields clean while making disputed source facts explicit and inspectable.

## Tables

### `wp_iss_archive_assertions`

Stores active and resolved source-critical disagreements per local archive object.

Key columns:

1. `object_post_id`
2. `source_record_id`
3. `source_snapshot_id`
4. `assertion_key`
5. `field_key`
6. `field_owner_class`
7. `status_key`
8. `confidence_key`
9. `canonical_value_json`
10. `source_value_json`
11. `first_detected_at`
12. `last_detected_at`
13. `resolved_at`
14. `is_active`

Current values:

1. `field_owner_class = source_owned`
2. `status_key = conflicted`
3. `confidence_key = high`

### `wp_iss_archive_evidence`

Stores explicit citation payloads for each assertion.

Current evidence kind:

1. `source_snapshot`

The evidence payload carries:

1. source key and record identifier
2. snapshot id and hash
3. fetched and content-modified timestamps
4. the normalized record that justified the assertion

## Detection Model

Assertions are derived only when the local canonical projection differs from the last applied source snapshot.

Current compared fields:

1. `md_object_id`
2. `year_label`
3. `inventory_number`
4. `object_type_label`
5. `material`
6. `dimensions`
7. `rights_holder`
8. `rights_status`
9. `institution_name`
10. source place assignments

Not included yet:

1. creator attribution, because the current museum-digital snapshot bundle does not expose a reliable object-level creator field
2. editorial local place links, because those remain `editor_owned`
3. title, excerpt, and body content, because those remain editorial shell fields in the current contract

## Sync Rules

The assertion service runs:

1. on plugin activation for install plus backfill
2. on `init` for schema/backfill guards
3. after archive object saves
4. after import writes, because imports already refresh object, relation, and source-record projections first

When a disagreement disappears, the assertion row is not deleted. It is marked inactive and receives `resolved_at`.

## Verification

New command:

```bash
wp iss-archive assertions-verify --allow-root
```

It verifies:

1. active assertions only exist when an applied snapshot exists
2. active assertions carry `conflicted` status and a confidence key
3. each active assertion has explicit evidence

## Why This Phase Is Narrow

This phase intentionally avoids:

1. a universal assertion system for every entity type
2. editor workflows for manual review
3. speculative confidence logic
4. route or template changes

It creates the minimum canonical structure needed to represent disputed source facts without polluting normal archive object fields.
