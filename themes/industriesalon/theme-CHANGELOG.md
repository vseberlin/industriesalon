# Industriesalon Theme Changelog

## 2026-07-01
- Reworked `/kalender/` into a booking-style two-panel selector: red scheme,
  visible month/day grid from the existing timeline bridge, compact result
  cards, and a lighter programme aside while preserving the occurrence-backed
  `industriesalon/timeline-query` contract.
- Reworked the grouped booking popup used by `Termin wählen`: the first
  bookable day is selected automatically, slots are visible immediately, and
  the modal uses a two-column desktop layout with a stacked mobile layout.
- Refined the single Führung layout around the JSON gesture contract:
  `intro` remains the hero description, `kapitel` becomes readable tour
  narrative, `leitfrage`, `zitat`, media, material, and closing gestures now
  have stable public treatments, the hero now behaves as a full-viewport route
  stage, and the gallery no longer dominates the mobile first screen.
- Added theme consumption for the new Führung `bildbuehne` gesture: when present,
  it replaces the template hero title, description, image, and gallery while the
  booking/date/facts rail stays outside the gesture.

## 2026-06-27
- Added the theme-owned Führung JSON renderer in `includes/tours-render.php`:
  enabled `_iss_editorial_fuehrung` documents now expose tour skin body
  classes, replace the hero description from the first `intro` gesture, and
  render later gesture sections in a dedicated single-tour editorial slot before
  the existing route block.
- Added `route-dossier`, `compact`, and `standard` Führung skin options and
  scoped `assets/css/single-tour.css` rules for tour editorial sections,
  galleries, archive refs, material file cards, quotes, and mobile fallbacks.

## 2026-06-21
- Added a shared primary button tier in `style.css` with `.iss-button` variants, Gutenberg `is-style-fill` mapping, and matching solid booking-button tokens in `assets/css/timeline-skin.css`.
- Reworked `templates/page-kalender.html` and `assets/css/page-kalender.css` into a wide-viewport calendar workbench: existing timeline-query controls become a left rail, results are capped into a compact occurrence list, recurring tour groups start collapsed, calendar listing media is hidden, and a theme-owned aside links visitors toward the separate exhibitions browser.
- Adjusted timeline kicker accent/color variables so kicker text, rule, and dot use the active timeline scheme consistently.

## 2026-06-12
- Moved the active Führung single block template from the legacy custom-template slug `single-tour` to WordPress' native CPT hierarchy slug `single-fuehrung`, keeping the existing `iss-tour-*` layout classes and dynamic block composition intact.

## 2026-05-30
- Added a reusable Terminblatt format layer for Veranstaltungen: `_iss_event_format` editor choices for Allgemein, Vortrag, Gespräch, Lesung, and Präsentation, stable `iss-event-format-*` body classes, and four matching editor patterns for chaptered event content.
- Refined the standard Terminblatt skin with a stricter paper hero and dark facts rail while keeping the existing `iss/content-meta` facts block and color scheme tokens as the source of public presentation.
- Added an editor-selectable Veranstaltung color scheme (`_iss_event_scheme`) beside the existing layout selector, using global theme color tokens to vary event accents and the fest hero without separate templates or local CSS.
- Rebuilt `templates/single-veranstaltung.html` as a stricter event-sheet surface:
  - hero title and excerpt now sit beside the existing `iss/content-meta` facts rail
  - featured media renders as contained poster/artwork instead of a cropped soft card
  - editorial body content has its own scalable column after the hero
- Replaced the Veranstaltung end-page related-card sections with compact `iss/related-content` rail groups, matching the project/place rail direction while keeping relation rendering in `iss-relations`.
- Added the semantic `fest` Veranstaltung layout (`Fest / Programm`) with a reusable poster-like festival skin and an `ISS Festprogramm` editor pattern; legacy `feature` layout values now normalize to `fest`.
  - The fest pattern's address cell now uses the Steuerung `address.full` field block so repeated festival pages inherit the canonical visitor address.
- Replaced the old soft `assets/css/single-event.css` treatment with rule-based layout variants for standard, compact, fest, and long Veranstaltung pages.
- Added `patterns/iss-event-program-spine.html` and registered it as `ISS Veranstaltungsprogramm` so large events can carry an editor-visible program table without a separate data model.

