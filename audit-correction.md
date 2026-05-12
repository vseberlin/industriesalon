# Archive Overhaul Audit Correction

## Purpose

This document corrects and narrows the archive overhaul plan from `audit.md`.

The original audit direction is valid:

- WordPress should remain the editorial/public shell.
- Canonical archive data should move out of `wp_postmeta`.
- Preservation, structured archive data, and public presentation must be separate layers.
- Import and reimport must become first-class workflows.
- Archive media masters must not be treated as normal WordPress attachments.

The correction is mainly about implementation scope.

The proposed model in `audit.md` is conceptually strong, but too broad for a first build. If implemented all at once, it risks becoming a large custom CMS inside WordPress before the migration path is proven.

The safer path is to start with the clearest pain points:

1. collection membership
2. archive object structured metadata
3. archive media references
4. relations
5. source snapshots and reimport
6. place states / epochs
7. assertions and evidence only where needed

## Main Correction

Do not start with a universal `archive_entity` abstraction unless the project is ready to maintain a full custom entity framework.

The shared identity model is elegant, but it adds early complexity:

- generic entity registry
- generic identifiers
- generic relation typing
- generic assertions
- generic evidence
- generic media assets
- generic source references
- more complex migration tooling
- more complex admin UI

That may be useful later, but it should not be phase 1.

Phase 1 should use direct, typed tables with clear ownership.

Recommended initial tables:

```text
wp_iss_archive_objects
wp_iss_archive_collections
wp_iss_archive_collection_members
wp_iss_archive_media
wp_iss_archive_relations
wp_iss_archive_sources
wp_iss_archive_snapshots
```

Optional later tables:

```text
wp_iss_archive_places
wp_iss_archive_place_states
wp_iss_archive_people
wp_iss_archive_organizations
wp_iss_archive_assertions
wp_iss_archive_evidence
wp_iss_archive_identifiers
```

The system can still evolve into a shared entity model later. It should not begin there.

## Revised Architecture

### 1. WordPress Layer

WordPress remains responsible for:

```text
editor accounts
roles and capabilities
public slugs
Gutenberg editorial text
featured images / public previews
publication status
menus and routing
basic SEO
```

WordPress post types remain useful as public/editorial shells:

```text
archivobjekt
archivsammlung
archivbeitrag
register_place
publication
ausstellung
fuehrung
veranstaltung
```

But these posts must stop being the canonical archive database.

### 2. Canonical Archive Layer

Custom tables become responsible for:

```text
archive object metadata
collection ordering
media provenance
source identifiers
import snapshots
relations between objects, places, epochs, people, publications and collections
queryable fields
```

No public template, block, REST route, or admin screen should write directly to SQL.

All reads/writes must go through service classes:

```text
ArchiveObjectService
ArchiveCollectionService
ArchiveMediaService
ArchiveRelationService
ArchiveSourceService
ArchiveImportService
```

### 3. Preservation Layer

Preservation storage remains outside normal WordPress uploads.

Example:

```text
/storage/archive-originals/IS-000123/master.tif
/storage/archive-originals/IS-000123/metadata.json
/storage/archive-originals/IS-000123/checksum.sha256
```

WordPress may receive only public derivatives:

```text
/wp-content/uploads/archive-preview/IS-000123.webp
/wp-content/uploads/archive-thumb/IS-000123.webp
```

Rule:

```text
WordPress publishes archive derivatives.
WordPress does not own archive masters.
```

## Revised Table Strategy

### Phase 1 Table: Collections

Start with collections because the current serialized collection blob is the clearest structural failure.

Current problem:

```text
iss_archive_collection_items
iss_archive_collection_children
iss_archive_collection_source_ids
```

These currently hold large ordered structures inside postmeta.

Replace with:

```text
wp_iss_archive_collections
wp_iss_archive_collection_members
```

Minimum `wp_iss_archive_collections` fields:

```text
id
wp_post_id
collection_key
title
summary
collection_type
source_system
source_id
created_at
updated_at
```

Minimum `wp_iss_archive_collection_members` fields:

```text
id
collection_id
object_wp_post_id
object_id
position
page_label
title_override
caption_override
member_role
source_url
source_id
created_at
updated_at
```

This immediately removes the worst large meta blob and gives a simple verification target:

```text
old collection count = new collection count
old item order = new member order
old caption/title overrides = new member overrides
public collection page unchanged
```

### Phase 2 Table: Objects

