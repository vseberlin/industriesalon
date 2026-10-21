# UAT

Work that depends on editor or user-acceptance feedback.

## Editorial Creation Flow

- Revamp editorial creation toward local CPT-first authoring where editors create `Veranstaltung`, `Ausstellung`, or `Führung` once with minimal fields.
- Confirm that created items appear automatically in calendar, timeline, and cards without normal publishing depending on SaaS.

## Project Dossier Authoring

Projects use the shared `iss-editorial` JSON canvas. The dedicated
`iss_project_uat` role can create drafts, edit its own and other authors' draft
and published projects, and use the WordPress media picker/uploads. Editing an
already published project updates that published content. The role cannot
publish new projects, delete projects, edit private projects, manage Sets/shared
taxonomies, or administer other content/settings/users. The `iss_read_sets`
capability permits browsing existing private Sets and selecting their approved
media in the project editor, without Set creation, review or management rights.
The Archive picker can open existing selections before they are attached to the
project; reading uses the same editable-project check as listing/searching.
The UAT role cannot create Archive selections, alter their members or change
their project links; selecting object references in the project remains allowed.
Standalone Archive and Set workbenches remain restricted.
Project-status assignment is allowed. Personal profile/password/logout remain
available through the account toolbar; the sidebar shows only Projects.

`iss-content` owns the separate project CPT capabilities; `iss-core` owns their
versioned role migration and restricted navigation. Existing roles retain their
previous project access. Menu hiding is accompanied by native CPT/REST
capability checks and a restricted admin-screen allowlist.

For a role-only staging deployment, deploy matching code and run
`wp iss-core caps migrate --apply`; create the tester with only
`iss_project_uat`, without notification flags. Passwords stay outside Git.
The 16 October full refresh copied local accounts and content together; Burmeister
is ID 328 and no invitation was sent. That historical procedure is retired.
Follow `docs/infrastructure/sync.md`: preserve existing target accounts during
content transfers and provision any new user separately. Do not copy local
credentials or infer account replacement from a role/content change.

Authenticated browser checks require `ISS_TEST_CREDENTIALS_FILE`, pointing to a
private mode-0600 JSON file with `username`, `password` and `login_url`. Its URL
origin must match `PLAYWRIGHT_BASE_URL`; no default test password is supplied.
The related-block authentication test disables retained traces/screenshots.

Verify with `wp eval-file tests/e2e/bin/project-uat.php USER_LOGIN`, using a
read-only mount when running through Docker. It exercises native JSON saves,
autosaves, role preservation and denied REST writes using disposable fixtures.
Acceptance still includes browser testing of rich text, image selection,
preview, save/reload, project creation and the restricted sidebar.

## Futura Editor Help Pilot

The local editor now includes searchable **Hilfe**, contextual `?` explanations,
and extended help for Futura's six section types. See the German
[editor guide and practice checklist](../../plugins/iss-editorial/README.md).
Use a disposable draft for practice. Human acceptance: edit text, select/caption
an image, compare presentations, reorder with mouse/keyboard, restore a deleted
section, and explain private draft saving versus the final WordPress action
without verbal assistance. Record unclear wording against the actual control.
This help slice is local only until code synchronization is requested.

## Touchtable Review

- Improve long detail-page previews before broad editor rollout.
- Distinguish narrative pages, empty shell pages, and map-context pages more clearly in review.
- Improve preview readability for very long source pages.
