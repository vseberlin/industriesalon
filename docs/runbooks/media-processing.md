# Media Processing Runbook

## Scope

Use this for local image or media conversion batches.

## Preconditions

- Source and destination folders are known.
- Originals must remain untouched.

## Inspect First

- source file count/types
- destination collisions
- available disk space

## Rules

- Preserve originals.
- Write generated files only to the intended destination folder.
- Re-scan for new files on incremental follow-ups instead of reprocessing the whole corpus.
- Check basename collisions before writing outputs.
- Verify output count and MIME types.

## Known Local Pattern

For large WebP batches, use bounded ImageMagick parallelism rather than unbounded conversion.

## Procedure

Convert only intended files into the destination folder. On follow-ups, rescan for newly added files only.

## Verification

Check output count, MIME types, and representative images.

## Rollback

Remove generated destination files; originals remain intact.

## What To Document

Record only durable batch rules or active follow-up, not full file lists unless needed.

## Known Pitfalls

- Duplicate basenames can overwrite outputs.
- Unbounded conversion can saturate the machine.
