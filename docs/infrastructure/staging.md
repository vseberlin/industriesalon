# Staging

Staging is infrastructure, not a development sandbox.

## Policy

- Code changes flow local repo -> GitHub `main` -> staging deploy.
- Direct staging admin code/plugin updates require explicit approval.
- Staging should resemble production. Avoid staging-only dependencies and permissions.
- Public staging exposure is a blocker; verify auth and noindex behavior when relevant.

For the 16 October 2026 project UAT release, the user explicitly chose to keep
the existing public frontend previews as an exception. Preserve `blog_public=0`
and `noindex, nofollow`; admin editing still requires WordPress login. Do not
add site-wide password protection as part of this release.

## Before Changes

Inspect system state, disk, memory, failed services, logs, active containers, and backup status. Follow `docs/agent/server-operations.md`.

## Data

- Verify backups before SQL imports or destructive operations.
- Transfer SQL/content/media privately over SSH, outside GitHub; apply them
  deliberately after matching code/plugin deploy and schema creation.
- Check DB `wp_template` authority when visible staging output differs from disk files.
- See `data-artifacts.md` for SQL/upload transfer artifact rules.
- See `sync.md` and `../runbooks/uploads-sync.md` before syncing uploads.
- See `services.md` before changing mail, search, or other optional services.
- Scheduler, monitored owner jobs, paired backups and scoped intake limits:
  [staging maintenance](../runbooks/staging-maintenance.md). The October refactor
  prepares these locally; it does not establish target activation.

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

## Current content baseline

The user-authorized 16 October full refresh makes local authoritative for site
content and users, including IDs, JSON, templates and media. Both installed
WordPress cores are 7.1.3; staging's existing PHP 8.3 FPM container image still
has the 7.0 tag because core lives in the persistent `app` mount. Local uses
Apache/PHP 8.2 and MariaDB 10.11; staging keeps FPM/MariaDB 11.8. Do not mistake
content/code parity for identical infrastructure configuration.

Preserve staging URL, noindex, credentials, login-audit and REST batch controls.
The old Event Drop MU bridge is retired; current intake uses private Sets.
Mailpit is running, but default WordPress mail has no SMTP route to it and the
configured sendmail executable is absent. Configure/verify mail routing in the
mail-testing task before sending notifications; no user invitation was sent.
