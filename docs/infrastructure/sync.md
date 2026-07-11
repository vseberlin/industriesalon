# Sync

Shared sync expectations across local, GitHub, staging, and production.

## Channels

- Code: local repo -> GitHub `main` -> staging deploy.
- SQL/data: explicit artifacts under `ops/sql/` or API-owned migrations under
  `ops/migrations/`.
- Uploads: explicit archive or `rsync` workflow; never assume uploads are already present.
- Search indexes: rebuildable from WordPress/database state.
- Mail queues/logs: runtime state, not canonical content.

## Rules

- Do not treat generated indexes, caches, mail queues, or logs as canonical content.
- Verify source and target before every sync.
- Prefer dry-run first for uploads and large media moves.
- Preserve ownership, permissions, timestamps, and directory shape when syncing uploads.
- Record cross-machine sync decisions in `handoff_CURRENT.md` only when current; durable procedure belongs in `docs/runbooks/`.
