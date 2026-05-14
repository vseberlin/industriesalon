# CSS System Review

## Scope
This audit covers the live `industriesalon` theme CSS stack as wired in `themes/industriesalon/functions.php`, plus the page/pattern markup that forces CSS workarounds.

Reviewed sources:
- global theme layers: `themes/industriesalon/style.css`, `themes/industriesalon/theme.json`
- shared theme assets: `assets/css/cards.css`, `patterns.css`, `overrides.css`, `iss-flex-split.css`
- all currently enqueued page/single CSS files under `assets/css/`
- theme-owned plugin skin files: `timeline-skin.css`, `tour-calendar-skin.css`, `fuehrungen-skin.css`
- page and pattern markup under `themes/industriesalon/templates/` and `themes/industriesalon/patterns/`

Current live stack size is roughly 26k lines of active CSS before archived files.

## Executive Verdict
The system works, but the ownership contract is not coherent enough yet.

The main problems are:
- `patterns.css` is overloaded. It contains shared patterns, homepage layout, rental page layout, projects page layout, calendar page layout, and stale Schoneweide selectors.
- `style.css` is too broad for a base layer. It still imposes page-wide heading and flow behavior through `.page`, `.single`, `.section`, and `body:has(...)` selectors.
- several page stylesheets repeat the same section-shell contract instead of using one shared page-shell pattern
- a lot of layout logic is compensating for Gutenberg inline column widths and raw `wp:html` islands
- the shared card system is real, but several pages still restyle raw `.iss-card` in context instead of using dedicated variants

The result is a theme where layout can look correct but the real source of truth is hard to locate.

## Live Ownership Graph
Base enqueue order in `themes/industriesalon/functions.php:927-974`:
- `style.css`
- `assets/css/cards.css`
- `assets/css/patterns.css`
- `assets/css/overrides.css`
- `assets/css/iss-flex-split.css` on every non-Schoneweide request
- page/single CSS after that

Schoneweide route:
- `assets/css/atlas-app.css` then `assets/css/page-schoneweide.css` in `functions.php:1134-1143`

Theme-owned plugin skins:
- `timeline-skin.css` via `iss_programm_timeline_assets_enqueued` at `functions.php:1229-1245`
- `tour-calendar-skin.css` via `iss_programm_calendar_assets_enqueued` at `functions.php:1247-1263`
- `fuehrungen-skin.css` via `iss_fuehrungen_assets_enqueued` at `functions.php:1265-1281`

Dormant root files not referenced anywhere:
- `themes/industriesalon/cards-compact.css`
- `themes/industriesalon/patterns-compact.css`
- `themes/industriesalon/style-compact.css`
- `themes/industriesalon/test.css`

These should either be archived or deleted. Keeping shadow stylesheets in the live theme root is a maintenance trap.

## Contract Review

### 1. `theme.json` is a token source, not the real layout authority
`theme.json` declares:
- `contentSize` and `wideSize` at `theme.json:7-10`
- spacing scale and `blockGap` at `theme.json:11-61` and `theme.json:171-173`
- palette and type system at `theme.json:63-159`

Real layout authority lives elsewhere:
- `.iss-container` cages width in `style.css:343-350`
- root custom properties duplicate palette and size tokens in `style.css:135-183`
- page and pattern files override spacing directly

Verdict:
- good: `theme.json` is useful for editor tokens
- bad: it does not currently act as the primary layout contract

Target:
- `theme.json` should own editor-facing tokens
- `style.css` should map only the truly global runtime tokens
- page width and section rhythm should not be redefined repeatedly in page files

### 2. `style.css` base layer is too broad
Problem areas:
- `.iss-container` is clean as a base cage at `style.css:343-350`
- `body:has(.iss-page--header-offset)` and `body:has(.iss-verein-page)` drive header mode at `style.css:495-603`
- section/page/single heading alignment is enforced very broadly at `style.css:1386-1495`
- narrative/compact scope modifies container width and spacing globally at `style.css:1801-1845`

Why this is a problem:
- base CSS is reaching into page semantics instead of staying primitive
- pages and components end up inheriting invisible behavior before their own CSS even runs
- header mode is tied to descendant structure instead of an explicit body or page-state class

Target:
- keep tokens, base container, and generic utilities in `style.css`
- move page-mode behavior off `body:has(...)`
- reduce `.page`, `.single`, `.section` heading rules to a smaller explicit section contract

