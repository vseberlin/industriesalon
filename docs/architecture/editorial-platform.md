# Editorial Platform

This is the implementation checkpoint for the SOW in
`/home/vladimir/Downloads/editor-sow.md`.

## Ownership

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
  workspace with outline, theme preview and inspector. All registered formats
  use that workspace, including retained v1 documents. Media selection uses the
  WordPress media library; there is no second section editor. Archive
  object selection uses the archive picker modal: attached/context buckets first,
  object thumbnails after bucket choice, and faceted object search as the
  secondary fallback.
- Media/object intake Sets are implemented in `iss-content` and documented in
  `docs/architecture/editorial-media-buckets.md`. Sets are private review
  state; public renderers consume only saved `media_refs` / `object_refs`.

## Portable composition

The vocabulary defines `statement`, `gateway`, `text_bild_reihe`, `feature`,
`galerie`, `fliesstext`, `zitat`, `schluss`, `kapitel`, `facts`, `material` and
`objektfokus` once, including supported fields and treatments. Landing, article, event, exhibition, project,
report, tour and publication formats use those contracts. The theme registers
their section renderer and delegates to the existing landing composition
functions; native domain sections retain their renderers. Preview and public
output use the same document and renderer. Event context rules permit these
portable gestures while retaining restrictions on domain-specific sections.

Galleries share grid, sequence, wall and viewport layouts. Galleries, prose and
quotes consume the same resolved media/archive references, retaining captions
and archive source links. Archive visibility stays with the archive owner;
unpublished objects do not render. Prose retains links and all three text
treatments. Quote source treatments retain image orientation, context and
attribution. Split-text geometry belongs to the renderer stylesheet in every
skin; skin palettes and typography remain separate. Existing gallery/text/quote
sections now use this shared presentation, without rewriting their documents.
The saved publication identifier `longread_quote` delegates to the quote
contract and remains editable; it is hidden from new insertion to avoid a
duplicate chooser item.

Questions use `statement.leitfrage` under Überschrift & Intro in all eight
formats. Saved event/exhibition/tour `leitfrage` identifiers remain registered
and editable, hidden from insertion, with the same statement fields and editor
label. Their default treatment is the question; selected statement treatments
are retained. The exhibition starter creates `statement.leitfrage`. Statements
support anchors, preserving tour navigation. The theme routes retained
identifiers through their declared family only in the render copy, leaving
stored JSON and preview section indices intact. The obsolete tour/exhibition/
event question styling is removed; the existing shared treatment and skin own
presentation. Native introductions and hero bindings keep their owners.

Conclusions share rich text, anchors and links, including links-only endings.
They reuse the text renderer and selected skin. Project contact navigation and
tour anchors keep their existing owners. Chapters share links, resolved media
with captions, inline/right-side placement and standard/aside/disclosure presentation. The
stored `section_treatment=aside` is labelled Hintergrund / Exkurs in the editor.
Event chapters retain `dynamic_refs` as an event-only extension, sanitized and
resolved by their existing owners. Project dossier pairing reuses the shared
chapter body while keeping its facts column, anchors and separate preview
addresses. Publication `longread_chapter` remains a distinct Lesekapitel with its
native numbering/navigation; no stored identifiers are rewritten.

Projects also offer the theme-owned `buehne` skin. The native project stage
keeps the ISS header/footer and the shared section renderer, with an expressive
opening and a navigation entry for each titled section, excluding the opening
and expandable details. Its template is selected
from the effective saved/preview skin; the other project templates and their
DB overrides are unchanged. Side, top and bottom reading navigation retain the
existing document feature contract.

Project identity lives in `features.identity`, with a registered `palette` and
at most one standard WordPress image reference in `media_refs`. The project owner
validates this feature through the shared document-feature filter; the theme
registers the available palette choices. **Seite → Projektidentität** reuses the
existing media picker. Logo and colours currently affect Bühne; they survive
preview, recovery and native revisions. The palette supplies readable text and
colour surfaces through skin tokens, without arbitrary CSS or per-project files.

A first **Bild & Text → Seitenauftakt** (`feature.opening`, v3) replaces the
Bühne template introduction when it has a title and an image. The theme renders
one H1, preserves the exact canvas section address and omits that section from
the body/navigation. Other project skins reject this treatment; the landing
opening retains its existing Startseite capability. Missing/invalid openings
fall back to the template introduction.

