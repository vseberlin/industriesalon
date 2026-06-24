# Operations Admin Permissions SOW

This SOW defines the reusable permission model for custom WordPress admin
screens before more operations screens are added.

## Goal

Build one code-owned capability contract for operational admin surfaces. The
contract must let the project grant narrow access to editorial operations
without making every trusted user an administrator.

## Scope

The Operations admin family should cover these current and near-term surfaces:

- Industriesalon Steuerung
- Hinweise
- Register
- Sets
- Rueckblicke
- Publications
- Archiv
- Newsletter
- technical operations tools such as sync, import, repair, and cleanup screens

The menu grouping can be broad, but the underlying permissions must stay
operation-specific.

## Ownership

- `iss-core` should own shared capability registration and helper conventions.
- CPT-owning plugins should define CPT-specific capabilities for their own post
  types.
- Custom-table and workflow plugins should define explicit workflow capabilities
  for their admin pages, REST routes, AJAX handlers, CLI-adjacent actions, and
  save/promote/delete operations.
- The theme should not own permission logic for operational workflows.

Third-party role plugins may be used only as assignment UI. They must not be the
source of truth for what capabilities exist or what operations they authorize.

### Cross-plugin capability assembly

Because ownership is split across plugins, role-to-capability assembly must not
assume load order. A role like Operations manager needs capabilities that other
plugins register, so granting them at the wrong moment silently grants nothing.

- Each plugin registers the capabilities it owns into a shared registry or a
  well-known filter exposed by `iss-core` (for example `iss_register_caps`).
- `iss-core` assembles role-to-capability grants after `plugins_loaded`, once
  every owning plugin has had a chance to declare its capabilities.
- The migration must never grant a capability that has not been declared. The
  diagnostic report should flag any role grant that references an unknown
  capability.

## Principles

- Check capabilities, not role names.
- Gate the menu and the action separately.
- Re-check permissions on every write path, not only when rendering a screen.
- Keep destructive, public-facing, and sync/repair actions separate from normal
  editing.
- Combine custom workflow caps with WordPress object checks when a post is in
  scope, for example `edit_post` plus a project capability.
- Prefer native WordPress CPT capability mapping for CPTs and explicit `iss_*`
  capabilities for custom tables and workflow actions.
- Avoid one catch-all operations capability except for opening the grouped menu.
- Deny by default: a new admin screen, REST route, AJAX action, or write handler
  must fail closed if it does not declare a required capability.
- Never register operational actions through `wp_ajax_nopriv_*`, and never set a
  mutating REST route's `permission_callback` to `__return_true`. Both bypass the
  entire model.
- Treat UI visibility as convenience only. Hidden UI is not authorization.
- `iss_access_operations` controls menu visibility only. Every page and action
  callback gates on its own narrow capability regardless of the menu cap, so
  direct navigation by a user who holds the narrow cap but not the menu cap is
  still correctly authorized.
- Capabilities live in the database (`wp_user_roles`), not in code. They do not
  travel with a code deploy; every environment must run the migration. See the
  Implementation Plan for the version-gated migration requirement.
- Capability slugs stay in English and remain stable for the life of the
  project. Only role display names are localized into German UI. German
  capability slugs are a long-term maintenance trap.
- Restricted roles still need the WordPress `read` capability so their users can
  enter wp-admin at all. A role with only operational caps and no `read` cannot
  load the dashboard.

## Capability Draft

Shared access:

- `iss_access_operations`: can see the grouped Operations menu.

Central facts and notices:

- `iss_manage_steuerung`: can edit central address, opening-hours, contact, and
  site-control facts.
- `iss_manage_hinweise`: can manage notices/hints.

Register and archive:

- `iss_manage_register`: can manage register objects and register admin tools.
- `iss_manage_archive`: can manage archive curation surfaces.
- `iss_import_archive`: can run archive import jobs.

Editorial media Sets:

- `iss_create_sets`: can create Sets and attach incoming media/items.
- `iss_edit_sets`: can organize Sets and edit non-review metadata.
- `iss_review_sets`: can review, reject, restore, annotate, and batch status Set
  items.
