# Current handoff — 2026-09-27

Website source is `/home/vladimir/wp-website`; `/home/vladimir/wp` is the preserved
archive checkout. The Atlas implementation is being exchanged from baseline
**e4cb658** through GitHub `main` to `staging.industriesalon.info`. The `.de`
staging hostname does not resolve. Data below is locally applied; target
migration receipts and the deployment closeout must confirm staging completion.
Production is outside this deployment.

## Atlas supplier update — local only

Applied the reviewed source revision `8574160c4ae79a6101dad59d21c9817ab29d0814`:
**24 current-field changes / eleven Places / seven modern milestones**, including
the accepted Reinbeckhallen follow-up. All 84 local
Places, 186 historical phases and 186 historical states remain intact; public
Atlas still contains 76 Places. Existing posts, protected metadata and taxonomy
relationships match their pre-import hashes. Four obsolete derived current owner
links are deprecated; every other graph relation is unchanged. Supplier reports
include May 2026 observations; retrieval in September does not make those facts
September-verified. Uncertain ownership and opening dates remain qualified.

Paired artifacts in `ops/migrations/`: `2026-09-27-register-source.json`,
`2026-09-27-register-updates.json`, `2026-09-27-register-current-sync.php`, then
`2026-09-27-register-current-relations.php`. Deploy matching plugin/theme code
before applying these on another environment. Both migrations support dry-run,
apply and verify; local apply is complete, repeat is a no-op. No uploads required.
Private backup/receipts: `/home/vladimir/.local/state/iss-register-sync-20260927/`.
Verified `before.sql.gz` SHA256:
`d708ad494d2b94dddda23e9daa717944ddd8a55c15aaeb7219bfee7589e717af`.
Verified post-import `after.sql.gz` SHA256:
`e0061f42ac9b9b33f0bc0f1081db765cb1e5e236c05b90d54cc6d24e09768875`.
Scoped before-images also remain in `wp_iss_backup_20260927_register_*` tables.
Rollback only affected rows using before-images, preserving later edits and the
additive schema; do not restore the entire database over subsequent work.

Verification: 25 transactional runtime checks, migration read-back/idempotence,
REST bootstrap/detail, desktop and 390px chronology, and existing dossier editor.
Targeted PHP syntax/static analysis pass. PHPCS passes changed files except
pre-existing unchanged findings in `place-states.php` (7 errors / 22 warnings).
Source authority and the JSON exchange/storage contract are documented in
`docs/architecture/places-editorial-atlas-restructure-plan.md`. Next shared action
is to review/commit the bounded changes and deploy code with both migrations;
no push or deployment has been performed for this task.

Reinbeckhallen follow-up: upstream #70 is a development within canonical #13,
local Place 12877, not a new Place. The register-owned
`includes/register-data/source-place-mappings.json` and shared import resolver
enforce this for future imports. Active cultural status, owner and all four local
historical phases remain unchanged. The extension's 22 studios and planned 2027
completion are sourced; its unknown start date stays unknown.
After the original two migrations, run the same current-sync migration with
`apply reinbeckhallen --use-include`; payload is
`ops/migrations/2026-09-27-register-reinbeckhallen.json`. Locally applied and
verified; repeat produces zero writes/events. Backup `reinbeck-before.sql.gz`
and `reinbeck-{dry-run,apply,repeat,verify}.json` are in the private evidence
directory above; separate `wp_iss_backup_20260927_register_reinbeck_*` tables
hold before-images. No uploads. All **30 runtime checks** pass, including alias
resolution and duplicate-identity rejection; PHP checks, REST count/status,
preservation hashes and the public chronology were verified.

Contacts: seven local contact clauses now live in staff-only
`_iss_register_contacts`; `research_note` is no longer public REST metadata.
Existing editor has separate name/email/phone/role/source fields. Public import
review rejects contact-bearing text. Shared supplier JSON is redacted; original
record hashes remain provenance identifiers and file checksums were refreshed.
Deploy code, then `2026-09-27-register-private-contacts.php` with mode and a
**privately transferred** payload path (outside the web root). Local private
`contact-separation/` under the evidence directory above holds `payload.json`,
original supplier evidence, verified `before.sql.gz` and migration receipts.
Do not put these files in Git/uploads. Backup table:
`wp_iss_backup_20260927_register_contacts`. Apply and repeat are verified;
21 privacy + 30 import tests pass, along with 16 anonymous page/API checks and
the authenticated contact editor. Public content/history are unchanged. This
follow-up is also local/uncommitted; no staging or production changes.

## Current programme state and ownership

Staging now has **four new Veranstaltungen and four new Ausstellungen**, imported
from nine published production announcements. The discovery game belongs to Walk
of Fame, so it is not a duplicate event with an invented date. Veranstaltungen
includes exhibitions through its existing timeline block; Kalender uses the
existing occurrence projection. Both landing templates are theme-owned.

