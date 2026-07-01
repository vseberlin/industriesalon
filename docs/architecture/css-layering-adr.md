# ADR: CSS Token And Renderer Layering

Date: 2026-07-01

## Status

Accepted.

## Context

Native page JSON migration gives the theme stable rendering classes for
`gesture`, `treatment`, `skin`, item count, and mapped dynamic slots. That
contract is a better long-term styling target than page-specific overrides such
as front-page-only selector chains.

## Decision

Use this CSS layer order:

0. `assets/css/tokens.css`
   - Owns only `--iss-*` custom property definitions and legacy token aliases.
   - Does not style layout, components, pages, WordPress block markup, or
     renderer output.
   - Tokens are for values that are shared, semantic, and expected to vary
     across theme, site, skin, or component contexts. Do not add tokens for
     one-off geometry, temporary migration values, implementation glue,
     copied design measurements without a second consumer, or names that
     describe a page location instead of meaning.
   - Start with component-local variables when a value is only locally useful;
     promote to `tokens.css` only when it becomes a real site contract.
1. `style.css`
   - Owns low-level base styles and reusable global helpers.
2. `assets/css/cards.css`, `assets/css/primitives.css`, `assets/css/patterns.css`
   - Own reusable components, primitives, and shared composition patterns.
3. Renderer contract CSS
   - Targets stable renderer classes such as
     `.iss-landing-section--gesture-*`,
     `.iss-landing-section--treatment-*`,
     `.iss-landing-section--skin-*`, and
     `.iss-landing-section--items-*`.
4. Skin CSS
   - Scopes token overrides and treatment mood to a skin wrapper.
5. Page exceptions
   - Allowed only for true one-offs, legacy fallback pages, or transition
     compatibility. They should shrink as JSON gestures gain treatments.
   - Page-specific files are drain targets. Any future change that touches an
     old page-specific stylesheet should move reusable values, shapes, or JSON
     renderer styling into the proper earlier layer and delete the old selector
     when it is no longer needed.
6. Public plugin CSS
   - First-party public plugin styles may consume the same `--iss-*` token
     contract, with fallback values for portability.
   - Plugin CSS should stay structural unless the plugin explicitly owns a
     public renderer. Theme-owned skins remain in the theme.
   - Plugin-local custom properties must be plugin-prefixed and should default
     to `--iss-*` tokens, for example
     `--iss-programm-gap: var(--iss-gap, 1.35rem)`.
   - Public plugin CSS must not redefine the global `--iss-*` contract,
     implement page skins, reset theme primitives, or duplicate theme button,
     card, surface, and typography systems.
7. Admin plugin CSS
   - Admin CSS is outside the public frontend stack.
   - Admin CSS may use WordPress admin variables and scoped plugin classes, but
     must not leak frontend token assumptions into wp-admin or style public
     theme primitives.

## Consequences

- New reusable values start as `--iss-*` tokens.
- Do not tokenize every value. Local component variables are preferred until a
  value has shared semantic meaning and more than one likely consumer.
- Reusable shapes become primitives or patterns before page selectors.
- JSON gesture polishing should add treatment/skin selectors rather than
  front-page or slug-specific overrides.
- First-party public plugin CSS joins the token contract as a consumer, not as
  another source of design authority.
- Admin plugin CSS stays scoped to wp-admin surfaces and is not part of the
  public renderer layer stack.
- Existing page CSS is migrated incrementally, gesture by gesture, during UAT
  polish instead of by a high-risk big-bang rewrite.
- CSS changes must be migration-positive: do not add to old page-specific CSS
  when the rule belongs in tokens, primitives/patterns, renderer contracts, or
  skins. Touched legacy CSS files should get smaller or more explicitly scoped
  over time.
