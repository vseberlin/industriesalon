# Shared editorial workspace

Accepted implementation scope, updated 2026-09-22. Based on the supplied
`editor-workspace-sow.md`, with the review corrections below. Staff and volunteer
editors are the first audience. The existing private front-page composition is
the pilot; publication is a separate editorial action.

## Ownership and reuse

`iss-editorial` owns the workspace shell, existing section controls, compact
WordPress TinyMCE configuration, draft/recovery flow and authenticated preview
bridge. `iss-content` owns section/treatment metadata. The theme owns all public
markup and the exact preview-only field addresses in each renderer.

The workspace is a layout adapter around `admin.js`, not a second document state,
renderer or save mechanism. It reuses the native autosave revision and existing
AJAX endpoint. All nine formats use this workspace; the previous section modal
and alternate preview layout are removed. No framework, build step or additional
save store is introduced. The general `article` format uses the engine's standard
per-format metadata contract. Existing v1 documents keep an explicit upgrade;
new documents use the existing v3 schema. See the
[consolidation audit](editorial-consolidation-audit.md) for coverage and checks.

## Delivery

- Persistent section outline, actual theme preview, and inspector with the same
  content, layout, links, media, facts, items and archive controls.
- Compact registered section icons/groups/tones and the existing shared SVG
  treatment schematics. Searchable insert palette, gap insertion, keyboard and
  pointer reordering, recoverable section trash.
- Workspace opens expanded to the viewport with outer scrolling locked. One top
  bar holds skin, device width, separate draft/preview status and native publishing
  navigation. Pane switches appear only where panes cannot remain together.
  Collapsing or leaving restores native WordPress controls and keyboard access;
  the publishing navigation never submits the post by itself.
- Inspector tabs have one level of headings, section position and type, and an
  automatically growing title field. Outline titles take at most two lines;
  untitled automatic sections use their registered treatment label. Treatment
  schematics include project notes, timeline, visit information and newsletter.
- Opening guidance belongs only to the selected opening section, including an
  invalid or misplaced opening. Preview selection stays solid during hover,
  has a type/treatment label and scrolls clear of the measured site header.
  Quiet section removal offers undo through the existing recoverable trash.
- In-place title and kicker editing, plus body and lead using the same compact
  rich-text engine as the inspector. Visible edit buttons and Enter accompany
  double-click. Cursor-only links, named theme colours and exact custom hex
  colours are retained. The form path remains a first-class editing choice.
- Generated content stays read-only in the preview. Individual gateway/item
  fields remain in the inspector. Public renderers retain their distinct content
  composition; generated slots, route data and source summaries are not made into
  editable prose. Empty documents offer registry-owned starter compositions.

## Editor styling

The warm neutral panes, white fields, deep red actions, serif title input and
dark canvas text toolbar adapt the supplied `admin-css.zip` to the current UI.
`editor-tokens.css` owns the existing `--iss-editor-*` contract; `workspace.css`
owns panes and inspector layout, `text-controls.css` owns shared TinyMCE/colour
controls and native link-dialog styling, and `preview-frame.css` owns canvas
affordances. Shared controls in `admin.css` consume those tokens. There is no
additional override stylesheet or change to public content colours.

Native media/link dialog adapters use the workspace body class, removed on
workspace destruction. The only new `!important` declarations are three
user-approved disabled-button colour overrides: WordPress core marks those
properties important. Layout, selection, focus and responsive rules use normal
cascade ownership. Status colour follows each status element's own state;
secondary text remains readable and the white colour picker stays independent
of the dark toolbar. Title input and sizing mirror share their typography.

Collection rows stack inside the inspector. At short window heights the whole
inspector scrolls; phone layouts retain their existing pane switches. Canvas
section controls sit above the fixed site header without changing public layout
or replacing the measured header clearance used for section navigation.

## Corrections to the supplied proposal

Unfinished input must remain recoverable. Input events update the parent document
and schedule its existing autosave before blur. Server validation gates preview
and publication, not recovery of incomplete drafts. A valid preview stays visible
when later input is incomplete or saving fails.

Do not replace a frame during an active edit. After completion the server-rendered
preview reconciles the actual document, including conditional headings/layout.
Escape restores only the active field's starting value and saves that restoration.
The frame keeps its previous valid rendered content until reconciliation.

The bridge checks origin, frame source, snapshot token, explicit field allowlist,
edit session and increasing sequence. Snapshot indices map to in-memory section
objects, never to a newly reordered index. A stale field cannot start an edit;
structural actions wait for the active field to finish. No IDs are added to stored
sections. Parent form controls for the selected section are inert only during a
canvas edit; the pane navigation and finish path remain available.

The workspace requires JavaScript. With JavaScript disabled, a truthful notice
explains this and stored content remains untouched. Preview failure must not
disable the inspector. Missing workspace assets report an error; they do not
open a second authoring system.

## Acceptance

An editor should add a section, choose an image, and change title/text in under
five minutes without help. Choosing the inspector is a successful workflow.
Check links with no selected text, named/custom colours, pending typing across
save/reload, Escape, invalid opening drafts, reordered/stale frames, insertion,
trash restore, keyboard navigation, and narrow screens. Chrome implementation
checks do not replace staff UAT, Firefox or real clipboard testing.

## Data artifacts

No SQL migration or uploads artifact is required for the workspace code. Existing
v3 storage and renderer support from the audit checkpoint remain prerequisites.
The local private composition is not a content deployment artifact. Canonical
homepage content, template authority and media must remain unchanged by tests.
