# Current Handoff

Updated: 2026-06-26

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Publication JSON editing now covers the first photoalbum slice:
  - `publication` has JSON gestures for `intro`, `source`, optional
    `publication_rail`, and editable `photoalbum`;
  - photoalbum sheets can be imported from Archivset or editorial Set sources,
    then reordered, hidden, captioned, and given nav titles in the custom JSON
    editor;
  - public photoalbum rendering now prefers explicit JSON sheets and falls back
    to legacy Gutenberg/Archivset behavior for unmigrated publications;
  - theme-owned publication skins now include `blueprint-matrix`, a
    viewport-wide technical matrix treatment for photoalbums with per-sheet
    description drawers and footer-style place plus related-content context;
    the footer context renders from relation data in the theme, not by calling
    related-content block callbacks during photoalbum rendering;
  - blueprint grid cells render cell-sized `medium` thumbnails from
    attachment/archive-object IDs while preserving full-size detail links, and
    no longer apply a per-image CSS filter during scrolling;
  - the left reading rail is controlled by a `publication_rail` gesture for
    JSON publications, while legacy non-JSON publications keep the old automatic
    rail behavior.
- Local photoalbum blueprint set:
  - `nef-album` (`post_id=18973`, `/publikationen/nef-album/`) has 63 sheets
    from Archivset `19`;
  - `fotoalbum-labor-konstruktions-und-versuchswerk-oberspree-1946`
    (`post_id=18894`) has 52 sheets from resolved Media Library refs;
  - `fotoalbum-produkte-lkvo-1946` (`post_id=18948`) has 23 sheets from
    resolved Media Library refs;
  - `fotoalbum-produktion-im-werk-fuer-fernmeldewesen-hf-1951`
    (`post_id=19038`) has 34 sheets from resolved Media Library refs.
  All four documents use skin `blueprint-matrix`, 4 sections (`intro`,
  `publication_rail`, `source`, `photoalbum`), and a primary relation to
  `Ostendstraße 1-5 / Behrensbau` (`register_place` `17976`). The three
  non-NEF albums use manual source metadata with `WF-Museum` as the visible
  source label.
- Transfer artifacts:
  - `ops/sql/2026-06-26-nef-album-publication-json.sql` writes the local
    `nef-album` JSON state, layout/meta state, and Behrensbau relation.
  - `ops/sql/2026-06-26-photoalbum-blueprint-other-albums.sql` writes the same
    DB-backed blueprint state for posts `18894`, `18948`, and `19038`.

## Preserve

- Keep theme-owned public rendering; plugins own data/contracts and dynamic
  block callbacks.
- Keep publications editable through the JSON gesture layer, not Gutenberg image
  blocks or hidden server-only render state.
- Keep legacy publication fallback behavior until each publication is explicitly
  migrated.
- The SQL artifacts assume the target already has the relevant publication
  posts, Archivset `19` for `nef-album`, referenced Media Library attachment
  rows/files for the manual WF albums, and register place `17976`. No new
  upload artifact was created in this checkpoint.
- Do not revert unrelated local files:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- On any target DB that should receive the photoalbum blueprint state, deploy
  the code first, confirm the target has the required publication/media/place
  rows, then review/apply
  `ops/sql/2026-06-26-nef-album-publication-json.sql` and
  `ops/sql/2026-06-26-photoalbum-blueprint-other-albums.sql`.
- Continue publication migration with chroniken/timelines and longreads using
  the same explicit JSON gesture/rail contract.

## Verified

- `node --check plugins/iss-editorial/assets/admin.js`
- PHP lint for touched plugin/theme PHP files.
- `bash tools/phpcs-target.sh` for touched PHP files.
- `bash tools/phpstan-target.sh` for touched PHP files.
- `npx stylelint plugins/iss-editorial/assets/admin.css themes/industriesalon/assets/css/publications.css`
- `git diff --check`
- Current safety rerun after the blueprint renderer fix:
  - PHP lint passed for
    `themes/industriesalon/includes/publications-render.php` and
    `plugins/iss-publications/includes/render-publication.php`;
  - `bash tools/phpcs-target.sh themes/industriesalon/includes/publications-render.php plugins/iss-publications/includes/render-publication.php`;
  - `bash tools/phpstan-target.sh themes/industriesalon/includes/publications-render.php plugins/iss-publications/includes/render-publication.php`;
  - `npx stylelint themes/industriesalon/assets/css/publications.css`;
  - `/publikationen/nef-album/` returned `200` locally with 63 blueprint cells,
    1 related-place item, and 4 related-content items;
  - WP-CLI render checks confirmed all four local blueprint albums render the
    expected sheet counts and footer-context counts;
  - direct PHP render of `nef-album` succeeded with `memory_limit=64M` and no
    WordPress block-support warning from fake block context rendering.
- Blueprint render-load follow-up:
  - `/publikationen/nef-album/` returned `200` locally;
  - rendered markup now has 63 blueprint grid images as
    `attachment-medium size-medium`, with only the separate featured image
    still using `attachment-large size-large`;
  - `php -l themes/industriesalon/includes/publications-render.php`;
  - `bash tools/phpcs-target.sh themes/industriesalon/includes/publications-render.php`;
  - `bash tools/phpstan-target.sh themes/industriesalon/includes/publications-render.php`;
  - `npx stylelint themes/industriesalon/assets/css/publications.css`;
  - `git diff --check`.
- WP-CLI confirmed the four local blueprint photoalbum JSON payloads, enabled
  flags, sheet counts (`63`, `52`, `23`, `34`), source labels, rail options,
  and Behrensbau place relations.
- The four photoalbum routes returned `200`, rendered the expected sheet counts,
  no reading nav, one related place block, and four related-content cards.
- Playwright desktop/mobile checks at `1440px` and `390px` showed no horizontal
  overflow, the collapsed rail taking no content space, and the Behrensbau
  related-place plus related-content footer context rendering inside each album.
- SQL artifact decode check confirmed rail options:
  `{"show_nav":true,"show_summary":false,"show_related":false,"variant":"detailed"}`.

## Git Notes

- This checkpoint was committed and pushed to `origin/main` at
  `247651f Reduce blueprint photoalbum render load`.
- Untracked unrelated/local files remain:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`
