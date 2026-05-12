# Archive Contract

## Status

This document freezes the current archive runtime contract before the archive-core rewrite begins.

It is the implementation companion to `audit-v2.md`.

Use this file to decide:

1. what is canonical today
2. what is only projection or editorial shell
3. what legacy fields must be preserved during migration
4. what must not grow further in `wp_postmeta`

## Scope

This contract covers the current archive runtime in:

1. `plugins/iss-wf-import`
2. `plugins/iss-relations`
3. `plugins/iss-publications`
4. `plugins/industriesalon-schoeneweide-register`
5. `themes/industriesalon` archive templates and page templates that consume archive blocks

## Current Runtime Owners

### `iss-wf-import`

Current archive owner for:

1. `archivbeitrag`
2. `archivsammlung`
3. `archivobjekt`

It currently owns:

1. archive CPT registration
2. archive taxonomies
3. archive postmeta registration
4. archive block rendering
5. archive collection editor UI
6. place suggestion helpers
7. legacy source and museum-digital shaped metadata

### `iss-relations`

Current editorial place-link owner for:

1. `register_place`
2. `archivbeitrag`
3. `fuehrung`
4. `publication`
5. `veranstaltung`
6. `ausstellung`
7. `projekt`
8. `post`
9. `page`

It currently stores place links in:

1. postmeta key `iss_related_places`
2. helper taxonomy `iss_place_ref`

This is a runtime/editorial relation layer, not the future canonical archive relation store.

### `iss-publications`

Editorial/publication owner for `publication`.

Publications may reference archive material but must not become canonical storage for archive facts.

### `industriesalon-schoeneweide-register`

Current spatial/editorial owner for `register_place`.

Important precedent:

1. historical epochs already moved into custom table `wp_iss_register_place_epochs`

Important limitation:

1. current place state is still partly flat postmeta and prose

## Current Live Footprint

Verified on the current WordPress runtime:

1. `archivobjekt`: `3048`
2. `archivsammlung`: `6`
3. `archivbeitrag`: `107`
4. archive-owned attachment flags (`iss_archive_owned_asset` meta rows): `3990`
5. object image meta rows (`iss_archive_object_images`): `3047`
6. object relation meta rows (`iss_related_archive_objects`): `902`
7. collection item meta rows (`iss_archive_collection_items`): `6`

These counts are large enough that the current postmeta model is already a structural risk.

## Stable Public and Editorial Surfaces

These surfaces must remain compatible during migration unless explicitly replaced later.

### Stable archive CPTs

1. `archivbeitrag`
2. `archivsammlung`
3. `archivobjekt`

### Stable archive taxonomies

1. `archiv_quelle`
2. `archiv_kategorie`
3. `archiv_schlagwort`
4. `archiv_themenfeld`
5. `archiv_objektfamilie`
6. `archiv_kontext`
7. `archiv_dekade`

### Stable block names

1. `iss-wf-import/archive-exhibition`
2. `iss-wf-import/archive-collection`
3. `iss-wf-import/archive-album`
4. `iss-wf-import/archive-object-media`
5. `iss-wf-import/archive-object-browser`

### Stable theme consumers

Current templates and pages already depend on archive runtime behavior:

1. `templates/page-archiv.html`
2. `templates/archive-archivobjekt.html`
3. `templates/single-archivobjekt.html`
4. `templates/archive-archivsammlung.html`
5. `templates/single-archivsammlung.html`
6. `templates/page-kinder-in-wf.html`
7. `templates/taxonomy-archiv_kategorie-kinder-im-wf.html`
8. `templates/page-roehren-und-halbleiter.html`
9. `templates/page-anlagen-automaten-arbeitsplaetze.html`
10. `templates/page-geraete-einschuebe-bauteile.html`
11. `templates/page-telekommunikation-sende-und-fernsehtechnik.html`
12. `templates/page-diverses-gebaeude-schaltbilder-etc.html`
13. `templates/page-menschen-im-wf.html`
14. `templates/page-publikationen.html`

### Stable route expectations

Keep these public/archive entry points compatible during migration:

1. archive landing page `/archiv/`
2. object archive and object singles
3. collection archive and collection singles
4. curated archive browser landings built on top of `archive-object-browser`
5. exhibition-style pages using `archive-exhibition`
6. publication pages that query or link `archivbeitrag`

### Current redirect compatibility

The archive plugin normalizes these paths today and that behavior must not break accidentally:

1. `/archivsammlungen/archivobjekte/`
2. `/archivobjekte/roehren-und-halbleiter/`
3. `/archivobjekte/anlagen-automaten-arbeitsplaetze/`
4. `/archivobjekte/geraete-einschuebe-bauteile/`
5. `/archivobjekte/telekommunikation-sende-und-fernsehtechnik/`
6. `/archivobjekte/diverses-gebaeude-schaltbilder-etc/`

## Current Canonical Storage by Domain

### 1. Archive shell posts

Current WP posts still carry both shell and canonical-looking data.

Today:

