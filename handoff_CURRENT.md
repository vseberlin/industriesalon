# Current handoff — 2026-09-22

Website source is `/home/vladimir/wp-website`; `/home/vladimir/wp` is the preserved
archive checkout. Local code, GitHub `main` and staging are synchronized through
**072e11f**; the subsequent closeout commit records this checkpoint. Verify current
`HEAD`/`origin/main` before work. Production was read for this import, not changed.

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

**Data now differ by design:** the new programme entries exist on staging only.
Local has 86 canonical documents; staging has 94. Staging **27415** is now the
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

Next: staff acceptance of staging, Firefox and real clipboard/paste checks.
The authenticated staging editor was not tested during this programme sync.
Production release and further data transfers remain separate tasks.
