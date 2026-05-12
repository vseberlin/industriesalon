<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_get_suggestion_post_types(): array
{
    return [
        ISS_WF_IMPORT_POST_TYPE,
        ISS_WF_IMPORT_OBJECT_POST_TYPE,
    ];
}

function iss_wf_import_is_suggestion_supported_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_wf_import_get_suggestion_post_types(), true);
}

function iss_wf_import_sanitize_place_suggestion(array $item): ?array
{
    $place_id = absint($item['place_id'] ?? 0);
    if ($place_id <= 0 || !get_post($place_id)) {
        return null;
    }

    $score = max(0, min(1000, absint($item['score'] ?? 0)));
    $reasons = [];
    foreach ((array) ($item['reasons'] ?? []) as $reason) {
        $reason = sanitize_text_field((string) $reason);
        if ($reason !== '') {
            $reasons[] = $reason;
        }
    }

    if (!$reasons) {
        return null;
    }

    $matched_terms = [];
    foreach ((array) ($item['matched_terms'] ?? []) as $term) {
        $term = sanitize_text_field((string) $term);
        if ($term !== '') {
            $matched_terms[] = $term;
        }
    }

    $confidence = sanitize_key((string) ($item['confidence'] ?? ''));
    if (!in_array($confidence, ['low', 'medium', 'high'], true)) {
        $confidence = 'medium';
    }

    return [
        'place_id' => $place_id,
        'score' => $score,
        'confidence' => $confidence,
        'reasons' => array_values(array_unique($reasons)),
        'matched_terms' => array_values(array_unique($matched_terms)),
    ];
}

function iss_wf_import_sanitize_place_suggestions($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $suggestion = iss_wf_import_sanitize_place_suggestion($item);
        if ($suggestion) {
            $clean[] = $suggestion;
        }
    }

    usort($clean, static function (array $a, array $b): int {
        if ($a['score'] === $b['score']) {
            return $a['place_id'] <=> $b['place_id'];
        }

        return $b['score'] <=> $a['score'];
    });

    return array_values($clean);
}

function iss_wf_import_normalize_text(string $value): string
{
    $value = remove_accents($value);
    $value = strtolower($value);
    $value = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $value);
    $value = preg_replace('/[\/|+_,.;:()\[\]{}"“”„\'`´’\-]+/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', (string) $value);

    return trim((string) $value);
}

function iss_wf_import_phrase_markers(): array
{
    return [
        'werk',
        'fabrik',
        'campus',
        'industriesalon',
        'museum',
        'atelier',
        'ateliers',
        'villa',
        'ufer',
        'halle',
        'hallen',
        'brauerei',
        'bahnhof',
        'behrens',
        'reinbeck',
        'wilhelminenhof',
        'edison',
        'rathenau',
        'nalepa',
        'oberspree',
        'schoneweide',
        'strasse',
        'str',
    ];
}

function iss_wf_import_strong_alias_markers(): array
{
    return [
        'aeg',
        'tro',
        'htw',
        'kwo',
        'fao',
        'behrens',
        'rathenau',
        'reinbeck',
        'wilhelminenhof',
        'nalepa',
        'ostend',
        'kabelwerk',
        'transformatorenwerk',
        'reinbeckstrasse',
        'nalepastrasse',
    ];
}

function iss_wf_import_alias_stopwords(): array
{
    return [
        'wf',
        'ddr',
        'veb',
        'gmbh',
        'ag',
        'ev',
        'e v',
        'land',
        'berlin',
        'bim',
        'geplant',
        'unbekannt',
        'eigentumer unbekannt',
        'eigentuemer unbekannt',
        'projekt',
    ];
}

function iss_wf_import_phrase_contains_marker(string $phrase, array $markers): bool
{
    $phrase = iss_wf_import_normalize_text($phrase);
    if ($phrase === '') {
        return false;
    }

    foreach ($markers as $marker) {
        $marker = iss_wf_import_normalize_text((string) $marker);
        if ($marker === '') {
            continue;
        }

        if (preg_match('/\b' . preg_quote($marker, '/') . '\b/u', $phrase)) {
            return true;
        }
    }

    return false;
}

