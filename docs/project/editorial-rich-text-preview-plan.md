# Editorial text editing and live preview: front-page pilot

Status: implemented locally, 2026-09-21; staff acceptance and publication pending.
The subsequent [landing workspace](editor-workspace-plan.md) replaces the modal
as the default v2/v3 landing interface. This file records the underlying rich-text
and preview contract; use the workspace plan for its current interaction model.
The homepage composition exists only in the admin author's private autosave.

## Outcome and scope

Existing staff and volunteer editors can build and revise a front page using
familiar rich-text controls, straightforward links, text colours, and a visible
rendered result. They already operate the Newsletter composer successfully;
that interaction is the reference for this work.

The first deliverable is a new front-page composition in the existing homepage's
private editorial draft, together with the editor improvements needed to author
it. The shared JSON engine, native revisions, format registry, domain data owners,
and theme renderers remain the foundation. Publication is a subsequent explicit
editorial action.

This implements the focused text/preview part of
[Editorial Admin Simplification](editorial-admin-simplification-sow.md).
[Editorial Platform](../architecture/editorial-platform.md) remains the current
implementation contract; this document records the agreed scope and review tasks.

## Baseline established from source

- `iss-editorial/assets/admin.js` already initializes a compact WordPress
  TinyMCE editor for body and lead fields. Quotes and repeated-item descriptions
  still use plain textareas. Its client sanitizer removes colour spans.
- `iss-editorial/includes/storage.php` strips HTML from repeated-item text.
  Some landing renderers also strip formatting from otherwise rich body fields.
- Newsletter uses `wp.editor.initialize()` and refreshes rendered block HTML
  after field changes/editor blur. A new editor dependency is unnecessary.
- WordPress's existing link dialog includes link text, URL and internal-content
  search. Reuse this before introducing any replacement dialog or search route.
- The theme palette already permits custom colours. `tokens.css` maps its named
  presets. Admin controls must consume the effective palette, not copy hex lists.
- Existing preview saves run through an authenticated, version-checked native
  autosave. Invalid but recoverable drafts are deliberately distinct from valid
  previews/publication.
- Landing sections lack a uniform preview identity. The existing front-page
  file also contains a static hero and legacy body fallback; enabled JSON is not
  automatically the authority for every visible word.

These findings describe the starting point, before the local implementation below.

## First front-page composition

Use current approved copy, media and destinations as source material. Establish
their actual DB/template authority before preparing the draft. Proposed order:

| Reader purpose | Existing composition mechanism |
| --- | --- |
| Understand the Industriesalon | Opening `feature.opening`, with image, heading, short text and action |
| Choose a visit or activity | `gateway` cards for tours, exhibitions and relevant existing destinations |
| See current activity | Existing `front-timeline` and `front-projects` automatic slots |
| Discover a story or archive material | `feature` and/or non-linked `text_bild_reihe` using existing editorial material |
| Plan a visit and stay in contact | Existing `front-visit-info` and `front-newsletter` slots |

Keep authoritative dates, visit facts and newsletter behaviour in their existing
owners. The pilot is page composition, not new functionality in those systems.
Do not reopen Atlas/map work as part of this pilot.

The opening image and narrative must be editable in the same composer. Reuse
the existing feature gesture; the theme assigns the opening heading its correct
H1 role. Suppress the static hero only when the new document supplies its
replacement. Legacy documents retain their current hero and fallback behaviour.
There must be one public H1 and no duplicate hero/body.

## Editing behaviour

### Text and links

- Use the installed WordPress editor with one compact toolbar: undo/redo,
  bold/italic, lists where supported, labelled Link, text colour, highlight,
  and clear formatting. Keep native keyboard shortcuts.
- Give narrative fields consistent controls, readable type and comfortable
  writing space. Short fields receive the applicable subset.
- Link opens the full existing WordPress dialog directly. It must work both
  with selected text and with only a cursor; the latter asks for link text.
- Capture/restore the editor selection through link and colour interactions.
  Keep the active editor mounted during autosave and preview updates.
- Clicking an existing link exposes edit/remove actions. URL changes must
  preserve the visible wording and its existing emphasis/colour.
