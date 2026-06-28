# Changelog

This file records durable project changes. Keep it compact: current state belongs in
`handoff_CURRENT.md`, active follow-up in `TODO.md`, and detailed investigation can
be recovered from Git history.

## 2026-06-28

- Added a read-only `wp iss-content editor-ui-audit` first slice for the
  editorial admin simplification work. It inventories metaboxes, dashboard
  anchors, registered meta, taxonomies, list-table columns, save hooks, sampled
  blocks, and user-specific Screen Options by role/post type, classifying
  surfaces as `must_show`, `integrated`, `hide_for_editors`, or `review` before
  any editor UI is hidden or moved. The SOW now records raw custom fields, slug,
  revisions, page attributes, raw taxonomy boxes, and diagnostic graph/search
  boxes as shared wholesale simplification candidates rather than CPT-specific
  decisions, and `iss-content` now hides those wholesale metaboxes for
  non-admin editors across supported ISS content, relation, archive, register,
  and graph-backed edit screens while preserving administrator visibility.
  Projekt now acts as the reference integrated-relations slice: promotion stays
  directly visible, while places, people/organizations, archive material, and
  Sets/media open from compact dashboard actions into their existing owner
  controls without changing storage or save paths. The same Projekt slice now
  puts excerpt, featured image, and required project facts in the top dashboard
  row, renames project facts to "Pflichtangaben", relabels the section as
  "Verknüpfte Inhalte", and simplifies promotion wording to "Inhalt promoten".
  Projekt ordering now keeps native WordPress `menu_order` storage but removes
  the raw "Startseiten-Reihenfolge" weight from normal editors; editors reorder
  projects by drag/drop in the unfiltered Projekt list table, while
  administrators keep the raw field as a repair hatch. This extends the
  existing `iss-content` admin/list-table layer instead of adding a parallel
  ordering store. Projekt linked-content controls now collapse into a right-rail
  "Verknüpfte Inhalte" launcher with Orte, Akteure, Archive, and Media modal
  buttons, while related-content promotion moves into "Pflichtangaben" as a
  simple "Inhalt promoten" toggle for normal editors. The graph save path remains
  the authority; related self-promotion no longer requires fake reason/expiry
  metadata. The same compact linked-content pattern now covers Veranstaltung,
  Ausstellung, Publication, Fuehrung, and Rueckblick where the owner controls
  are available: normal editors get right-rail launchers, promotion moves into
  facts/identity as a simple toggle, Fuehrung keeps route/place relations inside
  the route editor, and Publication keeps its publication-specific related
  publications box in the main dashboard for a later content-specific decision.
  Veranstaltung, Ausstellung, Publication, and Fuehrung now match the Projekt
  top-row pattern: excerpt, featured image, and required facts appear before the
  composition canvas, and generic basis/data panels use the editor-facing
  "Pflichtangaben" label.
- Implemented the first `iss-graph` related-graph autonomy slice: relation
  provenance/status columns, relation backfill/dedupe/audit CLI commands, dirty
  queue and bounded reconcile CLI, autonomy health and fixture checks, soft
  editorial-signal removal plus export/import, no-frontend-repair guards,
  harvest-critical Veranstaltung venue warnings, and promotion list-table
  status/filter/disable actions. Local Docker verification backfilled 4,796
  relation rows and deprecated four duplicate manual edges so relation
  integrity health reports zero missing provenance, orphan edges, and active
  duplicate edge keys.
- Expanded `docs/project/related-graph-autonomy-sow.md` with the editor-facing
  contract, list-table promotion workflow, audit/reversibility expectations,
  dirty-queue/cron autonomy gate, observability/health requirements, scheduler
  independence, harvest-critical validation, relation provenance/status
  guardrails, dry-run/rollback expectations, fixture coverage, performance
  limits, acceptance drills, and rebuild/recovery criteria for derived relation
  state.
- Added `docs/project/related-graph-autonomy-sow.md` to record the relatedness
  direction: public related places/content must be autonomously derivable
  without admin presence, manual relations are correction/curation rather than
  backbone data, and visible content promotion remains a separate
  non-graph-specific editorial signal.
- Made related-content resolution graph-first for the default current-post path
  and loosened the `iss-relations` relation payload contract from place-only
  `place_id` rows to generic entity targets while preserving legacy place rows.
  `iss-content` now persists Veranstaltung `iss_primary_place_id` as native
  meta, and `iss-graph` harvests it as a `content_native` venue edge so the
  related-places editor box is no longer the only source of relatedness.
- Added a Video CPT transcript JSON contract in `iss-content`: editors can clean
  timecoded segments in a dedicated metabox, save through the normal WordPress
  Update action, and public video blocks render `_iss_video_transcript_json`
  when present with legacy `post_content` fallback. The Video Gutenberg body
  canvas is hidden after migration so transcript edits happen in the JSON
  segment editor, and existing video transcript transfer state is captured as an
  explicit SQL artifact instead of a hidden runtime migration.

## 2026-06-27

- Unified editorial composition save behavior so JSON-first composition changes
  are committed through the normal WordPress Update action instead of a second
  in-canvas JSON save button, and aligned the Veranstaltung structure editor with
  the shared left-rail gesture layout.
- Implemented the shared editorial admin dashboard shell for compatible
  classic/editorial edit screens:
  - added reusable dashboard section registration and DOM assembly in
    `iss-content` without moving storage or save ownership;
  - converted `veranstaltung`, `projekt`, `ausstellung`, `publication`,
    `fuehrung`, and `rueckblick` to the shared section order for identity,
    composition, facts, relations/references, sale/publish where applicable;
  - kept `iss-editorial`, `iss-publications`, Führung meta, Sets,
    `iss-relations`, `iss-graph`, and `iss-archive` as the actual control/save
    owners;
  - explicitly left Gutenberg screens `video`, `page`, and `post` out of the
    classic dashboard mover pending a separate block-editor adapter.
- Added `docs/project/editorial-admin-simplification-sow.md` to define the
  shared ISS editorial admin direction: no global admin reskin, classify each
  CPT field/block/metabox as must-show, integrated, hidden, migrated, or purged,
  and converge JSON composition, facts, relations, media/Sets, archive refs, and
  list-table status into one dashboard model with one editing authority per
  concept.
- Completed the hard editorial vocabulary cleanup after the compatibility pass:
  - migrated local stored editorial JSON to canonical gestures, skins, and
    `features.rail`;
  - added rollback and deploy SQL artifacts at
    `ops/sql/2026-06-27-editorial-vocabulary-pre-migration.sql` and
    `ops/sql/2026-06-27-editorial-vocabulary-normalized-json.sql`;
  - removed hidden legacy section definitions, legacy skin aliases, legacy
    renderer branches, and the one-off `normalize-vocabulary` CLI command;
  - renamed Ausstellung skin CSS assets to `quellenbuehne` and `objektalbum`,
    removed the `industrieakte` stylesheet, and collapsed Veranstaltung skin
    defaults to canonical `typografisch`, `buehne`, and `chronik`;
  - left Führungen out of this cleanup pass per the architecture boundary.
- Started the shared gesture/skin/feature implementation from the audit answer:
  - added an `iss-content` editorial vocabulary helper for canonical gestures,
    canonical skins, skin aliases, and rail feature defaults;
  - kept public presentation in the theme by resolving canonical skin classes
    and legacy CSS aliases there instead of moving render logic into the
    plugin;
  - made `bildmatrix`, `quellenbuehne`, and `objektalbum` valid canonical skin
    slugs while preserving existing `blueprint-matrix`, `frauen-im-werk`, and
    `kinder-im-werk` render paths;
  - added project rail feature classes and resolver defaults while preserving
    existing `projekt_rail` documents as the current on/off source;
  - added `leitfrage` and `schluss` to Veranstaltung gesture palettes where
    the existing event structure can already render them.
- Added the first document-level feature slice for project rails:
  - extended `iss-editorial` documents with sanitized `features.rail` metadata;
  - added a compact project editor control for rail on/off, placement, and
    visual treatment;
  - made project rendering resolve rail from `features.rail` while preserving
    existing `projekt_rail` sections as the legacy default-on signal;
  - supported top/horizontal, side, and bottom rail placements without treating
    the rail as authored content.
- Hid legacy feature-only sections from editorial composition:
  - added a `ui_hidden` format-section flag to `iss-editorial`;
  - marked `projekt_rail` hidden in the project format so editors use the rail
    feature control instead of adding a fake navigation gesture;
  - kept hidden sections valid in stored documents for backwards-compatible
    rail fallback.
