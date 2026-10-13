# Database And Query Architecture

WordPress posts, post meta, terms, and `WP_Query` are the default model for normal editorial content and template loops. Custom tables are preferred when the data is not naturally post-shaped or needs stable indexed lookup paths.

## Existing Custom Table Owners

- `iss-content`: private editorial Sets, their items/context links and retained audit history.
- `iss-graph`: entity index, entity names, entity identifiers, entity evidence references, entity relations, editorial relation signals, search index, person facts, and organization facts.
- `iss-occurrences`: public programme occurrence projection and recurrence series for indexed calendar/timeline queries.
- `iss-commerce-lite`: lightweight public booking/order request intake, review/export/status handling, and notification state for low-volume operational handling. The request table keeps the existing `wp_iss_payments_lite_requests` name for compatibility.
- `iss-archive`: archive objects, archive collections, collection members, assertions, evidence, and source snapshots.
- `industriesalon-schoeneweide-register` (transitional): Place epoch/state and
  industry-actor projections used by the interactive Atlas while the accepted
  Place migration is observed. Enabled Place JSON is the epoch write authority;
  phase/state rows are derived read models, not a second editorial source.
  The existing epoch table also stores accepted supplier milestones with a
  non-null `milestone_key` and source-bearing `milestone_json`; phase queries
  exclude these rows. These append-only event records survive phase rebuilds.
  See the supplier exchange contract in `places-editorial-atlas-restructure-plan.md`.

See `source-of-truth.md` before deciding which storage layer is canonical for a surface.

Schema checks for graph, occurrences, commerce, archive and editorial Sets now
run on privileged non-AJAX `admin_init`, with activation and explicit owner/CLI
installation retained. Ordinary `init` no longer installs these tables. The
small shared `iss-core/schema.php` utility applies that admin gate and renders
upgrade errors as notices, allowing the existing diagnostics to remain usable.
It is bundled infrastructure code, not a requirement to activate ISS Core for
an owner's installer; deploy the helper with its callers.

All eleven installers retain their existing `dbDelta()` declarations and expose
those same declarations through `get_schema_statements()` (commerce uses its
existing function-style owner). The shared read-only verifier checks table
engines, required column types/nullability/autoincrement and BTREE index
names/columns/prefixes/uniqueness before recording each schema version. Integer
display widths are normalized; extra legacy columns/indexes and default-expression
comparison are outside this check. Unsupported declaration syntax fails closed.
No second schema manifest or migration writer was added. Failed DDL remains
repairable by retry; it is not transactional DDL. The existing operations report
checks every available owner's live structure and schema version. Commerce's
`verify` command now checks structure without installing it.

Occurrence upgrades check legacy graph-index/column removal, sentinel-date
replacement and retired option deletion before recording success. Collection
text-column upgrades likewise check reads and writes. These are the existing
migrations with verified outcomes, not new data transformations. The two
`*schema-install-verification.php` suites cover fresh/partial installs, repeated
upgrades, metadata/DDL/version-write failures, interrupted legacy migration and
routing with disposable tables/options. No table definition or version changed,
so this slice needs no migration artifact; no real upgrade/backfill was replayed.

`ISS_Content_Editorial_Sets_Service` owns atomic Set creation/update/deletion,
item creation/update/moves/status batches, context attachment, promotion and
archive-candidate markers. The private `run_atomic_write()` checks InnoDB/session
state and preserves caller transactions with savepoints. Required audit and Set
timestamp writes belong to the same transaction; rejected first writes preserve
zero/false or skipped-item results, while failed prerequisites, readbacks, audits
and transaction commands throw. Cleanup errors retain the original cause.

Writers lock parent Sets in ID order before their items, matching deletion.
Item ownership is rechecked after locking; a concurrent move aborts stale work
for retry. Missing positive Set IDs cannot acquire orphan children. Set zero
remains unassigned intake. Existing rights gates, defaults, normalization,
duplicate/no-op behavior, zero-row updates, position ordering and audit history
are retained. Move/status batches roll back earlier successful rows on a late
failure while retaining ordinary per-item rejection/count behavior. Deletion
checks every child, including those beyond the UI limit, and retains SQL
collation semantics for promoted/archive-candidate protection. It never deletes
source media/posts or audit history. This owner has no projection cache.

