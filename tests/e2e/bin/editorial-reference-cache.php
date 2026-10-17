<?php
/** wp eval-file: native reference-cache batching in connection-local fixture tables. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Connection-local temporary fixtures only.
global $wpdb;
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$assert(!wp_using_ext_object_cache(), 'Fixture caches must be local to this process');
$seed = $wpdb->get_row("SELECT * FROM {$wpdb->posts} LIMIT 1", ARRAY_A);
$tables = [];
$ids = [];
$media = static fn(int $id): array => ['source' => 'wp-media', 'kind' => 'media', 'id' => (string) $id];
$object = static fn(int $id): array => ['source' => 'iss-archive', 'kind' => 'archive_object', 'id' => (string) $id];
$cold = static function () use (&$ids): void {
    foreach ($ids as $id) { wp_cache_delete($id, 'posts'); wp_cache_delete($id, 'post_meta'); }
};
$measure = static function (callable $read): array {
    global $wpdb;
    $before = $wpdb->num_queries;
    $result = $read();
    return [$result, $wpdb->num_queries - $before];
};
$guard = static function (string $sql) use ($wpdb): string {
    if (preg_match('/^\s*(?:INSERT(?: IGNORE)? INTO|REPLACE INTO|UPDATE|DELETE FROM)\s+`?([^`\s]+)/i', $sql, $match)
        && !in_array($match[1], [$wpdb->posts, $wpdb->postmeta], true)) {
        throw new RuntimeException('Unexpected write outside fixture tables: ' . $match[1]);
    }
    return $sql;
};
$create = static function (int $id, string $type = 'attachment', string $status = 'inherit', string $mime = 'image/jpeg') use ($wpdb, $seed, &$ids, $assert): void {
    $row = array_merge($seed, [
        'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_parent' => 0,
        'post_title' => 'Reference fixture ' . $id, 'post_name' => 'reference-fixture-' . $id,
        'post_content' => '', 'post_excerpt' => '', 'post_password' => '', 'post_mime_type' => $mime,
        'guid' => 'https://example.invalid/fixture-' . $id . '.jpg',
    ]);
    $assert($wpdb->insert($wpdb->posts, $row) !== false, 'Insert temporary post');
    $ids[] = $id;
    if ($type === 'attachment') {
        foreach (['_wp_attached_file' => "2026/10/fixture-$id.jpg", '_wp_attachment_metadata' => [
            'width' => 1200, 'height' => 800, 'file' => "2026/10/fixture-$id.jpg", 'sizes' => [],
        ]] as $key => $value) {
            $assert($wpdb->insert($wpdb->postmeta, ['post_id' => $id, 'meta_key' => $key, 'meta_value' => maybe_serialize($value)]) !== false, 'Insert temporary attachment metadata');
        }
    }
};
try {
    foreach ([$wpdb->posts, $wpdb->postmeta] as $table) {
        $schema = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 1);
        $assert($wpdb->query($schema) !== false, 'Create temporary fixture table');
        $tables[] = $table;
    }
    add_filter('query', $guard);
    for ($id = 1900000200; $id < 1900000240; ++$id) { $create($id); }
    $create(1900000240, 'attachment', 'inherit', 'application/pdf');
    $create(1900000241, 'archivobjekt', 'publish', '');
    $create(1900000242, 'archivobjekt', 'trash', '');
    $create(1900000243, 'projekt', 'draft', '');
    $create(1900000244, 'archivobjekt', 'private', '');
    update_post_meta(1900000241, '_thumbnail_id', 1900000239);
    update_post_meta(1900000244, '_thumbnail_id', 1900000238);

    $refs = array_map($media, range(1900000200, 1900000229));
    $document = ['schema_version' => 3, 'skin' => 'buehne', 'features' => ['identity' => ['media_refs' => [$media(1900000230)]]], 'sections' => [
        ['type' => 'galerie', 'media_refs' => array_merge($refs, [$refs[0], $media(1900000240)]), 'object_refs' => [$object(1900000241)]],
        ['type' => 'gateway', 'items' => [['label' => 'Nested', 'url' => 'https://example.invalid/nested', 'media_refs' => [$media(1900000231)]]]],
    ], 'deleted_sections' => [['type' => 'galerie', 'media_refs' => [$media(1900000232)], 'items' => [['media_refs' => [$media(1900000233)]]]]]];
    $key = iss_editorial_get_document_meta_key('projekt');
    $assert($wpdb->insert($wpdb->postmeta, ['post_id' => 1900000243, 'meta_key' => $key, 'meta_value' => iss_editorial_encode_document($document)]) !== false, 'Insert temporary document');
    $canonical = iss_editorial_get_document(1900000243, 'projekt');
    $expected = $canonical;
    $cold();
    $before = $wpdb->num_queries;
    foreach ($expected['sections'] as &$section) {
        foreach (['object_refs', 'media_refs'] as $field) {
            if (empty($section[$field])) { continue; }
            $section[$field . '_resolved'] = array_map(static fn($ref) => ['reference' => $ref, 'resolved' => iss_editorial_resolve_reference($ref)], $section[$field]);
        }
    }
    unset($section);
    $unprimed_queries = $wpdb->num_queries - $before;
    $cold();
    get_post(1900000243);
    get_post_meta(1900000243, $key, true); // Keep the document read outside the reference query budget.
    [$actual, $queries] = $measure(static fn() => iss_editorial_get_read_model(1900000243, 'projekt'));
    $assert(wp_json_encode($actual) === wp_json_encode($expected), 'Batching preserves complete read model, reference order and duplicate entries');
    // Project identity validation reads its logo before the reference boundary (two queries).
    $assert($queries <= 6 && $unprimed_queries > 60, "Large mixed document batches references after identity validation: $unprimed_queries -> $queries queries");
    $assert(wp_cache_get(1900000230, 'post_meta') !== false && wp_cache_get(1900000231, 'post_meta') !== false, 'Public boundary includes identity and nested item media');
    $assert(wp_cache_get(1900000232, 'posts') === false && wp_cache_get(1900000233, 'posts') === false, 'Public boundary excludes deleted sections');
    [$warm, $queries] = $measure(static fn() => iss_editorial_get_read_model(1900000243, 'projekt'));
    $assert(wp_json_encode($warm) === wp_json_encode($actual) && $queries === 0, 'Repeated reads reuse native caches with identical output');

    $cold();
    $hydrated_expected = $document;
    $hydrated_expected['features']['identity']['media_refs'] = array_map('iss_editorial_hydrate_reference_preview', $document['features']['identity']['media_refs']);
    $hydrated_expected['sections'][0]['object_refs'] = array_map('iss_editorial_hydrate_reference_preview', $document['sections'][0]['object_refs']);
    $hydrated_expected['sections'][0]['media_refs'] = array_map('iss_editorial_hydrate_reference_preview', $document['sections'][0]['media_refs']);
    $hydrated_expected['sections'][1]['items'][0]['media_refs'] = [iss_editorial_hydrate_reference_preview($media(1900000231))];
    $cold();
    [$hydrated, $queries] = $measure(static fn() => iss_editorial_hydrate_document_previews($document));
    $assert($hydrated === $hydrated_expected && $queries <= 4, 'Editor batches active/nested/identity media and archive thumbnails while preserving opaque deleted sections');
    $assert(wp_cache_get(1900000232, 'posts') === false && wp_cache_get(1900000233, 'posts') === false, 'Deleted references are not fetched or hydrated');
    $assert(wp_cache_get(1900000241, 'posts') !== false, 'Editor primes archive objects for refreshed thumbnails');
    $assert($hydrated['sections'][0]['media_refs'][31]['mime'] === 'application/pdf', 'Non-image attachment behavior is preserved');

    $special = [$object(1900000242), $object(1900000244), $media(1900000241), $media(1900000299), $object(1900000299),
        ['source' => 'foreign', 'kind' => 'media', 'id' => '1900000200'], $media(0), []];
    $special_expected = array_map('iss_editorial_resolve_reference', $special);
    $cold();
    iss_editorial_prime_document_references(['sections' => [['media_refs' => $special]]]);
    $assert(wp_json_encode(array_map('iss_editorial_resolve_reference', $special)) === wp_json_encode($special_expected), 'Missing, trashed, private, wrong-type, zero and unknown references keep existing resolver behavior');
    $assert($special_expected[0] === [] && $special_expected[2] === [] && $special_expected[3] === [], 'Invalid and missing references still resolve to empty results');
    $preserved = $media(1900000200) + ['label' => 'Editor caption', 'thumbnail' => 'https://example.invalid/editor.jpg', 'width' => '7', 'height' => '9', 'mime' => 'image/custom'];
    $assert(iss_editorial_hydrate_document_previews(['sections' => [['media_refs' => [$preserved, $media(1900000299)]]]])['sections'][0]['media_refs'] === [iss_editorial_hydrate_reference_preview($preserved), $media(1900000299)], 'Editor captions and missing-reference placeholders stay untouched while previews refresh');
    foreach ([[], ['sections' => [['media_refs' => [[], 'invalid', ['source' => 'foreign', 'kind' => 'media', 'id' => '1900000299']]]]]] as $empty) {
        [, $queries] = $measure(static fn() => iss_editorial_prime_document_references($empty));
        $assert($queries === 0, 'Empty or unsupported reference lists cause no priming queries');
    }

    // Use native metadata invalidation; there is no second resolved-result cache to go stale.
    $metadata = wp_get_attachment_metadata(1900000200);
    $metadata['width'] = 640;
    update_post_meta(1900000200, '_wp_attachment_metadata', $metadata);
    $assert(iss_editorial_get_read_model(1900000243, 'projekt')['sections'][0]['media_refs_resolved'][0]['resolved']['width'] === '640', 'Native metadata updates refresh resolution in the same request');
    update_post_meta(1900000241, '_thumbnail_id', 1900000237);
    $changed = iss_editorial_get_read_model(1900000243, 'projekt');
    $assert($changed['sections'][0]['object_refs_resolved'][0]['resolved']['thumbnail'] === wp_get_attachment_image_url(1900000237, 'medium'), 'Changed archive thumbnails follow native invalidation');
    $draft = $document;
    $draft['sections'] = [['type' => 'galerie', 'media_refs' => [$media(1900000234)]]];
    $cold();
    $draft_preview = iss_editorial_hydrate_document_previews($draft);
    $assert($draft_preview['sections'][0]['media_refs'][0]['id'] === '1900000234' && wp_cache_get(1900000200, 'posts') === false, 'Priming follows the supplied draft document, not the saved canonical references');
    $assert(get_post_meta(1900000243, $key, true) === iss_editorial_encode_document($document), 'Reference reads never rewrite the stored document');
    WP_CLI::success("Reference cache: $checks checks passed; fixture reads $unprimed_queries -> 6 or fewer queries including identity validation; no persistent records written.");
} finally {
    remove_filter('query', $guard);
    foreach (array_reverse($tables) as $table) { $wpdb->query("DROP TEMPORARY TABLE IF EXISTS `$table`"); }
    $cold();
}
