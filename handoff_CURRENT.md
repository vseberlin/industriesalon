# Current handoff — 22 October 2026

Website source is `/home/vladimir/wp-website`; staging checkout is
`/srv/industriesalon/stage/repo`. Commerce 0.5.0 is deployed through GitHub
(`4666d81`, final code correction `9531b65`). Local and staging acceptance passed.
Production is untouched; staging remains noindex and uses test inventory/payments.

## Commerce upgrade and activation boundary

The five-slice WordPress booking upgrade is implemented and deployed in
`iss-commerce-lite` 0.5.0. The [commerce contract](plugins/iss-commerce-lite/README.md)
is the implementation/operations record; the [plan](docs/project/wordpress-booking-ledger-plan.md)
records the agreed scope. WordPress owns capacity; SuperSaaS supplies schedules.
The v5 additive migration is applied locally and on staging with verified private
before-images. It preserved all three local and all six staging requests. Target
users/usermeta are unchanged; existing payment callbacks continue normally.

Capacity is **30 people per departure**, explicitly confirmed by the user.
Local and staging each have 23 test departures. Nine legacy reservations (18 seats)
map to three departures and are retained in `legacy_review`; those departures
remain closed. The other 20 departures are open only as labelled test inventory.
Browser acceptance allocations are explicitly canceled and retain their history. Real inventory activation requires current reservation reconciliation.

The private SQL backup contains 2,283 historic bookings, including nine future
reservations (one paid/eight unpaid). The newer private YITH snapshot preserves
the 17 September expiry corrections. All 108 voucher records passed dry-run
validation and remain review-only; no real balances were imported or consumed.
Reconcile subsequent usage and establish one redemption authority before real
activation. Never put customer records/codes in Git or alter the source backups.
Private manifests, receipts and configuration before-image are under
`var/booking-upgrade-20261022/` locally and staging
`/srv/industriesalon/stage/var/commerce-upgrade-20261022/`. Each target has its own
private voucher lookup key in WordPress configuration; preserve it across releases.

Local verification: 218 request/payment/schedule assertions, 16 migration checks,
25 ledger assertions including real ten-process admission (three of ten for three
seats), concurrent voucher conservation, 16 worker assertions, 11 JS store tests,
PHP syntax, targeted PHPCS/PHPStan and ESLint. Chrome proved mixed
regular/reduced onsite confirmation and the 390px form without overflow. Staff
screen rendering is covered by CLI; the browser requires the normal staff login.
Staging Chrome proved the hosted Mollie sandbox payment, automatic callback
confirmation, persisted receipt and a 390px receipt without overflow. Repeated
HTTP callbacks caused no duplicate effects. Staff cancellation remains canceled
after provider reconciliation; synthetic booking #18 consumes zero seats.
Mailpit SMTP/API readback passed locally and on staging, including UTF-8 and the
zero-booking digest. Staff browser interaction still needs the normal staff login.

The independent commerce minute job is installed and actual cron runs are fresh
and successful. Host receipts/logs are under `/srv/industriesalon/stage/var/commerce/`.
Mail stays disabled pending authenticated sender setup and mailbox-delivery proof;
live payment keys are refused. The next operational step is current reservation
and voucher reconciliation plus one redemption authority before real cutover.
Existing non-commerce cron ownership and broader maintenance remain separate.
No uploads/content transfer is required. Preserve target accounts/authentication.
Do not restore old tables over later requests, redemptions or event history.
Close admissions with `ISS_COMMERCE_BOOKINGS_ENABLED=false` during recovery;
retain callbacks and the provider key until pending payments are reconciled.

The preceding Mollie baseline retained two labelled staging synthetic payments;
they have no departure allocation and do not consume real inventory or enter
staff digests. Receipt reads now use authoritative processed state; the worker
and callbacks perform provider verification. Local runtime has no provider key.

## Preserved content and operational constraints

The preceding Place review/contribution update remains intact. Its private
evidence is in `var/place-contributions-20261022/` and
`var/register-review-20261022/`; implementation history is in Git/CHANGELOG.
Canonical documents (191 valid on staging), local-only Places, ADMOS draft, upstream component
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
Final staging routes, containers, worker and PHP log checks passed. Git deployment must
use the ordinary source-file umask (`022`); scope private backup permissions to
the private files, so newly checked-out PHP remains readable by FPM.

Earlier human gates remain: Kiezfonds exact share, children's-photo rights,
actual 200% zoom, physical touch and screenreader acceptance. No additional
editorial work is queued by this booking plan; production activation is separate.
