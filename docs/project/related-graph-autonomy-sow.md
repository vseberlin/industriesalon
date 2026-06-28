# Related Graph Autonomy SOW

This SOW defines the direction for ISS related places, related content, graph
derivation, and editorial promotion. It exists so future relation work does not
silently make lay editors or real admins load-bearing for public relatedness.

## Goal

The site must survive years without a real admin actively repairing relations.
Related places and related content should be derived from canonical content
fields, graph entities, native CPT fields, archive authority data, and scheduled
or CLI reconciliation.

Manual relation controls may improve or correct results, but their absence must
not make public relatedness go dead.

## Core Rule

Hard-coded related places and hard-coded related content are not backbone data.
They are editorial correction or curation inputs.

Normal public relatedness must be rebuildable from:

- canonical WordPress content fields;
- native CPT fields such as venue, subject, transcript/entity refs, dates, and
  archive/object authority refs;
- graph entity relations across place, person, and organization families;
- harvested graph edges written by deterministic sync/reconcile code.

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

## Admin Constraint

Do not depend on admin presence for healing.

Allowed now:

- explicit WP-CLI sync and backfill commands;
- save hooks for the post currently being edited;
- one-time migrations for source-of-truth changes.

Required direction:

- the same reconcile logic must be idempotent and batchable;
- future cron should call the same derive/sync path as CLI;
- admin screens must not be the only trigger that keeps graph relations fresh.

Changes that can affect derived relatedness should mark affected posts dirty.
This includes post saves, place lifecycle changes, archive custom-table writes,
imports, and one-time migrations. CLI now, and cron later, should drain that
dirty set through the same reconcile path.

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

## Ownership

- `iss-graph` owns canonical entities, graph relation storage, harvested native
  graph edges, and future autonomous reconcile services.
- `iss-relations` owns relation-aware block resolution, relation projections,
  map/place read models, and compatibility with old place relations.
- `iss-content` owns CPT-native fields and editor workflow surfaces that provide
  canonical content facts for harvesting.
- `iss-archive` owns archive authority data and object/source relations that
  can feed derived relatedness.
- The theme owns public presentation only.

## Non-Goals

- Do not remove manual relation controls only because the autonomous path
  exists.
- Do not turn promotion into graph relation editing.
- Do not make maps or route renderers consume person/organization rows.
- Do not add another parallel relatedness store.
- Do not rely on Gutenberg body markup as relation truth.

## Implementation Sequence

1. Collapse place and entity derivation into one derive/read function with
   family-aware projections.
2. Extend native-field harvest beyond Veranstaltung venue only where a CPT has a
   real intrinsic field or authority ref.
3. Keep CLI sync as the operational reconcile path now.
4. Add a scheduled, incremental reconcile later using the same derive/sync
   functions.
5. Keep promotion controls visible and separate from technical relation boxes.
6. Add a dirty queue for relation-affecting saves, imports, place lifecycle
   changes, and archive writes so CLI/cron reconcile only the affected set.
7. Harden graph relation storage with an additive unique edge identity and
   upsert writes after duplicate cleanup has been planned and verified.
