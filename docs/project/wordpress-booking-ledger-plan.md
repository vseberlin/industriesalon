# WordPress booking ledger and legacy voucher redemption plan

Date: 22 October 2026. Status: implemented locally; staging acceptance and operational activation are in progress.

WordPress will own departure capacity, seat reservations, bookings, voucher
redemption and the operational ledger. SuperSaaS supplies scheduling information
only. Its capacity, remaining-place and sold-out values must no longer determine
whether a WordPress booking is accepted. This replaces the earlier assumption
that staff manage booking capacity in SuperSaaS.

The requested mail is a staff summary to `info@industriesalon.de`, **24 hours
before each tour**. Existing, eligible Gutscheine must be honored within their
original three-year validity; no new voucher sales or issuance are included.

## Existing implementation and scope

Extend `iss-commerce-lite`, its request endpoint and its existing custom request
table. Keep the shared booking dialog and the occurrence read API. The theme
continues to own presentation; `iss-occurrences` owns schedule ingestion and
calendar projection. No booking, redemption or log CPTs and no postmeta seat
counting are needed.

The deployed Mollie test integration creates payments and verifies callbacks,
but does not reserve seats. Its per-booking lock prevents duplicate payment
creation; it does not serialize different customers competing for the same
departure. The existing return-status endpoint can refresh persisted provider
state; the new receipt must become a read of the authoritative processed state.

Staging observations on 22 October: upcoming Elektropolis dates contain imported
SuperSaaS capacity 1 / available 0, which causes the current sold-out label and
booking rejection. Commerce notification mail is disabled, no `phpmailer_init`
hook is registered, and the configured sendmail executable is absent. The user
crontab contains only the baseline collector; `DISABLE_WP_CRON` is not defined
in `wp-config.php`. These observations require rechecking before activation.

Existing mechanisms to extend:

- [Commerce owner and payment contract](../../plugins/iss-commerce-lite/README.md).
- [Database ownership and migration conventions](../architecture/database.md).
- [Host maintenance runner and activation procedure](../runbooks/staging-maintenance.md).
- [Account-preserving transfer boundary](../infrastructure/sync.md).

## Storage and state model

All operational tables must use InnoDB and the WordPress table prefix, with
owner-managed schema verification. Names for new tables below describe roles;
final SQL names and migrations belong to the implementation slice.

| Table | Fields and purpose |
| --- | --- |
| Existing `iss_payments_lite_requests`, extended | Unique booking reference, departure inventory ID, booking state, ticket quantities and immutable price breakdown, total/voucher/Mollie amounts in integer cents, currency, hold expiry, confirmation/cancellation timestamps and test-mode marker. Preserve current IDs, request keys, payment binding and unrelated inquiry/order rows. |
| Departure inventory | Stable departure key, source identity, scheduled start in UTC, schedule revision, configured capacity and booking-open state. This is the row all seat writers lock. |
| Legacy vouchers | Unique normalized-code fingerprint, legacy site/YITH identity, original amount, verified remaining balance, currency, issue/expiry dates, enabled/review state and import provenance. |
| Voucher redemptions | Voucher and booking IDs, amount, reserved/committed/released/reversed state, reservation deadline and unique operation key. |
| Commerce event log | Booking/voucher/departure reference, transition, before/after state, actor, reason, timestamp and unique transition key. Append-only through owner APIs. |
| Mail outbox | Message key, type, recipient, due time, schedule revision, processing lease, attempts, next retry, delivery state, sanitized error and transport reference. |

The request table already provides the booking identity and payment fields, so
do not create a second booking record with competing authority. The additional
tables address departure-level locking, voucher balances, redemption history,
durable event records and retriable mail, which the request table cannot provide.
They are commerce-owned tables, not a new generic workflow or queue service.

