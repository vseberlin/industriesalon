# ISS Commerce Lite

WordPress owns bookings, capacity, legacy voucher redemption and the operational
ledger. `iss-occurrences` supplies schedules; imported SuperSaaS capacity does not
control sales. The theme retains public presentation. No booking CPT, external
queue, new checkout route or voucher-sales interface is introduced.

## Intake and allocation

`POST /wp-json/iss-payments/v1/request` remains the shared booking/inquiry/order
endpoint. Existing inquiry/order behavior and opt-in staff notifications remain.
Bookings require a durable request key. The existing request row is the booking:
its ticket mix, unit prices, total, voucher credit and provider remainder are
frozen. Retries return that same identity without extending its deadline.

All allocation writers lock the stable departure, then requests, then vouchers
inside short checked InnoDB transactions. Confirmed bookings, unreconciled legacy
reservations and unexpired pending holds consume ticket quantities. Checkout
holds last 15 minutes; availability releases expired holds without waiting for
cron. Accepted pay-on-arrival bookings are confirmed allocations. HTTP and SMTP
run outside transactions. Deadlocks receive bounded retries.

Departure identity uses source ownership, provider/calendar identity and external
slot identity, never the replaceable occurrence row ID. Schedule changes or
missing dates close inventory for staff review and retain reservations. Capacity
cannot be lowered below allocated seats. Unconfigured departures stay closed.
The approved capacity is 30 people per departure; staff must reconcile existing
reservations before opening real inventory. Test departures and their bookings
are explicitly marked and excluded from staff digests.

`Tools → ISS Anfragen` includes departure controls, staff onsite booking,
booking/payment states, ticket mix, holds, voucher credit, event history and CSV.
Capabilities and nonces protect staff writes; public and staff admission use the
same service. Cancellation, refund status and voucher reversal are separate.

## Migration and preservation

Deploy the commerce, occurrences, frontend and core status changes together.
Run `ops/migrations/2026-10-22-commerce-ledger.php` with WP-CLI `eval-file`,
`--use-include`, and modes `check`, `apply /PRIVATE/new-before.json`, `verify`.
The additive v5 migration verifies a private target-local before-image and
preserves all original request IDs, fields and payment bindings. It extends
`iss_payments_lite_requests` and creates commerce-owned departures, vouchers,
redemptions, events and outbox tables. Their SQL names always use the WP prefix.
Fresh activation and the owner schema verifier use the same declarations.

No uploads or editorial-content transfer is required. Private reservation/voucher
JSON travels separately over SSH; never put customer data, codes, secrets or
before-images in Git. Preserve target accounts, roles, credentials and sessions.

## Mollie test payments

New hosted checkout requires private `ISS_MOLLIE_ENABLED=true` and a `test_` key
in `ISS_MOLLIE_API_KEY`. Live keys are rejected. Online checkout on real inventory
is refused until a separately reviewed production release. A full voucher
payment confirms atomically without contacting Mollie; partial coverage sends
only the frozen remainder.

`POST /iss-payments/v1/mollie/webhook` performs an authenticated provider read,
verifying binding, metadata, currency, amount and mode. Allocation, voucher
changes and events commit before acknowledgement. Duplicate and stale callbacks
cannot repeat effects or undo settlement. The fragment bearer on the uncached
`POST /iss-payments/v1/payment-status` receipt reads committed processed state;
returning to the browser is not required for confirmation. No customer contacts
are exposed by receipts.

Payment creation keeps its frozen payload/idempotency key. Ambiguous creation
can retry for 45 minutes; later recovery needs the dashboard payment ID:

```sh
wp iss-commerce-lite payment-refresh BOOKING_ID --payment-id=tr_PROVIDER_ID
```

Local hold expiry is independent of provider expiry. A payment arriving late
rechecks seats and voucher value under the same locks. Without both it becomes
an unconfirmed payment exception for staff/refund resolution. Refund execution
remains an explicit provider action; refresh its verified state, cancel seats
explicitly and reverse voucher credit only through the logged staff operation.
A provider refund alone does not cancel a booking.

## Legacy vouchers and reservations

A stable, private `ISS_COMMERCE_VOUCHER_KEY` of at least 32 characters protects
indexed code fingerprints. Preserve it across releases; changing it invalidates
lookup. Codes are trimmed and compared without case, matching the legacy
WordPress title lookup. Raw codes are not stored in the ledger or event log.

`tools/commerce-legacy-export.py` reads the local SQL backup and the newer YITH
snapshot into private review JSON, without executing SQL or modifying sources.
The latter preserves the 17 September expiry corrections. The original
three-year terms must be verified; missing/ambiguous expiry is review-only.
Import never restarts validity or resets an existing voucher's spent balance.

