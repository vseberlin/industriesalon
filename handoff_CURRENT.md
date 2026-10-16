# Current handoff — 16 October 2026

## Delivery state

Website work belongs in `/home/vladimir/wp-website`. GitHub `main` is the code
exchange point; staging checkout is `/srv/industriesalon/stage/repo` on
`iss-prod-01`. Compare HEAD/origin rather than reusing an older release ref.
The user explicitly requested a full local-to-staging refresh: all site content,
users, settings, templates, custom tables, uploads and private intake now come
from local. This supersedes the earlier narrow project-draft transfer and its
target-specific IDs. No production or external archive storage was changed.

GitHub carries code and documentation only. 112 previously tracked SQL/content
JSON/media transfer artifacts were removed from the current tree and preserved
privately on both machines. Historical migration PHP stays tracked; its private
inputs must be restored before replay. Git history was not rewritten. Follow
`docs/infrastructure/{sync,data-artifacts}.md`.

## Futura help pilot

The help slice extends the shared editor: German guide at
`plugins/iss-editorial/README.md`, searchable help panel, focus/hover explanations,
clickable contextual popovers, six registry-owned section explanations and
status/capability-aware saving guidance. 52 DOM interaction checks and 14,127
registry/renderer checks pass; PHP lint/PHPCS/PHPStan and JS/CSS lint pass. Chrome
verified Burmeister access, search/Enter, Escape/focus, desktop, 390px mobile and
short-height layouts. Existing Futura draft was continued, never discarded or
published. The red-bordered Hilfe control stays prominent; the redundant toolbar
save shortcut is removed. Native WordPress saving remains available. The help
code needs no schema or media migration. The requested release also refreshes
staging from the latest local content; its DB snapshot travels privately, outside
GitHub. Deployment verification is pending. Next: human pilot using the guide
checklist after synchronization.

## Content and UAT account

The refresh aligned project IDs/status on both sites: KiezKarte **30811** and
Futura pilot **30615** are published, original Futura **25657** is preserved,
and composed Futura **30743** was copied as a draft. Subsequent local UAT saved
and published 30743; editing continues. The user-approved older admin autosave discard
is complete. Do not overwrite these later edits with the refresh snapshot.
Publish errors now reveal the recovery choice. Account switching releases
locks from signed-out editors, clears stale native lock dialogs and prevents
old tabs from renewing locks as another account. Logout now releases the exact
native lock owned by that session even if the account has other logins. Private
`_iss_editorial_lock_session` metadata records only the lock and a token hash;
another browser's renewed lock is preserved. Unsaved fields and private recovery
remain intact. Browser lock/release cycles, 50 lock checks, 45 UI checks,
511 storage checks and 74 project UAT checks pass. These follow-ups deploy code only,
without another database refresh or media artifact. All 84 Places and all canonical
JSON, facts, relations, templates, private Sets and intake state were included.
Do not replay earlier staging-only draft migrations over this full snapshot.

`Burmeister` is **328** on both sites, email `k.burmeister@industriesalon.de`,
with only `iss_project_uat`: create drafts, edit draft/published projects and use
media. No publish-new, delete, private-project or other admin rights. Editing a
published project updates its public content. Login opens Projects; only
Projekte is in the sidebar, with personal profile/logout in the account toolbar.
The same password now applies on both sites. Private credentials are in ignored
`var/project-uat-20261016/{credentials,staging-credentials}.json`. No invitation
or email was sent; send only when requested.

## Recovery and private delivery evidence

Verified pre-refresh staging backup:
`/srv/industriesalon/stage/backups/20261016-full-sync/` contains `database.sql.gz`,
`files.tar.gz`, `SHA256SUMS`, and `code-commit.txt`. Files include uploads,
private intake, installed core/configuration, environment and prior ops inputs.
Both archives were fully read/tested and checksums verified before replacement.
The earlier `20261016-073840/` backup remains available for the earlier release.

The full-sync directory also holds the imported `local-database.sql.gz`, private
`prior-tracked-data.tar.gz`, URL-rewrite and comparison receipts, and the retired
legacy Event Drop MU bridge. Local snapshots/receipts are in ignored
`var/full-sync-20261016/`. The transfer archive retains historical paths under
`ops/sql`, `ops/migrations`, and `ops/uploads`. Do not restore backups over later
UAT edits without review. Restoring a historical release requires matching code,
database and files, not just one SQL file.

## Verification and environment differences

At cutover, **117 of 118** source tables matched normalized row hashes; only
`wp_options` differed. Option differences after URL normalization were
`blog_public`, cron state, newsletter cron diagnostics and the staging audit
schema marker. The extra staging login-audit table was deliberately retained.
URL rewriting touched 11 epoch timestamps; guarded restoration preserved their
original editorial modification times. New sessions, edit locks, caches and
job receipts can diverge after application use.

Checksum dry runs reported zero file differences across **25,170 uploads**
(3,047,604,252 bytes), **27 private intake files** (201,488,963 bytes), and the
installed WordPress core. Both sites run WordPress **7.1.3**. Canonical media
validation passes for **180 documents / 179 unique media references**.
All 74 project/native JSON save/autosave/reload checks pass after the refresh;
fixtures were removed. Authenticated HTTP verifies both account logins, the
single Projects menu, two project editors and unrelated-screen 403s. Seven
public routes per site return 200. Staging containers are healthy and its
post-refresh log check has no PHP fatal/warning/parse errors.

The user retained public staging previews; keep staging `blog_public=0` and
noindex/nofollow with native admin login, without adding site-wide Basic Auth.
URLs, infrastructure credentials/configuration, security helpers and operational
logs remain environment-specific. Staging retains login-audit and REST batch
authentication MU plugins; local transcription/debug helpers remain local.
The obsolete staging accept-to-attachment MU bridge was retired according to
the current Sets-backed intake runbook. PHP/web server/DB container versions
remain environment-specific; staging's existing image is still tagged 7.0,
while its persistent installed WordPress core is verified at 7.1.3. No package
or container-image upgrade was performed. Mailpit exists but default WordPress
mail is not routed to it; inspect mail mode before notification/queue testing.

Human/browser UAT, Firefox/Word/Docs paste, macOS Safari and booking/order
acceptance remain open. Chrome automation was previously blocked by an extension
popup, so do not claim visual acceptance. Maintenance activation and upload-limit
work remain separate; never run all due mail tasks blindly.

Local `wp_app` mounts website plugins/theme/receiver from this checkout. Core,
database, uploads and intake remain under `/home/vladimir/wp`. Use Compose
project `wp`, its `docker-compose.yml`, and the website override
`var/refactoring-20261008/website-code.yml` for CLI. The mixed checkout and archive
source storage are not cleanup or sync targets.
