# Current Handoff

Updated: 2026-05-31

This file is the working handoff only. Full historical detail belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Repo State

- Branch: `main`
- Latest active checkpoint: off-canvas menu shell and fast search entry flow on 2026-05-31, so the drawer is file-backed, grouped, graph-search-enabled, and cross-browser checked.
- `main` is intentionally ahead of origin in this local workflow; check `git status --short` and `git log --oneline -5` before deployment or handoff.
- Standard closeout files: root `handoff_CURRENT.md`, root `CHANGELOG.md`, and root `TODO.md` when next work needs to be preserved.
- Root handoff/changelog files may be ignored by git in this repo; use `git add -f handoff_CURRENT.md CHANGELOG.md TODO.md` when committing closeout docs.

## Operating Guardrails

- Follow `AGENTS.md`: stability, maintainability, editor usability, predictability, simplicity, performance, visual refinement.
- Do not apply quick local patches. Trace the owner, data path, template authority, enqueue order, cascade, and block/editor behavior before changing code.
- Keep boundaries clear:
  - theme owns public composition, skins, templates, and frontend presentation
  - plugins own data, business logic, CPT contracts, import/projection services, and dynamic block data
  - shared renderers/contracts should be extended before creating parallel systems
- Gutenberg/editor-first remains the default. Prefer native blocks, patterns, reusable structural classes, and editor-visible layouts over shortcode-like or hidden configuration.
- Before CSS changes, inspect `theme.json`, global tokens, existing utilities, pattern/card CSS, and `overrides.css`. Use low-specificity structural selectors; no `!important`.
- Before PHP/JS changes, inspect existing hooks, helpers, enqueue logic, templates, blocks, and plugin ownership. Remove obsolete code before extending.
- For block-theme routes, check template authority before trusting disk edits:
  - `get_block_template("industriesalon//template-slug", "wp_template")`
  - if `source=db`, preserve useful DB content before deleting overrides
  - file-backed authority is preferred for durable templates
- Every substantive change needs a concise changelog entry with the reason, not just the result.

## Current Architecture Notes

- `register_place` is the local WordPress-owned Schöneweide place source. JSON imports and static fallback data were removed.
- Register contracts are intentionally split:
  - summary = lightweight app/list/map payload
  - detail = full public place detail payload
  - export = fuller transfer/audit payload
  - atlas = map/story behavior, not a full research payload and not a visual layout contract
- `iss-relations` owns relation queries and relation-aware blocks.
- `iss/atlas-slice` owns atlas/map rendering and marker output.
- `industriesalon-schoeneweide-register` owns structured place data, epoch rows, state projection, register context blocks, and register editor/admin tools.
- The theme owns the single-place dossier composition in `themes/industriesalon/templates/single-register_place.html` and its frontend skin in shared theme CSS.
- For publication/project/video/editorial work, keep CPT/data ownership in plugins and public layout/skins in the theme unless an existing plugin explicitly owns the renderer.

## Recent State To Preserve

### Single Place Dossier

- The current `single-register_place` direction is the 2026-05-29 dossier v2:
  - no hero meta chips
  - no repeated compact fact strip under the hero
  - atlas strip uses `iss/atlas-slice` with `source: current`, `perPage: 1`, `mapPreset: atlas-slice`
  - left overview shows `Adresse`, `Heute`, and `Gebiet`, not a repeated `Historisch` row
  - epoch history belongs in the epoch rail
  - related place/content context is compact rail/list language, not heavy card sections
- Important files:
  - `themes/industriesalon/templates/single-register_place.html`
  - `themes/industriesalon/assets/css/patterns.css`
  - `plugins/industriesalon-schoeneweide-register/includes/blocks.php`
  - `plugins/iss-relations/includes/blocks.php`
- Last verification from the previous pass:
  - `single-register_place.html` template source was `theme`
  - `parse_blocks()` returned 6 top-level blocks
  - KAOS/KWO smoke checks rendered atlas marker, compact rails, no duplicate hero meta/facts, and no horizontal overflow

### Register Data

