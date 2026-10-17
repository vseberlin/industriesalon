# Current handoff — 17 October 2026

## Delivery state

Work in `/home/vladimir/wp-website`; runtime/core/DB/uploads remain under
`/home/vladimir/wp`. `wp_app` mounts the website plugins/theme/receiver.
**Audit code release `9985528` is committed, pushed to GitHub and deployed to
staging** (17 October, 13:04 UTC). The following documentation receipt records
verification; local and staging synchronize it through GitHub `main` as well.
The release was code-only: no DB migration, content import or uploads artifact.
Staging's newer content and private recovery were preserved.

H1–H3, M1–M8 and L1–L10 are resolved; L3 intentionally retains the existing
canvas enablement boundary. Remaining consumer controls use owner declarations;
pickers are extracted, album imports page completely, preview caches are derived
transport data, schema versions are explicit, and purge/native-save boundaries
are enforced server-side. Legacy import commands now live with `iss-content`.
Plugin version is 0.2.0 (PHP 8.1 / WordPress 6.4 minimums).
Details: [audit](docs/project/json-editor-audit-2026-10-16.md) and
[platform contract](docs/architecture/editorial-platform.md).

## Verified and preserved

- Final checks: 99 editor DOM, 43 completion, 545 storage, 363 lossiness,
  334 validation, 237 retention, 42 revision, 319 version-token, 243 Place
  atomicity, 101 Place native-save, 74 Projects-only access and 14,126 renderer
  checks passed; M6 contracts, degraded mode and format cache passed too.
  PHP syntax/PHPCS/PHPStan and scoped JS/CSS lint passed.
- Signed-in Chrome passed Project restore/edit/recovery, media/Set pickers,
  native Save Draft/reload and phone previews; tour route panel/map shortcut
  and native save retained the relations owner's data. Disposable posts
  39282/39284, history and Set 722 were removed.
- All 180 canonical raw JSON values and all 180 public render hashes match
  baseline. Thirty-two normalized documents lose only derived preview cache
  fields. `validate-all`: 180 valid; registry: nine formats, zero errors.
- Final hashes match every baseline post and non-lock metadata row: 15,502
  posts and 239,205 metadata rows. All 193 epochs and 269 states are unchanged.
  Schöneweide 13251, Führungen 13301 and private Familienrallye autosave 26723
  remain intact. No existing content/history was migrated or bulk-pruned.
- Verified backup, final receipt, slice diff and comparison/test evidence:
  `var/editorial-audit-completion-20261017/` (ignored). Earlier slice evidence
  remains available via the audit. The final completion preserved twenty
  unrelated prior paths, including the already-deleted legacy asset; all reviewed
  audit files are now included in the release.

## Staging release verified

- Clean staging checkout fast-forwarded from `d9c4d79` to `9985528`; bind-mounted
  source loaded without restart. Active `iss-editorial` reports 0.2.0.
- Fresh verified rollback bundle:
  `/srv/industriesalon/stage/backups/iss-backup-20261017T130015Z/`:
  119-table DB, uploads, intake, code and runtime/config archives; hashes and
  archive readability verified. Code baseline is `d9c4d79`.
- Target checks: 180 valid documents, nine registries without errors, all 180
  public document render hashes identical before/after. Eleven HTTP routes
  passed with noindex; restricted Projects login/editor, unrelated-screen 403s
  and five exact served editor assets passed. Services healthy; no new PHP or
  nginx errors in release checks.
- All 15,504 staging posts and 239,197 non-lock metadata rows retain full and
  per-record hashes, including private drafts. All 193 epochs and 269 states
  retain their hashes. No revision purge or content/data sync was run.
- Read-only target probes: Futura registry builds 4 → 1; twenty ticket lookups
  sanitize 20 → 1 with identical output; larger reference reads 30 → 4 and
  50 → 2 queries. These are function probes, not Query Monitor/browser timings.
- Local receipt and verification: `var/editorial-release-20261017/` (ignored).
  Baseline/after public HTTP timings are recorded there; three-request samples
  are diagnostic, not a latency guarantee.

## Remaining acceptance

No audit implementation or requested release work remains. Human/cross-browser
UAT and interactive Query Monitor/editor-cadence acceptance remain separate.
**Reload already-open editors once** after the token changes; private recovery
remains readable. Later saves can prune normal revisions above the cap; preserve
the current backup because code rollback cannot recover pruned history. See the
[retention runbook](docs/runbooks/deployment.md#editorial-revision-retention).
Queue/cron/graph work and the closed Führungen content task remain outside scope.

Local CLI uses Compose project `wp`, both `/home/vladimir/wp/docker-compose.yml`
and `var/refactoring-20261008/website-code.yml`, with a read-only mount of
`tests/e2e/bin` at `/tests`. Use disposable records for further regressions.

## Preserve UAT and deployment state

- Current verified staging code release: audit `9985528`; earlier editor help
  `71318ad` remains included. German guide, contextual popovers and prominent
  Hilfe are deployed; WordPress owns saving.
- Futura **30743**, `/projekte/futura-biennale-2027-2/`, is the UAT project.
  Preserve Burmeister's private draft, original Futura **25657**, draft **30615**
  and published KiezKarte **30811**. Account-switch/logout lock fixes are already
  deployed; do not replay older staging-only migrations.
- `Burmeister` (**328**, `k.burmeister@industriesalon.de`) has Projects-only
  create/edit/media access; new projects need review. No invitation/email was
  sent. Credentials are private in ignored
  `var/project-uat-20261016/{credentials,staging-credentials}.json`.
- Staging stays publicly previewable with `blog_public=0`/noindex by explicit
  user exception. Default WordPress mail is not routed to Mailpit; inspect mail
  mode before any notification test. Human UAT and cross-browser acceptance remain open.
- Local is authoritative for content/users/templates/uploads/intake. Last full
  sync: 16 October, 13:33 UTC; 117/118 normalized tables matched (runtime/environment
  option exceptions), all 25,170 uploads and 27 intake files matched. Subsequent
  use can diverge; preserve newer UAT work. Detailed receipt: `d9c4d79` and private
  `var/help-release-20261016/`. GitHub carries code/docs only; SQL/media stay private.
- Staging checkout: `/srv/industriesalon/stage/repo` on `iss-prod-01`, via
  `vladimir@staging.industriesalon.info`. The current rollback bundle is above.
  Earlier full-refresh backup
  `backups/iss-backup-20261016T133026Z/` (code `69396de`) and private receipts
  `backups/help-release-20261016/` remain historical recovery evidence.
  Never restore an old snapshot over later UAT work; match code/data/files.
- Local `wp_app` mounts website plugins/theme/receiver; core, DB, uploads and
  intake remain under `/home/vladimir/wp`. Follow the [sync](docs/infrastructure/sync.md)
  and [artifact](docs/infrastructure/data-artifacts.md) rules for later deployment.
  The mixed/archive checkout and external archive storage are not cleanup targets.
