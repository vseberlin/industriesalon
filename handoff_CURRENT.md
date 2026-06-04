# Current Handoff

Updated: 2026-06-04

This file is the working handoff only. Full historical detail belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Repo State

- Branch: `main`
- Latest pushed checkpoint: `20e70ad` (`Add staging uploads delta archive`) on `main` / `origin/main`.
- Local working clone: `/home/vladimir/projects/industriesalon`.
- Staging deployment checkout: `/srv/industriesalon/stage/repo`.
- Staging WordPress app root: `/srv/industriesalon/stage/app`.
- Staging shared uploads root: `/srv/industriesalon/stage/shared/uploads` (`app/wp-content/uploads` symlinks here and the WordPress container mounts it at `/var/www/html/wp-content/uploads`).
- Staging Docker Compose file: `/srv/industriesalon/stage/compose.yml`.
- Staging nginx vhost: `/srv/industriesalon/shared/nginx/stage.industriesalon.info.conf`.
- Server action notes: `/home/vladimir/server-actions/`.
- Latest local documentation commit: `1dd6423` (`Document local project paths`), not yet pushed to GitHub.
- Latest local checkpoint prepared after `3b114ab`: front-page/footer visual polish, DB-template sync-back for `front-page` and `page-salon-vermietung`, local footer WebP assets, and newsletter partner row.
- The current policy is local repo -> GitHub `main` -> staging deploy. Direct plugin/code updates in staging admin violate the workflow unless explicitly approved.
- Current pushed production/staging transfer commits after `ffa3786`:
  - `cfbd641` `Add related card placeholder fallbacks`
  - `d426306` `Add newsletter plugin integration`
  - `e9caac2` `Add event editor shim`
  - `db1a171` `Add production newsletter SQL sync`
  - `3b114ab` `Update front page heading and menu links`
- Newsletter code is now tracked and pushed, including The Newsletter Plugin `9.2.5`, the `iss-newsletter` adapter, the live front-page `iss/newsletter-form` block integration, and bot-shaped subscription guards.
- Production/staging SQL sync artifacts now include:
  - `ops/sql/2026-06-03-production-video-transcripts-sync.sql`
  - `ops/sql/2026-06-03-production-front-page-sync.sql`
  - `ops/sql/2026-06-03-production-newsletter-sync.sql`
- The newsletter SQL artifact was syntax/import checked in a temporary DB schema and contains 859 cleaned subscribers, 28 newsletter email/template rows, an empty user-meta sync, and 8 stable Newsletter Plugin options. It intentionally excludes logs, send/stat history, volatile locks/diagnostics, update caches, and remote add-on catalog cache.
- Transcript text cleanups and newsletter subscriber imports were written to the local WordPress database and are represented for production transfer through the SQL artifacts above; preserve database state before destructive resets.
- This closeout checkpoint includes:
  - `front-page` and `page-salon-vermietung` Site Editor DB templates were synced back to disk and their DB overrides removed, so the file templates are authoritative again.
  - `/salon-vermietung/` inquiry CTA text/button colors were corrected on the dark CTA background.
  - The global footer now uses the dark `#1e1e1e` skin, the trimmed gray transparent footer logo, the red legal mark, and the linked Twinkl `Empfohlenes Museum` badge.
  - The front-page newsletter/supporter section is uncaged, uses a larger supporter logo, and includes the Visit Berlin, ERIH, and BZI tourism partner logos below the supporter row.
  - `themes/industriesalon/templates/page-salon-vermietung.html` still includes the `Räume & Ausstattung` section from old-site rental facts; page post `12606` body was restored from backup and should not be treated as the visible source.
- Expected local work left unstaged after this checkpoint:
  - `.gitignore` unignores `ops/seo/`.
  - `ops/seo/` contains SEO URL/media inventory and redirect-map draft/ready artifacts.
  - `CHANGELOG.md` still contains the uncommitted SEO inventory changelog line.
  - `AGENTS.md` contains expanded local/server operating instructions from the user and should remain unstaged unless explicitly requested.
