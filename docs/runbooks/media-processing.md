# Media Processing Runbook

Use this for local image or media conversion batches.

## Rules

- Preserve originals.
- Write generated files only to the intended destination folder.
- Re-scan for new files on incremental follow-ups instead of reprocessing the whole corpus.
- Check basename collisions before writing outputs.
- Verify output count and MIME types.

## Known Local Pattern

For large WebP batches, use bounded ImageMagick parallelism rather than unbounded conversion.
