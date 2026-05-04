# Handoff Current

## Status
- `committed`

## Date / Window
- Date: `2026-05-04`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD before final commit: `41f9026`

## What Was Done This Session
- Continued the `industriesalon-schoeneweide-register` integration foundation so Schöneweide places are real public content on the main site:
  - made `register_place` public under `/schoeneweide/orte/{slug}/`
  - added real taxonomies:
    - `register_area`
    - `register_status`
    - `register_role`
  - backfilled existing `area/status/role` meta into those taxonomies
  - added theme-owned public templates:
    - `themes/industriesalon/templates/page-schoneweide.html`
    - `themes/industriesalon/templates/single-register_place.html`
- Added public-field sync for `register_place`:
  - fills `post_excerpt` conservatively from existing register data when blank
  - assigns featured image only from `public` image-group items when available
  - current dataset still has no public images, so the single template was adjusted to stay text-first without empty hero-media dependence
- Saved the revised Schöneweide/Touchtable specs into the plugin docs folder:
  - `plugins/industriesalon-schoeneweide-register/docs/integration-proposal.md`
  - `plugins/industriesalon-schoeneweide-register/docs/integration-spec.md`
- Added one-way Touchtable source pull into the plugin:
  - local non-public source snapshot CPT: `register_source_item`
  - source pull admin page: `Tools -> Touchtable Pull`
  - pulls public Touchtable pages and media via WordPress REST
  - extracts map hotspots and coordinates from rendered map pages plus Elementor CSS
- Added the first real review/match workflow in the main site admin:
  - `Schöneweide Register -> Touchtable Review`
  - separates:
    - auto-match candidate
    - approved linked `register_place`
    - review status
  - supports actions:
    - accept auto-match
    - link existing place
    - create new draft `register_place` from a source item
    - ignore
    - reset
  - adds a `Touchtable Quelle` metabox on `register_place`
- Improved review usability for mixed-quality Touchtable detail pages:
  - source snapshots now derive normalized text, preview text, text lengths, and hotspot counts
  - review table now shows an `Inhalt` column with preview/metrics
  - text-rich detail pages sort to the top, zero-text pages fall to the bottom

## Verification
- Active theme confirmed: `industriesalon`
- Runtime function checks confirmed the new Touchtable workflow loads:
  - review page renderer
  - review query helper
  - source metabox renderer
- Real Touchtable pull completed successfully after the workflow refactor:
  - `32` page snapshots
  - `152` media snapshots
  - `184` total local source snapshots
- Existing snapshots were backfilled into the new workflow model:
  - all source items now have candidate/link/review-status metadata
- Review queue verification:
  - `27` open `detail_page` review items
  - text-rich detail pages like `Transformatorenwerk Oberschöneweide` surface at the top
  - zero-text items like `Behrens-Ufer` sort to the bottom
- Workflow helper verification through WP-CLI:
  - link candidate -> `linked`
  - reset -> back to `new`
  - create draft `register_place` from snapshot works; temporary verification draft was deleted again

## Important Notes
- The Touchtable review workflow still does not promote content into live public fields automatically after linking. It is review/match first, not full editorial promotion yet.
- Some Touchtable detail pages are effectively empty; others are large Elementor timelines. The current preview logic normalizes text enough for review, but long Elementor-derived content is still flattened and wants cleaner extraction later.
- No browser-level admin UI pass was done yet. The workflow was verified through WordPress runtime checks and WP-CLI helper calls.

## Next Recommended Steps
- Add deliberate promote/sync actions from linked source snapshots into curated `register_place` fields instead of only linking source items.
- Improve Touchtable extraction for Elementor-heavy narrative pages so previews and future promoted content are cleaner than flattened timeline text.
- Add reviewed media import/attachment flow with rights-safe handling before public image use.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
