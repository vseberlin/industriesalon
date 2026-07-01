# Decisions

Record durable architectural decisions here when they should outlive a single handoff. Keep entries short and link to implementation files when useful.

## Current Decisions

- Documentation model established on 2026-06-05: root docs stay small, deeper docs are task-loaded, runbooks are canonical procedures, and repo skills are triggers only.
- Keep root `AGENTS.md` compact and load deeper `docs/` files only by task.
- Keep session history out of repo memory folders; use `CHANGELOG.md`, `handoff_CURRENT.md`, and `TODO.md`.
- Use `WP_Query` for editorial post loops, and plugin-owned custom tables plus prepared SQL for projection/search/graph/archive/reporting data.
- Keep public UI in the theme and data/contracts in plugins unless an existing plugin explicitly owns a renderer.
- Use GitHub `main` as the exchange point between local and staging agents; prefer fast-forward-only pulls on clean trees.
- Model the public refactor around `Entity / Relation / Occurrence / View`: entities identify things, relations connect them, occurrences carry dated programme rows, and views render public/editor surfaces.
- Keep calendar/programme projection occurrence-only. Exhibition overview visibility is separate from programme projection.
- Keep editors in parent WordPress objects. Do not add an editor-visible occurrence/calendar/programme CPT.
- Keep the Offer bridge contract-only: `fuehrung` maps to `offer/tour`; `veranstaltung` maps to structural `_iss_entity_key` plus semantic `veranstaltung_art` terms. Public consumers should use graph-owned subtype labels instead of duplicating maps.
- Keep SuperSaaS ingestion in `iss-occurrences`; `iss-commerce-lite` owns booking/order request writes only.
- CSS layering follows `docs/architecture/css-layering-adr.md`: `tokens.css` owns
  the `--iss-*` token contract, reusable shapes belong in primitives/patterns,
  JSON output is styled through gesture/treatment/skin renderer classes, and
  page-specific CSS is only for true exceptions or migration compatibility.
- First-party public plugin CSS consumes the same token contract with fallbacks
  and plugin-prefixed local variables; admin plugin CSS stays scoped to wp-admin
  and is outside the public renderer stack.
