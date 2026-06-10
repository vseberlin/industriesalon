# Current Handoff

Updated: 2026-06-10

Current checkpoint only. History belongs in `CHANGELOG.md`; active tasks belong in `TODO.md`; durable rules live under `AGENTS.md` and `docs/`.

## Current State

- Branch: `main`.
- Current shareable checkpoint: `Frauen im Werk für Fernmeldewesen` visual-essay redesign, `Kinder im Werk` care-skin Ausstellung, Ausstellung editor cleanup, Archivset workbench/linking cleanup, generic archive-object placement block normalization, and hardened graph editorial-signal controls.
- The old local seven-commit experiment chain was not shipped as-is; it is preserved on local branch `local/pre-cleanup-20260610-211940`.
- `iss-content-model` keeps only structural Ausstellung data plus a generic editor modal bridge: CPT/editor support, dates, permanent flag, timeline flags, taxonomy support, and shared editor modal controls.
- `iss-wf-import` owns archive editor behavior: Archivset attachment, archive picker REST/helper code, archive object insertion adapter, and the archive-material modal handler.
- `iss-wf-import/assets/js/archive-object-picker.js` is the shared archive object picker for the Archivset metabox, Archivsets workbench, and editor archive insert modal; confirmation now waits for the add-member promise and refreshes member lists after successful insertion.
- Archive render blocks are deliberately not normal inserter choices. `/archiv/` and archive templates may still render `archive-object`, `archive-object-browser`, `archive-album`, `archive-collection`, and `archive-object-media`; the old `featured-archive-object` name is a hidden render-only compatibility alias and the generic attached-Archivset grid block has been retired.
- The Archivsets workbench remains the global admin surface. The contextual post editor Archivauswahl attaches/searches sets by title, links to the workbench, and inserts explicit `archive-object` blocks with `variant:"featured"` from set members. Shared editor set plumbing now lives in `iss-wf-import/assets/js/archive-set-selector.js`.
- `iss-newsletter` and its `newsletter` dependency are active locally; the front page renders the real `iss/newsletter-form` again.
- The obsolete saved `iss-register/register-app` block was purged from the local `Register Schöneweide` page and matched revisions. The active register/atlas block is `iss-register/schoneweide-atlas`.
- The retired `iss-wf-import/archive-exhibition` block was removed; it was the old archive-category chapter/exhibition stream path.
- `iss-graph` now owns editor-facing relation signals in `wp_iss_graph_editorial_signals`: context-target related picks, self-targeted `Vorne zeigen` promotion signals, the `iss-graph/v1/editorial-signals` REST route, and the `edit_graph_editorial_signals` capability granted to administrators.
- `iss-relations` consumes active graph editorial signals in automatic related-content blocks only; manual related blocks remain manual. Canonical graph relations are not mutated by editorial picks.
- Graph migration/backfill operation is explicit through `wp iss-graph migrate`; video transcript mention sync is intentionally opt-in with `--with-video-transcripts`.
- `saas-api` now loads the required WordPress admin includes before registering Settings API sections/options pages, so CLI/admin hook smoke tests can fire `admin_init` and `admin_menu` without the previous `add_settings_section()` fatal.
- Plugin-owned Ausstellung text/material/source/layout/corpus surfaces have been removed from the active contract.
- The theme-owned `industriesalon/ausstellung-announcement` block was removed; the active template no longer duplicates post body text into an automatic announcement band.
- The single-Ausstellung theme skin now has a full-viewport cover hero, a theme-owned visit/facts intro band, normal editor-owned `post-content`, and a related-card tail only.
- Station layout is CSS-only for authored Gutenberg groups: standard intro/text stations, full-viewport image stations, object-focus stations, quote pauses, and explicit object grids. Existing archive-object blocks remain the insertion path.
- The featured archive object station is the accepted dark object-focus pattern. The experimental split-picture `iss-ausstellung-station--picture` treatment for Folge 9 was reverted and its CSS removed.
- The active DB-backed `single-ausstellung` template override is post `26309` and was synced from disk after the skin reset.
- Local `Frauen im Werk für Fernmeldewesen` now has an editor-owned station body condensed from `Frauen im WF` Folgen 7-11 and uses the attached Archivset `Frauen im Werk - Bildauswahl` (`wp_iss_archive_sets` id `27`) instead of the accidental Kinder set. Its exhibition-facing Archivset member titles/captions were shortened in the Archivset member rows; source archive object titles were not changed.
- Local `Frauen im Werk für Fernmeldewesen` post `26287` was rewritten again into the current visual-essay flow based on `/home/vladimir/Downloads/frauen1.html`: split image/text sections, full-bleed/portrait caption panels, a flush red Fazit separator, and normalized hero-scale headings.
- Transfer artifacts for the current Ausstellung content/media state:
  `ops/sql/2026-06-10-frauen-in-werk-redesign.sql`,
  `ops/sql/2026-06-10-kinder-im-werk-care-skin.sql`,
  `ops/sql/2026-06-07-kinder-im-wf-salvage.sql`, and
  `ops/uploads/2026-06-10-ausstellungen-media.tar.gz` with its manifest/checksum.

