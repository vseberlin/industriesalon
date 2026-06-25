# Current Handoff

Updated: 2026-06-25

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Added the first `projekt` registry/gesture editing slice.
- Ownership stays inside the existing model:
  - `iss-content` registers the `projekt` editorial format and keeps Sets as the private intake/gallery layer.
  - `iss-editorial` stores the reviewed project document as `_iss_editorial_projekt`.
  - The theme owns project public rendering and falls back to legacy `post_content` unless `_iss_editorial_enabled_projekt` is enabled per post.
- Project gestures now include `kapitel`, `fliesstext`, `massstab`, `projekt_rail`, `galerie`, `image_wall`, `material`, and `schluss`.
- Project `massstab` now has structured `facts` rows (`value` plus `label`) so editors do not need inline `<strong>` markup in body text. Body text remains optional fallback/explanation.
- Project `kapitel`, `fliesstext`, and `schluss` body fields now use a small constrained rich-text editor. Stored body HTML is limited to `p`, `br`, `strong`, `em`, `a[href]`, `ul`, `ol`, and `li`.
- The project importer promotes legacy `.iss-context-panel` fact cards into separate `massstab` sections and skips those panels when building prose bodies.
- The project importer also restores the optional `projekt_rail` section in refreshed candidates. The generated rail, generated related places, and generated related-content group now render together inside one sticky generated side stack.
- `projekt_rail` is optional. If present, the theme appends the rail below the project meta panel and derives links from `kapitel` and `schluss` anchors; if absent, no rail renders.
- JSON-backed project pages now append generated related places plus one compact related-content group below the meta/rail stack. This comes from the existing relations layer, not from authored project JSON.
- First theme-owned project skin review pass is active:
  - allowed project skins are now `brief`, `dossier`, `field`, and `standard`;
  - `dossier` renders `projekt_rail` as a dark primary horizontal CTA band in the content flow, stacks the project story grid into a centered 75rem container, pairs `kapitel` plus following `massstab` sections into chapter/fact spreads, and moves generated places/context into a footer grid inspired by the Veranstaltung network/footer treatment;
  - `brief` keeps the project compact and suppresses the heavy chapter rail;
  - `field` keeps a side index/context stack and applies a darker spatial treatment without introducing a map schema.
- Project `galerie` now renders through project-owned carousel markup using the shared strip-carousel JS hooks. Project `material` can select non-image media/files in the JSON UI, and non-image promoted media refs render as file cards instead of disappearing.
- Project Sets are now the admin workflow entrance: the project edit metabox can
  create an attached project Set, open it, or open it for raw upload. The
  Workbench stores raw uploads as private `external_upload` Set items and keeps
  Media Library selection as a fallback. Approved image/video Set items promote
  into `galerie`; approved PDFs/documents and archive objects promote into
  `material`. The growing Set remains attached and preserved.
- Project Set lifecycle rule: new real `projekt` saves ensure a
  source-material Set. Project trash/delete can quarantine disposable raw intake
  and mark project-only Sets `stale`, but it must not delete promoted, retained,
  archive-candidate, Media Library, or shared Set material.
- Project public contribution intake now reuses the existing Event Drop gesture:
  project `upload_intake` sections render a CTA to `/event-drop/` with
  `event=projekt__<project-slug>`, and Event Drop sync routes incoming
  third-party uploads into the attached source-material project Set as pending
  `external_upload` items with project target provenance.
- Refreshed local candidates for all seven published projects, inserted the optional rail gesture and structured facts into those candidates, and enabled JSON rendering for visual review.
- Local review assignments: `walk-of-fame-schoeneweide` uses `brief`, `stadtlabor-wilhelminenhofstrasse` uses `field`, and `futura-biennale-2027` stays on `dossier`.
- Transfer artifacts:
  - `ops/sql/2026-06-25-project-editorial-json-candidates.sql`
  - `ops/sql/2026-06-25-project-editorial-enable-json-review.sql`
  - `ops/sql/2026-06-25-project-skin-review-assignments.sql`

## Changed Files For This Slice

