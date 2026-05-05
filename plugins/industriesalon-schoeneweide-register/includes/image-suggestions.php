<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_image_candidate_meta_key(): string
{
    return '_iss_register_image_candidates';
}

function iss_register_image_candidate_updated_meta_key(): string
{
    return '_iss_register_image_candidates_updated';
}

function iss_register_allowed_candidate_target_fields(): array
{
    return iss_register_image_group_fields();
}

function iss_register_get_image_candidates(int $post_id): array
{
    $stored = get_post_meta($post_id, iss_register_image_candidate_meta_key(), true);
    return iss_register_sanitize_image_candidates($stored);
}

function iss_register_set_image_candidates(int $post_id, array $candidates): void
{
    update_post_meta($post_id, iss_register_image_candidate_meta_key(), iss_register_sanitize_image_candidates($candidates));
    update_post_meta($post_id, iss_register_image_candidate_updated_meta_key(), current_time('mysql'));
}

function iss_register_get_imported_targets_for_attachment(int $post_id, int $attachment_id): array
{
    if ($attachment_id <= 0) {
        return [];
    }

    $targets = [];

    foreach (iss_register_allowed_candidate_target_fields() as $field) {
        $images = get_post_meta($post_id, $field, true);
        if (!is_array($images)) {
            continue;
        }

        foreach ($images as $image) {
            if (absint($image['media_id'] ?? 0) !== $attachment_id) {
                continue;
            }

            $targets[] = $field;
            break;
        }
    }

    return array_values(array_unique($targets));
}

