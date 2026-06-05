# Search Runbook

## Scope

Use this for search or index work.

## Preconditions

- Search owner is identified: `iss-graph` SQL provider first, Meilisearch optional.

## Inspect First

- configured provider
- search projection/index state
- frontend search route/UI

## Rules

- Start with `iss-graph` search provider and index ownership.
- Keep SQL search as the graceful fallback path.
- Treat external search services as optional convenience layers.
- Rebuild or inspect projection data before changing frontend search UI.
- Do not create a parallel search route until the full search experience is deliberately redesigned.

## Procedure

Inspect or rebuild projection data before changing UI or provider behavior.

## Verification

Check frontend results, provider fallback, and representative indexed content.

## Rollback

Return to SQL provider and rebuild projection from canonical WordPress/database state.

## What To Document

Record provider changes or index rebuild requirements when current.

## Known Pitfalls

- Creating a second public search route without redesigning full search.
