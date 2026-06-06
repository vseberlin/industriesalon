<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Transcript bridge reads plugin-owned graph tables for candidate extraction.

function iss_graph_get_video_post_type(): string
{
    return defined('ISS_CONTENT_MODEL_VIDEO_POST_TYPE') ? ISS_CONTENT_MODEL_VIDEO_POST_TYPE : 'video';
}

function iss_graph_video_transcript_source_system(): string
{
    return 'video_transcript';
}

function iss_graph_video_transcript_review_source_system(): string
{
    return 'video_transcript_review';
}

function iss_graph_video_transcript_is_video_post(WP_Post $post): bool
{
    return (string) $post->post_type === iss_graph_get_video_post_type();
}

function iss_graph_video_transcript_get_status(int $post_id, bool $has_content): string
{
    $status = sanitize_key((string) get_post_meta($post_id, 'iss_video_transcript_status', true));
    if (function_exists('iss_content_model_resolve_video_transcript_status')) {
        return iss_content_model_resolve_video_transcript_status($status, $has_content);
    }

    if ($status === '' || $status === 'none') {
        return $has_content ? 'excerpt' : 'none';
    }

    return in_array($status, ['planned', 'excerpt', 'full'], true) ? $status : 'none';
}

function iss_graph_video_transcript_normalize_text(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    $value = wp_strip_all_tags($value);
    $value = remove_accents($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/[^\pL\pN]+/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', trim((string) $value));

    return is_string($value) ? $value : '';
}

function iss_graph_video_transcript_parse_timecode_seconds(string $value): int
{
    $parts = array_map('absint', explode(':', trim($value)));
    if (count($parts) === 2) {
        return ($parts[0] * 60) + $parts[1];
    }

    if (count($parts) === 3) {
        return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
    }

    return 0;
}

function iss_graph_video_transcript_timecode_id(string $timecode): string
{
    return 't-' . str_replace(':', '-', trim($timecode));
}

function iss_graph_video_transcript_extract_segments(int $post_id): array
{
    $raw_content = (string) get_post_field('post_content', $post_id);
    if (trim(wp_strip_all_tags($raw_content)) === '' && trim($raw_content) === '') {
        return [];
    }

    $rendered = do_blocks($raw_content);
    if (trim((string) $rendered) === '') {
        $rendered = $raw_content;
    }

    $chunks = [];
    if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', (string) $rendered, $matches)) {
        $chunks = $matches[1];
    }

    if (!$chunks) {
        $chunks = preg_split('/\n{2,}/', wp_strip_all_tags((string) $rendered)) ?: [];
    }

    $segments = [];
    foreach (array_values($chunks) as $index => $chunk) {
        $text = trim((string) wp_strip_all_tags((string) $chunk));
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = is_string($text) ? trim($text) : '';
        if ($text === '') {
            continue;
        }

        $timecode = '';
        $seconds = 0;
        if (preg_match('/^\s*\[?((?:\d{1,2}:)?\d{1,2}:\d{2})\]?\s*/', $text, $time_match)) {
            $timecode = (string) $time_match[1];
            $seconds = iss_graph_video_transcript_parse_timecode_seconds($timecode);
            $text = trim((string) preg_replace('/^\s*\[?' . preg_quote($timecode, '/') . '\]?\s*/', '', $text));
        }

        $segments[] = [
            'index' => $index,
            'timecode' => $timecode,
            'seconds' => $seconds,
            'anchor' => $timecode !== '' ? iss_graph_video_transcript_timecode_id($timecode) : '',
            'text' => $text,
            'normalized_text' => iss_graph_video_transcript_normalize_text($text),
        ];
    }

    return $segments;
}

function iss_graph_video_transcript_name_is_usable(string $name, string $name_type): bool
{
    $name = trim($name);
    if ($name === '') {
        return false;
    }

    $name_type = sanitize_key($name_type);
    $plain = preg_replace('/[^\pL\pN]/u', '', $name);
    $plain = is_string($plain) ? $plain : '';
    $length = function_exists('mb_strlen') ? mb_strlen($plain, 'UTF-8') : strlen($plain);
    if ($length >= 4) {
        return true;
    }

    return $name_type === 'abbreviation'
        && $length >= 2
        && preg_match('/^[A-ZÄÖÜ0-9]{2,6}$/u', $name) === 1;
}

