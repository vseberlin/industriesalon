# Gesture, Skin, And Feature Consolidation Plan

Date: 2026-06-27

## Purpose

This document proposes a consolidated vocabulary for editorial **gestures**
(content block types), **skins** (whole-page reading or viewing postures), and
presentation **features** across the included editorial domains:
Veranstaltungen, Ausstellungen, Projekte, Rückblicke, and image-led
publications/photoalbums.

Führungen are explicitly excluded from this consolidation pass. The current
Führung JSON/editorial route work remains governed by `editorial-platform.md`
and the tour implementation docs until that surface is reviewed separately.

It is an audit response and planning document. It records the current state,
the proposed target state, and a field-by-field migration mapping. Completed
implementation history belongs in `CHANGELOG.md`; current operational state
belongs in `handoff_CURRENT.md`. For domain ownership see `content-model.md`
and `editorial-platform.md`.

## Problem

The system currently maintains **two parallel and incompatible vocabularies**
for editorial blocks:

- **Veranstaltungen** declare blocks via `allowed_gestures` in
  `plugins/iss-content/includes/veranstaltungen-registry.php`:
  `intro, kapitel, zitat, material, upload_intake, galerie, programm,
  leitfrage, chronik`.
- **Ausstellung / Projekt / Rückblick** declare blocks via the `sections` map
  of `iss_editorial_formats` in `plugins/iss-content/includes/editorial.php`:
  `objektfokus, quellenauszug, massstab, bildstrecke, image_wall, vollbild,
  fliesstext, schluss, aside, bericht, quellen, projekt_rail, …`.

The same file also contains the current `fuehrung` format, but this plan does
not change its gestures, skins, route relation authoring, or public rendering.

The same concept appears under two-to-four different names:

| Concept       | Current names                                          |
|---------------|--------------------------------------------------------|
| Quote         | `zitat`, `quellenauszug`                               |
| Gallery/images| `galerie`, `bildstrecke`, `image_wall`, `vollbild`, `autoalbum`, `photoalbum` |
| Essay text    | `fliesstext`, `bericht`                                |
| Facts/stats   | `facts`, `massstab` (label "Massstab" vs "Merkpunkte") |
| Archive focus | `objektfokus`, `quellen`                               |

Skins are similarly duplicated. Veranstaltungen define `typografisch, vortrag,
gespraech, lesung, festival, repair, dokumentarisch`; the other domains add
`standard, dossier, brief, field, frauen-im-werk, kinder-im-werk,
industrieakte, blueprint-matrix`. Several of these (`standard`, `vortrag`,
`lesung`, `gespraech`, `repair`, `brief`, `field`, `industrieakte`) are either
labels, variants, or implementation stabs rather than durable page designs.

The audit point is correct: many Veranstaltung entries are the same structure
with different wording. That difference belongs in semantic type metadata, not
in separate skins.

## What the system already gets right

The `shapes` abstraction in `veranstaltungen-registry.php`
(`moment`, `span`, `manual_recurring`, `backward`) is a clean model of an
object's time-behavior. It should be **promoted to all domains** rather than
living only inside Veranstaltungen.

## Target: 14 Gestures

One vocabulary, shared by every included domain. The same word means the same
block across those domains. Per-domain config declares a *recommended subset*,
not a separate language.

| # | Gesture        | Absorbs (current names)                                            |
|---|----------------|-------------------------------------------------------------------|
| 1 | `intro`        | intro                                                             |
| 2 | `kapitel`      | kapitel                                                           |
| 3 | `fliesstext`   | fliesstext, bericht                                               |
| 4 | `leitfrage`    | leitfrage                                                         |
| 5 | `zitat`        | zitat, quellenauszug (source becomes a flag, not a 2nd type)      |
| 6 | `schluss`      | schluss                                                           |
| 7 | `galerie`      | galerie, bildstrecke, image_wall, vollbild, autoalbum, photoalbum (layout = grid/sequence/wall/viewport is an option) |
| 8 | `objektfokus`  | objektfokus, quellen (archive-object signature — kept)            |
| 9 | `material`     | material                                                          |
| 10| `facts`        | facts, massstab, "Merkpunkte"                                     |
| 11| `programm`     | programm (festival sub-schedule)                                  |
| 12| `upload_intake`| upload_intake                                                     |
| 13| `gateway`      | curated next-path cards/links for landing pages                   |
| 14| `statement`    | landing-page thesis, editorial intro, or Leitfrage                |
| 15| `feature`      | landing-page highlighted media/text/facts section                 |
| 16| `dynamic_slot` | approved theme-owned dynamic modules for native page landings     |