## High-Severity Findings

### 1. `patterns.css` mixes shared patterns with page-local layout
This is the biggest architectural problem.

Homepage-only logic still lives in `patterns.css`:
- `.home .wp-site-blocks` at `patterns.css:591-594`
- `.home .iss-front-page-main` at `patterns.css:598-607`
- `.home #wp--skip-link--target` at `patterns.css:611-613`
- homepage visit strip at `patterns.css:1304-1374`
- homepage card-row tuning at `patterns.css:1385-1533`
- homepage overlay media-text overrides at `patterns.css:1541-1650`

Page-local logic also lives in `patterns.css`:
- rental page shell starts at `patterns.css:4914-4960`
- projects page shell at `patterns.css:5286-5295`
- calendar page shell and timeline styling at `patterns.css:5302-5577`

Stale Schoneweide intro selectors are still present:
- `patterns.css:5924-5990`
- no live template or pattern references remain for `iss-schoneweide-intro__*`

Verdict:
- shared component internals belong in `patterns.css`
- homepage, rental, projects, calendar, and dead Schoneweide page-shell logic do not

### 2. Shared card-grid contract is duplicated inside `cards.css`
`cards.css` defines `iss-card-grid` twice:
- first block at `cards.css:384-463`
- second block at `cards.css:470-520`

This is not just verbose. It makes the canonical behavior unclear:
- both blocks define grid columns and responsive collapse
- the first block is mixed with Gutenberg compatibility
- the second block repeats base layout and Query Loop support

Target:
- keep one canonical `iss-card-grid` block
- keep Gutenberg/Query compatibility immediately under it
- do not split one shared component into two partially overlapping sections

### 3. Page-shell border and kicker contract is duplicated instead of shared
Repeated page-shell logic:
- `page-ausstellungen.css:11-22`
- `page-events.css:24-33`
- `page-museum.css:10-18`
- `page-videos.css:18-28`
- calendar shell logic in `patterns.css:5307-5318`

This is the same pattern:
- top border on non-hero sections
- kicker left rail
- accent color selected per page

Target:
- introduce one shared page-shell contract, for example `.iss-page-shell` plus `--iss-accent`
- page files should only set the accent token and any truly unique background mood

### 4. Repeated page-level `margin-top: 0 !important` hacks indicate a missing header/page offset contract
Examples:
- `page-events.css:19-22`
- `page-museum.css:5-8`
- `page-videos.css:5-12`
- `page-archive.css:31-39`
- rental in `patterns.css:4938-4940`
- projects in `patterns.css:5293-5295`
- calendar in `patterns.css:5302-5304`
- homepage in `patterns.css:611-613`

This should not be solved page by page. The theme is missing a single reliable rule for pages that sit under the fixed header.

## Deep Selectors and Hidden Overrides

### Acceptable bridges
These are deep, but they are solving real markup integration problems:
- notice-banner bridge inside hero slot:
  - `patterns.css:1088-1251`
- Gutenberg Query Loop / Group compatibility for card grids:
  - `cards.css:422-452`
  - `style.css:1629-1639`
- Leaflet marker-container overrides in the standalone atlas app:
  - `atlas-app.css:825-831`

These should still be watched, but they are understandable bridge layers.

### Not acceptable as long-term pattern ownership
- homepage overlay media-text CTA alignment:
  - `patterns.css:1556-1628`
  - selector chain is too page-specific for a shared pattern file
- homepage component tuning under `.home`:
  - `patterns.css:1304-1650`
- page-local rewrites of raw `.iss-card` without a dedicated card variant:
  - `page-fuehrungen.css:90-122`
  - `single-tour-route.css:485-545`
  - `single-content.css:416-427`
  - `single-ausstellung.css:451-464`

Rule:
- deep selectors are acceptable for core/plugin bridge code
- they are not acceptable for ordinary page composition

## Markup That Forces CSS Workarounds

### 1. Inline Gutenberg column ratios are still driving `!important` fights
Templates and patterns use many inline `style="flex-basis:..."` column widths.
That is not automatically wrong, but it becomes wrong when CSS has to fight them back.

Concrete fights:
- `page-fuehrungen.css:133-138` and `page-fuehrungen.css:323-325`
- `page-fuehrungen-mosaic.css:23-28` and `page-fuehrungen-mosaic.css:287-323`
- `ueber-uns.css:156-188`, `ueber-uns.css:294-332`, `ueber-uns.css:491-517`, `ueber-uns.css:588-592`, `ueber-uns.css:962`

