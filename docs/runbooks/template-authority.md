# Template Authority Runbook

## Scope

Use this when file edits do not match visible block-theme output or when making disk templates authoritative.

## Preconditions

- Target template slug or route is known.

## Inspect First

Check template authority:

```bash
docker compose run --rm wpcli post list --post_type=wp_template --allow-root
docker compose run --rm wpcli post list --post_type=wp_template_part --allow-root
docker compose run --rm wpcli eval 'var_dump(get_block_template("industriesalon//template-slug", "wp_template")->source ?? null);' --allow-root
```

## Rules

- If `source=db`, treat the DB content as the live source.
- Preserve useful DB content before deleting overrides.
- Prefer file-backed authority for durable templates once editor changes have been synced back.
- Verify rendered output after authority changes.

## Procedure

Sync useful DB edits back to disk before deleting overrides. Delete only the relevant override rows.

## Verification

Re-check template source and frontend route output.

## Rollback

Restore the preserved DB template content or revert the disk template commit.

## What To Document

Record authority changes in `CHANGELOG.md`; note current risk in handoff only if unresolved.

## Known Pitfalls

- Editing disk while a DB override still owns the route.
