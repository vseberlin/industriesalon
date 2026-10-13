# Editorial consolidation audit — 2026-09-22

Authority: website checkout `/home/vladimir/wp-website`, based on `3a385a8`,
the running local WordPress database and effective templates. Compared against
the supplied `iss-editorial-composer-audit (2).md` dated 2026-09-21, including its
F1–F14 follow-up and V1–V9 visual review. This is a code delivery; existing
published documents and private drafts are preserved.

## Gesture portability checkpoint — 13 October 2026

The mounted website checkout now exposes 28 registered gesture identifiers in nine formats.
The workspace/CPT expansion is committed as `65c55b8`, shared portability as
`9eab967`, publication adapters as `c090540`, and shared gallery/text/quote
contracts as `32d3e3f`; native publication layout compatibility is committed as
`9fd10d0`, shared conclusions as `5fce0d0`, and shared chapters as `54afba5`.
Leitfrage consolidation is committed locally as `0c1b046` and Merkpunkte as
`3330c7f`; Material is committed as `6bfd6d4` and Archivobjekte as `16b2e53`.
The special-gesture chooser guidance is committed locally as `242593c`.
Registration, editor fields, save validation and
public rendering must agree before a gesture becomes available in another format.

| Gesture identifiers | Effective boundary / remaining work |
| --- | --- |
| `statement`, `gateway`, `text_bild_reihe`, `feature` | One portable field/treatment contract in the vocabulary. Available in landing, article, event, exhibition, project, report, tour and publication formats. All article CPTs inherit this. The existing composition renderer handles them under the selected skin. |
| `intro` | Event/report/Place/publication introductions retain their native meanings. The saved tour introduction remains editable but hidden from insertion; the tour hero uses Bildbühne text or the native excerpt. |
| `kapitel` | One shared chapter contract across eight formats: text, anchors, links, media/captions, inline/right-side media and standard/aside presentation. Event live references remain an owner-specific extension; project chapter/facts pairing retains its separate editing addresses. |
| `fliesstext` | One portable contract across the same eight formats: three text treatments, links, media and archive references. Shared split geometry now works in every skin. |
| `leitfrage` | Saved event/exhibition/tour identifier retained as a hidden statement-family entry. New questions use Überschrift & Intro → Leitfrage across eight formats; existing sections have the same inspector, fields, treatments and renderer. Default question treatment and stored identifiers survive. |
| `zitat` | One portable contract across eight formats: context, quote, attribution, media, objects and source/image orientation. Reuses the theme quote primitive. |
| `schluss` | One portable contract across eight formats: rich text, anchors and links, including links-only endings. Project contact navigation and tour anchors are retained; events now support conclusion links. |
| `galerie` | One portable contract across eight formats: media and archive objects with captions/source links; grid, sequence, wall and viewport layouts. Place retains its separate existing contract. |
| `objektfokus` | One portable archive-card contract across eight formats: anchors, prose, saved labels/captions and owner-resolved metadata/source links. Missing, unpublished and non-archive references remain hidden. Places retain their separate supplements. |
| `material` | One portable downloads/links/anchor/rich-text contract across eight formats. Events retain plain-text items, archive sources and live fields as owner-specific extensions. Place retains its separate contract and picker. |
| `facts` | One portable contract across eight formats: optional rich text, title/kicker, anchors and plain-text value/description rows. Reuses the existing copy and fact-list components; project Dossier retains chapter/facts pairing with separate preview addresses. |
| `upload_intake` | Tour/event/project/exhibition/report calls use the existing guest-contribution setting and signed upload URL. Explicit closure takes precedence. Without an explicit setting, an enabled stored call retains the legacy open default; registration or a private editor snapshot alone does not open intake. |
| `programm` | Manual programme text, shown in the Text group and available only for `event.festival` (Programm / Fest). Chooser filtering and server validation agree; retained invalid sections remain editable for correction. |
| `dynamic_slot` | Landing-only: seven named sources for projects, programme, visit information, newsletter, tour offers, team and Schöneweide Atlas. Each source now explains its owning content beside the selector. |
| `map_img` | Landing map/panorama composition uses linked places and the existing static-map model. Guidance points to native relationships for places and the section media controls for images. |
| `atlas_map` | Landing map treatments use linked places; tour routes use the existing station editor and separate route publication. Each format describes its own setup. |
| `bildbuehne` | Tour hero integration, booking rail and excerpt fallback remain tour-owned. |
| `epoche`, `gegenwart` | Place history and current-state projection remain a separate case. |
| `longread_quote` | Retained saved publication identifier using the shared quote contract. Existing sections remain editable; hidden from new insertion so the chooser presents one quote. No identifier rewrite. |
| `source`, `publication_rail` | Publication intro/source placement and reading-navigation options retain their existing owner contracts. Layout changes use native Update/Publish. |
| `longread_chapter`, `timeline_item`, `photoalbum` | Publication-only gestures now render in every publication layout using the existing components. Mixed order, native navigation/counts and album sources are retained. Multiple albums render with distinct sheet links. Native title/year requirements remain. The publication chapter is labelled Lesekapitel to distinguish its numbering/navigation from the shared Kapitel. |

