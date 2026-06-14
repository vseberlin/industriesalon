<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_register_blocks() {
    if (!function_exists('register_block_type')) {
        return;
    }

    $meta_dir = ISS_CONTENT_MODEL_PATH . 'blocks/content-meta';
    if (file_exists($meta_dir . '/block.json')) {
        register_block_type($meta_dir, [
            'render_callback' => 'iss_content_model_render_meta_block',
        ]);
    }

    $project_status_dir = ISS_CONTENT_MODEL_PATH . 'blocks/project-status';
    if (file_exists($project_status_dir . '/block.json')) {
        register_block_type($project_status_dir, [
            'render_callback' => 'iss_content_model_render_project_status_block',
        ]);
    }
}
add_action('init', 'iss_content_model_register_blocks');

function iss_content_model_month_name_de(int $month): string
{
    $names = [
        1 => 'Januar',
        2 => 'Februar',
        3 => 'März',
        4 => 'April',
        5 => 'Mai',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'August',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Dezember',
    ];

    return $names[$month] ?? '';
}

function iss_content_model_format_date_long_de(DateTimeImmutable $dt): string
{
    $timestamp = $dt->getTimestamp();
    $timezone = wp_timezone();
    $month_name = iss_content_model_month_name_de((int) wp_date('n', $timestamp, $timezone));

    if ($month_name === '') {
        return wp_date('d.m.Y', $timestamp, $timezone);
    }

    return wp_date('j.', $timestamp, $timezone) . ' ' . $month_name . ' ' . wp_date('Y', $timestamp, $timezone);
}

function iss_content_model_format_datetime($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value, wp_timezone());
        $time = $dt->format('H:i:s');
        if (in_array($time, ['00:00:00', '23:59:59'], true)) {
            return iss_content_model_format_date_long_de($dt);
        }

        return iss_content_model_format_date_long_de($dt) . ', ' . wp_date('H:i', $dt->getTimestamp(), wp_timezone()) . ' Uhr';
    } catch (Throwable $e) {
        return $value;
    }
}

function iss_content_model_format_date($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value, wp_timezone());
        return iss_content_model_format_date_long_de($dt);
    } catch (Throwable $e) {
        return $value;
    }
}

function iss_content_model_parse_iso_date($value): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
    if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $value) {
        return null;
    }

    return $dt;
}

function iss_content_model_get_project_status_display(int $post_id): array
{
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        return [];
    }

    $start = iss_content_model_parse_iso_date(get_post_meta($post_id, 'iss_start_date', true));
    $end = iss_content_model_parse_iso_date(get_post_meta($post_id, 'iss_end_date', true));
    $today = new DateTimeImmutable('today', wp_timezone());

    if ($end instanceof DateTimeImmutable && $end < $today) {
        return [
            'label' => __('Status', 'iss-content-model'),
            'value' => __('Abgeschlossen', 'iss-content-model'),
            'state' => 'completed',
        ];
    }

    if ($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable) {
        return [
            'label' => __('Zeitraum', 'iss-content-model'),
            'value' => iss_content_model_format_date_long_de($start) . ' – ' . iss_content_model_format_date_long_de($end),
            'state' => 'date-range',
        ];
    }

    if ($start instanceof DateTimeImmutable) {
        $prefix = $start > $today ? __('Ab', 'iss-content-model') : __('Seit', 'iss-content-model');

        return [
            'label' => __('Zeitraum', 'iss-content-model'),
            'value' => $prefix . ' ' . iss_content_model_format_date_long_de($start),
            'state' => $start > $today ? 'upcoming' : 'running',
        ];
    }

    if ($end instanceof DateTimeImmutable) {
        return [
            'label' => __('Zeitraum', 'iss-content-model'),
            'value' => __('Bis', 'iss-content-model') . ' ' . iss_content_model_format_date_long_de($end),
            'state' => 'until',
        ];
    }

    $status_terms = get_the_terms($post_id, ISS_CONTENT_MODEL_PROJECT_STATUS_TAXONOMY);
    if (is_array($status_terms) && !empty($status_terms)) {
        return [
            'label' => __('Status', 'iss-content-model'),
            'value' => implode(', ', wp_list_pluck($status_terms, 'name')),
            'state' => 'taxonomy',
        ];
    }

    $period = trim((string) get_post_meta($post_id, 'iss_period_label', true));
    if ($period !== '') {
        return [
            'label' => __('Zeitraum', 'iss-content-model'),
            'value' => $period,
            'state' => 'period',
        ];
    }

    return [];
}

