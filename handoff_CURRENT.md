# Current handoff — 22 October 2026

Website source is `/home/vladimir/wp-website`; staging checkout is
`/srv/industriesalon/stage/repo`. Mollie implementation `ad632d0` is on GitHub
and deployed to staging; this documentation checkpoint follows it. Production
is untouched and staging remains noindex.

## Booking and Mollie test checkout

The existing WordPress request flow now supports hosted Mollie test payments,
verified webhooks, a private uncached receipt, and payment status in
Tools → ISS Anfragen and its CSV. Staff workflow status remains independent.
The user chose WordPress booking/payment first: staff reserve and confirm seats
in SuperSaaS. No SuperSaaS writes or automatic seat confirmation were added.
The shared dialog retains onsite payment and offers card/PayPal test checkout.
Live keys are rejected; notification emails remain disabled. The test key exists
only in private staging configuration. Local runtime has no provider key.

Owner schema migration `ops/migrations/2026-10-22-commerce-mollie.php` is applied
on both targets. All three pre-existing requests on each target are unchanged.
Private before-images and acceptance evidence are in `var/mollie-20261022/`
on the respective checkouts; no uploads/content transfer was needed. Target
accounts, authentication and roles were not migration inputs.

Verified: 207 isolated PHP assertions, 16 migration checks, nine JavaScript
store tests, PHP lint/PHPCS/PHPStan and JS lint. A real staging €24 card test
completed; Mollie's webhook returned 200 before the browser checked the receipt.
Repeated callbacks with a forged status leave the paid row entirely unchanged.
Invalid receipt tokens return uncached 404. Desktop and 390px mobile receipts
work without horizontal overflow; the browser console reports no errors.

Unsuccessful PayPal method attempts leave the provider payment open. The UI
correctly shows unpaid and preserves the verified resume link, including when
Mollie omits that optional link. Terminal canceled/failed/expired, refunds and
chargebacks are covered by isolated tests, not claimed as live terminal outcomes.
Two visibly labelled synthetic bookings remain as test evidence (paid/open);
do not reserve real seats for them. No real money or notification email was sent.

Provider outage recovery, the 45-minute creation retry bound, CLI reconciliation
and rollback are in [the commerce contract](plugins/iss-commerce-lite/README.md).
Disable new checkout before rollback; retain payment columns, requests, the key
and callback support until pending payments are reconciled. Never restore the
old request table over later bookings. The requested staging scope is complete;
production/live activation and customer-email delivery are separate work.

## Preserved content and operational constraints

The preceding Place review/contribution update remains intact. Its private
evidence is in `var/place-contributions-20261022/` and
`var/register-review-20261022/`; implementation history is in Git/CHANGELOG.
All 84 canonical documents, local-only Places, ADMOS draft, upstream component
mapping, editorial history/media/relations and qualified source conflicts remain
protected. Existing signed upload generations and private intake moderation
must not be overwritten by historical backups.

The verified full staging backup remains at
`/srv/industriesalon/stage/backups/iss-backup-20261022T094828Z/`
(119 tables, all seven artifacts verified). Target accounts remain target-owned;
the unrestricted SQL importer stays disabled. Follow
[content sync](docs/infrastructure/sync.md). Never replay retired credentials,
sessions or authentication backups. Private incident evidence remains retained.
Project UAT retains its existing picker permissions.

Staging/touchtable PHP 8.3.36 emergency images remain active. Their separately
scoped maintenance is replacement with a verified patched official image under
[the runtime procedure](docs/infrastructure/staging.md#php-runtime-security-update).
Current staging containers and commerce checks are healthy. Git deployment must
use the ordinary source-file umask (`022`); scope private backup permissions to
the private files, so newly checked-out PHP remains readable by FPM.

Earlier human gates remain: Kiezfonds exact share, children's-photo rights,
actual 200% zoom, physical touch and screenreader acceptance. No additional
infrastructure or editorial work is queued by this booking implementation.
