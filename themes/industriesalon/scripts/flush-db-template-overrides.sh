#!/usr/bin/env bash
set -euo pipefail

# Flush Gutenberg DB-saved template overrides so theme files are authoritative.
# Usage:
#   ./themes/industriesalon/scripts/flush-db-template-overrides.sh
#   ./themes/industriesalon/scripts/flush-db-template-overrides.sh front-page,header

FILTER_NAMES="${1:-}"

if ! command -v docker >/dev/null 2>&1; then
  echo "docker not found in PATH" >&2
  exit 1
fi

# shellcheck disable=SC2016
PHP_CODE='
require "/var/www/html/wp-load.php";

$theme_slug = wp_get_theme()->get_stylesheet();
$names_raw = getenv("ISS_TEMPLATE_NAMES") ?: "";
$names = array_values(array_filter(array_map("trim", explode(",", $names_raw))));

$args = [
    "post_type"      => ["wp_template", "wp_template_part"],
    "post_status"    => "any",
    "posts_per_page" => -1,
    "fields"         => "ids",
    "tax_query"      => [[
        "taxonomy" => "wp_theme",
        "field"    => "slug",
        "terms"    => [$theme_slug],
    ]],
];

if (!empty($names)) {
    $args["post_name__in"] = $names;
}

$ids = get_posts($args);

if (empty($ids)) {
    echo "No DB template overrides found for theme: {$theme_slug}\n";
    exit(0);
}

$count = 0;
foreach ($ids as $id) {
    if (wp_delete_post((int) $id, true)) {
        $count++;
    }
}

echo "Theme: {$theme_slug}\n";
echo "Deleted DB overrides: {$count}\n";
if (!empty($names)) {
    echo "Filter: " . implode(", ", $names) . "\n";
}
'

if [[ -n "$FILTER_NAMES" ]]; then
  docker exec -e ISS_TEMPLATE_NAMES="$FILTER_NAMES" wp_app php -r "$PHP_CODE"
else
  docker exec wp_app php -r "$PHP_CODE"
fi
