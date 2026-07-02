# Intentional Vanilla Fallback Mode SOW

Date: 2026-07-02

Companion decision record: `docs/architecture/vanilla-fallback-mode-adr.md`.
Operator runbook: `docs/runbooks/fallback-mode.md`.

## Purpose

Implement a deliberate fallback layer that lets the site operate as a simple
WordPress site if the custom architecture can no longer be maintained. The
canonical architecture stays authoritative in normal mode. A projection layer
keeps visitor-facing core content available as native Posts/Pages with
categories, excerpts, featured images, statuses, and menus.

## Locked Decisions

- Implementation lives in `iss-content` (`includes/fallback-*.php`), not the
  theme, and not MU plugins in v1.
- Grouping uses native Categories. No custom fallback taxonomy. Source
  identity lives in post meta only.
- Fallback Category slugs use the `iss-...` prefix, not `fallback-...`, to
  avoid collisions while staying usable after first-party plugins are gone.
- Projectors register through a filter so owning plugins contribute their own.
  Publikationen and Rückblicke are separate projectors.
- Generated objects are `draft` while fallback mode is off. The mode switch
  flips generated objects between `draft` and their intended public status and
  never touches canonical content.
- Fallback public routing reuses `iss_content_model_page_claims_public_slug()`
  for the landing route slugs. No template-redirect layer.
- The projection sweep is scheduled, batched, and integrated with `iss-core`
  status/backfill flows.
- Generated bodies contain no occurrence dates, no dynamic `iss/*` block
  markup, no shortcodes, no skin classes, and no canonical link in the public
  body. The canonical URL stays in meta and admin UI.
- This work requires a `CHANGELOG.md` entry recording the no-parallel-systems
  check for any new service, table, or route it adds.

## Non-Goals

No replacement of CPTs, editorial JSON, relations, occurrences, archive or
register data, media intake, commerce, renderers, or skins. No sync-back from
fallback objects into canonical structures. No MU-plugin hardening beyond
documenting what should later move there.

## Deliverables

### 1. Category Seeding and Origin Contract

Seed native Category terms idempotently. Existing terms with matching slugs
are reused, never duplicated.

| Label | Slug |
| --- | --- |
| Veranstaltungen | `iss-veranstaltungen` |
| Führungen | `iss-fuehrungen` |
| Ausstellungen | `iss-ausstellungen` |
| Projekte | `iss-projekte` |
| Publikationen | `iss-publikationen` |
| Rückblicke | `iss-rueckblicke` |
| Aktuelles | `iss-aktuelles` |
| Seiten | `iss-seiten` |

Post meta contract for generated objects:

- `_iss_fallback_origin = generated`
- `_iss_fallback_source_type`
- `_iss_fallback_source_id`
- `_iss_fallback_source_hash`
- `_iss_fallback_last_projected_at`
- `_iss_fallback_canonical_url`
- `_iss_fallback_projector_version`
- `_iss_fallback_public_status` (intended status when fallback mode is on)

Fallback-native objects carry `_iss_fallback_origin = fallback-native` and
are never overwritten or deleted by projection. On save, an unlinked normal
Post/Page assigned to one of the `iss-...` fallback Categories should be
marked fallback-native automatically.

Helpers live in `includes/fallback-projection.php`; list-table origin column
and admin marking live in `includes/fallback-admin.php`.

### 2. Read-Model Vanilla Serializer

Named deliverable, not an implementation detail. A serializer in
`iss-content` consumes `iss_editorial_get_read_model()` and emits plain
durable HTML: core paragraph, heading, list, and image markup only.

Requirements:

- per-section-type serialization keyed to the registered format/section
  vocabulary (`iss_editorial_formats`)
- safe default for unknown section types: emit text content, drop layout,
  treatments, and slots
- `dynamic_slot` emits nothing
- media only via already-promoted `media_refs` resolved to attachments
- `rueckblick` is the acceptance case: it has no `post_content`, so its body
  comes from the serializer

### 3. Projector Registry

Filter-based registry, working name `iss_fallback_projectors`. Each projector
returns a normalized projection object: source type, source ID, target post
type (`post` or `page`), title, slug, excerpt, body, intended public status,
publication date, featured image attachment ID, Category slugs, canonical URL,
source hash.

V1 projectors:

- Veranstaltungen (`iss-content`, via the shape-aware repository)
- Führungen (`iss-content`): one post per `fuehrung` post; body from
  `post_content` plus serialized sections; a static booking line with link;
  never occurrence dates
- Ausstellungen (`iss-content`)
- Projekte (`iss-content`)
- Rückblicke (`iss-content`, serializer-only body)
- Publikationen (registered by `iss-publications`)

V1.1 candidates: Videos, selected landing pages, Aktuelles (registered by the
owning plugin if present).

Body composition rule for CPTs with `post_content`: reuse `post_content` with
dynamic `iss/*` blocks stripped, then append serialized sections and a plain
facts block.

### 4. Projection Service

Modes: dry run, project one type, project all v1 types, rebuild generated
objects. Every run reports create/update/unchanged/skipped/error counts.

Rules:

- lookup key is `_iss_fallback_source_type` + `_iss_fallback_source_id`, but
  only among `_iss_fallback_origin = generated`
- never create a second generated object for the same source
- update when the source hash differs or the projector version differs
- never touch fallback-native objects
- bounded batches with a per-run cap; the sweep resumes on the next run
- publication date maps to canonical publication date; never map event start
  dates to `post_date`
- featured images reuse existing attachment IDs; no sideloading in v1

Deletion policy: when a canonical source disappears or becomes non-public, set
the generated object to `draft` and `_iss_fallback_stale = 1`. Hard deletion
is a separate manual admin/CLI operation.