1. `archivobjekt`, `archivsammlung`, and `archivbeitrag` are public/editorial shells
2. `archivobjekt` and `archivsammlung` also still carry the effective canonical archive structure in postmeta

Future rule:

1. WP posts become projection and editorial shells only

### 2. Common source provenance fields

These meta keys exist on all archive post types and must be preserved during migration:

1. `iss_source_site`
2. `iss_archive_source_kind`
3. `iss_archive_source_external_id`
4. `iss_source_url`
5. `iss_source_slug`
6. `iss_source_date_gmt`
7. `iss_source_modified_gmt`
8. `iss_source_author`
9. `_iss_wf_source_hash`
10. `_iss_wf_last_synced_gmt`

Current classification:

1. canonical provenance data today
2. source-owned in the target model
3. future destination: source, source_record, source_snapshot, import_run tables

### 3. Collection structure

Current canonical collection structure is stored in:

1. `iss_archive_collection_items`
2. `iss_archive_collection_children`
3. `iss_archive_collection_source_ids`

These fields currently carry:

1. membership
2. ordering
3. page labels
4. title and caption overrides
5. child-collection ordering
6. source identifiers and source URLs

Current classification:

1. canonical today
2. mixed source-owned and editor-owned
3. first migration target

Future destination:

1. `wp_iss_archive_collections`
2. `wp_iss_archive_collection_members`
3. later source-record links for collection provenance

### 4. Object core metadata

Current object-level scalar archive data is stored in:

1. `iss_archive_primary_attachment_id`
2. `iss_archive_preview_attachment_id`
3. `iss_archive_object_type`
4. `iss_archive_inventory_number`
5. `iss_archive_rights_holder`
6. `iss_archive_rights_status`
7. `iss_archive_creator`
8. `iss_archive_material`
9. `iss_archive_dimensions`
10. `iss_archive_json_url`
11. `iss_md_object_id`
12. `iss_md_manifest_url`
13. `iss_md_image_url`
14. `iss_md_image_rights`
15. `iss_md_image_owner`
16. `iss_md_metadata_rights_status`
17. `iss_md_metadata_rights_holder`
18. `iss_md_institution_id`
19. `iss_md_institution_name`
20. `iss_archive_year`
21. `iss_archive_decade`

Current classification:

1. scalar descriptive archive data
2. mostly source-owned
3. some values are projection or derived, not true canonical facts

Immediate target split:

1. canonical object data goes to `wp_iss_archive_objects`
2. attachment projection references stay projection-level
3. derived/year facet values get explicit ownership rules

### 5. Object media

Current object media is stored in:

1. `iss_archive_object_images`

Each item currently mixes:

1. `source_id`
2. `source_url`
3. `preview_url`
4. `attachment_id`
5. `preview_attachment_id`
6. `filename`
7. `label`
8. `owner`
9. `creator`
10. `rights`
11. `type`
12. `is_main`

Attachment-level provenance also exists in:

1. `_iss_wf_source_media_url`
2. `iss_archive_owner_object_id`
3. `iss_archive_owned_asset`

Current classification:

1. canonical media provenance is entangled with WP projection fields
2. object image arrays are canonical today
3. attachment records are derivative/public delivery records

Future destination:

1. `wp_iss_archive_media` in v1
2. possible later split into media asset/file/variant/link tables

### 6. Object relationship arrays

Current object relationship arrays are stored in:

1. `iss_archive_object_tags`
2. `iss_archive_object_collections`
3. `iss_archive_object_series`
4. `iss_archive_object_events`
5. `iss_archive_object_places`
6. `iss_archive_object_people`
7. `iss_related_archive_objects`

Current classification:

1. canonical-looking structured links today
2. mixed source-owned and editor-enhanced
3. currently non-normalized arrays

Future destination:

1. canonical relation rows in `wp_iss_archive_relations`
2. collection membership removed from generic arrays where ordered membership needs a dedicated table

### 7. Archive taxonomies

Current archive taxonomies play two different roles:

1. source and editorial discovery on archive content
2. browser facets and grouping

Current classification by taxonomy:

1. `archiv_quelle`: source/discovery mirror, not ideal long-term canonical source store
2. `archiv_kategorie`: editorial taxonomy for `archivbeitrag`
3. `archiv_schlagwort`: editorial taxonomy for `archivbeitrag`
4. `archiv_themenfeld`: editorial or normalized object classification
5. `archiv_objektfamilie`: editorial or normalized object classification
6. `archiv_kontext`: editorial or normalized object classification
7. `archiv_dekade`: derived or normalized time bucket

Future rule:

1. taxonomies may remain for WordPress discovery
2. taxonomies should not remain the only canonical classification store for archive objects

### 8. Place suggestion helpers

Current place-suggestion meta:

1. `iss_wf_place_suggestions`
2. `_iss_wf_place_suggested_at_gmt`

Current classification:

1. derived admin helper data
2. not canonical archive data

Future rule:

1. preserve until tooling is replaced
2. do not model as canonical archive facts

### 9. Place relations via `iss-relations`

Current place-link meta and taxonomy:

1. `iss_related_places`
2. `iss_place_ref`

