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

The 16 October refresh established the site content baseline, including JSON,
templates and media. Account credentials are now strictly target-owned: the
old full refresh imported the local admin password into staging. Follow the
account-preserving transfer boundary in `sync.md`; the unrestricted importer is
disabled. Both installed
WordPress cores are 7.1.3; core lives in the persistent `app` mount, independently
of the container's bundled initial-install version. Local uses
Apache/PHP 8.2 and MariaDB 10.11; staging keeps FPM/MariaDB 11.8. Do not mistake
content/code parity for identical infrastructure configuration.

Preserve staging URL, noindex, credentials, login-audit and REST batch controls.
The old Event Drop MU bridge is retired; current intake uses private Sets.
Mailpit is running, but default WordPress mail has no SMTP route to it and the
configured sendmail executable is absent. Configure/verify mail routing in the
mail-testing task before sending notifications; no user invitation was sent.

## PHP runtime security update

On 21 October 2026, the user authorized patching staging and the separate
`/srv/industriesalon/touchtable` stack for CVE-2026-109618/109619. Both now use
`iss-wordpress:php8.3.36-20261021`, image ID
`sha256:39399375f92c9b2bc65719b9533eba3d51408c6349d5793f044bc62371459226`.
Their existing Compose files specify this image, its build context and
`pull_policy: never`. Only the two WordPress services were recreated.

The official WordPress tags inspected during the release still contained
unpatched PHP. The emergency image uses the upstream WordPress recipe at
commit `2d7faab7fb71508e6854cdbed948c195957f279e`, with PHP 8.3.36 pinned by
registry digest. PECL returned HTTP 504, so the same Imagick 3.8.1 release was
built from its pinned upstream GitHub commit. Exact sources, hashes, Dockerfile
and build log are retained at `/srv/industriesalon/stage/ops/php-security-20261021/`.
From either stack, `docker compose build wordpress` rebuilds that recipe.

Private evidence and recovery instructions:
`/srv/industriesalon/stage/backups/php-security-20261021/`.
The fresh staging backup is `backups/iss-backup-20261021T171343Z/` (119 tables,
seven verified artifacts); the patch evidence directory contains touchtable's
58-table dump and full runtime/uploads archive. A local receipt and build-source
copy is retained in ignored `var/php-security-20261021/`.
The previous image remains tagged `iss-wordpress:pre-php-security-20261021`;
it is vulnerable, so an emergency rollback must restrict external access.
Do not restore databases or uploads for a runtime-only rollback.

Verified: FPM 8.3.36, healthy containers, 41 extensions, GD/Imagick PNG encoding,
Intl, nine route responses, no startup PHP errors, unchanged mounts, PHP limits
and config/core-version hashes. Installed core remains staging 7.1.3 and
touchtable 7.0.3. Staging remains noindex; other containers are unchanged.
No source-code deployment, database migration or uploads transfer was required.
Replace the emergency image with an official image during subsequent runtime
maintenance only after verifying PHP 8.3.36 or later and repeating these checks.

## Upload PHP protection and incident evidence

The active staging vhost is `/etc/nginx/sites-available/stage.industriesalon.info`.
Its existing uploads/files PHP deny location must precede the generic PHP
location: Nginx uses the first matching regular-expression location. This order
was corrected with user approval on 21 October 2026. `nginx -t` and reload
passed; both legacy WPForms upload PHP guards return 403, while ordinary media,
home, login, theme CSS and Event Drop remain available. Preserve this order in
future vhost changes.

Private containment evidence and exact pre-change vhost backup:
`/srv/industriesalon/stage/backups/incident-20261021/`.
The workstation review is `var/login-investigation-20261021/impact-review.json`.
All WordPress sessions were revoked; the affected admin had no application
passwords. Account/code/upload/task comparisons found no unexplained retained
access, but cannot exclude data access during the confirmed login. Do not restore
old session/credential rows from incident evidence. No content migration or
uploads transfer accompanied containment or the vhost correction.
