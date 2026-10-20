# Current handoff — 20 October 2026

## Requested audit polish deployed

Implementation **23642a2** is committed, pushed to GitHub main and fast-forwarded
to staging. This documentation receipt follows the same exchange path.
Website: `/home/vladimir/wp-website`; staging: `/srv/industriesalon/stage/repo`.
The mixed/archive checkout `/home/vladimir/wp` is untouched.

Completed the requested Sammlungen copy, tour cross-links, wording, archive
terminology/sidebar, shared visual consolidation, layout, homepage details and
six-period alignment. **Hero button positions are preserved.** Home project
titles/Futura logo and About triptych remain. Three broken collection links now
reach the published women exhibition, preserving the old private draft.
Shared fixes cover image-link names, gallery keyboard access, filter ARIA,
text contrast, card spacing and mobile overflow. The
[correction plan](docs/project/website-correction-plan-2026-10-20.md)
records actual scope and retracts the earlier blanket completion claim.

## Verification

Local and staging: **145** audit/visitor checks (34 + 42 + 42 + 27), nine registries,
191 valid canonical documents and 184 existing media references. Targeted PHP
syntax, PHPCS/PHPStan, ESLint/Stylelint and three CSS contracts pass.

Chrome locally: 13 main routes at 390/768/1348/1720 px, without overflow or broken
loaded images. Firefox locally and on staging: those 13 routes plus six detail
pages at all four widths, no page overflow and no axe WCAG A/AA findings.
Staging Chrome: seven changed main routes at phone/desktop widths, noindex intact.
53 internal destinations/anchors pass on both sites; one staging request timed
out during the batch and returned HTTP 200 in 0.45 s on targeted retry.

Firefox functional checks pass locally and on staging: menu Tab/Escape and focus
return, both gallery controls, archive search/empty result/filter/reset/final
mobile control, Atlas search with period and full-screen return. Newsletter
invalid-address/required-consent checks send nothing. About saved editor preview
was verified locally at desktop/phone width, without publishing or changing drafts.
Actual 200% zoom, physical touch and real screenreader checks remain human gates.

## Private content release and preservation

Private evidence/inputs: `var/audit-polish-20261020/` on both machines.
Read-tested/hashed staging backup:
`/srv/industriesalon/stage/backups/iss-backup-20261020T144249Z/`
(119 tables, seven artifacts). Local private SQL backup is gzip-tested.

`ops/migrations/2026-10-20-audit-polish.php` applied and verified on both systems
with site-specific private input. Exactly four existing landing documents:
Home, Führungen, Schöneweide, About. Receipt `iss_backup_20261020_audit_polish`
contains before-images and private draft hashes. Restore through the same owner
API only after checking for later editorial edits. No schema or upload transfer,
no full DB replacement. **No SQL/content/media artifacts entered GitHub.**

All 191 canonical documents match after URL normalization. Exactly four existing
page metadata groups changed; four native revisions added. All prior posts and
revisions remain. Private drafts, user 328's Projects-only capabilities and booking
requests are unchanged. Both place projections match exactly (193 epochs and
269 states). Home/About template authority and project image hashes match.
Containers are healthy; no failed services or recent app/database errors.

## Remaining human/factual gates before production

Keep staging noindex and production untouched. No real messages, newsletter
subscriptions, bookings or orders sent. Earlier confirmed tour/admission, rental,
Repair-Café and KiezKarte pilot terms are preserved. Kiezfonds exact share remains
unconfirmed; retain the approved published wording.

Publication rights/credits for the two audited children's photos remain requested
from the user: source links exist but rights fields are empty. No rights invented.
Complete actual 200% browser zoom, physical touch and screenreader acceptance
before production. The current browser control did not change real zoom when
sent zoom keys; viewport tests are not a substitute. Maintenance activation
remains separate in `TODO.md`.
