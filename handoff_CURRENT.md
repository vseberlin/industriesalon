# Current Handoff

Updated: 2026-06-30

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active
follow-up belongs in `TODO.md`.

## Current Work

- Native page JSON landing V1 is implemented for allowlisted `page` posts:
  front page, `about`, `verein`, `salon-vermietung`, and `sammlungen`.
- V1 keeps native pages, existing URLs, menus, and the front-page template
  wrapper. JSON rendering is gated by `_iss_editorial_enabled_landing`; disabled
  or empty JSON falls back to the existing template/post-content path.
- The front page is enabled locally with the `frontpage` landing skin and JSON
  sections reconstructed from the previous hardcoded/Gutenberg body. Transfer
  artifact: `ops/sql/2026-06-30-frontpage-landing-json.sql`.
- Landing gestures now include `statement`, `gateway`, `feature`, and
  `dynamic_slot`; `gateway` supports `cards`, `link-list`, and `feature-strip`
  treatments, while feature supports the front-page media-panel and
  media/text-microblocks treatments.
- Theme-owned landing rendering emits stable skin/gesture/treatment classes,
  local IBM Plex Serif is registered for landing serif headings, and JSON CTA
  links use the shared `.iss-button` primary tier so they inherit the active
  page color scheme.

## Preserve

- Do not introduce a `landing_page` CPT for V1. Keep native WordPress pages and
  page templates as the ownership boundary.
- Keep public presentation in the theme; plugins own editorial JSON storage,
  eligibility, sanitization, and editor UI.
- Keep section `treatment` stored in JSON. It remains editor-visible during
  internal review and can be capability-gated to admins before handover without
  changing storage.
- Leave unrelated local untracked files out of Git unless explicitly requested:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- If moving this checkpoint to staging, deploy the committed code/assets first,
  then apply/review `ops/sql/2026-06-30-frontpage-landing-json.sql` on a target
  with matching front-page content/media IDs.
- Browser-check `/`, `/about/`, `/verein/`, `/salon-vermietung/`, and
  `/sammlungen/` after deployment. Non-enabled pages should keep current
  Gutenberg/post-content output.
- Before client handover, capability-gate the per-section treatment selector to
  admins while preserving the existing JSON storage key.

## Verified Locally

- `php -l` for edited PHP files in `iss-content`, `iss-editorial`, and the
  theme landing renderer.
- `node --check plugins/iss-editorial/assets/admin.js`.
- `npx stylelint plugins/iss-editorial/assets/admin.css
  themes/industriesalon/assets/css/page-landing-editorial.css`.
- `theme.json` parsed with Node.
- `git diff --check`.
- Browser checks for `/` on desktop/mobile: landing skin renders, IBM Plex Serif
  loads for landing headings, dark-surface overlay/rental text is visible,
  first-section and rental CTAs inherit primary red, rental CTA remains inside
  the panel, and no horizontal overflow was detected.

## Commit State

- Local commit requested for this checkpoint. No push has been requested.
