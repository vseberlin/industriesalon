<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_editorial_cli_get_post_by_token(string $token): ?WP_Post
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    if (ctype_digit($token)) {
        $post = get_post((int) $token);
    } else {
        $post = get_page_by_path(sanitize_title($token), OBJECT, 'ausstellung');
    }

    if (!$post instanceof WP_Post || $post->post_type !== 'ausstellung') {
        return null;
    }

    return $post;
}

function iss_editorial_cli_strip_html(string $html): string
{
    return trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($html)) ?? '');
}

function iss_editorial_cli_block_text(array $block): string
{
    $text = iss_editorial_cli_strip_html((string) ($block['innerHTML'] ?? ''));
    if ($text !== '') {
        return $text;
    }

    $parts = [];
    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (is_array($inner_block)) {
            $inner_text = iss_editorial_cli_block_text($inner_block);
            if ($inner_text !== '') {
                $parts[] = $inner_text;
            }
        }
    }

    return implode(' ', $parts);
}

function iss_editorial_cli_classify_block(array $block): string
{
    $block_name = (string) ($block['blockName'] ?? '');

    if (in_array($block_name, ['core/group', 'core/columns', 'core/column'], true)) {
        return 'container';
    }

    if ($block_name === 'core/heading') {
        return 'heading';
    }

    if (in_array($block_name, ['core/paragraph', 'core/list', 'core/quote'], true)) {
        return 'body';
    }

    if (in_array($block_name, ['core/image', 'core/gallery', 'core/media-text'], true)) {
        return 'media';
    }

    return $block_name === '' ? 'freeform' : 'unsupported';
}

function iss_editorial_cli_media_reference_from_attachment(int $attachment_id, string $label = ''): array
{
    if ($attachment_id <= 0) {
        return [];
    }

    $post = get_post($attachment_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'attachment') {
        return [];
    }

    $label = trim($label) !== '' ? trim($label) : (string) get_the_title($post);

    return [
        'kind' => 'media',
        'source' => 'wp-media',
        'id' => (string) $attachment_id,
        'label' => $label,
        'thumbnail' => (string) wp_get_attachment_image_url($attachment_id, 'medium'),
    ];
}

function iss_editorial_cli_image_caption(array $block): string
{
    $html = (string) ($block['innerHTML'] ?? '');
    if ($html === '') {
        return '';
    }

    if (!preg_match('/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', $html, $matches)) {
        return '';
    }

    return iss_editorial_cli_strip_html((string) $matches[1]);
}

function iss_editorial_cli_collect_media_refs(array $block): array
{
    $refs = [];
    $block_name = (string) ($block['blockName'] ?? '');
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

    if ($block_name === 'core/image') {
        $caption = iss_editorial_cli_image_caption($block);
        $ref = iss_editorial_cli_media_reference_from_attachment(absint($attrs['id'] ?? 0), $caption);
        if ($ref) {
            $refs[$ref['id']] = $ref;
        }
    }

    if ($block_name === 'core/media-text') {
        $ref = iss_editorial_cli_media_reference_from_attachment(absint($attrs['mediaId'] ?? 0), iss_editorial_cli_block_text($block));
        if ($ref) {
            $refs[$ref['id']] = $ref;
        }
    }

    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (!is_array($inner_block)) {
            continue;
        }

        foreach (iss_editorial_cli_collect_media_refs($inner_block) as $ref) {
            $refs[$ref['id']] = $ref;
        }
    }

    return array_values($refs);
}

function iss_editorial_cli_count_media_blocks(array $block): int
{
    $count = iss_editorial_cli_classify_block($block) === 'media' ? 1 : 0;
    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (is_array($inner_block)) {
            $count += iss_editorial_cli_count_media_blocks($inner_block);
        }
    }

    return $count;
}

function iss_editorial_cli_collect_text_parts(array $block, array &$parts): void
{
    $block_name = (string) ($block['blockName'] ?? '');
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

    if (iss_editorial_cli_classify_block($block) === 'media' || $block_name === 'core/navigation' || $block_name === 'core/navigation-link') {
        return;
    }

    $text = iss_editorial_cli_strip_html((string) ($block['innerHTML'] ?? ''));
    if ($block_name === 'core/heading' && $text !== '') {
        $parts['title'] = $parts['title'] === '' ? $text : $parts['title'];
    } elseif (in_array($block_name, ['core/paragraph', 'core/list'], true) && $text !== '') {
        $class_name = (string) ($attrs['className'] ?? '');
        if (!str_contains($class_name, 'iss-kicker')) {
            $parts['body'][] = $text;
        }
    } elseif ($block_name === 'core/quote' && $text !== '') {
        $parts['body'][] = $text;
    } elseif ($block_name === '' && $text !== '') {
        $parts['body'][] = $text;
    }

    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (is_array($inner_block)) {
            iss_editorial_cli_collect_text_parts($inner_block, $parts);
        }
    }
}

