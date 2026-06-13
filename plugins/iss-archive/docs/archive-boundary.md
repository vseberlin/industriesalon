# Archive Boundary

## Current role

This plugin is the local archive owner for:

- `archivbeitrag`
- `archivsammlung`
- `archivobjekt`
- Archivset saved selections linked to editorial content

The WordPress site is now the authoritative runtime for these records.

## What stays always loaded

These modules define the stable local archive contract and load on every request:

- post types and taxonomies
- archive meta registration
- archive blocks/rendering
- place suggestion and relation helpers
- editorial admin helpers
- the Archive Picker editor workflow for already-local archive objects

## Archivset editor scope

The Archive Picker metabox is the editor-facing workflow for saved
selections. It appears only on canonical editorial post types that publish
archive selections: `ausstellung`, `publication`, `projekt`, `veranstaltung`,
`video`, and `page`.

Archivset attachment is stored through the plugin-owned link table and managed
through the Archive Picker metabox/workbench. Public pages should consume
attached sets through explicit archive-object blocks or theme/template logic,
not through a generic attached-set grid block.

The Archivsets workbench is the global admin surface for maintaining saved
selections. Post editor controls are contextual: attach/search by title, edit
the active set members, and insert explicit `iss-wf-import/archive-object`
placement blocks into the story. The placement block carries the object
reference (`setId` plus `memberPosition`, or a direct fallback `postId`) and a
render `variant`; `featured` is the current public variant.
Shared editor-side set search, attach, load, and change notifications should
use `assets/js/archive-set-selector.js` rather than reimplementing Archivset
REST path logic in individual blocks.

## What is retired

These responsibilities are no longer part of the plugin contract:

- WF-Museum sync
- editor-triggered museum-digital sync
- collection scraping and remote mirroring
- recovery and reimport CLI flows

## Compatibility

- plugin directory is `iss-archive`
- PHP prefixes remain `iss_wf_import`
- existing CPT names, meta keys, block names, and script handles remain unchanged

This preserves stored data, templates, and references while the semantic role
shifts from "import plugin" to "archive owner".

## Operational guidance

- do not add new frontend/runtime dependencies on remote import functions
- keep remote imports in explicit WP-CLI commands, not editor REST routes
- treat local archive content as source of truth
