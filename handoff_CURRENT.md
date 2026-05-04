# Handoff Current

## Status
- `committed`

## Date / Window
- Date: `2026-05-04`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD before final commit: `e39b773`

## What Was Done This Session
- Continued the Schöneweide Atlas/public-layer work in the active theme and register plugin:
  - added a dedicated Atlas REST payload in `industriesalon-schoeneweide-register`
  - switched `/schoneweide/` to that lighter Atlas endpoint instead of the full register payload
  - moved Atlas-era/story summarization into PHP so the theme JS no longer has to derive as much from long research fields
  - stopped enqueuing unrelated `ueber-uns` / `iss-flex-split` styles on the Atlas page
  - switched the Atlas hero image source to the lighter `atlas-header-1536x768.png`
- Removed Touchtable from the active runtime/editor scene while preserving local source snapshots:
  - plugin bootstrap no longer loads the Touchtable pull/review modules
  - Atlas story/timeline payloads now resolve media locally only
  - cached Atlas story payloads are enriched from current local attachments on read
  - verified the named value pages are preserved locally as source snapshots
- Localized media for the named Touchtable value pages and verified local timeline image coverage:
  - `transformatorenwerk-oberschoeneweide`
  - `kabelwerk-oberspree`
  - `stromnetz-berlin`
  - `htw-berlin`
  - `elektro-innung-berlin`
  - `stephanus-stiftung-betriebsstaette-wilhelminenhof`
  - `getraenke-lydicke`
  - `first-sensor-ag`
  - `sven-thomsen`
- Added the first editor-facing external image sourcing flow for `register_place`:
  - new `Bildvorschläge` metabox in `industriesalon-schoeneweide-register`
  - Wikimedia Commons search driven by local title, address, and coordinates
  - candidate storage is local on the post
  - selected images import into existing image groups:
    - `Archivbilder`
    - `Aktuelle Bilder`
    - `Dokumentbilder`
  - imports go into the Media Library and default to image-group visibility `pending`

## Verification
- Active theme confirmed: `industriesalon`
- Active plugin confirmed: `industriesalon-schoeneweide-register`
- Runtime checks confirmed Touchtable runtime hooks are off:
  - `pull-off`
  - `review-off`
- Named value-page media/timeline audit after localization:
  - `transformatorenwerk-oberschoeneweide`: `events=4`, `local_event_images=4`, `remote_event_images=0`
  - `kabelwerk-oberspree`: `events=2`, `local_event_images=2`, `remote_event_images=0`
  - the remaining audited pages keep local text/media where present and currently have no timeline events
- PHP lint passed in the running `wp_app` container for:
  - `plugins/industriesalon-schoeneweide-register/includes/image-suggestions.php`
  - `plugins/industriesalon-schoeneweide-register/industriesalon-schoeneweide-register.php`
- JS syntax check passed:
  - `plugins/industriesalon-schoeneweide-register/assets/js/register-image-suggestions-admin.js`
- Live WP-CLI verification for Wikimedia sourcing:
  - `HTW Campus` returned `18` image candidates from Wikimedia Commons
  - import workflow was smoke-tested end to end on a temporary draft `register_place`
  - imported candidate saved into `current_images` with `visibility=pending`

## Important Notes
- Touchtable source snapshots still exist locally as archive/provenance via `register_source_item`; they are no longer part of the active runtime/editor flow.
- The old Touchtable source/admin/review files still exist on disk, but they are no longer loaded by the plugin bootstrap.
- The new image-sourcing flow is Wikimedia-only in this slice. Flickr and Google preview were intentionally not added.
- Google should stay preview-only if added later; it is not a clean local-import source.

## Next Recommended Steps
- Improve the `register_place` editor structure for non-technical editors by splitting raw register fields into smaller editorial groups instead of one large meta box.
- Add field-level promote actions from local source snapshots into curated `register_place` content if historical Touchtable material should become first-class local dossier content.
- Extend image sourcing only after the Wikimedia workflow settles:
  - Flickr with explicit license filtering
  - optional Google preview only, not local import

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
