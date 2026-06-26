# Current Handoff

Updated: 2026-06-26

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Project pages now have the first registry/gesture review slice plus a Set-first media workflow:
  - project `material` accepts promoted PDFs/documents and renders them as public download/file cards instead of image thumbnails;
  - the project edit media picker opens project Sets first, with Media Library as the fallback search path;
  - archive-object/material cards on project pages use the shared card treatment instead of loose inline output.
- A shared surface/color contract is active across the theme:
  - `style.css` defines readable surface foreground, muted/subtle text, rules, heading text, and kicker accent/text tokens;
  - projects, Veranstaltung skins, card families, archive/publication rails, related network/place groups, Kalender timeline labels, primitive meta labels, and Ausstellung editorial skins have been moved away from raw accent-as-text usage;
  - accent color is now intended for rails, dots, markers, links, and hover states, while readable text follows the active light/dark surface.
- The route `/veranstaltungen/fete-de-la-musique-berlin-2026/` is the real Veranstaltung URL. The singular `/veranstaltung/fete-de-la-musique-berlin-2026/` resolves locally to the flyer attachment because of a slug collision.

## Preserve

- Keep project intake Set-first. Raw/growing Sets remain private working collections; only promoted refs belong in project `galerie` / `material`.
- Keep public renderers off raw Set state.
- Keep project skin differences and public color/surface behavior theme-owned.
- Keep plugins focused on data/contracts. When plugin CSS emits frontend structure, it should consume shared theme tokens rather than define accent text colors independently.
- Do not revert unrelated local files. Untracked `iss-exhibition-composition-add.md` and `themes/industriesalon/theme2.json` are not part of this checkpoint.

## Next Action

- Start the next surface-system pass on publication singles, beginning with:
  `http://192.168.2.31:8082/publikationen/nef-album/`
- Then continue the project media intake browser review:
  - create/open a project Set from the project edit screen;
  - upload raw image/PDF material;
  - approve and promote mixed image/PDF selections;
  - confirm public `galerie` / `material` output;
  - verify project `Upload-Aufruf` opens `/event-drop/?event=projekt__<project-slug>`.

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
- Final HTTP route smoke returned `200` for the representative home, project,
  Veranstaltung, Kalender, Ausstellung, Sammlung, Führung, Video, Publikation,
  Schöneweide, Verein, Archiv, and register-place routes.

## Dirty Worktree Notes

- This checkpoint is ready for a local commit after the final route sweep.
- No push has been made.
- Untracked unrelated/local files remain:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`
