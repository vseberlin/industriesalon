# Current Handoff

Updated: 2026-06-13

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Branch `main` matches `origin/main` at `e4e2f19` after the occurrence/programme cleanup push.
- Staging is the current live working target, not a production release gate. Rebuild from Git plus explicit SQL/upload artifacts if it breaks.
- Domain ownership is current: `iss-content` owns CPT/editor contracts and tour data; `iss-occurrences` owns occurrence projection, SuperSaaS ingestion, tour-slot reads, and sync admin; `iss-frontend` owns programme/timeline/browser rendering; `iss-commerce-lite` owns booking/order request intake; `iss-archive` owns archive runtime; `iss-graph` owns graph/search/facade contracts; the theme owns public templates and skins.
- Occurrence rows are source-post keyed only. Open-ended rows use `ends_at = NULL` plus `is_open_ended = 1`; graph IDs and `2099-12-31` sentinels are invalid drift.
- Programme projection uses `iss_programme_enabled`; Ausstellung overview visibility uses `iss_public_overview_enabled`. Dauer/Digital Ausstellungen can remain in overviews without programme occurrences unless editors explicitly opt them in.
- `/wp-json/iss/v1` is the read-only facade boundary for contract, entities, relations, occurrence reads, search, timeline, availability, and tour slots. Booking writes stay on `/is-tours/v1/book`.
- Commerce-lite request rows remain in `wp_iss_payments_lite_requests` for compatibility. SuperSaaS settings use `iss_supersaas_*`; retired `is_saas_settings` is migrated and drift-guarded.
- No SQL/uploads transfer artifact is required for the latest pushed cleanup. The relevant data cleanup runs through plugin schema/runtime migrations and drift checks.

## Current Risk

- Request notification mail is implemented but disabled by default. Enable only after target mail mode and recipient are approved.
- First deploy to a database with old plugin basenames relies on the `iss-core` active-plugin migrator; verify `wp plugin list`.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk templates are live.
- History was rewritten on 2026-06-12. Existing secondary clones should be re-cloned or reset deliberately.
- `/home/vladimir/industriesalon-export` is stale and should not be used for deploy/push.

## Next Action

- UI polish later, especially Ausstellung search/filter interaction and public view polish.
- Before production deploy, verify target mail mode and decide whether request notification email should be enabled.

## Last Verified

- Latest pushed cleanup passed: PHP syntax, JS lint, PHPCS target, PHPStan target, programme visibility migration probe, SuperSaaS settings migration probe, `wp iss-occurrences verify`, `wp iss-occurrences sync --source=wp`, `wp iss-occurrences drift-check --limit=25`, `wp iss-content tours-drift-check --limit=25`, `wp iss-frontend ausstellungen-audit --strict`, graph occurrence/entity-occurrence/tour-slot/availability facade compares, default graph drift, `wp iss-commerce-lite verify`, active plugin list, and `git diff --check`.