- `iss_promote_media`: can promote approved media into public editorial
  references or WordPress attachments.
- `iss_delete_sets`: can delete Sets or Set items where deletion is allowed.
- `iss_cleanup_sets`: can run decay/quarantine cleanup jobs for Sets.

Editorial/publication surfaces:

- `iss_manage_rueckblicke`: can manage Rueckblick editorial objects.
- `iss_manage_publications`: can manage Publications.
- `iss_manage_newsletter_content`: can manage Newsletter content or drafts.
- `iss_send_newsletter`: can send or schedule public Newsletter dispatches.

Technical operations:

- `iss_sync_external_sources`: can run synchronization with external sources.
- `iss_repair_data`: can run repair and data consistency tools.
- `iss_cleanup_data`: can run destructive or semi-destructive cleanup tools.
- `iss_run_diagnostics`: can view and run read-only diagnostics.

For CPTs, define mapped primitive/meta capabilities instead of relying on
`edit_posts`, for example:

- `edit_rueckblick`, `edit_rueckblicke`, `edit_others_rueckblicke`,
  `publish_rueckblicke`, `delete_rueckblicke`
- equivalent mapped caps for Publications, Hinweise, Register objects, and
  Newsletter if they are CPT-backed

CPT capability mapping notes:

- Register CPTs with `'capability_type'` and `'map_meta_cap' => true`, then
  declare the explicit `'capabilities'` array. Do not leave it implicit.
- `create_posts` defaults to the plural `edit_*` cap unless explicitly mapped.
  If "can create" must be a distinct gate from "can edit", set
  `'capabilities' => ['create_posts' => 'create_rueckblicke']`. Otherwise editing
  implies creating.
- The `read_private_*`, `delete_published_*`, `delete_others_*`, and similar
  variants are omitted from the draft above. Omitting them is fine if intended,
  but the decision should be deliberate, because unmapped variants fall back to
  defaults that may be broader or narrower than expected.

## Role Draft

Administrator:

- All project capabilities.

Operations manager:

- Access Operations.
- Manage Steuerung, Hinweise, Register, Sets, Rueckblicke, Publications, Archiv,
  and Newsletter content.
- Promote media.
- Send newsletters only if explicitly trusted.
- Use selected operations tools.

Curator/editor:

- Access Operations.
- Manage assigned editorial CPTs.
- Create, edit, and review Sets.
- Promote media only if explicitly trusted.
- No technical sync/repair tools by default.

Reviewer:

- Access Operations.
- Review Sets and add notes/status decisions.
- No promotion, delete, cleanup, sync, newsletter send, or central-fact edits.

Intake helper:

- Access only the intake/Set surfaces needed for upload triage.
- Create or attach items to Sets where appropriate.
- No promotion, public publishing, central-fact edits, delete, cleanup, sync, or
  repair tools.

Technical maintainer:

- Access Operations.
- Use diagnostics, sync, repair, import, cleanup, and consistency tools.
- Content publishing permissions only when separately granted.

Every role above that needs wp-admin access must also retain the native `read`
capability.

## Helper Conventions

To make the correct pattern the path of least resistance and keep the bus factor
low, `iss-core` should expose two helpers that every Operations surface uses
instead of open-coding checks:

- A screen/action guard, for example `iss_require_cap( $cap, $object_id = null )`,
  that performs `current_user_can()` and `wp_die()`s with a 403 on failure. Page
  callbacks and write handlers call this first.
- A `permission_callback` factory, for example `iss_cap_check( 'iss_review_sets' )`,
  that returns a closure suitable for `register_rest_route()`. This removes the
  temptation to use `__return_true` and standardizes REST gating.

Hand-rolled `current_user_can()` calls are still allowed, but new code should
reach for the helpers first so a future maintainer cannot forget the 403 path.
Request integrity remains a separate required check: form and AJAX writes still
need nonces, and REST writes still need the authenticated REST request flow.

## Implementation Plan

1. Inventory current admin screens, CPTs, REST routes, AJAX handlers, save hooks,
   promotion actions, cleanup actions, imports, sync jobs, repair tools, and
   diagnostics.
