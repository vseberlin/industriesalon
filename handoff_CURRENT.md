# Current handoff — 22 October 2026

Website source is `/home/vladimir/wp-website`; staging checkout is
`/srv/industriesalon/stage/repo`. Implementation `58bfaec` is on GitHub and
deployed to staging. This checkpoint accompanies the completed Place content
migration on both targets. Production is untouched; staging remains noindex.

## Public source cleanup and contribution gestures

The requested follow-up removes 41 repository links from canonical Place
documents, revises source wording in 20 fact fields and seven milestone
summaries, and preserves internal import provenance. Public chronology, Atlas
detail and REST exports omit supplier milestone audit fields. Primary-source
links remain. Milestone dates, identities and internal citations are unchanged.

All 62 published Places with authored open questions now have the existing
upload gesture working: 61 intakes opened, KAOS 93's existing open choice retained.
Other Places and future default behavior are unchanged. The signed receiver
offers text and files; existing moderation/private Sets remain the owners.

Migration: `ops/migrations/2026-10-22-place-contributions.php`; private receipt:
`iss_backup_20261022_place_contributions`. Inputs, snapshots and verification
are in `var/place-contributions-20261022/` on both checkouts; start with
`completion.json` and `site-parity.json`. Fresh checked DB backups:
local `local-before.sql.gz` (118 tables), staging `staging-before.sql.gz`
(119 tables) in the respective review directory. The full staging backup below
also remains retained. No uploads or schema artifact is required.
Rollback uses exact receipt fields through owners after checking newer edits;
close only intake opened here and never restore old signed-link generations.

## Earlier supplier review retained

The preceding batch reviewed all 69 supplier records against 84 Places and
filled 36 present-day descriptions/four fact fields. Its private evidence stays
in `var/register-review-20261022/`; the later public-source cleanup above
supersedes its displayed citations. Upstream revision remains
`8574160c4ae79a6101dad59d21c9817ab29d0814` (11 September).
The [canonical review procedure](docs/architecture/places-editorial-atlas-restructure-plan.md#reviewing-omissions-in-enabled-place-documents)
requires fresh source/target review for later batches.

All 84 documents, reviewed facts, milestones and intake states match across
targets after URL normalization. Histories, media, relations, 17 local-only
Places, the ADMOS draft and upstream #70 → #13 component mapping are preserved.
The follow-up retained 3,619 local / 3,613 staging revisions, target users,
authentication and capabilities. KAOS address/ownership-role conflicts,
Energie-Museum access/destination and unconfirmed project dates remain qualified.

The verified full staging backup remains at
`/srv/industriesalon/stage/backups/iss-backup-20261022T094828Z/`
(119 tables, all seven artifacts verified). Never restore historical credentials
or unrelated target state from that backup.

## Verification

Both targets pass canonical validation (191 documents, zero invalid), all 62
changed Place epoch-parity checks, register contract/state checks and Atlas
payload budgets (58,654 bytes; 9,562 gzip). Idempotent replay and final receipt
verification pass after background projection work. Preservation recognizes
existing derived relation rows normalized by their owner; source relation
metadata, endpoints, roles, dates, weights and visibility remain protected.

All 83 published Place pages return 200 on both hosts, without public supplier
repository references. All 62 questions sections have the signed upload action.
Public export/Atlas collections and three representative signed receiver pages
per host pass. No files or comments were submitted. Chrome follows the No. 92
button into its text/file form on both hosts without desktop overflow. Prior
desktop/mobile checks remain unchanged; no CSS or template edit was needed.
PHP lint, PHPCS and PHPStan pass. The local transactional suite passes 35
assertions, including stale-edit rejection and preservation of milestone
provenance; all fixture changes rolled back. Its outdated flat-chronology and
legacy-current-text expectations now follow the existing canonical contracts.

## Preserved operational constraints and remaining gates

Target accounts remain target-owned; the unrestricted staging SQL importer is
disabled. Follow [content sync](docs/infrastructure/sync.md). The retired testing
credential and old authentication backups must never be replayed. Prior private
incident evidence remains retained; no further incident work is queued.
Project UAT retains its existing picker permissions; no permission changes here.

Staging/touchtable PHP 8.3.36 emergency images remain active. Their separately
scoped next maintenance is replacement with a verified patched official image,
following [the runtime procedure](docs/infrastructure/staging.md#php-runtime-security-update).
No runtime replacement was performed during this content review.

The requested local/staging update is complete. Existing human gates remain:
Kiezfonds exact share, children's-photo rights, actual 200% zoom, physical touch
and screenreader acceptance. Production/maintenance activation is separate.