function iss_content_model_get_default_meta_panel_copy($post_type) {
    switch ((string) $post_type) {
        case ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE:
            return [
                'kicker' => __('Planen', 'iss-content-model'),
                'title' => __('Termin und Ort', 'iss-content-model'),
            ];
        case ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE:
            return [
                'kicker' => __('Besuch', 'iss-content-model'),
                'title' => __('Laufzeit', 'iss-content-model'),
            ];
        case ISS_CONTENT_MODEL_PROJEKT_POST_TYPE:
            return [
                'kicker' => __('Projekt', 'iss-content-model'),
                'title' => __('Projektinfo', 'iss-content-model'),
            ];
        case ISS_CONTENT_MODEL_TEAM_POST_TYPE:
            return [
                'kicker' => __('Kontakt', 'iss-content-model'),
                'title' => __('Ansprechperson', 'iss-content-model'),
            ];
        default:
            return [
                'kicker' => __('Info', 'iss-content-model'),
                'title' => __('Details', 'iss-content-model'),
            ];
    }
}

function iss_content_model_get_meta_rows_for_post($post_id, array $options = []) {
    $post_id = (int) $post_id;
    $post_type = (string) get_post_type($post_id);
    $rows = [];
    $show_related_places = !isset($options['show_places']) || !empty($options['show_places']);
    $topic_terms = taxonomy_exists(ISS_CONTENT_MODEL_TOPIC_TAXONOMY)
        ? iss_content_model_get_term_name_list($post_id, ISS_CONTENT_MODEL_TOPIC_TAXONOMY)
        : [];
    $related_places = function_exists('iss_relations_get_related_place_items')
        ? iss_relations_get_related_place_items($post_id)
        : [];
    $related_links = ($related_places && function_exists('iss_relations_render_related_place_links_html'))
        ? iss_relations_render_related_place_links_html($post_id)
        : '';

    if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        $start = iss_content_model_format_datetime(get_post_meta($post_id, 'iss_start_datetime', true));
        $end = iss_content_model_format_datetime(get_post_meta($post_id, 'iss_end_datetime', true));
        $location = trim((string) get_post_meta($post_id, 'iss_location', true));

        if ($start !== '') {
            $rows[] = ['label' => __('Beginn', 'iss-content-model'), 'value' => $start];
        }
        if ($end !== '') {
            $rows[] = ['label' => __('Ende', 'iss-content-model'), 'value' => $end];
        }
        if ($location !== '') {
            $single_related_place_title = count($related_places) === 1
                ? trim((string) ($related_places[0]['title'] ?? ''))
                : '';

            if ($single_related_place_title !== '' && $related_links !== '' && $location === $single_related_place_title) {
                $rows[] = ['label' => __('Ort', 'iss-content-model'), 'value' => $related_links, 'html' => true];
            } else {
                $rows[] = ['label' => __('Ort', 'iss-content-model'), 'value' => $location];
            }
        } elseif (count($related_places) === 1 && $related_links !== '') {
            $rows[] = ['label' => __('Ort', 'iss-content-model'), 'value' => $related_links, 'html' => true];
        }
        if (!empty($topic_terms)) {
            $rows[] = ['label' => __('Thema', 'iss-content-model'), 'value' => implode(', ', $topic_terms)];
        }
    } elseif ($post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        $start = iss_content_model_format_date(get_post_meta($post_id, 'iss_start_date', true));
        $end = iss_content_model_format_date(get_post_meta($post_id, 'iss_end_date', true));
        $type_terms = iss_content_model_get_term_name_list($post_id, ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY);
        $collection_terms = iss_content_model_get_term_name_list($post_id, ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY);
        $is_permanent = function_exists('iss_content_model_ausstellung_is_permanent')
            ? iss_content_model_ausstellung_is_permanent($post_id)
            : false;

        if (!empty($type_terms)) {
            $rows[] = ['label' => __('Typ', 'iss-content-model'), 'value' => implode(', ', $type_terms)];
        } elseif ($is_permanent) {
            $rows[] = ['label' => __('Typ', 'iss-content-model'), 'value' => __('Dauerausstellung', 'iss-content-model')];
        }

        if ($start !== '' && $end !== '') {
            $rows[] = ['label' => __('Laufzeit', 'iss-content-model'), 'value' => $start . ' – ' . $end];
        } elseif ($start !== '') {
            $rows[] = ['label' => $is_permanent ? __('Seit', 'iss-content-model') : __('Beginn', 'iss-content-model'), 'value' => $start];
        } elseif ($end !== '') {
            $rows[] = ['label' => __('Bis', 'iss-content-model'), 'value' => $end];
        }

        if (!empty($collection_terms)) {
            $rows[] = ['label' => __('Sammlungsbereich', 'iss-content-model'), 'value' => implode(', ', $collection_terms)];
        }

        if (!empty($topic_terms)) {
            $rows[] = ['label' => __('Thema', 'iss-content-model'), 'value' => implode(', ', $topic_terms)];
        }
    } elseif ($post_type === ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        $period = trim((string) get_post_meta($post_id, 'iss_period_label', true));
        $status_terms = get_the_terms($post_id, ISS_CONTENT_MODEL_PROJECT_STATUS_TAXONOMY);
        $project_status = iss_content_model_get_project_status_display($post_id);
        $project_status_state = sanitize_key((string) ($project_status['state'] ?? ''));

        if (
            !empty($project_status['value'])
            && in_array($project_status_state, ['completed', 'date-range', 'running', 'upcoming', 'until'], true)
        ) {
            $rows[] = [
                'label' => (string) ($project_status['label'] ?? __('Status', 'iss-content-model')),
                'value' => (string) $project_status['value'],
            ];
        } else {
            if ($period !== '') {
                $rows[] = ['label' => __('Zeitraum', 'iss-content-model'), 'value' => $period];
            }
            if (is_array($status_terms) && !empty($status_terms)) {
                $rows[] = [
                    'label' => __('Status', 'iss-content-model'),
                    'value' => implode(', ', wp_list_pluck($status_terms, 'name')),
                ];
            }
        }
        if (!empty($topic_terms)) {
            $rows[] = ['label' => __('Thema', 'iss-content-model'), 'value' => implode(', ', $topic_terms)];
        }
    } elseif ($post_type === ISS_CONTENT_MODEL_TEAM_POST_TYPE) {
        $role_label = trim((string) get_post_meta($post_id, 'iss_role_label', true));
        $role_terms = get_the_terms($post_id, ISS_CONTENT_MODEL_TEAM_ROLE_TAXONOMY);
        $email = sanitize_email((string) get_post_meta($post_id, 'iss_email', true));
        $phone = trim((string) get_post_meta($post_id, 'iss_phone', true));

        if ($role_label !== '') {
            $rows[] = ['label' => __('Rolle', 'iss-content-model'), 'value' => $role_label];
        } elseif (is_array($role_terms) && !empty($role_terms)) {
            $rows[] = [
                'label' => __('Rolle', 'iss-content-model'),
                'value' => implode(', ', wp_list_pluck($role_terms, 'name')),
            ];
        }

        if ($email !== '') {
            $rows[] = [
                'label' => __('E-Mail', 'iss-content-model'),
                'value' => '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>',
                'html' => true,
            ];
        }

        if ($phone !== '') {
            $tel = preg_replace('/[^0-9+]/', '', $phone);
            $rows[] = [
                'label' => __('Telefon', 'iss-content-model'),
                'value' => $tel !== '' ? '<a href="tel:' . esc_attr($tel) . '">' . esc_html($phone) . '</a>' : esc_html($phone),
                'html' => true,
            ];
        }
    }

    if ($show_related_places && $related_places && $related_links !== '') {
        $render_related_places_row = true;

        if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE && count($related_places) <= 1) {
            $render_related_places_row = false;
        }

        if ($render_related_places_row) {
            $rows[] = [
                'label' => count($related_places) > 1 ? __('Orte', 'iss-content-model') : __('Ort', 'iss-content-model'),
                'value' => $related_links,
                'html' => true,
            ];
        }
    }

    return $rows;
}

