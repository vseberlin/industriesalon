<?php
if (!defined('ABSPATH')) exit;

function iss_programm_cards_get_type_label($item_type) {
    $item_type = sanitize_key((string) $item_type);

    if (in_array($item_type, ['tour', 'fuehrung', 'fuehrungen'], true)) {
        return __('Führung', 'iss-programm');
    }

    if (in_array($item_type, ['event', 'veranstaltung', 'veranstaltungen'], true)) {
        return __('Veranstaltung', 'iss-programm');
    }

    if (in_array($item_type, ['ausstellung', 'ausstellungen', 'exhibition'], true)) {
        return __('Ausstellung', 'iss-programm');
    }

    if (in_array($item_type, ['project', 'projekt', 'projekte'], true)) {
        return __('Projekt', 'iss-programm');
    }

    return $item_type !== '' ? ucfirst($item_type) : '';
}

function iss_programm_cards_get_card_url($row) {
    $row = is_array($row) ? $row : [];

    $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
    if ($source_post_id > 0) {
        $permalink = get_permalink($source_post_id);
        if (is_string($permalink) && $permalink !== '') {
            return $permalink;
        }
    }

    $cta_url = isset($row['cta_url']) ? trim((string) $row['cta_url']) : '';
    return $cta_url !== '' ? $cta_url : '';
}

function iss_programm_cards_build_block_config($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];

    $group = sanitize_text_field((string) ($attributes['group'] ?? ''));
    $time_mode = sanitize_key((string) ($attributes['timeMode'] ?? 'upcoming'));
    if (!in_array($time_mode, ['all', 'upcoming', 'past', 'month'], true)) {
        $time_mode = 'upcoming';
    }

    $default_month = preg_replace('/[^0-9\-]/', '', (string) ($attributes['defaultMonth'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}$/', $default_month)) {
        $default_month = wp_date('Y-m', null, wp_timezone());
    }

    return [
        'query' => [
            'limit' => max(1, (int) ($attributes['limit'] ?? 3)),
            'order' => $time_mode === 'past' ? 'DESC' : 'ASC',
            'groups' => $group !== '' ? [$group] : [],
            'filters' => [
                'time_mode' => $time_mode,
                'month' => $default_month,
                'item_types' => function_exists('iss_timeline_get_attribute_token_list')
                    ? iss_timeline_get_attribute_token_list($attributes['presetItemTypesList'] ?? [], [])
                    : [],
                'post_types' => function_exists('iss_timeline_get_attribute_token_list')
                    ? iss_timeline_get_attribute_token_list($attributes['postTypesList'] ?? [], [])
                    : [],
                'include_running_ranges' => !empty($attributes['includeRunningRanges']),
            ],
        ],
        'display' => [
            'showImage' => !array_key_exists('showImage', $attributes) || (bool) $attributes['showImage'],
            'showExcerpt' => !array_key_exists('showExcerpt', $attributes) || (bool) $attributes['showExcerpt'],
            'excerptLength' => max(8, (int) ($attributes['excerptLength'] ?? 20)),
            'showDate' => !array_key_exists('showDate', $attributes) || (bool) $attributes['showDate'],
            'showType' => !array_key_exists('showType', $attributes) || (bool) $attributes['showType'],
            'showButton' => !array_key_exists('showButton', $attributes) || (bool) $attributes['showButton'],
            'buttonText' => isset($attributes['buttonText']) ? sanitize_text_field((string) $attributes['buttonText']) : '',
        ],
    ];
}

