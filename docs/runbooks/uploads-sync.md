# Uploads Sync Runbook

Use this when syncing, restoring, archiving, or debugging WordPress uploads across local, staging, or production.

## Procedure

1. Inspect both machines first:
   - source path
   - target path
   - disk space
   - ownership and permissions
   - current file count/size
2. Confirm direction explicitly. Do not infer local -> staging or staging -> local.
3. Prefer `rsync --dry-run` before a real sync.
4. Preserve upload tree shape so the root maps to `wp-content/uploads/`.
5. Preserve timestamps and permissions where appropriate.
6. Verify with file count/size comparison and a few representative media URLs.
7. Record only the current sync state in `handoff_CURRENT.md`.

## Artifact Alternative

For archive transfer, create a tarball whose root is `uploads/`, create a checksum, transfer to the remote home directory, verify the remote hash, then unpack deliberately into `wp-content/`.

## Known Pitfalls

- `scp ... :/~` is not the user home; use `:~/` or an explicit `/home/vladimir/` path.
- Local checksum files may contain local paths; compare remote hashes directly if needed.