## 2026-05-29
- Refined the single-place dossier v2:
  - removed hero meta chips and the repeated fact strip below the hero
  - inserted the existing `iss/atlas-slice` below the hero with `source: current`, a visible marker, and a short dynamic place summary
  - removed the old left-rail Atlas callout and the repeated `Historisch` overview row so historical structure belongs to the epoch rail
  - added strict place-atlas strip styling in `assets/css/patterns.css`
  - contained atlas slice map overflow in `assets/css/primitives.css` for mobile stability
- Rebuilt the `register_place` single template as a place dossier/network surface:
  - replaced the old body/sidebar/today-band composition with a compact fact strip, left rail, epoch chronology, current data grid, and inline interpretation notes
  - moved related discovery out of large end sections and into compact rail links under the left overview
  - kept sparse pages clean by removing the fixed network wrapper so empty relation groups do not leave orphan headings
  - added strict line-based styling in `assets/css/patterns.css` for the epoch rail, data grid, and related rail lists
- Refined `templates/single-register_place.html` and the existing register-place rules in `assets/css/patterns.css`:
  - removed the duplicated body excerpt/hero-meta chips from the single-place lead
  - changed the sidebar overview from the generic `iss-info-panel` card shell to a place-specific rail
  - switched related sections to `iss/related-content` so empty related groups do not render
  - restrained the hero height, squared the chip treatment, and flattened the Today band to match the stricter industrial direction
- Replaced the `projekt` archive landing with a real `page-projekte` template:
  - added `templates/page-projekte.html`
  - added a restrained dark project-continuity hero with a compact light rail and no full-width media band
  - added a full-width zebra feature section for the two first curated upcoming projects
  - changed featured media to `contain` on white/paper surfaces so transparent project logos do not inherit the gray card-media placeholder
  - rewrote `assets/css/page-projekte.css` for the stricter project index surface
  - removed `templates/archive-projekt.html`
  - moved stylesheet loading to the real `/projekte/` page route

## 2026-05-06
- Reworked the single-tour theme composition for `Führungen`:
  - tightened the hero into a three-column editorial layout,
  - moved tour description into explicit template slots,
  - split `Weiter entdecken` into `Weitere Führungen` and `Orte entdecken`.
- Updated `assets/css/single-tour-route.css` to carry the isolated industrial single-tour skin:
  - flatter facts and booking rail,
  - route rail/carousel styling,
  - normalized route-card media sizing,
  - shared subsection card treatment for tours and places.
- Rebuilt the public `/schoneweide/` Atlas as a leaner theme-owned Leaflet surface with a dedicated app stylesheet in `assets/css/atlas-app.css` and a separate page-shell stylesheet in `assets/css/page-schoneweide.css`.
- Switched the Atlas page to the shared landing-page hero structure and moved its data loading/interaction layer into the simplified `assets/js/schoneweide.js` runtime.
- Made the public Atlas skinnable through `.iss-scheme-*` wrapper accents and moved shared card/kicker presentation back onto the global `style.css` and `cards.css` systems.
- Moved the Atlas hero note into `industriesalon-notices` with a dedicated `atlas` skin so the banner is plugin-owned instead of page-local markup/CSS.
- Removed the remaining JSON import/fallback path from `industriesalon-schoeneweide-register`; local `register_place` posts are now the authoritative Atlas/Register source and the import-only build metadata was deleted.
- Removed the public `Zeitraum` sidebar filter from `/schoneweide/` while keeping era data for story selection and place metadata.
- Equalized the public Atlas filter/map stage height in the two-column layout and bound Leaflet resize handling to the actual map surface so marker coordinates stay correct when the stage resizes.

## 2026-05-03
- Added `iss-control-info-panel` theme styling so the `industriesalon-steuerung` visit-info block renders through the existing `iss-info-panel` pattern language.
- Tightened the new visit panel visual treatment:
  - white light surface
  - smaller radius
  - lighter elevation
  - no visible card border
- Switched the row action treatment to site-native `iss-action-link` styling for Maps / route links.
- Added dark-surface handling and icon-row styling for plugin-driven visit/address/accessibility rows.

## 2026-04-30
- Recovered theme authority by syncing `page-ueber-uns` back to disk and flushing the remaining DB template overrides.
- Normalized confusing landing-template names:
  - `page-ausstellungen.html`
  - `page-publikationen.html`
  - `fuehrungen-landing.html`
