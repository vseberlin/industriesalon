# Gesture And Skin Consolidation Plan

Date: 2026-06-27

## Purpose

This document proposes a consolidated vocabulary for editorial **gestures**
(content block types) and **skins** (whole-page reading postures) across all
editorial domains: Veranstaltungen, Ausstellungen, Projekte, Rückblicke, and
Führungen.

It is a planning document. It records the current state, the proposed target
state, and a field-by-field migration mapping. Completed implementation history
belongs in `CHANGELOG.md`; current operational state belongs in
`handoff_CURRENT.md`. For domain ownership see `content-model.md` and
`editorial-platform.md`.

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
`standard, dossier, brief, field, frauen-im-werk`. Several of these
(`standard`, `vortrag`, `lesung`, `gespraech`, `repair`, `dokumentarisch`)
render essentially as text-forward layouts — they are *labels*, not distinct
page designs.

## What the system already gets right

The `shapes` abstraction in `veranstaltungen-registry.php`
(`moment`, `span`, `manual_recurring`, `backward`) is a clean model of an
object's time-behavior. It should be **promoted to all domains** rather than
living only inside Veranstaltungen.

## Target: 13 gestures

One vocabulary, shared by every domain. The same word means the same block
everywhere. Per-domain config declares a *recommended subset*, not a separate
language.

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

**Retired:**

- `aside` — curator voice becomes a style flag on `kapitel`, not a type.
- `projekt_rail` — auto-generated from `kapitel` anchors, never hand-authored.
- `chronik` — this is a skin (timeline posture), not a block.

The two largest wins are #5 and #7: today there are two quote types and four
image types. Collapsing image handling to one `galerie` with a `layout` option
removes the biggest single source of editor confusion and theme branching.

## Target: 5 skins

A skin is the whole-page reading posture, orthogonal to event taxonomy.

| # | Skin             | Absorbs                                                       | For                                            |
|---|------------------|---------------------------------------------------------------|------------------------------------------------|
| 1 | `typografisch`   | standard, vortrag, lesung, gespraech, praesentation, workshop, konzert, repair | Text-forward default. Most events, simple shows |
| 2 | `dossier`        | brief, field                                                  | Structured long-read with rail. Projects, deep exhibitions |
| 3 | `chronik`        | dokumentarisch                                                | Timeline-driven. Historical shows, Rückblicke  |
| 4 | `buehne`         | festival                                                      | Image/atmosphere-forward, full-bleed. Festivals, concerts |
| 5 | `objektzentriert`| frauen-im-werk                                                | Archive-object-led. Signature exhibition mode  |

**Key move:** `Vortrag`, `Lesung`, `Gespräch`, `Workshop`, `Konzert`,
`Repair Cafe` stop being skins. They remain the **semantic type** — driving the
icon, the display label, and timeline grouping — but render with
`typografisch`. This deletes several skins with no visible change to the site.
A lecture does not need a different layout than a reading; it needs a different
word on the page.

`blueprint-matrix` belongs to the publications domain and stays there, outside
this core set of 5.

## Führungen: out of the editorial system

Führungen are occurrence/booking objects (SuperSaaS-backed via
`iss-occurrences` / `iss-frontend`), not long-form editorial. They should not
carry a gesture palette or a skin selector. Model a Führung as:

- one optional `intro`,
- a standing **practical-facts** block (Treffpunkt, Dauer, Preis, Sprache),
- the **booking/availability projection** that already exists in the frontend.

One template, no skin chooser. Keeping tours in the occurrence lane prevents
maintaining an editorial vocabulary for what is operationally a calendar entry.

## Organizing model: four orthogonal axes

Every editorial object becomes four independent choices:

```
Domain        × Shape           × Skin             × Gestures
(what)          (time-behavior)   (reading posture)   (building blocks)

veranstaltung   moment            typografisch        [the 13, shared]
ausstellung     span              dossier
projekt         evergreen         chronik
rückblick       backward          buehne
führung         recurring         objektzentriert
```

Concretely:

1. **One `GestureRegistry`** — the 13 gestures, defined once. Replaces both the
   `allowed_gestures` arrays and the per-format `sections` maps.
2. **One `SkinRegistry`** — the 5 skins, defined once, each declaring template,
   CSS handle, and which gestures it emphasizes.
3. **Per-domain config shrinks to ~5 lines:** `default_skin`, `default_shape`,
   `recommended_gestures` (a soft subset, not a hard wall). The ~100 lines of
   per-domain vocabulary in `editorial.php` collapse away.
4. **Semantic type → metadata,** not skin: icon + label + timeline facet.
5. **Führung → occurrence lane,** out of editorial.

## Migration mapping (old → new)

Document migration runs against `_iss_content_json` block `type` values and the
post `_iss_skin` / `default_skin` assignments.

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
projekt_rail    → (removed; auto-derived from kapitel anchors)
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
brief           → dossier
field           → dossier
frauen-im-werk  → objektzentriert
```

## Suggested sequencing

1. Introduce `GestureRegistry` and `SkinRegistry` as the single source of
   truth; have the existing per-domain config read from them (no behavior
   change yet).
2. Add a `_version` field to `_iss_content_json` documents so the migration is
   idempotent and resumable.
3. Write a WP-CLI migration that rewrites block `type` and skin values per the
   mapping above, dry-run first.
4. Promote `shape` to Ausstellung / Projekt / Rückblick / Führung.
5. Move Führung to the occurrence-only template; remove its skin selector.
6. Remove retired gestures and skins from authoring UI and theme branches once
   no documents reference them.

## Open questions

- Does `objektzentriert` warrant its own template, or is it `dossier` plus an
  object-emphasis flag? (Recommend prototyping before committing a 5th skin.)
- Should `programm` remain a gesture, or become a projection rendered from
  child occurrences? (Festival programmes may be data, not prose.)
