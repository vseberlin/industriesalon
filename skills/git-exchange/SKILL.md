---
name: git-exchange
description: Use for this repo when syncing local and staging work through GitHub main, pulling feedback, starting or exiting a session, checking remote state, or preparing deployment. Staging is being retired since 2026-11-12; read docs/infrastructure/hosting-status.md first.
---

# Git Exchange

> **Status 12 November 2026:** the staging VPS (`staging.industriesalon.info`) is being retired and is no longer a deployment, sync or recovery target. Staging references below are historical. See [hosting status](../../docs/infrastructure/hosting-status.md).

Canonical docs:

- `docs/runbooks/git-exchange.md`
- `docs/agent/continuity.md`
- `docs/runbooks/deployment.md` when deployment is involved

Use GitHub `main` as the exchange point. Prefer `git merge --ff-only origin/main` when clean and behind.