- `plugins/iss-content/includes/editorial.php`
- `plugins/iss-content/includes/editorial-sets-admin.php`
- `plugins/iss-content/includes/editorial-sets-rest.php`
- `plugins/iss-content/includes/editorial-sets-service.php`
- `plugins/iss-content/includes/editorial-sets-promotion.php`
- `plugins/iss-content/includes/editorial-sets-integrations.php`
- `plugins/iss-content/assets/admin-editorial-sets.css`
- `plugins/iss-content/assets/admin-editorial-sets.js`
- `plugins/iss-editorial/assets/admin.css`
- `plugins/iss-editorial/assets/admin.js`
- `plugins/iss-editorial/includes/cli.php`
- `plugins/iss-editorial/includes/storage.php`
- `themes/industriesalon/functions.php`
- `themes/industriesalon/includes/projects-render.php`
- `themes/industriesalon/assets/css/single-content.css`
- `docs/architecture/editorial-platform.md`
- `CHANGELOG.md`
- `handoff_CURRENT.md`
- `ops/sql/2026-06-25-project-editorial-json-candidates.sql`
- `ops/sql/2026-06-25-project-editorial-enable-json-review.sql`
- `ops/sql/2026-06-25-project-skin-review-assignments.sql`

## Preserve

- Keep project intake Set-first. Do not treat `galerie` or `image_wall` as raw dumps.
- Keep public renderers off raw Set state. Only promoted references belong in `_iss_editorial_projekt`.
- Keep the legacy project fallback available; JSON rendering is currently enabled locally only for visual review.
- Keep project dates, programme flags, related places, featured images, and excerpts outside the gesture document.
- Keep project rail links derived from section anchors. Do not add manually maintained rail link lists.
- Keep related places/content generated by relations. Do not add them as manual project gestures unless the content is intentionally editorial story material.
- Keep structured facts in `massstab.facts`; do not rely on inline `<strong>` inside `body` as the editor-facing fact contract.
- Keep project prose rich text constrained. Do not add arbitrary spans, classes, inline styles, images, embeds, tables, or editor-controlled layout markup to `body`.
- Keep project skin differences theme-owned. Do not add `density`, `tone`, manual gesture lists, or project-map schema from the visual proposal unless a later content requirement proves the current slots insufficient.
- Keep project deletion conservative: only disposable raw `external_upload`
  intake can be quarantined with decay. Never delete promoted, retained,
  archive-candidate, Media Library, or shared Set material from project
  lifecycle hooks.
- Keep project contribution intake on the existing `/event-drop/` route. The
  project marker is `projekt__<project-slug>` because WordPress title
  sanitization collapses doubled hyphens but preserves underscores.

## Next Action

- Visually review the three project skin examples on localhost:
  `futura-biennale-2027` (`dossier`, primary horizontal chapter CTA, centered
  75rem chapter/fact spreads, footer context), `walk-of-fame-schoeneweide`
  (`brief`, compact no heavy chapter rail and project gallery carousel), and
  `stadtlabor-wilhelminenhofstrasse` (`field`, side index/context). Also test
  the project edit-screen Set flow in browser, including raw image/PDF upload,
  approval, promotion, public `galerie` / `material` output, new-project Set
  creation, and a project `Upload-Aufruf` CTA that opens `/event-drop/` with
  `event=projekt__<project-slug>`.

## Verified

