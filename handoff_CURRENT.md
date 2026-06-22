# Current Handoff

Updated: 2026-06-22

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- Local `main` and `origin/main` are current for this closeout; use `git log -1` for the exact commit. The pushed calendar/button/kicker implementation checkpoint before this documentation closeout is `65e17a4`.
- Staging is the current live working target, not a production release gate. Rebuild from Git plus explicit SQL/upload artifacts if it breaks.
- Domain ownership is current: `iss-content` owns CPT/editor contracts and tour data; `iss-editorial` owns the versioned editorial document engine/read model/autosave layer; `iss-occurrences` owns occurrence projection, SuperSaaS ingestion, tour-slot reads, and sync admin; `iss-frontend` owns programme/timeline/browser rendering plus reusable frontend editorial blocks such as `iss/dense-image-wall`; `iss-commerce-lite` owns booking/order request intake; `iss-archive` owns archive runtime; `iss-graph` owns graph/search/facade contracts; the theme owns public templates and skins.
- `/kalender/` is a file-backed theme template using the existing occurrence-backed `industriesalon/timeline-query` block inside a theme-owned wide workbench skin. Existing timeline filters are positioned as the wide left rail, results stay compact, recurring tour groups start collapsed, oversized listing media is hidden on this surface, and exhibitions remain linked to the separate `/ausstellungen/` availability browser.
- Shared theme primary button tokens, `.iss-button`, Gutenberg fill-button mapping, timeline booking-button tokens, and timeline kicker accent/color variable mapping are pushed. This checkpoint is code/CSS/template only; no SQL or uploads artifact is required.
- Editorial-platform v1 has its first code slice: `iss-editorial` is engine-only, `iss-content` opts `ausstellung` into the `OrderedFormat` pilot, archive-object references use typed refs through a normalized bucket-first archive picker modal, `ausstellung` now uses a custom main-canvas composition editor instead of the Gutenberg block editor/sidebar, and theme-owned JSON rendering is behind `_iss_editorial_enabled_ausstellung` so existing Gutenberg output remains the default fallback. The main canvas no longer exposes the JSON output flag as a normal editor control; it preserves the internal rollout flag while editors work only with section content and order.
- SOW stage: Phase 1 is signed off for `ausstellung` authoring and save/reload after editor proof confirmed add/edit/reorder/remove/save/reload behavior. Autosave recovery is intentionally deferred for now; the explicit red `Speichern` button writes the JSON structure directly, and WordPress `Aktualisieren` is only needed for WordPress-owned fields such as title, slug, status, taxonomies, or other metabox data. Phase 2 is signed off for the current Ausstellung pilot boundary: archive-object selection uses the existing bucket-first archive picker, media selection uses `wp.media`, selections persist as typed references, selected objects show titles plus explicit remove controls, and raw IDs are not exposed in the editor tray. The SOW-wide Relation Picker remains deferred until entity-editor work needs it. Phase 3 now has the central skin decision implemented for `frauen-im-werk`: no skin/layout controls in the editor, section `type` is the editorial gesture, the theme renders the `gesture x skin` treatment through universal section slots with optional partial fallback, and the first skin CSS lives in `themes/industriesalon/assets/css/skins/ausstellung-frauen-im-werk.css` behind conditional skin enqueueing. `kicker` is now a first-class JSON section field, imported from old `iss-kicker` markup and rendered through the shared site kicker classes. `quellenauszug` owns the current image/text/quote station treatment; `objektfokus` is reserved for archive-object grids; `vollbild` is a one-image full-viewport treatment with 16:9 editor guidance and a generic dark ink-panel overlay. Phase 3 still needs curator cleanup, preview proof, and migration/rollback artifact work before signoff.
- `wp iss-editorial ausstellung-dry-run` now reports read-only migration candidates for `Kinder im Werk` and `Frauen im Werk`; it does not write JSON meta or enable frontend rendering.
- Local post `26287` (`frauen-in-werk`) has `_iss_editorial_ausstellung` candidate document with skin `frauen-im-werk`, 8 sections, 6 media refs, and 2 archive-object refs. The first populated gestures are `quellenauszug`, `objektfokus`, `vollbild`, `quellenauszug`, `vollbild`, `quellenauszug`, `vollbild`, and `schluss`; the `objektfokus` section currently contains only archive-object refs. `_iss_editorial_enabled_ausstellung` is local review state and is currently enabled in the local DB; turn it off before treating Gutenberg as the visible local fallback again. `ops/sql/2026-06-22-frauen-im-werk-editorial-json.sql` captures the JSON document and enabled flag for transfer; the older media/upload transfer remains separate. Editorial JSON meta writes now slash encoded JSON before `update_post_meta()` so paragraph breaks survive storage.
- Static map ownership is now explicit and partly implemented: `iss-relations` owns map-block source/place-selection contracts and has a focused static-map contract check, `iss-frontend/modules/static-maps` owns marker lookup, projection/focus math, static stage/panel rendering, and static atlas/map frontend renderers, `industriesalon-schoeneweide-register` owns `register_place`, interactive Atlas data/cache contracts, Atlas REST schema checks, and the existing Schöneweide Atlas block, and the theme owns map assets/presets/skins. First-class inserter-visible map surfaces are `iss/related-place-map`, `iss/atlas-slice`, and `iss/spine-strip`; `iss/atlas-strip` and `iss/asymmetric-split-field` remain render-compatible but hidden as experimental.
- Static map framing has a new baked full-size derived canonical: `themes/industriesalon/assets/maps/schoneweide-map-spree-horizontal-17.webp` plus `schoneweide-map-spree-horizontal-17-markers.json`. The `spree-horizontal-17` preset is used by the front page and `/fuehrungen/` spine strips with page-specific vertical crop and 1.14 cover zoom; runtime rotation and horizontal-bias controls are hidden from the editor.
- `themes/industriesalon/theme.json` was committed separately as a valid design-token update. `themes/industriesalon/theme2.json` and `iss-exhibition-composition-add.md` remain local untracked files and were intentionally not committed.
- The merged Atlas/static-map rewrite plan is in `docs/architecture/atlas-static-map-implementation-plan.md`; the related-content editor JS split, static-map DTO boundary, interactive Atlas runtime module split, Atlas/static-map contract-schema checks, fullscreen/kiosk Atlas layout states, and final public-surface audit are in local commits. Broader archive/graph API consolidation is deferred until there is a concrete public consumer.
- Occurrence rows are source-post keyed only. Open-ended rows use `ends_at = NULL` plus `is_open_ended = 1`; graph IDs and `2099-12-31` sentinels are invalid drift.
- Programme projection uses `iss_programme_enabled`; Ausstellung overview visibility uses `iss_public_overview_enabled`. Dauer/Digital Ausstellungen can remain in overviews without programme occurrences unless editors explicitly opt them in.
- `/wp-json/iss/v1` is the read-only facade boundary for contract, entities, relations, occurrence reads, search, timeline, availability, and tour slots. Booking writes stay on `/is-tours/v1/book`.
- Commerce-lite request rows remain in `wp_iss_payments_lite_requests` for compatibility. SuperSaaS settings use `iss_supersaas_*`; retired `is_saas_settings` is migrated and drift-guarded.
- `/ausstellungen/` browser polish is committed locally: result summaries, debounced live search, clear-search fallback link, search-preserving filter URLs, and responsive page-skin controls stay within the existing `industriesalon/ausstellungen-browser` block and `/iss/v1/availability` route.
- `register_place` public image groups now bridge into WordPress featured-image rendering: public featured/fallback image-group selections override stale `_thumbnail_id` at render time and save-time sync rewrites `_thumbnail_id` when the selected public image changes. Local post `17960` was resynced from thumbnail `18852` to public current image `18856`; no SQL/upload artifact is required for the code path because the runtime filter corrects stale stored thumbnails.
- Walk of Fame dense-wall content needs paired transfer artifacts if applied elsewhere: `ops/sql/2026-06-14-walk-of-fame-dense-wall.sql` plus `ops/uploads/2026-06-14-walk-of-fame-dense-wall-media.tar.gz`.
- Current published `projekt` single-page content edits need the all-project transfer unit if applied elsewhere: `ops/sql/2026-06-14-project-content-sync.sql` plus `ops/uploads/2026-06-14-project-content-media.tar.gz`, manifest, and SHA256. The SQL covers all seven published project posts, postmeta, and term relationships; directly referenced media files are packaged in the paired archive.

