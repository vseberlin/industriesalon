# Current Handoff

Updated: 2026-06-28

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Local checkout contains a local-only checkpoint for related graph autonomy plus editorial admin simplification. It has not been pushed.
- Related graph autonomy foundation is in local commits ahead of `origin/main`:
  - relation provenance/status schema, dirty queue, bounded reconcile CLI, health/fixture checks, and editorial-signal export/import;
  - related self-promotion list-table filter/disable workflow;
  - graph health currently reports no relation integrity errors.
- Editorial admin simplification now uses the shared `iss-content` dashboard assembly for compatible classic/editorial screens:
  - converted screens: `veranstaltung`, `projekt`, `ausstellung`, `publication`, `fuehrung`, and `rueckblick`;
  - required facts now sit in the first dashboard row with excerpt and featured image, before the composition canvas;
  - generic basis/data boxes use the editor-facing label `Pflichtangaben`;
  - shared relation/reference controls open from a right-rail `Verknüpfte Inhalte` launcher where the owner metabox exists;
  - related-content promotion is a simple `Inhalt promoten` checkbox for normal editors, while admins keep full graph metadata controls.
- Projekt front-page ordering still uses native `menu_order`; normal editors reorder projects with drag and drop in the unfiltered Projekt list table, while admins keep the raw repair field.
- Storage/render ownership remains unchanged: `iss-content`, `iss-editorial`, `iss-publications`, Führung, `iss-relations`, `iss-graph`, `iss-archive`, and the theme keep their existing contracts.

## Preserve

- Do not force the classic dashboard mover onto Gutenberg screens. `page`, `post`, and Video block-editor surfaces still need separate adapter decisions.
- Do not move graph/relation/archive/storage ownership into `iss-content`; the dashboard only rearranges existing owner controls.
- Do not expose graph diagnostics or repair controls to normal editors by default.
- No SQL or upload artifact is required for the admin UI simplification itself.
- When deploying the graph autonomy commits, run the graph migration/reconcile checks from `TODO.md`.
- Leave unrelated local untracked files out of Git unless the user explicitly asks:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- Push only when explicitly requested.
- If this local checkpoint is deployed to a target, run the graph autonomy migration/health steps in `TODO.md`.
- Next editorial admin slice should be content-specific, not another shared layout pass:
  - decide Publication related-publications UX;
  - decide whether Rueckblick needs more relation owner controls;
  - design the separate Gutenberg adapter for `page`, `post`, and Video if those screens should enter the shared workflow.

## Verified Locally

- `php -l` on changed PHP files.
- Targeted PHPCS and PHPStan for changed PHP files.
- Targeted ESLint and Stylelint for the admin assets.
- `git diff --check`.
- WP-CLI dashboard config checks for administrator and editor users across `veranstaltung`, `projekt`, `ausstellung`, `publication`, `fuehrung`, and `rueckblick`.
- Playwright browser smoke:
  - rail buttons point to real owner metaboxes;
  - promotion metadata fields are hidden for normal editors;
  - required facts are first-row panels and composition is second for the converted CPTs.
- `wp iss-graph autonomy-health` completed with no relation integrity errors.