## Current Risk

- The Ausstellung SQL artifacts are narrow content/custom-table transfer files. Apply them together with `ops/uploads/2026-06-10-ausstellungen-media.tar.gz` when staging needs the same media references.
- Older local DB/custom-table state around DB template sync, Archivset `27`, attachment link, and member title/caption edits still needs separate transfer coverage if that exact earlier state must be reproduced on staging.
- Stale post meta rows from old experiments may still exist in the database, but the active code no longer registers or reads the removed source/layout/corpus/browser fields.
- Public post body text is rendered by the single-Ausstellung template through normal `post-content`; keep exhibition prose, announcement copy, and station text editor-owned.
- Editors need a quick Gutenberg UAT pass for the new classed group conventions before deciding whether any station structures should become theme patterns.
- `admin-editor-modal-controls.js` and `.css` remain active shared editor helpers; they should stay generic and avoid archive-specific behavior.
- `archive-editor-modal.js` is the archive-specific editor modal handler and should move together with archive picker/Archivset changes.
- Object search/filter/grid/pagination UI should reuse `archive-object-picker.js`; avoid reintroducing separate archive object picker markup in other plugins or theme code.
- If workbench testing needs the Archivsets admin page directly, use `wp-admin/edit.php?post_type=archivbeitrag&page=iss-archive-sets`; the submenu is not registered under `post_type=archivobjekt`.
- Do not reintroduce core archive-list blocks (`core/archives`, `core/categories`, `core/tag-cloud`, `core/query-title`) as normal post-editor choices; the theme hides them in post editors only, not in the Site Editor.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk files are live.
- The Frauen exhibition content and Archivset attachment are local database/custom-table state. A staging transfer would need a SQL artifact for post `26287` plus Archivset rows/members/links.
- The local graph DB now includes `wp_iss_graph_editorial_signals` plus synced public content graph entities for five previously drifting `fuehrung` posts (`12027`, `12028`, `12034`, `12186`, `12188`). Staging needs `wp iss-graph migrate` or equivalent graph-table sync before relying on editorial-signal controls there.

## Next Action

- Review a representative Ausstellung in Gutenberg/Classic editor after the cleanup and confirm editors can see the real content source.
- Review the new `Frauen im Werk für Fernmeldewesen` visual-essay body in the Gutenberg editor and confirm the classed group flow is comfortable for editors before converting any station structures into reusable theme patterns.
- Review the reset Ausstellung skin in Gutenberg and decide whether the five station structures should become reusable theme patterns.
- Review `admin-editor-modal-controls.js` / `.css` on a real editor screen and confirm no deleted `ausstellung-template-blocks.js` dependency remains.
- Review `archive-editor-modal.js` together with Archivset picker behavior on at least one supported non-Ausstellung post type.
- Manually test the shared archive object picker in the Archivset metabox, Archivsets workbench, and editor archive insert modal with real thumbnail/filter data.
- Open a representative Gutenberg post editor and confirm the archive block inserter is decluttered while existing archive object/selection blocks still render in saved content.
- Run a real editor UAT pass for `Vorne zeigen` and context-target related-content picks before giving non-admin roles the `edit_graph_editorial_signals` capability.

## Verified