`massstab`, `vollbild`, `image_wall` and `feature.microblocks` remain declared
compatibility aliases. Existing stored documents are not renamed or migrated.
`feature.opening` remains restricted to the front-page opening contract.

Implementation uses the existing `iss_editorial_formats` filter, `supports`,
rich-text profiles and treatment metadata. The vocabulary marks portable
contracts; the theme declares their section renderer in the same effective
registry. `registry-check` reports a portable gesture without a renderer. No
second editor, document store, block or rendering vocabulary is introduced.

### Special-gesture guidance — 13 October 2026

The chooser keeps portable gestures as compact two-column tiles. Native choices
show their existing registry descriptions across the panel width, before the
compact tiles in each group. Their accessible names stay short; descriptions
are also associated for assistive technology. Search includes those descriptions.
An already-open chooser refreshes when event structure changes while preserving
its search, so unavailable choices disappear immediately. The inspector shows
the selected section's description, including saved hidden sections.
Automatic-source guidance reuses treatment hints and updates immediately
when the selection changes. No guidance is written into document JSON.

The availability audit preserves all format/structure restrictions and the
front-page-only opening treatment. It corrects the earlier intake assumption:
`iss_content_upload_is_open()` deliberately retains legacy stored calls when
no explicit setting exists. The chooser now points editors to **Beiträge von
Gästen**; saving a call cannot override an explicit closure. Places remain a
separate case: their retained `upload_intake` entry is outside this intake
owner's eligible post types and produces no upload invitation. Resolve that
Place-specific choice within its own contract before treating it as usable.

Checks: 13,079 registry/renderer assertions, including exact special-choice
boundaries, festival-only programme validation and explicit upload closure;
301 storage checks over all 94 existing documents with content/metadata unchanged;
41 UI tests, PHP lint/PHPCS/PHPStan, JS and CSS lint. Chrome covers tour-route
guidance and its existing station-editor shortcut, landing-map search, live
festival/general-event chooser refresh, automatic source changes, Fertig, and
native Save Draft/reload on disposable content. Fixtures, revisions and autosaves
are removed.
At 390 px the chooser and its text fit the panel without horizontal overflow.
No real content, templates, database schema or uploads change; no paired data
artifact is needed. The subsequent workflow checks below cover controlled
clipboard paste in Chrome; external Word/Docs paste, Firefox and staff UAT
remain release work.

### Article workflow verification — 13 October 2026

Committed locally as `6b60774`; not pushed or deployed.

Disposable drafts were checked in authenticated Chrome for all eight article
post types: `page`, `post`, `video`, `team_member`, `entity_profile`,
`archivbeitrag`, `archivsammlung` and `archivobjekt`. Each passes keyboard section
selection, inspector editing, access to native owner panels and Save Draft /
reload. Native source blocks, summary and ordering survive; the video URL and
year remain unchanged. Archive metadata/taxonomy and collection sequence
controls remain available from the native form.

The page fixture also passes chooser search/ArrowDown/Escape, real keyboard
copy/paste between rich-text fields, reload followed by explicit recovery,
and native save of the recovered text. Bold, italic, links and lists survive
in canonical JSON. Direct preview title editing confirms with Enter; Escape
restores both title and rich-text contents, including the original formatting.
A confirmed direct title edit also survives native Save Draft and reload.

The existing storage suite now exercises every registered article post type:
private recovery leaves canonical JSON untouched, invalid JSON rejects the
whole native save while retaining recovery, and a valid native save preserves
formatted text and source fields while clearing recovery. This adds 81 checks:
382 total pass over 94 existing documents, with existing post content/metadata
unchanged after cleanup. All 41 UI tests and PHP syntax pass. No production
defect was found in these workflows, so this slice changes tests and documentation
only. Browser and automated fixtures, revisions and autosaves are removed.
No SQL, uploads or template artifact is required.

### Place contract review — 13 October 2026

Committed locally as `ec54190`; not pushed or deployed.