The archive-candidate action runs after the owner's successful scope, so a
failed audit emits no event. Within a caller-owned transaction, that caller still
controls final commit/rollback and external hook side effects. File/network
operations, promotion into editorial documents and supplemental integration/core
audits remain separate caller responsibilities (item callers can supply a
required additional audit to the existing update boundary); service atomicity does not make
an entire import or media workflow transactional. Generated-key/unassigned-item
races can still return zero through unique constraints; there is no automatic
retry. Context positions may tie across different Sets, ordered by ID as before.

The `editorial-set-*-atomic.php` suites use real-schema temporary tables;
`editorial-set-concurrency.php` uses uniquely named disposable tables and two
MariaDB connections. They verify rollback/retry, parent deletion races, stale
ownership, audit/timestamp failures and caller transactions without changing real
Set, child or audit rows. These changes need no schema/data migration or uploads
artifact. No real import, merge, promotion or cleanup was run.

Event Drop moderation uses that additional audit for its storage-state event.
Its file move remains outside the short database transaction. The existing
resolver checks known SHA-256 values and searches stable item-specific collision
filenames, allowing retry after a failed database save without losing the moved
file or selecting unrelated bytes. Target directories must resolve inside the
private intake root. This is retry recovery, not filesystem/database atomicity;
legacy unique-suffix moves already stranded before this change still require
exact-path review. `editorial-set-file-recovery.php` requires an explicit writable
`ISS_TEST_FILE_ROOT` fixture mount and never touches real intake files.

Graph relationship replacement is atomic for one entity, relation family and
source system. Name, identifier and evidence replacement are atomic for one entity and source system,
across that source's references. Empty rows intentionally clear the relevant
scope; name normalization, ordering and blank-name skipping are retained. Graph entity deletion
is atomic across its existing nine deletes in eight tables, including incoming
and outgoing edges, names, identifiers, evidence, search, facts and the entity.
Identifier replacement retains the existing same-entity source reassignment and
last-duplicate-input behavior. Invalid identifier arrays, conflicts with another
entity, or failed reads/writes now abort the entire replacement; non-array entries
remain skipped. Empty input still clears the requested source. The existing
upsert returns failure for failed inserts/updates, while a zero-row update succeeds.
Evidence replacement likewise aborts on rejected rows or failed reads/writes.
An explicit evidence ID can update another source's row belonging to the same
entity, and that update rolls back with the batch on failure. Foreign entity IDs
are rejected. Missing IDs (including rows deleted in the replacement scope)
still create new identities; no-ID duplicates and default evidence rows remain
valid. Non-array entries are skipped and empty input clears the source.
The combined `iss_graph_replace_entity_projection()` facade delegates to
`ISS_Graph_Service::replace_entity_projection()`. All supplied name, identifier,
evidence and relation-family sets share one outer transaction. Every participating
table is validated before any write; component writers retain their savepoints.
A failure in the final family restores preceding component writes and source
reassignments. Omitted/non-array parts are skipped; explicit empty arrays clear
only their existing scopes. An empty relation map clears no families. Flat lists,
the evidence alias and sequential sanitized-family collisions retain their
existing behavior. Empty/no-op payloads perform no database work.
`ISS_Graph_Service` shares one private transaction helper between these six
operations. It checks MariaDB session state and every participating InnoDB table,
checks writes and raises exceptions on failure. Caller-owned transactions,
including disabled autocommit, retain ownership through a savepoint. Only a
transaction started by the helper is committed.

The register bridge now runs automatic place synchronization and optional editor
groups inside `synchronize_entity_graph()`, which exposes that same private helper
for bridge-owned entity/name/identifier/relation writes. One-source payloads
cannot encompass target creation or several source labels, so this boundary
wraps the existing writers without merging their provenance. Place and target
entity upserts, identifiers, automatic roles, the three `register_admin` groups
and alias refresh succeed or roll back together. Aliases are derived after the
new manual names are written. Failed source/identity/alias reads and failed
entity writes abort rather than silently removing rows or reporting success;
unchanged entity updates remain valid. Imported role exclusions, hidden target
defaults, editor permissions/nonces and empty-group clearing are retained.