function iss_graph_video_transcript_candidate_entity_is_usable(array $row): bool
{
    $entity_kind = (string) ($row['entity_kind'] ?? '');
    $source_system = sanitize_key((string) ($row['source_system'] ?? ''));
    $is_public = !empty($row['is_public']);
    $profile_post_id = absint($row['profile_post_id'] ?? 0);

    if ($source_system === 'entity_alias_backfill') {
        return false;
    }

    if ($entity_kind === 'place') {
        return $is_public;
    }

    if ($entity_kind === 'person') {
        return $is_public || $profile_post_id > 0;
    }

    return $entity_kind === 'organization';
}

function iss_graph_video_transcript_get_candidate_names(): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $name_types = (array) apply_filters('iss_graph_video_transcript_name_types', [
        'primary',
        'canonical',
        'official',
        'historical',
        'alternative',
        'abbreviation',
    ]);
    $entity_kinds = (array) apply_filters('iss_graph_video_transcript_entity_kinds', [
        'place',
        'person',
        'organization',
    ]);

    $name_types = array_values(array_filter(array_map('sanitize_key', $name_types)));
    $entity_kinds = array_values(array_filter(array_map([$service, 'normalize_entity_kind'], $entity_kinds)));
    if (!$name_types || !$entity_kinds) {
        return [];
    }

    $name_placeholders = implode(', ', array_fill(0, count($name_types), '%s'));
    $kind_placeholders = implode(', ', array_fill(0, count($entity_kinds), '%s'));
    $values = array_merge($entity_kinds, $name_types);
    $sql = "SELECT
            n.entity_id,
            n.name,
            n.name_type,
            n.source_system,
            e.entity_kind,
            e.display_title,
            e.profile_post_id,
            e.is_public
        FROM {$service->get_name_table_name()} n
        INNER JOIN {$service->get_entity_table_name()} e
            ON e.id = n.entity_id
        WHERE e.entity_kind IN ({$kind_placeholders})
          AND n.name_type IN ({$name_placeholders})
        ORDER BY LENGTH(n.name) DESC, n.is_primary DESC, n.position ASC, n.id ASC";

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder lists are built from sanitized fixed arrays above, then prepared with values.
    $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    if (!is_array($rows)) {
        return [];
    }

    $seen = [];
    $raw_candidates = [];
    foreach ($rows as $row) {
        $name = sanitize_text_field((string) ($row['name'] ?? ''));
        $name_type = sanitize_key((string) ($row['name_type'] ?? ''));
        if (!iss_graph_video_transcript_candidate_entity_is_usable($row)) {
            continue;
        }

        if (!iss_graph_video_transcript_name_is_usable($name, $name_type)) {
            continue;
        }

        $entity_id = (int) ($row['entity_id'] ?? 0);
        if ($entity_id <= 0) {
            continue;
        }

        $normalized = iss_graph_video_transcript_normalize_text($name);
        if ($normalized === '') {
            continue;
        }

        $key = $entity_id . ':' . $normalized;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $raw_candidates[] = [
            'entity_id' => $entity_id,
            'entity_kind' => (string) ($row['entity_kind'] ?? ''),
            'display_title' => sanitize_text_field((string) ($row['display_title'] ?? '')),
            'name' => $name,
            'name_type' => $name_type,
            'normalized_name' => $normalized,
        ];
    }

    $ambiguous = [];
    $groups = [];
    foreach ($raw_candidates as $candidate) {
        $group_key = (string) ($candidate['entity_kind'] ?? '') . ':' . (string) ($candidate['normalized_name'] ?? '');
        $groups[$group_key][(int) ($candidate['entity_id'] ?? 0)] = true;
    }

    foreach ($groups as $group_key => $entity_ids) {
        if (count($entity_ids) > 1) {
            $ambiguous[$group_key] = true;
        }
    }

    return array_values(array_filter($raw_candidates, static function (array $candidate) use ($ambiguous): bool {
        $group_key = (string) ($candidate['entity_kind'] ?? '') . ':' . (string) ($candidate['normalized_name'] ?? '');

        return !isset($ambiguous[$group_key]);
    }));
}

