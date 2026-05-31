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

function iss_register_get_place_context_epoch_year_label(array $epoch): string
{
    $years = [];
    if (isset($epoch['start_year']) && $epoch['start_year'] !== null && $epoch['start_year'] !== '') {
        $years[] = (string) $epoch['start_year'];
    }
    if (isset($epoch['end_year']) && $epoch['end_year'] !== null && $epoch['end_year'] !== '') {
        $years[] = (string) $epoch['end_year'];
    }

    if (!$years) {
        return !empty($epoch['is_current']) ? 'Heute' : '';
    }

    if (count($years) === 2) {
        return implode('–', $years);
    }

    return !empty($epoch['is_current']) ? ($years[0] . '–heute') : $years[0];
}

function iss_register_get_place_context_definition_label(string $group, string $key): string
{
    $key = sanitize_key($key);
    if ($key === '') {
        return '';
    }

    if ($group === 'era' && function_exists('iss_register_get_atlas_era_definitions')) {
        $definitions = iss_register_get_atlas_era_definitions();
        return trim((string) ($definitions[$key]['name'] ?? ''));
    }

    if ($group === 'function' && function_exists('iss_register_get_epoch_function_definitions')) {
        $definitions = iss_register_get_epoch_function_definitions();
        return trim((string) ($definitions[$key]['label'] ?? ''));
    }

    if ($group === 'source' && function_exists('iss_register_get_epoch_source_confidence_definitions')) {
        $definitions = iss_register_get_epoch_source_confidence_definitions();
        return trim((string) ($definitions[$key]['label'] ?? ''));
    }

    return '';
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
        'owner' => trim((string) ($place['owner'] ?? '')),
        'operator' => trim((string) ($place['operator'] ?? '')),
        'developer' => trim((string) ($place['developer'] ?? '')),
        'tenant' => trim((string) ($place['tenant'] ?? '')),
        'investment' => trim((string) ($place['investment'] ?? '')),
        'size' => trim((string) ($place['size'] ?? '')),
        'jobs' => trim((string) ($place['jobs'] ?? '')),
        'website' => trim((string) ($place['website'] ?? '')),
        'kaufpreis' => trim((string) ($place['kaufpreis'] ?? '')),
        'potential_note' => trim((string) ($place['potential_note'] ?? '')),
        'risk_note' => trim((string) ($place['risk_note'] ?? '')),
        'questions' => isset($place['questions']) && is_array($place['questions']) ? array_values($place['questions']) : [],
        'source_summary' => trim((string) ($place['source_summary'] ?? $place['sources'] ?? '')),
        'source_links' => isset($place['source_links']) && is_array($place['source_links']) ? array_values($place['source_links']) : [],
        'epochs' => isset($place['epochs']) && is_array($place['epochs']) ? array_values($place['epochs']) : [],
        'history_terms' => iss_register_get_place_context_history_terms($place, $era),
        'history_label' => implode(' · ', iss_register_get_place_context_history_terms($place, $era)),
        'history_missing' => trim((string) ($place['history'] ?? '')) === '' && empty($era['explicit_eras']),
        'current_status_label' => trim((string) ($current_status['label'] ?? '')),
        'current_use_type_label' => trim((string) ($current_use_type['label'] ?? '')),
        'present_label' => function_exists('iss_register_build_present_label')
            ? trim((string) iss_register_build_present_label($current_status, $current_use_type))
            : '',
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

function iss_register_render_place_context_data_items(array $items, string $item_class, string $label_class, string $value_class): string
{
    if (!$items) {
        return '';
    }

    $html = '';
    foreach ($items as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        $value = trim((string) ($item['value'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));

        if ($label === '' || $value === '') {
            continue;
        }

        $html .= '<div class="' . esc_attr($item_class) . '">';
        $html .= '<p class="' . esc_attr($label_class) . '">' . esc_html($label) . '</p>';
        if ($url !== '') {
            $html .= '<p class="' . esc_attr($value_class) . '"><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($value) . '</a></p>';
        } else {
            $html .= '<p class="' . esc_attr($value_class) . '">' . esc_html($value) . '</p>';
        }
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
            $context['present_label'],
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
            ['label' => 'Heute', 'value' => $context['present_label']],
            ['label' => 'Gebiet', 'value' => $context['area']],
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
            ['label' => 'Heute', 'value' => $context['present_label']],
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
            ['label' => 'Heute', 'value' => $context['present_label']],
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

            $year_label = iss_register_get_place_context_epoch_year_label($epoch);
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

    if ($variant === 'epoch_rail' && !empty($context['epochs'])) {
        $html = '<div class="wp-block-group iss-register-place__epoch-rail">';
        foreach ($context['epochs'] as $epoch) {
            if (!is_array($epoch)) {
                continue;
            }

            $title = trim((string) ($epoch['phase_name'] ?? ''));
            if ($title === '') {
                continue;
            }

            $year_label = iss_register_get_place_context_epoch_year_label($epoch);
            $meta = array_values(array_filter([
                iss_register_get_place_context_definition_label('era', (string) ($epoch['era_slug'] ?? '')),
                iss_register_get_place_context_definition_label('function', (string) ($epoch['function_key'] ?? '')),
            ]));
            $summary = trim((string) ($epoch['summary'] ?? ''));
            $source_parts = array_values(array_filter([
                iss_register_get_place_context_definition_label('source', (string) ($epoch['source_confidence'] ?? '')),
                trim((string) ($epoch['source_summary'] ?? '')) !== '' ? wp_trim_words(wp_strip_all_tags((string) $epoch['source_summary']), 14, '…') : '',
            ]));
            $row_classes = ['wp-block-group', 'iss-register-place__epoch-row'];
            if (!empty($epoch['is_current'])) {
                $row_classes[] = 'is-current';
            }

            $html .= '<article class="' . esc_attr(implode(' ', $row_classes)) . '">';
            $html .= '<div class="iss-register-place__epoch-index">';
            if ($year_label !== '') {
                $html .= '<p class="iss-register-place__epoch-years">' . esc_html($year_label) . '</p>';
            }
            $html .= '</div>';
            $html .= '<div class="iss-register-place__epoch-body">';
            if ($meta) {
                $html .= '<p class="iss-register-place__epoch-meta">' . esc_html(implode(' · ', $meta)) . '</p>';
            }
            $html .= '<h3 class="iss-register-place__epoch-title">' . esc_html($title) . '</h3>';
            if ($summary !== '') {
                $html .= '<p class="iss-register-place__epoch-text">' . esc_html($summary) . '</p>';
            }
            if ($source_parts) {
                $html .= '<p class="iss-register-place__epoch-source">' . esc_html(implode(' · ', $source_parts)) . '</p>';
            }
            $html .= '</div>';
            $html .= '</article>';
        }
        $html .= '</div>';

        return $html;
    }

    if ($variant === 'current_data_grid') {
        $source_summary = $context['source_summary'] !== '' ? wp_trim_words(wp_strip_all_tags($context['source_summary']), 18, '…') : '';
        $website = esc_url_raw($context['website']);
        $website_label = $website !== '' ? preg_replace('/^www\./i', '', (string) wp_parse_url($website, PHP_URL_HOST)) : '';
        if (!is_string($website_label) || $website_label === '') {
            $website_label = $website;
        }

        $items = [
            ['label' => 'Status', 'value' => $context['present_label']],
            ['label' => 'Eigentum', 'value' => $context['owner']],
            ['label' => 'Operator', 'value' => $context['operator']],
            ['label' => 'Developer', 'value' => $context['developer']],
            ['label' => 'Tenant', 'value' => $context['tenant']],
            ['label' => 'Fläche', 'value' => $context['size']],
            ['label' => 'Investition', 'value' => $context['investment']],
            ['label' => 'Arbeitsplätze', 'value' => $context['jobs']],
            ['label' => 'Kaufpreis', 'value' => $context['kaufpreis']],
            ['label' => 'Website / Quelle', 'value' => $website_label, 'url' => $website],
            ['label' => 'Datenstand', 'value' => $source_summary],
        ];
        $body = iss_register_render_place_context_data_items(
            $items,
            'wp-block-group iss-register-place__data-item',
            'iss-register-place__data-label',
            'iss-register-place__data-value'
        );

        return $body !== '' ? '<div class="wp-block-group iss-register-place__data-grid">' . $body . '</div>' : '';
    }

    if ($variant === 'risk_potential') {
        $questions = array_values(array_filter(array_map(static function ($question): string {
            return is_scalar($question) ? trim((string) $question) : '';
        }, (array) ($context['questions'] ?? []))));
        $items = [
            ['label' => 'Potenzial', 'value' => $context['potential_note'], 'class' => 'iss-register-place__interpretation-item--potential'],
            ['label' => 'Risiko', 'value' => $context['risk_note'], 'class' => 'iss-register-place__interpretation-item--risk'],
            [
                'label' => count($questions) > 1 ? 'Offene Fragen' : 'Offene Frage',
                'value' => $context['risk_note'] === '' && $questions ? implode(' · ', $questions) : '',
                'class' => 'iss-register-place__interpretation-item--question',
            ],
        ];
        $html = '';

        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            $classes = ['wp-block-group', 'iss-register-place__interpretation-item', (string) ($item['class'] ?? '')];
            $html .= '<div class="' . esc_attr(implode(' ', array_filter($classes))) . '">';
            $html .= '<p class="iss-register-place__interpretation-label">' . esc_html($label) . '</p>';
            $html .= '<p class="iss-register-place__interpretation-text">' . esc_html($value) . '</p>';
            $html .= '</div>';
        }

        return $html !== '' ? '<div class="wp-block-group iss-register-place__interpretation">' . $html . '</div>' : '';
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

    register_block_type('iss-register/schoneweide-atlas', [
        'api_version' => 3,
        'title' => __('Schöneweide Atlas', 'iss-register'),
        'description' => __('Reusable public Schöneweide Atlas.', 'iss-register'),
        'category' => 'widgets',
        'icon' => 'location-alt',
        'editor_script' => 'iss-register-schoneweide-atlas-block-editor',
        'style' => 'iss-register-schoneweide-atlas-style',
        'view_script' => 'iss-register-schoneweide-atlas-view',
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
        ],
        'render_callback' => 'iss_register_render_schoneweide_atlas',
    ]);
});
