# Handoff Current (Theme)

## Status
- `ready_for_next_session`

## Date / Window
- Date: 2026-04-25
- Timezone: Europe/Berlin

## Branch / Commit
- Branch: `master`
- HEAD at handoff write: `8ba4ced`

## What Was Done This Session
- Added post layout variants for normal posts without disabling Gutenberg:
  - post meta `_iss_post_layout` (`standard`, `image`, `short`)
  - editor sidebar selector in post document settings
  - frontend body class `iss-post-layout-*`
- Updated single-post CSS in `assets/css/patterns.css`:
  - `standard`: hero image remains in container
  - `image`: full-width hero with viewport height cap (prevents >100vh)
  - `short`: compact mode, no large hero image
- Extended single-post content polish (typography, media, captions, quotes, tables, responsive alignment behavior).
- Removed now-redundant single-post image/align overrides from `assets/css/overrides.css`.
- Purged DB template overrides for active theme (`front-page`, `hero-page`, `single`) so disk template files are authoritative.

## Runtime Verification Snapshot
- Active theme confirmed: `industriesalon` `1.1.0`.
- Active theme path confirmed: `/var/www/html/wp-content/themes/industriesalon`.
- Syntax check passed for `themes/industriesalon/functions.php`.
- WP bootstrap check passed via `wp eval`.

## Open Item
- Validate visual output for all three post layout variants on real posts (desktop + mobile).

## Suggested Next Step
1. In post editor, set each layout (`standard`, `image`, `short`) on sample posts.
2. Verify hero height cap behavior for `image` variant.
3. Commit/push if visual QA passes.

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/themes/industriesalon/handoff_CURRENT.md`.
