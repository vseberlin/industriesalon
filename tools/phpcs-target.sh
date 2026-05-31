#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ "$#" -gt 0 ]; then
  exec vendor/bin/phpcs --report=summary "$@"
fi

custom_paths=(
  themes/industriesalon
  plugins/iss-*
  plugins/industriesalon-*
)

mapfile -t targets < <(
  {
    git diff --name-only --diff-filter=ACMRTUXB -- "${custom_paths[@]}"
    git ls-files --others --exclude-standard -- "${custom_paths[@]}"
  } | grep -E '\.php$' | sort -u
)

if [ "${#targets[@]}" -eq 0 ]; then
  echo "No changed PHP files found in custom theme/plugins."
  exit 0
fi

exec vendor/bin/phpcs --report=summary "${targets[@]}"
