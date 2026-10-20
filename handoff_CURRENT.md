# Current handoff — 20 October 2026

## Audit polish: local verification complete, release in progress

Website checkout: `/home/vladimir/wp-website`; baseline local/GitHub/staging
`56fba9a`. The mixed/archive checkout `/home/vladimir/wp` remains untouched.
The user authorized the remaining copy, cross-links, archive wording/sidebar,
visual consolidation, layout, homepage, periodisation and acceptance package.
**Hero button positions are explicitly excluded and preserved.**

The [correction plan](docs/project/website-correction-plan-2026-10-20.md)
now lists actual scope and retracts the previous blanket completion claim.
Sammlungen and tour cross-links are rewritten; three collection links to a
private women exhibition draft now reach its published counterpart. H1/H2 use
serif; public accents, cards, dividers and spacing are consolidated. Home project
titles/Futura logo and About triptych remain. All three period views use six
matching names/dates without rewriting historical place facts or legacy IDs.
Shared accessibility fixes name image links, focus galleries, correct exhibition
filter ARIA and improve contrast. Archive filters use page scrolling.

## Verification and release requirements

Local: 34 + 42 + 42 audit checks and 27 visitor checks; nine registries;
191 valid canonical documents, 184 existing media references. Chrome and Firefox:
13 routes at 390/768/1348/1720 px without page overflow. No axe WCAG A/AA violations
on those 13 routes. 53 internal destinations/anchors pass. Menu, galleries,
archive filters/empty state and Atlas search/period/fullscreen tested in Firefox.
Newsletter invalid-input/required-consent checks send nothing. About saved editor
preview works at desktop and phone width. Six additional detail pages pass the same Firefox width/axe checks. Targeted
PHP syntax, PHPCS/PHPStan, ESLint/Stylelint and three CSS contracts pass.
Deployment and staging checks are in progress.

Private folder: `var/audit-polish-20261020/` on each machine. Fresh read-tested and
hashed staging backup:
`/srv/industriesalon/stage/backups/iss-backup-20261020T144249Z/`
(119 tables, seven artifacts). Local SQL backup is private and gzip-tested.

Paired migration: `ops/migrations/2026-10-20-audit-polish.php`, applied/verified
locally with `input-local.json`; staging needs `input-stage.json` after code
fast-forward. Exactly four existing landing documents; receipt
`iss_backup_20261020_audit_polish` keeps before-images and private draft hashes.
Existing revisions retained. No schema or upload transfer. **No SQL/content/media
artifacts on GitHub.** Before-images guard against intervening editorial edits.

Next: finish detail acceptance; commit/push code, tests and docs; fast-forward
staging, apply/verify private API input; verify canonical parity, preservation,
public pages and noindex; replace this checkpoint with the release receipt.

## Preserved boundaries and human gates

Keep staging noindex, production untouched, mail routing, core/DB/uploads/intake,
Futura/ZiG private drafts and user 328's Projects-only create/edit/media rights.
Previous programme/admission, room (100 seated, from €400, hours/catering on
request), Repair Café (first/second Wednesday, household items, free, times from
calendar) and KiezKarte pilot terms remain. Kiezfonds exact share is unconfirmed.

Two audited children's photos have source links but no recorded publication
rights; user confirmation/credit requested. Actual 200% browser zoom, physical
touch and a real screenreader pass remain before production. Automated axe and
viewport tests do not replace those gates. No real mail, subscriptions, bookings
or orders sent. Maintenance activation remains separate in `TODO.md`.