- Reuse WordPress's authenticated internal-link search for public editorial
  destinations. Existing structured card destinations continue to use their
  current reference contract; inserting a prose hyperlink creates no graph edge.
- Treat the WordPress link dialog, colour popup and media picker as child
  interactions of the section workspace. Escape, focus return and undo must
  affect the intended layer/action.
- Pasting keeps paragraphs, basic emphasis, lists and links. Clean imported
  office/document styling without stripping deliberately selected editor colours.

### Field contracts and colours

Declare rich-text profiles in the existing format/section registry and expose
them through its normalizer. PHP validation and the client editor consume the
same capability description; the server remains authoritative.

| Field shape | Profile |
| --- | --- |
| Body and lead | Paragraphs, emphasis, lists, links and colour/highlight |
| Non-linked image/text descriptions | Inline emphasis, links, line breaks and colour/highlight |
| Description inside a whole-card link | Inline emphasis and colour/highlight; card destination is edited separately |
| Section headings and labels | Plain text in the pilot; typography/heading structure remains theme-owned |
| Attribution, dates, IDs and other factual fields | Existing owner/field rules |

Do not create nested links inside clickable gateway cards. Do not expose a
formatting action that the selected field cannot preserve and render.

Provide theme swatches, a custom hex colour picker, a small session-local recent
selection list, and explicit resets for text colour/highlight. Normal text keeps
the page's default colour. Reset preserves links and other formatting.

Respect the repository's CSS ownership and no-inline-style rule: store approved
colour marks on spans using allowlisted preset classes or deterministic classes
containing a validated six-digit RGB value. The theme emits scoped stylesheet
rules for custom values using WordPress's stylesheet API. The editor uses the
same validated colours in its editing document. Arbitrary CSS, arbitrary classes
and a separate colour database are unnecessary.

### Versioning and content compatibility

Existing HTML body/lead fields and plain item descriptions must not be confused.
Version 2 introduced the extended text contract; version 3 adds named palette
references and an explicit opening treatment, keeping the same section fields
and reference structure. Version 1
remains readable and retains its current public interpretation.

Convert the pilot explicitly into the author's draft: escape legacy plain item
text before converting it to rich text; retain existing rich bodies, metadata,
media, relations, deleted sections and unknown data for review. Opening a page
does not rewrite or upgrade canonical content. Unsupported legacy markup is
reported, never silently discarded by opening/saving the editor.

Generalize the existing version checks in validation, autosave and revision
recovery to consult format-supported versions. Initially only `landing` opts
into versions 2 and 3; event storage and other format semantics are unchanged. Do not
allow older writers to silently remove the new formatting. Keep version-3
reader support if the new editor UI must be reverted after content is saved.

## Live preview

- Expand the existing section-editing workspace into controls on the left and
  actual page preview on the right. The preview must remain visible while text
  is edited, rather than sit behind an obscuring modal.
- On narrow screens use Edit/Preview tabs with preserved selection and scroll.
  Include desktop, tablet and phone preview widths; these change the iframe's
  layout viewport. Keep the existing separate-window preview available.
- The section selector and preview follow each other: selecting in the editor
  reveals that section; clicking a preview section opens its controls. The frame
  stays mounted, pending edits survive, and narrow windows return to Edit.
- Reuse the existing authenticated full-page preview URL and theme renderer.
  Render the whole document so neighbouring sections and the real template are
  represented correctly. Do not duplicate page rendering in JavaScript.
- Reuse the existing serialized autosave queue. Coalesce changes during typing;
  refresh after the latest successful, valid save. Applying a link or colour
  schedules the same path. Target a visible result within two seconds of an idle
  edit on the healthy local stack; measure before claiming this is achieved.
- Preserve selection, undo history, preview scroll and the active section. Add
  preview-only section markers keyed to the exact draft snapshot and source
  section index. Do not treat an index as durable identity across reorder/save.
- Check the iframe source/origin and draft token when exchanging preview state.
  Ignore obsolete responses. Preview script hooks exist only for authenticated
  editorial preview and do not change public section anchors.