function iss_content_model_get_term_name_list($post_id, $taxonomy) {
    $terms = get_the_terms((int) $post_id, (string) $taxonomy);
    if (!is_array($terms) || empty($terms)) {
        return [];
    }

    $names = [];
    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $name = trim((string) $term->name);
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

function iss_content_model_render_meta_block($attributes = [], $content = '', $block = null) {
    $post_id = get_the_ID();
    if ($post_id <= 0) {
        return '';
    }

    $post_type = (string) get_post_type($post_id);
    $rows = iss_content_model_get_meta_rows_for_post($post_id, [
        'show_places' => !isset($attributes['showPlaces']) || !empty($attributes['showPlaces']),
    ]);
    if (empty($rows)) {
        return '';
    }

    $defaults = iss_content_model_get_default_meta_panel_copy($post_type);
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? $defaults['kicker'])));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? $defaults['title'])));
    $intro = trim(sanitize_text_field((string) ($attributes['intro'] ?? '')));
    $variant = sanitize_key((string) ($attributes['variant'] ?? ''));
    $variant_class = in_array($variant, ['red', 'green', 'blue', 'yellow', 'brown'], true) ? ' iss-info-panel--' . $variant : '';

    $wrapper_attrs = (function_exists('get_block_wrapper_attributes') && ($block instanceof WP_Block))
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-content-meta'])
        : 'class="wp-block-iss-content-meta"';

    $out = '<div ' . $wrapper_attrs . '>';
    $out .= '<aside class="iss-info-panel iss-info-panel--skin-aside' . esc_attr($variant_class) . '">';

    if ($kicker !== '' || $title !== '' || $intro !== '') {
        $out .= '<div class="iss-heading iss-heading--uncaged iss-info-panel__heading">';
        if ($kicker !== '') {
            $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
        }
        if ($title !== '') {
            $out .= '<h3 class="iss-heading__title iss-info-panel__title">' . esc_html($title) . '</h3>';
        }
        if ($intro !== '') {
            $out .= '<p class="iss-heading__text iss-info-panel__intro">' . esc_html($intro) . '</p>';
        }
        $out .= '</div>';
    }

    $out .= '<div class="iss-info-panel__rows">';
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['value'])) {
            continue;
        }

        $label = isset($row['label']) ? (string) $row['label'] : '';
        $value = (string) $row['value'];
        $allow_html = !empty($row['html']);

        $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text">';
        if ($label !== '') {
            $out .= '<strong>' . esc_html($label) . '</strong><br>';
        }
        $out .= $allow_html ? wp_kses_post($value) : esc_html($value);
        $out .= '</p></div></div>';
    }
    $out .= '</div></aside></div>';

    return $out;
}

