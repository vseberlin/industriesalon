# ADR: Skin Contract And Skin Authority

Date: 2026-10-16

## Status

Proposed; not accepted. The
[consolidated delivery plan](../project/gesture-treatment-consolidation-plan.md)
now governs implementation order and acceptance. A visual check alone does not
accept this ADR. Its page-skin role model remains input to that plan; its D1/D6
and migration proposals need the following qualifications:

- Gesture skins are registered treatments and may change structure. Contexts
  declare their availability/defaults; treatments need not be offered everywhere.
- Legacy field combinations require explicit adaptation; `treatment_aliases`
  currently resolves only a treatment slug. Orientation need not become a second
  treatment.
- Inventory active and retained consumers before declaring skin rules dead.
  Existing portable skins include typography/layout as well as colour adapters;
  the context claims below are hypotheses requiring per-consumer verification.

The original proposal follows as design evidence, not an executable migration.
The consolidated plan's local implementation now supplies declared legacy-field
adapters and context-filtered treatments, semantic portable colours and explicit
event skin precedence. It does not accept the older proposal's requirement that
all structural presentation be selected through page skin alone.

Extends `css-layering-adr.md` (layers 3 and 4) and completes the skin half of
`gesture-skin-consolidation.md`. Related audits:
`docs/project/css-system-audit-2026-10-16.md`.

## Context

The founding idea is **one shared gesture, many skins**: a gesture is defined
and rendered once; a skin changes how every gesture reads on a page.

The gesture half is in place:

- One vocabulary: 13 portable gestures (kapitel, zitat, galerie, facts,
  statement, gateway, feature, fliesstext, schluss, material, objektfokus,
  text_bild_reihe, leitfrage) appear in 8 of 9 formats, each with one contract.
- One renderer: since `9eab967` (2026-10-13) every domain renderer calls
  `industriesalon_render_editorial_portable_section()` first, which delegates
  to the landing composition and wraps the output in
  `.iss-landing-editorial--skin-{host skin}`.

The skin half is not:

1. **Skins barely reach gestures.** The only skin values for portable gestures
   are 2–4 colour variables per skin in `editorial-skins.css` (bildmatrix,
   longread-poster, chronik, quellenbuehne, objektalbum). route-dossier,
   compact and ortsdossier set nothing. No skin changes type, measure, rhythm,
   media frame, quote or gallery presentation.
2. **Old skin work is stranded.** The Ausstellung skins were written against
   domain markup with per-skin layouts (`ausstellungen-render.php:190–219`).
   Since the portable dispatch runs first, that branch only renders
   `upload_intake`. 172 of 237 rules in the four Ausstellung skin files target
   markup that is no longer emitted. The per-skin partial lookup
   (`sections/{skin}/part-{type}.php`) finds no files.
3. **Skins branch markup in PHP.** dossier (chapter/facts pairing, context stack
   placement, footer context in `projects-render.php`), buehne (own template
   `single-projekt-stage.php`), bildmatrix (`publications-render.php:1020`),
   frontpage (`landings-render.php:1767`), publication brochure template.
4. **Several skin authorities per page.** Document skin; Veranstaltung entity
   `default_skin` emitted as body class `iss-event-skin-*`
   (`veranstaltungen-registry.php`, `functions.php:844`); legacy skin meta keys;
   template selection.
5. **No skin registry.** The consolidation plan called for one SkinRegistry
   declaring each skin once. Skins are instead listed per format in the theme
   (`editorial-render.php`): 14 slugs, 8 of them available in a single format
   only.
6. **Two treatment mechanisms.** kapitel/zitat/galerie switch presentation
   through fields (`section_treatment`, `quote_treatment`, `gallery_layout`,
   `orientation`, `media_layout`); feature/statement/fliesstext/gateway/
   text_bild_reihe use registered `treatments`. Skins cannot address
   "treatment" uniformly.

Result: one gesture, effectively one look, 14 skin names.

## Decision

### D1. A skin is a value set plus feature defaults

A skin may:

- set the **skin role tokens** below on one document root;
- declare **feature defaults** (rail, context placement, opening, sheet layout);
- add a small number of **signature rules** scoped to its root, each reading
  role tokens and touching no more than the gesture's documented anatomy.

A skin may not:

- change gesture markup in PHP;
- select per-gesture layouts or partial templates;
- restate a gesture's selector tree (structure lives in `gestures.css`).

Anything genuinely structural (a different arrangement of the same content)
becomes a **registered treatment of the gesture**, available in every skin.

### D2. Skin role contract

Every skin sets, or inherits from `tokens.css`, these roles on its root. The
gesture renderer reads only these roles (plus treatment-local variables).

| Group | Roles |
| --- | --- |
| Colour | `--iss-skin-paper`, `--iss-skin-ink`, `--iss-skin-ink-muted`, `--iss-skin-ink-subtle`, `--iss-skin-rule`, `--iss-skin-accent`, `--iss-skin-surface-soft`, `--iss-skin-surface-dark` |
| Type | `--iss-skin-font-display`, `--iss-skin-font-text`, `--iss-skin-title-scale`, `--iss-skin-kicker-style` |
| Layout | `--iss-skin-measure`, `--iss-skin-rhythm` (section spacing), `--iss-skin-gutter` |
| Media | `--iss-skin-media-radius`, `--iss-skin-media-frame` (none, rule, mat), `--iss-skin-caption-style` |
| Gesture moods | `--iss-skin-quote-style` (pull, source), `--iss-skin-gallery-density` |

