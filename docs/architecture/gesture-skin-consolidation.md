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
| Gallery/images| `galerie`, `bildstrecke`, `image_wall`, `autoalbum`, `photoalbum` |
| Essay text    | `fliesstext`, `bericht`                                |
| Facts/stats   | `massstab` (label "Massstab" vs "Merkpunkte")          |
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

## Target: 13 Gestures

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
| 7 | `galerie`      | galerie, bildstrecke, image_wall, autoalbum, photoalbum (layout = grid/sequence/wall is an option) |
| 8 | `vollbild`     | vollbild (distinct from a gallery — kept)                         |
| 9 | `objektfokus`  | objektfokus, quellen (archive-object signature — kept)            |
| 10| `material`     | material                                                          |
| 11| `massstab`     | massstab, "Merkpunkte" (unify the label)                          |
| 12| `programm`     | programm (festival sub-schedule)                                  |
| 13| `upload_intake`| upload_intake                                                     |

**Retired as gestures:**

- `aside` — curator voice becomes a style flag on `kapitel`, not a type.
- `chronik` — this is a skin (timeline posture), not a block.

**Moved to features:**

- `projekt_rail` — rail/navigation is not content. It is a skinnable feature
  that can be enabled or disabled and positioned by skin or document settings.

The two largest wins are #5 and #7: today there are two quote types and four
image types. Collapsing image handling to one `galerie` with a `layout` option
removes the biggest single source of editor confusion and theme branching.

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

**Key move:** `Vortrag`, `Lesung`, `Gespräch`, `Workshop`, `Konzert`,
`Repair Cafe` stop being skins. They remain the **semantic type** — driving the
icon, the display label, and timeline grouping — but render with
`typografisch`. This deletes several skins with no visible change to the site.
A lecture does not need a different layout than a reading; it needs a different
word on the page.

`standard` remains only the generic base fallback where the editor framework
requires one. `industrieakte` was mapped to `quellenbuehne` during the hard
cleanup and is not a canonical skin.

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
```

Concretely:

1. **One `GestureRegistry`** — the 13 gestures, defined once. Replaces both the
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

The current Veranstaltung registry proves the audit point. Most event entities
share `shape = moment`, the same fields, and nearly the same gestures:

```
event.general
event.vortrag
event.gespraech
event.lesung
event.praesentation
event.workshop
event.konzert
event.school_program
```

These should keep their semantic labels and icons, but collapse to the
`typografisch` skin unless the content genuinely needs `buehne`.

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
maps to `chronik`. `event.repair_cafe` keeps its recurring shape, but should
not require a dedicated `repair` skin.

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

`image_wall` folds into `galerie` with `layout = wall`.

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

## Migration mapping (old → new)

Document migration runs against the format-specific `_iss_editorial_*` JSON
documents and Veranstaltung registry `default_skin` assignments.

Gestures:

```
quellenauszug   → zitat            (set source flag)
bildstrecke     → galerie          (layout = sequence)
image_wall      → galerie          (layout = wall)
autoalbum       → galerie          (source = set, auto)
photoalbum      → galerie          (source = set)
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