function iss_wf_import_phrase_has_marker(string $phrase): bool
{
    return iss_wf_import_phrase_contains_marker($phrase, iss_wf_import_phrase_markers());
}

function iss_wf_import_address_is_specific(string $address): bool
{
    $normalized = iss_wf_import_normalize_text($address);
    if ($normalized === '') {
        return false;
    }

    if (preg_match('/\d/', $normalized)) {
        return true;
    }

    return (bool) preg_match('/\b(strasse|str|ufer|allee|weg|platz|bau|gebaude|gebäude|halle|haus|hof)\b/u', $normalized);
}

function iss_wf_import_is_planning_segment(string $normalized_segment): bool
{
    $normalized_segment = iss_wf_import_normalize_text($normalized_segment);
    if ($normalized_segment === '') {
        return false;
    }

    $markers = [
        'potenziell',
        'potenzielle',
        'potenzial',
        'potenzialflache',
        'geplant',
        'rahmenplan',
        'massnahme',
        'standort offen',
        'vorgeschichte',
        'konzept',
        'studie',
        'oder andere bestandsbauten',
    ];

    foreach ($markers as $marker) {
        if (str_contains($normalized_segment, $marker)) {
            return true;
        }
    }

    return false;
}

function iss_wf_import_add_phrase(array &$bucket, string $phrase, string $source, int $weight, bool $strong): void
{
    $phrase = iss_wf_import_normalize_text($phrase);
    if ($phrase === '' || strlen($phrase) < 4) {
        return;
    }

    if (!$strong && !iss_wf_import_phrase_has_marker($phrase) && !preg_match('/\d/', $phrase)) {
        return;
    }

    if (!isset($bucket[$phrase]) || $bucket[$phrase]['weight'] < $weight) {
        $bucket[$phrase] = [
            'phrase' => $phrase,
            'source' => $source,
            'weight' => $weight,
            'strong' => $strong,
        ];
    }
}

function iss_wf_import_phrase_word_count(string $phrase): int
{
    $words = preg_split('/\s+/u', trim(iss_wf_import_normalize_text($phrase)));
    if (!is_array($words)) {
        return 0;
    }

    $words = array_values(array_filter($words, static function ($word): bool {
        return $word !== '';
    }));

    return count($words);
}

function iss_wf_import_is_specific_match_phrase(string $phrase): bool
{
    $phrase = iss_wf_import_normalize_text($phrase);
    if ($phrase === '') {
        return false;
    }

    if (preg_match('/\d/', $phrase)) {
        return true;
    }

    if (iss_wf_import_phrase_contains_marker($phrase, iss_wf_import_strong_alias_markers()) && iss_wf_import_phrase_word_count($phrase) >= 2) {
        return true;
    }

    return false;
}

function iss_wf_import_detect_suggestion_confidence(string $post_type, int $score, array $matched_terms, array $matched_sources): string
{
    $has_primary_match = count(array_intersect($matched_sources, ['Titel', 'Alias im Titel', 'Adresse', 'Straße', 'Register-Tag', 'Abkürzung'])) > 0;
    $specific_matches = 0;

    foreach ($matched_terms as $term) {
        if (iss_wf_import_is_specific_match_phrase((string) $term)) {
            $specific_matches++;
        }
    }

    if ($score >= 85 || $has_primary_match) {
        return 'high';
    }

    if ($post_type === ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        if ($score >= 55 || $specific_matches >= 2) {
            return 'medium';
        }

        return 'low';
    }

    return $score >= 70 ? 'high' : 'medium';
}

