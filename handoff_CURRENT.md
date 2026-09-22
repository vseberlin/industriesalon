# Current handoff — 2026-09-22

Website source: `/home/vladimir/wp-website`, branch `main`. The accepted editor
consolidation, hero restoration and staging CARTO repair are prepared for the
GitHub -> staging sync authorized on 2026-09-22. Deployment is in progress;
verify the final Git refs and migration result before treating it as complete.
`/home/vladimir/wp` remains the preserved archive checkout; do not merge its
history into website delivery.

## Implementation and next action

One JSON workspace serves nine registered formats: landing, article, project,
tour, exhibition, report, event, place and publication. Article reuses the
landing renderer for ordinary pages/posts/videos. Registry metadata owns
versions, sections, treatments, aliases, skins, features and explicit starters.
The old cards/modal authoring path is removed. Native relationship, route,
transcript and publishing controls retain their owners. Existing block content
is not automatically migrated or enabled.

Theme CSS now separates `editorial-landing.css` anatomy/treatments from
`editorial-skins.css`. The old combined stylesheet is removed; legacy
`front-page.css` loads only for the fallback composition. URL anchors no longer
choose presentation. Admin control appearance and workspace layout remain in
their existing separate layers. No new `!important` or override layer.
Homepage hero regression repaired: JSON reuses the original `iss-front-hero`
pattern, matching staging geometry/type/gradient at desktop and phone widths.
Duplicate opening CSS is removed; optional prose remains below the image.
The theme-owned `front-page` fallback shares the same pattern; no DB migration.

Read the [audit and latest-audit comparison](docs/project/editorial-consolidation-audit.md)
and [editorial contract](docs/architecture/editorial-platform.md).
Deploy the matching owning plugins and theme together. The code itself needs
no DB rewrite; the explicitly requested content sync uses
`ops/migrations/2026-09-22-editorial-staging-sync.php` and
`ops/uploads/2026-09-22-editorial-sync.manifest`. Use `--use-include` with
`wp eval-file`; default is guarded preflight, `apply` writes, `verify` checks.
Scope: 52 documents, 59 content/template records and 13 new attachment rows;
33 media files are checksum verified. Preserve private autosaves, unrelated
Sets/programme data, accounts and environment configuration. Staging backup:
`/srv/industriesalon/stage/backups/20260922-132743/` (DB and uploads). Config and
original dirty patch: `/home/vladimir/server-actions/sync-20260922/`.
The staged CARTO key stays in `app/wp-config.php`, outside Git. The old dirty
repo Compose change is inactive (staging uses `stage/compose.yml`); preserve its
patch/stash while making the checkout match GitHub.
Next: complete deploy/migration verification, then staff acceptance, Firefox
and real clipboard/paste checks.

## Authority and preservation

- Homepage **12257**, slug `home-2`, has a published v3 document with **10 sections
  and four deleted sections**. Administrator autosave **27678** has its own valid
  **10+4** composition. Both are retained. The old handoff's 27454 / 12+2 state
  was stale. Home content/JSON is unchanged; preview refreshed only its autosave
  timestamps/base-token bookkeeping and normal edit locks.
- **86 existing documents** validate. About, Schöneweide and Führungen stay v1;
  schema upgrades are explicit. Preserve report **27388**, event **26813**, Set
  data, Event Drop and existing Place migration/upload artifacts.
- Effective DB overrides remain: `single-ausstellung` **26309**,
  `page-publikationen` **26560**, `page-projekte` **26532**. `single-video` is
  theme-owned locally; check target template authority before deployment.
- Verified pre-change backup and full-row baseline are in
  `/home/vladimir/.local/state/iss-editorial-consolidation-20260922/`:
  `before.sql.gz`, `baseline.json`, `preservation-result.json`. Read-only
  `verify-preservation.py` checks 15,390 posts and 238,520 non-lock metadata rows;
  expected home-preview bookkeeping is explicitly classified. The latest broad
  comparison is **not clean**: autosaves 26790/27228 changed, 27941 was added,
  metadata was added to autosaves 26723/26790/27941, and route draft metadata
  was added to 12191. Published content/JSON and home draft content are unchanged;
  no rows were removed. Preserve these private drafts; review their provenance
  before claiming full-row equality. Do not replay fixture or old draft scripts.

## Verified and runtime

- **34 DOM tests**, **287 storage checks**, **137 registry/renderer checks**,
  **169 Set assertions** pass; database fixtures are removed in `finally`.
  `wp iss-editorial registry-check --format=json` validates all nine formats.
- Targeted JS/CSS lint, PHP syntax and PHPStan pass. PHPCS passes 18 changed
  PHP files; `videos.php` retains **24 pre-existing escaping findings**, verified
  against HEAD, with no new findings. See audit for exact validation scope.
- Chrome: home workspace; disposable project/tour/publication/page/video
  workspaces; project edit → autosave → reload/recover → native Save Draft;
  native relationship navigation; project/publication phone and project 800px
  layouts. Fixtures removed, viewport reset. Public landing checks at phone and
  desktop widths: no overflow, one H1, no editing markers; media/text ratio kept.
  Eleven existing public routes returned 200. Firefox/paste/staff UAT remain.
- `wp_app` mounts website code through
  `/home/vladimir/.local/state/iss-editorial-pilot-20260921/website-code.yml`.
  **Do not restart base Compose:** it would restore older source mounts.
  Local WP-CLI wrapper: `/home/vladimir/.local/state/iss-editorial-pilot-20260921/wp`.
  PHP test scripts are mounted under `/tmp/iss-tests/`; run database suites
  sequentially. Repeatable commands are in the audit.
