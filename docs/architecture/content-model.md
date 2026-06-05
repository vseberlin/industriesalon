# Content Model

This repo uses WordPress editorial content as the primary authoring surface. Custom plugins define CPTs, fields, dynamic blocks, imports, and data contracts; the theme owns public composition and visual presentation.

## Ownership

- Theme: public templates, skins, layout composition, frontend CSS/JS, and editor-visible patterns.
- `iss-content-model`: shared CPT/editor/data contracts where content does not belong to a narrower plugin.
- `industriesalon-steuerung`: persistent institutional visit, address, contact, and notice facts.
- `industriesalon-schoeneweide-register`: `register_place` structured data, epochs, state projection, register tools, and admin workflows.
- `iss-wf-import`: archive ingest, normalization, projection, archive object runtime, assertions, evidence, and collection data.
- `iss-graph`: shared entities, names, relations, graph-backed profiles, and public search projection.
- `iss-relations`: relation queries and relation-aware blocks.

For first-party plugin boundaries, see `plugin-map.md`.

## Rules

- Prefer Gutenberg-editable content and reusable patterns for editorial surfaces.
- Keep CPT/data ownership in plugins and public presentation in the theme unless an existing plugin explicitly owns the renderer.
- Do not add shortcode-like workflows or hidden configuration when an editor-visible block or pattern can express the structure.
- Check DB `wp_template` authority before assuming a file template is live.