function iss_graph_video_transcript_contains_candidate(array $segment, array $candidate): bool
{
    $name = (string) ($candidate['name'] ?? '');
    $name_type = sanitize_key((string) ($candidate['name_type'] ?? ''));
    $normalized_name = (string) ($candidate['normalized_name'] ?? '');
    if ($name === '' || $normalized_name === '') {
        return false;
    }

    if ($name_type === 'abbreviation') {
        return preg_match('/(?<![\pL\pN])' . preg_quote($name, '/') . '(?![\pL\pN])/u', (string) ($segment['text'] ?? '')) === 1;
    }

    $haystack = ' ' . (string) ($segment['normalized_text'] ?? '') . ' ';

    return str_contains($haystack, ' ' . $normalized_name . ' ');
}

function iss_graph_video_transcript_candidate_confidence(array $candidate, string $transcript_status): int
{
    $name_type = sanitize_key((string) ($candidate['name_type'] ?? ''));
    $confidence = in_array($name_type, ['primary', 'canonical', 'official'], true) ? 80 : 70;
    if ($name_type === 'abbreviation') {
        $confidence = 65;
    }
    if ($transcript_status === 'full') {
        $confidence += 5;
    }

    return max(0, min(100, $confidence));
}

function iss_graph_video_transcript_snippet(string $text, string $name): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?: $text);
    if ($text === '') {
        return '';
    }

    $position = function_exists('mb_stripos') ? mb_stripos($text, $name, 0, 'UTF-8') : stripos($text, $name);
    if ($position === false) {
        return wp_trim_words($text, 28, '...');
    }

    $start = max(0, (int) $position - 80);
    $length = 180;
    $snippet = function_exists('mb_substr')
        ? mb_substr($text, $start, $length, 'UTF-8')
        : substr($text, $start, $length);

    return trim((string) $snippet);
}

function iss_graph_video_transcript_collect_mentions(int $post_id, array $segments, string $transcript_status): array
{
    $candidate_names = iss_graph_video_transcript_get_candidate_names();
    if (!$candidate_names || !$segments) {
        return [];
    }

    $max_mentions_per_entity = (int) apply_filters('iss_graph_video_transcript_max_mentions_per_entity', 5);
    $max_entities = (int) apply_filters('iss_graph_video_transcript_max_entities_per_video', 100);
    $mentions = [];

    foreach ($segments as $segment) {
        foreach ($candidate_names as $candidate) {
            if (!iss_graph_video_transcript_contains_candidate($segment, $candidate)) {
                continue;
            }

            $entity_id = (int) ($candidate['entity_id'] ?? 0);
            if ($entity_id <= 0) {
                continue;
            }

            if (!isset($mentions[$entity_id])) {
                if (count($mentions) >= $max_entities) {
                    continue;
                }

                $mentions[$entity_id] = [
                    'entity_id' => $entity_id,
                    'entity_kind' => (string) ($candidate['entity_kind'] ?? ''),
                    'display_title' => (string) ($candidate['display_title'] ?? ''),
                    'matched_names' => [],
                    'segments' => [],
                    'confidence' => iss_graph_video_transcript_candidate_confidence($candidate, $transcript_status),
                ];
            }

            $mentions[$entity_id]['matched_names'][(string) ($candidate['name'] ?? '')] = true;
            $mentions[$entity_id]['confidence'] = max(
                (int) ($mentions[$entity_id]['confidence'] ?? 0),
                iss_graph_video_transcript_candidate_confidence($candidate, $transcript_status)
            );

            if (count($mentions[$entity_id]['segments']) >= $max_mentions_per_entity) {
                continue;
            }

            $segment_key = ((string) ($segment['timecode'] ?? '')) . ':' . ((int) ($segment['index'] ?? 0));
            if (isset($mentions[$entity_id]['segments'][$segment_key])) {
                continue;
            }

            $mentions[$entity_id]['segments'][$segment_key] = [
                'timecode' => (string) ($segment['timecode'] ?? ''),
                'seconds' => (int) ($segment['seconds'] ?? 0),
                'anchor' => (string) ($segment['anchor'] ?? ''),
                'text' => iss_graph_video_transcript_snippet((string) ($segment['text'] ?? ''), (string) ($candidate['name'] ?? '')),
            ];
        }
    }

    foreach ($mentions as &$mention) {
        $mention['matched_names'] = array_values(array_filter(array_keys((array) ($mention['matched_names'] ?? []))));
        $mention['segments'] = array_values((array) ($mention['segments'] ?? []));
    }
    unset($mention);

    return array_values($mentions);
}