Rules:

- `--iss-skin-ink-muted` and `--iss-skin-ink-subtle` must pass WCAG AA
  (4.5:1) on `--iss-skin-paper`.
- Values come from `tokens.css` and theme.json presets; skins choose among them
  rather than inventing literals.
- The existing `--iss-landing-*` and `--iss-surface-*` variables become aliases
  of these roles during migration, then retire.

### D3. One skin authority per document

Resolution order:

```
document.skin → domain default skin (format registry) → standard
```

- The Veranstaltung entity `default_skin` becomes the **default for the
  document skin** when a document has none. It stops emitting a separate body
  class.
- Template selection (project Bühne stage, publication brochure) follows the
  resolved skin through a declared feature, not a slug comparison in templates.
- The skin root class is `.iss-skin-{slug}` on the document root, emitted once
  by the shared editorial wrapper. Existing `iss-X-editorial--skin-*` and
  `iss-X-editorial-skin-*` classes remain as aliases until drained.

### D4. One skin registry

Skins are declared once (slug, label, role values handle, feature defaults,
optional template feature) in the theme, as the presentation owner. Formats
list which registered skins they allow; they do not define skins.

### D5. Features carry page-shell behaviour

Behaviour currently hardcoded per skin becomes features with skin defaults and
constrained document overrides, following the existing resolution order
(document override → skin default → domain default → off):

| Current branch | Feature |
| --- | --- |
| dossier chapter/facts pairing | `features.pairing` (facts beside preceding chapter) |
| dossier context stack placement | `features.context` (side, footer, off) |
| buehne stage template and opening | `features.opening` + `features.template` |
| bildmatrix sheet rendering | galerie treatment `galerie.matrix` |
| frontpage composition | `features.template` (front page) |

### D6. One treatment mechanism

Field switches map onto registered treatments via the existing
`treatment_aliases`, without rewriting stored JSON:

- `quote_treatment=source` → `zitat.source`, plus `orientation` → `zitat.source-reverse`
- `section_treatment=aside|disclosure` → `kapitel.aside`, `kapitel.disclosure`
- `gallery_layout=grid|sequence|wall|viewport` → `galerie.grid` … `galerie.viewport`

The renderer emits `iss-gesture--treatment-{slug}` for all gestures.

## Migration

0. **Visual check (gate).** On staging, compare Frauen im Werk (quellenbuehne),
   Kinder im Werk (objektalbum) and a Chronik Rückblick against their intended
   design. Record whether sections render in the landing look. If not stranded,
   revise this ADR before continuing.
1. **Roles in tokens.** Add the D2 roles with current landing values as
   defaults. No visual change.
2. **Gesture renderer reads roles.** Point `editorial-landing.css` and
   `gestures.css` at the roles (via the existing `--iss-landing-*` variables as
   aliases). Emit `.iss-skin-{slug}` and `iss-gesture--{slug}` /
   `iss-gesture--treatment-{slug}` classes additively. No visual change.
3. **Port Quellenbühne** into a role value block plus signature rules, taking
   its identity from `skins/ausstellung-quellenbuehne.css`. Structural parts
   become treatments (D6). Then Objektalbum, Chronik, typografisch, dossier,
   buehne, bildmatrix, longread-poster, route-dossier, compact, ortsdossier,
   territorial, frontpage.
4. **Single authority.** Event entity skin becomes the document default;
   remove the `iss-event-skin-*` body class after porting `buehne` for events.
5. **Features.** Replace the PHP skin branches with D5 features.
6. **Treatments.** Add the D6 aliases; registry and editor show one treatment
   chooser per gesture.
7. **Skin registry.** Move skin declarations to one theme registry; formats
   reference slugs.
8. **Delete stranded code** after each port's visual check: Ausstellung
   per-skin layout map and partial lookup, the unreachable skin rules, native
   renderer branches for portable types after the early return (verify helper
   reuse first, e.g. project gallery and dossier pairing).

Each step: browser check at 390 / 768 / 1280 px for every format that offers
the skin; registry check (`wp iss-editorial registry-check`); stored JSON
unchanged.

## Consequences

- Any skin can be offered to any format without new CSS for its gestures; a new
  skin is a value block plus feature defaults.
- Portable gestures look native in every host page.
- Skin files shrink to values and a few signature rules; dead per-domain
  selector trees disappear.
- Editors see one treatment model per gesture, independent of skin.
- Some current skin effects that depended on custom markup must either become
  treatments (available everywhere) or be dropped. Each such case is decided
  during its port, not silently lost.

## Non-goals

- No change to stored document shape or section identifiers.
- No change to the Place dossier contract (it keeps its native types) in this
  pass; Place may adopt the role contract for its own renderer later.
- No change to public plugin CSS ownership (layer 6 of the layering ADR).