- Consolidated gallery/image gestures:
  - added a `gallery_layout` section field with `grid`, `sequence`, and `wall`
    values;
  - added canonical `galerie` support to exhibitions and marked legacy
    `bildstrecke` / `image_wall` sections hidden where replacement authoring is
    ready;
  - made project and exhibition renderers treat `galerie` with `wall` layout as
    the existing image-wall treatment while keeping old stored section types
    valid.
- Consolidated exhibition quote/source gestures:
  - marked legacy `quellenauszug` hidden while keeping stored sections valid;
  - added `quote_treatment` to canonical `zitat` sections so editors can choose
    normal quote or source-focused excerpt treatment;
  - made source-treated `zitat` sections reuse the existing source-focus
    exhibition layouts, including media orientation.
- Consolidated exhibition aside gestures:
  - marked legacy `aside` hidden while keeping stored sections valid;
  - added `section_treatment` to canonical `kapitel` sections so editors can
    choose the existing Ausstellungsentscheidung layout without changing
    gesture type;
  - kept the old `layout-aside` theme treatment as the rendering target.
- Consolidated Rückblick registry gestures:
  - added canonical `fliesstext`, `galerie`, `objektfokus`, `material`, and
    `schluss` sections;
  - marked legacy `bericht`, `bildstrecke`, and `quellen` hidden while keeping
    them valid for imported or old documents;
  - made editorial Set promotion prefer canonical `galerie` before legacy
    `bildstrecke`.
- Consolidated skin picker vocabularies:
  - kept legacy skins renderable for stored documents;
  - removed alias-only skin choices from Ausstellung, Projekt, and publication
    editor pickers so new authoring uses `quellenbuehne`, `objektalbum`,
    `dossier`, `typografisch`, and `bildmatrix`.
- Added `wp iss-editorial normalize-vocabulary` as a dry-run-first maintenance
  command for stored editorial JSON, with `--write` required to persist legacy
  gesture and skin rewrites.
- Added a registry diagnostics and backfill maintenance slice:
  - added `wp iss status` in `iss-core` to report first-party plugin loading,
    key CPT registration, schema/backfill option versions, graph/occurrence/Set
    tables, Event Drop storage isolation, and expected theme render helpers;
  - added `wp iss backfill-all` as an explicit operator command for graph,
    relations, WP occurrence-source, and editorial Set schema backfills, with
    `--dry-run` by default-friendly reporting and external sync excluded unless
    `--include-external` is passed;
  - made `iss-relations` consume its activation and graph-identifier backfill
    flags on admin requests instead of leaving callable backfill functions
    unhooked;
  - replaced silent theme render-helper includes with a tracked expected-helper
    list and an administrator notice when a required render helper is missing.
- Added the Führung JSON gesture/skin slice:
  - registered a `fuehrung` editorial format in the shared `iss-editorial`
    engine with tour gestures for intro, chapters, thesis/quote moments,
    galleries, image walls, material, and conclusion links;
  - kept booking, dates, route stations, facts, hero-gallery meta, Atlas map,
    and related discovery on the existing tour module/template contracts;
  - added a theme-owned tour renderer and template slot so enabled
    `_iss_editorial_fuehrung` documents replace only the narrative layer while
    unmigrated tours keep the legacy `post_content` description fallback.
- Migrated the existing local Führung narrative layer into JSON:
  - added `wp iss-editorial fuehrung-dry-run` and
    `wp iss-editorial fuehrung-import-candidate` for published Führung
    candidates;
  - imported and enabled `_iss_editorial_fuehrung` for all 15 published local
    Führung posts, skipping only template-owned infrastructure blocks such as
    tour calendar and related-content output;
  - created `ops/sql/2026-06-27-fuehrung-editorial-json.sql` as the narrow DB
    transfer artifact for the JSON documents and enabled flags; no uploads
    artifact is required because this migration does not introduce media refs.
- Integrated Führung route-station editing into the JSON composition editor:
  - added a `Route / Stationen` panel for Führungen that edits the existing
    `iss_related_places` station rows while leaving station storage in
    `iss-relations`, not in `_iss_editorial_fuehrung`;
  - hid the older `Verknüpfte Orte` metabox on Führung edit screens to avoid
    two competing station editors while keeping the shared metabox for other
    post types;
  - added a narrow `iss-relations` REST save/read route for post place
    relations because the existing related-preview endpoint is read-only for
    card previews and raw post-meta REST saves would skip the established
    station object/story back-link side effects; the route degrades to normal
    WordPress form submission through synced hidden `iss_relations` fields.
- Extended the publication JSON migration beyond photoalbums:
  - added `longread_chapter` and `timeline_item` gestures to the existing
    `publication` editorial format instead of adding new Gutenberg blocks or a
    second authoring path;
  - added a generic `year` field to the shared editorial editor/storage layer
    for dated timeline stations, with timeline images continuing to use the
    existing promoted `media_refs` contract;
  - made JSON-backed longreads and timelines produce the same payload shapes as
    the legacy publication blocks, so the existing theme-owned longread and
    timeline renderers remain the public UI owner;
  - kept legacy Gutenberg/block parsing as the fallback for unmigrated
    publications.
- Migrated the existing local publication longreads/timelines into the JSON
  gesture contract:
  - enabled `_iss_editorial_publication` for 12 existing posts, covering
    timelines `18864`, `18865`, `18873` and longreads `18878`, `18881`,
    `18886`, `21105`, `21109`, `21110`, `21111`, `21114`, `21125`;
  - converted dated chronik entries into `timeline_item` sections with `year`
    and promoted `media_refs`, and converted longread body sections into
    `longread_chapter` sections with anchors;
  - created `ops/sql/2026-06-27-publication-longread-timeline-json.sql` as the
    DB transfer artifact for the migrated JSON documents, enabled flags, and
    layout meta;
  - recorded that `18873` is currently an intro/rail timeline stub with no
    dated timeline stations in the local source content, and that no upload
    artifact was created because timeline images rely on existing Media Library
    attachment rows/files.
- Added the first theme-owned longread poster skin:
  - exposed `longread-poster` as a publication skin and assigned it locally to
    all nine migrated JSON longreads;
  - added a reusable `longread_quote` gesture and moved normal longread imagery
    onto `longread_chapter` through the shared `media_refs` contract, with a
    constrained inline or right-aside chapter image placement;
  - kept longread navigation generated from `longread_chapter` sections only,
    while quote moments render in document order between chapters;
  - styled the skin in the theme with a restrained typographic longread rhythm,
    pull quotes, chapter images, mobile fallbacks, and an empty-rail collapse
    path;
  - regenerated `ops/sql/2026-06-27-publication-longread-timeline-json.sql` so
    the transfer artifact now includes the `longread-poster` assignments for
    migrated longreads.

## 2026-06-26

- Added the first publication JSON migration slice for photoalbums:
  - registered a `publication` editorial JSON format with `intro`, `source`,
    optional `publication_rail`, and editable `photoalbum` gestures;
  - extended the shared editorial editor/storage layer with constrained
    photoalbum source/sheet controls and publication rail options instead of
    relying on Gutenberg image blocks or server-only album magic;
  - made photoalbums render from explicit JSON sheets first, while preserving
    legacy Gutenberg/Archivset fallback behavior for unmigrated publications;
  - allowed album sheets to be imported from an Archivset or editorial Set,
    then reordered, hidden, captioned, and given nav titles in the JSON editor;
  - made publication reading rails optional/skinnable through a
    `publication_rail` gesture while keeping legacy non-JSON rails automatic;
  - added a theme-owned `blueprint-matrix` publication skin for photoalbums,
    with a viewport-wide technical matrix, compact sheet cards, and per-sheet
    description drawers plus footer-style place and related-content context;
  - rendered the blueprint footer context through theme-owned relation markup
    and cached raw skin lookup, avoiding dynamic block-callback recursion and
    full editorial-document reads during frontend rendering;
  - seeded local `nef-album` with a 63-sheet JSON document and added
    `ops/sql/2026-06-26-nef-album-publication-json.sql` as the narrow transfer
    artifact for that DB-backed publication state and its Behrensbau place
    relation;
  - extended the same `blueprint-matrix` structure to the remaining local WF
    photoalbums: Labor/LKVO (`18894`, 52 sheets), Produkte LKVO (`18948`, 23
    sheets), and Produktion HF (`19038`, 34 sheets), using manual `WF-Museum`
    source metadata, resolved Media Library sheet refs, Behrensbau place
    relations, and
    `ops/sql/2026-06-26-photoalbum-blueprint-other-albums.sql` as the paired
    transfer artifact.