This is the clearest sign of a broken contract:
- markup says one ratio through inline styles
- page CSS reasserts another or the same ratio through `!important`

Target:
- if the ratio matters, use a shared grid or shell class
- do not rely on inline Gutenberg flex-basis plus stylesheet `!important`

### 2. `wp:html` islands are still a major source of fragility
Heavy `wp:html` usage remains in:
- `templates/page-schoneweide.html`
- `templates/page-salon-vermietung.html`
- `templates/page-fuehrungen-mosaic.html`
- `templates/page-repair-cafe.html`
- `templates/page-projekte.html`
- matching pattern files for several of those pages

Why this matters:
- it bypasses block semantics
- it makes editor structure inconsistent
- it usually leads to custom CSS that cannot rely on normal block wrappers

### 3. Mixed top-level section markup still leaks into selectors
Example:
- `page-ausstellungen.css:11-13` targets `.section`, `.wp-block-pattern > .section`, and bare `section`

That selector only exists because the page contract is inconsistent.

### 4. Content-structure workarounds using `:has(...)`
Examples:
- hide duplicate exhibition story heading at `single-ausstellung.css:176-178`
- hide empty event media column at `single-event.css:64-66`

These are stopgaps for markup/content-model issues. They should not multiply.

### 5. Gutenberg flow resets that exist only because wrappers are wrong
Examples:
- `page-sammlungen.css:240-242`
- `page-sammlungen.css:258-260`
- `page-sammlungen.css:559-560`

This is a page building its own shell while still inheriting Gutenberg flow behavior it did not really want.

## Card System Review

### Strong shared foundations that should stay
- `iss-card` core in `cards.css`
- `iss-card-grid` in `cards.css`
- `iss-team-card` in `cards.css:936-1030`
- `iss-card-skin--exhibition` in `cards.css:344-360`
- publications cards with dedicated local class families in `publications.css:67-74`, `313-319`, `357-373`, `418-433`

These have a solid foundation because they either:
- define a proper shared family, or
- use dedicated local subclasses on top of shared card anatomy

### Weak local card overrides

#### A. Same accent-card skin duplicated twice
- `single-content.css:409-427`
- `single-ausstellung.css:434-464`

This should become one shared accent skin:
- related-heading kicker accent
- card rail color
- border hover state

#### B. Tours booking cards restyle raw shared cards in context
- `page-fuehrungen.css:90-122`

If this visual style is intentional and reusable, it needs a named card variant. Right now it is just a contextual rewrite of `.iss-card`.

#### C. Tour route related cards are a page-local reskin of `.iss-card`
- `single-tour-route.css:485-545`

This is substantial enough to deserve a proper variant.

#### D. Archive cards are shared in reality, but still owned by the archive page stylesheet
- canonical definitions live in `page-archive.css:603-718`
- reused outside archive on museum page through `page-das-museum.html` and local tweaks at `page-museum.css:117-127`

This is a clear ownership bug.
`iss-archive-card` is no longer page-local. It should move into `cards.css` or a dedicated shared archive-card file.

#### E. About person card is mostly legitimate, except for the post-ID fix
- local subclass behavior is fine in `ueber-uns.css:684-706`
- this is not fine: `ueber-uns.css:694-695`

`post-13082` is not a valid card contract. It is content-specific CSS debt.

### Rule for local card overrides
Local card overrides should exist only when all of these are true:
- the module has its own local root class
- the markup or behavior is genuinely page-specific
- it does not target bare `.iss-card` without a dedicated subclass
- it does not use post IDs, page IDs, or `!important` to win layout fights
- it cannot be expressed as a shared card skin or variant

By that rule:
- `publications.css` is mostly good
- `iss-about-person-card` is mostly good except for the post-ID fix
- `page-fuehrungen.css`, `single-tour-route.css`, `single-content.css`, and `single-ausstellung.css` are weaker than they should be

## Common Patterns Repeated Locally

### 1. `iss-story-intro` is shared, but the events page still ships a bespoke duplicate
Shared intro system:
- `patterns.css:782-943`

Live shared usage:
- `page-schoneweide.html`
- `page-ueber-uns.html`
- `page-ausstellungen.html`

