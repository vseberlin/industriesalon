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
5. Apply SQL/upload artifacts only after code and plugin/schema prerequisites are present.
6. Verify frontend routes and logs after deploy.
7. Update `handoff_CURRENT.md` and `CHANGELOG.md` when the checkpoint changes.

## Verification

- Confirm deployed commit.
- Check containers/services and logs.
- Check homepage and changed routes.

## Rollback

Return to the previous Git commit and restore data from verified backups/artifacts when data changed.

## What To Document

Record current deploy state, risk, next action, and verification in `handoff_CURRENT.md` when it changes.

## Known Pitfalls

For server inspection rules, read `docs/agent/server-operations.md`.
For uploads, read `docs/runbooks/uploads-sync.md`.
For optional services, read `docs/infrastructure/services.md`.