## Current Risk

- The `Frauen im Werk` editorial candidate and its `frauen-im-werk` skin assignment are DB-backed local state. Use `ops/sql/2026-06-22-frauen-im-werk-editorial-json.sql` for the editorial JSON and enabled flag before transferring that candidate document to another environment.
- The composition canvas is still v1: basic WordPress media refs are imported/editable/renderable with caption fields and explicit removal controls, archive-object selection is now bucket-first through attached Archivsets with explicit selected-object removal, and conclusion/decision links are editable and render as a theme-owned button rail. Source links and object/source section semantics still need curator cleanup before the `Frauen im Werk` candidate is ready.
- Request notification mail is implemented but disabled by default. Enable only after target mail mode and recipient are approved.
- First deploy to a database with old plugin basenames or without `iss-editorial` active relies on the `iss-core` active-plugin migrator; verify `wp plugin list`.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk templates are live.
- Front-page remains DB-backed in local state, but `iss/spine-strip` no longer depends on saved `source` when `placeIds` are present because the map-block contract resolves that as `manual`.
- Static marker JSON now covers published coordinate-bearing `register_place` posts in the local audit, including derived markers added for Waldfriedhof entries, IRIS, Innovationspark Wuhlheide, Energie-Museum Berlin, and Spree 27. Marker provenance and the manual update verification path are documented in `docs/architecture/static-map-rendering.md`.
- The new `spree-horizontal-17` marker JSON is a derived projection from `schoneweide-static-markers-new.json`; regenerate it from the unrotated canonical map if the baked `-17deg` projection changes.
- `page-projekte` currently remains DB-backed (`custom`) after being flushed to `themes/industriesalon/templates/page-projekte.html`; delete that override only after the disk template is verified in the target flow.
- History was rewritten on 2026-06-12. Existing secondary clones should be re-cloned or reset deliberately.
- `/home/vladimir/industriesalon-export` is stale and should not be used for deploy/push.
- `iss/tour-route` currently treats place image groups and station archive objects as separate media channels. `/fuehrungen/elektropolis-tour/` station 1 suppresses its historical place image because `archive_images` is private, while the linked archive object still renders as a detail card; decide deliberately before merging those concepts.

