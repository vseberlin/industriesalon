# CSS System Audit (2026-10-16)

Scope: 39 first-party public stylesheets (theme plus public plugin CSS; admin
CSS excluded), about 45k lines and 5,670 rules at `eb14e8b`. Measured against
`docs/architecture/css-layering-adr.md` and
`docs/architecture/gesture-skin-consolidation.md`.

Method: static parse of rules, custom properties, `var()` reads and raw
values, cross-checked against class names in PHP/JS/HTML/JSON sources, plus
per-file line counts against the ADR commit (`70e27aa`, 2026-07-01). Nothing
was rendered. Contrast figures assume a white background and ignore large
text. Unreferenced classes may still come from stored `post_content`.

Status: findings only. The
[consolidated delivery plan](gesture-treatment-consolidation-plan.md) supersedes
the target/fix order below; `TODO.md` points to its immediate slice.

Review qualification (2026-10-17): C1 overstates separate active rendering;
portable gestures already delegate to the shared renderer. C3's assertion that
no skin file contains `.iss-landing-*` selectors is incorrect even at `eb14e8b`:
`editorial-skins.css` contains portable host-skin adapters. Verify their coverage
and the retained legacy consumers. Counts below remain the original static audit
baseline; intentional context-specific gesture treatments are not duplication
merely because their structure differs.

Local delivery receipt (2026-10-17): the consolidated plan now records implemented
quote/chapter/gallery contracts, removal of obsolete quote renderers/selectors,
event skin authority, conditional timeline ownership and preset aliases. The
edited public CSS is 42,748 source bytes smaller. This does not turn every finding
below into a closed item; retained consumers and verification are listed in the
plan's drain record, rather than inferred from the original static counts.

## Verdict

The concern is confirmed. The gesture and skin vocabulary was consolidated in
the registry, not in CSS. Each renderer still emits its own class family, so
every gesture is restyled once per renderer. The semantic tokens that skins
could set are barely read by renderers, so skins restate selectors instead of
changing values.

Page-file draining since the ADR works (about −5.5k lines), but part of it
landed in the global layer rather than in renderer contracts.

## How it got scattered

### C1. Gestures forked per renderer

Seven renderer class families: landing, ausstellung, project, tour, event,
publication, place.

| Gesture | Files styling it | Parallel components |
| --- | --- | --- |
| intro | 21 | 4 renderer families |
| facts | 14 | 13 (`iss-event-facts`, `iss-fuehrung-facts`, `iss-archive-facts`, `iss-repair-facts`, …) |
| quote | 11 | `iss-quote`, `iss-landing-quote`, `iss-ausstellung-care-quote` |
| gallery | 11 | 8 (landing, project, event, rental, viewport, dense wall, stage, core) |

`gestures.css` (447 lines) is the ADR's universal anatomy layer, but it covers
only stage, viewport gallery, atlas map and upload intake; none of the 14 core
gestures. Example fork: the carousel control-text block is copied identically
into four renderers (`patterns.css` related feed, `editorial-landing.css`
gateway, `single-event.css` gallery, `single-content.css` project gallery).

### C2. Skins are structural forks, not value sets

The ADR says skins should change values, not duplicate the selector tree.
Custom properties are 2–9% of declarations in the skin files:

| File | Declarations | Custom properties |
| --- | --- | --- |
| `skins/ausstellung-quellenbuehne.css` | 401 | 2% |
| `skins/ausstellung-objektalbum.css` | 122 | 4% |
| `skins/ausstellung-typografisch.css` | 142 | 6% |
| `skins/ausstellung-chronik.css` | 142 | 8% |
| `editorial-skins.css` | 457 | 22% |

The same skin is implemented in several places: typografisch (Ausstellung +
`single-event.css`), buehne (`editorial-skins.css` + `single-event.css`),
dossier (`editorial-skins.css` + `single-content.css`), bildmatrix and
longread-poster inside `publications.css`.

"Skin" also names theme overrides of plugin output (`timeline-skin.css`,
`tour-calendar-skin.css`, `fuehrungen-skin.css`). That is a different concept
under the same word.

