# Current Handoff

Updated: 2026-06-15

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Local `main` is based on `origin/main` at `537a6b7`; the working tree is intentionally dirty with the static-map implementation slice and earlier static-map planning/doc changes.
- Staging is the current live working target, not a production release gate. Rebuild from Git plus explicit SQL/upload artifacts if it breaks.
- Domain ownership is current: `iss-content` owns CPT/editor contracts and tour data; `iss-occurrences` owns occurrence projection, SuperSaaS ingestion, tour-slot reads, and sync admin; `iss-frontend` owns programme/timeline/browser rendering plus reusable frontend editorial blocks such as `iss/dense-image-wall`; `iss-commerce-lite` owns booking/order request intake; `iss-archive` owns archive runtime; `iss-graph` owns graph/search/facade contracts; the theme owns public templates and skins.
- Static map ownership is now explicit and partly implemented: `iss-relations` owns map-block source/place-selection contracts, `iss-frontend/modules/static-maps` owns marker lookup, projection/focus math, static stage/panel rendering, and static atlas/map frontend renderers, `industriesalon-schoeneweide-register` owns `register_place` and interactive Atlas data/cache contracts, and the theme owns map assets/presets/skins.
- The merged Atlas/static-map rewrite plan is in `docs/architecture/atlas-static-map-implementation-plan.md`; use it as the source for the next implementation slice.
- Occurrence rows are source-post keyed only. Open-ended rows use `ends_at = NULL` plus `is_open_ended = 1`; graph IDs and `2099-12-31` sentinels are invalid drift.
- Programme projection uses `iss_programme_enabled`; Ausstellung overview visibility uses `iss_public_overview_enabled`. Dauer/Digital Ausstellungen can remain in overviews without programme occurrences unless editors explicitly opt them in.
- `/wp-json/iss/v1` is the read-only facade boundary for contract, entities, relations, occurrence reads, search, timeline, availability, and tour slots. Booking writes stay on `/is-tours/v1/book`.
- Commerce-lite request rows remain in `wp_iss_payments_lite_requests` for compatibility. SuperSaaS settings use `iss_supersaas_*`; retired `is_saas_settings` is migrated and drift-guarded.
- Walk of Fame dense-wall content needs paired transfer artifacts if applied elsewhere: `ops/sql/2026-06-14-walk-of-fame-dense-wall.sql` plus `ops/uploads/2026-06-14-walk-of-fame-dense-wall-media.tar.gz`.
- Current published `projekt` single-page content edits need the all-project transfer unit if applied elsewhere: `ops/sql/2026-06-14-project-content-sync.sql` plus `ops/uploads/2026-06-14-project-content-media.tar.gz`, manifest, and SHA256. The SQL covers all seven published project posts, postmeta, and term relationships; directly referenced media files are packaged in the paired archive.

## Current Risk

- Request notification mail is implemented but disabled by default. Enable only after target mail mode and recipient are approved.
- First deploy to a database with old plugin basenames relies on the `iss-core` active-plugin migrator; verify `wp plugin list`.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk templates are live.
- Front-page remains DB-backed in local state, but `iss/spine-strip` no longer depends on saved `source` when `placeIds` are present because the map-block contract resolves that as `manual`.
- Static marker JSON now covers published coordinate-bearing `register_place` posts in the local audit, including derived markers added for Waldfriedhof entries, IRIS, Innovationspark Wuhlheide, Energie-Museum Berlin, and Spree 27. The marker file still needs provenance: generator or manual update runbook.
- `page-projekte` currently remains DB-backed (`custom`) after being flushed to `themes/industriesalon/templates/page-projekte.html`; delete that override only after the disk template is verified in the target flow.
- History was rewritten on 2026-06-12. Existing secondary clones should be re-cloned or reset deliberately.
- `/home/vladimir/industriesalon-export` is stale and should not be used for deploy/push.

## Next Action

- Delete the `page-projekte` DB template override after verifying the flushed disk template on the target.
- Continue static-map cleanup from `docs/architecture/atlas-static-map-implementation-plan.md`: freeze public block names, decide whether `iss/atlas-strip` and `iss/asymmetric-split-field` are removed or hidden as experimental, split focused editor JS controls, then add marker provenance.
- UI polish later, especially Ausstellung search/filter interaction and public view polish.
- Before production deploy, verify target mail mode and decide whether request notification email should be enabled.

## Last Verified

- Latest local checkpoint passed focused JS/PHP syntax, PHPCS target, `git diff --check`, WP-CLI block registry/render checks for `iss/dense-image-wall`, route checks for `/projekte/`, front page, and Walk of Fame, plus SQL/upload artifact inspection.
- Static-map implementation slice passed PHP syntax, targeted PHPCS, targeted PHPStan, `node --check` for the related-content block editor script, `wp iss-relations map-block-audit` in table and JSON modes, WP-CLI block registration checks, direct `do_blocks()` render checks for `iss/atlas-slice` and `iss/spine-strip`, route smoke checks for `/`, `/fuehrungen/`, and `/schoneweide/`, marker JSON validation, and `git diff --check`.
- Atlas plan closeout: merged the audit and peer review into `docs/architecture/atlas-static-map-implementation-plan.md`; `git diff --check` passed for the new document.
- Project content artifact verification: imported `ops/sql/2026-06-14-project-content-sync.sql` locally; confirmed zero dev-host references in published project rows; verified seven project routes return `200`; verified `ops/uploads/2026-06-14-project-content-media.tar.gz` contains 28 files and matches its SHA256.
