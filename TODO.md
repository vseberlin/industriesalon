# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Tomorrow: Finish Greenfield Refactor Checkpoint

- Decide whether local `main` should be pushed for staging review; it is currently ahead of `origin/main` and intentionally not pushed.
- Run a final public-consumer audit for legacy read URLs:
  - expected converted reads: header search, timeline query, tour-slot reads
  - expected survivors: legacy compatibility reads, admin/editor REST, archive admin helpers, and `/is-tours/v1/book`
- Re-run the local transfer gate:
  - PHP lint / JS syntax / targeted PHPCS / targeted PHPStan
  - all facade comparators: search, occurrences, entities, timeline, tour-slots
  - `wp iss-graph verify`, `wp iss-graph drift-check`, `wp iss-occurrences verify`, `wp iss-occurrences drift-check`
  - public HTTP checks for `/`, `/kalender/`, `/ausstellungen/`, `/fuehrungen/`, `/veranstaltungen/`
- Prepare staging transfer with paired data:
  - backup target DB first
  - apply `ops/sql/2026-06-11-strict-programme-toggle-backfill.sql`
  - apply `ops/sql/2026-06-11-ausstellung-availability-cleanup.sql`
  - run target occurrence schema/sync/backfill commands as needed, then graph and occurrence drift checks
- After staging passes, mark the checkpoint complete in `refactor.md` and `handoff_CURRENT.md`; leave old read routes in place until a separate removal checkpoint.

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
- Review the `Führung` single-page booking flow:
  - collapse `single-tour.html` and `single-tour-on-demand.html` only if editors do not need distinct compositions
  - keep CTA/mode switching in render logic, not parallel full-page templates
- Review footer navigation and column spacing after the current footer refactor:
  - decide whether `Entdecken` / `Service` stay as separate menus or move to real footer menu assignments
  - rebalance wide-screen spacing between columns, labels, and hours exception rows
