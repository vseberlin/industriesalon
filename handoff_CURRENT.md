# Handoff Current

## Status
- `committed`

## Date / Window
- Date: `2026-05-06`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD at start of this pass: `4e13567`

## What Was Done This Session
- Added the first explicit Atlas editorial model inside `plugins/industriesalon-schoeneweide-register` without breaking the current place payload:
  - new file `includes/atlas-model.php`
  - taxonomy `atlas_era`
  - CPT `atlas_story`
  - activation seeding for the controlled era vocabulary
- Extended the Atlas REST layer in `plugins/industriesalon-schoeneweide-register/includes/rest-controller.php`:
  - explicit editorial eras now override heuristic inference when assigned
  - legacy era fields stay available on `/wp-json/iss-register/v1/atlas`
  - additive endpoint `/wp-json/iss-register/v1/atlas-context` now returns era and story context
- Updated the Schöneweide Atlas frontend in `themes/industriesalon` to consume the new context:
  - `themes/industriesalon/assets/js/schoneweide.js`
  - `themes/industriesalon/assets/css/atlas-app.css`
  - `themes/industriesalon/templates/page-schoneweide.html`
  - `themes/industriesalon/functions.php`
  - Atlas UI now exposes explicit era filters and story-aware fallback behavior while keeping current place/map behavior intact
- Documented Atlas phase boundaries in:
  - `plugins/industriesalon-schoeneweide-register/docs/atlas-phase-1.md`
  - `plugins/industriesalon-schoeneweide-register/docs/atlas-phase-2.md`
  - updated plugin `readme.md`
- Refactored `plugins/iss-wf-import` into a local archive owner instead of an importer:
  - plugin header now presents `ISS Archive`
  - removed obsolete sync/import modules:
    - `includes/cli.php`
    - `includes/importer.php`
    - `includes/md-importer.php`
    - `includes/wf-collections.php`
  - kept the stable archive model bootstrap for:
    - `archivbeitrag`
    - `archivsammlung`
    - `archivobjekt`
- Cleaned the archive editor interface in `plugins/iss-wf-import`:
  - centralized post-type and taxonomy label generation in `includes/post-type.php`
  - root archive menu now reads `Archiv`
  - added clearer CPT descriptions for editors
  - rewrote place-suggestion/admin review copy in `includes/admin.php` to use clearer terms like `Orte prüfen`, `Alle Ortsbezüge`, and `Ausgewählte Orte verknüpfen`

## Verification
- Active theme rechecked with WP-CLI: `industriesalon`
- Active relevant plugins rechecked with WP-CLI:
  - `industriesalon-schoeneweide-register`
  - `iss-wf-import`
- `themes/industriesalon/assets/js/schoneweide.js` passed `node --check`
- Atlas runtime registration rechecked with WP-CLI:
  - `atlas_story` CPT present
  - `atlas_era` taxonomy present
  - `/wp-json/iss-register/v1/atlas-context` route present
- Archive runtime registration rechecked with WP-CLI:
  - `archivbeitrag | Archivbeiträge | Archiv`
  - `archivsammlung | Archivsammlungen | Sammlungen`
  - `archivobjekt | Archivobjekte | Objekte`
  - CPT descriptions present for all three archive post types
- Direct WP-CLI include checks succeeded for:
  - `plugins/iss-wf-import/includes/post-type.php`
  - `plugins/iss-wf-import/includes/admin.php`
- Browser QA for the Atlas phase-2 UI was confirmed during the session

## Important Notes
- The Atlas migration is still additive:
  - explicit editorial eras are available now
  - legacy inferred era buckets remain in the main `/atlas` payload for compatibility
- `iss-wf-import` still keeps its existing directory name, text domain, function prefixes, block namespaces, slugs, and meta keys for compatibility
- The archive plugin is now archive-authoritative only:
  - no remaining import/sync runtime is loaded
  - upstream WF retirement no longer matters operationally for this site

## Current Worktree
- Clean after committing this snapshot

## Next Recommended Steps
- Create and assign the first real `atlas_story` entries for the main eras so the new story layer stops falling back to place-only cards
- Assign `atlas_era` terms to the highest-value `register_place` records to reduce heuristic fallback
- Decide whether the remaining internal `iss_wf_import_*` PHP prefixes and block namespaces should stay permanently as compatibility contracts or be deprecated later with a migration plan

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
