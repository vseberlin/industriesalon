#!/usr/bin/env bash
# Retired unrestricted importer. Keep this entrypoint to block old instructions.
set -euo pipefail

cat >&2 <<'MESSAGE'
Full database imports into staging are disabled.
They can replace staging credentials with local passwords and restore sessions.
Use reviewed content migrations under ops/migrations/ and follow
docs/infrastructure/sync.md. Preserve target accounts, roles, authentication
metadata, security audit records and environment-specific settings.
Historical full dumps are recovery evidence, not deployment inputs.
No database connection or import was attempted.
MESSAGE
exit 78
