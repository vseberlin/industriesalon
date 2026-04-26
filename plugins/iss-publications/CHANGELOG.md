# Changelog

All notable changes for `iss-publications` are documented here.

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
