# Current State

Use `handoff_CURRENT.md` for the live operational checkpoint. This file describes only stable project structure.

## Stable Direction

- Theme owns visible public composition and frontend skins.
- Plugins own data contracts, CPTs, imports, dynamic block data, and service APIs.
- File-backed templates are preferred for durable block-theme routes unless editors intentionally own a DB override.
- SQL/data transfer is explicit through repo artifacts, not hidden migration behavior.
- Agent rules are repo-owned under `AGENTS.md` and `docs/`, with current state in `handoff_CURRENT.md`.
- Root `TODO.md` is limited to immediate executable work; broader future work lives in `backlog.md` and UAT-dependent work in `uat.md`.
- Cross-machine deployment/sync/service knowledge is repo-owned so local and staging agents share the same procedures.
