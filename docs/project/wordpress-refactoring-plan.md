# WordPress refactoring plan

Proposed on 2026-10-07. This plan targets stability, maintainability, database
integrity and efficient public requests using PHP, JavaScript, CSS and native
WordPress APIs. Implementation and deployment are separate steps.

Develop in `/home/vladimir/wp-website`. Use `staging.industriesalon.info` as the
running reference and acceptance target. Local phpMyAdmin, Mailpit exposure and
local PHP version findings are development housekeeping, not prerequisites for
this refactor. Preserve staging access and indexing settings.

## Execution checkpoint — 2026-10-11

The user authorized committing the existing checkpoint and continuing this plan
through completion autonomously. Keep reviewable commits and checks between
slices; the earlier pause-for-confirmation requirement is superseded. Execution
remains inside the two local WordPress roots. Push/staging deployment and host
changes outside those roots require separate authorization.

| Phase | Current state |
| --- | --- |
| 1 Baseline | Local mounts, backups and route/query comparisons retained; browser and WordPress 7.0 acceptance still open |
| 2 Database/setup | Seeds, graph/queue/profile/transcript/archive boundaries and Set creation/deletion completed; remaining Set writes, setup gates and commerce retries next |
| 3 Native frontend | Pending |
| 4 Query/projection consolidation | Pending |
| 5 Code/CSS consolidation | Pending |
| 6 Maintenance | Pending within repository/local scope; target operations remain separate |

Use `handoff_CURRENT.md` and the private verification receipts for live checkpoint
state. The following starting-point observations are historical, from 7 October.

## Verified starting point

Read-only SSH inspection found local website source, GitHub `main` and staging
at `6c8cea7`. Staging serves that checkout through container mounts and runs
WordPress 7.0, PHP 8.3.31 and MariaDB 11.8.8. Core Interactivity API and router
modules are present. The four inspected public routes returned HTTP 200.

Staging has 118 InnoDB tables, no orphaned post metadata or graph edges in the
sampled checks, and 66 pending graph entries, the oldest from 2026-06-30.
Database overrides remain for `single-ausstellung`, `page-projekte` and
`page-publikationen`. A Redis container exists, but no WordPress object-cache
drop-in was found; running a service does not establish cache integration.

The local runtime currently mounts the preserved `/home/vladimir/wp` checkout,
despite older handoff instructions describing a website-code override. Restore
an explicit, verified development mount before testing website changes. Do not
replace either database with the other: content IDs and editorial history differ.

The local diagnostic calendar render used 342 queries; disabling three taxonomy
seed callbacks in that diagnostic process reduced it to 136. The seed and graph
service files match staging. The query reduction is a local observation, not a
staging speed guarantee.

## Existing ownership to retain

| Responsibility | Owner |
| --- | --- |
| Editorial documents, recovery and revisions | `iss-editorial` |
| CPTs, fields and editorial contracts | `iss-content` and existing domain owners |
| Programme dates and booking availability | `iss-occurrences` |
| Interactive programme and calendar blocks | `iss-frontend` |
| Request validation, persistence and notifications | `iss-commerce-lite` |
| Graph and SQL search projection | `iss-graph` |
| Public composition, shared cards and skins | `industriesalon` theme |

Keep `WP_Query` for ordinary post lists. Keep prepared SQL and existing indexed
tables for occurrences, graph, search and archive projections. Interactivity API
changes frontend behavior; it does not replace these query services or authorize
another editor, calendar table, search engine or background worker system.

## Ordered delivery phases

### 1 Establish a reproducible development baseline

- Verify the local website/receiver mounts and test against staging's WordPress
  7.0 API baseline. Keep PHP compatibility with staging's 8.3 runtime.
- Capture representative homepage, Kalender, Veranstaltungen, exhibition,
  bookable tour, publication and search behavior, including database templates.
- Record query counts and database time, rendered IDs/order, compressed asset
  bytes and browser requests. Measure cold and warm behavior in isolated local
  tests; do not flush shared staging caches for a benchmark.
- Preserve an environment-specific database/files backup before implementation
  that changes data. Run stateful suites sequentially.

Exit: a test request demonstrably executes the edited website code; reference
results and protected data are identified. Baseline work creates no content sync.

### 2 Remove repeated setup and protect database writes

- Version-gate seed routines in `iss-content/includes/post-types.php`,
  `iss-content/includes/fallback-projection.php` and
  `iss-archive/includes/post-type.php`. Keep taxonomy registration on `init`;
  execute seed updates through controlled installation/migration paths.
- Move schema/backfill work out of ordinary public rendering. Verify required
  tables/indexes before advancing a migration version; expose failures through
  the existing diagnostics. Fresh installations and upgrades must both work.
- Make graph replacement/deletion and editorial Set multi-table changes atomic.
  Check every write result; roll back failures and invalidate caches after
  commit. Keep network and file work outside short database transactions and
  provide recovery for partially completed file operations.
- Replace the graph queue's read-then-update lock with atomic acquisition and
  ownership-aware release. Claim work after locking; recover stale processing
  rows, bound retries, and preserve a new dirty change arriving during processing.
- Make commerce request retry handling idempotent in the existing owner. Use an
  explicit request key and an appropriate unique constraint if required; a
  pre-insert duplicate SELECT alone does not prevent concurrent duplicates.

Exit: no seed loops during ordinary public requests; repeated installation is
safe; injected write failures preserve old data; overlapping workers and retried
submissions cannot create partial or duplicate results. Re-measure query counts.

### 3 Replace Flatpickr with native controls and Interactivity API

