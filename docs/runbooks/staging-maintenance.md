# Staging maintenance

Repository implementation is prepared and tested locally. Installation changes
the target scheduler, backups and PHP limits and belongs to an authorized release.
Use the existing Compose services and owner jobs; no daemon or extra queue.

## Verified target and activation

Read-only inspection on 11 October 2026 found a clean staging checkout at
`6c8cea7`, healthy WordPress 7.0/PHP 8.3, MariaDB 11.8 and Redis containers,
118 GB free, and no failed host services. Redis has no WordPress drop-in.
Traffic WP-Cron is enabled; the only user cron entry collects the server
baseline. The existing `ops/backup-stage.sh` has no schedule, archive verification
or retention. Latest manual backup is `20260922-132743`. The graph queue has
66 dirty rows dating from 30 June. These are observations, not live guarantees.

For an approved release, inspect services, logs, mounts, disk and backups again
under [server operations](../agent/server-operations.md). Deploy through the
[Git exchange](git-exchange.md); preserve target templates and data. Use
target-local before-images and owner migrations for seeds/schema/idempotency and
owner setup. Never copy the development database onto staging.

1. Pause scheduling during the configuration transition. Preserve the previous
   target scripts, Compose/configuration and crontab in a private before-image.
   Confirm the target checkout is clean and the `mariadb` service supplies
   `MARIADB_USER`, `MARIADB_PASSWORD`, `MARIADB_DATABASE`; never print values.
2. Run the two repository scripts in `check` mode against
   `/srv/industriesalon/stage`. Install these reviewed scripts together into its
   existing `ops/` directory. Required tools: Bash, Docker Compose, flock,
   timeout, Python 3, Git, tar, gzip and coreutils. Use the existing unprivileged
   deployment account and existing WP-CLI image; the runner never pulls images.
3. Set `DISABLE_WP_CRON=true` in target WordPress configuration (including CLI).
   Re-check it in both runtimes. Do not disable cron without completing this
   transition or restoring the previous setting if activation fails.
4. With all cron runners paused, run
   `ops/migrations/2026-10-11-maintenance-cron.php` through `wp eval-file` in
   `check`, `apply /PRIVATE/new-before.json`, then `verify` mode with
   `--use-include`. It refuses active owners/callbacks and removes only four
   retired Jetpack/WooCommerce/ACF hooks. Other unknown hooks need owner review.
   Historical orders, tables and plugin data remain intact. Rollback restores
   selected before-image events through WordPress APIs, never the entire cron
   option over newer events.
5. Run one monitored `ops/maintenance-stage.sh run /srv/industriesalon/stage`.
   This executes due jobs, including remote SuperSaaS synchronization and any
   due Newsletter/mail tasks: target mail mode and approved recipients must be
   verified first. An initial missing job receipt is an error until the owner
   actually succeeds; do not manufacture success timestamps. Observe one normal
   due cycle or deliberately run the exact owner hook during this release.
6. After the manual run and recovery checks, add this single user cron entry;
   retain the existing baseline collector:

   ```cron
   */5 * * * * /srv/industriesalon/stage/ops/maintenance-stage.sh run /srv/industriesalon/stage
   ```

## Execution and failure recovery

One host `flock` covers the sequential path: due WP-Cron, graph reconciliation
(50 rows, 30 seconds, three attempts), a verified daily backup when due, and
`wp iss maintenance-status`. Each CLI container has a fixed identity and a
180-second outer limit. A timeout stops that container before unlocking; an
existing orphan is reported and blocks new CLI work until inspected. Independent
backup/graph phases can still run after another phase fails. Backup has a
30-minute limit and its own lock; partial archives never advance success.

Private `var/maintenance/last-run.json` and `last-run.log` describe the latest
run. `maintenance-stage.sh status ROOT` exits nonzero on failure or a receipt
older than ten minutes. Inspect this with the existing host baseline/operations
review, and check the ISS Operations status screen. Graph dead rows, stale
processing (>15 minutes), dirty age (>one hour), overdue cron and missing
callbacks are errors. SuperSaaS success must be within two hours; fallback
projection and Set decay within 26 hours. Set decay marks at most 500 expired
unretained rows stale per run; it deletes neither records nor files.

