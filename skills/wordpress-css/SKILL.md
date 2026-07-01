---
name: wordpress-css
description: Use for this repo when changing theme CSS, block styling, Gutenberg layout structure, or frontend visual behavior.
---

# WordPress CSS

Canonical docs:

- `docs/agent/wordpress-engineering.md`
- `docs/architecture/css-layering-adr.md`
- `docs/architecture/content-model.md`

Inspect `theme.json`, `assets/css/tokens.css`, global styles, shared primitives,
card/pattern CSS, renderer/skin CSS, and `overrides.css` before changing CSS.

Layering rule:

1. Add or change reusable values in `assets/css/tokens.css` as `--iss-*`
   custom properties.
2. Do not tokenize every value. Use layer-0 tokens only for shared, semantic
   values expected to vary across theme/site/skin/component contexts. Keep
   one-off geometry, temporary migration values, implementation glue, and
   component-only internals local.
3. Add reusable shapes to primitives/patterns before page selectors.
4. For JSON-rendered pages, prefer gesture/treatment/skin selectors such as
   `.iss-landing-section--gesture-*` and
   `.iss-landing-section--treatment-*`.
5. Public first-party plugin CSS consumes `--iss-*` tokens with fallback values
   and plugin-prefixed local variables. It must not redefine the global token
   contract or duplicate theme-owned primitives.
6. Admin plugin CSS is outside the public layer stack. Keep it scoped to
   wp-admin/plugin classes and WordPress admin conventions.
7. Use page-specific CSS only for true one-offs, legacy fallback pages, or
   temporary migration compatibility.
8. Make CSS changes migration-positive. When touching old page-specific CSS,
   drain reusable rules into tokens, primitives/patterns, renderer contracts,
   or skins, and remove obsolete page selectors in the same pass when safe.