Bespoke duplicate still present:
- `page-veranstaltungen.html:36-68`
- `page-events.css:35-108` and responsive follow-ups at `page-events.css:285-340`

Verdict:
- `iss-events-context` should be replaced by the shared `iss-story-intro` contract

### 2. Old Schoneweide intro selectors remain in shared CSS with no live users
- `patterns.css:5924-5990`
- no live template/pattern references were found for `iss-schoneweide-intro__*`

This is dead shared CSS and should be removed.

### 3. Info-panel column ratios are repeated in markup everywhere
Repeated inline ratios:
- `34/66` info panels across many templates and patterns
- examples in `templates/page-fuehrungen.html`, `page-veranstaltungen.html`, `page-projekte.html`, `page-repair-cafe.html`, `page-sammlungen.html`, `page-ueber-uns.html`, and several pattern files

The shared `iss-info-panel` system already exists in `patterns.css:3052-3470`, but the column ratio is still editor-inline instead of contract-owned.

This is a strong unification candidate:
- move panel ratios into the component contract
- stop repeating inline `flex-basis:34%` and `66%`

## File Ownership Leaks and Copy/Paste Artifacts
- `page-ausstellungen.css:821-830` contains `iss-repair-page` rules

That is not a harmless comment typo. It is page ownership leakage inside the wrong stylesheet.

## Smaller Findings
- `iss-flex-split.css` is globally loaded for every non-Schoneweide request in `functions.php:968-974`, but actual usage is limited to two pattern files
- `front-page.css` is relatively clean; the real homepage problem is that too much `.home` logic still lives in `patterns.css`
- `page-schoneweide.css` is in a healthier state than before. It mainly styles local pre-app modules and uses variable-based tweaks on shared structures instead of overriding the hero contract
- `overrides.css` is disciplined and should stay narrow; it currently looks like a valid runtime/vendor patch layer

## Proposed Target System

### `theme.json`
- editor tokens only
- no expectation that it owns runtime layout directly

### `style.css`
- global tokens
- container and section primitives
- header/page state contract
- small global utilities
- no page selectors like `.home`, `.page`, `.single` unless they are truly universal runtime rules

### `cards.css`
- all shared card families
- all shared card skins
- Query Loop / Gutenberg bridge rules for cards

### `patterns.css`
- shared non-page pattern systems only
- no homepage shell
- no rental/projects/calendar page layout
- no dead Schoneweide intro legacy

### `page-*.css`
- page composition only
- local module styling only
- no overrides of shared hero, shared card, or shared heading internals unless done through explicit local variants or custom properties

### `overrides.css`
- vendor/runtime emergency fixes only

## Priority Refactor Plan

### Phase 1: Stop hidden ownership drift
1. Move homepage shell logic from `patterns.css` into `front-page.css`
2. Move rental/projects/calendar page shells out of `patterns.css` into dedicated page files
3. delete dead `iss-schoneweide-intro__*` selectors from `patterns.css`
4. remove the stray `iss-repair-page` stub from `page-ausstellungen.css`
5. consolidate the duplicated `iss-card-grid` blocks in `cards.css`

### Phase 2: Unify repeated contracts
1. create one shared page-shell accent contract for border-top plus kicker rail
2. replace `iss-events-context` with `iss-story-intro`
3. move `iss-archive-card` out of `page-archive.css` into a shared card layer
4. create a shared accent-related-card skin so `single-content.css` and `single-ausstellung.css` stop duplicating the same idea
5. move info-panel width ratios out of inline template markup into the component contract

### Phase 3: Remove layout fights
1. replace inline flex-basis plus `!important` patterns in:
   - `ueber-uns.css`
   - `page-fuehrungen.css`
   - `page-fuehrungen-mosaic.css`
2. replace `body:has(...)` page-header mode with an explicit body/page-state class
3. replace repeated `margin-top: 0 !important` page fixes with one global header-offset contract

### Phase 4: Pay down markup debt
1. reduce `wp:html` islands on the biggest offender pages
2. normalize top-level sections so selectors no longer need `.wp-block-pattern > .section`
3. stop rendering empty wrappers that require `:has(...)` cleanup

## Final Verdict
This is not a broken theme. It is a theme with a real shared system that has been allowed to drift.

The strongest shared pieces already exist:
- container and token layer
- card family
- hero family
- story intro family
- info-panel family

