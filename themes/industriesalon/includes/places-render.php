<?php

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_editorial_place_is_enabled(int $post_id): bool
{
    return $post_id > 0
        && function_exists('iss_editorial_document_is_enabled')
        && iss_editorial_document_is_enabled($post_id, 'place');
}

function industriesalon_editorial_place_year_label(array $section): string
{
    if (!empty($section['milestone'])) {
        return iss_register_milestone_date_label($section['milestone']);
    }

    $start = absint($section['start_year'] ?? 0);
    $end = absint($section['end_year'] ?? 0);

    if ($start > 0 && $end > 0 && $start !== $end) {
        return sprintf('%d–%d', $start, $end);
    }
    if ($start > 0 && $end === $start) {
        return (string) $start;
    }
    if ($start > 0) {
        return sprintf(__('ab %d', 'industriesalon'), $start);
    }

    return '';
}

function industriesalon_editorial_place_definition_label(string $kind, string $key): string
{
    $labels = [
        'era' => [
            'kaiserzeit' => __('Kaiserzeit', 'industriesalon'),
            'weimar' => __('Weimarer Republik', 'industriesalon'),
            'ns-zeit' => __('NS-Zeit', 'industriesalon'),
            'nachkriegszeit' => __('Nachkriegszeit', 'industriesalon'),
            'ddr' => __('DDR', 'industriesalon'),
            'nach-1990' => __('Nach 1990', 'industriesalon'),
        ],
        'function' => [
            'industrial' => __('Industrie / Produktion', 'industriesalon'),
            'commercial' => __('Gewerbe / Handel', 'industriesalon'),
            'culture' => __('Kultur', 'industriesalon'),
            'education' => __('Bildung / Forschung', 'industriesalon'),
            'community' => __('Gemeinwohl / Soziales', 'industriesalon'),
            'residential' => __('Wohnen', 'industriesalon'),
            'mixed' => __('Mischnutzung', 'industriesalon'),
            'vacant' => __('Leerstand', 'industriesalon'),
            'infrastructure' => __('Infrastruktur', 'industriesalon'),
        ],
        'source' => [
            'oral' => __('Mündliche Quelle', 'industriesalon'),
            'archive' => __('Archivquelle', 'industriesalon'),
            'publication' => __('Publikation', 'industriesalon'),
            'url' => __('Webquelle', 'industriesalon'),
        ],
    ];

    return (string) ($labels[$kind][$key] ?? '');
}

function industriesalon_render_editorial_place_media(array $section, bool $all = false): string
{
    $figures = '';
    foreach ((array) ($section['media_refs_resolved'] ?? []) as $item) {
        $resolved = is_array($item['resolved'] ?? null) ? $item['resolved'] : [];
        $reference = is_array($item['reference'] ?? null) ? $item['reference'] : [];
        $attachment_id = absint($resolved['id'] ?? 0);
        if ($attachment_id <= 0 || strpos((string) get_post_mime_type($attachment_id), 'image/') !== 0) {
            continue;
        }

        $image = wp_get_attachment_image($attachment_id, 'large', false, [
            'class' => 'iss-register-place__epoch-image',
            'loading' => 'lazy',
        ]);
        if ($image === '') {
            continue;
        }

        $caption = trim((string) ($reference['label'] ?? $resolved['title'] ?? ''));
        $html = '<figure class="iss-register-place__epoch-figure">' . $image;
        if ($caption !== '') {
            $html .= '<figcaption class="iss-register-place__epoch-caption"><span class="iss-register-place__epoch-caption-text">'
                . esc_html($caption) . '</span></figcaption>';
        }

        $figures .= $html . '</figure>';
        if (!$all) {
            break;
        }
    }

    return $figures;
}