- Removed redundant archive templates:
  - `archive-projekt.html`
  - `archive-veranstaltung.html`
- Hardened the off-canvas menu against Gutenberg header edits:
  - added `assets/menu-shell.html`
  - moved shell rendering to `functions.php`
  - updated `parts/header.html`, `assets/js/header.js`, and `style.css`
- Added the new Verein landing template:
  - `templates/page-verein.html`
  - scoped styles in `assets/css/patterns.css`
- Repaired Archiv landing styling after the mixed-layout rebuild by restoring the missing `iss-archive-*` selector set and resolving dark-section color collisions.

## 2026-04-28
- Added a new front-page research/object contrast section for the Teilchendetektor:
  - pattern: `patterns/iss-section-object-highlight.html`
  - registration: `functions.php`
  - insertion: `templates/front-page.html`
  - styles: `assets/css/patterns.css`
- Added theme-owned detector assets for frontend use:
  - `assets/img/teilchendetektor/teilchen.png`
  - `assets/img/teilchendetektor/teilchen-back.png`
  - `assets/img/teilchendetektor/note.png`
- Extended the detector section to show the back side of the object next to the handwritten note.
- Updated `templates/page-ueber-uns.html` team query loop from 3 to 4 columns.
- Tightened `Über uns` team-card spacing in `assets/css/ueber-uns.css` for a cleaner 4-up row.
- Removed the page-wide `iss-page--compact` wrapper class from the file-backed `Über uns` template.

## 2026-04-27
- Restored front-page disk authority after syncing current live content back into `templates/front-page.html` and removing the DB `front-page` template override.
- Replaced remaining front-page discovery pills with arrow/text links:
  - `In kürzen` cards now use `Weiter`
  - `Projekte` / `Über uns` media-text rows now use `Weiter`
- Added a semantic accent scheme layer in `style.css`:
  - wrapper classes `.iss-scheme-red|blue|green|yellow|brown`
  - default shared accent surfaces now follow `--iss-accent`
- Added top-level CSS documentation for scheme wrappers and default CTA usage.
- Refactored reusable pattern files to prefer common color switching and low-chrome discovery CTAs:
  - generic discovery CTAs now default to `Mehr` arrow links
  - transactional request/inquiry actions remain buttons
  - removed fixed decorative red/yellow modifiers from generic reusable patterns where they blocked wrapper-based color switching
- Added reusable `.iss-action-link` helper for default discovery CTAs.

## 2026-04-26
- Moved publication page rendering into native Gutenberg theme templates:
  - `templates/page-publikationen.html`
  - `templates/single-publication.html`
- Added reusable editorial pattern for publication intros:
  - `patterns/iss-publications-intro.html`
- Registered the new publications intro pattern in `functions.php`.
- Added publications layout support for the new `iss-publications` plugin:
  - publication feature layout in `assets/css/patterns.css`
  - publication archive/single layout styles in `assets/css/patterns.css`
- Included section tint/alt styling refinements in `style.css`.
- Added revised publications/payment architecture proposal:
  - `publications-commerce-refactor-revised.md`

## 2026-04-25
- Added reference-inspired multi-panel off-canvas header menu:
  - panel tabs: `Navigation`, `Kalender`, `Info`, `Suche`
  - panel markup and search form in `parts/header.html`
  - panel switching logic in `assets/js/header.js`
  - tab/panel styles in `assets/css/patterns.css`
- Committed as `3e20381` (`feat(theme): add multi-panel off-canvas header menu`).
- Added post layout selector for posts in Gutenberg (`standard`, `image`, `short`) via `_iss_post_layout`.
- Added frontend body class mapping for post layout variants (`iss-post-layout-*`).
- Implemented layout-specific single post hero behavior:
  - `standard`: image stays caged in container
  - `image`: full-width hero with viewport cap (prevents oversize >100vh)
  - `short`: compact hero/content mode without large hero image
- Improved single-post content styling in `assets/css/patterns.css`:
  - refined headings/paragraph/list rhythm
  - improved figure/caption/quote/table rendering
  - better responsive handling for align left/right/wide/full content
- Removed redundant single-post image/align overrides from `assets/css/overrides.css`.
- Purged `wp_template` DB overrides for `front-page`, `hero-page`, and `single` so disk templates are authoritative.
