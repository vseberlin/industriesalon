# JSON Editor Audit (2026-10-16)

Scope: `plugins/iss-editorial` at `d9c4d79` (about 12.1k LOC) plus the contract
edges it shares with `iss-content` and the theme renderers. Criteria:
inconsistencies, dead code, longevity, security, performance.

Method: static review only. No WordPress runtime was available, so performance
statements come from the call graph, not from profiling. Confirm M1–M4 with
Query Monitor on staging (programme page, a large album/Futura edit screen)
before and after each fix.

Status: findings only. Nothing in this document has been changed in code yet.
Active follow-up lives in `TODO.md`.

## Summary

| ID | Severity | Area | One line |
| --- | --- | --- | --- |
| H1 | High | Degraded mode | Without iss-editorial, saving a Veranstaltung downgrades or deletes its document, with no revision copy |
| H2 | High | Revisions | Only the edit-form path snapshots a revision; storage API writers (CLI `--force`, contributions, epochs) overwrite irreversibly |
| H3 | High | Validation | Lossiness check covers a hand-kept key list; some fields are still silently truncated or rewritten |
| M1 | Medium | Performance | Format registry is rebuilt on every call, at least 5 times per document read |
| M2 | Medium | Performance | Programme cards sanitize the full event document once per occurrence row |
| M3 | Medium | Performance | Reference resolution is N+1; no cache priming anywhere |
| M4 | Medium | Performance | 350 ms autosave debounce with an iframe frontend render per pause, plus a save on every editor open |
| M5 | Medium | Longevity | Revision count is unbounded; each revision copies full JSON metas |
| M6 | Medium | Consistency | 54 consumer-specific slugs hardcoded in the generic engine |
| M7 | Medium | Robustness | Edit-version token is invalidated by unrelated background writes |
| M8 | Medium | Longevity | Registry changes freeze posts, including through trashed sections; no stored-document sweep |
| L1–L10 | Low | Mixed | Dead code, duplicated sanitizers, truncated album import, missing plugin headers, hidden coupling |

Security: no exploitable issue found (see the Security section).

## High

### H1. Degraded mode destroys Veranstaltung documents

When `iss-editorial` is inactive, `iss-content` registers the old `Struktur`
meta box and loads `assets/admin-veranstaltung-content.js` (1,238 LOC). Saving
goes through `iss_content_model_save_veranstaltung_content_meta`
(`plugins/iss-content/includes/admin.php:2040`).

- The JS forces `schema_version: 1` (`admin-veranstaltung-content.js:348`,
  `:374`). `compactSection` keeps only kicker/title/body/quote/attribution,
  string items and refs. It drops treatment, links, facts, lead, layout fields,
  skin, `hero_*` and `deleted_sections`.
- If the JS does not run, the hidden field still holds the stored v3 document.
  The engine-less branch of `iss_content_model_sanitize_veranstaltung_content_json`
  (`veranstaltungen-content.php:321`) accepts only v1 and returns `''`.
  The caller then runs `delete_post_meta` (`admin.php:2058`).
- Revision meta registration lives in iss-editorial
  (`iss_editorial_register_revision_meta`), so no revision holds the JSON.

Result: one Aktualisieren in degraded mode loses or downgrades the document.
This contradicts the Vanilla Fallback intent (`vanilla-fallback-mode-sow.md`).

Fix: remove the fallback editor, its v1 sanitizer branch and the write path.
With the engine absent, render a read-only notice and never write the
document key. Removes about 1,238 LOC JS plus the PHP box and sanitizer branch.

Acceptance: with iss-editorial deactivated, editing and saving a Veranstaltung
leaves `_iss_content_json` byte-identical; an e2e check asserts this.

### H2. Revision capture only on the edit-form path

`wp_save_post_revision($post_id)` runs inside the `wp_insert_post_data`
filter (`revisions.php:300`). `iss_editorial_save_document` and
`iss_editorial_persist_document` (`storage.php:895`, `:905`) never snapshot.
Writers that bypass the form therefore overwrite without history:

- CLI `ausstellung|projekt|fuehrung|place-import-candidate --force`
  (`cli.php:882`, `:1057`, `:1474`, `:1620`)
- `iss_editorial_set_document_enabled` callers in iss-content contributions
- the `iss_editorial_document_writer` epochs wrapper in the register plugin

Fix: snapshot the current state inside `iss_editorial_persist_document` before
calling the writer, and drop the duplicate call from the form filter.

Acceptance: a CLI `--force` replace produces a restorable revision.

### H3. Silent lossy normalization

`iss_editorial_validate_document` promises to reject edits before
normalization discards content. It enforces this only for explicit lists
(`storage.php:824` for collections, `:835` for enumerations). Still silent:

- `contribution_question` truncated to 240 characters (`storage.php:599`)
- `text_bild_reihe` / `map_img` item `media_refs` sliced to one (`:222`)
- `anchor` rewritten by `sanitize_title` (`:500`)
- title/kicker tags and newlines stripped by `sanitize_text_field` (`:489`)

Every new field repeats the risk. Fix: one generic comparison of scalar leaves
between input and normalized output, with an explicit opt-out list for
deliberate normalizations (whitespace, numeric strings). Reject with the
field path on difference.

## Medium

### M1. Registry rebuilt on every call

`iss_editorial_get_registered_formats()` (`formats.php:6`) has no memo. Each
call runs six `iss_editorial_formats` callbacks (iss-content x4, publications,
theme), rebuilds the gesture vocabularies (about 190 `__()` calls) and
re-sanitizes every label. `get_format`, `get_document_meta_key`,
`get_skin_meta_key`, `get_empty_document` and `get_format_for_post` all route
through it, so one document read costs at least five rebuilds. It also runs on
every `_edit_lock` write via `iss_editorial_track_lock_session` →
`iss_editorial_uses_canvas` (heartbeat).

Fix: static memo once `did_action('init')` has fired (the normalizer filters
on `post_type_exists`, so caching earlier would freeze an empty registry), plus
an explicit flush function for tests.

### M2. Programme hot path

`industriesalon_event_ticket_action`
(`themes/industriesalon/includes/veranstaltungen-render.php:499`) is called
per programme entry (`:543`), i.e. per occurrence row, and also for
ausstellung rows. Each call runs `iss_editorial_get_document` and therefore a
full `sanitize_document` (kses per body, `get_permalink` per link) only to find
ticket links in `material` sections. It passes `get_post_type($id)` as the
format slug, which works only because the slugs coincide.

Fix: memoize per post ID per request, or read links from the raw decoded
document, and resolve the format via `iss_editorial_get_format_for_post`.

### M3. N+1 in reference resolution

`iss_editorial_get_read_model` resolves each object/media reference one by
one (`references.php:7`): `get_post`, thumbnail URL, attachment metadata. No
`_prime_post_caches` / `update_meta_cache` call exists in the theme or the
editorial plugins. Without a persistent object cache that is about three to
four queries per reference.

Fix: collect IDs per document and prime post and meta caches once.

### M4. Autosave cadence and preview reloads

With live preview the debounce is 350 ms (`assets/admin.js:595`). Each pause
causes an AJAX draft save (version check, full validation,
`create_post_autosave`, meta writes, two `saved_token` hashes) and a full
frontend render in the preview iframe. The editor also saves and renders once
on every open (`admin.js:316`), even without edits.

Fix: 1,000–1,500 ms debounce; reload the iframe on blur/idle instead of while
typing; skip the open-time save when the state equals the stored base.

### M5. Unbounded revisions

