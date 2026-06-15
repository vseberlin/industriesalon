# Changelog

This file records durable project changes. Keep it compact: current state belongs in
`handoff_CURRENT.md`, active follow-up in `TODO.md`, and detailed investigation can
be recovered from Git history.

## 2026-06-15

- Defined the static-map DTO boundary between `iss-relations` and `iss-frontend`: block place selection now normalizes into a relation result with ordered static-map place DTOs, and `docs/architecture/static-map-rendering.md` records the contract keys.
- Split the `iss-relations` related-content block editor script into focused modules for editor context, place-source controls, related-card controls, static-map controls, spine-strip controls, and editorial-signal controls while keeping the existing block names, render callbacks, and `iss-relations-related-blocks` editor handle stable.
- Froze the first-class static map block surface:
  - kept `iss/related-place-map`, `iss/atlas-slice`, and `iss/spine-strip` as normal inserter-visible map blocks;
  - kept `iss/atlas-strip` and `iss/asymmetric-split-field` render-compatible but hidden from the inserter as experimental/non-current public surfaces.
- Documented static marker provenance and the manual marker update verification path in `docs/architecture/static-map-rendering.md`.
- Split static map block responsibilities:
  - added a shared `iss-relations` map-block source contract used by PHP defaults, render resolution, and editor settings;
  - moved static marker lookup, projection math, focus-window calculation, stage rendering, panel rendering, and static map frontend rendering entry points into `iss-frontend/modules/static-maps`;
  - kept thin compatibility wrappers in `iss-relations` while block callbacks delegate to the frontend renderer;
  - fixed map-block source resolution so implicit manual `placeIds` no longer depend on an out-of-scope rendered block object;
  - added `wp iss-relations map-block-audit` to check DB and file-backed static map blocks, selected-place marker resolution, and public coordinate-bearing `register_place` marker coverage against the same contract;
  - added missing derived static markers for published coordinate-bearing places that were absent from `schoneweide-static-markers-new.json`;
  - kept `industriesalon-schoeneweide-register` as the `register_place` and interactive Atlas data/cache owner;
  - documented the cleanup pattern in `docs/architecture/static-map-rendering.md`.
- Added the Atlas/static-map implementation plan in `docs/architecture/atlas-static-map-implementation-plan.md`, merging the local audit and peer review into one durable architecture plan.

## 2026-06-14

- Added conditional project status rendering:
  - introduced `iss/project-status` for project lists;
  - renders date ranges from project start/end meta when present;
  - falls back to completed state after end date, taxonomy status, then period label.
- Reconciled current editor/template drift:
  - copied current front-page text edits into the file template and removed its DB override;
  - flushed the current `page-projekte` DB template body to disk while leaving the DB override in place for later deletion.
- Moved `iss/dense-image-wall` to `iss-frontend`:
  - kept the block name stable for existing content;
  - split editor workflow into composition and text/link modes;
  - moved baseline block CSS out of the theme;
  - hardened render output for class and URL handling.
- Fixed booking CTA modal loading for tour/calendar surfaces by enqueueing the shared programme script where slot triggers render.
- Added transfer artifacts for the Walk of Fame dense wall content:
  - `ops/sql/2026-06-14-walk-of-fame-dense-wall.sql`;
  - `ops/uploads/2026-06-14-walk-of-fame-dense-wall-media.tar.gz`;
  - matching manifest and SHA256 files.
- Added transfer artifacts for the current `projekt` single-page content edits:
  - exports all seven published `projekt` posts plus their postmeta and term relationships;
  - normalizes local dev URLs in project content to root-relative paths;
  - pairs the SQL with a 28-file upload archive for directly referenced project media.

## 2026-06-13

- Compacted repository documentation:
  - reduced `handoff_CURRENT.md` and `TODO.md` to current operational checkpoints;
  - collapsed the content model architecture notes into `docs/architecture/entity-model.md`;
  - removed stale planning/audit documents and local backup archives;
  - kept `docs/project/decisions.md` and `docs/project/backlog.md` as the durable project record.
- Finalized occurrence/programme cleanup:
  - made `iss-occurrences` the owner of occurrence projection, public query readiness, recurrence grouping, and SuperSaaS ingestion;
  - removed graph/entity ID coupling from occurrence rows;
  - removed frontend dependencies from occurrence query helpers;
  - kept `iss-programm`/`iss-frontend` rendering-oriented.
- Reorganized plugin domains:
  - renamed functional plugin folders toward `iss-content`, `iss-frontend`, `iss-commerce-lite`, and `iss-graph`;
  - refreshed active-plugin SQL artifacts and removed obsolete plugin-folder references from docs.
- Hardened public request surfaces:
  - added rate limiting, body-size guards, nonce support, timing checks, honeypot handling, and light spam rejection logging for public commerce endpoints;
  - moved request/order storage from capped options to plugin-owned tables.
- Cleaned editorial calendar visibility:
  - renamed the UI concept from technical timeline wording to programme/calendar wording while preserving migration compatibility;
  - kept projects opt-in for programme projection;
  - made permanent and digital exhibitions opt-in for programme/calendar visibility.
- Continued graph/facade cleanup:
  - added graph hygiene checks for orphan membership edges, bad relation years, and duplicate alias tokens;
  - preserved typed relationship semantics for employment, construction/design, membership, and founding links;
  - kept consumer discovery read-only through facade/query helpers.
