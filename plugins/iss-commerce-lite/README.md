# ISS Commerce Lite

Lightweight universal request capture for Industriesalon domain plugins.

## Scope

Owns:

- universal booking, inquiry, and order submit endpoint
- durable lightweight request storage
- request admin review/export/status handling
- opt-in request notification emails
- public write endpoint spam/rate-limit guards

Does not own:

- calendar CPT storage
- slot mapping/cache
- SuperSaaS settings, occurrence sync, or public booking-slot reads
- timeline rendering
- tour, event, exhibition, or publication content models
- seat reservations or writes to SuperSaaS

## Current Role

Right now this plugin owns:

```txt
POST /wp-json/iss-payments/v1/request
Tools > ISS Anfragen
wp iss-commerce-lite verify
```

`/iss-payments/v1/request` is the only public intake route for shared booking,
inquiry, and order flows. It accepts `intent` values for `booking`, `inquiry`,
and `order`.

SuperSaaS settings, occurrence ingestion, and `GET /wp-json/iss/v1/booking-slots`
belong to `iss-occurrences`.

Requests are stored in `wp_iss_payments_lite_requests`. The table keeps the
existing `request_kind` column name, but new writes store normalized intent
values there.

By default the supported payment method is `onsite`. Online settlement methods
must be enabled by a provider integration through
`iss_payments_lite_supported_payment_methods`; do not accept a method until the
provider actually creates/settles payment state.

Request notification mail is opt-in from the admin screen. This keeps staging
safe unless outbound mail has been deliberately approved for the environment.

## Mollie test booking payments

The existing booking request owns one Mollie test payment. Enable new checkout
with `ISS_MOLLIE_ENABLED=true` and a `test_` key in `ISS_MOLLIE_API_KEY`, defined
only in the target's private `wp-config.php`. Live keys are rejected. Keep test
mail disabled. Local fixture tests intercept all provider HTTP and mail.

Apply `ops/migrations/2026-10-22-commerce-mollie.php` through WP-CLI with
`check`, then `apply /PRIVATE/new-before-image.json`, then `verify`. Its private
before-image preserves existing requests; the additive columns and unique payment
binding belong to the existing request table. No content or uploads transfer.

The shared booking dialog offers onsite payment or hosted Mollie test checkout
(card/PayPal). Prices and regular/reduced quantities come from existing source
owners. Inquiries and publication orders remain on their current request path.
Staff still reserve/confirm seats in SuperSaaS; payment never changes availability
or marks the booking confirmed. The admin request table and CSV show payment
status separately from staff workflow status.

`POST /iss-payments/v1/mollie/webhook` accepts a payment ID, then reads Mollie over
HTTPS with the private key. It verifies the exact binding, metadata, amount,
currency and test mode before writing status. Unknown/not-yet-bound IDs and
provider/storage failures return 503 for redelivery. Forged status fields have
no effect. A per-booking DB session lock serializes creation and status reads.
Repeated callbacks neither dispatch booking hooks nor send mail.

Creation freezes the provider payload before the first HTTP request and retains
one idempotency key. Lost responses retry the same payment for at most 45 minutes
(Mollie's documented retention is one hour). After that, reconcile the payment ID
from the dashboard; never blindly create another payment:

```sh
wp iss-commerce-lite payment-refresh BOOKING_ID --payment-id=tr_PROVIDER_ID
```

The command verifies the same binding and refuses a different existing payment.
Without `--payment-id` it refreshes the known payment. Disabling new checkout does
not disable callbacks when the test key is retained. Do not rotate/remove the key
until existing test payments have been reconciled.

The hosted return opens the existing booking dialog. An unguessable receipt token
stays in the URL fragment and travels only in the body of
`POST /iss-payments/v1/payment-status`; it is not put in server URLs or referrers.
The uncached receipt excludes customer name/email and always rechecks Mollie.
The screen distinguishes paid, open, pending, authorized, failed, canceled,
expired, refunds and chargebacks. Failed/canceled/expired requests permit a new
booking; a retry of the original identity continues to describe its original
payment. Staff confirmation and refund execution remain manual.

Verification: `tests/e2e/bin/commerce-idempotency.php` includes the isolated
`commerce-mollie.php` scenarios; run with cron disabled, process-local cache and
an admin CLI user. `commerce-migration.php` checks backed-up upgrades and failure
recovery. Browser acceptance must additionally prove a real test-mode checkout,
a provider webhook and the return screen on staging.

Rollback: disable new checkout first. Retain additive payment columns and requests;
restore previous code and its schema option only if required. Never restore an old
request table over newer bookings. Retain the provider key/callback code until
pending payments have been reconciled. Production/live mode is not activated.