### C3. Portable gestures do not take the host skin

Landing-rendered portable sections can be inserted into Ausstellung, Projekt,
tour, event and publication documents. No host renderer or skin file contains
a `.iss-landing-*` selector. `editorial-landing.css` reads its own
`--iss-landing-*` variables 108 times and the shared surface tokens 8 times.
Result: two visual languages on one page. This is the most visible source of
inconsistency.

### C4. Semantic tokens exist but are bypassed

- `tokens.css` defines 71 names; 584 distinct `--iss-*` names are defined in
  total, 176 of them per-domain colour roles (`--iss-event-rule`,
  `--iss-ausstellung-ink`, …).
- Surface-token reads per renderer: landing 8, Ausstellung 1, tour 0,
  event 31, publications 16, single-content 35.
- 1,125 raw colour literals, 417 distinct. One ink colour
  (`rgba(30,30,30,α)`) appears in 55 alpha steps (532 uses); muted text
  alone uses ten values between .58 and .78.
- 19 variables have conflicting fallbacks, for example `--iss-red`
  (`#e81d25` vs `#b22`), `--iss-accent` (one fallback is gold `#f2b705`),
  `--iss-header-height` (88px / 5rem / 5.5rem / 4.5rem), `--iss-radius-sm`
  (4px vs 8px).

### C5. Typography and spacing ungoverned

- No type tokens in `tokens.css`; the theme.json `fontSizes` presets are read
  0 times.
- 899 raw `font-size` declarations, 303 distinct values; 201 distinct sizes
  for titles/h1/h2 alone.
- Three serif stacks (hardcoded Georgia ×10, `--iss-landing-serif`, the Plex
  Serif preset); Plex Condensed hardcoded 13 times.
- `--iss-space-*` read 25 times, preset spacing 5 times.
- Same values, shifted names: theme.json `xs` = 0.35rem / `2xl` = 2.5rem;
  tokens `xs` = 0.5rem / `2xl` = 4rem. Two different section-padding clamps
  (theme.json `section` vs `--iss-section-pad-y`).
- Text measures 58, 60, 62, 64, 66, 70 and 72ch.

### C6. Palette has two sources of truth

`tokens.css` repeats the theme.json palette as hex instead of aliasing
`--wp--preset--color--*`, so block editor and frontend can drift apart.

## Load and structure

### C7. Global layer bloated and mislabelled

- Every page loads tokens + `style.css` + cards + primitives + patterns +
  gestures: about 449 KB uncompressed; `patterns.css` alone is 255 KB.
- `patterns.css` holds page and domain code: footer, front-page composition,
  the Repair Café page, the timeline bridge. Lines 5100–7963 are headed
  "FRONT PAGE TIMELINE SECTION" but contain register_place (114 rules), the
  Schöneweide map (100), related feed (78), related place map, publication
  single and reading nav.
- Since the ADR: page files −5.5k lines (`ueber-uns.css` −995,
  `tour-calendar-skin.css` −690, `page-kalender.css` −543, `single-tour.css`
  −514, …; `page-fuehrungen.css`, `page-landing-editorial.css`,
  `page-schoneweide.css` removed), but `patterns.css` +325, `cards.css` +295
  and `timeline-skin.css` +311.

### C8. Timeline styled in three layers

Plugin `timeline.css` (572 lines), the `patterns.css` timeline bridge and
colour schemes (about 370 lines, global) and `timeline-skin.css` (645 lines).
Which rule wins depends on load order.

### C9. Three skin hook conventions

- wrapper `iss-X-editorial--skin-*` (landing, Ausstellung, project, tour)
- body class `iss-X-editorial-skin-*` (Ausstellung, project, publication, tour)
- section `iss-X-editorial__section--skin-*` (project, tour)

CSS targets a mix: Projekt 32 body / 16 wrapper selectors, publication body
only, Ausstellung wrapper. `single-content.css` styles Projekt through 246
`.single-projekt` page-context selectors instead of renderer classes; 410
page-context selectors in total.

### C10. Breakpoints

