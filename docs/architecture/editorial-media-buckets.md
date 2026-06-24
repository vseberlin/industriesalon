# Editorial Media Intake And Sets SOW

This is the implementation scope for a future shared intake, review, and
promotion workflow. It is not a renderer and not a public content model by
itself. Public pages consume only promoted references.

## Purpose

Editors need a place to collect messy photo and source dumps before material is
curated into a public section. `media_refs`, `object_refs`, and `galerie`
sections remain approved structured content. Intake and Sets are private staging
state before that approval.

The system should standardize the workflow, not the content. Events, projects,
publications, exhibitions, archive objects, and pages remain separate editorial
entities. The common layer is the lifecycle from raw submission to review to
promotion.

Core editorial rule:

```text
Upload first.
Meaning later.
Public only after promotion.
Archive only after stricter curation.
```

## Boundary

- `iss-content` should own the cross-CPT editorial Set workflow if this
  becomes code, because it owns CPT/editor contracts.
- A new isolated intake receiver may exist outside the normal public renderer
  path, but it must communicate through explicit interfaces only.
- `iss-archive` continues to own Archivsets, archive objects, source snapshots,
  archive curation, archive metadata requirements, and archive-object picker
  behavior.
- WordPress media library continues to own attachments.
- `iss-editorial` and `iss-content` documents store only approved references,
  not raw upload state.
- The theme must not read Set state directly.
- Public renderers must ignore Set state entirely.
- `_iss_content_json` and `_iss_editorial_ausstellung` store only promoted,
  approved references.

Do not generalize Archivsets into a universal media/intake storage layer.
Archivsets remain archive-owned saved selections. Cross-CPT intake and review
state belongs behind an `iss-content` editorial workflow interface.

## Terminology

Use `Set` in the editor UI and handoff language. Editors should see names such
as:

- `Fete set`
- `Futura set`
- `Partner photos 2027`
- `Publication source set`

Use `bucket` only as an internal implementation term when useful. The public
and editor-facing concept is a named Set. Agent/user handoffs should also prefer
Set unless discussing the internal storage implementation.

A Set is a private working collection. It may start without a known public
context and later be attached or promoted to one or more editorial targets.

Examples:

```text
Fete set
  -> later attached to Veranstaltung
  -> promoted to Rueckblick
  -> selected items marked archive candidates

Futura set
  -> attached to Projekt
  -> reused in Publication
```

## Non-Goals

- Do not create separate `eventset`, `projectset`, `publicationset`, or
  exhibition-specific dump systems.
- Do not make `galerie` a raw upload dump.
- Do not make public rendering depend on raw intake availability.
- Do not let the archive plugin become the generic media-intake owner.
- Do not import every upload into the archive automatically.
- Do not require users to classify uploads as event, archive, project, or
  publication material before upload.

## Editor Experience

Editors should not look through different Set systems for different events or
content types. The primary UI should be one intake workbench with named Sets:

```text
Intake Workbench
  - new uploads
  - sets
  - uncategorized
  - suggested context
  - suggested use
  - review status
  - rights warnings
  - stale items
```

The default review surface should be a thumbnail grid, not a raw filename list.
Filename tables may exist as secondary admin/debug views only.

Grid tile minimum:

- thumbnail, video poster, document preview, or file-type icon
- status badge
- upload date
- suggested context
- rights/metadata warning
- quick select

Clicking a tile opens a modal for evaluation:

- larger preview
- original filename and file facts
- uploader/source/provenance fields
- rights, consent, credits, and license
- suggested event, Rueckblick, project, publication, exhibition, or page context
- duplicate or near-duplicate warning when available
- approve, reject, promote, retain, delete, and mark archive-candidate actions
- curator/editor notes

Batch actions should support low-friction review:

- approve selected
- reject selected
- move selected to another Set
- attach selected to a Set
- promote selected to a chosen target
- promote or attach the entire Set to a chosen target
- mark selected as archive candidates
- retain selected beyond normal decay

Sets must support preparation before content exists. Editors should be able to
create a Set first, collect material there, and attach it later when the target
Veranstaltung, Rueckblick, publication, project, exhibition, or page exists.

Uncategorized intake is allowed. It is the default holding area for material
that has not yet been assigned to a named Set. Items can be moved from
uncategorized into a Set, moved between Sets, or removed from a Set without
deleting the underlying upload/attachment.

## Contract Shape

The user-facing workbench is shared. Set records may exist with or without
typed editorial contexts:

