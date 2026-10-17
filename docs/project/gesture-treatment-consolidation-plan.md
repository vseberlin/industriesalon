# Gesture treatments and CSS consolidation

Date: 2026-10-17

## Status and scope

Implemented locally on 2026-10-17 after the user's explicit request to execute
end to end. That request superseded the original plan's intermediate review pauses.
The delivered scope covers Zitat, Kapitel and Galerie treatment resolution and
controls, portable colour roles, event skin authority, timeline ownership and
proved-obsolete quotation CSS. Verification and the drain record are below.
Implementation is complete and released through GitHub to staging as `a9dd4a6`;
existing content was not migrated. See [current handoff](../../handoff_CURRENT.md)
for verification and rollback; SQL/content/media artifacts stay outside GitHub.

This is the single delivery sequence for the
[CSS audit](css-system-audit-2026-10-16.md) (`19a697c`), the proposed
[skin contract](../architecture/skin-contract-adr.md) (`a8d6a1f`), and the
remaining [gesture/skin consolidation](../architecture/gesture-skin-consolidation.md).
It supersedes their competing migration orders and the assumptions that every
treatment must be offered everywhere or that page skin alone determines a
gesture's layout. Those documents retain their evidence and historical mappings.
The accepted [CSS layering ADR](../architecture/css-layering-adr.md) and
[editorial ownership](../architecture/editorial-platform.md#ownership) remain
the foundation. The implementation receipt identifies delivered work and
retained compatibility;
the product and ownership rules below govern further additions.

## Product contract

Editors compose with a curated set of designed choices. They can express
editorial intent without choosing arbitrary CSS, fonts, coordinates or layouts.
The collection may grow, but every addition must have a clear purpose and owner.

| Concept | Meaning and authority |
| --- | --- |
| Gesture | Shared editorial intent and content contract: quotation, chapter, gallery, etc. |
| Gesture skin / treatment | One registered presentation of that gesture; these are two names for the same concept, not separate axes. Use `treatment` in implementation. |
| Context | The host format, document/page skin and declared capabilities that determine permitted treatments and defaults. |
| Page skin | Document-wide visual roles and feature defaults; independent of an individual gesture treatment. |
| Bounded option | A declared choice within a treatment, such as image orientation; not automatically another treatment. |
| Feature | Page-shell behaviour such as navigation/rail or opening; not authored gesture content. |

A project quotation and an exhibition quotation may deliberately look and behave
differently. They share the quotation contract and reusable mechanics; their
treatments may differ in composition, framing, typography, source presentation
and responsive behaviour. Context-specific treatments are legitimate. Similar
appearance alone does not justify merging different content sources or business
behaviour, especially publication albums, archive references and related cards.

Normal editors receive relevant labels, short explanations and previews or
schematics, with a useful default and only the permitted options. Use the existing
chooser and inspector. Preserve existing role permissions; define any treatment
control exposure change explicitly in its slice instead of broadly granting access.
Do not add free-form CSS, a second advanced editor or a parallel registry.

## Evidence to use carefully

The audit measured public CSS at `eb14e8b`, excluded admin CSS, and performed no
browser rendering. Counts are a baseline, not current measurements or deletion
proof. Verify actual backgrounds and text sizes before declaring contrast defects.

- **C1 needs qualification:** portable gestures already delegate to the shared
  renderer through `industriesalon_render_editorial_portable_section()`.
  Distinguish active duplication from unreachable branches and retained CSS.
- **C3 needs correction:** `editorial-skins.css` already contained portable
  `.iss-landing-*` adapters at the audited revision. Measure missing coverage
  and lost treatment behaviour rather than assuming all host styling is absent.
- **C2 is about ownership, not visual uniformity:** page-skin role values and
  registered treatment structure are different responsibilities. A low share
  of custom properties does not by itself prove a bad treatment.
- **D3 is a candidate list:** inspect stored JSON, `post_content`, templates,
  revisions/recovery and fallback renderers before removing compatibility.

Audit C4–C6 inform token cleanup; C7/C8 inform asset and timeline ownership;
C9 informs stable hooks; C10 and D1–D4 inform verification and checks. The proposed
150 KB payload and four breakpoints are investigation targets, not acceptance
requirements. Choose responsive boundaries from actual component behaviour.

## Owners and registry rules

Extend the existing `iss_editorial_formats` pipeline:

- `iss-content/includes/editorial-vocabulary.php` and format owners declare the
  semantic contracts, supported fields and context policy.
- `iss-editorial/includes/formats.php` owns normalization, resolution and
  diagnostics. Its existing admin controls consume the effective declaration.
- Theme contributions in `includes/editorial-render.php`, the existing renderer
  helpers and theme CSS own public presentation and assets.
- Domain plugins retain their data, reference, permission and workflow authority.

Declare each treatment once and reference it from contexts. Extend existing
metadata only where the pilot demonstrates a gap. A treatment declaration must
make these facts available, directly or through its existing owner:

1. Stable ID and gesture family; editorial purpose, label and preview/schematic.
2. Compatible contexts/capabilities and the context defaults that reference it.
3. Supported or required information and bounded options, with sensible defaults
   and missing-content behaviour.
4. Renderer/CSS owner, legacy mapping and lifecycle status where applicable.

Before adding a treatment, decide whether it is a distinct composition,
an existing reusable treatment, a bounded option, or a legacy name. Do not create
variants solely for page IDs, item counts, small spacing changes or reversed
orientation. Share an implementation when purpose and behaviour match. Retire
duplicates through declared mappings after checking all consumers; do not delete
old authored data simply because its choice is no longer offered for new sections.

## Resolution and content preservation

The editor, preview and public renderer must consume the same effective contract,
including the **actual host format**, rather than always consulting `landing`.

For declared choices, resolve in this order (known authored treatments remain
readable after a context change even when no longer offered for new selection):

1. Explicit stored `treatment`.
2. Explicit legacy presentation fields, adapted without losing their options.
3. Declared context default, including any permitted page-skin default.
4. Gesture default.

Legacy adaptation must be explicit. The current `treatment_aliases` resolves a
treatment slug; it does not interpret `quote_treatment`, `section_treatment`,
`gallery_layout` or combinations with `orientation`. The existing format normalization now declares those adapters. PHP and the
registry-driven editor consume the same declarations; explicit canonical choices
win conflicts without deleting the retained legacy options.

Preserve hidden fields, captions, citations, media/object references, ordering,
unknown fields and existing recovery/revision data. Switching treatment or page
skin must not silently discard content. An unavailable or unknown stored choice
must remain recoverable: diagnose it and use an explicitly defined safe rendering
fallback without rewriting it to the default on open, preview or unrelated save.
Content required by one treatment may be optional in another; define behaviour
for sparse content without forcing editors to supply irrelevant fields.

Read adaptation is preferred initially. If persisting a new choice needs a schema
change or migration, specify that transition, compatible readers, backup and
rollback before implementing it. Do not bulk-rewrite documents as a CSS cleanup.

## CSS ownership, isolation and draining

| Layer | Owns |
| --- | --- |
| Theme presets and semantic tokens | Shared colour, font and spacing meanings; no token for every local measurement. |
| Existing primitives | Reusable buttons, cards, media and other shared mechanics. |
| Gesture anatomy | Common semantic slots and default local values. |
| Registered treatment | Its composition, typography, framing, responsive rules and interaction presentation. |
| Page skin | Scoped document role values and declared defaults; structural gesture variants resolve to treatments. |
| Compatibility | Precisely scoped remaining legacy consumers, with an owner and removal condition. |
| Admin controls | Independent scoped system typography and UI rules; public preview uses the theme contract. |

Use stable gesture and treatment hooks on the owning section root. The exact hook
mapping is a pilot deliverable; reuse existing stable hooks where sufficient.
If neutral hooks are needed, add and drain them within the slice, not across every
renderer in advance. Scope treatment rules and variables to their owner, prevent
leakage into neighbours/nested components, and keep context selection out of CSS
page-ID/body-selector forks. Avoid specificity escalation, `!important`, inline
style fixes and resets that erase intended page-skin inheritance.

Shared anatomy may live in `gestures.css`; that does not mean all treatment and
domain CSS should become globally loaded. Reuse the existing enqueue mechanism,
accounting for portable gestures in every host, authenticated preview and fallback
templates. Theme-owned styling remains in the theme.

For **each** slice, record a compact drain table:

| Existing selector/helper/asset | Actual consumers | Decision and new owner | Removal evidence or compatibility exit condition |
| --- | --- | --- | --- |
| Filled from the slice inventory | Include stored/fallback consumers | Keep, consolidate, replace or retire | Named checks and remaining dependency |

Move reusable rules, delete the replaced declarations and retire unused helpers,
classes and enqueue dependencies together where their consumers are gone.
Temporary compatibility needs a named dependency, verification and removal
condition. Smaller page files alone are insufficient if the same duplication
has moved into global CSS. Report both ownership and loaded-asset changes.

## Delivered slices

| Slice | Delivered result |
| --- | --- |
| S0 | Inventoried 180 documents, all posts/revisions and metadata consumers, template authority and 17 live quotations in four documents. A disposable project supplied the missing project quotation case. |
| S1 | Four registered quote treatments, contextual choices/defaults, shared resolution and neutral quote anatomy; old exhibition/event/tour quote branches and selectors drained. |
| S2 | Kapitel and Galerie use the same registered chooser and resolution, including project disclosure pairing and the media picker. Native Place gallery keeps its owner; publication album sources/sheets keep their distinct semantics. Facts already uses the shared contract and needs no artificial treatment catalogue. |
| S3 | Portable defaults consume existing semantic surface roles; domain palette adapters have safe fallbacks. Events use explicit document skin before entity defaults in editor, preview, shell and content. New/legacy documents without a skin receive the entity default. Existing rail/opening/identity declarations and their owner validators remain authoritative. |
| S4 | Timeline bridge moved from global patterns to the conditional timeline owner, duplicate declarations removed and enqueue order made independent of hook timing. Fourteen palette/spacing aliases now read theme presets. Undefined event foreground/paper reads use paired semantic surface roles. Scoped ownership gates added. |

This is consolidation of the above contracts, not a claim that every historical
CSS audit candidate has been redesigned. The remaining front-page gateway/feature,
rental “Der Ort”, Place/Repair pattern groups and other non-quote exhibition rules
retain their current owners. Further ports must repeat the consumer inventory and
drain gate; publication albums, archive-query cards and relation-driven cards
must not be merged merely because they look similar. No breakpoint catalogue or
150 KB target is imposed: the changed source layout wraps at its tested 48rem
boundary. No global contrast change was made from the audit's white-background
assumptions; the affected captions were checked on their actual surfaces.

## Acceptance for every implementation slice

- **Creative choice:** suitable choices and defaults for each context; meaningful
  project/exhibition differences retained; no arbitrary layout/style controls.
- **Content:** opening, switching, reordering, save/reload, recovery and revision
  restore preserve data; sparse and legacy documents have defined behaviour.
- **Rendering:** preview and public output resolve the same treatment; check all
  affected consumers, adjacent differing treatments, missing media and fallback
  templates. Check stored template authority/block validation when relevant.
- **Responsive and accessible:** test affected layouts at 390/768/1280 px and
  the actual wrap boundaries; no horizontal overflow or clipped references;
  verify keyboard/focus/semantics and contrast on actual surfaces. Include editor
  short-height/narrow-window checks when controls change; record browser coverage.
- **Drain:** every replaced rule/helper has a disposition; no parallel active
  implementation; compatibility is explicit; scoped CSS/JS and enqueue ownership
  are verified. No unrelated gesture or domain data model is collapsed.
- **Checks:** use the repository verification matrix and existing targeted
  suites. Extend `editorial-registry.php` and `editorial-ui.cjs`; use relevant
  validation, lossiness, storage and revision checks when those paths change.
  Record exact commands/results and browser evidence, not a claimed blanket pass.
- **Release:** document code-only vs schema/content/media dependencies and a
  rollback path. If data changes are necessary, pair owner-approved migrations
  and uploads artifacts. Commit, push and deployment are separate requested work.

## Implementation receipt

### Registry and persistence

| Gesture | Registered treatments | Legacy adapter / bounded options |
| --- | --- | --- |
| Zitat | `zitat.pull`, `zitat.source`; exhibition contexts additionally `zitat.display`, `zitat.source-stage` | `quote_treatment`: pull/source. `orientation` remains an independent bounded option. |
| Kapitel | `kapitel.standard`, `kapitel.aside`, `kapitel.disclosure` | `section_treatment`: standard/aside/disclosure. |
| Galerie | `galerie.grid`, `galerie.sequence`, `galerie.wall`, `galerie.viewport` | `gallery_layout`: grid/sequence/wall/viewport. Media and references are retained when a treatment displays fewer items. |

The effective format registry adds `legacy_treatment_field`, each treatment's
`legacy_value`, and optional `treatment_contexts[skin]` (`allowed`, `default`,
`legacy`). The theme declares the two exhibition treatments once in its existing
format contribution; contexts reference their IDs. Quellenbühne, Objektalbum and
Chronik adapt legacy pull/source to display/source-stage. Standard and
Typografisch offer the two common choices. Other hosts keep their own defaults.

`iss_editorial_section_presentation()` creates a transient renderer view using
the actual host definition. `_resolved_treatment` is not stored. Existing stable
`data-section-gesture`, `data-section-treatment` and
`.iss-landing-section--treatment-{id}` hooks remain the treatment contract;
`.iss-editorial-quote*` describes quote anatomy. Chapter mechanics retain native
`details/summary`; gallery mechanics retain the existing strip/viewport owners.

Opening a legacy document does not materialize an absent canonical treatment or
legacy presentation field, so context defaults remain effective. Choosing a
treatment writes its ID and compatible legacy value through the existing save
path. Unknown IDs remain available for diagnosis and private recovery; public
rendering uses the context fallback, and write validation blocks an unknown
choice rather than silently substituting it. Known choices outside the new
context's offered set remain readable and the chooser explains their retention.

### Drain record

| Previous owner / hook | Consumers and disposition |
| --- | --- |
| `quote_treatment`, `section_treatment`, `gallery_layout` hardcoded inspector controls | Replaced by the existing generic registered chooser; retained fields are compatibility data, not a second UI or resolver. Media picker and project disclosure logic read effective presentation. |
| `.iss-landing-quote*` and quote media gallery-track class | Replaced by neutral quote anatomy in the existing renderer stylesheet. No stored post/revision/meta consumers found. |
| Exhibition quote/source-focus skin forks and obsolete exhibition/event/tour quote HTML | All quote-capable declarations dispatch to the shared renderer. No stored consumers of these old quote hooks found. Removed 150 selector entries across four exhibition skins and the three domain stylesheets, plus the dead renderer branches. |
| Gallery `--layout-*` and chapter `--disclosure` section hooks | Replaced by canonical treatment section hooks in emitted markup and owned CSS. Native chapter anatomy remains reusable inside the project chapter/facts composition. |
| Global timeline theme bridge | Consolidated into existing `timeline-skin.css`. It loads only with plugin timeline assets, after shared theme CSS. Plugin structural CSS remains plugin-owned. Checked timeline computed styles match before/after. |
| `.iss-timeline-promo-slot*` | Retained as scoped block compatibility in `patterns.css`: 16 revisions (25790–25806, excluding 25803) contain it. Retire only after that stored composition's supported recovery path is migrated. |
| Literal theme palette / matching spacing values in `tokens.css` | Fourteen aliases now read `theme.json` presets; the unmatched 4rem local spacing value is retained. Existing token names remain compatible. |
| Undefined `--iss-event-ink` / `--iss-event-paper` | Replaced with existing foreground/background roles, including inverse upload-button text and dark event download surfaces. |
| Existing domain skin wrappers, non-quote legacy selectors and unrelated pattern groups | Retained with their current renderers/templates; not declared dead from static audit counts. Future ports must inventory their stored consumers and remove replaced rules in the same change. |

Measured against local HEAD `4e4dbc0`: edited public CSS totals 546,322 →
503,574 bytes (**−42,748 bytes**, uncompressed source, not transfer size).
`patterns.css` falls by 24,035 bytes on every page. The conditional timeline
stylesheet grows by 5,452 bytes; it is absent on the checked project page.
No new runtime stylesheet, dependency or separate registry was introduced.

### Verification and delivery

- Registry/renderer matrix, validation, lossiness, storage and native revision
  suites pass; exact final counts are in `handoff_CURRENT.md`.
- 108 editor interaction checks and 3 CSS ownership gates pass. Coverage includes
  legacy adaptation without hydration writes, context switching, canonical-only
  gallery picker behaviour, focus retention, conflicting legacy values, hidden
  content and revision restoration.
- Full CSS lint (including the ownership gate), scoped JS lint and PHP
  syntax/PHPCS/PHPStan pass. PHPStan excludes the test
  scripts; those execute through WP-CLI.
- Chrome: project, exhibition and dark event fixtures at 390/768/1280 px and
  767/769 px; no page or treatment overflow. Public and embedded preview resolve
  the same choices. Existing Kinder im Werk quotes retain all source images,
  including the two-image station with separate, non-overlapping figures.
- Browser chooser: four relevant exhibition choices; a switch retains text,
  attribution and media, keeps keyboard focus and reaches the saved preview.
  At 900×560 and 1280×620 the header spans the full width, system typography is
  scoped to admin controls, the compact section selector and Fertig remain usable.
- Existing custom `single-ausstellung` template authority is retained. Test
  fixtures, private recovery, revisions and empty Sets are removed. The final
  row/hash comparison confirms all 180 canonical documents, 15,502 post rows,
  238,933 metadata rows (excluding native edit locks/last-editor fields), and
  the three inspected template authorities unchanged.

Evidence and reversible starting copies are ignored local artifacts under
`var/gesture-consolidation-20261017/`. The implementation required no schema bump,
content migration or upload change. Its authorized code-only staging release is
recorded in `handoff_CURRENT.md`, with private `var/gesture-release-20261017/` evidence.
Rollback this change's code delta while preserving the pre-existing editor UI
work; `before.patch` and `working-before/` record that boundary. New authored
choices use an existing schema field plus compatible legacy values, so a later
release must move plugin and theme changes together. Human interactive staging
and cross-browser acceptance remain separate from the automated release checks.
