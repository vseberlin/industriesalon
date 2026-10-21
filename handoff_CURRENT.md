# Current handoff — 21 October 2026

## Project picker access deployed

`0d1ec04` is deployed through GitHub to staging. Burmeister remains the sole
`iss_project_uat` account. This role can browse existing private Sets and select
approved media, and open existing Archive selections in the project editor.
Set/archive management, membership/link changes and unrelated administration
remain denied. Existing projects and private drafts were not edited.

The owner capability migration added only `iss_read_sets` to administrator and
project-UAT roles, on local and staging. Private before-images and verification:
local `var/project-picker-20261021/`; staging
`/srv/industriesalon/stage/repo/var/project-picker-20261021/roles-before.json`.
No SQL/content/uploads transfer. Rollback must review later role changes before
restoring the before-image and previous code; never restore unrelated user data.

Verified: 103 local UAT assertions with fixture cleanup; PHP lint/PHPCS/PHPStan;
staging role diff, idempotence, five successful picker reads and denied write
permissions. Chrome as Burmeister loaded both pickers, selected an approved Set
image and an Archive object without applying them, and kept unapproved media
disabled. Existing Futura private draft/conflict was preserved. No further
permission work is pending; earlier human acceptance gates below still apply.

## Audit follow-up deployed and verified

Website checkout: `/home/vladimir/wp-website`; staging:
`/srv/industriesalon/stage/repo`. Implementation: `889af3d`, published through
GitHub and applied on staging with both private content migrations.
This checkpoint closes the requested Word audit pp. 5–7 and 9 corrections and
adds the final collection-photo width fix; that CSS fix needs no data/media artifact.

- Sammlungen: four illustrated entry points, fewer repeated navigation boxes;
  retained gallery and six periods. Archive cards distinguish object type from
  subject, with readable taxonomy names and local image enlargement.
- Projects: In Planung / Läuft / Abgeschlossen from existing project status;
  Futura planned, KiezKarte and ZiG running. Anne Rabe links to internal ZiG.
- Behrensbau: name first, address secondary, existing public featured image used
  when no dated comparison pair exists. Shared small labels have adequate contrast.
- Exhibition detail: lighter image overlay, opaque visit panel, corrected copy.
- **SuperSaaS supplies schedule data only.** All booking controls and fallbacks
  stay internal, using the existing checkout. No provider submission/redirect.
- Old Industriesalon/WF/project minisite navigation removed. Source credits and
  provenance retained; existing quiz links now lead to the internal retrospective.
  Seventeen archive PDFs are now local downloads. Hero button positions unchanged.

## Verification and private release

Local and staging: 211 read-only audit/booking checks, 191 valid canonical
documents, 184 existing media references, consistent registries. Local additionally
passes 73 isolated event/feedback assertions with fixtures removed.
All 412 public pages return 200 on both hosts, without old-site/minisite/SuperSaaS
links. Staging initially rate-limited 132 requests; a slower serial retry passed
all of them without changing rate limits. All 3053 object bodies contain no
embedded legacy links; archive renderers
use local copies while keeping source credits. Firefox checks cover ten affected
routes at 390/768/1348/1720 px, with no overflow or remaining axe findings.
Checkout, Escape/focus return, group inquiry and archive/gallery controls pass on both hosts;
no external provider requests or real submissions. Chrome checkout and key page
visuals inspected. Targeted PHP/CSS checks pass.

Private inputs/evidence: `var/audit-followup-20261020/` on both hosts.
Migrations: `2026-10-20-audit-followup.php` (65 local / 57 staging published
records; differences are local URL cleanup), and `2026-10-20-local-websites.php`
(two place website fields). Before-images/private drafts are guarded in their
`iss_backup_20261020_*` options. Eight term display/status changes; no taxonomy
merges. No schema change or full database replacement.

Paired uploads: 17 PDFs, attachment registration through WordPress. All 194
referenced download files have matching hashes on staging. Transfer archive hash
`a5983a619ce005f32721c19cf8bba397890df5b45a4797b56f02dc30b00a7f67`.
Verified staging backup (119 tables, seven artifacts):
`/srv/industriesalon/stage/backups/iss-backup-20261020T162319Z/`.
SQL, content JSON, media, manifests and backups remain private, outside GitHub.

All prior posts/revisions remain on both hosts, and all 191 canonical documents
match after site-URL normalization. Only the authorized records changed.
Local before/after comparison confirms that place owner APIs rebuilt 16 epoch
and 29 state projection rows; 193 epochs/269 states retain all historical dates,
labels, media and meanings. Only source URLs, derived IDs and timestamps changed.
Restore receipts through owner APIs only after checking for newer editorial edits.

## Remaining gates

Production untouched; staging remains noindex. Kiezfonds exact share and the
previously requested children's-photo rights confirmation remain outstanding.
Actual 200% zoom, physical touch and screenreader acceptance remain human gates;
viewport/axe checks do not replace them. Maintenance activation is separate.
