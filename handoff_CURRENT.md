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
- Related content now resolves through graph entity relatedness before the old
  place-branch fallback for default current-post blocks. `iss-relations`
  accepts generic entity targets while keeping old `place_id` payloads working.
- Veranstaltung `iss_primary_place_id` is registered and saved as native
  integer meta. `iss-graph` harvests that field into `content_native` venue
  edges and leaves admin-curated person/organization rows in `content_admin`.

## Preserve

- Do not force the classic `titlediv`/postbox dashboard mover onto Gutenberg screens.
- `page` and `post` remain outside the SOW for now; `post` is fallback-only and unused.
- Video transcript transfer is DB postmeta only; no upload artifact is required.
- Graph/native relation code changes require no SQL or upload artifact, but
  existing target content needs a graph content sync after deploy.
- Do not revert unrelated local untracked files:
  - `iss-exhibition-composition-add.md`
  - `themes/industriesalon/theme2.json`

## Staging Instructions

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

Refresh graph content entities so existing Veranstaltung venue fields are
harvested into `content_native` relations:

```bash
wp iss-graph sync-content
```

`wp iss backfill-all` is also valid if the whole shared backfill suite should be
reconciled on the target.

## Verify On Staging

- Open a Video edit screen, e.g. `21116`: transcript JSON editor should show segment rows; the old Gutenberg body canvas should be hidden.
- Open the matching frontend video route: transcript timecode anchors and the left rail should render.
- Spot-check one converted classic/editorial screen such as Veranstaltung and one `iss-editorial` screen to confirm normal WordPress Update still persists JSON changes.
- Open a Veranstaltung with an Atlas-Ort, save normally, then confirm related
  content still resolves without editing the related-places box.
- `videos.php` still has pre-existing PHPCS escaping findings in the renderer; the new helper/admin files pass targeted checks.

## Verified Locally

- PHP: Docker `php -l` for changed plugin PHP files.
- JS/CSS: `node --check`, targeted ESLint, targeted Stylelint.
- PHPStan: targeted checks for video transcript helper and video renderer passed.
- PHPCS: new helper/admin files passed; `videos.php` reports the same 24 pre-existing renderer escaping findings as `HEAD`.
- WP-CLI: video transcript JSON rows applied locally, count `27`; sample video `21116` renders JSON transcript with timecode anchors.
- Browser: sample Video edit screen shows 13 JSON segment rows and hides the Gutenberg body canvas; frontend sample renders transcript anchors and rail.
- Save probe: temporary local video wrote one JSON segment through the normal save handler and was deleted.
- WP-CLI: temporary local Veranstaltung `11216` with place `17960` produced one
  `content_native` graph place edge with role `venue`; original meta was
  restored and the post was resynced.
- WP-CLI: the one-time Veranstaltung primary-place migration populated
  `iss_primary_place_id` on 25/25 local Veranstaltungen; serial
  `wp iss-graph sync-content` produced 25 `content_native` venue edges.
- Related-content graph smoke: publication `12983` had no place items but
  resolved graph-related posts `4206`, `13379`, and `12985`.
- Static map contract check passed.
- `git diff --check` passed.
- Git exchange before commit: local `HEAD=890a358`, `origin/main=053ac70`; local branch ahead-only and not pushed.
