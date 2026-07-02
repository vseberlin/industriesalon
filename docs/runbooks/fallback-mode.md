# Intentional Vanilla Fallback Mode Runbook

Date: 2026-07-02

## Purpose

Fallback mode is an intentional continuity mode for operating the site as a
simple WordPress site when the custom ISS architecture can no longer be
maintained. It is not a silent failure mode and not a replacement for the
canonical content model.

Normal mode keeps the current ISS architecture authoritative. Fallback mode
uses generated and fallback-native Posts/Pages, native Categories with
`iss-...` slugs, the `Fallback` menu, and a fallback front Page.

## Normal Operation

Keep fallback projection fresh before it is needed:

```sh
wp iss fallback dry-run
wp iss fallback project --all
wp iss fallback status
```

Generated fallback objects stay `draft` while fallback mode is disabled. They
should not appear in normal public archives, feeds, search, sitemap output,
REST, or main queries.

## Category Contract

The fallback grouping uses native WordPress Categories so the content remains
usable if first-party plugins are later deactivated.

| Label | Slug |
| --- | --- |
| Veranstaltungen | `iss-veranstaltungen` |
| Führungen | `iss-fuehrungen` |
| Ausstellungen | `iss-ausstellungen` |
| Projekte | `iss-projekte` |
| Publikationen | `iss-publikationen` |
| Rückblicke | `iss-rueckblicke` |
| Aktuelles | `iss-aktuelles` |
| Seiten | `iss-seiten` |

Do not rename these slugs. Labels may be translated or adjusted for display
only if the slug remains stable.

## Generated vs Fallback-Native

Generated objects are projections from canonical ISS content. They are marked
with `_iss_fallback_origin = generated`. Edits may be overwritten by the next
projection run.

Fallback-native objects are created directly in vanilla WordPress. They are
marked with `_iss_fallback_origin = fallback-native`. Projection must never
overwrite or delete them.

If manual fallback-only content is needed, create a separate fallback-native
Post/Page instead of editing a generated object.

## Enable Fallback Mode

Use the supported command or admin status screen:

```sh
wp iss fallback enable
```

The enable path must:

- publish generated objects to their intended public status
- activate fallback Pages for route ownership
- switch primary navigation to the `Fallback` menu
- set the fallback front Page if configured
- log the previous mode, new mode, timestamp, and actor

After enabling, verify:

- the front page loads
- section URLs for Veranstaltungen, Führungen, Ausstellungen, Projekte,
  Publikationen, Rückblicke, Aktuelles, and Seiten resolve
- native Category archives list fallback content
- generated content is readable with standard WordPress templates

## Disable Fallback Mode

Use the supported command or admin status screen:

```sh
wp iss fallback disable
```

The disable path must:

- return generated objects to `draft`
- restore the previous primary navigation assignment
- restore previous front-page settings
- return fallback Pages to their normal-mode non-claiming state
- leave canonical ISS content untouched

After disabling, verify current ISS routes and renderers work as before.

## Zero-Plugin Escape Hatch

If first-party plugins are already deactivated, the supported switch is no
longer available. Activation is still intentional through vanilla WordPress:

1. In Posts, filter by the native `iss-...` fallback Categories.
2. Bulk-edit generated fallback content to Published.
3. Assign the `Fallback` menu as the primary navigation menu.
4. Set the fallback front Page under WordPress reading settings.
5. Use Pages, Posts, Media, Categories, Tags, and Menus for ongoing operation.

This path does not restore custom CPTs, graph relations, occurrences, booking
logic, renderers, or skins. It keeps visitor-facing content alive as a simple
WordPress site.

## Return to Normal Architecture

If first-party plugins and custom rendering are restored:

```sh
wp iss fallback disable
wp iss fallback status
```

Confirm generated fallback content is back to `draft`, normal menus and front
page are restored, and canonical routes work again.

## Later MU Hardening

The v1 implementation does not move architecture into MU plugins. A later MU
hardening pass may contain only survival-critical registrations,
fallback-mode reader, and projection access. It must not become a second
application architecture.
