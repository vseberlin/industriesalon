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
taxonomies, or administer other content/settings/users. Private Set operations
retain their existing capability requirements; use the ordinary media picker.
Project-status assignment is allowed. Personal profile/password/logout remain
available through the account toolbar; the sidebar shows only Projects.

`iss-content` owns the separate project CPT capabilities; `iss-core` owns their
versioned role migration and restricted navigation. Existing roles retain their
previous project access. Menu hiding is accompanied by native CPT/REST
capability checks and a restricted admin-screen allowlist.

For staging, deploy the matching code, then run `wp iss-core caps migrate
--dry-run`, `wp iss-core caps migrate`, and `wp iss-core caps report`. Create the
tester locally on the target with only the `iss_project_uat` role. Do not copy
the whole users/roles tables from local. `wp user create` sends no invitation
unless `--send-email` is supplied; omit that flag until invitation is approved.
Passwords stay outside Git. No content migration or uploads artifact is needed
for the role/account change; the project content being reviewed retains its
separate paired migration/upload requirements.

Verify with `wp eval-file tests/e2e/bin/project-uat.php USER_LOGIN`, using a
read-only mount when running through Docker. It exercises native JSON saves,
autosaves, role preservation and denied REST writes using disposable fixtures.
Acceptance still includes browser testing of rich text, image selection,
preview, save/reload, project creation and the restricted sidebar.

## Touchtable Review

- Improve long detail-page previews before broad editor rollout.
- Distinguish narrative pages, empty shell pages, and map-context pages more clearly in review.
- Improve preview readability for very long source pages.