function industriesalon_render_editorial_place_epoch(array $section): string
{
    $title = trim((string) ($section['title'] ?? ''));
    if ($title === '') {
        return '';
    }

    $meta = array_values(array_filter([
        industriesalon_editorial_place_definition_label('era', sanitize_title((string) ($section['era_key'] ?? ''))),
        industriesalon_editorial_place_definition_label('function', sanitize_key((string) ($section['function_key'] ?? ''))),
    ]));
    $source_summary = trim((string) ($section['source_summary'] ?? ''));
    $source_label = industriesalon_editorial_place_definition_label('source', sanitize_key((string) ($section['source_confidence'] ?? '')));
    $source_refs = (array) ($section['source_refs'] ?? []);
    $media = industriesalon_render_editorial_place_media($section);
    $classes = ['iss-register-place__epoch-row'];
    if (!empty($section['is_current'])) {
        $classes[] = 'is-current';
    }
    if ($media !== '') {
        $classes[] = 'has-media';
    }

    $html = '<article class="' . esc_attr(implode(' ', $classes)) . '">';
    $html .= '<div class="iss-register-place__epoch-index"><p class="iss-register-place__epoch-years">'
        . esc_html(industriesalon_editorial_place_year_label($section)) . '</p></div>';
    $html .= '<div class="iss-register-place__epoch-body">';
    if ($meta) {
        $html .= '<p class="iss-register-place__epoch-meta">' . esc_html(implode(' · ', $meta)) . '</p>';
    }
    $html .= '<h3 class="iss-register-place__epoch-title">' . esc_html($title) . '</h3>';
    if (trim((string) ($section['body'] ?? '')) !== '') {
        $html .= '<div class="iss-register-place__epoch-text">' . wp_kses_post((string) $section['body']) . '</div>';
    }
    if ($source_summary !== '' || $source_refs) {
        $html .= '<details class="iss-register-place__epoch-sources"><summary>'
            . esc_html__('Quellenhinweis', 'industriesalon') . '</summary>';
        if ($source_label !== '') {
            $html .= '<p class="iss-register-place__epoch-source-confidence">' . esc_html($source_label) . '</p>';
        }
        if ($source_summary !== '') {
            $html .= '<p class="iss-register-place__epoch-source">' . esc_html($source_summary) . '</p>';
        }
        if ($source_refs) {
            $html .= '<ul class="iss-register-place__epoch-source-links">';
            foreach ($source_refs as $source_ref) {
                if (!is_array($source_ref) || empty($source_ref['url'])) {
                    continue;
                }
                $label = trim((string) ($source_ref['label'] ?? '')) ?: __('Quelle öffnen', 'industriesalon');
                $html .= '<li><a href="' . esc_url((string) $source_ref['url']) . '">' . esc_html($label) . '</a></li>';
            }
            $html .= '</ul>';
        }
        $html .= '</details>';
    }
    $html .= '</div>' . $media . '</article>';

    return industriesalon_editorial_preview_section($html, $section, ['title' => 'iss-register-place__epoch-title', 'body' => 'iss-register-place__epoch-text']);
}

/** Fixed dossier slots reuse the Place heading, prose and image components. */
function industriesalon_render_editorial_place_overview(array $section): string
{
    $intro = ($section['type'] ?? '') === 'intro';
    $title = trim((string) ($section['title'] ?? ''));
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $media = industriesalon_render_editorial_place_media($section, true);
    $links = $intro ? '' : industriesalon_render_editorial_links((array) ($section['links'] ?? []));
    if ($title === '' && $kicker === '' && $body === '' && $media === '' && $links === '') {
        return '';
    }
    $title = $title !== '' ? $title : ($intro ? __('Ort auf einen Blick', 'industriesalon') : __('Der Ort heute', 'industriesalon'));
    $kicker = $kicker !== '' ? $kicker : ($intro ? __('Kurzprofil', 'industriesalon') : __('Gegenwart', 'industriesalon'));
    $body_class = $intro ? 'iss-indent iss-indent--uncaged iss-register-place__lead iss-register-place__lead-primary' : 'iss-register-place__present-text';
    $html = '<section class="iss-register-place__section iss-register-place__section--' . ($intro ? 'lead' : 'present') . '">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
    $html .= '<h2 class="iss-register-place__section-title">' . esc_html($title) . '</h2>';
    if ($body !== '') {
        $html .= '<div class="' . esc_attr($body_class) . '">' . wp_kses_post($body) . '</div>';
    }
    $html .= $media . $links . '</section>';
    return industriesalon_editorial_preview_section($html, $section, [
        'title' => 'iss-register-place__section-title',
        'kicker' => 'iss-kicker',
        'body' => $intro ? 'iss-register-place__lead-primary' : 'iss-register-place__present-text',
    ]);
}