```sh
wp iss-commerce-lite inventory-sync
wp iss-commerce-lite voucher-import /PRIVATE/vouchers-review.json
wp iss-commerce-lite voucher-import /PRIVATE/reconciled-vouchers.json --apply
wp iss-commerce-lite reservation-import /PRIVATE/mapped-reservations.json
wp iss-commerce-lite reservation-import /PRIVATE/mapped-reservations.json --apply
```

Voucher imports default to isolated test copies (`--real` is an explicit cutover).
Each private record carries legacy site/ID, source SHA-256, code, integer cents,
issue/expiry, currency, enabled state and explicit `reconciled`, `terms_verified`,
`single_authority` flags. Missing flags disable redemption. Reimport of identical
provenance is a no-op; conflicting evidence requires staff reconciliation.
Before real activation, reconcile changes since the snapshot and retire the old
redemption path or route it through the same authority. No new voucher sales.

Mapped reservations use legacy identity, departure key, quantity/mix, frozen
amount, legacy status and source SHA-256. Imports require closed departures.
They retain seats in `legacy_review`, without asserting that an old unpaid
reservation was accepted or that a historic payment was freshly verified.
Staff reconciles/accepts/cancels these explicitly. Replays cannot duplicate them.

## Staff digest and scheduler

The outbox sends `info@industriesalon.de` a summary 24 elapsed hours before each
tour, including zero-booking summaries, confirmed booking/participant/mix counts,
paid/voucher/onsite totals, separate holds/exceptions and a ledger link. Deadlines
are UTC; display uses Europe/Berlin. Rescheduling invalidates pending summaries;
a previously sent summary gets a distinct correction. Late jobs catch up before
the event and mark post-event misses for review.

Mail is off unless target-private `ISS_COMMERCE_STAFF_MAIL=true`. Configure a
verified `ISS_COMMERCE_MAIL_FROM` and authenticated SMTP through the existing
WordPress mail API. Optional private constants are `ISS_COMMERCE_SMTP_HOST`,
`ISS_COMMERCE_SMTP_PORT` (default 587), `ISS_COMMERCE_SMTP_SECURITY` (default tls),
`ISS_COMMERCE_SMTP_USER`, `ISS_COMMERCE_SMTP_PASSWORD`. Existing mail hooks can own
transport instead. Staging acceptance uses Mailpit; customer mail is not enabled.
SMTP acceptance is not proof of mailbox delivery. Messages retain a stable
Message-ID; abandoned sending leases become `uncertain`, requiring mailbox review
before explicit retry. SMTP cannot promise exactly-once delivery.

Run `ops/commerce-stage.sh check|run|status /srv/industriesalon/stage`. Install only
after owner migration, target health/backup review and transport-mode inspection:

```cron
* * * * * /srv/industriesalon/stage/ops/commerce-stage.sh run /srv/industriesalon/stage
```

The runner has its own flock and named CLI container, a 55-second outer timeout,
20-item batches and a private last-run receipt. It does not share the long
maintenance/backup lock. The bounded owner command performs schedule checks,
expiry, payment reconciliation and due mail. `wp iss-commerce-lite status` and
ISS Operations report freshness. No commerce WP-Cron hook is installed; existing
non-commerce scheduling stays under its current owner. Disable traffic WP-Cron
only together with a verified replacement for those unrelated schedules.

## Recovery and verification

Set `ISS_COMMERCE_BOOKINGS_ENABLED=false` to close new admissions while retaining
callbacks, receipts and the ledger. Set `ISS_MOLLIE_ENABLED=false` to stop new
provider checkout; keep its private key and callbacks until pending payments are
reconciled. Disable staff mail before transport work. Never restore an old request
table, voucher balance or event log over later transactions. Keep additive schema
and payment/ledger processing during recovery; old code that ignores allocations
is not a safe rollback for open inventory.

Isolated suites: `commerce-idempotency.php` (including Mollie and schedule tests),
`commerce-migration.php`, `commerce-ledger.php`, `commerce-worker.php` and
`commerce-mailpit.php` under `tests/e2e/bin/`; JS store tests are in
`booking-tickets.cjs`. Run with cron disabled and process-local caches. Ledger
admin rendering in `commerce-idempotency.php` also needs WP-CLI `--user=ID` for
an existing authorized staff user; it does not change that account. Ledger
setup returns a private fixture receipt; launch ten independent `worker` commands
against it, then `verify`. `voucher-seed`, five concurrent `voucher-worker`
commands and `voucher-verify` prove balance conservation; finish with `cleanup`.
Mailpit testing requires explicit `ISS_TEST_MAILPIT=mailpit`, then API readback.
No real customer mail, real voucher consumption or live charge belongs in tests.
