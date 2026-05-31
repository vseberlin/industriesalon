#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ "$#" -gt 0 ]; then
  exec yamllint -c .yamllint.yml "$@"
fi

mapfile -t targets < <(
  git ls-files '*.yml' '*.yaml' | grep -v '^vendor/' | sort -u
)

if [ "${#targets[@]}" -eq 0 ]; then
  echo "No tracked YAML files found."
  exit 0
fi

exec yamllint -c .yamllint.yml "${targets[@]}"
