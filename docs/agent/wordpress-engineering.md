# WordPress Engineering Rules

Use this for code, design, Gutenberg, CSS, PHP, JavaScript, template, theme, and plugin work.

## Architecture

- Inspect the real owner before changing code: active theme, CPT owner, render callback, enqueue path, template hierarchy, DB `wp_template` authority, and live output when relevant.
- Theme owns public composition, skins, templates, and frontend presentation.
- Plugins own data, contracts, business logic, import/projection services, CPTs, and dynamic block data.
- Extend existing shared renderers, patterns, and contracts before creating a new surface.
- Remove obsolete code when replacing a system. Do not leave commented-out legacy code.

## Gutenberg And Editorial UX

- Editor experience has priority over frontend cleverness.
- Prefer native Gutenberg behavior, reusable patterns, editor-visible layouts, semantic HTML, and server-rendered stability.
- Blocks and patterns must degrade gracefully, survive editor changes, avoid wrapper dependency chains, and remain portable.
- Do not rely on editor-generated class chains or unstable markup.
- Verify editor rendering, frontend rendering, responsive states, template compatibility, and block validation when relevant.

## CSS

- Before adding CSS, inspect `theme.json`, `assets/css/tokens.css`, global tokens, shared variables, layout primitives, existing utilities, pattern/card CSS, renderer/skin CSS, and `overrides.css`.
- Treat `assets/css/tokens.css` as layer 0 for the `--iss-*` custom property contract. Do not put layout, component, page, or WordPress markup selectors there.
- Do not tokenize every value. Add layer-0 tokens only for shared, semantic
  values expected to vary across theme/site/skin/component contexts. Keep
  one-off geometry, temporary migration values, implementation glue, and
  component-only internals as local variables or plain declarations.
- Prefer this layer order: token values, base helpers, primitives/patterns, renderer contracts, skins, then page exceptions.
- For JSON-rendered pages, target stable gesture/treatment/skin classes before page-specific selectors.
- Public first-party plugin CSS may consume `--iss-*` tokens with fallback
  values and plugin-prefixed local variables, but it must not redefine global
  tokens or duplicate theme-owned button/card/surface/type systems.
- Admin plugin CSS is outside the public CSS layer stack; keep it scoped to
  wp-admin/plugin classes and WordPress admin conventions.
- CSS changes should be migration-positive. When touching an old page-specific
  stylesheet, drain reusable rules into tokens, primitives/patterns,
  renderer-contract CSS, or skins, then delete the obsolete page selector when
  nothing still depends on it.
- Prefer structural layout changes over appearance patches.
- Keep CSS global, structural, token-based, predictable, and low-specificity.
- Avoid `!important`, page-specific hacks, duplicated tokens, selector escalation, DOM-dependent styling, and deep specificity chains.
- Target stable project-owned classes and wrapper-level architecture.
- If CSS would grow materially, explain why the increase is structurally necessary before implementing.

## PHP And JavaScript

- Trace root cause before changing behavior.
- Prefer stable WordPress APIs and existing repo helpers over custom glue.
- Keep code lean, readable, explicit, and traceable.
- Do not add fallback layers, wrappers, conditionals, guards, or duplicate logic unless they are structurally necessary.
- Keep presentation, data, and logic separated. Do not move business logic into templates or inline scripts/styles.

## Data Storage And Queries

- Prefer WordPress posts, post meta, taxonomies, and `WP_Query` for normal editorial content, template loops, and editor-owned surfaces.
- Prefer plugin-owned custom tables for data that is not naturally post-shaped: projections, search indexes, imported archive objects, graph/entity relations, assertions, evidence, memberships, reporting, and high-volume lookup data.
- Prefer prepared `$wpdb` SQL over forcing complex `WP_Query` / meta-query stacks when the query depends on custom tables, relevance scoring, multi-table joins, aggregates, projections, or indexed lookup paths.
- Custom tables must be owned by the responsible plugin, installed with `dbDelta()`, versioned with schema options, named with `$wpdb->prefix`, indexed for the real read paths, and accessed through service classes rather than templates.
- Direct SQL must use `$wpdb->prepare()` for dynamic values, keep dynamic table names limited to service-owned table-name methods, and include explicit PHPCS justifications only where WordPress cannot infer safety.
- Public rendering should consume service/query results; it should not build raw SQL inside theme templates or block markup.
- Add or change custom tables only when the current WordPress storage model is the wrong shape or produces fragile/slow query logic. Do not create tables as a convenience shortcut.

## Source Of Truth Checks

- For block-theme templates, check DB overrides before trusting disk files:
  - `docker compose run --rm wpcli post list --post_type=wp_template --allow-root`
  - `docker compose run --rm wpcli eval 'var_dump(get_block_template("industriesalon//template-slug", "wp_template")->source ?? null);' --allow-root`
- If `source=db`, preserve useful DB content before removing overrides.
- File-backed authority is preferred for durable templates when editors do not need an active DB override.

## Validation

- Use repo-local tooling before ad hoc checks.
- For PHP linting in this stack, prefer Docker/WP-CLI when host PHP is unavailable:
  - `docker compose run --rm --entrypoint php wpcli -l /var/www/html/wp-content/path/to/file.php`
- Useful local checks include:
  - `npm run lint`
  - `npm run lint:css`
  - `npm run lint:js`
  - `npm run lint:shell`
  - `tools/phpcs-target.sh`
  - `tools/phpstan-target.sh`
- For route/layout changes, verify representative frontend output and mobile/desktop overflow.
- For content/database mutations, create export or backup evidence before and after when risk warrants it.