function iss_wf_import_extract_title_variants(WP_Post $place, array &$bucket): void
{
    $title = trim((string) $place->post_title);
    if ($title === '') {
        return;
    }

    iss_wf_import_add_phrase($bucket, $title, 'Titel', 90, true);

    $base = preg_split('/\s*[\(\/]\s*/u', $title);
    if (is_array($base) && !empty($base[0])) {
        iss_wf_import_add_phrase($bucket, (string) $base[0], 'Titel', 80, true);
    }

    if (preg_match_all('/\(([^)]+)\)/u', $title, $matches)) {
        foreach ((array) ($matches[1] ?? []) as $alias) {
            $alias = trim((string) $alias);
            if ($alias === '') {
                continue;
            }

            $normalized = iss_wf_import_normalize_text($alias);
            if (in_array($normalized, iss_wf_import_alias_stopwords(), true)) {
                continue;
            }

            $looks_like_acronym = (bool) preg_match('/^[A-ZÄÖÜ0-9\-]{2,8}$/u', $alias);
            if (!$looks_like_acronym && !iss_wf_import_phrase_has_marker($normalized) && !preg_match('/\d/', $normalized)) {
                continue;
            }

            iss_wf_import_add_phrase($bucket, $alias, 'Alias im Titel', 65, $looks_like_acronym || strlen($normalized) >= 6);
        }
    }
}

function iss_wf_import_extract_meta_variants(int $place_id, array &$bucket): void
{
    $address = trim((string) get_post_meta($place_id, 'address', true));
    if ($address !== '' && iss_wf_import_address_is_specific($address)) {
        iss_wf_import_add_phrase($bucket, $address, 'Adresse', 75, true);

        if (preg_match('/([A-Za-zÄÖÜäöüß\.\-]+(?:straße|str\.?|ufer|allee|weg|platz))\s*([0-9]+[A-Za-z]?)/ui', $address, $match)) {
            iss_wf_import_add_phrase($bucket, trim($match[1] . ' ' . $match[2]), 'Adresse', 80, true);
        }

        if (preg_match('/([A-Za-zÄÖÜäöüß\.\-]+(?:straße|str\.?|ufer|allee|weg|platz))/ui', $address, $match)) {
            iss_wf_import_add_phrase($bucket, trim((string) $match[1]), 'Straße', 30, false);
        }
    }

    $tag_values = get_post_meta($place_id, 'tags', true);
    foreach ((array) $tag_values as $tag) {
        iss_wf_import_add_phrase($bucket, (string) $tag, 'Register-Tag', 65, true);
    }

    foreach (['previous_use', 'history_short', 'current_use'] as $field) {
        $value = trim(wp_strip_all_tags((string) get_post_meta($place_id, $field, true)));
        if ($value === '') {
            continue;
        }

        $segments = preg_split('/[.!?;]+/u', $value);
        if (!is_array($segments)) {
            continue;
        }

        foreach ($segments as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '') {
                continue;
            }

            $normalized = iss_wf_import_normalize_text($segment);
            if (iss_wf_import_is_planning_segment($normalized)) {
                continue;
            }

            if (!iss_wf_import_phrase_has_marker($normalized) && !preg_match('/\b(htw|tro|aeg|kwo|be u)\b/u', $normalized)) {
                continue;
            }

            iss_wf_import_add_phrase($bucket, $segment, 'Historischer Hinweis', 55, true);

            if ($field !== 'current_use') {
                foreach (iss_wf_import_extract_historical_ngrams($normalized) as $ngram) {
                    iss_wf_import_add_phrase($bucket, $ngram, 'Historischer Begriff', 60, true);
                }
            }

            if (preg_match_all('/\(([A-ZÄÖÜ0-9\-]{2,8})\)/u', $segment, $matches)) {
                foreach ((array) ($matches[1] ?? []) as $alias) {
                    $normalized_alias = iss_wf_import_normalize_text((string) $alias);
                    if (in_array($normalized_alias, iss_wf_import_alias_stopwords(), true)) {
                        continue;
                    }
                    iss_wf_import_add_phrase($bucket, (string) $alias, 'Abkürzung', 35, false);
                }
            }
        }
    }
}

