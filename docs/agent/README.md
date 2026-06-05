# Agent Guide

This directory is the repo-owned, shareable agent guidance for the local checkout and staging server. Keep `AGENTS.md` as the small default entrypoint and place detailed rules here only when they are durable across machines.

## Files

- `../README.md`: repo-level documentation index and task routing.
- `wordpress-engineering.md`: theme, plugin, Gutenberg, CSS, PHP, JavaScript, validation, and architecture rules.
- `server-operations.md`: VPS, staging, Docker, packages, services, backups, and logging rules.
- `continuity.md`: handoff, changelog, TODO, memory, and skill rules.
- `change-classification.md`: task classes, required docs, checks, and closeout.
- `verification.md`: verification matrix by change type.
- `no-parallel-systems.md`: operational checklist before adding new systems.
- `../architecture/`: durable ownership and data-shape notes.
- `../infrastructure/`: staging, production, and media-server notes.
- `../project/`: current state, decisions, and roadmap.
- `../runbooks/`: compact repeatable procedures.
- `../../skills/`: small task-triggered wrappers that point to canonical docs.

## Size Discipline

- Keep each file procedural and compact.
- Do not copy session history into agent rules.
- Prefer links to the current source of truth instead of duplicating it.
- Move historical detail to `CHANGELOG.md`.
- Move active next actions to `TODO.md`.
- Move current operational state to `handoff_CURRENT.md`.
- Load deeper `docs/` files only when the task needs that surface.
- Keep repo skills small; avoid duplicating full runbook text inside `SKILL.md`.
- Put cross-machine procedures in repo docs so local and staging agents share the same deployment, sync, mail, and search expectations.
- Current docs model established on 2026-06-05: root docs stay small, deeper docs are task-loaded, runbooks are canonical procedures, and repo skills are triggers only.
