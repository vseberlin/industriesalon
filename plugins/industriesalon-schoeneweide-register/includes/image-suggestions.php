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
            'license' => sanitize_text_field((string) ($item['license'] ?? '')),
            'license_url' => esc_url_raw((string) ($item['license_url'] ?? '')),
            'author' => sanitize_text_field((string) ($item['author'] ?? '')),
            'credit' => sanitize_text_field((string) ($item['credit'] ?? '')),
            'description' => sanitize_textarea_field((string) ($item['description'] ?? '')),
            'year' => sanitize_text_field((string) ($item['year'] ?? '')),
            'distance_m' => isset($item['distance_m']) && $item['distance_m'] !== '' ? max(0, (int) $item['distance_m']) : '',
            'score' => isset($item['score']) ? (int) $item['score'] : 0,
            'can_import_locally' => !empty($item['can_import_locally']),
            'imported_attachment_id' => absint($item['imported_attachment_id'] ?? 0),
            'imported_targets' => $targets,
        ];
    }

    return array_values(array_filter($sanitized, static function (array $item): bool {
        return $item['candidate_id'] !== '' && $item['full_image_url'] !== '';
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

function iss_register_extract_wikimedia_metadata_value(array $extmetadata, string $key): string
{
    $value = (string) ($extmetadata[$key]['value'] ?? '');
    if ($value === '') {
        return '';
    }

    return trim(wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
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
        echo '<p class="iss-register-image-suggestions__empty">Noch keine Bildvorschläge gespeichert. Die Suche nutzt Titel, Adresse und vorhandene Koordinaten des Register-Orts.</p>';
        echo '</div>';
        return (string) ob_get_clean();
    }

    foreach ($candidates as $candidate) {
        $imported_targets = isset($candidate['imported_targets']) && is_array($candidate['imported_targets']) ? $candidate['imported_targets'] : [];
        $target_labels = array_map('iss_register_get_image_candidate_field_label', $imported_targets);

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
        echo '<strong>Wikimedia Commons</strong>';
        if ($candidate['distance_m'] !== '') {
            echo '<span>' . esc_html((string) $candidate['distance_m']) . ' m entfernt</span>';
        }
        if (!empty($candidate['license'])) {
            echo '<span>' . esc_html((string) $candidate['license']) . '</span>';
        }
        echo '</div>';
        echo '<h4 class="iss-register-image-suggestion-card__title">' . esc_html((string) $candidate['title']) . '</h4>';
        echo '<p class="iss-register-image-suggestion-card__details">';
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
            echo '<a href="' . esc_url((string) $candidate['page_url']) . '" target="_blank" rel="noreferrer">Dateiseite öffnen</a>';
        }
        echo '</p>';

        if ($target_labels) {
            echo '<p class="iss-register-image-suggestion-card__imported">Bereits importiert in: ' . esc_html(implode(', ', $target_labels)) . '</p>';
        }

        echo '<div class="iss-register-image-suggestion-card__actions">';
        foreach (iss_register_allowed_candidate_target_fields() as $target_field) {
            echo '<button type="button" class="button iss-register-image-suggestion-card__import" data-post-id="' . esc_attr((string) $post_id) . '" data-candidate-id="' . esc_attr((string) $candidate['candidate_id']) . '" data-target-field="' . esc_attr($target_field) . '">Als ' . esc_html(iss_register_get_image_candidate_field_label($target_field)) . ' importieren</button>';
        }
        echo '</div>';
        echo '</div>';
        echo '</article>';
    }

    echo '</div>';

    return (string) ob_get_clean();
}

function iss_register_render_image_suggestions_meta_box(WP_Post $post): void
{
    echo '<div class="iss-register-image-suggestions" data-post-id="' . esc_attr((string) $post->ID) . '">';
    echo '<p>Lokale Bildvorschläge aus <strong>Wikimedia Commons</strong>. Die Suche bleibt lokal gespeichert, importiert aber erst nach redaktioneller Auswahl in die bestehenden Bildgruppen.</p>';
    echo '<div class="iss-register-image-suggestions__dynamic">';
    echo iss_register_render_image_candidates_html((int) $post->ID);
    echo '</div>';
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

    $candidates = iss_register_search_wikimedia_candidates($post_id);
    if (is_wp_error($candidates)) {
        wp_send_json_error(['message' => $candidates->get_error_message()], 500);
    }

    iss_register_set_image_candidates($post_id, $candidates);

    wp_send_json_success([
        'html' => iss_register_render_image_candidates_html($post_id),
        'count' => count($candidates),
        'message' => count($candidates) > 0
            ? count($candidates) . ' Wikimedia-Bildvorschläge gespeichert.'
            : 'Keine Wikimedia-Bildvorschläge gefunden.',
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
