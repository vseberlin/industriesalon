# Search Runbook

Use this for search or index work.

## Rules

- Start with `iss-graph` search provider and index ownership.
- Keep SQL search as the graceful fallback path.
- Treat external search services as optional convenience layers.
- Rebuild or inspect projection data before changing frontend search UI.
- Do not create a parallel search route until the full search experience is deliberately redesigned.