Standalone editor saves invalidate the existing register cache after the graph
commit. A caller owning an outer transaction remains responsible for final
commit/cache coordination. WordPress source posts/meta, register epoch/state
tables and search updates are outside this graph transaction; a graph failure
does not undo an already-saved WordPress post. No schema or content migration is
required.

Place narrative persistence has a separate register-owned boundary in
`ISS_Register_Place_Epoch_Service::with_place_transaction()`. It checks the posts,
postmeta, epoch and state tables for InnoDB, locks the source Place row, and wraps
canonical JSON, skin/activation, epoch snapshots and state replacement. It checks
reads/writes, rolls back SQL/PHP failures, evicts metadata caches, and returns
`WP_Error` on failure. Epoch and state writers also use this boundary independently.
Nested/caller-owned transactions use savepoints; only an owned transaction is
committed. The register cache is invalidated after successful outer service work;
callers owning a larger transaction retain final commit/cache responsibility.
Native post fields, taxonomies, graph and subsequent Set-use notifications remain
outside this Place document boundary. No table or schema change is involved.
`tests/e2e/bin/place-editorial-atomic.php` injects failures against connection-local
copies of the actual schemas, with recovery and real-table preservation checks.

Automatic public-content synchronization in `iss_graph_sync_public_content_entity()`
also uses `synchronize_entity_graph()`. Content entity/title, WordPress identifiers,
aliases and the native place/person/organization families commit together,
including nested register venue and venue-owner graph creation. Prerequisite
source/venue read failures and missing results from required entity writes abort
the operation. A genuinely absent, invalid or trashed venue still intentionally
clears its native relation. Manual `content_admin` rows and other source labels
remain separate. Trash/auto-draft projection removal uses the existing atomic
entity deletion boundary; a failed lookup cannot report successful removal.
Caller transactions retain their savepoints and final commit ownership. Source
WordPress posts/meta and subsequent read-model/search writes remain outside this
graph transaction. No schema/data migration or uploads are required.
`tests/e2e/bin/graph-content-atomic.php` checks rollback, nested venue writes,
source contracts and queue retry integration with temporary graph/queue/options
tables and process-local source fixtures; real data is retained.

Automatic archive-object graph synchronization in
`iss_graph_sync_archive_object_entity()` uses `synchronize_entity_graph()` around
the object entity, title, identifiers, aliases and all `archive_projection`
relationship families. Required named person/organization/place creation and
local register-place/owner graph writes share that boundary. Checked source,
placeholder, local-place and result reads prevent failed SQL from becoming empty
relations or duplicate identities. Required entity failures raise errors. Source
labels, identifier trust, placeholder identity reuse, organization-like person
labels, hidden named targets, object status/privacy and unsupported/missing-source
no-ops remain unchanged. It does not write search or drain the dirty queue.

The `iss-archive` object/relation getters can create missing projections. The
existing object service now owns `synchronize_projection()`, shared by standalone
object saves, complete relation replacements and paired graph-source preparation.
It checks MariaDB transaction state and InnoDB tables, locks the source post,
uses a standalone transaction or caller savepoint, and checks finish/rollback
results. The locked post supplies the current source type/status/title. Required
object creation rolls back with failed relation replacement, including all eight
families. Object readback verifies saved values and relation readback verifies
replacement count; valid zero-row updates and empty replacements still succeed.
No archive transaction helper existed; the graph helper is private and scoped to
graph ownership, so this small boundary belongs in the existing archive owner.
No new service, table or graph dependency was added to the archive plugin.

Paired preparation commits before the standalone graph transaction. Every graph
family consumes the same prepared object/relation snapshots, including the
institution relation. Failed preparation restores both archive projections and
stops graph work; a later graph rollback preserves successfully prepared archive
rows. Canonical WordPress posts/meta, media/collection projections, deletion,
search and preservation storage remain separate.

