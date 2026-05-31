#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ "$#" -gt 0 ]; then
  exec shellcheck "$@"
fi

mapfile -t targets < <(
  {
    git ls-files '*.sh'
    git ls-files '.envrc'
  } | grep -v '^vendor/' | sort -u
)

if [ "${#targets[@]}" -eq 0 ]; then
  echo "No tracked shell files found."
  exit 0
fi

exec shellcheck "${targets[@]}"