Current classification:

1. editorial/runtime linking layer
2. not the future canonical archive relation store
3. still required for current frontend and editor UI

Future rule:

1. keep during migration
2. eventually bridge WP editorial objects to canonical archive IDs and canonical place relations

## Ownership Rules

These are the required ownership classes for migration planning.

### `source_owned`

Imported or source-derived values that may be refreshed by reimport policy.

Current examples:

1. `iss_source_site`
2. `iss_archive_source_kind`
3. `iss_archive_source_external_id`
4. `iss_source_url`
5. `iss_source_date_gmt`
6. `iss_source_modified_gmt`
7. `_iss_wf_source_hash`
8. `iss_archive_inventory_number`
9. `iss_archive_creator`
10. `iss_archive_material`
11. `iss_archive_dimensions`
12. `iss_md_*` metadata
13. source URLs inside collection and media arrays

### `editor_owned`

Values that belong to curation or public interpretation and must not be overwritten automatically.

Current examples:

1. `archivbeitrag` title, editor content, excerpt, featured image
2. collection captions and page labels inside `iss_archive_collection_items`
3. collection sequencing decisions
4. theme/template placements using archive blocks
5. publication curation around archive content
6. place links chosen in `iss_related_places`
7. taxonomy assignments where they represent editorial classification rather than source copy

### `derived`

Values generated from source or canonical data.

Current examples:

1. `iss_archive_decade`
2. archive browser facet buckets
3. preview attachment references
4. archive-owned attachment flags
5. place suggestions

### `conflicted`

Values that will need review workflows once canonical tables exist.

Expected examples:

1. conflicting creator labels
2. conflicting rights status
3. mismatched source IDs
4. competing place assignments
5. collection membership mismatches between source and local curation

## Projection Boundaries

### WordPress keeps ownership of

1. public slugs and routing
2. Gutenberg content and editorial prose
3. featured images used as public/editorial presentation
4. publication status
5. menus, templates, and page composition
6. exhibitions, publications, and tours as editorial objects

### Canonical archive core will own

1. object identity
2. collection membership and ordering
3. archive object scalar metadata
4. media provenance and source references
5. relation rows
6. source records and snapshots
7. import run history

### Transitional rule

During migration:

1. `archivobjekt` and `archivsammlung` remain public shell posts
2. legacy meta remains readable
3. canonical writes move to new tables one domain at a time

## Frozen Legacy Fields

Do not add new archive structure to these legacy stores.

### Frozen legacy postmeta keys

1. `iss_archive_collection_items`
2. `iss_archive_collection_children`
3. `iss_archive_collection_source_ids`
4. `iss_archive_object_images`
5. `iss_archive_object_tags`
6. `iss_archive_object_collections`
7. `iss_archive_object_series`
8. `iss_archive_object_events`
9. `iss_archive_object_places`
10. `iss_archive_object_people`
11. `iss_related_archive_objects`

### Frozen helper postmeta keys

1. `iss_archive_primary_attachment_id`
2. `iss_archive_preview_attachment_id`
3. `iss_archive_owned_asset`
4. `iss_archive_owner_object_id`
5. `_iss_wf_source_media_url`
6. `iss_wf_place_suggestions`
7. `_iss_wf_place_suggested_at_gmt`
8. `iss_related_places`

### Frozen taxonomy semantics

No new canonical meaning should be added to:

1. `archiv_quelle`
2. `archiv_themenfeld`
3. `archiv_objektfamilie`
4. `archiv_kontext`
5. `archiv_dekade`

Use them as compatibility mirrors or editorial discovery terms only while canonical tables are introduced.

## Compatibility Rules

1. Keep plugin directory `iss-wf-import`
2. Keep PHP prefix `iss_wf_import`
3. Keep CPT names `archivbeitrag`, `archivsammlung`, `archivobjekt`
4. Keep current block names
5. Keep current theme templates working without requiring immediate rewrites
6. Do not remove legacy meta until dual-read verification passes
7. New canonical reads may use table-first, legacy-fallback behavior
8. Once a domain is migrated, new canonical writes must stop going to blob meta
9. `iss-relations` remains active until a replacement editorial-to-canonical bridge exists
10. `register_place` and `publication` continue as editorial/public shells, not canonical archive stores

## Phase 1 Migration Gate

Phase 1 is accepted only if it does all of the following:

1. moves collection membership out of `iss_archive_collection_items`
2. preserves order, page labels, and caption/title overrides
3. preserves public collection rendering
4. does not break `archive-collection` or `archive-album` blocks
5. does not require route changes
6. stops new canonical collection writes to collection blob meta

## Immediate Follow-Up Documents

The next documents must be based on this contract:

1. `archive-phase-1-schema.md`
2. `archive-import-contract.md`
3. `archive-vocabulary.md`

## Immediate Working Rule

Until the first canonical tables exist:

1. no new archive structure goes into `wp_postmeta`
2. no new frontend dependency should be added to retired remote-import behavior
3. all archive rewrite work should preserve public continuity first and move one domain at a time