Portable sections expose **Darstellung → Fläche** with paper, soft, dark and
accent surfaces (`surface=default|soft|dark|accent`); these are skin tokens,
not arbitrary colours. The composed opening owns its fixed surface. Old documents
do not acquire new surface or item fields. **Text-Bild-Reihe** additionally offers
**Themenspalten** and **Programm / Chronik**. Both use the existing item editor;
optional `eyebrow`, `group` and `detail` fields are plain text. Agenda groups enable
scoped filter buttons with a live count. Every row is server-rendered, filters
appear only after enhancement, and native details work without JavaScript.
These are editorial programme descriptions; event dates, bookings and confirmed
relations remain with their existing domain owners. Quiet horizontal project
navigation reuses the rail renderer and presentation tokens.

**Bild & Text** and **Linkkarten** share the optional `image_fit=cover|contain`
choice (**Darstellung → Bilddarstellung**). Contain keeps supplied cards, logos
and posters complete, including the project opening. Existing documents retain
their cropped-photo default without acquiring a new stored field. The renderer
reuses the existing contain media primitive and an inherited image-fit token.

Chapters additionally support `section_treatment=disclosure` (**Aufklappbar**).
The portable renderer uses native `details`/`summary`, closed for visitors and
open in the editing canvas. Dossier chapter/facts pairing excludes disclosures
so the title, body and following facts keep independent editor addresses.

Merkpunkte (`facts`) shares optional rich text, title/kicker, anchors and plain-text
`value`/`label` rows across the same eight formats. Standalone sections reuse the
existing copy and fact-list components, with responsive cards and the selected
skin. Zero values and rows containing only a value or description remain visible;
empty sections emit no public wrapper. Existing `massstab` normalization is
unchanged. Legacy numeric prose remains prose, without parsing or rewriting it.
Project Dossier still pairs a chapter followed by facts in its existing column;
that column retains its own anchor, versioned prose and preview editing address.
Place facts, event structure and other authoritative metadata keep their owners.

Material (`material`, Begleitende Dateien) shares optional rich text, anchors,
attachment downloads and links across all eight formats. The existing
`iss-file-insert` primitive now consumes surface colours for light/dark skins.
Saved image attachments remain downloadable files; gallery presentation stays
with `galerie`. Both WordPress and Set media pickers retain every selected file
type, with Set approval/visibility checks unchanged. Labels and attachment IDs
remain in the existing document; files are neither copied nor re-uploaded.

Events retain plain-text `items`, owner-resolved `dynamic_refs` and archive
`object_refs` as Material-only extensions. Published archive sources use the
shared visibility/caption renderer. Closed events still suppress ticket links
while preserving accompanying notes. Other formats reject those event-only
fields. Place Material retains its separate renderer and media-picker rules.
Obsolete project/tour Material branches and layout CSS are removed. Native
event downloads remain for the engine-disabled fallback; Ausstellung supplements
retain Place Material rendering. Generic domain media helpers keep their callers.

Archivobjekte (`objektfokus`) shares anchors, optional rich text and archive
references across the same eight formats. The existing archive owner resolves
public availability and supplies source links, thumbnails and live metadata.
Saved labels override card titles; nonempty `member_caption` overrides the
excerpt. Empty overrides use the archive title/excerpt. Set/member IDs remain in
the document and never trigger Set or archive mutations. The existing picker
preserves those fields on reselection; the shared inspector edits labels and
captions with the existing media controls. Missing, unpublished and non-archive
references emit no card; a wholly empty section emits no wrapper.

The archive featured-card styles live in `cards.css`, with component variables
for archive landing, native exhibition blocks and portable composition. Shared
objects use bounded card widths, uncropped images and complete captions; skin palettes stay in
`editorial-skins.css`. Superseded exhibition object-grid rules are removed.
Places retain their existing supplementary archive path.

Publication payload adapters retain shared and publication-native gestures in
the reading flow of all four layouts. Chapter, timeline-moment and album markup
is reused by each layout; changing layout does not convert stored section types.
Standard/longread navigation and counts include chapters only. Composed timelines
keep consecutive epoch groups in document order, with unique anchors and one
navigation link per epoch; their counts include dated moments only. Every album
remains an owner-managed sheet sequence at its document position. Album navigation
collects visible sheets from all albums; the first album retains existing sheet
anchors and later albums receive distinct prefixes. Sheet sources, captions,
visibility and numbering remain album-owned.