- Reduced `blueprint-matrix` photoalbum frontend render load after staging
  feedback: the theme now regenerates blueprint grid cell images from
  attachment/archive-object IDs as `medium` thumbnails while preserving
  full-size detail links, and removed the per-cell image filter that made
  scroll/paint work expensive on large albums.
- Normalized project media Set ownership:
  - project source Sets now use the canonical `project-set-<project-slug>` key
    when created or reopened;
  - the Event Drop attachment watcher now uses the same target resolver as raw
    incoming uploads, so project attachments with `projekt__<slug>` or a
    project parent land in the project source-material Set instead of creating
    a separate `event-drop-*` Set;
  - added an idempotent normalization helper for existing project-shaped
    duplicate Sets and used it locally to merge the Walk of Fame duplicate
    `event-drop-*` Set into `project-set-walk-of-fame-schoeneweide`.
- Added the first shared surface/color contract slice:
  - introduced readable surface tokens in `style.css` for foreground, muted
    text, subtle text, rules, heading text, and kicker text/accent;
  - kept existing `section--dark`, `iss-heading--light`, and
    `iss-kicker--light` as compatibility hooks while aligning them with the new
    contract;
  - moved project editorial section kicker/title/body text away from raw accent
    colors and onto surface tokens, including nested project media, download,
    facts, and archive-reference cards;
  - switched the obvious page-family heading overrides, archive featured-object
    cards, and timeline kickers so accent remains the rail/dot color while
    text uses the readable surface color;
  - aligned Veranstaltung skins with the same surface contract, including the
    dark festival skin, event meta panels, generic info panels, and related
    network/plugin bridge output;
  - extended the sweep to shared card families, project context sidebars,
    archive/publication rails, Kalender timeline labels, primitive meta labels,
    and Ausstellung editorial skins so small kicker/meta text uses readable
    surface text while accents remain markers, rails, and hover states;
  - fixed publication single surfaces across standard, longread, photoalbum,
    and timeline layouts, including dark photoalbum reading panels, longread
    chapter indexes, timeline year/range labels, related-card links, and file
    download buttons;
  - audited public plugin renderers: archive/content/graph/relations/video
    blocks mostly emit shared `.iss-kicker` markup for the theme to style; the
    remaining inline atlas-slice caption in `iss-relations` is a later cleanup.

## 2026-06-25

- Added the first `projekt` registry/gesture migration slice:
  - registered the `projekt` editorial format in `iss-content` with `kapitel`,
    `fliesstext`, `massstab`, `projekt_rail`, `galerie`, `image_wall`,
    `material`, and `schluss` gestures;
  - made `projekt_rail` an optional navigation gesture: when present, the theme
    appends it below the project meta panel and derives links from `kapitel`
    and `schluss` section anchors; when absent, short projects stay in a
    single-column flow;
  - moved JSON-backed project context out of manual Gutenberg rail markup: the
    theme now appends generated related places plus a compact related-content
    group below the meta/rail stack from the existing relations layer;
  - kept project media intake Set-first: Sets remain private growing
    collections, while promotion writes approved `media_refs` / `object_refs`
    into the project document only;
  - added a theme-owned project editorial renderer with legacy `post_content`
    fallback unless `_iss_editorial_enabled_projekt` is enabled per post;
  - added `wp iss-editorial projekt-dry-run` and
    `wp iss-editorial projekt-import-candidate` for disabled migration
    candidates;
  - created `ops/sql/2026-06-25-project-editorial-json-candidates.sql` for the
    seven current published project candidates, all with frontend rendering
    disabled.
- Added structured facts to the project `massstab` gesture:
  - extended the existing project editorial format with repeatable `value` /
    `label` fact rows so editors do not need inline `<strong>` markup in body
    text;
  - made the project renderer prefer structured facts while preserving optional
    body fallback text;
  - added a scoped `layout-key-points` project treatment in `single-content.css`.
- Added the first theme-owned project skin review pass:
  - exposed `brief`, `dossier`, `field`, and `standard` as allowed `projekt`
    skins through the existing editor-visible skin path;
  - kept the JSON gesture contract unchanged while allowing skin-specific theme
    markup for project navigation and `massstab` fact sections;
  - made `dossier` render project navigation as a horizontal chapter strip in
    the content flow, pair `kapitel` plus following `massstab` sections into a
    chapter/fact spread, center the dossier story flow in a 75rem section
    container, promote the horizontal rail into a dark primary CTA band, and
    move generated places/context into a footer grid;
  - made `brief` a compact project-sheet treatment without the heavy chapter
    rail, and `field` a darker spatial/index treatment that reuses generated
    context and related-place output instead of adding a map schema;
  - added `ops/sql/2026-06-25-project-skin-review-assignments.sql` for the
    three local review assignments: Walk of Fame as `brief`, Stadtlabor as
    `field`, and Futura as `dossier`.
- Strengthened project media/material handling:
  - made project `galerie` render through project-owned carousel markup while
    reusing the shared strip-carousel JavaScript hooks already used by
    Veranstaltungen;
  - gave project galleries a stronger dark section treatment and kept mobile as
    a horizontal strip rather than a long image stack;
  - allowed `material` sections in the editorial JSON UI to pick files and
    documents, not only images, and added a project file-card fallback for
    non-image media refs.
- Reconciled project media Sets with the admin workflow:
  - made the Sets Workbench context-aware so project-scoped Workbench views show
    only Sets attached to that project;
  - kept Sets reachable under Operations when the user has Operations access,
    with a Tools fallback when that parent menu is unavailable;
  - added project edit-screen actions to create the attached project Set, open
    it, or open it directly for upload;
  - added a Set-scoped raw upload endpoint and Workbench file picker so project
    files enter the private `external_upload` intake/review path before Media
    Library import;
  - changed default project Set promotion to split approved items by type:
    images/videos append to `galerie`, while PDFs/documents/archive objects
    append to `material`.
- Added project Set lifecycle rules:
  - new real `projekt` saves now ensure a source-material project Set unless
    the post is an autosave, revision, auto-draft, or trash;
  - deleting or trashing a project may quarantine disposable raw
    `external_upload` intake with decay, but it does not delete promoted,
    retained, archive-candidate, Media Library, or shared Set material;
  - project-only Sets are marked `stale` after disposable raw intake is
    quarantined, while Sets attached to other live contexts are left active.
- Extended public contribution intake to projects:
  - added a project `upload_intake` gesture that renders a theme-owned CTA to
    the existing `/event-drop/` interface instead of adding a parallel uploader;
  - routes project Event Drop targets with the preserved `projekt__<slug>`
    marker into the attached source-material project Set;
  - records project target provenance on incoming `external_upload` items and
    keeps public pages rendering only promoted editorial references.
- Added constrained rich-text editing for project chapter prose:
  - replaced the project `kapitel`, `fliesstext`, and `schluss` body textarea
    with a small custom editor for paragraphs, emphasis, links, and lists;
  - limited stored project body HTML to `p`, `br`, `strong`, `em`, `a[href]`,
    `ul`, `ol`, and `li` with server-side sanitization.
- Added the reusable `image_wall` / `Bilderwand` editorial gesture for
  Ausstellung JSON documents:
  - registered the gesture in the existing `iss-content` editorial format
    registry with `media_refs` only, instead of adding a parallel Gutenberg or
    shortcode path;
  - mapped it through the theme-owned Ausstellung renderer as
    `layout-image-wall`;
  - added a framed, uncropped image-wall treatment in
    `single-ausstellung.css`, with horizontal scrolling on narrow screens so
    dense media does not turn into a long vertical stack;
  - kept `iss/dense-image-wall` as the heavier asymmetric Gutenberg composer
    because its per-item layout controls are useful but outside the custom
    editor gesture model.

## 2026-06-24

- Added `docs/runbooks/event-drop-staging.md` for the current Sets-backed
  Event Drop staging setup: deploy from GitHub instead of untarring, create the
  writable `/event-drop-storage` mount tree, verify the mounted intake
  interface and `docker/php/uploads.ini`, keep upload secrets out of Git, and
  treat ZIP files as raw intake rather than a staging extraction step.
- Added `docs/project/operations-admin-permissions-sow.md` to define the
  code-owned Operations admin capability model before more custom admin screens
  are built; documented cross-plugin capability assembly, fail-closed
  screen/action authorization, version-gated role migrations, audit logging,
  rollout slices, and that third-party role plugins may only be assignment UI,
  not permission authority.