**Retired as gestures:**

- `aside` — curator voice becomes a style flag on `kapitel`, not a type.
- `chronik` — this is a skin (timeline posture), not a block.

**Moved to features:**

- `projekt_rail` — rail/navigation is not content. It is a skinnable feature
  that can be enabled or disabled and positioned by skin or document settings.

The two largest wins are #5 and #7: today there are two quote types and four
image types. Collapsing image handling to one `galerie` with a `layout` option
removes the biggest single source of editor confusion and theme branching.
`gateway` is added for native landing pages as curated onward navigation. It is
not a generic layout primitive, and it should not spawn count-specific gestures
such as `card_row`, `teaser_grid`, `portal_grid`, or `gateway_3_cards`.
`statement`, `fliesstext`, and `feature` cover landing thesis, longer prose,
and highlighted front-page body sections. The common image-beside-text layout is
a `feature.media-text` treatment, not another gesture. `dynamic_slot` is
only for mapped renderers owned by the theme; it is not a raw block, shortcode,
or arbitrary embed primitive.

## Target: Skins

A skin is the whole-page reading posture, orthogonal to event taxonomy.

| # | Skin           | Absorbs / aliases                                             | For                                            |
|---|----------------|---------------------------------------------------------------|------------------------------------------------|
| 1 | `typografisch` | standard, vortrag, lesung, gespraech, praesentation, workshop, konzert, repair | Text-forward default. Most events, simple pages |
| 2 | `dossier`      | brief, field variants                                         | Structured long-read with optional rail. Projects and dossiers |
| 3 | `quellenbuehne`| frauen-im-werk                                                | Source/image-led exhibition dramaturgy         |
| 4 | `objektalbum`  | kinder-im-werk                                                | Immersive object, image, and quote sequence    |
| 5 | `bildmatrix`   | blueprint-matrix                                              | Matrix/grid treatment for photoalbums and gallery-led content |
| 6 | `buehne`       | festival                                                      | Image/atmosphere-forward events and programmes |
| 7 | `chronik`      | dokumentarisch                                                | Timeline-driven Rückblicke and historical views |
| 8 | `frontpage`    | front-page Gutenberg parity                                   | Native page JSON reconstruction of the current homepage body |
| 9 | `territorial`  | Schöneweide map-led page posture                              | Place, map, and spatial-index landing pages       |

**Key move:** `Vortrag`, `Lesung`, `Gespräch`, `Workshop`, `Konzert`,
`Repair Cafe` stop being skins. They remain the **semantic type** — driving the
icon, the display label, and timeline grouping — but render with
`typografisch`. This deletes several skins with no visible change to the site.
A lecture does not need a different layout than a reading; it needs a different
word on the page.

`standard` remains only the generic base fallback where the editor framework
requires one. `industrieakte` was mapped to `quellenbuehne` during the hard
cleanup and is not a canonical skin.

`frontpage` is intentionally narrow. It is a page-level skin used to carry the
existing hardcoded homepage visual posture into native landing JSON. If its
rules prove useful elsewhere, they can be promoted later; until then, do not
turn individual homepage row spacing into new public gesture treatments.

`territorial` is the map-led landing posture. It controls indexed typography,
rules, spatial card density, and the transition into an operational map. It
does not own Atlas data or create page-specific gestures.

`blueprint-matrix` is a real skin behavior. The current publication renderer
switches photoalbum output into a separate coordinate/matrix treatment, so the
canonical skin should be generalized to `bildmatrix` and made reusable for
gallery-led pages beyond publications.