The cleanup is now mostly about enforcing ownership:
- page CSS should stop overriding shared primitives
- `patterns.css` should stop pretending to be a page stylesheet
- markup should stop forcing CSS to fight Gutenberg inline layout and raw HTML islands

If the goal is "no hidden overrides, no workarounds, no deep selectors, coherent and logical system," the next decisive wins are:
- split page shells out of `patterns.css`
- unify the repeated page-shell accent contract
- stop the inline flex-basis plus `!important` pattern
- promote the reused card families to named shared variants instead of contextual rewrites

---

## Peer Review: Cleanup Commit Audit (3ed2211)

Commit `3ed2211` ("Audit CSS system and clean theme plugin assets") was the first concrete response to this review. The following section evaluates what it did against the priority refactor plan above, notes what is now closed, and flags what remains open or was introduced as new debt.

### What was deleted

**Orphaned compact CSS stack** — resolved.
`style-compact.css` (1,520 lines), `cards-compact.css` (1,632 lines), and `patterns-compact.css` (6,676 lines) were all removed. These were not enqueued anywhere and posed a maintenance trap. Removal is correct. No replacement is needed.

**`_archive/` directory** — resolved.
The entire contents of `assets/css/_archive/` were deleted: all staged experiment files, the staging organization drafts including `CSS-SYSTEM-MANUAL.md`, `unused.css` (1,211 lines), `style.css-orig` (552 lines), and HTML test snapshots. The README that documented the archive is also gone. This is clean. The development history now lives in git rather than in tracked files.

**`test.css`** — resolved.
The 38-line scratch file at the theme root is gone.

**Two ACF block plugins** — resolved.
`acf-field-group-block` and `acf-field-group-block-plugin1` were removed entirely, taking 87 and 80 lines of plugin CSS with them. These were dead weight; the plugins were not referenced from active templates.

**`industriesalon-notices/assets/example-banner.css`** — resolved.
Removed. The block's live `style.css` remains.

**Binary asset bloat** — resolved.
Map image exports, zip archives, and backup template files were removed from the tracked tree. Correct; these should not be in version control.

---

### What was added or changed

#### `iss-story-intro` and `iss-landing-shell` — strong addition

The most significant CSS change in this commit is the introduction of `iss-story-intro` in `patterns.css` (roughly lines 724–943 after the commit). This is a properly designed shared component:

- 25+ CSS custom properties cover every dimension of the layout: grid columns, gaps, typographic scale, color, note borders.
- Consumers set only the properties they need to override; the component handles the rest.
- First consumers are `page-ausstellungen.css` (`iss-ausstellungen-outside`) and `ueber-uns.css` (`iss-about-work`), both of which now use variable blocks instead of bespoke selectors.

This directly addresses finding 3 from the high-severity section ("page-shell border and kicker contract is duplicated") for the editorial intro pattern family specifically. The approach is exactly right.

The companion `iss-landing-shell` component (a two-column rail-and-content grid) follows the same pattern and gives the Fuehrungen landing redesign a shared structural root.

#### `front-page.css` — correct direction, incomplete migration

A new `front-page.css` is introduced and conditionally enqueued via `is_front_page()`. It covers the landscape strip layout for the homepage.

What it does not yet do: the bulk of homepage-specific layout logic (`.home .wp-site-blocks`, `.home .iss-front-page-main`, `.home #wp--skip-link--target`, the visit strip, card-row tuning, and overlay media-text overrides) still lives in `patterns.css` starting around line 591. The file has been started, but the migration from `patterns.css` is not complete. Findings 1 and 4 from the high-severity section remain partially open.

#### `atlas-app.css` token block — correct

A `.iss-schoneweide-atlas-page` block was added at the top of `atlas-app.css`. It collects all atlas surface colors into one scoped token declaration that other rules inside the file can inherit. This replaces hardcoded inline values and makes the atlas color contract legible. The 7 previously questionable `!important` declarations in this file remain; they are not eliminated but the context around them is now cleaner.

#### `page-ausstellungen.css` — partially migrated, stray rule remains

The bespoke `.iss-ausstellungen-outside__*` selectors (~100 lines) were removed and replaced with `iss-story-intro` variable overrides. This is good. However, lines 821–830 in the current file still contain `.iss-repair-page` rules. This was flagged in the file-ownership-leaks section above ("page ownership leakage inside the wrong stylesheet") and was not addressed.