function iss_register_sanitize_image_candidates($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $sanitized = [];

    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $targets = [];
        $raw_targets = isset($item['imported_targets']) && is_array($item['imported_targets']) ? $item['imported_targets'] : [];
        foreach ($raw_targets as $target) {
            $target = sanitize_key((string) $target);
            if (in_array($target, iss_register_allowed_candidate_target_fields(), true) && !in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        $sanitized[] = [
            'candidate_id' => sanitize_key((string) ($item['candidate_id'] ?? '')),
            'source' => sanitize_key((string) ($item['source'] ?? 'wikimedia')),
            'title' => sanitize_text_field((string) ($item['title'] ?? '')),
            'file_title' => sanitize_text_field((string) ($item['file_title'] ?? '')),
            'preview_url' => esc_url_raw((string) ($item['preview_url'] ?? '')),
            'full_image_url' => esc_url_raw((string) ($item['full_image_url'] ?? '')),
            'page_url' => esc_url_raw((string) ($item['page_url'] ?? '')),
            'original_url' => esc_url_raw((string) ($item['original_url'] ?? '')),
            'license' => sanitize_text_field((string) ($item['license'] ?? '')),
            'license_url' => esc_url_raw((string) ($item['license_url'] ?? '')),
            'author' => sanitize_text_field((string) ($item['author'] ?? '')),
            'credit' => sanitize_text_field((string) ($item['credit'] ?? '')),
            'description' => sanitize_textarea_field((string) ($item['description'] ?? '')),
            'year' => sanitize_text_field((string) ($item['year'] ?? '')),
            'provider' => sanitize_text_field((string) ($item['provider'] ?? '')),
            'category_label' => sanitize_text_field((string) ($item['category_label'] ?? '')),
            'note' => sanitize_text_field((string) ($item['note'] ?? '')),
            'distance_m' => isset($item['distance_m']) && $item['distance_m'] !== '' ? max(0, (int) $item['distance_m']) : '',
            'score' => isset($item['score']) ? (int) $item['score'] : 0,
            'can_import_locally' => !empty($item['can_import_locally']),
            'discovery_only' => !empty($item['discovery_only']),
            'imported_attachment_id' => absint($item['imported_attachment_id'] ?? 0),
            'imported_targets' => $targets,
        ];
    }

    return array_values(array_filter($sanitized, static function (array $item): bool {
        if ($item['candidate_id'] === '') {
            return false;
        }

        return $item['full_image_url'] !== '' || $item['page_url'] !== '' || $item['preview_url'] !== '';
    }));
}

function iss_register_compact_candidate_text(string $text, int $limit = 180): string
{
    $text = trim(wp_strip_all_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($text === '') {
        return '';
    }

    return wp_trim_words($text, max(12, (int) floor($limit / 6)), '…');
}

function iss_register_candidate_tokens(string $text): array
{
    $slug = sanitize_title($text);
    if ($slug === '') {
        return [];
    }

    $tokens = array_filter(array_map('trim', explode('-', $slug)), static function (string $token): bool {
        return strlen($token) >= 3;
    });

    return array_values(array_unique($tokens));
}

function iss_register_candidate_overlap_score(array $haystack_tokens, array $needle_tokens): int
{
    if (!$haystack_tokens || !$needle_tokens) {
        return 0;
    }

    return count(array_intersect($haystack_tokens, $needle_tokens));
}

function iss_register_candidate_year_from_value(string $value): string
{
    if (preg_match('/\b(18\d{2}|19\d{2}|20\d{2})\b/', $value, $matches)) {
        return (string) $matches[1];
    }

    return '';
}

function iss_register_get_image_candidate_source_label(string $source): string
{
    $labels = [
        'wikimedia' => 'Wikimedia Commons',
        'ddb' => 'Deutsche Digitale Bibliothek',
        'europeana' => 'Europeana',
    ];

    return $labels[$source] ?? ucfirst($source);
}

function iss_register_get_europeana_api_key(): string
{
    if (defined('ISS_REGISTER_EUROPEANA_API_KEY') && is_string(ISS_REGISTER_EUROPEANA_API_KEY)) {
        return trim(ISS_REGISTER_EUROPEANA_API_KEY);
    }

    return trim((string) get_option('iss_register_europeana_api_key', ''));
}

function iss_register_has_europeana_api_key(): bool
{
    return iss_register_get_europeana_api_key() !== '';
}

function iss_register_candidate_search_queries(int $post_id): array
{
    $title = trim((string) get_the_title($post_id));
    $address = trim((string) get_post_meta($post_id, 'address', true));

    $queries = [];
    if ($title !== '') {
        $queries[] = $title;
        $title_without_parentheses = trim((string) preg_replace('/\s*\([^)]*\)/u', '', $title));
        if ($title_without_parentheses !== '' && $title_without_parentheses !== $title) {
            $queries[] = $title_without_parentheses;
        }
    }

    if ($address !== '') {
        $street = trim((string) preg_replace('/,.*/', '', $address));
        if ($street !== '') {
            $queries[] = $street;
        }
    }

    foreach (['previous_use', 'history_short'] as $field) {
        $value = trim((string) get_post_meta($post_id, $field, true));
        if ($value === '') {
            continue;
        }

        foreach (iss_register_extract_historical_search_phrases($value) as $phrase) {
            $queries[] = $phrase;
        }
    }

    $queries = array_values(array_unique(array_filter(array_map('trim', $queries))));

    return array_slice($queries, 0, 6);
}

function iss_register_get_ddb_search_queries(int $post_id): array
{
    $queries = [];
    $title = trim((string) get_the_title($post_id));
    $address = trim((string) get_post_meta($post_id, 'address', true));

    if ($title !== '' && iss_register_query_has_place_marker($title)) {
        $queries[] = $title;
        $title_without_parentheses = trim((string) preg_replace('/\s*\([^)]*\)/u', '', $title));
        if ($title_without_parentheses !== '' && $title_without_parentheses !== $title && iss_register_query_has_place_marker($title_without_parentheses)) {
            $queries[] = $title_without_parentheses;
        }
    }

    if ($address !== '') {
        $street = trim((string) preg_replace('/,.*/', '', $address));
        if ($street !== '' && iss_register_query_has_place_marker($street)) {
            $queries[] = $street;
        }
    }

    foreach (['previous_use', 'history_short'] as $field) {
        $value = trim((string) get_post_meta($post_id, $field, true));
        if ($value === '') {
            continue;
        }

        foreach (iss_register_extract_historical_search_phrases($value) as $phrase) {
            $queries[] = $phrase;
        }
    }

    $refined = [];
    $normalized_queries = array_map('iss_register_normalize_candidate_phrase', $queries);
    $has_behrens_bau = in_array('peter behrens bau', $normalized_queries, true) || in_array('peter behrens bau oberschoneweide', $normalized_queries, true);

    foreach ($queries as $query) {
        $normalized = iss_register_normalize_candidate_phrase($query);
        if ($normalized === '' || in_array($normalized, ['aeg', 'veb', 'bdk', 'gmbh'], true)) {
            continue;
        }

        if ($has_behrens_bau && $normalized === 'peter behrens') {
            continue;
        }

        $refined[] = $query;

        if ($normalized === 'peter behrens bau') {
            $refined[] = 'Peter-Behrens-Bau Oberschöneweide';
        }

        if ((str_contains($normalized, 'kabelwerk oberspree') || str_contains($normalized, 'transformatorenwerk oberspree')) && !str_contains($normalized, 'berlin')) {
            $refined[] = $query . ' Berlin Oberschöneweide';
        }
    }

    return array_slice(array_values(array_unique(array_filter(array_map('trim', $refined)))), 0, 6);
}

function iss_register_query_has_place_marker(string $query): bool
{
    $normalized = iss_register_normalize_candidate_phrase($query);
    if ($normalized === '') {
        return false;
    }

    foreach ([
        'behrens',
        'bau',
        'kabelwerk',
        'transformatorenwerk',
        'funkhaus',
        'wilhelminenhof',
        'reinbeck',
        'nalepa',
        'oberspree',
        'schoneweide',
        'ufer',
        'rathenau',
        'halle',
        'museum',
        'campus',
        'ostend',
    ] as $needle) {
        if (str_contains($normalized, $needle)) {
            return true;
        }
    }

    return false;
}

function iss_register_query_has_specific_place_marker(string $query): bool
{
    $normalized = iss_register_normalize_candidate_phrase($query);
    if ($normalized === '') {
        return false;
    }

    foreach ([
        'peter behrens bau',
        'behrensbau',
        'kabelwerk',
        'transformatorenwerk',
        'funkhaus',
        'wilhelminenhof',
        'reinbeck',
        'nalepa',
        'oberspree',
        'schoneweide',
        'oberschoneweide',
        'niederschoneweide',
        'ostend',
        'rathenau',
        'mellowpark',
        'spree',
    ] as $needle) {
        if (str_contains($normalized, $needle)) {
            return true;
        }
    }

    return false;
}

function iss_register_extract_historical_search_phrases(string $value): array
{
    $value = html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($value === '') {
        return [];
    }

    $phrases = [];
    $segments = preg_split('/[.!?;]+/u', $value);
    if (!is_array($segments)) {
        $segments = [$value];
    }

    foreach ($segments as $segment) {
        $segment = trim((string) $segment);
        if ($segment === '') {
            continue;
        }

        $segment = preg_replace('/^\s*(DDR|heute|seit|ab)\s*:\s*/ui', '', $segment);
        $segment = trim((string) preg_replace('/\(\s*(18|19|20)\d{2}\s*\)/u', '', $segment));
        if ($segment === '') {
            continue;
        }

        if (preg_match('/\b(Peter[\s-]Behrens(?:[\s-]Bau)?)\b/ui', $segment, $matches)) {
            $phrases[] = $matches[1];
            $phrases[] = 'Peter-Behrens-Bau';
        }

        if (preg_match('/\b((?:AEG|VEB)?\s*(?:Kabelwerk|Transformatorenwerk|Turbinenfabrik|Funkhaus|Hauptwerk)\s+[A-ZÄÖÜ][A-Za-zÄÖÜäöüß\-]+(?:\s+[A-ZÄÖÜ][A-Za-zÄÖÜäöüß\-]+){0,2})\b/u', $segment, $matches)) {
            $phrases[] = trim((string) $matches[1]);
        }

        if (preg_match('/\b(Werk\s+für\s+Fern(?:seh|melde)wesen(?:\s*\([A-Z]{2,5}\))?)\b/ui', $segment, $matches)) {
            $phrases[] = trim((string) $matches[1]);
        }

        if (preg_match('/\b(Wilhelminenhof(?:straße|str\.?)\s*\d+[A-Za-z\-\/]*)\b/ui', $segment, $matches)) {
            $phrases[] = $matches[1];
        }

        if (preg_match('/\b(Reinbeck(?:straße|str\.?)\s*\d+[A-Za-z\-\/]*)\b/ui', $segment, $matches)) {
            $phrases[] = $matches[1];
        }

        if (preg_match('/\b(Nalepa(?:straße|str\.?)\s*\d+[A-Za-z\-\/]*)\b/ui', $segment, $matches)) {
            $phrases[] = $matches[1];
        }

        if (preg_match('/\b([A-ZÄÖÜ]{2,5})\b/u', $segment, $matches)) {
            $acronym = trim((string) $matches[1]);
            if (!in_array($acronym, ['DDR', 'GMBH', 'BERLIN', 'AEG', 'VEB'], true)) {
                $phrases[] = $acronym;
            }
        }
    }

    $clean = [];
    foreach ($phrases as $phrase) {
        $phrase = trim((string) preg_replace('/\s+/u', ' ', $phrase));
        if ($phrase === '' || strlen($phrase) < 3) {
            continue;
        }
        $clean[$phrase] = $phrase;
    }

    return array_slice(array_values($clean), 0, 5);
}

function iss_register_primary_research_query(int $post_id): string
{
    foreach (['previous_use', 'history_short'] as $field) {
        $value = trim((string) get_post_meta($post_id, $field, true));
        if ($value === '') {
            continue;
        }

        $historical = iss_register_extract_historical_search_phrases($value);
        if (!empty($historical[0])) {
            return (string) $historical[0];
        }
    }

    $queries = iss_register_candidate_search_queries($post_id);
    return (string) ($queries[0] ?? '');
}

function iss_register_render_image_research_links(int $post_id): string
{
    $query = iss_register_primary_research_query($post_id);
    if ($query === '') {
        return '';
    }

    $links = [
        'Wikimedia Commons' => 'https://commons.wikimedia.org/wiki/Special:MediaSearch?type=image&search=' . rawurlencode($query),
        'Deutsche Digitale Bibliothek' => iss_register_ddb_search_url($query),
        'Europeana' => 'https://www.europeana.eu/de/search?query=' . rawurlencode($query),
    ];

    $items = [];
    foreach ($links as $label => $url) {
        $items[] = '<a href="' . esc_url($url) . '" target="_blank" rel="noreferrer">' . esc_html($label) . '</a>';
    }

    return '<p class="iss-register-image-suggestions__research">Weitere Recherche mit Query <strong>' . esc_html($query) . '</strong>: ' . implode(' · ', $items) . '</p>';
}

function iss_register_europeana_request(string $path, array $query_args = [])
{
    $api_key = iss_register_get_europeana_api_key();
    if ($api_key === '') {
        return new WP_Error('iss_register_europeana_missing_key', 'Europeana API-Schlüssel fehlt.');
    }

    $request_url = add_query_arg($query_args, 'https://api.europeana.eu' . $path);
    $response = wp_remote_get($request_url, [
        'timeout' => 20,
        'redirection' => 3,
        'headers' => [
            'User-Agent' => 'IndustriesalonRegisterBot/1.0 (image suggestions)',
            'Accept' => 'application/json',
            'Accept-Language' => 'de',
            'X-Api-Key' => $api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return new WP_Error('iss_register_europeana_http_error', 'Europeana antwortete mit HTTP ' . $status_code . '.');
    }

    $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($decoded)) {
        return new WP_Error('iss_register_europeana_decode_error', 'Europeana lieferte keine lesbare Antwort.');
    }

    return $decoded;
}

function iss_register_get_europeana_search_queries(int $post_id): array
{
    return iss_register_get_ddb_search_queries($post_id);
}

function iss_register_get_first_string_value($value): string
{
    if (is_array($value)) {
        foreach ($value as $item) {
            $item = iss_register_get_first_string_value($item);
            if ($item !== '') {
                return $item;
            }
        }

        return '';
    }

    return is_string($value) ? trim($value) : '';
}

function iss_register_get_europeana_item_value(array $item, array $keys): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $item)) {
            continue;
        }

        $value = iss_register_get_first_string_value($item[$key]);
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function iss_register_get_europeana_item_page_url(string $item_id): string
{
    $item_id = trim($item_id);
    if ($item_id === '') {
        return '';
    }

    return esc_url_raw('https://www.europeana.eu/item' . $item_id);
}

function iss_register_search_europeana_candidates(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_REGISTER_POST_TYPE || !iss_register_has_europeana_api_key()) {
        return [];
    }

    $queries = iss_register_get_europeana_search_queries($post_id);
    if (!$queries) {
        return [];
    }

    $context_tokens = iss_register_candidate_tokens(implode(' ', array_merge(
        [$post->post_title],
        $queries,
        [(string) get_post_meta($post_id, 'address', true)]
    )));
    $generic_tokens = ['behrens', 'bau', 'werk', 'halle', 'campus', 'museum', 'ufer'];
    $strong_context_tokens = array_values(array_diff($context_tokens, $generic_tokens));

    $candidates = [];

    foreach ($queries as $query) {
        $response = iss_register_europeana_request('/record/v2/search.json', [
            'query' => $query,
            'rows' => 8,
            'profile' => 'rich',
            'qf' => ['TYPE:IMAGE'],
        ]);

        if (is_wp_error($response) || empty($response['items']) || !is_array($response['items'])) {
            continue;
        }

        foreach ($response['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_id = trim((string) ($item['id'] ?? ''));
            $page_url = iss_register_get_europeana_item_page_url($item_id);
            $original_url = esc_url_raw(iss_register_get_europeana_item_value($item, ['edmIsShownAt']));
            $preview_url = esc_url_raw(iss_register_get_europeana_item_value($item, ['edmPreview']));
            $title = iss_register_get_europeana_item_value($item, ['title', 'dcTitleLangAware']);

            if ($title === '' || ($page_url === '' && $original_url === '') || $preview_url === '') {
                continue;
            }

            $provider = iss_register_get_europeana_item_value($item, ['dataProvider', 'provider']);
            $provider_via = iss_register_get_europeana_item_value($item, ['provider']);
            $rights = esc_url_raw(iss_register_get_europeana_item_value($item, ['rights']));
            $description = iss_register_compact_candidate_text(
                iss_register_get_europeana_item_value($item, ['dcDescription', 'description']),
                220
            );
            $match_text = implode(' ', array_filter([$title, $description, $provider, $original_url]));
            $overlap = iss_register_candidate_overlap_score(
                iss_register_candidate_tokens($match_text),
                $context_tokens
            );
            $strong_overlap = iss_register_candidate_overlap_score(
                iss_register_candidate_tokens($match_text),
                $strong_context_tokens
            );

            if ($provider_via !== '' && $provider_via !== $provider) {
                $provider = trim($provider . ' via ' . $provider_via);
            }

            if ($strong_overlap < 1 && !iss_register_query_has_specific_place_marker($match_text)) {
                continue;
            }

            if (iss_register_is_local_mirror_candidate($provider, $original_url, $page_url, $rights)) {
                continue;
            }

            $candidate = [
                'candidate_id' => sanitize_key('europeana_' . md5($item_id !== '' ? $item_id : $page_url)),
                'source' => 'europeana',
                'title' => $title,
                'file_title' => '',
                'preview_url' => $preview_url,
                'full_image_url' => $preview_url,
                'page_url' => $page_url,
                'original_url' => $original_url,
                'license' => $rights,
                'license_url' => $rights,
                'author' => iss_register_get_europeana_item_value($item, ['dcCreator', 'edmAgentLabel']),
                'credit' => '',
                'description' => $description,
                'year' => iss_register_candidate_year_from_value(implode(' ', array_filter([
                    iss_register_get_europeana_item_value($item, ['year']),
                    iss_register_get_europeana_item_value($item, ['timestamp_created']),
                    iss_register_get_europeana_item_value($item, ['edmTimespanLabel']),
                ]))),
                'provider' => $provider,
                'category_label' => 'Europeana Bild',
                'note' => 'Recherchehinweis aus Europeana. Lokaler Import folgt später über Originalquelle oder API.',
                'distance_m' => '',
                'score' => 0,
                'can_import_locally' => false,
                'discovery_only' => true,
                'imported_attachment_id' => 0,
                'imported_targets' => [],
            ];

            $candidate['score'] = iss_register_score_discovery_candidate($candidate, $context_tokens, (string) $query) - 4;

            $candidate_key = $original_url !== '' ? $original_url : ($page_url !== '' ? $page_url : $candidate['candidate_id']);
            if (isset($candidates[$candidate_key]) && ($candidates[$candidate_key]['score'] ?? 0) >= $candidate['score']) {
                continue;
            }

            $candidates[$candidate_key] = $candidate;
        }
    }

    $candidates = array_values($candidates);
    usort($candidates, static function (array $left, array $right): int {
        if (($left['score'] ?? 0) === ($right['score'] ?? 0)) {
            return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        }

        return (int) ($right['score'] ?? 0) <=> (int) ($left['score'] ?? 0);
    });

    return array_slice($candidates, 0, 10);
}

function iss_register_extract_wikimedia_metadata_value(array $extmetadata, string $key): string
{
    $value = (string) ($extmetadata[$key]['value'] ?? '');
    if ($value === '') {
        return '';
    }

    return trim(wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function iss_register_normalize_candidate_phrase(string $value): string
{
    $value = remove_accents($value);
    $value = strtolower($value);
    $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', (string) $value);

    return trim((string) $value);
}

function iss_register_html_request(string $url)
{
    $response = wp_remote_get($url, [
        'timeout' => 20,
        'redirection' => 4,
        'headers' => [
            'User-Agent' => 'IndustriesalonRegisterBot/1.0 (image suggestions)',
            'Accept-Language' => 'de',
        ],
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return new WP_Error('iss_register_remote_http_error', 'Externe Suche antwortete mit HTTP ' . $status_code . '.');
    }

    return (string) wp_remote_retrieve_body($response);
}

function iss_register_create_xpath_from_html(string $html): ?DOMXPath
{
    if ($html === '' || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        return null;
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return null;
    }

    return new DOMXPath($dom);
}

function iss_register_xpath_first_text(DOMXPath $xpath, string $query, ?DOMNode $context = null): string
{
    $nodes = $xpath->query($query, $context);
    if (!$nodes || $nodes->length === 0) {
        return '';
    }

    return trim(wp_strip_all_tags((string) $nodes->item(0)->textContent));
}

function iss_register_xpath_first_attr(DOMXPath $xpath, string $query, string $attribute, ?DOMNode $context = null): string
{
    $nodes = $xpath->query($query, $context);
    if (!$nodes || $nodes->length === 0 || !$nodes->item(0) instanceof DOMElement) {
        return '';
    }

    return trim((string) $nodes->item(0)->getAttribute($attribute));
}

function iss_register_ddb_search_url(string $query): string
{
    return add_query_arg([
        'query' => $query,
        'rows' => 8,
        'offset' => 0,
        'viewType' => 'list',
        'isThumbnailFiltered' => 'true',
        'lang' => 'de',
    ], 'https://www.deutsche-digitale-bibliothek.de/searchresults');
}

function iss_register_ddb_absolute_url(string $path): string
{
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return esc_url_raw($path);
    }

    return esc_url_raw('https://www.deutsche-digitale-bibliothek.de' . $path);
}

function iss_register_ddb_is_image_type(string $label): bool
{
    $label = iss_register_normalize_candidate_phrase($label);
    if ($label === '') {
        return false;
    }

    foreach (['foto', 'fotografie', 'bild', 'grafik', 'negativ', 'dia', 'postkarte', 'reprofoto'] as $needle) {
        if (str_contains($label, $needle)) {
            return true;
        }
    }

    return false;
}

function iss_register_fetch_ddb_search_rows(string $query): array
{
    $html = iss_register_html_request(iss_register_ddb_search_url($query));
    if (is_wp_error($html)) {
        return [];
    }

    $xpath = iss_register_create_xpath_from_html($html);
    if (!$xpath) {
        return [];
    }

    $items = $xpath->query("//ul[@id='results-container']/li[contains(@class,'item')]");
    if (!$items) {
        return [];
    }

    $rows = [];
    foreach ($items as $item) {
        $category_label = iss_register_xpath_first_text($xpath, ".//p[contains(@class,'category-type')]", $item);
        if (!iss_register_ddb_is_image_type($category_label)) {
            continue;
        }

        $title = iss_register_xpath_first_text($xpath, ".//h3//a[contains(@class,'h4-ddb')]", $item);
        $page_url = iss_register_ddb_absolute_url(
            iss_register_xpath_first_attr($xpath, ".//h3//a[contains(@class,'h4-ddb')]", 'href', $item)
        );
        $preview_url = esc_url_raw(
            iss_register_xpath_first_attr($xpath, ".//div[contains(@class,'thumbnail')]//img", 'src', $item)
        );

        if ($title === '' || $page_url === '') {
            continue;
        }

        $rows[] = [
            'title' => $title,
            'page_url' => $page_url,
            'preview_url' => $preview_url,
            'description' => iss_register_compact_candidate_text(
                iss_register_xpath_first_text($xpath, ".//p[contains(@class,'description')]", $item),
                220
            ),
            'category_label' => $category_label,
            'query' => $query,
        ];
    }

    return $rows;
}

function iss_register_fetch_ddb_item_details(string $page_url): array
{
    static $cache = [];

    if (isset($cache[$page_url])) {
        return $cache[$page_url];
    }

    $html = iss_register_html_request($page_url);
    if (is_wp_error($html)) {
        $cache[$page_url] = [];
        return [];
    }

    $xpath = iss_register_create_xpath_from_html($html);
    if (!$xpath) {
        $cache[$page_url] = [];
        return [];
    }

    $rights = iss_register_xpath_first_text(
        $xpath,
        "//dt[contains(normalize-space(.), 'Rechteinformation')]/following-sibling::dd[1]"
    );

    $details = [
        'provider' => iss_register_xpath_first_text($xpath, "//a[contains(@class,'organization')]"),
        'original_url' => esc_url_raw(
            iss_register_xpath_first_attr($xpath, "//a[contains(@class,'core-data-button')]", 'href')
        ),
        'rights' => $rights,
        'image_url' => esc_url_raw(
            iss_register_xpath_first_attr($xpath, "//meta[@property='og:image']", 'content')
        ),
    ];

    $cache[$page_url] = $details;

    return $details;
}

function iss_register_is_local_mirror_candidate(string $provider, string $original_url, string $page_url, string $rights): bool
{
    $blob = iss_register_normalize_candidate_phrase(implode(' ', [
        $provider,
        $original_url,
        $page_url,
        $rights,
    ]));

    foreach ([
        'industriesalon',
        'museum digital',
        'museum-digital',
        'berlin museum digital',
        'wf museum',
        'wf-museum',
        '192 168 2 31',
        'localhost 8082',
    ] as $needle) {
        if (str_contains($blob, iss_register_normalize_candidate_phrase($needle))) {
            return true;
        }
    }

    return false;
}

function iss_register_score_discovery_candidate(array $candidate, array $context_tokens, string $matched_query): int
{
    $candidate_tokens = iss_register_candidate_tokens(implode(' ', array_filter([
        (string) ($candidate['title'] ?? ''),
        (string) ($candidate['description'] ?? ''),
        (string) ($candidate['provider'] ?? ''),
        (string) ($candidate['category_label'] ?? ''),
    ])));
    $score = iss_register_candidate_overlap_score($candidate_tokens, $context_tokens) * 18;

    $normalized_title = iss_register_normalize_candidate_phrase((string) ($candidate['title'] ?? ''));
    $normalized_query = iss_register_normalize_candidate_phrase($matched_query);
    if ($normalized_query !== '' && str_contains($normalized_title, $normalized_query)) {
        $score += 28;
    }

    return $score + 40;
}

function iss_register_search_ddb_candidates(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_REGISTER_POST_TYPE) {
        return [];
    }

    $queries = iss_register_get_ddb_search_queries($post_id);
    if (!$queries) {
        return [];
    }

    $context_tokens = iss_register_candidate_tokens(implode(' ', array_merge(
        [$post->post_title],
        $queries,
        [(string) get_post_meta($post_id, 'address', true)]
    )));

    $candidates = [];
    foreach ($queries as $query) {
        foreach (iss_register_fetch_ddb_search_rows($query) as $row) {
            $details = iss_register_fetch_ddb_item_details((string) $row['page_url']);
            $provider = (string) ($details['provider'] ?? '');
            $original_url = (string) ($details['original_url'] ?? '');
            $rights = (string) ($details['rights'] ?? '');

            if (iss_register_is_local_mirror_candidate($provider, $original_url, (string) $row['page_url'], $rights)) {
                continue;
            }

            $candidate = [
                'candidate_id' => sanitize_key('ddb_' . md5((string) $row['page_url'])),
                'source' => 'ddb',
                'title' => (string) $row['title'],
                'file_title' => '',
                'preview_url' => (string) ($row['preview_url'] ?: ($details['image_url'] ?? '')),
                'full_image_url' => (string) ($details['image_url'] ?? ''),
                'page_url' => (string) $row['page_url'],
                'original_url' => $original_url,
                'license' => $rights,
                'license_url' => '',
                'author' => '',
                'credit' => '',
                'description' => (string) ($row['description'] ?? ''),
                'year' => '',
                'provider' => $provider,
                'category_label' => (string) ($row['category_label'] ?? ''),
                'note' => 'Recherchehinweis aus der DDB. Lokaler Import folgt später über Originalquelle oder API.',
                'distance_m' => '',
                'score' => 0,
                'can_import_locally' => false,
                'discovery_only' => true,
                'imported_attachment_id' => 0,
                'imported_targets' => [],
            ];

            $candidate['score'] = iss_register_score_discovery_candidate($candidate, $context_tokens, (string) $query);

            $page_url = (string) $candidate['page_url'];
            if ($page_url !== '' && isset($candidates[$page_url]) && ($candidates[$page_url]['score'] ?? 0) >= $candidate['score']) {
                continue;
            }

            $candidates[$page_url] = $candidate;
        }
    }

    $candidates = array_values($candidates);
    usort($candidates, static function (array $left, array $right): int {
        if ($left['score'] === $right['score']) {
            return strcmp((string) $left['title'], (string) $right['title']);
        }

        return $right['score'] <=> $left['score'];
    });

    return array_slice($candidates, 0, 10);
}

function iss_register_wikimedia_request(array $query_args)
{
    $request_url = add_query_arg(array_merge([
        'action' => 'query',
        'format' => 'json',
        'formatversion' => 2,
    ], $query_args), 'https://commons.wikimedia.org/w/api.php');

    $response = wp_remote_get($request_url, [
        'timeout' => 20,
        'redirection' => 3,
        'headers' => [
            'User-Agent' => 'IndustriesalonRegisterBot/1.0 (image suggestions)',
            'Accept-Language' => 'de',
        ],
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return new WP_Error('iss_register_wikimedia_http_error', 'Wikimedia antwortete mit HTTP ' . $status_code . '.');
    }

    $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($decoded)) {
        return new WP_Error('iss_register_wikimedia_decode_error', 'Wikimedia lieferte keine lesbare Antwort.');
    }

    return $decoded;
}

function iss_register_get_wikimedia_search_queries(int $post_id): array
{
    $title = trim((string) get_the_title($post_id));
    $address = trim((string) get_post_meta($post_id, 'address', true));

    $queries = [];
    if ($title !== '' && iss_register_query_has_place_marker($title)) {
        $queries[] = $title;
        $title_without_parentheses = trim((string) preg_replace('/\s*\([^)]*\)/u', '', $title));
        if ($title_without_parentheses !== '' && $title_without_parentheses !== $title && iss_register_query_has_place_marker($title_without_parentheses)) {
            $queries[] = $title_without_parentheses;
        }
    }

    if ($address !== '') {
        $street = trim((string) preg_replace('/,.*/', '', $address));
        if ($street !== '' && iss_register_query_has_place_marker($street)) {
            $queries[] = $title !== '' ? $title . ' ' . $street : $street;
        }
    }

    $queries = array_values(array_unique(array_filter(array_map('trim', $queries))));

    return array_slice($queries, 0, 3);
}

function iss_register_collect_wikimedia_titles_by_geosearch(float $lat, float $lng): array
{
    $response = iss_register_wikimedia_request([
        'list' => 'geosearch',
        'gsprimary' => 'all',
        'gsnamespace' => 6,
        'gscoord' => $lat . '|' . $lng,
        'gsradius' => 1600,
        'gslimit' => 18,
    ]);

    if (is_wp_error($response)) {
        return [];
    }

    $pages = isset($response['query']['geosearch']) && is_array($response['query']['geosearch'])
        ? $response['query']['geosearch']
        : [];

    $titles = [];
    foreach ($pages as $page) {
        $title = trim((string) ($page['title'] ?? ''));
        if ($title === '') {
            continue;
        }

        $titles[$title] = isset($page['dist']) ? max(0, (int) $page['dist']) : 0;
    }

    return $titles;
}

function iss_register_collect_wikimedia_titles_by_text_query(string $query): array
{
    $response = iss_register_wikimedia_request([
        'generator' => 'search',
        'gsrsearch' => $query,
        'gsrnamespace' => 6,
        'gsrlimit' => 12,
        'prop' => 'info',
    ]);

    if (is_wp_error($response)) {
        return [];
    }

    $pages = isset($response['query']['pages']) && is_array($response['query']['pages'])
        ? $response['query']['pages']
        : [];

    $titles = [];
    foreach ($pages as $page) {
        $title = trim((string) ($page['title'] ?? ''));
        if ($title === '') {
            continue;
        }

        $titles[$title] = '';
    }

    return $titles;
}

function iss_register_fetch_wikimedia_file_details(array $titles): array
{
    $titles = array_values(array_unique(array_filter(array_map('trim', $titles))));
    if (!$titles) {
        return [];
    }

    $response = iss_register_wikimedia_request([
        'prop' => 'imageinfo',
        'titles' => implode('|', $titles),
        'iiprop' => 'url|extmetadata',
        'iiurlwidth' => 900,
    ]);

    if (is_wp_error($response)) {
        return [];
    }

    $pages = isset($response['query']['pages']) && is_array($response['query']['pages'])
        ? $response['query']['pages']
        : [];

    $details = [];

    foreach ($pages as $page) {
        $title = trim((string) ($page['title'] ?? ''));
        if ($title === '') {
            continue;
        }

        $imageinfo = isset($page['imageinfo'][0]) && is_array($page['imageinfo'][0]) ? $page['imageinfo'][0] : [];
        if (!$imageinfo) {
            continue;
        }

        $details[$title] = $imageinfo;
    }

    return $details;
}

function iss_register_score_wikimedia_candidate(array $candidate, array $context_tokens): int
{
    $candidate_tokens = iss_register_candidate_tokens((string) ($candidate['file_title'] ?? '') . ' ' . (string) ($candidate['title'] ?? ''));
    $overlap = iss_register_candidate_overlap_score($candidate_tokens, $context_tokens);
    $distance = isset($candidate['distance_m']) && $candidate['distance_m'] !== '' ? max(0, (int) $candidate['distance_m']) : 9999;
    $distance_score = $distance < 9999 ? max(0, 80 - (int) floor($distance / 30)) : 0;

    return ($overlap * 18) + $distance_score;
}

function iss_register_search_image_candidates(int $post_id)
{
    $wikimedia_candidates = iss_register_search_wikimedia_candidates($post_id);
    if (is_wp_error($wikimedia_candidates)) {
        return $wikimedia_candidates;
    }

    $combined = [];
    foreach (array_merge(
        $wikimedia_candidates,
        iss_register_search_ddb_candidates($post_id),
        iss_register_search_europeana_candidates($post_id)
    ) as $candidate) {
        $candidate_id = sanitize_key((string) ($candidate['candidate_id'] ?? ''));
        if ($candidate_id === '') {
            continue;
        }

        $combined[$candidate_id] = $candidate;
    }

    $combined = array_values($combined);
    usort($combined, static function (array $left, array $right): int {
        if (($left['score'] ?? 0) === ($right['score'] ?? 0)) {
            return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        }

        return (int) ($right['score'] ?? 0) <=> (int) ($left['score'] ?? 0);
    });

    return array_slice($combined, 0, 24);
}

function iss_register_search_wikimedia_candidates(int $post_id)
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_REGISTER_POST_TYPE) {
        return new WP_Error('iss_register_invalid_post', 'Ungültiger Register-Ort.');
    }

    $title_queries = iss_register_get_wikimedia_search_queries($post_id);
    $lat = get_post_meta($post_id, 'lat', true);
    $lng = get_post_meta($post_id, 'lng', true);
    $has_coordinates = $lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng);

    $title_distances = [];
    if ($has_coordinates) {
        $title_distances = iss_register_collect_wikimedia_titles_by_geosearch((float) $lat, (float) $lng);
    }

    foreach ($title_queries as $query) {
        foreach (iss_register_collect_wikimedia_titles_by_text_query($query) as $file_title => $distance) {
            if (!isset($title_distances[$file_title])) {
                $title_distances[$file_title] = $distance;
            }
        }
    }

    if (!$title_distances) {
        return [];
    }

    $context_tokens = iss_register_candidate_tokens(implode(' ', array_merge(
        [$post->post_title],
        $title_queries,
        [(string) get_post_meta($post_id, 'address', true)]
    )));

    $details = iss_register_fetch_wikimedia_file_details(array_keys($title_distances));
    $candidates = [];

    foreach ($details as $file_title => $imageinfo) {
        $extmetadata = isset($imageinfo['extmetadata']) && is_array($imageinfo['extmetadata']) ? $imageinfo['extmetadata'] : [];
        $full_image_url = esc_url_raw((string) ($imageinfo['url'] ?? ''));
        $preview_url = esc_url_raw((string) ($imageinfo['thumburl'] ?? $full_image_url));
        if ($full_image_url === '') {
            continue;
        }

        $display_title = iss_register_extract_wikimedia_metadata_value($extmetadata, 'ObjectName');
        if ($display_title === '') {
            $display_title = preg_replace('/^File:/', '', $file_title);
        }

        $attachment_id = iss_register_get_attachment_id_by_source_url($full_image_url);

        $candidate = [
            'candidate_id' => sanitize_key('wikimedia_' . md5($file_title . '|' . $full_image_url)),
            'source' => 'wikimedia',
            'title' => $display_title,
            'file_title' => $file_title,
            'preview_url' => $preview_url,
            'full_image_url' => $full_image_url,
            'page_url' => esc_url_raw((string) ($imageinfo['descriptionurl'] ?? '')),
            'license' => iss_register_extract_wikimedia_metadata_value($extmetadata, 'LicenseShortName'),
            'license_url' => esc_url_raw((string) ($extmetadata['LicenseUrl']['value'] ?? '')),
            'author' => iss_register_extract_wikimedia_metadata_value($extmetadata, 'Artist'),
            'credit' => iss_register_extract_wikimedia_metadata_value($extmetadata, 'Credit'),
            'description' => iss_register_compact_candidate_text(iss_register_extract_wikimedia_metadata_value($extmetadata, 'ImageDescription')),
            'year' => '',
            'distance_m' => $title_distances[$file_title] ?? '',
            'score' => 0,
            'can_import_locally' => true,
            'imported_attachment_id' => $attachment_id,
            'imported_targets' => iss_register_get_imported_targets_for_attachment($post_id, $attachment_id),
        ];

        $candidate['year'] = iss_register_candidate_year_from_value(
            iss_register_extract_wikimedia_metadata_value($extmetadata, 'DateTimeOriginal')
        );

        if ($candidate['year'] === '') {
            $candidate['year'] = iss_register_candidate_year_from_value(
                iss_register_extract_wikimedia_metadata_value($extmetadata, 'DateTime')
            );
        }

        $candidate['score'] = iss_register_score_wikimedia_candidate($candidate, $context_tokens);
        $candidates[] = $candidate;
    }

    usort($candidates, static function (array $left, array $right): int {
        if ($left['score'] === $right['score']) {
            return strcmp((string) $left['title'], (string) $right['title']);
        }

        return $right['score'] <=> $left['score'];
    });

    return array_slice($candidates, 0, 18);
}

