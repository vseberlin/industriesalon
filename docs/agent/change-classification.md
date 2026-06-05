# Change Classification

Classify every task before changing files or infrastructure. If a task spans classes, follow the strictest relevant checks.

| Class | Required docs | Required checks | Closeout |
| --- | --- | --- | --- |
| Content/editorial | `docs/architecture/content-model.md`, `docs/project/uat.md` | editor/source-of-truth check, frontend spot check when public | `TODO.md` only for immediate next work |
| Theme/presentation | `docs/agent/wordpress-engineering.md`, `docs/architecture/source-of-truth.md` | CSS/layout checks from `docs/agent/verification.md` | changelog for substantive public changes |
| Plugin/data contract | `docs/architecture/plugin-map.md`, `docs/architecture/database.md` | PHP/runtime/schema checks | changelog plus handoff if state changes |
| Infrastructure/deploy | `docs/agent/server-operations.md`, `docs/runbooks/deployment.md` | system inspection, logs, service verification | handoff current state/risk/next action |
| Database transfer | `docs/infrastructure/data-artifacts.md` | backup, import/syntax where possible, row counts | record artifact and target state |
| Uploads/media | `docs/runbooks/uploads-sync.md`, `docs/infrastructure/sync.md` | dry-run/counts/sizes/sample URLs | record current sync state if relevant |
| Search/mail/service | `docs/infrastructure/services.md`, relevant runbook | service status/logs/fallback behavior | record service state and rollback |
| Documentation/continuity | `docs/agent/continuity.md`, `docs/README.md` | `git diff --check`, structure remains compact | changelog when model changes |
