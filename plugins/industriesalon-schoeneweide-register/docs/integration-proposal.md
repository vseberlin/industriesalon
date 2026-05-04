# Revised Proposal: Schoneweide Register + Touchtable Integration

## Core Principle

Plugin = data, sync, research interface  
Theme = public website

## Key Decisions

- Keep `industriesalon-schoeneweide-register` as the single source of truth
- Keep `register_place` as the live CPT and do not rename it
- Move all public rendering to `themes/industriesalon`
- Use Touchtable as enrichment source, not as frontend
- Avoid plugin-owned design systems and page layouts

## Public Model

### CPT

- `register_place`
- this name is already live and must stay unchanged

### Native WP fields

- title
- excerpt
- content
- featured image

### Taxonomies

- `register_area`
- `register_status`
- `register_role`
- optional thematic taxonomy if needed later

### Structured meta

- address
- lat/lng
- ownership and usage fields
- metrics like size, jobs, investment
- `timeline_events`
- reviewed gallery/image references
- `aliases`
- sync metadata

## Public Routing

- landing page stays page-owned: `/schoeneweide/`
- single places become public under: `/schoeneweide/orte/{slug}/`
- `register_place` should be public with controlled rewrite
- no CPT archive on the root slug

## Timeline Model

Use structured repeater items:

- year
- title
- text
- source
- image_id

Do not parse long history text at runtime after migration.

## Hotspots

Use structured hotspot arrays, not one global coordinate pair:

- `image_context`
- `x`
- `y`
- `label`
- `mode`

This allows one place to appear in multiple images.

## Touchtable Integration

Pipeline:

1. import
2. match
3. store staging fields
4. manual review
5. promote approved content into native/public fields

Rules:

- never auto-publish imported long text
- do not merge based only on fuzzy title similarity
- keep sync provenance and override tracking from the start

### Kiosk / Touchtable sync decision

This should be documented early, but it no longer blocks Phase 1 template and route work.

Needed choice:

- poll
- webhook push

Also required:

- one documented REST ingestion endpoint
- reserved implementation slot for kiosk sync foundation before full sync rollout

## Sync Preparation

Store at minimum:

- source system
- source hash
- last synced state
- manual override fields
- external source reference

No bidirectional sync in phase 1.

## Repository Layer

Plugin repository responsibilities:

- fetch one or many places
- filtering
- caching
- marker payload
- timeline payload
- hotspot payload
- shared contract for all plugin blocks
- one REST endpoint family for all blocks
- no parallel ad hoc block fetch paths

No HTML rendering in the repository layer.

## Blocks

### Keep

- `iss-register/register-app`

### Add only if justified

- `iss-register/map`
- `iss-register/hotspots`
- optional `iss-register/place-facts`
- optional `iss-register/place-timeline`

### Do not add

- plugin-rendered cards
- plugin-rendered featured layouts
- full page composition blocks
- plugin-rendered related-place sections unless absolutely necessary

Current `render-register-*.php` files should be treated as legacy/internal and must remain until theme-owned public replacements are confirmed live.

## Theme Responsibilities

Theme owns:

- `/schoeneweide/` landing page
- single place template
- cards and featured sections
- narrative sections
- integrations into archive, tours, exhibitions, and projects
- all public visual styling

Use Query Loop, patterns, and theme templates wherever possible.

## Public Pages

### `/schoeneweide/`

- intro
- featured places
- map preview
- thematic groups
- entry to full research interface

### `/schoeneweide/orte/{slug}/`

- hero
- lead text
- facts
- images
- story
- timeline
- map
- related places
- sources

## Editor Workflow

Tooling decision:

- preferred: custom meta box JS
- fallback: ACF Pro
- rule: one approach per CPT, no overlapping dual admin layer

Recommended admin tabs:

1. Basis
2. Lage
3. Nutzung
4. Geschichte
5. Bilder
6. Quellen
7. Sync / Qualitaet

Minimum required:

- title
- area
- status
- coordinates
- short public summary or current use

## Query Strategy

Use:

- Query Loop
- prebuilt theme patterns
- repository helpers where native queries are insufficient

Do not rely on editors building complex manual query setups.

## Performance

- cache repository queries
- prebuild marker payloads when useful
- avoid runtime-heavy joins and repeated meta shaping

## Migration Phases

1. model cleanup and CPT lock
2. public theme templates and public routes
3. editor UI and review tooling
4. kiosk sync decision and endpoint foundation
5. Touchtable import and review workflow
6. map and hotspot enrichments
7. sync preparation

## Final Summary

Plugin feeds structured place data.  
Theme builds the public Schoneweide experience.  
Kiosk/Touchtable remains an explicit consumer of the same place model.

This keeps the system scalable, maintainable, and editor-friendly.
