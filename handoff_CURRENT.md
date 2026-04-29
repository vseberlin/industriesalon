# Handoff Current

## Status
- `ready_for_next_session`

## Date / Window
- Date: `2026-04-29`
- Timezone: `Europe/Berlin`

## Scope
- Worked in:
  - `themes/industriesalon`
  - `plugins/iss-payments-lite`

## What Was Done
- Built a new file-backed `Repair Café` landing page:
  - created [page-repair-cafe.html](/home/vladimir/wp/themes/industriesalon/templates/page-repair-cafe.html)
  - created [page-repair-cafe-template.html](/home/vladimir/wp/themes/industriesalon/patterns/page-repair-cafe-template.html)
  - registered the new pattern in `themes/industriesalon/functions.php`
  - added page-scoped hero/section styles in `themes/industriesalon/assets/css/patterns.css`
  - created live page `13253` with slug `/repair-cafe/`
- Integrated the existing `industriesalon-notices` plugin into the Repair hero:
  - replaced hardcoded note markup with `industriesalon/notice-banner`
  - created page-scoped notice `13254` for `Repair Café`
  - fixed the hero slot structure and page-scoped cover grid so the banner sits on the right track
- Refined the publications archive and single-publication templates:
  - archive now uses a restrained two-column masthead instead of the old archive-title/prose stack
  - single publication now uses a tighter long-read opening with intro + sidebar in one row
  - archive listing is limited to `3` cards
  - archive CTA changed from `Details / bestellen` to `Mehr`
  - masthead clearance now accounts for the oversized sticky logo
- Fixed publication card image behavior:
  - corrected the media fill/stretches chain
  - switched archive covers to full-bleed image behavior
  - removed the half-empty white lower area in publication cards
- Wired the publication order panel into `iss-payments-lite`:
  - added frontend modal assets:
    - `plugins/iss-payments-lite/assets/publication-order.js`
    - `plugins/iss-payments-lite/assets/publication-order.css`
  - added REST endpoint:
    - `POST /wp-json/iss-payments/v1/publication-order`
  - implemented the existing `iss_publications_order_button_html` filter
  - single publication order panel now opens a modal and stores thin order requests locally

## Verification
- Active theme remained `industriesalon`
- `themes/industriesalon/functions.php` PHP lint passed in container
- `plugins/iss-payments-lite/iss-payments-lite.php` PHP lint passed in container
- `/repair-cafe/` renders the new page and notice-banner integration
- `/publikationen/` renders the new masthead and `3` publication cards
- publication order endpoint returned `{"ok":true}` in a live POST test
- publication order requests are stored in `iss_publication_order_requests`

## Important Notes
- The publication order flow is now `modal -> submit -> local record`
- `Mollie` remains present but disabled as `bald`
- No gateway creation or webhook logic is implemented yet

## Suggested Next Step
1. Continue the publication payment path from `iss-payments-lite` by replacing the disabled `Mollie` placeholder with real gateway creation and confirmation handling.
2. If needed, align the remaining publications single-page visuals with the quieter archive treatment.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