## Next Action

- Delete the `page-projekte` DB template override after verifying the flushed disk template on the target.
- Complete source/object editing in the composition canvas, then review and clean up the `Frauen im Werk` JSON candidate in the editor UI, verify preview/frontend output for the `frauen-im-werk` skin, and keep legacy fallback available until curator signoff.
- Keep `Kinder im Werk` pure Gutenberg until archive-object/html mapping is deliberately handled.
- Decide whether Führung station archive objects should remain separate detail cards or also populate the station “Damals” image slot when place-level public `archive_images` are missing.
- Before production deploy, verify target mail mode and decide whether request notification email should be enabled.
- If the front-page or Führungen map crop needs further tuning, adjust `biasY` on the block or promote page-specific crop defaults into named presets; no SQL/upload artifact is required for the current code/assets-only checkpoint.

## Agent-start checks (security posture)

- Run this at each new agent session before touching staging:
  - `uptime`
  - `free -h`
  - `df -h /`
  - `systemctl --failed`
  - `docker ps`
- Verify service logs for abuse signals:
  - `journalctl -xe --no-pager | tail -n 200`
  - `journalctl -u nginx --no-pager --since "1 hour ago"`
  - `journalctl -u fail2ban --no-pager --since "1 hour ago"`
  - `journalctl -u ssh --no-pager --since "1 hour ago" -o short-iso`
- Probe / attack pattern scan (high-signal quick check):
  - `tail -n 200 /var/log/nginx/access.log`
  - `grep -Ei "(wp-login|xmlrpc.php|/wp-json/|/wp-admin|/wp-content/uploads/.+\\.php|/\\.git|/\\.env|\\.php\\?| 40[13])" /var/log/nginx/access.log | tail -n 200`
  - `grep -Ei "malformed|bot|curl|sqlmap|nmap|nikto|wpscan|scanner|probe|attack" /var/log/nginx/error.log /var/log/nginx/access.log | tail -n 200`
