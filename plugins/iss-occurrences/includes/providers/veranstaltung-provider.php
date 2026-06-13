<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ISS_Occurrences_VeranstaltungProvider extends ISS_Occurrences_Abstract_Editorial_Provider
{
    public function get_post_type(): string
    {
        return 'veranstaltung';
    }

    public function get_kind(): string
    {
        return 'event';
    }

    public function get_start_end_for_post(WP_Post $post): array
    {
        $post_id = (int) $post->ID;
        $start_raw = trim((string) get_post_meta($post_id, 'iss_start_datetime', true));
        $end_raw = trim((string) get_post_meta($post_id, 'iss_end_datetime', true));
        $start = $this->normalize_start($start_raw, $post);

        return [
            'starts_at' => $start['starts_at'],
            'ends_at' => iss_occurrences_get_service()->normalize_datetime($end_raw, true),
            'is_open_ended' => false,
            'date_source' => $start['date_source'],
        ];
    }
}