- Implemented the Operations admin capability foundation:
  - added the `iss-core` capability registry, mapped capability helper,
    Operations root menu, `iss_require_cap()` / `iss_cap_check()` helpers,
    version-gated `iss_caps_version` role migration, WP-CLI diagnostics, and
    lightweight operations audit logging;
  - created project roles for operations manager, curator/editor, reviewer,
    intake helper, and technical maintainer while preserving administrator and
    editor compatibility grants;
  - moved Steuerung, Sets, Register tools, SuperSaaS sync/settings, ISS
    requests, and operational CPT menus under Operations where available;
  - mapped Rueckblick, Publications, Register places, Hinweise, and Archive
    CPTs to explicit primitive/meta caps and tightened Set/Archivset REST
    permissions for create, edit, review, promote, and delete paths.
- Added `ops/sql/2026-06-24-remove-shop-surecart-roles.sql` as the transfer
  artifact for removing legacy shop/SureCart roles and residual Woo/SureCart
  capabilities from `wp_user_roles`; the artifact backs up the prior option
  value before replacing it with the verified core plus ISS role set.
- Implemented the editorial media Sets workflow foundation from `docs/architecture/editorial-media-buckets.md`:
  - added `iss-content` owned Set, item, context-link, and audit tables because the full SOW requires indexed queues, cross-context filters, batch review, decay sweeps, and promotion history that post meta would make fragile;
  - added the private Intake Workbench admin surface with Set creation, media attachment, thumbnail grid, modal facts, filters, status/batch actions, context attachment, promotion, and archive-candidate marking;
  - added explicit REST/service APIs for Set CRUD, item movement/review, context links, promotion, decay, and Event Drop attachment intake without making public renderers read raw Set state;
  - added Event Drop incoming-file sync so successful public uploads appear as pending private Workbench items before WordPress attachment promotion, with authenticated admin previews, promotion-time Media Library import, reject quarantine/restore handling, German Workbench labels, and filename/file-metadata recovery when manifest rows are missing;
  - added `rueckblick` as a first-class CPT with an `iss-editorial` document format, while keeping promotion into public pages limited to approved `media_refs` / `object_refs`.
- Added a test `upload_intake` Veranstaltung gesture:
  - exposes an editor-visible Upload-Aufruf section that renders a public CTA to `/event-drop/?event=<veranstaltung-slug>`;
  - keeps uploaded material in the moderated intake/Set path and does not render raw uploads on public pages.
- Normalized the local Event Drop upload frontend:
  - mounted the committed intake interface at `/event-drop/` in Docker and added local ignored `.env` upload-code support;
  - made the intake form honor the `event` query parameter so Veranstaltung upload CTAs prefill the target event.
- Raised local Event Drop upload runtime limits:
  - mounted `docker/php/uploads.ini` into the WordPress and WP-CLI containers;
  - increased PHP upload/post limits for large moderated intake files and verified an upload above the old 2 MB default succeeds.
- Implemented the Veranstaltung Shape + Skin cleanup:
  - added registry-derived `iss-event-shape-*`, `iss-event-skin-*`, and normalized `iss-event-entity-*` body classes for singular Veranstaltungen;
  - added concrete Vortrag, Lesung, Gespraech, Repair, and Festival skins from the shape/skin mockup, with scoped typography, sidebar, color, layout, and programme treatments while leaving undesigned event entities on the baseline treatment;
  - made `galerie` the explicit image-gallery gesture with a framed carousel strip, removed image refs from `material`, and added `ops/sql/2026-06-24-veranstaltung-24988-material-gallery-split.sql` for the one existing material-image row;
  - replaced old `iss-event-format-*` renderer markup/CSS with the structured JSON class surface and removed stale festival-info styling;
  - removed completed legacy body import commands/helpers and the unused skin-override meta shell after all current Veranstaltung JSON documents passed review;
  - moved graph offer subtype sources to `_iss_entity_key` only, removing the remaining layout/format fallback path.
- Added the guarded Veranstaltung single-renderer slice:
  - marked the editorial review for `24988`, `13349`, and `25808` as passed in the active checkpoint;
  - added a theme-owned `_iss_content_json` renderer for those reviewed posts only, with legacy `post_content` fallback for every other Veranstaltung;
  - reused the existing `iss-content` structured document contract and central `industriesalon-steuerung` field API instead of adding a parallel route, template, or data layer.
- Widened the Veranstaltung structured renderer from a hard-coded reviewed-post list to every Veranstaltung with a valid sanitized `_iss_content_json` document, while preserving legacy `post_content` fallback for empty or invalid rows.
- Migrated the remaining Veranstaltung legacy bodies into structured JSON:
  - imported 22 additional `_iss_content_json` documents through the existing `iss-content` importer without overwriting the three reviewed documents;
  - brought the local Veranstaltung structured-content state to `25` stored, `25` valid, `0` invalid documents;
  - added `ops/sql/2026-06-24-veranstaltungen-content-json-full.sql` as the full transfer artifact for all current Veranstaltung structured-content JSON;
  - marked editorial review as passed for all 25 migrated Veranstaltung JSON documents.
- Moved Veranstaltung authoring off Gutenberg:
  - removed `veranstaltung` from the block-editor opt-in list and removed default editor support for the CPT;
  - promoted the existing `Struktur` JSON editor to the first high-priority normal editor surface while keeping title, publish/status, taxonomy/meta, and relation boxes available;
  - removed the obsolete theme-owned Veranstaltung Gutenberg panel for inserting Terminblatt block patterns.
- Removed the legacy Veranstaltung presentation switches:
  - removed active `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme` registration/save/UI/body-class/template decisions;
  - deleted the remaining local `wp_postmeta` rows for those legacy keys and added `ops/sql/2026-06-24-veranstaltungen-remove-legacy-presentation-meta.sql` for transfer;
  - removed migration-only legacy layout/format inference from the registry and dry-run command now that all current Veranstaltungen have curated `_iss_entity_key` values;
  - removed the old Terminblatt/Fest Gutenberg event patterns and kept a single baseline single-event CSS treatment until explicit skins/template work replaces it.
- Routed Veranstaltung related rails through the shape-aware repository:
  - fixed `iss_content_model_veranstaltungen_related()` to use the existing entity-related source contract and filter returned posts through registry-valid `_iss_entity_key` values;
  - delegated Veranstaltung-only related-content rails on Veranstaltung pages to that repository method while preserving manual related blocks;
  - kept the raw Veranstaltung query audit passing with only the approved repository/CLI query paths.
- Audited Veranstaltung archive/homepage/calendar projections:
  - confirmed the public block surfaces use the existing occurrence-backed `industriesalon/timeline-query` route rather than raw Veranstaltung post loops;
  - confirmed the Veranstaltung occurrence provider already gates synced rows to timeline-shaped entity keys;
  - left the existing projection owner in place instead of adding a parallel repository bridge.
- Added the sixth Veranstaltung JSON editor slice:
  - added `dynamic_refs` to the structured Veranstaltung section contract for centralized `industriesalon-steuerung` field references;
  - taught legacy import to preserve `industriesalon/field` blocks as references such as `address.full` instead of flattening address/opening-hours/link values into `_iss_content_json`;
  - added editor preview support that resolves current Steuerung values read-only while saving only the reference metadata;
  - imported post `25808` into `_iss_content_json` as a five-section `event.festival` candidate and added `ops/sql/2026-06-24-veranstaltung-25808-content-json.sql`.
- Tightened Veranstaltung archive-object refs:
  - stopped storing long Archivset member captions in `_iss_content_json`;
  - capped archive-object labels and rendered selected objects as thumbnail cards in the `Struktur` tray and preview;
  - refreshed post `24988` and `ops/sql/2026-06-23-veranstaltung-24988-content-json.sql` with the lean object-ref payload.
- Added `galerie` to the normal Veranstaltung gesture set so editors can collect image-heavy event posts in a dedicated gallery section instead of overloading prose or material sections.
- Added the editorial media bucket contract stub:
  - documented one shared private intake/review/promotion model for Veranstaltung, Ausstellung, Projekt, Publication, pages, and archive contexts;
  - kept `galerie` as an approved presentation section, not an editor dump area;
  - deferred UI/storage implementation and explicitly avoided separate `eventset`, `projectset`, or `publicationset` systems.
