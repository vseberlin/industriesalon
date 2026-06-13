<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ISS_Occurrences_AusstellungProvider extends ISS_Occurrences_Abstract_Editorial_Provider
{
    public function get_post_type(): string
    {
        return 'ausstellung';
    }

    public function get_kind(): string
    {
        return 'ausstellung';
    }

    public function get_start_end_for_post(WP_Post $post): array
    {
        $post_id = (int) $post->ID;
        $start_raw = trim((string) get_post_meta($post_id, 'iss_start_date', true));
        $end_raw = trim((string) get_post_meta($post_id, 'iss_end_date', true));
        $start = $this->normalize_start($start_raw, $post);
        $is_permanent = function_exists('iss_content_model_ausstellung_is_permanent')
            ? iss_content_model_ausstellung_is_permanent($post_id)
            : $this->has_type($post_id, ['dauerausstellung']);
        $is_open_ended_type = $is_permanent || $this->has_type($post_id, ['digitaleausstellungen']);
        $is_open_ended = $end_raw === '' && $is_open_ended_type;

        return [
            'starts_at' => $start['starts_at'],
            'ends_at' => $is_open_ended ? '' : iss_occurrences_get_service()->normalize_datetime($end_raw, true),
            'is_open_ended' => $is_open_ended,
            'date_source' => $start['date_source'],
        ];
    }

    public function is_availability_type(int $post_id): bool
    {
        if ($post_id <= 0 || (string) get_post_type($post_id) !== $this->get_post_type()) {
            return false;
        }

        return $this->has_type($post_id, ['dauerausstellung', 'digitaleausstellungen']);
    }

    public function has_type(int $post_id, array $target_slugs): bool
    {
        if (!taxonomy_exists('ausstellung_typ')) {
            return false;
        }

        $type_slugs = wp_get_post_terms($post_id, 'ausstellung_typ', ['fields' => 'slugs']);
        if (is_wp_error($type_slugs) || empty($type_slugs)) {
            return false;
        }

        $target_slugs = array_values(array_unique(array_filter(array_map(static function ($slug): string {
            return sanitize_title((string) $slug);
        }, $target_slugs))));
        foreach ((array) $type_slugs as $slug) {
            if (in_array(sanitize_title((string) $slug), $target_slugs, true)) {
                return true;
            }
        }

        return false;
    }
}
