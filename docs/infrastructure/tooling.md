# Tooling

Use repo-local tooling before adding ad hoc checks.

For task-specific verification, see `docs/agent/verification.md`.

## JavaScript, CSS, Shell, YAML

- `npm run lint`
- `npm run lint:css`
- `npm run lint:js`
- `npm run lint:shell`
- `npm run lint:yaml`

Scope JS/CSS linting to the custom theme and custom `iss-*` / `industriesalon-*` plugins, not bundled third-party code.

## PHP

- Changed-file helpers:
  - `tools/phpcs-target.sh`
  - `tools/phpstan-target.sh`
- Full-repo PHP commands can be heavier and noisier; use only when the task warrants it.
- `PHPSTAN_MEMORY_LIMIT` can override changed-file PHPStan memory.

## Runtime

- Normal WP-CLI pattern:
  - `docker compose run --rm wpcli <command> --allow-root`
- If host PHP is unavailable, lint touched PHP through the Docker/WP-CLI PHP entrypoint.
