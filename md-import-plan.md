# Museum-Digital Archive Media Migration Plan

## Goal

Normalize archive media handling so that:

- high-resolution archive masters are stored locally outside WordPress Media Library authority
- WordPress keeps only screen-optimized preview assets for frontend/editor/runtime use
- archive metadata, provenance, and descriptive text remain attached to the archive projection/runtime
- existing imported assets continue to render during the migration

This is a deferred implementation plan only. Do not execute blindly. Each phase should be validated before moving to the next one.

## Current State Summary

Observed characteristics of the current archive media flow:

- many `archivobjekt` records still depend on WordPress featured-image attachments
- the importer can still download source images into WordPress attachments
- archive media projection already supports a mixed model:
  - `source_url`
  - `preview_url`
  - `attachment_id`
  - `preview_attachment_id`
  - `storage_kind`
- the Media Library currently contains a large archive-owned attachment set, so runtime cannot be switched by deletion alone

Implication:

- the current system is not yet safe to clean up by removing archive attachments from WordPress
- migration must preserve legacy attachments as fallback until preview-first rendering is fully proven

## Target Model

For each archive image, separate three responsibilities:

1. Archive master
- canonical high-resolution local file
- stored outside WordPress Media Library authority
- owned by archive runtime/storage

2. Runtime preview
- screen-sized derivative
- stored as a normal WordPress attachment
- used by frontend cards, object pages, editor previews, and featured-image compatibility

3. Archive metadata and provenance
- source URLs, rights, description, labels, dimensions, checksum, source identifiers
- stored in archive tables/meta, not dependent on attachment post fields

## Non-Goals

- do not redesign archive editorial metadata in this migration
- do not remove legacy attachments in the first pass
- do not introduce live remote dependency at render time
- do not block current public rendering while backfill is incomplete

## Guiding Rules

- importer-first, then renderer, then backfill, then cleanup
- every migration step must be resumable
- every migrated item must remain renderable even if later steps pause
- no destructive delete until preview-first rendering is verified in production-like conditions
- keep enough legacy markers to audit which assets were migrated and how

## Proposed Data Model Additions

Add explicit archive-master fields to the archive media projection or adjacent archive-owned metadata:

- `master_storage`
  - expected values: `local_archive`, `remote`, `none`
- `master_path`
  - filesystem path to canonical high-resolution local archive file
- `master_checksum`
  - content checksum for dedupe/audit
- `master_mime_type`
- `master_width`
- `master_height`
- `preview_generation_status`
  - expected values: `pending`, `generated`, `failed`, `legacy`
- `legacy_attachment_id`
  - original attachment that used to act as the primary local runtime asset

If `storage_kind` remains in use, normalize its meaning:

- `legacy_attachment`
- `preview_attachment`
- `remote`
- `hybrid`

Exact schema location should be chosen based on current archive table ownership in `iss-wf-import`, but the semantics should be explicit either way.

## Storage Layout Proposal

Store archive masters outside normal uploads authority, for example under a dedicated archive path such as:

- `var/archive-media/masters/...`
- or another repo-external persistent path mounted into the container/runtime

Requirements:

- persistent across deploys/local rebuilds
- not mixed with editor uploads
- not indexed as ordinary Media Library content
- pathing should support deterministic naming by archive source/object/media identifier

Recommended naming ingredients:

- source system key
- source object id
- source media id or deterministic ordinal
- checksum or stable suffix

## Migration Phases

### Phase 0: Audit and Freeze

Before code changes:

- inventory current archive media states
- identify how many assets are:
  - legacy attachment only
  - preview-backed
  - remote-only
  - missing preview representation
- confirm every consumer path that still relies on:
  - `_thumbnail_id`
  - `has_post_thumbnail()`
  - `get_the_post_thumbnail()`
  - attachment IDs stored in archive meta

Deliverable:

- audited list of runtime consumers and migration blockers

### Phase 1: Stop Creating New Hi-Res Media-Library Bloat

Change the museum-digital importer for future imports:

- download the canonical high-resolution source into archive-owned local storage
- do not store that master as the primary WordPress attachment
- generate a preview/screen derivative
- create a WordPress attachment only for the preview asset
- populate archive media projection with:
  - `master_path`
  - `master_checksum`
  - preview attachment
  - preview dimensions
  - descriptive metadata

Compatibility during this phase:

- keep writing enough fallback metadata for current frontend paths until renderer changes land

Success criteria:

- new archive imports no longer create hi-res library clutter
- new archive objects still render normally on the public site

### Phase 2: Introduce Preview-First Render Resolution

Update archive renderers and helper services so image resolution order becomes:

