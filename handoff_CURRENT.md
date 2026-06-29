# Current Handoff

Updated: 2026-06-29

Current checkpoint only. Completed history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Front-page baseline checkpoint is captured for temporary client hero
  image/text experiments.
- The active homepage is not file-backed right now: `get_block_template(
  "industriesalon//front-page", "wp_template" )` reports `source=custom`.
- Local front-page DB state at capture time:
  - static front page option points to page ID `12257` (`home`, `home-2`);
  - active DB template row is `wp_template` ID `26534`, slug `front-page`,
    modified `2026-06-29 10:52:09`;
  - hero baseline uses attachment ID `25235` and headline
    `Industriekultur und Transformation`.
- Rollback artifact: `ops/sql/2026-06-29-frontpage-baseline.sql`.

## Preserve

- Treat the client hero/text pass as a one-off DB-template experiment unless a
  later decision explicitly promotes it into the file-backed theme template.
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

- Let the client test hero image/text variants in the DB-owned front-page
  template.
- When the experiment is done, replay
  `ops/sql/2026-06-29-frontpage-baseline.sql` to roll back, or sync the accepted
  DB template content into `themes/industriesalon/templates/front-page.html`
  and remove the override.
- Continue the Veranstaltung booking public-render TODO separately.

## Verified Locally

- `git diff --check`.
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
