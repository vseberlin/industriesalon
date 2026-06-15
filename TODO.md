# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Active Today

- Preserve the current ownership split: `iss-relations` resolves place/source contracts, `iss-frontend` owns frontend map rendering, `industriesalon-schoeneweide-register` owns register/interactive Atlas data, and the theme owns map assets/presets/skins.
- Before production deploy, verify target mail mode and enable `Tools > ISS Anfragen` notification email only for an approved recipient if request emails should leave the server.
- Before production deploy, reduce first-party dynamic block clutter: reconcile DB template overrides, move theme render-filter dependencies into plugin defaults where needed, hide unused legacy blocks from the inserter, migrate `industriesalon/program-cards` to `industriesalon/timeline-query`, and collapse related-content wrappers around one shared card renderer before deleting registrations.
- Delete the `page-projekte` DB template override after the flushed file template is verified on the target.
- Treat staging as the current live working target, not a production-grade release gate. If staging breaks, rebuild/reapply from Git and known data artifacts.

## Other Active Work

- Resolve remaining `register_place` coordinate gaps:
  - `ITZ 4.0`
  - `Rahmenplan Oberschöneweide`
  - `IBA 2034 Berlin - Standort Oberschöneweide`
  - `Standortgemeinschaft Oberschöneweide`
  - `Treptow-Ateliers e.V.`