Both request-local projection caches are invalidated around saves. Each getter
checks session transaction state before reusing cached rows: reads inside caller
transactions or with autocommit disabled bypass and evict the cache. Pending rows
and absence are never cached, so caller rollback cannot leave those observations
behind. Object saves also invalidate the relation cache's embedded object row.
Failed metadata queries evict WordPress's empty error result before propagating,
allowing a complete retry. Canonical metadata writes and cache coordination after
caller-owned source changes remain the caller's responsibility. The extra
transaction-state query per getter is intentional; this is not a performance claim.

`tests/e2e/bin/archive-projection-atomic.php` checks warm/cold replacements, all
families, late failures, retries, caches, unsupported sources and caller
transactions. `tests/e2e/bin/graph-archive-atomic.php` retains graph rollback,
identity/privacy and source-preparation coverage. Real projection/graph/queue/
options rows remain unchanged in the temporary-table suites. No real repair or
backfill, schema/data migration or uploads artifact is required or performed.
Multi-worker consistency and browser/staging acceptance were not exercised.

Automatic video transcript synchronization in
`iss_graph_sync_video_transcript_mentions()` reuses `synchronize_entity_graph()`
around required video graph creation and `video_transcript` evidence replacement.
The nested evidence writer checks its table before writing, and failures undo
both evidence changes and any new video entity/title/identifiers/aliases/native
relations. Candidate and review reads fail explicitly instead of producing empty
sets. Reviewed targets, other evidence sources and existing manual relations
retain their separate ownership. Matching, confidence, caps, status normalization
and the automatic save hook's priority remain unchanged.

Source post/status reads and block rendering precede the standalone graph
transaction. The bridge checks its source reads and the renderer's reported SQL
error; rendering side effects and errors swallowed inside another renderer are
outside this write contract. Caller-owned transactions retain final commit and
cache coordination. This function does not update search, write review decisions
or promote relationships. Source editorial saves remain separate, and no
concurrent-worker ordering guarantee is established by the isolated tests in
`tests/e2e/bin/graph-video-transcript-atomic.php`. No schema/data migration or
uploads are required.

Manual video transcript review in `iss_graph_save_video_transcript_review()`
uses `synchronize_video_transcript_review()`, an entry to the same transaction
helper covering posts (source lock), postmeta and graph entities, names,
identifiers, relations and evidence. One submitted batch commits or rolls back
its `content_admin` person/organization promotions, canonical `iss_related_places`
metadata, accepted/rejected `video_transcript_review` decisions and automatic
transcript evidence refresh together. Prerequisite reads, decision writer results
and stored decision fields are checked. Existing permissions, nonce, ownership
and pending-evidence checks, accept-over-dismiss precedence, source labels and
save priority remain in place. Unusable place posts are skipped without an
accepted decision.

Place writes retain the `iss-relations` owner and WordPress Metadata API.
Its source reads/target normalization now distinguish SQL errors from missing
values. Its writer verifies persisted serialized rows, including duplicate rows,
accepts unchanged values and checks explicit clearing; slashing preserves literal
backslashes. The review clears its metadata cache before reading source relations,
suspends cache additions during the transaction and clears metadata caches in
`finally`. The existing relation-read-model and search metadata callbacks are
restored in `finally` and their work runs after a successful boundary. Other native
metadata callbacks remain active. The register cache callback excludes videos.

Place taxonomy/graph projections and public search retain their existing derived
owners and run after standalone review commit; their later failure cannot undo
that commit. A caller-owned outer transaction retains final commit/rollback and
cache coordination, including these derived updates. Arbitrary plugin callback
side effects are outside the verified write set. Source post content/status is
already saved separately. Rendering is prepared before the standalone review
transaction and automatic/review callers share the existing evidence writer via
`iss_graph_sync_video_transcript_segments()`. No multi-worker ordering guarantee
is claimed. `tests/e2e/bin/graph-video-review-atomic.php` covers the save handler,
whole-batch rollback, source metadata and real owner callbacks using temporary
tables. No schema/data migration or uploads artifact is required.

