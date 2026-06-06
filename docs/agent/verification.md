# Verification Matrix

Use this as the default verification map. Add task-specific checks when risk warrants it.

| Change type | Checks |
| --- | --- |
| CSS/layout | `npm run lint:css` or targeted stylelint, desktop/mobile render, horizontal overflow, editor check if Gutenberg-facing |
| PHP plugin/theme | `php -l` or Docker PHP lint, `tools/phpcs-target.sh`, `tools/phpstan-target.sh`, WP-CLI plugin/theme load when runtime-facing |
| JavaScript | `npm run lint:js` or `node --check`, browser behavior check when user-facing |
| Block template | `parse_blocks()`, `wp_template` authority, frontend route render |
| SQL import/artifact | backup, syntax/import test where possible, row counts, target dependency check, paired upload dependency check |
| Deploy | repo state, containers/services, logs, homepage, changed routes |
| Upload sync | source/target inspection, referenced-media coverage, matching SQL dependency check, dry-run, counts, sizes, hashes or representative media URLs |
| Search/mail/service | service status, logs, configured provider, frontend behavior, fallback behavior |
| Documentation | `git diff --check`, no broad duplicate policy, docs remain task-loaded |
