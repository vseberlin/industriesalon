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

Skin and layout decisions are not editor controls. A document stores a known
skin slug, but the main canvas does not expose skin, variant, layout, or section
role fields. For `ausstellung` Phase 3, the durable surface is
`gesture x skin = treatment`: section `type` carries editorial intent, while the
theme chooses the visual treatment for that gesture inside the active skin. The
first named skin is `frauen-im-werk`. Optional theme partials resolve by
`sections/{skin}/part-{type}.php`, then `sections/part-{type}.php` before
falling back to the generic renderer. The generic Ausstellung shell stays in
`themes/industriesalon/assets/css/single-ausstellung.css`; per-skin gesture
treatments live under `themes/industriesalon/assets/css/skins/` and are
enqueued only when the enabled editorial document resolves to that skin. The
default theme renderer emits a universal section anatomy with stable `inner`,
`media`, `copy`, `kicker`, `body`, `quote`, and `refs` slots; skins map gestures
onto those slots in CSS. `kicker` is a first-class JSON section field because it
is shared site language, not a skin decoration. In the first `frauen-im-werk`
treatment, `quellenauszug` owns the image/text/quote station treatment and
`objektfokus` remains reserved for archive-object grid treatments. `vollbild`
is a one-image, full-viewport gesture; editors are guided toward 16:9 media and
the theme uses a generic `viewport-image` treatment with cover-cropped imagery
and an ink-panel text overlay. Theme partials are reserved for true structural
exceptions, not for normal gesture rendering.

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

Editors save reviewed structure changes through the canvas' explicit `Speichern`
action. That route writes the permanent JSON document and the enabled flag
together. WordPress' default update action is only needed for WordPress-owned
post fields such as title, slug, status, taxonomies, or other metabox data.

For the current Ausstellung pilot, Phase 2 covers archive-object and media
selection only. Archive-object selection uses the existing bucket-first archive
picker and stores typed references with optional bucket provenance. Media
selection uses WordPress `wp.media`. The SOW-wide Relation Picker is deferred
until entity-editor work needs relationship editing.

## Static Analysis

`iss-editorial` is part of both `phpstan.neon.dist` `paths` and `scanFiles`.
The repo target runner analyzes changed PHP files one at a time, so new plugin
entrypoints must be in `scanFiles` before include-file globals are visible to
PHPStan. Runtime include guards inside individual include files are not the
right fix for those positives.