The manual content editor's `iss_graph_save_public_content_relations()` wraps
its source/target reads, required content graph creation and both `content_admin`
person/organization groups in `synchronize_entity_graph()`, using the existing
combined projection writer. Read failures abort; genuinely invalid, wrong-kind
or profileless targets retain their existing skip behavior. Nonces, permissions,
privacy, source references, missing/malformed field clearing and save priority
remain unchanged. Other relation families and sources are retained. Missing
required source creation raises an error; trash/auto-draft keeps its no-create
behavior. Read-model/search updates follow the manual graph boundary and are
skipped when it fails. A later derived-update failure cannot undo a standalone
graph commit. An outer transaction owner still controls final commit and cache
coordination. `tests/e2e/bin/graph-content-editor-atomic.php` exercises the actual
save handler with temporary graph/queue/options, taxonomy and postmeta tables.

Profile binding uses `synchronize_profile_binding()` in the same graph service,
delegating to its existing transaction helper with posts (for source-row locking),
postmeta, entities, names and identifiers. The editor passes its requested entity
into `iss_graph_sync_profile_binding_for_post()` before metadata changes. Current
and displaced profile metadata, old/new entity binding fields, profile-owned
identifiers and generated aliases succeed or roll back together. Source post
content/status is already saved and remains outside this write boundary.

Binding metadata reads use prepared SQL with row locks; writes retain WordPress's
Metadata API and hooks, with result verification. The source post row serializes
same-profile saves. Entity locks use primary IDs from requested/stored bindings,
the previous-ID hint and the existing unique WordPress identifier; no scan of the
unindexed `profile_post_id` column or new index is needed. Detachment checks the
expected profile owner and removes its `entity_profile` identifiers so reassignment
does not conflict with stale identities. A stale reverse link cannot remove a
displaced profile's different current metadata choice. Legacy inconsistencies
outside those known identities are not swept or migrated by this save.

The bridge temporarily defers its own metadata-triggered search callback and
suspends cache additions, restoring both in `finally`. Affected metadata caches
are cleared after commit/rollback; register cache invalidation and affected
profile search refreshes follow a successful standalone binding transaction.
Other Metadata API callbacks still run inside it. A later search failure does
not undo the committed binding. Caller-owned transactions retain savepoints and
must coordinate their final commit/rollback and cache invalidation. Direct callers
which already saved source metadata before synchronization retain that metadata
as the rollback baseline; this does not undo an earlier REST/import source write.
No schema/data migration or uploads are required. The isolated binding suite is
`tests/e2e/bin/graph-profile-binding-atomic.php`.

Manual profile names in `iss_graph_save_entity_profile_aliases()` reuse that
profile transaction boundary. The source post, stored binding metadata and
linked entity are locked in the same order as binding saves. Prerequisite SQL
failures abort; an absent or unsupported entity remains a no-op. Manual
`entity_profile_admin` names and `entity_alias_backfill` aliases derived from
those new names commit or roll back together. Other name sources, facts,
identifiers and source post/metadata values remain untouched. Existing nonce,
capability, sanitation and empty-input clearing rules are retained.

After successful standalone commit, the metadata cache is invalidated before
refreshing search; cached bindings cannot redirect the save or its search text.
Search failure cannot undo committed names. An outer transaction caller retains
final commit/rollback and cache coordination. The isolated failure/behavior suite
is `tests/e2e/bin/graph-profile-aliases-atomic.php`; it does not establish
multi-worker concurrency behavior. No schema/data migration or uploads are needed.

`upsert_entity_facts()` uses the same transaction helper for the entity and its
person/organization facts table. It locks the entity and existing facts row,
checks prerequisite reads and writes, and verifies the stored fields before
commit. Failed or missing readback and mismatched stored values roll back;
zero-row updates remain successful when the stored values match. Sanitation,
nullable/clamped years, creation timestamps and replacement defaults are retained.
Unsupported kinds and genuinely absent entities still return an empty result.
The service contract also applies to enrichment callers; it does not make an
entire enrichment import transactional.

`iss_graph_save_entity_profile_facts()` locks the stored profile binding inside
the existing profile transaction boundary, then calls the facts writer. Its
checked nested transaction validates the facts table before any facts write.
Search runs only after successful standalone persistence and metadata cache
invalidation. Source posts, bindings, aliases and other fact rows are unchanged;
the separate facts, alias and binding callbacks do not form one whole-editor
transaction. Outer callers retain final commit/rollback and cache coordination.
`tests/e2e/bin/graph-profile-facts-atomic.php` covers inserts/updates and injected
failures with isolated real-schema tables. No schema/data migration or uploads
are needed; concurrent-worker behavior is not established by these fixtures.

