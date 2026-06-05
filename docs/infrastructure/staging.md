# Staging

Staging is infrastructure, not a development sandbox.

## Policy

- Code changes flow local repo -> GitHub `main` -> staging deploy.
- Direct staging admin code/plugin updates require explicit approval.
- Staging should resemble production. Avoid staging-only dependencies and permissions.
- Public staging exposure is a blocker; verify auth and noindex behavior when relevant.

## Before Changes

Inspect system state, disk, memory, failed services, logs, active containers, and backup status. Follow `docs/agent/server-operations.md`.

## Data

- Verify backups before SQL imports or destructive operations.
- Apply SQL artifacts deliberately after code/plugin deploy and required plugin activation/schema creation.
- Check DB `wp_template` authority when visible staging output differs from disk files.
- See `data-artifacts.md` for SQL/upload transfer artifact rules.
- See `sync.md` and `../runbooks/uploads-sync.md` before syncing uploads.
- See `services.md` before changing mail, search, or other optional services.
