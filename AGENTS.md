# AGENTS.md

## Core priorities

Longevity, traceability, simplicity, stability, and coherence have priority over speed.

Do not apply quick tweaks. Do not patch locally around a problem. First understand the existing system, dependencies, and intended architecture.

All changes must be lean, traceable, documented, and justified.


## Engineering Philosophy

Priority order:

1. Stability
2. Maintainability
3. Editor usability
4. Predictability
5. Simplicity
6. Performance
7. Visual refinement

The project intentionally prefers boring, understandable solutions over clever ones.

Code should be obvious to future maintainers unfamiliar with the project.

Avoid:
- clever abstractions
- fragile CSS tricks
- workaround-based implementations
- hidden side effects
- over-engineering
- micro-optimizations
- one-off exceptions
- selector hacks
- DOM-dependent styling
- deep specificity chains
- animation-heavy UI
- JavaScript solutions for problems solvable structurally
- custom systems when WordPress/Gutenberg already provides one

Prefer:
- native Gutenberg behavior
- predictable block structure
- reusable structural patterns
- low-specificity CSS
- semantic HTML
- editor-visible layouts
- server-rendered stability
- progressive enhancement
- explicit code over abstract helpers
- small understandable components
- long-lived APIs and markup

## Editor-First Principle

Editor experience has priority over frontend cleverness.

If a solution is visually elegant but difficult to edit, fragile in Gutenberg, or confusing for non-technical staff, reject it.

Content editors must be able to:
- understand layouts visually
- move blocks safely
- reuse patterns predictably
- edit without breaking layouts
- avoid shortcode-like workflows
- avoid hidden configuration logic

Frontend implementation must adapt to editorial workflow — not the opposite.

## CSS Rules

CSS must remain:
- global
- structural
- token-based
- predictable
- low-specificity

Never:
- use !important
- style individual pages with isolated hacks
- patch spacing locally
- introduce exceptions without architectural reason
- duplicate existing patterns
- increase CSS complexity without justification

Before adding CSS:
1. check theme.json
2. check global tokens
3. check existing utilities/patterns
4. check whether structure can solve the problem first

Prefer changing layout structure over patching appearance.

## Gutenberg Rules

Blocks and patterns must:
- degrade gracefully
- survive editor changes
- remain editable
- avoid wrapper dependency chains
- avoid DOM-fragile selectors
- avoid reliance on editor-generated class names

Server-rendered blocks should still feel editable and understandable inside Gutenberg.

Avoid creating systems editors cannot mentally model.

## JavaScript / PHP Rules

No defensive patching.
No bandaid fixes.
No duplicate logic.

Always:
- trace root cause
- simplify before extending
- remove dead code first
- document architectural reason for changes
- prefer stable APIs over custom glue code

The correct solution is usually the simpler one.

## Before proposing or changing anything

Always inspect the repository first.

Check existing tools, conventions, helpers, theme configuration, build steps, scripts, and prior solutions before proposing new code.

Check for dead, unused, duplicated, or obsolete code before adding or changing anything.

Check dependencies and how they may influence functionality.

Do not invent parallel systems if the repository already contains a suitable mechanism.

## CSS rules

CSS must stay coherent and slim.

Before changing CSS, always inspect:

- global styles
- `theme.json`
- shared variables/tokens
- existing layout primitives
- existing pattern/card/override files
- reusable classes already present

Do not solve problems with narrow individual selectors.

Do not use `!important`.

Do not create page-specific or block-specific shortcuts unless explicitly approved.

Prefer structural changes over selector patches.

If CSS code would increase, ask for permission first and explain why the increase is necessary.

Avoid local fixes that hide a deeper layout or token problem.

CSS changes must preserve the global design system.

## PHP and JavaScript rules

No patching.

No extra guardrails unless they are structurally necessary and explicitly justified.

Do not add defensive layers, fallbacks, wrappers, or special cases by default.

Prefer clear structural changes over accumulated conditionals.

Keep PHP and JavaScript lean, readable, and traceable.

Before changing PHP or JavaScript, check existing hooks, helpers, enqueue logic, templates, blocks, and plugin boundaries.

## Change discipline

Every change must have a clear reason.

Every change must be documented in code where the reason is not immediately obvious.

All changes and reasons for change must be recorded in the changelog.

Do not make unrelated changes.

Do not reformat files unnecessarily.

Do not rename or move things unless the structural benefit is clear.

## Proposal discipline

Before implementation, explain:

- what existing mechanism was checked
- what dead or unused code was found
- what dependency impact was considered
- what structural change is proposed
- why no simpler existing solution is sufficient

Prefer one coherent change over several small patches.

No shortcuts. No cosmetic fixes that weaken the system.

## Architecture integrity

Do not bypass architecture boundaries.

Presentation, data, and logic must remain separated.

Do not move logic into templates, inline scripts, or inline styles unless explicitly required.

Do not let plugins own presentation markup unless the repository architecture explicitly requires it.

Prefer reusable primitives over one-off implementations.

## Gutenberg / WordPress discipline

Do not rely on Gutenberg-generated class chains or unstable editor markup.

Target stable semantic classes controlled by the project.

Do not solve frontend issues by fighting editor output with selector escalation.

Patterns and blocks must remain portable and context-independent.

Changes must work across editor, frontend, and responsive states.

Always verify:
- editor rendering
- frontend rendering
- template compatibility
- block validation stability

## Selector discipline

Prefer:
- tokens
- layout primitives
- component classes
- wrapper-level architecture

Avoid:
- deeply nested selectors
- specificity escalation
- chained selectors tied to WP internals
- styling against transient DOM structures

Keep selector depth shallow.

## Performance discipline

Every dependency, script, stylesheet, animation, observer, and query has a cost.

Before adding anything:
- check if it already exists
- check if it can be simplified
- check if it increases maintenance burden

Avoid runtime-heavy solutions where static structure is sufficient.

Prefer CSS over JS where appropriate.

Prefer static rendering over dynamic rendering where appropriate.

## Debugging discipline

Do not guess.

Trace the actual source of the problem before proposing changes.

Check:
- inheritance
- global tokens
- container logic
- template hierarchy
- enqueue order
- cascade order
- block/editor differences
- responsive behavior
- plugin interference

Fix root causes, not symptoms.

## Code cleanliness

Remove obsolete code when replacing systems.

Do not leave commented-out legacy code in production files.

Avoid duplicate utilities, duplicate tokens, and overlapping abstractions.

Keep files organized and predictable.

## Documentation discipline

Document:
- why the change exists
- architectural intent
- dependency assumptions
- known limitations
- integration points

Comments should explain intent, not restate code.

## Review discipline

Before finalizing any change:
- check for simpler solutions
- check for system-wide impact
- check consistency with existing architecture
- verify no unnecessary complexity was introduced
- verify no duplicated logic was created

If uncertain, stop and propose options instead of improvising.

Do not optimize for passing the immediate task only. Optimize for maintainability of the repository over time.
When a structural issue is discovered, report it even if it is outside the immediate task scope.
Do not silently work around broken architecture.