- Local DB backup for the temporary Salon-Vermietung page-body attempt:
  - `backups/page_salon_vermietung_12606_before_room_facts_20260603-2155.sql`
  - `backups/page_salon_vermietung_12606_before_room_facts_20260603-2155.sql.sha256`
- Re-check `git status --short` before deployment or handoff.
- Standard closeout files: root `handoff_CURRENT.md`, root `CHANGELOG.md`, and root `TODO.md` when next work needs to be preserved.
- Root handoff/changelog files may be ignored by git in this repo; use `git add -f handoff_CURRENT.md CHANGELOG.md TODO.md` when committing closeout docs.

## Current Server State

- Provider reported shutdowns after the fact; local evidence shows unclean host stops around 2026-06-01 02:28-02:43 UTC and 2026-06-03 00:00-00:08 UTC. MariaDB recovered cleanly after the 2026-06-03 restart.
- Added a 2 GiB `/swapfile`, persisted in `/etc/fstab`, with `vm.swappiness=10` in `/etc/sysctl.d/99-industriesalon-swap.conf`.
- Hardened SSH with `/etc/ssh/sshd_config.d/20-no-password-auth.conf`; effective setting is `PasswordAuthentication no`, with key auth still enabled.
- Added nginx default catch-all server at `/etc/nginx/sites-available/00-catch-all.conf`, enabled via `/etc/nginx/sites-enabled/00-catch-all.conf`, returning `444` for unknown/raw-IP HTTP and HTTPS hosts.
- Blocked `xmlrpc.php` in `/etc/nginx/sites-available/stage.industriesalon.info`; `https://staging.industriesalon.info/xmlrpc.php` returns `403`.
- Server action notes and rollback commands are recorded in:
  - `/home/vladimir/server-actions/2026-06-04-add-swapfile.md`
  - `/home/vladimir/server-actions/2026-06-04-ssh-nginx-hardening.md`
- Last verification: no failed systemd units, staging homepage returned `200 OK`, containers remained up and healthy.

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

- Current direction from today’s work:
  - theme owns visible public composition and design systems
  - `iss-content-model` owns CPT/editor/data contracts
  - `iss-newsletter` is a thin adapter around The Newsletter Plugin, not a parallel newsletter system
  - production data transfer is explicit SQL under `ops/sql`, not hidden migration code
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

### Video CPT / Transcripts / Landing Pages

- `iss-content-model` now registers video dynamic blocks from metadata-backed directories while keeping the existing server render callbacks:
  - `iss/video-library`
  - `iss/video-library-feature`
  - `iss/video-library-filters`
  - `iss/video-library-playlists`
  - `iss/video-library-external`
  - `iss/video-library-inventory`
  - `iss/video-library-cta`
  - `iss/video-player`
  - `iss/video-transcript`
- The theme remains the owner of `/videos/` and single-video composition:
  - `themes/industriesalon/templates/page-videos.html`
  - `themes/industriesalon/templates/single-video.html`
  - `themes/industriesalon/assets/css/page-videos.css`
  - `themes/industriesalon/assets/css/single-video.css`
  - `themes/industriesalon/assets/js/single-video.js`
- `/videos/` current behavior:
  - filters stay in the main listing column and are sticky at the same header offset as the right player column on desktop
  - filter clicks use controlled smooth scrolling to avoid abrupt browser anchor jumps
  - `Übersicht` shows overview facts only, not transcript status
  - `Transkript lesen` opens the full transcript tab
  - `Kapitel` shows a timecode table of contents
- Single-video current behavior:
  - the video panel sits flush below the fixed header
  - source link is an icon-only YouTube link
  - transcript heading is `Gesprächsdokumentation`
  - transcript navigation/timecodes seek the YouTube iframe through the theme `single-video.js`
  - related content is an editor-visible slot inside `iss/video-transcript` and renders as a right rail on wide screens