- Epoch backfill added 47 conservative epoch rows to 31 `register_place` posts via `iss_register_get_epoch_service()->save_epochs_for_place()`.
- Contemporary data pass updated owner/operator/developer/tenant/investment/size/website/kaufpreis fields on the same 31-place tranche using existing meta fields.
- Remaining public-visible published place without structured epochs: `12901` `Zeitlose Kunst`, left untouched because address/source status is unclear.
- `ADMOS Immobilien AG` post `12928` is intentionally `draft` with `place_visibility=tour_only`; it was removed from public related places and derived state/actor rows.
- `89 Lighthouse` post `12889` has sourced address/current-use/history/source fields and six structured epoch rows.
- Backups/exports from recent register work:
  - `/tmp/register-place-epoch-backfill-before-20260529.json`
  - `/tmp/register-place-epoch-backfill-after-20260529.json`
  - `/tmp/register-place-contemporary-data-before-20260529.json`
  - `/tmp/register-place-contemporary-data-after-20260529.json`
  - `backups/full_20260529-204248.sql`
  - `backups/full_20260529-204248.sql.sha256`
  - `backups/full_20260529-213252.sql`
  - `backups/full_20260529-213252.sql.sha256`

### Projects And Editorial Surfaces

- `/projekte/` is a real page-owned landing route, not the stale `projekt` archive.
- Project singles use the current dossier direction: dark project hero, metadata rail, anchored chapter content, compact related-place rail, and editable Gutenberg body.
- `projekt` is opted into the block editor at the CPT owner.
- `industriesalon/project-dossier-chapters` is still a project-specific starter pattern. Promote to a generic `iss/reading-chapters` block only if multiple CPTs need the same chapter contract.
- Recent project bodies normalized into the dossier model include FUTURA, Connected by Lights, Industrieabfälle, Stadtlabor Wilhelminenhofstraße, Landmark, Boulevard, and Walk of Fame Schöneweide.
- Steuerung remains the source for persistent address/contact/visit fields through `industriesalon/field` and `industriesalon/visit-info`; avoid hardcoded address/contact prose.

### Veranstaltungen / Event Templates

- `single-veranstaltung` is currently a theme-owned reusable event sheet:
  - template: `themes/industriesalon/templates/single-veranstaltung.html`
  - skin: `themes/industriesalon/assets/css/single-event.css`
  - public relation rails use existing `iss/related-content` blocks instead of page-specific related-card markup
  - event facts are rendered through the existing `iss/content-meta` block in the hero rail
- Veranstaltung editor controls live in `themes/industriesalon/functions.php` and are all post meta exposed through the same document sidebar panel:
  - `_iss_event_layout`: `standard`/Terminblatt, `compact`, `fest`, `long`; legacy `feature` normalizes to `fest`
  - `_iss_event_scheme`: `blue`, `red`, `green`, `yellow`, `brown`, mapped to existing theme color tokens and `iss-event-scheme-*` body classes
  - `_iss_event_format`: `general`, `vortrag`, `gespraech`, `lesung`, `praesentation`, mapped to stable `iss-event-format-*` body classes
- The format selector is intentionally editorial, not a hidden layout reorder system. Actual event structure stays normal Gutenberg content through patterns:
  - `industriesalon/event-format-vortrag`
  - `industriesalon/event-format-gespraech`
  - `industriesalon/event-format-lesung`
  - `industriesalon/event-format-praesentation`
  - existing program patterns remain `industriesalon/event-program-spine` and `industriesalon/event-fest-program`
- On empty Veranstaltung editor content, choosing one of the concrete Terminblatt formats now inserts the matching editable pattern from the theme pattern file and keeps the event on the standard Terminblatt layout.
- Existing event content is never overwritten by the selector; if a format is selected but no format sheet exists, the document panel exposes an explicit `Terminblatt-Struktur einfügen` action.
- All four Terminblatt format patterns include a `Materialien` / `Downloads und Quellen` chapter for PDFs, slides, handouts, links, and follow-up documents without creating a separate event media-library model.
- Standard Terminblatt now has the stricter paper hero and dark facts rail direction; fest pages keep their poster-like hero and program styling.
- `Fête de la Musique Berlin 2026` (`25808`) is the current fest reference:
  - `_iss_event_layout=fest`
  - `_iss_event_scheme=red`
  - body uses the fest program/info-cell pattern
  - address cell uses Steuerung-backed `industriesalon/field {"key":"address.full"}` instead of hardcoded address prose
