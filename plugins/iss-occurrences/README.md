# ISS Occurrences

Owns imported dates, series mappings and public occurrence projections.
SuperSaaS supplies schedules; WordPress commerce owns capacity and bookings.

## Staff workflow

Open **Operations → Termine & Synchronisierung** (Tools without ISS Core).

- **Übersicht** shows configuration readiness, the existing maintenance receipt,
  last successful and next planned run, and links to upcoming review counts.
  Manual runs use the same monitor as hourly runs. A failed import retains the
  last success and displays failure even when a mapping was saved successfully.
- **Zu prüfen** defaults to upcoming unassigned or invalid-source slots. Filters
  also expose assigned, ignored, canceled and past dates. Empty search results
  distinguish filters from a database with no imported dates. Pagination and
  action redirects preserve context. Tour rows link to their series; event rows
  offer explicitly scoped individual assignment/ignore/review actions. Creating
  an event draft still requires the native creation capability and leaves its
  programme flag disabled pending editorial review.
- **Terminreihen** separates tours from events, shows the affected date count
  and current source, and links to the exact date list. Replacements require
  acknowledgement and the source ID displayed by the form must still match.
  Selecting a tour already connected to another series is rejected; staff must
  first inspect and explicitly unlink that series. The tour editor offers only
  tour series, validates before writing and does nothing on unchanged saves.
  Existing multiple-series links remain intact; the editor shows their list
  instead of a single-value control that could collapse them.
- **Verbindung** uses the existing Settings API option. Invalid configuration
  leaves the entire old configuration intact. Saved API keys are never rendered;
  a blank key field preserves the existing key. Existing calendar keys are
  immutable, IDs must be positive integers and duplicate keys are rejected.
  `salonbelegung` uses range/event retrieval; other keys use tour-slot retrieval.
  An optional blank calendar is available for additions. Disabling calendars
  stops future retrieval; it does not delete previously imported dates.

Existing `iss-occurrences-sync` and `iss-supersaas-api` bookmarks still work.
The API settings submenu is hidden in favor of the connection tab. Nonces,
existing sync capabilities and target-post editing rights protect writes.
Result notices belong to the acting user and expire after ten minutes. Native
forms work without JavaScript; a small enhancement prevents repeated submission
of the manual sync form and announces that the run is in progress.

## Mapping and projection contract

Stored `mapped`, explicitly unlinked `unreviewed`, and `ignored` series decisions
outrank automatic title/tag matching and previous projections. Exact-title
reconciliation only fills series without a review state. Ignored state must not
be overwritten by automatic series metadata updates. Whole-series actions update
its staged slots through the existing service for both tours and events; restoring
an ignored series clears inherited ignored slot state. Explicit event-slot
assignments take precedence over the series source; series ignore remains a gate.

These are bounded changes to the existing owner. They do not introduce a queue,
new schema, REST route, source-of-truth store or booking writer. The existing
five-minute source-response cache remains; the UI explains the possible delay.
Publication rules are unchanged: matching a draft is not editorial publication.
Commerce inventory is updated by its existing worker or staff control, not by the
sync screen. Booking/payment history and reconciliation requirements remain intact.

## Verification and release

`tests/e2e/bin/supersaas-admin.php` exercises actual owner methods and admin
handlers against connection-local temporary tables. All upstream HTTP is
intercepted. It checks rejected/stale/conflicting writes, repeated sync after
mapping/unlink/ignore/restore, failed upstream reads, failed SQL, user-scoped
feedback, settings validation and semantic form labels. Before/after checksums
protect real source/configuration/mapping/booking/inventory tables. Run with
WP-CLI as an authorized staff user and cron disabled for that process:

```sh
wp --user=STAFF_ID --exec='define("DISABLE_WP_CRON", true);' \
  eval-file tests/e2e/bin/supersaas-admin.php
```

Also run `occurrences-public-query.php` and `commerce-idempotency.php`, targeted
PHP lint/PHPCS/PHPStan, ESLint and Stylelint. Browser acceptance covers native
admin/editor rendering, filters, series separation, expanded controls and
390/1024/desktop layouts. Automated actions use isolated data; do not turn a
visual check into a real import, booking activation or mail operation.

Deploy occurrence and commerce admin code together. This release needs no schema,
content, options or uploads migration; leave target settings and staff records
in place. Rollback is a code rollback, never restoration of old booking data.
