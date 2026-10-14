# Current handoff — 14 October 2026

## Accepted local checkpoint

Place dossier design, optional sections, JSON editing and on-demand epoch
invitations are complete and accepted locally. This checkpoint is local on `main`
in `/home/vladimir/wp-website`; use Git history for implementation details and
`HEAD`/`origin/main` for delivery refs. Nothing in this checkpoint is pushed or
deployed. Other-machine release remains separately authorized.

All 84 existing local Places use the canonical `place` JSON canvas; the enabled
Kino pilot was preserved. Core titles/excerpts/content and old metadata remain
before-images; active facts, contacts, image permissions, relations and imported
milestones keep their owners. The atomic persistence boundary covers JSON and
phase/state projections, not native post fields or earlier graph writes.
Retired icon/color controls and duplicated narrative
controls are hidden without deleting stored data. Empty sections/epochs are valid.
The theme follows the supplied dossier proposal and retains the existing compact
static map with its selected Place marker.

Add **Automatische Inhalte → Wissen zur Epoche beitragen** after the intended
epoch. It uses the nearest preceding epoch in document order; blank gesture title
produces the date-based question, custom title replaces it. No implicit invitation
appears on other epochs. All submissions use the same signed Place context and
single private Set, with epoch topic retained for moderation. Explicit **Feedback
und Upload geöffnet** is still required. Only KAOS 93 was opened locally; its DDR
phase has one chosen gesture. Native moderated Place text feedback is now supported.

Verification: 13,119 registry/renderer checks, 227 isolated Place atomic checks,
86 optional-section checks and 64 contribution checks pass. Storage regression
previously passed 506 checks across 177 documents (CLI-only 2 GB memory allowance
for the large existing-data snapshot). Scoped PHP lint/PHPCS/PHPStan and JS/CSS
checks pass; Event Drop receiver retains its pre-existing PHPCS baseline findings.
Chrome verifies native chooser add/remove, preview source/context, Update/reload,
desktop/390px layout, map marker and correct signed form topic. Representative
Place/landing routes return 200. Disposable fixtures were removed; no real guest
submission was made. Wider browser/staff acceptance remains a release gate.

## Data and media delivery

Deploy matching code/schema before reviewing these guarded owner-API artifacts.
Run `dry-run` before `apply`; preserve later target editorial changes. Do not copy
this local database to staging. The readable JSON manifest contains 83 reviewed
Places; the previously enabled Kino pilot is outside it.

1. `2026-10-14-place-image-preview.php` with
   `ops/uploads/2026-10-14-place-dossier-preview.tar.gz` and its adjacent manifest/
   unpacked artifact directory. This adds a JPEG native large size to the existing
   public TIFF attachment; original TIFF/attribution remain intact. Archive SHA-256:
   `03b4b239c91aa74f8822575bf66a3f948a81d61d7a4ab046aa5c9490e3c41daf`.
2. `2026-10-14-place-research-question-preview.php` authors the reviewed legacy
   KAOS DDR question **before** JSON conversion. It has a targeted before-image.
3. `2026-10-14-place-json.php` plus `2026-10-14-place-documents.json` activate the
   reviewed documents atomically with phase/state projections. Before-images and
   guarded rollback are per Place; milestones and intake choices stay intact.
4. `2026-10-14-place-epoch-gesture.php` adds the one chosen KAOS DDR gesture after
   JSON conversion, retaining its own guarded document before-image. Earlier
   baseline-only verification intentionally rejects this later edited document.

`2026-10-14-place-contributions-preview.php` is a separate explicit opt-in to open
KAOS intake; never infer target opening from a stored gesture or code deployment.
No new uploads accompany JSON/gesture/intake artifacts. See the current contract
and migration detail in `docs/architecture/editorial-platform.md`.

Private ignored recovery lives in `var/place-dossier-20261014/`: 0600 Place
before-images, the preserved unchanged pre-gesture autosave, schema/phase baseline,
and JSON apply/verify receipts. Targeted backup options are non-autoload and
private. Original local uploads/intake persist outside this checkout.

## Runtime and remaining release work

`wp_app` mounts website plugin/theme/receiver code from this checkout;
core/database/uploads/intake remain in `/home/vladimir/wp`. Re-inspect mounts
before testing. Use Compose project `wp` with `/home/vladimir/wp/docker-compose.yml`
and `var/refactoring-20261008/website-code.yml`; private CLI wrapper/receipts remain
under `var/refactoring-20261008/`. The mixed checkout is not a code/cleanup target.

Earlier refactor/calendar/Repair-Café work is locally complete; source/runbooks
and Git history retain its evidence. A release must also review the four existing
owner/maintenance migrations and the paired Schöneweide landing migration from
12 October. Scheduler/retention/scoped server limits remain uninstalled on staging;
last read-only staging inspection was 11 October, not current deployment evidence.
Use `docs/runbooks/staging-maintenance.md` and the active release TODO.

Preserve target DB template overrides, recovery, private contacts/Sets, orders,
moderation and uploads. Local Place template authority is file-backed. The retained
Ausstellung override and earlier archive collection slot need target review.
Staff workflows, Firefox/external Word/Docs paste, macOS Safari, full uploads/intake
restore and actual FPM/Nginx large-upload behavior remain acceptance work. Existing
private refactor restore receipts remain under `var/refactoring-20261008/`; never
restore those snapshots over later editorial work. Other Atlas/TODO work is separate.
