# Current handoff — 17 October 2026

## Local delivery state

Website work belongs in `/home/vladimir/wp-website`. `wp_app` mounts that
checkout's plugins/theme; runtime/core/DB/uploads remain under `/home/vladimir/wp`.
Release authorized: commit and sync the editor refinements, gesture-treatment/CSS
consolidation and landing-preview recovery through GitHub to staging. Pre-release
inspection found local HEAD `4e4dbc0`, GitHub/staging `4239654`, a clean staging
checkout and healthy services. Deployment verification is pending in this code
checkpoint; a follow-up receipt will record the final synchronized state.

The consolidated local work is complete: compact section selector and full-width
admin toolbar with system typography; registered quote/chapter/gallery treatments
with contextual choices and legacy adapters; distinct exhibition presentations;
portable semantic colours and explicit event skin precedence; retired quote CSS
and render branches; conditional timeline ownership and theme preset aliases.
The edited public CSS is 42,748 source bytes smaller. The
[delivery contract](docs/project/gesture-treatment-consolidation-plan.md)
records the exact scope, treatment matrix, drain decisions and compatibility.

## Verified and preserved

- Passed: 14,680 registry/renderer, 334 validation, 363 lossiness, 554 storage
  (including all 180 existing documents), 42 native revision, 108 editor DOM
  checks and 3 CSS ownership gates. PHP syntax/PHPCS/PHPStan and JS/CSS lint pass;
  PHPStan excludes test scripts, which execute through WP-CLI.
- Chrome: public project/exhibition/dark-event fixtures at 390/768/1280 px and
  767/769 px; no horizontal overflow. Existing Kinder im Werk quotations retain
  source images/captions, including the two-image station. Timeline computed
  styles match the pre-move check; unrelated pages omit its stylesheet.
- Actual editor treatment switch retains text, attribution and media, keeps
  focus and reaches a saved preview. At 900×560 and 1280×620 the header uses the
  full width, compact section navigation works and controls use system fonts.
  Browser viewport override reset; fixtures 40842/40844/40846, their revisions,
  private recovery and empty Set 739 removed.
- Full baseline comparison: all 180 raw documents, 15,502 post rows and 238,933
  metadata rows unchanged (native `_edit_lock` / `_edit_last` excluded), with
  no added/removed rows. Three inspected template authorities unchanged,
  including the custom `single-ausstellung` override. No live content migration.
- Evidence and pre-change copies: `var/gesture-consolidation-20261017/` (ignored).
  `before.patch` and `working-before/` preserve the initial dirty-work boundary.
  Roll back this change's code delta without reverting the earlier UI work.
- Landing preview recovery fix: an unfinished recovered draft now shows the
  authenticated saved page without canvas editing when no previous valid frame
  exists. Führungen's empty link in section 6 remains in its private draft;
  completing/removing it restores the current preview. All 108 editor DOM checks
  and scoped JS lint pass. Chrome verified Führungen's fallback (about 4 seconds),
  Home/Schöneweide/About and an event editor, including phone preview. The original
  Führungen tab now displays the fallback. Four saved and three private draft
  documents are unchanged; evidence: `var/landing-preview-20261017/` (ignored).

Code-only delivery: existing schema, content and uploads need no paired artifact.
Move the plugin/theme changes together for this release. Unknown treatments
stay recoverable and use a rendering fallback; validation blocks canonical writes
until the choice is resolved. Existing scoped timeline promo compatibility is
retained for 16 stored revisions. Other audit candidates are not implicitly dead.

## Next action and release boundary

Complete the authorized code-only staging deployment and record its verification.
All 180 canonical staging documents passed preflight validation. No SQL/content/
uploads transfer is needed; retain target UAT/private drafts. Human cross-browser
acceptance remains separate. Existing editors should reload for changed assets.

Local WP-CLI uses Compose project `wp`, `/home/vladimir/wp/docker-compose.yml`
and `var/refactoring-20261008/website-code.yml`, mounting `tests/e2e/bin` read-only
at `/tests`. Run `npm run lint:css` for style lint plus the new ownership gate,
and `node --test tests/e2e/bin/editorial-ui.cjs` for editor interactions.
Use disposable fixtures for runtime checks. No notification/mail was sent.

## Preserve UAT and deployment state

- Last recorded staging code release: audit `9985528`; earlier editor help
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
  `vladimir@staging.industriesalon.info`. The last verified rollback bundle is
  `backups/iss-backup-20261017T130015Z/` (pre-release code `d9c4d79`).
  Earlier full-refresh backup
  `backups/iss-backup-20261016T133026Z/` (code `69396de`) and private receipts
  `backups/help-release-20261016/` remain historical recovery evidence.
  Never restore an old snapshot over later UAT work; match code/data/files.
- Local `wp_app` mounts website plugins/theme/receiver; core, DB, uploads and
  intake remain under `/home/vladimir/wp`. Follow the [sync](docs/infrastructure/sync.md)
  and [artifact](docs/infrastructure/data-artifacts.md) rules for later deployment.
  The mixed/archive checkout and external archive storage are not cleanup targets.
