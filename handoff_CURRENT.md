# Current Handoff

Updated: 2026-06-06

Current checkpoint only. History belongs in `CHANGELOG.md`; active tasks belong in `TODO.md`; durable rules live under `AGENTS.md` and `docs/`.

## Current State

- Branch: `main`.
- Current GitHub/staging checkpoint covered by this handoff: `d367739` (`Add Sammlungen media transfer artifacts`).
- Staging already applied the 2026-06-05 Repair Cafe/Sammlungen SQL/uploads artifacts; rollback backups are under `/srv/industriesalon/stage/backups/20260605-213910-repair-cafe-sammlungen-stage/`.
- Staging applied the 2026-06-06 graph/entity code checkpoint plus the paired Sammlungen SQL/uploads artifacts. Rollback backups are under `/srv/industriesalon/stage/backups/20260606-2104-graph-sammlungen-deploy/`.
- New paired Sammlungen transfer artifacts are tracked for the latest local media/template state:
  - `ops/sql/2026-06-06-sammlungen-media.sql`
  - `ops/uploads/2026-06-06-sammlungen-media.tar.gz`
  - `ops/uploads/2026-06-06-sammlungen-media.manifest`
  - `ops/uploads/2026-06-06-sammlungen-media.tar.gz.sha256`
  - Coverage: 16 attachment rows and 81 upload files. The SQL also removes active `front-page` and `page-sammlungen` DB template overrides so those templates remain file-backed after import.
- Current code checkpoint:
  - `iss-graph` Phase 1 adds entity identifiers, evidence refs, resolver wrappers, drift checks, alias backfill, identifier-aware search projection, and resolver-before-create paths for register, archive, content, and enrichment labels.
  - Video transcripts now bridge into pending `video_transcript` evidence refs; Video CPT editors can accept person/organization hints into graph relations, accept place hints through `iss-relations`, or dismiss hints.
  - Manual `entity_profile` aliases and generated alias backfill are separated by source system.
  - `iss/related-content` and `iss/related-cards` can pull graph-related CPTs and archive objects via entity, place, person, and organization sources.
  - Ausstellung backend meta is now `iss_exhibition_type` plus `iss_exhibition_source`; the retired `iss_surface_mode` path and manually insertable `ausstellung-corpus` block were removed.
  - Sammlungen is synced back from the editor DB copy to the theme template, with the stronger `Wege hinein` / `Jetzt stark` / image-carousel composition and fixed Gutenberg grid margins.
  - The active Front Page DB override was purged without syncing its DB-only souvenir image to disk.
  - All active block templates now resolve from theme files; remaining `wp_template` rows are backup/nonmatching slugs only, and there are no `wp_template_part` rows.
- Local working clone: `/home/vladimir/projects/industriesalon`.
- Staging deployment checkout: `/srv/industriesalon/stage/repo`.
- Staging WordPress app root: `/srv/industriesalon/stage/app`.
- Staging shared uploads root: `/srv/industriesalon/stage/shared/uploads` (`app/wp-content/uploads` symlinks here and the WordPress container mounts it at `/var/www/html/wp-content/uploads`).
- Staging Docker Compose file: `/srv/industriesalon/stage/compose.yml`.
- Staging nginx vhost: `/srv/industriesalon/shared/nginx/stage.industriesalon.info.conf`.
- `docs/runbooks/git-exchange.md` is the active local/staging machine sync rule. GitHub `main` is the exchange point; clean behind clones fast-forward only; dirty or diverged clones stop for inspection.
- The repo remote uses the SSH alias `github-industriesalon` and the deploy key `/home/vladimir/.ssh/industriesalon_deploy`.

## Current Server State

- Provider reported shutdowns after the fact; local evidence showed unclean host stops around 2026-06-01 02:28-02:43 UTC and 2026-06-03 00:00-00:08 UTC. MariaDB recovered cleanly after the 2026-06-03 restart.
- Added a 2 GiB `/swapfile`, persisted in `/etc/fstab`, with `vm.swappiness=10` in `/etc/sysctl.d/99-industriesalon-swap.conf`.
- Hardened SSH with `/etc/ssh/sshd_config.d/20-no-password-auth.conf`; effective setting is `PasswordAuthentication no`, with key auth still enabled.
- Added nginx default catch-all server at `/etc/nginx/sites-available/00-catch-all.conf`, enabled via `/etc/nginx/sites-enabled/00-catch-all.conf`, returning `444` for unknown/raw-IP HTTP and HTTPS hosts.
- Blocked `xmlrpc.php` in `/etc/nginx/sites-available/stage.industriesalon.info`; `https://staging.industriesalon.info/xmlrpc.php` returns `403`.
- Server action notes and rollback commands are recorded in `/home/vladimir/server-actions/`.
- Repair Cafe/Sammlungen staging artifact application is recorded in `/home/vladimir/server-actions/2026-06-05-apply-repair-cafe-sammlungen-stage-artifacts.md`.
- Graph/Sammlungen staging deployment is recorded in `/home/vladimir/server-actions/2026-06-06-deploy-graph-sammlungen-stage.md`.

## Current Risk

