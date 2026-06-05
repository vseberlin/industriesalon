# Current Handoff

Updated: 2026-06-05

Current checkpoint only. History belongs in `CHANGELOG.md`; active tasks belong in `TODO.md`; durable rules live under `AGENTS.md` and `docs/`.

## Current State

- Branch: `main`.
- Latest pushed checkpoint known from prior handoff: `3b114ab` (`Update front page heading and menu links`) on `main` / `origin/main`.
- Current local checkpoint: agent and continuity docs were restructured for small default context and staging-shareable guidance.
- The new staged docs split guidance across:
  - `docs/agent/`
  - `docs/architecture/`
  - `docs/infrastructure/`
  - `docs/project/`
  - `docs/runbooks/`
  - `skills/*/SKILL.md`
- Cross-machine runbooks now cover uploads sync, mail, Meilisearch, services, and sync channels.

## Current Risk

- `.gitignore` uses a default-deny model, so new shareable docs/skills need explicit narrow allow rules before staging.
- Existing SQL/data artifacts under `ops/sql/` represent local DB transfer state; verify backups before applying them to staging or production.
- Template output can still be DB-backed; check `wp_template` authority before assuming disk files are live.
- Uploads, mail, and Meilisearch are cross-machine concerns; use the repo runbooks before changing staging state.

## Next Action

- Commit the staged documentation restructure if the staged diff still matches intent.
- After pulling on staging, verify the staging agent sees `AGENTS.md`, `docs/`, and `skills/`.
- Keep future handoff entries limited to current state, risk, next action, and verification.
- Keep root `TODO.md` for immediate executable work only; use `docs/project/backlog.md` and `docs/project/uat.md` for broader work.

## Verified

- `git diff --check` passed after the final split.
- `git diff --cached --check` passed after staging the final split.
- New docs were kept compact and staged deliberately.
