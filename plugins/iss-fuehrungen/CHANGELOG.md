# Changelog

All notable changes for `iss-fuehrungen` are documented here.

## [Unreleased]

### Added
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
  - new inquiry meta: `inquiry_url`, `inquiry_label`, `inquiry_note`,
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
- Updated ownership docs to reflect the thin booking boundary split:
  - `saas-api` is documented as calendar infrastructure only,
  - `iss-payments-lite` is documented as the booking submit owner for `/is-tours/v1/book`.

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