- Pulling on staging must follow `docs/runbooks/git-exchange.md`; do not merge into a dirty or diverged staging clone.
- `ops/sql/2026-06-06-ausstellung-backend-meta-migration.sql` is applied on staging. Apply it on another environment only after a DB backup and only when that environment should migrate old `iss_surface_mode` rows.
- `ops/sql/2026-06-06-sammlungen-media.sql` and `ops/uploads/2026-06-06-sammlungen-media.*` are applied on staging. Treat them as one deployment unit on any other environment.
- Graph alias, resolver/source-label, search, and video transcript evidence rows are derived DB state. Staging was resynced after deploy; rerun graph sync/verify commands after future graph-affecting imports.
- Template output can still become DB-backed after Site Editor saves; check `wp_template` authority before assuming disk files are live.
- Uploads, mail, Meilisearch, and SQL artifacts are cross-machine concerns; use the repo runbooks before changing staging state.

## Next Action

- Review `/sammlungen/`, `/ausstellungen/`, representative single Ausstellung pages, graph search, related-content previews, and Video CPT transcript-review metaboxes on staging.
- Keep root `TODO.md` for immediate executable work only; broader backlog stays under `docs/project/`.

## Verified

- 2026-06-05 staging artifact application: staging repo fast-forwarded to `a94d06c`; staging DB backup and upload rollback archive were created; upload manifest verified 61 files; SQL import completed; `/repair-cafe/` and `/sammlungen/` returned `200 OK`.
- 2026-06-06 staging deployment: staging repo fast-forwarded to `d367739`; DB backup and upload rollback archive were created under `/srv/industriesalon/stage/backups/20260606-2104-graph-sammlungen-deploy/`; Sammlungen upload artifact checksum passed; 81 manifest files were extracted and verified; Ausstellung migration SQL and Sammlungen media SQL imported successfully.
- 2026-06-06 staging data verification:
  - Old `iss_surface_mode` rows: `0`.
  - Sammlungen attachment rows: `16`; attachment postmeta rows: `32`.
  - Active `front-page` / `page-sammlungen` DB template overrides: `0`.
  - Video transcript evidence refs: `3`.
- 2026-06-06 staging graph verification:
  - `wp iss-graph sync-register`: synced 84 register places.
  - `wp iss-graph sync-archive`: synced 3,048 archive objects.
  - `wp iss-graph sync-aliases`: entities=3547, with_aliases=1824, names=4428.
  - `wp iss-graph sync-search`: search rows=3316.
  - `wp iss-graph sync-video-transcripts`: videos=30, synced=30, mentions=3.
  - Removed stale derived graph entity `3349`, which still pointed at attachment `8654`; valid Ausstellung entity `3367` points at post `25772`.
  - `wp iss-graph verify`: passed with entities=3546, names=8096, identifiers=12936, relations=4764, search=3316, evidence_refs=3.
  - `wp iss-graph drift-check`: passed.
- 2026-06-06 staging frontend verification: `/`, `/sammlungen/`, `/ausstellungen/`, `/wp-content/uploads/2026/06/Uli-Berger.webp`, and `/wp-content/uploads/2026/06/1_TRO-1949-008.webp` returned `200`.
- Local graph alias verification on 2026-06-06:
  - `wp iss-graph sync-register`: synced 84 register places.
  - `wp iss-graph sync-archive`: synced 3,048 archive objects.
  - `wp iss-graph sync-aliases`: entities=3545, with_aliases=1824, names=4428.
  - `wp iss-graph sync-search`: search rows=3315.
  - `wp iss-graph verify`: passed with entities=3545, names=8094, identifiers=12934, relations=4764, search=3315.
  - `wp iss-graph drift-check`: passed.
- Video transcript bridge verification on 2026-06-06:
  - Video inventory: 30 videos (`full=27`, `excerpt=3`).
  - `wp iss-graph sync-video-transcripts`: videos=30, synced=30, mentions=3.
  - `wp_iss_entity_evidence_refs.source_system = video_transcript`: pending entity refs=3.
  - Video CPT edit metabox `iss-graph-video-transcript-review` registered.
- Related-content graph-source verification on 2026-06-06:
  - Führung `12183` with source `entity_place` and target `archivobjekt` returned archive-object material.
  - Profile `24965` with source `entity_person` returned mixed posts/publications/videos.
  - Project `24815` with source `entity` returned mixed project and Führung results.
  - REST preview with JSON body returned `200` and four archive-object preview items.
- Template authority verification on 2026-06-06:
  - `page-sammlungen` resolves as `source=theme`, `id=none`.
  - `front-page` resolves as `source=theme`, `id=none`.
  - All `themes/industriesalon/templates/*.html` files resolve as `source=theme`.
  - No `wp_template_part` rows exist.
  - `/sammlungen/` and `/` returned `200` locally.
- Sammlungen media artifact verification on 2026-06-06:
  - `ops/uploads/2026-06-06-sammlungen-media.tar.gz.sha256` verified locally.
  - Upload archive contains 81 files and matches the 81-line manifest.
  - `ops/sql/2026-06-06-sammlungen-media.sql` passed isolated MariaDB import verification.
  - Import verification returned 16 attachment rows, 32 attachment postmeta rows, and 0 active `front-page` / `page-sammlungen` DB template overrides.
  - SQL artifact contains no local `192.168.2.31` or `localhost` URLs.
- Closeout verification before commit:
  - `git diff --check` passed.
  - Targeted ESLint and `node --check` passed for changed JavaScript.
  - Targeted Stylelint passed for changed Sammlungen and Ausstellung CSS.
  - Docker PHP `-l`, PHPCS, and PHPStan passed for changed PHP files.
  - `parse_blocks()` passed for changed theme templates.
  - `wp iss-graph verify` and `wp iss-graph drift-check` passed.
- `git push origin main` advanced GitHub from `d25efbf` to `9813fa3`; follow-up handoff-only commits record the post-push state.