`photoalbum` itself is not the skin. It is a content shape/source: ordered image
sheets with captions and source context. `bildmatrix` is the viewing posture.

## Target: Features

Features are presentation capabilities controlled by the skin and, where
appropriate, constrained document settings. They are not authored content
gestures.

The first feature to extract is `rail`:

```json
{
  "features": {
    "rail": {
      "enabled": true,
      "placement": "left",
      "mode": "anchor-nav",
      "treatment": "sticky"
    }
  }
}
```

Allowed rail values:

| Field | Values | Notes |
|-------|--------|-------|
| `enabled` | `true`, `false` | Replaces authored `projekt_rail` on/off behavior |
| `placement` | `left`, `right`, `top`, `bottom`, `horizontal` | Skin default, optionally document override |
| `mode` | `anchor-nav`, `section-index`, `contextual` | What the rail lists |
| `treatment` | `quiet`, `card`, `line`, `sticky`, `overlay` | Theme-owned visual treatment |

Resolution order:

```
document feature override -> skin feature default -> domain feature default -> off
```

## Target: Treatments

Treatments are per-gesture rendering choices inside a whole-page skin. They are
separate from `skin`: the skin is the page reading posture, while the treatment
is how one gesture renders inside that posture.

Landing pages start with these gateway treatments:

| Treatment | Purpose |
|-----------|---------|
| `gateway.cards` | Card grid for two, three, or four next paths; count is handled by CSS only. |
| `gateway.link-list` | Compact editorial link list for dense onward navigation. |
| `gateway.feature-strip` | Horizontal feature band for a small set of emphasized next paths. |
| `statement.lead` | Centered editorial thesis/intro treatment. |
| `statement.leitfrage` | Typographic guiding-question treatment. |
| `feature.media-panel` | Bild mit Infokasten; copy, facts, links, and a supporting media panel. |
| `feature.media-text` | Bild neben Text; reusable image-beside-text section for native pages and CPT-style landing copy. Supports compact, balanced, and wide text ratios without creating new treatments. |
| `feature.image-overlay` | Titel auf Bild; image-dominant section with title/body on the image. |
| `slot.projects` | Theme-owned front-page project notes slot. |
| `slot.timeline` | Theme-owned front-page timeline query slot. |
| `slot.visit-info` | Theme-owned front-page visit-info slot. |
| `slot.newsletter` | Theme-owned front-page newsletter slot. |

Treatment names should describe durable visual behavior. Do not add a new
treatment for every card count or one-off page layout.

Examples:

```php
'dossier' => [
    'features' => [
        'rail' => [
            'enabled' => true,
            'placement' => 'left',
            'mode' => 'anchor-nav',
            'treatment' => 'sticky',
        ],
    ],
],
'bildmatrix' => [
    'features' => [
        'rail' => [
            'enabled' => true,
            'placement' => 'top',
            'mode' => 'section-index',
            'treatment' => 'line',
        ],
    ],
],
'typografisch' => [
    'features' => [
        'rail' => [
            'enabled' => false,
        ],
    ],
],
```

## Führungen: excluded from this plan

Führungen already have a current local editorial JSON checkpoint with route,
facts, booking, dates, and related content kept on their existing contracts.
This plan must not remove that surface, migrate its skins, or collapse it into
an occurrence-only template.

Any later Führung simplification needs its own audit against the active tour
renderer, SuperSaaS occurrence projection, route relation fields, and enabled
`_iss_editorial_fuehrung` documents. Until then, Führung remains a separate
follow-up, not part of the gesture/skin consolidation described here.

## Organizing Model: Five Orthogonal Axes

Every editorial object becomes five independent choices:

```
Domain        × Shape           × Skin             × Gestures          × Features
(what)          (object behavior)  (posture)          (building blocks)   (presentation capabilities)

veranstaltung   moment            typografisch        [shared subset]      rail off
ausstellung     exhibition        quellenbuehne       [shared subset]      rail optional
projekt         evergreen         dossier             [shared subset]      rail on
rückblick       backward          chronik             [shared subset]      rail optional
publication     photoalbum        bildmatrix          galerie/material     rail top
page            landing           typografisch/dossier/frontpage/territorial gateway rail off
```

