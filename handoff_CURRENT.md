# Current Handoff

Updated: 2026-06-12

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch: `main`; this handoff is the GitHub exchange closeout for the greenfield refactor checkpoint. Last runtime implementation checkpoint is `8563410 Switch tour slots to facade`; the final push also includes closeout documentation.
- The greenfield refactor checkpoint is ready for staging review: occurrence projection, Ausstellung availability browser/editor hardening, entity-kind registry, `/wp-json/iss/v1` facade, and the first public facade consumers are committed for `origin/main`.
- `iss-occurrences` owns `wp_iss_occurrences` and `wp_iss_occurrence_series`; `iss-programm` renders calendar/timeline/browser blocks; `saas-api` owns SuperSaaS sync and tour-slot reads; the theme owns public templates/skins.
- `/ausstellungen/` uses the dedicated `industriesalon/ausstellungen-browser` and WP_Query availability filters. Dauer/Digital Ausstellungen are availability-only and do not sync into occurrence rows.
- Local Ausstellung cleanup artifacts exist and must travel with the matching code if deployed: `ops/sql/2026-06-11-ausstellung-availability-cleanup.sql` and `ops/sql/2026-06-11-strict-programme-toggle-backfill.sql`.
- `/wp-json/iss/v1` is a read-only facade, not a new storage owner. Active facade routes are contract, entities, entity detail, occurrences, search, timeline, and tour-slots.
- Public consumers already switched to the facade: header search uses `/iss/v1/search`, timeline query uses `/iss/v1/timeline`, and tour slot reads use `/iss/v1/tour-slots`. Legacy read routes remain active; booking submissions still use `/is-tours/v1/book`.
- `iss-core` and `iss-frontend` remain scaffolds/helper-convention plugins only. They do not own CPTs, REST routes, renderers, CSS, or domain scripts.

## Current Risk

- Staging/production do not automatically have this local checkpoint. Transfer needs code plus the paired SQL/data steps and target-side verification.
- Local DB state changed during the refactor: occurrence schema/backfill/sync, graph backfill, scaffold plugin activation, and Ausstellung availability cleanup.
- Do not remove legacy read routes yet. Keep old-vs-new comparators until staging has run the same checks and public UI is stable.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk files are live.

## Next Action

- On staging, pull `origin/main` only after confirming the staging tree is clean and `origin/main` is the expected exchange point.
- Prepare staging transfer as code plus data: take a target DB backup, apply the two SQL artifacts, run occurrence migrate/sync if needed, then run graph/occurrence verify and drift checks on the target.
- Verify staging public consumers after the data step: `/`, `/kalender/`, `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`, and inline REST config for search/timeline/tour-slot reads.
- After staging passes, mark the refactor checkpoint complete in `refactor.md`/`handoff_CURRENT.md`; only then consider retiring old read routes in a separate cleanup commit.

## Verified Locally

- `git fetch origin --prune` on 2026-06-12 showed local `main` was 0 behind / 16 ahead of `origin/main` before the final closeout documentation commit.
- `git diff --check origin/main..HEAD`, PHP syntax over all changed PHP files, `npm run lint:js`, PHPCS over all 41 changed PHP files, and PHPStan over all 41 changed PHP files passed.
- Public-consumer audit found converted reads on facade endpoints. Static remaining old-path consumer is the expected booking write route `/is-tours/v1/book`; legacy read routes remain active for compatibility and comparator rollback.
- `wp iss-graph facade-check --limit=2` passes and checks `/iss/v1/contract`, `/entities`, `/entities/{id}`, `/occurrences`, `/search`, `/timeline`, and `/tour-slots`.
- Facade comparators pass: search, occurrences, entities, timeline, and tour-slots. `ELEKTRO` tour slots matched legacy `/is-tours/v1/slots` with `source=occurrences` and 3 slots.
- `wp iss-graph verify`, `wp iss-graph drift-check --limit=25`, `wp iss-occurrences verify`, and `wp iss-occurrences drift-check --limit=25` passed.
- HTTP checks returned `200` for `/`, `/kalender/`, `/fuehrungen/`, and `/veranstaltungen/`.
- `/kalender/` inline config points timeline reads to `/wp-json/iss/v1/timeline`; `/fuehrungen/` points tour-slot reads to `/wp-json/iss/v1/tour-slots` and booking to `/wp-json/is-tours/v1/book`.