| Production source | Staging ID | Programme entry |
| --- | --- | --- |
| 12763 | 27411 | Anne Rabe, 15 October, 19:00 |
| 12060 | 27413 | Nachts am Telefon, 24 September, 19:00 |
| 11969 | 27415 | Tag des offenen Denkmals, 12–13 September |
| 11915 | 27417 | Festival der Berliner Industriekultur, 12 September–4 October |
| 12744 | 27419 | Jüdisches Leben und Arbeiten, 9 October–7 November |
| 12680 | 27421 | Pioniere der Transformation, 12 September–4 October |
| 11895 + 12018 | 27423 | Walk of Fame, 12 September–4 October |
| 11696 | 27425 | PS & Pioniergeist, 4 July–30 August |

All dates are 2026. Unspecified end times stay blank; inclusive date-range bounds
are not opening hours. Source identity, wording, image metadata, ticket links and
material are retained. Two old inline image references are missing in production
itself; the import records that and uses the available current featured images.
Event material links now render through the existing shared theme helper.

SuperSaaS refreshed **45 slots**: one new occurrence, 44 updated, no errors or
inactivations. Its previously unmapped `tour:waldfriedhof-anja` series now points
to existing Führung **11940**, including 14 November, 11:00–13:00. Production's
announcement corroborates the mapping. Do not run another import merely to test.

**The eight programme entries now also exist locally**, imported by source URL
with fresh post and attachment IDs. Both environments have 94 canonical documents. Staging **27415** is now the
Denkmaltag event; local **27415** is an unrelated legacy fixture. Never identify
cross-environment content by matching numeric IDs or overwrite staging with a
local database snapshot. Use source identity and the recorded migration maps.

## Programme artifacts and verification

- Applied once: `ops/migrations/2026-09-22-programme-sync.php`; public source and
  media evidence: `ops/migrations/2026-09-22-programme-source.json`.
- Paired uploads manifest: `ops/uploads/2026-09-22-programme-sync.manifest`.
  **18 attachments / 215 files / 99,701,416 bytes**, hashes verified. All files were
  additions; no existing upload was overwritten or removed.
- Private staging evidence: `/home/vladimir/server-actions/programme-20260922/`:
  verified `before.sql.gz`, `after.sql.gz`, `table-comparison.json`, and
  `cli/receipt.json` with new IDs, existing-content hashes and mapping before-image.
  Local evidence: `/home/vladimir/.local/state/iss-programme-sync-20260922/`.
- Read-only verification, with the migration and receipt available to WP-CLI:
  `wp eval-file FILE verify /PRIVATE/receipt.json --use-include`. **Do not replay
  `apply`**, including any earlier editorial/media migration.
- Postflight passed: all eight published documents and dates, exactly one public
  active WP occurrence per entry, media hashes, and Waldfriedhof mapping. Every
  pre-existing post and metadata row is unchanged. Comparison of 67 SQL tables
  confirms archive, booking/commerce, newsletter, user and usermeta data unchanged.
- `wp iss-editorial media-check`: **94 canonical documents / 169 unique media
  references**, passed. Local **139 registry/renderer checks**, targeted PHP lint,
  PHPCS/PHPStan and `git diff --check` passed; test fixtures removed.
- Chrome desktop verified September/October programme listings, calendar listings
  including November's mapped tour, detail dates/media, Walk of Fame game/PDFs and
  the telephone event's external ticket link. No horizontal overflow on checked
  pages. The summer exhibition detail shows its July–August range. Phone layout
  was not retested for this import. Containers remain healthy; none restarted.

## Earlier accepted website state to preserve

One JSON workspace and registry serve nine formats; the theme owns composition
and skins. The homepage uses the original shared hero pattern. About restores
its inset heading, tall triptych images, light captions and stacked mobile
images via two bounded slots in the same document renderer. Staging's CARTO key
support is shared code; the key stays outside Git. See the
[audit](docs/project/editorial-consolidation-audit.md) and
[editorial contract](docs/architecture/editorial-platform.md).

Earlier local-to-staging editorial sync and thumbnail repair remain applied:
52 documents, 60 content/template records, 13 initial plus seven repaired media
records, 33 plus eight transferred files. Their postflights, media checks and
desktop/phone checks passed. Original private drafts and unrelated data survived.
Report **27388** retains source event **26813** and its owner-managed venue link.
Local home **12257** keeps published **10+4** and autosave **27678** **10+4**.
Local overrides `single-ausstellung` **26309**, `page-publikationen` **26560** and
`page-projekte` **26532** match staging; keep checking effective template authority.
Earlier checks included 34 DOM tests, 287 storage checks and 169 Set assertions.
`videos.php` retains its 24 pre-existing PHPCS findings.