- Current canonical visitor address is `Reinbeckstraße 10`; avoid reintroducing `Reinbeckstraße 9` in templates or reusable event content.
- Last verification from the event pass:
  - `php -l themes/industriesalon/functions.php`
  - `git diff --check`
  - `_iss_event_layout`, `_iss_event_scheme`, and `_iss_event_format` registered for `veranstaltung`
  - four Terminblatt format patterns registered and parsed with `parse_blocks()`
  - Playwright desktop/mobile checks passed for one standard Terminblatt event and the Fête fest page with no horizontal overflow

### Menu Shell / Site Search

- The off-canvas drawer is now file-backed in `themes/industriesalon/assets/menu-shell.html`; it no longer depends on stale `wp_navigation` refs.
- Drawer groups follow the `Theme_assets/nav.md` structure, refined toward the latest mockup:
  - large search tile at top
  - compact `Kalender` / `Besuch` / `Atlas` quick buttons
  - primary `Führungen` / `Raummieten`
  - red plain section labels for `Institution`, `Entdecken`, and `Archiv`
  - two-column discovery/archive link grids
  - no address block in the drawer
  - bottom status row for `Heute` and `Nächste Veranstaltung`
- The search tile opens a theme-owned fast-search modal rendered by `industriesalon_render_search_modal()` in `themes/industriesalon/functions.php`.
- Fast search reuses the existing `iss-graph` REST endpoint at `/wp-json/iss-search/v1/search`, showing up to 8 quick results.
- Full search handoff intentionally remains the native WordPress route `/?s=query`; do not introduce `/suche/?q=` until the full search page is deliberately redesigned.
- `Heute` in the drawer status row is fed by `Industriesalon_Steuerung::render_visit_status('public')`.
- `Nächste Veranstaltung` queries the next published `veranstaltung` by `iss_start_datetime`; this is intentionally theme presentation over existing event meta, not a new programme data model.
- Cross-browser layout guardrails from the last pass:
  - quick-link grid must target the rendered Navigation block inner `ul`: `nav.iss-menu-shell__quick-grid > .wp-block-navigation__container`
  - drawer inner owns `height: 100dvh` / `min-height: 100dvh`; avoid reverting it to percent height, because Chrome/logged-in viewport chrome can otherwise prevent bottom pinning
  - status row is bottom-pinned with `margin-top: auto`
  - archive link font size matches the Entdecken grid
- Last verification from the menu/search pass:
  - `php -l themes/industriesalon/functions.php`
  - `node --check themes/industriesalon/assets/js/header.js`
  - `npx stylelint themes/industriesalon/style.css`
  - `bash tools/phpcs-target.sh themes/industriesalon/functions.php`
  - `bash tools/phpstan-target.sh themes/industriesalon/functions.php`
  - `git diff --check`
  - Playwright Chromium and Firefox checks for equal quick-button columns, no drawer horizontal overflow, stable status bottom gap, and fast search returning results

### Archive / Publication / Video Direction

- Archive runtime ownership stays in `iss-wf-import`; graph/profile reuse stays in `iss-graph`.
- For archive ingest/projection, keep the layered direction: `ingest -> normalize -> project -> enrich -> provenance`.
- Publication/video surfaces should keep browsing context and related-content reuse. For videos, preserve the CPT and prefer poster-first or delayed iframe behavior before sacrificing YouTube traffic.
- Long historical detail from the 2026-05-29 Berlin.de reconstruction, Strunk/WF continuity, publications, and project imports is in `CHANGELOG.md`.

## Active Next Steps

- Review existing Veranstaltung posts and set `_iss_event_format` where useful:
  - Salon Gespräch entries likely map to `gespraech`
  - lecture-style entries map to `vortrag`
  - reading entries map to `lesung`
  - launches/result showings map to `praesentation`
- In the Gutenberg editor, continue reviewing Terminblatt format patterns on real events before bulk-normalizing older event content.
- Review the project dossier v2 authoring pattern in the `projekt` Gutenberg editor.
- Review `/videos/` embed behavior against the YouTube-hit goal; poster/metadata selection with explicit play may be preferable to immediate in-page playback.
- Resolve remaining `register_place` coordinate gaps listed in `TODO.md`; improve addresses or set coordinates manually where geocoding is unreliable.
- Design the Touchtable promote workflow for `register_place` before broad editor rollout:
  - source snapshots stay traceable
  - field-level promotion must be deliberate
  - public rendering remains theme-owned and local-data-only
