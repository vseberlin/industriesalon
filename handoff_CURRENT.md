# Current Handoff

Updated: 2026-06-27

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Editorial admin simplification SOW is implemented for the compatible classic/editorial edit screens using one shared dashboard assembly layer in `iss-content`.
- Converted screens:
  - `veranstaltung`: identity, composition, required facts, relations/references, publish;
  - `projekt`: identity, `iss-editorial` composition, project facts, relations/references;
  - `ausstellung`: identity, `iss-editorial` composition, Ausstellung facts/ACF fallback, relations/references;
  - `publication`: identity, `iss-editorial` composition, publication facts, sale controls, relations/references;
  - `fuehrung`: identity, `iss-editorial` composition, tour facts/calendar mapping, relations/references;
  - `rueckblick`: identity, `iss-editorial` composition, relations/references.
- Existing render/save/storage owners are preserved. The dashboard moves existing DOM/metabox controls only:
  - `iss-editorial` keeps JSON composition;
  - `iss-content` keeps Veranstaltung, Projekt, Ausstellung, Video, Set, and CPT contracts;
  - `iss-publications` keeps publication bibliography/display/sale/related controls;
  - Führung module keeps tour facts and gallery fields;
  - `iss-relations`, `iss-graph`, and `iss-archive` keep their own relation/reference controls.
- Gutenberg screens `video`, `page`, and `post` are explicitly outside this classic dashboard path and now report `dashboard=no`.

## Preserve

- Do not migrate storage as part of this admin-shell work; `_iss_content_json`, `_iss_editorial_*`, publication meta, tour meta, graph/relation/archive tables all stay authoritative.
- Do not force the classic `titlediv`/postbox dashboard mover onto Gutenberg screens; they need a separate block-editor adapter if simplification continues there.
- Administrator technical access remains available during migration; normal editor paths hide technical/duplicate boxes where the dashboard is active.
- Do not revert unrelated local untracked files:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- If continuing the SOW, design a separate Gutenberg adapter for `video`, `page`, and `post` rather than extending the classic DOM mover.
- For any converted CPT, next functional work should be edit/save/reload parity checks with a normal editorial role before hiding more legacy boxes or purging anything.
- Push or otherwise exchange the local commits only when the local checkpoint should become shared through GitHub `main`.

## Verified

- PHP: Docker `php -l`, `bash tools/phpcs-target.sh plugins/iss-content/includes/admin.php`, and `bash tools/phpstan-target.sh plugins/iss-content/includes/admin.php`.
- JavaScript/CSS: `node --check plugins/iss-content/assets/admin-editor-modal-controls.js`, targeted ESLint, targeted Stylelint.
- Browser sweep passed for representative screens:
  - Veranstaltung `25808`: 5 sections, no desktop overflow;
  - Projekt `25720`: 4 sections, `iss-editorial` canvas in composition, no desktop overflow;
  - Ausstellung `26381`: 4 sections, `iss-editorial` canvas in composition, no desktop overflow;
  - Publication `25731`: 5 sections, `iss-editorial` canvas in composition, no desktop overflow after graph-control containment;
  - Führung `12191`: 4 sections, `iss-editorial` canvas in composition, no desktop overflow;
  - Rückblick new-post screen: 3 sections, `iss-editorial` canvas in composition, no desktop overflow.
- Mobile publication overflow check passed after constraining dashboard graph controls.
- `git diff --check` passed.
- Git exchange before commit: local `HEAD=28d4362`, `origin/main=053ac70`; local branch was ahead by 13 and not pushed.
