# Current Handoff

Updated: 2026-06-26

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Project pages now have the first registry/gesture review slice plus a Set-first media workflow:
  - project `material` accepts promoted PDFs/documents and renders them as public download/file cards instead of image thumbnails;
  - the project edit media picker opens project Sets first, with Media Library as the fallback search path;
  - archive-object/material cards on project pages use the shared card treatment instead of loose inline output.
- Project media intake browser review is closed locally. The broader duplicate
  Set issue is fixed in code:
  - project source Sets canonicalize to `project-set-<project-slug>`;
  - Event Drop attachment imports for projects now resolve through the same
    target resolver as raw incoming uploads, instead of creating a separate
    `event-drop-*` Set from attachment parent/meta;
  - local DB normalization merged the Walk of Fame duplicate
    `event-drop-walk-of-fame-schoeneweide` into
    `project-set-walk-of-fame-schoeneweide`.
- A shared surface/color contract is active across the theme:
  - `style.css` defines readable surface foreground, muted/subtle text, rules, heading text, and kicker accent/text tokens;
  - projects, Veranstaltung skins, card families, archive/publication rails, related network/place groups, Kalender timeline labels, primitive meta labels, and Ausstellung editorial skins have been moved away from raw accent-as-text usage;
  - publication singles now follow the same rule across standard, longread,
    photoalbum, and timeline layouts;
  - accent color is now intended for rails, dots, markers, links, and hover states, while readable text follows the active light/dark surface.
- The route `/veranstaltungen/fete-de-la-musique-berlin-2026/` is the real Veranstaltung URL. The singular `/veranstaltung/fete-de-la-musique-berlin-2026/` resolves locally to the flyer attachment because of a slug collision.

## Preserve

- Keep project intake Set-first. Raw/growing Sets remain private working collections; only promoted refs belong in project `galerie` / `material`.
- Keep one source-material project Set per project. Public Event Drop uploads
  using `event=projekt__<project-slug>` and internal project uploads should
  converge on the same `project-set-<project-slug>` Set.
- Keep public renderers off raw Set state.
- Keep project skin differences and public color/surface behavior theme-owned.
- Keep plugins focused on data/contracts. When plugin CSS emits frontend structure, it should consume shared theme tokens rather than define accent text colors independently.
- Do not revert unrelated local files. Untracked `iss-exhibition-composition-add.md` and `themes/industriesalon/theme2.json` are not part of this checkpoint.

## Next Action

- If this Set-normalization code is deployed to another DB, inspect the target
  for existing project-shaped `event-drop-*` duplicates and run:
  `wp eval 'print_r(iss_content_editorial_sets_normalize_project_duplicate_sets());'`
  only after the code is present.
- Continue the project skin/content review from `TODO.md`.

## Verified

- Project PDF/material render pass:
  - PHP lint for `themes/industriesalon/includes/projects-render.php`;
  - synthetic PDF render fallback;
  - browser checks on representative project routes.
- Project Set-first picker pass:
  - `node --check` for `plugins/iss-editorial/assets/admin.js`;
  - targeted Stylelint for `plugins/iss-editorial/assets/admin.css`;
  - WP-CLI/service smokes from the project Set workflow remain valid from the current slice.
- Surface/color-system pass:
  - targeted Stylelint for touched theme CSS files;
  - `git diff --check`;
  - browser contrast sweeps across representative project, Veranstaltung, Kalender, Ausstellungen, Führungen, Publikationen, Schöneweide, Verein, Archiv, and register-place routes;
  - `/kalender/` year label computes at contrast `5.62` after the final fix.
  - `/videos/` dark-surface labels compute at contrast `16.64` for the page kicker and `4.95` for video accent labels.
- Publication single pass:
  - targeted Stylelint for `themes/industriesalon/assets/css/publications.css`;
  - `/publikationen/nef-album/` reading-main contrast sweep returned `lowCount: 0`;
  - all 18 published publication singles returned `200` and the publication
    single contrast sweep returned `totalLow=0`.
- Project Set normalization pass:
  - PHP lint for `plugins/iss-content/includes/editorial-sets-service.php` and
    `plugins/iss-content/includes/editorial-sets-integrations.php`;
  - `bash tools/phpcs-target.sh` passed for both files;
  - `bash tools/phpstan-target.sh` passed for both files;
  - local normalizer returned `merged_sets=1` and `moved_items=2`;
  - idempotency rerun returned `merged_sets=0` and `moved_items=0`;
  - attachment resolver smoke maps project attachments `26694` and `26695` to
    `project-set-walk-of-fame-schoeneweide`, while Veranstaltung attachment
    `26659` remains in `event-drop-fete-de-la-musique-berlin-2026`.
- Final HTTP route smoke returned `200` for the representative home, project,
  Veranstaltung, Kalender, Ausstellung, Sammlung, Führung, Video, Publikation,
  Schöneweide, Verein, Archiv, and register-place routes.

## Dirty Worktree Notes

- The project/material/surface checkpoint was committed locally as `ff5de9d`.
- The publication single surface pass was committed locally as `91d7a93`.
- The project Set normalization fix is included in the current local exit checkpoint.
- No push has been made.
- Untracked unrelated/local files remain:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`
