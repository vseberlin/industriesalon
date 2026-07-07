# Changelog

All notable changes for `iss-fuehrungen` are documented here.

## [Unreleased]

### Added
- Registered `fuehrung` as an `iss-editorial` JSON format so tour narrative
  content can move to gesture sections while tour meta remains the source for
  facts, booking, dates, route, and hero-gallery behavior.
- Added `wp iss-editorial fuehrung-dry-run` and
  `wp iss-editorial fuehrung-import-candidate` migration helpers for converting
  published Führung `post_content` narrative into `_iss_editorial_fuehrung`
  JSON documents.
- Added `iss/tour-description`, a dynamic block for curated prose-only tour descriptions that can be safely reused inside controlled single-tour layouts.
- Added tour-level calendar mapping field back to the editor UI:
  - `calendar_tag` meta field in `Führungsdaten`.
  - tag suggestions sourced from `iss_calendar_source_map`.
- Added automatic mapping sync on tour save:
  - stores/refreshes source map entry (`tag -> source_post_id/source_post_type`),
  - attempts series relink for matching `iss_calendar_item` entries.
- Added admin warning on tour edit screen when calendar mode is expected but no linked future dates are found.
- Added comprehensive plugin documentation in `MANUAL.md`:
  - functional scope,
  - editor field behavior,
  - data mapping to `saas-api`/calendar,
  - hostile-environment failure modes and deficiencies.
- Added prioritized work backlog in `TODO.md`.
- Added this changelog file.
- Added Phase 1 booking-mode support in `iss-fuehrungen`:
  - new tour meta: `booking_mode`, `allow_on_demand_with_calendar`,
  - new inquiry meta: `inquiry_label`, `inquiry_note`,
  - effective mode resolver for `auto|calendar|on_demand|hybrid`,
  - mode-aware booking box and archive status rendering,
  - conditional single-template rendering (calendar section vs on-demand section).
- Added Phase 2 dynamic blocks in `iss-fuehrungen`:
  - `iss/tour-facts` (renders structured facts),
  - `iss/tour-booking-panel` (renders mode-aware booking panel),
  - single template now uses these blocks instead of direct helper calls.
- Added mode-based single template routing to theme HTML templates:
  - `single-tour.html` for bookable/hybrid tours,
  - `single-tour-on-demand.html` for on-demand tours.
- Removed shortcode/ACF placeholders from those two HTML templates and replaced them with dynamic blocks.

### Changed
- Added a filter to `iss/tour-description` so the active theme can replace the
  legacy `post_content` description with the enabled JSON `intro` gesture.
- Updated the Führung editor hint to point editors to the structured
  composition canvas for narrative sections and media.
- Integrated route station editing into the Führung JSON editor while keeping
  station rows stored in `iss_related_places`; the generic `Verknüpfte Orte`
  metabox is no longer shown on Führung edit screens.
- Moved public Führung rendering to the active theme/template hierarchy and removed the plugin public template loader, including the singular selector, single PHP fallback, and archive/taxonomy fallback template.
- Reworked the single-tour render path to use explicit layout blocks instead of context-sensitive output hacks:
  - scheduled and on-demand templates now consume `iss/tour-description` instead of raw `post_content` in controlled description slots,
  - the old single-tour suppression hook for related-place block output was removed,
  - route-aware place discovery now uses the explicit cards-only relation output path.
- Refined tour route rendering for the current single-tour composition:
  - route navigation now drives the station carousel instead of only anchor jumps,
  - station rows support denser editorial media/detail-card combinations.
- Removed an unused carousel callback argument left over from the route navigation refactor.
- Updated ownership docs to reflect the thin booking boundary split:
  - `saas-api` is documented as calendar infrastructure only,
  - `iss-payments-lite` is documented as the booking submit owner for `/is-tours/v1/book`.
- Removed the legacy SuperSaaS-derived `inquiry_url` field from the active
  Führung booking contract. On-demand and hybrid inquiry CTAs now use the local
  request modal path instead of stored external URLs.

### Planned
- Security hardening for booking entry flow (implemented in `iss-payments-lite`, tracked from this plugin doc set).
- Editor UI cleanup for currently exposed but inactive fields.
- Mapping/fallback model cleanup and explicit ownership documentation.

## [1.0.0] - 2026-04-22

### Added
- Initial `iss-fuehrungen` release with:
  - `fuehrung` CPT,
  - `fuehrung_typ` taxonomy,
  - structured tour metadata registration and admin meta box,
  - custom single and archive templates,
  - card/facts/booking template helpers,
  - integration with `iss/tour-calendar` and `iss/tour-dates` blocks from `saas-api`.

### Changed
- Removed plugin-level ownership/UI for legacy `calendar_tag` mapping (mapping handled via calendar source-map and linking workflows).
