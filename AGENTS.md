# AGENTS.md

This is the compact entrypoint for agents working in this repository. Keep it small enough to load by default. Detailed, shareable project rules live in `docs/agent/`.

## Load Order

1. Read this file first.
2. Classify the task with `docs/agent/change-classification.md`.
3. For code, design, Gutenberg, CSS, PHP, JavaScript, or template work, read `docs/agent/wordpress-engineering.md`.
4. For VPS, staging, Docker, deploy, backup, package, or service work, read `docs/agent/server-operations.md`.
5. For checkpoint, memory, skill, changelog, handoff, or closeout work, read `docs/agent/continuity.md`.
6. For start, exit, sync, staging feedback, or deploy coordination, read `docs/runbooks/git-exchange.md`.
7. For task-triggered procedures, use the small repo skills under `skills/`; they point back to canonical docs/runbooks.
8. For project structure, read the focused docs under `docs/architecture/`, `docs/infrastructure/`, `docs/project/`, or `docs/runbooks/` only when they match the task.
9. Read `handoff_CURRENT.md` only when current project state matters. Read `CHANGELOG.md` only for historical detail or when writing a new entry.

## Core Priorities

Stability comes first. Core website functionality has priority over convenience services, AI integrations, search indexing, analytics, and background jobs.

Prefer longevity, traceability, simplicity, stability, and coherence over speed. Do not apply quick local tweaks before understanding the owning system, dependency path, and intended architecture.

All changes must be lean, reversible where practical, documented, tested, and justified.

## Repository Rules

- Inspect before changing: existing tools, helpers, theme configuration, build steps, enqueue logic, template authority, plugin boundaries, and prior solutions.
- Do not invent parallel systems when WordPress, Gutenberg, the theme, or an existing plugin already provides the right mechanism.
- Use `docs/agent/no-parallel-systems.md` before adding a new block, helper, table, CSS primitive, service, route, or runbook.
- Keep public presentation in the theme unless an existing plugin explicitly owns a renderer. Keep plugins focused on data, contracts, business logic, imports, and dynamic block data.
- Prefer editor-visible Gutenberg structures, native blocks, reusable patterns, stable semantic classes, and server-rendered output.
- Do not rely on Gutenberg-generated class chains, fragile DOM structure, selector escalation, inline styles, or hidden shortcode-like workflows.
- No `!important`, narrow selector hacks, workaround layers, duplicate logic, or unrelated refactors.
- Before CSS changes, inspect `theme.json`, tokens, global styles, shared layout primitives, card/pattern CSS, and `overrides.css`.
- Before PHP or JavaScript changes, inspect hooks, helpers, enqueue logic, templates, blocks, and plugin ownership.
- Use `WP_Query` for normal editorial post loops; use plugin-owned custom tables and prepared `$wpdb` SQL for projection, search, graph, archive, reporting, and other non-post-shaped data.
- Verify frontend, editor, responsive behavior, template compatibility, and block validation when relevant.
- Use `docs/agent/verification.md` to choose checks for the task class.

## Operations Rules

- Treat staging/VPS systems as infrastructure, not development sandboxes.
- Inspect system status, disk, memory, failed services, logs, running containers, and backup status before server changes.
- Check logs before conclusions. Do not stack random fixes.
- Prefer Docker Compose and reproducible changes over ad hoc container commands.
- Do not run blind package updates, expose databases, disable auth/firewalls, store secrets in Git, or use root unnecessarily.
- No destructive operation without backup verification.

## Continuity Rules

- `handoff_CURRENT.md` is the current checkpoint only. Keep it short.
- `CHANGELOG.md` is the durable historical log.
- `TODO.md` is for active follow-up.
- Local Codex memories and skills should contain compact discovery/procedure notes, not copied project policy or session history.
- Repo-owned agent rules under `docs/agent/` are the shareable source for local and staging agents.
- Repo-owned architecture, infrastructure, project, and runbook docs under `docs/` are loaded on demand, not by default.
- Repo-owned `skills/*/SKILL.md` files are tiny task triggers that point to canonical docs; do not put session history there.
- Cross-machine facts that must be shared between local and staging belong in `docs/infrastructure/`, `docs/runbooks/`, and `handoff_CURRENT.md`; secrets and volatile runtime status stay machine-local.
- GitHub `main` is the exchange point between local and staging agents; follow `docs/runbooks/git-exchange.md` before pulling, pushing, or ending shared work.
- When asked to commit, exit, or write handoff/changelog, update the relevant root docs before stopping.
