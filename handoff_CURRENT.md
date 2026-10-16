# Current handoff — 16 October 2026

## Delivered to GitHub and staging

Website work belongs in `/home/vladimir/wp-website`. The queued website code,
project UAT role (`04ab699`) and reviewed legacy migration baselines (`5ed4c90`)
are deployed through GitHub `main` to `iss-prod-01`,
`/srv/industriesalon/stage/repo`. The final documentation commit is the delivery
checkpoint; compare local/remote HEAD rather than copying an older ref here.
No production deployment occurred.

The user explicitly retained staging's existing public preview access for this
UAT release. `blog_public=0` and `noindex, nofollow` are verified. Native admin
login remains required; do not introduce site-wide Basic Auth for this release.

Local and staging user `Burmeister` (`k.burmeister@industriesalon.de`) has only
`iss_project_uat`: create drafts, edit draft/published projects and use media.
It cannot publish new projects, delete them, access private projects or manage
other admin areas. Editing a published project updates its public content.
Login lands on Projects; only Projekte is in the sidebar; personal profile and
logout remain in the account toolbar. No invitation/email was sent. Local and
staging passwords are separate, private and ignored in
`var/project-uat-20261016/{credentials,staging-credentials}.json`.

## Data delivered and recovery

Fresh verified staging backup:
`/srv/industriesalon/stage/backups/20261016-073840/` contains the 118-table SQL
backup, uploads, private intake, tracked code, installed runtime/configuration
and original commit `6c8cea7`, with `SHA256SUMS`. The successful archives were
fully read and checksummed. A failed first runtime archive is explicitly named
`runtime-config.incomplete`; it is not in the verified manifest.

Owner migrations applied and verified: `2026-10-08-taxonomy-seeds`,
`2026-10-11-owner-setup`, `2026-10-11-commerce-idempotency`,
`2026-10-12-schoneweide-landing`, and `2026-10-15-fuehrungen-categories`.
Private target-local before-images are under
`/srv/industriesalon/stage/var/project-uat-20261016/`. Existing commerce requests
were retained. Schöneweide's two obsolete empty reference arrays and four older
tour metadata states were reviewed and added as exact accepted baselines;
unrecognized drift is still rejected. Never restore these snapshots over later
editorial changes.

The paired `2026-10-15-futura-composition` and `2026-10-15-kiezkarte` migrations
and upload archives created staging review drafts **27454** and **27457**.
All 23 original media checksums and 53 generated image sizes were verified.
Both authenticated draft previews and sampled images return 200. Seven pre-existing published
projects remain unchanged. Private migration receipts remain in the existing
`iss_backup_20261015_futura_composition` and `iss_backup_20261015_kiezkarte`
options. Do not replay/rollback after editors change these drafts without review.

Preserve local KiezKarte **30811** and Futura **30615**, which the user published,
original Futura **25657**, and the composed review draft **30743**. Staging's
new copies intentionally remain drafts. Review conflicting prices, programme,
partners, historical copy, imagery and the AI illustration before publication;
the migration JSONs retain review notes. The older Futura pilot is historical,
not the current review draft.

## Verification and remaining work

74 project capability/native JSON autosave/save/reload checks passed locally and
on staging; all temporary fixtures were removed. Authenticated HTTP verifies
login routing, the sole Projects menu, both review editors, media selection and
403 responses for unrelated admin screens. Local tour checks pass 47 assertions;
staging tour drift and all applied migrations verify. All 11 owner schema groups
pass. Staging media-check covers 96 canonical documents and 179 unique media refs.
Homepage, Projects, Schöneweide, Führungen, Veranstaltungen and Kalender return
200 with noindex. The project template is theme-owned. Containers are healthy,
there are no failed host services and no PHP fatal/warning/parse errors in the
post-deploy FPM log check.

Chrome visual verification for the restricted user remains pending because an
extension popup blocked automation. Human editor UAT, Firefox/Word/Docs paste,
macOS Safari and staff booking/order acceptance remain open. Send the invitation
only when requested.
The retained Ausstellung DB override and archive collection slot still need
their separate target review; this release does not replace target templates.

The existing maintenance backlog is not resolved by this UAT release: fallback
and Set-decay have no new monitor receipts yet, 66 old graph rows remain dirty,
and five unregistered cron hooks remain (including `newsletter_update`, which
needs owner review). Scheduling, retention, legacy-cron cleanup and scoped
FPM/Nginx upload-limit activation were not changed. Follow
`docs/runbooks/staging-maintenance.md`; never run all due mail tasks blindly.

## Place data and local runtime retained

Place code is deployed, but the separate 14 October Place data/preview/intake
migrations were not applied on staging as part of project UAT. All 84 local
Places retain their accepted canonical JSON state; the manifest contains 83
reviewed conversions and excludes the earlier Kino pilot. For a future Place
release, use the guarded image preview, research-question, Place JSON and epoch
invitation artifacts in that order. Opening KAOS intake is a separate explicit
opt-in: a stored gesture never opens it. Preserve private contacts/Sets, facts,
relations, milestones and existing target templates. Canonical procedure is in
`docs/architecture/editorial-platform.md`; local private recovery remains in
`var/place-dossier-20261014/`.

Local `wp_app` mounts website plugins/theme/intake receiver from this checkout;
core/database/uploads/intake stay in `/home/vladimir/wp`. Use Compose project
`wp`, `/home/vladimir/wp/docker-compose.yml`, and
`var/refactoring-20261008/website-code.yml` for matching CLI code. The mixed
checkout and archive/source storage are not cleanup or sync targets.