- Expanded the media intake/bucket contract into an implementation SOW:
  - defined one shared thumbnail-grid intake workbench instead of separate editor-facing buckets per event/content type;
  - made named Sets the editor-facing model, including uncategorized intake, context-free preparation Sets, item movement between Sets, and whole-Set attachment/promotion to multiple CPT targets;
  - added temporary decay/retention rules for raw uploads;
  - made Rueckblick a first-class promotion target and archive promotion a stricter `iss-archive` curation handoff.

## 2026-06-23

- Added the first Veranstaltung JSON editor slice:
  - added an `iss-content` admin structure box for `_iss_content_json` on Veranstaltung edit screens without disabling the normal editor or changing public rendering;
  - limited available gestures by the selected `_iss_entity_key` registry contract and saved normalized section cards through the existing nonce-protected post save path;
  - tightened `veranstaltungen-content-audit` so empty meta rows are not counted as stored JSON documents.
- Added the second Veranstaltung JSON editor slice:
  - added `wp iss-content veranstaltungen-content-dry-run` to report legacy-body import candidates for all converted Veranstaltungen;
  - added guarded `wp iss-content veranstaltungen-import-candidate --post=<id-or-slug>` with dry-run default and `--yes` writes to `_iss_content_json`;
  - kept generated documents editor-only and reported media/unsupported blocks for curator review.
- Added the third Veranstaltung JSON editor slice:
  - taught the import candidate builder to recognize the Gutenberg event format sheet, skip its navigation block, and preserve sheet chapters as structured sections with kicker/title/body/material items;
  - imported post `24988` into `_iss_content_json` as a six-section `event.vortrag` document from the saved format sheet;
  - added the transfer artifact `ops/sql/2026-06-23-veranstaltung-24988-content-json.sql` for that reviewed local document while keeping public rendering on legacy `post_content`.
- Added the fourth Veranstaltung JSON editor slice:
  - added a read-only preview column to the existing `Struktur` box so editors can inspect the compacted `_iss_content_json` sections before saving;
  - kept the preview entirely in the `iss-content` admin editor script, with no public renderer switch and no new endpoint;
  - verified post `24988` still stores a valid six-section `event.vortrag` document.
- Added the fifth Veranstaltung JSON editor slice:
  - added media and archive-object reference support to the existing `Struktur` box, reusing WordPress media selection and the existing archive-object picker when available;
  - taught legacy content import to preserve WordPress image/media blocks as `media_refs` without storing local dev-host thumbnail URLs;
  - imported post `13349` into `_iss_content_json` as a one-section `event.vortrag` document with one media ref and added `ops/sql/2026-06-24-veranstaltung-13349-content-json.sql`;
  - left public rendering on legacy `post_content` and left the remaining `25808` dynamic `industriesalon/field` block for editor review.
- Completed the local Veranstaltung entity migration checkpoint:
  - curated all remaining Veranstaltung posts so `wp iss-content veranstaltungen-dry-run` now reports `25 safe`, `0 review`, `0 blocked`, and `25 converted`;
  - normalized missing or partial date facts using explicit curator input where needed and recorded inference notes on fallback dates;
  - moved post `25763` from `veranstaltung` to `fuehrung` as `Sonderführung` with on-demand Führung meta;
  - added the transfer artifact `ops/sql/2026-06-23-veranstaltungen-entity-migration.sql` for the source-state post/postmeta/taxonomy changes, with occurrence and graph/search projections regenerated after import.
- Added the seventeenth Veranstaltung entity-registry slice:
  - registered the `_iss_content_json` structured-content shell and hidden `_iss_skin_override` meta for Veranstaltungen;
  - added `wp iss-content veranstaltungen-content-audit` / `wp iss-content-model veranstaltungen-content-audit`;
  - kept stored documents optional and read-only for now, with no content import or renderer switch.
- Added the sixteenth Veranstaltung entity-registry slice:
  - added `wp iss-content veranstaltungen-query-audit` / `wp iss-content-model veranstaltungen-query-audit`;
  - moved the theme menu's next-event data lookup behind the `iss-content` Veranstaltung repository;
  - made raw Veranstaltung post queries fail the audit outside the approved repository and curation tooling.
- Added the fifteenth Veranstaltung entity-registry slice:
  - registered normalized query fact meta for `_iss_datetime_start`, `_iss_datetime_end`, and `_iss_published_at`;
  - added save/set-entity synchronization from existing Veranstaltung date facts;
  - made required-fact validation prefer normalized facts while preserving legacy fallbacks.
- Added the fourteenth Veranstaltung entity-registry slice:
  - added a shape-aware Veranstaltung repository facade for upcoming, archive, reports, homepage teasers, related, and menu-event lookups;
  - filtered converted posts by `_iss_entity_key` through registry-derived primary surfaces;
  - kept unconverted legacy posts on the existing menu fallback only, so public behavior remains stable until curation.
- Added the thirteenth Veranstaltung entity-registry slice:
  - expanded the Veranstaltung registry with per-entity domain, post type, icon, and field contracts;
  - validated field names, field types, relation targets, and required shape facts in `veranstaltungen-registry-check`;
  - kept the new contract read-only for future generated authoring, with no renderer or content migration change.
- Added the twelfth Veranstaltung entity-registry slice:
  - taught the graph offer contract to prefer `_iss_entity_key` when set on Veranstaltungen;
  - added an `event_report` offer subtype for `report.rueckblick`;
  - kept unset legacy Veranstaltungen on the existing `_iss_event_layout` / `_iss_event_format` subtype fallback.
- Added the eleventh Veranstaltung entity-registry slice:
  - added `wp iss-content veranstaltungen-registry-check` / `wp iss-content-model veranstaltungen-registry-check`;
  - exposed a standalone schema/entity/shape validation command for CI, staging, and pre-renderer checks.
- Added the tenth Veranstaltung entity-registry slice:
  - added `--status`, `--entity`, and `--missing-facts` filters to `veranstaltungen-dry-run`;
  - made review, blocked, converted, and entity-specific curation queues available without exporting the full audit table.
- Added the ninth Veranstaltung entity-registry slice:
  - extended `veranstaltungen-dry-run` table and JSON output with a guarded `set_command` recommendation per candidate;
  - kept generated commands dry-run by default by omitting `--yes`, so copied recommendations still validate before writing.
- Added the eighth Veranstaltung entity-registry slice:
  - exposed selected Veranstaltung `Typ` on singular event pages as stable theme body classes;
  - added `iss-event-entity-*`, `iss-event-shape-*`, and `iss-event-surface-*` only when `_iss_entity_key` is set;
  - left unset legacy posts on the existing layout/scheme/format body-class contract with no CSS or renderer changes.
- Added the seventh Veranstaltung entity-registry slice:
  - added a read-only legacy-derived Typ suggestion inside the Veranstaltung metabox;
  - centralized the suggestion helper so the editor hint and `veranstaltungen-dry-run` share the same confidence and conflict logic;
  - kept suggestions non-authoritative: editors still choose and save `_iss_entity_key` deliberately.
- Added the sixth Veranstaltung entity-registry slice:
  - added a `Typ` column to the Veranstaltung admin list table;
  - added a list-table filter for concrete Veranstaltung entity keys and unset posts;
  - kept the filter read-only over `_iss_entity_key`, giving editors a curation dashboard without changing public rendering.
- Added the fifth Veranstaltung entity-registry slice:
  - centralized Veranstaltung required-fact checks in the registry helpers;
  - made the Veranstaltung status box shape-aware once `Typ` is set, so event types check their date facts while `report.rueckblick` checks publication instead of event start;
  - switched the curation CLI to the same required-fact helper used by the editor status.
- Added the fourth Veranstaltung entity-registry slice:
  - added `wp iss-content veranstaltungen-set-entity` / `wp iss-content-model veranstaltungen-set-entity` as the controlled write path for curated `_iss_entity_key` assignment;
  - kept the command dry-run by default, requiring `--yes` to write and warning/blocking on missing required facts unless `--force` is explicit;
  - wired successful writes to `iss_occurrences_sync_source()` when available, so setting `report.rueckblick` clears event occurrence rows through the normal provider path.
- Added the third Veranstaltung entity-registry slice:
  - taught the Veranstaltung occurrence provider to honor `_iss_entity_key` once set;
  - kept unset legacy Veranstaltungen on the existing `iss_programme_enabled` occurrence path;
  - made `report.rueckblick` produce no event occurrence, so saving a converted Rueckblick clears it from upcoming timeline surfaces without changing the single-page renderer.