The graph dirty queue retains its `iss_graph_dirty_queue_lock` option. Acquisition
reads the database directly, then uses the unique option name for first insertion
or an exact-value conditional update for expired/malformed leases. Each lease
has a unique token; `drain_dirty_queue()` retains the serialized lease and releases
only that exact value in `finally`. A former owner cannot delete its successor's
lock. Conditional SQL is required because the Options API has no compare-and-swap
operation; option caches are invalidated after writes. Live legacy leases are
respected, expired ones are replaced, and SQL errors raise exceptions rather
than reporting success or ordinary contention. No schema/data migration is needed.
The existing TTL remains `max_runtime + 60` seconds for drains, with a 60-second
minimum. This is a lease, not cancellation of an overlong worker or rollback of
projection writes that reconciliation has already performed.
The isolated check is `tests/e2e/bin/graph-queue-lock.php`; run through WP-CLI
with cron disabled. It uses two database connections, a disposable options table
and a temporary queue, leaving real queue rows and lock/run-status options intact.

Queue processing selects its bounded batch after acquiring the lease. A new
owner can recover abandoned `processing` rows because no earlier run owns the
current lease; recovery and dirty work share the batch/runtime limits. Claim,
acknowledgement and last-run recording reuse the graph transaction helper, locking
the option row and checking its exact lease value and expiry. Reconciliation runs
outside those short transactions. A drain requires an independent autocommit
connection so it cannot hold queue locks inside a caller's transaction. Lease loss
stops further claims and acknowledgements, preserves the replacement owner's run
status, and is reported through `lease_lost` and a CLI warning.

`mark_post_dirty()` atomically coalesces on the existing unique source-post key.
Each new source signal sets `dirty`, clears the claim/error and resets attempts,
while retaining row identity, creation time and last successful processing time.
Completion matches the processing claim and current lease; a newer dirty signal
survives even when timestamps and attempt numbers could repeat. Reported failures
and exceptions consume one attempt per selected row per run. Abandoned claims
retain consumed attempts; exhausted dirty/processing rows become `dead` without
another reconciliation. New source signals reopen dead rows with a fresh budget.
Dry runs only list dirty rows and never recover, claim or record a run. SQL failures
are surfaced and leave untouched or recoverable work instead of false success.
No new table, schema migration, scheduler or worker is introduced. Coverage is in
`tests/e2e/bin/graph-queue-processing.php`, using disposable tables and missing
fixture post IDs; the real queue is never drained by these tests.

Programme calendar/timeline queries are served from `iss-occurrences`. The old
hidden `iss_calendar_item` post type is not an active storage or query layer.
WordPress source posts enter the occurrence projection only through explicit
`iss_programme_enabled` opt-in meta; missing toggle meta means hidden.
Ausstellung overview/browser visibility is separate and uses
`iss_public_overview_enabled`, so Dauer/Digital exhibitions can remain visible
in Ausstellung overviews without becoming programme occurrences unless editors
also opt them into the programme.
Public occurrence rows depend on `source_post_id` and `source_post_type` for
calendar identity, sync, delete, and editor routing. Occurrence rows must not
store graph IDs; graph-facing routes translate entity requests to source post
filters at the facade boundary.
Open-ended occurrence ranges use `ends_at = NULL` plus `is_open_ended = 1`; far
future sentinel dates such as `2099-12-31` are invalid drift.
Editorial WordPress source providers are `veranstaltung`, `ausstellung`, and
opt-in `projekt`. `fuehrung` occurrence rows are external SuperSaaS slot
projections linked back to parent Führung posts, not editorial date rows.
Legacy programme meta/options such as `iss_timeline_item_id`,
`iss_exhibition_source`, `iss_exhibition_type`, `_iss_legacy_archive_term_slug`,
`iss_is_permanent`, retired `iss_timeline_enabled`, and `iss_calendar_*`
mapping options are invalid drift, not compatibility inputs.