```json
{
  "schema_version": 1,
  "set_key": "fete-2027",
  "title": "Fete set",
  "set_role": "gallery_candidate",
  "status": "working",
  "contexts": [
    {
      "context_type": "veranstaltung",
      "context_id": "24988",
      "link_role": "source_material"
    }
  ],
  "items": []
}
```

`contexts` may be empty while a Set is being prepared.

Allowed `context_type` values should start with:

- `veranstaltung`
- `rueckblick`
- `ausstellung`
- `projekt`
- `publication`
- `page`
- `archive_set`
- `archive_object`

Allowed `set_role` values:

- `intake`: raw upload or imported material, not reviewed.
- `review`: material under editorial review.
- `gallery_candidate`: likely gallery material, not public-ready.
- `approved_gallery`: approved source for promotion into a gallery section.
- `rueckblick_source`: material prepared for a Rueckblick.
- `source_material`: background/source material that may support writing but is
  not necessarily public media.
- `archive_candidate`: material proposed for archive curation, not yet an
  archive object.

Set statuses:

- `working`
- `reviewing`
- `approved`
- `promoted`
- `retained`
- `stale`
- `archived`

Set item shape:

```json
{
  "kind": "wp_media",
  "source": "wp-media",
  "id": "11408",
  "status": "pending",
  "label": "Optional short editor note",
  "origin": "manual_upload",
  "set_key": "fete-2027",
  "decay_at": "2026-09-24T00:00:00Z",
  "retain": false
}
```

Initial item kinds:

- `wp_media`: references a WordPress attachment ID.
- `archive_object`: references an `iss-archive` object ID, optionally with
  Archivset provenance.
- `external_upload`: reserved for later Event Drop/intake files before they are
  promoted into the media library.

Allowed item statuses:

- `pending`
- `reviewing`
- `approved`
- `rejected`
- `stale`
- `promoted`
- `retained`

## Set Attachment And Movement

Sets can be attached to multiple editorial targets through explicit links. A Set
attachment does not make its items public. Public visibility still requires
promotion into the target document or archive workflow.

Set links should store:

- target type
- target ID
- role, such as `source_material`, `gallery_candidate`, `rueckblick_source`, or
  `archive_candidate`
- position when order matters
- attached by/at audit fields

Items can be moved between Sets. Moving an item changes its working collection;
it does not duplicate the file and does not remove any already-promoted public
reference. If the same underlying file is useful in multiple Sets, store
separate item references to the same attachment/upload rather than duplicating
the binary file.

Set-level actions:

- create empty Set
- rename Set
- attach Set to one or more targets
- detach Set from a target
- move selected items into another Set
- promote selected items
- promote the entire approved Set into a target
- mark selected items or the whole Set as archive candidates
- retain, stale, archive, or delete the Set subject to promoted-item safeguards

## Promotion Rule

Promotion is explicit:

1. Intake item is uploaded or imported.
2. Item appears in the shared intake workbench.
3. Editor leaves it uncategorized or moves it into a named Set.
4. Editor reviews it in the grid/modal UI.
5. Item status becomes `approved`, `rejected`, `retained`, or
   `archive_candidate`.
6. Editor promotes approved material into a target, either item-by-item or as an
   approved Set.
7. The target stores normal promoted references only.
8. The Set/item status becomes `promoted` where applicable.

The public renderer sees only the promoted target references. It never reads raw
intake or Set state.

## Promotion Targets

### Gallery Or Structured Content

Promote selected media into `media_refs` or selected archive objects into
`object_refs`. `galerie` is an approved presentation section. It is not a dump
area.

Photo dumps should land in a Set with `set_role=intake`, `review`, or
`gallery_candidate`. Only selected approved items should be promoted into a
`galerie` section as `media_refs` or `object_refs`.

### Rueckblick

Rueckblick should be treated as a first-class public editorial node when a
post-event report is more than a small note on the original Veranstaltung.

A Rueckblick may relate to one or more Veranstaltungen, projects, exhibitions,
places, or partners, but it owns its own curated post-event story:

- title
- publication date
- body/sections
- selected media refs
- credits and rights state
- related original event(s)

Promotion to Rueckblick should be separate from editing the original event. A
Veranstaltung remains the planned/programme object; Rueckblick is the published
memory/report object.

### Archive Candidate

Archive promotion is a stricter second path. Uploaded material may become part
of the archive as documented history, but not automatically and not through the
same action as Rueckblick/gallery promotion.

