# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Active Today

- Editorial platform next slice: curator-review `Frauen im Werk für Fernmeldewesen` in the custom editor with the `frauen-im-werk` JSON skin enabled locally. Clean up gesture choices, kickers, section text, captions, archive-object choices, source links, and research/navigation links, then verify preview/frontend output.
- Define the `objektfokus` treatment separately as an archive-object grid; do not reuse the `quellenauszug` image/text/quote station treatment for it.
- Review `vollbild` images in the pilot for true 16:9 crops; the editor now warns from stored media dimensions, but final image choice is still curator work.
- Keep Ausstellung skin/layout decisions out of editor controls: editors add, edit, save, and reorder gesture sections; the theme renders each `gesture x skin` treatment through the universal section slots and dedicated skin CSS such as `themes/industriesalon/assets/css/skins/ausstellung-frauen-im-werk.css`.
- Before transferring the `Frauen im Werk` JSON pilot elsewhere, apply/review `ops/sql/2026-06-22-frauen-im-werk-editorial-json.sql`; the current skin assignment and section document are DB-backed local state.
- Keep `Kinder im Werk` on pure Gutenberg until archive-object/html mapping is deliberately handled.
- Preserve the current ownership split: `iss-relations` resolves place/source contracts, `iss-frontend` owns frontend map rendering, `industriesalon-schoeneweide-register` owns register/interactive Atlas data, and the theme owns map assets/presets/skins.
- Before production deploy, verify target mail mode and enable `Tools > ISS Anfragen` notification email only for an approved recipient if request emails should leave the server.
- Before production deploy, reduce first-party dynamic block clutter: reconcile DB template overrides, move theme render-filter dependencies into plugin defaults where needed, hide unused legacy blocks from the inserter, migrate `industriesalon/program-cards` to `industriesalon/timeline-query`, and collapse related-content wrappers around one shared card renderer before deleting registrations.
- Delete the `page-projekte` DB template override after the flushed file template is verified on the target.
- Decide the long-term Führung route media contract: keep station archive objects as separate detail cards, or let selected `station_object_id` images fill the station “Damals” slot when the related place has no public `archive_images`.
- Treat staging as the current live working target, not a production-grade release gate. If staging breaks, rebuild/reapply from Git and known data artifacts.

## Other Active Work

- Resolve remaining `register_place` coordinate gaps:
  - `ITZ 4.0`
  - `Rahmenplan Oberschöneweide`
  - `IBA 2034 Berlin - Standort Oberschöneweide`
  - `Standortgemeinschaft Oberschöneweide`
  - `Treptow-Ateliers e.V.`
