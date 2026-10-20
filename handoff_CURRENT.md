# Current handoff — 20 October 2026

## Release in progress

The first [website audit package](docs/project/website-correction-plan-2026-10-20.md)
and programme corrections are locally verified and being delivered through
GitHub `main` to staging. Starting revision: **c3bb60e** in all three checkouts.
Website code: `/home/vladimir/wp-website`; staging:
`/srv/industriesalon/stage/repo`. Do not edit the mixed/archive checkout.

The package covers public working-note removal, full home/team/funder copy,
publication/exhibition selection, shared overflow/map interaction corrections,
separate public/group Elektropolis and Licht offers, reduced-ticket intake,
evergreen Kiez/bus copy, private calendar exclusions and special appointment
labels. Prices/durations follow the user's confirmation and existing published
facts. Behrensbau interiors remain closed. No external appointments changed.

Local verification: 42 audit, 160 isolated commerce, 31 isolated occurrence and
seven booking-JS checks; existing programme-preview/native-booking acceptance,
scoped PHP/CSS/JS checks and browser checks. Real requests and private drafts
were retained. No real form submission or notification was sent.

## Private data and rollback

Verified pre-release staging backup:
`/srv/industriesalon/stage/backups/iss-backup-20261020T120337Z/`
(seven hashed/read-tested artifacts, 119 tables, baseline **c3bb60e**).
All four existing managed backups were retained.

Apply `ops/migrations/2026-10-20-*.php` in this exact order:
`public-copy-cleanup`, `tour-facts`, `reduced-tickets`, `programme-corrections`.
Inputs and target snapshots: `var/corrections-release-20261020/` on each machine;
original local inputs: `var/corrections-20261020/`. The first Elektropolis document
is superseded by the second: verify each immediately after applying. Resolve
identities by slug and normalize environment URLs. Four non-autoload
`iss_backup_20261020_*` receipts hold before-images and draft-preservation checks.
Restore through the documented owner APIs only after comparing later edits.

Code/tests/docs and migration programs belong in GitHub. SQL, content JSON,
source audits, backup archives and uploads stay private. Existing attachment
1496 is reused; no upload transfer, schema change or full DB replacement needed.

## Boundaries and next action

Complete staging migrations and acceptance, then record the final release here.
The remainder of the consolidated audit is separate work; next is visitor
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
