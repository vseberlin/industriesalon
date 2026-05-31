#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ "$#" -gt 0 ]; then
  targets=("$@")
else
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
fi

if [ "${#targets[@]}" -eq 0 ]; then
  echo "No changed PHP files found in custom theme/plugins."
  exit 0
fi

status=0
memory_limit="${PHPSTAN_MEMORY_LIMIT:-1G}"

for target in "${targets[@]}"; do
  echo "==> ${target}"
  if ! vendor/bin/phpstan analyse --no-progress --error-format=table --memory-limit="$memory_limit" "$target"; then
    status=1
  fi
done

exit "$status"