- Confirm fail2ban enforcement state:
  - `fail2ban-client status`
  - `fail2ban-client status nginx-bad-request`
  - `fail2ban-client status nginx-forbidden`
  - `fail2ban-client status nginx-limit-req`
  - `fail2ban-client status nginx-botsearch`
  - `fail2ban-client status recidive`
  - Note: `sshd` jail remains intentionally disabled (`enabled = false`) because admin SSH source is rotating; monitor auth logs but do not rely on automated bans for SSH.
- Check firewall rule presence (pick whichever is active on host):
  - `systemctl status ufw nftables iptables --no-pager`
  - `ufw status verbose`
  - `nft list ruleset`
- Optional SSH abuse smoke checks (daily pass):
  - `grep -iE "Failed password|Invalid user|authentication failure|Did not receive identification string|Connection closed by authenticating user" /var/log/auth.log /var/log/secure 2>/dev/null | tail -n 200`
  - `grep -iE "Accepted password|Accepted publickey" /var/log/auth.log /var/log/secure 2>/dev/null | tail -n 50`
- Unauthorized `/etc` change monitoring (host integrity):
  - `sudo find /etc -type f -mmin -240 -print`  # files changed in last 4 hours
  - `sudo find /etc -type f -cmin -240 -print`  # inode metadata changed in last 4 hours
  - `sudo grep -Ei "install|upgrade|remove" /var/log/dpkg.log /var/log/apt/history.log 2>/dev/null | tail -n 200`
  - If auditd is enabled: `sudo ausearch -k etc-watch --raw -i` and `sudo aureport --file --summary | tail -n 50`
  - If audit tools are unavailable, note that `sudo find /etc -type f -mmin -240 -print` and package logs remain the fallback.
- If we cannot prove root of change, escalate these `/etc` alerts immediately:
  - any `/etc` binary or service drop-in changed outside maintenance windows
  - unexpected auth/SSH/SSL config edits (`sshd_config`, `sudoers`, `nginx`, `php-fpm`, `fail2ban`, `crontab` files in `/etc`)
  - more than 5 unexpected `/etc` file changes in 15 minutes (single window) with matching service restarts

### Incident thresholds (what triggers escalation)

- Any SSH abuse signal over baseline in one hour:
  - >20 failed auth lines in 60 minutes
  - any "Invalid user" burst from same source / same hour
- Nginx probe burst:
  - >100 4xx/5xx requests in 5 minutes from one source, or repeated `/.git|/xmlrpc.php|/wp-login|/wp-admin` patterns
- Fail2Ban drift:
  - any active jail suddenly disabled in `fail2ban-client status`
  - repeated unbans/resets outside change window
- Firewall anomalies:
  - firewall service down or no rule set printed
- `/etc` integrity:
  - any unexpected config-file edit outside approved maintenance window
  - package install/removal activity not matched to known maintenance ticket/cron

### Incident severity map (quick triage)

- Critical:
  - sustained SSH abuse + signs of successful unauthorized auth
  - active `/etc` compromise indicators (auth/SSH/DB/firewall config changed unexpectedly)
  - confirmed data exfiltration or confirmed website write compromise
- High:
  - repeated Nginx probing with brute-force pattern and escalating blocklist activity
  - recidive/limited jails repeatedly cycling from the same /24 with service impact
  - firewall service loss or dropped rules unexpectedly
- Medium:
  - small spike in 4xx/5xx from one source
  - unauthorized package change outside schedule without privilege escalation
  - fail2ban jail unexpectedly disabled but no confirmed intrusion evidence
- Low:
  - isolated spikes with no persistence and no sensitive files touched
  - temporary service noise where baseline returns within 30 minutes

## Verified Locally

