# Data Artifacts

Use explicit artifacts for data transfer and review.

## Mandatory Check

Every commit, push, deploy, and handoff must check whether local DB state or
upload files are required for the changed code/templates/content to work on the
target. When both are required, create and verify paired database artifacts
under `ops/sql/` or `ops/migrations/` and media artifacts under `ops/uploads/`;
do not treat code, database state, and media as independent closeout items. When
no database or upload artifact is needed, record the no-op decision in the
closeout context.

## SQL

- Store deliberate transfer SQL under `ops/sql/`.
- Verify backups before applying SQL to staging or production.
- Apply SQL after code/plugin deploy and required schema creation.
- Keep SQL artifacts narrow: stable content/configuration data, not volatile logs, runtime locks, caches, or diagnostics.
- Do not embed normalized plugin internals such as PHP-serialized relation rows
  when the owning plugin exposes a write API.

## API Migrations

Use readable WP-CLI migrations under `ops/migrations/` when transferred state
must be normalized, indexed, or synchronized by an owning plugin.

- Run them with `wp eval-file` after the matching code deployment.
- Keep source inputs readable and diffable; do not paste encoded database
  representations into the migration.
- Create a targeted backup before the first write and fail when that backup
  already exists.
- Write through public plugin APIs and verify the saved read model afterward.
- Keep rollback instructions and the backup table name in the migration header.

## Uploads

- Store upload transfer manifests or archives under `ops/uploads/` when they are part of deployment.
- Verify hashes locally and remotely after transfer.
- Preserve uploads-relative archive paths so entries map cleanly into `wp-content/uploads/`.
- Use `docs/runbooks/uploads-sync.md` for live `rsync` or restore procedures.

## Paired Database And Upload Artifacts

When a checkpoint contains database artifacts and `ops/uploads/*` artifacts,
treat them as one deployment unit.

- Deploy code first so templates/plugins reference the same contract as the artifacts.
- Verify the uploads archive checksum before extraction.
- Create a DB backup before SQL import.
- Create a targeted rollback archive for any files in the upload manifest that already exist on the target.
- Restore/extract uploads before or with the SQL import so attachment metadata and files stay aligned.
- Apply SQL or API migrations only after code and upload prerequisites are present.
- Verify template authority, expected row counts, manifest files, representative media URLs, changed routes, and container/service health.
- Record backup paths, artifact names, verification, and rollback notes in `handoff_CURRENT.md` or a machine-local server-action note when the deployment changes staging state.

## Backups

- Use host-owned backup files for risky DB/content mutations.
- Record important backup paths in `handoff_CURRENT.md` only when they are relevant to the current checkpoint.
