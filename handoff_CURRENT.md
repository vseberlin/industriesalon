# Current Handoff

Updated: 2026-06-29

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Front-page client hero/text/project experiment is implemented and synced to
  disk for review.
- The active homepage is not file-backed right now: `get_block_template(
  "industriesalon//front-page", "wp_template" )` reports `source=custom`.
- Current front-page state:
  - static front page option points to page ID `12257` (`home`, `home-2`);
  - active DB template row is `wp_template` ID `26534`, slug `front-page`;
  - DB template content matches
    `themes/industriesalon/templates/front-page.html`;
  - hero uses imported attachment ID `26778` from the local converted image and
    left-side gradient readability only;
  - old spine strip, project rails, and notice-banner slots are removed;
  - two project cards render as pinned-note cards with project logos and primary
    `Alle Projekte ansehen` CTA.
- `/fuehrungen/` is file-backed (`page-fuehrungen` source is `theme`): dated
  Führung cards render first, followed by a non-tabbed `Gruppen & Co.` Führung
  card grid.
- Rollback artifact for the original front-page baseline:
  `ops/sql/2026-06-29-frontpage-baseline.sql`.

## Preserve

- Treat the front-page client pass as one-off review state until accepted or
  rolled back. Disk and DB are currently synced, but the active source is still
  the DB override.
- Do not delete the front-page DB override while the client is actively testing
  variants; it is the current live source for the homepage.
- Do not enable Mollie as a selectable payment method until a real provider integration creates/settles payment state and registers support through `iss_payments_lite_supported_payment_methods`.
- Do not add another booking/order storage layer. `iss-commerce-lite` owns request storage, admin review/export/status, public write guards, and notifications.
- Do not change public Veranstaltung booking visibility silently: `TODO.md` records that single Veranstaltung output still needs a public booking section/block.
- Keep public rendering theme-owned; plugins own data/contracts and request writes.
- Leave unrelated local untracked files out of Git unless explicitly requested:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Next Action

- Let the client test the current front-page and `/fuehrungen/` variants.
- When the front-page experiment is done, replay
  `ops/sql/2026-06-29-frontpage-baseline.sql` to roll back, or sync the accepted
  DB template content into `themes/industriesalon/templates/front-page.html`
  and remove the override.
- Continue the Veranstaltung booking public-render TODO separately.

## Verified Locally

- `git diff --check`.
- Targeted stylelint for `themes/industriesalon/assets/css/front-page.css` and
  `themes/industriesalon/assets/css/page-fuehrungen.css`.
- Playwright desktop/mobile checks for `/` and `/fuehrungen/`: front-page
  project cards/logos/CTA, removed banner/rail surfaces, Führungen dated cards
  before `Gruppen & Co.`, no old catalog, and no horizontal overflow.
- Front-page DB/file template hash matched after sync; `page-fuehrungen` source
  is `theme`.
- `git diff --cached --check`.
- `git fetch origin --prune`; local `HEAD` and `origin/main` were both
  `f2b85df` before this checkpoint.
- WP-CLI confirmed `show_on_front=page`, `page_on_front=12257`,
  `page_for_posts=0`.
- WP-CLI confirmed active front-page template source is `custom`.
- SQL artifact syntax was checked with `mariadb` against copied local tables in
  a temporary database.

## Commit State

- Local checkpoint commit only; no push requested.