Move queryable object metadata out of postmeta.

Minimum `wp_iss_archive_objects` fields:

```text
id
wp_post_id
object_key
inventory_number
source_system
source_id
source_url
title
object_type_key
year_label
sort_year_start
sort_year_end
summary
description
material
dimensions
rights_status
rights_holder
institution_name
content_hash
last_imported_at
created_at
updated_at
```

Do not try to model every possible museum field at first.

Start with fields needed for:

```text
stable identity
faceted browsing
import idempotency
rights handling
basic public display
relations
```

Leave long editorial interpretation in the WP post shell.

### Phase 3 Table: Media

Replace image arrays such as:

```text
iss_archive_object_images
```

with:

```text
wp_iss_archive_media
```

Minimum fields:

```text
id
object_id
wp_attachment_id
role_key
position
storage_kind
master_path
source_url
preview_url
checksum_sha256
mime_type
width
height
original_filename
caption
creator_label
owner_label
rights_status
rights_holder
is_primary
created_at
updated_at
```

Keep this table simple at first.

Do not split into `media_asset`, `media_file`, `media_variant` in phase 1 unless there is already a concrete need.

Later, if media complexity grows, split into:

```text
media_asset
media_file
media_variant
media_link
```

But the first migration only needs one canonical media table.

### Phase 4 Table: Relations

Create a generic but simple relation table.

```text
wp_iss_archive_relations
```

Minimum fields:

```text
id
from_type
from_id
to_type
to_id
relation_type
date_from
date_to
note
source_system
source_id
confidence_key
created_at
updated_at
```

Allowed types can start small:

```text
archive_object
archive_collection
register_place
place_state
publication
ausstellung
fuehrung
veranstaltung
person
organization
```

This table should eventually absorb scattered relation storage:

```text
iss_related_archive_objects
iss_archive_object_places
object-object relation arrays
place relation indexes
publication/object links
exhibition/object links
tour/place/object links
```

### Phase 5 Tables: Sources and Snapshots

Reimport should become explicit after the first canonical object/media/collection tables exist.

Recommended tables:

```text
wp_iss_archive_sources
wp_iss_archive_source_records
wp_iss_archive_source_snapshots
wp_iss_archive_import_runs
```

Minimum `wp_iss_archive_source_snapshots` fields:

```text
id
source_record_id
snapshot_hash
payload_json
parser_version
fetched_at
content_modified_at
created_at
```

Rule:

```text
Every import can be replayed.
Every source payload can be compared.
Every canonical write can be traced back to source input.
```

### Phase 6: Place States

The existing register epoch precedent is important, but it should not be forced into the archive core too early.

Later, unify current state and historical epochs as:

```text
wp_iss_place_states
```

Minimum fields:

```text
id
place_wp_post_id
place_key
period_key
date_from
date_to
phase_name
institution_name
owner_label
operator_label
function_key
function_label
status_key
public_access_key
summary
transition_note
confidence_key
checked_at
is_current
sort_order
created_at
updated_at
```

This should replace the split between:

```text
legacy status
current_status
current_use_type
epoch rows
inferred current state
free-text current use
```

But do this after the archive object/collection migration is stable.

### Phase 7: Assertions and Evidence

Do not build assertions/evidence as a default field system in phase 1.

Use simple canonical fields first.

Add assertions only for facts that are:

```text
disputed
source-critical
multi-valued
historically uncertain
legally sensitive
important enough to cite precisely
```

Example use cases:

```text
conflicting dates
uncertain maker
contradictory place attribution
source-backed ownership transition
oral-history claim vs publication claim
```

This avoids building an academic evidence system before the archive browser has been stabilized.

## Revised Plugin Split

### `iss-archive-core`

Owns:

```text
custom tables
schema migrations
services/repositories
canonical archive REST
import/reimport logic
source snapshots
validation reports
```

### `iss-archive-public`

Owns:

```text
public routes
projection adapters
frontend browser endpoints
public rendering helpers
compatibility with existing archivobjekt / archivsammlung slugs
```

### `iss-archive-editor`

Owns:

```text
admin review screens
collection ordering UI
source diff UI
conflict review
manual correction tools
```

### `iss-relations`

Keep as a separate concern, but narrow the interface.

It should eventually link WP editorial objects to canonical archive IDs, not own all relation logic itself.

Correct long-term role:

```text
editorial relationship UI
relationship discovery helpers
relation projection into WP admin
```