function iss_register_get_image_candidate_field_label(string $field): string
{
    $labels = [
        'archive_images' => 'Archivbilder',
        'current_images' => 'Aktuelle Bilder',
        'document_images' => 'Dokumentbilder',
    ];

    return $labels[$field] ?? $field;
}

function iss_register_render_image_candidates_html(int $post_id): string
{
    $candidates = iss_register_get_image_candidates($post_id);
    $updated_at = trim((string) get_post_meta($post_id, iss_register_image_candidate_updated_meta_key(), true));

    ob_start();

    echo '<div class="iss-register-image-suggestions__toolbar">';
    echo '<button type="button" class="button button-secondary iss-register-image-suggestions__search" data-post-id="' . esc_attr((string) $post_id) . '">Bildvorschläge suchen</button>';
    echo '<span class="spinner iss-register-image-suggestions__spinner"></span>';
    if ($updated_at !== '') {
        echo '<p class="description iss-register-image-suggestions__updated">Letzte Suche: ' . esc_html(mysql2date('d.m.Y H:i', $updated_at)) . '</p>';
    }
    echo '</div>';

    echo '<div class="iss-register-image-suggestions__results">';

    if (!$candidates) {
        echo '<p class="iss-register-image-suggestions__empty">Noch keine Bildvorschläge gespeichert. Die Suche nutzt aktuelle und historische Begriffe des Register-Orts.</p>';
        echo '</div>';
        return (string) ob_get_clean();
    }

    foreach ($candidates as $candidate) {
        $imported_targets = isset($candidate['imported_targets']) && is_array($candidate['imported_targets']) ? $candidate['imported_targets'] : [];
        $target_labels = array_map('iss_register_get_image_candidate_field_label', $imported_targets);
        $source = sanitize_key((string) ($candidate['source'] ?? 'wikimedia'));
        $source_label = iss_register_get_image_candidate_source_label($source);
        $can_import = !empty($candidate['can_import_locally']) && !empty($candidate['full_image_url']);

        echo '<article class="iss-register-image-suggestion-card">';
        echo '<div class="iss-register-image-suggestion-card__media">';
        if ($candidate['preview_url'] !== '') {
            echo '<img src="' . esc_url($candidate['preview_url']) . '" alt="">';
        } else {
            echo '<span class="iss-register-image-suggestion-card__no-media">Kein Vorschaubild</span>';
        }
        echo '</div>';
        echo '<div class="iss-register-image-suggestion-card__body">';
        echo '<div class="iss-register-image-suggestion-card__meta">';
        echo '<strong>' . esc_html($source_label) . '</strong>';
        if (!empty($candidate['category_label'])) {
            echo '<span>' . esc_html((string) $candidate['category_label']) . '</span>';
        }
        if ($candidate['distance_m'] !== '') {
            echo '<span>' . esc_html((string) $candidate['distance_m']) . ' m entfernt</span>';
        }
        if (!empty($candidate['license'])) {
            echo '<span>' . esc_html((string) $candidate['license']) . '</span>';
        }
        echo '</div>';
        echo '<h4 class="iss-register-image-suggestion-card__title">' . esc_html((string) $candidate['title']) . '</h4>';
        echo '<p class="iss-register-image-suggestion-card__details">';
        if (!empty($candidate['provider'])) {
            echo '<span>' . esc_html((string) $candidate['provider']) . '</span>';
        }
        if (!empty($candidate['author'])) {
            echo '<span>' . esc_html((string) $candidate['author']) . '</span>';
        }
        if (!empty($candidate['year'])) {
            echo '<span>' . esc_html((string) $candidate['year']) . '</span>';
        }
        echo '</p>';
        if (!empty($candidate['description'])) {
            echo '<p class="iss-register-image-suggestion-card__description">' . esc_html((string) $candidate['description']) . '</p>';
        }
        echo '<p class="iss-register-image-suggestion-card__links">';
        if (!empty($candidate['page_url'])) {
            echo '<a href="' . esc_url((string) $candidate['page_url']) . '" target="_blank" rel="noreferrer">Datensatz öffnen</a> ';
        }
        if (!empty($candidate['original_url'])) {
            echo '<a href="' . esc_url((string) $candidate['original_url']) . '" target="_blank" rel="noreferrer">Original beim Datenpartner</a>';
        }
        echo '</p>';

        if ($target_labels) {
            echo '<p class="iss-register-image-suggestion-card__imported">Bereits importiert in: ' . esc_html(implode(', ', $target_labels)) . '</p>';
        }

        if (!empty($candidate['note'])) {
            echo '<p class="iss-register-image-suggestion-card__note">' . esc_html((string) $candidate['note']) . '</p>';
        }

        if ($can_import) {
            echo '<div class="iss-register-image-suggestion-card__actions">';
            foreach (iss_register_allowed_candidate_target_fields() as $target_field) {
                echo '<button type="button" class="button iss-register-image-suggestion-card__import" data-post-id="' . esc_attr((string) $post_id) . '" data-candidate-id="' . esc_attr((string) $candidate['candidate_id']) . '" data-target-field="' . esc_attr($target_field) . '">Als ' . esc_html(iss_register_get_image_candidate_field_label($target_field)) . ' importieren</button>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</article>';
    }

    echo '</div>';

    return (string) ob_get_clean();
}

function iss_register_render_image_suggestions_meta_box(WP_Post $post): void
{
    $sources = '<strong>Wikimedia Commons</strong> und der <strong>Deutschen Digitalen Bibliothek</strong>';
    if (iss_register_has_europeana_api_key()) {
        $sources .= ' sowie <strong>Europeana</strong>';
    }

    echo '<div class="iss-register-image-suggestions" data-post-id="' . esc_attr((string) $post->ID) . '">';
    echo '<p>Bildvorschläge aus ' . $sources . '. Wikimedia bleibt direkt importierbar, DDB und Europeana erscheinen im ersten Durchgang als Recherchehinweis mit Link zur Originalquelle.</p>';
    echo '<div class="iss-register-image-suggestions__dynamic">';
    echo iss_register_render_image_candidates_html((int) $post->ID);
    echo '</div>';
    echo iss_register_render_image_research_links((int) $post->ID);
    echo '</div>';
}

function iss_register_find_image_candidate(int $post_id, string $candidate_id): array
{
    foreach (iss_register_get_image_candidates($post_id) as $candidate) {
        if (($candidate['candidate_id'] ?? '') === $candidate_id) {
            return $candidate;
        }
    }

    return [];
}

function iss_register_update_image_candidate(int $post_id, array $updated_candidate): void
{
    $candidates = iss_register_get_image_candidates($post_id);

    foreach ($candidates as $index => $candidate) {
        if (($candidate['candidate_id'] ?? '') !== ($updated_candidate['candidate_id'] ?? '')) {
            continue;
        }

        $candidates[$index] = $updated_candidate;
        iss_register_set_image_candidates($post_id, $candidates);
        return;
    }
}

function iss_register_import_image_candidate_to_group(int $post_id, string $candidate_id, string $target_field)
{
    if (!in_array($target_field, iss_register_allowed_candidate_target_fields(), true)) {
        return new WP_Error('iss_register_invalid_target_field', 'Ungültige Bildgruppe.');
    }

    $candidate = iss_register_find_image_candidate($post_id, $candidate_id);
    if (!$candidate) {
        return new WP_Error('iss_register_candidate_not_found', 'Bildvorschlag nicht gefunden.');
    }

    if (empty($candidate['can_import_locally']) || empty($candidate['full_image_url'])) {
        return new WP_Error('iss_register_candidate_not_importable', 'Bild darf nicht lokal gespeichert werden.');
    }

    $attachment_id = absint($candidate['imported_attachment_id'] ?? 0);
    if ($attachment_id <= 0) {
        $attachment_id = iss_register_import_attachment_from_source_url((string) $candidate['full_image_url'], (string) $candidate['title']);
    }

    if ($attachment_id <= 0) {
        return new WP_Error('iss_register_candidate_import_failed', 'Bild konnte nicht in die Mediathek übernommen werden.');
    }

    if ((int) wp_get_post_parent_id($attachment_id) !== $post_id) {
        wp_update_post([
            'ID' => $attachment_id,
            'post_parent' => $post_id,
        ]);
    }

    $images = get_post_meta($post_id, $target_field, true);
    if (!is_array($images)) {
        $images = [];
    }

    $already_present = false;
    foreach ($images as $image) {
        if (absint($image['media_id'] ?? 0) === $attachment_id) {
            $already_present = true;
            break;
        }
    }

    if (!$already_present) {
        $images[] = [
            'media_id' => $attachment_id,
            'url' => (string) (wp_get_attachment_url($attachment_id) ?: ''),
            'caption' => (string) ($candidate['title'] ?? ''),
            'year' => (string) ($candidate['year'] ?? ''),
            'source' => 'Wikimedia Commons',
            'photographer' => (string) ($candidate['author'] ?? ''),
            'rights' => trim(implode(' | ', array_filter([
                (string) ($candidate['license'] ?? ''),
                (string) ($candidate['page_url'] ?? ''),
            ]))),
            'is_featured' => false,
            'visibility' => 'pending',
        ];

        update_post_meta($post_id, $target_field, iss_register_sanitize_image_group($images));
    }

    $targets = isset($candidate['imported_targets']) && is_array($candidate['imported_targets']) ? $candidate['imported_targets'] : [];
    if (!in_array($target_field, $targets, true)) {
        $targets[] = $target_field;
    }

    $candidate['imported_attachment_id'] = $attachment_id;
    $candidate['imported_targets'] = array_values(array_unique($targets));
    iss_register_update_image_candidate($post_id, $candidate);

    return [
        'attachment_id' => $attachment_id,
        'target_field' => $target_field,
    ];
}

function iss_register_ajax_search_wikimedia_images(): void
{
    check_ajax_referer('iss_register_image_suggestions', 'nonce');

    $post_id = absint($_POST['post_id'] ?? 0);
    if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Keine Berechtigung für diesen Register-Ort.'], 403);
    }

    $candidates = iss_register_search_image_candidates($post_id);
    if (is_wp_error($candidates)) {
        wp_send_json_error(['message' => $candidates->get_error_message()], 500);
    }

    iss_register_set_image_candidates($post_id, $candidates);

    wp_send_json_success([
        'html' => iss_register_render_image_candidates_html($post_id),
        'count' => count($candidates),
        'message' => count($candidates) > 0
            ? count($candidates) . ' Bildvorschläge gespeichert.'
            : 'Keine Bildvorschläge gefunden.',
    ]);
}

add_action('wp_ajax_iss_register_search_wikimedia_images', 'iss_register_ajax_search_wikimedia_images');

function iss_register_ajax_import_image_candidate(): void
{
    check_ajax_referer('iss_register_image_suggestions', 'nonce');

    $post_id = absint($_POST['post_id'] ?? 0);
    $candidate_id = sanitize_key((string) ($_POST['candidate_id'] ?? ''));
    $target_field = sanitize_key((string) ($_POST['target_field'] ?? ''));

    if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Keine Berechtigung für diesen Register-Ort.'], 403);
    }

    $result = iss_register_import_image_candidate_to_group($post_id, $candidate_id, $target_field);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 500);
    }

    wp_send_json_success([
        'html' => iss_register_render_image_candidates_html($post_id),
        'message' => 'Bild in ' . iss_register_get_image_candidate_field_label($target_field) . ' übernommen. Die Seite wird aktualisiert.',
    ]);
}

add_action('wp_ajax_iss_register_import_image_candidate', 'iss_register_ajax_import_image_candidate');

add_action('add_meta_boxes', function () {
    add_meta_box(
        'iss-register-image-suggestions',
        'Bildvorschläge',
        'iss_register_render_image_suggestions_meta_box',
        ISS_REGISTER_POST_TYPE,
        'normal',
        'default'
    );
});

function iss_register_admin_enqueue_image_suggestion_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== ISS_REGISTER_POST_TYPE) {
        return;
    }

    wp_enqueue_script(
        'iss-register-image-suggestions-admin',
        ISS_REGISTER_URL . 'assets/js/register-image-suggestions-admin.js',
        [],
        ISS_REGISTER_VERSION,
        true
    );
    wp_localize_script('iss-register-image-suggestions-admin', 'issRegisterImageSuggestionsAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('iss_register_image_suggestions'),
    ]);
    wp_enqueue_style(
        'iss-register-image-suggestions-admin',
        ISS_REGISTER_URL . 'assets/css/register-image-suggestions-admin.css',
        [],
        ISS_REGISTER_VERSION
    );
}

add_action('admin_enqueue_scripts', 'iss_register_admin_enqueue_image_suggestion_assets');