On failure inspect the phase exit code and private log, then its owner's
diagnostics. Check credentials/connectivity for synchronization, table/schema
health for SQL failures, and disk/permissions for backups. Retain failed graph
rows and their error; repair the cause before an explicit owner retry. Do not
erase a failure, release somebody else's lock, blindly clear cron, or run a
full rebuild to silence status. If the runner is terminated, its old receipt
becomes overdue; inspect the named container before retrying. This provides
observable status; it does not install an external alert service or recipient.

## Backups and restoration

The replacement for the existing backup script creates mode-0700 directories
and mode-0600 files containing seven artifacts: database, uploads, raw Event
Drop storage, tracked code, runtime Compose/environment, installed WordPress
core/plugins/themes/config/MU plugins, and code commit. The runtime archive
also preserves installed third-party packages that are absent from Git; uploads
and the mounted intake interface are excluded from that archive because their
owners are captured separately. The verified mount for private intake is
`repo/var/event-drop-storage`, distinct from `shared/uploads`. Secrets stay in
private backup artifacts. Source archives/preservation drives are outside scope.
Dirty tracked code is refused, never reset. Space must cover uploads, intake and
installed runtime size plus a 3 GiB reserve. A changing file causes tar failure and a retry; these are
live backups, not a cross-database/filesystem transaction. Quiesce intake/editing
for a coordinated pre-release restore point. Runtime images/core versions must
also be recoverable from the preserved Compose configuration and image digest.

Every new archive is fully read, the SQL contains schema, and all seven files
are SHA-256 hashed before the completed directory and success receipt appear.
Retention touches only complete directories carrying this script's exact
owner/root manifest: keep the newest per day for 14 available days and newest
per ISO week for four weeks. Manual backups, partials, foreign directories and
symlinks are untouched. Retention is not archival-source deletion authority.

Monthly, hash-check retained artifacts and restore a selected set in isolation:
disable cron/mail/external HTTP; recreate matching core/image, restore code and
configuration with target-local secrets/URL adjustments, import SQL, restore
uploads and intake paths/permissions, compare tables and sample file hashes,
then exercise routes, login, owner health and a quarantined intake recovery.
Never validate by restoring over the running site. Keep an independently stored
copy for host-loss recovery; off-host destination/setup remains an operational
decision, not an automatic copy to other workstation drives.

Local refactor evidence: the 118-table database restored into an isolated
WordPress 7.0 fixture; 117 table checksums matched and only volatile options
differed. Six representative code/config/upload files restored from the saved
archive and matched source hashes. A full multi-GB uploads/intake restoration
was not performed locally. Backup orchestration/retention tests use tiny fake
Docker fixtures, not live staging backups. Private receipts are under
`var/refactoring-20261008/` on the development machine.

## Scope the Event Drop limits

The inspected staging FPM currently allows 4096M upload/post bodies and unlimited
execution/input time globally. Nginx already limits ordinary routes to 128M and
the existing `/event-drop/` location to 4G. The proposed paired change is:

- Replace the mounted global `docker/php/uploads.ini` source in target Compose
  with `repo/ops/maintenance-public-php.ini` (128M, 120-second public limits).
- In the existing Event Drop Nginx location, retain its routing/authentication,
  `client_max_body_size 4G`, `fastcgi.conf`, `SCRIPT_FILENAME`, `PATH_INFO` and
  `fastcgi_pass`; add these location-scoped settings:

  ```nginx
  fastcgi_param PHP_VALUE "upload_max_filesize=4096M\npost_max_size=4096M\nmax_execution_time=1800\nmax_input_time=1800";
  fastcgi_read_timeout 1800s;
  client_body_timeout 1800s;
  ```

This preserves the existing intake body ceiling and bounds previously unlimited
PHP time. `PHP_VALUE` must be a server-owned constant, never request input.
Inspect effective FPM pool `php_admin_value` settings before activation; they
can prevent overrides. Validate Compose, `nginx -t`, and FPM configuration;
recreate only WordPress if the mount changes, then reload validated Nginx.
Check actual FPM values (CLI is insufficient), normal uploads and signed intake
below/above limits, timeout handling, storage permissions and editor promotion.
Large-file/FPM acceptance is a release gate, not established by local CLI tests.
Rollback restores the paired mount/location before recreation/reload.

## Update cadence

Review supported WordPress/PHP/plugin releases monthly and security advisories
promptly. Use official release information at update time; do not assume the
versions observed above remain supported indefinitely. Test a selected release
on the isolated fixture with matching target core, owner/schema checks, route
and three-engine frontend checks, then staff/editor acceptance and a verified
backup before coordinated staging delivery. Do not add unattended package,
plugin or core upgrades to this runner. Record release/rollback commits and
acceptance separately from successful scheduled maintenance.

