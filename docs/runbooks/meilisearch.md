# Meilisearch Runbook

## Scope

Use this when deploying, configuring, rebuilding, or debugging Meilisearch.

## Preconditions

- SQL search fallback remains available.
- Endpoint/configuration ownership is known.

## Inspect First

- service/container status
- logs
- disk and memory
- configured provider

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

## Verification

- Frontend search works.
- SQL fallback remains usable when Meilisearch is unavailable.

## Rollback

Disable the Meilisearch provider and return to SQL search.

## What To Document

Record current provider, service risk, rollback, and next verification when relevant.

## Known Pitfalls

- Treating a generated search index as canonical content.
- Making public search depend on an optional service.