Earlier one-time artifacts: `2026-09-22-editorial-staging-sync.php`,
`2026-09-22-editorial-sync-dependencies.php`, and
`2026-09-22-editorial-media-repair.php` under `ops/migrations/`, with matching
uploads manifests. Full staging DB/uploads backup:
`/srv/industriesalon/stage/backups/20260922-132743/`. Earlier private before-images
and comparisons: `/home/vladimir/server-actions/sync-20260922/` and
`/home/vladimir/server-actions/thumbnails-20260922/`. The original staging changes
remain in its named pre-sync Git stash; its inactive Compose patch was not
reapplied. Staging uses `/srv/industriesalon/stage/compose.yml`.

Local runtime mounts website code through
`/home/vladimir/.local/state/iss-editorial-pilot-20260921/website-code.yml`.
**Do not restart base Compose:** it would restore the older source mounts.
Local WP-CLI wrapper: `/home/vladimir/.local/state/iss-editorial-pilot-20260921/wp`.

Next: staff acceptance of the revised Veranstaltungen page; Firefox and real clipboard/paste checks remain separate UAT items.
The authenticated staging editor was not tested during this programme sync.
Production release and further data transfers remain separate tasks.


## Veranstaltungen overview — current delivery

The accepted page now leads with its next event and an editorial summary, then
full-width upcoming date rows, ongoing programme notices and separate current /
future exhibitions. Past appointments are behind the native details disclosure;
only illustrated published event/exhibition reports qualify as Rückblicke. The
existing programme block/query and shared cards own this presentation. Theme
`page-veranstaltungen` is effective locally and on staging; no DB override reset
or service restart is required.

Local-only one-time import: `ops/migrations/2026-09-22-programme-local.php`,
with `2026-09-22-programme-local-source.json` and
`ops/uploads/2026-09-22-programme-local.manifest`. It uses the already verified
programme media bundle: 18 attachment records / 215 files, all additions locally.
No old staging import or SuperSaaS refresh was replayed. Local-only existing
recurring occurrences remain local; databases are not interchangeable snapshots.

Both environments use `ops/migrations/2026-09-22-events-copy.php` for eight native
editorial excerpts. This changes no dates, original document text or media.
Local before-image and receipts are in
`/home/vladimir/.local/state/iss-editorial-pilot-20260921/`:
`events-landing-before.sql`, `events-local-receipt.json`, `events-copy-receipt.json`.
All pre-existing local posts/meta survived the import. Local media-check passed
94 documents / 169 media references; 23 read-only programme checks passed.
CSS/JS lint, targeted PHP checks, desktop and 390px checks passed. Staging is
also deployed at 41b34d2 with the excerpt migration applied once. Both sites pass
23 programme checks and media-check (94 documents / 169 media references).
Staging desktop/phone checks confirm real images, October dates, current ranges,
no horizontal overflow, native history disclosure and pagination. Homepage,
Veranstaltungen, Kalender and Ausstellungen return HTTP 200 on both sites;
staging retains noindex/nofollow. Containers are healthy; none restarted.
Authenticated Site Editor interaction and Firefox were not retested.

Staging backup/receipt:
`/home/vladimir/server-actions/events-landing-20260922/private/` contains verified
`before.sql.gz` and `copy-receipt.json`. Before/after checksum evidence locally:
`/home/vladimir/.local/state/iss-events-landing-20260922/staging-{before,after}.json`.
All unrelated staging posts/meta are unchanged; 100 SQL tables are unchanged.
Changes are limited to the eight target posts/meta and their existing graph,
search, occurrence and cache projections. Existing archive, commerce, newsletter
and user data remain unchanged. Rollback uses the excerpt before-images plus the
preceding code commit; local import additions are enumerated in its receipt.
Do not replay either import or the excerpt apply. Production remains unchanged.


## Date rotation — current delivery

`8dddf10` adds automatic date rotation, Berlin-day handling for missing event ends,
ongoing/open-ended exhibitions, recurring next dates, six-week quiet-period
exhibition fallback, and expiring focus through the existing graph promotion.
Event basis fields now offer Terminstatus; existing promotion exposes Hervorheben
bis. Cancelled/sold-out entries carry notices and use Details. Editors can open
“Datumsvorschau für die Redaktion” on Veranstaltungen; its nonce-protected clock
changes no records. Preview history intentionally has no AJAX continuation.
See `docs/architecture/content-model.md` for the contract and test commands.

Local and staging each pass **48 rotation + 23 overview checks**, including actual
SQL/REST midnight transitions, preview permissions, future overview, DST/year
boundaries and existing promotion expiry conversion. Targeted PHPCS/PHPStan pass.
Public Chrome desktop/mobile and no-cache headers verified; staging still
noindex/nofollow. Anonymous preview parameters cannot change the featured event.
No source content/date/media changes, DB migration or uploads artifact required;
blank metadata defaults and existing graph storage suffice. No service restarted.
Authenticated form rendering and preview were exercised through WP-CLI; a browser
save of the editor controls and Firefox remain staff acceptance checks.
