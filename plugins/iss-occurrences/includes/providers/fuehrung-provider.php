<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ISS_Occurrences_FuehrungProvider
{
    public function get_post_type(): string
    {
        return 'fuehrung';
    }

    public function get_kind(): string
    {
        return 'tour';
    }

    public function sync_slot(array $slot): int
    {
        if (empty($slot['external_id']) && !empty($slot['id'])) {
            $slot['external_id'] = $slot['id'];
        }

        $source_post_id = isset($slot['source_post_id']) ? (int) $slot['source_post_id'] : 0;
        $source_post_type = isset($slot['source_post_type']) ? sanitize_key((string) $slot['source_post_type']) : '';
        $external_id = isset($slot['external_id']) ? sanitize_text_field((string) $slot['external_id']) : '';
        $origin = isset($slot['origin']) ? sanitize_key((string) $slot['origin']) : 'supersaas';

        if ($external_id === '' || $source_post_id <= 0 || $source_post_type !== $this->get_post_type()) {
            if ($external_id !== '') {
                iss_occurrences_get_service()->delete_occurrence_by_external($origin, $external_id);
            }
            return 0;
        }

        $source = get_post($source_post_id);
        if (!$source instanceof WP_Post || $source->post_status !== 'publish' || $source->post_type !== $this->get_post_type()) {
            iss_occurrences_get_service()->delete_occurrence_by_external($origin, $external_id);
            return 0;
        }

        $service = iss_occurrences_get_service();
        $start = $service->normalize_datetime((string) ($slot['start'] ?? $slot['starts_at'] ?? ''));
        if ($start === '') {
            iss_occurrences_get_service()->delete_occurrence_by_external($origin, $external_id);
            return 0;
        }

        return $service->upsert_occurrence([
            'source_post_id' => $source_post_id,
            'source_post_type' => $this->get_post_type(),
            'kind' => $this->get_kind(),
            'title' => isset($slot['title']) ? sanitize_text_field((string) $slot['title']) : get_the_title($source_post_id),
            'starts_at' => $start,
            'ends_at' => $service->normalize_datetime((string) ($slot['end'] ?? $slot['ends_at'] ?? ''), true),
            'is_open_ended' => false,
            'date_source' => 'supersaas',
            'status' => 'active',
            'visibility' => 'public',
            'origin' => $origin,
            'source_calendar' => isset($slot['source_calendar']) ? sanitize_text_field((string) $slot['source_calendar']) : '',
            'external_id' => $external_id,
            'tag' => isset($slot['tag']) ? strtoupper(sanitize_text_field((string) $slot['tag'])) : '',
            'series_key' => isset($slot['series_key']) ? sanitize_text_field((string) $slot['series_key']) : '',
            'booking_url' => isset($slot['booking_url']) ? esc_url_raw((string) $slot['booking_url']) : '',
            'location_label' => isset($slot['location']) ? sanitize_text_field((string) $slot['location']) : '',
            'availability_state' => isset($slot['availability_state']) ? sanitize_key((string) $slot['availability_state']) : '',
            'capacity_total' => array_key_exists('capacity', $slot) && $slot['capacity'] !== null ? (int) $slot['capacity'] : null,
            'capacity_available' => array_key_exists('available', $slot) && $slot['available'] !== null ? (int) $slot['available'] : null,
        ]) > 0 ? 1 : 0;
    }
}
