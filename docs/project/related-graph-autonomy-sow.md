# Related Graph Autonomy SOW

This SOW defines the direction for ISS related places, related content, graph
derivation, and editorial promotion. It exists so future relation work does not
silently make lay editors or real admins load-bearing for public relatedness.

## Goal

The site must survive years without a real admin actively repairing relations,
including silent failure of the autonomous machinery itself. Related places and
related content should be derived from canonical content fields, graph entities,
native CPT fields, archive authority data, and scheduled or CLI reconciliation.

Manual relation controls may improve or correct results, but their absence must
not make public relatedness go dead.

Normal editors should not need to understand relation storage, source systems,
weights, graph families, or projection state. Their default edit screen should
stay focused on document structure, required public facts, references, and one
plain promotion control when the content can be intentionally featured.

The autonomy claim is only met when it is observable and testable. The system
must expose whether reconciliation is running, whether queued work is stuck, and
whether derived relation state can be rebuilt from canonical data.

## Implemented Checkpoint 2026-06-28

`iss-graph` schema `2026-06-28-autonomy-v1` implements the first autonomy
slice:

- relation rows carry `edge_key`, `source_field`, `relation_status`, and
  `confidence`;
- editorial signal removal is soft by status instead of hard deletion;
- `wp_iss_graph_dirty_queue` stores dirty, processing, dead, and clean
  reconcile items;
- frontend read fallbacks no longer repair graph state during public requests;
- trusted editors get the simple related-promotion toggle and list-table
  switch-off workflow, while advanced pin/suppress/search/availability controls
  remain manager/admin controls.

Operational commands now available:

- `wp iss-graph autonomy-health --format=json`
- `wp iss-graph relation-audit --format=json`
- `wp iss-graph relation-backfill --dry-run --batch-size=500`
- `wp iss-graph relation-dedupe --dry-run --limit=100`
- `wp iss-graph reconcile --dry-run --batch-size=50 --max-runtime=30`
- `wp iss-graph signals-export --status=active --file=/path/signals.json`
- `wp iss-graph signals-import --file=/path/signals.json --dry-run`
- `wp iss-graph autonomy-fixtures --format=json`

Local verification after migration/backfill reported:

- 4,796 relation rows with no missing edge keys, no missing provenance, no
  invalid statuses, no orphan edges, and no active duplicate edge keys;
- dirty queue present with no dirty/dead items;
- Veranstaltung `iss_primary_place_id` health counter at zero missing items;
- fixture coverage present for one Veranstaltung, Ausstellung, Publication,
  archive object, place, and person/organization.

Production scheduling still needs an external cron/container scheduler entry
that runs `wp iss-graph reconcile --batch-size=50 --max-runtime=30` on the
chosen cadence. Page-request cron must not be treated as the production
autonomy mechanism.

No SQL transfer artifact is required for this autonomy slice: relation
provenance and duplicate cleanup are deterministic plugin-owned graph table
migrations run by `wp iss-graph migrate` or the narrower
`relation-backfill`/`relation-dedupe` commands. Export editorial signals before
destructive graph work, but do not copy local derived relation rows as content
truth.

## Core Rule

Hard-coded related places and hard-coded related content are not backbone data.
They are editorial correction or curation inputs.

Normal public relatedness must be rebuildable from:

- canonical WordPress content fields;
- native CPT fields such as venue, subject, transcript/entity refs, dates, and
  archive/object authority refs;
- graph entity relations across place, person, and organization families;
- harvested graph edges written by deterministic sync/reconcile code.

Editorial promotion and suppression are signal data, not canonical relation
data. They may alter public ordering or visibility, but they must never mint
graph edges or become the only reason the site can show a relation.

## Editor Contract

The normal editor surface should expose:

- document structure and public content fields;
- CPT-native facts that are required by public renderers, programme projection,
  search, archive references, or relation harvest;
- media, archive, Set, place, person, and organization pickers only where they
  express real editorial facts;
- one obvious promotion toggle for content that can be featured in related
  surfaces.

The normal editor surface should not expose:

- graph table/source-system terms;
- relation family, relation weight, rank, boost, edge identity, or projection
  fields;
