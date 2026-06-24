# Editorial Media Buckets

This is the contract stub for future media intake/review buckets. It is not a
renderer, not a new public content model, and not a new storage implementation
yet.

## Purpose

Editors need a place to collect messy photo and source dumps before material is
curated into a public section. `media_refs`, `object_refs`, and `galerie`
sections remain approved structured content. Buckets are the private staging
area before that approval.

## Boundary

- `iss-content` should own the cross-CPT editorial bucket workflow if this
  becomes code, because it owns CPT/editor contracts.
- `iss-archive` continues to own Archivsets, archive objects, source snapshots,
  and archive-object picker behavior.
- WordPress media library continues to own attachments.
- The theme must not read bucket state directly.
- Public renderers must ignore bucket state entirely.
- `_iss_content_json` and `_iss_editorial_ausstellung` store only promoted,
  approved references.

## Contract Shape

A bucket is attached to a typed editorial context:

```json
{
  "schema_version": 1,
  "context_type": "veranstaltung",
  "context_id": "24988",
  "bucket_role": "gallery_candidate",
  "label": "Editor photo dump",
  "items": []
}
```

Allowed `context_type` values should start with:

- `veranstaltung`
- `ausstellung`
- `projekt`
- `publication`
- `page`
- `archive_set`
- `archive_object`

Allowed `bucket_role` values:

- `intake`: raw upload or imported material, not reviewed.
- `review`: material under editorial review.
- `gallery_candidate`: likely gallery material, not public-ready.
- `approved_gallery`: approved source for promotion into a gallery section.
- `source_material`: background/source material that may support writing but is
  not necessarily public media.

Bucket item shape:

```json
{
  "kind": "wp_media",
  "source": "wp-media",
  "id": "11408",
  "status": "pending",
  "label": "Optional short editor note",
  "origin": "manual_upload"
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
- `approved`
- `rejected`
- `promoted`

## Promotion Rule

Promotion is explicit:

1. Bucket item is reviewed.
2. Item status becomes `approved`.
3. Editor promotes it into a structured document section.
4. The document stores a normal `media_refs` or `object_refs` entry.
5. Bucket item status becomes `promoted`.

The public renderer sees only step 4. It never reads the bucket.

## Gallery Rule

`galerie` is an approved presentation section. It is not a dump bucket.

Photo dumps should land in a bucket with `bucket_role=intake`,
`review`, or `gallery_candidate`. Only selected approved items should be
promoted into a `galerie` section as `media_refs` or `object_refs`.

## Implementation Notes

Do not add a custom table until an editor UI needs persistence beyond existing
post meta and Archivsets. A first implementation can be a metabox/read model
that attaches a bucket to one post context and reuses:

- WordPress media selection for `wp_media`.
- Existing archive-object picker and Archivsets for `archive_object`.
- Existing Event Drop/import paths only after files are accepted into a trusted
storage workflow.

If a table becomes necessary later, keep it plugin-owned, versioned, and
service-backed under `iss-content`; do not create separate `eventset`,
`projectset`, or `publicationset` systems.