function iss_wf_import_extract_historical_ngrams(string $normalized_segment): array
{
    $words = preg_split('/\s+/u', trim($normalized_segment));
    if (!is_array($words) || count($words) < 2) {
        return [];
    }

    $markers = iss_wf_import_strong_alias_markers();
    $phrases = [];
    $word_count = count($words);

    for ($start = 0; $start < $word_count; $start++) {
        for ($length = 2; $length <= 4; $length++) {
            $slice = array_slice($words, $start, $length);
            if (count($slice) < 2) {
                continue;
            }

            $phrase = trim(implode(' ', $slice));
            if ($phrase === '' || strlen($phrase) < 8) {
                continue;
            }

            if (!iss_wf_import_phrase_contains_marker($phrase, $markers)) {
                continue;
            }

            if (in_array($phrase, iss_wf_import_alias_stopwords(), true)) {
                continue;
            }

            $phrases[] = $phrase;
        }
    }

    return array_values(array_unique($phrases));
}

function iss_wf_import_build_place_match_index(): array
{
    static $index = null;

    if (is_array($index)) {
        return $index;
    }

    $index = [];
    $places = get_posts([
        'post_type' => 'register_place',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    foreach ($places as $place) {
        if (!$place instanceof WP_Post) {
            continue;
        }

        $phrases = [];
        iss_wf_import_extract_title_variants($place, $phrases);
        iss_wf_import_extract_meta_variants((int) $place->ID, $phrases);

        if (!$phrases) {
            continue;
        }

        $index[(int) $place->ID] = [
            'post' => $place,
            'phrases' => array_values($phrases),
        ];
    }

    return $index;
}

function iss_wf_import_build_archivbeitrag_corpus(WP_Post $post): array
{
    $title = trim((string) $post->post_title);
    $content = trim(wp_strip_all_tags((string) $post->post_content));
    $excerpt = trim((string) $post->post_excerpt);
    $categories = wp_get_object_terms($post->ID, ISS_WF_IMPORT_CATEGORY_TAXONOMY, ['fields' => 'names']);
    $tags = wp_get_object_terms($post->ID, ISS_WF_IMPORT_TAG_TAXONOMY, ['fields' => 'names']);

    return [
        'title' => ' ' . iss_wf_import_normalize_text($title) . ' ',
        'body' => ' ' . iss_wf_import_normalize_text(implode(' ', array_filter([
            $title,
            $excerpt,
            $content,
            implode(' ', is_array($categories) ? $categories : []),
            implode(' ', is_array($tags) ? $tags : []),
        ]))) . ' ',
    ];
}

function iss_wf_import_extract_named_ref_names(array $items): array
{
    $names = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = trim((string) ($item['name'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

function iss_wf_import_build_archivobjekt_corpus(WP_Post $post): array
{
    $title = trim((string) $post->post_title);
    $content = trim(wp_strip_all_tags((string) $post->post_content));
    $excerpt = trim((string) $post->post_excerpt);
    $projection = iss_wf_import_get_object_service()->get_projection_for_post((int) $post->ID);
    $relation_projection = iss_wf_import_get_relation_service()->get_projection_for_post((int) $post->ID);
    $inventory = is_array($projection)
        ? trim((string) ($projection['inventory_number'] ?? ''))
        : trim((string) get_post_meta($post->ID, ISS_WF_IMPORT_OBJECT_INVENTORY_META, true));
    $object_type = is_array($projection)
        ? trim((string) ($projection['object_type_label'] ?? ''))
        : trim((string) get_post_meta($post->ID, ISS_WF_IMPORT_OBJECT_TYPE_META, true));
    $tags = is_array($relation_projection)
        ? iss_wf_import_extract_named_ref_names((array) ($relation_projection['tags'] ?? []))
        : iss_wf_import_extract_named_ref_names((array) get_post_meta($post->ID, ISS_WF_IMPORT_OBJECT_TAGS_META, true));
    $collections = is_array($relation_projection)
        ? iss_wf_import_extract_named_ref_names((array) ($relation_projection['collections'] ?? []))
        : iss_wf_import_extract_named_ref_names((array) get_post_meta($post->ID, ISS_WF_IMPORT_OBJECT_COLLECTIONS_META, true));
    $series = is_array($relation_projection)
        ? iss_wf_import_extract_named_ref_names((array) ($relation_projection['series'] ?? []))
        : iss_wf_import_extract_named_ref_names((array) get_post_meta($post->ID, ISS_WF_IMPORT_OBJECT_SERIES_META, true));

    return [
        'title' => ' ' . iss_wf_import_normalize_text($title) . ' ',
        'body' => ' ' . iss_wf_import_normalize_text(implode(' ', array_filter([
            $title,
            $excerpt,
            $content,
            $inventory,
            $object_type,
            implode(' ', $tags),
            implode(' ', $collections),
            implode(' ', $series),
        ]))) . ' ',
    ];
}

function iss_wf_import_build_suggestion_corpus(WP_Post $post): array
{
    if ($post->post_type === ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return iss_wf_import_build_archivobjekt_corpus($post);
    }

    return iss_wf_import_build_archivbeitrag_corpus($post);
}

function iss_wf_import_phrase_in_corpus(string $phrase, string $haystack): bool
{
    $phrase = trim(iss_wf_import_normalize_text($phrase));
    if ($phrase === '') {
        return false;
    }

    return str_contains($haystack, ' ' . $phrase . ' ');
}

function iss_wf_import_generate_place_suggestions(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_wf_import_is_suggestion_supported_post_type($post->post_type)) {
        return [];
    }

    $index = iss_wf_import_build_place_match_index();
    if (!$index) {
        return [];
    }

    $corpus = iss_wf_import_build_suggestion_corpus($post);
    $suggestions = [];
    $is_archive_object = $post->post_type === ISS_WF_IMPORT_OBJECT_POST_TYPE;

    foreach ($index as $place_id => $entry) {
        $score = 0;
        $reasons = [];
        $matched_terms = [];
        $matched_sources = [];

        foreach ((array) ($entry['phrases'] ?? []) as $phrase_entry) {
            $phrase = (string) ($phrase_entry['phrase'] ?? '');
            if ($phrase === '') {
                continue;
            }

            $source = (string) ($phrase_entry['source'] ?? 'Treffer');
            $weight = (int) ($phrase_entry['weight'] ?? 0);
            $strong = !empty($phrase_entry['strong']);

            if (iss_wf_import_phrase_in_corpus($phrase, $corpus['title'])) {
                $score += $weight;
                $reasons[] = $source . ': "' . $phrase . '" im Titel';
                $matched_terms[] = $phrase;
                $matched_sources[] = $source;
                continue;
            }

            if (iss_wf_import_phrase_in_corpus($phrase, $corpus['body'])) {
                $score += $strong ? max(20, (int) floor($weight * 0.6)) : max(10, (int) floor($weight * 0.5));
                $reasons[] = $source . ': "' . $phrase . '" im Inhalt';
                $matched_terms[] = $phrase;
                $matched_sources[] = $source;
            }
        }

        $matched_terms = array_values(array_unique($matched_terms));
        $reasons = array_values(array_unique($reasons));
        $matched_sources = array_values(array_unique($matched_sources));
        $specific_match_count = 0;

        foreach ($matched_terms as $term) {
            if (iss_wf_import_is_specific_match_phrase((string) $term)) {
                $specific_match_count++;
            }
        }

        if ($is_archive_object) {
            if ($score < 30 || $specific_match_count < 1) {
                continue;
            }
        } elseif ($score < 45) {
            continue;
        } elseif ($score < 70 && count($matched_terms) < 2) {
            continue;
        }

        $suggestions[] = [
            'place_id' => (int) $place_id,
            'score' => $score,
            'confidence' => iss_wf_import_detect_suggestion_confidence($post->post_type, $score, $matched_terms, $matched_sources),
            'reasons' => array_slice($reasons, 0, 4),
            'matched_terms' => array_slice($matched_terms, 0, 6),
        ];
    }

    return iss_wf_import_sanitize_place_suggestions($suggestions);
}

function iss_wf_import_refresh_suggestions_for_post(int $post_id): array
{
    $suggestions = iss_wf_import_generate_place_suggestions($post_id);

    if ($suggestions) {
        update_post_meta($post_id, ISS_WF_IMPORT_PLACE_SUGGESTIONS_META, $suggestions);
    } else {
        delete_post_meta($post_id, ISS_WF_IMPORT_PLACE_SUGGESTIONS_META);
    }

    update_post_meta($post_id, ISS_WF_IMPORT_PLACE_SUGGESTED_AT_META, current_time('mysql', true));

    return $suggestions;
}

function iss_wf_import_apply_place_suggestions(int $post_id, array $place_ids): array
{
    if (!function_exists('iss_relations_update_post_relations') || !function_exists('iss_relations_sync_post_terms')) {
        return [];
    }

    $selected_place_ids = array_values(array_unique(array_filter(array_map('absint', $place_ids))));
    if (!$selected_place_ids) {
        return [];
    }

    $existing_relations = function_exists('iss_relations_get_post_relations')
        ? iss_relations_get_post_relations($post_id)
        : [];

    $existing_by_place = [];
    foreach ($existing_relations as $relation) {
        $existing_by_place[(int) ($relation['place_id'] ?? 0)] = $relation;
    }

    $suggestions = (array) get_post_meta($post_id, ISS_WF_IMPORT_PLACE_SUGGESTIONS_META, true);
    $suggestions_by_place = [];
    foreach ($suggestions as $suggestion) {
        if (!is_array($suggestion)) {
            continue;
        }
        $suggestions_by_place[(int) ($suggestion['place_id'] ?? 0)] = $suggestion;
    }

    foreach ($selected_place_ids as $place_id) {
        if (isset($existing_by_place[$place_id])) {
            continue;
        }

        $score = (int) (($suggestions_by_place[$place_id]['score'] ?? 0));
        $existing_relations[] = [
            'place_id' => $place_id,
            'role' => 'subject',
            'weight' => max(1, min(100, $score)),
            'label' => '',
        ];
    }

    iss_relations_update_post_relations($post_id, $existing_relations);
    iss_relations_sync_post_terms($post_id);

    return iss_relations_get_post_relations($post_id);
}

function iss_wf_import_generate_suggestions(array $options = []): array
{
    $post_id = absint($options['post_id'] ?? 0);
    $limit = absint($options['limit'] ?? 0);
    $force = !empty($options['force']);
    $post_type = sanitize_key((string) ($options['post_type'] ?? ISS_WF_IMPORT_POST_TYPE));

    if ($post_id > 0) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || !iss_wf_import_is_suggestion_supported_post_type($post->post_type)) {
            return [
                'processed' => 0,
                'with_suggestions' => 0,
                'without_suggestions' => 0,
                'skipped' => 0,
                'errors' => [sprintf('Post %d is not a supported suggestion target.', $post_id)],
            ];
        }

        $post_type = $post->post_type;
    }

    if (!iss_wf_import_is_suggestion_supported_post_type($post_type)) {
        return [
            'processed' => 0,
            'with_suggestions' => 0,
            'without_suggestions' => 0,
            'skipped' => 0,
            'errors' => [sprintf('Unsupported post type "%s" for place suggestions.', $post_type)],
        ];
    }

    $query = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $post_id > 0 ? 1 : -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => true,
    ];

    if ($post_id > 0) {
        $query['p'] = $post_id;
    }

    $posts = get_posts($query);
    $stats = [
        'processed' => 0,
        'with_suggestions' => 0,
        'without_suggestions' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        if (!$force && get_post_meta($post->ID, ISS_WF_IMPORT_PLACE_SUGGESTED_AT_META, true) !== '') {
            $stats['skipped']++;
            continue;
        }

        $suggestions = iss_wf_import_refresh_suggestions_for_post((int) $post->ID);
        $stats['processed']++;

        if ($suggestions) {
            $stats['with_suggestions']++;
        } else {
            $stats['without_suggestions']++;
        }

        if ($limit > 0 && $stats['processed'] >= $limit) {
            break;
        }
    }

    return $stats;
}
