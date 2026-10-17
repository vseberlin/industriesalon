# Current handoff — 17 October 2026

## Released checkpoint

Gesture treatments, CSS consolidation, editor controls and landing-preview recovery
are committed and deployed on staging as **`a9dd4a6`**, including the earlier editor
commit `4e4dbc0`. Staging advanced cleanly from `4239654` through GitHub `main` at
19:39:48 UTC. This receipt records the final documentation checkpoint.
No SQL/content/uploads artifact, schema migration, service restart or configuration
change was needed. GitHub contains code, tests and documentation only.

Website delivery belongs in `/home/vladimir/wp-website`; local `wp_app` mounts its
plugins/theme/receiver. Core, DB, uploads and intake remain under `/home/vladimir/wp`.
Staging checkout is `/srv/industriesalon/stage/repo`, runtime is its parent's
`compose.yml` and `app`, via `vladimir@staging.industriesalon.info` (`iss-prod-01`).

The [delivery contract](docs/project/gesture-treatment-consolidation-plan.md)
records treatment resolution, distinct exhibition presentations, legacy adapters,
event skin precedence and the CSS drain. Edited public CSS is 42,748 source bytes
smaller. Plugin and theme changes must travel together. No stored content migration.

## Verification and preservation

- Local: 14,680 registry/renderer, 334 validation, 363 lossiness, 554 storage,
  42 native revision, 108 editor DOM checks and 3 CSS ownership gates pass.
  PHP syntax/PHPCS/PHPStan and JS/CSS lint pass. Browser coverage includes
  responsive treatments, chooser focus/preservation and compact editor controls.
- Local Führungen: an empty link in section 6 of its private draft blocked the
  preview. Recovery now shows the authenticated saved page without canvas editing
  until the entry is completed/removed; then the current preview resumes. Four
  saved and three private draft documents remained unchanged. Its original tab
  was reloaded and the preview became visible. Nothing was published.
- Staging: nine registries and all 180 canonical documents validate; all 180
  document renderers return, with no previously nonempty output becoming empty.
  Twelve public routes return 200/noindex. Restricted Projects login/editor works;
  four unrelated admin routes remain 403. Six editor script assets match exactly.
- Eight authenticated landing-preview requests (Führungen, home, Schöneweide,
  About; viewing and editing modes) return 200 with correct section markers and
  editing-asset isolation; sampled HTTP responses are 181–441 ms. Chrome confirms
  exhibition treatment/source-image rendering and phone layouts without overflow.
  Human interactive staging and cross-browser UAT remain separate acceptance work.
- Staging before/after/final hashes match all 15,504 posts and 239,199 non-lock
  postmeta rows, including revisions, private drafts and DB templates. Place epoch/
  state projections also match. Containers are healthy; no release PHP/nginx errors.
  No email, invitation, test content or historical revision purge was performed.

Private release evidence: `var/gesture-release-20261017/`; implementation evidence:
`var/gesture-consolidation-20261017/`; preview fix: `var/landing-preview-20261017/`.
These ignored directories are not GitHub transfer artifacts.

## Rollback and next action

Fresh verified staging backup: `backups/iss-backup-20261017T193729Z/` under the stage
root; seven hashed/read-tested artifacts, 119 tables, code baseline `4239654`.
The earlier audit rollback bundle is retained as the manual copy
`backups/editorial-audit-20261017T130015Z/`; the 16 October full-refresh backup
`backups/iss-backup-20261016T133026Z/` also remains. Never restore old content over
newer UAT edits. This release changed code only; rollback must preserve later work.

Reload existing editors for changed assets. Complete the human/cross-browser
checks in [TODO](TODO.md), especially contextual treatments and unfinished-draft
recovery. Production deployment is not part of this release.

## Preserve UAT and environment boundaries

- Futura **30743**, `/projekte/futura-biennale-2027-2/`, is the UAT project.
  Preserve Burmeister's private draft, original Futura **25657**, draft **30615**,
  and published KiezKarte **30811**; do not replay older staging-only migrations.
- `Burmeister` (**328**) retains Projects-only create/edit/media access; new projects
  need review. Credentials stay private in ignored `var/project-uat-20261016/`.
- Staging remains publicly previewable with `blog_public=0`/noindex by explicit
  user exception. Default WordPress mail is not routed to Mailpit; inspect mail
  mode before any notification test. Maintenance activation remains separate.
- The last full content/users/templates/uploads/intake sync was 16 October,
  13:33 UTC; preserve subsequent target UAT work. Its detailed receipt is `d9c4d79`
  and private `var/help-release-20261016/`. This release did not repeat that sync.
- The mixed/archive checkout and external archive storage are not cleanup targets.
