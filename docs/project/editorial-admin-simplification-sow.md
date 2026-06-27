# Editorial Admin Simplification SOW

This SOW defines how to simplify ISS editorial edit screens without painting the
whole WordPress admin. The goal is one coherent editor workflow across public
content types while preserving the real storage and render owners.

## Goal

Build one shared ISS editorial admin layer for content editing screens. Editors
should see a consistent workflow whether they edit Ausstellung, Projekt,
Veranstaltung, Publication, Fuehrung, Rueckblick, Video, page, or post.

The result should reduce each edit screen to the minimum required decisions:

- identity and publish state;
- primary editorial composition;
- facts required by the public renderer or programme projections;
- media and archive references;
- people, organization, place, and related-content curation;
- status/warnings and clear next actions.

## Problem

The current admin direction is partly unified and partly fragmented:

- `iss-editorial` now owns a shared JSON composition canvas for several content
  types and disables Gutenberg/default editor support for registered editorial
  formats.
- `iss-content` still owns separate CPT meta boxes for dates, types, status,
  and older Veranstaltung structure.
- `iss-relations` exposes a standalone `Verknuepfte Orte` metabox.
- `iss-graph` exposes people/organization relations, related-content signals,
  search signals, and availability signals in separate boxes.
- `iss-archive` exposes a standalone `Archivauswahl` metabox and separate
  Archivset workbench.
- Gutenberg blocks and document panels still exist for some CPTs while other
  CPTs are now JSON-first.

This makes editors move between custom JSON tools, default WordPress tables,
custom metaboxes, Gutenberg panels, and plugin workbenches. The same ideas
appear multiple times with different controls and visual language.

## Non-Goals

- Do not globally reskin WordPress admin menus, list tables, buttons, inputs, or
  notices.
- Do not move storage ownership into a new catch-all plugin.
- Do not remove data paths only because the UI is confusing.
- Do not turn diagnostic/admin-only controls into normal editor controls.
- Do not replace WordPress list tables where a standardized column/filter layer
  is enough.

## Ownership

- `iss-editorial` should own the shared composition canvas, editor shell,
  section controls, reference trays, save/autosave behavior, and reusable admin
  UI primitives for editorial documents.
- `iss-content` should own CPT registration, required facts, entity/type
  contracts, programme/overview flags, and editor dashboard assembly.
- `iss-relations` should own place relation storage and read models, but normal
  editors should use integrated place selectors inside the shared dashboard
  wherever a CPT has a primary place or route/station workflow.
- `iss-graph` should own graph entity storage, people/organization relations,
  and editorial signals, but the normal editor UI should expose them through one
  shared "Relations and visibility" dashboard panel.
- `iss-archive` should own archive objects and Archivsets, but content edit
  screens should use shared reference pickers/trays rather than a separate
  generic metabox for every CPT.
- The theme owns public templates, skins, and frontend render interpretation.

## Editorial Contract

Every supported ISS edit screen should follow the same editorial order:

1. Identity
2. Editorial composition
3. Required facts
4. Relations and references
5. Publish

This order is part of the editorial platform contract. New CPTs should adopt
the shared order instead of inventing custom editor layouts.

The order does not mean every CPT has every panel. It means that when a concept
exists, it appears in the same relative place and uses the same visual language.

## Dashboard Assembly

Editorial screens should be assembled from reusable dashboard contributions, not
independent metabox layouts.

Owning plugins contribute:

- storage and save paths;
- validation rules;
- labels and capability requirements;
- data providers and API endpoints;
- specialized controls when a generic field is not enough.

The shared editorial dashboard determines:

- section order;
- grouping;
- visual hierarchy;
- navigation;
- progressive disclosure;
- default editor versus advanced/technical visibility.

Plugins may own a field or picker implementation, but they should not decide the
final screen layout when the control appears inside a supported ISS editorial
screen.

## Concept Authority

Every editorial concept should have one normal editing authority. Duplicate
editing controls are legacy until proven otherwise.

| Concept | Normal editing authority |
| --- | --- |
| Identity, title, slug, featured image | Identity panel and WordPress publish state |
| Dates, type, programme and overview flags | Facts panel from the CPT owner |
| Editorial body and section order | Composition canvas |
| Places and route/station relations | Relations panel backed by `iss-relations` |
| People and organizations | Relations panel backed by `iss-graph` |
| Archive material and object references | References panel backed by `iss-archive` |
| Sets and promoted media | References panel backed by `iss-content` Sets |
| Related-content decisions | Relations/visibility panel backed by `iss-graph` or `iss-relations` |

