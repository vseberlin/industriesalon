# Data Artifacts

Use explicit artifacts for data transfer and review.

## SQL

- Store deliberate transfer SQL under `ops/sql/`.
- Verify backups before applying SQL to staging or production.
- Apply SQL after code/plugin deploy and required schema creation.
- Keep SQL artifacts narrow: stable content/configuration data, not volatile logs, runtime locks, caches, or diagnostics.

## Uploads

- Store upload transfer manifests or archives under `ops/uploads/` when they are part of deployment.
- Verify hashes locally and remotely after transfer.
- Preserve `uploads/` as the archive root when creating restore-friendly tarballs.
- Use `docs/runbooks/uploads-sync.md` for live `rsync` or restore procedures.

## Backups

- Use host-owned backup files for risky DB/content mutations.
- Record important backup paths in `handoff_CURRENT.md` only when they are relevant to the current checkpoint.
