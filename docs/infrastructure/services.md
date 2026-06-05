# Services

Shared service expectations for local and staging agents. Fresh inspection still wins over this file for runtime status.

## Staging Services

- WordPress: Docker Compose managed app/runtime.
- Database: persistent Docker volume or bind-mounted database state; never disposable container state.
- Uploads: persistent WordPress uploads storage; sync through explicit runbooks or artifacts.
- Mail: staging-safe behavior only unless explicitly approved.
- Meilisearch: optional search service; core rendering and SQL search must still work when unavailable.

## Rules

- Core website rendering must not depend on convenience services.
- Service changes need inspection, logs, rollback path, and handoff documentation.
- Runtime status, exact paths, service PIDs, and secret locations belong in machine-local state or live inspection, not committed docs.
- When a service is introduced or changed on staging, update `handoff_CURRENT.md` with the current risk and the next verification step.