The implementation must avoid:

- duplicated editing controls for the same concept;
- duplicated save paths for the same concept;
- duplicated validation logic for the same concept;
- hidden editor-only alternatives that bypass the owning storage contract.

Compatibility shims can exist during migration, but normal editors should see
one clear control for each concept.

## Classification Model

Every current field, block, panel, and metabox must be assigned one state:

| State | Meaning | Action |
| --- | --- | --- |
| Must show | Required for correct public output, programme projection, or publishing. | Keep visible in the primary dashboard. |
| Integrated | Useful editor action, but should not live as a separate metabox. | Move into shared dashboard/picker UI while keeping current storage. |
| Hide for editors | Useful for admins, migration, diagnostics, or rare recovery only. | Hide for non-admin editors; keep accessible to technical roles. |
| Migrate | Valuable data or workflow, but current storage/UI is the wrong owner. | Write migration and compatibility plan before hiding old UI. |
| Purge | Legacy UI/data with no live render, no migration need, and no admin value. | Remove after usage audit and rollback note. |

No surface should be purged until the audit proves:

- it is not read by the public renderer, list-table filters, sync jobs, CLI
  checks, import/export artifacts, or search/graph projection;
- current rows are empty or migrated;
- there is a replacement path for future editing if the concept remains useful.

## Current Surface Audit

### Shared editorial composition

`iss-editorial` is already the preferred direction for JSON-first narrative
composition. It renders a main canvas after the title, stores
`_iss_editorial_{format}`, supports skins/features, media refs, object refs,
links, facts, and Set-first media picking.

Decision: **must show** for CPTs where a format exists. It should become the
single primary editor canvas for Ausstellung, Projekt, Publication, Fuehrung,
Rueckblick, and any future JSON-first CPT.

Needed cleanup:

- fold common side controls into the same visual dashboard;
- standardize status chips and save language;
- keep old document-compatible section types valid but hidden where canonical
  gestures exist;
- prevent duplicate standalone relation/archive boxes when the same action is
  integrated into the canvas.

### Veranstaltung structure

Veranstaltung currently has its own `iss-content` Struktur editor using
`_iss_content_json`, plus separate Basis, Typ & Ausgabe, and Redaktionsstatus
boxes.

Decision: **migrate/integrate**. The storage can remain `_iss_content_json` for
compatibility, but the UI should converge with the `iss-editorial` dashboard
model or be wrapped by the same shared admin primitives.

Must show:

- title, publish/status;
- Veranstaltung type/entity;
- required date/place facts;
- programme visibility;
- structured content sections;
- featured image/gallery status.

Hide or integrate:

- generic category/tag/page-parent/custom-fields boxes for normal editors;
- duplicated relation boxes when primary place is already controlled in Basis.

### Ausstellung

Ausstellung currently mixes Gutenberg/default editor availability, an
Ausstellungsdaten side box or ACF fallback, taxonomy state, timeline panel, and
separate people/organization, place, archive, and related signal boxes.

Decision: **convert to dashboard-first**.

Must show:

- title, publish/status;
- exhibition type;
- start/end dates;
- overview/programme visibility;
- featured image/excerpt where public cards need them;
- composition canvas when `_iss_editorial_ausstellung` is enabled or being
  authored.

Integrate:

- people and organizations;
- primary/related places;
- archive object and Archivset references;
- related-content promotion where editors need it.

Hide for editors:

- raw taxonomy boxes whose values are controlled by better UI;
- custom-fields, slug, revisions, page parent, and technical boxes unless a
  technical role opens advanced mode;
- availability/search signals until their editor value is proven and named in
  plain editorial language.

Audit before purge:

- legacy Gutenberg body content and blocks on older exhibitions;
- ACF group authority versus `iss-content` fallback;
- `ausstellung_typ` taxonomy rows and overview/programme meta usage.

### Projekt

Projekt currently has the shared `iss-editorial` canvas, project facts/date box,
Set-first media picker, archive picker, people/organization relations,
place relations, automatic related preview, and related-place/public context
rendering.

Decision: **make Projekt the reference slice** for the shared dashboard if the
next implementation starts with a JSON-first CPT.

Must show:

