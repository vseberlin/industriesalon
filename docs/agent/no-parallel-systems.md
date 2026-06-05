# No Parallel Systems Checklist

Before adding a new block, helper, table, CSS primitive, service, route, or runbook, check existing ownership first.

## Check First

- Existing Gutenberg block or pattern
- Existing theme class, token, layout primitive, card, or pattern CSS
- Existing plugin service or render helper
- Existing custom table or query service
- Existing REST endpoint, CLI command, or admin tool
- Existing runbook or skill wrapper
- Existing data artifact pattern under `ops/`

## If A New System Is Still Needed

Record the reason in `CHANGELOG.md`:

- what existing mechanism was checked
- why it was insufficient
- which plugin/theme layer owns the new system
- how it degrades
- how it is verified
