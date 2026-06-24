# Veranstaltung JSON Transfer Instructions

Date: 2026-06-24

## Scope

This transfer moves the local Veranstaltung structured-content checkpoint:

- all 25 current Veranstaltungen have valid `_iss_content_json`
- Veranstaltung authoring uses the `Struktur` editor surface, not Gutenberg
- legacy presentation meta `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme` is removed

## Artifacts

- `ops/sql/2026-06-23-veranstaltungen-entity-migration.sql`
  - Required if the target does not already have the curated `_iss_entity_key` and normalized Veranstaltung facts.
- `ops/sql/2026-06-24-veranstaltungen-content-json-full.sql`
  - Full `_iss_content_json` state for all 25 current Veranstaltung posts.
  - Supersedes the three per-post JSON artifacts for full-state transfer.
- `ops/sql/2026-06-24-veranstaltung-24988-material-gallery-split.sql`
  - Applies the later split that moves the one existing material image into a `galerie` section.
- `ops/sql/2026-06-24-veranstaltungen-remove-legacy-presentation-meta.sql`
  - Deletes inert legacy `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme` rows.
- Optional narrow review artifacts:
  - `ops/sql/2026-06-23-veranstaltung-24988-content-json.sql`
  - `ops/sql/2026-06-24-veranstaltung-13349-content-json.sql`
  - `ops/sql/2026-06-24-veranstaltung-25808-content-json.sql`

No uploads artifact is required for this checkpoint. The JSON stores existing attachment IDs and dynamic Steuerung refs; it does not introduce new upload files.

## Apply Order

1. Create a target DB backup.
2. Deploy code first.
3. If needed, apply `ops/sql/2026-06-23-veranstaltungen-entity-migration.sql`.
4. Apply `ops/sql/2026-06-24-veranstaltungen-content-json-full.sql`.
5. Apply `ops/sql/2026-06-24-veranstaltung-24988-material-gallery-split.sql`.
6. Apply `ops/sql/2026-06-24-veranstaltungen-remove-legacy-presentation-meta.sql`.
7. Refresh projections:

```bash
wp iss-occurrences sync
wp iss-graph sync-content
wp iss-graph sync-search
```

8. Verify:

```bash
wp iss-content veranstaltungen-dry-run --format=json
wp iss-content veranstaltungen-content-audit
wp iss-content veranstaltungen-repository-check
wp iss-content veranstaltungen-query-audit
wp iss-content tours-drift-check
wp db query "SELECT COUNT(*) AS legacy_rows FROM wp_postmeta WHERE meta_key IN ('_iss_event_layout','_iss_event_format','_iss_event_scheme');"
```

Expected target state after import:

- `veranstaltungen-dry-run`: `25 safe`, `25 converted`, `0 review`, `0 blocked`
- `veranstaltungen-content-audit`: `stored=25 valid=25 invalid=0`
- legacy presentation meta count: `0`

## Browser Smoke

Check representative routes after import:

- `/veranstaltungen/neue-nachbarschaften-im-globalen-vergleich/`
- `/veranstaltungen/zukunft-im-gesprach/`
- `/veranstaltungen/fete-de-la-musique-berlin-2026/`

Expected:

- structured renderer is present
- no `iss-event-layout-*` or `iss-event-scheme-*` body classes
- no horizontal overflow on desktop/mobile
- Veranstaltung edit screen has `Struktur` first and no Gutenberg/default content editor
