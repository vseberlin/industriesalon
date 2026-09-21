# Editorial Platform

This is the implementation checkpoint for the SOW in
`/home/vladimir/Downloads/editor-sow.md`.

## V1 Boundary

- `iss-editorial` is an engine-only plugin. It owns versioned JSON document
  storage, section registry behavior, validation, autosave, and normalized read
  models.
- `iss-content` remains the CPT/editor contract owner and opts formats such as
  `ausstellung`, `projekt`, and `rueckblick` into the engine through the
  `iss_editorial_formats` filter.
- `iss-archive`, `iss-graph`, `iss-relations`, and `iss-occurrences` remain the
  canonical owners of their data. `iss-editorial` stores typed references only.
- The theme owns public HTML and CSS. It consumes `iss_editorial_get_read_model()`
  and does not decode storage JSON directly.
- When `iss-editorial` is active, enabled documents and eligible new auto-drafts use
  the shared editor. Existing disabled documents retain their legacy editor and
  public authority. The shared editor surface is a custom
  main-canvas composition UI below the title, with section gestures on the left,
  ordered section cards in the main area, and section editing in modals. Media
  selection uses the WordPress media library inside the section modal. Archive
  object selection uses the archive picker modal: attached/context buckets first,
  object thumbnails after bucket choice, and faceted object search as the
  secondary fallback.
- Media/object intake Sets are implemented in `iss-content` and documented in
  `docs/architecture/editorial-media-buckets.md`. Sets are private review
  state; public renderers consume only saved `media_refs` / `object_refs`.

## Material and optional Rückblicke

Events, exhibitions, projects and tours can create a separate optional Rückblick
from **Material & Rückblicke → Rückblick anlegen**. The original offer/date stays
intact. A Rückblick can refer to several source posts and has an optional date for
one occurrence of a recurring tour. Sources are canonical `iss-graph` edges:
family/type `reports_on`, source system `iss_content_rueckblick`, from report to
source. Publication/withdrawal updates the edge flag; public cards also verify
current post status. The theme reuses the ordered exhibition renderer/Chronik skin
and shared related-card renderer, with a native `single-rueckblick` template.
Relationship cards are composed on the native `core/post-content` block for the
queried post. They must not be appended through `the_content`: automatic card
excerpts also run that filter and would recursively render the relationships.

The source and report share existing private Sets. If necessary a source Set is
created when connecting the report, so later submissions enter that same Set.
Removing a report's source relation does not delete previously shared material.
**Zum Entwurf hinzufügen** requires reviewed material, an explicit destination,
and current document/draft tokens. Images enter `galerie`, documents `material`.
It prepares the current editor's native autosave; restore it in the editor, inspect
preview, then Save Draft / Update / Publish normally. It never silently enables
an existing legacy document. `editorial_set_item_id` records provenance; actual
canonical saves record all confirmed uses in the existing Set audit history.
Use history is historical and does not claim that removed references remain live.
The Set picker excludes unapproved/rejected items and pages through Sets/items.
Validation rechecks stamped references before save/preview, including approval
withdrawn after a draft was prepared. Deleted sections do not count as uses.

**Beiträge von Gästen → Upload geöffnet**, followed by native Save/Update,
controls a shareable link bound to one post. Closing and reopening invalidates
older links. Add an `upload_intake` section for a public invitation, or share the
link directly. Existing upload sections remain open until explicitly closed;
new content defaults closed. The existing Event Drop receiver loads WordPress to
verify the post and link on every GET/POST. Legacy code URLs may upload only into
an open, resolvable context. Files remain in intake for rights review. Supported
extensions: JPG/JPEG, PNG, GIF, WebP; MP4/MOV/M4V/MKV/WebM/AVI; ZIP;
PDF, DOCX, PPTX, XLSX, ODT. The server verifies extension/MIME compatibility.

Validation commands (local Docker stack):

```bash
docker compose run --rm -T --no-deps -v "$PWD/tests/e2e/bin:/tmp/iss-tests:ro" wpcli eval-file /tmp/iss-tests/editorial-sets.php --allow-root
node tests/e2e/bin/editorial-set-ui.cjs
```