Booking state and payment state are independent. Pending checkout consumes seats
only while its hold is valid. Confirmed bookings consume their ticket quantity,
including voucher-funded and accepted pay-on-arrival bookings. Canceled or
expired reservations consume none. A late paid booking without a seat remains
unconfirmed and visible for resolution; `payment_status=paid` alone must not
create an allocation. Refund status and booking cancellation are separate actions.

Use UTC for persisted deadlines and Europe/Berlin for staff/public display. Add
indexes for departure/state/expiry, due mail, voucher lookup and redemption
ownership. Protect request, provider-payment, booking-reference, redemption and
mail identities with unique constraints. Do not put voucher codes, API keys,
receipt tokens, raw provider bodies or unnecessary customer data into logs.

## 1. WordPress inventory and booking ledger

Add staff capacity controls per departure, with an optional tour default. An
unset capacity must require configuration rather than imply unlimited sales or
reuse a SuperSaaS number. Reconcile existing real reservations before opening a
departure. Identify synthetic test bookings explicitly so they do not consume
real inventory or enter operational reports.

Give each departure a stable commerce identity mapped to its schedule source;
do not depend on a projection row ID that a sync can delete and recreate.
Schedule changes must preserve bookings. A missing or rescheduled date closes
or flags the departure for review rather than silently deleting, moving or
releasing reservations. Reject capacity reductions below allocated seats unless
staff resolve the affected bookings through an explicit logged operation.

Switch all public availability consumers and server validation to the same
commerce inventory service. Remove imported SuperSaaS capacity and availability
from those decisions, including timeline labels and calendar disabled states.
Deploy that switch with the WordPress inventory implementation; removing only
the label would leave the server restriction or open uncontrolled sales.

Extend Tools → ISS Anfragen with a departure ledger: booking reference, ticket
breakdown, hold deadline, booking/payment/voucher state, exceptions, event
history and CSV export. All staff mutations require capabilities, nonce checks,
and the same locking and logging rules as public writes.

## 2. Atomic seat and voucher holds

Create a pending booking with a **15-minute hold before redirecting to Mollie**.
Within one short transaction:

1. Lock the stable departure inventory row with `SELECT ... FOR UPDATE`.
2. Check the idempotency identity and read current seat allocations. Use current
   locking reads or an explicitly chosen isolation strategy; a stale consistent
   snapshot established before the parent lock is not a safe count.
3. Count confirmed seats plus unexpired pending seats. Compare ticket quantities,
   not booking-row counts, with configured capacity.
4. Lock any voucher rows in deterministic order; check eligibility and balance
   after existing unexpired redemption reservations.
5. Insert the booking, optional voucher reservation and required event records.
6. Commit, then contact Mollie outside the transaction using the persisted
   idempotency identity and frozen amount.

All writers, including staff actions, expiry, cancellation and webhook handling,
follow the same lock order. Check every write/commit result and use bounded
deadlock retries. Repeated form submissions return the original booking without
extending its hold. Do not hold SQL locks during HTTP or SMTP operations.

Expired holds stop consuming capacity directly in the availability query; cron
performs durable cleanup and logging. Availability therefore does not depend on
a cleanup worker running at exactly the deadline. Voucher reservations follow
the same deadline, bounded by the voucher's own expiry. Admission controls must
also limit repeated anonymous holds without bypassing transaction safety.

An accepted pay-on-arrival booking needs an explicit confirmed allocation, not
an unpaid checkout hold that expires in 15 minutes. Unconfirmed inquiries remain
distinct from reservations.

## 3. Authoritative payment processing and late payment

The webhook verifies payment state through an authenticated Mollie API read,
including binding, amount, currency and mode. Apply booking, redemption, log and
required outbox changes atomically. Acknowledge only after persistence succeeds;
duplicates produce no repeated effects. Keep callback-before-binding recovery
and the existing bounded idempotent payment-creation recovery.

Provider `failed`, `canceled` and `expired` states release an active hold and its
voucher reservation. A failed method attempt that leaves the overall provider
payment open is not a terminal payment failure. The return page reads status;
closing the tab cannot prevent confirmation. A bounded scheduled reconciliation
uses the same transition service to recover missed callbacks.