- Added the second Veranstaltung entity-registry slice:
  - exposed `_iss_entity_key` as an editor-visible `Typ` radio group in the existing Veranstaltung metabox;
  - saved the semantic type independently from legacy `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme`;
  - added the type to the Veranstaltung status box while leaving current rendering, occurrence sync, and timeline behavior unchanged.
- Added the first Veranstaltung entity-registry slice:
  - registered a read-only Phase 1 entity/shape contract in `iss-content` with `_iss_entity_key` validation but no content migration;
  - added `wp iss-content veranstaltungen-dry-run` / `wp iss-content-model veranstaltungen-dry-run` to map legacy layout/format metadata to candidate event/report entities;
  - verified the local dry-run result: 26 Veranstaltungen, 0 safe auto-conversions, 15 review candidates, 11 blocked by missing `iss_start_datetime`.
- Added the compressed `Röhren für die Republik` Ausstellung JSON candidate:
  - saved and enabled the local `_iss_editorial_ausstellung` document for post `21108` with the `industrieakte` skin and 16 sections;
  - added source-backed fact-scale sections for Leningrad T2, research-file gaps, and the tube-to-semiconductor transition;
  - wrote paired transfer SQL at `ops/sql/2026-06-23-roehren-republik-editorial-json.sql`;
  - widened `industrieakte` `massstab` facts into a responsive grid for desktop/mobile review.
- Converted local `Kinder im Werk` to a curated Ausstellung JSON document:
  - registered the `kinder-im-werk` JSON skin and added its theme-owned stylesheet;
  - saved and enabled the local `_iss_editorial_ausstellung` document for post `26381`;
  - styled the skin's hero suppression, source excerpts, typographic quote, object-focus cards, album copy order, and stat gesture with existing shared/typographic treatments;
  - wrote paired transfer SQL at `ops/sql/2026-06-23-kinder-im-werk-editorial-json.sql`.
- Added a footer-oriented `register` layout variant for graph related-content rails and switched the single-Ausstellung tail to it.
- Replaced the single-Ausstellung tail from Ausstellung-only `iss/related-cards` to graph-backed mixed `iss/related-content`, and synced the local DB-backed template override.
- Moved Ausstellung practical metadata into the single-Ausstellung hero:
  - relocated the existing `iss/content-meta` block from the post-content intro slot into the hero overlay;
  - styled the existing `iss-info-panel--skin-aside` output as a compact dark hero facts rail without duplicating metadata markup;
  - synced the local DB-backed `single-ausstellung` template override to match the file template for immediate local review.
- Exposed Ausstellung editorial skin assignment in the custom composition editor:
  - added an `iss_editorial_format_skins` hook so the theme can provide allowed skins for a format;
  - registered the current Ausstellung skins from the theme owner;
  - added a single `Darstellung` selector that writes the document `skin` while keeping layout, variant, and section-role controls out of the editor.

## 2026-06-22

- Added phone/camera capture to the one-off Event Drop Uppy intake snapshot:
  - wired Uppy `Webcam` into the existing inline Dashboard/XHRUpload flow;
  - enabled native mobile/tablet camera capture for photo/video uploads;
  - kept the same `/event-drop/` upload endpoint, `media` field, moderation storage, CSV manifest, and WordPress bridge contract.
- Fixed the theme off-canvas menu shell after broad CSS changes:
  - capped the fixed shell to the visible viewport with safer viewport-unit fallbacks;
  - reset WordPress block-gap margins inside the shell so the menu no longer renders at roughly 1.5 viewport heights in Firefox;
  - distributed the Institution/Entdecken/Archiv sections evenly through the available menu height;
  - pinned the Heute/next-event status strip to the bottom of the menu viewport.
- Signed off Phase 1 of the Ausstellung editorial-platform SOW for authoring/save/reload after editor roundtrip proof:
  - kept autosave recovery deferred by decision;
  - renamed the explicit structure action from `JSON speichern` to `Speichern`;
  - styled the explicit structure save as a red editor-canvas button;
  - added a post-save reminder that JSON section content is updated by that button, while WordPress `Aktualisieren` is only needed for WordPress-owned fields such as title, slug, status, taxonomies, or other metabox data.
- Finalized Phase 2 for the current Ausstellung pilot boundary:
  - kept archive-object selection on the existing bucket-first archive picker and media selection on WordPress `wp.media`;
  - changed selected archive-object chips to show human labels with explicit remove buttons instead of clickable raw/fallback identifiers;
  - made archive-object selection refresh the canvas and schedule persistence immediately after picker confirmation;
  - recorded the SOW-wide Relation Picker as deferred until entity-editor work needs it.
- Restored Ausstellung decision/research links in the JSON editor path:
  - added a supported `links` field for `schluss` and `aside` gestures;
  - exposed repeatable link editing in the composition modal;
  - rendered section links as a theme-owned button rail;
  - taught the migration helper to preserve legacy Gutenberg navigation links;
  - updated the `Frauen im Werk` local JSON candidate and transfer SQL with the five legacy links.
- Advanced the editorial-platform SOW Phase 3 skin decision for the Ausstellung pilot:
  - kept skin, variant, layout, and section-role choices out of the main editor canvas so editors only add, edit, save, and reorder content sections;
  - changed the editor save path to preserve the internal JSON-rendering rollout flag without exposing it as a normal authoring control;
  - added theme-owned JSON skin resolution for `frauen-im-werk`, with reusable layout classes and the first `gesture x skin` treatments;
  - extracted the first `frauen-im-werk` treatment CSS into a dedicated theme skin stylesheet that loads only when the enabled editorial document resolves to that skin;
  - changed the default JSON renderer to emit universal section slots for media, copy, kicker, body, quote, and refs so skins can express live station-like treatments without one partial per gesture;
  - made `kicker` a first-class JSON section field in storage, editor UI, migration import, and theme rendering;
  - corrected the first `frauen-im-werk` source-station treatment to belong to the `quellenauszug` gesture, while `objektfokus` stays reserved for archive-object grids;
  - styled the `objektfokus` gesture as a dedicated dark archive-object grid for the `frauen-im-werk` skin, using the existing archive-card renderer with responsive two-column/one-column behavior;
  - adapted `bildstrecke` and `massstab` for the `frauen-im-werk` skin from the existing `Kinder im Werk` album/stat patterns, keeping the shared JSON renderer slots and avoiding new editor controls;
  - added two additional theme-owned editorial JSON skins, `typografisch` and `chronik`, as conditional Ausstellung stylesheets over the same universal gesture/layout classes for later real-content testing;
  - added the `industrieakte` editorial JSON skin for technical/industrial Ausstellung series, translating the WF/Röhren dossier mockup into theme-owned gesture treatments without copying its inline dashboard markup;
  - added a scoped `quellenauszug` text-position flip so source stations can render with text left or text right in the `frauen-im-werk` skin;
  - added quiet typographic treatments for existing `kapitel`, `leitfrage`, `zitat`, and `fliesstext` gestures using the generic renderer slots and CSS-derived chapter numbering, without adding parallel JSON fields or per-gesture partials;
  - made `vollbild` a generic one-image full-viewport treatment with a 16:9 editor hint, media-dimension refs, first-image renderer fallback, and dark ink-panel overlay;
  - added `ops/sql/2026-06-22-frauen-im-werk-editorial-json.sql` as the narrow transfer artifact for the local JSON pilot document and enabled flag;
  - added the SOW partial lookup path for future theme overrides before falling back to generic section rendering;
  - promoted the Ausstellung vocabulary with `vollbild`, `fliesstext`, and `schluss`, then migrated the local `Frauen im Werk für Fernmeldewesen` JSON pilot from generic `bildstrecke` sections to concrete poster-essay gestures while keeping its local JSON rendering enabled for review.

## 2026-06-21

