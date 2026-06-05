# Current Handoff

Updated: 2026-06-05

Current checkpoint only. History belongs in `CHANGELOG.md`; active tasks belong in `TODO.md`; durable rules live under `AGENTS.md` and `docs/`.

## Current State

- Branch: `main`
- Latest GitHub checkpoint before the current local commit: `ba0b0c4` (`Finalize infra sync handoff`) on `origin/main`.
- Current local checkpoint prepared for GitHub:
  - Repair Café template/CSS cleanup is file-backed in the theme.
  - Sammlungen template media swaps are synced from the editor DB copy back to disk.
  - Matching DB template overrides were renamed as backups locally; both routes now resolve from `theme`.
  - Media transfer artifacts were added under `ops/sql/` and `ops/uploads/`.
- Local working clone: `/home/vladimir/projects/industriesalon`.
- Staging deployment checkout: `/srv/industriesalon/stage/repo`.
- Staging WordPress app root: `/srv/industriesalon/stage/app`.
- Staging shared uploads root: `/srv/industriesalon/stage/shared/uploads` (`app/wp-content/uploads` symlinks here and the WordPress container mounts it at `/var/www/html/wp-content/uploads`).
- Staging Docker Compose file: `/srv/industriesalon/stage/compose.yml`.
- Staging nginx vhost: `/srv/industriesalon/shared/nginx/stage.industriesalon.info.conf`.
- Server action notes: `/home/vladimir/server-actions/`.
- Agent, continuity, infrastructure, architecture, verification, and sync rules are now repo-owned and staging-shareable.
- Shared docs are split across:
  - `docs/agent/`
  - `docs/architecture/`
  - `docs/infrastructure/`
  - `docs/project/`
  - `docs/runbooks/`
  - `skills/*/SKILL.md`
- `docs/runbooks/git-exchange.md` is the active local/staging machine sync rule. GitHub `main` is the exchange point; clean behind clones fast-forward only; dirty or diverged clones stop for inspection.
- The repo remote now uses the SSH alias `github-industriesalon` and the deploy key `/home/vladimir/.ssh/industriesalon_deploy`.
- Repo-local Git config sets `core.sshCommand=ssh -F /home/vladimir/.ssh/config` because the system SSH include `/etc/ssh/ssh_config.d/20-systemd-ssh-proxy.conf` currently fails default SSH parsing.

## Current Server State

- Provider reported shutdowns after the fact; local evidence shows unclean host stops around 2026-06-01 02:28-02:43 UTC and 2026-06-03 00:00-00:08 UTC. MariaDB recovered cleanly after the 2026-06-03 restart.
- Added a 2 GiB `/swapfile`, persisted in `/etc/fstab`, with `vm.swappiness=10` in `/etc/sysctl.d/99-industriesalon-swap.conf`.
- Hardened SSH with `/etc/ssh/sshd_config.d/20-no-password-auth.conf`; effective setting is `PasswordAuthentication no`, with key auth still enabled.
- Added nginx default catch-all server at `/etc/nginx/sites-available/00-catch-all.conf`, enabled via `/etc/nginx/sites-enabled/00-catch-all.conf`, returning `444` for unknown/raw-IP HTTP and HTTPS hosts.
- Blocked `xmlrpc.php` in `/etc/nginx/sites-available/stage.industriesalon.info`; `https://staging.industriesalon.info/xmlrpc.php` returns `403`.
- Added `/home/vladimir/.ssh/config` with a scoped `github-industriesalon` alias for the Industriesalon deploy key, and switched this repo's `origin` remote to `git@github-industriesalon:vseberlin/industriesalon.git`.
- Server action notes and rollback commands are recorded in:
  - `/home/vladimir/server-actions/2026-06-04-add-swapfile.md`
  - `/home/vladimir/server-actions/2026-06-04-ssh-nginx-hardening.md`
- GitHub SSH setup notes are recorded in `/home/vladimir/server-actions/2026-06-05-github-ssh-deploy-key.md`.
- Last verification: no failed systemd units, staging homepage returned `200 OK`, containers remained up and healthy.

## Current Risk

- `.gitignore` uses a default-deny model, so new shareable docs/skills need explicit narrow allow rules before staging.
- Existing SQL/data artifacts under `ops/sql/` represent local DB transfer state; verify backups before applying them to staging or production.
- Apply `ops/sql/2026-06-05-repair-cafe-sammlungen-media.sql` only after confirming the target has a DB backup; it replaces the referenced attachment rows and removes `page-repair-cafe` / `page-sammlungen` DB template overrides.
- Restore `ops/uploads/2026-06-05-repair-cafe-sammlungen-uploads-delta.tar.gz` into the target uploads root before or with the matching SQL so attachment metadata and files stay aligned.
- Template output can still be DB-backed; check `wp_template` authority before assuming disk files are live.
- Uploads, mail, and Meilisearch are cross-machine concerns; use the repo runbooks before changing staging state.
- Default `ssh` without the repo-local config currently fails on `/etc/ssh/ssh_config.d/20-systemd-ssh-proxy.conf`; inspect that system file before relying on general SSH behavior.

## Next Action

- On the other machine, follow `docs/runbooks/git-exchange.md`: inspect, fetch, and fast-forward to latest `origin/main` if clean and behind.
- After pulling on staging, apply the 2026-06-05 uploads archive and SQL artifact, then verify `/repair-cafe/` and `/sammlungen/` are file-backed and render the new media.
- Keep future handoff entries limited to current state, risk, next action, and verification.
- Keep root `TODO.md` for immediate executable work only; use `docs/project/backlog.md` and `docs/project/uat.md` for broader work.

## Verified

- `git push origin main` succeeded through `github-industriesalon` after adding the deploy-key alias.
- CSS parse passed for `patterns.css`, `primitives.css`, and `page-sammlungen.css`.
- `page-repair-cafe` and `page-sammlungen` both resolve as `source=theme`, `id=none`.
- `/repair-cafe/` and `/sammlungen/` both returned `200` locally and rendered the new media/copy.
- `ops/sql/2026-06-05-repair-cafe-sammlungen-media.sql` imported cleanly into a temporary MariaDB schema.
- `ops/uploads/2026-06-05-repair-cafe-sammlungen-uploads-delta.tar.gz` passed SHA256 verification and contains 61 files.
