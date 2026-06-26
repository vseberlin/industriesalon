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
  - the left reading rail is controlled by a `publication_rail` gesture for
    JSON publications, while legacy non-JSON publications keep the old automatic
    rail behavior.
- Local `nef-album` (`post_id=18973`, `/publikationen/nef-album/`) is seeded as
  JSON with 4 sections: `intro`, `publication_rail`, `source`, `photoalbum`.
  The photoalbum section has 63 sheets from Archivset `19`.
- Transfer artifact:
  `ops/sql/2026-06-26-nef-album-publication-json.sql` writes the local
  `_iss_editorial_publication`, `_iss_editorial_enabled_publication`, and
  `_iss_publication_photoalbum_archivset_id` meta for `nef-album`.

## Preserve

- Keep theme-owned public rendering; plugins own data/contracts and dynamic
  block callbacks.
- Keep publications editable through the JSON gesture layer, not Gutenberg image
  blocks or hidden server-only render state.
- Keep legacy publication fallback behavior until each publication is explicitly
  migrated.
- The SQL artifact assumes the target already has publication `18973`,
  Archivset `19`, the referenced archive objects, and their attachment/media
  rows/files. No new upload artifact was created in this checkpoint.
- Do not revert unrelated local files:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- On any target DB that should receive the `nef-album` JSON state, deploy the
  code first, confirm the target has Archivset `19` and the existing NEF album
  media, then review/apply
  `ops/sql/2026-06-26-nef-album-publication-json.sql`.
- Continue publication migration with chroniken/timelines and longreads using
  the same explicit JSON gesture/rail contract.

## Verified

- `node --check plugins/iss-editorial/assets/admin.js`
- PHP lint for touched plugin/theme PHP files.
- `bash tools/phpcs-target.sh` for touched PHP files.
- `bash tools/phpstan-target.sh` for touched PHP files.
- `npx stylelint plugins/iss-editorial/assets/admin.css themes/industriesalon/assets/css/publications.css`
- `git diff --check`
- WP-CLI confirmed `nef-album` JSON sections and 63 sheet payload.
- `/publikationen/nef-album/` returned `200`, rendered 63 album items, 63 nav
  links, the JSON rail heading, and no related rail.
- Playwright desktop/mobile checks at `1440px` and `390px` showed no horizontal
  overflow.
- SQL artifact decode check confirmed rail options:
  `{"show_nav":true,"show_summary":false,"show_related":false,"variant":"detailed"}`.

## Dirty Worktree Notes

- This checkpoint was committed locally before exit.
- Untracked unrelated/local files remain:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`