- PHP lint for changed PHP files.
- Direct Stylelint passed for `themes/industriesalon/assets/css/single-content.css`.
- `node --check` and targeted ESLint passed for `plugins/iss-editorial/assets/admin.js`.
- Targeted PHPCS and PHPStan passed for changed PHP files.
- WP-CLI registry smoke returned the `projekt` format with the expected gestures and skins.
- `wp iss-editorial projekt-dry-run` found candidates for all seven published projects.
- `wp iss-editorial projekt-import-candidate --post=all --force` refreshed all candidates with section anchors; `_iss_editorial_enabled_projekt` is now `1` for all seven project posts.
- SQL candidate artifact loaded into a temporary DB table with 14 rows and 7 disabled flags.
- Synthetic render smoke for `futura-biennale-2027` emitted project section markup and a material layout.
- HTTP route checks returned `200`, one JSON content wrapper, and rail links for all seven project pages.
- Playwright screenshots confirmed the project rail sits under the meta panel on desktop and stacks between meta and content on mobile.
- Final route sweep confirmed all seven JSON project pages render one generated context stack and no legacy hardcoded `iss-project-rail-stack`.
- Structured project facts/rail pass: PHP lint, PHPCS, PHPStan, targeted Stylelint, targeted ESLint, WP-CLI format/sanitizer smoke, project dry-run, local postmeta counts, artifact temp-table import, and Playwright desktop/mobile overflow checks passed. All seven JSON project pages render one generated sticky side stack, one generated rail, one related-place block, one related-content block, no legacy rail stack, and no horizontal overflow. Futura currently renders two key-point sections with seven fact cards.
- Project rich-text editor pass: PHP lint for `storage.php`, `node --check` for `admin.js`, targeted Stylelint for `admin.css`, WP-CLI sanitizer smoke, `git diff --check`, and `git diff --cached --check` passed.
- Project skin review pass: PHP lint for `projects-render.php`, direct Stylelint for `single-content.css`, WP-CLI skin registry smoke, route checks for Futura/Walk/Stadtlabor skin classes and structural hooks, `ops/sql/2026-06-25-project-skin-review-assignments.sql` temp-table import (`6` rows: `3` documents, `3` enabled flags), `git diff --check`, and Playwright desktop/mobile checks passed with `overflowX = 0` for all three representative pages. Futura now reports one primary horizontal CTA rail, two dossier spreads, two fact rails, one footer context, no side context, and no loose `layout-key-points` sections; the dossier story flow measures 1200px wide on 1440px/1920px desktop viewports and stacks to 350px on a 390px mobile viewport.
- Project gallery/material pass: PHP lint for `projects-render.php`, `node --check` for `plugins/iss-editorial/assets/admin.js`, direct Stylelint for `single-content.css`, synthetic PDF render fallback, and Playwright desktop/mobile checks passed for Walk of Fame and Stadtlabor project galleries. Walk renders one project gallery carousel with five items and `overflowX = 0`; Stadtlabor renders one project gallery carousel with two items and `overflowX = 0`. No SQL/upload artifact changed in this pass.
- Project Set workflow pass: PHP lint, PHPCS, PHPStan, ESLint, and Stylelint
  passed for the changed Set admin/service/REST/promotion assets. WP-CLI
  verified the Sets menu under Operations, the empty project metabox create
  actions for Futura, context-filtered Set listing, a transaction-rolled mixed
  promotion smoke where one image went to `galerie` and one PDF went to
  `material`, and a transaction-rolled raw PDF upload smoke where an
  `external_upload` item imported on promotion and became a project `material`
  ref. No SQL/upload artifact changed in this pass.
- Project Set lifecycle pass: PHP lint, PHPCS, and PHPStan passed for
  `editorial-sets-integrations.php`. Transaction-rolled WP-CLI smokes confirmed
  a new draft project gets one source Set; project-only deletion cleanup marks
  disposable raw intake `rejected` with decay while leaving a `wp_media` item
  untouched and marking the Set `stale`; a Set attached to another live context
  stays `working` and leaves raw intake `pending`.
- Project contribution intake pass: PHP lint, PHPCS, PHPStan, and targeted
  Stylelint passed for the changed project contribution files. WP-CLI confirmed
  project upload CTA URLs use `/event-drop/?event=projekt__<project-slug>` and
  a transaction-rolled Event Drop sync smoke routed one synthetic incoming file
  into the project source Set with `target_type=projekt` provenance. A
  transaction-rolled render smoke confirmed an `upload_intake` section emits the
  expected CTA markup and Event Drop URL.
- `git diff --check` and `git diff --cached --check` passed.
- `npm run lint:css -- themes/industriesalon/assets/css/single-content.css` still scans the full configured CSS set and fails on pre-existing `themes/industriesalon/assets/css/atlas-app.css:962`.

## Dirty Worktree Notes

- No commit was made.
- Pre-existing unrelated dirty files remain and were not cleaned here, including several Veranstaltung/admin files, `iss-exhibition-composition-add.md`, and `themes/industriesalon/theme2.json`.
