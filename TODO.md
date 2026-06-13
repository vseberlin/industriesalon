# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Active Today

- Backend knowledge-graph refactor closeout is complete locally; do not reopen backend slices unless final review exposes a real contract gap.
- Push the local closeout commit only when explicitly requested.
- Next active slice: UI polish, especially clean Ausstellung search/filter interaction and public view polish.
- Treat staging as the current live working target, not a production-grade release gate. If staging breaks, rebuild/reapply from Git and known data artifacts.

## Active Refactor Notes

- Keep WordPress CPTs as the editor shell; do not rename `fuehrung` / `veranstaltung` or change public templates for the offer bridge.
- `/ausstellungen/` now progressively filters/searches through `/iss/v1/availability` while keeping no-JS links/forms.
- Header search, timeline reads, tour-slot reads, and Ausstellung availability reads are on `/wp-json/iss/v1`; booking writes stay on `/is-tours/v1/book`.
- `/iss/v1/entities/{id}/occurrences` is now the entity-scoped occurrence read surface; it does not create occurrence storage or an editor-visible occurrence CPT.
- Offer subtype public labels are centralized in `iss-graph`; header search, related cards, and timeline cards consume those labels without exposing contract internals.
- `wp iss-fuehrungen drift-check` now guards the public tour Offer catalog against missing published tours, invalid `offer/tour` contracts, unknown catalog groups, and missing renderer shell fragments.
- `wp iss-graph facade-consumer-audit` guards the known public facade consumers and the one allowed booking write route.
- `wp iss-graph view-contract-audit` guards the main file-backed public views against mixing occurrence, availability, and offer projection layers.
- `wp iss-occurrences drift-check` now guards active SuperSaaS generated occurrences against the service-owned series table, so recurrence rows stay linked to their parent `fuehrung`.
- SuperSaaS series and tag source resolution now live in the service-owned series table; the retired `iss_occurrences_series_map` and `iss_occurrences_source_map` options are deleted after migration and guarded by drift.
- Local graph data is aligned with the reviewed alias replay, KWO/AEG, and Industriesalon/WF curation artifacts.
- Ausstellung availability now consumes self-scoped `availability` editorial signals from `iss-graph`; search and related signals remain separate surfaces.
- Editor UX audit for this checkpoint is recorded in `docs/project/kg-editor-ux-audit-2026-06-13.md`.
- Offer consumer audit for this checkpoint is recorded in `docs/project/kg-offer-consumer-audit-2026-06-13.md`.
- No SQL/uploads transfer artifact is required for the backend closeout; the SuperSaaS source/series option migration runs through the `iss-occurrences` schema installer.

## Other Active Work

- 2026-06-07: Review the 3 pending `video_transcript` evidence refs in Video CPT editors and accept/dismiss them after the graph entity hygiene audit exists.
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
