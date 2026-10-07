# Current handoff — 2026-10-07

## Tomorrow start here

Continue website work in `/home/vladimir/wp-website` on `main`. The user asked
to retain the refactoring plan for tomorrow and stop; implementation has not
started. Read:

- [WordPress refactoring plan](docs/project/wordpress-refactoring-plan.md)
- [Native calendar and intake migration](docs/project/universal-occurrence-calendar-intake-plan.md#native-wordpress-frontend-migration)

First establish the correct local development mounts and record the query/UI
baseline. Then implement version-gated taxonomy seeding as the first small
change. Follow with atomic writes/queue locking, native frontend migration,
query consolidation, code/CSS cleanup and predictable scheduled maintenance.
Run changes and stateful verification sequentially.

The requested direction is PHP, JavaScript and CSS using native WordPress APIs,
with no additional application framework or parallel implementation. Remove
Flatpickr through native controls and the Interactivity API while preserving
the existing occurrence, editorial and commerce owners. Native date/slot
presentation and pagination changes in the plan still need staff acceptance.
The ambiguous jQuery wording was not clarified: the plan audits first-party
jQuery separately and does not remove WordPress admin dependencies globally.

## Runtime and exchange boundary

- The running website/reference target is
  `https://staging.industriesalon.info`. The 7 October SSH inspection verified
  `vladimir@iss-prod-01`, repository `/srv/industriesalon/stage/repo`, and runtime
  `/srv/industriesalon/stage/compose.yml`. Use the saved key with strict host
  verification. Staging was inspected only.
- Before this documentation closeout, local website source, GitHub main and
  staging were all at `6c8cea7`. The closeout commit contains documentation
  only; the deployed staging code remains `6c8cea7` until a later deliberate
  deployment. Check current refs on resume.
- Staging runs WordPress 7.0, PHP 8.3.31 and MariaDB 11.8.8. Core Interactivity
  modules are present. Local exposure/PHP findings are development housekeeping,
  not refactoring prerequisites.
- **Local mount warning:** fresh Docker inspection found plugins, theme and
  receiver served from the preserved `/home/vladimir/wp` checkout. This
  supersedes the earlier handoff's claim that the website-code override is
  active. Inspect the existing override/wrapper under
  `~/.local/state/iss-editorial-pilot-20260921/` before choosing a controlled
  local mount correction. Do not blindly restart base Compose.
- Preserve the mixed archive checkout and its dirty work. Do not merge it into
  website main, copy its old code over staging, or replace either site's database
  with the other. Source identity, not numeric post ID, governs transfers.

## Evidence and preservation

The local diagnostic calendar dropped from 342 to 136 queries when seed
callbacks were skipped in that process only. Staging's matching seed code needs
the same bounded review; this is not a measured staging speed improvement.
Staging has 66 pending graph entries, not the local 462. All 118 staging tables
are InnoDB; sampled orphan metadata/graph-edge checks returned zero. Four public
staging routes returned HTTP 200 and retain noindex/nofollow.

Keep the deployed event presentation, automatic date rotation, shared JSON
workspace/recovery, moderated feedback and rights-controlled private Sets.
Source dates, historical chronology, private contacts, captions, relationships,
uploads and preservation records remain under their existing owners.
Keep checking DB template overrides for `single-ausstellung`,
`page-projekte` and `page-publikationen`.

Existing imports and migrations are already applied. Do not replay apply to
verify them. Detailed prior delivery/receipt context remains in
`git show 6c8cea7:handoff_CURRENT.md`, canonical architecture docs and changelog.
The prior handoff records these private evidence locations; verify their host
and existence before use:

- `/home/vladimir/server-actions/events-sync-20260927/private/`
- `/home/vladimir/server-actions/atlas-sync-20260927/private/`
- `/home/vladimir/server-actions/programme-20260922/`
- `/srv/industriesalon/stage/backups/20260922-132743/`

These are prior receipts, not a fresh backup for tomorrow's mutations.
Local/staging post IDs differ; for example, staging 27415 is Denkmaltag while
local 27415 is unrelated. Preserve useful template edits and later editorial
work during any scoped migration/rollback.

## Verification and delivery

Planning links and Git whitespace checks passed. No source implementation,
database migration, uploads artifact, runtime switch, restart or deployment was
performed. This documentation closeout needs no SQL or uploads artifacts.
Existing Firefox/authenticated-editor/staff UAT remains listed in `TODO.md`;
do not turn prior automated checks into new visual acceptance.

Use the plan's per-phase verification and rollback gates. Fresh staging status,
logs and backup verification precede any later staging mutation. Deliver code
through the website checkout, GitHub main and a coordinated staging release.
