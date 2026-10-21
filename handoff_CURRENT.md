# Current handoff — 21 October 2026

Website implementation `4cfdf32` is on GitHub and staging; both checkouts were
clean at closeout. This handoff-only checkpoint needs no SQL, content, uploads or
runtime deployment. The next staging agent can fast-forward the documentation.

The Goosee-Audit follow-up is complete. Across 45 retained access logs, none of
the campaign's 76 IPs or its user-agent reappears after the successful login,
through 21 October 19:52:36 UTC. No subsequent use of that session was established.
Missing session identifiers/request bodies and unlogged routes prevent ruling
out reuse under different identifiers. Private evidence and checksum-verified
review: `var/login-investigation-20261021/goosee-followup/review.json`; staging
copy: `/srv/industriesalon/stage/backups/incident-20261021/goosee-followup/`.
The operator remains unidentified. No further incident work is queued.

## Staging login containment and persistence review complete

The leaked local testing password is retired. It now matches none of the eight
accounts across local, staging and touchtable. Local admin was rotated to a
generated password and its three local sessions cleared; the private credential
record is `var/local-admin-credentials.json` (0600). Staging's user-rotated
credential was preserved, with zero sessions and admin application passwords.
The unrestricted staging importer is disabled; content refreshes must preserve
target accounts and follow `docs/infrastructure/sync.md`. Browser tests require
a private target-bound credential file. Incident/rollback evidence is preserved;
never restore old authentication state. No content/media transfer is required.
Private remediation checks: `var/login-investigation-20261021/credential-remediation/`.

The 20 October 14:21:10 UTC login audit and Nginx access log confirm an
unrecognized successful `admin` login following 71 failed attempts. The user
changed the password. On 21 October, all three stored WordPress sessions across
two accounts were revoked through core APIs; zero remained. The affected admin
had no application passwords. No new accounts, changed emails or unexplained
privilege, plugin, file or scheduled-task changes were found against the verified
17 October backup, official WordPress checksums and trusted Git `e75ac4a`.

The separately approved staging Nginx fix moves the existing upload-PHP deny
rule before the general PHP handler. Syntax/reload checks passed; both benign
upload PHP guards now return 403, while home/login/media/CSS/intake return 200.
Noindex and other services are preserved. Private evidence, backup paths and
limits: `var/login-investigation-20261021/impact-review.json`. Requested checks
are complete; this does not rule out data viewing/copying during the login.
No SQL migration, content/uploads transfer or source-code deployment was needed.

The historical follow-up found no pre-login PHP-like upload requests in retained
staging logs (7–20 October) and no added upload scripts in nine preserved archives.
The 16 October full DB refresh imported the local admin password hash. Offline
verification confirmed the local testing password was valid on staging before
and after the login, but not before the refresh. The submitted attacker password
is not logged. Later PDFs match the authorized release. Snapshot gaps and Git-only
plugin backups limit attribution. Private timeline:
`var/login-investigation-20261021/timeline/prelogin-review.json`.

## PHP security patch active

Staging and the separate touchtable VPS stack now run PHP 8.3.36 for
CVE-2026-109618/109619. Both WordPress containers are healthy; nine route checks,
all 41 extensions, GD/Imagick/Intl, persistent mounts, config/core-version hashes
and existing PHP limits passed. Staging remains noindex. Other service
containers and website code (`e75ac4a`) were preserved; no SQL migration or
uploads transfer was needed. STRATO production already serves patched 8.3.36.

Verified backups, pinned build and recovery paths are recorded in
[staging infrastructure](docs/infrastructure/staging.md#php-runtime-security-update).
Private receipt and rebuild source copy: `var/php-security-20261021/`.
Next runtime maintenance: replace the emergency build with an official image
only after verifying patched PHP and repeating the runtime checks.

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
