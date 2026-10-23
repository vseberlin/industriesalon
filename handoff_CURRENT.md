# Current handoff — 23 October 2026

Website source is `/home/vladimir/wp-website`; staging checkout is
`/srv/industriesalon/stage/repo`. Commerce 0.5.0 is deployed through GitHub
(`4666d81`, final code correction `9531b65`). Local and staging acceptance passed.
Production is untouched; staging remains noindex and uses test inventory/payments.

## SuperSaaS admin refactor — staging release

Occurrences 0.2.0 and commerce 0.5.1 are deployed through GitHub at `e668797`.
Local and staging acceptance passed; production is untouched.
`Operations → Termine & Synchronisierung` now separates overview, review,
tour/event series and connection settings. `Buchungen & Anfragen` separates
requests, capacity, bookings, delivery and settings with contextual actions.
The [occurrence admin contract](plugins/iss-occurrences/README.md) documents
mapping precedence, validation, navigation and the isolated regression command.

Local checks passed: 96 isolated admin assertions, 218 commerce assertions,
31 public occurrence assertions, targeted PHPCS/PHPStan/PHP syntax/ESLint/Stylelint
and authenticated Chrome navigation, filter recovery and desktop/tablet/mobile
layouts (including expanded mapping forms). Real mappings, settings, bookings
and inventory were preserved by the isolated tests. No real sync was manually
triggered, no mail sent, no accounts/permissions changed. There is no schema,
content or uploads migration. Existing target data must remain target-owned.
Staging passed 96 isolated admin checks, 31 public-date checks, read-only commerce
verification, seven HTTP page/asset checks and authenticated Chrome desktop/mobile
review. The first admin run detected a changing options checksum during concurrent
page checks; the standalone rerun passed with all protected tables unchanged.
Before/after release hashes matched for accounts, roles, URLs/noindex, sync/commerce
settings and capacity. The existing worker continued polling its two retained test
payments (changing check timestamps); no booking or mail action was invoked.
Containers and worker remained healthy; no new FPM/Nginx errors. No restart or
data transfer was needed. Source rollback target is `35e07ca`; retain target data.
All seven hashes were reverified for the 124-table backup at
`/srv/industriesalon/stage/backups/iss-backup-20261023T111347Z/`.
Private preservation receipts: `var/supersaas-admin-release-20261023/` locally.
No further release step is queued; production activation remains separate.

## Consent and privacy — staging release

Code `067dc7b` and paced browser checks `5d199ba` are on GitHub and staging.
The paired consent settings migration and offline preview import are applied.
All 28 published videos have local previews (27 imported, one editorial image
preserved); no subscriber/schema transfer or account import was performed.
Atlas keeps CARTO with its existing staging key. The compact bottom popup offers
accept-all, decline-all and separate choices; the native privacy notice and
newsletter double opt-in/no-tracking adapter are deployed.

Local acceptance: 26 isolated PHP assertions, five browser scenarios, native
core-block validation, targeted PHPStan/PHP syntax/ESLint/Stylelint and diff checks.
The same 24 pre-existing PHPCS findings in `iss-content/includes/videos.php` and
one pre-existing footer image validation warning remain; no new findings.
Staging acceptance: all five browser scenarios passed; the matching receipt is
recorded, all 11 privacy checks pass and Site Health reports good. Manual Chrome
verified actual YouTube embedding and CARTO tiles/zoom with 76 markers. All 27
imported originals match the manifest; generated sizes exist and repeat import is a no-op. Before/after
digests prove target users/usermeta, roles, URL/noindex, CARTO key and newsletter
tables are unchanged. Operations rendering passed via CLI; authenticated
interactive admin/editor review still uses the normal staff login. Services are
healthy and release checks found no new PHP warnings/fatals. No email was sent.

Verified full before-release backup:
`/srv/industriesalon/stage/backups/iss-backup-20261023T111347Z/`
(124 tables, seven fully read/hash-verified artifacts). Private local evidence:
`var/consent-20261023/`; target evidence/settings and thumbnail before-images:
`/srv/industriesalon/stage/var/consent-20261023/`. The 27-image originals archive,
checksum and manifest travelled privately and were applied through the video
owner; target attachment IDs/image sizes were generated locally. Never restore
old accounts or subscriber data for rollback. Preserve later editorial choices.

[ISS Consent](plugins/iss-consent/README.md) and the
[maintenance procedure](docs/runbooks/staging-maintenance.md#consent-and-privacy-review)
cover update invalidation, 30-day checks and recovery. Staging checks use
`ISS_CONSENT_TEST_PAUSE_MS=45000` to respect its existing request limits; server
protections are unchanged. Local CARTO also uses private runtime configuration.
Hetzner hosting is confirmed. Before production confirm the actual newsletter
delivery supplier (`industriesalon.de` is the sender domain), processing
agreements and retention practice. Production remains untouched.

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

The latest verified full staging backup is
`/srv/industriesalon/stage/backups/iss-backup-20261023T111347Z/`
(124 tables, all seven artifacts verified). Target accounts remain target-owned;
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
