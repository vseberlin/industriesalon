# Current handoff — 22 October 2026

Website source is `/home/vladimir/wp-website`; staging checkout is
`/srv/industriesalon/stage/repo`. Implementation `929f814` is on GitHub and
deployed to staging. This checkpoint accompanies the completed Place content
migration on both targets. Production is untouched; staging remains noindex.

## Atlas and Orte supplier review

Reviewed all 69 supplier records at upstream revision `8574160c4ae79a6101dad59d21c9817ab29d0814`
(11 September) against 84 local/staging Places. Updated 36 canonical Place
documents and four metadata fields through the editorial owner. The source
revision was unchanged since the previous selective import; this batch fills
omissions and qualifies stale claims, including Wilhelminenhofstraße 92.
The Atlas reads the updated canonical present-day text.

All 84 documents and selected metadata match across targets after URL
normalization. Preserved histories, historical projections, media, relationships,
17 local-only Places, the ADMOS draft, and the upstream #70 → #13 Reinbeckhallen
component mapping. Target users, authentication, capabilities and site settings
are unchanged. Existing revisions preserved: 3,583 local / 3,577 staging.

Canonical procedure and limitations:
[Place supplier review](docs/architecture/places-editorial-atlas-restructure-plan.md#reviewing-omissions-in-enabled-place-documents).
Migration: `ops/migrations/2026-10-22-register-review.php`;
private receipt option: `iss_backup_20261022_register_review`.
New batches require a fresh source/target review. Never blindly replay this
manifest against later editorial changes or import a full database.

Private comparison, before-images, manifests and evidence are in
`var/register-review-20261022/` on both checkouts; start with `review.html`,
`review.json` and `completion.json`. The report records unresolved KAOS
address/ownership-role conflicts, Energie-Museum access/destination questions,
and unconfirmed project dates. No inferred map moves or graph identities.
No uploads or schema change was required; content payloads stay outside GitHub.

Verified backups: local `var/register-review-20261022/local-before.sql.gz`
(118 tables, checksum in `local-backup.json`); staging
`/srv/industriesalon/stage/backups/iss-backup-20261022T094828Z/`
(119 tables, all seven artifacts verified). A checked post-update DB snapshot
also remains in staging's private review directory. Rollback must use the
receipt's exact fields/documents through owner APIs after reviewing newer edits;
never restore historical credentials or unrelated target state.

## Verification

Both targets pass canonical validation (191 documents, zero invalid), all 36
changed Place epoch-parity checks, register contract/state checks and Atlas
payload budgets (58,654 bytes; 9,562 gzip). Idempotent replay and final receipt
verification pass after background projection work. Preservation recognizes
eight existing derived relation rows normalized by their owner; source relation
metadata, endpoints, roles, dates, weights and visibility remain protected.

All 83 published Place pages return 200 on both hosts. Updated prose/source
links and both public read models pass for all 36 changes. Local checks use
individual detail endpoints; staging uses public export/Atlas collections after
the initial detail sweep reached its API rate limit. No rate controls changed.
Home, Schöneweide and Atlas bootstrap also pass. Chrome checks cover the No. 92
and Katapult pages at desktop/mobile widths without horizontal overflow.
PHP lint, PHPCS and PHPStan pass for both changed PHP files. Staging services
are healthy and recent WordPress logs contain no fatal/parse errors.

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
