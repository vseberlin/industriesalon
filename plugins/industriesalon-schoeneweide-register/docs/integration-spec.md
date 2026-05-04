# Revised Technical Spec: Schoneweide Register + Touchtable Integration

## Status

This document is the detailed implementation spec for the register and Touchtable integration.

Core decision:

- keep `industriesalon-schoeneweide-register` as the data, sync, and research-interface plugin
- move as much public presentation as possible into `themes/industriesalon`
- avoid growing a second plugin-owned frontend system for cards, page sections, and dossier layouts

## Verdict

The project is feasible and sensible if the boundary is tightened.

The scalable version is:

- plugin owns content model, sync, import review, research app, REST/query layer
- theme owns landing pages, single templates, cards, narrative sections, and site styling

## Goals

- integrate Touchtable content into the main Industriesalon site cleanly
- keep Schoneweide places in one local structured source
- preserve the existing full register as the research interface
- create public place pages and reusable page integrations
- prepare for future push-back or bidirectional sync without locking presentation into the plugin

## Non-Goals

- do not rebuild the Touchtable UI inside the main site
- do not create a second plugin-owned card/design system
- do not depend on runtime external API calls for public rendering
- do not auto-publish imported long texts without editorial review

## Architectural Principle

Use WordPress native content ownership wherever possible.

That means:

- title in post title
- public lead text in excerpt
- long reviewed public story in post content
- lead image as featured image
- public facets in taxonomies where useful
- structured supplemental data in post meta

The plugin should enrich and normalize content.
The theme should assemble pages.

## Source of Truth

Primary source of truth:

- CPT: `register_place`

This CPT name is already live.

Do not rename it.

Required continuation:

- keep CPT-backed local storage
- keep importer into local WP data
- keep transient/query caching
- keep JSON fallback only as import/bootstrap safety, not as the preferred runtime source

## Public Routing

### Main landing

Use page-owned route:

```text
/schoeneweide/
```

### Single place pages

Make places publicly queryable:

```text
/schoeneweide/orte/{slug}/
```

Requirements:

- `register_place` becomes public
- no CPT archive on the root slug
- no route collision with page-owned editorial landings

Recommended CPT behavior:

- `public => true`
- `has_archive => false`
- custom rewrite under `schoeneweide/orte`

## Ownership Matrix

### Plugin owns

- `register_place` data model
- import from local JSON
- Touchtable import/enrichment
- matching and sync status
- editor-side review helpers
- repository/query layer
- REST endpoints
- full research interface block
- interactive map/hotspot behavior where theme-only rendering is impractical

### Theme owns

- `/schoeneweide/` landing page
- `single-register_place.html` or equivalent theme-owned single template
- teaser cards
- featured strips
- narrative sections
- dossier layout
- integration into `/archiv/`, `/ausstellungen/`, `/fuehrungen/`, `/projekte/`
- visual system and page rhythm

## Content Model

## Native fields

Use native WP fields for public-first editorial output:

- `post_title`
- `post_excerpt`
- `post_content`
- `featured_image`

## Taxonomies

Move public query facets out of free-text-only meta where practical.

Recommended taxonomies:

- `register_area`
- `register_status`
- `register_role`
- `register_theme`

## Structured meta

Keep structured meta for data that is not native WP content.

Recommended meta groups:

- address and coordinates
- owner/operator/developer/tenant
- previous/current use
- investment/size/jobs
- source summary and source links
- reviewed public gallery references
- timeline events
- Touchtable sync metadata
- quality state

## Required new fields

Public/editorial:

- `public_ready`
- `public_summary`
- `aliases`
- `content_quality_status`

Media:

- `gallery_image_ids`

Timeline:

- `timeline_events`

Sync/provenance:

- `source_system`
- `source_hash`
- `last_synced_at`
- `sync_state`
- `manual_override_fields`
- `touchtable_source_url`
- `touchtable_import_status`

## Timeline event structure

```json
{
  "year": "1928",
  "title": "Werkserweiterung",
  "text": "Short public-facing explanation.",
  "source": "Optional source note",
  "image_id": 123
}
```

## Hotspot data model

Do not store one global `hotspot_x` / `hotspot_y` pair on the place.

Use a structured hotspot item list instead:

```json
[
  {
    "image_context": "spree-corridor-1930",
    "x": 42.5,
    "y": 63.2,
    "label": "Kabelwerk Oberspree",
    "mode": "preview"
  }
]
```

## Touchtable Integration

Touchtable becomes an enrichment source, not a second frontend.

Kiosk/Touchtable remains an explicit consumer of the same structured place domain, even if its presentation stays separate from the main website theme.

## Kiosk / Touchtable Sync Decision

This decision should be documented early, but it no longer blocks Phase 1 route and template work.

Required choice:

- poll-based import
- webhook/push-based import

Current recommendation:

- start with poll/manual-trigger import unless the kiosk system already has a reliable push contract
- only choose webhook push if delivery, retry, and authentication are clearly defined

Implementation requirement:

- one documented REST ingestion endpoint for kiosk/Touchtable sync
- authentication and payload contract must be documented before rollout
- reserve an implementation slot for kiosk sync foundation before full sync work

## Import behavior

Use manual review workflow.

Import stages:

1. fetch/parse Touchtable source data
2. match candidate to existing `register_place`
3. store imported payload in review fields
4. show diff/review state in admin
5. editor approves selected fields into public/native fields

## Matching logic

Matching priority:

1. explicit source ID mapping
2. stored external reference
3. `register_id`
4. alias/title match
5. address match
6. coordinate proximity

