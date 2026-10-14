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
    if (!empty($epoch['milestone'])) {
        return iss_register_milestone_date_label($epoch['milestone']);
    }

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

/** Respect Place image visibility while keeping editorial media order. */
function iss_register_get_place_context_epoch_images(array $epoch, int $post_id): array
{
    $images = [];
    $known = [];
    foreach (['archive_images', 'current_images'] as $group) {
        foreach (iss_register_normalize_image_group_value(get_post_meta($post_id, $group, true)) as $image) {
            $id = absint($image['media_id'] ?? 0);
            if ($id > 0) { $known[$id] = $image; }
        }
    }
    foreach (array_unique(array_map('absint', (array) ($epoch['media_ids'] ?? []))) as $id) {
        if (!wp_attachment_is_image($id) || in_array(get_post_status($id), ['private', 'trash'], true)) { continue; }
        if (isset($known[$id]) && ($known[$id]['visibility'] ?? '') !== 'public') { continue; }
        $images[] = $known[$id] ?? ['media_id' => $id];
    }
    return $images;
}

function iss_register_get_place_context_primary_image(array $images): array
{
    foreach ($images as $image) {
        if (is_array($image) && !empty($image['is_featured']) && wp_attachment_is_image(absint($image['media_id'] ?? 0))) {
            return $image;
        }
    }

    foreach ($images as $image) {
        if (is_array($image) && wp_attachment_is_image(absint($image['media_id'] ?? 0))) {
            return $image;
        }
    }

    return [];
}

function iss_register_render_place_context_epoch_image(array $image): string
{
    $media_id = absint($image['media_id'] ?? 0);
    if ($media_id <= 0) {
        return '';
    }

    $image_html = wp_get_attachment_image($media_id, 'large', false, [
        'class' => 'iss-register-place__epoch-image',
        'loading' => 'lazy',
    ]);
    if ($image_html === '') {
        return '';
    }

    $caption = trim((string) ($image['caption'] ?? ''));
    if ($caption === '') {
        $caption = trim((string) wp_get_attachment_caption($media_id));
    }

    $year = trim((string) ($image['year'] ?? ''));
    $credit = array_values(array_filter([
        trim((string) ($image['photographer'] ?? '')),
        trim((string) ($image['source'] ?? '')),
        trim((string) ($image['rights'] ?? '')),
    ]));

    $html = '<figure class="iss-register-place__epoch-figure">';
    $html .= $image_html;
    if ($caption !== '' || $year !== '' || $credit) {
        $html .= '<figcaption class="iss-register-place__epoch-caption">';
        if ($caption !== '') {
            $html .= '<span class="iss-register-place__epoch-caption-text">' . esc_html($caption) . '</span>';
        }
        if ($year !== '') {
            $html .= '<span class="iss-register-place__epoch-credit">' . esc_html($year) . '</span>';
        }
        if ($credit) {
            $html .= '<details class="iss-register-place__epoch-image-details">';
            $html .= '<summary>Bildnachweis</summary>';
            $html .= '<p>' . esc_html(implode(' · ', $credit)) . '</p>';
            $html .= '</details>';
        }
        $html .= '</figcaption>';
    }
    $html .= '</figure>';

    return $html;
}

function iss_register_render_place_context_comparison_image(array $image, string $label): string
{
    $media_id = absint($image['media_id'] ?? 0);
    if ($media_id <= 0) {
        return '';
    }

    $image_html = wp_get_attachment_image($media_id, 'large', false, [
        'class' => 'iss-register-place__comparison-image',
        'loading' => 'lazy',
    ]);
    if ($image_html === '') {
        return '';
    }

    $caption = trim((string) ($image['caption'] ?? ''));
    if ($caption === '') {
        $caption = trim((string) wp_get_attachment_caption($media_id));
    }
    $year = trim((string) ($image['year'] ?? ''));
    $credit = array_values(array_filter([
        trim((string) ($image['photographer'] ?? '')),
        trim((string) ($image['source'] ?? '')),
        trim((string) ($image['rights'] ?? '')),
    ]));

    $html = '<figure class="iss-register-place__comparison-figure">';
    $html .= '<div class="iss-register-place__comparison-media">';
    $html .= $image_html;
    $html .= '<span class="iss-register-place__comparison-label">' . esc_html($label) . '</span>';
    $html .= '</div>';
    if ($caption !== '' || $year !== '' || $credit) {
        $html .= '<figcaption class="iss-register-place__comparison-caption">';
        if ($caption !== '') {
            $html .= '<span>' . esc_html($caption) . '</span>';
        }
        if ($year !== '') {
            $html .= '<span class="iss-register-place__comparison-year">' . esc_html($year) . '</span>';
        }
        if ($credit) {
            $html .= '<details><summary>Bildnachweis</summary><p>' . esc_html(implode(' · ', $credit)) . '</p></details>';
        }
        $html .= '</figcaption>';
    }
    $html .= '</figure>';

    return $html;
}