2. Add a small `iss-core` capability registry with constants/helpers for shared
   `iss_*` capabilities, plus the screen guard and `permission_callback` factory
   described in Helper Conventions.
3. Convert operational CPT registrations to mapped custom capabilities with
   `map_meta_cap => true`, declaring the explicit `capabilities` array including
   `create_posts` where a distinct create gate is wanted.
4. Require every Operations screen and action to declare a capability. Missing
   declarations must fail closed.
5. Gate grouped Operations menu access through `iss_access_operations`.
6. Gate every submenu/page callback with the narrow capability for that surface.
7. Gate every write action, REST route, AJAX action, promotion, delete, cleanup,
   import, repair, and sync operation with the same narrow capability.
8. For every form/AJAX/REST write path, check both authorization and request
   integrity: `current_user_can(...)`, nonce or REST `permission_callback`, and
   object ownership/status checks where relevant.
9. Add an idempotent, version-gated role migration. It runs on activation and on
   upgrade behind a stored `iss_caps_version` option, never unconditionally on
   `init`. It grants administrator all project caps and creates or updates only
   approved project roles. Because caps live in the database, every environment
   (staging and production) must run this migration; a code deploy alone does not
   apply capability changes and can leave even administrators locked out of new
   surfaces.
10. Add migration safety: dry-run output, backup/report of current role caps,
    no removal of unknown third-party capabilities, and management only of
    project-owned `iss_*` capabilities unless explicitly stated.
11. Add WP-CLI or diagnostic output that reports project roles, assigned caps,
    missing capabilities, capabilities present in code but not in the current
    database, and screens/actions without declared caps. Lean on native commands
    (`wp role list`, `wp cap list <role>`, `wp user list-caps <user>`) where they
    suffice; custom output is only needed for the "declared caps" coverage that
    WP-CLI cannot infer.
12. Add audit logging for destructive and public-facing actions. A failed log
    write must not silently block the action it records, but the log failure must
    itself be surfaced or recorded so the gap is visible.
13. Verify admin menus and action denial paths with at least one administrator
    and one restricted role.

## Recommended Rollout Slices

Slice 0: inventory only.

- List current admin menus, CPTs, REST routes, AJAX handlers, save hooks, Set
  actions, sync/import/repair tools, and any `manage_options` checks.
- Record the intended owner plugin and required capability for each surface.
- Do not change runtime behavior in this slice.

Slice 1: `iss-core` capability contract.

- Add the shared capability registry/filter.
- Add capability constants or helper accessors.
- Add `iss_require_cap()` and `iss_cap_check()` helpers.
- Add a diagnostic command/report for declared caps and unknown grants.
- No role mutation yet except temporary administrator-only smoke checks if
  needed locally.

Slice 2: screen and action declaration.

- Convert existing Operations screens/actions to declare narrow required caps.
- Fail closed for missing declarations in new Operations registration helpers.
- Keep current administrator access working while diagnostics prove coverage.

Slice 3: role migration.

- Add the version-gated `iss_caps_version` migration.
- Grant administrator all declared project caps.
- Create or update approved project roles.
- Include dry-run/report output and preserve unknown third-party caps.

Slice 4: CPT capability mapping.

- Convert operational CPT registrations to explicit mapped capabilities.
- Handle `create_posts`, private reads, delete variants, and publish variants
  deliberately per CPT.
- Verify edit/create/publish/delete behavior with administrator and restricted
  roles before touching production.

Slice 5: audit logging.

- Log public-facing and destructive actions first: media promotion, delete,
  restore, cleanup, import, sync, repair, newsletter send, Steuerung changes,
  and capability migrations.
- Choose table or structured file storage before implementation.

Slice 6: restricted-role verification.

- Browser-smoke administrator, operations manager, reviewer, and intake helper.
- Test direct URL access, form submits, AJAX, REST, and batch actions.
- Confirm negative paths deny promotion, delete, cleanup, sync, repair,
  newsletter send, and central-fact edits without the exact capability.

## Authorization Requirements

