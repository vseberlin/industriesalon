# ADR: Intentional Vanilla Fallback Mode

Date: 2026-07-02

## Status

Proposed.

## Context

The public architecture is custom: CPTs, editorial JSON documents, dynamic
blocks, occurrence projection, relations, and skins. It works while actively
maintained. The long-term risk is a future operator who can use WordPress but
cannot maintain the custom layer.

Single block templates already render `wp:post-title` and `wp:post-content`,
and dynamic `iss/*` blocks degrade to empty output when unregistered. The
actual point of death is CPT registration: deactivating first-party plugins
makes CPT routes 404 and hides CPT content from admin. Fallback therefore
means keeping visitor-facing core content alive as native Posts/Pages that
survive with zero first-party plugins active.

Fallback is an intentional operational mode, not emergency crash behavior and
not a second canonical content system. Canonical ISS content remains
authoritative in normal mode. A projection layer keeps public core content
available as ordinary Posts/Pages with native categories, excerpts, featured
images, menus, and statuses.

## Decision

Implement an Intentional Vanilla Fallback Mode as a projection coordinated by
`iss-content`.

Selected canonical content is projected into native Posts/Pages. Projected
objects are fallback representations only: safe to overwrite from canonical
data, never treated as canonical.

Fallback mode is activated explicitly by a capability-protected switch. When
inactive, current public routes, templates, renderers, and skins are unchanged
and generated fallback objects stay non-public. When active, the public site
presents a coherent vanilla surface based on Posts, Pages, category archives,
menus, and standard theme templates.

## Key Architectural Rules

- The projection is coordinated in the content layer, not the theme. The
  theme presents content; it does not own continuity logic.
- `iss-content` owns the fallback registry, projection service, native
  category contract, and mode switch. Source-owning plugins contribute their
  own projectors where needed, such as `iss-publications` for Publikationen.
- No MU-plugin migration in v1. Later MU hardening stays deliberately small:
  survival-critical registrations, fallback-mode reader, and projection
  access.
- Grouping uses native Categories, not a custom taxonomy. A custom fallback
  taxonomy would unregister with the plugin, failing in the scenario fallback
  exists for. Machine-readable source identity belongs in post meta, never in
  visible terms.
- Native fallback Categories use fixed `iss-...` slugs such as
  `iss-veranstaltungen`, while labels stay human-readable. This avoids
  collisions with normal editorial categories.
- Public routing in fallback mode reuses the existing
  `iss_content_model_page_claims_public_slug()` mechanism: fallback Pages
  intentionally claim landing route slugs. No template-redirect layer is added
  in v1.
- Projectors register through a filter, mirroring `iss_editorial_formats`.
- Body serialization consumes `iss_editorial_get_read_model()` and is keyed to
  the registered format/section vocabulary, with a text-only default for
  unknown section types.

## Canonical and Fallback Ownership

- Generated objects carry `_iss_fallback_origin = generated` and may be
  overwritten on every projection run. Manual edits to them are not preserved.
- Fallback-native objects carry `_iss_fallback_origin = fallback-native`, are
  created directly in vanilla WordPress, and are never overwritten or deleted
  by projection. They do not sync back into canonical structures.

## Scope V1

Veranstaltungen, Führungen, Ausstellungen, Projekte, Publikationen,
Rückblicke, plus fallback-native Pages for core sections. Publikationen and
Rückblicke are separate projectors: Publikationen are owned by
`iss-publications`, and `rueckblick` has no `post_content` and depends on the
serializer.

Excluded: archive inventory, register objects, graph internals, media
intake/Set state, occurrence data, commerce/payment objects, and skin-specific
layout.

## Freshness

Projection depends on the system whose failure it insures against, so the
snapshot must stay fresh without manual runs. A scheduled sweep keeps
generated objects current. It runs in bounded batches and must not write
during ordinary public page loads.

The implementation must integrate with `iss-core` status and backfill flows.
If reusable extension hooks do not exist yet, the work must either add them to
`iss-core` or extend `iss_core_status_report()` and `iss_core_backfill_all()`
directly as part of the implementation.

## Normal-Mode Visibility

Generated objects are held as `draft` while fallback mode is off, so they
never leak into archives, feeds, search, sitemaps, REST, or normal main
queries. Enabling fallback mode transitions generated objects to their
intended public status; disabling reverts them to `draft`. Canonical content
is never touched by the switch.

Fallback Pages only claim public landing slugs while fallback mode is enabled.
Normal-mode setup must not let fallback Pages accidentally disable CPT
archives or current route ownership.

If first-party plugins are already gone, the vanilla escape hatch is
documented in the operator runbook: filter the Posts list by the native
`iss-...` fallback categories and bulk-edit generated content to Published,
assign the Fallback menu, and set the fallback front Page. This keeps
activation intentional without depending on plugin code.

## Consequences

Positive: future operators keep the site alive with ordinary WordPress skills;
emergency manual publishing via fallback-native posts; projection supports dry
runs, counts, and testing.

Negative: content exists twice; generated posts may tempt edits; mode
switching adds operational responsibility; projected content loses graph and
skin semantics.

Mitigations: visible generated markers and edit warnings, idempotent
projection with dry-run counts, `draft` status in normal mode, fixed
`iss-...` category slugs, narrow v1 scope, and an operator runbook.

## Alternatives Considered

- Full migration to vanilla WordPress: rejected; destroys the structured
  model.
- Silent automatic fallback: rejected; hides failures.
- Theme-based fallback: rejected; the theme presents, it does not own
  continuity.
- Custom fallback taxonomy: rejected; it dies with the plugin and creates a
  parallel visible grouping system.
- Immediate MU-plugin migration: deferred; prove the projection contract in
  `iss-content` first.