Canonical relation storage should live in the archive core once archive objects are table-owned.

## Revised Migration Plan

### Phase 0: Freeze and Inventory

Do not add new archive structures to postmeta.

Inventory:

```text
all archive meta keys
all archive taxonomies
all public templates
all REST routes
all admin screens
all import paths
all current object/collection counts
all attachment ownership flags
all shortcode/block consumers
```

Output:

```text
archive-contract.md
```

This file must define:

```text
canonical fields
projection fields
frozen legacy fields
source-owned fields
editor-owned fields
derived fields
stable public routes
compatibility rules
```

### Phase 1: Collection Membership Migration

Build:

```text
wp_iss_archive_collections
wp_iss_archive_collection_members
ArchiveCollectionService
```

Migrate:

```text
iss_archive_collection_items
iss_archive_collection_children
iss_archive_collection_source_ids
```

Use dual-read:

```text
read from new table if present
fallback to old meta
```

Do not delete old meta yet.

Success condition:

```text
collection pages render unchanged
order preserved
overrides preserved
load time improves
no new collection writes to blob meta
```

### Phase 2: Object Core Migration

Build:

```text
wp_iss_archive_objects
ArchiveObjectService
```

Migrate core structured object fields.

Do not migrate all obscure meta at once.

Success condition:

```text
object identity stable
source IDs stable
inventory numbers searchable
basic facets no longer depend on meta_query
object page still renders
```

### Phase 3: Media Migration

Build:

```text
wp_iss_archive_media
ArchiveMediaService
```

Migrate:

```text
iss_archive_object_images
main image flags
preview attachment IDs
source image URLs
rights/owner/creator media fields
```

Success condition:

```text
object media gallery renders from table
primary image stable
WP attachment is derivative, not master
source URL preserved
rights preserved per media row
```

### Phase 4: Relation Migration

Build:

```text
wp_iss_archive_relations
ArchiveRelationService
```

Migrate first:

```text
object-object relations
object-place relations
object-collection links not already handled by collection_members
```

Success condition:

```text
related objects resolve from table
place pages can find linked archive objects
archive objects can find linked places
relation queries no longer depend on scattered meta arrays
```

### Phase 5: Query and Browser Rewrite

Replace archive browser meta queries with service-layer queries.

Facets should query indexed columns, not serialized `meta_value`.

Initial facets:

```text
object type
collection
date/year range
rights status
source system
place relation
```

Later facets:

```text
epoch/place state
person/org
function/use type
confidence
source record
```

### Phase 6: Source Snapshot and Reimport

Build source tables and reimport logic after canonical object/media/collection tables are working.

Import flow:

```text
fetch source
store snapshot
normalize
compare
apply canonical updates
preserve editor-owned overrides
write import report
```

Success condition:

```text
same source ID imported twice updates same object
no duplicates
changes are visible before apply
source-owned and editor-owned fields do not overwrite each other accidentally
```

### Phase 7: Place State Unification

Move register epochs and current place state into one place-state model.

Success condition:

```text
one place can have many phases
current state is one phase, not separate flat truth
atlas can filter by epoch/function
single place page can show Zeitschichten
```

### Phase 8: Assertions and Evidence

Add only after the system has clear cases requiring it.

Success condition:

```text
disputed facts can be represented without polluting normal object fields
source-backed transitions can be cited
uncertain facts have explicit confidence
```

## Field Ownership Rules

Every canonical field should belong to one of four ownership classes.

### `source_owned`

Imported from source and overwritten on reimport unless manually protected.

Examples:

```text
source title
source description
source URL
external ID
raw dating label
raw rights label
```

### `editor_owned`

Written by editors and never overwritten automatically.

Examples:

```text
public title override
curated summary
collection caption override
public note
exhibition context
```

### `derived`

Generated by normalization rules.

Examples:

```text
sort_year_start
sort_year_end
object_type_key
slug suggestion
content_hash
facet labels
```

### `conflicted`

Requires review because source and editor data disagree or two sources disagree.

Examples:

```text
conflicting date
conflicting rights status
conflicting creator attribution
conflicting place relation
```

## Public Projection Rules

### `archivobjekt`

Keep as public/editorial shell during migration.

Contains:

```text
post_title
post_name
post_status
editorial intro
featured image derivative
SEO/editorial fields
```

Does not canonically own:

```text
inventory number
source ID
object type
dating
rights
media provenance
collection membership
relations
```

### `archivsammlung`