- Added a shared theme button primary tier: `style.css` now exposes solid primary button tokens plus `.iss-button` variants and maps Gutenberg `is-style-fill` buttons to the solid tier, while the programme timeline skin uses the same primary tokens for booking actions.
- Reworked the public `/kalender/` wide-viewport skin: the file-backed calendar template now wraps the existing occurrence-backed `industriesalon/timeline-query` block in a theme-owned workbench, moves the block's existing filters into a left rail on wide screens, caps the result column, collapses recurring tour groups by default, hides oversized listing media on the calendar surface, and adds a right rail linking exhibitions back to the separate availability browser.
- Adjusted programme timeline kickers to set the shared kicker accent/color variables so the text, left rule, and dot follow the active timeline scheme together.
- Added the first `iss-editorial` vertical slice for the finalized editorial-platform SOW:
  - introduced an engine-only plugin for versioned editorial JSON documents, autosave, format/section registration, typed references, and normalized read models;
  - wired `iss-content` to opt `ausstellung` into the initial `OrderedFormat` pilot while preserving existing Gutenberg/meta content as the default fallback;
  - added archive-object picker integration for object-reference sections without exposing raw IDs to editors;
  - added theme-owned Ausstellung JSON rendering behind a per-post feature flag, with unresolved references omitted publicly and shown as preview/editor placeholders;
  - replaced the temporary Gutenberg sidebar/meta-box editorial UI with a custom main-canvas composition editor for `ausstellung`, using palette gestures, ordered section cards, and modal section editing;
  - added media selection to the composition modal via the WordPress media library, imported legacy exhibition images as `bildstrecke` media refs, added editable captions/removal controls, and rendered resolved media refs in the theme-owned JSON output;
  - normalized the archive-object picker toward the media-picker workflow: it now opens as a modal, starts from attached/context Archivset buckets, shows bucket members as thumbnail cards, keeps faceted object search as the secondary fallback, and preserves selected bucket/member provenance in editorial references;
  - stabilized the archive picker modal for editor use with inline/modal mode separation, bucket-first search guards, readable global-search result cards, fixed result scrolling, and theme-red selected-object accents;
  - added a REST save route for reviewed editorial JSON documents and their per-post enabled flag;
  - added a read-only `wp iss-editorial ausstellung-dry-run` report for `Kinder im Werk` and `Frauen im Werk` before any permanent JSON-rendering switch;
  - added `wp iss-editorial ausstellung-import-candidate --post=<slug-or-id>` and imported a disabled local JSON candidate for `Frauen im Werk für Fernmeldewesen` with 7 sections and 6 media refs while leaving public Gutenberg rendering active;
  - fixed editorial JSON meta writes to slash encoded JSON before `update_post_meta()`, preserving paragraph breaks and other escaped content through WordPress meta storage;
  - added `iss-editorial` to PHPStan paths/scanFiles so the repo's per-file target runner can see the new plugin symbols without include-file workarounds;
  - documented the boundary in `docs/architecture/editorial-platform.md` and added `iss-editorial` to the plugin map.
- Reworked static map framing for front-page and Führungen landing surfaces:
  - added a full-size baked `-17deg` Spree-horizontal derived map plus matching marker projection JSON;
  - registered `spree-horizontal-17` as a reusable theme map preset while keeping the unrotated canonical map as the source reference;
  - switched front page and `/fuehrungen/` spine strips to the baked preset with page-specific vertical crop and 1.14 cover zoom;
  - simplified spine-strip overlays so map markers carry the spatial meaning and the direction rail stays decorative;
  - hid raw rotation/horizontal-bias controls from the editor, kept zoom up to 200%, and exposed vertical crop as the author-facing framing control.

## 2026-06-15

- Made `register_place` public editor image groups authoritative for frontend featured-image rendering: public featured/fallback image-group selections now win through the WordPress thumbnail filter and save-time sync updates `_thumbnail_id` when the selected public image changes.
- Audited `/fuehrungen/elektropolis-tour/` route media behavior: the `iss/tour-route` block renders place-level public `archive_images`/`current_images` as station figures and keeps `station_object_id` as a separate detail card, so missing “Damals” images on that route are currently caused by private/missing place image-group data rather than a PHP render failure.
- Polished the `/ausstellungen/` browser interaction: added visible result summaries, debounced live search, a no-JS-capable clear-search link, filter URLs that preserve the current search term, and responsive control styling for the exhibition page skin.
- Closed the Atlas/static-map cleanup with a public-surface audit: first-class surfaces remain `iss/related-place-map`, `iss/atlas-slice`, `iss/spine-strip`, and `iss-register/schoneweide-atlas`; experimental static surfaces stay inserter-hidden; broader archive/graph API consolidation is deferred until a concrete consumer exists.
- Added fullscreen and kiosk layout states to the existing Schöneweide Atlas block: the Atlas now exposes embedded/fullscreen/kiosk controls, keeps the same REST/render path, invalidates Leaflet sizing on mode changes, and resets filters/map view after kiosk idle.
- Added focused contract/schema checks for Atlas REST payloads and static-map inputs: `iss-register contract-check` now validates Atlas place/context schema, and new `wp iss-relations static-map-contract-check` validates first-class map-block contracts plus static-map relation result/DTO shape.
- Finished the interactive Atlas runtime module split by extracting marker icon and map-marker orchestration into `atlas/markers.js`, leaving `schoneweide.js` as the bootstrap coordinator for config, payload loading, state creation, and module calls.
- Split the remaining interactive Atlas DOM-heavy renderers into focused modules:
  - added `atlas/detail.js` for popup/detail rendering and place media figures;
  - added `atlas/stories.js` for story intro, story cards, and fallback place cards;
  - added `atlas/relations.js` for relation rail rendering and mini-map projection;
  - wired the new detail/story/relation script handles before the existing public Atlas view handle, leaving the main runtime focused on bootstrap and marker/map orchestration.
- Split the interactive Schöneweide Atlas place/filter UI out of the main runtime:
  - added `themes/industriesalon/assets/js/atlas/places.js` for filter buttons, filter labels/counts, search/reset bindings, root filter attributes, summary/count rendering, and selected-place render context;
  - wired the new `iss-register-schoneweide-atlas-places` script between the store module and the existing public Atlas view handle;
  - left marker rendering, popup/detail UI, story cards, and relation rails in `schoneweide.js` for the next focused split.
- Continued modularizing the interactive Schöneweide Atlas runtime:
  - extracted payload normalization, derived era/actor maps, filter/selection state, selected story resolution, and relation scoring into `themes/industriesalon/assets/js/atlas/store.js`;
  - wired the new `iss-register-schoneweide-atlas-store` script before the existing public Atlas view handle;
  - kept the visible block, REST payloads, map adapter, and DOM rendering behavior unchanged while leaving place/filter UI and detail/story/relation rendering as the next split.
- Began modularizing the interactive Schöneweide Atlas runtime without changing the public block or REST payload:
  - extracted shared Atlas runtime core utilities, provider selection, payload/config loading, resize/layout sync, and the Leaflet map adapter into ordered theme JS modules;
  - kept the existing `iss-register-schoneweide-atlas-view` handle as the public view entrypoint with the new modules as dependencies;
  - left place filtering/list UI, detail/popup rendering, and story/relation panels in the main runtime for the next split.
- Defined the static-map DTO boundary between `iss-relations` and `iss-frontend`: block place selection now normalizes into a relation result with ordered static-map place DTOs, and `docs/architecture/static-map-rendering.md` records the contract keys.
- Split the `iss-relations` related-content block editor script into focused modules for editor context, place-source controls, related-card controls, static-map controls, spine-strip controls, and editorial-signal controls while keeping the existing block names, render callbacks, and `iss-relations-related-blocks` editor handle stable.
- Froze the first-class static map block surface:
  - kept `iss/related-place-map`, `iss/atlas-slice`, and `iss/spine-strip` as normal inserter-visible map blocks;
  - kept `iss/atlas-strip` and `iss/asymmetric-split-field` render-compatible but hidden from the inserter as experimental/non-current public surfaces.
- Documented static marker provenance and the manual marker update verification path in `docs/architecture/static-map-rendering.md`.
- Split static map block responsibilities:
  - added a shared `iss-relations` map-block source contract used by PHP defaults, render resolution, and editor settings;
  - moved static marker lookup, projection math, focus-window calculation, stage rendering, panel rendering, and static map frontend rendering entry points into `iss-frontend/modules/static-maps`;
  - kept thin compatibility wrappers in `iss-relations` while block callbacks delegate to the frontend renderer;
  - fixed map-block source resolution so implicit manual `placeIds` no longer depend on an out-of-scope rendered block object;
  - added `wp iss-relations map-block-audit` to check DB and file-backed static map blocks, selected-place marker resolution, and public coordinate-bearing `register_place` marker coverage against the same contract;
  - added missing derived static markers for published coordinate-bearing places that were absent from `schoneweide-static-markers-new.json`;
  - kept `industriesalon-schoeneweide-register` as the `register_place` and interactive Atlas data/cache owner;
  - documented the cleanup pattern in `docs/architecture/static-map-rendering.md`.
- Added the Atlas/static-map implementation plan in `docs/architecture/atlas-static-map-implementation-plan.md`, merging the local audit and peer review into one durable architecture plan.

## 2026-06-14