- title, publish/status;
- editorial composition canvas;
- project date/period label;
- programme visibility;
- homepage order only if it still drives a real public placement;
- featured image/excerpt/card status;
- Set/media/material controls used by `galerie` and `material`.

Integrate:

- people and organizations;
- related places;
- archive references and Archivsets;
- related-content promotion or preview.

Hide for editors:

- raw category/tag/page-parent/custom-fields boxes;
- homepage order if a list-table or dashboard ordering control replaces it;
- automatic preview controls unless they support an explicit editor decision.

Audit before purge:

- legacy Gutenberg body and manual related blocks on migrated projects;
- old project skin/rail section compatibility;
- whether `menu_order` is still the right homepage-order authority.

### Publication

Publication has editorial JSON for photoalbums/longreads/timelines plus older
publication-specific bibliography/display/sale boxes and archive/media source
workflows.

Decision: **dashboard-first, with commerce/sale controls separated**.

Must show:

- title, publish/status;
- publication editorial canvas where enabled;
- publication type/layout;
- source/bibliography facts that render publicly;
- cover/featured image;
- sale/order controls only on sale-enabled publications.

Integrate:

- photoalbum source import from Archivset/Set;
- people/organizations;
- related content;
- archive references.

Hide for editors:

- sale controls on non-sale publications;
- technical display switches that can be derived from layout/type;
- raw archive/source metaboxes once the shared reference tray covers them.

Audit before purge:

- old brochure template metadata;
- longread/timeline source blocks;
- DB artifacts that migrate publication JSON.

### Fuehrung

Fuehrung already demonstrates the right pattern: the JSON narrative layer is in
`iss-editorial`, while route stations edit existing `iss_related_places` rows
from inside the integrated canvas, and the older place metabox is hidden for
that route workflow.

Decision: **use as integration precedent**.

Keep:

- route/date/booking facts on their existing owners;
- integrated route/station editor;
- public related and Atlas contracts.

Do not duplicate:

- standalone `Verknuepfte Orte` route editing when the route editor is active.

### Rueckblick

Rueckblick is a first-class public editorial node with Sets/media workflow and a
JSON format.

Decision: **dashboard-first**.

Must show:

- title, publish/status;
- editorial composition;
- attached source Veranstaltung/project/exhibition relations if present;
- Set/media promotion state;
- featured image/excerpt/card status.

Integrate:

- people/organizations;
- places;
- archive candidates/references.

### Video

Video currently remains metadata-heavy and Gutenberg-capable, with a custom
Videodaten box, video-library blocks, transcript status/source, graph content
relations, and related/search signals.

Decision: **separate media metadata from editorial composition**.

Must show:

- video URL/source;
- source family, source label/source URL;
- year/original date/duration/language/rights;
- transcript status/source;
- featured flag only if it still drives a real public surface.

Integrate:

- people/organization relations from transcript/content review;
- places where videos are used as place context;
- related-content decisions.

Hide for editors:

- raw search/boost controls unless renamed into a plain editorial visibility
  panel.

## Cross-CPT Rules

### Shared editor structure

The supported CPTs should reuse the same editor shell and component vocabulary:

- status strip;
- identity panel;
- composition canvas;
- facts panel;
- reference tray;
- relations panel;
- validation panel;
- publish panel.

New CPT-specific components should be promoted into the shared dashboard library
when a second CPT needs the same interaction.

### List tables

Keep WordPress list tables as the shell, but standardize ISS columns and
filters:

- type/status chips;
- structured-content enabled/valid indicator;
- missing featured image/excerpt indicator;
- date/period where relevant;
- attached Sets/media status;
- relation/reference count;
- direct "continue editing" action into the shared dashboard.

Do not replace list tables with custom pages unless batch review requires a
workbench, as Sets already does.

### Relations and references

Editors should not choose between "related places", "persons", "organizations",
"archive selection", "manual related content", and "automatic preview" as
unrelated boxes.

Target model:

- one dashboard panel named around editorial intent, such as "Beziehungen und
  Verweise";
- subareas for Orte, Personen, Organisationen, Archivmaterial, Sets/Medien, and
  Verwandte Inhalte;
- each subarea writes to the current owning plugin/storage;
- technical source terms such as graph, boost, relation weight, source system,
  and projection stay out of normal editor labels.

### Advanced mode

Add one advanced/technical mode for administrators and technical maintainers.
It may reveal:

