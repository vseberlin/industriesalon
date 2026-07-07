# Current Handoff

Updated: 2026-07-07

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Führung booking no longer uses the legacy SuperSaaS-derived `inquiry_url`
  field. The field is removed from active meta registration, saved Führung
  edits clear any old value, and on-demand/hybrid inquiry CTAs open the local
  `iss-payments/v1/request` inquiry modal through the shared programme
  frontend runtime.
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
- Local request capture for Führung inquiries stays in `iss-commerce-lite`;
  do not reintroduce a SuperSaaS/external inquiry URL as the canonical booking
  path.
- CSS migration should keep draining old page-specific selectors into shared
  primitives, renderer contracts, or scoped compatibility.

## Next Action

- Continue browser UAT on staging for representative single Führungen and one
  Veranstaltung, especially mobile overflow around the booking rail, Führung
  inquiry modal, relation network, upload-intake CTA, and JSON atlas-map route
  rendering.
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
- For the Führung booking cleanup: `node --check` for the programme calendar
  JS, PHP lint for touched Führung files, targeted PHPCS for touched Führung
  PHP files, `git diff --check`, and WP-CLI render smoke confirmed a published
  Führung booking panel renders a local inquiry trigger.

## Commit State

- Local, GitHub, and staging `main` should be synchronized at the July 7
  Führung booking inquiry checkpoint after this closeout deploy.
- No SQL artifact or uploads artifact is required for the July 7 booking
  cleanup.
