# Current Handoff

Updated: 2026-06-28

Current work only. Completed checkpoint history belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current Work

- Editorial admin simplification SOW is implemented for compatible classic/editorial edit screens using the shared `iss-content` dashboard assembly layer.
- Converted classic/editorial screens: `veranstaltung`, `projekt`, `ausstellung`, `publication`, `fuehrung`, and `rueckblick`.
- Existing storage/render owners are preserved:
  - `iss-editorial` keeps JSON composition storage;
  - `iss-content` keeps Veranstaltung, Projekt, Ausstellung, Video, Set, and CPT contracts;
  - `iss-publications`, Führung module, `iss-relations`, `iss-graph`, and `iss-archive` keep their own controls/storage.
- JSON composition save behavior now uses the normal WordPress Update action; the separate `iss-editorial` REST save/autosave endpoints were removed.
- Video now has a structured transcript JSON contract:
  - `_iss_video_transcript_json` is the active transcript authority when present;
  - existing local Video CPT body transcripts were parsed into `ops/sql/2026-06-28-video-transcript-json.sql`;
  - the Video Gutenberg body canvas is hidden, while the title/sidebar/metaboxes and normal Update action remain active;
  - public video rendering uses JSON first and falls back to legacy `post_content`.

## Preserve

- Do not force the classic `titlediv`/postbox dashboard mover onto Gutenberg screens.
- `page` and `post` remain outside the SOW for now; `post` is fallback-only and unused.
- Video transcript transfer is DB postmeta only; no upload artifact is required.
- Do not revert unrelated local untracked files:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Staging Instructions

Local checkpoint is not pushed yet. To share it, push from local:

```bash
git push origin main
```

On staging after the push:

```bash
git fetch origin --prune
git status --short
git merge --ff-only origin/main
```

After the code is deployed/fast-forwarded, apply the video transcript artifact with the staging WP-CLI wrapper, for example:

```bash
wp db query < ops/sql/2026-06-28-video-transcript-json.sql
wp db query "SELECT COUNT(*) AS migrated_rows FROM wp_postmeta WHERE meta_key = '_iss_video_transcript_json';"
```

Expected transcript row count from this artifact: `27`.

## Verify On Staging

- Open a Video edit screen, e.g. `21116`: transcript JSON editor should show segment rows; the old Gutenberg body canvas should be hidden.
- Open the matching frontend video route: transcript timecode anchors and the left rail should render.
- Spot-check one converted classic/editorial screen such as Veranstaltung and one `iss-editorial` screen to confirm normal WordPress Update still persists JSON changes.
- `videos.php` still has pre-existing PHPCS escaping findings in the renderer; the new helper/admin files pass targeted checks.

## Verified Locally

- PHP: Docker `php -l` for changed plugin PHP files.
- JS/CSS: `node --check`, targeted ESLint, targeted Stylelint.
- PHPStan: targeted checks for video transcript helper and video renderer passed.
- PHPCS: new helper/admin files passed; `videos.php` reports the same 24 pre-existing renderer escaping findings as `HEAD`.
- WP-CLI: video transcript JSON rows applied locally, count `27`; sample video `21116` renders JSON transcript with timecode anchors.
- Browser: sample Video edit screen shows 13 JSON segment rows and hides the Gutenberg body canvas; frontend sample renders transcript anchors and rail.
- Save probe: temporary local video wrote one JSON segment through the normal save handler and was deleted.
- `git diff --check` passed.
- Git exchange before commit: local `HEAD=890a358`, `origin/main=053ac70`; local branch ahead-only and not pushed.
