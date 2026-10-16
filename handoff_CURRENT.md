# Current handoff — 16 October 2026

## Delivery state

Website delivery belongs in `/home/vladimir/wp-website`; GitHub `main` is the
exchange point. Staging checkout is `/srv/industriesalon/stage/repo` on
`iss-prod-01`, reached as `vladimir@staging.industriesalon.info`. The editor help
release `71318ad` is deployed, followed by this documentation receipt. Compare
live HEAD/origin before further work. Both sites are running.

The user-authorized full local-to-staging refresh was repeated with the latest
local snapshot at 13:33 UTC, preserving subsequent Futura edits and private
drafts. Local remains authoritative for content, users, settings, templates,
custom tables, uploads and private intake. Production and external archive
storage were untouched. GitHub carries code/documentation only; SQL/content and
media transfer artifacts remain private. Follow `docs/infrastructure/sync.md`
and `docs/infrastructure/data-artifacts.md`.

## Editor help and UAT

The shared editor now has the German guide at `plugins/iss-editorial/README.md`,
searchable help, hover/focus explanations, clickable contextual popovers, six
registry-owned Futura section explanations and status-aware saving guidance.
Hilfe has a prominent red border. The redundant toolbar save shortcut is gone;
normal WordPress saving remains accessible by minimizing the workspace.

Local validation passed 52 DOM interaction tests, 14,127 registry/renderer
checks, PHP lint/PHPCS/PHPStan and JS/CSS lint. Chrome checked desktop, 390px and
short-height layouts locally. Staging Chrome verified Burmeister login, help on
desktop/390px, no horizontal overflow, the removed shortcut and native Update
access. The preserved private Futura draft was continued; nothing was discarded
or published during deployment verification. One browser-extension autofill error
interrupted the first login click; retry succeeded, with no website JS errors.

Composed Futura **30743** is published at `/projekte/futura-biennale-2027-2/`;
KiezKarte **30811** is published, Futura **30615** is a draft and original
Futura **25657** is preserved. Earlier admin-autosave discard and account-switch/
logout lock fixes are included. Do not replay older staging-only project migrations.

`Burmeister` is user **328**, email `k.burmeister@industriesalon.de`, with only
`iss_project_uat`: create drafts, edit draft/published projects and use media.
New projects require review; no publish-new/delete/private-project/other-admin
rights. Updating a published project changes its public content. Login opens
Projects and only Projects appears in the sidebar. The same password applies
on both sites; credentials remain in ignored
`var/project-uat-20261016/{credentials,staging-credentials}.json`.
No invitation or email was sent. Next: human UAT using the guide checklist.

## Verified parity and private rollback

Before traffic resumed, **117 of 118** source tables matched normalized row
hashes and counts. Only `wp_options` differed: after URL normalization the
exceptions were `blog_public`, cron state/lock and staging's login-audit schema
marker. The extra staging `wp_login_audit` table was retained. Eleven epoch
modification timestamps touched by URL rewriting were restored with content
guards. Sessions, edit locks, private-draft save timestamps, caches and job
receipts can diverge after use.

Checksum comparison found zero upload/intake differences: **25,170 uploads**
(3,047,604,252 bytes) and **27 private intake files** (201,488,963 bytes).
Installed WordPress core files match at **7.1.3**; an empty local `ops/uploads/`
directory is not runtime content. Both canonical media checks pass for
**180 documents / 179 references**. Authenticated HTTP checked the Projects
menu/editor, create-project link and unrelated-screen 403s. Seven public routes
per site return 200; staging remains public with noindex. Containers are healthy
and post-deployment logs show no PHP fatal/warning/parse errors.

Fresh verified rollback backup:
`/srv/industriesalon/stage/backups/iss-backup-20261016T133026Z/` contains DB,
uploads, intake, code, installed runtime and configuration archives, with a
verified checksum manifest. Its code baseline is `69396de`. The exact final
pre-import DB is also saved in `backups/help-release-20261016/` alongside the
imported snapshot and verification receipts. Local private evidence is in
`var/help-release-20261016/`. The earlier `20261016-full-sync/` backup and
private historical transfer artifacts remain available. Never restore an old
snapshot over later UAT work without review; restore matching code/data/files.

## Environment boundaries and remaining acceptance

Keep staging URLs, infrastructure secrets, login-audit/REST batch helpers and
`blog_public=0` with noindex/nofollow. The user explicitly retained public
previews; do not add site-wide Basic Auth for this release. Local debug helpers
remain local. The obsolete staging Event Drop MU bridge remains retired.
PHP/web/DB container versions differ by environment; staging's persistent core
is 7.1.3 despite its image tag 7.0. No package or image upgrade was performed.
Mailpit is present, but default WordPress mail is not routed to it; inspect mail
mode before notification testing. Never run all due mail tasks blindly.

Human acceptance, Firefox/Word/Docs paste, macOS Safari and booking/order tests
remain open. Maintenance activation and upload limits are separate tasks.
Local `wp_app` mounts this checkout's plugins/theme/receiver. Core, DB, uploads
and intake remain under `/home/vladimir/wp`. Use Compose project `wp`, that
checkout's `docker-compose.yml`, and
`var/refactoring-20261008/website-code.yml` for CLI. The mixed/archive checkout
and archive source storage are not cleanup or sync targets.