Keep as public/editorial shell during migration.

Does not own member order canonically after phase 1.

### `archivbeitrag`

Keep as true editorial CPT.

It may link to archive objects, places, collections, publications, and epochs.

It should not become canonical storage for those entities.

### `register_place`

Keep as canonical spatial/editorial shell until place-state model is introduced.

Long term:

```text
register_place = place shell
place_states = changing identity/use over time
archive_relations = links to objects/media/publications/events
```

## Decisions from Original Audit to Keep

Keep these recommendations unchanged:

```text
separate preservation from publication
keep raw source snapshots
keep public slugs stable during migration
stop treating WP attachments as archive masters
make collections table-backed
make reimport idempotent
keep publications editorial
avoid canonical archive facts in publication/tour/exhibition blobs
```

## Decisions to Delay

Delay these until after the first migration succeeds:

```text
universal archive_entity table
full assertion/evidence model
separate media_asset/media_file/media_variant/media_link model
first-class person/org/event entities
full place-state migration
full graph traversal UI
```

These are not rejected. They are deferred.

## Immediate Next Documents

### 1. `archive-contract.md`

Must define:

```text
canonical data boundaries
WP projection boundaries
legacy meta freeze list
field ownership rules
public route stability
minimum public REST contracts
migration compatibility rules
```

### 2. `archive-phase-1-schema.md`

Must define exact DDL for:

```text
wp_iss_archive_collections
wp_iss_archive_collection_members
```

including:

```text
indexes
foreign-key policy or cleanup policy
migration mapping
rollback plan
verification queries
```

### 3. `archive-import-contract.md`

Must define:

```text
source identity
source record identity
idempotency rules
hashing rules
snapshot storage
source-owned/editor-owned overwrite policy
```

## Final Recommendation

Accept the strategic direction of `audit.md`, but reduce the first implementation to a narrow table-backed migration.

The first real target should be:

```text
collections out of postmeta
```

Then:

```text
objects out of postmeta
media out of object image arrays
relations out of scattered meta
queries out of meta_query
imports into snapshots and canonical rows
```

Do not begin by building a universal cultural-heritage entity graph.

Build the smallest canonical archive core that removes the current pain and proves the migration pattern.

Once that works, the larger model can grow safely.


##additional note 


---

## Industriesalon Theme & Plugin Audit

### 1. `functions.php` — Concrete Issues

**Staging path leaked into production** (`functions.php:6-9`)
```php
$industriesalon_fuehrungen_filters_helper = get_stylesheet_directory() . '/assets/css/staging/industriesalon-fuehrungen-filters.php';
if (file_exists($industriesalon_fuehrungen_filters_helper)) {
    require_once $industriesalon_fuehrungen_filters_helper;
}
```
A PHP file living inside `assets/css/staging/` is conditionally required. A CSS directory is carrying executable PHP. This should either become a proper includes file in the theme or move to a plugin.

---

**Two near-identical `template_redirect` hooks** (`functions.php:51-87`)
Both hooks do the same 404 → redirect dance — parse URL, check path, redirect. This pattern should be one function with a lookup table:
```php
$redirects = ['/ueber-uns' => '/about/', '/aktuelles' => '/kalender/'];
add_action('template_redirect', function() use ($redirects) {
    if (!is_404()) return;
    $path = untrailingslashit(wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    if (isset($redirects[$path])) {
        wp_redirect(home_url($redirects[$path]), 301);
        exit;
    }
});
```

---

**Dead branch in `industriesalon_filter_short_post_excerpt`** (`functions.php:622-624`)
```php
if ($layout !== 'short') {
    return (string) $excerpt;
}
```
`'short'` is a legacy alias that `industriesalon_sanitize_post_layout()` normalizes to `'compact'` before it ever reaches this filter. This condition can never be true. The check should be `!== 'compact'`.

---

**Inline JavaScript in PHP heredocs** (`functions.php:519-591`, `822-884`)
Both editor panel scripts (`PostLayoutPanel` and `EventLayoutPanel`) are embedded as PHP heredoc strings. These should live in `/assets/js/post-layout-editor.js` and `/assets/js/event-layout-editor.js` respectively, and enqueued as proper files. This makes them searchable, lintable, and separately cacheable.

---

**Inconsistent asset versioning strategy** (`functions.php:893-1083`)
- `style.css` → uses `filemtime`
- `header.js` → uses `$version` (theme version string from `style.css` header)
- `reading-nav.js` → uses `filemtime`
- `schoneweide.js` → uses `filemtime`

