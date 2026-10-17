<?php
/** wp eval-file: Place select vocabularies follow the owner registry, not consumer names. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$format = iss_editorial_get_format('place');
$vocabularies = $format['sections']['epoche']['vocabularies'];
$expected = [
    'era_key' => array_column(iss_register_get_atlas_era_definitions(), 'name', 'slug'),
    'function_key' => array_column(iss_register_get_epoch_function_definitions(), 'label', 'key'),
];
foreach ($expected as $field => $choices) {
    $assert(array_column($vocabularies[$field]['choices'], 'label', 'value') === $choices, "$field retains owner values, labels and order");
}
$assert($vocabularies['era_key']['label'] === 'Historische Epoche' && $vocabularies['function_key']['label'] === 'Funktion', 'Editor labels retained');
foreach ($format['supported_versions'] as $version) {
    foreach (array_keys($expected['era_key']) as $era) {
        foreach (array_keys($expected['function_key']) as $function) {
            $doc = iss_editorial_get_empty_document('place'); $doc['schema_version'] = $version;
            $doc['sections'] = [['type' => 'epoche', 'title' => 'Phase', 'era_key' => $era, 'function_key' => $function, 'start_year' => 1920]];
            $valid = iss_editorial_validate_document($doc, 'place');
            $assert(!is_wp_error($valid), 'Every declared pair passes the existing Place validator');
            $assert($valid['sections'][0]['era_key'] === $era && $valid['sections'][0]['function_key'] === $function, 'Accepted values survive unchanged');
        }
    }
}

$probe = static function (array $formats): array {
    $formats['renamed_history'] = $formats['place'];
    $section = $formats['place']['sections']['epoche'];
    $section['vocabularies'] = [
        'era_key' => ['label' => 'Period', 'choices' => ['ANCIENT' => 'Earlier', 'recent' => 'Recent']],
        'function_key' => ['label' => 'Use', 'choices' => ['research' => 'Research', 'workshop' => 'Workshop']],
    ];
    $formats['renamed_history']['sections'] = ['renamed_phase' => $section];
    $formats['renamed_history']['starters'] = [];
    return $formats;
};
$fault = null;
add_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache();
try {
    $renamed = iss_editorial_get_format('renamed_history');
    $assert(array_column($renamed['sections']['renamed_phase']['vocabularies']['era_key']['choices'], 'value') === ['ancient', 'recent'], 'Registry normalizes machine keys');
    foreach ([1, 2, 3] as $version) {
        $doc = iss_editorial_get_empty_document('renamed_history'); $doc['schema_version'] = $version;
        $doc['sections'] = [['type' => 'renamed_phase', 'title' => 'Phase', 'era_key' => 'ancient', 'function_key' => 'research']];
        $valid = iss_editorial_validate_document($doc, 'renamed_history');
        $assert(!is_wp_error($valid) && $valid['sections'][0]['era_key'] === 'ancient' && $valid['sections'][0]['function_key'] === 'research', 'Renamed consumers use their own vocabulary');
        $doc['sections'][0]['era_key'] = 'Ancient ';
        $doc['sections'][0]['function_key'] = 'Research ';
        $normal = iss_editorial_sanitize_document($doc, 'renamed_history');
        $assert($normal['sections'][0]['era_key'] === 'ancient' && $normal['sections'][0]['function_key'] === 'research', 'Existing title/key sanitation stays intact');
        $assert(is_wp_error(iss_editorial_validate_document($doc, 'renamed_history')), 'Validation still rejects changed nonempty input');
        foreach (['era_key' => 'ddr', 'function_key' => 'culture'] as $field => $foreign) {
            $bad = $valid; $bad['sections'][0][$field] = $foreign;
            $result = iss_editorial_validate_document($bad, 'renamed_history');
            $assert(is_wp_error($result) && $result->get_error_code() === 'editorial_invalid_field', 'Historical Place values cannot override a declaration');
            foreach (['', null] as $empty) {
                $bad['sections'][0][$field] = $empty;
                $result = iss_editorial_validate_document($bad, 'renamed_history');
                $assert(!is_wp_error($result) && $result['sections'][0][$field] === '', 'Generic empty value remains empty; Place requirements stay with its validator');
            }
        }
    }
    $assert(iss_editorial_registry_errors() === [], 'Renamed declarations are complete');
    foreach ([
        [['label' => ''], 'missing era_key vocabulary label or choices'],
        [['choices' => []], 'missing era_key vocabulary label or choices'],
        [['choices' => ['ancient' => '']], 'era_key vocabulary choice lacks a label'],
    ] as [$override, $message]) {
        $fault = static function (array $formats) use ($override): array {
            $formats['renamed_history']['sections']['renamed_phase']['vocabularies']['era_key'] = array_replace($formats['renamed_history']['sections']['renamed_phase']['vocabularies']['era_key'], $override);
            return $formats;
        };
        add_filter('iss_editorial_formats', $fault, 160); iss_editorial_reset_format_cache();
        $assert(in_array('renamed_history.renamed_phase: ' . $message, iss_editorial_registry_errors(), true), 'Registry diagnoses ' . $message);
        remove_filter('iss_editorial_formats', $fault, 160); $fault = null; iss_editorial_reset_format_cache();
    }
} finally {
    if ($fault) { remove_filter('iss_editorial_formats', $fault, 160); }
    remove_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache();
}
$assert(iss_editorial_registry_errors() === [], 'Original registry restored');
WP_CLI::success("$checks Place vocabulary checks; no persistent fixtures.");
