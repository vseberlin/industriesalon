# Current handoff — 17 October 2026

## Delivery state

Work in `/home/vladimir/wp-website`; runtime/core/DB/uploads remain under
`/home/vladimir/wp`. `wp_app` mounts the website plugins/theme/receiver.
The user authorized committing the completed audit and synchronizing GitHub and
staging. This checkpoint contains the completed code; deployment verification
is in progress and will be recorded in the release receipt. Baseline `a8d6a1f`
matched GitHub; staging was clean at `d9c4d79`. No DB migration or uploads artifact
is needed. Staging content and private recovery are preserved during code sync.

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
  remains available via the audit. Twenty unrelated prior dirty paths retain
  their exact hashes, including the previously deleted legacy asset.

## Next action / release gates

No audit implementation slice remains. Human/cross-browser UAT and staging
profiling are separate acceptance gates. The requested release is in progress: inspect target drift, preserve newer
UAT/private recovery, validate the registry and raw documents on the target,
and record staging verification. Human Query Monitor measurements remain
separate from read-only deployment probes.
Before releasing revision retention, verify a current DB backup: later saves
prune old normal revisions and code rollback cannot recover them. See the
[retention runbook](docs/runbooks/deployment.md#editorial-revision-retention).
Already-open editors must reload once after the token changes; private recovery
remains readable. Queue/cron/graph work and the closed Führungen content task
are outside this completion.

Local CLI uses Compose project `wp`, both `/home/vladimir/wp/docker-compose.yml`
and `var/refactoring-20261008/website-code.yml`, with a read-only mount of
`tests/e2e/bin` at `/tests`. Use disposable records for further regressions.

## Preserve UAT and deployment state

- Last verified staging code release: editor help `71318ad`, receipt `d9c4d79`.
  German guide, contextual popovers and prominent Hilfe are deployed; the
  redundant toolbar save button is gone and WordPress owns saving. Staging
  runtime was not rechecked at closeout.
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
  `vladimir@staging.industriesalon.info`. Verified rollback bundle:
  `/srv/industriesalon/stage/backups/iss-backup-20261016T133026Z/` (code baseline
  `69396de`); final pre-import DB/receipts: `backups/help-release-20261016/`.
  Never restore an old snapshot over later UAT work; match code/data/files.
- Local `wp_app` mounts website plugins/theme/receiver; core, DB, uploads and
  intake remain under `/home/vladimir/wp`. Follow the [sync](docs/infrastructure/sync.md)
  and [artifact](docs/infrastructure/data-artifacts.md) rules for later deployment.
  The mixed/archive checkout and external archive storage are not cleanup targets.
