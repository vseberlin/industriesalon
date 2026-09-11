# Current handoff — 2026-09-11

Website checkout: `/home/vladimir/wp-website`, branch `main`; read `AGENTS.md`
first. Archive work is preserved separately in `/home/vladimir/wp` on
`archive/local-work-20260911` and must stay out of website delivery.

## Current task and accepted local state

The shared JSON editor and optional Rückblick/material workflow are implemented
locally. The installed Chrome extension connects; yesterday's native-host blocker
is resolved. No further extension installation is needed.

Eligible landing pages, exhibitions, projects, tours, publications, retrospectives,
Places and events share the bounded JSON canvas and explicit autosave recovery.
Existing disabled/non-JSON content retains its authority. Dates, booking and other
owner panels still use their own native save contracts.

Events, exhibitions, projects and tours expose **Material & Rückblicke**:
create a linked Rückblick draft, share existing source Sets, and open/close a
per-content guest upload link. Guest files remain pending for rights review.
**Zum Entwurf hinzufügen** prepares reviewed photos/documents in an explicit
destination's own autosave; restore, preview and save normally. Approval is checked
again before save/preview. Use history retains multiple destinations. Graph edges
own report/source links; native publication determines public visibility. The
Rückblick template reuses the ordered renderer, Chronik skin and shared cards.

The Repair-Café critical error is resolved: relationship cards appended through
`the_content` recursively generated excerpts until PHP exhausted memory. The
theme now composes those cards on the queried post's native `core/post-content`
block. User-created published report **27388** links to event **26813**; preserve
both. Their public pages return HTTP 200 and their reciprocal links work in Chrome.

Implementation, editor instructions and repeatable commands:
[Editorial platform](docs/architecture/editorial-platform.md).
Guest receiver dependency/delivery notes:
[Event Drop](docs/runbooks/event-drop-staging.md#current-editorial-integration-2026-09-11).

## Verification and remaining review

- Storage/HTTP suite: **171 checks**, including **85 existing documents**;
  pre-existing post content/meta unchanged. Sets suite: **169 assertions** plus
  cleanup checks, including all five content types, rights withdrawal, multiple
  report sources, publication/withdrawal, pagination and protected Set deletion.
  Regression coverage renders singular content blocks with empty report excerpts
  for all four source types and checks that excerpts exclude relationship cards.
- **11 shared-editor DOM tests + 2 Set/upload interaction tests** passed.
  Targeted ESLint, PHPCS, PHPStan, PHP syntax and whitespace checks passed.
- Chrome: earlier TinyMCE, nested pickers, recovery, reorder and native saves;
  new rights form, explicit destination, gallery/PDF recovery, native Save Draft,
  linked Rückblick creation, source search, optional date and two confirmed uses.
  Actual HTTP JPEG/PDF submissions reached the selected private Set; altered
  event text did not change the destination; closed GET/POST returned 403.
- Rückblick preview and guest upload were checked at **390px**, without horizontal
  overflow. A guest-form grid issue was fixed. Earlier event nested-picker mobile,
  mouse dragging and Firefox checks remain unverified; do not claim a complete
  cross-browser matrix. Temporary viewport overrides were reset.
- Temporary browser posts/account, one imported fixture image, raw fixture files,
  manifest rows and orphan fixture graph records were removed. Ten old empty
  integration Sets were removed after SQL backup and exact-ID/dead-link checks.
  At that initial feature cleanup checkpoint, **11 real Sets / 37 original items**
  remained and existing published post content/meta checksums were unchanged.
  The user subsequently created report 27388. The critical-error regression suite
  removed only its own fixtures and left existing Sets, items and links unchanged.

Next: user review of the local workflow, then coordinated delivery if requested.
Local site `http://localhost:8082`; canonical `http://192.168.2.31:8082`.
The temporary browser login was removed; the user should use their normal account.

## Preserve and delivery state

- Website development continues in `/home/vladimir/wp-website` on **main**,
  tracking `origin/main` `0857845`. This checkpoint commits the shared editor,
  recovery, Rückblick and reviewed-upload changes on top of the two website
  commits `5c6433a` (from `cf862c9`) and `7d97628` (from `aa1a23a`). The 20
  unpublished archive commits are excluded from this history. Nothing is pushed.
- The original checkout `/home/vladimir/wp` is preserved on
  **archive/local-work-20260911**, HEAD `8d6889e`, without an upstream. Its full
  mixed history and archive working files remain intact. Do not merge or push
  this branch into website main. Archive preservation tools/tests/docs,
  archive-only AGENTS changes, `AGENTS-wp.md`, `archive-backlog.md` and the old
  `TODO.md` deletion were not copied into the website checkout.
- This checkpoint records all **39 website/editorial files**, including the
  small shared object-picker modal-focus integration in `plugins/iss-archive`.
  Original uncommitted copies remain in the preserved checkout. Continue
  website edits in the website checkout to avoid diverging copies.
- Docker and the local site still serve `/home/vladimir/wp`; this Git separation
  did not change services, mounts, the database or uploads. The copied runtime
  code/templates match that checkout. Future edits in `wp-website` are not
  automatically served by the existing containers; arrange an explicit runtime
  switch or controlled code sync before browser verification of further edits.
- Separation verification: all 13 editor/Set DOM interaction tests passed in the
  website checkout; source/template/artifact byte comparisons and Git whitespace
  checks passed. Earlier storage, rights and browser checks above were performed
  against the original running checkout. No fresh PHP/DB suite was needed for
  the unchanged runtime code.
- The replayed Place commits retain all three `ops/migrations/2026-07-19-*.php`
  artifacts and the paired `2026-07-19-kino-spreehoefe-historical-media` upload
  archive/manifest/checksum. The archive hash and five manifest members were
  verified. No migrations were run. This editor/Rückblick code commit requires
  no new DB or uploads artifact: reports, settings and media use normal editor
  actions; existing local editorial content is not exported by this commit.
  Deliver Event Drop, `iss-content`, `iss-editorial` and the theme together.
- A verified pre-separation patch, untracked-file backup and scope manifest are
  machine-local at `/home/vladimir/wp/.git/branch-separation-20260911/`.
  The editor changes are committed locally; nothing was pushed or deployed.