- Verification:
  - ran local PHP syntax checks for touched plugin/theme files;
  - ran focused PHPStan/PHPCS checks where configured;
  - ran WP-CLI drift, active-plugin, and facade/offer-consumer smoke checks where relevant;
  - ran `git diff --check`.

## 2026-06-12

- Built graph editorial signals and search influence:
  - added rating/confidence/visibility controls for canonical graph entities and relationships;
  - added materialized influence rows and search-weight support;
  - added admin export and CLI reporting for graph hygiene.
- Prepared and applied graph hygiene artifacts:
  - canonicalized WF/Industriesalon and KWO/AEG organization data;
  - backfilled alias replay artifacts;
  - removed orphan or semantically incorrect graph edges.
- Added facade and consumer guardrails:
  - kept offer consumers on read-only query helpers;
  - added route/audit tooling for old offer/availability consumers;
  - documented facade ownership in architecture docs.
- Moved tour/public rendering ownership:
  - collapsed Führung public templates back to the theme;
  - cleaned old page-template overrides and tour-template SQL artifacts;
  - added guards around SuperSaaS occurrence reactivation and legacy occurrence-origin purge.
- Retired legacy public-read routes and stale runtime surfaces:
  - removed old REST compatibility paths;
  - kept compatibility only where required for migration or local verification.
- Rewrote history to remove obsolete production newsletter SQL from the active branch.
- Verification included PHP syntax checks, WP-CLI smoke checks, PHPCS/PHPStan passes for touched slices, and `git diff --check`.

## 2026-06-11

- Established the greenfield content/facade checkpoint:
  - set `iss-content` as the editorial source;
  - kept public presentation in the theme and data/business contracts in plugins;
  - documented the source-of-truth architecture in `docs/architecture/entity-model.md`.
- Separated exhibitions from programme/calendar projection:
  - introduced strict programme opt-in for exhibitions/projects;
  - excluded availability-only exhibition types from automatic calendar projection;
  - added SQL artifacts for availability cleanup, strict programme toggle backfill, and legacy programme meta purge.
- Introduced `iss-occurrences` as the durable occurrence projection service:
  - replaced hidden calendar runtime behavior;
  - added occurrence admin/CLI tooling and public query helpers;
  - began migration away from legacy `iss_calendar_item` assumptions.
- Added first read-only availability/entity relation/offer facade slices.
- Verification included WP-CLI source checks, occurrence smoke tests, SQL artifact dry-runs, and PHP syntax checks.

## 2026-06-10

- Reworked key exhibition pages and transfer artifacts:
  - redesigned Frauen im Werk and Kinder im Werk content structures;
  - added related-content and care/skin improvements;
  - prepared media and SQL transfer artifacts under `ops/sql/` and `ops/uploads/`.
- Added SuperSaaS admin bootstrap and began tour availability integration work.
- Improved archive/publication page behavior and related-card presentation.
- Verification included local route checks, WP-CLI template/meta checks, and artifact validation.

## 2026-06-09

- Tightened the Ausstellung plugin/theme contract:
  - clarified plugin data ownership and theme presentation ownership;
  - removed hidden shortcode-style rendering expectations;
  - improved exhibition archive/editor behavior.
- Continued related-card and carousel contract cleanup.
- Verification focused on archive rendering, editor compatibility, and PHP syntax checks.

## 2026-06-08

- Improved exhibition editor controls and archive semantics:
  - added reusable editorial metadata controls;
  - refined exhibition visibility and type handling;
  - improved block/editor alignment for exhibition content.
- Continued CSS contract cleanup around archive/card rendering.

## 2026-06-07

- Audited archive, graph, project, publication, and video ownership:
  - kept public UI in the theme;
  - kept content contracts in plugins;
  - preserved Classic Editor policy unless explicitly opted into Gutenberg.
- Salvaged and restructured selected Kinder/Project content.
- Added and refined publication/video landing and timeline authoring work.

## 2026-06-06

- Added Sammlungen media/template transfer artifacts:
  - created SQL and upload delta artifacts for media sync;
  - adjusted public template behavior for collection pages.
- Migrated Ausstellung backend metadata:
  - added SQL cleanup/migration artifacts;
  - aligned backend metadata with frontend requirements.

## 2026-06-05

- Established the repository documentation model:
  - current checkpoint in `handoff_CURRENT.md`;
  - durable history in `CHANGELOG.md`;
  - active follow-up in `TODO.md`;
  - detailed operational docs under `docs/`.
- Prepared Repair Cafe and Sammlungen media/content transfer artifacts.
- Added operational closeout conventions for staging/local exchange.

## 2026-06-04

- Prepared staging content delta artifacts and supporting transfer notes.
- Continued front-page, archive, and template-authority checks.

## 2026-06-03

- Prepared production transfer artifacts for front-page and video transcript sync.
- Fixed front-page/menu-shell/newsletter rendering details.
- Added featured-image fallback behavior and event-template improvements.

## 2026-06-02

- Added local video transcription workflow support for the `video` CPT.
- Improved video block metadata and editor compatibility.

## Older History

- Added `veranstaltung` scheme/meta support and Terminblatt v1 templates.
- Built `register_place` atlas-led dossier templates and supporting patterns.
- Added `projekt` single-shell work and chaptered-prose planning.
- Added brochure/photoalbum publication templates and related-content rails.
- Built publication/video landing and reusable publication timeline authoring.
- Added shared related-content carousel/card contracts.
- Recovered and converted local media assets as needed for theme work.
- Introduced and evolved the ISS content-model, archive, graph, programme, and
  commerce/plugin stack now summarized in the architecture docs.
