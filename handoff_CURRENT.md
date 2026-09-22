# Current handoff — 2026-09-22

Local website checkout, GitHub `main` and staging are synchronized. Implementation
and data migrations are committed through **0bba0dd**; the subsequent closeout
commit only records this state. Verify current `HEAD`/`origin/main` before work.
Website source: `/home/vladimir/wp-website`. `/home/vladimir/wp` is the preserved
archive checkout, not the website delivery branch. Production was not changed.

## Accepted state

One JSON workspace and registry serve nine formats. Theme CSS separates renderer
anatomy from skins; the alternate cards/modal editor and duplicate homepage hero
CSS are removed. The JSON homepage uses the original shared theme hero pattern.
Staging's CARTO key support is now shared code; its actual key stays outside Git.
See the [audit](docs/project/editorial-consolidation-audit.md) and
[editorial contract](docs/architecture/editorial-platform.md).

The explicitly authorized local -> staging content sync is complete:

- **52 editor documents**, **60 content/template records**, **13 attachment rows**;
  **33 media files** transferred, all **1,408 available referenced files** match
  by SHA256. The manifest records the direct uploads transfer; the large PDF
  was not put into Git.
- Three existing local template overrides now match staging: `single-ausstellung`
  **26309**, `page-publikationen` **26560**, `page-projekte` **26532**. `front-page`
  and `single-video` remain theme-owned. Keep inspecting effective authority.
- Report **27388** includes its required source event **26813**, connected through
  the owning report API. Its venue relation uses the relation API. Media rights,
  attribution and consent for transferred Event Drop attachments are retained.
  Native APIs created required empty editorial context Sets and derived graph/
  Place projections; unrelated Set contents were not imported.
- Local posts and non-lock metadata did not change. Existing staging posts/meta
  outside the named migration scope and all existing private autosaves were
  preserved. Database INSERT-data comparison confirms archive Sets, programme/
  occurrences, SuperSaaS slots, booking requests, newsletters, users and usermeta
  are unchanged. Staging config file hash and noindex setting are unchanged.
- Local home **12257** retains published **10+4** and autosave **27678** **10+4**.
  Private drafts remain environment-specific. Legacy fixture **27415** was not
  transferred. Neither old cleanup SQL nor archive retirement scripts were run.

## Artifacts and recovery

Applied in order, after code and media:

1. `ops/migrations/2026-09-22-editorial-staging-sync.php`
2. `ops/migrations/2026-09-22-editorial-sync-dependencies.php`

Use `wp eval-file FILE verify --use-include` for read-only verification. These
are guarded one-time migrations; do not rerun `apply`. Media manifest:
`ops/uploads/2026-09-22-editorial-sync.manifest`. Staging permalink rules were
flushed after the new Rückblick type was deployed.

Verified full staging DB/uploads backup:
`/srv/industriesalon/stage/backups/20260922-132743/`.
Configuration backup, original dirty patch, final DB dump, protected-table
verification and targeted migration before-images:
`/home/vladimir/server-actions/sync-20260922/` (before-images under `cli/`).
The original staging edits also remain in its named pre-sync Git stash. Its
inactive repo Compose port patch was preserved there, not reapplied; staging
uses the separate `stage/compose.yml`. No containers were restarted.
Local backup and comparison evidence:
`/home/vladimir/.local/state/iss-sync-20260922/`.

## Verification and next action

- Local: **34 DOM tests**, **287 storage checks**, **137 registry/renderer checks**,
  **169 Set assertions**; targeted lint/static checks. `videos.php` has 24 existing
  PHPCS findings, unchanged from the earlier baseline.
- Staging: both migration postflights pass; **86 stored documents** validate;
  nine-format registry has no errors. Seventeen route/API/login checks return
  200 without local URLs or public editing markers. Chrome desktop/phone hero
  checks pass with no overflow; Atlas loads 18 keyed tiles without console errors.
  Containers remain healthy. Existing backups/retired image variants missing on
  both hosts were not invented or deleted; current referenced originals exist.
- Next: staff acceptance of the staging editor, Firefox and real clipboard/paste
  checks. Staging's authenticated editor was not exercised in this deployment
  turn; earlier Chrome editor/save/recovery checks ran locally. Production
  release and further content migrations remain separate tasks.

Local runtime still mounts website code via
`/home/vladimir/.local/state/iss-editorial-pilot-20260921/website-code.yml`.
**Do not restart base Compose:** it would restore the older source mounts.
Local WP-CLI wrapper: `/home/vladimir/.local/state/iss-editorial-pilot-20260921/wp`.