function industriesalon_render_editorial_place_document(int $post_id, array $document): string
{
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    $intro = '';
    $epochs = [];
    $present = '';
    $supplements = '';

    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }
        $type = (string) ($section['type'] ?? '');
        if ($type === 'intro') {
            // Existing repeated slots remain visible even though new insertion is limited.
            $intro .= industriesalon_render_editorial_place_overview($section);
        } elseif ($type === 'epoche') {
            $epochs[] = $section;
        } elseif ($type === 'gegenwart') {
            $present .= industriesalon_render_editorial_place_overview($section);
        } elseif (in_array($type, ['galerie', 'material', 'upload_intake'], true)) {
            // Reuse the shared archive/media section renderer and its CSS contract.
            $supplements .= industriesalon_render_editorial_ausstellung_section($section, false, 0, 'standard', 'place');
        }
    }

    $epochs = iss_register_merge_place_milestones($post_id, $epochs);

    $html = '<div class="iss-register-place-editorial iss-register-place-editorial--ortsdossier">' . $intro;
    if ($epochs) {
        $html .= '<section class="iss-register-place__section iss-register-place__section--epochs">';
        $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Epochen', 'industriesalon') . '</p>';
        $html .= '<h2 class="iss-register-place__section-title">' . esc_html__('Schichten des Ortes', 'industriesalon') . '</h2>';
        $html .= '<div class="iss-register-place__epoch-rail">';
        foreach ($epochs as $epoch) {
            $html .= industriesalon_render_editorial_place_epoch($epoch);
        }
        $html .= '</div></section>';
    }
    return $html . $present . $supplements . '</div>';
}

function industriesalon_render_editorial_place_fallback(int $post_id): string
{
    $history = trim((string) get_post_meta($post_id, 'history_short', true));
    $current_use = trim((string) get_post_meta($post_id, 'current_use', true));
    $html = '<div class="iss-register-place-editorial iss-register-place-editorial--legacy">';
    if ($history !== '' || $current_use !== '') {
        $html .= '<section class="iss-register-place__section iss-register-place__section--lead">';
        $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Kurzprofil', 'industriesalon') . '</p>';
        $html .= '<h2 class="iss-register-place__section-title">' . esc_html__('Ort auf einen Blick', 'industriesalon') . '</h2>';
        $html .= '<div class="iss-indent iss-indent--uncaged iss-register-place__lead">';
        if ($history !== '') {
            $html .= '<p class="iss-register-place__lead-primary">' . esc_html($history) . '</p>';
        }
        if ($current_use !== '') {
            $html .= '<p class="iss-register-place__lead-secondary">' . esc_html($current_use) . '</p>';
            $current_source = get_post_meta($post_id, '_iss_register_current_import', true);
            if (is_array($current_source) && !empty($current_source['source_url'])) {
                $html .= '<p><a href="' . esc_url($current_source['source_url']) . '">' . esc_html__('Quelle der aktuellen Angaben: Standortregister', 'industriesalon') . '</a></p>';
            }
        }
        $html .= '</div></section>';
    }
    if (function_exists('iss_register_render_place_context')) {
        $epochs = iss_register_render_place_context(['variant' => 'epoch_rail']);
        $comparison = iss_register_render_place_context(['variant' => 'then_now']);
        if ($epochs !== '' || $comparison !== '') {
            $html .= '<section class="iss-register-place__section iss-register-place__section--epochs">';
            $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Epochen', 'industriesalon') . '</p>';
            $html .= '<h2 class="iss-register-place__section-title">' . esc_html__('Schichten des Ortes', 'industriesalon') . '</h2>';
            $html .= $epochs . $comparison . '</section>';
        }
    }

    return $html . '</div>';
}

function industriesalon_render_editorial_place_block(): string
{
    if (is_admin() || !is_singular('register_place')) {
        return '';
    }

    $post_id = (int) get_queried_object_id();
    if (
        industriesalon_editorial_place_is_enabled($post_id)
        && function_exists('iss_editorial_get_read_model')
    ) {
        $document = iss_editorial_get_read_model($post_id, 'place', is_preview() && current_user_can('edit_post', $post_id));
        if (!empty($document['sections'])) {
            return industriesalon_render_editorial_place_document($post_id, $document);
        }
    }

    return industriesalon_render_editorial_place_fallback($post_id);
}

function industriesalon_register_editorial_place_block(): void
{
    register_block_type('industriesalon/editorial-place', [
        'api_version' => 2,
        'render_callback' => 'industriesalon_render_editorial_place_block',
    ]);
}
add_action('init', 'industriesalon_register_editorial_place_block');
