# Archive Boundary

## Current role

This plugin is the local archive owner for:

- `archivbeitrag`
- `archivsammlung`
- `archivobjekt`

The WordPress site is now the authoritative runtime for these records.

## What stays always loaded

These modules define the stable local archive contract and load on every request:

- post types and taxonomies
- archive meta registration
- archive blocks/rendering
- place suggestion and relation helpers
- editorial admin helpers

## What is retired

These responsibilities are no longer part of the plugin contract:

- WF-Museum sync
- museum-digital sync
- collection scraping and remote mirroring
- recovery and reimport CLI flows

## Compatibility

- plugin directory remains `iss-wf-import`
- PHP prefixes remain `iss_wf_import`
- existing CPT names, meta keys, and block names remain unchanged

This preserves stored data, templates, and references while the semantic role
shifts from "import plugin" to "archive owner".

## Operational guidance

- do not add new frontend/runtime dependencies on remote import functions
- treat local archive content as source of truth
