# Current handoff — 13 October 2026

## Editorial workspace and Place contract review

Article workflow verification is committed locally as `6b60774`, following
chooser guidance `242593c`; nothing is pushed or deployed. The accepted eight-type
workflow passed Chrome save/reload, keyboard, paste, recovery and cancellation
checks, 382 storage assertions and 41 UI tests. See the audit for exact scope.

The completed Place contract review is accepted for local commit.
There are 84 Places; only Kino Spreehöfe (12899) has enabled/stored Place JSON.
Its six epochs match the register projection; the effective template is theme-backed.
Five disposable save probes exposed a contract gap: missing epoch title/era/function,
multiple current phases or no remaining epoch pass JSON validation and report a
successful save while leaving the previous projection in place. The fixture and
its projections are removed; live pilot JSON and epochs remained identical.

Next: guard Place canonical/native saves using the projection owner's validation,
retain invalid drafts for recovery, and test a corrected retry. Then align
intro/present-day fields and ordering with the dossier renderer, and resolve the
separate contribution contract. The registered upload gesture renders nothing;
template links use a legacy `ort` identifier the intake handler cannot resolve,
and Places are outside its eligible types. No upload was submitted. See the
[Place review](docs/project/editorial-consolidation-audit.md#place-contract-review--13-october-2026).

This slice changes documentation only. No SQL, uploads or template artifact is
required. Staff UAT, Firefox and external Word/Docs paste remain release checks.
Effective page/report/publication/Place templates are file-backed;
Ausstellung override 26309 is retained. The earlier CPT commit's archive
collection slot still needs target override review at release.

## Scope and delivery state

The six-phase [WordPress refactoring plan](docs/project/wordpress-refactoring-plan.md)
is complete within the authorized local scope. The user authorized commits and
execution through completion; stepwise confirmation is superseded. Work lives in
`/home/vladimir/wp-website` on `main`. The accepted calendar/homepage and Repair-Café
checkpoint joins the completed refactor for GitHub exchange on exit; use Git
history and `HEAD` / `origin/main` for delivery refs. Staging deployment remains
separate. The distinct `/home/vladimir/wp` mixed checkout remains
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
  Liste/Kalender programme month view with existing day results. The requested
  live staging calendar/time chooser now uses one shared compact picker skin
  across all three surfaces. Choosing a time reveals the request fields.
  `/kalender/` keeps compact period/type buttons above the grid and the programme
  results alongside it; type selection submits the existing native GET form.
  Flatpickr and obsolete controllers/cache helpers are removed. Source
  visibility, capacity and Berlin dates are checked
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

Calendar design checkpoint: 70 backend checks and 60 Chrome/Firefox/Linux-WebKit
booking/month/commerce/filter/router scenarios pass, including phone, keyboard,
timezone and no-JavaScript cases. The final return-to-selected-date focus check
also passes in all three engines. PHP/PHPCS/PHPStan and JS/CSS checks pass.
Live staging was compared read-only in the browser. Desktop/phone review confirms
the shared date tiles, date/time/request sequence and filters above the calendar,
without overflow. Earlier warm full local renders measured 113 SQL queries for
the plain list and 116 for calendar plus results (zero writes in each); the earlier 79-query measurement
was for the retired grid without its adjacent listing. Booking month/day changes
reuse loaded slots without extra requests. These are local, not staging timings.

Accepted programme presentation: the calendar mockup and
homepage share Programme / Compact programme rows, large dates, existing images
and booking actions; running exhibitions show Läuft bis and their closing date.
The active editorial `front-timeline` slot and Gutenberg fallback now use the
existing program-cards block's automatic feature plus four compact dates below
Demnächst. Existing selection/expiry rules choose the feature, including a
current-exhibition fallback and expiring editorial focus; its source is removed
from the adjacent list. Missing images and empty states need no manual handling.
The layout stacks on phones and homepage cache headers prevent frozen dates.
All 122 read-only preview/rotation/overview/booking checks, targeted PHP/JS/CSS
checks, editor control smoke checks and template roundtrips pass; the preceding
78-check calendar/filter/booking checkpoint also passed. Chrome desktop/390px
review covers both pages, filters/month navigation, the feature, calendar link
and booking request-form handoff without submission. The latest presentation's
cross-engine rerun and real editor save/reload remain. No additional DB/uploads
artifact is needed; editorial JSON and existing media are unchanged. No staging
deployment was made.

Repair-Café's `/repair-cafe/` destination is now enforced by shared mapping,
SuperSaaS sync and public-read logic, including newly imported dates. Its cards
use the effective landing-template hero without copying attachment metadata.
All 96 destination/hero/overview/preview/booking checks, PHP lint/PHPStan and
desktop/390px review pass. PHPCS passes six changed runtime files; sync admin's
8 errors/21 warnings match the preceding `3ef84f7` baseline and are outside the changes. No live sync,
content/media write or new data artifact was required.

## Schöneweide landing

The locally applied landing now has five sections including the opening, three
selected Place cards, six historical phases and the existing Atlas. The prior
card previews are retained. Matching theme/section-contract changes and
`ops/migrations/2026-10-12-schoneweide-landing.php` plus its JSON payload are
delivered together in Git. Deploy matching code before applying the migration; it checks the
reviewed baseline and existing media before writing through `iss-editorial`.
It passed check/apply/verify, repeat-apply and rejection checks. Private recovery
is `var/schoneweide-20261012/before.json` (0600); preserve later editorial edits.
Chrome desktop/900px/390px and 31 backend checks pass; authenticated editor
save/reload and cross-engine UAT remain. No new uploads or staging change.

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
`month-calendar-request-queries.json`, `month-calendar-assets.json`, and the
current `wp70/staging-design-*` logs/screenshots; the preceding layout and query
receipts remain in `wp70/calendar-design-*` and `calendar-design-queries.json`.
A 118-table database restore passed (117
matching checksums; volatile options differed), and six representative files
restored with matching hashes. Full multi-GB uploads/intake restoration remains
a release gate. Never restore these snapshots over later editorial work.

Next is a separately authorized staging release and acceptance from GitHub `main`, following
the maintenance runbook. Deliver matching code with four scoped artifacts:
`2026-10-08-taxonomy-seeds.php`, `2026-10-11-commerce-idempotency.php`,
`2026-10-11-owner-setup.php`, `2026-10-11-maintenance-cron.php` under
`ops/migrations/`, each with target-local before-images and check/apply/verify.
Taxonomy/commerce are already applied to the original local site; owner setup and
cron cleanup were exercised only on the isolated fixture. No uploads transfer is
needed for this refactor. The calendar extension changes only code/assets/tests;
it needs no additional DB or uploads artifact. The Schöneweide composition needs
its separate guarded content migration described above. Preserve DB template overrides (`single-ausstellung`,
`page-projekte`, `page-publikationen`), editorial recovery, private contacts/Sets,
historical orders, moderation and uploads. Do not copy the local DB to staging.

Remaining acceptance: real editor save/reload/block validity, staff workflows
including both new calendar views,
macOS Safari (Linux WebKit is not Safari acceptance), full file restoration and
actual FPM/Nginx large-upload behavior. These are release gates, not unfinished
local code slices. Unrelated TODOs and other Atlas backlog remain separate.
