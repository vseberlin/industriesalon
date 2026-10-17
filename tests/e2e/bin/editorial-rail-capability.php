<?php
/** wp eval-file: rail editor capability is owner-declared, independent of saved features. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$formats = iss_editorial_get_registered_formats();
foreach ($formats as $slug => $format) {
    $assert(!empty($format['editor']['rail_control']) === ($slug === 'projekt'), "$slug retains its editor capability");
}
$project = $formats['projekt'];
$probe = static function (array $formats): array {
    $formats['renamed_rail_owner'] = $formats['projekt'];
    $formats['projekt']['editor']['rail_control'] = false;
    return $formats;
};
add_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache();
try {
    $renamed = iss_editorial_get_format('renamed_rail_owner');
    $disabled = iss_editorial_get_format('projekt');
    $assert(!empty($renamed['editor']['rail_control']), 'A renamed owner retains its declared capability');
    $assert(empty($disabled['editor']['rail_control']), 'The historical project name cannot force the control');
    $assert($renamed['editor']['identity_palettes'] === $project['editor']['identity_palettes'], 'Other owner controls retain their configuration');
    $assert($disabled['skins'] === $project['skins'], 'Disabling an editor control does not alter skin defaults');
    foreach (['projekt', 'renamed_rail_owner', 'article'] as $slug) {
        foreach ([1, 2, 3] as $version) {
            foreach ([false, true] as $enabled) {
                $document = iss_editorial_get_empty_document($slug); $document['schema_version'] = $version;
                $rail = ['enabled' => $enabled, 'placement' => 'bottom', 'mode' => 'anchor-nav', 'treatment' => 'card'];
                $document['features']['rail'] = $rail;
                $valid = iss_editorial_validate_document($document, $slug);
                $assert(!is_wp_error($valid) && $valid['features']['rail'] === $rail, "$slug v$version retains saved rail values independently of editor visibility");
            }
        }
    }
    $assert(iss_editorial_registry_errors() === [], 'Renamed capability leaves the owner registry valid');
} finally {
    remove_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache();
}
$assert(wp_json_encode(iss_editorial_get_registered_formats()) === wp_json_encode($formats), 'Original registry restored');
WP_CLI::success("$checks rail capability checks; no persistent fixtures.");