function iss_graph_video_transcript_build_evidence_rows(int $post_id, array $mentions, string $transcript_status): array
{
    $permalink = (string) get_permalink($post_id);
    $rows = [];
    foreach ($mentions as $mention) {
        if (!is_array($mention)) {
            continue;
        }

        $target_id = (int) ($mention['entity_id'] ?? 0);
        if ($target_id <= 0) {
            continue;
        }

        $segments = array_values((array) ($mention['segments'] ?? []));
        $first_segment = is_array($segments[0] ?? null) ? $segments[0] : [];
        $anchor = sanitize_title((string) ($first_segment['anchor'] ?? ''));
        $source_url = $permalink !== '' && $anchor !== '' ? $permalink . '#' . $anchor : $permalink;
        $note = wp_json_encode([
            'candidate_type' => 'video_transcript_entity_mention',
            'video_post_id' => $post_id,
            'target_entity_kind' => (string) ($mention['entity_kind'] ?? ''),
            'target_title' => (string) ($mention['display_title'] ?? ''),
            'transcript_status' => $transcript_status,
            'matched_names' => array_values((array) ($mention['matched_names'] ?? [])),
            'segments' => $segments,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $rows[] = [
            'target_kind' => 'entity',
            'target_id' => $target_id,
            'source_url' => $source_url,
            'label' => sprintf('Transcript mention: %s', (string) ($mention['display_title'] ?? ('Entity ' . $target_id))),
            'note' => is_string($note) ? $note : '',
            'confidence' => (int) ($mention['confidence'] ?? 0),
            'status' => 'pending',
        ];
    }

    return $rows;
}

function iss_graph_video_transcript_get_reviewed_target_ids(int $entity_id): array
{
    if ($entity_id <= 0) {
        return [];
    }

    $review_rows = iss_graph_get_service()->get_evidence_refs_for_entity($entity_id, [
        'source_system' => iss_graph_video_transcript_review_source_system(),
        'limit' => 500,
    ]);

    $target_ids = [];
    foreach ($review_rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $target_id = absint($row['target_id'] ?? 0);
        $status = sanitize_key((string) ($row['status'] ?? ''));
        if ($target_id > 0 && in_array($status, ['accepted', 'rejected'], true)) {
            $target_ids[$target_id] = true;
        }
    }

    return $target_ids;
}

function iss_graph_video_transcript_filter_reviewed_mentions(array $mentions, int $entity_id): array
{
    $reviewed_target_ids = iss_graph_video_transcript_get_reviewed_target_ids($entity_id);
    if (!$reviewed_target_ids) {
        return $mentions;
    }

    return array_values(array_filter($mentions, static function ($mention) use ($reviewed_target_ids): bool {
        if (!is_array($mention)) {
            return false;
        }

        $target_id = absint($mention['entity_id'] ?? 0);

        return $target_id > 0 && !isset($reviewed_target_ids[$target_id]);
    }));
}

function iss_graph_sync_video_transcript_mentions(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_graph_video_transcript_is_video_post($post)) {
        return [
            'synced' => false,
            'entity_id' => 0,
            'mentions' => 0,
        ];
    }

    $entity = iss_graph_get_service()->find_entity_by_post('video', $post_id);
    if (!$entity && function_exists('iss_graph_sync_public_content_entity')) {
        $entity = iss_graph_sync_public_content_entity($post_id);
    }

    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0) {
        return [
            'synced' => false,
            'entity_id' => 0,
            'mentions' => 0,
        ];
    }

    $segments = iss_graph_video_transcript_extract_segments($post_id);
    $transcript_status = iss_graph_video_transcript_get_status($post_id, $segments !== []);
    $mentions = in_array($transcript_status, ['excerpt', 'full'], true)
        ? iss_graph_video_transcript_collect_mentions($post_id, $segments, $transcript_status)
        : [];
    $mentions = iss_graph_video_transcript_filter_reviewed_mentions($mentions, $entity_id);
    $rows = iss_graph_video_transcript_build_evidence_rows($post_id, $mentions, $transcript_status);

    iss_graph_get_service()->replace_entity_evidence_refs_for_source(
        $entity_id,
        iss_graph_video_transcript_source_system(),
        $rows,
        'post:' . $post_id
    );

    return [
        'synced' => true,
        'entity_id' => $entity_id,
        'mentions' => count($rows),
        'segments' => count($segments),
        'status' => $transcript_status,
    ];
}

