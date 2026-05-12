<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_place_context_fallback_history_label(array $place, array $era): string
{
    $explicit_names = array_values(array_filter(array_map(static function ($item): string {
        return is_array($item) ? trim((string) ($item['name'] ?? '')) : '';
    }, (array) ($era['explicit_eras'] ?? []))));
    if ($explicit_names) {
        return implode(' · ', $explicit_names);
    }

    $history_signal = trim(implode(' ', array_filter([
        (string) ($place['history'] ?? ''),
        (string) ($place['excerpt'] ?? ''),
    ])));

    $era_slug = trim((string) ($era['slug'] ?? ''));
    if (
        $history_signal !== ''
        && preg_match('/\b(18\d{2}|19\d{2}|20\d{2})\b/u', $history_signal)
        && $era_slug !== ''
        && $era_slug !== 'nach-1990'
    ) {
        return trim((string) ($era['name'] ?? $era['label'] ?? ''));
    }

    return '';
}

function iss_register_get_place_context_history_terms(array $place, array $era): array
{
    if (!empty($place['historical_phase_labels']) && is_array($place['historical_phase_labels'])) {
        return array_values(array_filter(array_map(static function ($label): string {
            return is_scalar($label) ? trim((string) $label) : '';
        }, $place['historical_phase_labels'])));
    }

    $explicit_names = array_values(array_filter(array_map(static function ($item): string {
        return is_array($item) ? trim((string) ($item['name'] ?? '')) : '';
    }, (array) ($era['explicit_eras'] ?? []))));
    if ($explicit_names) {
        return $explicit_names;
    }

    $fallback = iss_register_place_context_fallback_history_label($place, $era);

    return $fallback !== '' ? [$fallback] : [];
}

function iss_register_get_place_context_payload(int $post_id): array
{
    $place = function_exists('iss_register_get_place_entity_by_post_id')
        ? iss_register_get_place_entity_by_post_id($post_id)
        : null;

    if (!is_array($place)) {
        return [];
    }

    $era = function_exists('iss_register_detect_atlas_era')
        ? iss_register_detect_atlas_era($place)
        : [];
    $current_status = function_exists('iss_register_get_normalized_current_status_payload')
        ? iss_register_get_normalized_current_status_payload($place)
        : [];
    $current_use_type = function_exists('iss_register_detect_current_use_type')
        ? iss_register_detect_current_use_type($place)
        : [];

    return [
        'address' => trim((string) ($place['address'] ?? '')),
        'area' => trim((string) ($place['area'] ?? '')),
        'epochs' => isset($place['epochs']) && is_array($place['epochs']) ? array_values($place['epochs']) : [],
        'history_terms' => iss_register_get_place_context_history_terms($place, $era),
        'history_label' => implode(' · ', iss_register_get_place_context_history_terms($place, $era)),
        'history_missing' => trim((string) ($place['history'] ?? '')) === '' && empty($era['explicit_eras']),
        'current_status_label' => trim((string) ($current_status['label'] ?? '')),
        'current_use_type_label' => trim((string) ($current_use_type['label'] ?? '')),
    ];
}

function iss_register_render_place_context_items(array $items, string $item_class, string $label_class, string $value_class): string
{
    if (!$items) {
        return '';
    }

    $html = '';

    foreach ($items as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        $value = trim((string) ($item['value'] ?? ''));

        if ($label === '' || $value === '') {
            continue;
        }

        $html .= '<div class="' . esc_attr($item_class) . '">';
        $html .= '<p class="' . esc_attr($label_class) . '">' . esc_html($label) . '</p>';
        $html .= '<p class="' . esc_attr($value_class) . '">' . esc_html($value) . '</p>';
        $html .= '</div>';
    }

    return $html;
}

