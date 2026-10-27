# Deployment Runbook

## Scope

Use this only when deployment is in scope.

## Preconditions

- Intended commit/checkpoint is known.
- Target environment is known.
- Data changes have backup and rollback expectations.

## Inspect First

- `git status --short`
- target service/container state
- target logs when available

## Procedure

1. Inspect local repo state with `git status --short`.
2. Confirm intended commit/checkpoint and pushed branch.
3. Verify backups for database, uploads, config, and environment files before data changes.
4. Deploy code through local repo -> GitHub `main` -> staging.
5. Transfer SQL/content/media artifacts privately over SSH, outside GitHub.
   Apply SQL, API-migration, and upload artifacts only after code and
   plugin/schema prerequisites are present.
6. Verify frontend routes and logs after deploy.
7. Update `handoff_CURRENT.md` and `CHANGELOG.md` when the checkpoint changes.

## Verification

- Confirm deployed commit.
- Check containers/services and logs.
- Check homepage and changed routes.

## Rollback

Return to the previous Git commit and restore data from verified backups/artifacts when data changed.

## Editorial revision retention

`iss-editorial` caps normal revisions at 30 for an eligible post that has its
canonical document metadata key. Disabled and legacy JSON are included; ordinary
posts/pages without that key keep their existing WordPress policy. Lower global
limits, including disabled revisions, remain effective. Native
`wp_{post_type}_revisions_to_keep` overrides still run last. Private autosaves are
outside the allowance; their existing explicit save/discard lifecycle is unchanged.

Core removes older normal revisions on subsequent revision creation. Deployment
and editor reads do not run a historical purge, and unchanged JSON saves do not
trigger one. Before releasing this policy, verify a current database backup
covering parents, revisions and revision metadata, preserving newer UAT work.
Code rollback stops future pruning but cannot recover already removed history;
restore any needed older revisions from the matching backup through a separately
reviewed recovery. This policy needs no schema migration, SQL replay or uploads
artifact. Do not bulk-prune existing records as part of deployment.

Verify locally with `tests/e2e/bin/editorial-retention.php` and the revision/storage
suites using disposable records. Run `wp iss-editorial validate-all` on the release
target as the read-only canonical-content gate. For editor/registry/validation
changes use `wp iss-editorial validate-all --include-drafts --editor-view` as well:
it checks private recovery and editor-copy compatibility. Invalid drafts require
explicit repair review; stale bases require comparison, not automatic cleanup.
Verify conflict, timeout and authentication errors remain visible in the expanded
workspace and that their recovery buttons work. Keep local fixtures off production.

## What To Document

Record current deploy state, risk, next action, and verification in `handoff_CURRENT.md` when it changes.

## Known Pitfalls

For server inspection rules, read `docs/agent/server-operations.md`.
For local/staging Git coordination, read `docs/runbooks/git-exchange.md`.
For uploads, read `docs/runbooks/uploads-sync.md`.
For optional services, read `docs/infrastructure/services.md`.
