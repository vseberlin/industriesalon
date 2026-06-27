<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_editorial_cli_get_post_by_token_for_type(string $token, string $post_type): ?WP_Post
{
    $token = trim($token);
    $post_type = sanitize_key($post_type);
    if ($token === '') {
        return null;
    }

    if (ctype_digit($token)) {
        $post = get_post((int) $token);
    } else {
        $post = get_page_by_path(sanitize_title($token), OBJECT, $post_type);
    }

    if (!$post instanceof WP_Post || $post->post_type !== $post_type) {
        return null;
    }

    return $post;
}

function iss_editorial_cli_get_post_by_token(string $token): ?WP_Post
{
    return iss_editorial_cli_get_post_by_token_for_type($token, 'ausstellung');
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
    $metadata = wp_get_attachment_metadata($attachment_id);
    $width = is_array($metadata) ? absint($metadata['width'] ?? 0) : 0;
    $height = is_array($metadata) ? absint($metadata['height'] ?? 0) : 0;

    return [
        'kind' => 'media',
        'source' => 'wp-media',
        'id' => (string) $attachment_id,
        'label' => $label,
        'thumbnail' => (string) wp_get_attachment_image_url($attachment_id, 'medium'),
        'width' => (string) $width,
        'height' => (string) $height,
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

    if ($block_name === 'iss/dense-image-wall') {
        foreach ((array) ($attrs['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim(implode(' ', array_filter([
                (string) ($item['kicker'] ?? ''),
                (string) ($item['title'] ?? ''),
                (string) ($item['text'] ?? ''),
                (string) ($item['caption'] ?? ''),
            ])));
            $ref = iss_editorial_cli_media_reference_from_attachment(absint($item['id'] ?? 0), $label);
            if ($ref) {
                $refs[$ref['id']] = $ref;
            }
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

function iss_editorial_cli_collect_links(array $block): array
{
    $links = [];
    $block_name = (string) ($block['blockName'] ?? '');
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

    if ($block_name === 'core/navigation-link') {
        $label = trim((string) ($attrs['label'] ?? ''));
        $url = trim((string) ($attrs['url'] ?? ''));
        if ($label !== '' && $url !== '') {
            $links[] = [
                'label' => $label,
                'url' => $url,
            ];
        }
    }

    if ($block_name === 'core/button') {
        $html = (string) ($block['innerHTML'] ?? '');
        if (preg_match('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches) === 1) {
            $label = iss_editorial_cli_strip_html((string) $matches[2]);
            $url = trim(html_entity_decode((string) $matches[1], ENT_QUOTES));
            if ($label !== '' && $url !== '') {
                $links[] = [
                    'label' => $label,
                    'url' => $url,
                ];
            }
        }
    }

    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (!is_array($inner_block)) {
            continue;
        }

        $links = array_merge($links, iss_editorial_cli_collect_links($inner_block));
    }

    return $links;
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
        if (str_contains($class_name, 'iss-kicker')) {
            $parts['kicker'] = $parts['kicker'] === '' ? $text : $parts['kicker'];
        } else {
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

function iss_editorial_cli_project_is_context_panel(array $block): bool
{
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $class_name = (string) ($attrs['className'] ?? '');

    return str_contains($class_name, 'iss-context-panel');
}

function iss_editorial_cli_project_fact_from_context_panel(array $block): array
{
    $value = '';
    $labels = [];

    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (!is_array($inner_block)) {
            continue;
        }

        $attrs = is_array($inner_block['attrs'] ?? null) ? $inner_block['attrs'] : [];
        $class_name = (string) ($attrs['className'] ?? '');
        $text = iss_editorial_cli_block_text($inner_block);
        if ($text === '') {
            continue;
        }

        if (str_contains($class_name, 'iss-context-panel__label') && $value === '') {
            $value = $text;
            continue;
        }

        $labels[] = $text;
    }

    if ($value === '' && $labels) {
        $value = array_shift($labels);
    }

    if ($value === '') {
        $value = iss_editorial_cli_block_text($block);
    }

    return [
        'value' => $value,
        'label' => implode(' ', array_filter(array_map('trim', $labels))),
    ];
}

function iss_editorial_cli_collect_project_facts(array $block): array
{
    if (iss_editorial_cli_project_is_context_panel($block)) {
        $fact = iss_editorial_cli_project_fact_from_context_panel($block);

        return trim((string) ($fact['value'] ?? '')) !== '' || trim((string) ($fact['label'] ?? '')) !== '' ? [$fact] : [];
    }

    $facts = [];
    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (!is_array($inner_block)) {
            continue;
        }

        $facts = array_merge($facts, iss_editorial_cli_collect_project_facts($inner_block));
    }

    return $facts;
}

function iss_editorial_cli_collect_project_text_parts(array $block, array &$parts): void
{
    if (iss_editorial_cli_project_is_context_panel($block)) {
        return;
    }

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
        if (str_contains($class_name, 'iss-kicker')) {
            $parts['kicker'] = $parts['kicker'] === '' ? $text : $parts['kicker'];
        } else {
            $parts['body'][] = $text;
        }
    } elseif ($block_name === 'core/quote' && $text !== '') {
        $parts['body'][] = $text;
    } elseif ($block_name === '' && $text !== '') {
        $parts['body'][] = $text;
    }

    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (is_array($inner_block)) {
            iss_editorial_cli_collect_project_text_parts($inner_block, $parts);
        }
    }
}

