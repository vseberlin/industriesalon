# Current Handoff

Updated: 2026-06-11

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch: `main`.
- Work is local only; do not push unless explicitly requested.
- Programme/calendar is mid-rebuild around the new first-party `iss-occurrences` plugin.
- `iss-occurrences` owns `wp_iss_occurrences` and `wp_iss_occurrence_series` as the public programme projection for calendar/timeline queries.
- Calendar/timeline rendering stays in `iss-programm` and theme surfaces; graph/search identity stays in `iss-graph`.
- Occurrence schema is now v3 with `entity_id` and `location_entity_id`. Public occurrence rows are graph-linked while `source_post_id` / `source_post_type` remain sync, delete, and editor-routing metadata.
- SuperSaaS Führungen sync through `saas-api/includes/supersaas-sync.php` into occurrence rows. Unlinked/unpublished SuperSaaS slots are deleted from the public projection.
- `/kalender/` starts in month mode, defaults to `Alle`, uses strict public opt-in semantics, and groups recurring Führungen with `Termine anzeigen`.
- Legacy hidden-calendar code has been removed from active runtime paths; the old `iss_calendar_item` CPT/query layer is not an active storage or query layer.
- Staged local commit contains the new occurrence plugin, SuperSaaS sync include, SQL migration artifacts, architecture docs, changelog, and this handoff. Broader programme cleanup files are still unstaged.

## Current Risk

- The worktree remains dirty after the staged commit: many related programme cleanup edits are intentionally still unstaged.
- `plugins/iss-occurrences/` and `plugins/saas-api/includes/supersaas-sync.php` were untracked before staging; ensure they remain included in any programme checkpoint.
- Occurrence drift now depends on graph entity health. If graph entities drift, `wp iss-occurrences drift-check` should fail even if the calendar visually renders.
- Every direct occurrence write path must preserve `entity_id`; the SuperSaaS adapter has been fixed, but future direct service calls need the same discipline.
- Database state was changed locally: occurrence schema v3 installed and graph backfill applied. Staging/production need explicit migration/backfill before relying on graph-linked occurrences.

## Next Action

- Commit the staged local checkpoint only; do not push.
- Continue reviewing/staging the remaining programme cleanup files as a separate checkpoint.
- Before deploy or staging transfer, run `wp iss-occurrences verify`, `wp iss-occurrences drift-check`, and `wp iss-graph drift-check` on the target.
- Apply the `ops/sql/2026-06-11-*` migration artifacts only with the matching code checkpoint and after a database backup.

## Verified

- `php -l` passed for changed `iss-occurrences` files and `saas-api/includes/supersaas-sync.php`.
- PHPCS passed for changed `iss-occurrences` files and `saas-api/includes/supersaas-sync.php`.
- PHPStan passed for changed `iss-occurrences` files and `saas-api/includes/supersaas-sync.php`.
- `wp iss-occurrences verify` reports `public_occurrences=78` and `public_graph_occurrences=78`.
- `wp iss-occurrences drift-check` passed.
- `wp iss-graph drift-check` passed.
- Public active occurrence count check: `78` rows, `78` with `entity_id`, `61` WP rows, `17` SuperSaaS rows.
- SuperSaaS sync reports `created=0 updated=9 unlinked=1 inactivated=26 backfilled=4 errors=0`.
- Playwright verified `/kalender/` still renders the first month rows, groups `Elektropolis` once with two disclosed dates, and has no mobile horizontal overflow.
- `git diff --check` passed before commit.
