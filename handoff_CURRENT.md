# Current handoff — 2026-09-27


## Veranstaltungen release — staging sync in progress

The reviewed website changes add an image-led event opening, occurrence-backed
current dates, wider shared Rückblick cards and moderated text feedback through
the existing guest link. **Material & Rückblicke → Feedback und Upload geöffnet**
opens intake; **Feedback prüfen** uses native WordPress comment moderation.
Existing events/reports, saved documents and explicit closed choices are preserved.
The user authorized GitHub/staging synchronization. Preflight: local and staging
started at `21123a2`, staging checkout clean, healthy containers, no failed host
services. Deploy this code through GitHub `main`, then the scoped content migration.
The approved layout includes optional opening fields, visit panel, upcoming cards
and past-event report/voice priority. Deploy `iss-editorial` with the other components.
Anne Rabe opening copy is applied locally via
`ops/migrations/2026-09-27-event-opening.php`; run check/apply/verify after target
code deployment. Its before-image is
`/home/vladimir/.local/state/iss-editorial-pilot-20260921/event-opening-before-20260927.json`.
No schema/new uploads/template override artifact is needed. Existing 240px Anne
Rabe artwork is preserved; its low resolution limits the image quality.

Local receiver runtime is still mounted from `/home/vladimir/wp/ops/event-drop/interface/`;
its `index.php` was backed up and explicitly synced from website source, without a
restart. Before-image and HTTP receipts are under
`/home/vladimir/.local/state/iss-events-feedback-20260927/`. Other runtime code uses
the existing website-code override. Deliver receiver/plugin/theme changes together.

Validation: 49 event/feedback checks, 295 shared-storage checks (94 documents),
33 editor UI checks, 169 prior Set checks and 48+23 programme checks;
PHP syntax, targeted PHPCS/PHPStan, CSS lint and whitespace checks. Chrome desktop
and actual 390px covered the event opening (including the dark programme skin), illustrated report, approved voices and
contribution form; native editor controls/moderation list were inspected. A new
temporary draft verified headline editing, subtitle clearing, photo selection,
autosave, live preview and native Save Draft; that fixture and its revisions
were removed. The existing Anne Rabe image remains uncropped. HTTP
verified consent/nonce denial, text-only submission, pending privacy, approval,
duplicate rejection, file upload and closed GET/POST. Temporary fixtures and their
files/manifest rows are removed after verification. Firefox remains staff UAT.


Website source is `/home/vladimir/wp-website`; `/home/vladimir/wp` remains the
preserved archive checkout. Atlas implementation **8ab3571** is pushed to GitHub
`main` and deployed on **https://staging.industriesalon.info**, together with all
four data migration steps below. Preserve that applied Atlas state during event deployment.
`staging.industriesalon.de` does not resolve; the configured `.info` host was
verified and used. Production was not changed. No services were restarted.

## Atlas current facts, chronology and private contacts

Local and staging now have **24 current-field updates / eleven Places / seven
modern milestones** from supplier revision
`8574160c4ae79a6101dad59d21c9817ab29d0814`. Local historical narratives, phases,
media, editorial relationships and local-only Places remain authoritative.
Preservation hashes passed for all posts, protected metadata, 186 historical
phases, 186 historical states and taxonomy relationships. Atlas still contains
76 public Places. Four obsolete automatic current owner links were deprecated;
every other graph relation was preserved. May supplier observations are not
presented as September verification; uncertain dates and ownership stay qualified.

Upstream **#13 and #70 resolve to the existing Reinbeckhallen Place**. The shared
resolver blocks duplicate identities and whole-site changes from component data.
The Gründerzentrum's 22 studios and 2027 target are development information, with
an unknown event date; the site's active cultural status remains intact.

Seven embedded contact records were moved into protected
`_iss_register_contacts` fields (name/email/phone/role/source). Research notes are
also excluded from public REST. Public import validation rejects contact-bearing
text. The shared supplier snapshot is redacted; original evidence and the
contact migration payload stay private, outside Git and the web root.

Applied and verified on both environments, in order:

1. `ops/migrations/2026-09-27-register-current-sync.php apply --use-include` with
   its pinned `register-source.json` and `register-updates.json` artifacts.
2. `2026-09-27-register-current-relations.php apply --use-include`.
3. The current-sync migration with `apply reinbeckhallen --use-include`, using
   `2026-09-27-register-reinbeckhallen.json`.
4. `2026-09-27-register-private-contacts.php apply /PRIVATE/payload.json --use-include`.

All support read-only `verify`; use that for audits. No uploads artifact was
needed. Scoped before-images remain in `prefix_iss_backup_20260927_register_*`.
Restore only affected rows if recovery is needed, preserving later edits; do
not restore an entire database over subsequent editorial work.

Local private evidence: `/home/vladimir/.local/state/iss-register-sync-20260927/`.
Staging backup, config copies, private contact payload and all migration/test
receipts: `/home/vladimir/server-actions/atlas-sync-20260927/private/`.
Verified staging full database backups:

- `before.sql.gz`: `4c66e8931f1d1e25aafc5ba7e07b6ce00a46f0fba28c0d5ab57962cd958b6b51`
- `after.sql.gz`: `2f1c52943badc7849a43ee2e4de9926c53846fefa643fa63aa60cd5048c761b3`

Local and staging each pass **30 import + 21 privacy runtime checks**. All four
staging migrations report zero pending changes. Thirteen anonymous staging page/
API checks passed privacy, HTTP and noindex checks; migration inputs are not
publicly served. Staging browser verification covered Behrens-Ufer and
Reinbeckhallen chronology. Local desktop/390px and authenticated contact editor
were checked; authenticated staging editor and Firefox remain staff UAT.
Targeted PHP syntax, PHPCS and PHPStan pass, except unchanged pre-existing PHPCS
findings in `place-states.php` (7 errors / 22 warnings). Containers remain healthy.
The authority/exchange contract is in
`docs/architecture/places-editorial-atlas-restructure-plan.md`.

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