The integration harness creates and removes its own records, including momentary
local publication fixtures to test public/withdrawn report edges. It verifies that
pre-existing Sets/items/links are unchanged. It must not run against production.

## Shared editing and recovery

Eligible landing pages, exhibitions, projects, tours, publications, retrospectives,
Places and events share `iss-editorial`'s section canvas, WordPress text editor,
media/link controls, keyboard reordering, trash, preview and recovery controls.
The section registry still decides what each content type supports. Dates, booking,
route stations and other structured facts retain their owning plugin interfaces.
This does not migrate other pages or disabled CPT documents into JSON.

Events register an adapter from `iss-content` to the existing `_iss_content_json`
key. `_iss_entity_key` continues to determine event structure and public skin.
The old event script is only a fallback when the shared engine is inactive.
Event material lists and existing references are preserved. Automatic landing
content choices and their treatments come from one PHP registry entry.
Section, archive and Set dialogs share one focus lifecycle: Tab stays inside
the active dialog, Escape closes that layer, and focus returns to its opener.
The pickers accept the shared focus handler as an optional callback, preserving
their existing standalone consumers. Adding archive references keeps the labels
and context of references already selected; panel counts update with their lists.

Section edits, title and excerpt are autosaved after an idle interval to the
current author's native WordPress autosave revision. A successful server response
is required before reporting that a draft is secured. Reloading offers explicit
recovery or discard when a different own draft exists; neither choice publishes.
This choice appears above the section canvas. Until it is made, the inactive
canvas is hidden and preview is disabled, rather than displaying unresponsive
section buttons. **Entwurf weiterbearbeiten** restores the draft and focuses its
first edit control; **Entwurf verwerfen** opens the saved version only after the
server acknowledges the discard. Failed discard keeps the choice available.
The classic edit screen's post-field-only cleanup must not delete editorial
autosaves containing JSON changes. A narrowly scoped `pre_delete_post` filter
retains those revisions on edit-screen GET requests; normal save and explicit
draft discard keep their native deletion path.
Draft recovery preserves unfinished entries, including a link whose address is
still missing. Such a draft is secured with a diagnostic; preview and publication
wait until the entries are completed or removed.
WordPress Update / Publish saves the permanent document and its enabled state.
The canvas replaces classic autosave for these fields, while heartbeat keeps
WordPress's post locking. Other fact and relation fields retain their existing
save contracts; they are not part of JSON draft recovery.
Project ordering joins the original `wp_insert_post_data` update, avoiding a
recursive `save_post` update that would invalidate the editorial version token.

Canonical and draft tokens reject stale saved versions and competing tabs.
Validation runs before the main WordPress update and before owner save hooks:
malformed JSON, unsupported schema/sections, unsupported populated section fields,
incomplete list entries, invalid registered choices and incompatible slots fail
without normalizing away the submitted content. An invalid stored document is
shown with a blocking diagnostic. Existing hidden gesture types remain visible
in stored documents even when they are unavailable in the insertion palette.
Security sanitization remains in the storage layer.

Document, skin and enabled metadata, plus event structure, participate in WordPress revisions. The
native revision comparison includes section changes; restoration invokes the
existing document-saved contract to rebuild owner projections. Preview reads
the current author's draft only for the requested post.

Regression checks:

- `node --test tests/e2e/bin/editorial-ui.cjs` checks the common DOM interactions,
  recovery and failed-save behavior; it stubs WordPress rich editing and is not a
  substitute for browser checks of TinyMCE, media dialogs or responsive layout.
- `docker compose run --rm -T --no-deps -v "$PWD/tests/e2e/bin/editorial-storage.php:/tmp/editorial-storage.php:ro" wpcli eval-file /tmp/editorial-storage.php --allow-root`
  validates stored documents and exercises drafts, version conflicts, atomic
  validation, native revision restore, authenticated admin/AJAX and preview
  requests using temporary records. It removes
  its fixtures and verifies existing post content/meta are unchanged.

This editor rollout requires code only: no content or template migration and no
uploads artifact. Existing enabled flags and database template overrides remain
the authority for already published content.

