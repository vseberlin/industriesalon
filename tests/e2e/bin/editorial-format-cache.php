<?php
/** Run: wp --require=/tests/editorial-format-cache.php eval-file /tests/editorial-format-cache.php */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }

// WordPress converts these preinitialized hooks during bootstrap. No database fixtures.
if (!function_exists('add_action')) {
    // Exercise the native switcher even in CLI containers without language packs.
    $GLOBALS['iss_editorial_cache_test_languages'] = static fn($languages) => array_unique(array_merge($languages, ['de_DE']));
    $GLOBALS['wp_filter']['get_available_languages'][10][] = [
        'function' => $GLOBALS['iss_editorial_cache_test_languages'], 'accepted_args' => 1,
    ];
    $GLOBALS['wp_filter']['after_setup_theme'][PHP_INT_MAX][] = [
        'function' => static function (): void {
            $builds = 0;
            $count = static function ($formats) use (&$builds) { ++$builds; return $formats; };
            add_filter('iss_editorial_formats', $count, PHP_INT_MAX);
            iss_editorial_get_registered_formats();
            iss_editorial_get_registered_formats();
            remove_filter('iss_editorial_formats', $count, PHP_INT_MAX);
            $GLOBALS['iss_editorial_cache_test_startup']['before_init'] = $builds;
        },
        'accepted_args' => 0,
    ];
    $GLOBALS['wp_filter']['init'][PHP_INT_MAX][] = [
        'function' => static function (): void {
            $builds = 0;
            $fixture = static function ($formats) use (&$builds) {
                ++$builds;
                $formats['cache_fixture'] = ['post_types' => ['iss_cache_fixture'], 'sections' => ['text' => []]];
                return $formats;
            };
            add_filter('iss_editorial_formats', $fixture, PHP_INT_MAX);
            try {
                $before = iss_editorial_get_registered_formats();
                iss_editorial_get_registered_formats();
                register_post_type('iss_cache_fixture', ['public' => false]);
                $after = iss_editorial_get_registered_formats();
                $GLOBALS['iss_editorial_cache_test_startup']['during_init'] = [
                    'builds' => $builds,
                    'before' => isset($before['cache_fixture']),
                    'after' => isset($after['cache_fixture']),
                ];
            } finally {
                remove_filter('iss_editorial_formats', $fixture, PHP_INT_MAX);
                unregister_post_type('iss_cache_fixture');
            }
        },
        'accepted_args' => 0,
    ];
    return;
}

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$startup = $GLOBALS['iss_editorial_cache_test_startup'] ?? [];
$assert(($startup['before_init'] ?? 0) === 2, 'Before init, each read observes current registrations');
$assert(($startup['during_init']['builds'] ?? 0) === 3, 'During init, late callbacks cannot freeze the registry');
$assert($startup['during_init']['before'] === false, 'Unregistered post types are excluded');
$assert($startup['during_init']['after'] === true, 'A late init post type is visible on the next read');
$assert(!isset(iss_editorial_get_registered_formats()['cache_fixture']), 'Startup definitions cannot leak into the post-init cache');

