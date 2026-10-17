<?php
/** wp eval-file: media placement follows owner declarations, independent of format/gesture names. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$accept = static function (array $document, string $slug) use ($assert): array {
    $result = iss_editorial_validate_document($document, $slug);
    $assert(!is_wp_error($result), is_wp_error($result) ? $result->get_error_message() : 'Valid layout');
    return $result;
};
$definitions = 0;
foreach (iss_editorial_get_registered_formats() as $slug => $format) {
    foreach ($format['sections'] as $type => $section) {
        if (!in_array('media_layout', $section['supports'], true)) { continue; }
        ++$definitions;
        $ratio = $slug === 'landing' && $type === 'feature';
        $layout = $section['media_layout'];
        $assert(array_column($layout['choices'], 'value') === ($ratio ? ['50-50', '40-60', '60-40'] : ['inline', 'aside-right']), "$slug.$type retains choices and order");
        $assert(array_column($layout['choices'], 'label') === ($ratio ? ['Ausgewogen', 'Text kompakt', 'Text breit'] : ['Im Text', 'Rechts daneben']), 'Existing control labels remain');
        $assert($layout['default'] === ($ratio ? '50-50' : 'inline') && $layout['treatments'] === ($ratio ? ['feature.media-text'] : []), 'Default and visibility preserve the current contract');
        foreach ($format['supported_versions'] as $version) {
            $doc = iss_editorial_get_empty_document($slug); $doc['schema_version'] = $version;
            $doc['sections'] = [['type' => $type, 'title' => 'Preserved placement']];
            $assert($accept($doc, $slug)['sections'][0]['media_layout'] === $layout['default'], 'Omitted layout receives its owner default');
            foreach ($layout['choices'] as $choice) {
                $doc['sections'][0]['media_layout'] = $choice['value'];
                $assert($accept($doc, $slug)['sections'][0]['media_layout'] === $choice['value'], 'Registered layout survives validation');
            }
            $doc['sections'][0]['media_layout'] = 'unregistered';
            $invalid = iss_editorial_validate_document($doc, $slug);
            $assert(is_wp_error($invalid) && $invalid->get_error_code() === 'editorial_invalid_field', 'Unknown nonempty layout remains rejected');
            $assert(iss_editorial_sanitize_document($doc, $slug)['sections'][0]['media_layout'] === $layout['default'], 'Lenient reads retain the declared fallback');
        }
    }
}
$assert($definitions === 17, 'All current layout-bearing definitions covered');
$probe = static function (array $formats): array {
    $ratio = $formats['landing']['sections']['feature'];
    $ratio['media_layout']['default'] = '60-40';
    $formats['projekt']['sections']['ratio_probe'] = $ratio;
    $formats['projekt']['sections']['feature'] = $ratio;
    $formats['landing']['sections']['feature'] = $formats['article']['sections']['feature'];
    return $formats;
};
$fault = null;
add_filter('iss_editorial_formats', $probe, 150);
iss_editorial_reset_format_cache();
try {
    $assert(iss_editorial_registry_errors() === [], 'Renaming the consumer requires no engine branch');
    foreach (['ratio_probe', 'feature'] as $type) {
        $doc = iss_editorial_get_empty_document('projekt');
        $doc['sections'] = [['type' => $type, 'title' => 'Renamed ratio']];
        $assert($accept($doc, 'projekt')['sections'][0]['media_layout'] === '60-40', 'Explicit default may differ from the first choice');
        $doc['sections'][0]['media_layout'] = '40-60';
        foreach (['feature.media-text', 'feature.media-panel', 'feature.image-overlay'] as $treatment) {
            $doc['sections'][0]['treatment'] = $treatment;
            $assert($accept($doc, 'projekt')['sections'][0]['media_layout'] === '40-60', 'Hiding a control never discards its stored layout');
        }
        $doc['sections'][0]['media_layout'] = 'aside-right';
        $assert(is_wp_error(iss_editorial_validate_document($doc, 'projekt')), 'Foreign layout is rejected even under the historical feature name');
    }
    $doc = iss_editorial_get_empty_document('landing');
    $doc['sections'] = [['type' => 'feature', 'media_layout' => 'aside-right']];
    $assert($accept($doc, 'landing')['sections'][0]['media_layout'] === 'aside-right', 'Landing feature can use the declared inline contract');
    foreach ([
        [['choices' => []], 'missing media layout choices or invalid default'],
        [['default' => 'unknown'], 'missing media layout choices or invalid default'],
        [['treatments' => ['unknown']], 'unknown media layout treatment'],
        [['choices' => ['60-40' => '']], 'media layout choice lacks a label'],
    ] as [$override, $message]) {
        $fault = static function (array $formats) use ($override): array {
            $formats['projekt']['sections']['ratio_probe']['media_layout'] = array_replace($formats['projekt']['sections']['ratio_probe']['media_layout'], $override);
            return $formats;
        };
        add_filter('iss_editorial_formats', $fault, 160);
        iss_editorial_reset_format_cache();
        $assert(in_array('projekt.ratio_probe: ' . $message, iss_editorial_registry_errors(), true), 'Registry diagnostics catch ' . $message);
        remove_filter('iss_editorial_formats', $fault, 160); $fault = null;
        iss_editorial_reset_format_cache();
    }
} finally {
    if ($fault) { remove_filter('iss_editorial_formats', $fault, 160); }
    remove_filter('iss_editorial_formats', $probe, 150);
    iss_editorial_reset_format_cache();
}
$assert(iss_editorial_registry_errors() === [], 'Original registry restored');
WP_CLI::success("$checks media-layout contract checks; no persistent fixtures.");
