# Documentation Index

This directory is the repo-owned source for durable project, agent, architecture, infrastructure, and runbook knowledge. Root files stay small by design.

## Document Roles

- Policy: `docs/agent/`
- Architecture facts: `docs/architecture/`
- Infrastructure facts: `docs/infrastructure/`
- Current project direction: `docs/project/`
- Procedures: `docs/runbooks/`
- Task triggers: `skills/*/SKILL.md`
- Current checkpoint: `handoff_CURRENT.md`
- Immediate tasks: `TODO.md`
- History: `CHANGELOG.md`

## Ownership Map

- Theme: public templates, skins, frontend layout, and presentation.
- Plugins: CPTs, data contracts, dynamic block data, imports, projections, custom tables, and service APIs.
- Infrastructure: deploy path, services, backups, sync, mail/search runtime, and persistence.
- Editors: Gutenberg-visible content, patterns, and intentional DB template overrides.

## Common Task Routing

- Content/editorial: `docs/architecture/content-model.md`, `docs/project/uat.md`
- Theme/presentation: `docs/agent/wordpress-engineering.md`
- Plugin/data contract: `docs/architecture/plugin-map.md`, `docs/architecture/database.md`
- Infrastructure/deploy: `docs/agent/server-operations.md`, `docs/runbooks/deployment.md`
- Database transfer: `docs/infrastructure/data-artifacts.md`
- Uploads/media: `docs/runbooks/uploads-sync.md`, `docs/infrastructure/sync.md`
- Search/mail/service: `docs/infrastructure/services.md`, relevant runbook
- Continuity/docs: `docs/agent/continuity.md`
- Git exchange/start/exit: `docs/runbooks/git-exchange.md`

## Change Discipline

Classify the task before changing anything. Use `docs/agent/change-classification.md` and `docs/agent/verification.md` to choose required docs, checks, and closeout.