- Add a reviewed Touchtable media workflow before exposing source media publicly.
- Review `single-tour.html` vs `single-tour-on-demand.html`; collapse duplicate templates unless editors truly need different compositions.
- Simplify the `industriesalon/timeline-query` editor contract; too many overlapping controls still describe similar query concepts.
- Keep footer navigation/spacing review on the queue after current content work.

## Tooling

Use repo-local tooling before adding ad hoc checks.

### JavaScript / CSS / Shell / YAML

- `npm run lint`
- `npm run lint:css`
- `npm run lint:js`
- `npm run lint:shell`
- `npm run lint:yaml`

Scope for JS/CSS linting is the custom theme and custom `iss-*` / `industriesalon-*` plugins, not third-party bundled code.

Current baseline from the earlier tooling pass:

- `npm run lint:js` passed with warnings only.
- `npm run lint:css` still had existing issues in `plugins/industriesalon-schoeneweide-register/assets/css/register-frontend.css`.
- `npm run lint:shell` was green.
- `npm run lint:yaml` reported no tracked YAML files in the repo-owned surface.

### PHP

- Global composer may not be available; use the repo-local binary:
  - `php tools/composer.phar run lint:php`
  - `php tools/composer.phar run lint:phpstan`
- Changed-file helpers:
  - `tools/phpcs-target.sh`
  - `tools/phpstan-target.sh`
- Full-repo PHP commands exist but are not the default workflow because the historical baseline can be noisy/heavy:
  - `php tools/composer.phar run lint:php:all`
  - `php tools/composer.phar run lint:phpstan:all`
- `PHPSTAN_MEMORY_LIMIT` can override the default changed-file PHPStan memory limit.

### E2E / Accessibility

- Disposable WordPress environment:
  - `npm run wp-env:start`
  - `npm run wp-env:stop`
  - `npm run wp-env:clean`
  - `npm run wp-env:logs`
- Playwright:
  - `npm run test:e2e`
  - `npm run test:e2e:headed`
  - `npm run test:e2e:debug`
  - `npm run test:e2e:a11y`
- Keep `core: null` in `.wp-env.json` for the disposable setup.
- Do not point `wp-env` at `./wp` unless intentionally testing the existing mounted local core/config.
- Current known machine baseline from the tooling pass: `node v20.19.2`, `npm 9.2.0`; `@wordpress/env` installed with engine warnings but worked.

### WordPress Runtime Checks

- Normal local pattern:
  - `docker compose run --rm wpcli <command> --allow-root`
- Register contract guardrail:
  - `docker compose run --rm wpcli iss-register contract-check --allow-root`
- Register place-state guardrail:
  - `docker compose run --rm wpcli iss-register place-state-check --allow-root`
- Useful template authority check:
  - `docker compose run --rm wpcli eval 'var_dump(get_block_template("industriesalon//single-register_place", "wp_template")->source ?? null);' --allow-root`

## Register Tool Guardrails

- Register tools page slug: `iss-register-tools`.
- Admin tools page owner: `plugins/industriesalon-schoeneweide-register/includes/admin-tools.php`.
- Guardrail owner: `plugins/industriesalon-schoeneweide-register/includes/register-data/guardrails.php`.
- CLI owner: `plugins/industriesalon-schoeneweide-register/includes/cli.php`.
- Coordinate geocoding is dry-run by default and batch-limited.
- Epoch seed migration must not be run casually:
  - create a DB backup first
  - download `/wp-json/iss-register/v1/export` first
  - use the tools-page migration action, not direct SQL
  - the admin action requires backup and export confirmation checkboxes
- After changing register contracts or projections, run the contract check and inspect summary/detail/export/atlas shape explicitly.

## Verification Pattern

- Syntax-check touched PHP:
  - `php -l path/to/file.php`
- For changed block templates, run `parse_blocks()` and check template authority.
- For public route changes, verify frontend render with curl or browser checks on representative routes.
- For layout changes, include desktop and mobile viewport checks and horizontal-overflow checks.
- For Gutenberg/editor-facing changes, verify editor rendering and block validation stability, not only frontend output.
- For DB/content mutations, export before/after state and create/checksum a host-owned SQL backup.

## Closeout Pattern

- If the user asks for handoff/changelog, update this file and `CHANGELOG.md`.
- If the user asks to pause/tomorrow, also update `TODO.md` with the concrete next action.
- If the user asks to commit all/main, include root docs, force-add ignored docs if needed, commit once, and re-check `git status --short`.
- Do not silently leave important next steps only in chat.