function iss_register_render_place_context(array $attributes = []): string
{
    $post_id = get_the_ID();
    if (!$post_id || get_post_type($post_id) !== ISS_REGISTER_POST_TYPE) {
        return '';
    }

    $context = iss_register_get_place_context_payload((int) $post_id);
    if (!$context) {
        return '';
    }

    $variant = sanitize_key((string) ($attributes['variant'] ?? 'terms'));

    if ($variant === 'hero_meta') {
        $labels = array_values(array_filter([
            $context['history_label'] !== '' ? $context['history_label'] : '',
            $context['current_status_label'],
            $context['current_use_type_label'],
            $context['area'],
        ]));

        if (!$labels) {
            return '';
        }

        $html = '<div class="wp-block-group iss-register-place__hero-meta">';
        foreach ($labels as $label) {
            $html .= '<p class="iss-register-place__hero-term">' . esc_html($label) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    if ($variant === 'hero_panel') {
        $items = [
            ['label' => 'Adresse', 'value' => $context['address']],
            ['label' => 'Historisch', 'value' => $context['history_label'] !== '' ? $context['history_label'] : 'Noch nicht eingeordnet'],
            ['label' => 'Heute', 'value' => $context['current_status_label']],
            ['label' => 'Nutzung', 'value' => $context['current_use_type_label']],
        ];

        return iss_register_render_place_context_items(
            $items,
            'wp-block-group iss-register-place__fact',
            'iss-register-place__fact-label',
            'iss-register-place__fact-value'
        );
    }

    if ($variant === 'facts_row') {
        $items = [
            ['label' => 'Adresse', 'value' => $context['address']],
            ['label' => 'Historisch', 'value' => $context['history_label'] !== '' ? $context['history_label'] : 'Noch nicht eingeordnet'],
            ['label' => 'Heute', 'value' => $context['current_status_label']],
            ['label' => 'Nutzung', 'value' => $context['current_use_type_label']],
            ['label' => 'Gebiet', 'value' => $context['area']],
        ];

        return iss_register_render_place_context_items(
            $items,
            'wp-block-group iss-register-place__compact-fact',
            'iss-register-place__compact-label',
            'iss-register-place__compact-value'
        );
    }

    if ($variant === 'today_panel') {
        $items = [
            ['label' => 'Heute', 'value' => $context['current_status_label']],
            ['label' => 'Nutzung', 'value' => $context['current_use_type_label']],
        ];

        $html = '<div class="wp-block-group iss-register-place__today-meta">';
        $html .= iss_register_render_place_context_items(
            $items,
            'wp-block-group iss-register-place__today-fact',
            'iss-register-place__today-label',
            'iss-register-place__today-value'
        );
        $html .= '</div>';

        return $html;
    }

    if ($variant === 'terms' && !empty($context['epochs'])) {
        $html = '<div class="wp-block-group iss-register-place__phase-list">';
        foreach ($context['epochs'] as $epoch) {
            if (!is_array($epoch)) {
                continue;
            }

            $title = trim((string) ($epoch['phase_name'] ?? ''));
            if ($title === '') {
                continue;
            }

            $years = [];
            if (isset($epoch['start_year']) && $epoch['start_year'] !== null && $epoch['start_year'] !== '') {
                $years[] = (string) $epoch['start_year'];
            }
            if (isset($epoch['end_year']) && $epoch['end_year'] !== null && $epoch['end_year'] !== '') {
                $years[] = (string) $epoch['end_year'];
            }
            $year_label = '';
            if ($years) {
                $year_label = count($years) === 2 ? implode('–', $years) : $years[0];
            }

            $summary = trim((string) ($epoch['summary'] ?? ''));
            $html .= '<div class="wp-block-group iss-register-place__phase">';
            if ($year_label !== '') {
                $html .= '<p class="iss-register-place__phase-years">' . esc_html($year_label) . '</p>';
            }
            $html .= '<p class="iss-register-place__phase-title">' . esc_html($title) . '</p>';
            if ($summary !== '') {
                $html .= '<p class="iss-register-place__phase-text">' . esc_html($summary) . '</p>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    $terms = array_values(array_filter(array_map(static function ($term): string {
        return is_scalar($term) ? trim((string) $term) : '';
    }, (array) ($context['history_terms'] ?? []))));

    if (!$terms) {
        return '';
    }

    $html = '<div class="wp-block-group iss-register-place__chip-list">';
    foreach ($terms as $term) {
        $html .= '<p class="iss-register-place__term">' . esc_html($term) . '</p>';
    }
    $html .= '</div>';

    return $html;
}

add_action('init', function () {
    if (!function_exists('register_block_type')) {
        return;
    }

    $block_json = ISS_REGISTER_PATH . 'block.json';
    if (file_exists($block_json)) {
        register_block_type(ISS_REGISTER_PATH, [
            'render_callback' => 'iss_register_render_register_app',
        ]);
    }

    register_block_type('iss/register-place-context', [
        'api_version' => 3,
        'attributes' => [
            'variant' => [
                'type' => 'string',
                'default' => 'terms',
            ],
        ],
        'render_callback' => 'iss_register_render_place_context',
    ]);
});
