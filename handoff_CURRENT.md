# Current handoff — 20 October 2026

## Released

Implementation **172340e** (including **37489f2**) is deployed from local through
GitHub `main` to staging. This release checkpoint is committed and synchronized
through the same channel. Website checkout:
`/home/vladimir/wp-website`; staging: `/srv/industriesalon/stage/repo`.

- Homepage project notes show readable titles and the saved Futura identity logo.
- About's triptych starts higher with square desktop images; phones retain the
  stacked 16:10 images. Shared JSON content and renderer ownership remain intact.
- Futura's 2025 report, the Zukunft im Gespräch project, its six event memberships
  and five reports are synchronized. Past events show their reports at their
  original URLs; Anne Rabe retains the pending-report view.
- Saved Führungen, Schöneweide and About documents are synchronized through
  `iss_editorial_save_document()`. This is saved-content delivery, not renewed
  classification/refactoring work.

## Verification

All **190 canonical documents match** after environment URL normalization;
all validate. Nine registries and **185 WordPress media references** pass.
Twenty-one public routes return HTTP 200/noindex without PHP errors.
Chrome staging checks passed for home/About at desktop and 390px: titles, logos,
triptych geometry, image loading and no horizontal overflow. Local tablet checks
also passed. PHP syntax/PHPCS/PHPStan, CSS lint/contracts, block roundtrip and
`git diff --check` passed. Containers are healthy; no failed services or release
runtime errors were found.

Only the three expected existing event posts and metadata on those events,
Futura and three landings changed. All existing revisions, private drafts and
unrelated post/meta rows remain intact; Place epoch/state projections match.
The migrations created one project, three events, six reports and 32 native
revisions. No notifications were sent.

## Private data and rollback

Verified pre-release backup:
`/srv/industriesalon/stage/backups/iss-backup-20261020T082447Z/`
(seven hashed/read-tested artifacts, 119 tables, code baseline **299058d**).
Retain this and the earlier full-refresh/editorial backups.

Applied and verified migration programs under `ops/migrations/`:

- `2026-10-19-futura-rueckblick.php`
- `2026-10-19-zukunft-gespraech.php`
- `2026-10-19-zig-rueckblicke.php`
- `2026-10-20-landing-sync.php`

Inputs, checksums, before/after comparisons and verification logs are private
under `var/release-20261020/` locally and on staging. Local `receipt.json`,
`comparison.json` and `http.json` hold detailed evidence. The four non-autoload
`iss_backup_20261019_*` / `iss_backup_20261020_landing_sync` options retain
target-local before-images and migration receipts. Use each script's rollback
instructions only after checking later edits; do not blindly replay.

**GitHub received code/tests/documentation only.** No SQL dump, content JSON,
source DOCX, media or transfer archive was committed. Existing uploads matched;
no upload transfer, full database replacement or schema migration was needed.
New IDs differ across environments: ZiG is local **42100** / staging **31337**;
Futura's report is local **42087** / staging **31333**. Resolve by slug/identity.

## Next acceptance and boundaries

Human editor/Firefox acceptance remains in `TODO.md`; private draft recovery
prompts remain untouched. No further implementation is queued by this release.
Preserve Futura **30743**, original **25657**, draft **30615**, KiezKarte **30811**
and Burmeister's private drafts. User **328** keeps Projects-only create/edit/media
rights; credentials remain private.

`wp_app` mounts website code; core/DB/uploads/intake remain in `/home/vladimir/wp`.
The mixed/archive checkout and external archive storage are not cleanup targets.
Staging stays publicly previewable with noindex by the user's exception; preserve
its URLs, credentials and infrastructure settings. Mail routing, maintenance
activation and production deployment remain separate work.