- Show separate saved-draft and preview status. On invalid content or a failed
  refresh, retain and label the previous valid preview; preserve unfinished input
  for recovery. Never claim that old output represents the current draft.
- Loading the preview must not publish, submit a newsletter form or trigger
  another public action. Keep dynamic slots visible, with interactive submission
  disabled inside the editing preview.
- Do not automatically save canonical route/date/relation panels as a side
  effect of text-preview refresh. Their existing contracts remain distinct.

## Implementation sequence and ownership

1. **Establish the pilot baseline.** Verify served code, active theme, homepage
   option, enabled landing document, template overrides and existing autosave.
   Inspect the actual link/colour behaviour in a browser. Preserve existing
   canonical content and any recoverable draft before preparing the pilot.
2. **Complete one text path end to end.** Add landing text profiles/version
   handling; fix client normalization, server sanitization and owning renderer
   together. Enable colour round trips first on body/lead, then repeated text
   with the appropriate link rules. Test with the opening and story sections.
3. **Improve links and the workspace.** Integrate the labelled WordPress link
   dialog and compact colour controls; fix nested focus/selection handling.
   Extend the existing section workspace for the preview pane. Extract only
   cohesive rich-text/preview code from `admin.js` when needed; no broad rewrite.
4. **Connect live preview and compose the front page.** Use the existing save
   queue and full theme preview; implement snapshot matching and stale-state
   feedback. Prepare the complete proposed front page through the private draft
   path, including the editable opening feature and existing dynamic slots.
5. **Verify with staff, then extend.** Perform the tasks below without an
   introductory lesson. Fix observed failures before adapting other formats.
   A full schematic library and starter manager can be evaluated later; they do
   not block this deliverable. Preview-to-section selection was added following
   the user's first review.

Primary implementation locations:

- `plugins/iss-editorial/assets/{admin.js,ui.js,admin.css}`: shared editing and
  preview interaction; small extracted files only where responsibility warrants.
- `plugins/iss-editorial/includes/{formats.php,storage.php,admin.php,revisions.php}`:
  profiles, validation, native draft/version compatibility and asset configuration.
- `plugins/iss-content/includes/editorial.php`: landing field capabilities.
- Theme `includes/landings-render.php`, relevant template authority and existing
  renderer CSS: preserve formatting, colour output and front-page composition.
  Theme tokens and `theme.json` remain the palette authorities.

## Acceptance and regression checks

The staff task is: change an introduction; make selected words red; link them to
an existing page; change the destination; reset a colour; undo; preview on phone;
close/reopen and continue. Repeat link insertion without a selection and editing
inside an image/text row. This must work without lost words, formatting or focus.

Extend existing `tests/e2e/bin/editorial-ui.cjs` and `editorial-storage.php` for
behavioural/round-trip regressions, including safe link protocols, custom colour
validation, legacy literal text, version-1 rendering, version-2 drafts/revisions,
invalid drafts, stale responses, two-tab conflicts, and gateway nested-link
prevention. Existing Set/media interaction tests cover shared modal regressions.

Run the appropriate targeted JS/CSS/PHP checks and local storage/HTTP harness.
Use disposable fixtures: the storage harness must not run on production. Prove
pre-existing content/meta remain unchanged. DOM stubs cannot establish TinyMCE
selection, popup, clipboard or actual preview layout behaviour; use real Chrome
and Firefox checks, keyboard navigation, and representative desktop/390px views.
Check actual frontend template output, single H1, legacy fallback, dynamic slots,
no horizontal overflow, and formatting after native save and revision restore.

## Worktree and delivery

Code/document changes are in `/home/vladimir/wp-website`. The local WordPress
container now mounts its plugins/themes through an explicit machine-local
Compose override; core, uploads and the database retain their original mounts.
The preserved mixed archive checkout `/home/vladimir/wp` was not edited or synced.

The current homepage is post 12257; its effective front-page template comes from
the theme, with no active database override. The admin author's private autosave
27454 contains the 12-section version-3 pilot, including an editable opening and
a non-linked image/text row. Existing canonical content, enabled metadata,
deleted sections, dynamic slots and approved media references are preserved.
A verified local database backup and the clean draft JSON are retained outside
Git; see the current handoff for the local runtime checkpoint.

