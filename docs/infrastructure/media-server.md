# Media Server

Media workflows must preserve originals and keep durable files in explicit storage.

## Rules

- Do not store important media only inside containers.
- Preserve source originals during conversion or batch processing.
- Use destination-only output folders for local conversion batches.
- Check basename collisions before generating derivative files.
- For large local WebP batches on this host, bounded ImageMagick parallelism has been the reliable path.

## Related Paths

- `Theme_assets/`: local asset working area.
- `ops/uploads/`: tracked transfer manifests and archives when upload artifacts are part of deployment.
- `wp/wp-content/uploads`: runtime uploads mount; do not treat it as a casual source tree.
