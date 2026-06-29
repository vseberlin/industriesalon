# Current Handoff

Updated: 2026-06-29

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Veranstaltung and shared editorial JSON body fields use the local lightweight rich-text helper with paragraph, bold, italic, link, unordered-list, and ordered-list controls. Links are visible and removable; body sanitization permits only the matching narrow HTML contract.
- Veranstaltung media picker feedback updates live inside the edit modal after select/remove/hydration.
- Veranstaltung `material` now means description plus downloadable media files. The editor exposes media refs, not list/archive/dynamic refs; non-image material attachments render publicly as `Herunterladen` download cards.
- Veranstaltung `programm`, `upload_intake`, and `schluss` no longer expose the obsolete `Punkte, je Zeile ein Eintrag` item field; rich-text lists cover that workflow. Legacy saved item output remains renderable until edited/saved.

## Preserve

- Do not enable Mollie as a selectable payment method until a real provider integration creates/settles payment state and registers support through `iss_payments_lite_supported_payment_methods`.
- Do not add another booking/order storage layer. `iss-commerce-lite` owns request storage, admin review/export/status, public write guards, and notifications.
- Do not change public Veranstaltung booking visibility silently: `TODO.md` records that single Veranstaltung output still needs a public booking section/block.
- Keep public rendering theme-owned; plugins own data/contracts and request writes.
- Keep the editor helper local unless a future pass proves a full external editor dependency is worth the maintenance cost.
- Leave unrelated local untracked files out of Git unless explicitly requested:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- Add a visible public booking CTA/section for bookable single Veranstaltungen in the theme template or `_iss_content_json` renderer.
- Continue converging JSON-driven CPTs on the shared compact composition model where remaining screens diverge.

## Verified Locally

- PHP syntax checks on touched PHP files.
- Targeted ESLint on touched JS files.
- Targeted Stylelint on touched CSS files.
- `git diff --check`.
- Browser-loaded Veranstaltung admin config confirmed updated gesture supports.
- Playwright checks confirmed rich editor/link/media picker behavior during the admin UX pass.
- PHP render smoke confirmed a real PDF material attachment renders as a `Herunterladen` file card.

## Commit State

- Commit and push requested for the current editor/material UX checkpoint.