Code delivery needs no database migration or uploads artifact. Moving or
publishing the composition is a separate editorial action and requires its own
content/media mapping. Deploy registry, reader, sanitizer and renderer together
before accepting version-3 content; keep version-3 reading support on UI rollback.
The user authorized GitHub delivery of the website branch after reviewing the
local workflow. Deployment and publication remain separate actions; neither was
performed for this pilot.

## Local verification and staff review

The audit follow-up adds registry-owned layout diagrams/native radio choices,
**Seitenauftakt** with visible requirements, named theme colours, retained custom
colours under **Eigene Farbe**, and contrast guidance. Existing hex choices are
preserved exactly. Preview processing reuses one validated snapshot and reports
stale frames immediately. Lead/body have separate rendering hooks. See the
[implemented contracts](../architecture/editorial-platform.md#landing-rich-text-and-live-preview).

Chrome follow-up: named colour → custom hex → Undo → reset verified with actual
TinyMCE and rendered preview. Clearing the opening title retained the previous
valid preview and displayed the correction. Layout choices preserve focus/text;
800px and 390px editor windows and the 390px preview fit without horizontal
overflow. Temporary browser edits were removed; the private composition matches
its baseline except for its intended v3/explicit-opening upgrade. Canonical
homepage content, metadata and effective template remain unchanged.

- Storage/HTTP harness: 227 checks, including 86 existing documents; existing
  content/meta unchanged and disposable fixtures removed. Covers versioned rich
  text, unsafe markup, plain legacy text, native draft/revision round trips,
  authenticated embedded preview, stale tokens and anonymous access. The audit
  follow-up covers named colours, v3 native save/revisions, explicit opening
  validation, one validation across 14 preview consumers and snapshot consistency
  across a concurrent save; ordinary API reads remain fresh.
- Shared editor: 20 DOM tests and 2 Set/upload tests pass. Targeted ESLint,
  Stylelint, PHPCS, PHPStan, PHP syntax and whitespace checks pass.
- Chrome in the user's admin session: selected-text and cursor-only links,
  internal search, changing link destinations, custom colour replacement,
  separate colour/highlight resets, undo and nested-dialog Escape. Links and
  colours also survive editing and rendering in a short image/text description.
- Actual theme preview: one H1, 12 source-section markers, dynamic slots and
  disabled newsletter submission; desktop, tablet and phone layouts. At a 390px
  preview width and an 800px editor window there is no horizontal overflow.
  Incomplete links and stale saves retain and label the previous valid preview.
- Reopening initially exposed an old usability defect: the recovery prompt was
  below the disabled section canvas, making it appear frozen. The prompt now
  precedes the canvas with **Entwurf weiterbearbeiten** / **Entwurf verwerfen**;
  inactive controls stay hidden until a choice is made. Fresh-load recovery,
  clicking into an edit field, closing and reopening were verified in Chrome.
- Section switching: editor-to-preview navigation, preview clicks opening the
  matching controls, current-section outline and narrow-screen tab switching
  verified in Chrome. Regression tests cover pending edits, selective discard,
  keyboard selection and messages from stale/unrelated frames. Selection styles
  remain absent from ordinary page previews and public pages.
- Before the audit follow-up, two title edits reached the visible preview in 1.94s and 1.85s after
  editing, measured through the browser on this local stack. This meets the
  two-second pilot target in those samples, not a general latency guarantee.
- Staff still need to perform the acceptance task above without a lesson.
  Firefox and real clipboard/paste interaction remain unverified; sanitizer
  coverage is not a substitute for those browser checks.

API references checked during planning:
[WordPress editor initialization](https://developer.wordpress.org/reference/functions/wp_enqueue_editor/),
[internal-link queries](https://developer.wordpress.org/reference/classes/_wp_editors/wp_link_query/),
and [stylesheet output](https://developer.wordpress.org/reference/functions/wp_add_inline_style/).