- Added conditional project status rendering:
  - introduced `iss/project-status` for project lists;
  - renders date ranges from project start/end meta when present;
  - falls back to completed state after end date, taxonomy status, then period label.
- Reconciled current editor/template drift:
  - copied current front-page text edits into the file template and removed its DB override;
  - flushed the current `page-projekte` DB template body to disk while leaving the DB override in place for later deletion.
- Moved `iss/dense-image-wall` to `iss-frontend`:
  - kept the block name stable for existing content;
  - split editor workflow into composition and text/link modes;
  - moved baseline block CSS out of the theme;
  - hardened render output for class and URL handling.
- Fixed booking CTA modal loading for tour/calendar surfaces by enqueueing the shared programme script where slot triggers render.
- Added transfer artifacts for the Walk of Fame dense wall content:
  - `ops/sql/2026-06-14-walk-of-fame-dense-wall.sql`;
  - `ops/uploads/2026-06-14-walk-of-fame-dense-wall-media.tar.gz`;
  - matching manifest and SHA256 files.
- Added transfer artifacts for the current `projekt` single-page content edits:
  - exports all seven published `projekt` posts plus their postmeta and term relationships;
  - normalizes local dev URLs in project content to root-relative paths;
  - pairs the SQL with a 28-file upload archive for directly referenced project media.

## 2026-06-13

- Compacted repository documentation:
  - reduced `handoff_CURRENT.md` and `TODO.md` to current operational checkpoints;
  - collapsed the content model architecture notes into `docs/architecture/entity-model.md`;
  - removed stale planning/audit documents and local backup archives;
  - kept `docs/project/decisions.md` and `docs/project/backlog.md` as the durable project record.
- Finalized occurrence/programme cleanup:
  - made `iss-occurrences` the owner of occurrence projection, public query readiness, recurrence grouping, and SuperSaaS ingestion;
  - removed graph/entity ID coupling from occurrence rows;
  - removed frontend dependencies from occurrence query helpers;
  - kept `iss-programm`/`iss-frontend` rendering-oriented.
- Reorganized plugin domains:
  - renamed functional plugin folders toward `iss-content`, `iss-frontend`, `iss-commerce-lite`, and `iss-graph`;
  - refreshed active-plugin SQL artifacts and removed obsolete plugin-folder references from docs.
- Hardened public request surfaces:
  - added rate limiting, body-size guards, nonce support, timing checks, honeypot handling, and light spam rejection logging for public commerce endpoints;
  - moved request/order storage from capped options to plugin-owned tables.
- Cleaned editorial calendar visibility:
  - renamed the UI concept from technical timeline wording to programme/calendar wording while preserving migration compatibility;
  - kept projects opt-in for programme projection;
  - made permanent and digital exhibitions opt-in for programme/calendar visibility.
- Continued graph/facade cleanup:
  - added graph hygiene checks for orphan membership edges, bad relation years, and duplicate alias tokens;
  - preserved typed relationship semantics for employment, construction/design, membership, and founding links;
  - kept consumer discovery read-only through facade/query helpers.
- Verification:
  - ran local PHP syntax checks for touched plugin/theme files;
  - ran focused PHPStan/PHPCS checks where configured;
  - ran WP-CLI drift, active-plugin, and facade/offer-consumer smoke checks where relevant;
  - ran `git diff --check`.

## 2026-06-12

- Built graph editorial signals and search influence:
  - added rating/confidence/visibility controls for canonical graph entities and relationships;
  - added materialized influence rows and search-weight support;
  - added admin export and CLI reporting for graph hygiene.
- Prepared and applied graph hygiene artifacts:
  - canonicalized WF/Industriesalon and KWO/AEG organization data;
  - backfilled alias replay artifacts;
  - removed orphan or semantically incorrect graph edges.
- Added facade and consumer guardrails:
  - kept offer consumers on read-only query helpers;
  - added route/audit tooling for old offer/availability consumers;
  - documented facade ownership in architecture docs.
- Moved tour/public rendering ownership:
  - collapsed Führung public templates back to the theme;
  - cleaned old page-template overrides and tour-template SQL artifacts;
  - added guards around SuperSaaS occurrence reactivation and legacy occurrence-origin purge.
- Retired legacy public-read routes and stale runtime surfaces:
  - removed old REST compatibility paths;
  - kept compatibility only where required for migration or local verification.
- Rewrote history to remove obsolete production newsletter SQL from the active branch.
- Verification included PHP syntax checks, WP-CLI smoke checks, PHPCS/PHPStan passes for touched slices, and `git diff --check`.

## 2026-06-11

- Established the greenfield content/facade checkpoint:
  - set `iss-content` as the editorial source;
  - kept public presentation in the theme and data/business contracts in plugins;
  - documented the source-of-truth architecture in `docs/architecture/entity-model.md`.
- Separated exhibitions from programme/calendar projection:
  - introduced strict programme opt-in for exhibitions/projects;
  - excluded availability-only exhibition types from automatic calendar projection;
  - added SQL artifacts for availability cleanup, strict programme toggle backfill, and legacy programme meta purge.
- Introduced `iss-occurrences` as the durable occurrence projection service:
  - replaced hidden calendar runtime behavior;
  - added occurrence admin/CLI tooling and public query helpers;
  - began migration away from legacy `iss_calendar_item` assumptions.
- Added first read-only availability/entity relation/offer facade slices.
- Verification included WP-CLI source checks, occurrence smoke tests, SQL artifact dry-runs, and PHP syntax checks.

## 2026-06-10

- Reworked key exhibition pages and transfer artifacts:
  - redesigned Frauen im Werk and Kinder im Werk content structures;
  - added related-content and care/skin improvements;
  - prepared media and SQL transfer artifacts under `ops/sql/` and `ops/uploads/`.
- Added SuperSaaS admin bootstrap and began tour availability integration work.
- Improved archive/publication page behavior and related-card presentation.
- Verification included local route checks, WP-CLI template/meta checks, and artifact validation.

## 2026-06-09

- Tightened the Ausstellung plugin/theme contract:
  - clarified plugin data ownership and theme presentation ownership;
  - removed hidden shortcode-style rendering expectations;
  - improved exhibition archive/editor behavior.
- Continued related-card and carousel contract cleanup.
- Verification focused on archive rendering, editor compatibility, and PHP syntax checks.

## 2026-06-08

- Improved exhibition editor controls and archive semantics:
  - added reusable editorial metadata controls;
  - refined exhibition visibility and type handling;
  - improved block/editor alignment for exhibition content.
- Continued CSS contract cleanup around archive/card rendering.

## 2026-06-07

- Audited archive, graph, project, publication, and video ownership:
  - kept public UI in the theme;
  - kept content contracts in plugins;
  - preserved Classic Editor policy unless explicitly opted into Gutenberg.
- Salvaged and restructured selected Kinder/Project content.
- Added and refined publication/video landing and timeline authoring work.

## 2026-06-06

- Added Sammlungen media/template transfer artifacts:
  - created SQL and upload delta artifacts for media sync;
  - adjusted public template behavior for collection pages.
- Migrated Ausstellung backend metadata:
  - added SQL cleanup/migration artifacts;
  - aligned backend metadata with frontend requirements.

## 2026-06-05

- Established the repository documentation model:
  - current checkpoint in `handoff_CURRENT.md`;
  - durable history in `CHANGELOG.md`;
  - active follow-up in `TODO.md`;
  - detailed operational docs under `docs/`.
- Prepared Repair Cafe and Sammlungen media/content transfer artifacts.
- Added operational closeout conventions for staging/local exchange.

## 2026-06-04

- Prepared staging content delta artifacts and supporting transfer notes.
- Continued front-page, archive, and template-authority checks.

## 2026-06-03

- Prepared production transfer artifacts for front-page and video transcript sync.
- Fixed front-page/menu-shell/newsletter rendering details.
- Added featured-image fallback behavior and event-template improvements.

## 2026-06-02

- Added local video transcription workflow support for the `video` CPT.
- Improved video block metadata and editor compatibility.

## Older History

- Added `veranstaltung` scheme/meta support and Terminblatt v1 templates.
- Built `register_place` atlas-led dossier templates and supporting patterns.
- Added `projekt` single-shell work and chaptered-prose planning.
- Added brochure/photoalbum publication templates and related-content rails.
- Built publication/video landing and reusable publication timeline authoring.
- Added shared related-content carousel/card contracts.
- Recovered and converted local media assets as needed for theme work.
- Introduced and evolved the ISS content-model, archive, graph, programme, and
  commerce/plugin stack now summarized in the architecture docs.
