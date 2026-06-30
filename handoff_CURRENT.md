# Current Handoff

Updated: 2026-06-30

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Universal occurrence calendar/intake refactor is implemented locally and ready
  for staging/live editor testing.
- Public slot reads use `GET /wp-json/iss/v1/booking-slots`; the old public
  `/iss/v1/tour-slots` route is no longer registered.
- Public commerce writes use `POST /wp-json/iss-payments/v1/request` with
  `booking`, `inquiry`, and `order` intents; the old public tour booking and
  publication-order routes are no longer registered.
- Timeline grouped `Termin wählen` opens the shared occurrence calendar modal
  instead of rendering long slot lists.
- The shared booking calendar modal uses the light booking-modal palette from
  `industriesalon_booking_modal.html`; the prior dark treatment is not active.
- Sold-out, unavailable, or past-only selected days render
  `Keine Termine verfügbar` and no time buttons.

## Preserve

- Keep occurrence availability in `iss-occurrences`, public calendar UI in
  `iss-frontend`, and request persistence/payment-gateway handoff in
  `iss-commerce-lite`.
- Keep the request table name and `request_kind` column for now; new writes
  store normalized intent values there.
- Keep modal calendar styling scoped to `.is-tour-calendar--modal`; full page
  Führung calendars keep their existing skin.
- Leave unrelated local untracked files out of Git unless explicitly requested:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- On staging, pull the pushed commit and live-test `/kalender/`:
  grouped recurring `Termin wählen`, month navigation, available slot booking
  form, and sold-out/unavailable date empty state.
- Live-test a publication order to confirm it posts `intent=order` through
  `/iss-payments/v1/request`.
- Before production deploy, verify target mail mode and enable
  `Tools > ISS Anfragen` notification email only for an approved recipient.
- If a dedicated booking section is needed on single Veranstaltung pages, add it
  as a separate theme/render slice; the current work covers timeline/calendar
  entry points.

## Verified Locally

- PHP lint for changed PHP files in `iss-occurrences`, `iss-frontend`,
  `iss-commerce-lite`, and `iss-graph`.
- `bash tools/phpcs-target.sh` for changed PHP files passed.
- `node --check` for `programm.js` and `publication-order.js`.
- `npx stylelint themes/industriesalon/assets/css/tour-calendar-skin.css`.
- `git diff --check`.
- WP runtime probe confirmed new routes are registered and old public routes are
  gone.
- WP runtime probe returned booking slots for Führung and Veranstaltung sources.
- Browser probes on `/kalender/` confirmed modal open, `/booking-slots` fetch,
  universal booking payload fields, available slot rendering, unavailable date
  empty state, desktop/mobile no horizontal overflow, and the final light
  booking-modal calendar palette.

## Commit State

- Commit and push requested for this checkpoint.
