# Current Handoff

Updated: 2026-06-28

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- The related-graph autonomy foundation, shared editorial admin dashboard simplification, and Veranstaltung admin polish checkpoint are prepared for the requested push to `origin/main`.
- Veranstaltung editing now uses a structural `Struktur` choice plus a hidden semantic `veranstaltung_art` taxonomy for `Art` filters. Active structures are `Veranstaltung`, `Programm / Fest`, and `Serientermin`; active semantic options include Vortrag, Gespräch, Lesung, Präsentation, Workshop, Konzert, Film, and Repair Café.
- Veranstaltung editor polish is current: editor-facing copy avoids storage/render terms, promotion sits in `Struktur & Art`, `Redaktionsstatus` is compact, linked-content actions explain why to use them, editor Screen Options/postbox action controls are locked, and default Posts/Pages are hidden from non-admin editor navigation.
- Veranstaltung list-table filters now expose Struktur, Art, and visibility only; the unused WordPress category dropdown is disabled for Veranstaltung.
- Storage/render ownership remains unchanged: `iss-content`, `iss-editorial`, `iss-publications`, Führung, `iss-relations`, `iss-graph`, `iss-archive`, and the theme keep their existing contracts.

## Preserve

- Do not add drag/drop/manual ordering to Veranstaltungen without a separate product decision; event ordering is date/calendar-driven.
- Do not force the classic dashboard mover onto Gutenberg screens. `page`, `post`, and Video block-editor surfaces still need separate adapter decisions.
- Do not move graph/relation/archive/storage ownership into `iss-content`; the dashboard only rearranges existing owner controls.
- No SQL or upload artifact is required for the Veranstaltung admin polish. The hidden semantic taxonomy terms and legacy term backfill are code-driven.
- When deploying the graph autonomy commits, run the graph migration/reconcile checks from `TODO.md`.
- Leave unrelated local untracked files out of Git unless the user explicitly asks:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- Other agents can continue from `origin/main` after the requested push.
- If deployed to a target, run the graph autonomy migration/health steps in `TODO.md`.
- Remaining editorial admin work should be content-specific: Publication related-publications UX, Rueckblick relation controls, and a separate Gutenberg adapter decision for `page`, `post`, and Video.

## Verified Locally

- `php -l` on changed PHP files.
- Targeted PHPCS and PHPStan for changed PHP files.
- Targeted Stylelint and `git diff --check`.
- WP-CLI checks: Veranstaltung registry/repository, semantic taxonomy/terms, and `iss-graph view-contract-audit`.
- Playwright editor smokes for Veranstaltung edit/list screens:
  - structure/semantic selects save correctly;
  - editor Screen Options and postbox action icons are locked;
  - Posts/Pages menu items are hidden for editors;
  - promotion is inside `Struktur & Art`;
  - compact Redaktionsstatus renders without the old facts table;
  - list-table category filter is gone while Struktur/Art/visibility filters remain.
