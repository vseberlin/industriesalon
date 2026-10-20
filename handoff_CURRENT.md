# Current handoff — 20 October 2026

## Released and verified

Exhibition visitor information is deployed from local through GitHub `main` to
staging: visitor implementation **f6fc8a3**, confirmed admission **76898e4**,
baseline **4b02fdd**. This checkpoint follows
the same synchronization path. Website code: `/home/vladimir/wp-website`;
staging: `/srv/industriesalon/stage/repo`. Do not edit the mixed/archive checkout.

The overview reuses the central visit block. Current exhibitions with an explicit
museum venue show shared address, museum hours, free admission and access; online, external,
future and past exhibitions do not inherit those conditions. Ended exhibitions
are labelled. The existing venue field is editor-visible; thematic Place links
remain distinct. The duplicate single-exhibition template override is preserved
under a retired slug as a draft; the theme now owns the active composition.

The user confirmed that museum admission and the current Jüdisches Leben
exhibition are both free. **Eintritt frei.** is now stored once in central
settings and shown in both visitor panels. F04 is complete; next independent
audit slice is F06 (project status and actions).

Verification: **27 visitor checks + 42 prior audit checks** pass locally and on
staging. PHP lint/PHPCS/PHPStan, targeted ESLint/stylelint and three CSS contracts
pass. Chrome: overview/current/off-site/past/digital cases at 390/1440 px, no
horizontal overflow; native editor venue field checked without saving. Five
public routes return 200/noindex. No failed services or release PHP errors.
All 36 exhibition records/documents and existing address/hours/access settings
are preserved, apart from normal local editor lock metadata. The only changed
central fact is the confirmed admission note. Active templates and central facts match
between environments. No messages or real requests submitted.

## Private data and rollback

Verified pre-release staging backup:
`/srv/industriesalon/stage/backups/iss-backup-20261020T124900Z/`
(seven hashed/read-tested artifacts, 119 tables, baseline **4b02fdd**).
All existing managed backups retained. Apply and verify in order:
`ops/migrations/2026-10-20-exhibition-visit-info.php`, then
`ops/migrations/2026-10-20-museum-admission.php`. The second intentionally
supersedes the first price snapshot; verify admission after the final step.
Private environment-specific inputs, snapshots and receipts:
`var/visitor-info-20261020/` on each machine. Before-image option:
`iss_backup_20261020_exhibition_visit_info` and
`iss_backup_20261020_museum_admission` (non-autoload). Restore the recorded template name and
status through `wp_update_post` only after checking later edits; no template was
deleted. Admission rollback uses the recorded option before-image (absent before
this release). Detailed verification: `preservation.json`,
`admission-preservation.json`, `http.json` and release logs.
No SQL, content JSON, backups or uploads belong in GitHub; no schema/upload
transfer or full database replacement was needed.

## Preserved baseline and boundaries

The first audit/programme package remains delivered: **0eccd4e**, **805ad9f**,
receipt **4b02fdd**. It covers separate public/group tours, confirmed 14/10 public
fares and approximately two-hour tours, reduced tickets, calendar exclusions,
working-note removal, home/team/funder copy and exhibition/publication filters.
Behrensbau interiors remain closed. Previous migration/backup evidence remains
under `var/corrections-release-20261020/` and in the
[correction plan](docs/project/website-correction-plan-2026-10-20.md).

Home project titles/Futura logo, About triptych and project/report imports remain
delivered (**172340e**, receipt **c3bb60e**). Preserve Futura 30743, original 25657,
draft 30615, KiezKarte 30811 and Burmeister's private drafts. IDs can differ across
environments; resolve by slug. User 328 retains Projects-only create/edit/media
rights. Human editor/Firefox acceptance remains in `TODO.md`.

`wp_app` mounts website code; core/DB/uploads/intake remain in `/home/vladimir/wp`.
Staging stays publicly previewable with noindex. Preserve credentials, URLs,
mail routing and infrastructure settings. Production deployment and maintenance
activation remain separate work.
