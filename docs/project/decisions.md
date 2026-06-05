# Decisions

Record durable architectural decisions here when they should outlive a single handoff. Keep entries short and link to implementation files when useful.

## Current Decisions

- Keep root `AGENTS.md` compact and load deeper `docs/` files only by task.
- Keep session history out of repo memory folders; use `CHANGELOG.md`, `handoff_CURRENT.md`, and `TODO.md`.
- Use `WP_Query` for editorial post loops, and plugin-owned custom tables plus prepared SQL for projection/search/graph/archive/reporting data.
- Keep public UI in the theme and data/contracts in plugins unless an existing plugin explicitly owns a renderer.
