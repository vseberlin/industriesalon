<?php
/** wp eval-file: legacy body policy follows section ownership, not consumer names. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$formats = iss_editorial_get_registered_formats();
$legacy = ['fuehrung' => ['bildbuehne', 'intro', 'kapitel', 'leitfrage', 'material', 'schluss'], 'projekt' => ['kapitel', 'fliesstext', 'schluss']];
$basic_count = 0;
foreach ($formats as $slug => $format) {
    foreach ($format['sections'] as $type => $section) {
        $basic = in_array($type, $legacy[$slug] ?? [], true);
        $assert($section['legacy_body_policy'] === ($basic ? 'basic' : 'post'), "$slug.$type retains its fallback policy");
        $basic_count += (int) $basic;
    }
}
$assert($basic_count === 9, 'Exactly nine sections retain the basic fallback');
$probe = static function (array $formats): array {
    $formats['renamed_owner'] = $formats['projekt'];
    $formats['renamed_owner']['sections']['renamed_body'] = $formats['projekt']['sections']['kapitel'];
    $formats['projekt']['sections']['kapitel']['legacy_body_policy'] = 'post';
    return $formats;
};
add_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache();
try {
    $renamed = iss_editorial_get_format('renamed_owner');
    $project = iss_editorial_get_format('projekt');
    $assert($renamed['sections']['renamed_body']['legacy_body_policy'] === 'basic', 'Renamed owner and section retain the policy');
    $assert(iss_editorial_registry_errors() === [], 'Renamed declarations are valid');
    $cases = [
        ['<p>Plain &amp; escaped &lt;tag&gt;.</p>', '<p>Plain &amp; escaped &lt;tag&gt;.</p>'],
        ['<p class="drop" style="color:red">Keep <strong>bold</strong><em>italic</em><br>line</p><ul><li>One</li></ul><ol><li>Two</li></ol>', '<p>Keep <strong>bold</strong><em>italic</em><br>line</p><ul><li>One</li></ul><ol><li>Two</li></ol>'],
        ['<h2>Heading</h2><div>Box</div><table><tr><td>Cell</td></tr></table><img src="/x.jpg">', 'HeadingBoxCell'],
        ['<span class="iss-ink-123abc">Colour</span>', 'Colour'],
        ['<a href="/archive/" target="_blank" rel="nofollow" title="Hint">Link</a>', '<a href="/archive/">Link</a>'],
    ];
    foreach (['/relative/', '#anchor', '?q=test', '../parent', './child', '//example.invalid/path', 'relative-path', 'http://example.invalid/', 'https://example.invalid/', 'mailto:test@example.invalid', 'tel:+4930'] as $href) {
        $link = '<a href="' . $href . '">Link</a>'; $cases[] = [$link, $link];
    }
    foreach (['javascript:alert(1)', 'jav&#x61;script:alert(1)', 'data:text/html,bad', 'ftp://example.invalid/', ''] as $href) {
        $cases[] = ['<a href="' . $href . '">Link</a>', '<a>Link</a>'];
    }
    $cases[] = ["<a href='javascript:bad()'>Single</a><a href=javascript:bad()>Bare</a>", '<a>Single</a><a>Bare</a>'];
    foreach ([1, 2, 3] as $version) {
        $basic = $renamed; $basic['sections']['renamed_body']['rich_text'] = [];
        $post = $project; $post['sections']['kapitel']['rich_text'] = [];
        foreach ($cases as [$input, $expected]) {
            $assert(iss_editorial_sanitize_body_html($input, $basic, 'renamed_body', $version) === $expected, "v$version basic HTML and safe links retained");
            $assert(iss_editorial_sanitize_body_html($input, $post, 'kapitel', $version) === wp_kses_post($input), "v$version explicit post policy overrides the historical consumer name");
        }
        $profiled = $renamed;
        $coloured = '<p><span class="iss-ink-123abc">Colour</span> <a href="/archive/">Link</a></p>';
        $expected = $version === 1 ? '<p>Colour <a href="/archive/">Link</a></p>' : $coloured;
        $assert(iss_editorial_sanitize_body_html($coloured, $profiled, 'renamed_body', $version) === $expected, "v$version keeps rich-text profile precedence");
        $doc = iss_editorial_get_empty_document('renamed_owner'); $doc['schema_version'] = $version;
        $doc['sections'] = [['type' => 'renamed_body', 'title' => 'Retained body', 'body' => '<p>Keep <strong>bold</strong> and <a href="/archive/">link</a>.</p>']];
        $doc['deleted_sections'] = [['type' => 'retired', 'body' => '<iframe src="/opaque/">Keep trash exact</iframe>']];
        $valid = iss_editorial_validate_document($doc, 'renamed_owner');
        $assert(!is_wp_error($valid) && $valid['sections'][0]['body'] === $doc['sections'][0]['body'] && $valid['deleted_sections'] === $doc['deleted_sections'], "v$version retains valid body and opaque trash");
        $doc['sections'][0]['body'] = '<p><a href="javascript:bad()">Rejected link</a></p>';
        $assert(is_wp_error(iss_editorial_validate_document($doc, 'renamed_owner')), "v$version rejects lossy unsafe body input");
    }
    $missing = $renamed; unset($missing['sections']['renamed_body']['legacy_body_policy']);
    $assert(iss_editorial_sanitize_body_html('<h2>Heading</h2>', $missing, 'renamed_body', 1) === '<h2>Heading</h2>', 'Absent policy retains WordPress post HTML');
    $invalid = static function (array $formats): array { $formats['projekt']['sections']['kapitel']['legacy_body_policy'] = 'unknown'; return $formats; };
    add_filter('iss_editorial_formats', $invalid, 160); iss_editorial_reset_format_cache();
    try { $assert(in_array('projekt.kapitel: unknown legacy body policy', iss_editorial_registry_errors(), true), 'Unknown policy has an owner diagnostic'); }
    finally { remove_filter('iss_editorial_formats', $invalid, 160); iss_editorial_reset_format_cache(); }
} finally { remove_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache(); }
$assert(wp_json_encode(iss_editorial_get_registered_formats()) === wp_json_encode($formats), 'Registry restored');
WP_CLI::success("$checks legacy body checks; nine basic declarations; no persistent fixtures.");