This is a separate contract review, not an expansion of portable gestures or a
Place migration. The mounted runtime has 84 Places; only Kino Spreehöfe (12899)
has stored/enabled Place JSON. Its v1 document contains one introduction, six
epochs, one present-day section and one contribution section. The other 83 Places
retain legacy rendering. `single-register_place` is theme-backed. The registry
check and the pilot's six-epoch projection parity check pass.

| Gesture | Current contract and observed behavior |
| --- | --- |
| `intro` | Body of the first nonempty introduction renders in the fixed opening position. The editor also offers title and media; neither renders. Additional introductions are accepted but their bodies disappear after the first nonempty one. |
| `epoche` | Repeatable phase with title, prose, dates, era, function, current flag, sources and media. Public rows show the first usable image; the projection retains the media IDs. JSON owns editorial history, and the register service owns derived epoch/state rows. Validation disagrees with that service; see the first priority below. |
| `gegenwart` | First nonempty body renders after the history rail under the fixed heading “Der Ort heute”. Offered title, media and links are not rendered. Additional present-day sections are likewise not displayed. |
| `galerie` | Separate Place supplement: media/archive references and four gallery layouts use the existing exhibition renderer. It is not the portable gallery contract. |
| `material` | Separate Place supplement: anchor, downloads and links use the same existing renderer. Archive objects belong in its gallery, not this picker. |
| `upload_intake` | Registered, insertable, seeded by the candidate builder and retained in the pilot, but produces no gesture output because the signed intake owner excludes Places. The template has separate contribution links; these are also inconsistent with the current endpoint. |

The dossier deliberately groups introduction, history, present-day prose and
supplements rather than following arbitrary cross-type document order. Accepted
supplier milestones can further sort the public chronology. The shared reorder
interface does not explain this distinction. Geographic/current facts, graph
actors, relationships, the template fact panels and Atlas remain with their
existing owners; generic composition gestures must not duplicate them.

Prioritized follow-up:

1. **Guard Place saves before canonical JSON changes.** Implemented in the
   validation slice below. The original review's disposable enabled
   Place with one valid projected epoch reproduced five failures: missing title,
   missing era, missing function, two current phases, and no remaining epoch.
   Each passed `iss_editorial_validate_document()` and
   `iss_editorial_save_document()` returned true; JSON changed while the previous
   epoch projection remained. The projection callback returns `WP_Error` through
   an action whose return value the save engine does not consume. Reuse the
   register owner's validation through the existing editorial validation/native
   update path, retain invalid work in recovery, and test rejection of the whole
   native save plus a successful corrected retry. Include starter and last-epoch
   deletion behavior. Projection write-failure handling needs its own explicit
   owner-bound test; this review reproduced validation failures, not disk/DB faults.
2. **Align offered fields and ordering with the dossier renderer.** Render
   supported title/media/link fields through the existing Place components, and
   make the fixed dossier positions and singular introduction/present-day sections
   clear. Preserve saved content; do not silently delete fields or extra sections.
3. **Resolve Place contributions through the existing intake owner.** The
   template helper emits `event=ort__<slug>`. The current Event Drop handler
   resolves the prefix as a post type; `ort` does not resolve `register_place`.
   Moreover, `iss_content_upload_is_open()` rejects Places even if the identifier
   is corrected. Runtime URL resolution confirms both mismatches; no upload was
   submitted. Unify the template/gesture invitation with the chosen owner policy
   in a separate slice. Changing the chooser alone would leave the public links
   misleading; merely adding Places to report-source types would wrongly couple
   intake to reports. Keep new Place intake insertion out of broader rollout until
   this contract is settled, retaining stored sections for compatibility.

Verification: authenticated Chrome inspection of the existing chooser/inspector
and public dossier; read-only inventory/registry/parity checks; five temporary
save/projection probes and renderer probes for ignored fields, duplicate intro
and fixed ordering. The temporary post and its projections were deleted through
owner hooks. Live pilot JSON and epoch rows remained identical across the probes.
No production code, real content, templates, schema or uploads changed; no paired
data artifact is needed. This review does not constitute Place upload acceptance
or a full editor save test on real content.

### Place save validation — 13 October 2026

Committed locally as `45a3568`. The register epoch service exposes its existing row validation without
writing. Both its projection writer and the shared editorial validation filter
use that method. One extracted document-to-epoch mapping serves preflight and
projection; required fields and the single-current-phase rule remain owned by
the register service. Editors receive actionable German messages.

Canonical saves and native WordPress updates reject missing epoch titles, eras
or functions, multiple current phases, and documents without an active epoch.
The empty new document and unfinished Place starter remain recoverable drafts;
they must be completed before saving the canonical document. Trashed unfinished
epochs do not block otherwise valid history. Failed validation leaves native
post fields, saved JSON and epoch/state projections unchanged. A corrected retry
updates the document and its derived history through the existing save hooks.

