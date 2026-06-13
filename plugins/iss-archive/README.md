# ISS Archive

This plugin owns the local archive content model used by the site:

- `archivbeitrag`
- `archivsammlung`
- `archivobjekt`
- Archivset saved selections in custom tables

The plugin directory is `iss-archive`. PHP prefixes, stored option names, block
names, and legacy handles still use `iss_wf_import` / `iss-wf-import` where
renaming them would require a stored-content migration.

Remote museum-digital ingestion remains a WP-CLI operation. Editors use the
Archive Picker to select already-local archive objects.

See [docs/archive-boundary.md](./docs/archive-boundary.md).