function iss_editorial_cli_build_media_section(array $block, array $media_refs): array
{
    $parts = [
        'title' => '',
        'body' => [],
    ];
    iss_editorial_cli_collect_text_parts($block, $parts);

    return [
        'type' => 'bildstrecke',
        'title' => $parts['title'] !== '' ? $parts['title'] : __('Dokumentarische Strecke', 'iss-editorial'),
        'body' => implode("\n\n", array_filter(array_map('trim', $parts['body']))),
        'media_refs' => $media_refs,
    ];
}

function iss_editorial_cli_append_body(array &$sections, string $body): void
{
    if ($body === '') {
        return;
    }

    if (!$sections) {
        $sections[] = [
            'type' => 'kapitel',
            'title' => __('Einleitung', 'iss-editorial'),
            'body' => '',
        ];
    }

    $last_index = count($sections) - 1;
    $separator = $sections[$last_index]['body'] === '' ? '' : "\n\n";
    $sections[$last_index]['body'] .= $separator . $body;
}

function iss_editorial_cli_process_ausstellung_block(array $block, array &$sections, array &$unsupported_blocks, int &$media_blocks): void
{
    $kind = iss_editorial_cli_classify_block($block);
    $text = iss_editorial_cli_block_text($block);

    if ($kind === 'container') {
        $media_refs = iss_editorial_cli_collect_media_refs($block);
        if ($media_refs) {
            $media_blocks += iss_editorial_cli_count_media_blocks($block);
            $sections[] = iss_editorial_cli_build_media_section($block, $media_refs);
            return;
        }

        foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
            if (is_array($inner_block)) {
                iss_editorial_cli_process_ausstellung_block($inner_block, $sections, $unsupported_blocks, $media_blocks);
            }
        }
        return;
    }

    if ($kind === 'heading') {
        $sections[] = [
            'type' => 'kapitel',
            'title' => $text !== '' ? $text : __('Ohne Titel', 'iss-editorial'),
            'body' => '',
        ];
        return;
    }

    if ($kind === 'body' || $kind === 'freeform') {
        iss_editorial_cli_append_body($sections, $text);
        return;
    }

    if ($kind === 'media') {
        ++$media_blocks;
        $media_refs = iss_editorial_cli_collect_media_refs($block);
        if ($media_refs) {
            $sections[] = iss_editorial_cli_build_media_section($block, $media_refs);
        }
        return;
    }

    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (is_array($inner_block)) {
            iss_editorial_cli_process_ausstellung_block($inner_block, $sections, $unsupported_blocks, $media_blocks);
        }
    }

    $block_name = (string) ($block['blockName'] ?? '');
    if ($block_name !== '') {
        $unsupported_blocks[$block_name] = true;
    }
}

function iss_editorial_cli_build_ausstellung_candidate(WP_Post $post): array
{
    $blocks = parse_blocks((string) $post->post_content);
    $sections = [];
    $unsupported_blocks = [];
    $media_blocks = 0;

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        iss_editorial_cli_process_ausstellung_block($block, $sections, $unsupported_blocks, $media_blocks);
    }

    $sections = array_values(array_filter($sections, static function (array $section): bool {
        return trim((string) $section['title']) !== ''
            || trim((string) $section['body']) !== ''
            || !empty($section['media_refs']);
    }));

    return [
        'document' => [
            'schema_version' => 1,
            'skin' => 'standard',
            'variant' => 'standard',
            'sections' => $sections,
        ],
        'legacy_blocks' => count($blocks),
        'media_blocks' => $media_blocks,
        'unsupported_blocks' => array_keys($unsupported_blocks),
    ];
}

