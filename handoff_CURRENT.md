# Current Handoff

Updated: 2026-06-12

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch: `main`; current checkpoint includes the programme frontend-helper refactor, legacy occurrence cleanup guard, and first `iss-core` capability-helper adoption.
- GitHub repo `vseberlin/industriesalon` is private. The old production Newsletter SQL artifact was removed from current `main` and purged from reachable Git history.
- The greenfield refactor checkpoint has passed its first staging validation pass per operator report on 2026-06-12: occurrence projection, Ausstellung availability browser/editor hardening, entity-kind registry, `/wp-json/iss/v1` facade, and the first public facade consumers are committed on `origin/main`.
- `iss-occurrences` owns `wp_iss_occurrences` and `wp_iss_occurrence_series`; `iss-programm` renders calendar/timeline/browser blocks; `saas-api` owns SuperSaaS sync and tour-slot reads; the theme owns public templates/skins.
- `/ausstellungen/` uses the dedicated `industriesalon/ausstellungen-browser` and WP_Query availability filters. Dauer/Digital Ausstellungen are availability-only and do not sync into occurrence rows.
- Local programme/template cleanup artifacts exist and must travel with the matching code if deployed: `ops/sql/2026-06-11-ausstellung-availability-cleanup.sql`, `ops/sql/2026-06-11-strict-programme-toggle-backfill.sql`, `ops/sql/2026-06-12-legacy-occurrence-origin-purge.sql`, `ops/sql/2026-06-12-supersaas-past-occurrence-reactivation.sql`, `ops/sql/2026-06-12-tour-template-collapse.sql`, and `ops/sql/2026-06-12-fuehrung-template-hierarchy-cleanup.sql`.
- `/wp-json/iss/v1` is a read-only facade, not a new storage owner. Active facade routes are contract, entities, entity detail, occurrences, search, timeline, and tour-slots.
- Public consumers already switched to the facade: header search uses `/iss/v1/search`, timeline query uses `/iss/v1/timeline`, and tour slot reads use `/iss/v1/tour-slots`. The old public read routes are retired; booking submissions still use `/is-tours/v1/book`.
- Retired read routes are not registered locally: `/iss-search/v1/search`, `/iss-programm/v1/timeline`, and `/is-tours/v1/slots`.
- `iss-core` remains a scaffold/helper-convention plugin only. `iss-frontend` now provides shared frontend helper functions consumed by `iss-programm` for REST URL generation, dialog attributes, and datepicker registration. Neither plugin owns CPTs, REST routes, renderers, CSS, or domain scripts.

## Current Risk

- Production does not automatically have this checkpoint. Transfer needs code plus the paired SQL/data steps and target-side verification.
- Local and staging DB state changed during the refactor: occurrence schema/backfill/sync, graph backfill, scaffold plugin activation, and Ausstellung availability cleanup.
- The legacy read-route retirement pass must be deployed separately from the staging-proven checkpoint and verified on target before production traffic depends on it.
- The route-retirement code itself has no SQL or uploads artifact; it depends on the existing refactor data artifacts when deployed with the broader checkpoint.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk files are live.
- Führung singles now depend on the native `single-fuehrung.html` block-theme hierarchy. Targets must apply `ops/sql/2026-06-12-fuehrung-template-hierarchy-cleanup.sql` so published Führung posts are not pinned to retired `single-tour` / `single-tour-on-demand` custom-template meta.
- History was rewritten on 2026-06-12. Existing staging/secondary clones must not normal-pull blindly; re-clone or reset only after checking local state.
- `/home/vladimir/industriesalon-export` is a stale local clone of the GitHub repo: its local `main` is behind rewritten `origin/main` and has one old local export commit. It does not contain the purged Newsletter blob, but it should not be used for deploy/push as-is.

## Next Action

- Apply the Führung template hierarchy cleanup SQL on staging/production with the matching code, then run `wp iss-fuehrungen drift-check --limit=25` and spot-check representative Führung singles.
- Deploy the route-retirement checkpoint only after confirming no target-side consumer still calls `/iss-search/v1/search`, `/iss-programm/v1/timeline`, or `/is-tours/v1/slots`.
- For production transfer, take a target DB backup, apply the programme SQL artifacts, run occurrence migrate/sync if needed, then run graph/occurrence verify and drift checks on the target.
- Verify production public consumers after the data step: `/`, `/kalender/`, `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`, and inline REST config for search/timeline/tour-slot reads.

## Verified Locally

- `git fetch origin --prune` on 2026-06-12 showed local `main` was 0 behind / 16 ahead of `origin/main` before the final closeout documentation commit.
- `git diff --check origin/main..HEAD`, PHP syntax over all changed PHP files, `npm run lint:js`, PHPCS over all 41 changed PHP files, and PHPStan over all 41 changed PHP files passed.
- Public-consumer audit found converted reads on facade endpoints. Static remaining old-path consumer is the expected booking write route `/is-tours/v1/book`; retired legacy read routes are no longer registered locally.
- The old production Newsletter SQL transfer artifact was removed from current `main`, then purged from reachable Git history with `git-filter-repo`; a fresh mirror clone no longer exposes that path.
- Local clone audit found only one additional GitHub clone on this machine, `/home/vladimir/industriesalon-export`; the purged Newsletter SQL path/blob is absent from `/home/vladimir/wp`, `/home/vladimir/industriesalon-export`, `/home/vladimir/ISS-mirror`, and `/home/vladimir/strato`.
- `wp iss-graph facade-check --limit=2` passes and checks `/iss/v1/contract`, `/entities`, `/entities/{id}`, `/occurrences`, `/search`, `/timeline`, and `/tour-slots`.
- Facade comparators pass: search, occurrences, entities, timeline, and tour-slots. Tour-slot comparison now checks the service callback against `/iss/v1/tour-slots`.
- First staging validation pass was reported passed by the operator on 2026-06-12; local shell verification was not rerun against staging in this update.
- `wp iss-graph verify`, `wp iss-graph drift-check --limit=25`, `wp iss-occurrences verify`, and `wp iss-occurrences drift-check --limit=25` passed.
- HTTP checks returned `200` for `/`, `/kalender/`, `/fuehrungen/`, and `/veranstaltungen/`.
- `/kalender/` inline config points timeline reads to `/wp-json/iss/v1/timeline`; `/fuehrungen/` points tour-slot reads to `/wp-json/iss/v1/tour-slots` and booking to `/wp-json/is-tours/v1/book`.
- Route-retirement verification on 2026-06-12: REST registry reports retired read routes missing and `/iss/v1/search`, `/iss/v1/timeline`, `/iss/v1/tour-slots`, and `/is-tours/v1/book` registered; public HTTP returns `404` for retired GET routes and `200` for facade GET routes.
- Route-retirement PHP checks passed: syntax on touched PHP files, PHPCS target, PHPStan target, and `git diff --check`.
- Legacy occurrence cleanup guard and programme frontend-helper refactor checks passed locally: PHP syntax, PHPCS target, PHPStan target, `git diff --check`, `wp iss-occurrences drift-check`, and HTTP spot checks for `/kalender/` and `/fuehrungen/` inline REST config.
- Führung template hierarchy checks passed locally: `single-fuehrung` is theme-backed, `single-tour` and `single-tour-on-demand` are no longer block-template sources, published Führung custom-template assignments were deleted, `wp iss-fuehrungen drift-check --limit=25` passes, and representative public/on-demand Führung URLs render through the hierarchy template without empty public-date panels.
