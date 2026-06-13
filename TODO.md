# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Security: Post Git-History Purge

- Re-clone or deliberately reset `/home/vladimir/industriesalon-export` before using it again; current local `main` is stale/diverged from rewritten `origin/main`.
- Check any staging/server clone before deploy. Because `main` was rewritten, prefer fresh clone or explicit reset to rewritten `origin/main` after preserving any local-only state.
- Consider GitHub Support cache/unreachable-object purge for the removed Newsletter SQL artifact if strict privacy cleanup is required.
- Review Newsletter subscriber tokens and decide whether token regeneration is required after the prior public exposure.

## Production: Transfer Greenfield Refactor Checkpoint

- Prepare production transfer with paired data:
  - backup target DB first
  - apply `ops/sql/2026-06-11-strict-programme-toggle-backfill.sql`
  - apply `ops/sql/2026-06-11-ausstellung-availability-cleanup.sql`
  - apply `ops/sql/2026-06-12-legacy-occurrence-origin-purge.sql`
  - apply `ops/sql/2026-06-12-supersaas-past-occurrence-reactivation.sql`
  - apply `ops/sql/2026-06-12-tour-template-collapse.sql`
  - apply `ops/sql/2026-06-12-fuehrung-template-hierarchy-cleanup.sql`
  - apply `ops/sql/2026-06-12-graph-alias-backfill-replay.sql`, run `wp iss-graph sync-aliases`, and verify `--checks=alias-backfill-replay`
  - apply `ops/sql/2026-06-12-graph-canonical-kwo-aeg-organizations.sql` and verify `--checks=canonical-organization-seeds`
  - apply `ops/sql/2026-06-12-graph-canonical-wf-industriesalon.sql`, run `wp iss-graph sync-aliases`, and verify `--checks=canonical-wf-industriesalon`
  - run target occurrence schema/sync/backfill commands as needed, then graph and occurrence drift checks
- Verify production public consumers:
  - `/`, `/kalender/`, `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`
  - header search, timeline query, and tour-slot reads use `/wp-json/iss/v1`
  - expected old-path survivor is `/is-tours/v1/book`

## Active

- 2026-06-13: Continue knowledge-graph alignment after the local offer-bridge slice:
  - offer bridge staging validation is green: additive `/iss/v1` `contract_kind` / `subtype`, `public-object-contract`, `facade-check`, and `facade-entities-compare` passed per operator report
  - entity-relations facade staging validation is green: `entity-relations-contract`, `facade-check`, and `facade-entity-relations-compare` passed per operator report
  - smoke-test the graph editorial signal controls in wp-admin plus `/iss/v1/search`
  - keep WordPress CPTs as the editor shell; do not rename `fuehrung` / `veranstaltung` or change public templates for the bridge
  - choose the next facade endpoint candidate after staged relation validation: availability
- 2026-06-07: Review the 3 pending `video_transcript` evidence refs in Video CPT editors and accept/dismiss them after the graph entity hygiene audit exists.
- 2026-06-12: Follow up the human graph-influence layer after staging/editor validation:
  - review the 5 legacy related `feature` signal rows and either add reason/expiry or retire expired rows deliberately
  - decide whether exhibitions, projects, events, and tours need additional signal consumers beyond graph search and related-content blocks
- Review `/videos/` embed behavior against the YouTube-hit goal:
  - test whether card selection should update poster/metadata while playback starts only after explicit user play
  - keep a strong `Zum Original` / YouTube handoff path if on-site playback reduces channel traffic
- Improve local video transcription fallback before processing remaining non-caption videos:
  - keep transcript storage unchanged in `video.post_content` plus existing transcript meta
  - evaluate a local-only external provider behind an environment API key
  - add chunking before OpenAI-style uploads if needed
  - preserve backups and explicit per-post runs before writing generated transcripts
- Resolve remaining `register_place` coordinate gaps:
  - `Innovationspark Wuhlheide`
  - `IRIS GmbH`
  - `ITZ 4.0`
  - `Rahmenplan Oberschöneweide`
  - `IBA 2034 Berlin - Standort Oberschöneweide`
  - `Standortgemeinschaft Oberschöneweide`
  - `Energie-Museum Berlin`
  - `Treptow-Ateliers e.V.`
  - `Spree 27`
- Review footer navigation and column spacing after the current footer refactor:
  - decide whether `Entdecken` / `Service` stay as separate menus or move to real footer menu assignments
  - rebalance wide-screen spacing between columns, labels, and hours exception rows