### Landing rich text and live preview

Landing documents support versions 1, 2 and 3. Other formats still support version
1. The explicit **Textfarben aktivieren** action converts legacy plain item
descriptions to escaped rich text in the author's draft, including deleted
sections. Version-2 documents are prepared as version 3 in the editor copy,
including recovered drafts; this does not write canonical content. The format registry's
`rich_text` profiles declare block prose, inline descriptions and descriptions
inside a linked card. The last profile excludes hyperlinks to prevent nested
anchors; headings, labels and factual fields remain plain text.

Rich-text landing documents use the installed WordPress TinyMCE with a compact toolbar. **Link**
opens the full native dialog, including link text when no text is selected and
internal-content search. **Farbe** and **Marker** offer the effective theme
palette, custom six-digit RGB values, session-local recent colours and separate
resets. Undo, selection and links survive colour changes. Version 3 stores named
palette choices as `span.iss-ink-preset-{slug}` / `span.iss-mark-preset-{slug}`;
the server allowlists effective WordPress palette slugs and the theme resolves
its preset variables. Custom colours stay under **Eigene Farbe**, with contrast
guidance. They retain `span.iss-ink-rrggbb` / `span.iss-mark-rrggbb`, including
existing saved colours; there is no nearest-colour conversion. Arbitrary classes
and CSS are excluded. The theme emits scoped stylesheet rules, not style attributes.
Block prose permits paragraphs, emphasis, lists, breaks and safe links; short
descriptions permit their inline subset. Unsupported imported markup stays
visible for review until the editor explicitly chooses **Formatierung
vereinfachen**; server validation also rejects lossy normalization.

Rich-text landings use a persistent three-pane workspace: section outline, the
actual WordPress page preview, and a tabbed inspector reusing the section form
controls. The preview has 1280/768/390px viewports. Narrow workspaces use pane
selection. The workspace starts expanded, with one compact top bar and separate
acknowledgements for draft saving and preview freshness. Covered native controls
are inert only while expanded; leaving restores their prior state. Publishing
navigation focuses the native control without submitting. Searchable grouped
insertion, gaps, keyboard reordering and recoverable trash/undo use the existing
document state. Native save,
publication and revision history remain authoritative. The old modal is retained
under **Weitere Werkzeuge** during UAT and remains the editor for other formats.