Intro/source placement retains the existing layout contract. In album layouts
with no album section, their text precedes the other authored sections. Native
chapter title and timeline year requirements are unchanged. Publication payload
caches distinguish saved content, disabled JSON and authenticated preview
snapshots. Native layout changes take effect on WordPress Update/Publish; the
editor explains that save boundary. No stored document is rewritten.

Places remain separate, including their existing gallery fields and renderer.
The complete inventory of 28 registered identifiers (including the retained
publication quote identifier) and
remaining contract differences are in the
[consolidation audit](../project/editorial-consolidation-audit.md#gesture-portability-checkpoint--13-october-2026).

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
link directly. Existing event/tour/project/exhibition/report upload sections
retain their open default until explicitly closed; new content defaults closed.
Places always require explicit opening, including existing stored invitations.
Intake eligibility is separate from report-source eligibility: Places can accept
files but cannot create or supply Rückblicke. The existing Event Drop receiver loads WordPress to
verify the post and link on every GET/POST. Legacy code URLs may upload only into
an open, resolvable context. Files remain in intake for rights review. Supported
extensions: JPG/JPEG, PNG, GIF, WebP; MP4/MOV/M4V/MKV/WebM/AVI; ZIP;
PDF, DOCX, PPTX, XLSX, ODT. The server verifies extension/MIME compatibility.

### Event presentation and written feedback

Veranstaltungen use one theme-owned opening layout: title, excerpt and current
occurrence facts alongside the featured image. The native `single-veranstaltung`
template remains file-backed. Both skins share the layout; their colour and
content treatments remain distinct. Recurring detail dates read the same
`iss-occurrences` query as the programme, including running appointments. If no
current occurrence remains, the latest historical one is labelled accordingly;
posts without projection retain their editorial dates and expire at their stated
end (or end of the starting day). Detail pages are uncached.

The opening has an optional `hero_kicker`, `hero_title`, `hero_subtitle` and
`hero_image_fit` (`contain` or `cover`) in the same event JSON document. These
use the shared editor's document bindings, draft recovery and revision storage;
clearing a bound field is a saved change. In the editor choose **Angaben &
Beziehungen → Seitenauftakt gestalten**. The post title remains the search/list
identity; an empty opening title falls back to it. No automatic title splitting.
Native featured-image sizing preserves posters; the optional photo treatment
uses a landscape crop. Captions come from the selected attachment.

The existing `iss/content-meta` block has theme-owned `event-summary` and
`event-visit` presentations, editable in its inspector. The visit card reads the
selected Atlas place or an explicit venue relation, never a merely related place.
External ticket links use the same material-link convention as programme cards.
Past/cancelled/sold-out events suppress those ticket actions; past events move
published reports and approved voices ahead of the original announcement and
invite contributions only when intake is open. The existing programme-cards
block uses `event-next` to show up to three distinct upcoming events, excluding
the current event and cancelled entries. Fewer eligible events produce fewer
cards. There is no new calendar, content store or custom block.

Published Rückblicke use the shared card renderer's `stack` layout: image and
copy side by side on desktop, stacked on phones. Reports without a featured image
omit the media placeholder. Existing legacy aligned-body-image selectors remain
only for stored WordPress content; obsolete event sidebar/hero selector trees
were removed. Shared card geometry belongs in `patterns.css`.

For events, **Feedback und Upload geöffnet** uses the existing upload-open state
and signed link. Opening it also exposes **Erinnerung oder Fotos beitragen** on
the event page. Existing explicit closed choices stay closed. The receiver has
an independent text form, so a file is optional. Photos and documents still go
to private Sets; text goes to native WordPress comments with type `iss_feedback`.
This uses the existing moderation interface instead of introducing another review
store or turning private media Sets into public content.

**Feedback prüfen** opens the event's filtered comment list. Every submission
starts pending, including submissions from logged-in staff. **Approve / Genehmigen**
publishes it under **Stimmen zur Veranstaltung**; unapproving, spam or trash removes
it. Closing intake retains already approved voices. Public output requires consent,
escapes text, omits email/IP, and paginates twelve voices at a time. Text is limited
to 4,000 characters, with signed-context and nonce checks, explicit consent, a
honeypot, and native duplicate/flood checks. The native moderation notification
settings continue to apply. Feedback requires a published, accessible event.

Deploy receiver, `iss-content`, `iss-editorial`, `iss-frontend` and theme together.
No schema, uploads or DB-template migration is required. The explicit Anne Rabe
opening copy is transferred with `ops/migrations/2026-09-27-event-opening.php`
after code deployment (`check`, `apply` with a new private receipt path, `verify`).
Its targeted before-image and header describe rollback through the editor API;
it preserves post title, body, media and all other document fields. Existing
media are reused. Native comments/commentmeta and per-post controls own feedback.
Local regression: `wp eval-file tests/e2e/bin/event-detail-feedback.php` (through
the documented local wrapper/mounted `/tmp/iss-tests/` path); creates and removes
its own fixtures and suppresses fixture notifications.

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

### Unified format contract

`iss_editorial_get_registered_formats()` is the effective contract. Data owners
contribute sections, supports, rich-text profiles, family/icon/tone/group,
treatments/defaults/aliases, and explicit starter compositions. The theme
contributes supported skins and preview capabilities in `includes/editorial-render.php`;
`iss-content` contributes skin feature defaults to those same choices. Renderers
read the normalized choices instead of maintaining separate allowed lists.
`wp iss-editorial registry-check [--format=json]` diagnoses missing metadata,
invalid defaults, alias collisions and unknown starter types without writes.

Formats are `landing`, `article`, `projekt`, `ausstellung`, `rueckblick`,
`veranstaltung`, `place`, `fuehrung`, and `publication`. `article` covers ordinary
pages, posts, videos, Team, profiles, archive articles, collections and objects,
sharing a subset of landing sections and the same theme renderer. All current
public content CPTs have a format. Places retain the specialized `place` contract;
their epochs, facts and projections are not generalized into article sections.
Existing landing eligibility wins. Operational records such as notices, and
WordPress internals such as attachments and navigation, are not narrative CPTs.
Structured archive and profile data retain their domain ownership. Existing block content is never parsed
or replaced automatically: an existing post switches only when a reviewed JSON
document is explicitly enabled through the existing storage API/migration path.
New eligible auto-drafts use the shared workspace immediately.

The shared vocabulary owns concise gesture labels; formats retain their field
capabilities and contextual descriptions. Cross-format gesture expansion requires
renderer and provider coverage before exposing additional choices. Archive
collections use the existing editorial block before their archive album; other
new article integrations use their native post-content slot. These effective
templates are file-backed locally; deploys must check target template authority.

Preview-only source indices are attached to the hydrated snapshot, carried through
publication payloads and combined project sections, and never stored. Native
owner panels, route station controls, project rails, Set permissions, bookings
and publication metadata remain attached to their original owners.

### Landing rich text and live preview

All nine editorial formats support versions 1, 2 and 3. New documents start at
version 3; saved v1 documents retain their version until explicitly upgraded. The explicit **Textfarben aktivieren** action converts legacy plain item
descriptions to escaped rich text in the author's draft, including deleted
sections. Version-2 documents are prepared as version 3 in the editor copy,
including recovered drafts; this does not write canonical content. The format registry's
`rich_text` profiles declare block prose, inline descriptions and descriptions
inside a linked card. The last profile excludes hyperlinks to prevent nested
anchors; headings, labels and factual fields remain plain text.

Registered rich-text fields use the installed WordPress TinyMCE with a compact toolbar. **Link**
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

All enabled editorial documents use one persistent two-column workspace: the
actual WordPress page preview and one right panel. **Abschnitte**, **Hinzufügen**
and **Seite** switch between the outline, compact searchable two-column chooser
and document controls. Selecting a section opens its inspector in the same panel.
**Fertig** secures the draft through the existing save queue and returns to the
outline/preview; invalid or failed saves retain the inspector and recoverable
input. It never publishes. Skins, project rails and tour route controls live under
**Seite**; a selected route provides a direct entry. Route rows respond to panel
width, including on wide screens. The preview has 1280/768/390px viewports.
Narrow workspaces switch between preview and controls. The workspace starts
expanded, with one compact top bar and separate
acknowledgements for draft saving and preview freshness. Covered native controls
are inert only while expanded; leaving restores their prior state. Publishing
navigation focuses the native control without submitting. Searchable grouped
insertion, gaps, keyboard reordering and recoverable trash/undo use the existing
document state. Native save,
publication and revision history remain authoritative. **Weitere Werkzeuge →
Angaben & Beziehungen** reveals native owner controls without replacing the
workspace. The old section modal and its alternate preview layout are removed.

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
`workspace.js` owns the pane layout, `admin.js` retains document state and domain
field controls, `ui.js` owns shared inputs, schematics and picker focus, `rich-text.js` owns text controls, `live-preview.js` owns frame lifecycle, and `preview-frame.js` owns the authenticated frame interaction.
The preview selection stylesheet also loads only in the authenticated embedded
page. `includes/rich-text.php` and `includes/preview.php` implement their server
contracts; public rendering remains in the theme. Each embedded request validates
one snapshot and reuses its document, enabled state and resolved references.
A concurrent autosave cannot replace that snapshot during rendering. The cache
is request-local and keyed by user, post, format and token; ordinary API reads
remain fresh after writes. The iframe sandbox restricts interactions such as
forms/popups; it is not the authentication boundary.

Hero content belongs to JSON; appearance belongs to the existing theme hero
pattern. The homepage opening renders native Cover/Buttons with `iss-front-hero`
and its homepage modifier, shared with the template fallback. Optional lead/body
appear beneath the image. There is no separate opening typography/layout family.
Other landing templates retain their current hero content until explicitly
migrated; migrations must preserve their design and leave only one active owner.

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

The native template gives the dossier a fixed public sequence: introduction and
epochs, public people relations, gallery/material and linked collection objects,
present-day narrative and structured facts, then one questions/contribution band.
The existing `industriesalon/editorial-place` block exposes story, supplements,
present, contribution-copy and approved-feedback slots; it remains the only
narrative renderer. Source section indexes survive grouping for editor previews.
Accepted dated milestones become compact source-bearing updates in their matching
epoch. Undated or unmatched updates remain visible as compact independent rows;
no event date or historical association is inferred.
Introduction and present-day sections expose `max_instances: 1` in the registry;
the shared chooser and insertion path apply that limit to active sections only.
This is an insertion constraint, not a save validator: retained duplicates and
trash recovery remain editable, saveable and visible within their dossier group.
Both overview slots render their title, kicker, body and all usable image
references through existing Place components; present-day links reuse the shared
link renderer. Empty slots remain absent, and body/image/link-only slots retain
default headings. Preview fields keep their source JSON indexes across grouping.

Place public sections are optional by resolved content. A title or kicker alone,
empty rich-text markup, and unresolved media/object references do not create a
section. Narrative/media/link-only sections retain their default headings;
epochs need a title plus narrative or usable imagery. An enabled empty document
does not revive legacy text. Legacy and JSON dossiers both show available public
archive/current images: two distinct usable attachments form a comparison, one
forms a single view, and none leave no heading or spacing.

The theme omits empty native template groups after their children render, without
rerunning queries. Facts show populated fields; the Atlas slot requires a rendered
Place marker. The existing related-content owner retains relation and editorial
selection, while an empty rail disappears and a lone dossier column fills the
grid. Authored `legacy_questions` already exposed by the public Place contract
appear in the optional closing questions/contribution section. It survives when
there are authored questions or a working signed action, and otherwise disappears. Native template groups and all stored sections stay
available to editors; there is no additional visibility toggle, storage, or page
template. `tests/e2e/bin/place-optional-sections.php` verifies sparse/populated
rendering with process-local fixtures and a database write guard. The optional
section rules alone need no database migration or uploads artifact.

The shared Place template now follows the supplied dossier mockup: text opening
and manual excerpt, full-width public image comparison, compact fact band,
chronology, optional people/objects and visible current facts in the reading
column, related links followed by a compact Atlas excerpt in the rail, then one
optional questions/intake band. The excerpt retains the current Place marker and
address/district caption. The shared band layout uses one column; reserving the
copy column for a minimal strip previously squeezed its desktop map to zero width.
The legacy chronology reads authored epoch rows for narrative/source/question
fields; Atlas-only historical states remain readable where no authored phases
exist. Enabled present-day JSON stays the narrative authority; its old legacy
current-use note is suppressed. Legacy Places now display their existing published narrative (or existing long
history) as the introduction; imported current-use reporting stays with current
facts. Source text and authored manual excerpts are not rewritten. Slash-separated post titles
display their existing descriptor below the main heading; saved titles do not
change. The legacy profile omits history repeated verbatim by the manual excerpt.
The theme's existing Plex font presets, native blocks, renderer classes and skin
palette remain the owners. Legacy Places also load the editorial skin stylesheet;
the retired cover, alternating epoch layout and dark data/disclosure styles are
removed. The rail precedes the chronology below 900px. Images keep their complete
attachment proportions and recorded dates, without implying that an older
photograph shows today's condition.

KAOS 93's original public TIFF has no native browser rendering. The paired
`2026-10-14-place-image-preview.php` migration and
`ops/uploads/2026-10-14-place-dossier-preview.tar.gz` add its reviewed JPEG as the
existing attachment's native `large` size. The migration matches the exact
uploads-relative source, checks source/preview hashes, backs up metadata in
`iss_backup_20261014_place_image_preview`, and writes through WordPress's media
API. The original TIFF, attachment ID, Place content, image group and attribution
remain unchanged. Deploy the metadata migration with its media artifact; use
`dry-run`, `apply`, and `verify`. The backup option contains the metadata required
for API-owned rollback.

The Place gesture and template contribution links use the same signed intake
owner URL. **Angaben & Beziehungen → Material & Beiträge → Feedback und Upload geöffnet**
controls both; closing hides both and reopening invalidates prior links. The
register helper no longer embeds a shared environment upload code or emits an
unresolved `ort` receiver target. The receiver derives `register_place__<slug>`
from the signed post context, and the existing Set bridge resolves that context.
Files remain private until review. Places and events share native moderated
`iss_feedback` comments; text requires no file. Approved Place memories appear
after its narrative, while pending or absent memories leave no heading. The
theme's shared feedback primitive retains event labels in the event composition.
No existing Place is opened or migrated by deploying this code.

Epoch invitations are added on demand using the existing **Automatische Inhalte**
gesture with the Place-owned source **Wissen zur Epoche beitragen**
(`dynamic_slot`, `slot_key: place-epoch-contribution`). Insert it after the intended
epoch; it uses the nearest preceding `epoche` in the authored document order.
The inspector names that epoch. Native chooser add/remove, generated preview and
saved/reloaded gesture controls are verified alongside the atomic Place suites.
Reordering the gesture changes its context; an
orphan before every epoch cannot publish and remains a recoverable draft.
The theme derives the question from the epoch's dates, including single-year,
ongoing/open-ended, end-only and undated phases. Leave the gesture's title blank
for automatic wording or enter a custom question there. Generated text is never
stored in JSON or phase rows. There is no implicit invitation on other epochs.
A chosen gesture can expose an otherwise narrative-free named phase as a research
gap while intake is open; removing it or closing intake hides that invitation.
Its preview retains its own JSON source index inside the epoch row.

All gesture links use the same signed Place context and single
`register_place__<slug>` Set. The descriptive subject includes phase title, years
and question. Place intake still requires explicit opening. Existing open
questions retain their numbered closing list and one general action. There is
no new block, Set, table, visibility flag or media artifact; Place admits only
this existing dynamic gesture/source, without generic portable sections.

The optional `subject` URL hint is escaped, bounded to 240 characters, and carried
into `_iss_feedback_subject` or the file receipt's `contribution_subject`; it never
changes signed post authorization. Editors see the text topic in native comment
editing and the file topic in the private Set item. The manifest writer extends
older CSV headers atomically under a shared writer lock, retains a
`.before-subject` before-image and appends empty topic values to older rows. The
Set bridge retains the topic in item provenance.

The older `contribution_question` field remains a compatible typed value in
existing Place JSON/phase rows. The legacy epoch editor retains its explicit
question control; the canonical editor uses the gesture title instead. Epoch schema `2026-10-14-v4` adds only
`contribution_question` with an empty default through the existing owner installer.
Enabled JSON remains canonical and projects that field atomically; disabled
Places store it in their existing phase rows. The chosen gesture derives its automatic invitation only from phase dates,
never from a missing source or photograph. Stored legacy questions do not
implicitly activate a gesture. The before-image
`var/place-dossier-20261014/epochs-before-question.json` verified that all 193
existing rows stayed identical apart from the new empty field. The isolated
Place atomic suite verifies canonical/projection readback and rollback behavior.

Both epoch editors use existing WordPress media selection: JSON `media_refs` and
legacy `media_ids`. The older editor now has a photo chooser and previews, replacing
manual ID entry. All usable selected photos appear in a single epoch media column;
legacy Place image visibility remains authoritative. The rail's existing static
map and selected marker receive a short caption from address/district data.
There are no new map assets, storage tables, or upload forms.

`ops/migrations/2026-10-14-place-contributions-preview.php` explicitly opens only
KAOS 93 for the local preview through the contribution API, with a targeted
before-image and rollback mode. It is separate from deployment and requires no
uploads artifact. The earlier image-preview migration and paired JPEG artifact
remain required for the KAOS archive TIFF preview. The separate
`2026-10-14-place-research-question-preview.php` owner-API migration authors one
KAOS 93 DDR-phase question, retaining a targeted before-image and supporting
rollback. It has no media dependency and is not automatically applied elsewhere. Runtime checks are
`tests/e2e/bin/place-contributions.php` (temporary comment tables and receipt files,
no mail) and `place-optional-sections.php` (read-only cache fixtures).
The later `2026-10-14-place-epoch-gesture.php` migration adds one chosen dynamic
gesture after KAOS's DDR phase in its enabled JSON. It runs after the Place JSON
migration, checks the reviewed document hash, retains a private before-image,
and verifies unchanged phase facts, imported milestones, core/meta and intake.
It provides `dry-run`, `apply`, `verify` and guarded `rollback`; no other Place
receives a gesture. Once later editorial changes are made, older baseline-only
migration verification intentionally refuses those changed documents.


The Place CPT now uses the existing JSON section canvas for its narrative. The
local reviewed migration covers all 84 existing Places (83 manifest entries plus
the previously enabled Kino pilot). `ops/migrations/2026-10-14-place-json.php`
and its adjacent readable `2026-10-14-place-documents.json` preflight legacy
sources/recovery documents, save a private non-autoload before-image per Place,
and activate through the atomic owner API. Modes are `dry-run`, `apply`, `verify`,
and guarded `rollback`; an optional second argument selects one manifest slug.
Existing enabled documents are verified, never replaced. Core titles, excerpts,
old content/meta, permissions, relations, imported milestones and intake choices
remain intact. No additional media transfer is needed for JSON activation.

All sections, including epochs, are optional. Places without historical phases
can save an introduction/current note without inventing a date or phase; clearing
epochs removes their derived rows while independent milestones remain. New Places
already use the same canvas. The facts panel retains address/map coordinates,
visibility, status, factual data, shared sources, public image groups, open
questions and private research/contacts. `legacy_icon` and `legacy_color` controls
are retired; their registered/imported values remain compatible with older DTOs
and the Atlas fallback color reader. Enabled Places hide `history_short`,
`history_long`, `current_use` and the separate epoch editor. Missing/hidden form
fields are never cleared; checkbox inputs explicitly submit their off value.
Atlas narrative DTOs, generated teaser candidates and Place search text now read
the enabled canonical document, including deliberately empty narrative. The old
core/history text remains a before-image rather than stale public/search copy.
The candidate builder retains the published full introduction, falls back to
long history, keeps the manual teaser separate, and carries the current report's
source link into Gegenwart. Native public image groups remain the header/media
permission owner; individual epoch images are edited inside JSON sections. Local
verification includes all 83 manifest readbacks, the preserved Kino pilot,
KAOS's native editor Update/reload with unchanged content/meta, desktop/390px
map-marker and overflow checks, and the isolated Place/storage regressions.
Private before-images are also exported to
`var/place-dossier-20261014/place-json-before-images.json` (0600); migration and
verification receipts live alongside it. These local receipts are not deployment
artifacts. The before-image export stays private because it includes staff-only
metadata.


Place document persistence uses the register epoch service's transaction boundary.
`iss_editorial_save_document()` accepts an optional fifth argument for activation;
Place callers must include activation in that call rather than setting the flag
after saving. The `iss_editorial_document_writer` filter wraps the engine's native
metadata writer with the register projection. `iss_editorial_document_saved` is
a success notification after this boundary, not the projection write hook.
Native revision restoration runs WordPress's metadata copy through the same
boundary and verifies the copied values before rebuilding projections. Failure
keeps the prior section document and recovery draft; native WordPress post fields
such as title and excerpt are outside this transaction.

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
The shared `facts` contract supplies project fact rows (`value` plus `label`)
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
The Führungen landing offer catalog uses explicit `offer_catalog_groups` post
meta with only `oeffentlich` and `gruppen`. Editors can select both; audience,
words in the title and the presence of future dates never infer membership.
An explicitly saved empty array omits the post from the landing while retaining
its page; the drift check distinguishes this choice from an unmigrated missing
key. The existing optional `tour_badge` carries labels such as `Sonderführung`,
independently of the two categories. Audience details remain in `target_group`.
Public cards link to source dates and retain availability; between dates they
show an empty-date notice and a details link. Group cards show the existing
inquiry action, even for dual-category tours with public dates. Custom labels
such as `Unterlagen anfragen` remain supported, with a detail-page link when
JavaScript is unavailable. The old `fuehrung_typ` taxonomy remains hidden and
is not the classification source for landing filters. Its landing composition is the
JSON-native `fuehrungen-offers` dynamic slot: `iss-content` supplies grouped
posts and booking state, the theme renders the cards, and the existing
`iss-relations` strip runtime supplies carousel interaction. The former Query
Loop, standalone catalog block, and private catalog JavaScript are retired.
After this cutover the file template owns only the hero shell and inert landing
slot; `ops/migrations/2026-07-11-fuehrungen-landing.php` enables the JSON-owned
body and writes page relations through their owning APIs after code deployment.
`ops/migrations/2026-10-15-fuehrungen-categories.php` and its readable JSON manifest
provide `check|apply|verify` for the reviewed assignments, aligned booking settings
and the offer-slot introduction. Slugs resolve target-local posts; later edits
to affected values fail closed before writes. Apply requires a new private
before-image; no uploads artifact is needed. The duplicate Licht group page and
historical Rathenau page have explicit empty selections; their URLs, narratives
and publication state are retained. The main Licht page supports both types.
The theme consumes `iss_editorial_get_read_model()` in
`themes/industriesalon/includes/tours-render.php`, exposes `route-dossier`,
`compact`, and `standard` skins, replaces the hero description from the first
`intro` section, and renders later gesture sections in the dedicated
single-tour editorial slot.
The public Führung gesture contract is intentionally narrow:
`bildbuehne` is the optional first-viewport image stage with overlay title/text
and a compact gallery; `intro` feeds the fallback hero description only;
`kapitel` is the tour narrative or context; saved `leitfrage` sections use the
shared statement question treatment; `zitat` is a source or voice moment; `galerie` owns image treatments
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

`/about/` is a completed landing cutover. Its file template owns the hero shell
and heading. Two instances of the existing `industriesalon/editorial-landing`
block place the first section inside that shell (`limit: 1`) and the remaining
sections afterward (`offset: 1`). These non-overlapping template slots consume
one nine-section JSON document through the same renderer and preserve original
section indexes for workspace preview/editing. With no attributes the block
still renders the complete document. The triptych remains the first editable
section; images and captions are never copied into the template.
The landing `text_bild_reihe` gesture
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

`/schoneweide/` is a territorial landing. Its file template owns the photographic
hero; four JSON sections own places, history, exploration links and the Atlas.
`gateway.atlas-plates` resolves the selected dossier links once through the
ordinary manual Place contract, supplying both the shared overview map and the
individual card previews. `text-bild-reihe.chronology` uses the normal text/image
item renderer for a compact chronological sequence, with optional images and no
forced card height. Both sections expose the existing editor-visible `links`
field. `gateway.link-list` supplies the exploration row, while
`slot.schoneweide-atlas` delegates to the existing interactive Atlas renderer.
The local content change has a guarded, owner-API migration under
`ops/migrations/2026-10-12-schoneweide-landing.php`; it uses existing attachments.
The reusable `map_img` treatment remains available for map/panorama compositions
and resolves ordinary page Place relations. Map calibration and all Atlas
payloads remain outside landing JSON.

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
`leitfrage`, `zitat`, `fliesstext` and `facts` now use the shared composition renderer
and selected skin; their content uses the common
`kicker`, `title`, `body`, `quote`, and `attribution` fields rather than
parallel gesture-specific keys. `objektfokus` uses shared archive cards across
formats, with the selected skin and the archive owner visibility checks.
`galerie` owns full-viewport and image-wall
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
downloads, links, archive objects, and dynamic references. Image refs appear as
downloadable files through the shared Material renderer, rather than inline
gallery images. `galerie` means an approved presentation section, not an
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