Never silently auto-merge based only on fuzzy similarity.

## Push-back readiness

Future push-back sync is foreseen, but should not shape public rendering.

Prepare for it by keeping:

- source IDs
- per-field provenance
- sync status
- override tracking

Do not attempt bidirectional write-back in phase 1.

## Query Layer

Add a repository layer inside the plugin.

Suggested structure:

```text
includes/data/repository.php
includes/data/filters.php
includes/data/presenters.php
includes/data/sync-state.php
```

## Repository responsibilities

- fetch one place
- fetch multiple places
- filter by taxonomy/meta
- support selected IDs
- support current post context
- return public-safe image/timeline payloads
- centralize caching
- expose one consistent data contract to all plugin blocks
- ensure all blocks use the same REST endpoint family and repository layer
- do not allow parallel ad hoc data fetch paths per block

## Presenter responsibilities

Keep them lightweight.

Presenters may normalize data for:

- map markers
- fact rows
- timeline items
- hotspot payload

Do not make presenters return finished public card HTML.

## Public Rendering Strategy

Default rule:

- theme templates and patterns render public content
- plugin only renders where interactivity or structured field projection truly requires it

## Theme-owned public composition

### `/schoeneweide/`

Build in theme templates/patterns using:

- editorial intro
- featured places section
- simple map preview
- thematic place sections
- Touchtable-derived visual exploration section
- entry to full research interface

### Single place page

Theme-owned single template:

```text
Hero
Lead text
Fact panel
Current + archive images
Long story
Timeline
Location/map
Related places
Sources
```

### Other site integrations

- `/archiv/`: research-oriented teaser and selected timelines
- `/ausstellungen/`: object-place relation and contextual place links
- `/fuehrungen/`: route/place context and mini map
- `/projekte/`: teaser-only editorial integration

## Plugin Blocks

Keep the block surface small.

## Keep

- `iss-register/register-app`

## Add only where needed

- `iss-register/map`
- `iss-register/hotspots`
- optionally `iss-register/place-facts`
- optionally `iss-register/place-timeline`

## Do not add as plugin-rendered presentation blocks

- `iss-register/place-cards`
- `iss-register/place-featured`
- `iss-register/related-places`

If card grids are needed, use:

- Query Loop on `register_place`
- theme patterns
- small field projection helpers only where native fields are insufficient

## Deprecation Note For Current Render Helpers

Existing plugin render helpers such as:

- `includes/render-register-list.php`
- `includes/render-register-featured.php`
- `includes/render-register-map.php`

must stay in place until the corresponding theme templates and public integrations are confirmed live.

Rule:

- no hard removal before theme-owned replacements are verified in production-like output
- once theme templates are live and stable, these helpers should be marked legacy and phased down rather than expanded

## CSS Strategy

### Plugin CSS

Plugin CSS should only cover:

- research app
- map behavior
- hotspot behavior
- minimal utility wrappers for plugin interactive blocks

### Theme CSS

Theme CSS should cover:

- public card layouts
- page sections
- dossier rhythm
- responsive public presentation
- all editorial integration styling

## Editor Workflow

Goal: low-to-medium editor load.

## Editor UI Tooling Decision

Recommended approach:

- custom meta box JS for this CPT

Fallback:

- ACF Pro only if the custom UI becomes too costly to maintain or the field complexity materially exceeds the current plugin admin model

Rule:

- choose one approach per CPT
- do not mix a partial custom meta box UI with a second overlapping ACF field layer for the same editorial workflow
- if ACF Pro is chosen later, it should replace the overlapping custom admin surface, not coexist with it indefinitely

## Migration Strategy

### Phase 1: Model cleanup and CPT lock

- make `register_place` public with controlled rewrite
- add needed taxonomies
- add new sync/provenance fields
- add repository layer
- keep current research app working

### Phase 2: Public theme templates and route scaffolding

- build `/schoeneweide/` page template
- build `single-register_place` template
- confirm page-owned and single-place route structure early
- keep public rendering moving into the theme before sync complexity increases

### Phase 3: Editor UI and review tooling

- finalize custom meta box JS approach or formally choose ACF Pro fallback
- add review-oriented admin grouping
- add quality box and public-ready signals
- make sure one editorial surface owns the CPT workflow

### Phase 4: Kiosk / Touchtable sync foundation

- decide poll vs webhook push
- document sync endpoint and auth contract
- build importer/matcher entry point
- reserve validated kiosk sync slot in the implementation plan

### Phase 5: Touchtable enrichment workflow

- build importer/matcher
- store imported content in review fields
- add admin review tools
- promote approved content into native/public fields

### Phase 6: Interactive enrichments

- add map block
- add hotspot block
- add optional facts/timeline helpers if the theme needs them

### Phase 7: Push-back readiness

- finalize sync provenance
- document override rules
- only then assess real bidirectional sync

## Main Risks

- plugin grows into a second frontend system
- public prose remains trapped in meta instead of native content fields
- free-text facets make querying and editorial grouping brittle
- route collisions if public place pages are introduced without slug planning
- Touchtable imports overwrite reviewed editorial content
- kiosk sync starts before poll vs webhook ownership is settled
- mixed admin tooling creates duplicate editorial surfaces for the same CPT

## Final Recommendation

Proceed with the integration, but with this stricter rule:

- plugin = content model, sync, research app, interactive utilities
- theme = public website

That gives:

- cleaner scalability
- lower long-term rendering complexity
- better editor ergonomics
- better reuse across the site
- safer future push-back sync
- one place model serving both the public site and the kiosk/Touchtable consumer