Local hold expiry is separate from provider payment expiry. Mollie currently
documents longer windows for card and PayPal payments, and cancellation depends
on `isCancelable`. Do not assume the hosted payment becomes impossible after
15 minutes or overwrite provider status with local hold status.

When payment arrives after the hold deadline, lock the departure and relevant
vouchers again. Confirm only if the seats and any required voucher value can
still be allocated. Otherwise preserve the paid state, record an unconfirmed
payment exception, and flag refund/staff resolution without a seat confirmation.
Never take seats allocated to a later customer. Retry and out-of-order callback
tests must cover this path. Refund execution and voucher reinstatement require
explicit, idempotent recorded actions; deleting history is not a reversal.

## 4. Existing WooCommerce Gutscheine

The read-only source inspection found this private local SQL backup:

`/home/vladimir/strato/snapshots/20260917-before-yith-restore.sql.gz`

Its SQL footer is dated 17 September 2026, 10:30:51. It contains 108 YITH
`gift_card` records and metadata for `_ywgc_amount_total`,
`_ywgc_balance_total`, `_ywgc_currency`, `_ywgc_expiration`, `_ywgc_order_id`
and redemption order links such as `_ywgc_orders`. YITH reads the voucher code
from the gift-card post title. This count is not a count of currently redeemable
vouchers. The backup and all extracted customer/code data remain private.

Implement a dry-run, repeatable importer using legacy identity, private source
hashes and reconciliation receipts. Preserve original values, balances,
enabled/revoked states, order evidence and original validity. Verify issue-date
and expiry semantics against the legacy records and terms: import must not
restart the three-year validity or invent an expiry for ambiguous records.

Automatically accept only verified eligible vouchers. Fully redeemed, expired,
disabled or uncertain records fail redemption or enter staff review. Partial-use
records require reconciliation against their original terms and remaining
balance; never reset them to face value or assume every nonzero balance is unused.

Full voucher coverage commits the redemption and booking in one transaction
without Mollie. Partial coverage reserves voucher value and sends only the
remainder to Mollie. Failed/expired checkout releases the reservation; successful
confirmation commits it once. Never exceed the verified balance, including when
two customers use the same code concurrently. Preserve code matching semantics,
use a private keyed fingerprint for indexed lookup and rate-limit guessing.

Before activation reconcile redemptions and other changes since the backup.
There must be one redemption authority: retire the old redemption path at
cutover or have it call the same atomic service. Independent imported balances
on two live sites would permit double spending. Staging uses isolated test
copies and must not consume production voucher balances. No voucher sales or
issuance interface is added.

## 5. Transactional mail and the staff digest

Configure authenticated mail through WordPress's existing mail API, with
target-local secrets, a verified sender and provider-appropriate DNS settings.
Use Mailpit for staging tests, then verify actual delivery for the approved
staff recipient. `wp_mail()` success alone is not proof of mailbox delivery.
The required recipient is `info@industriesalon.de`; customer email delivery is
not automatically enabled by this planning task.

Create a digest due **24 hours before each tour**. Include the tour and start
time, booking count, total participants, regular/reduced breakdown, paid/voucher/
pay-on-arrival totals, outstanding holds or exceptions separately, and a staff
ledger link. Exclude canceled/expired reservations and synthetic test bookings
from confirmed totals. Send a zero-booking summary for an active departure too.

Use a unique message identity per departure, schedule revision and message type.
Select due-and-unsent work, not equality with a particular minute. A reschedule
invalidates an unsent old digest and recalculates its due time; after a digest
has been sent, a schedule change requires a distinguishable correction. Tour
cancellation suppresses unsent ordinary summaries. Overdue summaries catch up
before the event; missed post-event notices are recorded for staff review.

Claim outbox rows with leases, send outside transactions and record attempts,
retry times and transport references. Recover abandoned workers. Ordinary SMTP
cannot guarantee exactly-once delivery after an ambiguous send; retain a stable
message identity and observable uncertain outcomes rather than claim otherwise.