Checks: 433 storage assertions over all 94 existing documents, 13,079
registry/renderer assertions, all 41 UI tests, PHP syntax, PHPCS and PHPStan.
The storage suite verifies recovery, whole-update rejection and corrected retry,
plus preservation of existing Place epochs, supplier milestones and states after
fixture cleanup. Its preview fixture now uses a valid epoch after an empty intro.
Chrome confirms the missing-era message, Fertig retaining the invalid inspector,
recovery after reload, correction, native Save Draft/reload and matching projected
history. Browser and automated fixtures and their derived rows are removed;
Kino Spreehöfe's six-epoch parity check still passes.

This slice addresses validation failures before writes. It does not make the
projection writer transactional or add propagation of database write failures;
that is the next separate persistence slice. Renderer/ordering and contribution
issues from the review remain open. No real content, template, schema or uploads
changed, and no paired data artifact is needed.

### Place persistence — 13 October 2026

The register epoch owner now coordinates canonical Place JSON, skin/activation,
epoch rows, snapshot metadata and Atlas states in one checked transaction. Epoch
and state replacements also use it independently. Participating tables must be
InnoDB; a source-row lock serializes writers and savepoints preserve caller-owned
transactions. Failed reads/writes and PHP exceptions return failure after rollback;
WordPress metadata caches are evicted and successful-save notifications follow
successful persistence. The engine exposes one writer wrapper for this owner,
without moving Place data rules into the shared editor.

Native save failures show an error before recovery is cleared. WordPress's native
revision metadata copy is wrapped and verified before projection, preserving exact
revision values. First activation, the Place candidate CLI and the two existing
Place migration consumers use the combined save API instead of a later activation
and second projection. Those scripts were not executed. Native post fields such as
title/excerpt, taxonomies and earlier graph writes are outside this boundary; it
does not promise rollback of the whole WordPress update. Callers with an outer
transaction retain responsibility for final commit and cache coordination.

Verification: 205 checks in `tests/e2e/bin/place-editorial-atomic.php` use temporary
copies of the actual schemas. They cover transaction/engine checks, source reads,
JSON/skin/activation writes, epoch update/insert/delete/readback, both snapshots,
state delete/insert, late PHP failure, commit/savepoint release, native error and
recovery retention, revision read/copy/delete/projection, retry, and caller-owned
transactions including disabled autocommit. Real posts, postmeta, epoch/state rows
and options are unchanged after cleanup. The 433 storage checks, 13,079 registry
checks and 41 UI tests pass. PHP syntax and PHPStan pass; PHPCS passes for the
engine/CLI/epoch service, while the state service has exactly its pre-existing
7 errors/22 warnings. Chrome confirms an epoch edit, Fertig, native Save Draft and
fresh reload; saved JSON and projected history agree and recovery is cleared.
All browser/test fixtures are removed; Kino Spreehöfe still has six matching epochs.

No real content, schema, template or upload changes and no new paired data artifact
are required. Renderer/ordering alignment is next; contribution intake remains a
separate follow-up. This persistence slice is verified and accepted for local commit.

The existing media/text pattern now owns its flex layout for native blocks and
JSON alike. Its defaults are component-scoped, the overlay retains image height
on phones. Native Columns compatibility selectors remain because WordPress
assigns its own flex basis; their markup and responsive rules remain supported. The image-overlay treatment now
renders every supplied link, and feature leads render in retained v1/v2 documents
without changing the stored version. Event composition can use the full existing content
column; ordinary event prose keeps its reading measure. Archival skin palettes
flow through existing domain variables, without new gesture selector trees.

Publication adapters use the same shared renderer and existing publication-native
components. All four layouts keep chapters, dated entries, albums and shared
sections in document order. Timeline composition preserves
consecutive epoch groups, generates unique repeated-epoch anchors and counts only
native dates/epochs. Each album keeps its own sheet sequence and source metadata;
album navigation includes visible sheets from every album with distinct anchors.
Intro/source placement retains the existing layout contract; an album layout
without an album section still shows that text before its other content.
Payload caches now distinguish enabled/disabled canonical JSON and exact preview
snapshots. An empty legacy album no longer suppresses original block content.
The existing album stylesheet loses its `100vw` breakout (which caused horizontal
scrolling), and its phone title stacks instead of clipping. Skin variables reuse
publication palettes; no extra gesture selector tree is introduced.
Chapter text and shared headings now consume existing surface/skin variables,
fixing dark-on-dark text in Bildmatrix. Its album palette is component-owned so
the same album can appear in other layouts without losing its colours.

