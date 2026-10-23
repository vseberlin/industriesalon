# Current handoff — 23 October 2026

Website source is `/home/vladimir/wp-website`; staging checkout is
`/srv/industriesalon/stage/repo`. Commerce 0.5.0 is deployed through GitHub
(`4666d81`, final code correction `9531b65`). Local and staging acceptance passed.
Production is untouched; staging remains noindex and uses test inventory/payments.

## Consent and privacy — release prepared

Consent implementation and local acceptance are complete. GitHub/staging delivery
is authorized and in progress from baseline `4e31fd0`; target acceptance follows
the paired code, settings and preview-media import.
See [ISS Consent](plugins/iss-consent/README.md) and the existing
[maintenance runbook](docs/runbooks/staging-maintenance.md#consent-and-privacy-review).
YouTube/CARTO have separate choices plus accept-all/decline-all in a compact
nonmodal bottom popup. Atlas retains CARTO; the added static fallback has been
removed. The user confirms its API key is already configured on staging. All 28 published videos
now have local previews: 27 imported thumbnails and one preserved editorial image. Newsletter
double opt-in/no-tracking policy and the native German privacy notice are updated.
Site Health and Operations flag updates, settings drift and a 30-day browser
review. The complete matching browser receipt is recorded locally.

Passed: 26 isolated PHP assertions, all five browser scenarios (including mobile,
keyboard, no-JS, expiry and cross-tab withdrawal), native core-block validation
for all 26 privacy-section blocks and the newsletter pattern, targeted PHPStan,
PHP syntax, ESLint, Stylelint and diff checks. Operations/Site Health rendering
passed via CLI; interactive admin/editor checks require the normal staff login.
The footer retains its identical pre-existing image validation warning. PHPCS
retains the same 24 pre-existing errors in `iss-content/includes/videos.php` as
HEAD; other changed production PHP passes. Manual Chrome checked privacy layout,
Atlas consent controls and actual YouTube embedding. The existing staging CARTO
key is now installed in the ignored local WordPress runtime configuration, with
a verified private before-image (`wp-config-before-carto.php`). No staging
configuration was changed. Manual Chrome proved actual CARTO tiles (18/18 loaded),
street/river basemap, markers and zoom after consent. All local consent health
rows now pass. The key is absent from Git and shared documentation.

Private before-image, test evidence and runtime tools: `var/consent-20261023/`.
For this release, deploy the paired code/settings migration with
target-local backup, preserve template overrides/accounts and existing CARTO
configuration, purge page caches, and rerun target acceptance. No schema or
subscriber transfer; the canonical map assets already exist. Video previews
require `var/consent-20261023/video-thumbnails.tar.gz`, its SHA-256 and
`thumbnails-manifest.json`, paired with the documented offline `wp iss-content
videos thumbnails --source-dir=... --apply --before=...` API import. All 27
originals were hash-verified (about 1.1 MB); target IDs/sizes are regenerated and
existing featured images are preserved. `thumbnails-before.json` records rollback.
Hetzner hosting is confirmed. Before production verify the actual newsletter delivery
supplier (the supplied `industriesalon.de` is only the sender domain), processing
agreements and retention practice. No newsletter or user mail was sent.

## Tour panel consistency

Shared Führungen panel fix `b54eae9` is deployed locally and on staging. All 16
published tours passed browser layout checks at 1920, 1024 and 390px on both
targets: uniform panel widths, readable dates/status labels and no horizontal
overflow. The Kiez booking link still opens the selected-date form. Targeted
Stylelint, PHP syntax/PHPCS and native template roundtrip passed. The site editor
loads the native Groups with no hero validation warnings and no unsaved changes;
an existing footer image validation warning is separate from this change.
Date rows now omit repeated tour names; booking-dialog titles are preserved.
The theme owns `single-fuehrung`; no DB/content/uploads transfer is required.
Private layout receipts are in `var/tour-panels-20261022/` locally.

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