32 distinct values. `max-width: 1100px` (26) and `min-width: 1100px` (5) both
match at exactly 1100px; the same at 900px. Near-duplicate clusters:
1023/1024, 1080/1100/1160/1180, 720/760/767/780/781/782, 600/640/680.

## Defects

- **D1 Undefined variables.** 24 `--iss-*` names are read but never defined.
  - Visible bug: `--iss-event-ink` (`single-event.css` 481–510, no fallback):
    event download cards lose their accent left border and hover colour.
  - Also unset without fallback: `--iss-event-paper`,
    `--iss-ausstellung-ink` (objektalbum), `--iss-project-rail-muted`,
    `--iss-text`, `--iss-marker-*`, `--iss-anchor-x` (patterns).
  - Renames masked by fallbacks: `--iss-surface`, `--iss-scarlet-red`,
    `--iss-editorial-soft`, `--iss-editorial-dark`, `--iss-archive-card-bg`.
- **D2 Contrast.** 46 text `color` declarations fall below 4.5:1 on white.
  The token `--iss-surface-subtle` (α .56) is itself 3.9:1 and fails WCAG AA
  for small text; α .58 is 4.15:1, α .62 is 4.71:1.
- **D3 Dead code (verify against stored content before deleting).**
  - 37 variables defined but never read, including all 15 legacy `--is--*`
    aliases in `tokens.css`.
  - 98 classes never referenced in code, among them the whole
    `iss-quiet-card` family (`cards.css`), `iss-story-intro` (about 300 lines
    in `patterns.css`), `iss-data-insert`, `iss-marginalia`,
    `iss-divider-note` (`primitives.css`). Check with a `post_content` LIKE
    query first.
- **D4 `!important`.** 23 uses outside admin (`atlas-app.css` 7,
  `patterns.css` 5, `style.css` 4, others 7), against the AGENTS.md rule.

## What is solid

Public plugin CSS follows the ADR (no global token redefinition, no raw hex in
timeline/programm/fuehrungen). Kicker and button systems are unified and
tokenized. Page-file draining is real.

## Target

1. **One contract class.** Every renderer additionally emits
   `.iss-gesture.iss-gesture--{slug}.iss-gesture--treatment-{treatment}`
   (additive; existing classes stay until drained). `gestures.css` owns the
   anatomy of the 14 core gestures.
2. **One skin hook as a value set.** `.iss-skin-{slug}` on the document root
   sets a fixed role list: ink, ink-muted, ink-subtle, paper, rule, accent,
   display font, text font, measure, section rhythm, media radius. Skin files
   shrink to variables plus a few signature rules. Portable gestures inherit
   the host skin automatically.
3. **Tokens.** Type scale aliasing theme.json `fontSizes`; font roles; a
   five-step ink-alpha ladder that passes AA; spacing aliasing the theme.json
   scale; palette as aliases of the presets; drop `--is--*` aliases once
   unused.
4. **Split `patterns.css`.** Move place, publication, related feed/map and
   Repair Café blocks to conditionally loaded files; keep only true shared
   patterns global. Aim for a global payload of about 150 KB.
5. **Four breakpoints** documented as constants (media queries cannot read
   custom properties), for example 600 / 782 / 1100 / 1280.
6. **Lint gate** in `tools/`, dev-only (no runtime build step): undefined
   variables, raw colours outside tokens and skins, new breakpoint values,
   `!important`, unknown `--iss-*` names on `:root`.

## Fix order

1. D1/D2: fix undefined variables and the subtle-ink contrast (cheap,
   visible).
2. Token additions (C4–C6), without changing rendered values yet.
3. Emit the contract class from all renderers (C1, C3).
4. Port quote, gallery and facts first (largest forks), then the rest.
5. Convert typografisch to a value set first (two implementations), then the
   Ausstellung skins (C2, C9).
6. Split `patterns.css` (C7) and collapse the timeline layers (C8).
7. Breakpoint constants and the lint gate (C10, D4); remove dead code after the
   content check (D3).

Each step: browser-check affected templates at 390 / 768 / 1280 px and keep the
change migration-positive per the ADR (touched legacy files shrink).
