## Persistent Architecture Guardrails
- `industriesalon-schoeneweide-register` now has an explicit contract split:
  - summary = lightweight list/bootstrap payload
  - detail = per-place full record
  - export = full filtered dataset
  - atlas = map/story contract
- Keep presentation and data boundaries explicit:
  - page bootstrap consumes summary only
  - REST routes are adapters onto shared data services, not the data layer itself
  - do not reintroduce load-order coupling between render/bootstrap and REST modules
- For lightweight reuse across pages, prefer summary field projection on:
  - `/wp-json/iss-register/v1/places?fields=id,name,lat,lng`
  - do not create ad hoc mini-endpoints for each page need
- Invalid summary field projection must fail closed:
  - bad `fields=` requests now return `400`
  - do not revert to silent widening of payloads
- Treat these checks as required after contract or bootstrap changes:
  - `docker compose run --rm wpcli iss-register contract-check --allow-root`
  - verifies summary, projected summary, detail, export, atlas, and bootstrap summary shapes
- When adding fields, decide ownership first:
  - `summary` only if needed on first load or for lightweight cross-page reuse
  - `detail` if it is place-specific deep content
  - `export` if it is bulk/research oriented
  - `atlas` only if it belongs to map/story behavior
- Do not let summary drift toward detail again. If a page needs richer data, prefer:
  - projected summary fields when possible
  - detail fetch for one place
  - dedicated export/research path for bulk-rich data
  - Register plugin owns data contracts and semantic view-models.
  - Theme owns card layout, section layout, color scheme, and visual hierarchy.
  - Plugin-rendered frontend HTML must stay minimal and semantic.
  - Do not add layout classes such as grid/card-size/rail/homepage variants inside register PHP.
  - Do not query register DB tables directly from templates, blocks, or JS.
  - All consumers must use shared data services or documented REST contracts.
  - REST routes must not contain independent query logic that differs from service logic.
  - Before adding a new field or endpoint, state:
    - which existing contract cannot serve it
    - what would become harder to remove later
    - whether this creates a new public dependency
  - Prefer extending an existing contract only when the field belongs to that contract’s purpose.
  - atlas = map/story behavior contract, not full research payload and not visual layout contract


# Handoff Current

## Status
- `verified checkpoint after historical epoch infrastructure implementation for register_place`

## Date / Window
- Date: `2026-05-11`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD at start of this pass: `27d5575`

