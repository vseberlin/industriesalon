# Current handoff — 20 October 2026

## Website audit completion

The remaining code/content corrections from the
[combined audit plan](docs/project/website-correction-plan-2026-10-20.md)
are deployed and verified through GitHub main on staging: implementation
**dbbe547**, Repair-Café notice follow-up **f700a36**, baseline **5c2c605**.
This receipt follows the same fast-forward synchronization path. Website code: `/home/vladimir/wp-website`;
staging: `/srv/industriesalon/stage/repo`. The mixed/archive checkout is untouched.

This package covers project status/actions and stable project ordering, clear
visitor copy, one exhibition browser, archive search/facts/image links, video
labels/dates, compact header, mobile/tablet layout fixes, newsletter accessibility,
confirmed rental and Repair-Café details, and removal of technical captions and
generic social destinations. About triptych, Futura logo, archive provenance and
all previous tour/admission corrections are preserved.

User-confirmed rental terms: up to 100 seated overall, from €400, hours/catering
on request. Repair Café: first and second Wednesday, free, household-sized items,
no large machinery; published calendar dates/times remain authoritative. KiezKarte
retains €49, 20 partner offers and expiry 31 December 2026. Exact Kiezfonds share
remains unconfirmed; do not invent a percentage or extend the pilot.

## Checks and release dependencies

Local and staging: 42 new + 42 existing audit + 27 visitor checks; nine registries;
191 valid canonical documents; 184 existing media references. Targeted lint/static
checks and three CSS contracts pass. `videos.php` retains its 24 pre-existing
PHPCS findings, with no new findings from this change. Chrome: twelve routes at
390/768/1348/1720 px, no page overflow after final fixes; menu Tab/Escape/focus,
archive search/empty state, atlas search/epoch/fullscreen and About appearance.
Staging: 21 HTTP checks, 24 mobile/desktop route checks, noindex and legal anchors
verified. All 191 canonical documents match after URL normalization; existing
project image hashes and About/home template authority match. All 269 factual
place-state rows match the backup; native save rebuilt only the corrected place's
projection IDs/timestamps. Three old ordinary revisions of the edited Walk of Fame project
were pruned by native retention; the verified backup retains them. Private drafts
and unrelated posts are unchanged. No real messages, newsletter submissions,
bookings or orders sent.

Verified staging backup:
`/srv/industriesalon/stage/backups/iss-backup-20261020T133735Z/`
(seven read-tested/hashed artifacts, 119 tables, existing backups retained).
Private inputs/snapshots/evidence: `var/audit-completion-20261020/` on each machine.
Applied and verified after fast-forwarding staging:

1. `ops/migrations/2026-10-20-audit-completion.php` with `input-stage.json`.
2. Same migration with `place-input-stage.json`.
3. `ops/migrations/2026-10-20-archive-labels.php`.
4. Completion migration with `repair-input-stage.json` (calendar-owned times).

Scope: thirteen content records, two settings, two preserved template overrides,
twelve taxonomy display labels. Before-images are saved in non-autoloaded options
`iss_backup_20261020_audit_completion`, `iss_backup_20261020_audit_place_copy`,
`iss_backup_20261020_archive_labels`, `iss_backup_20261020_audit_repair_notice`.
Rollback uses owner APIs against these
receipts after checking later edits. No whole-DB replacement, schema or upload
transfer. SQL, content inputs, backups and media never go to GitHub.

## Boundaries and remaining acceptance

Staging remains noindex. Keep credentials, mail routing, core/DB/uploads/intake,
private drafts and user 328's Projects-only create/edit/media rights. Earlier
programme and visitor receipts/backups remain under their existing private folders.

Before production, complete human Firefox, screenreader, touch, actual 200% zoom
and editor acceptance, plus source-rights review where not already established.
This release is not a claim of accessibility certification or production launch.
The exact Kiezfonds share is the only outstanding fact requested in this audit.
Maintenance activation remains a separate task in `TODO.md`.
