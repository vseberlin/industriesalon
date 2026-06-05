# Template Authority Runbook

Use this when file edits do not match visible block-theme output or when making disk templates authoritative.

## Checks

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
