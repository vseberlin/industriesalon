# Data Artifacts

Use explicit private artifacts for data transfer and review. GitHub carries
code, migration programs and documentation; it does not carry SQL dumps,
content snapshot JSON, uploaded media, or transfer archives.

## Mandatory Check

Every commit, push, deploy, and handoff must check whether local DB state or
upload files are required for the changed code/templates/content to work on the
target. When both are required, create and verify paired database artifacts
in ignored local storage (for example `var/<release>/`) and transfer them
directly to the target over SSH;
do not treat code, database state, and media as independent closeout items. When
no database or upload artifact is needed, record the no-op decision in the
closeout context.

## SQL

- Store deliberate transfer SQL privately under ignored release storage.
- Historical `ops/sql/` files are ignored compatibility inputs, not Git assets.
- Verify backups before applying SQL to staging or production.
- Apply SQL after code/plugin deploy and required schema creation.
- For ordinary deployments, keep SQL artifacts narrow: stable content/configuration
  data, not volatile logs, runtime locks, caches, or diagnostics. An explicitly
  authorized full refresh follows `sync.md` and transfers a consistent snapshot.
- Do not embed normalized plugin internals such as PHP-serialized relation rows
  when the owning plugin exposes a write API.

## API Migrations

Use readable WP-CLI migrations under `ops/migrations/` when transferred state
must be normalized, indexed, or synchronized by an owning plugin.

- Run them with `wp eval-file` after the matching code deployment.
- Migration PHP stays in Git; content JSON and other source inputs travel
  privately with the release. Historical scripts expect these inputs beside
  the PHP files under `ops/migrations/`; restore them there from private storage.
- Keep source inputs readable and diffable; do not paste encoded database
  representations into the migration.
- Create a targeted backup before the first write and fail when that backup
  already exists.
- Write through public plugin APIs and verify the saved read model afterward.
- Keep rollback instructions and the backup table name in the migration header.

## Uploads

- Store upload manifests and archives privately with the database snapshot.
- Historical `ops/uploads/` inputs are ignored and restored privately when a
  historical migration needs them.
- Verify hashes locally and remotely after transfer.
- Preserve uploads-relative archive paths so entries map cleanly into `wp-content/uploads/`.
- Use `docs/runbooks/uploads-sync.md` for live `rsync` or restore procedures.

## Paired Database And Upload Artifacts

When a checkpoint contains database and upload artifacts,
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
