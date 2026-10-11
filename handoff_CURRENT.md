# Current handoff — 11 October 2026

## Scope and delivery state

The six-phase [WordPress refactoring plan](docs/project/wordpress-refactoring-plan.md)
is complete within the authorized local scope. The user authorized commits and
execution through completion; stepwise confirmation is superseded. Work lives in
`/home/vladimir/wp-website` on `main`. Commits are local and unpushed; freshly
fetched `origin/main` is `fe849bc`, with no incoming commits. Use Git history for
implementation details. The distinct `/home/vladimir/wp` mixed checkout remains
dirty and is not a commit/cleanup target. No home-level files, other drives,
preservation sources or staging state were changed.

Staging was inspected read-only on 11 October: clean code at `6c8cea7`, WordPress
7.0/PHP 8.3, healthy services, 118 GB free. Traffic WP-Cron is still enabled and
66 old graph rows remain queued. These observations are not deployment evidence.
The prepared scheduler, backup/retention scripts and scoped PHP limits have not
been installed there; see [maintenance activation/recovery](docs/runbooks/staging-maintenance.md).

## Implemented and verified

- Versioned seed/setup gates, checked owner schema installation, transactional
  graph/Set writers, ownership-aware queue processing, Event Drop recovery and
  commerce retry idempotency. Public requests no longer install/backfill schemas.
- Native GET programme controls, core Interactivity/router navigation and one
  native booking/inquiry/order dialog. The subsequent user-requested full
  calendar adds an availability month picker to inline tours/dialogs and a
  Liste/Kalender programme month view with existing day results. Flatpickr and
  obsolete controllers/cache helpers are removed. Source visibility, capacity and Berlin dates are checked
  at intake. Booking records requests; it does not reserve capacity or confirm
  payment. Email dispatch is not exactly-once across a process crash.
- Bounded/private SQL search, batched read-only relations/occurrences and coalesced
  ordinary-save projections through the existing queue. Explicit transactional
  writers remain immediate. Curated menu selection stays on its existing owner:
  occurrence opt-in/recurrence semantics failed the equivalence check.
- Graph/CLI/relations/programme responsibilities are split within their owners;
  admin/CLI code loads in context. Project/team ordering uses native controls.
  Existing saved-layout CSS compatibility and unrelated Atlas work are preserved.
- Monitored owner jobs, bounded non-destructive Set decay, one locked host runner,
  seven-artifact verified backups with guarded retention, inactive-owner cron
  migration and paired public/intake limit proposal are ready for release review.

Refactor closeout checks: 355 backend assertions across seven affected suites, 23 disposable
host-runner/backup assertions, PHP lint/PHPCS/PHPStan, shell syntax and 63
Chrome/Firefox/Linux-WebKit browser scenarios pass. The scoped cron migration
passed check/apply/verify on the isolated fixture; every other event was retained.
Earlier broader checkpoints (3,134 assertions; later extraction 1,821) retain
separate receipts and scopes. No real notification, external sync or real queue
drain was executed for verification. Public pages were measured locally; reduced
calendar/tour assets and increased publication assets are not a uniform speedup.

Calendar extension: 155 backend checks and final 36 Chrome/Firefox/Linux-WebKit
booking/month/commerce scenarios pass, including phone, keyboard, timezone and
no-JavaScript cases. PHP/PHPCS/PHPStan and JS/CSS checks pass. The broader 60-case
run passed 59 with one WebKit page-close failure in an existing router case;
that case passed three consecutive reruns. Warm full local renders measured
113 SQL queries for the list and 79 for the month view (zero writes in each).
Booking month/day changes reuse loaded slots without extra requests. Changed
frontend assets add 2,728 computed gzip bytes; this is not a staging speed claim.

## Runtime, recovery evidence and next action

`wp_app` still serves website plugin/theme/receiver code from this checkout;
core/database/uploads/intake remain in `wp`. Re-inspect mounts before testing.
Use Compose project `wp` with both `/home/vladimir/wp/docker-compose.yml` and
`var/refactoring-20261008/website-code.yml`; CLI wrapper is
`var/refactoring-20261008/wp`, with `DISABLE_WP_CRON` explicitly enabled for checks.
The isolated WordPress 7.0 fixture (`iss-refactor-wp70`, loopback 8097) is stopped;
its private core/database/Compose files and blocked-mail/HTTP configuration remain.

Private ignored evidence/backups: `var/refactoring-20261008/` (0700), especially
`wp70/final-*.log`, `maintenance-runner-tests.log`, `wp70/restore-verification.json`
and `file-restore-verification.json`; calendar receipts are `wp70/month-*.log`,
`month-calendar-request-queries.json` and `month-calendar-assets.json`.
A 118-table database restore passed (117
matching checksums; volatile options differed), and six representative files
restored with matching hashes. Full multi-GB uploads/intake restoration remains
a release gate. Never restore these snapshots over later editorial work.

Next is a separately authorized GitHub/staging release and acceptance, following
the maintenance runbook. Deliver matching code with four scoped artifacts:
`2026-10-08-taxonomy-seeds.php`, `2026-10-11-commerce-idempotency.php`,
`2026-10-11-owner-setup.php`, `2026-10-11-maintenance-cron.php` under
`ops/migrations/`, each with target-local before-images and check/apply/verify.
Taxonomy/commerce are already applied to the original local site; owner setup and
cron cleanup were exercised only on the isolated fixture. No uploads transfer is
needed for this refactor. The calendar extension changes only code/assets/tests;
it needs no additional DB or uploads artifact. Preserve DB template overrides (`single-ausstellung`,
`page-projekte`, `page-publikationen`), editorial recovery, private contacts/Sets,
historical orders, moderation and uploads. Do not copy the local DB to staging.

Remaining acceptance: real editor save/reload/block validity, staff workflows
including both new calendar views,
macOS Safari (Linux WebKit is not Safari acceptance), full file restoration and
actual FPM/Nginx large-upload behavior. These are release gates, not unfinished
local code slices. Unrelated TODOs and the inactive map thread remain separate.