- Latest local checkpoint passed focused JS/PHP syntax, PHPCS target, `git diff --check`, WP-CLI block registry/render checks for `iss/dense-image-wall`, route checks for `/projekte/`, front page, and Walk of Fame, plus SQL/upload artifact inspection.
- Editorial-platform v1 slice passed PHP syntax for `iss-editorial`, `iss-content`, `iss-core`, and the Ausstellung render helper; PHPCS target; directory-level PHPStan for `plugins/iss-editorial` plus touched integration files; targeted ESLint and Stylelint; WP-CLI checks for active plugin migration, format registration, REST route registration, document sanitization, read-model output, autosave REST dispatch, and preview placeholder rendering; HTTP route smoke for `/ausstellungen/kinder-im-werk/` with legacy fallback off and a temporary JSON-rendering enable/cleanup cycle. The earlier repo per-file PHPStan positives were structural: the target runner analyzes include files independently, and `iss-editorial` was missing from PHPStan `paths`/`scanFiles`; that config is now updated.
- Static-map implementation slice passed PHP syntax, targeted PHPCS, targeted PHPStan, `node --check` for the related-content block editor script, `wp iss-relations map-block-audit` in table and JSON modes, WP-CLI block registration checks, direct `do_blocks()` render checks for `iss/atlas-slice` and `iss/spine-strip`, route smoke checks for `/`, `/fuehrungen/`, and `/schoneweide/`, marker JSON validation, experimental block inserter visibility checks, and `git diff --check`.
- Atlas plan closeout: merged the audit and peer review into `docs/architecture/atlas-static-map-implementation-plan.md`; `git diff --check` passed for the new document.
- Related-content editor JS split passed targeted related-content ESLint, full `npm run lint:js`, PHP syntax for `plugins/iss-relations/includes/blocks.php`, WP-CLI script-handle/block-registration checks, and `git diff --check`.
- Static-map DTO boundary passed PHP syntax, PHPCS/PHPStan targets for `plugins/iss-relations/includes/blocks.php`, `wp iss-relations map-block-audit`, a WP-CLI DTO shape smoke for `iss_relations_resolve_static_map_relation_result()`, and `git diff --check`.
- Interactive Atlas modularization first slice passed targeted Atlas JS ESLint, full `npm run lint:js`, PHP syntax for `plugins/industriesalon-schoeneweide-register/includes/assets.php`, WP-CLI script registration/dependency checks, `git diff --check`, and a Playwright smoke on `/schoneweide/` showing ready state, 74 markers, five loaded Atlas modules, and no console errors.
- Interactive Atlas store/state slice passed targeted Atlas JS ESLint, full `npm run lint:js`, PHP syntax for `plugins/industriesalon-schoeneweide-register/includes/assets.php`, WP-CLI script registration/dependency checks, `git diff --check`, and a Playwright smoke on `/schoneweide/` showing ready state, 74 default markers, actor-filter marker reduction, six loaded Atlas modules including `store`, and no console errors.
- Interactive Atlas place/filter UI slice passed targeted Atlas JS ESLint, full `npm run lint:js`, PHP syntax for `plugins/industriesalon-schoeneweide-register/includes/assets.php`, WP-CLI script registration/dependency checks, `git diff --check`, and a Playwright smoke on `/schoneweide/` showing ready state, 74 default markers, actor-filter marker reduction, reset back to 74 markers, seven loaded Atlas modules including `places`, and no console errors.
- Interactive Atlas detail/story/relation rendering slice passed targeted Atlas JS ESLint, full `npm run lint:js`, PHP syntax for `plugins/industriesalon-schoeneweide-register/includes/assets.php`, WP-CLI script registration/dependency checks, `git diff --check`, and a Playwright smoke on `/schoneweide/` showing ready state, 74 default markers, popup detail rendering, six story cards, actor-filter marker reduction, reset back to 74 markers, ten loaded Atlas modules including `detail`, `stories`, and `relations`, and no console errors.
- Interactive Atlas marker split passed targeted Atlas JS ESLint, full `npm run lint:js`, PHP syntax for `plugins/industriesalon-schoeneweide-register/includes/assets.php`, WP-CLI script registration/dependency checks, `git diff --check`, and a Playwright smoke on `/schoneweide/` showing ready state, 74 default markers, active marker selection, popup detail rendering, actor-focus marker classes, reset back to 74 markers, eleven loaded Atlas modules including `markers`, and no console errors.
- Atlas/static-map contract-schema slice passed `wp iss-register contract-check`, `wp iss-relations static-map-contract-check`, PHP syntax for `plugins/industriesalon-schoeneweide-register/includes/register-data/guardrails.php` and `plugins/iss-relations/includes/cli.php`, targeted PHPCS, targeted PHPStan, and `git diff --check`.
- Atlas fullscreen/kiosk layout slice passed PHP syntax for `plugins/industriesalon-schoeneweide-register/includes/atlas-render.php`, JS syntax checks for the edited Atlas scripts, targeted ESLint, targeted Stylelint, targeted PHPCS/PHPStan, WP-CLI block render/script registration checks, `git diff --check`, and Playwright desktop/mobile smokes on `/schoneweide/` showing embedded/fullscreen/kiosk modes, 74 markers, loaded tiles, and no console errors.
- Atlas public-surface closeout passed file-template and DB-backed content scans, block registration/inserter visibility checks, `wp iss-relations map-block-audit`, `wp iss-relations static-map-contract-check`, `wp iss-register contract-check`, and `git diff --check`.
- `/ausstellungen/` browser polish passed PHP syntax for `plugins/iss-frontend/modules/programme/includes/ausstellungen-browser.php`, JS syntax/ESLint for `plugins/iss-frontend/modules/programme/assets/ausstellungen-browser.js`, Stylelint for timeline and page-Ausstellungen CSS, targeted PHPCS/PHPStan, `wp iss-frontend ausstellungen-audit --strict`, block-render checks, `git diff --check`, and Playwright desktop/mobile smokes on `/ausstellungen/` covering live search, clear, filter switching, URL state, mobile control widths, and no console errors.
- Project content artifact verification: imported `ops/sql/2026-06-14-project-content-sync.sql` locally; confirmed zero dev-host references in published project rows; verified seven project routes return `200`; verified `ops/uploads/2026-06-14-project-content-media.tar.gz` contains 28 files and matches its SHA256.
- Register-place image bridge passed PHP syntax, targeted PHPCS/PHPStan, `git diff --check`, WP-CLI candidate-vs-thumbnail checks (`candidate_diffs=0`), related-card/static-map DTO render checks, and frontend spot checks for the Industriesalon place route and search result.
- Elektropolis route media audit verified `/fuehrungen/elektropolis-tour/` returns `200`, `single-fuehrung` is file-backed, no PHP route errors appear in container logs, station 1 has private `archive_images` plus public `current_images`, and station 2 renders public “Damals”/“Heute” image groups correctly.
- 2026-06-17 staging health check (`19:40 UTC`): staging DNS resolves to `142.132.191.224`; `curl -I https://staging.industriesalon.info/`, `/wp-login.php`, and `/wp-json/` all returned `200 OK`. `systemctl is-active nginx fail2ban docker` reported nginx and fail2ban active; local WP stacks are healthy in Docker list (`Up` states, one healthy `php8.3-fpm` stack). `free -h`/`df -h /` showed low memory pressure and 25% root usage. `fail2ban-client status` shows no active bans for `nginx-forbidden`, `nginx-bad-request`, `nginx-limit-req`, `nginx-wordpress-probe-stage`, `nginx-wordpress-probe-touchtable`; `findtime` currently `30m`, `bantime` `30m`, elevated retry caps loaded on the key jails.
- Static-map framing checkpoint was not run through automated validation in this session; implementation was iterated from local visual feedback and screenshot review. Code/assets only, no database or upload transfer artifact required.
- Calendar/button/kicker checkpoint passed Stylelint for `style.css`, `timeline-skin.css`, and `page-kalender.css`; `git diff --check`; file-template parse checks; `wp_template` source check for `industriesalon//page-kalender` returning `theme`; and Playwright desktop/tablet/mobile smokes on `/kalender/` covering no horizontal overflow, filter/reset, recurring-details expansion, aside image load, and booking-button visual state.
- Exit checkpoint: `origin/main` is current for this closeout; the pushed implementation checkpoint before the documentation commit is `65e17a4`. Remaining local dirt is limited to untracked `theme2.json` and `iss-exhibition-composition-add.md`.