Eleven visible composition gestures now share fields and rendering across eight
formats. Gallery/text/quote rendering consumes resolved references through one
existing composition path, with published-object checks delegated to the archive
owner. Existing documents adopt the shared presentation while retaining their
selected palette. Split-text rules move out of Dossier into the renderer layer;
duplicate Territorial positioning is removed so shared phone stacking can apply.
Domain gallery/quote compatibility branches and their styling remain for legacy
content and Place supplements; they are not a second new gesture contract.

Verification covers the eleven gestures, their treatments/layouts in every
registered skin of eight formats, save/reload, public content routing, snapshot
addresses, media/archive captions, source visibility, links, rich text,
front-page exclusion and event-structure restrictions: 12,606 assertions plus 37
UI tests. Retained publication quote IDs and Place fields/rendering are checked.
The publication matrix covers four layouts in all three skins,
interleaving, native navigation/source data, all-native mixed documents, multiple
albums, standalone native gestures under mismatched layouts, warm-cache layout
switches, disabled fallback and two successive
snapshots after a warmed public cache. Storage checks pass 300 assertions over all
94 existing documents, with post content/metadata unchanged. Chapter coverage
includes all format/skin combinations, versions 1–3, both media positions and
aside treatment, captions, links, event live-only references, owner boundaries,
project pairing and exact snapshot addresses. Shared chapters render in all
publication layouts without joining native chapter counts/navigation.
Chrome checks event, tour, exhibition, project, article and publication at
desktop/390 px: no document overflow, images stack and captions remain visible.
The project pairing also fits 820 px. Event edit/Fertig/native Update/reload
preserves its live reference, media, caption and link. Paired project facts edit
independently. The chooser distinguishes Kapitel and Lesekapitel; live reference
counts now include dynamic sources. The preceding conclusion checkpoint covered
links-only endings and title/link editing. Leitfrage coverage includes versions
1–3, every native event/tour/exhibition skin and statement treatment, anchors,
rich text, links, exact preview addresses and saved identifiers. New statements
retain anchors across all eight formats; native intro/hero and Place contracts
remain separate. Chrome checks all three existing exhibition questions and
private event/tour/exhibition samples at desktop/390 px with no overflow.
The compact chooser offers one question path; new question insertion and Fertig
return to the outline were exercised. Existing question title/treatment
edits return through Fertig to the outline; native Update/reload retains type,
anchor, formatted text and links while leaving the neighbouring section intact.
Review fixtures are removed.
Earlier tour/event/exhibition checks remain recorded in the preceding commit.
Places remain explicitly excluded. No SQL, content migration, uploads or template
artifact is needed. The reviewed local templates are file-backed except the
existing Ausstellung override (26309), which was rendered successfully and left
unchanged.

## Chapter contract boundary

The read-only inventory found 72 existing `kapitel` sections. They retain their
stored identifiers and fields. The vocabulary supplies the common contract;
only event chapters add `dynamic_refs`. The existing event sanitizer and
institution-field renderer own those references, including the stored
`address.full` reference. Other formats reject the event extension.

The theme's chapter body reuses shared prose, links and resolved media. The
project dossier uses that same body alongside its facts component, retaining
both anchors and separate preview selection. A small chapter layout contract
owns right-side media, phone stacking and aside presentation; no second field
editor or storage path is added. Old domain renderer branches/CSS remain for
legacy compatibility; this slice removes duplicate chapter definitions and the
paired project's duplicate copy markup. Native publication chapters keep their
numbering/navigation contract. Places remain separate.

## Question contract boundary

The read-only inventory found three saved `leitfrage` sections, all in
exhibitions. They keep their identifiers and text. The hidden registry entry
inherits the statement contract with `statement.leitfrage` as its default;
saved selections can use the other statement treatments. No stored identifier
is rewritten. The exhibition starter now creates the canonical statement
treatment. The existing portable adapter resolves declared families for rendering
only, including the retained publication quote identifier. The old question
layout mappings and obsolete domain/skin CSS are removed; shared treatment/skin
CSS owns the presentation. No new renderer, stylesheet or storage path is added.

## Facts contract boundary

The read-only inventory found 12 stored `massstab` sections: five prose-only
sections in two exhibitions and seven structured lists in six projects. The
existing normalizer already resolves these to `facts`; no stored documents or
versions are rewritten. The shared registry now exposes Merkpunkte across eight
formats. Optional formatted prose remains intact, including legacy numbers and
emphasis. Fact values/descriptions remain escaped plain text. Empty, value-only,
description-only and zero-value cases are covered.