#### `style.css` — minor utility addition

`.iss-section--full-bleed` (8 lines) was added. It is a reasonable full-width escape hatch for sections that need to own their own viewport width. The comment on the rule is sufficient.

#### Grid column bug fix in `patterns.css`

Line 664: `grid-template-columns: minmax(0, 1fr) minmax(0, var(--iss-banner-width))` was changed to `minmax(0, 1fr) auto`. The variable `--iss-banner-width` was being consumed without being defined on the element, which would produce a zero-width column. Replacing it with `auto` is correct.

---

### Findings status after this commit

| Finding | Status |
|---------|--------|
| Orphaned compact CSS files | **Closed** — deleted |
| `test.css` scratch file | **Closed** — deleted |
| `_archive/` bloat in tracked tree | **Closed** — deleted |
| Dead ACF plugins | **Closed** — deleted |
| `iss-story-intro` component missing | **Closed** — added in patterns.css |
| `iss-ausstellungen-outside__*` bespoke selectors | **Closed** — migrated to `iss-story-intro` |
| Homepage CSS in `patterns.css` | **Partially open** — `front-page.css` started but `.home` logic not fully extracted |
| Repeated `margin-top: 0 !important` hacks | **Open** — unchanged in page-events.css, page-museum.css, page-videos.css, patterns.css lines 4940, 5295, 5304 |
| Stale `iss-schoneweide-intro__*` selectors | **Open** — still present at patterns.css lines 5925–5988 and 7460 |
| Stray `iss-repair-page` in page-ausstellungen.css | **Open** — still present at lines 821–830 |
| `iss-card-grid` duplicated in cards.css | **Open** — two definitions remain at lines 384 and 471 |
| `iss-events-context` not replaced by `iss-story-intro` | **Open** — page-events.css still uses its own intro system |
| `iss-archive-card` not moved to shared cards layer | **Open** — still in page-archive.css:603–718 |
| `body:has(...)` page-header mode | **Open** — unchanged in style.css |

---

### New observations not in the original audit

#### `ueber-uns.css` CSS nesting syntax issue

The `iss-about-work` variable block added by this commit contains what appears to be invalid native CSS nesting. The rules starting around line 30 of the added block open with:

```css
.iss-about-work .iss-story-intro {
  --iss-story-intro-gap: ...;
  /* ... */
  .iss-about-work .iss-story-intro__top {
  .iss-about-work .iss-story-intro__head-wrap {
```

Inside a native CSS nesting context, the nested selectors would be relative to the parent, so `.iss-about-work .iss-story-intro__top` inside `.iss-about-work .iss-story-intro` would resolve to `.iss-about-work .iss-story-intro .iss-about-work .iss-story-intro__top`, which is a non-matching double-scope. If this is intentional flat CSS, the nesting braces are mismatched. This needs verification against a browser dev tools computed styles check.

#### `iss-story-intro` custom property defaults are partially redundant

The component defaults in `patterns.css` set `--iss-story-intro-text-color: rgba(30, 30, 30, 0.78)` and `--iss-story-intro-lead-color: rgba(30, 30, 30, 0.9)`. These are inline hex/rgba values rather than references to the global token candidates noted in the original audit. If named text-hierarchy tokens are added to `:root` in `style.css`, these component defaults should reference them. Not blocking for now, but worth keeping consistent.

#### `page-fuehrungen-mosaic.css` — new file, no audit yet

This commit also introduced `page-fuehrungen-mosaic.css` (376 lines) via a prior commit in the same push window. It has not been reviewed. Given the pattern of `!important` fights in other Fuehrungen CSS (`page-fuehrungen.css:133-138`, `page-fuehrungen-mosaic.css:23-28`, `page-fuehrungen-mosaic.css:287-323`), this file should be checked for the same inline flex-basis vs. stylesheet override conflict that the original audit flagged.

---

### Overall assessment

The cleanup commit is a meaningful step. The deletion work is comprehensive and correct. The addition of `iss-story-intro` and `iss-landing-shell` is the most architecturally significant change: it proves the shared component pattern works in this codebase and gives future pages a clean integration point.

The remaining open items are all from Phase 1 and Phase 2 of the priority plan. None of them are regressions introduced by this commit. The two new concerns — the CSS nesting issue in `ueber-uns.css` and the incomplete homepage extraction — should be the immediate follow-up targets before Phase 2 work continues.