Every protected operation must check authorization at the point of execution, not
only when the UI is rendered.

Required checks:

- Admin page render: required capability for the screen.
- Form submit: required capability plus nonce.
- AJAX action: required capability plus nonce. Operational actions are registered
  on `wp_ajax_*` only, never `wp_ajax_nopriv_*`.
- REST route: `permission_callback` using the required capability, never
  `__return_true`. Cookie-authenticated REST requests must send the `wp_rest`
  nonce as `X-WP-Nonce`; without it the request is treated as logged-out and the
  capability check correctly fails closed.
- Object mutation, CPT-backed: workflow capability plus the native mapped object
  capability, for example `iss_promote_media` plus `edit_post` resolved through
  `map_meta_cap`.
- Object mutation, custom-table rows: workflow capability plus an explicit,
  hand-written ownership/status guard. Sets and the archive rows are not WP
  posts, so `current_user_can( 'edit_post', $id )` does nothing for them. The
  row-level check is bespoke code and must be written for every custom-table
  write path.
- Batch actions: check the batch capability and each affected object where
  object-level ownership/status matters.

## Audit Logging

The implementation should log at least the following action classes:

- media promotion
- delete and restore actions
- cleanup/quarantine jobs
- import and sync jobs
- repair tools
- newsletter send or schedule
- central facts changes in Steuerung
- capability/role migration runs

Minimum log fields:

- timestamp
- user ID
- capability checked
- action name
- affected object IDs or job ID
- result: allowed, denied, failed, completed

The first implementation can use a simple project log table or structured file
log, provided it is searchable by date, user, and action. Logging must not be on
the critical path of the action: a failed log write is recorded or surfaced but
does not abort the operation. If a file log is used, define rotation so it cannot
grow unbounded on shared hosting.

## Verification

Minimum checks for implementation:

- PHP lint for changed plugin files.
- Targeted PHPCS/PHPStan for changed capability/admin code.
- Runtime role/capability dump through WP-CLI (`wp role list`,
  `wp cap list <role>`, `wp user list-caps <user>`).
- Diagnostic report for screens/actions without declared capabilities and for
  caps present in code but missing from the current database.
- Admin screen smoke tests for administrator, operations manager, reviewer, and
  intake helper.
- Negative tests confirming restricted roles cannot promote, delete, clean up,
  sync, repair, send newsletters, or edit central facts unless granted the
  specific capability.
- Negative tests confirming no operational action is reachable via
  `wp_ajax_nopriv_*` and no mutating REST route uses `__return_true`.
- REST/AJAX save tests where relevant, because hidden UI is not sufficient
  security.
- Migration dry-run test confirming that unknown third-party capabilities are
  not removed.
- Migration re-run test confirming idempotency: a second run behind the same
  `iss_caps_version` makes no changes.

## Non-Goals

- Do not introduce a third-party plugin as the permission authority.
- Do not add a parallel user system outside WordPress capabilities.
- Do not solve newsletter provider selection here.
- Do not combine all operations into one `manage_options` replacement.
- Do not use role names directly in screen or action authorization checks.
- Do not solve concurrency/locking for long-running destructive jobs here unless
  decided otherwise below. Two maintainers triggering the same import or cleanup
  is a data-integrity concern, not a permissions one.

## Open Decisions

- Final CPT list and owning plugin for Publications and Newsletter.
- Whether Rueckblick remains only an editorial document target or also needs a
  separate CPT-level role.
- Exact production role names in German UI.
- Whether role assignment should stay code-only or use a third-party UI for
  day-to-day assignment after the code contract exists.
- Where audit logs should live: custom table, structured file log, or both.
- Whether newsletter sending is handled inside WordPress or delegated to an
  external provider with only content approval stored in WordPress.
- Deactivation and uninstall behavior for `iss_*` capabilities: leave roles
  intact on deactivate; decide whether uninstall strips project-owned caps and
  removes project roles, or leaves them for reinstall.
- Whether long-running destructive jobs (archive import of ~11k objects, cleanup)
  need an explicit lock to prevent concurrent runs, or whether concurrency stays
  an accepted out-of-scope risk.