Concretely:

1. **One `GestureRegistry`** — the shared gestures, defined once. Replaces both the
   `allowed_gestures` arrays and the per-format `sections` maps.
2. **One `SkinRegistry`** — the canonical skins, defined once, each declaring
   template, CSS handle, and which gestures it emphasizes.
3. **Per-domain config shrinks to ~5 lines:** `default_skin`, `default_shape`,
   `recommended_gestures` (a soft subset, not a hard wall). The ~100 lines of
   per-domain vocabulary in `editorial.php` collapse away.
4. **Semantic type → metadata,** not skin: icon + label + timeline facet.
5. **Features → presentation controls,** not gestures: rail, context stack,
   index display, and similar UI capabilities are resolved by skin defaults and
   constrained document overrides.

## Veranstaltungen

The Veranstaltung registry now separates structural choices from semantic
labels. `_iss_entity_key` is structural and intentionally small:

```
event.general
event.festival
event.series
```

Semantic labels such as Vortrag, Gespräch, Lesung, Präsentation, Workshop,
Konzert, Film, and Repair Cafe live in the `veranstaltung_art` taxonomy for
search and filters. They do not create their own skins.

Recommended event gesture palette:

```
intro
kapitel
leitfrage
zitat
galerie
material
upload_intake
schluss
```

`event.festival` keeps `programm` and maps to `buehne`. `report.rueckblick`
and `event.school_program` are no longer Veranstaltung structure choices.
Repair Cafe is a semantic label; weekly evergreen Repair Cafe pages use the
`event.series` / `Serientermin` structure and the normal `typografisch` skin.

## Projekte

Project documents already have a good reusable content vocabulary:

```
kapitel
fliesstext
massstab
galerie
image_wall
material
upload_intake
schluss
```

The cleanup is to move `projekt_rail` out of the gesture list and into the
`rail` feature. Its current purpose is to toggle/generated rail behavior across
skins; it has no standalone content meaning.

`dossier` is the real project skin. `brief` and `field` should become variants
or feature presets of `dossier`, not separate canonical skins.

`image_wall` folds into `galerie` with `layout = wall`. `vollbild` folds into
`galerie` with `layout = viewport`. `massstab` folds into `facts`.

## Photoalbums And Gallery-Led Content

Photoalbums need a separate real visual posture. The current
`blueprint-matrix` publication treatment is not just a label; it changes the
renderer into a coordinate/matrix view with album facts, axis labels, source
notes, and context groups.

Generalize it as:

```
blueprint-matrix -> bildmatrix
```

Use `bildmatrix` wherever the primary editorial object is an ordered image set
or gallery-led page. Keep `galerie` as the gesture and `photoalbum` as the
shape/source; the skin decides whether it renders as sequence, wall, carousel,
or matrix.

## Native Landing Pages

Native landing pages stay WordPress `page` posts and use an eligibility-gated
`landing` format rather than a `landing_page` CPT. Eligible pages currently
include the front page, `about`, `verein`, `salon-vermietung`, `sammlungen`,
`fuehrungen`, and `schoneweide`.

The landing registry owns allowed gestures, skins, default skin, and treatment
options. Page meta stores `_iss_editorial_landing`,
`_iss_editorial_enabled_landing`, `_iss_editorial_landing_skin`, and ordered
sections. Each section may store an optional `treatment`.

The `gateway` gesture is the first landing-specific addition to the shared
vocabulary. It stores `kicker`, `title`, `body`, optional `treatment`, and
`items[]` entries with `label`, `text`, `url`, optional `page_id`, and optional
`media_refs`. `feature.media-text` reuses the shared image-beside-text pattern
already common on pages and CPT detail views. The `/salon-vermietung/` “Der
Ort” section maps to this treatment with compact text / larger image ratio,
not to a rental-specific gesture. Native landing pages may also reuse canonical
`fliesstext` for longer text-only sections so `feature` can stay focused on
media/facts.
Theme rendering must emit stable skin, gesture, and treatment classes. Disabled
or empty JSON falls back to the existing page template and post content.

