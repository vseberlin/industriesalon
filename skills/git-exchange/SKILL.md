---
name: git-exchange
description: Use for this repo when syncing local and staging work through GitHub main, pulling feedback, starting or exiting a session, checking remote state, or preparing deployment.
---

# Git Exchange

Canonical docs:

- `docs/runbooks/git-exchange.md`
- `docs/agent/continuity.md`
- `docs/runbooks/deployment.md` when deployment is involved

Use GitHub `main` as the exchange point. Prefer `git merge --ff-only origin/main` when clean and behind.
