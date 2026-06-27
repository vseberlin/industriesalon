# Current Handoff

Updated: 2026-06-27

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Publication JSON editing now covers the first photoalbum slice:
  - `publication` has JSON gestures for `intro`, `source`, optional
    `publication_rail`, `longread_chapter`, `longread_quote`,
    `timeline_item`, and editable `photoalbum`;
  - photoalbum sheets can be imported from Archivset or editorial Set sources,
    then reordered, hidden, captioned, and given nav titles in the custom JSON
    editor;
  - longread chapters and timeline stations now normalize from the JSON
    document into the same payload shapes as the legacy publication blocks, so
    existing theme-owned renderers remain authoritative and unmigrated
    publications keep their Gutenberg/block fallback;
  - longread quote sections render in document order between chapters; normal
    longread imagery is attached to `longread_chapter` through `media_refs`
    with an inline/right-aside placement switch; only `longread_chapter`
    sections generate reading-nav links;
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
  - theme-owned publication skins now also include `longread-poster`, a quiet
    typographic longread treatment with pull quotes and constrained
    inline/right-aside chapter imagery;
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
  - `ops/sql/2026-06-27-publication-longread-timeline-json.sql` writes the
    migrated local JSON state, enabled flag, and layout meta for 12 existing
    longread/timeline publication posts: timelines `18864`, `18865`, `18873`
    and longreads `18878`, `18881`, `18886`, `21105`, `21109`, `21110`,
    `21111`, `21114`, `21125`. The nine longreads in this artifact are assigned
    to skin `longread-poster`.

## Preserve

- Keep theme-owned public rendering; plugins own data/contracts and dynamic
  block callbacks.
- Keep publications editable through the JSON gesture layer, not Gutenberg image
  blocks or hidden server-only render state.
- Keep legacy publication fallback behavior until each publication is explicitly
  migrated.
- The SQL artifacts assume the target already has the relevant publication
  posts, Archivset `19` for `nef-album`, referenced Media Library attachment
  rows/files for the manual WF albums and timeline station images, and register
  place `17976`. No new upload artifact was created in this checkpoint.
- Migrated publication `18873` is still only an intro/rail timeline stub in the
  local source content, so its JSON document has no `timeline_item` sections.
- Do not revert unrelated local files:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- On any target DB that should receive the photoalbum blueprint state, deploy
  the code first, confirm the target has the required publication/media/place
  rows, then review/apply
  `ops/sql/2026-06-26-nef-album-publication-json.sql` and
  `ops/sql/2026-06-26-photoalbum-blueprint-other-albums.sql`.
- On any target DB that should receive the longread/timeline migration, deploy
  the code first, confirm the target has the 12 publication posts and referenced
  timeline image attachment rows/files, then review/apply
  `ops/sql/2026-06-27-publication-longread-timeline-json.sql`.
- Curator-review the migrated drafts/stub in the JSON editor before publishing,
  especially `18873` because it has no dated timeline stations. For longreads,
  review whether each story should add `longread_quote` moments or chapter
  `media_refs`; the migrated source content currently remains chapter-only.

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
- Publication longread/timeline gesture-definition check:
  - PHP lint passed for touched publication/editorial PHP files;
  - `node --check plugins/iss-editorial/assets/admin.js`;
  - `npx eslint plugins/iss-editorial/assets/admin.js`;
  - `bash tools/phpcs-target.sh` for touched PHP files;
  - `bash tools/phpstan-target.sh` for touched PHP files;
  - WP-CLI confirmed the `publication` format sections are now `intro`,
    `source`, `publication_rail`, `longread_chapter`, `longread_quote`,
    `timeline_item`, and `photoalbum`;
  - temporary draft-post WP-CLI checks confirmed JSON longread payload/nav/rail
    normalization, JSON timeline station/source normalization, theme render
    filter smoke output, and cleanup of temporary draft posts.
- Publication longread/timeline content migration check:
  - local DB state now has enabled publication JSON for 12 longread/timeline
    posts, with 27 timeline stations for `18864`, 19 for `18865`, no station
    sections for draft stub `18873`, one chapter for eight longreads, and nine
    chapters for `21125`;
  - SQL artifact decode check confirmed 36 generated meta rows and 12 valid
    `_iss_editorial_publication` JSON documents in
    `ops/sql/2026-06-27-publication-longread-timeline-json.sql`;
  - WP-CLI payload checks confirmed JSON timeline payload item counts and
    JSON longread `sections`/`nav_items` counts;
  - the six published migrated routes returned `200` locally; the other six
    migrated publications remain drafts and do not have public pretty routes.
- Publication longread-poster skin check:
  - `longread-poster` is exposed as a publication skin and assigned locally to
    all nine migrated longreads;
  - WP-CLI confirmed the `publication` format now exposes `longread_quote` and
    chapter `media_refs`/`media_layout`, and that JSON longread payload counts
    still match the migrated chapter/nav counts;
  - temporary draft smoke test confirmed `longread_quote` renders in document
    order and chapter media renders in the theme with `aside-right` getting the
    expected class; quote sections do not add reading-nav items;
  - Playwright checked the four published longread routes at `1440px` and
    `390px`: all returned `200`, had the `longread-poster` body class, matched
    expected chapter/nav counts, and had no horizontal overflow;
  - temporary published hidden-rail smoke route confirmed an empty
    `publication_rail` collapses visually and the reading main spans the
    desktop grid;
  - SQL artifact decode check confirmed 36 generated meta rows, 12 valid JSON
    documents, and `longread-poster` skins on all nine migrated longreads.
- Publication longread chapter-media refactor check:
  - WP-CLI confirmed the `publication` format now exposes sections `intro`,
    `source`, `publication_rail`, `longread_chapter`, `longread_quote`,
    `timeline_item`, and `photoalbum`, with `longread_chapter` supporting
    `anchor`, `media_refs`, and `media_layout`;
  - temporary draft smoke test confirmed chapter `media_refs` resolve into the
    longread payload, render inside the chapter with `aside-right`, and preserve
    one reading-nav item for the chapter while the quote remains a separate
    non-nav flow section;
  - temporary published route smoke checked the same chapter-media longread at
    `1440x1000` and `390x900`: both returned `200`, rendered one chapter media
    figure, one quote, one nav item, the `longread-poster` body class, and no
    horizontal overflow;
  - no SQL artifact rewrite was required because the migrated local longread
    JSON documents are still chapter-only and contain no removed
    `longread_media` sections.

## Git Notes

- Last pushed checkpoint: `247651f Reduce blueprint photoalbum render load`.
- The 2026-06-27 publication JSON gesture/content migration and longread-poster
  skin work is local and not committed yet.
- Untracked unrelated/local files remain:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`
