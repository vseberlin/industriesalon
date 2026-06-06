# Uploads Sync Runbook

## Scope

Use this when syncing, restoring, archiving, or debugging WordPress uploads across local, staging, or production.

## Preconditions

- Sync direction is explicit.
- Source and target paths are known.
- Backup/rollback expectation is clear.
- Any matching SQL artifact need has been checked.

## Inspect First

- source path
- target path
- disk space
- ownership and permissions
- current file count/size

## Procedure

1. Confirm direction explicitly. Do not infer local -> staging or staging -> local.
2. For commit/checkpoint work, compare changed media references and attachment IDs against existing committed upload artifacts before push.
3. Prefer `rsync --dry-run` before a real sync.
4. Preserve upload tree shape so the root maps to `wp-content/uploads/`.
5. Preserve timestamps and permissions where appropriate.
6. Verify with file count/size comparison and a few representative media URLs.
7. Record only the current sync state in `handoff_CURRENT.md`.

## Verification

Compare counts/sizes and check representative media URLs.

## Rollback

Restore from the previous archive/snapshot or reverse only the known sync delta.

## What To Document

Record current source, target, direction, and verification when sync state affects deployment.

## Artifact Alternative

For archive transfer, create a tarball whose entries are relative to the uploads
root, create a checksum, transfer to the remote home directory, verify the
remote hash, then unpack deliberately into `wp-content/uploads/`.

When an upload archive is paired with a SQL artifact, use
`docs/infrastructure/data-artifacts.md#paired-sql-and-upload-artifacts` before
extracting files or importing SQL.

Do not close a checkpoint with changed media references until the matching
upload files and SQL attachment/template/content rows have either been packaged
together or explicitly ruled out.

## Known Pitfalls

- `scp ... :/~` is not the user home; use `:~/` or an explicit `/home/vladimir/` path.
- Local checksum files may contain local paths; compare remote hashes directly if needed.