function iss_graph_video_transcript_decode_evidence_note(array $evidence): array
{
    $note = (string) ($evidence['note'] ?? '');
    $decoded = $note !== '' ? json_decode($note, true) : null;

    return is_array($decoded) ? $decoded : [];
}

function iss_graph_video_transcript_get_pending_evidence_for_video(int $post_id): array
{
    $entity = iss_graph_get_service()->find_entity_by_post('video', $post_id);
    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0) {
        return [];
    }

    $rows = iss_graph_get_service()->get_evidence_refs_for_entity($entity_id, [
        'source_system' => iss_graph_video_transcript_source_system(),
        'status' => 'pending',
        'limit' => 100,
    ]);

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $target_id = absint($row['target_id'] ?? 0);
        $target = $target_id > 0 ? iss_graph_get_service()->get_entity_by_id($target_id) : null;
        if (!$target) {
            continue;
        }

        $row['target'] = $target;
        $row['decoded_note'] = iss_graph_video_transcript_decode_evidence_note($row);
        $out[] = $row;
    }

    return $out;
}

function iss_graph_video_transcript_get_review_rows_for_video(int $post_id): array
{
    $entity = iss_graph_get_service()->find_entity_by_post('video', $post_id);
    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0) {
        return [];
    }

    return iss_graph_get_service()->get_evidence_refs_for_entity($entity_id, [
        'source_system' => iss_graph_video_transcript_review_source_system(),
        'limit' => 100,
    ]);
}

function iss_graph_video_transcript_find_evidence_by_id(int $post_id, int $evidence_id): ?array
{
    if ($post_id <= 0 || $evidence_id <= 0) {
        return null;
    }

    $entity = iss_graph_get_service()->find_entity_by_post('video', $post_id);
    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0) {
        return null;
    }

    $evidence = iss_graph_get_service()->get_evidence_ref_by_id($evidence_id);
    if (
        !$evidence
        || (int) ($evidence['entity_id'] ?? 0) !== $entity_id
        || (string) ($evidence['source_system'] ?? '') !== iss_graph_video_transcript_source_system()
        || (string) ($evidence['status'] ?? '') !== 'pending'
    ) {
        return null;
    }

    return $evidence;
}