## What Was Done This Session
- Implemented historical epoch infrastructure in `plugins/industriesalon-schoeneweide-register` without introducing a new public CPT.
- Added custom-table storage via:
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/epochs.php`
  - table: `wp_iss_register_place_epochs`
  - schema/version option: `iss_register_epoch_schema_version`
  - snapshot meta:
    - `_iss_register_epoch_snapshot_latest`
    - `_iss_register_epoch_snapshots`
- Added epoch service responsibilities:
  - schema install / upgrade
  - CRUD through one service boundary
  - vocabulary definitions for:
    - era slugs from existing `atlas_era` definitions
    - fixed `function_key` list
    - fixed `source_confidence` list
  - validation for:
    - invalid era slugs
    - invalid function keys
    - invalid year ranges
    - multiple `is_current` rows
  - cleanup of invalid media IDs and invalid URLs
  - export/import document builder
  - seed migration helper for historical first-pass rows
- Extended place entity enrichment so `register_place` records now carry:
  - `epochs`
  - `has_epochs`
  - `available_era_slugs`
  - `available_function_keys`
  - `historical_phase_labels`
  - `primary_historical_era_slug`
- Added editor UI on the existing place screen:
  - `Zeitschichten` meta box in `includes/meta-fields.php`
  - transport stored as hidden JSON textarea
  - persistent save still goes through the epoch service on `save_post_register_place`
  - new admin assets:
    - `assets/js/register-place-epochs-admin.js`
    - `assets/css/register-place-epochs-admin.css`
  - compact snapshot notice shown in the meta box
  - save errors are surfaced back in the editor via `_iss_register_epoch_save_error`
- Extended REST/data contracts:
  - summary contract now can expose:
    - `available_era_slugs`
    - `available_function_keys`
    - `primary_historical_era_slug`
    - `has_epochs`
  - detail contract now exposes:
    - `epochs`
    - `historical_phase_labels`
    - the same epoch availability fields
  - `/wp-json/iss-register/v1/meta` now includes:
    - era vocabulary
    - epoch function vocabulary
    - source confidence vocabulary
    - counts by era/function
  - `/wp-json/iss-register/v1/export` now returns structured export with:
    - `places`
    - `epochs`
    - schema metadata
- Extended atlas behavior:
  - atlas contract now includes:
    - `has_epochs`
    - `epoch_summaries`
  - atlas era detection now prefers epoch data when present
  - `/wp-json/iss-register/v1/atlas` now accepts:
    - `era_slug`
    - `function_key`
  - atlas filter narrowing now uses the epoch table first when those filters are active
- Extended single place rendering:
  - `plugins/industriesalon-schoeneweide-register/includes/blocks.php`
  - `iss/register-place-context` `terms` variant now renders chronological phase rows when epochs exist
  - fallback inferred/taxonomy-era behavior remains for places without epochs
- Tightened register app bootstrap behavior:
  - `plugins/industriesalon-schoeneweide-register/includes/register-app/bootstrap.php`
  - bootstrap summary payload now respects configured `limitArea` / `limitStatus` filters instead of always loading the full summary list
- Added tools-page support for tomorrow/editorial operations:
  - export button on the Register tools page
  - guarded seed migration action requiring:
    - backup confirmation
    - export confirmation

## Verification
- PHP syntax checks passed inside `wp_app` for the touched register plugin PHP files, including:
  - `includes/admin-tools.php`
  - `includes/meta-fields.php`
  - `includes/register-data/epochs.php`
- `node --check` passed for:
  - `plugins/industriesalon-schoeneweide-register/assets/js/register-place-epochs-admin.js`
- Live WordPress runtime verification passed for:
  - epoch service load
  - schema option resolution
  - repeated `maybe_install_schema()` call after timestamp-column simplification
  - contract smoke check via `docker compose run --rm wpcli iss-register contract-check --allow-root`
- Verified epoch table indexes exist:
  - `place_post_id`
  - `era_function`
  - `place_sort`
  - `place_current`
  - `chronology`
- Sample live payload checks confirmed:
  - summary contracts contain epoch availability fields
  - detail contracts contain `epochs`
  - atlas contracts contain `has_epochs` and `epoch_summaries`

## Important Notes
- No seed migration was run yet.
  - current places still mostly show `has_epochs = false`
  - this is expected until editors add rows or the migration tool is executed
- The epoch schema originally used `ON UPDATE CURRENT_TIMESTAMP`.
  - this caused noisy repeated `dbDelta` behavior
  - it was simplified to explicit `created_at` / `updated_at` values set in service code
- No theme CSS architecture changes were made in this pass.
  - work stayed inside plugin PHP/admin assets and existing single-place dynamic rendering
- The public single template file itself was not edited in this pass.
  - phase output was added through the existing dynamic block path

## Current Worktree
- Source-file changes from this pass:
  - `plugins/industriesalon-schoeneweide-register/industriesalon-schoeneweide-register.php`
  - `plugins/industriesalon-schoeneweide-register/includes/admin-tools.php`
  - `plugins/industriesalon-schoeneweide-register/includes/blocks.php`
  - `plugins/industriesalon-schoeneweide-register/includes/meta-fields.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-app/bootstrap.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/atlas-contracts.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/cache.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/contracts.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/enrichment.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/entity-repository.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/epochs.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/guardrails.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/query.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/services.php`
  - `plugins/industriesalon-schoeneweide-register/includes/rest/routes.php`
  - `plugins/industriesalon-schoeneweide-register/assets/js/register-place-epochs-admin.js`
  - `plugins/industriesalon-schoeneweide-register/assets/css/register-place-epochs-admin.css`

## Next Recommended Steps
- Commit the epoch infrastructure pass if the surrounding unrelated plugin-tree changes stay intentionally excluded.
- Decide whether to run seed migration now or keep epoch entry manual-first.
- If migration is run:
  - create DB backup first
  - download `/wp-json/iss-register/v1/export` first
  - use the tools page migration action instead of direct SQL
- Then test one real editor workflow on a known place:
  - add multiple phases
  - toggle one current phase
  - verify detail + atlas + single-place output

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
- Then:
  - decide on seed migration timing before broader content editing
  - test one editor-owned place with multiple saved epochs


# Handoff Current

## Status
- `clean checkpoint after schoneweide register/place-page refactor`

## Date / Window
- Date: `2026-05-11`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD at start of this pass: `27d5575`

## What Was Done This Session
- Continued the Schöneweide atlas/register refactor in `industriesalon-schoeneweide-register`:
  - added explicit editor-facing present-day fields:
    - `current_status`
    - `current_use_type`
  - synced those fields into public taxonomies via:
    - `register_current_status`
    - `register_current_use_type`
  - extended entity/repository, contracts, query layer, services and REST payloads so atlas/detail consumers receive:
    - present-state labels
    - present-use labels
    - present combined label
    - explicit era names
- Refactored the public Schöneweide atlas UI and popup:
  - public role filter was replaced by:
    - `Epochen`
    - `Heutige Situation`
    - `Nutzung heute`
  - popup/card output is now structured-first instead of prose-first
  - era handling now respects explicit multi-era membership
- Added a narrow dynamic block for single place context in:
  - `plugins/industriesalon-schoeneweide-register/includes/blocks.php`
  - single place pages now reuse atlas-era/present-state logic instead of old status/role-only terms
- Curated high-value Schöneweide place records directly in WordPress content:
  - `Spreehöfe / ADMOS`
  - `Ostendstraße 1-5 / Behrensbau`
  - `Rathenau-Hallen-Komplex / Urban Banks Berlin`
  - `Bärenquell-Brauerei`
  - `BAE Batterien`
  - `Funkhaus Nalepastraße`
  - `Dokumentationszentrum NS-Zwangsarbeit`
  - `FEZ Berlin`
  - `Behrens-Ufer`
- Then completed a corpus-wide normalization pass for all `register_place` entries:
  - every place now has:
    - `history_short`
    - `history_long`
    - `current_status`
    - `current_use_type`
    - at least one explicit `atlas_era`
  - spot-corrected edge cases after the bulk fill, for example:
    - `1. FC Union Berlin` → `Gemeinwohl / Soziales`
    - `Wilhelminenhofstr. 91` → `Gewerbe / Bueros`
- Improved image suggestion scoring in:
  - `plugins/industriesalon-schoeneweide-register/includes/image-suggestions.php`
  - nearby-but-wrong Wikimedia hits are now penalized more when house number or street mismatch
- Reworked the publication-side Schöneweide reading loop:
  - publication chapter-end now offers a quiet atlas return link
  - publication sticky-nav jump offset was corrected so chapter headings are not hidden
- Refactored `themes/industriesalon/templates/single-register_place.html` from dossier-like stacked panels toward a public-facing editorial page:
  - public hero wording
  - atlas action near the top
  - text-first lead and present-day sections
  - one support rail instead of multiple summary cards
- Fixed a markup regression in `single-register_place.html`:
  - missing container closures caused duplicate content before/after footer
  - featured image was not missing; the page was being misparsed and rendered twice

## Verification
- PHP syntax / runtime checks passed inside `wp_app` for touched register plugin files.
- `node --check` passed for touched frontend JS files during the atlas popup/filter work.
- Live verification completed for:
  - `/schoneweide/`
  - `/schoeneweide/orte/spreehofe-admos/`
  - `/schoeneweide/orte/kaos-93-kunst-und-gewerbehof-genossenschaft-i-gr/`
  - `/publikationen/schoeneweide-eine-ortsgeschichte/`
- Register corpus completeness check now returns:
  - `missing_history_short = 0`
  - `missing_history_long = 0`
  - `missing_status = 0`
  - `missing_type = 0`
  - `missing_eras = 0`
- Verified the featured image pipeline for KAOS 93:
  - image asset returns `200`
  - image tag is rendered in HTML
  - duplicate-before/after-footer behavior was caused by malformed template markup, now fixed

## Important Notes
- Many `register_place` records are now structurally complete, but not all are deeply researched editorial texts.
- The corpus now has a usable explicit metadata layer across atlas and single pages; later work can focus on improving prose quality, not filling missing fields.
- Single place pages currently render only the featured image:
  - `current_images`
  - `archive_images`
  - `document_images`
  are not yet shown as a public gallery
- Some stored image-group items are still marked `pending`, so even if a gallery is added later they should not automatically appear without visibility review.
- The single place template is now safe again, but it remains a Gutenberg HTML file:
  - malformed wrappers can still cause parser drift
  - keep block structure disciplined when editing

## Current Worktree
- Source-file changes from this pass:
  - `plugins/industriesalon-schoeneweide-register/includes/blocks.php`
  - `plugins/industriesalon-schoeneweide-register/includes/image-suggestions.php`
  - `plugins/industriesalon-schoeneweide-register/includes/meta-fields.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/atlas-contracts.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/contracts.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/entity-repository.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/query.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/services.php`
  - `plugins/industriesalon-schoeneweide-register/includes/rest/routes.php`
  - `plugins/industriesalon-schoeneweide-register/includes/taxonomies.php`
  - `plugins/iss-publications/includes/render-publication.php`
  - `themes/industriesalon/assets/css/atlas-app.css`
  - `themes/industriesalon/assets/css/patterns.css`
  - `themes/industriesalon/assets/css/publications.css`
  - `themes/industriesalon/assets/js/schoneweide.js`
  - `themes/industriesalon/templates/page-schoneweide.html`
  - `themes/industriesalon/templates/single-register_place.html`
- A large share of this session’s work also lives in WordPress content/database:
  - curated register-place text/meta updates
  - explicit era/status/use assignments for the full register corpus

## Next Recommended Steps
- Add a public image section for single place pages:
  - keep featured image in hero
  - show reviewed `current_images` / `archive_images` below the narrative
  - respect visibility and do not expose `pending` items by default
- Continue editorial improvement on the weakest machine-like places:
  - planning entries
  - owner-unclear parcels
  - thin current-use records
- Consider dedicated short public summary fields later if `history_short` / `current_use` start carrying too much burden as the public-facing lead text.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
- Then continue with single-place gallery/public-image rendering or targeted editorial cleanup of weak Schöneweide records, not another schema rewrite.
    - `geraete-bauteile`
    - `telekommunikation-fernsehtechnik`
    - `diverses-gebaeude-schaltbilder`
  - hardened the CLI importer so large WF-Museum seed pages fall back from `discover_seed_rows()` to raw object-id discovery when row extraction comes back empty
- Added three new page-owned browser landings in the active theme:
  - `themes/industriesalon/templates/page-geraete-einschuebe-bauteile.html`
  - `themes/industriesalon/templates/page-telekommunikation-sende-und-fernsehtechnik.html`
  - `themes/industriesalon/templates/page-diverses-gebaeude-schaltbilder-etc.html`
- Created the missing page owners in WordPress content:
  - `Geräte, Einschübe, Bauteile` (`ID 21133`)
  - `Telekommunikation, Sende- und Fernsehtechnik` (`ID 21131`)
  - `Diverses, Gebäude, Schaltbilder` (`ID 21132`)
- Ran chunked museum-digital imports with the now-validated safe pattern:
  - use `--selection=remaining`
  - use `--skip-possible-duplicates`
  - stop long runs at visible checkpoints instead of one opaque full-seed pass
- Brought the technical taxonomy to these checkpoint counts:
  - `Geräte / Bauteile`: `1165`
  - `Telekommunikation / Fernsehtechnik`: `268`
  - `Diverses`: `333`
  - `Gebäude / Werkumfeld`: `21`
  - `Schaltbild / Repro`: `52`

## Verification
- Active theme rechecked: `industriesalon`
- Relevant plugin verified during this pass:
  - `iss-wf-import`
- PHP syntax verified inside the WordPress container:
  - `plugins/iss-wf-import/includes/post-type.php`
  - `plugins/iss-wf-import/includes/museum-digital-importer.php`
- Live page checks passed:
  - `/ausstellungen/elektrotechnik-im-wf/`
  - `/ausstellungen/betriebsfotoalben-im-wf/`
  - `/geraete-einschuebe-bauteile/`
  - `/telekommunikation-sende-und-fernsehtechnik/`
  - `/diverses-gebaeude-schaltbilder-etc/`
- Archive redirect path verified:
  - `/archivobjekte/geraete-einschuebe-bauteile/` redirects to the page-owned landing
- The new browser pages are not empty shells anymore:
  - `Geräte / Bauteile` shows real local object cards
  - `Telekommunikation / Fernsehtechnik` shows relays, senders, meters, cameras, TV equipment
  - `Diverses` now visibly surfaces `Schaltbild / Repro` objects such as the imported `Dunkelschaltbild ...` series

## Important Notes
- The current import workflow is workable and should be continued, but in chunks:
  - giant one-shot runs are technically possible but operationally poor
  - the practical pattern is chunked `remaining` imports with visible checkpoints
- `wf-museum.de/home-2/wf-technik/geraete-einschuebe-bauteile/` is the largest of the remaining technical source pages:
  - source-side object-id discovery showed `1881` links
- `wf-museum.de/home-2/wf-technik/telekommunikation-sende-und-fernsehtechnik/` showed `793`
- `wf-museum.de/home-2/wf-technik/diverses-gebaeude-schaltbilder-etc/` showed `334`
- Database size is still reasonable for continuing toward the larger museum-digital corpus:
  - current `archivobjekt` count: `2779`
  - `wp_posts`: about `34.77 MB`
  - `wp_postmeta`: about `52.52 MB`
  - current archive objects average about `33.52` meta rows per object
- The real scaling risk is not object count alone, but bad query design or uncontrolled attachments. The current browser/taxonomy direction is the right one.




## Current Worktree
- Source-file changes from this pass:
  - `plugins/iss-wf-import/includes/post-type.php`
  - `themes/industriesalon/templates/page-geraete-einschuebe-bauteile.html`
  - `themes/industriesalon/templates/page-telekommunikation-sende-und-fernsehtechnik.html`
  - `themes/industriesalon/templates/page-diverses-gebaeude-schaltbilder-etc.html`
- There are also older unrelated pending worktree changes from earlier sessions in theme/plugin files; do not revert them casually.
- Large parts of this pass also live in WordPress content/database, not only in repo files:
  - updated `Elektrotechnik im WF`
  - created `Betriebsfotoalben im WF`
  - created the three technical page owners
  - imported many new `archivobjekt` records

## Next Recommended Steps
- Continue the same chunked import pattern, not a redesign:
  - `Telekommunikation / Fernsehtechnik` next `remaining` slice
  - then the remaining `Geräte / Bauteile` tail
  - then any still-missing `Diverses` residue
- After enough import coverage, refine the taxonomy semantics where it still drifts:
  - some objects in `Diverses` are still broad and may need sharper family/context assignment
  - `Geräte / Bauteile` will likely need later splits inside the field once the corpus stabilizes
- Only after import coverage is materially stronger, consider new curated surfaces such as:
  - `Röhren und Halbleiter im WF`
  - `Arbeitsplätze und Prüfstände`
  - more device/television-focused exhibitions or publications

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
- Then continue chunked `remaining` imports for the WF-Technik pages, using the new profiles and stopping at explicit checkpoints instead of one-shot full-seed runs.

## 2026-05-13 Closeout
- WordPress/database work completed in this session:
  - enriched multiple `fuehrung` entries from local source folders
  - folded TRO chronology material into tour pages
  - rebuilt `Waldfriedhof Oberschöneweide` as an on-demand tour and added `tour_only` cemetery route stations
  - created first real `projekt`, `ausstellung`, and `veranstaltung` entries from legacy `post` content
  - privatized large parts of the legacy/noise layer in `post`
- Resulting public content shape after the cleanup pass:
  - `post`: `30` publish, `58` private
  - `projekt`: `5` publish
  - `ausstellung`: `15` publish
  - `veranstaltung`: `8` publish
- Repo worktree that should now be committed:
  - `plugins/industriesalon-schoeneweide-register/includes/meta-fields.php`
  - `plugins/industriesalon-schoeneweide-register/includes/post-types.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/atlas-contracts.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/entity-repository.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/query.php`
  - `plugins/industriesalon-schoeneweide-register/includes/register-data/services.php`
  - `linux-stack-migration.md`
  - `plugins/media-library-assistant/`
- Backup created:
  - `backups/db_20260513-222652.sql`

## Next Recommended Steps
- Review the remaining public multilingual tour posts and decide whether they stay as language variants or should be folded into a cleaner language model.
- Do a second pass on the remaining public legacy posts with content value:
  - `2953`, `2999`, `3078`, `11093` are the strongest candidates for a clearer `video` or place-story model
  - `4095`, `5120`, `5516`, `5695`, `6082`, `6266`, `7738`, `9357` still need an explicit keep/migrate/archive decision
- Finish the structurally weak remaining tours:
  - `12034 Energie am Fluss – Fahrradtour`
  - `12027 Jüdische Unternehmen im historischen Berlin – Bustour`
  - `12028 Kiez-Tour Wilhelminenhofstraße`
  - `12186 Light & Magic`
  - `12188 Mit dem Fahrrad: Die Wäschewasch-Tour`
- Add a gallery to `11940 Waldfriedhof Oberschöneweide`.