Editor chrome uses the shared `--iss-editor-*` tokens for warm neutral panes,
readable fields, focus and selection. Existing workspace, text-control and frame
stylesheets own their respective surfaces; native media/link dialog adapters are
scoped to the authoring screen. The public theme and authored content palette
remain independent. See the [styling contract](../project/editor-workspace-plan.md#editor-styling)
for responsive behavior and the limited WordPress compatibility exception.

Title, kicker, body and lead can be edited directly in the authenticated canvas
with visible buttons, double-click or Enter. The same WordPress text engine and
colour/link contracts apply. Input is sent to the parent for autosave before blur;
Escape restores only the active field. Snapshot-bound messages check source,
origin, token, explicit field names, session and increasing sequence. Snapshot
indices map to the original section objects after reordering. A stale field
cannot start an edit. Structural changes wait for the active edit to finish.
Frame replacement is deferred during typing and reconciles with the real renderer
after completion. All fields remain available in the inspector; individual item
text and generated content do not become editable in the canvas in this slice.
Scrolling alone does not change the selected section. See the
[workspace plan](../project/editor-workspace-plan.md) for acceptance and scope.

The existing serialized autosave queue coalesces edits after
350ms idle time. Only an acknowledged, valid, current draft loads a new preview.
The iframe handshake checks source, origin and exact snapshot token; an older
response cannot replace newer work. Pending frames replace the displayed frame
only after readiness, preserving scroll while leaving TinyMCE mounted. Invalid
input and failed refreshes retain the previous valid preview with a visible
stale-state message. HTTP 409 responses notify the pending frame immediately,
with the same source/origin/token checks. Unfinished input still belongs to native draft recovery.
The embedded page disables navigation and form submission; the separate-window
preview remains available. No route/date/relation panel is saved by this queue.

The preview bridge and source-section markers are emitted only for an
authenticated, authorized WordPress preview of the matching draft. Public HTML
keeps its existing anchors and contains neither bridge nor editing markers.
`workspace.js` owns the pane layout, `admin.js` retains document state and field
controls, `rich-text.js` owns text controls, `live-preview.js` owns frame lifecycle, and `preview-frame.js` owns the authenticated frame interaction.
The preview selection stylesheet also loads only in the authenticated embedded
page. `includes/rich-text.php` and `includes/preview.php` implement their server
contracts; public rendering remains in the theme. Each embedded request validates
one snapshot and reuses its document, enabled state and resolved references.
A concurrent autosave cannot replace that snapshot during rendering. The cache
is request-local and keyed by user, post, format and token; ordinary API reads
remain fresh after writes. The iframe sandbox restricts interactions such as
forms/popups; it is not the authentication boundary.

Version 3 registers **Seitenauftakt** (`feature.opening`) explicitly. It requires
first position, `frontpage` skin, title and an image. Incomplete or moved openings
remain recoverable drafts but fail preview/publication validation; the editor
keeps the last valid preview and explains what needs fixing. Cards show the role,
and a notice explains when the template opening is active. Version-2 public
openings retain their previous interpretation; the server maps an existing valid
implicit opening to the explicit role only in the editor copy. Version 1 remains
opt-in. Landing treatments carry schematic/hint metadata in the existing format
registry and appear as labelled native radio choices with miniature diagrams.
Palette and section cards reuse those diagrams. Lead and body render separately
through the storage prose allowlist, including the existing map-note placement.

Deploy the version registry, editor, sanitizer and theme together before saving
version-3 content. Keep the version-3 reader/sanitizer/renderer if reverting the
new UI afterward. The front-page pilot is an author's private autosave using
existing media, so these code changes require no database migration or uploads
artifact. Moving the accepted composition to another site is a separate content
delivery with destination/media mapping. See the
[front-page pilot plan and review](../project/editorial-rich-text-preview-plan.md).

## Rollout

The first rollout is one real Ausstellung using `OrderedFormat`. Existing
Gutenberg content remains the fallback. Public JSON rendering is enabled per
post through `_iss_editorial_enabled_ausstellung`; disabled posts keep the
legacy `post_content` path.

Places now have the first complete pilot on the same engine. `iss-content`
registers the existing `register_place` CPT and the `place` format with
`intro`, typed repeatable `epoche`, `gegenwart`, `galerie`, `material`, and
`upload_intake` gestures. Structured address, coordinates, construction,
monument, visibility, and current-use facts remain outside narrative JSON.
Enabled Place JSON is the sole epoch write authority for that post; save
rebuilds the existing epoch/state read projections, and the old epoch metabox
is hidden. Disabled Places retain the legacy editor and renderer.

Post `12899` (`Kino Spreehöfe`) is the enabled local pilot. Its theme-owned
renderer consumes `iss_editorial_get_read_model()` through the
`industriesalon/editorial-place` template slot. The replayable migration is
`ops/migrations/2026-07-19-kino-spreehoefe-place-editorial.php`; parity is
guarded by `wp iss-register place-editorial-check`. Curator review remains
required before wider Place migration.

Projects now have their first registry/gesture migration path. The `projekt`
format uses the existing OrderedFormat engine with the `dossier` skin and
gestures for `kapitel`, `fliesstext`, `facts`, `galerie`, `material`, and
`schluss`. The rail is a document feature rather than a content gesture and carries
only the rail heading language. When present, the theme appends the rail below
the existing project meta panel and derives links from `kapitel` and `schluss`
section anchors. When absent, no rail renders, so short project notes can remain
a compact text/gallery flow. Public project links are not authored manually in
the rail. Project Sets are the
durable intake/gallery layer: they can grow over time and remain attached to a
project as private working collections. Public project documents store only
promoted `media_refs` and `object_refs`; the theme never reads raw Set state.
Set promotion defaults projects into `galerie` so approved intake material can
become public without collapsing the preserved Set history.
Project `facts` sections own structured fact rows (`value` plus `label`)
so editors do not have to encode fact emphasis with inline HTML in body text.
The body remains available as an optional explanatory paragraph and as a legacy
fallback.
Project `kapitel`, `fliesstext`, and `schluss` body fields use a constrained
inline editor in the custom JSON UI. The stored `body` value may contain only
`p`, `br`, `strong`, `em`, `a[href]`, `ul`, `ol`, and `li`; server-side storage
sanitization enforces the same subset and strips layout/style markup.

Project public rendering is enabled per post through
`_iss_editorial_enabled_projekt`; disabled documents keep the legacy Gutenberg
`post_content` path. The current local candidate transfer artifact is
`ops/sql/2026-06-25-project-editorial-json-candidates.sql`, which imports
disabled `_iss_editorial_projekt` documents for all seven published projects,
including `projekt_rail` sections for the current visual-review candidates.
For JSON-backed project pages, related places and compact related content are
generated by the theme from the existing relations layer below the meta/rail
stack. They are not authored as project JSON gestures and are not copied from
raw Set intake state.

Führungen now use the same `iss-editorial` engine boundary for narrative
composition. `iss-content` registers the `fuehrung` format with gestures for
`bildbuehne`, `intro`, `kapitel`, `leitfrage`, `zitat`, `galerie`,
`atlas_map`, `material`, `upload_intake`, and `schluss`. Existing Führung meta remains the
owner for duration,
meeting point, target group, pricing, booking mode, inquiry details, and the
legacy hero gallery when no `bildbuehne` is active. For `bildbuehne`, the first
`media_refs` image is the stage background and later `media_refs` images are the
compact stage gallery. Existing route station data, dates, booking, facts, and
the required relation network stay outside the JSON document. Public JSON rendering is enabled
per post through `_iss_editorial_enabled_fuehrung`; disabled Führung posts keep
the legacy `post_content` description path through `iss/tour-description`.
The Führungen landing offer catalog uses the controlled `offer_catalog_groups`
post meta for `Öffentlich`, `Gruppen`, `Individuell`, and `Familien & Kinder`;
empty values fall back to the legacy booking/text heuristic only for migration
continuity. The old `fuehrung_typ` taxonomy is no longer an editor-facing
classification source for landing filters. Its landing composition is the
JSON-native `fuehrungen-offers` dynamic slot: `iss-content` supplies grouped
posts and booking state, the theme renders the cards, and the existing
`iss-relations` strip runtime supplies carousel interaction. The former Query
Loop, standalone catalog block, and private catalog JavaScript are retired.
After this cutover the file template owns only the hero shell and inert landing
slot; `ops/migrations/2026-07-11-fuehrungen-landing.php` enables the JSON-owned
body and writes page relations through their owning APIs after code deployment.
The theme consumes `iss_editorial_get_read_model()` in
`themes/industriesalon/includes/tours-render.php`, exposes `route-dossier`,
`compact`, and `standard` skins, replaces the hero description from the first
`intro` section, and renders later gesture sections in the dedicated
single-tour editorial slot.
The public Führung gesture contract is intentionally narrow:
`bildbuehne` is the optional first-viewport image stage with overlay title/text
and a compact gallery; `intro` feeds the fallback hero description only;
`kapitel` is the tour narrative or context; `leitfrage` is a framed guiding
question; `zitat` is a source or voice moment; `galerie` owns image treatments
such as sequence, wall, and viewport; `atlas_map` is the optional route map
gesture backed by existing relation station rows; `material` is for downloads
and supporting links; `upload_intake` is an optional public contribution CTA
that sends visitor uploads into the moderated Event Drop / Set workflow with a
`fuehrung__{slug}` context and never publishes raw uploads directly; `schluss`
is the closing invitation. The route station editor,
booking panel, dates, facts, and the shared `iss/related-content` relation
network are template/module surfaces, not JSON payload fields or editor-owned
body gestures. The Führung template remains the scaffold for the left alignment
grid, right booking rail, required relation placement, and fallback hero; the
theme consumes `bildbuehne` into that scaffold instead of
rendering it as a normal body section. `bildbuehne` styling should follow the shared gesture
contract: universal stage/gallery anatomy first, with Führung or
`route-dossier` differences expressed as scoped custom properties on the tour
renderer or skin wrapper. Do not promote single-tour geometry into global
tokens, and do not duplicate the gesture selector tree for each content type.
The custom Führung editor also shows a `Route / Stationen` panel, but that panel
continues to write the existing `iss_related_places` relation rows through
`iss-relations`. It is a unified authoring surface, not a second route storage
contract. The route station fields map to relation keys `place_id`, `role=stop`,
`weight`, `route_title`, `route_teaser`, `station_object_id`, and
`station_story_id`.
Static Atlas maps use the shared `atlas-map` gesture contract rather than
separate map slice/strip/place-map authoring concepts. Editor-facing JSON and
block fallback should declare only the gesture plus a registered variant such
as `tour-route`, `place-locator`, `map-only`, or `editorial-split`;
`iss-relations` expands that
variant into source, preset, fit, line, marker, panel, and ratio renderer
details. Those internal details are registry/developer-owned and should not
become free editor controls. The fallback block form is
`<!-- wp:iss/atlas-map {"variant":"tour-route"} /-->`, and it must render
through `iss_relations_render_atlas_map_variant()` and the same shared
static-map PHP renderer as JSON gestures. CSS targets the stable
`.iss-gesture-atlas-map` namespace first, with legacy `related-place-map` /
`atlas-slice` classes kept only as compatibility anatomy while old blocks are
drained. Legacy block compatibility should delegate only to a registered
variant when the old block's semantics are unambiguous, such as a
`related-place-map` with `source=route` delegating to `tour-route`.
Route variants may use the internal marker-box ratio mode: the renderer derives
the crop and stage ratio from the far-left/far-right and top/bottom marker
bounds plus registry padding, rather than exposing map-fit details to editors.
Führung JSON exposes `atlas_map` with treatment `atlas-map.tour-route`; it calls
the same `iss_relations_render_atlas_map_variant()` helper and reads route
stations from `iss-relations`. Route presentation currently flows through that
JSON gesture; the old `iss/tour-route` template block and route dossier
carousel were removed during the JSON-only Führung stabilization. A later
vanilla WordPress fallback should be rebuilt explicitly from the stabilized JSON
and relation contracts instead of reviving the deleted block surface.

Native landing pages use the same engine only behind an eligibility gate. The
`landing` format applies to native WordPress `page` posts, not a new CPT, and
is limited in V1 to the front page plus `about`, `verein`, `salon-vermietung`,
`sammlungen`, and `fuehrungen`. WordPress keeps page identity, URLs, menus, hierarchy, and the
static front-page setting. The front page keeps `front-page.html` as its wrapper.
Landing JSON is stored in `_iss_editorial_landing`, enabled through
`_iss_editorial_enabled_landing`, and assigned a page posture through
`_iss_editorial_landing_skin`. The registry owns allowed landing gestures,
skins, default skin, and allowed gesture treatments; page meta stores the chosen
skin and ordered section content. Disabled, invalid, missing, or empty landing
JSON leaves the existing template and `post_content` output untouched. Static
page templates host the theme-owned `industriesalon/editorial-landing` dynamic
slot; it emits nothing until an eligible page has enabled, renderable landing
JSON.
The current front-page landing document uses the dedicated `frontpage` skin for
parity with the hardcoded Gutenberg homepage body. That skin is scoped to the
homepage migration; reusable visual choices still belong in per-gesture
treatments.
Landing JSON exposes the first JSON-native `atlas_map` gesture. Its treatment
choices are recipe names that map to registered `atlas-map` variants:
`atlas-map.place-locator`, `atlas-map.map-only`, and
`atlas-map.editorial-split`. The renderer calls
`iss_relations_render_atlas_map_variant()` and does not store map source details
in the section payload. General landing maps resolve current-page relations;
the editorial split treatment selects a taller registered viewport ratio for its
text/map composition and adds its marker presentation. Its markers remain
ordinary page relations, with no treatment-specific selection or result cap.
Single Führung route maps use the same gesture
contract but keep their source and order in relation station rows.

`/about/` is a completed landing cutover. Its file template owns only the
masthead and `industriesalon/editorial-landing` slot; the paired API migration
owns the nine-section body document. The landing `text_bild_reihe` gesture
represents repeated, non-navigational image/title/text items and stays distinct
from linked `gateway` destinations. Its `visual` and `compact` treatments share
one markup contract and let item count resolve in CSS.

The About dossier keeps three reusable composition treatments instead of
normalizing every narrative into the default landing stack:
`feature.origin-story` separates heading/lead from the image, quotation,
narrative, and fact card; `text.story-split` places narrative left and heading
right; `text.story-split-flip` reverses that direction. `feature` exposes a
separate `lead` field so this structure remains editable without parsing body
HTML or storing page-specific markup classes.

The About Team section is the `team-directory` dynamic slot. `team_member`
posts remain the roster source of truth: publish status controls visibility,
the existing role-label and profile fields control card content, and staff
manage `menu_order` through the shared drag-and-drop list control. The slot
queries every published profile in that order and stores no person IDs or
person-specific presentation rules in landing JSON or PHP.

`/schoneweide/` is a territorial landing. Its file template retains the hero;
seven JSON sections own the body. `map-img.editorial-atlas` restores the
map/panorama orientation composition and its four non-linked place cards;
`text-bild-reihe.chronology` presents the epoch sequence,
`gateway.atlas-plates` presents linked place dossiers, and
`slot.schoneweide-atlas` delegates to the existing interactive Atlas renderer.
The map panel inside `map_img` resolves the page's ordinary place relations
without a treatment-specific cap. Its registered crop and all Atlas payloads
remain outside landing JSON.

The reusable `gateway.pathways` treatment presents a sequence of image-led
destinations as a compact horizontal editorial strip. It uses the normal
gateway item contract and belongs to landing composition CSS; legacy pathway
block classes and page-specific CSS are not part of the treatment.

Native landing pages also expose the canonical `galerie` gesture with the
shared `gallery_layout` option. The Führungen landing uses `sequence`: the
theme renders a light, image-led carousel through the site-wide strip runtime,
while JSON stores only ordered media references and stable captions. New
landing galleries must not revive `iss/dense-image-wall`, per-cell spans,
manual row heights, or overlay copy.

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
archive-object grid treatments. `galerie` owns full-viewport and image-wall
treatments through `gallery_layout=viewport` and `gallery_layout=wall`: editors
select and reorder images, while the skin decides whether it renders as a
cover-cropped viewport image, sequence, grid, or dense wall. Theme partials are reserved
for true structural exceptions, not for normal gesture rendering.

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

`wp iss-editorial projekt-dry-run` reports read-only migration candidates for
all published projects by default. Use `--posts=<slug-or-id,slug-or-id>` to
target a subset. `wp iss-editorial projekt-import-candidate --post=all` writes
disabled `_iss_editorial_projekt` candidate documents and leaves frontend
rendering on legacy `post_content`; use `--post=<slug-or-id>` for a single
project and `--force` only when replacing an existing candidate intentionally.

`wp iss-editorial fuehrung-dry-run` reports read-only migration candidates for
all published Führungen by default. Use `--posts=<slug-or-id,slug-or-id>` to
target a subset. `wp iss-editorial fuehrung-import-candidate --post=all
--enable` writes `_iss_editorial_fuehrung` documents and switches the narrative
layer to the JSON renderer while route stations, facts, booking, dates, and
related blocks stay on their existing contracts. Use `--force` only when
replacing an existing candidate intentionally.

For Veranstaltungen, Phase 1 now lives in `iss-content` plus the theme-owned
single template. `plugins/iss-content/includes/veranstaltungen-registry.php`
defines the structural entity/shape/default-skin contract for `_iss_entity_key`.
The public editor now separates structure from semantics: `_iss_entity_key`
stores `Veranstaltung`, `Programm / Fest`, or `Serientermin`, while the hidden
`veranstaltung_art` taxonomy stores semantic labels such as Vortrag, Gespräch,
Lesung, Präsentation, Workshop, Konzert, Film, and Repair Cafe for search and
filter use.

All current Veranstaltungen have curated `_iss_entity_key` and reviewed
`_iss_content_json`. The active editor surface is the shared section canvas;
event structure remains in its existing facts panel. The public single template renders valid JSON through
the theme helper and falls back to `post_content` only when no valid structured
document exists.

The theme exposes registry-derived structural body classes on singular Veranstaltung pages:
`iss-event-entity-*`, `iss-event-shape-*`, `iss-event-surface-*`, and
`iss-event-skin-*`. The template uses shared dynamic blocks only as
infrastructure: `iss/content-meta` for the facts panel and shared relation rails
for related content. Veranstaltung-specific color/layout switches are not part
of the template contract. Public skins are structural registry defaults:
`typografisch` for normal and series events and `buehne` for `Programm / Fest`.
Semantic labels do not create separate skins.

Current operational commands are:

- `wp iss-content veranstaltungen-dry-run`
- `wp iss-content veranstaltungen-set-entity --post=<id-or-slug> --entity=<key>`
- `wp iss-content veranstaltungen-registry-check`
- `wp iss-content veranstaltungen-repository-check`
- `wp iss-content veranstaltungen-query-audit`
- `wp iss-content veranstaltungen-content-audit`

The migration-only content importer has been removed after the approved JSON
migration. Future media/gallery work should extend the JSON renderer contract
directly instead of reviving a Gutenberg-body importer.

The fifth JSON editor slice adds media and archive-object references to the same
Veranstaltung section schema. `intro` and `kapitel` can carry media refs for
section images. `galerie` is the explicit image-gallery gesture for pictures
from the media library or future upload intake and renders as a framed carousel
strip on the public Veranstaltung page. `material` is reserved for
downloads, links, archive objects, and dynamic references; it no longer carries
rendered image refs. `galerie` means an approved presentation section, not an
editor dump; raw media dumps belong in the future editorial media bucket
workflow before promotion.
The admin editor reuses WordPress media selection plus the existing archive
object picker when loaded. Legacy import preserves WordPress image/media blocks
as `media_refs` by attachment ID and avoids persisting local dev-host thumbnail
URLs. Local post `13349` now has a one-section structured Veranstaltung
candidate with one media ref; transfer it with
`ops/sql/2026-06-24-veranstaltung-13349-content-json.sql` if that local review
state is needed elsewhere. Archive-object refs in the Veranstaltung path stay
lightweight: selected objects save ID, compact label, thumbnail, and bucket
provenance only, not long Archivset member captions. The `Struktur` tray and
preview render those refs as image cards so editors can verify the selected
object visually without inflating `_iss_content_json`.

The sixth JSON editor slice preserves centralized Steuerung facts as references
instead of copied text. Sections may carry `dynamic_refs` with
`kind=control_field`, `source=industriesalon-steuerung`, and the Steuerung field
`key` plus original block display attributes. The admin preview resolves current
values through `Industriesalon_Steuerung::get_field_value()` for review, but the
saved `_iss_content_json` stores only the reference metadata. Local post `25808`
now has a five-section `event.festival` candidate whose `Ort` section references
`address.full`; transfer it with
`ops/sql/2026-06-24-veranstaltung-25808-content-json.sql` if that local review
state is needed elsewhere.

`wp iss-editorial ausstellung-import-candidate --post=<slug-or-id>` writes the
same conservative candidate into `_iss_editorial_ausstellung`, clears the
autosave meta, and forces `_iss_editorial_enabled_ausstellung` to `0`. It
refuses to replace an existing JSON document unless `--force` is passed.
Legacy image/media blocks are imported as `bildstrecke` sections with
`media_refs` where attachment IDs are available.

Ausstellung archive-object references may carry fuller bucket provenance
(`set_id`, `set_title`, `member_id`, `member_caption`) when selected through an
Archivset. Rendering can still resolve by object ID, but the provenance stays
available for future captions and source context. Veranstaltung references use
the leaner event path above.

Editors save reviewed structure changes through WordPress Update / Publish,
along with WordPress-owned fields. Automatic draft saving and section-modal
`Fertig` do not publish changes.

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
