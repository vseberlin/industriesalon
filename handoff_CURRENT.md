# Current Handoff

Updated: 2026-06-12

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch: `main`; local `HEAD` and `origin/main` are aligned at `3dcbf5b` after the greenfield refactor checkpoint and post-purge documentation.
- GitHub repo `vseberlin/industriesalon` is private. The old production Newsletter SQL artifact was removed from current `main` and purged from reachable Git history.
- The greenfield refactor checkpoint has passed its first staging validation pass per operator report on 2026-06-12: occurrence projection, Ausstellung availability browser/editor hardening, entity-kind registry, `/wp-json/iss/v1` facade, and the first public facade consumers are committed on `origin/main`.
- `iss-occurrences` owns `wp_iss_occurrences` and `wp_iss_occurrence_series`; `iss-programm` renders calendar/timeline/browser blocks; `saas-api` owns SuperSaaS sync and tour-slot reads; the theme owns public templates/skins.
- `/ausstellungen/` uses the dedicated `industriesalon/ausstellungen-browser` and WP_Query availability filters. Dauer/Digital Ausstellungen are availability-only and do not sync into occurrence rows.
- Local Ausstellung cleanup artifacts exist and must travel with the matching code if deployed: `ops/sql/2026-06-11-ausstellung-availability-cleanup.sql` and `ops/sql/2026-06-11-strict-programme-toggle-backfill.sql`.
- `/wp-json/iss/v1` is a read-only facade, not a new storage owner. Active facade routes are contract, entities, entity detail, occurrences, search, timeline, and tour-slots.
- Public consumers already switched to the facade: header search uses `/iss/v1/search`, timeline query uses `/iss/v1/timeline`, and tour slot reads use `/iss/v1/tour-slots`. Legacy read routes remain active; booking submissions still use `/is-tours/v1/book`.
- `iss-core` and `iss-frontend` remain scaffolds/helper-convention plugins only. They do not own CPTs, REST routes, renderers, CSS, or domain scripts.

## Current Risk

- Production does not automatically have this checkpoint. Transfer needs code plus the paired SQL/data steps and target-side verification.
- Local and staging DB state changed during the refactor: occurrence schema/backfill/sync, graph backfill, scaffold plugin activation, and Ausstellung availability cleanup.
- Do not remove legacy read routes in the same checkpoint that proved staging. Keep old-vs-new comparators until the separate route-retirement pass has its own verification.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk files are live.
- History was rewritten on 2026-06-12. Existing staging/secondary clones must not normal-pull blindly; re-clone or reset only after checking local state.
- `/home/vladimir/industriesalon-export` is a stale local clone of the GitHub repo: its local `main` is behind rewritten `origin/main` and has one old local export commit. It does not contain the purged Newsletter blob, but it should not be used for deploy/push as-is.

## Next Action

- Keep the refactor checkpoint complete and stable through production planning; do not bundle more architecture movement into the same pass.
- Plan the next code slice as a separate route-retirement checkpoint: audit remaining static/runtime consumers, remove only proven-unused legacy read routes, keep `/is-tours/v1/book`, and preserve comparator coverage until after removal verification.
- For production transfer, take a target DB backup, apply the two SQL artifacts, run occurrence migrate/sync if needed, then run graph/occurrence verify and drift checks on the target.
- Verify production public consumers after the data step: `/`, `/kalender/`, `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`, and inline REST config for search/timeline/tour-slot reads.

## Verified Locally

- `git fetch origin --prune` on 2026-06-12 showed local `main` was 0 behind / 16 ahead of `origin/main` before the final closeout documentation commit.
- `git diff --check origin/main..HEAD`, PHP syntax over all changed PHP files, `npm run lint:js`, PHPCS over all 41 changed PHP files, and PHPStan over all 41 changed PHP files passed.
- Public-consumer audit found converted reads on facade endpoints. Static remaining old-path consumer is the expected booking write route `/is-tours/v1/book`; legacy read routes remain active for compatibility and comparator rollback.
- The old production Newsletter SQL transfer artifact was removed from current `main`, then purged from reachable Git history with `git-filter-repo`; a fresh mirror clone no longer exposes that path.
- Local clone audit found only one additional GitHub clone on this machine, `/home/vladimir/industriesalon-export`; the purged Newsletter SQL path/blob is absent from `/home/vladimir/wp`, `/home/vladimir/industriesalon-export`, `/home/vladimir/ISS-mirror`, and `/home/vladimir/strato`.
- `wp iss-graph facade-check --limit=2` passes and checks `/iss/v1/contract`, `/entities`, `/entities/{id}`, `/occurrences`, `/search`, `/timeline`, and `/tour-slots`.
- Facade comparators pass: search, occurrences, entities, timeline, and tour-slots. `ELEKTRO` tour slots matched legacy `/is-tours/v1/slots` with `source=occurrences` and 3 slots.
- First staging validation pass was reported passed by the operator on 2026-06-12; local shell verification was not rerun against staging in this update.
- `wp iss-graph verify`, `wp iss-graph drift-check --limit=25`, `wp iss-occurrences verify`, and `wp iss-occurrences drift-check --limit=25` passed.
- HTTP checks returned `200` for `/`, `/kalender/`, `/fuehrungen/`, and `/veranstaltungen/`.
- `/kalender/` inline config points timeline reads to `/wp-json/iss/v1/timeline`; `/fuehrungen/` points tour-slot reads to `/wp-json/iss/v1/tour-slots` and booking to `/wp-json/is-tours/v1/book`.