Commerce-lite request rows are stored in the existing `wp_iss_payments_lite_requests`.
New public writes use the universal `/iss-payments/v1/request` intake with
`booking`, `inquiry`, and `order` intents. The table keeps its existing
`request_kind` column name until a dedicated schema cleanup is worth doing, but
new writes store the normalized intent value there. Schema v3 adds nullable
unique `request_key` and `request_fingerprint` fields through
`ops/migrations/2026-10-11-commerce-idempotency.php`; historical rows retain null
keys. New forms retain their random key through network retries. A matching
committed replay returns the original success without another mail/hook dispatch;
changed intent conflicts. A database session lock serializes the existing semantic
duplicate window. Mail remains outside persistence and is not exactly-once across
process termination.

New intake validates public, non-password-protected sources, current slot state
and quantity. Slot time/title come from the occurrence/source owners. Preferred
dates use strict site-timezone validation, including nonexistent DST times.
Booking availability is the complete source set within the existing default
12-month horizon (owner maximum 24 months), with no silent 1000-row truncation.
The public endpoint reads the occurrence projection and returns `no-store`;
legacy tag-only transients cannot override current source visibility. This is
request capture, not a capacity reservation or automatic external booking.
Online settlement is not implied by a stored request row; supported payment
methods default to onsite/request capture until a provider integration is
explicitly added.

## Rules

- Use `WP_Query` for ordinary public post loops and editor-authored content lists.
- Use plugin-owned custom tables plus prepared `$wpdb` SQL for projections, search indexes, graph relations, archive/reporting data, joins, aggregates, relevance scoring, and high-volume lookups.
- Install custom tables with `dbDelta()`, schema version options, `$wpdb->prefix`, and indexes that match real read paths.
- Access custom tables through service classes. Do not build raw SQL in theme templates or block markup.
- Use `$wpdb->prepare()` for dynamic values. Keep dynamic table names limited to service-owned table-name methods.
- Add custom tables only when WordPress storage is the wrong shape or makes query logic fragile.

### Ordinary save projection boundary

Ordinary content refresh, relation save/meta and search save/meta callbacks now
coalesce through the existing graph dirty queue. Every accepted mutation marks
its source durably; a request-local ID set only narrows the shutdown drain after
classic metabox and REST metadata callbacks finish. The drain uses existing
owned leases/claims/retries, at most 50 sources and five seconds between items.
It never drains unrelated historic work. Empty or invalid ID scopes fail closed.
Busy/failed/remaining sources stay in the queue for the maintenance command.
Public read-only requests never flush this work; SQL source visibility checks
remain authoritative even when a projection is delayed.

Explicit atomic editorial/profile/transcript/route writers keep their immediate
contracts. Occurrence sync remains synchronous in its existing owner; booking
validation cannot depend on a delayed search projection. The reconciliation path
calls each source bridge through the relations owner once and checks search and
term write failures before queue acknowledgment. Derived hooks from that same
reconciliation do not requeue themselves. Direct metadata callers needing a
read-your-write search projection must explicitly use the owner sync API or wait
until the request boundary. Queue/schema/data format is unchanged.

`tests/e2e/bin/projection-coalescing.php` covers repeated callbacks, final values,
scoped acknowledgment, write failure/retry and a busy worker. Existing queue race
and atomic writer suites retain their contracts; real rows remain untouched.

### Core and newsletter setup gates

Capabilities and newsletter defaults run through activation/privileged non-AJAX
administration or their explicit owner APIs, never `plugins_loaded` or
`newsletter_loaded`. Capability completion checks persisted role grants;
newsletter completion checks both settings before advancing its version.
Existing newsletter administrator edits remain intact after that first versioned
setup. Legacy plugin aliases load compatible owners during bootstrap without
rewriting `active_plugins`; privileged setup persists replacements.

Deploy `ops/migrations/2026-10-11-owner-setup.php` with these changes. Its
`check|apply|verify` interface follows the taxonomy seed artifact; apply requires
a new private mode-0600 target-local before-image for the six exact option names.
Do not copy local options/roles into another environment. Verify the target
before normal administration; rollback must review later settings edits first.
The original local database was not migrated by this slice; the isolated
WordPress 7.0 fixture exercised apply/verify. No uploads artifact is required.
