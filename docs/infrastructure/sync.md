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

Local may be authoritative for content, templates, media and approved custom
data. Accounts and authentication belong to each target environment, including
when the user requests identical content. Never transfer local passwords or
sessions as part of a content refresh.

The former unrestricted `ops/import-db-stage.sh` is disabled and exits before
connecting to the database. Its target copy at
`/srv/industriesalon/stage/ops/import-db-stage.sh` must remain the same disabled
entrypoint. Do not bypass it with a raw SQL pipe or replay an old private import
script. Use reviewed owner/API migrations under `ops/migrations/`. A future bulk
content transfer needs an explicitly reviewed table/record scope and verification
that target identity and security state remain unchanged.

1. Verify code refs, mounts, environment settings, disk and service health.
2. Back up target DB, uploads, private intake, core/configuration and code;
   read/test archives and verify their hashes before writes.
3. Exclude the target's `users` and `usermeta` tables (using its actual prefix),
   user IDs/logins/emails, password and reset hashes, sessions, application
   passwords, roles/capabilities, security audit tables and environment settings.
   Do not replace the whole options table. Apply role changes only through the
   separately reviewed owning capability migration.
4. Resolve content authors/owners against existing target accounts. Abort on an
   unresolved identity; provision new users separately with a target-specific
   password and explicit permission/notification scope.
5. Quiesce writes when required by the migration. Export only reviewed content
   records and paired media; transfer privately over SSH and apply through owner
   APIs. Preserve target configuration, secrets, security controls and noindex.
6. Limit serialized-data-aware URL replacement to transferred content, retaining
   GUIDs. Never run blanket replacement across account or authentication tables.
7. Verify account identity, roles and credential metadata against the private
   target before-image without exposing values. Verify content/media, restricted
   editor access, public routes and logs before resuming traffic. Abort on an
   unexplained security-state difference.
8. Record artifacts and target-only exceptions. Never restore retired credentials
   or sessions from a rollback backup. Disaster recovery from a historical full
   dump must stay offline until credentials are rotated and sessions/application
   passwords revoked; it is not a deployment procedure.

The 16 October 2026 full refresh copied the local admin credential into staging.
Offline checks confirmed this on 21 October. The above account boundary replaces
the old instruction to import all tables/users. Incident backups and Git history
remain preserved evidence; access is revoked by retiring the credential.
