# Current Handoff

Updated: 2026-06-05

Current checkpoint only. History belongs in `CHANGELOG.md`; active tasks belong in `TODO.md`; durable rules live under `AGENTS.md` and `docs/`.

## Current State

- Branch: `main`
- Latest GitHub checkpoint: `90132e7` (`Add shared agent runbooks`) on `origin/main`.
- Local `main` also contains:
  - `1dd6423` (`Document local project paths`)
  - `35f0548` (`Document server hardening handoff`)
- Local working clone: `/home/vladimir/projects/industriesalon`.
- Staging deployment checkout: `/srv/industriesalon/stage/repo`.
- Staging WordPress app root: `/srv/industriesalon/stage/app`.
- Staging shared uploads root: `/srv/industriesalon/stage/shared/uploads` (`app/wp-content/uploads` symlinks here and the WordPress container mounts it at `/var/www/html/wp-content/uploads`).
- Staging Docker Compose file: `/srv/industriesalon/stage/compose.yml`.
- Staging nginx vhost: `/srv/industriesalon/shared/nginx/stage.industriesalon.info.conf`.
- Server action notes: `/home/vladimir/server-actions/`.
- Agent and continuity docs were restructured for small default context and staging-shareable guidance.
- The new staged docs split guidance across:
  - `docs/agent/`
  - `docs/architecture/`
  - `docs/infrastructure/`
  - `docs/project/`
  - `docs/runbooks/`
  - `skills/*/SKILL.md`
- Cross-machine runbooks now cover uploads sync, mail, Meilisearch, services, and sync channels.

## Current Server State

- Provider reported shutdowns after the fact; local evidence shows unclean host stops around 2026-06-01 02:28-02:43 UTC and 2026-06-03 00:00-00:08 UTC. MariaDB recovered cleanly after the 2026-06-03 restart.
- Added a 2 GiB `/swapfile`, persisted in `/etc/fstab`, with `vm.swappiness=10` in `/etc/sysctl.d/99-industriesalon-swap.conf`.
- Hardened SSH with `/etc/ssh/sshd_config.d/20-no-password-auth.conf`; effective setting is `PasswordAuthentication no`, with key auth still enabled.
- Added nginx default catch-all server at `/etc/nginx/sites-available/00-catch-all.conf`, enabled via `/etc/nginx/sites-enabled/00-catch-all.conf`, returning `444` for unknown/raw-IP HTTP and HTTPS hosts.
- Blocked `xmlrpc.php` in `/etc/nginx/sites-available/stage.industriesalon.info`; `https://staging.industriesalon.info/xmlrpc.php` returns `403`.
- Server action notes and rollback commands are recorded in:
  - `/home/vladimir/server-actions/2026-06-04-add-swapfile.md`
  - `/home/vladimir/server-actions/2026-06-04-ssh-nginx-hardening.md`
- Last verification: no failed systemd units, staging homepage returned `200 OK`, containers remained up and healthy.

## Current Risk

- `.gitignore` uses a default-deny model, so new shareable docs/skills need explicit narrow allow rules before staging.
- Existing SQL/data artifacts under `ops/sql/` represent local DB transfer state; verify backups before applying them to staging or production.
- Template output can still be DB-backed; check `wp_template` authority before assuming disk files are live.
- Uploads, mail, and Meilisearch are cross-machine concerns; use the repo runbooks before changing staging state.

## Next Action

- After pulling on staging, verify the staging agent sees `AGENTS.md`, `docs/`, and `skills/`.
- Keep future handoff entries limited to current state, risk, next action, and verification.
- Keep root `TODO.md` for immediate executable work only; use `docs/project/backlog.md` and `docs/project/uat.md` for broader work.

## Verified

- `git diff --check` passed after the final split.
- `git diff --cached --check` passed after staging the final split.
- New docs were kept compact and staged deliberately.
