<?php

if (!defined('ABSPATH')) {
    exit;
}

interface ISS_Graph_Search_Provider_Interface
{
    /**
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public function search(string $query, array $args = []);
}

final class ISS_Graph_SQL_Search_Provider implements ISS_Graph_Search_Provider_Interface
{
    public function search(string $query, array $args = [])
    {
        $post_types = iss_search_resolve_public_search_post_types($args);
        if (!$post_types) {
            return [];
        }

        return iss_graph_search_public_posts($query, [
            'post_types' => $post_types,
            'limit' => iss_search_normalize_limit($args['limit'] ?? 24, 24, 1000),
        ]);
    }
}

final class ISS_Graph_Meili_Search_Provider implements ISS_Graph_Search_Provider_Interface
{
    public function search(string $query, array $args = [])
    {
        return new WP_Error(
            'iss_search_meili_unavailable',
            __('The Meili search provider is reserved but not configured in this runtime.', 'iss-graph'),
            ['status' => 501]
        );
    }
}

function iss_search_get_provider_name(): string
{
    $provider = defined('ISS_SEARCH_PROVIDER') ? (string) ISS_SEARCH_PROVIDER : 'sql';
    $provider = sanitize_key((string) apply_filters('iss_search_provider', $provider));

    return in_array($provider, ['sql', 'meili'], true) ? $provider : 'sql';
}

function iss_search_get_provider(): ISS_Graph_Search_Provider_Interface
{
    static $providers = [];

    $name = iss_search_get_provider_name();
    if (!isset($providers[$name])) {
        $providers[$name] = $name === 'meili'
            ? new ISS_Graph_Meili_Search_Provider()
            : new ISS_Graph_SQL_Search_Provider();
    }

    return $providers[$name];
}

function iss_search_normalize_limit($value, int $default = 24, int $max = 100): int
{
    $limit = absint($value);
    if ($limit <= 0) {
        $limit = $default;
    }

    return max(1, min($max, $limit));
}

function iss_search_normalize_token_list($value): array
{
    $items = is_array($value) ? $value : explode(',', (string) $value);
    $tokens = [];

    foreach ($items as $item) {
        if (is_array($item)) {
            $tokens = array_merge($tokens, iss_search_normalize_token_list($item));
            continue;
        }

        $token = sanitize_key(trim((string) $item));
        if ($token !== '') {
            $tokens[] = $token;
        }
    }

    return array_values(array_unique($tokens));
}

function iss_search_get_public_type_label(string $type, array $post_types = []): string
{
    $labels = [
        'place' => __('Ort', 'iss-graph'),
        'tour' => __('Führung', 'iss-graph'),
        'event' => __('Veranstaltung', 'iss-graph'),
        'exhibition' => __('Ausstellung', 'iss-graph'),
        'project' => __('Projekt', 'iss-graph'),
        'publication' => __('Publikation', 'iss-graph'),
        'video' => __('Video', 'iss-graph'),
        'story' => __('Beitrag', 'iss-graph'),
        'profile' => __('Profil', 'iss-graph'),
        'archive_object' => __('Archivobjekt', 'iss-graph'),
    ];

    if (isset($labels[$type])) {
        return $labels[$type];
    }

    $post_type = (string) ($post_types[0] ?? '');
    $object = $post_type !== '' ? get_post_type_object($post_type) : null;

    return $object && isset($object->labels->singular_name)
        ? (string) $object->labels->singular_name
        : $type;
}

function iss_search_get_result_type_label(string $type, array $post_types, array $contract): string
{
    if (function_exists('iss_graph_get_contract_public_label')) {
        $contract_label = trim((string) iss_graph_get_contract_public_label($contract));
        if ($contract_label !== '') {
            return $contract_label;
        }
    }

    return iss_search_get_public_type_label($type, $post_types);
}

function iss_search_get_public_type_options(): array
{
    $options = [];

    foreach (iss_graph_get_public_search_post_type_map() as $post_type => $config) {
        $post_type = sanitize_key((string) $post_type);
        if ($post_type === '' || !post_type_exists($post_type) || !is_array($config)) {
            continue;
        }

        $type = sanitize_key((string) ($config['bucket'] ?? $post_type));
        if ($type === '') {
            continue;
        }

        if (!isset($options[$type])) {
            $options[$type] = [
                'type' => $type,
                'label' => '',
                'post_types' => [],
            ];
        }

        $options[$type]['post_types'][] = $post_type;
    }

    foreach ($options as $type => $option) {
        $options[$type]['post_types'] = array_values(array_unique($option['post_types']));
        $options[$type]['label'] = iss_search_get_public_type_label($type, $options[$type]['post_types']);
    }

    ksort($options);

    return $options;
}

function iss_search_get_type_aliases(): array
{
    $aliases = [
        'archive' => 'archive_object',
        'archivobjekt' => 'archive_object',
        'archivobjekte' => 'archive_object',
        'events' => 'event',
        'veranstaltungen' => 'event',
        'exhibitions' => 'exhibition',
        'ausstellungen' => 'exhibition',
        'places' => 'place',
        'orte' => 'place',
        'profiles' => 'profile',
        'projects' => 'project',
        'publikationen' => 'publication',
        'publications' => 'publication',
        'stories' => 'story',
        'tours' => 'tour',
        'fuehrungen' => 'tour',
        'videos' => 'video',
    ];

    foreach (iss_search_get_public_type_options() as $type => $option) {
        $aliases[$type] = $type;
        foreach ((array) ($option['post_types'] ?? []) as $post_type) {
            $aliases[sanitize_key((string) $post_type)] = $type;
        }
    }

    return (array) apply_filters('iss_search_type_aliases', $aliases);
}

function iss_search_normalize_public_search_types($value): array
{
    $aliases = iss_search_get_type_aliases();
    $types = [];

    foreach (iss_search_normalize_token_list($value) as $token) {
        $type = sanitize_key((string) ($aliases[$token] ?? $token));
        if ($type !== '') {
            $types[] = $type;
        }
    }

    return array_values(array_unique($types));
}

function iss_search_resolve_public_search_post_types(array $args = []): array
{
    $public_post_types = iss_graph_get_public_search_post_types();
    if (!$public_post_types) {
        return [];
    }

    $explicit_post_types = iss_search_normalize_token_list($args['post_types'] ?? []);
    if ($explicit_post_types) {
        return array_values(array_intersect($explicit_post_types, $public_post_types));
    }

    $types = iss_search_normalize_public_search_types($args['types'] ?? ($args['type'] ?? []));
    if (!$types) {
        return $public_post_types;
    }

    $post_types = [];
    foreach (iss_search_get_public_type_options() as $type => $option) {
        if (in_array($type, $types, true)) {
            $post_types = array_merge($post_types, (array) ($option['post_types'] ?? []));
        }
    }

    return array_values(array_intersect(array_unique($post_types), $public_post_types));
}

function iss_search_public_posts(string $query, array $args = [])
{
    $query = iss_graph_normalize_public_search_query($query);
    if ($query === '') {
        return [];
    }

    return iss_search_get_provider()->search($query, $args);
}

function iss_search_public_post_ids(string $query, array $args = []): array
{
    $hits = iss_search_public_posts($query, $args);
    if (is_wp_error($hits)) {
        return [];
    }

    $ids = array_map(static function (array $row): int {
        return (int) ($row['post_id'] ?? 0);
    }, is_array($hits) ? $hits : []);

    return array_values(array_unique(array_filter($ids)));
}

function iss_search_get_result_image(WP_Post $post): ?array
{
    $thumbnail_id = get_post_thumbnail_id($post);
    if ($thumbnail_id > 0) {
        $url = wp_get_attachment_image_url($thumbnail_id, 'medium');
        if (is_string($url) && $url !== '') {
            return [
                'id' => $thumbnail_id,
                'url' => $url,
                'alt' => (string) get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
            ];
        }
    }

    if (defined('ISS_WF_IMPORT_OBJECT_MD_IMAGE_URL_META') && $post->post_type === (function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt')) {
        $url = esc_url_raw((string) get_post_meta($post->ID, ISS_WF_IMPORT_OBJECT_MD_IMAGE_URL_META, true));
        if ($url !== '') {
            return [
                'id' => 0,
                'url' => $url,
                'alt' => get_the_title($post),
            ];
        }
    }

    return null;
}

function iss_search_get_result_contract(WP_Post $post, int $entity_id): array
{
    if ($entity_id > 0 && function_exists('iss_graph_get_entity_contract_payload') && function_exists('iss_graph_get_service')) {
        $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
        if (is_array($entity)) {
            return iss_graph_get_entity_contract_payload($entity);
        }
    }

    if (function_exists('iss_graph_get_contract_payload_for_post')) {
        $contract = iss_graph_get_contract_payload_for_post($post);
        if (is_array($contract)) {
            return $contract;
        }
    }

    $storage_kind = function_exists('iss_graph_get_storage_entity_kind_for_post_type')
        ? iss_graph_get_storage_entity_kind_for_post_type((string) $post->post_type)
        : sanitize_key((string) $post->post_type);
    $canonical_kind = function_exists('iss_graph_get_canonical_entity_kind')
        ? iss_graph_get_canonical_entity_kind($storage_kind)
        : $storage_kind;

    return [
        'kind' => $canonical_kind,
        'subtype' => '',
        'subtype_source' => '',
        'canonical_kind' => $canonical_kind,
        'storage_kind' => $storage_kind,
    ];
}

function iss_search_prepare_result(array $hit): ?array
{
    $post_id = (int) ($hit['post_id'] ?? 0);
    $post = $post_id > 0 ? get_post($post_id) : null;
    if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
        return null;
    }

    $config = iss_graph_get_public_search_post_type_config((string) $post->post_type);
    $type = sanitize_key((string) ($hit['search_bucket'] ?? ($config['bucket'] ?? $post->post_type)));
    $entity_id = absint($hit['entity_id'] ?? 0);
    $contract = iss_search_get_result_contract($post, $entity_id);
    $excerpt = trim((string) ($hit['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = trim((string) get_the_excerpt($post));
    }
    if ($excerpt === '') {
        $excerpt = wp_trim_words(wp_strip_all_tags((string) $post->post_content), 28, '');
    }

    return [
        'id' => $post_id,
        'result_kind' => $entity_id > 0 ? 'entity' : 'post',
        'entity_id' => $entity_id,
        'type' => $type,
        'type_label' => iss_search_get_result_type_label($type, [(string) $post->post_type], $contract),
        'contract_kind' => sanitize_key((string) ($contract['kind'] ?? '')),
        'subtype' => sanitize_key((string) ($contract['subtype'] ?? '')),
        'contract' => [
            'kind' => sanitize_key((string) ($contract['kind'] ?? '')),
            'subtype' => sanitize_key((string) ($contract['subtype'] ?? '')),
            'subtype_source' => sanitize_text_field((string) ($contract['subtype_source'] ?? '')),
            'canonical_kind' => sanitize_key((string) ($contract['canonical_kind'] ?? '')),
            'storage_kind' => sanitize_key((string) ($contract['storage_kind'] ?? '')),
        ],
        'post_type' => (string) $post->post_type,
        'title' => trim((string) ($hit['title'] ?? '')) ?: get_the_title($post),
        'excerpt' => $excerpt,
        'url' => (string) get_permalink($post),
        'image' => iss_search_get_result_image($post),
        'relevance' => (int) ($hit['relevance'] ?? 0),
    ];
}
