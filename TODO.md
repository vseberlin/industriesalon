# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Active Today

- Editorial platform next slice: define Ausstellung skins and skin-control decisions against SOW Phase 3. Decide the first skin names, whether editors choose only semantic skins or also variants, how skin controls appear in the main canvas, and how theme partial overrides are resolved.
- After skin decisions, choose the first curator-reviewed Ausstellung pilot, create/review its versioned JSON document through the new editor UI, verify preview/frontend output, and leave legacy fallback available until curator signoff.
- Review the disabled local JSON candidate on `Frauen im Werk für Fernmeldewesen`, clean up section types, image captions, archive-object choices, source links, and navigation/research links in the editor UI, then decide whether to enable JSON rendering for that one pilot.
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
