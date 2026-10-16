# Sync

Shared sync expectations across local, GitHub, staging, and production.

## Channels

- Code: local repo -> GitHub `main` -> staging deploy.
- SQL/data and media payloads: private SSH transfer between the authorized
  source and target; never commit them to GitHub. Migration programs under
  `ops/migrations/` remain code, with required inputs transferred separately.
- Uploads: explicit archive or `rsync` workflow; never assume uploads are already present.
- Search indexes: rebuildable from WordPress/database state.
- Mail queues/logs: runtime state, not canonical content.

## Rules

- Do not treat generated indexes, caches, mail queues, or logs as canonical content.
- Verify source and target before every sync.
- Prefer dry-run first for uploads and large media moves.
- Preserve ownership, permissions, timestamps, and directory shape when syncing uploads.
- Record cross-machine sync decisions in `handoff_CURRENT.md` only when current; durable procedure belongs in `docs/runbooks/`.

## Full Local-To-Staging Refresh

When the user explicitly requests identical local/staging content, local is the
source for all WordPress content, custom tables, users, settings, templates,
uploads and private intake. A partial project migration is insufficient. This
replaces target-only content; retain a verified target rollback backup first.

1. Verify code refs, mounts, environment settings, disk and service health.
2. Back up target DB, uploads, private intake, core/configuration and code;
   read/test archives and verify their hashes before replacement.
3. Quiesce application writes. Export a consistent local DB snapshot and review
   checksum-based dry runs for uploads, private intake and installed core.
4. Transfer snapshots privately over SSH. Mirror only the inspected application
   roots, preserving target configuration, secrets and security controls.
5. Import all local tables; remove superseded target-only content/backup tables
   only after backup verification. Preserve target security audit records.
6. Use serialized-data-aware WP-CLI URL replacement across all imported tables;
   retain GUIDs. Keep staging home/siteurl, noindex and infrastructure secrets.
   Keep environment-specific security/operations helpers separate from content.
7. Verify normalized table hashes and counts, all canonical media references,
   checksum dry runs, restricted-user login/editor behavior and public routes.
   Resume application traffic and inspect logs.
8. Record snapshot/backup locations and exact environment/runtime exceptions.
   New sessions, caches, logs and scheduled-job receipts can diverge after use.

On 16 October 2026 the user explicitly authorized this full refresh and kept
public noindex previews. Data artifacts previously tracked in Git were removed
from the current tree, with private copies retained; Git history was not rewritten.
