# KAOS 93 browser preview

The JPEG is a display derivative of the existing public TIFF. The original TIFF
and its attribution are preserved. `manifest.json` records both checksums.

Deploy alongside `ops/migrations/2026-10-14-place-image-preview.php`.
Keep this directory available next to the migration for guarded automatic copy,
or extract the paired archive at the target uploads directory before applying.
Run the migration with `dry-run`, `apply`, then `verify`.

Rollback uses the metadata retained in option
`iss_backup_20261014_place_image_preview` through
`wp_update_attachment_metadata()`. The TIFF is never overwritten or deleted.

Archive SHA-256: 03b4b239c91aa74f8822575bf66a3f948a81d61d7a4ab046aa5c9490e3c41daf