1. `preview_attachment_id`
2. `preview_url`
3. legacy attachment / featured image
4. no-image fallback

Priority targets:

- archive cards
- archive collection cards
- archive object single views
- any shared block render callback in `iss-wf-import` or related archive UI

Important:

- featured-image dependency should become compatibility fallback, not the primary contract

Success criteria:

- archive frontend works with preview-first data
- legacy objects continue to render unchanged

### Phase 3: Backfill Existing Legacy Attachments

For each existing archive-owned attachment:

1. inspect the current source attachment file
2. copy or move the canonical original into archive master storage
3. compute/store checksum and dimensions
4. generate a preview derivative if needed
5. create or assign a WordPress preview attachment
6. record `legacy_attachment_id`
7. mark migration status on the archive media row

Important rules:

- do not delete or detach the legacy attachment yet
- make this batchable and resumable
- prefer idempotent commands keyed by archive object/media ids

Success criteria:

- archive object has canonical master + preview representation
- old attachment still exists as rollback fallback

### Phase 4: Repoint Compatibility Fields

Once preview-first rendering is verified, repoint WordPress compatibility fields:

- set `_thumbnail_id` to the preview attachment for archive objects that still need WP featured-image compatibility
- update any archive-local attachment meta that should now reference the preview attachment instead of the legacy original

Goal:

- WordPress/editor/query-loop style consumers continue to work
- the old hi-res attachment stops being the runtime thumbnail identity

Success criteria:

- archive objects using WP thumbnail helpers resolve to preview attachments
- public cards no longer need the old legacy asset

### Phase 5: Quarantine Legacy Attachments

After compatibility fields are repointed:

- tag legacy attachments clearly, for example with:
  - `_iss_archive_legacy_original = 1`
  - `_iss_archive_master_migrated = 1`
  - `_iss_archive_replaced_by_preview = <preview_attachment_id>`
- remove them from normal archive runtime resolution
- keep them available for rollback/audit

Optional:

- move them to a dedicated admin bucket or taxonomy marker if operationally useful

Success criteria:

- no active frontend path depends on the old legacy attachment
- rollback remains possible

### Phase 6: Controlled Cleanup

Only after successful validation:

- detach or delete legacy hi-res attachments in batches
- never delete the archive master copy outside WordPress
- keep a migration report for each batch

Cleanup should be:

- batched
- logged
- restartable
- reversible until file deletion is confirmed safe

## Suggested Implementation Units

### Importer

Likely work area:

- `plugins/iss-archive/includes/museum-digital-importer.php`

Tasks:

- split master download from preview attachment creation
- write archive-owned storage path logic
- add preview derivative generation
- populate new archive media fields

### Media Service / Resolver

Likely work area:

- `plugins/iss-archive/includes/media-service.php`

Tasks:

- introduce canonical image resolution helpers
- encapsulate preview-first fallback order
- expose helper methods to renderers

### Archive Rendering

Likely work area:

- `plugins/iss-archive/includes/blocks.php`
- any archive single template helpers that still use featured-image functions directly

Tasks:

- switch renderer logic to media-service helpers
- reduce direct `has_post_thumbnail()` dependence

### Backfill Command

Recommended delivery:

- WP-CLI command in archive plugin scope

Desired capabilities:

- dry run
- filter by object id or batch size
- resumable cursor
- verification output
- preview generation only mode
- repoint `_thumbnail_id` mode

## Validation Checklist

Validate after each phase:

- archive cards render images
- archive object single pages render images
- archive collection listings render images
- Media Library still behaves normally for editorial users
- imported archive descriptions/rights/source metadata remain intact
- no live render path requires museum-digital network access
- new imports follow the new storage contract

## Rollback Strategy

At every migration stage before final cleanup:

- keep legacy attachment records intact
- keep legacy files intact
- avoid overwriting without recording original IDs and paths
- allow renderer fallback to old attachment if preview resolution fails

Rollback should only require:

- switching resolver order back to legacy-first
- restoring `_thumbnail_id` where necessary

## Open Decisions

- exact filesystem location for archive masters
- whether archive master storage belongs inside current project mounts or an external persistent volume
- whether checksums should be stored in archive tables or adjacent meta
- whether preview generation should happen via WP image APIs, Imagick, or a dedicated archive utility path
- whether some preview assets can remain remote when stable `preview_url` already exists, or whether local preview normalization should be mandatory

## Recommended First Work Session

When this migration is scheduled, do the first session in this order:

1. audit current consumer paths
2. define the archive master storage location
3. patch importer for new imports only
4. patch media resolver to support preview-first order
5. test one or two newly imported archive objects

Do not start with bulk cleanup. The current runtime still depends on legacy attachments in too many places.
