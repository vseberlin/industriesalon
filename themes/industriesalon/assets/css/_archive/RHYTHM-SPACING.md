# ISS vertical rhythm and spacing scale

Load this file after `style.css`.

## Purpose

This layer standardizes spacing without rewriting the whole theme.

It does four things:

1. creates a small spacing scale
2. normalizes section spacing
3. normalizes kicker / heading / intro text rhythm
4. removes random Gutenberg margins inside known content wrappers

## File order

1. `style.css`
2. `style-left-aligned.css` if you use it
3. `iss-rhythm.css`
4. pattern CSS files

The rhythm file should come before pattern-specific CSS if those patterns need to override gaps. If you want rhythm to win almost everywhere, load it after the pattern files instead. For first testing, load it after `style.css` and before the pattern CSS.

## Scale

Tokens:

- `--iss-space-2xs` = 0.375rem
- `--iss-space-xs` = 0.5rem
- `--iss-space-s` = 0.75rem
- `--iss-space-m` = 1rem
- `--iss-space-l` = 1.5rem
- `--iss-space-xl` = 2rem
- `--iss-space-2xl` = 3rem
- `--iss-space-3xl` = 4.5rem

Use this scale instead of ad hoc values like `0.85rem`, `1.35rem`, `2.3rem` unless a specific pattern really needs it.

## Section spacing

Default section vertical padding becomes:

- mobile: `2.5rem`
- desktop: `5rem`

You also get two optional variants:

- `.section--compact`
- `.section--spacious`

Use them only at section level, not inside cards.

## Section intros

This file makes the section intro predictable:

- kicker
- heading
- intro text

The main rule is:

- `.iss-heading` controls intro rhythm
- child margins are reset to zero
- the wrapper gap controls spacing

That means you should stop solving intro spacing with random bottom margins on each paragraph and heading.

## Content stacks

Use `.iss-stack` when you need a vertical text group that should behave predictably.

Examples:

```html
<div class="wp-block-group iss-stack">
  <p class="iss-kicker">Archiv</p>
  <h2 class="iss-heading__title">Einstieg ins Archiv</h2>
  <p>Texte, Bilder und biografische Spuren.</p>
</div>
```

Variants:

- `.iss-stack--tight`
- `.iss-stack--base`
- `.iss-stack--loose`

## Existing patterns already normalized

The file also normalizes these wrappers directly:

- `.iss-media-text__inner`
- `.iss-asymmetric-feature__content-inner`
- `.iss-1to4-grid__lead-body`
- `.iss-1to4-grid__grid-body`
- `.iss-feature-stack__content`
- `.iss-4-card-row__card`

That means most recent patterns should improve immediately without markup changes.

## Utilities

Available utilities:

Margin top:
- `.iss-mt-0`
- `.iss-mt-xs`
- `.iss-mt-s`
- `.iss-mt-m`
- `.iss-mt-l`
- `.iss-mt-xl`

Margin bottom:
- `.iss-mb-0`
- `.iss-mb-xs`
- `.iss-mb-s`
- `.iss-mb-m`
- `.iss-mb-l`
- `.iss-mb-xl`

Gap:
- `.iss-gap-xs`
- `.iss-gap-s`
- `.iss-gap-m`
- `.iss-gap-l`
- `.iss-gap-xl`

Use them sparingly. If the same spacing need appears more than twice, move it into a component class instead.

## Recommended clean wiring

Use spacing at these levels only:

### Section level
Use `.section`, `.section--compact`, `.section--spacious`

### Intro level
Use `.iss-heading` or `.iss-indent`

### Content group level
Use `.iss-stack` or a pattern-specific inner wrapper

### Card level
Use `.iss-card__body`

Avoid solving rhythm on single paragraphs unless it is truly exceptional.

## What this should fix

- headings too detached from kicker
- paragraphs randomly drifting due to Gutenberg defaults
- inconsistent card body rhythm
- inconsistent spacing between section intro and section content
- too many one-off margins in pattern CSS
