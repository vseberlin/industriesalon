<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_register_editorial_format(array $formats): array
{
    $formats['publication'] = [
        'label' => __('Publikation', 'iss-publications'),
        'base' => 'ordered',
        'post_types' => [ISS_PUBLICATIONS_POST_TYPE],
        'default_skin' => 'standard',
        'default_variant' => 'standard',
        'sections' => [
            'intro' => [
                'label' => __('Einleitung', 'iss-publications'),
                'description' => __('Kurze Einordnung der Publikation.', 'iss-publications'),
                'supports' => [],
            ],
            'source' => [
                'label' => __('Quelle / Rechte', 'iss-publications'),
                'description' => __('Quellenhinweise, Rechte und redaktionelle Notizen.', 'iss-publications'),
                'supports' => ['links'],
            ],
            'publication_rail' => [
                'label' => __('Leserail', 'iss-publications'),
                'description' => __('Optional reading rail generated from the publication sections.', 'iss-publications'),
                'supports' => ['rail_options', 'no_body'],
            ],
            'longread_chapter' => [
                'label' => __('Longread-Kapitel', 'iss-publications'),
                'description' => __('One chapter in a JSON-backed longread publication.', 'iss-publications'),
                'supports' => ['anchor', 'media_refs', 'media_layout'],
            ],
            'longread_quote' => [
                'label' => __('Longread-Zitat', 'iss-publications'),
                'description' => __('A standalone quote moment in a JSON-backed longread publication.', 'iss-publications'),
                'supports' => ['quote'],
            ],
            'timeline_item' => [
                'label' => __('Zeitleisten-Station', 'iss-publications'),
                'description' => __('One dated station in a JSON-backed timeline publication.', 'iss-publications'),
                'supports' => ['year', 'media_refs'],
            ],
            'photoalbum' => [
                'label' => __('Fotoalbum', 'iss-publications'),
                'description' => __('Editable album sequence imported from an Archivset or editorial Set.', 'iss-publications'),
                'supports' => ['album_source', 'sheets', 'no_body'],
            ],
        ],
    ];

    return $formats;
}
add_filter('iss_editorial_formats', 'iss_publications_register_editorial_format');

function iss_publications_editorial_document_is_enabled(int $post_id): bool
{
    return $post_id > 0
        && function_exists('iss_editorial_document_is_enabled')
        && iss_editorial_document_is_enabled($post_id, 'publication');
}

function iss_publications_get_editorial_publication_read_model(int $post_id): array
{
    if (
        $post_id <= 0
        || !iss_publications_editorial_document_is_enabled($post_id)
        || !function_exists('iss_editorial_get_read_model')
    ) {
        return [];
    }

    $document = iss_editorial_get_read_model($post_id, 'publication', false);

    return is_array($document) ? $document : [];
}

function iss_publications_get_editorial_publication_section(int $post_id, string $type): array
{
    $type = sanitize_key($type);
    if ($post_id <= 0 || $type === '') {
        return [];
    }

    $document = iss_publications_get_editorial_publication_read_model($post_id);
    foreach ((array) ($document['sections'] ?? []) as $section) {
        if (is_array($section) && sanitize_key((string) ($section['type'] ?? '')) === $type) {
            return $section;
        }
    }

    return [];
}

function iss_publications_get_publication_rail_settings(int $post_id): array
{
    $fallback = [
        'enabled' => false,
        'show_nav' => false,
        'show_summary' => false,
        'show_related' => false,
        'variant' => 'detailed',
        'kicker' => '',
        'title' => '',
        'source' => 'none',
    ];

    if ($post_id <= 0) {
        return $fallback;
    }

    if (!iss_publications_editorial_document_is_enabled($post_id)) {
        return [
            'enabled' => true,
            'show_nav' => true,
            'show_summary' => true,
            'show_related' => true,
            'variant' => 'detailed',
            'kicker' => '',
            'title' => '',
            'source' => 'legacy',
        ];
    }

    $rail_section = iss_publications_get_editorial_publication_section($post_id, 'publication_rail');
    if (!$rail_section) {
        return $fallback;
    }

    $options = is_array($rail_section['rail_options'] ?? null) ? $rail_section['rail_options'] : [];
    $variant = sanitize_key((string) ($options['variant'] ?? 'detailed'));
    if (!in_array($variant, ['detailed', 'compact'], true)) {
        $variant = 'detailed';
    }

    return [
        'enabled' => true,
        'show_nav' => !array_key_exists('show_nav', $options) || !empty($options['show_nav']),
        'show_summary' => !array_key_exists('show_summary', $options) || !empty($options['show_summary']),
        'show_related' => !array_key_exists('show_related', $options) || !empty($options['show_related']),
        'variant' => $variant,
        'kicker' => trim((string) ($rail_section['kicker'] ?? '')),
        'title' => trim((string) ($rail_section['title'] ?? '')),
        'source' => 'editorial',
    ];
}

function iss_publications_publication_rail_allows(int $post_id, string $feature): bool
{
    $feature = sanitize_key($feature);
    $settings = iss_publications_get_publication_rail_settings($post_id);

    return !empty($settings['enabled']) && !empty($settings[$feature]);
}
