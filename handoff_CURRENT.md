# Current Handoff

Updated: 2026-07-06

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Führung single templates are now JSON-first: the old `iss/tour-route` block,
  route carousel assets, renderer helpers, and dedicated route CSS were removed.
  Route presentation for enabled JSON content flows through the `atlas_map`
  gesture backed by `iss-relations`.
- Single Führung related output now uses a required shared
  `iss/related-content` relation network rail. Veranstaltung uses the same
  wrapper contract, with shared CSS in `patterns.css`.
- Führung JSON gained `upload_intake`, rendered as a moderated Event Drop CTA
  with a `fuehrung__{slug}` context. Gallery, material, upload, Leitfrage,
  Zitat, and Führung atlas-map editor copy was polished.
- Führung landing categorisation now has explicit `offer_catalog_groups` meta
  labelled `Art der Führung`. The offer catalog uses explicit groups first and
  falls back to the legacy booking/text heuristic only when empty. The old
  `fuehrung_typ` taxonomy is no longer editor-facing.

## Preserve

- Public presentation stays in the theme; editor/format contracts stay in
  `iss-content` / `iss-editorial`; route relations and Atlas map contracts stay
  in `iss-relations`.
- Do not reintroduce the deleted `iss/tour-route` block as an interim fallback.
  A vanilla WordPress fallback should be rebuilt later from the stabilized JSON
  and relation contracts.
- Booking, occurrence slots, commerce amounts, facts, route stations, graph
  projection state, and the required relation network stay outside JSON gesture
  bodies.
- CSS migration should keep draining old page-specific selectors into shared
  primitives, renderer contracts, or scoped compatibility.

## Next Action

- Deploy the pushed `main` checkpoint to staging and smoke-test:
  `/fuehrungen/`, representative single Führungen, and one Veranstaltung.
- On staging, confirm the single Führung relation network, upload-intake CTA,
  JSON atlas-map route rendering, and booking rail still render without mobile
  overflow.
- No SQL artifact or uploads artifact is part of this checkpoint.

## Verified Locally

- PHP lint for touched Führung, editorial, Veranstaltung, and theme renderer
  files.
- Stylelint for touched shared/admin/content/event/tour CSS.
- `git diff --check`.
- WP-CLI registry probes for gesture labels/descriptions, Führung
  `upload_intake`, and `offer_catalog_groups`.
- WP-CLI runtime probes confirmed explicit landing category override,
  taxonomy visibility, `iss-fuehrungen drift-check`, and source ownership for
  the relevant templates/pages.

## Commit State

- Local checkpoint is ready to commit and push to `origin/main`.
- After push/deploy, record the final commit hash and staging verification in
  the closeout response.
