# Graph Entity Hygiene Review - 2026-06-12

Status: local read-only review.

This review uses the committed `wp iss-graph entity-hygiene-audit` command and
read-only SQL summaries against the local graph tables. It does not change graph
rows, aliases, identifiers, posts, uploads, or templates.

Implementation follow-up: the code guard now prevents known organization
abbreviations and official names from being proposed on non-organization
entities. `wp iss-graph sync-aliases --dry-run --limit=25` reports 30 changed
entities, 64 generated aliases removed, and 0 generated aliases added. The
persisted alias replay is still pending; the reviewed target data step is
prepared in `ops/sql/2026-06-12-graph-alias-backfill-replay.sql`.
The post-replay curated organization seed for `KWO` and `AEG` is prepared in
`ops/sql/2026-06-12-graph-canonical-kwo-aeg-organizations.sql` and should be
applied only after the generated alias replay is clean.

## Commands

```bash
wp iss-graph entity-hygiene-audit --limit=50 --format=json
```

Additional local SQL summaries grouped exact normalized-name matches and
searched related organization rows. Staging comparison is still pending.

## Summary

The high-volume duplicate names are mostly generated aliases from
`entity_alias_backfill`, not trusted identifier conflicts. The top duplicate
groups include broad archive/title fragments such as `hf` (40 entities),
`fernseh` (25), `x2-foto-1958` (20), `v1-foto-1958` (15),
`fernschreibmaschine` (13), and `land-berlin` (11). These are not good first
merge targets.

The focus terms show a narrower problem: known organization abbreviations and
official names are being generated as aliases on non-organization entities when
their titles contain those organizations. That makes entity resolution
ambiguous even when the content object itself is not the organization.

## Focus Terms

### Industriesalon Schöneweide

Broader audit match count: 11 entities.

Exact normalized-name collision:

- `#150` `organization` - `Industriesalon Schöneweide`, source
  `archive_institution:institution:29`.
- `#122` `place` - `Industriesalon Schöneweide /`, source
  `register_post:17960`.

Related hidden organization candidates also exist:

- `#123` `Industriesalon Schöneweide e.V.`
- `#3459` `Industriesalon Schöneweide e.V. (Betreiber/Träger; Gebäudeeigentum nicht bestätigt)`
- `#103` `Industriesalon Schöneweide e.V. (Projektinitiator)`
- `#3435` `Industriesalon Schöneweide e.V. als Projektinitiator; Standort/Eigentum offen`

Review decision needed: choose one canonical organization row for the current
association/institution identity, then decide whether archive institution
identifier `institution:29` belongs on that row or remains a separate source
identity. Do not merge the place row into an organization row.

### WF

Exact `wf` match count: 13 entities.

Organization rows:

- `#3463` `Werk für Fernmeldewesen (WF)`
- `#3462` `Werk für Fernsehelektronik (WF)`

Non-organization alias leakage:

- 7 archive objects
- 1 post
- 1 publication
- 2 videos

Review decision needed: do not canonicalize `WF` to a single entity before
curatorial review. It appears to refer to at least two organization identities.
The immediate implementation should stop generated `WF` abbreviations from
being attached as identity aliases to archive objects/posts/publications/videos.

### KWO

Exact `kwo` match count: 6 entities, all non-organization:

- `#129` `place` - `Wilhelminenhofstraße / Rathenaustraße (KWO)`
- `#3197` `post`
- `#3211` `publication`
- `#3217` `publication`
- `#3229` `video`
- `#3249` `video`

Related title search found more content/place rows, but no `organization` row
for `Kabelwerk Oberspree` / `VEB Kabelwerk Oberspree`.

Review decision needed: create or resolve a canonical organization row for
`Kabelwerk Oberspree` before any alias rewrite. Then `KWO` and
`VEB Kabelwerk Oberspree` can become organization aliases/evidence instead of
content-entity aliases.

### TRO

Exact `tro` match count: 1 entity:

- `#3336` `organization` - `TRO`

No immediate ambiguity was found. Later enrichment can add the official name
`VEB Transformatorenwerk Oberspree` if the curator confirms it.

### AEG

Exact `aeg` match count: 8 entities, all non-organization:

- 5 archive objects
- `#128` `place` - `Mathildenstraße / AEG-Rüstungsproduktion`
- 2 publications

No canonical `organization` row for `AEG` / `Allgemeine
Elektricitäts-Gesellschaft` was found in the local graph. The generated
official-name variants are also attached to the same non-organization rows.

Review decision needed: create or resolve a canonical organization row for
`Allgemeine Elektricitäts-Gesellschaft`, then attach `AEG` and official spelling
variants there. Non-organization rows should retain their titles/source text,
but not these generated organization aliases.

## Recommended Implementation Order

1. Tighten `entity_alias_backfill` known-pattern behavior. Implemented in code;
   persisted replay is still pending.
   - keep title spelling variants for the owning entity
   - generate known organization abbreviations and official names only for
     `organization` entities
   - do not generate `WF`, `KWO`, `AEG`, `TRO`, or official organization names
     as identity aliases on archive objects, posts, publications, videos, or
     places
2. Add a focused dry-run or audit mode around alias backfill changes so the
   number of removed generated aliases is visible before write replay.
   Implemented as `wp iss-graph sync-aliases --dry-run`.
3. After staging dry-run comparison, apply
   `ops/sql/2026-06-12-graph-alias-backfill-replay.sql` to create a rollback
   snapshot, then replay alias backfill with `wp iss-graph sync-aliases`.
4. Add missing canonical organization rows for `KWO` and `AEG` through a
   reviewed graph data step, not as automatic resolver output. Prepared as
   `ops/sql/2026-06-12-graph-canonical-kwo-aeg-organizations.sql`; apply after
   the generated alias replay reports no remaining changes.
5. Revisit `Industriesalon Schöneweide` organization variants after generated
   alias leakage is reduced.

## Non-Actions

- No entity merge now.
- No alias deletion now.
- No automatic reassignment of identifiers.
- No change to public rendering.
- No change to transcript evidence promotion. The video transcript bridge
  already excludes `entity_alias_backfill` names from candidate matching.