Pick one strategy. `filemtime` is better for cache busting; the theme version only changes on release.

---

**Four-level ternary dependency chain** (`functions.php:947-964`)
The `$page_dependencies` logic assumes the previous stylesheet loaded. If it didn't, it falls back two levels deep. This is hard to follow and should be a simple array built by collecting what actually loaded:
```php
$page_dependencies = ['industriesalon-base'];
if ($cards_loaded) $page_dependencies[] = 'industriesalon-cards';
// ... then pass just the last item as the single dependency
```

---

**`add_editor_style()` loads all page-specific CSS for every editor session** (`functions.php:16-39`)
All 20 stylesheets — `single-tour.css`, `page-archive.css`, `page-ausstellungen.css`, etc. — are registered for the block editor globally. None of these are conditional. The editor loads all of them on every post type. Only global styles (`style.css`, `cards.css`, `patterns.css`, `overrides.css`) should be in this list.

---

**Shortcode in a block theme** (`functions.php:800`)
```php
add_shortcode('iss_compact_header_context', 'industriesalon_render_compact_header_context');
```
This is a full block theme using FSE templates. Shortcodes are a classic-theme pattern. The compact header context should be a server-rendered block registered by the theme, not a shortcode. The renderer function is already well-structured — wrapping it in a block is straightforward.

---

**Conditional styles tied to URL slugs** (`functions.php:975-1062`)
```php
'condition' => is_page('archiv') || is_page('roehren-und-halbleiter') || ...
```
String-slug matching is fragile. If anyone renames a page slug, the CSS silently stops loading. Better to use page templates (already used for `ueber-uns`) or custom meta flags per page.

---

### 2. `theme.json` — Issues

**`contentSize` and `wideSize` are identical** (`theme.json:8-9`)
```json
"contentSize": "1720px",
"wideSize": "1720px"
```
WordPress uses wide alignment as a visual breakout beyond content width. Setting them equal removes the wide-align affordance entirely. If wide alignment isn't used, disable it explicitly; if it is used, give it a distinct value (e.g., `2000px`).

---

**`front-page-test` registered as a production template** (`theme.json:331-339`)
```json
{
  "name": "front-page-test",
  "title": "Front Page Test (Default Layout)",
  "postTypes": ["page"]
}
```
This test artifact is exposed to editors in the template picker. Remove it from `customTemplates`.

---

**No `templateParts` declaration in `theme.json`**
The theme has `parts/header.html` and `parts/footer.html` but they're not declared in `theme.json`. WordPress can still find them, but declaring them enables proper editor labeling and area assignment.

---

### 3. Naming & Structure Inconsistencies

**Plugin prefix split: `iss-` vs `industriesalon-`**
- `iss-` prefix: `iss-content-model`, `iss-fuehrungen`, `iss-programm`, `iss-publications`, `iss-relations`, `iss-wf-import`, `iss-payments-lite`, `iss-media-control`
- `industriesalon-` prefix: `industriesalon-steuerung`, `industriesalon-notices`, `industriesalon-schoeneweide-register`, `saas-api`

Pick one prefix. `iss-` is shorter and consistent with most plugins. `industriesalon-steuerung` and `industriesalon-schoeneweide-register` are the main outliers.

---

**Pattern file naming has four different conventions**
```
pattern-info-panel-anmeldung.html    ← pattern- prefix
iss-section-feature-split.html       ← iss-section- prefix
iss-flex-split.html                  ← iss- prefix (no section-)
archive-landing.html                 ← no prefix at all
page-fuehrungen-template.html        ← page- prefix
```
All pattern files should use one convention. The `iss-` prefix (matching the plugin namespace) is the most consistent choice.

---

**`_archive/` CSS directory alongside active assets**
`assets/css/_archive/` (1.2MB) with subdirectories `staged/`, `staging/`, and empty `test/` sits beside active CSS. These are not used — move them to git history and delete the directory.

---

**Compact CSS variant files with no corresponding load logic**
`style-compact.css`, `cards-compact.css`, `patterns-compact.css` and `theme-compact.json` exist at the theme root, but `functions.php` never references the compact CSS files. Either they're dead (delete them) or they belong to a conditional load path that was never wired up.

---

### 4. Relating to `audit.md` — Archive Architecture