function iss_editorial_cli_print_rows(string $format, array $rows, array $fields): void
{
    if ($format === 'json') {
        fwrite(STDOUT, (string) wp_json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        return;
    }

    fwrite(STDOUT, implode("\t", $fields) . PHP_EOL);
    foreach ($rows as $row) {
        $values = [];
        foreach ($fields as $field) {
            $values[] = isset($row[$field]) ? (string) $row[$field] : '';
        }
        fwrite(STDOUT, implode("\t", $values) . PHP_EOL);
    }
}

function iss_editorial_cli_ausstellung_dry_run(array $args, array $assoc_args): void
{
    unset($args);

    $posts_arg = isset($assoc_args['posts']) ? (string) $assoc_args['posts'] : 'kinder-im-werk,frauen-in-werk';
    $format = isset($assoc_args['format']) ? (string) $assoc_args['format'] : 'table';
    $tokens = array_filter(array_map('trim', explode(',', $posts_arg)));
    $rows = [];

    foreach ($tokens as $token) {
        $post = iss_editorial_cli_get_post_by_token($token);
        if (!$post instanceof WP_Post) {
            WP_CLI::warning(sprintf('Ausstellung not found: %s', $token));
            continue;
        }

        $candidate = iss_editorial_cli_build_ausstellung_candidate($post);
        $stored = get_post_meta((int) $post->ID, iss_editorial_get_document_meta_key('ausstellung'), true);
        $enabled = iss_editorial_document_is_enabled((int) $post->ID, 'ausstellung');
        $section_types = array_unique(array_map(static function (array $section): string {
            return (string) ($section['type'] ?? '');
        }, (array) $candidate['document']['sections']));

        $notes = ['read_only', 'legacy_fallback'];
        if (is_string($stored) && trim($stored) !== '') {
            $notes[] = 'existing_json_document';
        }
        if (!empty($candidate['media_blocks']) || !empty($candidate['unsupported_blocks'])) {
            $notes[] = 'manual_review_required';
        }

        $rows[] = [
            'ID' => (int) $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'editorial_doc' => is_string($stored) && trim($stored) !== '' ? 'yes' : 'no',
            'enabled' => $enabled ? 'yes' : 'no',
            'legacy_blocks' => (int) $candidate['legacy_blocks'],
            'legacy_chars' => strlen(wp_strip_all_tags((string) $post->post_content)),
            'candidate_sections' => count((array) $candidate['document']['sections']),
            'section_types' => implode(',', array_filter($section_types)),
            'media_blocks' => (int) $candidate['media_blocks'],
            'unsupported_blocks' => implode(',', (array) $candidate['unsupported_blocks']),
            'notes' => implode(',', $notes),
        ];
    }

    if (!$rows) {
        WP_CLI::error('No Ausstellung posts resolved for dry run.');
    }

    iss_editorial_cli_print_rows($format, $rows, [
        'ID',
        'title',
        'slug',
        'status',
        'editorial_doc',
        'enabled',
        'legacy_blocks',
        'legacy_chars',
        'candidate_sections',
        'section_types',
        'media_blocks',
        'unsupported_blocks',
        'notes',
    ]);
}

WP_CLI::add_command('iss-editorial ausstellung-dry-run', 'iss_editorial_cli_ausstellung_dry_run');

function iss_editorial_cli_ausstellung_import_candidate(array $args, array $assoc_args): void
{
    unset($args);

    $token = isset($assoc_args['post']) ? trim((string) $assoc_args['post']) : '';
    if ($token === '') {
        WP_CLI::error('Missing required --post=<ausstellung-id-or-slug>.');
        return;
    }

    $post = iss_editorial_cli_get_post_by_token($token);
    if (!$post instanceof WP_Post) {
        WP_CLI::error(sprintf('Ausstellung not found: %s', $token));
        return;
    }

    $format_slug = 'ausstellung';
    $document_key = iss_editorial_get_document_meta_key($format_slug);
    $autosave_key = iss_editorial_get_autosave_meta_key($format_slug);
    $stored = get_post_meta((int) $post->ID, $document_key, true);
    $force = isset($assoc_args['force']);

    if (is_string($stored) && trim($stored) !== '' && !$force) {
        WP_CLI::error(sprintf('Post %d already has an editorial document. Use --force to replace it.', (int) $post->ID));
        return;
    }

    $candidate = iss_editorial_cli_build_ausstellung_candidate($post);
    $sections = is_array($candidate['document']['sections'] ?? null) ? $candidate['document']['sections'] : [];
    if (!$sections) {
        WP_CLI::error(sprintf('No importable sections found for post %d.', (int) $post->ID));
        return;
    }

    if (!iss_editorial_save_document((int) $post->ID, $format_slug, $candidate['document'], false)) {
        WP_CLI::error(sprintf('Failed to save editorial document for post %d.', (int) $post->ID));
        return;
    }

    iss_editorial_set_document_enabled((int) $post->ID, $format_slug, false);
    delete_post_meta((int) $post->ID, $autosave_key);

    $notes = ['imported', 'enabled_off', 'legacy_fallback'];
    if (!empty($candidate['media_blocks']) || !empty($candidate['unsupported_blocks'])) {
        $notes[] = 'manual_review_required';
    }

    iss_editorial_cli_print_rows('table', [[
        'ID' => (int) $post->ID,
        'title' => get_the_title($post),
        'slug' => $post->post_name,
        'document_key' => $document_key,
        'enabled' => iss_editorial_document_is_enabled((int) $post->ID, $format_slug) ? 'yes' : 'no',
        'candidate_sections' => count($sections),
        'media_blocks' => (int) $candidate['media_blocks'],
        'unsupported_blocks' => implode(',', (array) $candidate['unsupported_blocks']),
        'notes' => implode(',', $notes),
    ]], [
        'ID',
        'title',
        'slug',
        'document_key',
        'enabled',
        'candidate_sections',
        'media_blocks',
        'unsupported_blocks',
        'notes',
    ]);
}

WP_CLI::add_command('iss-editorial ausstellung-import-candidate', 'iss_editorial_cli_ausstellung_import_candidate');