$builds = 0;
$count = static function ($formats) use (&$builds) { ++$builds; return $formats; };
$changed = static function ($formats) {
    $formats['projekt']['label'] = 'Cache invalidation fixture';
    return $formats;
};
$translation = static function ($translated, $text) {
    return $text === 'Projekt' && get_locale() === 'de_DE' ? 'Übersetztes Testprojekt' : $translated;
};
$original_blog_id = get_current_blog_id();
$original_locale = get_locale();
$locale_switched = false;
add_filter('iss_editorial_formats', $count, PHP_INT_MAX);
add_filter('gettext_iss-content-model', $translation, 10, 2);
iss_editorial_reset_format_cache();
try {
    $formats = iss_editorial_get_registered_formats();
    // Rebuilt eligibility closures have new identities; compare their data contract separately.
    $contract = wp_json_encode($formats);
    $assert(count($formats) === 9, 'All nine owner/theme contracts are present');
    foreach ($formats as $slug => $format) {
        $assert(iss_editorial_get_format($slug) === $format, "$slug: warm reads retain the complete contract");
    }
    $document = iss_editorial_get_document(30743, 'projekt');
    $assert(iss_editorial_get_document(30743, 'projekt') === $document, 'Repeated document reads are identical');
    $assert($builds === 1, 'Format and document consumers share one post-init registry build');
    $mutated = $formats;
    $mutated['projekt']['sections']['feature']['label'] = 'Caller-only edit';
    $assert(iss_editorial_get_registered_formats() === $formats, 'Caller edits cannot mutate cached arrays');

    $post = new WP_Post((object) ['ID' => -1, 'post_type' => 'page', 'post_name' => 'cache-ineligible']);
    $assert(iss_editorial_get_format_for_post($post)['slug'] === 'article', 'Ordinary page resolves to article');
    $post->post_name = 'about';
    $assert(iss_editorial_get_format_for_post($post)['slug'] === 'landing', 'Eligibility observes changed post state with a warm cache');
    $post->post_name = 'cache-ineligible';
    $assert(iss_editorial_get_format_for_post($post)['slug'] === 'article', 'Eligibility is evaluated on every read');
    $assert($builds === 1, 'Eligibility checks do not rebuild static definitions');

    add_filter('iss_editorial_formats', $changed, 120);
    iss_editorial_reset_format_cache();
    $assert($builds === 1, 'Reset invalidates lazily');
    $assert(iss_editorial_get_format('projekt')['label'] === 'Cache invalidation fixture', 'Reset observes a newly added filter');
    $assert($builds === 2, 'The first read after reset builds once');
    remove_filter('iss_editorial_formats', $changed, 120);
    iss_editorial_reset_format_cache();
    $assert(wp_json_encode(iss_editorial_get_registered_formats()) === $contract, 'Reset observes filter removal');
    $assert(iss_editorial_get_document(30743, 'projekt') === $document, 'Fresh and cached document results match');
    $assert($builds === 3, 'Fresh document read reuses the rebuilt definitions');

    $target_locale = $original_locale === 'en_US' ? 'de_DE' : 'en_US';
    $locale_switched = switch_to_locale($target_locale);
    $assert($locale_switched && get_locale() === $target_locale, 'Native locale switch uses the in-memory language fixture');
    $localized = iss_editorial_get_registered_formats();
    $assert(iss_editorial_get_registered_formats() === $localized && $builds === 4, 'A switched locale gets one new registry build');
    $assert($localized['projekt']['label'] === __('Projekt', 'iss-content-model'), 'Registry labels follow the active translation context');
    $assert($localized['projekt']['label'] !== $formats['projekt']['label'], 'The switched locale does not reuse translated labels from the previous locale');
    restore_previous_locale();
    $locale_switched = false;
    $assert(get_locale() === $original_locale && wp_json_encode(iss_editorial_get_registered_formats()) === $contract, 'Restoring the locale restores the original definitions');
    $assert($builds === 5, 'Restored locale is rebuilt once');

    // Single-site harness: change only the in-memory blog context read by get_current_blog_id().
    $GLOBALS['blog_id'] = $original_blog_id + 1;
    iss_editorial_get_registered_formats();
    iss_editorial_get_registered_formats();
    $assert($builds === 6, 'A different blog context cannot reuse the previous registry');
    $GLOBALS['blog_id'] = $original_blog_id;
    $assert(wp_json_encode(iss_editorial_get_registered_formats()) === $contract && $builds === 7, 'Restoring the blog context rebuilds the original contract');
    WP_CLI::log("PASS: $checks registry cache lifecycle checks; no content writes.");
} finally {
    $GLOBALS['blog_id'] = $original_blog_id;
    if ($locale_switched) { restore_previous_locale(); }
    remove_filter('iss_editorial_formats', $changed, 120);
    remove_filter('iss_editorial_formats', $count, PHP_INT_MAX);
    remove_filter('gettext_iss-content-model', $translation, 10);
    remove_filter('get_available_languages', $GLOBALS['iss_editorial_cache_test_languages'], 10);
    iss_editorial_reset_format_cache();
    unset($GLOBALS['iss_editorial_cache_test_startup'], $GLOBALS['iss_editorial_cache_test_languages']);
}
