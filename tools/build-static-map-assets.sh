#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
map_dir="${repo_root}/themes/industriesalon/assets/maps"
source_image="${map_dir}/schoneweide-map-canonical.png"

command -v magick >/dev/null 2>&1 || {
  echo "ImageMagick 'magick' is required." >&2
  exit 1
}

[[ -f "${source_image}" ]] || {
  echo "Missing canonical source: ${source_image}" >&2
  exit 1
}

build_variant() {
  local width="$1"
  local output="$2"
  local temporary="${output}.tmp.webp"

  magick "${source_image}" \
    -strip \
    -resize "${width}x" \
    -quality 82 \
    -define webp:method=6 \
    "${temporary}"
  mv "${temporary}" "${output}"
  identify "${output}"
}

build_variant 1024 "${map_dir}/schoneweide-map-canonical-display-1024.webp"
build_variant 2048 "${map_dir}/schoneweide-map-canonical-display.webp"