Follow the detailed migration in
[Universal occurrence calendar and intake](universal-occurrence-calendar-intake-plan.md#native-wordpress-frontend-migration).

Deliver native programme filters first, then the shared booking/inquiry flow.
Each converted surface has one active implementation. Keep native controls,
server rendering, core script modules and the existing query/intake owners.
Remove Flatpickr vendor assets, adapters and selector rules when its last
consumer is migrated. Keep booking identity and editorial block attributes stable.

Exit: zero Flatpickr assets or initialization on affected routes; one request
path per interaction; server validation and accessible no-JavaScript navigation
remain usable. Verify this on WordPress 7.0 before using newer API features.

### 4 Simplify public queries and projection updates

- Move shared menu event date lookup to the occurrence service after proving
  equivalent opt-in, visibility, recurrence and date behavior.
- Consolidate search visibility and pagination in the existing SQL provider.
  Avoid producing a large ID list followed by redundant metadata filtering;
  preserve unpublished/private exclusions, relevance, counts and pagination.
- Batch post/meta/term retrieval and reuse service results within a request.
  Coalesce multiple metadata changes from one save into one projection update,
  with a documented consistency boundary and the existing retry queue.
- Use `EXPLAIN` on the actual retained queries before adding indexes. Keep
  ordinary editorial metadata and existing modest autoload sizes intact.
- Retain one cache invalidation mechanism per owner. Date-sensitive results must
  expire at relevant Berlin date transitions, and previews/private data must
  remain isolated. Measure the need for Redis before integrating or retiring it.

Exit: representative reads use bounded results and verified query plans; public
visibility and date rotation match the baseline; no external service is required
to render core content.

### 5 Consolidate code and remove proven obsolete assets

- Extract schema, read queries, writes, admin and CLI responsibilities from large
  files within their current owners. Start with graph core/CLI, relations block
  rendering and the programme files already touched. Preserve public functions
  and hooks while callers migrate; remove obsolete wrappers in the same phase.
- Load admin and CLI implementation only in the corresponding context, after
  separating helpers that REST or public rendering still require.
- Consolidate CSS into the established tokens, primitives, renderer contracts
  and skins. Drain duplicate page rules and review existing `!important` uses.
  Split large stylesheets by responsibility and load only required components.
- Determine dead code from PHP hooks, enqueue registrations, template overrides,
  stored blocks, editorial JSON and browser coverage. Remove complete retired
  paths with their imports/tests/styles; source-text absence alone is insufficient.
- Audit first-party jQuery use separately. The identified use is admin project
  ordering, not Flatpickr's frontend. Native replacement must retain keyboard
  ordering and the existing save contract; do not dequeue WordPress dependencies
  globally. The calendar phase introduces no jQuery dependency.

Exit: smaller coherent modules, reduced delivered assets, no duplicated renderer
or calendar controllers, and no removed feature still referenced by saved content.
File length is a review signal, not a reason to create many trivial wrappers.

### 6 Make routine maintenance predictable

- Inventory existing scheduled jobs and consumers, then use one host scheduler
  with a non-overlapping sequential command path. Reuse WP-Cron and the existing
  graph CLI; do not add a worker daemon or queue service.
- Establish bounded graph drain, SuperSaaS sync and cleanup jobs with last-success
  time, queue age, retries and actionable failure reporting. Retire stale hooks
  only after confirming their plugin/data owner; retain historical orders/data.
- Define an automated backup/retention process and test restoration. Verify the
  existing staging arrangements before proposing replacements.
- Scope large-upload and execution limits to the established intake workflow;
  inspect the active staging settings instead of copying local assumptions.
- Keep supported WordPress/PHP/plugin versions on a tested update cadence.
  Low maintenance requires scheduled upkeep, with human attention for failures
  and release acceptance.

Exit: a failed or overdue job becomes visible, recovery is repeatable, and normal
public requests do not perform migrations, remote synchronization or full rebuilds.

## Release and rollback gates

Deliver phases as small reviewable changes through local source, GitHub `main`
and a coordinated staging release. Re-check service state/logs and verified
backups before any staging mutation. Deploy each converted frontend together
with its renderer, module registration and CSS removal. Roll back by restoring
the preceding release, rather than running old and new handlers together.

Run targeted PHP lint, PHPCS/PHPStan, JS/CSS lint and the existing programme
rotation/overview, editorial registry/storage/Set and event/feedback suites as
affected. Add failure/concurrency and browser tests for the changed contracts.
Do not run fixture-writing suites against staging without their bounded scope
and cleanup path being established. No test should send a real notification.

Use desktop and phone Chrome, Firefox and a real WebKit/Safari environment for
native date controls. Verify editor block validity, keyboard/focus behavior,
Berlin midnight/DST, failed requests, slow responses, no-JavaScript navigation,
multiple block instances, and anonymous versus authenticated visibility.

| Change | Artifact requirement |
| --- | --- |
| This planning change | Documentation only; no SQL, uploads or runtime change |
| Seed/schema/queue/idempotency changes | Versioned owner migration when schema/data changes; check/apply/verify and scoped before-image |
| Native frontend and file extraction | Code/assets only if saved attributes/templates remain unchanged |
| Changed DB template or editorial data | Explicit scoped API/SQL artifact; preserve intentional target overrides |
| New referenced media | Paired attachment/data and uploads artifacts |

Never overwrite staging with the local database, replay completed historical
imports, or remove archive/preservation records as part of dead-code cleanup.
The first implementation slice is phase 1 followed by the versioned seed work
in phase 2; the native frontend work follows after the write/query baseline is
stable.
