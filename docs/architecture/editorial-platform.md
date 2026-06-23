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

Skin assignment is an editor-visible format-level control because the document
must declare which theme-owned treatment it is using. Layout, variant, and
section-role decisions are not editor controls. For `ausstellung` Phase 3, the
durable surface is `gesture x skin = treatment`: section `type` carries
editorial intent, while the theme chooses the visual treatment for that gesture
inside the active skin. The
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
treatment, `quellenauszug` owns the image/text/quote station treatment and has
a scoped text-position flip for left/right source stations. `kapitel`,
`leitfrage`, `zitat`, and `fliesstext` stay on the generic renderer anatomy and
receive quiet typographic treatments in skin CSS; their content uses the common
`kicker`, `title`, `body`, `quote`, and `attribution` fields rather than
parallel gesture-specific keys. `objektfokus` remains reserved for
archive-object grid treatments. `vollbild` is a one-image, full-viewport
gesture; editors are guided toward 16:9 media and the theme uses a generic
`viewport-image` treatment with cover-cropped imagery and an ink-panel text
overlay. Theme partials are reserved for true structural exceptions, not for
normal gesture rendering.

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

For Veranstaltungen, the first Phase 1 slice lives in `iss-content`, not in a
new parallel event plugin. `plugins/iss-content/includes/veranstaltungen-registry.php`
defines the initial entity/shape contract for `_iss_entity_key`, and
`wp iss-content veranstaltungen-dry-run` audits existing posts without writing
content or switching renderers. The dry-run maps current `_iss_event_layout` and
`_iss_event_format` values to candidate entities, validates required query facts
such as `iss_start_datetime`, and marks rows as `safe`, `review`, or `blocked`.
This slice deliberately leaves the current editor, occurrence sync, timeline
rendering, and legacy single-template rendering unchanged.

The second Veranstaltung slice exposes `_iss_entity_key` in the existing
Veranstaltung metabox as the editor-visible `Typ` choice. It is saved
independently from the old `_iss_event_layout`, `_iss_event_format`, and
`_iss_event_scheme` fields, which remain in place only to keep the current
theme renderer stable during migration. A set `Typ` is therefore the semantic
future contract, not yet the rendering switch.

The third Veranstaltung slice makes the occurrence provider shape-aware for
posts that already have `_iss_entity_key`. Unset legacy Veranstaltungen continue
to project through `iss_programme_enabled` and the existing date facts. Once a
post is explicitly set to `report.rueckblick`, the provider returns no event
occurrence, so a normal save/sync removes that post from upcoming timeline
queries while preserving the legacy single-page renderer.

The fourth Veranstaltung slice adds the controlled curation write path:
`wp iss-content veranstaltungen-set-entity --post=<id-or-slug> --entity=<key>`.
It is dry-run by default and requires `--yes` to write `_iss_entity_key`. The
command validates required query facts, blocks unsafe writes unless `--force` is
explicit, and calls `iss_occurrences_sync_source()` after a successful write when
the occurrence plugin is active. This is the preferred path for scripted/manual
conversion instead of ad hoc postmeta edits.

The fifth Veranstaltung slice centralizes required-fact checks in the registry.
The existing Veranstaltung status box now reads the selected `Typ`: unset legacy
posts still show the old start-date expectation, event entities check their
timeline facts, and `report.rueckblick` checks publication state instead of
requiring an event start. The CLI uses the same helper so editor feedback and
scripted curation fail for the same reasons.

The sixth Veranstaltung slice adds a curation dashboard to the WordPress admin
list table: a `Typ` column plus filters for each concrete `_iss_entity_key` and
for unset posts. This remains a read-only admin affordance over the semantic
type field; it does not change rendering, occurrence rules, or migration state.

The seventh Veranstaltung slice surfaces the legacy-derived suggestion directly
in the Veranstaltung metabox. The suggestion helper is shared with
`veranstaltungen-dry-run`, including review downgrades such as `layout=fest`
conflicting with a non-festival candidate. The hint is read-only; editors still
choose and save `_iss_entity_key` deliberately.

The eighth Veranstaltung slice exposes the selected semantic `Typ` to the theme
as stable body classes on singular Veranstaltung pages:
`iss-event-entity-*`, `iss-event-shape-*`, and `iss-event-surface-*`. These
classes appear only when `_iss_entity_key` is set. Unset legacy posts continue
to emit only the existing layout/scheme/format classes. No CSS or renderer
branch is introduced in this slice.

The ninth Veranstaltung slice makes the dry-run report directly actionable by
adding a `set_command` field to table and JSON output. The generated command
omits `--yes`, so copied recommendations still run through the guarded dry-run
path before a curator intentionally applies them.

The tenth Veranstaltung slice turns the dry-run report into a queue tool:
`--status=<safe|review|blocked|converted>`, `--entity=<entity-key>`, and
`--missing-facts` filter the computed audit rows after validation. This keeps
batch review focused while preserving the same read-only audit logic.

The eleventh Veranstaltung slice adds the standalone registry guard
`wp iss-content veranstaltungen-registry-check`, with the same
`iss-content-model` alias. It validates the entity registry without scanning
posts and is the cheap precondition check before staging, CI, or renderer work
depends on `_iss_entity_key`.

The twelfth Veranstaltung slice connects the curated `Typ` to the graph/search
contract. For Veranstaltungen with `_iss_entity_key`, `iss-graph` now derives
the public offer subtype from that semantic key instead of the legacy
layout/format fields. `report.rueckblick` maps to a dedicated `event_report`
offer subtype, while unset legacy posts keep the existing
`_iss_event_layout` / `_iss_event_format` fallback.

The thirteenth Veranstaltung slice fills in the authoring-facing part of the
registry contract without exposing a new editor. Every entity now declares its
domain, post type, icon, and query/editor field list. The registry check
validates field types, relation targets, and that shape-required facts are
present and marked required on each entity. This is the future generated-form
contract; current legacy rendering and content storage remain unchanged.

The fourteenth through seventeenth Veranstaltung slices complete the mechanical
pre-editor layer. `iss-content` now exposes a shape-aware read repository for
upcoming events, archives, reports, homepage teasers, related items, and the
menu next-event lookup. Normalized query facts are registered as
`_iss_datetime_start`, `_iss_datetime_end`, and `_iss_published_at`, with
save/set-entity synchronization from the existing legacy date fields. A raw
query audit prevents new direct `veranstaltung` post queries outside the
approved repository/curation tooling. `_iss_content_json` and hidden
`_iss_skin_override` are registered as the future structured document shell,
guarded by `veranstaltungen-content-audit`, but no real content document is
generated yet.

The local curation pass has now assigned `_iss_entity_key` to every remaining
Veranstaltung and moved post `25763` to `fuehrung` as `Sonderführung`.
`wp iss-content veranstaltungen-dry-run` reports `25 safe`, `0 review`,
`0 blocked`, and `25 converted`. The portable source-state artifact is
`ops/sql/2026-06-23-veranstaltungen-entity-migration.sql`; occurrence rows and
graph/search rows remain projections and should be regenerated on the target
after SQL import.

The next Veranstaltung slice is the structured JSON editor. Start from the
registered `_iss_content_json` shell and registry-declared entity fields rather
than adding a parallel event editor. The first version should be entity-aware
for required/optional sections, keep the legacy body as the migration source,
store reviewed structure in `_iss_content_json`, and avoid switching the public
renderer until a real post proves save/reload, audit, and fallback behavior.

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