- duplicate manual relation boxes when the same concept is already controlled
  by a CPT fact panel, dashboard picker, route editor, or reference tray;
- diagnostic sync/repair controls.

Advanced and technical roles may still access compatibility metaboxes,
diagnostics, source summaries, and manual repair tools. Those controls must stay
out of the default editor workflow and must not be required for normal
publishing.

Harvest-critical fields are the exception to hiding technical-looking inputs. If
a native CPT field is required for relation harvest, it remains visible as a
plain editorial fact, publish-time validation warns when it is empty, and the
health monitor reports empty harvest-critical fields by CPT.

## Current Direction

The frontend related-content block should prefer graph/entity relatedness and
only fall back to old place-based paths where needed for compatibility.

The deeper relation read layer should be collapsed into one canonical
"derive relations for post" function. That function should:

- sync or find the graph entity for the post;
- run native-field harvest before reading derived edges;
- read all supported relation families: place, person, and organization;
- return normalized generic relation rows with `target`, `relation_family`,
  `relation_type`, and `relation_role`;
- expose projections for callers that need a narrower shape.

Place-specific consumers such as static maps, route stations, and
`iss_relations_get_related_place_items()` should remain place projections. They
must not be forced to understand people or organizations.

The derivation layer has two different outputs:

- normalized relation rows for graph reads, admin summaries, map/place
  projections, and diagnostics;
- related content candidates for strips and cards.

These outputs should share graph/native-field inputs, but they are not the same
contract and should not be collapsed into one ambiguous payload.

## Relation Edge Contract

Derived graph edges must be explainable and reversible. Each edge should carry
enough provenance to answer which field, import, authority row, transcript pass,
or manual correction created it.

Required edge metadata direction:

- source/provenance fields that identify producer, source system, source ref,
  and source field or authority object where available;
- a small lifecycle/status vocabulary for relation rows, such as `derived`,
  `suggested`, `accepted`, `deprecated`, and `rejected`;
- confidence or trust only for sources that need review, not as normal editor
  vocabulary;
- public readers consume accepted/derived public rows, not raw suggestions,
  unless a surface explicitly opts into suggestions.

Editorial signals are not edge lifecycle states. `pin`, `feature`, and
`suppress` remain non-destructive overlays with audit/expiry; they must not be
stored as relation statuses such as "manual-pinned" or "suppressed".

## Admin Constraint

Do not depend on admin presence or public site traffic for healing.

Allowed now:

- explicit WP-CLI sync and backfill commands;
- save hooks for the post currently being edited;
- one-time migrations for source-of-truth changes.

Required direction:

- the same reconcile logic must be idempotent and batchable;
- a real scheduler must call the same derive/sync path as CLI before technical
  repair boxes are hidden for normal editors;
- admin screens must not be the only trigger that keeps graph relations fresh.

Production reconciliation must not rely only on page-request `wp-cron.php`.
Preferred production scheduling is OS-level cron, container scheduling, or a
monitored external scheduler that invokes WP-CLI on a fixed cadence. The cadence,
command, and configuration location must be documented with the rebuild
procedure.

## Dirty Queue Contract

Changes that can affect derived relatedness should mark affected posts dirty.
This includes post saves, place lifecycle changes, archive custom-table writes,
imports, and one-time migrations. CLI and scheduled jobs should drain that dirty
set through the same reconcile path.

The dirty queue is part of the autonomy contract, not an optional polish item.
Until it exists, manual relation screens remain recovery tools for technical
roles and must not be removed outright.

`iss-graph` should own queue draining and remain the single reconcile authority.
Other plugins may mark affected posts dirty, but they should not introduce
parallel drainers.

Queue failure semantics:

- reconciliation is idempotent and at-least-once;
- one malformed post or missing authority ref cannot stall the whole drain;
- failed items get bounded retry/backoff and then dead-letter with the error
  preserved;
- each drain run records processed, failed, dead-lettered, duration, and
  completion time.

## Autonomy Health

The autonomous path must report its own health. A CLI command, status option, or
small admin diagnostic should expose:

- last successful reconcile timestamp;
- current dirty count and oldest dirty age;
- dead-letter count;
- scheduler liveness against the configured cadence.

A read-only invariant check should count structural drift without mutating it:

- orphan relation edges;
- dangling place relation taxonomy terms;
- duplicate graph edges;
- edges missing required provenance;
- posts with populated harvest-critical fields but zero derived relations;
- empty harvest-critical fields by CPT.

When scheduler liveness, queue age, or dead-letter count crosses configured
thresholds, the system should emit an alert through a configured channel such as
email, logs, or webhook.

## Operations Guardrails

Mass relation work must be previewable, bounded, and reversible.

Required operational rules:

- CLI reconcile, harvest, duplicate cleanup, migration, and rebuild commands
  should support dry-run output that reports what would change before writes;
- manual editorial signals must be exportable before migrations that touch graph
  or relation state, with a documented rollback/import path;
- batch jobs need explicit batch size, lock/concurrency protection, maximum
  runtime, and continuation behavior;
- frontend requests must never reconcile or repair graph state; public surfaces
  read projections and fail soft;
- diagnostics are read-only by default;
- repair actions are separate, explicit, nonce-protected, capability-protected,
  and audited.

Test fixtures should cover at least one representative item in each relation
family and content family: one Veranstaltung, one Ausstellung, one Publication,
one archive object, one place, and one person or organization. Acceptance tests
should prove these fixtures derive, project, promote/suppress, rebuild, and
recover as expected.

## Manual Relation Controls

Manual related places and manual related content may stay available for hard
corrections, exceptional editorial context, pinning, suppression, or ordering.

They should not be prominent as normal lay-editor work. They should be treated
as technical or advanced curation, not as required content input.

Manual relation data should remain bounded and non-destructive. It should not
overwrite the autonomous derived baseline.

Manual overrides must be inspectable and reversible. Future admins should be
able to see who pinned, suppressed, promoted, or corrected a relation, when it
happened, why it exists if a note was provided, and remove it without changing
canonical graph data.

Removal should be soft or audited. A hidden deletion that erases who changed a
signal, when, and why does not meet the reversibility requirement.

## Content Promotion

Content promotion is different from relation editing.

Promotion is a visible editorial action and should stay visible in the editor.
It is not graph-specific and should not be hidden with technical relation boxes.

Promotion should express human editorial intent such as featuring, highlighting,
or prioritizing content on a surface. It should remain a non-destructive signal
layer on top of derived relatedness, not a canonical graph edge.

Suppression belongs beside promotion as a protected editorial signal. Editors
need a way to say "do not show this here" as well as "feature this here", but
suppression should be more explicit and easier to audit because it hides derived
content.

Promotion should be easy to find and switch off without opening each editor
screen. Supported public CPT list tables should expose:

- a promotion status column or clear indicator;
- a filter for currently promoted content;
- a nonce- and capability-protected row or bulk action to turn promotion off;
- no graph or ranking vocabulary in the label.

Promotion can be visible to trusted editors or managers. Suppression should be a
curator/manager or technical action unless a future workflow proves a simpler
editor-facing label and audit path.

Suppressions should default to expiring because they hide derived content.
Permanent suppression is allowed only as an explicit, audited curator/manager
decision. Promotion may be long-lived; suppression should decay toward visibility
unless deliberately renewed.

## Rebuild And Recovery

The graph stack should be recoverable after a severe data or admin-workflow
failure. The derived layer is disposable: canonical content, archive authority,
manual curation, and editorial signals are the state that must survive.

Rebuildable derived state includes:

- content post entities and accepted aliases that can be regenerated from
  WordPress posts, titles, identifiers, and profile bindings;
- harvested native relation edges from CPT fields and archive authority refs;
- relation projections for related-content cards, map/place reads, search, and
  diagnostics;
- compatibility indexes such as hidden place-relation taxonomy terms.

Non-derived state must be backed up or exported before destructive work and by
regular off-box backup:

- canonical WordPress content, media, and CPT-native meta;
- archive authority rows and source snapshots;
- graph profile facts, accepted identifiers, and manually curated entity data;
- editorial signals such as promotion, pinning, suppression, and manual
  corrections;