- raw WordPress custom fields;
- raw taxonomy boxes not part of the curated editor;
- diagnostic graph/search/availability controls;
- old compatibility metaboxes during migration windows;
- schema/source/debug summaries.

Normal editors should not need Screen Options to find the right workflow.

Visibility levels should be explicit:

- Editorial: the default daily editing workflow.
- Curator: deeper curation and review actions.
- Manager: publishing, ordering, promotion, and operational decisions.
- Technical: diagnostics, compatibility boxes, raw fields, and repair tools.

Technical controls should never appear in the default editorial workflow.

## Implementation Plan

1. Inventory and classify current surfaces.
   - Generate a CPT-by-CPT matrix of metaboxes, Gutenberg panels, blocks,
     list-table columns, registered post meta, taxonomies, and save paths.
   - Mark every surface with one state from the Classification Model.

2. Build the shared dashboard shell.
   - Status strip.
   - Navigation.
   - Panel framework.
   - Reference tray.
   - Validation area.
   - Progressive disclosure.

3. Define the shared dashboard contract.
   - Shared shell, section panel, status strip, field row, reference tray,
     picker modal, empty state, warning, and save/autosave patterns.
   - Reuse existing `iss-editorial` CSS/JS where possible rather than adding a
     parallel admin skin.
   - Define how owning plugins register dashboard contributions without owning
     final layout/order.

4. Convert one reference CPT.
   - Preferred first slice: Projekt, because it already uses `iss-editorial`
     and carries the full problem set: facts, Sets/media, archive refs, places,
     people/organizations, related content, and list-table order.
   - Alternative first slice: Ausstellung, if the main goal is to resolve the
     Gutenberg/custom-metabox split first.

5. Hide duplicates for normal editors.
   - Hide only after integrated controls save to the same storage and a browser
     edit/save/reload check proves parity.
   - Keep administrator advanced visibility during the migration window.

6. Standardize CPT list tables.
   - Add shared column/filter helpers where possible.
   - Do not duplicate the same status logic in each CPT.

7. Migrate or purge legacy surfaces.
   - For each purge candidate, record current row counts/usages and replacement
     path.
   - Create SQL artifacts only when DB state changes or transfer needs it.

## Migration Lifecycle

Every legacy editor surface follows the same lifecycle:

1. Experimental
2. Supported
3. Deprecated
4. Hidden
5. Removed

No feature moves directly from supported to removed. Hiding is allowed only
after the replacement control saves to the same authority and edit/save/reload
parity has been verified.

## Verification

For each converted CPT:

- load edit screen as administrator and as a normal editorial role;
- verify required boxes appear in the primary dashboard;
- verify hidden boxes are still available to advanced roles where intended;
- edit/save/reload title, status, required facts, composition, media refs,
  places, people/organizations, archive refs, and related decisions;
- verify one normal editing authority per concept;
- verify there are no duplicated default editor controls for the same concept;
- verify the dashboard order is consistent across supported CPTs;
- verify progressive disclosure reveals legacy/technical controls only to the
  intended roles;
- verify shared dashboard components are reused instead of CPT-specific visual
  frameworks;
- verify public frontend output and list-table indicators;
- run CSS/JS lint for touched assets;
- run PHP lint/PHPCS/PHPStan for touched save/render paths;
- check whether DB-backed state, uploads, or SQL artifacts are required before
  commit/deploy.

## First Decisions

- Do not ship a global `iss-admin.css` skin.
- Use `iss-editorial` and `iss-content` as the convergence point for owned
  editorial screens.
- Treat Fuehrung route integration as the successful precedent for hiding a
  duplicate standalone relation metabox.
- Treat Projekt or Ausstellung as the first reference conversion, not all CPTs
  at once.
- Keep WordPress list tables, but make their ISS status/filters coherent.

## Open Questions

- Should Veranstaltung storage remain `_iss_content_json`, or should the shared
  editor support a compatibility adapter under `iss-editorial`?
- Should Ausstellung continue to allow Gutenberg body editing once the JSON
  canvas is enabled, or should it become JSON-first like Projekt/Fuehrung?
- Which role should see advanced technical boxes by default:
  administrator only, or `iss_technical_maintainer` too?
- Is project homepage ordering still editor-facing, or should it move to a
  dashboard/list-table ordering workflow?
- Which graph signals are truly editorial controls, and which should be
  diagnostics only?