The audit is sharp and the proposed three-layer architecture (`iss-archive-core` / `iss-archive-public` / `iss-archive-editor`) is the right direction. A few additions and alternatives:

**On the plugin split:**
The audit's recommended split is correct. One practical addition: `iss-archive-core` should own a **service locator or DI container** (even a minimal one) so that `iss-archive-public` and `iss-archive-editor` can depend on service interfaces, not concrete implementations. Without this, the split just moves the coupling from one file to plugin-level `require`.

**On the identity spine — `archive_entity` as a shared table:**
The audit proposes a single `archive_entity` table with typed subtypes. This is a sound pattern (Entity-Attribute-Value avoidance). One risk: the `entity_type` discriminator creates a hot row that gets joined by every query. Consider whether `archive_place`, `archive_object`, etc. need the shared `entity_id` or whether each can carry its own UUID. The shared identity only pays off if you have cross-type queries (the relation graph). For the migration phases, adding the shared spine in Phase 1 but making it optional for initial subtypes is safer than requiring it everywhere from day one.

**On `archive_place_state` unifying epochs + current state:**
This is the clearest win in the schema. The current split between the epoch table and flat `Heute` fields is exactly the inconsistency the audit calls out in Finding 7/8. The `is_current` flag on `archive_place_state` is the right mechanism, but add a **unique partial index** on `(place_entity_id) WHERE is_current = TRUE` to enforce single-current-state at the DB level, not just in application logic.

**On `archive_source_snapshot` with `payload_json`:**
Storing full JSON payloads as rows will grow large quickly at 10k+ objects. Consider whether the snapshot store should use a separate storage backend (object storage, a dedicated log table with rotation) rather than the main MySQL `wp_` database. The audit correctly flags this as a preservation concern — a `payload_json` column in the main database is not a preservation backend, it's a staging area.

**On the migration phase order:**
The audit puts collections first (Phase 2) because they're the clearest failure. That's right tactically. However, **Phase 0 (freeze new meta writes) is the most important step** and the hardest to enforce without tooling. Consider adding a `wp_doing_archive_meta_migration` flag and a `_doing_it_wrong()` call on the known meta keys being migrated — this creates noise that catches unreviewed meta writes during the migration window.

**On `iss-relations` widening:**
The audit recommends keeping `iss-relations` and widening it beyond place-only links. This is correct, but the rename should happen before widening: `iss-relations` currently names itself after its narrowest current use. If it becomes a generic editorial-to-entity link layer, renaming it to something like `iss-editorial-links` during the rewrite avoids compounding naming confusion as the codebase grows.

**On WordPress attachments:**
The audit recommends removing WP attachments as canonical media (Review Question E, recommendation: no). Worth making explicit: during migration, keeping the `wp_iss_media_control` ownership flags and transitioning them to `archive_media_link` rows is the cleanest path. Don't delete attachments during migration — just stop treating them as the source of truth and let `archive_media_file` become the record.

---

### Summary of Highest-Priority Actions

| Priority | Item | Location |
|---|---|---|
| 🔴 Fix | Dead branch `!== 'short'` never fires | `functions.php:622` |
| 🔴 Fix | Staging PHP file required in production | `functions.php:6-9` |
| 🔴 Fix | `front-page-test` exposed in editor | `theme.json:331-339` |
| 🟡 Refactor | Duplicate 404 redirect hooks → one | `functions.php:51-87` |
| 🟡 Refactor | Inline JS heredocs → external `.js` files | `functions.php:519-884` |
| 🟡 Refactor | Editor styles load ALL page CSS globally | `functions.php:16-39` |
| 🟡 Refactor | Shortcode → block in a block theme | `functions.php:800` |
| 🟡 Refactor | Inconsistent asset versioning | `functions.php:893-1083` |
| 🟢 Cleanup | Delete `assets/css/_archive/` | theme assets |
| 🟢 Cleanup | Declare `templateParts` in `theme.json` | `theme.json` |
| 🟢 Cleanup | Fix `contentSize` == `wideSize` | `theme.json:8-9` |
| 🟢 Cleanup | Unify pattern file naming | `patterns/` directory |
| 🟢 Cleanup | Standardize plugin prefix | all plugins |
| 🏗️ Architecture | Begin Phase 0 freeze + `iss-archive-core` | per `audit.md` |
| 🏗️ Architecture | Add unique partial index to `archive_place_state` | schema design |
| 🏗️ Architecture | Evaluate snapshot storage backend vs MySQL | `iss-archive-core` design |