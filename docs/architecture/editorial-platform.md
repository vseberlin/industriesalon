# Editorial Platform

This is the implementation checkpoint for the SOW in
`/home/vladimir/Downloads/editor-sow.md`.

## V1 Boundary

- `iss-editorial` is an engine-only plugin. It owns versioned JSON document
  storage, section registry behavior, validation, autosave, and normalized read
  models.
- `iss-content` remains the CPT/editor contract owner and opts `ausstellung`
  into the engine through the `iss_editorial_formats` filter.
- `iss-archive`, `iss-graph`, `iss-relations`, and `iss-occurrences` remain the
  canonical owners of their data. `iss-editorial` stores typed references only.
- The theme owns public HTML and CSS. It consumes `iss_editorial_get_read_model()`
  and does not decode storage JSON directly.
- When `iss-editorial` is active, registered editorial post types leave the
  Gutenberg block editor. The expected `ausstellung` editor surface is a custom
  main-canvas composition UI below the title, with section gestures on the left,
  ordered section cards in the main area, and section editing in modals. Media
  selection uses the WordPress media library inside the section modal. Archive
  object selection uses the archive picker modal: attached/context buckets first,
  object thumbnails after bucket choice, and faceted object search as the
  secondary fallback.

## Rollout

The first rollout is one real Ausstellung using `OrderedFormat`. Existing
Gutenberg content remains the fallback. Public JSON rendering is enabled per
post through `_iss_editorial_enabled_ausstellung`; disabled posts keep the
legacy `post_content` path.

Unresolved references are omitted from public output and shown as placeholders
in previews for editors.

## Migration Audit

`wp iss-editorial ausstellung-dry-run` reports read-only migration candidates
for `Kinder im Werk` and `Frauen im Werk` by default. It does not write
postmeta or switch frontend rendering.

The report compares the current legacy `post_content` path with a conservative
OrderedFormat candidate:

- existing JSON document and enabled flag;
- legacy block and text volume;
- candidate section count and section types;
- media/unsupported block counts that require manual curator review.

Use `--posts=<slug-or-id,slug-or-id>` to target a different Ausstellung list and
`--format=json` when the output should be machine-readable.

`wp iss-editorial ausstellung-import-candidate --post=<slug-or-id>` writes the
same conservative candidate into `_iss_editorial_ausstellung`, clears the
autosave meta, and forces `_iss_editorial_enabled_ausstellung` to `0`. It
refuses to replace an existing JSON document unless `--force` is passed.
Legacy image/media blocks are imported as `bildstrecke` sections with
`media_refs` where attachment IDs are available.

Archive-object references may carry bucket provenance (`set_id`, `set_title`,
`member_id`, `member_caption`) when selected through an Archivset. Rendering can
still resolve by object ID, but the provenance stays available for future
captions and source context.

Editors save reviewed JSON changes through the canvas' explicit JSON save
action. That route writes the permanent document and the enabled flag together.

## Static Analysis

`iss-editorial` is part of both `phpstan.neon.dist` `paths` and `scanFiles`.
The repo target runner analyzes changed PHP files one at a time, so new plugin
entrypoints must be in `scanFiles` before include-file globals are visible to
PHPStan. Runtime include guards inside individual include files are not the
right fix for those positives.
