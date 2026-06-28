# Current Handoff

Updated: 2026-06-28

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Local working checkpoint implements unified lightweight booking/order intake through `iss-commerce-lite`.
- `POST /wp-json/iss-payments/v1/request` is the canonical public request route for `tour_booking`, `event_booking`, and `publication_order`; the old tour and publication routes remain compatibility wrappers.
- Veranstaltung editor now has booking controls in the native WordPress right rail: enable booking, Euro price, CTA label, and booking hint. Mollie remains a disabled/stub option until a provider integration registers support.
- Timeline/programme booking modals post to the generic commerce request endpoint and can render Veranstaltung price/payment data.
- JSON composition UX was consolidated: editors see `Abschnitt`/`Vorspann`, shared `iss-editorial` cards expose `Löschen`, and Veranstaltung composition uses compact cards plus edit modals instead of always-open forms.
- Veranstaltung composition includes the health strip directly; the separate `Redaktionsstatus` metabox is no longer registered. The strip now calls out Beitragsbild and Kurzbeschreibung explicitly.

## Preserve

- Do not enable Mollie as a selectable payment method until a real provider integration creates/settles payment state and registers support through `iss_payments_lite_supported_payment_methods`.
- Do not add another booking/order storage layer. `iss-commerce-lite` owns request storage, admin review/export/status, public write guards, and notifications.
- Do not change public Veranstaltung booking visibility silently: `TODO.md` records that single Veranstaltung output still needs a public booking section/block.
- Keep public rendering theme-owned; plugins own data/contracts and request writes.
- Leave unrelated local untracked files out of Git unless explicitly requested:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- Add a visible public booking CTA/section for bookable single Veranstaltungen in the theme template or `_iss_content_json` renderer.
- Continue converging JSON-driven CPTs on the shared compact composition model where remaining screens diverge.

## Verified Locally

- PHP syntax checks on touched PHP files.
- Targeted PHPCS and PHPStan on touched PHP files.
- `node --check` and targeted ESLint on touched JS files.
- Targeted Stylelint on touched CSS files.
- `git diff --check`.
- WP-CLI route smoke: generic commerce request route and legacy compatibility routes are registered.

## Commit State

- Local commit requested. No push requested.