Standalone facts use the existing shared copy and fact-list components, with a
smaller heading, responsive columns and top-aligned card contents. Removed
exhibition stat-layout CSS and standalone project key-points CSS are obsolete
because those paths now delegate to the shared renderer. Project Dossier keeps
its existing paired fact column and component styles; its anchor now targets the
column, and kicker/body preview fields and document text version are propagated.
Domain fact styling outside these replaced layouts remains compatibility code.

Facts verification includes versions 1–3, every format/skin, all publication
layouts, save/reload, unsupported-field rejection, escaping and distinct project
chapter/fact snapshot addresses. Chrome checks the two existing exhibitions,
Futura's paired facts, and private article/publication samples at desktop/390 px
without horizontal overflow. Article chooser insertion, row edit/add/remove,
Fertig, native Update and reload retain zero values, anchors and formatted text;
the neighbouring chapter stays intact. Private fixtures/revisions/autosaves are
removed. PHP lint, targeted PHPCS/PHPStan and changed CSS lint pass. Staff UAT
and Firefox remain separate release checks.

## Material contract boundary

The read-only inventory found 13 saved Material sections: seven projects, three
events, two tours and one exhibition. The old project/tour paths suppressed
image attachments, and the event path suppressed its stored archive source.
Shared Material keeps every resolved attachment as a downloadable file, retaining
labels and IDs; gallery images remain a separate presentation choice. Events
retain their plain-text notes, live institution fields and archive references.
Archive visibility stays owner-controlled. Closing booking removes ticket links
without dropping accompanying notes. No stored sections are converted.

The shared renderer reuses copy, links, the existing `iss-file-insert` primitive
and the archive reference renderer. The primitive now uses semantic surface
colours instead of hard-coded light colours. No new file-card CSS vocabulary,
media store or editor is added. Obsolete project/tour Material branches and
layout CSS are removed. Native event downloads remain for engine-disabled
rendering, and Ausstellung supplements retain Place Material. Generic domain
media helpers/CSS keep their callers. Material picker changes apply only to its portable contract,
so Places keep their existing restrictions. Set selection still permits only
approved/promoted attachment-backed items.

Verification covers versions 1–3, every format/skin, all publication layouts,
file-only/link-only/body-only sections, missing attachments, image downloads,
labels, anchors, event extensions, private archive exclusion and closed tickets
with retained notes. The UI suite covers media reselection, explicit removal,
Set confirmation and Place compatibility. Chrome checks existing project,
event and tour Material plus a private publication at desktop/390 px. Downloads
fit both light/dark skins; native media selection preserves PDF/image IDs and
edited labels. Fertig/native Update/reload retain the document and neighbouring
chapter. Review fixtures, revisions and autosaves are removed. No real stored
content, templates or uploads changed; no paired artifact is needed.

## Archivobjekte contract boundary

Read-only inventory found two existing sections, on Kinder im Werk (26381) and
Frauen im Werk (26287). Their six source references retain object/Set/member IDs,
labels and saved captions. The prior renderer ignored label/caption overrides;
the shared renderer passes them through the existing archive featured-card API.
No source record, Set membership, stored document or identifier is rewritten.

Twelve visible composition gestures now share contracts across eight formats.
The shared archive inspector uses the existing media controls and archive picker;
label/caption edits, reselection and explicit removal retain reference identity.
The archive owner still decides public availability and supplies metadata and
canonical source links. Complete captions and uncropped images use the moved
card component; archive landing and native exhibition geometry use component
variables. Obsolete object-grid layout rules and its dispatch entry are removed.
Place supplements and native Gutenberg archive cards retain their render paths.

Verification: 13,039 registry/renderer assertions across formats/skins, versions
1–3 and four publication layouts; 301 storage checks over all 94 existing
documents with content/metadata unchanged; 39 UI tests; targeted PHP lint,
PHPCS/PHPStan, JS and CSS checks. Chrome covers both existing exhibitions and
a private publication at desktop/390 px, dark/light skins, source labels/captions,
picker reselection, chooser insertion, removal, Fertig and native Update/reload.
The archive landing card fits both widths. Its untouched quick-navigation grid
has separate phone overflow (409 px content / 375 px viewport), recorded for
follow-up. No SQL, uploads or template artifact is required.

Next after review: audit availability and guidance for remaining owner-bound
intro/hero, maps, programme/dynamic slots and intake. Places remain separate.
Staff UAT and Firefox are still release checks.

## Findings and disposition

