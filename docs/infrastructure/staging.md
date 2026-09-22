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

## Access and active paths

- SSH: `vladimir@staging.industriesalon.info` using the saved workstation key;
  verify the host key and identity before operations.
- Checkout: `/srv/industriesalon/stage/repo`.
- Runtime: `/srv/industriesalon/stage/compose.yml`, with environment settings in
  the sibling `.env`; neither is the repository's development Compose file.
- WordPress root: `/srv/industriesalon/stage/app`; uploads:
  `/srv/industriesalon/stage/shared/uploads`.
- WP-CLI: from `/srv/industriesalon/stage`, run
  `docker compose run --rm -T --no-deps wpcli ...`.

The existing `ops/deploy-stage.sh` resets the checkout. Do not use it over
unreviewed local edits. For code-only updates with unchanged runtime configuration,
preserve/classify dirty state, fetch GitHub and fast-forward the checkout; the
running containers already bind-mount its plugin and theme directories. A restart
is not needed just to load changed source. Keep secrets such as the CARTO key in
`app/wp-config.php`, never in the shared repository.
