# Current handoff — 20 October 2026

## Released and verified

The first [website audit package](docs/project/website-correction-plan-2026-10-20.md)
and programme corrections are deployed from local through GitHub `main` to
staging: implementation **0eccd4e**, series-label follow-up **805ad9f**. This
checkpoint follows the same synchronization path. Previous baseline: **c3bb60e**.
Website code: `/home/vladimir/wp-website`; staging:
`/srv/industriesalon/stage/repo`. Do not edit the mixed/archive checkout.

The package covers public working-note removal, full home/team/funder copy,
publication/exhibition selection, shared overflow/map interaction corrections,
separate public/group Elektropolis and Licht offers, reduced-ticket intake,
evergreen Kiez/bus copy, private calendar exclusions and special appointment
labels. Prices/durations follow the user's confirmation and existing published
facts. Behrensbau interiors remain closed. No external appointments changed.

Verification: 42 audit, 26 programme-preview and 26 native-booking checks pass
locally and on staging. Local isolated tests: 160 commerce, 31 occurrence and
seven booking-JS checks; PHP lint/PHPCS/PHPStan, CSS/JS lint and CSS contracts pass.
All **191 canonical documents match and validate**, nine registries and 185 media
references pass. Staging: 14 public routes return 200/noindex, TXL returns 404;
no release PHP errors or failed services. Six pages fit at 390/1440 px. Browser
checks cover mixed/reduced-only totals, zero/capacity guards, group inquiry,
long mobile headings and the retained About triptych. No real request submitted.

Only 13 scoped existing posts and seven metadata groups changed. One group offer
(staging 31385 / local 42227) and 20 native revisions were created. Existing
requests, revisions, private drafts, unrelated post/meta rows and Place
projections are preserved. The normal calendar refresh retained the exclusions;
raw capacities and upstream appointments were unchanged. Its already-started
special-slot cache expired normally; the occurrence and before-image remain.

## Private data and rollback

Verified pre-release staging backup:
`/srv/industriesalon/stage/backups/iss-backup-20261020T120337Z/`
(seven hashed/read-tested artifacts, 119 tables, baseline **c3bb60e**).
All four existing managed backups were retained.

Applied and verified `ops/migrations/2026-10-20-*.php` in this order:
`public-copy-cleanup`, `tour-facts`, `reduced-tickets`, `programme-corrections`.
Inputs and target snapshots: `var/corrections-release-20261020/` on each machine;
original local inputs: `var/corrections-20261020/`. The first Elektropolis document
is superseded by the second: verify each immediately after applying. Resolve
identities by slug and normalize environment URLs. Four non-autoload
`iss_backup_20261020_*` receipts hold before-images and draft-preservation checks.
A private `restore-series-title.php` follow-up restored the matched bus-series
label after the first refresh exposed its coupling to appointment labels;
`iss_backup_20261020_programme_series_title` retains that before-image. The
follow-up code prevents recurrence. Restore through owner APIs only after
comparing later edits. Detailed evidence: private `receipt.json`,
`comparison.json`, `http.json`, `browser-widths.json` and release logs.

Code/tests/docs and migration programs belong in GitHub. SQL, content JSON,
source audits, backup archives and uploads stay private. Existing attachment
1496 is reused; no upload transfer, schema change or full DB replacement needed.

## Boundaries and next action

This release is complete. The remainder of the consolidated audit is separate
work; next is visitor
information. Human editor/Firefox acceptance remains in `TODO.md`.

Previous home project titles/Futura logo, About triptych, project/report imports
and saved landing documents remain delivered (implementation **172340e**, prior
receipt **c3bb60e**; private evidence `var/release-20261020/`).
Preserve Futura 30743, original 25657, draft 30615, KiezKarte 30811 and Burmeister's
private drafts. ZiG and new content IDs differ across environments; use slugs.
User 328 retains Projects-only create/edit/media rights.

`wp_app` mounts website code; core/DB/uploads/intake remain in `/home/vladimir/wp`.
Staging remains publicly previewable with noindex. Preserve credentials, URLs,
mail routing and infrastructure settings. Production deployment and maintenance
activation remain separate work.