function iss_editorial_cli_build_media_section(array $block, array $media_refs): array
{
    $parts = [
        'kicker' => '',
        'title' => '',
        'body' => [],
    ];
    iss_editorial_cli_collect_text_parts($block, $parts);

    return [
        'type' => 'bildstrecke',
        'kicker' => $parts['kicker'],
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
            'kicker' => '',
            'title' => __('Einleitung', 'iss-editorial'),
            'body' => '',
        ];
    }

    $last_index = count($sections) - 1;
    $separator = $sections[$last_index]['body'] === '' ? '' : "\n\n";
    $sections[$last_index]['body'] .= $separator . $body;
}

function iss_editorial_cli_append_links(array &$sections, array $links): void
{
    if (!$links) {
        return;
    }

    if (!$sections) {
        $sections[] = [
            'type' => 'schluss',
            'kicker' => '',
            'title' => '',
            'body' => '',
            'links' => [],
        ];
    }

    $last_index = count($sections) - 1;
    $sections[$last_index]['links'] = array_values(array_merge((array) ($sections[$last_index]['links'] ?? []), $links));
}

function iss_editorial_cli_process_ausstellung_block(array $block, array &$sections, array &$unsupported_blocks, int &$media_blocks): void
{
    $kind = iss_editorial_cli_classify_block($block);
    $text = iss_editorial_cli_block_text($block);
    $links = iss_editorial_cli_collect_links($block);

    if ($links && in_array((string) ($block['blockName'] ?? ''), ['core/navigation', 'core/navigation-link'], true)) {
        iss_editorial_cli_append_links($sections, $links);
        return;
    }

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
            'kicker' => '',
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

function iss_editorial_cli_project_section_type(string $anchor, array $block, array $media_refs): string
{
    $anchor = sanitize_key($anchor);
    $class_name = (string) (($block['attrs']['className'] ?? ''));

    if ($anchor === 'material') {
        return 'material';
    }
    if ($anchor === 'kontakt') {
        return 'schluss';
    }
    if ($media_refs && str_contains($class_name, 'iss-dense-image-wall')) {
        return 'galerie';
    }

    return 'kapitel';
}

function iss_editorial_cli_build_project_sections(array $block): array
{
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $anchor = sanitize_key((string) ($attrs['anchor'] ?? ''));
    $media_refs = iss_editorial_cli_collect_media_refs($block);
    $links = iss_editorial_cli_collect_links($block);
    $facts = iss_editorial_cli_collect_project_facts($block);
    $parts = [
        'kicker' => '',
        'title' => '',
        'body' => [],
    ];
    iss_editorial_cli_collect_project_text_parts($block, $parts);

    $type = iss_editorial_cli_project_section_type($anchor, $block, $media_refs);
    $section = [
        'type' => $type,
        'anchor' => $anchor,
        'kicker' => $parts['kicker'],
        'title' => $parts['title'] !== '' ? $parts['title'] : ($anchor !== '' ? ucfirst(str_replace('-', ' ', $anchor)) : __('Projektabschnitt', 'iss-editorial')),
        'body' => implode("\n\n", array_filter(array_map('trim', $parts['body']))),
    ];

    if (in_array($type, ['galerie', 'image_wall', 'material'], true)) {
        $section['media_refs'] = $media_refs;
        $section['object_refs'] = [];
    }
    if (in_array($type, ['kapitel', 'fliesstext', 'material', 'schluss'], true)) {
        $section['links'] = $links;
    }

    $sections = [$section];
    if ($type === 'kapitel' && $facts) {
        $sections[] = [
            'type' => 'massstab',
            'anchor' => $anchor !== '' ? $anchor . '-fakten' : '',
            'kicker' => __('Fakten', 'iss-editorial'),
            'title' => __('Eckdaten', 'iss-editorial'),
            'body' => '',
            'facts' => $facts,
        ];
    }

    return $sections;
}

function iss_editorial_cli_build_project_gallery_section(array $block): array
{
    $media_refs = iss_editorial_cli_collect_media_refs($block);

    return [
        'type' => 'galerie',
        'anchor' => 'projektgalerie',
        'kicker' => '',
        'title' => __('Projektgalerie', 'iss-editorial'),
        'body' => '',
        'media_refs' => $media_refs,
        'object_refs' => [],
    ];
}

function iss_editorial_cli_project_rail_section(): array
{
    return [
        'type' => 'projekt_rail',
        'kicker' => __('Projektstimme', 'iss-editorial'),
        'title' => __('Kapitel', 'iss-editorial'),
        'body' => '',
    ];
}

function iss_editorial_cli_insert_project_rail_section(array $sections): array
{
    foreach ($sections as $section) {
        if (is_array($section) && (string) ($section['type'] ?? '') === 'projekt_rail') {
            return $sections;
        }
    }

    if (!$sections) {
        return [iss_editorial_cli_project_rail_section()];
    }

    $insert_at = 0;
    if ((string) ($sections[0]['type'] ?? '') === 'fliesstext') {
        $insert_at = 1;
    }

    array_splice($sections, $insert_at, 0, [iss_editorial_cli_project_rail_section()]);

    return $sections;
}

function iss_editorial_cli_process_project_block(array $block, array &$sections, array &$unsupported_blocks, int &$media_blocks): void
{
    $block_name = (string) ($block['blockName'] ?? '');
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $class_name = (string) ($attrs['className'] ?? '');

    if ($block_name === 'iss/dense-image-wall' || str_contains($class_name, 'iss-dense-image-wall')) {
        $media_refs = iss_editorial_cli_collect_media_refs($block);
        if ($media_refs) {
            $media_blocks += max(1, iss_editorial_cli_count_media_blocks($block));
            $sections[] = iss_editorial_cli_build_project_gallery_section($block);
        }
        return;
    }

    if ($block_name === 'core/group' && str_contains($class_name, 'iss-project-chapter')) {
        $media_blocks += iss_editorial_cli_count_media_blocks($block);
        foreach (iss_editorial_cli_build_project_sections($block) as $section) {
            $sections[] = $section;
        }
        return;
    }

    if ($block_name === 'core/paragraph') {
        $text = iss_editorial_cli_block_text($block);
        if ($text !== '') {
            $sections[] = [
                'type' => 'fliesstext',
                'kicker' => '',
                'title' => '',
                'body' => $text,
                'links' => [],
            ];
        }
        return;
    }

    if ($block_name === 'core/group' && str_contains($class_name, 'iss-project-dossier')) {
        foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
            if (is_array($inner_block)) {
                iss_editorial_cli_process_project_block($inner_block, $sections, $unsupported_blocks, $media_blocks);
            }
        }
        return;
    }

    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (is_array($inner_block)) {
            iss_editorial_cli_process_project_block($inner_block, $sections, $unsupported_blocks, $media_blocks);
        }
    }

    if ($block_name !== '' && !in_array($block_name, ['core/group', 'core/columns', 'core/column', 'core/list-item'], true)) {
        $unsupported_blocks[$block_name] = true;
    }
}