Each Update can create two revisions (the pre-update snapshot in
`revisions.php:300` plus core's post-update revision). Each copies every
revisioned meta key, including the full document JSON. There is no
`wp_revisions_to_keep` filter or `WP_POST_REVISIONS` setting in the repo.
Large album/Futura documents bloat `wp_postmeta`, and the revision diff walks
the whole document.

Fix: cap revisions per format post type (for example 30) and record the
policy in the runbook. Re-check after H2 moves the snapshot.

### M6. Engine knows its consumers

The generic engine hardcodes 54 consumer slugs (`assets/admin.js` 37,
`includes/storage.php` 12, `includes/admin.php` 5). Examples:

- fuehrung/projekt rich-text allowlist (`storage.php:454`)
- `text_bild_reihe` / `map_img` item shape (`storage.php:530`, `:814`)
- `landing.feature` media layouts (`storage.php:608`)
- `era_key` / `function_key` vocabularies (`storage.php:574`, `:580`), which
  are place-domain data
- `openingSkin` mapping projekt → buehne (`admin.php:380`)
- `format === 'projekt'` rail control in `admin.js:293`

Fix: express these as registry fields owned by iss-content (item shape,
allowed values per field, opening skin, owner controls). Mechanical, can be
done gesture family by gesture family.

### M7. Version token invalidated by unrelated writes

`iss_editorial_saved_token` (`revisions.php:53`) hashes `post_modified_gmt`
plus every revisioned meta of the post type. Any background write to an open
post (REST menu_order reorder, cron sweeps, relations/graph resync) makes the
editor's next autosave fail with "Inzwischen wurde eine neue Fassung
gespeichert". This will surface once the planned relations cron sweep runs.

Fix: hash only editorial-owned keys plus title and excerpt.

### M8. Registry evolution freezes posts

An unknown section type, slot or treatment anywhere in `sections` or
`deleted_sections` makes validation fail. The editor then becomes inert and
every save, including recovery discard, is rejected (`config.validationError`
in `admin.js`). Freezing is the correct failsafe, but:

- trashed sections are validated against the current registry, so a section
  deleted long ago can lock a page;
- `deleted_sections` never expires (only a `manage_options` purge);
- `wp iss-editorial registry-check` validates the registry only, not stored
  documents.

Fix: validate trash leniently (keep unknown entries as opaque data) and add
`wp iss-editorial validate-all` as a pre-deploy gate after registry changes.

## Low

- **L1 Dead code.**
  - `iss_editorial_add_meta_boxes`: no-op hook (`admin.php:17`).
  - `iss_editorial_post_type_supports_format`: unused (`formats.php:304`).
  - `iss_editorial_render_route_relation_hidden_fields`: unused (`admin.php:77`).
  - Unused `$route_config` and `$enabled` in `render_main_canvas`
    (`admin.php:99`–`100`); unused `$post_type` in `save_meta_box` (`:478`).
  - `_autosave` meta deletion (`admin.php:499`, CLI): nothing writes that key.
  - Orphan CSS from the pre-workspace modal in `assets/admin.css`:
    `.iss-editorial-modal-open`, `.iss-editorial-insert-preview*`,
    `.iss-editorial-gesture__*`, `.iss-editorial-opening-badge`.
- **L2 Payload.** The edit page ships the document three times (hidden input,
  `data-document`, localized `document`) and the format sections twice.
  Localized `document`, `sections`, `enabled` and `workspace` are never read by
  JS. Keep `document` until after `iss_editorial_admin_settings` (the
  Veranstaltung filter reads it), then drop it.
- **L3 `enabled` flag.** The canvas always posts `1`; disabling is reachable
  only via CLI or tests.
- **L4 CLI importers.** About 1,500 LOC of one-shot Gutenberg importers in the
  generic engine. Once those migrations are closed, archive them under
  `tools/` or move them to iss-content.
- **L5 Three divergent href checks.** PHP `iss_editorial_body_href_is_safe`
  and `admin.js` `isSafeRichTextHref` do not strip control characters;
  `rich-text.js` `safeHref` does. Server-side kses (`wp_kses_bad_protocol`) is
  the real gate, so this is not exploitable, but it is drift. Drop the PHP
  stripper and route v1 through `rich-text.js`.
- **L6 Album import truncation.** Editorial-set import requests `perPage=120`
  (`admin.js:2342`); the service clamps at 120
  (`editorial-sets-service.php:631`); the client ignores `total`. Sets over 120
  are silently cut while the status reports success. Page through or warn.
- **L7 Missing plugin headers.** No `Requires PHP` / `Requires at least`. The
  code needs PHP 8.1+ (`array_is_list`) and WP 6.x APIs (HTML Tag Processor,
  autosave meta). Version constant still `0.1.0`.
- **L8 Hidden coupling.** Text profiles depend on a caller-mutated
  `$format['document_version']` (`storage.php:702`, `:749`; `rich-text.php:14`).
  A new caller of `iss_editorial_sanitize_section` silently gets v1 rules. Pass
  the version explicitly.
- **L9 Persisted preview caches.** Hydration writes thumbnail/width/height/mime
  into stored JSON (fill only when empty), so editor previews go stale after
  WebP conversion or thumbnail regeneration, and the values are revisioned. The
  frontend correctly uses resolved data. Hydrate for transport only and strip
  on save.
- **L10 Minor robustness.**
  - `canPurgeDeletedSections` is enforced client-side only (revisions mitigate).
  - `iss_editorial_validate_post_update` matches by format slug, not by
    `post_ID === $_POST['post_ID']`; a cascaded save of another same-format post
    during the request would `wp_die` 409. No current trigger found; one-line
    guard.
  - Core hooks registered as closures (`revisions.php`, `preview.php`) cannot
    be removed by tests or other plugins.

## Security

No exploitable issue found:

- Nonces and capability checks on the AJAX draft save, the canonical save and
  the preview routes.
- The embedded preview is bound to the authenticated snapshot token.
- DOM is built with `createElement`/`textContent`; `innerHTML` only behind the
  template sanitizers or on static markup.
- `postMessage` handlers check origin, source and token.
- kses runs on all rich fields server-side.
- `sandbox="allow-scripts allow-same-origin"` on the preview iframe is not a
  security boundary; the code comment already says so.

## What is solid

Read-lenient / write-strict split, drafts on core autosave revisions, the
lock/session handling, and the volume of storage/lock/UI checks.

## Fix order

1. H1: remove the fallback writer (small; removes a data-loss path).
2. H2: revision snapshot in `iss_editorial_persist_document`.
3. M1: registry memo (biggest site-wide performance win).
4. M2 + M3: per-request memo and cache priming.
5. M4: autosave debounce and preview reload policy.
6. M8: `validate-all` CLI and lenient trash validation.
7. M5: revision cap.
8. H3: generic lossiness check.
9. M6: move hardcoded slugs into the registry (ongoing).
10. L1–L10: cleanup batch, together with the `admin.js` picker split already
    in `TODO.md`.