- `php -l` passed for the edited plugin/theme PHP files.
- `npm run lint:css` passed.
- `node --check plugins/iss-content-model/assets/admin-editor-modal-controls.js` passed.
- `node --check plugins/iss-wf-import/assets/js/archive-editor-modal.js` passed.
- `node --check plugins/iss-wf-import/assets/js/archive-object-picker.js` passed.
- `node --check plugins/iss-wf-import/assets/js/archivsets-admin.js` passed.
- `git diff --check` passed.
- WP-CLI confirmed the archive object picker REST endpoint returns paginated items for an admin request.
- WP-CLI confirmed Archivset service add-member creates a member for a real `archivobjekt` in a temporary set.
- Playwright verified the Archivsets workbench can select an archive object, confirm the shared picker tray, add the object as a set member, and show the success notice; the temporary test user and set were deleted afterward.
- WP-CLI confirmed `iss-wf-import-archive-object-picker` enqueues on both `publication` and `ausstellung` edit screens with the archive helper, adapter, modal, and Archivset admin script.
- WP-CLI confirmed `iss-wf-import/archive-exhibition` is no longer registered.
- WP-CLI confirmed surviving archive render blocks register with `inserter=false`.
- Runtime PHP confirmed `iss-wf-import/archive-object` is registered as the canonical render block, `iss-wf-import/featured-archive-object` is registered only as a hidden compatibility alias, and retired `iss-wf-import/archive-selection` is not registered.
- MariaDB confirmed active non-revision post content contains zero `iss-wf-import/featured-archive-object` blocks and two `iss-wf-import/archive-object` blocks; remaining old-name occurrences are revisions only.
- WP-CLI confirmed the editor cleanup script enqueues on normal post editors and not on the Site Editor.
- `curl http://192.168.2.31:8082/archiv/` returned `200` and still rendered the archive browser plus featured archive object.
- `curl http://192.168.2.31:8082/ausstellungen/kinder-im-werk/` returned `200`; rendered HTML contains canonical archive-object wrappers and no old block comments.
- `ops/uploads/2026-06-10-ausstellungen-media.tar.gz` contains 53 files and passed `sha256sum -c`.
- The cleaned Git checkpoint excludes the accidentally tracked `plugins/advanced-custom-fields` vendor tree and separate graph/relation experiment files.
- WP-CLI confirmed `industriesalon/ausstellung-announcement`, `industriesalon/ausstellung-material`, and `iss-content-model/ausstellung-surface` are not registered.
- WP-CLI confirmed DB template `26309` no longer contains announcement or material blocks.
- `curl http://192.168.2.31:8082/ausstellungen/frauen-in-werk/` returned `200`; rendered HTML contains none of the removed automatic section strings.
- `curl http://192.168.2.31:8082/ausstellungen/kinder-im-wf/` returned `200`; rendered HTML includes the authored station content and featured archive-object blocks.
- `curl http://192.168.2.31:8082/ausstellungen/frauen-in-werk/` returned `200`; rendered HTML includes the condensed Frauen source stations and seven archive-object cards from `Frauen im Werk - Bildauswahl`.
- `curl http://192.168.2.31:8082/ausstellungen/frauen-in-werk/` returned `200`; rendered HTML includes `Presstellerfertigung im Fotoalbum` in the accepted featured object station and no longer contains `iss-ausstellung-station--picture`.
- WP-CLI confirmed the active `single-ausstellung` template source is still `custom` after syncing DB template `26309` from disk.
- Playwright viewport checks for `/ausstellungen/kinder-im-wf/` showed no horizontal overflow at 1440px desktop or 390px mobile; the hero fills the first viewport and the post body starts after the visit/facts intro band.
- Playwright screenshot checks verified the `/ausstellungen/frauen-in-werk/` featured object station on desktop/mobile after the dark object-focus styling and confirmed Folge 9 is back to a normal station.
- `npx stylelint themes/industriesalon/assets/css/single-ausstellung.css` passed after the visual-essay CSS changes.
- `git diff --check` passed after the visual-essay CSS/content artifact changes.
- WP-CLI confirmed post `26287` content matches the local redesign block artifact before commit.
- Playwright desktop/mobile checks for `/ausstellungen/frauen-in-werk/` confirmed zero section gaps, normalized hero-scale headings, loaded image-backed sections, and no horizontal page overflow.
- `php -l` passed for `plugins/iss-graph/iss-graph.php`, `includes/editorial-signals-admin.php`, `includes/editorial-signals-rest.php`, `includes/cli.php`, and `plugins/iss-relations/includes/blocks.php`.
- `node --check` passed for `plugins/iss-relations/blocks/related-content/index.js` and `plugins/iss-graph/assets/js/entity-relations-block.js`.
- `bash tools/phpcs-target.sh` passed for the edited graph/relation PHP files.
- Runtime PHP confirmed `wp_iss_graph_editorial_signals` exists, the administrator role has `edit_graph_editorial_signals`, `/iss-graph/v1/editorial-signals` is registered, `iss/related-content` is registered, and a temporary editorial signal can be inserted then removed.
- `wp iss-graph verify` passed with all graph/search/editorial-signal tables present.
- `wp iss-graph drift-check --checks=editorial-signals` passed over 3 current rows.
- `wp iss-graph migrate --skip-sync --checks=editorial-signals` passed.
- `wp iss-graph sync-content` synced 225 public content posts; a following full `wp iss-graph drift-check` passed.
- `php -l` and `bash tools/phpcs-target.sh` passed for `plugins/saas-api/saas-api.php`.
- Runtime PHP confirmed `do_action('admin_init')` and `do_action('admin_menu')` complete with `saas-api` active.