Archive promotion should create an archive candidate for `iss-archive` curation.
The archive layer decides whether it becomes an Archivobjekt, enters an
Archivset, remains private, or is rejected.

Archive candidate metadata should require stronger fields before public archive
publication:

- title
- description
- date or date range
- creator, photographer, donor, or uploader when known
- rights, license, and consent state
- provenance/source story
- relation to event, place, person, project, publication, or exhibition
- source file hash
- original filename
- accession or archive status
- visibility/publication decision
- curator notes

Rules:

```text
Every archive object may come from intake.
Not every intake item becomes archive.
Not every Rueckblick item becomes archive.
Archive publication requires stricter curation.
```

## Decay And Retention

Raw intake should decay. Unreviewed uploads should not silently become permanent
CMS structure or permanent storage.

Each raw item should have a decay date unless intentionally retained, promoted,
or handed to the archive workflow.

Suggested lifecycle:

```text
pending
  -> reviewing
  -> approved
  -> promoted

pending/reviewing
  -> stale
  -> deleted or retained

approved
  -> promoted
  -> retained
  -> stale
```

Decay should be visible in the workbench:

- stale badge
- "needs decision" filter
- bulk delete/retain actions
- retention reason when retained

Deletion policy must distinguish:

- raw external uploads
- WordPress attachments
- promoted public references
- archive candidates
- archive objects

Do not delete promoted public media or archive-owned records through the raw
intake or Set cleanup path.

## Interfaces

The intake receiver should expose only explicit operations to WordPress/editorial
systems:

- list intake items by status/filter
- fetch preview metadata and thumbnail URLs
- approve/reject item
- mark item retained
- mark item stale
- promote to WordPress attachment
- promote to Rueckblick/gallery target
- mark as archive candidate
- record audit/log entries

The editorial layer should expose only explicit operations back to intake:

- create or update private Set
- attach Set to one or more contexts
- attach approved item to Set/context
- move items between Sets
- promote approved item into `media_refs` / `object_refs`
- promote approved Set into a target
- report promotion status

The archive layer should expose only explicit curation operations:

- create archive candidate from approved intake item
- map candidate to archive metadata fields
- create/update Archivobjekt after curation
- attach curated object to Archivset
- report archive decision to the intake workbench

## Storage Direction

Start lean. Do not add a custom table until the workbench requires indexed,
cross-context queues, decay sweeps, audit trails, or high-volume filtering.

Phase 1 may use post meta/read models for small contextual Sets and the
existing Event Drop/import paths after files are accepted into trusted storage.

If a table becomes necessary later, keep it plugin-owned, versioned, and
service-backed under `iss-content`; do not create separate event/project/public
media tables.

## Implementation Slices

1. Document and enforce ownership boundaries.
2. Add a private intake/Set read model that supports an uncategorized inbox and
   context-free named Sets, without public renderer changes.
3. Build the shared workbench shell with Set filters and thumbnail grid.
4. Add item modal with preview, metadata, rights, notes, and status actions.
5. Add item movement between Sets and Set attachment to one or more targets.
6. Add promotion from approved items or approved Sets into
   `galerie`/`media_refs`.
7. Add Rueckblick as a first-class promotion target and relation to source
   Veranstaltung.
8. Add decay dates, stale filter, retain/delete actions, and cleanup safeguards.
9. Add archive-candidate handoff to `iss-archive` with stricter required
   metadata.
10. Add background enrichment only after the manual workflow is stable:
   duplicate detection, OCR, speech-to-text, entity suggestions, and archive
   matching.

## Verification

Each implementation slice should verify:

- public pages ignore raw Set/intake state
- broken intake does not break existing Veranstaltung, Ausstellung, project,
  publication, archive, or page rendering
- promoted refs survive editor save/load
- rejected/stale items do not appear publicly
- deletion does not remove promoted public media or archive-owned records
- archive candidates cannot become public archive objects without required
  curation metadata
- responsive thumbnail grid and modal are usable on desktop and mobile
- large batches can be filtered, selected, and batch-reviewed without relying on
  raw filename scanning

## Implementation Notes

A first implementation can reuse:

- WordPress media selection for `wp_media`.
- Existing archive-object picker and Archivsets for `archive_object`.
- Existing Event Drop/import paths only after files are accepted into a trusted
  storage workflow.
- Existing `_iss_content_json` / `_iss_editorial_ausstellung` promoted ref
  contracts.
- Existing Veranstaltung `galerie` renderer for approved public galleries.

Keep public presentation in the theme. Keep intake, review, promotion, and data
contracts in plugins.
