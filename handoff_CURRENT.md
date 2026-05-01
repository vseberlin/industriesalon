# Handoff Current

## Status
- `not committed`

## Date / Window
- Date: `2026-05-01`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD at session start: `536443e`

## What Was Done This Session
- Fixed page/archive route ownership collisions in the active site:
  - confirmed `/ausstellungen/` had fallen back to the CPT archive after the archive template rename
  - confirmed `page-ausstellungen.html` still held the intended landing content
  - added route-guard logic in `plugins/iss-content-model/iss-content-model.php` so page-owned landing slugs can disable colliding CPT archives automatically
  - restored `/ausstellungen/` to the page-owned landing route
- Continued the same page-owned route cleanup for program landings:
  - `Veranstaltungen`:
    - confirmed the old `archive-veranstaltung.html` was the only prior dedicated template and was sparse
    - created `themes/industriesalon/templates/page-veranstaltungen.html` as the page-owned landing
    - created/assigned the live page route so `/veranstaltungen/` is no longer archive-owned
  - `Führungen`:
    - confirmed the live page already existed but under slug `/fuhrungen/`
    - renamed `fuehrungen-landing.html` to `page-fuehrungen.html`
    - moved the existing live page to slug `/fuehrungen/`
    - let the archive auto-disable under the shared route guard
- Reworked the `Veranstaltungen` landing structure using existing theme systems instead of one-off sections:
  - reused the `Ausstellungen` text-block rhythm
  - reused existing route-card/image-text patterns
  - removed the redundant archive section
  - kept the existing filter/timeline-query system as the functional core
  - added a handoff TODO to design a stronger next-generation timeline/calendar render
  - later tightened the page again:
    - switched accents to theme blue
    - replaced the custom `Mitmachen` box with the shared info-panel pattern
- Updated the shell menu:
  - refined spacing between primary items, secondary items, divider areas, and the contact row in `themes/industriesalon/style.css`
  - corrected the requested primary/secondary navigation targets in the stored navigation records
  - fixed `Über uns` links to use the actual live slug `/about/`
- Normalized the active landing-page hero system around the home-page hero markup/CSS:
  - converted active landing templates and generic page templates to the shared `iss-front-hero` + `iss-front-banner-slot` structure
  - kept `Über uns`, `Publikationen`, and `Verein` on their separate hero systems
  - removed stale page-level hero compensation layers and old inner-page hero overlap where no longer needed
  - unified viewport-height ownership under the shared hero CSS by removing inline cover `min-height` values from:
    - `front-page.html`
    - `page.html`
    - `hero-page.html`
    - `page-fuehrungen.html`
    - `page-ausstellungen.html`
    - `page-veranstaltungen.html`
    - `page-salon-vermietung.html`
    - `page-repair-cafe.html`
    - `page-projekte.html`
    - `page-archiv.html`
  - shared hero viewport height now follows the home-page value globally through `themes/industriesalon/assets/css/patterns.css`

## Verification
- Active theme remained `industriesalon`
- `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`, `/salon-vermietung/`, `/repair-cafe/`, `/archiv/`, and `/projekte/` returned `200`
- live route checks showed the shared `iss-front-hero` markup on the normalized landing pages
- `/about/` still rendered its separate `iss-page-hero__title`-based hero
- `/publikationen/` still rendered `iss-publications-masthead`
- `/verein/` still rendered `iss-verein-hero`
- leftover scan for inline front-hero `min-height` markup in the active templates returned clean after the final template pass

## Important Notes
- Worktree is still uncommitted.
- `page-veranstaltungen.html.bak-20260501-rollback` still exists as the requested rollback snapshot.
- `Verein` remains intentionally outside the shared front-hero system even though the page is now live.
- TODO: add a new timeline design/render for program-style pages, especially `Veranstaltungen`, with stronger date rhythm and a cleaner culture-calendar presentation.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
