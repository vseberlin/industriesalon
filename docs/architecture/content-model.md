# Content Model

This repo uses WordPress editorial content as the primary authoring surface. Custom plugins define CPTs, fields, dynamic blocks, imports, and data contracts; the theme owns public composition and visual presentation.

## Ownership

- Theme: public templates, skins, layout composition, frontend CSS/JS, and editor-visible patterns.
- `iss-content`: shared CPT/editor/data contracts, including `register_place`,
  its shared Place editorial-format registration, and the former content-model
  and Führung module surfaces.
- `industriesalon-steuerung`: persistent institutional visit, address, contact, and notice facts.
- `industriesalon-schoeneweide-register`: transitional Place facts, legacy
  epoch/state projections, compact interactive-Atlas contracts, register tools,
  and admin workflows. It no longer owns the `register_place` CPT or Place
  narrative editor.
- `iss-archive`: archive ingest, normalization, projection, archive object runtime, assertions, evidence, and collection data.
- `iss-graph`: shared entities, names, relations, graph-backed profiles, and public search projection.
- `iss-relations`: relation queries and relation-aware blocks.

For first-party plugin boundaries, see `plugin-map.md`.

## Rules

- Prefer Gutenberg-editable content and reusable patterns for editorial surfaces.
- Keep CPT/data ownership in plugins and public presentation in the theme unless an existing plugin explicitly owns the renderer.
- Do not add shortcode-like workflows or hidden configuration when an editor-visible block or pattern can express the structure.
- Check DB `wp_template` authority before assuming a file template is live.

## Veranstaltungen overview

The Veranstaltungen template uses the existing `industriesalon/program-cards`
block's `presentation: programme` option. The occurrence query remains owned by
`iss-frontend`; the theme composes the next appointment, subsequent date rows,
running programme ranges, current exhibitions and future exhibitions. Recurring
series show their next occurrence; the full calendar retains all dates. The
programme card variants extend `cards.css`; obsolete page intro/sidebar/formats
selectors were removed from `page-events.css`.

Editors maintain titles, featured images and native excerpts on the source
records. Excerpts are displayed as complete editorial summaries, without automatic
truncation. Existing material ticket links supply the primary ticket action.
Images retain their full composition, including embedded credits and poster text.
Past appointments use the existing paginated timeline, newest first; illustrated,
published Rückblicke linked to events/exhibitions use the shared related cards.
No report is generated merely because an event ended.