function iss_editorial_cli_build_project_candidate(WP_Post $post): array
{
    $blocks = parse_blocks((string) $post->post_content);
    $sections = [];
    $unsupported_blocks = [];
    $media_blocks = 0;

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        iss_editorial_cli_process_project_block($block, $sections, $unsupported_blocks, $media_blocks);
    }

    $sections = array_values(array_filter($sections, static function (array $section): bool {
        return trim((string) ($section['title'] ?? '')) !== ''
            || trim((string) ($section['body'] ?? '')) !== ''
            || !empty($section['media_refs'])
            || !empty($section['object_refs'])
            || !empty($section['facts'])
            || !empty($section['links']);
    }));
    $sections = iss_editorial_cli_insert_project_rail_section($sections);

    return [
        'document' => [
            'schema_version' => 1,
            'skin' => 'dossier',
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

function iss_editorial_cli_project_posts_from_args(array $assoc_args): array
{
    $posts_arg = isset($assoc_args['posts']) ? trim((string) $assoc_args['posts']) : '';
    $tokens = $posts_arg !== '' ? array_filter(array_map('trim', explode(',', $posts_arg))) : [];
    $posts = [];

    if ($tokens) {
        foreach ($tokens as $token) {
            $post = iss_editorial_cli_get_post_by_token_for_type($token, 'projekt');
            if (!$post instanceof WP_Post) {
                WP_CLI::warning(sprintf('Projekt not found: %s', $token));
                continue;
            }
            $posts[] = $post;
        }

        return $posts;
    }

    return get_posts([
        'post_type' => 'projekt',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
    ]);
}

function iss_editorial_cli_project_dry_run(array $args, array $assoc_args): void
{
    unset($args);

    $format = isset($assoc_args['format']) ? (string) $assoc_args['format'] : 'table';
    $rows = [];

    foreach (iss_editorial_cli_project_posts_from_args($assoc_args) as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $candidate = iss_editorial_cli_build_project_candidate($post);
        $stored = get_post_meta((int) $post->ID, iss_editorial_get_document_meta_key('projekt'), true);
        $enabled = iss_editorial_document_is_enabled((int) $post->ID, 'projekt');
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
        WP_CLI::error('No Projekt posts resolved for dry run.');
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

WP_CLI::add_command('iss-editorial projekt-dry-run', 'iss_editorial_cli_project_dry_run');

function iss_editorial_cli_project_import_candidate(array $args, array $assoc_args): void
{
    unset($args);

    $token = isset($assoc_args['post']) ? trim((string) $assoc_args['post']) : '';
    if ($token === '') {
        WP_CLI::error('Missing required --post=<projekt-id-or-slug|all>.');
        return;
    }

    $posts = [];
    if ($token === 'all') {
        $posts = iss_editorial_cli_project_posts_from_args([]);
    } else {
        $post = iss_editorial_cli_get_post_by_token_for_type($token, 'projekt');
        if ($post instanceof WP_Post) {
            $posts[] = $post;
        }
    }

    if (!$posts) {
        WP_CLI::error(sprintf('Projekt not found: %s', $token));
        return;
    }

    $format_slug = 'projekt';
    $document_key = iss_editorial_get_document_meta_key($format_slug);
    $autosave_key = iss_editorial_get_autosave_meta_key($format_slug);
    $force = isset($assoc_args['force']);
    $rows = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $stored = get_post_meta((int) $post->ID, $document_key, true);
        if (is_string($stored) && trim($stored) !== '' && !$force) {
            WP_CLI::warning(sprintf('Post %d already has an editorial document. Use --force to replace it.', (int) $post->ID));
            continue;
        }

        $candidate = iss_editorial_cli_build_project_candidate($post);
        $sections = is_array($candidate['document']['sections'] ?? null) ? $candidate['document']['sections'] : [];
        if (!$sections) {
            WP_CLI::warning(sprintf('No importable sections found for post %d.', (int) $post->ID));
            continue;
        }

        if (!iss_editorial_save_document((int) $post->ID, $format_slug, $candidate['document'], false)) {
            WP_CLI::warning(sprintf('Failed to save editorial document for post %d.', (int) $post->ID));
            continue;
        }

        iss_editorial_set_document_enabled((int) $post->ID, $format_slug, false);
        delete_post_meta((int) $post->ID, $autosave_key);

        $notes = ['imported', 'enabled_off', 'legacy_fallback'];
        if (!empty($candidate['media_blocks']) || !empty($candidate['unsupported_blocks'])) {
            $notes[] = 'manual_review_required';
        }

        $rows[] = [
            'ID' => (int) $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'document_key' => $document_key,
            'enabled' => iss_editorial_document_is_enabled((int) $post->ID, $format_slug) ? 'yes' : 'no',
            'candidate_sections' => count($sections),
            'media_blocks' => (int) $candidate['media_blocks'],
            'unsupported_blocks' => implode(',', (array) $candidate['unsupported_blocks']),
            'notes' => implode(',', $notes),
        ];
    }

    if (!$rows) {
        WP_CLI::error('No Projekt candidates were imported.');
    }

    iss_editorial_cli_print_rows('table', $rows, [
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

WP_CLI::add_command('iss-editorial projekt-import-candidate', 'iss_editorial_cli_project_import_candidate');

function iss_editorial_cli_fuehrung_posts_from_args(array $assoc_args): array
{
    $posts_arg = isset($assoc_args['posts']) ? trim((string) $assoc_args['posts']) : '';
    $tokens = $posts_arg !== '' ? array_filter(array_map('trim', explode(',', $posts_arg))) : [];
    $posts = [];

    if ($tokens) {
        foreach ($tokens as $token) {
            $post = iss_editorial_cli_get_post_by_token_for_type($token, 'fuehrung');
            if (!$post instanceof WP_Post) {
                WP_CLI::warning(sprintf('Führung not found: %s', $token));
                continue;
            }
            $posts[] = $post;
        }

        return $posts;
    }

    return get_posts([
        'post_type' => 'fuehrung',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
    ]);
}

function iss_editorial_cli_fuehrung_body_html(array $block): string
{
    $html = function_exists('render_block') ? render_block($block) : (string) ($block['innerHTML'] ?? '');
    $html = trim((string) $html);

    return $html !== '' ? wp_kses_post($html) : '';
}

function iss_editorial_cli_fuehrung_heading_is_material(string $heading): bool
{
    $heading = strtolower(remove_accents($heading));

    return preg_match('/\b(quelle|quellen|material|download|hinweis|achtung|kontakt|anmeldung|buchung)\b/', $heading) === 1;
}

function iss_editorial_cli_fuehrung_collect_units(array $block, array &$units, array &$unsupported_blocks, int &$media_blocks, int &$skipped_blocks): void
{
    $block_name = (string) ($block['blockName'] ?? '');
    $kind = iss_editorial_cli_classify_block($block);
    $text = iss_editorial_cli_block_text($block);

    if ($block_name === '' && $text === '') {
        return;
    }

    if (in_array($block_name, ['iss/tour-calendar', 'iss/related-content'], true)) {
        ++$skipped_blocks;
        return;
    }

    if ($kind === 'heading') {
        if ($text !== '') {
            $units[] = [
                'kind' => 'heading',
                'text' => $text,
            ];
        }
        return;
    }

    if ($block_name === 'core/quote') {
        $quote = iss_editorial_cli_strip_html((string) ($block['innerHTML'] ?? ''));
        if ($quote !== '') {
            $units[] = [
                'kind' => 'quote',
                'quote' => $quote,
            ];
        }
        return;
    }

    if (in_array($block_name, ['core/paragraph', 'core/list'], true) || ($block_name === '' && $text !== '')) {
        $html = iss_editorial_cli_fuehrung_body_html($block);
        if ($html !== '') {
            $units[] = [
                'kind' => 'body',
                'html' => $html,
                'text' => $text,
            ];
        }
        return;
    }

    if ($kind === 'media') {
        $media_refs = iss_editorial_cli_collect_media_refs($block);
        $media_blocks += max(1, iss_editorial_cli_count_media_blocks($block));
        if ($media_refs) {
            $units[] = [
                'kind' => 'media',
                'media_refs' => $media_refs,
            ];
        }
        return;
    }

    if ($kind === 'container') {
        $before = count($units);
        foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
            if (is_array($inner_block)) {
                iss_editorial_cli_fuehrung_collect_units($inner_block, $units, $unsupported_blocks, $media_blocks, $skipped_blocks);
            }
        }
        if ($before === count($units) && $text !== '') {
            $units[] = [
                'kind' => 'body',
                'html' => wpautop(esc_html($text)),
                'text' => $text,
            ];
        }
        return;
    }

    if ($text !== '') {
        $unsupported_blocks[] = $block_name !== '' ? $block_name : 'freeform';
        $units[] = [
            'kind' => 'body',
            'html' => wpautop(esc_html($text)),
            'text' => $text,
        ];
    }
}

function iss_editorial_cli_fuehrung_flush_section(?array &$section, array &$sections): void
{
    if ($section === null) {
        return;
    }

    $body = trim((string) ($section['body'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $quote = trim((string) ($section['quote'] ?? ''));
    $media_refs = is_array($section['media_refs'] ?? null) ? $section['media_refs'] : [];
    if ($body !== '' || $title !== '' || $quote !== '' || $media_refs) {
        $sections[] = $section;
    }

    $section = null;
}

function iss_editorial_cli_build_fuehrung_candidate(WP_Post $post): array
{
    $blocks = function_exists('parse_blocks') ? parse_blocks((string) $post->post_content) : [];
    $units = [];
    $unsupported_blocks = [];
    $media_blocks = 0;
    $skipped_blocks = 0;

    foreach ($blocks as $block) {
        if (is_array($block)) {
            iss_editorial_cli_fuehrung_collect_units($block, $units, $unsupported_blocks, $media_blocks, $skipped_blocks);
        }
    }

    $sections = [];
    $current = null;
    $intro_done = false;

    foreach ($units as $unit) {
        $kind = (string) ($unit['kind'] ?? '');

        if ($kind === 'body' && !$intro_done) {
            $sections[] = [
                'type' => 'intro',
                'kicker' => '',
                'title' => '',
                'body' => (string) ($unit['html'] ?? ''),
                'media_refs' => [],
            ];
            $intro_done = true;
            continue;
        }

        if ($kind === 'heading') {
            iss_editorial_cli_fuehrung_flush_section($current, $sections);
            $title = (string) ($unit['text'] ?? '');
            $current = [
                'type' => iss_editorial_cli_fuehrung_heading_is_material($title) ? 'material' : 'kapitel',
                'kicker' => '',
                'title' => $title,
                'body' => '',
            ];
            continue;
        }

        if ($kind === 'quote') {
            iss_editorial_cli_fuehrung_flush_section($current, $sections);
            $sections[] = [
                'type' => 'zitat',
                'kicker' => '',
                'title' => '',
                'body' => '',
                'quote' => (string) ($unit['quote'] ?? ''),
                'attribution' => '',
            ];
            $intro_done = true;
            continue;
        }

        if ($kind === 'media') {
            iss_editorial_cli_fuehrung_flush_section($current, $sections);
            $sections[] = [
                'type' => 'galerie',
                'kicker' => '',
                'title' => '',
                'body' => '',
                'media_refs' => (array) ($unit['media_refs'] ?? []),
                'object_refs' => [],
            ];
            $intro_done = true;
            continue;
        }

        if ($kind === 'body') {
            if ($current === null) {
                $current = [
                    'type' => 'kapitel',
                    'kicker' => '',
                    'title' => '',
                    'body' => '',
                    'media_refs' => [],
                ];
            }
            $current['body'] = trim((string) ($current['body'] ?? '') . "\n\n" . (string) ($unit['html'] ?? ''));
            $intro_done = true;
        }
    }

    iss_editorial_cli_fuehrung_flush_section($current, $sections);

    return [
        'document' => [
            'schema_version' => 1,
            'skin' => 'route-dossier',
            'variant' => 'standard',
            'sections' => $sections,
        ],
        'legacy_blocks' => count($blocks),
        'media_blocks' => $media_blocks,
        'skipped_blocks' => $skipped_blocks,
        'unsupported_blocks' => array_values(array_unique(array_filter($unsupported_blocks))),
    ];
}

function iss_editorial_cli_fuehrung_dry_run(array $args, array $assoc_args): void
{
    unset($args);

    $format = isset($assoc_args['format']) ? (string) $assoc_args['format'] : 'table';
    $rows = [];

    foreach (iss_editorial_cli_fuehrung_posts_from_args($assoc_args) as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $candidate = iss_editorial_cli_build_fuehrung_candidate($post);
        $stored = get_post_meta((int) $post->ID, iss_editorial_get_document_meta_key('fuehrung'), true);
        $enabled = iss_editorial_document_is_enabled((int) $post->ID, 'fuehrung');
        $section_types = array_unique(array_map(static function (array $section): string {
            return (string) ($section['type'] ?? '');
        }, (array) $candidate['document']['sections']));

        $notes = ['read_only'];
        if (is_string($stored) && trim($stored) !== '') {
            $notes[] = 'existing_json_document';
        }
        if (!empty($candidate['unsupported_blocks'])) {
            $notes[] = 'manual_review_required';
        }
        if (!empty($candidate['skipped_blocks'])) {
            $notes[] = 'skipped_infrastructure_blocks';
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
            'skipped_blocks' => (int) $candidate['skipped_blocks'],
            'unsupported_blocks' => implode(',', (array) $candidate['unsupported_blocks']),
            'notes' => implode(',', $notes),
        ];
    }

    if (!$rows) {
        WP_CLI::error('No Führung posts resolved for dry run.');
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
        'skipped_blocks',
        'unsupported_blocks',
        'notes',
    ]);
}

WP_CLI::add_command('iss-editorial fuehrung-dry-run', 'iss_editorial_cli_fuehrung_dry_run');

function iss_editorial_cli_fuehrung_import_candidate(array $args, array $assoc_args): void
{
    unset($args);

    $token = isset($assoc_args['post']) ? trim((string) $assoc_args['post']) : '';
    if ($token === '') {
        WP_CLI::error('Missing required --post=<fuehrung-id-or-slug|all>.');
        return;
    }

    $posts = [];
    if ($token === 'all') {
        $posts = iss_editorial_cli_fuehrung_posts_from_args([]);
    } else {
        $post = iss_editorial_cli_get_post_by_token_for_type($token, 'fuehrung');
        if ($post instanceof WP_Post) {
            $posts[] = $post;
        }
    }

    if (!$posts) {
        WP_CLI::error(sprintf('Führung not found: %s', $token));
        return;
    }

    $format_slug = 'fuehrung';
    $document_key = iss_editorial_get_document_meta_key($format_slug);
    $autosave_key = iss_editorial_get_autosave_meta_key($format_slug);
    $force = isset($assoc_args['force']);
    $enable = isset($assoc_args['enable']);
    $rows = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $stored = get_post_meta((int) $post->ID, $document_key, true);
        if (is_string($stored) && trim($stored) !== '' && !$force) {
            WP_CLI::warning(sprintf('Post %d already has an editorial document. Use --force to replace it.', (int) $post->ID));
            continue;
        }

        $candidate = iss_editorial_cli_build_fuehrung_candidate($post);
        $sections = is_array($candidate['document']['sections'] ?? null) ? $candidate['document']['sections'] : [];
        if (!$sections) {
            WP_CLI::warning(sprintf('No importable sections found for post %d.', (int) $post->ID));
            continue;
        }

        if (!iss_editorial_save_document((int) $post->ID, $format_slug, $candidate['document'], false)) {
            WP_CLI::warning(sprintf('Failed to save editorial document for post %d.', (int) $post->ID));
            continue;
        }

        iss_editorial_set_document_enabled((int) $post->ID, $format_slug, $enable);
        delete_post_meta((int) $post->ID, $autosave_key);

        $notes = ['imported', $enable ? 'enabled_on' : 'enabled_off'];
        if (!empty($candidate['unsupported_blocks'])) {
            $notes[] = 'manual_review_required';
        }
        if (!empty($candidate['skipped_blocks'])) {
            $notes[] = 'skipped_infrastructure_blocks';
        }

        $rows[] = [
            'ID' => (int) $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'document_key' => $document_key,
            'enabled' => iss_editorial_document_is_enabled((int) $post->ID, $format_slug) ? 'yes' : 'no',
            'candidate_sections' => count($sections),
            'media_blocks' => (int) $candidate['media_blocks'],
            'skipped_blocks' => (int) $candidate['skipped_blocks'],
            'unsupported_blocks' => implode(',', (array) $candidate['unsupported_blocks']),
            'notes' => implode(',', $notes),
        ];
    }

    if (!$rows) {
        WP_CLI::error('No Führung candidates were imported.');
    }

    iss_editorial_cli_print_rows('table', $rows, [
        'ID',
        'title',
        'slug',
        'document_key',
        'enabled',
        'candidate_sections',
        'media_blocks',
        'skipped_blocks',
        'unsupported_blocks',
        'notes',
    ]);
}

WP_CLI::add_command('iss-editorial fuehrung-import-candidate', 'iss_editorial_cli_fuehrung_import_candidate');

function iss_editorial_cli_normalize_vocabulary_format_slugs(array $assoc_args): array
{
    $format_arg = isset($assoc_args['format']) ? trim((string) $assoc_args['format']) : '';
    $requested = $format_arg !== ''
        ? array_filter(array_map('sanitize_key', array_map('trim', explode(',', $format_arg))))
        : ['ausstellung', 'projekt', 'rueckblick', 'publication'];

    $formats = [];
    foreach ($requested as $format_slug) {
        if ($format_slug !== '' && iss_editorial_get_format($format_slug)) {
            $formats[] = $format_slug;
        }
    }

    return array_values(array_unique($formats));
}

function iss_editorial_cli_normalize_vocabulary_posts(string $format_slug, array $assoc_args): array
{
    $format = iss_editorial_get_format($format_slug);
    if (!$format) {
        return [];
    }

    $post_arg = isset($assoc_args['post']) ? trim((string) $assoc_args['post']) : '';
    $post_types = array_values(array_filter(array_map('sanitize_key', (array) ($format['post_types'] ?? []))));
    if (!$post_types) {
        return [];
    }

    if ($post_arg !== '') {
        $posts = [];
        foreach ($post_types as $post_type) {
            $post = iss_editorial_cli_get_post_by_token_for_type($post_arg, $post_type);
            if ($post instanceof WP_Post) {
                $posts[] = $post;
            }
        }

        return $posts;
    }

    return get_posts([
        'post_type' => $post_types,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'meta_key' => iss_editorial_get_document_meta_key($format_slug), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Maintenance CLI scans only stored editorial documents.
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);
}

function iss_editorial_cli_normalize_vocabulary_skin(string $format_slug, string $skin): string
{
    $skin = sanitize_key($skin);
    $maps = [
        'ausstellung' => [
            'frauen-im-werk' => 'quellenbuehne',
            'kinder-im-werk' => 'objektalbum',
        ],
        'projekt' => [
            'brief' => 'dossier',
            'field' => 'dossier',
        ],
        'publication' => [
            'blueprint-matrix' => 'bildmatrix',
        ],
    ];

    return (string) ($maps[$format_slug][$skin] ?? $skin);
}

function iss_editorial_cli_normalize_vocabulary_section(array $section, string $format_slug, array &$changes): array
{
    $type = sanitize_key((string) ($section['type'] ?? ''));
    if ($type === '') {
        return $section;
    }

    if ($type === 'bildstrecke') {
        $section['type'] = 'galerie';
        $section['gallery_layout'] = 'sequence';
        $changes[] = 'section:bildstrecke->galerie';
        return $section;
    }

    if ($type === 'image_wall') {
        $section['type'] = 'galerie';
        $section['gallery_layout'] = 'wall';
        $changes[] = 'section:image_wall->galerie';
        return $section;
    }

    if ($format_slug === 'ausstellung' && $type === 'quellenauszug') {
        $section['type'] = 'zitat';
        $section['quote_treatment'] = 'source';
        $changes[] = 'section:quellenauszug->zitat';
        return $section;
    }

    if ($format_slug === 'ausstellung' && $type === 'aside') {
        $section['type'] = 'kapitel';
        $section['section_treatment'] = 'aside';
        $changes[] = 'section:aside->kapitel';
        return $section;
    }

    if ($format_slug === 'rueckblick' && $type === 'bericht') {
        $section['type'] = 'fliesstext';
        $changes[] = 'section:bericht->fliesstext';
        return $section;
    }

    if ($format_slug === 'rueckblick' && $type === 'quellen') {
        $has_links = !empty($section['links']) && is_array($section['links']);
        $has_objects = !empty($section['object_refs']) && is_array($section['object_refs']);
        $section['type'] = ($has_links || !$has_objects) ? 'material' : 'objektfokus';
        $changes[] = 'section:quellen->' . $section['type'];
        return $section;
    }

    return $section;
}

function iss_editorial_cli_normalize_vocabulary_document(array $document, string $format_slug, array &$changes): array
{
    $skin = sanitize_key((string) ($document['skin'] ?? ''));
    $canonical_skin = iss_editorial_cli_normalize_vocabulary_skin($format_slug, $skin);
    if ($canonical_skin !== $skin) {
        $document['skin'] = $canonical_skin;
        $changes[] = 'skin:' . $skin . '->' . $canonical_skin;
    }

    $sections = [];
    $rail_enabled = false;
    foreach ((array) ($document['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        if ($format_slug === 'projekt' && sanitize_key((string) ($section['type'] ?? '')) === 'projekt_rail') {
            $rail_enabled = true;
            $changes[] = 'section:projekt_rail->features.rail';
            continue;
        }

        $sections[] = iss_editorial_cli_normalize_vocabulary_section($section, $format_slug, $changes);
    }

    if ($rail_enabled) {
        $features = is_array($document['features'] ?? null) ? $document['features'] : [];
        $rail = is_array($features['rail'] ?? null) ? $features['rail'] : [];
        $rail['enabled'] = true;
        $features['rail'] = $rail;
        $document['features'] = $features;
    }

    $document['sections'] = $sections;

    return $document;
}

function iss_editorial_cli_normalize_vocabulary(array $args, array $assoc_args): void
{
    unset($args);

    $write = isset($assoc_args['write']);
    $format = isset($assoc_args['output']) ? (string) $assoc_args['output'] : 'table';
    $rows = [];

    foreach (iss_editorial_cli_normalize_vocabulary_format_slugs($assoc_args) as $format_slug) {
        foreach (iss_editorial_cli_normalize_vocabulary_posts($format_slug, $assoc_args) as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $stored = get_post_meta((int) $post->ID, iss_editorial_get_document_meta_key($format_slug), true);
            if (!is_string($stored) || trim($stored) === '') {
                continue;
            }

            $document = json_decode($stored, true);
            if (!is_array($document)) {
                $rows[] = [
                    'ID' => (int) $post->ID,
                    'format' => $format_slug,
                    'title' => get_the_title($post),
                    'skin' => '',
                    'changes' => 'invalid_json',
                    'written' => 'no',
                ];
                continue;
            }

            $changes = [];
            $normalized = iss_editorial_cli_normalize_vocabulary_document($document, $format_slug, $changes);
            if (!$changes) {
                continue;
            }

            $written = 'dry-run';
            if ($write) {
                $written = iss_editorial_save_document((int) $post->ID, $format_slug, $normalized, false) ? 'yes' : 'no';
            }

            $rows[] = [
                'ID' => (int) $post->ID,
                'format' => $format_slug,
                'title' => get_the_title($post),
                'skin' => (string) ($document['skin'] ?? ''),
                'changes' => implode(',', array_values(array_unique($changes))),
                'written' => $written,
            ];
        }
    }

    if (!$rows) {
        WP_CLI::success('No vocabulary changes found.');
        return;
    }

    iss_editorial_cli_print_rows($format, $rows, [
        'ID',
        'format',
        'title',
        'skin',
        'changes',
        'written',
    ]);
}

WP_CLI::add_command('iss-editorial normalize-vocabulary', 'iss_editorial_cli_normalize_vocabulary');
