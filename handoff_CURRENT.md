# Current handoff — 2026-10-11

## Execution and scope

Work in `/home/vladimir/wp-website`, branch `main`. On 11 October the user
requested: **commit the current checkpoint and continue the refactoring plan
through completion autonomously**. This supersedes the earlier requirement to
pause for confirmation after every slice. Continue in small verified commits.

Keep writes inside `/home/vladimir/wp` and `/home/vladimir/wp-website`; preserve
unrelated work. Home-level files, other drives, preservation storage and staging
mutations remain outside scope. No push or staging deployment is authorized.
GitHub `origin/main` was freshly checked at `fe849bc`; this checkpoint and later
work are local commits. Follow [git-exchange.md](docs/runbooks/git-exchange.md)
before later exchange; never pull over unrelated dirty work.

## Current implementation and next work

The first 22 slices are complete: controlled local runtime mounts, versioned
seed delivery, graph replacement/deletion and bridge atomicity, owned queue
leases/processing, profile and transcript consistency, archive projection
transactions/caches, and editorial Set deletion/creation with required creation
audit. Git history, [CHANGELOG.md](CHANGELOG.md) and
[database.md](docs/architecture/database.md) hold the implementation record.

Continue the [refactoring plan](docs/project/wordpress-refactoring-plan.md):

1. Migrate programme filters and shared booking/inquiry to native controls and
   core Interactivity API; remove Flatpickr only after its last consumer moves.
2. Simplify occurrence-backed menu dates, search queries, batched reads and
   coalesced projection updates with measured plans and privacy/date equivalence.
3. Extract responsibilities within existing owners; consolidate touched CSS and
   remove obsolete paths only with saved-content/enqueue/browser evidence.
4. Complete repository-owned maintenance commands, monitoring, backup/restore
   verification and operational documentation within the local scope. Target
   deployment and any host changes outside the checkout roots remain separate.

Set field/key and item/context writers now require their audits/timestamps in
one owner transaction; move/status batches roll back late failures. Five suites
passed 890 checks, including two-connection deletion/ownership races, plus lint,
PHPCS and PHPStan. Logs: `editorial-set-*-final.log` in the private evidence root.
Event Drop moderation now has collision-safe retry resolution and its extra
audit shares the item transaction (33 file recovery checks plus 369 regressions).
Schema setup is now privileged non-AJAX admin/activation/explicit CLI work.
All eleven owner installers verify structure and version persistence; existing
occurrence/collection migration writes are checked. Two suites passed 194
checks; lint/PHPCS/PHPStan passed and all 11 live schemas passed read-only
inspection. Commerce CLI verification is read-only. Commerce idempotency is now
complete: unique request keys, checked duplicate serialization, stable client
retry keys and dispatch only for newly committed rows. Passed 76 backend, 16
migration and three Chrome retry checks; PHP lint/PHPCS/PHPStan and JS lint pass.
The commerce migration is applied locally; all three historical requests remain.
Private scoped SQL backup and target-local JSON before-image are retained under
`commerce-before-idempotency.sql.gz` and `commerce-migration-fixtures/` in the
private evidence root. Deploy with `ops/migrations/2026-10-11-commerce-idempotency.php`
check/apply/verify and a new target-local before-image; no uploads artifact.
Next: native GET programme filters, then the shared native booking flow. Existing
email dispatch is not exactly-once across process crashes.
Do not run real imports/merges or destructive cleanup for verification.

## Runtime and evidence

- `wp_app` serves plugin/theme/receiver code from `wp-website`; core, database,
  uploads and private intake remain in `wp`. Mounts checked again 11 October.
- Use Compose project `wp`, base `/home/vladimir/wp/docker-compose.yml` and
  `/home/vladimir/wp-website/var/refactoring-20261008/website-code.yml` together.
  Do not recreate from base Compose alone or use the old home-level wrapper.
- CLI: `var/refactoring-20261008/wp`; pass
  `--exec='define("DISABLE_WP_CRON", true);'` for isolated checks. Stateful suites
  run sequentially. Do not send real notifications from tests.
- Local runtime: WordPress 7.1.3, web PHP 8.2.30, CLI PHP 8.3.31, MariaDB 10.11.15.
  Staging's last known reference was WordPress 7.0/PHP 8.3.31/MariaDB 11.8.8 on
  7 October; these are historical facts, not current health/acceptance evidence.
- Private ignored evidence directory: `var/refactoring-20261008/` (mode 0700).
  `autonomous-checkpoint-tests.json` and checkpoint logs record the combined
  regression pass: 3,134 checks across 21 suites, PHP lint, PHPCS and PHPStan.
  Per-slice `*-verification.json` receipts retain earlier scope.
- Latest Set creation receipt: `editorial-set-create-verification.json`, with
  133 creation and 181 deletion checks, lint/PHPCS/PHPStan and HTTP 200. Real
  Set/items/links/audit/options/queue rows were retained. The broader checkpoint
  regression pass covers the accumulated code together before committing.
- Source before-images and backup hashes remain in `*-backup-verification.json`.
  Set backup: `editorial-set-delete-before.sql.gz`; archive/graph backup:
  `graph-archive-before.sql.gz`. Original database/files baselines and seven-route
  comparisons remain available. Their gzip/hash/SQL checks passed; a full restore
  rehearsal is still outstanding. Never restore these over later editorial work.

## Contracts and release gates

Set service writes are atomic within the owner. Supplemental integration audits
and external file/editorial workflows remain caller responsibilities. Caller transactions retain final decisions;
generated-key races can return zero. Later attachment/import/merge operations
are separate until refactored. Archive source preparation commits before graph
writes; projection caches discard pending observations. Canonical editorial
source/cache coordination and preservation storage retain their owners.

The taxonomy migration is applied locally. Its delivery artifact is
`ops/migrations/2026-10-08-taxonomy-seeds.php` with target-local before-images and
check/apply/verify; do not replay its completed local application. Current graph,
archive and Set code changes need no schema/data migration or uploads artifact.
Before future commits, check whether their changes introduce such dependencies.

Browser/editor/responsive, Firefox/WebKit, WordPress 7.0 and staging acceptance
remain open. Backend tests do not establish those results. Preserve DB template
overrides (`single-ausstellung`, `page-projekte`, `page-publikationen`), editorial
history/recovery, private contacts/Sets, date rotation, moderation and uploads.
No full-database equality claim: concurrent editor activity exists; per-suite
receipts state the exact retained rows. No real queue has been drained. Keep the
map thread and unrelated TODOs outside this refactor.
