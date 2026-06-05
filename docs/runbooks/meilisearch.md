# Meilisearch Runbook

Use this when deploying, configuring, rebuilding, or debugging Meilisearch.

## Rules

- Meilisearch is optional infrastructure.
- SQL search through `iss-graph` remains the fallback path.
- Search index data is rebuildable and not canonical content.
- Public site rendering must continue if Meilisearch is unavailable.

## Procedure

1. Inspect service/container status, logs, disk, memory, and configured endpoint.
2. Confirm whether the runtime is using SQL or Meilisearch provider.
3. Deploy or change service configuration through reproducible infrastructure steps.
4. Rebuild the index from WordPress/database state.
5. Verify frontend search and fallback behavior.
6. Record current service state, rollback path, and next verification in `handoff_CURRENT.md` when relevant.
