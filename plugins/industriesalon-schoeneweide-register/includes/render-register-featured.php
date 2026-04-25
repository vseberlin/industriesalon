<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('iss_register_safe_text')) {
    function iss_register_safe_text($value, string $fallback = ''): string
    {
        if (!is_scalar($value)) {
            return $fallback;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : $fallback;
    }
}

if (!function_exists('iss_register_status_label')) {
    function iss_register_status_label(string $status): string
    {
        $map = [
            'aktiv' => 'Aktiv',
            'entwicklung' => 'In Entwicklung',
            'geplant' => 'Geplant',
            'unklar' => 'Unklar',
            'abzug' => 'Abzug geplant',
            'sucht' => 'Sucht Standort',
            'mieter' => 'Mieter',
        ];

        $normalized = strtolower(trim($status));

        return $map[$normalized] ?? iss_register_safe_text($status, 'Unbekannt');
    }
}

if (!function_exists('iss_register_status_class')) {
    function iss_register_status_class(string $status): string
    {
        $normalized = strtolower(trim($status));
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized);

        return is_string($normalized) && $normalized !== '' ? $normalized : 'neutral';
    }
}

if (!function_exists('iss_register_extract_first_image')) {
    function iss_register_extract_first_image(array $place, array $groups = ['current_images', 'archive_images', 'document_images']): ?array
    {
        foreach ($groups as $group) {
            $images = $place[$group] ?? [];
            if (!is_array($images)) {
                continue;
            }

            foreach ($images as $image) {
                if (!is_array($image)) {
                    continue;
                }

                $url = isset($image['url']) ? esc_url_raw((string) $image['url']) : '';
                if ($url === '') {
                    continue;
                }

                return [
                    'url' => $url,
                    'caption' => isset($image['caption']) ? sanitize_text_field((string) $image['caption']) : '',
                    'year' => isset($image['year']) ? sanitize_text_field((string) $image['year']) : '',
                    'source' => isset($image['source']) ? sanitize_text_field((string) $image['source']) : '',
                ];
            }
        }

        return null;
    }
}

if (!function_exists('iss_register_place_summary')) {
    function iss_register_place_summary(array $place, int $max_length = 180): string
    {
        $summary = '';

        $candidates = [
            $place['current'] ?? '',
            $place['history'] ?? '',
            $place['vornutzung'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            $text = wp_strip_all_tags((string) $candidate);
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if ($text !== '') {
                $summary = $text;
                break;
            }
        }

        if ($summary === '') {
            return 'Kein Kurztext vorhanden.';
        }

        $length = function_exists('mb_strlen') ? mb_strlen($summary) : strlen($summary);
        if ($length <= $max_length) {
            return $summary;
        }

        $truncated = function_exists('mb_substr')
            ? mb_substr($summary, 0, $max_length - 1)
            : substr($summary, 0, $max_length - 1);

        return rtrim($truncated) . '…';
    }
}

if (!function_exists('iss_register_render_image')) {
    function iss_register_render_image(array $place, string $fallback, string $class_name, array $groups = ['current_images', 'archive_images', 'document_images']): string
    {
        $image = iss_register_extract_first_image($place, $groups);

        if (!$image) {
            return sprintf(
                '<div class="%1$s %1$s--empty"><span>%2$s</span></div>',
                esc_attr($class_name),
                esc_html($fallback)
            );
        }

        $alt_candidates = [
            iss_register_safe_text($image['caption'] ?? ''),
            iss_register_safe_text($place['name'] ?? ''),
            'Standortbild',
        ];

        $alt = '';
        foreach ($alt_candidates as $candidate) {
            if ($candidate !== '') {
                $alt = $candidate;
                break;
            }
        }

        return sprintf(
            '<div class="%1$s"><img src="%2$s" alt="%3$s" loading="lazy" decoding="async"></div>',
            esc_attr($class_name),
            esc_url($image['url']),
            esc_attr($alt)
        );
    }
}

if (!function_exists('iss_register_render_featured_cards')) {
    function iss_register_render_featured_cards(array $places, int $limit = 6): string
    {
        if (!$places) {
            return '<p class="iss-register-empty">Keine Standorte vorhanden.</p>';
        }

        $items = array_slice($places, 0, max(1, $limit));
        $html = '<div class="iss-register-featured-grid">';

        foreach ($items as $place) {
            if (!is_array($place)) {
                continue;
            }

            $id = iss_register_safe_text($place['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $status = iss_register_safe_text($place['status'] ?? '');
            $area = iss_register_safe_text($place['area'] ?? 'Gebiet offen');

            $html .= '<article class="iss-register-featured-card">';
            $html .= sprintf(
                '<button type="button" class="iss-register-featured-card__button" data-place-id="%s">',
                esc_attr($id)
            );
            $html .= iss_register_render_image($place, 'Bild gesucht', 'iss-register-featured-card__media');
            $html .= '<div class="iss-register-featured-card__body">';
            $html .= sprintf(
                '<h4 class="iss-register-featured-card__title">%s</h4>',
                esc_html(iss_register_safe_text($place['name'] ?? 'Unbenannter Standort'))
            );
            $html .= '<p class="iss-register-featured-card__meta">';
            $html .= sprintf(
                '<span class="iss-register-badge iss-register-badge--%1$s">%2$s</span>',
                esc_attr(iss_register_status_class($status)),
                esc_html(iss_register_status_label($status))
            );
            $html .= sprintf('<span>%s</span>', esc_html($area));
            $html .= '</p>';
            $html .= sprintf(
                '<p class="iss-register-featured-card__text">%s</p>',
                esc_html(iss_register_place_summary($place, 150))
            );
            $html .= '<span class="iss-register-inline-link">Details ansehen</span>';
            $html .= '</div>';
            $html .= '</button>';
            $html .= '</article>';
        }

        $html .= '</div>';

        return $html;
    }
}
