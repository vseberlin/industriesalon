# Project TODO

## Active
- Turn the current Touchtable review/match layer into a real promote workflow for `register_place`:
  - add deliberate field-level promotion from linked source snapshots into curated public fields instead of stopping at source linking
  - decide which fields stay source-only vs which can overwrite local editorial fields
  - keep public rendering theme-owned and local-data-only
- Clean up Touchtable extraction quality before broad editor rollout:
  - reduce flattened Elementor/timeline text in long detail-page previews
  - distinguish narrative pages, empty shell pages, and map-context pages more explicitly in review
  - improve preview readability for very long source pages
- Add reviewed Touchtable media workflow:
  - decide attachment/import path for source media
  - preserve source/rights metadata
  - only expose rights-safe reviewed media to the public theme
- Review the `Führung` single-page booking flow and remove unnecessary template duplication:
  - collapse `themes/industriesalon/templates/single-tour.html` and `single-tour-on-demand.html` into one template unless editors truly need two different page compositions
  - keep CTA/mode switching in render logic (`booking_mode`, inquiry fields, booking panel), not in parallel full-page templates
  - make `iss/tour-calendar` bail out cleanly when a tour has no usable calendar mapping instead of rendering an empty widget shell
  - audit calendar-mode tours whose content/title implies on-demand behavior but whose effective mode still resolves to `calendar`
- Add a stronger next-generation timeline/calendar render for program-style pages, especially `Veranstaltungen`, with better date rhythm and a cleaner culture-calendar presentation.
- Review the footer navigation and column spacing after the current footer refactor:
  - check whether `Entdecken` / `Service` should stay as two separate menus or move to real footer menu assignments
  - rebalance spacing between footer columns, section labels, and hours exception rows on wide screens

## After UAT
- Revamp the editorial creation flow into a local CPT-first path where editors create `Veranstaltung`, `Ausstellung`, or `Führung` once with minimal fields and the item then appears automatically in calendar, timeline, and cards without SaaS dependency in the normal publishing flow.