- any manual relation rows that have not yet been replaced by canonical facts.

Every major relation/schema change should document the rebuild command sequence,
the expected verification commands, and whether SQL or uploads artifacts are
required.

The long-term target is one rebuild-and-verify command that reconstructs the
derived layer from the non-derived backup and then runs the invariant monitor.
Backups must include plugin-owned custom tables, not only the standard WordPress
tables.

## Acceptance Criteria

The autonomy claim is accepted only when these drills pass:

1. A normal editor can publish or update content, and scheduled reconciliation
   refreshes public relatedness without administrator action.
2. Reconciliation still runs on cadence when public traffic is near zero.
3. Stopping the scheduler makes the health signal stale and triggers an alert
   within the configured threshold.
4. A malformed dirty item dead-letters without blocking reconciliation for other
   posts.
5. Starting from the non-derived backup, the rebuild-and-verify path recreates
   derived relations and the invariant monitor reports no blocking drift.
6. Fixture-backed dry-run and write-mode checks show the same intended changes,
   and manual signal export can be restored after a migration rehearsal.

## Ownership

- `iss-graph` owns canonical entities, graph relation storage, harvested native
  graph edges, dirty-queue draining, autonomy health, and future autonomous
  reconcile services.
- `iss-relations` owns relation-aware block resolution, relation projections,
  map/place read models, and compatibility with old place relations.
- `iss-content` owns CPT-native fields and editor workflow surfaces that provide
  canonical content facts for harvesting, including soft validation for
  harvest-critical fields.
- `iss-archive` owns archive authority data and object/source relations that
  can feed derived relatedness, and should mark affected posts dirty when
  archive custom-table writes change relation inputs.
- The theme owns public presentation only.

## Non-Goals

- Do not remove manual relation controls only because the autonomous path
  exists.
- Do not turn promotion into graph relation editing.
- Do not make maps or route renderers consume person/organization rows.
- Do not add another parallel relatedness store.
- Do not rely on Gutenberg body markup as relation truth.
- Do not rely on page-request cron for unattended healing.
- Do not let the autonomous path fail silently.
- Do not reconcile or repair relation state during frontend requests.

## Implementation Sequence

1. Inventory editor-visible relation, graph, promotion, suppression, search, and
   availability fields per CPT; classify each as must-show, integrated, hidden
   for editors, migrate, or purge.
2. Collapse place and entity derivation into one derive/read function with
   family-aware projections.
3. Plan duplicate cleanup, then add the additive unique edge identity and upsert
   writes to graph relation storage with source/provenance metadata so later
   mass harvest writes are self-deduplicating and explainable.
4. Add dry-run support for reconcile, harvest, duplicate cleanup, migration, and
   rebuild commands before enabling write-mode batch operations.
5. Extend native-field harvest beyond Veranstaltung venue only where a CPT has a
   real intrinsic field or authority ref.
6. Add harvest-critical soft validation and health counters for fields that feed
   relation harvest.
7. Add a dirty queue for relation-affecting saves, imports, place lifecycle
   changes, and archive writes so CLI and cron reconcile only the affected set.
8. Add batch locks, batch size, maximum runtime, and continuation behavior for
   queued reconcile jobs.
9. Keep CLI sync as the operational reconcile path and add scheduled,
   incremental production scheduling using the same derive/sync functions before
   hiding normal editor recovery paths.
10. Add autonomy health: staleness gauge, invariant monitor, dead-letter
   visibility, and alerting.
11. Keep promotion controls visible and separate from technical relation boxes;
   add list-table promotion indicators, filter, and switch-off actions.
12. Make editorial signals soft-removable or explicitly audited; default
    suppression to expiring before relying on signals as long-lived public
    controls.
13. Add manual-signal export/rollback and verified off-box backup coverage for
    the non-derived state and the standing rebuild-and-verify command.
14. Add fixture-backed acceptance tests for representative content, archive,
    place, and person/organization cases.
15. Hide technical/manual relation boxes for normal editors only after
    integrated controls save to the same authority and editor/save/reload parity
    is verified per CPT and the acceptance criteria pass.