- Local DB transcript state after the cleanup pass:
  - 22 YouTube-caption transcripts were rewritten as timecoded Gutenberg paragraphs and pre-editorially cleaned
  - `21120` and duplicate `24990` share the cleaned 63-paragraph Whisper transcript
  - remaining Whisper fallback videos were reviewed/cleaned or explicitly marked as no usable timed transcript
- Verification from the latest video landing/single-video pass:
  - `php -l plugins/iss-content-model/includes/videos.php`
  - `npx stylelint themes/industriesalon/assets/css/page-videos.css`
  - `npx stylelint themes/industriesalon/assets/css/single-video.css`
  - `npx eslint plugins/iss-content-model/assets/video-library.js`
  - Playwright checks for `/videos/` confirmed sticky filters/player, distinct tab content, timecode chapter labels, smooth filter scroll, and no horizontal overflow
  - Playwright checks for single videos confirmed no header gap and no horizontal overflow

### Local Video Transcription Workflow

- Local-only transcription files are intentionally ignored by Git:
  - `docker-compose.transcription.local.yml`
  - `local/transcription-cli.Dockerfile`
  - `local/transcribe-videos.local.sh`
  - `wp/wp-content/mu-plugins/iss-local-video-transcription.php`
- Caption import worked for many videos: `22` full transcripts are marked as `YouTube captions DE, local WP-CLI 2026-06-01`.
- Whisper fallback is too slow in the current CPU-only Docker image:
  - backup created before the test: `backups/full_20260602-091424_before-video-transcription.sql` plus `.sha256`
  - targeted run for post `24990` downloaded/converter audio and entered `whisper-cli`
  - audio duration was about `3772.992` seconds (~63 minutes)
  - the process was still CPU-active after ~54 minutes with no text output, then stopped
  - post `24990` remained unchanged: `status=excerpt`, `words=93`, empty transcript source
- TODO now records the need to evaluate a faster local-only external transcription fallback behind an environment API key, with chunking for OpenAI-style upload limits.

### Deployment / Staging Workflow

- Staging code changes should flow local repo -> GitHub `main` -> staging deploy. Do not update plugins directly in staging admin unless this rule is explicitly suspended.
- Next staging action after this handoff is to run the staging deploy script on the VPS so it pulls GitHub `main` at `68abb93`.
- GitHub `main` was reset to a clean one-commit staging baseline on 2026-05-31; the prior local history is preserved on `backup/pre-clean-main-20260531-c079b6a`.
- Current tracked third-party plugin versions after local maintenance:
  - `classic-editor-and-classic-widgets` `1.5.3` (inactive)
  - `webp-converter-for-media` `6.6.0` (active)
  - `media-library-assistant` `3.37` (active)
- Last plugin maintenance verification:
  - changed plugin PHP files passed `php -l`
  - WP-CLI `plugin list` loaded all active plugins and reported the updated versions
  - WP-CLI `plugin list --update=available` reported no remaining local plugin updates

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

- Deploy GitHub `main` at `3b114ab` to staging through the normal Git-first deployment path.
- Before applying any SQL on staging/production, verify backups for database/uploads/config and confirm the target has the required plugins active.
- Apply SQL artifacts deliberately and in dependency order:
  - plugin/code deploy first
  - newsletter plugin activation/schema creation
  - `ops/sql/2026-06-03-production-newsletter-sync.sql`
  - `ops/sql/2026-06-03-production-video-transcripts-sync.sql`
  - `ops/sql/2026-06-03-production-front-page-sync.sql` only where the production DB still needs template authority/project-order sync
- Review and commit or discard the local SEO inventory work under `ops/seo/` separately; it is not pushed yet.
- Keep the local-only video transcription workflow out of Git. Transfer transcript data through SQL artifacts only.
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
