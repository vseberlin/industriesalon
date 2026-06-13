<?php

if (!defined('ABSPATH')) {
    exit;
}

abstract class ISS_Occurrences_Abstract_Editorial_Provider implements ISS_Occurrences_Source_Provider
{
    public function is_calendar_enabled(WP_Post $post): bool
    {
        return $post->post_type === $this->get_post_type()
            && $this->get_bool_meta((int) $post->ID, 'iss_programme_enabled', false);
    }

    public function get_occurrences_for_post(WP_Post $post): array
    {
        if ($post->post_type !== $this->get_post_type() || !$this->is_calendar_enabled($post)) {
            return [];
        }

        $post_id = (int) $post->ID;
        $dates = $this->get_start_end_for_post($post);
        if (empty($dates['starts_at'])) {
            return [];
        }

        $location = $this->get_location_data($post_id);

        return [[
            'source_post_id' => $post_id,
            'source_post_type' => $post->post_type,
            'kind' => $this->get_kind(),
            'title' => get_the_title($post_id),
            'starts_at' => $dates['starts_at'],
            'ends_at' => $dates['ends_at'],
            'is_open_ended' => !empty($dates['is_open_ended']),
            'date_source' => $dates['date_source'],
            'status' => 'active',
            'visibility' => 'public',
            'origin' => 'wp',
            'external_id' => 'wp:' . $post->post_type . ':' . $post_id,
            'tag' => '',
            'source_calendar' => '',
            'series_key' => '',
            'booking_url' => '',
            'location_post_id' => (int) $location['location_post_id'],
            'location_label' => (string) $location['location_label'],
        ]];
    }

    protected function get_bool_meta(int $post_id, string $key, bool $default): bool
    {
        $raw = get_post_meta($post_id, $key, true);
        if ($raw === '' || $raw === null) {
            return $default;
        }

        return !empty($raw);
    }

    protected function get_post_date_fallback(WP_Post $post): string
    {
        $date = trim((string) $post->post_date);
        if ($date === '' || $date === '0000-00-00 00:00:00') {
            return current_time('mysql');
        }

        return $date;
    }

    protected function normalize_start(string $raw, WP_Post $post): array
    {
        $service = iss_occurrences_get_service();
        $start = $service->normalize_datetime($raw);
        $date_source = $start !== '' ? 'explicit' : 'fallback_post_date';

        if ($start === '') {
            $start = $service->normalize_datetime($this->get_post_date_fallback($post));
        }

        return [
            'starts_at' => $start,
            'date_source' => $date_source,
        ];
    }

    protected function get_location_data(int $post_id): array
    {
        $location_post_id = (int) get_post_meta($post_id, 'iss_primary_place_id', true);
        $location_label = trim((string) get_post_meta($post_id, 'iss_location', true));

        if ($location_label === '' && $location_post_id > 0) {
            $location_label = trim((string) get_the_title($location_post_id));
        }

        return [
            'location_post_id' => $location_post_id,
            'location_label' => $location_label,
        ];
    }
}