## Consent and privacy review

After WordPress, theme or plugin updates, new embeds/providers, tracking or
newsletter changes, and at least every 30 days, review **Tools → Site Health**
and **Operations → Datenschutz und externe Inhalte**. These use the same
read-only checks as `wp iss consent-check`. Updates invalidate browser acceptance;
a missing/stale receipt remains a warning until a matching complete report is
recorded. No new cron job or unattended settings repair is needed.

On each target, apply the paired consent settings migration once after deploying
the matching code. From the repository with the target's existing WP-CLI wrapper:

```sh
wp eval-file ops/migrations/2026-10-23-consent.php check --use-include
wp eval-file ops/migrations/2026-10-23-consent.php apply /PRIVATE/new-consent-before.json --use-include
wp eval-file ops/migrations/2026-10-23-consent.php verify --use-include
wp iss consent-fingerprint
```

Paths must be visible inside the target runtime. Keep the verified before-image
private; it contains target configuration. Preserve DB template overrides before
reconciliation. There are no schema/subscriber changes. Video preview attachments
require their paired private originals/manifest and the [owner import](../../plugins/iss-consent/README.md#local-video-previews); code alone does not restore thumbnails.
Purge any page/CDN caches after deployment so previously cached active embeds
cannot bypass the server-side gate.

Run the repository browser suite against that target, with the exact `home_url`
(origin/port/scheme included) and the fingerprint returned above:

```sh
PLAYWRIGHT_BASE_URL='https://TARGET' \
ISS_CONSENT_FINGERPRINT='TARGET_FINGERPRINT' \
ISS_CONSENT_REPORT='/PRIVATE/consent-browser.json' \
npm run test:consent
```

Use an installed Playwright browser; `PLAYWRIGHT_CHANNEL=chrome` selects installed
Chrome. On rate-limited staging, set `ISS_CONSENT_TEST_PAUSE_MS=45000`
to let request budgets replenish between scenarios; remote assertions allow
15 seconds. For protected staging, use the authorized test access arrangement. Do not
remove server authentication to run tests. The suite needs the published website
routes and video fixture named in `tests/e2e/specs/consent.spec.js`; update fixture
selection deliberately if editorial URLs change, keeping equivalent coverage.
It intercepts external origins, checks that there are no attempts before consent,
then tests separate choices, persistence, expiry, cross-tab withdrawal, the consent-gated
CARTO Atlas basemap, video library, newsletter links, keyboard/mobile and no-JS use.
It does not subscribe anyone or send messages. Screenshots and JSON are private
verification artifacts. Do not run the isolated PHP option-table fixture on a
production site.

After all five scenarios pass, copy the JSON privately to the same target runtime
and record it within one hour (using paths visible to WP-CLI):

```sh
wp iss consent-record-browser /PRIVATE/consent-browser.json
wp iss consent-check
```

The recorder rejects another origin/fingerprint, stale reports, failed, skipped
or missing scenarios. Record no receipt for an incomplete check. Separately verify
actual YouTube playback/CARTO tiles using voluntary test consent, then withdraw;
a missing private `INDUSTRIESALON_CARTO_KEY` is a separate configuration warning.
Never put that key in reports or Git. Stubbed responses establish gating, not provider availability. After newsletter
vendor upgrades inspect the adapter hooks/cookie names and verify confirmation,
profile and unsubscribe links in an isolated no-delivery fixture. Real mailbox
delivery tests require the existing explicit mail authorization.

Review provider/privacy terms and actual data flows monthly, including new
browser requests, scripts, storage, account cookies, SMTP supplier, processor
agreements, international transfers and deletion/retention practice. Update
`/verein/#datenschutz` when those facts change. Material changes to consent
purposes/providers also require a new `ISS_CONSENT_POLICY`. Hetzner hosting is
confirmed; `industriesalon.de` is the newsletter sender domain, not evidence of
the delivery supplier. Verify that supplier before production release. Technical
health status does not certify legal compliance or completion of this manual
review. Record the review date/findings with the private release evidence.

On failure keep the affected external integration or newsletter delivery off,
fix through its existing owner, rerun the relevant checks, then record acceptance.
See [ISS Consent](../../plugins/iss-consent/README.md) for boundaries and recovery.
