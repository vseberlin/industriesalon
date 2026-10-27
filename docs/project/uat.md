# UAT

Work that depends on editor or user-acceptance feedback.

## Editorial Creation Flow

- Revamp editorial creation toward local CPT-first authoring where editors create `Veranstaltung`, `Ausstellung`, or `Führung` once with minimal fields.
- Confirm that created items appear automatically in calendar, timeline, and cards without normal publishing depending on SaaS.

## Editorial Operator Dashboard

The `iss_editorial_operator` role used by Anja opens the native Dashboard after
login. Its standard widgets are replaced by menu cards with short German
descriptions and links. Labels, order, available sections and destinations come
from the current permitted WordPress menu, including the Newsletter
submenu. Dashboard does not link to itself. Personal Profile stays outside the
sidebar and remains available through the account toolbar.

ISS Core uses native dashboard widgets and shares menu URL resolution with
ISS-Betrieb; no separate navigation registry, CSS layout or dashboard route is
introduced. Native widget layout, keyboard controls and responsive behavior
remain available. Administrators and Project UAT keep their existing dashboards
and navigation. No role migration or account/content/media transfer is needed.

Run `wp eval-file tests/e2e/bin/editorial-dashboard.php --use-include` for read-only
menu, widget, destination and role-preservation checks. The CLI may omit upstream
Newsletter admin registration; verify its card and destination in Anja's existing
browser session, along with dashboard navigation and desktop/mobile layout.

The editorial operator has full create/edit/publish/delete access within its
existing content areas, including other authors' published and private content.
Bilder-Sets includes review, media promotion and deletion. JSON section trash
uses the parent content's edit/delete permissions in both the UI and save paths.
Newsletter authoring, deletion, test delivery, sending, scheduling and delivery
pause/resume/cancel use `iss_send_newsletter`; accounts without it remain draft-only.
Subscriber administration, technical settings, archive curation, register tools,
fallback tools and bulk cleanup remain outside this role. Run the isolated
`tests/e2e/bin/editorial-operator.php` harness locally; it never sends mail.
Deploy capability version `2026-10-27-editorial-operator-3` through the existing
owner-setup `check|apply|verify` migration with a private target before-image.
No content/media transfer or uploads artifact is required.

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