function iss_register_get_place_contribution_url(int $post_id): string
{
    $post = $post_id > 0 ? get_post($post_id) : null;
    if (!$post instanceof WP_Post || $post->post_type !== ISS_REGISTER_POST_TYPE) {
        return '';
    }

    $url = function_exists('iss_content_upload_url') ? iss_content_upload_url($post_id) : '';
    if ($url === '') {
        return '';
    }

    return (string) apply_filters('iss_register_place_contribution_url', $url, $post_id);
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
    // An Atlas classification fallback is not an authored Place fact.
    if (($current_status['source'] ?? '') === 'fallback') {
        $current_status = [];
    }
    $current_use_type = function_exists('iss_register_detect_current_use_type')
        ? iss_register_detect_current_use_type($place)
        : [];
    $epochs = isset($place['epochs']) && is_array($place['epochs']) ? array_values($place['epochs']) : [];

    return [
        'register_id' => trim((string) ($place['id'] ?? $post_id)),
        'address' => trim((string) ($place['address'] ?? '')),
        'area' => trim((string) ($place['area'] ?? '')),
        'construction_period' => trim((string) ($place['construction_period'] ?? '')),
        'original_name' => trim((string) ($place['original_name'] ?? '')),
        'monument_status' => trim((string) ($place['monument_status'] ?? '')),
        'monument_record_url' => trim((string) ($place['monument_record_url'] ?? '')),
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
        'epochs' => $epochs,
        'archive_images' => isset($place['archive_images']) && is_array($place['archive_images']) ? array_values($place['archive_images']) : [],
        'current_images' => isset($place['current_images']) && is_array($place['current_images']) ? array_values($place['current_images']) : [],
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
        $url = esc_url_raw(trim((string) ($item['url'] ?? '')));

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

    if ($variant === 'people') {
        return (string) apply_filters('iss_register_place_people_presentation', '', (array) (iss_register_get_place_entity_by_post_id((int) $post_id)['people'] ?? []));
    }

    if ($variant === 'collection') {
        $cards = [];
        foreach (iss_register_get_related_objects_for_place((int) $post_id, 4) as $item) {
            $object = get_post(absint($item['post_id'] ?? 0));
            if (!$object || $object->post_status !== 'publish' || $object->post_password !== '') { continue; }
            $cards[] = iss_relations_render_related_content_card($object, [], ['showImage' => true, 'showExcerpt' => false]);
        }
        return $cards ? iss_relations_render_cards_grid($cards, 'archivobjekt', ['columns' => 4]) : '';
    }

    if ($variant === 'location') {
        $location = array_values(array_unique(array_filter([$context['address'], $context['area']])));
        return $location ? '<p class="iss-register-place__location">' . esc_html(implode(' · ', $location)) . '</p>' : '';
    }

    if ($variant === 'current_note') {
        if (iss_editorial_document_is_enabled((int) $post_id, 'place')) { return ''; }
        $note = trim((string) get_post_meta((int) $post_id, 'current_use', true));
        if ($note === '') { return ''; }
        $html = '<div class="iss-register-place__current-note"><p>' . esc_html($note) . '</p>';
        $source = get_post_meta((int) $post_id, '_iss_register_current_import', true);
        if (is_array($source) && !empty($source['source_url'])) {
            $html .= '<p><a href="' . esc_url($source['source_url']) . '">Quelle der aktuellen Angaben: Standortregister</a></p>';
        }
        return $html . '</div>';
    }

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
            ['label' => 'Entstehungszeit', 'value' => $context['construction_period']],
            ['label' => 'Ursprünglicher Name', 'value' => $context['original_name']],
            ['label' => 'Denkmal', 'value' => $context['monument_status'], 'url' => $context['monument_record_url']],
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

        $body = iss_register_render_place_context_items(
            $items,
            'wp-block-group iss-register-place__today-fact',
            'iss-register-place__today-label',
            'iss-register-place__today-value'
        );
        return $body === '' ? '' : '<div class="wp-block-group iss-register-place__today-meta">' . $body . '</div>';
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

    if ($variant === 'epoch_rail') {
        // Narrative fields belong to authored phases, not the Atlas state classification.
        $authored_epochs = iss_register_get_epoch_service()->get_epochs_for_place((int) $post_id);
        $context['epochs'] = iss_register_merge_place_milestones($post_id, $authored_epochs ?: (array) ($context['epochs'] ?? []));
    }
    if ($variant === 'epoch_rail') {
        $html = '';
        foreach ($context['epochs'] as $epoch) {
            if (!is_array($epoch)) {
                continue;
            }

            if (!empty($epoch['milestone'])) {
                $html .= apply_filters('iss_register_place_updates_presentation', '', [$epoch['milestone']]);
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
            $source_summary = trim((string) ($epoch['source_summary'] ?? ''));
            $source_links = array_values(array_filter((array) ($epoch['source_links'] ?? []), static function ($url): bool {
                return is_string($url) && esc_url($url) !== '';
            }));
            $source_confidence_key = sanitize_key((string) ($epoch['source_confidence'] ?? ''));
            $source_confidence = in_array($source_confidence_key, ['', 'unknown', 'unbekannt', 'not-assessed', 'not_assessed', 'nicht-bewertet', 'nicht_bewertet'], true)
                ? ''
                : iss_register_get_place_context_definition_label('source', $source_confidence_key);
            $image_html = '';
            foreach (iss_register_get_place_context_epoch_images($epoch, (int) $post_id) as $image) {
                $image_html .= iss_register_render_place_context_epoch_image($image);
            }
            if ($image_html !== '') { $image_html = '<div class="iss-register-place__epoch-media">' . $image_html . '</div>'; }
            $updates = (string) apply_filters('iss_register_place_updates_presentation', '', (array) ($epoch['milestones'] ?? []));
            $invitation = $summary !== '' || $image_html !== '' || $updates !== '' || trim((string) ($epoch['contribution_question'] ?? '')) !== ''
                ? (string) apply_filters('iss_register_place_epoch_contribution', '', (int) $post_id, $epoch) : '';
            if ($summary === '' && $image_html === '' && $invitation === '' && $updates === '') {
                continue;
            }
            $row_classes = ['wp-block-group', 'iss-register-place__epoch-row'];
            if (!empty($epoch['is_current'])) {
                $row_classes[] = 'is-current';
            }
            if ($image_html !== '') {
                $row_classes[] = 'has-media';
            }
            if (trim((string) ($epoch['contribution_question'] ?? '')) !== '') { $row_classes[] = 'has-question'; }

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
            if ($source_summary !== '' || $source_links) {
                $html .= '<details class="iss-register-place__epoch-sources">';
                $html .= '<summary>Quellenhinweis</summary>';
                if ($source_confidence !== '') {
                    $html .= '<p class="iss-register-place__epoch-source-confidence">' . esc_html($source_confidence) . '</p>';
                }
                if ($source_summary !== '') {
                    $html .= '<p class="iss-register-place__epoch-source">' . esc_html($source_summary) . '</p>';
                }
                foreach (array_unique($source_links) as $url) {
                    $label = (string) wp_parse_url($url, PHP_URL_HOST);
                    $html .= '<p><a href="' . esc_url($url) . '">' . esc_html($label !== '' ? $label : 'Quelle öffnen') . '</a></p>';
                }
                $html .= '</details>';
            }
            $html .= $updates . $invitation;
            $html .= '</div>';
            $html .= $image_html;
            $html .= '</article>';
        }
        return $html === '' ? '' : '<div class="wp-block-group iss-register-place__epoch-rail">' . $html . '</div>';
    }

    if ($variant === 'then_now') {
        $archive_image = iss_register_get_place_context_primary_image($context['archive_images']);
        $current_image = iss_register_get_place_context_primary_image($context['current_images']);
        if (absint($archive_image['media_id'] ?? 0) === absint($current_image['media_id'] ?? 0)) {
            $current_image = [];
        }

        $archive_html = iss_register_render_place_context_comparison_image($archive_image, 'Archiv');
        $current_html = iss_register_render_place_context_comparison_image($current_image, 'Spätere Ansicht');
        if ($archive_html === '' && $current_html === '') {
            return '';
        }

        $paired = $archive_html !== '' && $current_html !== '';
        $grid_class = 'iss-register-place__comparison-grid' . ($paired ? '' : ' iss-register-place__comparison-grid--single');
        return '<section class="iss-register-place__comparison" aria-label="' . esc_attr($paired ? 'Archiv und spätere Ansicht' : 'Ansicht des Ortes') . '">'
            . '<div class="' . esc_attr($grid_class) . '">' . $archive_html . $current_html . '</div></section>';
    }

    if ($variant === 'questions') {
        $items = '';
        foreach ($context['questions'] as $question) {
            $question = is_scalar($question) ? trim((string) $question) : '';
            if ($question !== '') {
                $items .= '<li>' . esc_html($question) . '</li>';
            }
        }
        return $items === '' ? '' : '<ol class="iss-register-place__questions">' . $items . '</ol>';
    }

    if ($variant === 'current_data_grid') {
        $source_summary = $context['source_summary'] !== '' ? wp_trim_words(wp_strip_all_tags($context['source_summary']), 18, '…') : '';
        $website = esc_url_raw($context['website']);
        $website_label = $website !== '' ? preg_replace('/^www\./i', '', (string) wp_parse_url($website, PHP_URL_HOST)) : '';
        if (!is_string($website_label) || $website_label === '') {
            $website_label = $website;
        }

        $items = [
            ['label' => 'Eigentum', 'value' => $context['owner']],
            ['label' => 'Betreiber', 'value' => $context['operator']],
            ['label' => 'Projektentwicklung', 'value' => $context['developer']],
            ['label' => 'Nutzung / Mieter', 'value' => $context['tenant']],
            ['label' => 'Fläche', 'value' => $context['size']],
            ['label' => 'Investition', 'value' => $context['investment']],
            ['label' => 'Arbeitsplätze / Nutzung', 'value' => $context['jobs']],
            ['label' => 'Kaufpreis', 'value' => $context['kaufpreis']],
            ['label' => 'Website / Quelle', 'value' => $website_label, 'url' => $website],
            ['label' => 'Quellenhinweis', 'value' => $source_summary],
        ];
        $body = iss_register_render_place_context_data_items(
            $items,
            'wp-block-group iss-register-place__data-item',
            'iss-register-place__data-label',
            'iss-register-place__data-value'
        );

        if ($body === '') {
            return '';
        }

        return '<div class="wp-block-group iss-register-place__data-grid">' . $body . '</div>';
    }

    if ($variant === 'contribution_link') {
        $url = iss_register_get_place_contribution_url((int) $post_id);
        if ($url === '') {
            return '';
        }

        return '<a class="iss-register-place__upload-button" href="' . esc_url($url) . '">Fotos, Erinnerungen oder Korrekturen beitragen</a>';
    }

    if ($variant === 'hero_contribution') {
        $url = iss_register_get_place_contribution_url((int) $post_id);
        if ($url === '') {
            return '';
        }

        return '<a class="iss-register-place__hero-contribution" href="' . esc_url($url) . '">'
            . '<span class="iss-register-place__hero-contribution-kicker">Wissen Sie mehr?</span>'
            . '<span class="iss-register-place__hero-contribution-action">Fotos und Erinnerungen teilen <span aria-hidden="true">→</span></span>'
            . '</a>';
    }

    if ($variant === 'risk_potential') {
        $items = [
            ['label' => 'Potenzial', 'value' => $context['potential_note'], 'class' => 'iss-register-place__interpretation-item--potential'],
            ['label' => 'Risiko', 'value' => $context['risk_note'], 'class' => 'iss-register-place__interpretation-item--risk'],
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