| Finding | Result |
| --- | --- |
| Home alone had the current workspace | One workspace for all nine formats, including v1 documents. The general article format reuses existing sections for pages/posts/videos. |
| Parallel section UI | Old section cards/modal and modal-preview layout removed. Inspector, canvas and native owner panels share one document and autosave queue. |
| F4/F5: disconnected vocabularies | Effective format registry combines owner metadata, aliases, defaults, skins, skin features and starters. Renderer skin lists and landing treatment allowlist removed. Read-only CLI consistency check added. |
| F1/F2: blind treatments, undifferentiated palette | Registered labels, icons, groups and schematics available across formats. Native radio treatment controls retained. |
| F3: preview | Existing authenticated WordPress preview extended to all formats. Source indices survive skipped sections, publication payload transforms and paired project sections. |
| F6: language | Registry descriptions and shared format/skin labels use German. Stored identifiers remain stable. |
| F7/V3: control styling | Shared input appearance belongs to admin controls; workspace CSS owns layout. Existing tokens and text toolbar retained. |
| F8: controller size | Removed alternative editor and extracted inputs/schematics into existing `ui.js`. Domain-specific field controls remain in the state controller; further extraction is maintenance, not a second state/store. |
| F9: authoring boundary | Enabled JSON/new auto-drafts use one workspace. Existing block documents retain native WordPress authority until explicitly migrated. No dual editing of the same enabled document. |
| F10: exact text colours | Named theme presets are supported. Previously approved custom hex colours remain supported; no lossy nearest-colour migration. This intentionally differs from the audit recommendation. |
| F11/V1: opening ownership | Explicit opening treatment and section-specific guidance retained, with tests. Article starters cannot introduce a front-page opening. |
| F12: repeated validation | Snapshot cache retained; repeated consumers validate once. |
| F13: rich-text profiles | Registered profiles drive validation/editing. Cross-format checks prove emphasis and named colour retention. Unsupported legacy markup remains explicit review work. |
| F14: frame portability | Device sizing uses standard transforms rather than CSS zoom; fixed internal viewport dimensions are tested. |
| V2/V4–V8 | Existing compact toolbar, inspector tabs, growing title, outline labels, measured selection, fixed workspace and undo retained. DOM checks cover selection, reorder, recovery, failure and native publishing navigation. |
| V9: untitled generated section | Outline uses registered treatment/slot labels; no invented editorial title is saved. |
| CSS mixed renderer/skin/page rules | Renderer and skin files separated. Legacy front-page CSS does not load on the JSON page. Five exact duplicate declaration blocks consolidated. |
| CSS anchored to URL names | Removed anchor-driven styling decisions. Changing a section anchor cannot change its treatment/classes. |
| Place sections silently absent | Registered gallery/material/upload sections now use the existing shared media/archive renderer. |
| Video template bypassed article content | Existing shared composition block now supplies the article slot; timed transcripts retain their owner. Draft headers are available only to editors; anonymous lookups remain published-only. |
| Generated excerpts rendered full compositions | Content renderers skip excerpt generation, preserving native excerpts and preventing duplicated text/related-card content. |
| Preview scaling created outer scrollbars | Existing viewport CSS now lets the iframe own scrolling; project navigation controls belong to the outline owner panel. |

## Content and template snapshot — 22 September 2026

- 86 stored documents validate: events 26, places 1, landings 4, tours 15,
  exhibitions 16, projects 7, reports 1, publications 16. Registration does not
  imply activation: only three exhibition documents are currently enabled.
- Homepage **12257** (`home-2`) has a published v3 document with 10 sections and
  four deleted sections. Administrator autosave **27678** is a separate valid
  10+4 composition. The previous handoff's 27454 / 12+2 private-only description
  was stale. Neither current composition is replaced by this delivery.
- About, Schöneweide and Führungen still store v1 documents. They now use the
  same workspace without an automatic schema/content rewrite.
- Effective `single-ausstellung` uses database override **26309**. Additional
  overrides include `page-publikationen` **26560** and `page-projekte` **26532**.
  They were inspected and preserved; theme files alone are not their authority.
  `single-video` is theme-owned locally and now contains the existing shared
  composition block. Check target overrides before deployment.
- Report **27388**, event **26813**, all source content, revisions, native
  relationships and Set data remain preserved. No historical migration replayed.

## Verification and repeatability

From the website checkout, against the existing local stack:

```sh
node --test tests/e2e/bin/editorial-ui.cjs tests/e2e/bin/editorial-set-ui.cjs
wp iss-editorial registry-check --format=json
wp eval-file tests/e2e/bin/editorial-registry.php
wp eval-file tests/e2e/bin/editorial-storage.php
wp eval-file tests/e2e/bin/editorial-sets.php
bash tools/phpcs-target.sh
bash tools/phpstan-target.sh
```

