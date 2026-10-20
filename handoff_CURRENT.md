# Current handoff — 20 October 2026

## Release in progress

The user authorized local -> GitHub -> staging synchronization, including required
database state, with all SQL/content/media artifacts kept out of GitHub. Pending
release includes **37489f2**, readable front-page project-note titles and the saved
Futura identity logo, plus a higher About triptych with taller desktop images.
Desktop/tablet/phone checks and targeted PHP/CSS checks passed.

Staging starts at **299058d**, with a clean checkout. Backup
`backups/iss-backup-20261020T082447Z/` is verified (seven artifacts, 119 tables).
Staging has no newer affected content edits. Existing logo/triptych files match.
The three 19 October migrations below are required, plus
`ops/migrations/2026-10-20-landing-sync.php` for the saved Führungen, Schöneweide
and About documents. Private payloads and comparison evidence are in
`var/release-20261020/`; apply through owner APIs after code delivery.
Preserve target private drafts and infrastructure settings. No uploads or schema
migration is needed. Replace this in-progress checkpoint with verified results.

## Delivered locally

- **ZiG project 42100**, `/projekte/zukunft-im-gespraech/`: shared JSON editor,
  Bühne/Ocker presentation and automatic project events. Optional **Reihe / Projekt**
  (`iss_project_id`) appears in the Veranstaltung form/table and projects a graph
  `part_of` edge. The homepage preview features the next eligible evening; compact
  timeline rows show the other dates and past editions. All six current evenings
  are past, so a feature appears once a future event is registered.
- Six canonical events: existing **24829, 13349, 28060**, new **42103, 42106,
  42111**. Kowalczuk, Isabella Hermann and La Hengst start at user-confirmed 18:00.
  Massarrat is **22 April 2026, 19–21**; Anne Rabe is **15 October 2026, 19:00**.
  Existing event documents/private drafts remain intact. Undated 2027 plans stay
  editorial text. Individual evenings do not share recurrence keys.
- Five ZiG reports **42152, 42155, 42158, 42161, 42164** preserve the ten essay
  paragraphs from the supplied July DOCX. Each has seven graph links: original
  event, project, guest, moderator, two organisers and venue. Internal accounting
  and completion notes remain private. Anne's source section is an announcement,
  so her event currently displays the pending-report view.
- Every dated event automatically presents a **Rückblick at its original URL**
  after the existing occurrence boundary. Published reports and approved voices
  precede the original announcement. Without a report, the page says one is not
  yet available. Cancellations remain explicit; future recurrences stay current;
  closed intake and private reports remain protected. No date-triggered post or
  graph identity is created. Reports created from an event connect both ways.
- Bühne projects render the native content block and its reciprocal report feed.
  Text-only report cards omit empty images. Graph queries select one primary
  name per entity, preventing duplicate relation rows from imported aliases.
- **Futura report 42087**, `/rueckblick/futura-2025/`, has six JSON sections and
  ten verified graph relationships to two projects, three people, two organisations
  and three places. Project **30743** links internally; its opening venue is
  corrected to Industriesalon. Protected source posts/private drafts are preserved.

## Verification and acceptance

Passed: 75 event/feedback, 48 date-rotation, 59 ZiG public/text/graph/editor,
42 project membership/editor/visibility/feature/report, 25 homepage-preview,
114 graph atomicity and 29 programme checks; targeted syntax/PHPCS/PHPStan and
`git diff --check`. Browser checks cover desktop and 390px project/report layouts,
Anne's pending-report view and Isabella's published report, anchors and overflow.
Event responses remain no-cache. Test fixtures were removed; existing events,
reports, comments, source revisions and six ZiG calendar occurrences are preserved.

Next acceptance: review the local Futura/ZiG pages and shared JSON editor. ZiG
has a user private draft recovery prompt (19 October, 12:56); it remains untouched.
Canvas/selector hydration and native fixture saves passed; interactive user-draft
save and Firefox UAT remain open. Broader accepted UAT work stays in `TODO.md`.

## Private data dependencies

Migration programs are in Git; content inputs, sources, before/after snapshots
and verification receipts are ignored, private files:

| Migration under `ops/migrations/` | Private input and evidence directory |
| --- | --- |
| `2026-10-19-futura-rueckblick.php` | `var/futura-rueckblick-20261019/` |
| `2026-10-19-zukunft-gespraech.php` | `var/zukunft-gespraech-20261019/` |
| `2026-10-19-zig-rueckblicke.php` | `var/zig-rueckblicke-20261019/` |

Each directory retains valid `content.json`, `before.json`, `after.json` and
`verification.json`. ZiG reports also require their retained `source.docx`.
Non-autoload DB receipts: `iss_backup_20261019_futura_rueckblick`,
`iss_backup_20261019_zukunft_gespraech`, `iss_backup_20261019_zig_rueckblicke`.
Latest public recheck: `var/event-retrospective-20261019/zig-verification.json`.
Existing media is reused; **no new uploads or table/schema migration** is needed.
The automatic retrospective presentation itself needs no stored-content migration.

A later authorized release must transfer private inputs separately, deploy matching
code, inspect target identities/media and later edits, then use migration owner APIs
and their verification modes. Never replay over newer content or private drafts.
Rollback guidance is in each migration header; retain its before-image/receipt.

## Environment and preservation boundaries

- `wp_app` mounts website code from `/home/vladimir/wp-website`; core, DB, uploads
  and intake remain under `/home/vladimir/wp`. The mixed/archive checkout and
  external archive storage are not cleanup targets.
- Staging: `/srv/industriesalon/stage/repo`, runtime in its parent, via
  `vladimir@staging.industriesalon.info` (`iss-prod-01`). Last full data/media sync:
  16 October, 13:33 UTC. Preserve subsequent target UAT work.
- Preserve Futura UAT **30743**, original **25657**, draft **30615**, KiezKarte
  **30811** and Burmeister's private draft. Burmeister **328** retains Projects-only
  create/edit/media rights; credentials stay in ignored `var/project-uat-20261016/`.
- Staging remains publicly previewable with noindex by user exception. Default
  WordPress mail is not routed to Mailpit; inspect mail mode before notification
  tests. Maintenance activation and production deployment are separate work.
- Last verified staging backup: `backups/iss-backup-20261017T193729Z/` under the
  stage root (seven verified artifacts, 119 tables, baseline **4239654**). Retain
  the 16 October full-refresh and editorial-audit backups. Previous release
  evidence: `var/gesture-release-20261017/` and `var/landing-preview-20261017/`.