## 6. Real cron and operations

Use a bounded commerce WP-CLI job from host cron approximately every minute for
hold cleanup, payment reconciliation, due digests and mail dispatch. Extend the
existing maintenance conventions; use process locking, time limits, bounded
batches, last-success receipts and the existing operations status surface.
Long backup/maintenance tasks must not block the commerce minute job.

Inspect all active scheduler owners before activation. Disable traffic-driven
WP-Cron only while completing and verifying its host-driven replacement; retain
existing non-commerce schedules and audit mail mode before running due callbacks.
The wider maintenance/backup/PHP-limit programme is not implicitly activated by
adding booking jobs. No extra daemon or external queue is required.

Store deadlines in UTC and calculate the digest as the actual start instant
minus 24 hours. Verify Berlin daylight-saving boundaries, delayed jobs, process
termination, overlapping runs, provider outages and recovery after downtime.

## 7. Delivery slices and acceptance

| Slice | Deliverable and gate |
| --- | --- |
| 1 | WordPress inventory, ledger fields/admin, atomic holds and event log. Prove concurrent admission before switching public availability away from SuperSaaS. |
| 2 | Transactional Mollie transitions, read-only receipts, late-payment exceptions and reconciliation. Prove abandoned-browser and duplicate-callback behavior. |
| 3 | YITH dry-run importer, private reconciliation report, atomic redemptions and voucher/payment combinations. Activation requires current source balances and one redemption authority. |
| 4 | Mail transport, outbox, 24-hour digest and host-cron installation. Prove Mailpit output, recipient delivery, retries and scheduler health. |
| 5 | Staging acceptance, preservation checks, release documentation and operational recovery. Production/live payments remain a separate release. |

Minimum acceptance cases:

- Ten concurrent one-seat customers competing for three seats admit exactly
  three; multi-seat requests cannot exceed the same capacity.
- Concurrent use of one voucher cannot allocate its value twice.
- Duplicate requests, repeated/out-of-order callbacks and callback-before-binding
  races preserve one booking/payment/redemption effect.
- Hold expiry, provider failure/cancellation and late payment after reassignment
  preserve capacity and money reconciliation without false confirmations.
- Full/partial voucher payment, expired/revoked/used voucher rejection, legacy
  import replay, and cancellation/refund reversal preserve balances and history.
- Staff booking, capacity changes, pay-on-arrival, schedule resync and rescheduling
  obey the same allocation rules.
- Worker crashes, mail ambiguity/retry, overlapping cron, delayed digest execution,
  daylight-saving transitions and test-data exclusion are observable and recover.
- No real customer mail, voucher use or charges occur in isolated/staging tests.

Use real concurrent database connections, not only sequential mocks. Each schema
slice needs a verified target-local backup/before-image and additive owner
migration; keep historical request IDs and provider bindings. Preserve accounts,
authentication and unrelated data. No uploads transfer is expected. Do not restore
an old whole table over newer bookings, redemptions or event history on rollback.

## Next action and prerequisites

Implementation and repeatable operations now live in the commerce plugin README.
Complete target migration, staging acceptance and scheduler installation. Capacity values and existing real reservations
must be confirmed before opening departures. Current voucher balances/terms and
the authenticated mail sender are prerequisites for their later activation,
not blockers to building the first isolated slice.

The implementation uses the additive v5 owner migration and private target-local
before-images. Current verification, deployment and remaining activation gates
are in `handoff_CURRENT.md`; operational commands are in the commerce README.

## Reference contracts

- [MariaDB row locks and transactions](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update).
- [Mollie payment states and expiry](https://docs.mollie.com/docs/handling-payment-status).
- [Mollie payment cancellation](https://docs.mollie.com/reference/cancel-payment).
- [WordPress mail result semantics](https://developer.wordpress.org/reference/functions/wp_mail/).
- [Host-driven WordPress cron](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/).
