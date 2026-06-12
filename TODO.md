# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Security: Post Git-History Purge

- Re-clone or deliberately reset `/home/vladimir/industriesalon-export` before using it again; current local `main` is stale/diverged from rewritten `origin/main`.
- Check any staging/server clone before deploy. Because `main` was rewritten, prefer fresh clone or explicit reset to rewritten `origin/main` after preserving any local-only state.
- Consider GitHub Support cache/unreachable-object purge for the removed Newsletter SQL artifact if strict privacy cleanup is required.
- Review Newsletter subscriber tokens and decide whether token regeneration is required after the prior public exposure.

## Refactor: Deploy Legacy Read-Route Retirement

- Transfer and verify the separate route-retirement checkpoint on staging/production only after confirming no target-side consumer still calls:
  - `/iss-search/v1/search`
  - `/iss-programm/v1/timeline`
  - `/is-tours/v1/slots`
- Preserve `/is-tours/v1/book` for booking writes.
- Rerun facade checks, retired-route absence checks, public route checks, and smoke checks after deploy.

## Production: Transfer Greenfield Refactor Checkpoint

- Prepare production transfer with paired data:
  - backup target DB first
  - apply `ops/sql/2026-06-11-strict-programme-toggle-backfill.sql`
  - apply `ops/sql/2026-06-11-ausstellung-availability-cleanup.sql`
  - apply `ops/sql/2026-06-12-legacy-occurrence-origin-purge.sql`
  - apply `ops/sql/2026-06-12-supersaas-past-occurrence-reactivation.sql`
  - apply `ops/sql/2026-06-12-tour-template-collapse.sql`
  - run target occurrence schema/sync/backfill commands as needed, then graph and occurrence drift checks
- Verify production public consumers:
  - `/`, `/kalender/`, `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`
  - header search, timeline query, and tour-slot reads use `/wp-json/iss/v1`
  - expected old-path survivor is `/is-tours/v1/book`

## Active

- 2026-06-07: Run the editorial entity-hygiene pass needed for transcript relation building:
  - inventory duplicate normalized names, wrong-kind entities, and ambiguous aliases around `Industriesalon Schöneweide`, `WF`, `KWO`, `TRO`, and `AEG`
  - review the 3 pending `video_transcript` evidence refs in Video CPT editors and accept/dismiss them
  - define the smallest safe merge/reassign workflow before loosening transcript matching beyond curated names
- 2026-06-07: Design the human graph-influence layer:
  - define temporary curatorial signals for boost, pin, suppress, and feature without editing canonical relations or aliases
  - require context, reason, author, and expiry for temporary signals
  - decide the first consuming surfaces: graph search, related-content blocks, exhibitions, projects, events, and tours
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