`wp` denotes the target's normal WP-CLI invocation; in this local Docker stack
use the wrapper and `/tmp/iss-tests/` paths recorded in the handoff. The registry
command is read-only. The three PHP suites create only their own disposable
fixtures and remove them in `finally`; storage/Set suites check preservation.
Run database suites sequentially. Also run changed PHP syntax, JS lint, CSS lint
and `git diff --check`.

Automated evidence: 287 storage checks (including the 86 existing documents),
137 cross-format registry/renderer checks, and 169 Set assertions. The browser
checks public homepage/About/Schöneweide/Führungen at phone and desktop widths;
no horizontal overflow, one H1, and no public editing markers. The Schöneweide
media-text treatment retains its 40:60 ratio. Public HTTP checks cover the
existing formats and protected report/event routes.

Authenticated Chrome checks passed for the home workspace and disposable
project, tour, publication, ordinary page/article and video/article drafts.
Project editing, autosave, reload/recovery and native Save Draft retained the
changed section title without publishing. Native relationship navigation and
route owner controls were checked. Project/publication phone layouts fit at
390px; the 800px project layout also fits. The video fixture used an intentionally
unresolvable media URL: article/header rendering was verified, playback was not.
All browser fixtures and their empty Sets were removed; viewport reset.

34 DOM tests, targeted ESLint/Stylelint, PHP syntax and PHPStan pass. PHPCS passes
for 18 changed PHP files; `iss-content/includes/videos.php` retains the same 24
pre-existing escaping findings as HEAD, with no new findings. They are not
silently suppressed or claimed as a clean lint run.

Full-row comparison uses a baseline of 15,390 posts and 238,520 non-lock metadata
rows. The latest rerun reports private draft differences: autosaves 26790 and
27228 changed, autosave 27941 was added, metadata was added to autosaves
26723/26790/27941, and `_iss_relations_route_draft` was added to tour 12191.
Published content/JSON and home draft content remain unchanged; no rows
were removed. Home autosave 27678 has timestamp/base-token bookkeeping changes;
normal edit locks are excluded. The broad preservation check therefore does not
pass full-row equality. These private drafts remain intact; their provenance
requires review before accepting a new baseline. Firefox, real clipboard/paste
and staff speed/acceptance remain rollout checks.

## Homepage hero restoration

Staging and Git comparison found that commit `3ac41b7` introduced a separate
`iss-landing-opening` design when JSON gained hero ownership. Its serif font,
740px height cap, gradient and filled buttons differed from the existing
`iss-front-hero` pattern. The front-page skin also overrode its zero padding;
ordinary mobile section spacing affected it too. The CSS split retained this
regression rather than resolving the duplicate presentation contract.

The JSON opening now renders native Cover/Buttons through the original hero
pattern. The template fallback uses the same homepage modifier; its former
inline gradient and kicker settings belong to `patterns.css`. Generated group
spacing is expressed as explicit hero rhythm. The duplicate opening CSS is
removed, and ordinary section rules exclude the opening. Optional JSON prose
is retained beneath the image. Cover image sizes account for portrait viewports.

Chrome comparison against `https://staging.industriesalon.info/` at 1440×900 and
390×844 verifies matching hero height, title font/position, gradient and kicker
spacing, no extra section padding and no horizontal overflow. The JSON preview
retains section/field addresses. Theme-owned `front-page` parses/serializes
without changes; one Cover and one editorial slot remain. No content, enabled
flags or DB template overrides are changed by this repair.

## Delivery boundary and next action

The owning plugins and theme are deployed together on staging. The code alone
requires no data rewrite. The subsequently authorized website/editor content sync
applied `ops/migrations/2026-09-22-editorial-staging-sync.php`, followed by
`ops/migrations/2026-09-22-editorial-sync-dependencies.php`, with
`ops/uploads/2026-09-22-editorial-sync.manifest`. It transferred 52 documents,
their content/template/media prerequisites, and the report's required source
connection through owner APIs. Private autosaves and unrelated domain data were
preserved. Use migration `verify --use-include`, not a second `apply`.

Staging validates all 86 stored documents and all nine registry formats. Public
route, desktop/phone hero and keyed map-tile checks pass. Full and targeted
backups, scope and preservation evidence are recorded in `handoff_CURRENT.md`.
The local content database was not rewritten. Production was not touched.

Next action: authenticated staff acceptance and Firefox/clipboard checks on a
landing, project, tour and publication, including owner controls, draft recovery
and native Save Draft. Existing tests and the registry command remain the
repeatable gate for later registry extensions.
