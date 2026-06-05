# Decisions

Record durable architectural decisions here when they should outlive a single handoff. Keep entries short and link to implementation files when useful.

## Current Decisions

- Documentation model established on 2026-06-05: root docs stay small, deeper docs are task-loaded, runbooks are canonical procedures, and repo skills are triggers only.
- Keep root `AGENTS.md` compact and load deeper `docs/` files only by task.
- Keep session history out of repo memory folders; use `CHANGELOG.md`, `handoff_CURRENT.md`, and `TODO.md`.
- Use `WP_Query` for editorial post loops, and plugin-owned custom tables plus prepared SQL for projection/search/graph/archive/reporting data.
- Keep public UI in the theme and data/contracts in plugins unless an existing plugin explicitly owns a renderer.
- Use GitHub `main` as the exchange point between local and staging agents; prefer fast-forward-only pulls on clean trees.
