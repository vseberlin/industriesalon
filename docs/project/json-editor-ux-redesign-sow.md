# JSON Editor UX Redesign SOW

Date: 2026-07-01

## Purpose

Redesign the editorial JSON editor as a clean, failsafe authoring surface after
UAT without changing the storage contract or public rendering authority.

The goal is a focused editor that feels like a purpose-built composition tool:
drag to order, drag to add, edit one section in a structured modal, and keep
technical JSON details out of the normal editor workflow.

## Locked Decisions

- The JSON document storage shape does not change in this pass.
- `iss-content` continues to register editorial formats and field contracts.
- `iss-editorial` owns editor mechanics, autosave, validation, shared drag/drop,
  and editor UI primitives.
- The theme owns public rendering, layout CSS, and frontend treatments.
- No global WordPress admin reskin is introduced.
- No field names, registry slugs, or storage keys leak into the author-facing
  modal labels unless they are deliberate editorial vocabulary.
- Drag/drop behavior is shared through a small helper instead of repeated
  one-off implementations.
- Preview must read the same draft ordering that editors see before clicking
  Update.

## Module Boundary

The editor should move toward these modules:

- `assets/admin.js`: document state, autosave, section rendering orchestration,
  and temporary adapters for current picker-heavy flows.
- `assets/dnd.js`: shared drag/drop and keyboard reordering for section canvases
  and later nested gesture editors.
- `assets/ui.js`: modal shell, panel primitives, and editor-only UI structure.
- Future picker module: archive object, media, set media, page link, and album
  picker adapters.

`admin.js` is allowed to remain the orchestrator during the redesign, but new
visual structure should not be hard-coded there when a small UI primitive is
enough.

## Modal UX

Each section opens in one structured modal with named panels:

- `Inhalt`: kicker, title, anchor, body, quote text.
- `Darstellung`: treatment, slot, orientation, gallery/media layout, rail and
  section display options.
- `Archiv`: archive object references.
- `Bilder`: images, files, and media captions.
- `Album`: album source and sheet sequence.
- `Fakten`: fact rows and microblocks.
- `Ziele`: gateway/card rows.
- `Links`: action links.

Panels may be hidden when the section type does not support the field family.
The modal footer may expose destructive section removal, but deletion must stay
explicit and reversible by normal editor update discipline.

## Failsafe Rules

- Empty or unsupported field families must not render empty panels.
- Existing section preview cards remain the primary scanning view.
- Modal edits must continue to autosave draft JSON and update the canvas.
- Section delete from the modal must close the modal and re-render the canvas.
- Browser behavior must be verified in Chromium and Firefox when drag/drop or
  modal interaction changes.

## Follow-Up

After this redesign, split picker-heavy adapters out of `admin.js` so the main
orchestrator stops growing. The picker extraction should preserve the current
REST/media contracts and should not change storage.
