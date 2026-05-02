# Handoff Current

## Status
- `committed`

## Date / Window
- Date: `2026-05-02`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD at session start: `b68638e`

## What Was Done This Session
- Replaced the old generic post single shell with the same layout logic used for `Veranstaltung`, but in the theme red scheme:
  - converted the `post` layout selector from `standard / image / short` to `standard / compact / long`
  - preserved old stored values safely:
    - `short` now normalizes to `compact`
    - `image` now normalizes to `standard`
  - rebuilt `themes/industriesalon/templates/single.html` to use one authoritative intro/body structure:
    - media + meta on the left
    - title + content on the right
    - `compact` and `long` handled by body-class variants in CSS
- Added the red post single layout system in `themes/industriesalon/assets/css/patterns.css`:
  - `standard`: split editorial layout
  - `compact`: narrow centered short-message layout
  - `long`: one-column longread layout
  - restored sane inline-image wrapping behavior inside post content
- Added the must-have intro gradient treatment to single `Führung` pages by moving top spacing ownership into `.iss-tour-hero` instead of `.iss-tour-page`
- Left `single-tour.html` and `single-tour-on-demand.html` structurally unchanged; booking and calendar behavior were not touched
- Earlier in the session:
  - redesigned `single-ausstellung.html` into an exhibition-specific layout with intro media, brown meta panel, and template-led story shell
  - committed that exhibition-specific redesign separately as `b68638e`

## Verification
- Active theme remained `industriesalon`
- `single.html` parses cleanly through WordPress `parse_blocks()`
- `/offnungszeiten/` now renders with `iss-post-layout-compact` while still storing legacy meta value `short`
- `/fuehrungen/elektropolis-tour/` still renders the expected `iss-tour-page` / `iss-tour-hero` structure after the gradient change

## Important Notes
- The post layout editor UI now exposes `Standard`, `Kurze Meldung`, and `Longread`.
- Existing posts using `_iss_post_layout=short` remain valid through normalization; no data migration was required.
- `single-ausstellung` was already committed earlier in this session chain as `b68638e`.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