### 5. Scheduled Sweep and `iss-core` Integration

- scheduled sweep, daily by default, keeps generated objects fresh
- no projection writes during ordinary public page loads
- background work yields to core site functionality
- `wp iss status` reports projection counts, last run, stale count, and mode
- `wp iss backfill-all` can run the projection/backfill path

If `iss-core` does not already expose reusable extension hooks for status and
backfill registration, this implementation must either add those hooks or
extend `iss_core_status_report()` and `iss_core_backfill_all()` directly.

### 6. Capabilities and Option

Register in the `iss-core` capability map, owner `iss-content`:

- `iss_run_fallback_projection`
- `iss_manage_fallback_mode`

Option `iss_fallback_mode_enabled`, default false, named per `iss-core`
schema/option conventions. Mode changes are logged with previous mode, new
mode, timestamp, and actor user ID.

### 7. Admin Safeguards

- generated objects: admin notice "Generierte Fallback-Projektion.
  Änderungen werden überschrieben." with canonical source link
- origin column (generated / fallback-native) on Posts/Pages list tables
- edit warning on generated objects
- non-admin editors cannot edit generated objects
- action "Create fallback-native copy" for intentional manual divergence
- status screen: current mode, last sweep, counts, dry-run button, run button,
  enable/disable with explanatory warning

### 8. Fallback Mode Switch Behavior

Enable:

- record current primary navigation assignment and front-page settings before
  changing them
- transition generated objects from `draft` to `_iss_fallback_public_status`
- publish or activate fallback Pages so they intentionally claim landing route
  slugs via the existing page-claims mechanism
- switch primary navigation to the maintained `Fallback` menu
- set the configured fallback-native front Page when required

Disable:

- revert generated objects to `draft`
- restore the recorded normal navigation assignment and front-page settings
- return fallback Pages to their normal-mode non-claiming state
- canonical content and current public behavior remain unchanged

The switch must never activate automatically.

### 9. Fallback Navigation and Front Page

Deliverable, not an assumption: a nav menu named `Fallback` covering the
seeded sections, and a fallback-native front Page, both created or verified by
the projection setup and referenced by the enable path.

The setup must store enough IDs/options to restore normal mode exactly after a
fallback-mode test.

### 10. CLI

- `wp iss fallback dry-run`
- `wp iss fallback project --type=<type>`
- `wp iss fallback project --all`
- `wp iss fallback status`
- `wp iss fallback enable`
- `wp iss fallback disable`

All commands print counts and errors.

### 11. Operator Runbook

Maintain `docs/runbooks/fallback-mode.md` with:

- what normal and fallback mode are, when to use fallback
- how to run projection and read counts
- why generated posts must not be edited
- how to create fallback-native posts
- how to enable/disable and return to normal mode
- the zero-plugin path: if first-party plugins are already deactivated,
  filter the Posts list by the native `iss-...` Categories and bulk-edit to
  Published; assign the `Fallback` menu; set the fallback front Page
- what later MU-plugin hardening may contain

## Test Plan

- dry-run reports plausible create/update/skip counts before first write
- project one sample per v1 family; verify title, slug, excerpt, body,
  featured image, Category slug, source meta, status `draft`, canonical URL
  meta
- re-run projection: zero duplicates, zero updates on unchanged sources
- change canonical content, re-run: generated object updates, hash advances
- bump a projector version, re-run: affected objects update
- `rueckblick` sample renders a coherent body from the serializer alone
- `fuehrung` sample contains booking link and no occurrence dates
- generated bodies contain no `iss/*` block markup, shortcodes, or skin
  classes
- edit a generated object: warning shown; non-admin editors blocked
- fallback-native post with an `iss-...` Category survives projection
  untouched
- unpublish a canonical source: generated object becomes `draft` and stale
- enable fallback mode: generated objects published, landing slugs claimed,
  Fallback menu active, fallback front Page active, category archives readable
  with standard templates
- disable fallback mode: generated objects draft again, normal routing,
  navigation, and front-page settings restored

Regression:

- normal-mode CPT archives, single renderers, REST output, and admin screens
  unchanged
- no generated content in normal-mode feeds, search, sitemap, REST, or main
  queries
- no duplicate slugs among generated objects
- no collisions with canonical routes in normal mode
- no projection writes on public page loads
- sweep respects the batch cap

## Phases

1. Contract: Category seeding, origin meta helpers, list-table visibility.
2. Serializer: read-model vanilla serializer with per-section coverage and
   safe default; `rueckblick` as acceptance case.
3. Engine: projector filter registry, dry run, idempotent create/update, hash
   and version staleness, batching, CLI.
4. Projectors: Veranstaltungen, Führungen, Ausstellungen, Projekte,
   Rückblicke, Publikationen.
5. Sweep and integration: scheduled sweep, `wp iss status` /
   `wp iss backfill-all` integration, capabilities, option.
6. Admin protection: warnings, edit gate, fallback-native copy action, status
   screen.
7. Mode switch: status transitions, slug claiming, Fallback menu and front
   Page, enable/disable regression.
8. Documentation: operator runbook, `CHANGELOG.md` entry, plugin-map update.

## Acceptance Criteria

- generated objects are created idempotently, visibly marked, protected, and
  stay `draft` in normal mode
- fallback-native objects are never overwritten or deleted
- dry-run and projection counts are reliable; sweep keeps the snapshot at most
  one day old
- `rueckblick` and `fuehrung` projections meet their specific body rules
- enabling fallback mode produces a coherent vanilla public site using native
  Categories, menus, and standard templates
- disabling fallback mode restores current behavior exactly
- capabilities and status reporting are integrated with `iss-core`
- no custom taxonomy, no theme-owned continuity logic, no MU-plugin migration
  in v1
