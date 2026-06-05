# Server Operations Rules

Use this for VPS, staging, Docker, deployment, package, service, backup, and infrastructure work.

## Principle

The server provides a stable staging environment. Treat it as infrastructure, not a development sandbox.

Every system change must be minimal, reversible, documented, tested, and based on observable evidence.

## Inspect Before Changing

Run or otherwise verify the equivalent of:

```bash
uptime
free -h
df -h
systemctl --failed
journalctl -p err -b
docker ps
```

Also confirm backup status before significant changes.

## Logging First

Do not guess. Check logs before conclusions:

```bash
journalctl -xe
journalctl -u nginx
journalctl -u php-fpm
journalctl -u mysql
docker logs <container>
```

When something breaks: stop, gather evidence, identify root cause, apply the minimal fix, verify, and document.

## Packages And Services

- Do not run blind upgrades.
- Before package updates:
  - `apt update`
  - `apt list --upgradable`
- Prefer targeted upgrades. Major upgrades require explicit approval.
- Before restarting a service, inspect status, logs, and dependency chain.
- After restart:
  - `systemctl status service`
  - `journalctl -u service -n 100`

## Docker And Data

- Prefer Docker Compose:
  - `docker compose config`
  - `docker compose ps`
  - `docker compose pull`
  - `docker compose up -d`
- Avoid manual container creation.
- Containers are disposable; data is not.
- Persist data only through volumes, bind mounts, databases, backups, and documented configuration.
- Before recreating containers, inspect compose config, service state, and volumes.

## Backups

Verify backup existence before significant or destructive changes.

Critical data:

- database
- uploads
- configuration
- environment files

No destructive operation without backup verification and a rollback path.

## Security

Do not disable firewalls, disable authentication, expose databases publicly, store secrets in repositories, or use root unnecessarily.

Prefer `sudo`, SSH keys, least privilege, and reproducible configuration.

## Staging Integrity

Staging should resemble production. Avoid staging-only hacks, permissions, configuration, or dependencies unless they are explicitly temporary, documented, and removable.

The current deploy policy is local repo -> GitHub `main` -> staging deploy. Direct staging admin code/plugin updates are exceptions and require explicit approval.
