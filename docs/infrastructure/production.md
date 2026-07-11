# Production

Production changes must be minimal, reversible, and evidence-based.

## Rules

- Do not apply SQL, uploads, plugin, or config changes without backup verification.
- Transfer data through explicit artifacts under `ops/sql`, `ops/migrations`,
  or `ops/uploads` when possible.
- Do not expose secrets or environment files through Git.
- Confirm plugin/schema dependencies before importing data.
- Keep convenience services optional; core website rendering must degrade gracefully.
