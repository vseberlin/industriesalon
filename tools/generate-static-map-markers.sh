#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${repo_root}"
mkdir -p themes/industriesalon/assets/maps/qa

docker compose run --rm \
  --user "$(id -u):$(id -g)" \
  wpcli \
  iss-relations map-markers generate \
  --qa=/var/www/html/wp-content/themes/industriesalon/assets/maps/qa/schoneweide-canonical-projection.svg \
  --allow-root

docker compose run --rm wpcli \
  iss-relations map-markers verify \
  --allow-root