## Migration mapping (old → new)

Document migration runs against the format-specific `_iss_editorial_*` JSON
documents and Veranstaltung registry `default_skin` assignments.

Gestures:

```
quellenauszug   → zitat            (set source flag)
bildstrecke     → galerie          (layout = sequence)
image_wall      → galerie          (layout = wall)
vollbild        → galerie          (layout = viewport)
autoalbum       → galerie          (source = set, auto)
photoalbum      → galerie          (source = set)
massstab        → facts
bericht         → fliesstext
quellen         → objektfokus      (or material if no object_refs)
aside           → kapitel          (voice = curator flag)
projekt_rail    → rail feature     (enabled/placement/mode/treatment)
chronik (gesture) → (removed; becomes chronik skin)
```

Skins:

```
standard        → typografisch
vortrag         → typografisch     (type label retained)
lesung          → typografisch     (type label retained)
gespraech       → typografisch     (type label retained)
repair          → typografisch     (type label retained)
dokumentarisch  → chronik
festival        → buehne
brief           → dossier          (variant/feature preset)
field           → dossier          (variant/feature preset)
frauen-im-werk  → quellenbuehne
kinder-im-werk  → objektalbum
blueprint-matrix → bildmatrix
industrieakte   → quellenbuehne
```

## Implementation status

As of 2026-06-27, the local hard migration has been applied for the included
domains:

- `iss-content` owns the shared gesture, skin, and rail feature vocabulary.
- Project rail authoring moved to `features.rail`; `projekt_rail` is no longer
  a valid project gesture.
- `galerie` now covers sequence, grid, and wall layouts for the included
  domains.
- Ausstellung `zitat` now covers pull quotes and source-focused excerpts via
  `quote_treatment`.
- Ausstellung `kapitel` now covers the curator aside treatment via
  `section_treatment`.
- Rückblick authoring now exposes `fliesstext`, `galerie`, `objektfokus`,
  `material`, and `schluss`.
- Native landing pages use an eligibility-gated extension of the same registry
  model, with `gateway` as the first landing gesture and treatments resolved
  separately from whole-page skins.
- Stored local Ausstellung, Projekt, and publication JSON was migrated to
  canonical section types and skins; the deployable SQL artifact is
  `ops/sql/2026-06-27-editorial-vocabulary-normalized-json.sql`.
- Legacy skin aliases, hidden compatibility sections, old renderer branches,
  the one-off migration CLI command, and the `industrieakte` stylesheet have
  been removed from runtime code.

Still pending:

- promotion of `shape` beyond the Veranstaltung registry;
- whether `programm` should remain authored prose or become an occurrence
  projection;
- whether `bildmatrix` becomes a public skin for non-publication gallery-led
  pages, beyond the current reusable gallery layout options.

## Applied cleanup sequence

1. Introduced the shared gesture, skin, and rail feature vocabulary.
2. Migrated local stored Ausstellung, Projekt, Rückblick, and publication JSON
   to canonical block `type`, `skin`, and `features.rail` values.
3. Wrote paired SQL artifacts for rollback/reference and deployment:
   `ops/sql/2026-06-27-editorial-vocabulary-pre-migration.sql` and
   `ops/sql/2026-06-27-editorial-vocabulary-normalized-json.sql`.
4. Replaced authored `projekt_rail` with `features.rail` metadata.
5. Removed retired gestures, skins, alias helpers, hidden compatibility
   sections, renderer fallback branches, the one-off migration CLI, and the
   `industrieakte` stylesheet from runtime code.
6. Left Führungen untouched in this pass.

## Open questions

- Should `programm` remain a gesture, or become a projection rendered from
  child occurrences? (Festival programmes may be data, not prose.)
- Should `bildmatrix` support non-publication galleries immediately, or first
  stay publication-only while the resolver is introduced?
