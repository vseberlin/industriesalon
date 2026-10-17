<?php
/** wp eval-file: opening capability is owner-declared and eligibility stays live. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$front_id = 900001;
$front_option = static function () use (&$front_id): int { return $front_id; };
$builds = 0;
$count_builds = static function (array $formats) use (&$builds): array { ++$builds; return $formats; };
$probe = null; $fault = null;
add_filter('pre_option_page_on_front', $front_option);
add_filter('iss_editorial_formats', $count_builds, 200);
iss_editorial_reset_format_cache();
try {
    $front = new WP_Post((object) ['ID' => 900001, 'post_type' => 'page', 'post_name' => 'about', 'post_status' => 'publish']);
    $other = new WP_Post((object) ['ID' => 900002, 'post_type' => 'page', 'post_name' => 'verein', 'post_status' => 'draft']);
    $project = new WP_Post((object) ['ID' => 900003, 'post_type' => 'projekt', 'post_name' => 'opening-probe', 'post_status' => 'draft']);
    $formats = iss_editorial_get_registered_formats();
    foreach ($formats as $slug => $format) {
        $assert($format['opening_skin'] === (['landing' => 'frontpage', 'projekt' => 'buehne'][$slug] ?? ''), "$slug declaration preserves capability");
        $assert(iss_editorial_get_opening_skin($format, $other) === ($slug === 'projekt' ? 'buehne' : ''), 'Only the homepage receives the landing opening');
    }
    $assert(iss_editorial_get_opening_skin($formats['landing'], $front) === 'frontpage', 'Homepage receives opening skin');
    $assert(iss_editorial_get_opening_skin($formats['projekt'], $project) === 'buehne', 'Project receives its opening skin');
    $assert($formats['article']['opening_skin'] === '', 'Article does not inherit landing capability');
    $assert(iss_editorial_get_opening_skin([], $front) === '', 'Undeclared capability defaults to absent');
    $front_id = 900002;
    $cached = iss_editorial_get_registered_formats();
    $assert($builds === 1 && $cached === $formats, 'Registry remains cached');
    $assert(iss_editorial_get_opening_skin($cached['landing'], $front) === '', 'Former homepage loses capability without registry reset');
    $assert(iss_editorial_get_opening_skin($cached['landing'], $other) === 'frontpage', 'New homepage gains capability without registry reset');
    $front_id = 0;
    $assert(iss_editorial_get_opening_skin($cached['landing'], $other) === '', 'No static homepage offers no landing opening');
    $assert(iss_editorial_get_opening_skin($cached['projekt'], $project) === 'buehne', 'Homepage setting never affects project capability');

    $eligible = false; $calls = [];
    $probe = static function (array $formats) use (&$eligible, &$calls): array {
        $formats['renamed_opening'] = $formats['projekt'];
        $formats['renamed_opening']['opening_skin'] = 'STANDARD';
        $formats['renamed_opening']['opening_eligibility_callback'] = static function (WP_Post $post, array $format) use (&$eligible, &$calls): bool {
            $calls[] = [$post->ID, $format['slug']];
            return $eligible;
        };
        // Historical format names must not override the new owner declaration.
        $formats['projekt']['opening_skin'] = '';
        $formats['landing']['opening_skin'] = 'typografisch';
        unset($formats['landing']['opening_eligibility_callback']);
        return $formats;
    };
    add_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache();
    $renamed = iss_editorial_get_format('renamed_opening');
    $assert($renamed['opening_skin'] === 'standard', 'Declared skin is normalized');
    $assert(iss_editorial_get_opening_skin($renamed, $project) === '', 'Owner callback can deny a renamed format');
    $eligible = true;
    $assert(iss_editorial_get_opening_skin($renamed, $other) === 'standard', 'Same cached definition observes live eligibility');
    $assert($calls === [[900003, 'renamed_opening'], [900002, 'renamed_opening']], 'Callback receives the current post and normalized format on each resolution');
    $assert(iss_editorial_get_opening_skin(iss_editorial_get_format('projekt'), $project) === '', 'Project name cannot force an opening');
    $assert(iss_editorial_get_opening_skin(iss_editorial_get_format('landing'), $other) === 'typografisch', 'Landing name cannot force skin or eligibility');
    $assert(iss_editorial_registry_errors() === [], 'Renamed owner declarations are valid');
    foreach ([
        [['opening_skin' => 'unknown'], 'unknown opening skin'],
        [['opening_eligibility_callback' => 'missing_opening_callback'], 'invalid opening eligibility callback'],
    ] as [$override, $message]) {
        $fault = static function (array $formats) use ($override): array {
            $formats['renamed_opening'] = array_replace($formats['renamed_opening'], $override);
            return $formats;
        };
        add_filter('iss_editorial_formats', $fault, 160); iss_editorial_reset_format_cache();
        $assert(in_array('renamed_opening: ' . $message, iss_editorial_registry_errors(), true), 'Registry diagnoses ' . $message);
        remove_filter('iss_editorial_formats', $fault, 160); $fault = null; iss_editorial_reset_format_cache();
    }
} finally {
    if ($fault) { remove_filter('iss_editorial_formats', $fault, 160); }
    if ($probe) { remove_filter('iss_editorial_formats', $probe, 150); }
    remove_filter('iss_editorial_formats', $count_builds, 200);
    remove_filter('pre_option_page_on_front', $front_option);
    iss_editorial_reset_format_cache();
}
$assert(iss_editorial_registry_errors() === [], 'Original registry restored');
WP_CLI::success("$checks opening-skin contract checks; no persistent fixtures.");
