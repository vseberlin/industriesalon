# Industriesalon Theme

## Accent Contract

`iss-scheme-*` on the outer page wrapper is the default accent source.

- Page wrappers set `--iss-accent`, `--iss-accent-rgb`, `--iss-accent-soft`, and `--iss-accent-border`.
- Shared primitives must read those tokens by default.
- Do not restyle generic rails, kickers, links, hover states, or CTA buttons with named palette vars in page CSS.

## Page Versus Component Accent

Page accent and component accent are separate systems.

- Page accent:
  - Comes from `iss-scheme-red|blue|green|yellow|brown` on the page wrapper.
  - Drives shared kickers, action links, button rails, section rails, shared cards, and shared info panels by default.
- Component-local accent:
  - Is allowed only when a component must intentionally diverge from the surrounding page scheme.
  - Must use a local token instead of mutating `--iss-accent`.

Current component-local tokens:

- `--iss-kicker-accent`
- `--iss-kicker-color`
- `--iss-card-accent`
- `--iss-info-accent`
- `--iss-button-accent`
- `--iss-flex-split-callout-accent`
- `--iss-publication-epoch-accent`

## Surface Contract

Section surface and page accent are separate.

- `section--plain`, `section--tint`, `section--soft`, `section--inset`, `section--fade-right`, and `section--dark` define section surface only.
- `section--dark` owns readable text/button/link tokens for dark backgrounds.
- `iss-heading--light` is a readability helper for headings on dark surfaces or dark media.
- `iss-kicker--light` changes kicker readability only. It does not define a page accent.

## Markup Rules

- On scheme-led pages, use neutral shared markup first.
- Do not add `iss-kicker--red|blue|green|yellow|brown`, `iss-card--red|blue|green|yellow|brown`, or `iss-info-panel--red|blue|green|yellow|brown` just to restyle a page section.
- If a page already has `iss-scheme-*`, let the wrapper drive the shared component accent.
- Keep explicit component variants for intentional exceptions only.
- Current intentional exceptions are the component demo patterns in:
  - `patterns/pattern-info-panel-anmeldung.html`
  - `patterns/pattern-info-panel-besuch.html`
  - `patterns/pattern-info-panel-vermietung.html`
- Publications timeline epochs are an allowed semantic exception.
- Archive type/status cards and tour family/status skins are allowed semantic exceptions.

## Info Panel Contract

`iss-info-panel` is one shared component. Width, accent, shell, and layout each have one owner.

- Width:
  - Gutenberg panel wrappers stay `layout.type = default`.
  - Page shells like `.iss-container` own panel width.
  - `iss-info-panel` itself stays full-width inside that shell and does not self-center with its own content cap.
- Color:
  - Scheme-led pages let the nearest `iss-scheme-*` wrapper drive `--iss-accent`.
  - `iss-info-panel` inherits that accent by default for the kicker, the right-column top border, every `iss-info-row` separator, and row link hover states.
  - Do not add `iss-info-panel--red|green|blue|yellow|brown` on scheme-led pages unless the panel is a documented exception.
- Canonical two-column markup:
  - Root: `.iss-info-panel`
  - Inner layout: `.iss-info-panel__grid`
  - Left column: `.iss-info-panel__title-col`
  - Left-column heading stack: `.iss-heading.iss-heading--uncaged.iss-info-panel__heading`
  - Heading children: `.iss-kicker`, `.iss-heading__title.iss-info-panel__title`, optional `.iss-heading__text.iss-info-panel__intro`
  - Right column: `.iss-info-panel__rows-col > .iss-info-panel__rows > .iss-info-row`
- Skins:
  - bare `iss-info-panel`: normal two-column panel
  - `iss-info-panel--skin-compact`: tighter single-column utility panel
  - `iss-info-panel--skin-aside`: one-column sidebar/meta panel
  - `iss-info-panel--skin-plain`: shellless panel that still inherits accent
  - `iss-info-panel--skin-menu`: compact off-canvas/menu shell panel
- Skin limits:
  - Skins may change spacing, radius, background, stack direction, and density.
  - Skins must not change accent ownership.
- Plugin/output rules:
  - Plugin PHP may emit semantic panel skin/surface hooks and content rows.
  - Plugin PHP must not inject inline panel layout like `flex-basis`.
  - If a renderer outputs an info panel, the real HTML class attribute must carry the skin/variant classes; Gutenberg comment metadata alone is not enough.

## CSS Rules

- Shared primitives in `style.css`, `assets/css/cards.css`, and `assets/css/patterns.css` must default to `var(--iss-accent)` or a component-local token that itself defaults to `var(--iss-accent)`.
- Page CSS may define page-local tone variables, but those tones must be derived from `--iss-accent` with `rgba(var(--iss-accent-rgb), ...)` or `color-mix(...)`.
- Do not overwrite `--iss-accent` inside shared component modifiers.
- If a template has no `iss-scheme-*` wrapper, the template shell may set a local default `--iss-accent`.
  - Example: `single-content.css` keeps red as the default single-post accent because generic singles do not carry a scheme wrapper.
- If a component is reused on more than one page, its color skin cannot live in page CSS.
- New named-color overrides or new component color variants require explicit approval before they are added.

## CSS Verification

- After any non-trivial edit to theme CSS, run `npm run lint:css` from repo root before browser debugging.
- Treat CSS parser/syntax failures as first-line suspects when renders break in ways that seem larger than the intended change.
- In this repo, malformed closing braces / broken comment boundaries have already caused hours of misleading layout debugging.
- Do not jump to contract, markup, or Gutenberg-runtime conclusions until lint/parser checks pass.

## Review Checklist

- Wrapper has exactly one `iss-scheme-*` class when the page is scheme-led.
- Section surface is defined by a `section--*` class, not by ad hoc color overrides.
- Shared kickers, left rails, hover states, and CTA rails inherit from the wrapper without page-local palette overrides.
- Shared cards and info panels stay neutral unless there is a documented local semantic exception.
- Any page-local art direction uses accent-derived tones, not unrelated named palette colors.
- Plugin PHP emits semantic classes/data for color exceptions; it does not inject inline palette values.