function iss_graph_video_transcript_upsert_review_decision(int $entity_id, int $target_id, string $status, array $source_evidence = []): void
{
    if ($entity_id <= 0 || $target_id <= 0) {
        return;
    }

    $status = sanitize_key($status);
    if (!in_array($status, ['accepted', 'rejected'], true)) {
        return;
    }

    $existing_id = 0;
    $existing_rows = iss_graph_get_service()->get_evidence_refs_for_entity($entity_id, [
        'source_system' => iss_graph_video_transcript_review_source_system(),
        'limit' => 500,
    ]);
    foreach ($existing_rows as $row) {
        if (is_array($row) && absint($row['target_id'] ?? 0) === $target_id) {
            $existing_id = (int) ($row['id'] ?? 0);
            break;
        }
    }

    $source_note = iss_graph_video_transcript_decode_evidence_note($source_evidence);
    $target = iss_graph_get_service()->get_entity_by_id($target_id);
    $target_title = $target ? (string) ($target['display_title'] ?? '') : ('Entity ' . $target_id);
    $note = wp_json_encode([
        'decision_type' => 'video_transcript_review',
        'source_evidence_id' => (int) ($source_evidence['id'] ?? 0),
        'target_entity_kind' => $target ? (string) ($target['entity_kind'] ?? '') : '',
        'target_title' => $target_title,
        'source_note' => $source_note,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    iss_graph_get_service()->upsert_entity_evidence_ref($entity_id, [
        'id' => $existing_id,
        'target_kind' => 'entity',
        'target_id' => $target_id,
        'source_system' => iss_graph_video_transcript_review_source_system(),
        'source_ref' => 'entity:' . $entity_id,
        'source_url' => (string) ($source_evidence['source_url'] ?? ''),
        'label' => sprintf('Transcript review: %s', $target_title),
        'note' => is_string($note) ? $note : '',
        'confidence' => (int) ($source_evidence['confidence'] ?? 0),
        'status' => $status,
    ]);
}

function iss_graph_video_transcript_append_graph_relation(int $from_entity_id, int $target_id, string $target_kind): bool
{
    $target_kind = sanitize_key($target_kind);
    if (!in_array($target_kind, ['person', 'organization'], true)) {
        return false;
    }

    $service = iss_graph_get_service();
    $existing = $service->get_relations_for_entity($from_entity_id, $target_kind, [
        'source_system' => 'content_admin',
        'limit' => 500,
    ]);
    $rows = [];
    foreach ($existing as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        if ((int) ($row['entity_id'] ?? 0) === $target_id) {
            return true;
        }

        $rows[] = [
            'to_entity_id' => (int) ($row['entity_id'] ?? 0),
            'relation_type' => sanitize_key((string) ($row['relation_type'] ?? 'subject')) ?: 'subject',
            'relation_role' => sanitize_key((string) ($row['relation_role'] ?? '')),
            'relation_label' => sanitize_text_field((string) ($row['relation_label'] ?? '')),
            'note' => sanitize_textarea_field((string) ($row['note'] ?? '')),
            'weight' => (int) ($row['weight'] ?? 0),
            'position' => isset($row['position']) ? (int) $row['position'] : $index,
            'is_primary' => !empty($row['is_primary']),
            'valid_from_year' => $service->normalize_year($row['valid_from_year'] ?? null),
            'valid_to_year' => $service->normalize_year($row['valid_to_year'] ?? null),
            'is_public' => !empty($row['is_public']),
        ];
    }

    $options = iss_graph_get_content_relation_options($target_kind);
    $rows[] = [
        'to_entity_id' => $target_id,
        'relation_type' => 'subject',
        'relation_label' => (string) ($options['subject'] ?? 'Thema'),
        'position' => count($rows),
        'is_public' => true,
    ];

    $service->replace_entity_relations_for_source(
        $from_entity_id,
        $target_kind,
        'content_admin',
        $rows,
        'entity:' . $from_entity_id
    );

    return true;
}

function iss_graph_video_transcript_append_place_relation(int $post_id, array $target): bool
{
    if (
        !function_exists('iss_relations_get_stored_post_relations')
        || !function_exists('iss_relations_update_post_relations')
    ) {
        return false;
    }

    $place_post_id = absint($target['post_id'] ?? 0);
    if ($place_post_id <= 0) {
        return false;
    }

    $relations = iss_relations_get_stored_post_relations($post_id);
    foreach ($relations as $relation) {
        if (is_array($relation) && absint($relation['place_id'] ?? 0) === $place_post_id) {
            return true;
        }
    }

    $relations[] = [
        'place_id' => $place_post_id,
        'role' => 'subject',
        'weight' => 0,
        'label' => 'Transkript',
    ];

    iss_relations_update_post_relations($post_id, $relations);
    if (function_exists('iss_relations_sync_post_read_models')) {
        iss_relations_sync_post_read_models($post_id);
    }

    return true;
}

function iss_graph_video_transcript_promote_evidence(int $post_id, array $evidence): bool
{
    $entity = iss_graph_get_service()->find_entity_by_post('video', $post_id);
    $from_entity_id = (int) ($entity['id'] ?? 0);
    $target_id = absint($evidence['target_id'] ?? 0);
    $target = $target_id > 0 ? iss_graph_get_service()->get_entity_by_id($target_id) : null;
    if ($from_entity_id <= 0 || !$target) {
        return false;
    }

    $target_kind = sanitize_key((string) ($target['entity_kind'] ?? ''));
    if ($target_kind === 'place') {
        $promoted = iss_graph_video_transcript_append_place_relation($post_id, $target);
    } else {
        $promoted = iss_graph_video_transcript_append_graph_relation($from_entity_id, $target_id, $target_kind);
    }

    if ($promoted) {
        iss_graph_video_transcript_upsert_review_decision($from_entity_id, $target_id, 'accepted', $evidence);
    }

    return $promoted;
}

function iss_graph_video_transcript_dismiss_evidence(int $post_id, array $evidence): bool
{
    $entity = iss_graph_get_service()->find_entity_by_post('video', $post_id);
    $from_entity_id = (int) ($entity['id'] ?? 0);
    $target_id = absint($evidence['target_id'] ?? 0);
    if ($from_entity_id <= 0 || $target_id <= 0) {
        return false;
    }

    iss_graph_video_transcript_upsert_review_decision($from_entity_id, $target_id, 'rejected', $evidence);

    return true;
}

function iss_graph_video_transcript_posted_ids(string $field_name): array
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the transcript review nonce before reading posted IDs.
    $raw = $_POST[$field_name] ?? [];
    $raw = is_array($raw) ? $raw : [];

    return array_values(array_unique(array_filter(array_map('absint', wp_unslash($raw)))));
}

function iss_graph_video_transcript_render_evidence_summary(array $evidence): string
{
    $note = is_array($evidence['decoded_note'] ?? null) ? $evidence['decoded_note'] : [];
    $segments = is_array($note['segments'] ?? null) ? $note['segments'] : [];
    $segment = is_array($segments[0] ?? null) ? $segments[0] : [];
    $timecode = sanitize_text_field((string) ($segment['timecode'] ?? ''));
    $snippet = sanitize_text_field((string) ($segment['text'] ?? ''));
    $matched_names = is_array($note['matched_names'] ?? null)
        ? implode(', ', array_map('sanitize_text_field', $note['matched_names']))
        : '';

    $parts = [];
    if ($timecode !== '') {
        $parts[] = sprintf('Zeitcode %s', $timecode);
    }
    if ($matched_names !== '') {
        $parts[] = sprintf('Treffer: %s', $matched_names);
    }
    if ($snippet !== '') {
        $parts[] = sprintf('"%s"', wp_trim_words($snippet, 26, '...'));
    }

    return implode(' · ', $parts);
}

function iss_graph_render_video_transcript_review_meta_box(WP_Post $post): void
{
    if (!iss_graph_video_transcript_is_video_post($post)) {
        return;
    }

    wp_nonce_field('iss_graph_save_video_transcript_review', 'iss_graph_video_transcript_review_nonce');

    $pending = iss_graph_video_transcript_get_pending_evidence_for_video((int) $post->ID);
    $reviewed = iss_graph_video_transcript_get_review_rows_for_video((int) $post->ID);

    echo '<p>' . esc_html__('Transkript-Treffer sind Vorschlaege. Annehmen legt eine normale Beziehung an; verwerfen merkt sich die Entscheidung und der automatische Abgleich erzeugt den Treffer nicht erneut.', 'iss-graph') . '</p>';

    if (!$pending) {
        echo '<p><strong>' . esc_html__('Keine offenen Transkript-Hinweise.', 'iss-graph') . '</strong></p>';
    } else {
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Ziel', 'iss-graph') . '</th>';
        echo '<th>' . esc_html__('Hinweis', 'iss-graph') . '</th>';
        echo '<th>' . esc_html__('Annehmen', 'iss-graph') . '</th>';
        echo '<th>' . esc_html__('Verwerfen', 'iss-graph') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($pending as $evidence) {
            if (!is_array($evidence)) {
                continue;
            }

            $evidence_id = (int) ($evidence['id'] ?? 0);
            $target = is_array($evidence['target'] ?? null) ? $evidence['target'] : [];
            $target_title = sanitize_text_field((string) ($target['display_title'] ?? ($evidence['label'] ?? '')));
            $target_kind = sanitize_key((string) ($target['entity_kind'] ?? ''));
            $confidence = (int) ($evidence['confidence'] ?? 0);
            $summary = iss_graph_video_transcript_render_evidence_summary($evidence);

            echo '<tr>';
            echo '<td><strong>' . esc_html($target_title) . '</strong><br><span class="description">' . esc_html($target_kind) . ' · ' . esc_html((string) $confidence) . '%</span></td>';
            echo '<td>' . esc_html($summary) . '</td>';
            echo '<td><label><input type="checkbox" name="iss_graph_video_transcript_accept[]" value="' . esc_attr((string) $evidence_id) . '"> ' . esc_html__('Beziehung anlegen', 'iss-graph') . '</label></td>';
            echo '<td><label><input type="checkbox" name="iss_graph_video_transcript_dismiss[]" value="' . esc_attr((string) $evidence_id) . '"> ' . esc_html__('Ignorieren', 'iss-graph') . '</label></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    if ($reviewed) {
        $accepted = 0;
        $rejected = 0;
        foreach ($reviewed as $row) {
            $status = sanitize_key((string) ($row['status'] ?? ''));
            if ($status === 'accepted') {
                $accepted++;
            } elseif ($status === 'rejected') {
                $rejected++;
            }
        }

        echo '<p class="description">' . esc_html(sprintf('Bereits geprueft: angenommen %d, verworfen %d.', $accepted, $rejected)) . '</p>';
    }
}

function iss_graph_add_video_transcript_review_meta_box(): void
{
    add_meta_box(
        'iss-graph-video-transcript-review',
        __('Transkript-Hinweise', 'iss-graph'),
        'iss_graph_render_video_transcript_review_meta_box',
        iss_graph_get_video_post_type(),
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'iss_graph_add_video_transcript_review_meta_box');

function iss_graph_save_video_transcript_review(int $post_id, WP_Post $post): void
{
    if (!iss_graph_video_transcript_is_video_post($post)) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (
        !isset($_POST['iss_graph_video_transcript_review_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_video_transcript_review_nonce'])), 'iss_graph_save_video_transcript_review')
    ) {
        return;
    }

    $accepted_ids = iss_graph_video_transcript_posted_ids('iss_graph_video_transcript_accept');
    $dismissed_ids = array_diff(
        iss_graph_video_transcript_posted_ids('iss_graph_video_transcript_dismiss'),
        $accepted_ids
    );

    $changed = false;
    foreach ($accepted_ids as $evidence_id) {
        $evidence = iss_graph_video_transcript_find_evidence_by_id($post_id, (int) $evidence_id);
        if ($evidence && iss_graph_video_transcript_promote_evidence($post_id, $evidence)) {
            $changed = true;
        }
    }

    foreach ($dismissed_ids as $evidence_id) {
        $evidence = iss_graph_video_transcript_find_evidence_by_id($post_id, (int) $evidence_id);
        if ($evidence && iss_graph_video_transcript_dismiss_evidence($post_id, $evidence)) {
            $changed = true;
        }
    }

    if ($changed) {
        iss_graph_sync_video_transcript_mentions($post_id);

        if (function_exists('iss_graph_sync_public_search_post')) {
            iss_graph_sync_public_search_post($post_id);
        }
    }
}
add_action('save_post', 'iss_graph_save_video_transcript_review', 58, 2);

function iss_graph_backfill_video_transcript_mentions(): array
{
    $post_type = iss_graph_get_video_post_type();
    if (!post_type_exists($post_type)) {
        return [
            'videos' => 0,
            'synced' => 0,
            'mentions' => 0,
        ];
    }

    $post_ids = get_posts([
        'post_type' => $post_type,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
    ]);

    $stats = [
        'videos' => count($post_ids),
        'synced' => 0,
        'mentions' => 0,
    ];

    foreach ($post_ids as $post_id) {
        $result = iss_graph_sync_video_transcript_mentions((int) $post_id);
        if (!empty($result['synced'])) {
            $stats['synced']++;
            $stats['mentions'] += (int) ($result['mentions'] ?? 0);
        }
    }

    return $stats;
}

function iss_graph_sync_video_transcript_mentions_on_save(int $post_id, WP_Post $post): void
{
    if (!iss_graph_video_transcript_is_video_post($post)) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_graph_sync_video_transcript_mentions($post_id);
}
add_action('save_post', 'iss_graph_sync_video_transcript_mentions_on_save', 45, 2);