function iss_content_model_render_project_status_block($attributes = [], $content = '', $block = null): string
{
    $post_id = 0;
    if ($block instanceof WP_Block && !empty($block->context['postId'])) {
        $post_id = absint($block->context['postId']);
    }
    if ($post_id <= 0) {
        $post_id = (int) get_the_ID();
    }

    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        return '';
    }

    $status = iss_content_model_get_project_status_display((int) $post_id);
    $value = trim((string) ($status['value'] ?? ''));
    if ($value === '') {
        return '';
    }

    $show_label = !empty($attributes['showLabel']);
    $state = sanitize_html_class((string) ($status['state'] ?? 'default'));
    $classes = trim('wp-block-iss-project-status iss-project-status iss-project-status--' . $state);
    $wrapper_attrs = (function_exists('get_block_wrapper_attributes') && ($block instanceof WP_Block))
        ? get_block_wrapper_attributes(['class' => $classes])
        : 'class="' . esc_attr($classes) . '"';

    $label = trim((string) ($status['label'] ?? ''));
    $out = '<div ' . $wrapper_attrs . '>';
    if ($show_label && $label !== '') {
        $out .= '<span class="iss-project-status__label">' . esc_html($label) . '</span> ';
    }
    $out .= '<span class="iss-project-status__value">' . esc_html($value) . '</span>';
    $out .= '</div>';

    return $out;
}