function iss_programm_cards_render_item($timeline_item, $display = []) {
    if (!is_array($timeline_item)) {
        return '';
    }

    $display = is_array($display) ? $display : [];
    $row = $timeline_item;
    if (empty($row)) {
        return '';
    }

    $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
    $link_url = iss_programm_cards_get_card_url($row);
    $title = trim((string) ($row['title'] ?? ''));
    if ($title === '') {
        return '';
    }

    $meta_parts = [];
    if (!empty($display['showDate']) && !empty($row['date_label'])) {
        $meta_parts[] = (string) $row['date_label'];
        if (!empty($row['time_label'])) {
            $meta_parts[] = (string) $row['time_label'];
        }
    }
    $meta_text = implode(' · ', $meta_parts);

    $excerpt = '';
    if (!empty($display['showExcerpt'])) {
        if ($source_post_id > 0 && function_exists('iss_timeline_extract_teaser_text')) {
            $excerpt = iss_timeline_extract_teaser_text($source_post_id, (int) ($display['excerptLength'] ?? 20));
        }
        if ($excerpt === '' && !empty($row['summary'])) {
            $excerpt = wp_trim_words(wp_strip_all_tags((string) $row['summary']), (int) ($display['excerptLength'] ?? 20));
        }
    }

    $type_label = !empty($display['showType']) ? iss_programm_cards_get_type_label($row['type'] ?? '') : '';
    $button_label = trim((string) ($display['buttonText'] ?? ''));
    if ($button_label === '') {
        $button_label = __('Mehr', 'iss-programm');
    }

    $out = '<article class="iss-card iss-card--flat iss-program-cards__card">';

    if (!empty($display['showImage']) && $source_post_id > 0 && has_post_thumbnail($source_post_id)) {
        $out .= '<figure class="iss-card__media">';
        $out .= get_the_post_thumbnail($source_post_id, 'large');
        $out .= '</figure>';
    }

    $out .= '<div class="iss-card__body">';

    if ($type_label !== '') {
        $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($type_label) . '</p>';
    }

    if ($link_url !== '') {
        $out .= '<h3 class="iss-card__title"><a href="' . esc_url($link_url) . '">' . esc_html($title) . '</a></h3>';
    } else {
        $out .= '<h3 class="iss-card__title">' . esc_html($title) . '</h3>';
    }

    if ($meta_text !== '') {
        $out .= '<p class="iss-card__meta">' . esc_html($meta_text) . '</p>';
    }

    if ($excerpt !== '') {
        $out .= '<p class="iss-card__text">' . esc_html($excerpt) . '</p>';
    }

    if (!empty($display['showButton']) && $link_url !== '') {
        $out .= '<p><a class="iss-card__link" href="' . esc_url($link_url) . '">' . esc_html($button_label) . '</a></p>';
    }

    $out .= '</div></article>';

    return $out;
}

function iss_programm_render_cards_block($attributes = [], $content = '', $block = null) {
    $attributes = is_array($attributes) ? $attributes : [];
    $config = iss_programm_cards_build_block_config($attributes);
    $items = function_exists('iss_timeline_get_items_advanced')
        ? iss_timeline_get_items_advanced($config['query'])
        : [];

    $title = trim((string) ($attributes['title'] ?? ''));
    $kicker = trim((string) ($attributes['kicker'] ?? ''));
    $intro = trim((string) ($attributes['intro'] ?? ''));

    $use_block_wrapper = function_exists('get_block_wrapper_attributes') && ($block instanceof WP_Block);
    $attrs = $use_block_wrapper
        ? get_block_wrapper_attributes(['class' => 'iss-program-cards'])
        : 'class="iss-program-cards"';

    $out = '<section ' . $attrs . '>';

    if ($kicker !== '' || $title !== '' || $intro !== '') {
        $out .= '<div class="iss-heading iss-program-cards__heading">';
        if ($kicker !== '') {
            $out .= '<p class="iss-kicker">' . esc_html($kicker) . '</p>';
        }
        if ($title !== '') {
            $out .= '<h2 class="iss-heading__title">' . esc_html($title) . '</h2>';
        }
        if ($intro !== '') {
            $out .= '<p class="iss-heading__text">' . esc_html($intro) . '</p>';
        }
        $out .= '</div>';
    }

    if (!empty($items)) {
        $out .= '<div class="iss-card-grid">';
        foreach ($items as $item) {
            $out .= iss_programm_cards_render_item($item, $config['display']);
        }
        $out .= '</div>';
    } else {
        $out .= '<p class="iss-program-cards__empty">' . esc_html__('Derzeit sind keine passenden Einträge verfügbar.', 'iss-programm') . '</p>';
    }

    $out .= '</section>';

    return $out;
}
