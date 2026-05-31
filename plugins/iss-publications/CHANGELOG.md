# Changelog

All notable changes for `iss-publications` are documented here.

## [0.1.1] - 2026-05-27

### Added
- Added editor-facing `iss/publication-photoalbum` and `iss/publication-photoalbum-sheet` blocks.
- Added the `iss-publications/photoalbum-starter` pattern for block-authored photoalbum publications.

### Changed
- Hardened photoalbum parsing/rendering so explicit photoalbum blocks use the same payload contract and public renderer as imported album markup.
- Made explicit `iss/publication-photoalbum` blocks promote otherwise-standard publications to the photoalbum layout contract.
- Moved photoalbum section heading copy into editor-authored block attributes instead of generated renderer text.
- Added the `iss-publication-photoalbum-ready` body class only when a photoalbum has a renderable parsed payload.
- Kept parsed photoalbums out of the `iss-publication-no-cover` state so the theme can derive the visible cover from the first album sheet.
- Broadened the shared publication related-rail block so it can render for photoalbum layouts as well as chaptered longreads.
- Removed stale plugin-side photoalbum presentation markup; raw authored content is now the fallback when no theme renderer is available.
- Removed the remaining PHPCS slow-query warnings by replacing publication-grid meta/tax query filters with runtime filtering of the ordered publication collection.
- Simplified the one-time disallowed-template cleanup to avoid meta-key/meta-value queries.
- Removed the unused related-publications helper that duplicated older taxonomy-query behavior.
- Updated the shared publication reading-navigation block label so it describes longread and photoalbum use.
- Clarified the publication layout admin help text for block-authored timeline and photoalbum layouts.
- Clarified that photoalbum starter sheets need images before the generated album navigation appears.

## [0.1.0] - 2026-04-26

### Added
- Added `publication` CPT with `publication_type` and `publication_topic` taxonomies.
- Added minimal publication metadata for bibliography, featured state, and thin payment preparation.
- Added admin meta boxes for bibliography, sale, and display fields.
- Added server-rendered publication blocks and shortcode fallbacks:
  - `iss/featured-publication`
  - `iss/publications-grid`
  - `iss/publication-order-panel`
  - `iss/publication-meta`

### Changed
- Moved archive and single publication rendering out of plugin PHP templates and into native `industriesalon` Gutenberg theme templates.
- Reduced plugin responsibility to publication data, editorial helper rendering, and dynamic blocks.

### Removed
- Removed plugin-owned archive/single publication templates and template routing.
