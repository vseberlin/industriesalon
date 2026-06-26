<?php

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_get_editorial_publication_skins(): array
{
    return [
        'standard',
        'blueprint-matrix',
    ];
}

add_filter('iss_editorial_format_skins', function (array $skins, string $format_slug): array {
    if ($format_slug !== 'publication') {
        return $skins;
    }

    return [
        'standard' => [
            'slug' => 'standard',
            'label' => __('Standard', 'industriesalon'),
        ],
        'blueprint-matrix' => [
            'slug' => 'blueprint-matrix',
            'label' => __('Blueprint-Matrix', 'industriesalon'),
        ],
    ];
}, 10, 2);

function industriesalon_resolve_editorial_publication_skin(array $document): string
{
    $skin = sanitize_key((string) ($document['skin'] ?? 'standard'));

    return in_array($skin, industriesalon_get_editorial_publication_skins(), true) ? $skin : 'standard';
}

function industriesalon_get_editorial_publication_post_skin(int $post_id): string
{
    static $cache = [];

    if (
        $post_id <= 0
        || !function_exists('iss_editorial_document_is_enabled')
        || !iss_editorial_document_is_enabled($post_id, 'publication')
    ) {
        return '';
    }

    if (array_key_exists($post_id, $cache)) {
        return $cache[$post_id];
    }

    $document = [];
    if (function_exists('iss_editorial_get_document_meta_key')) {
        $stored = get_post_meta($post_id, iss_editorial_get_document_meta_key('publication'), true);
        if (is_string($stored) && trim($stored) !== '') {
            $decoded = json_decode($stored, true);
            $document = is_array($decoded) ? $decoded : [];
        }
    }

    if ($document === [] && function_exists('iss_editorial_get_document')) {
        $document = iss_editorial_get_document($post_id, 'publication', false);
    }

    $cache[$post_id] = industriesalon_resolve_editorial_publication_skin($document);

    return $cache[$post_id];
}

function industriesalon_publications_resolve_longread_payload(int $post_id, ?array $payload = null): array
{
    if (is_array($payload) && $payload !== []) {
        return $payload;
    }

    if (!function_exists('iss_publications_get_longread_payload')) {
        return [];
    }

    $payload = iss_publications_get_longread_payload($post_id);
    return is_array($payload) ? $payload : [];
}

function industriesalon_publications_resolve_longread_payload_from_content(int $post_id, string $content): array
{
    static $cache = [];

    $post_id = (int) $post_id;
    $content = (string) $content;
    if ($post_id <= 0 || trim($content) === '') {
        return [];
    }

    $cache_key = $post_id . ':' . md5($content);
    if (!array_key_exists($cache_key, $cache)) {
        if (function_exists('iss_publications_parse_longread_payload')) {
            $cache[$cache_key] = iss_publications_parse_longread_payload($post_id, $content);
        } else {
            $cache[$cache_key] = industriesalon_publications_resolve_longread_payload($post_id);
        }
    }

    return is_array($cache[$cache_key]) ? $cache[$cache_key] : [];
}

function industriesalon_publications_replace_inline_css_var(string $html, string $var_name, string $value): string
{
    $pattern = '/(' . preg_quote($var_name, '/') . '\s*:\s*)[^;"]+/';
    if (!preg_match($pattern, $html)) {
        return $html;
    }

    return (string) preg_replace_callback(
        $pattern,
        static function (array $matches) use ($value): string {
            return (string) ($matches[1] ?? '') . $value;
        },
        $html,
        1
    );
}

function industriesalon_publications_transform_essay_map_html(string $html): string
{
    $html = trim($html);
    if ($html === '' || strpos($html, 'iss-related-place-map') === false) {
        return $html;
    }

    $html = preg_replace(
        '/iss-related-place-map(\s+)wp-block-iss-related-place-map/',
        'iss-related-place-map iss-related-place-map--essay$1wp-block-iss-related-place-map',
        $html,
        1
    );
    $html = str_replace('iss-related-place-map__body--panel-right', 'iss-related-place-map__body--panel-below', $html);
    $html = industriesalon_publications_replace_inline_css_var($html, '--iss-related-place-map-scale-x', '2.8000');
    $html = industriesalon_publications_replace_inline_css_var($html, '--iss-related-place-map-scale-y', '2.4500');
    $html = industriesalon_publications_replace_inline_css_var($html, '--iss-related-place-map-offset-x', '-106.710%');
    $html = industriesalon_publications_replace_inline_css_var($html, '--iss-related-place-map-offset-y', '-74.308%');

    return $html;
}

function industriesalon_publications_render_longread_summary(array $summary): string
{
    if (!$summary) {
        return '';
    }

    $html = '<div class="iss-publication-longread__summary" aria-label="' . esc_attr__('Rahmendaten', 'industriesalon') . '">';
    foreach ($summary as $item) {
        $html .= '<p class="iss-publication-longread__summary-item"><strong>' . esc_html((string) ($item['value'] ?? '')) . '</strong><span>' . esc_html((string) ($item['label'] ?? '')) . '</span></p>';
    }
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_render_publication_rail_head(array $settings): string
{
    $kicker = trim((string) ($settings['kicker'] ?? ''));
    $title = trim((string) ($settings['title'] ?? ''));
    if ($kicker === '' && $title === '') {
        return '';
    }

    $html = '<div class="iss-publication-reading-rail__head">';
    if ($kicker !== '') {
        $html .= '<p class="iss-publication-reading-rail__kicker">' . esc_html($kicker) . '</p>';
    }
    if ($title !== '') {
        $html .= '<h2 class="iss-publication-reading-rail__title">' . esc_html($title) . '</h2>';
    }
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_render_longread_nav(array $nav_items, array $rail_settings = []): string
{
    if (!$nav_items) {
        return '';
    }

    $html = '<nav class="iss-reading-nav iss-publication-longread__nav" data-reading-nav-mode="stack" aria-label="' . esc_attr__('Kapitelnavigation', 'industriesalon') . '">';
    $html .= industriesalon_publications_render_publication_rail_head($rail_settings);
    $html .= '<div class="iss-reading-nav__inner iss-publication-longread__nav-inner">';
    foreach ($nav_items as $item) {
        $href = trim((string) ($item['href'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));
        if ($href === '' || $label === '') {
            continue;
        }

        $html .= '<a href="' . esc_attr($href) . '">' . esc_html($label) . '</a>';
    }
    $html .= '</div></nav>';

    return $html;
}

function industriesalon_publications_render_longread_bridge(int $post_id): string
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_name !== 'schoeneweide-eine-ortsgeschichte') {
        return '';
    }

    $cards = [
        [
            'kicker' => __('Atlas', 'industriesalon'),
            'title' => __('Schoneweide raeumlich erkunden', 'industriesalon'),
            'text' => __('Orte, Epochen und Umbrueche im Atlas oeffnen und denselben Raum nicht linear, sondern als Netz von Dossiers lesen.', 'industriesalon'),
            'links' => [
                [
                    'label' => __('Zum Atlas', 'industriesalon'),
                    'url' => '/schoneweide/#atlas-buehne',
                ],
                [
                    'label' => __('Zur Uebersicht', 'industriesalon'),
                    'url' => '/schoneweide/',
                ],
            ],
        ],
        [
            'kicker' => __('Atlas', 'industriesalon'),
            'title' => __('Orte und Rollen im Atlas', 'industriesalon'),
            'text' => __('Wer direkt nach Werken, Strassen, Instituten oder Nachnutzungen sucht, bekommt im Atlas den raeumlichen Einstieg.', 'industriesalon'),
            'links' => [
                [
                    'label' => __('Zum Atlas', 'industriesalon'),
                    'url' => '/schoneweide/#atlas-buehne',
                ],
                [
                    'label' => __('Fuehrungen vor Ort', 'industriesalon'),
                    'url' => '/fuehrungen/',
                ],
            ],
        ],
    ];

    $html = '<aside class="iss-publication-longread__bridge">';
    $html .= '<div class="iss-heading iss-heading--uncaged iss-publication-longread__bridge-intro">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Weiter erkunden', 'industriesalon') . '</p>';
    $html .= '<h2 class="iss-heading__title">' . esc_html__('Dieselbe Ortsgeschichte laesst sich auch als Atlas lesen.', 'industriesalon') . '</h2>';
    $html .= '<p class="iss-heading__text">' . esc_html__('Die Publikation fuehrt linear durch Schoneweide. Atlas und Register oeffnen denselben Raum parallel als Orte, Geschichten und Dossiers.', 'industriesalon') . '</p>';
    $html .= '</div>';
    $html .= '<div class="iss-publication-longread__bridge-grid">';

    foreach ($cards as $card) {
        $html .= '<article class="iss-publication-longread__bridge-card">';
        $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html((string) $card['kicker']) . '</p>';
        $html .= '<h3 class="iss-publication-longread__bridge-title">' . esc_html((string) $card['title']) . '</h3>';
        $html .= '<p class="iss-publication-longread__bridge-text">' . esc_html((string) $card['text']) . '</p>';
        $html .= '<p class="iss-publication-longread__bridge-links">';
        foreach ($card['links'] as $link) {
            $html .= '<a class="iss-action-link" href="' . esc_url((string) $link['url']) . '">' . esc_html((string) $link['label']) . '</a> ';
        }
        $html .= '</p>';
        $html .= '</article>';
    }

    $html .= '</div>';
    $html .= '</aside>';

    return $html;
}

function industriesalon_publications_render_longread_rail_cell(string $title, string $url = '', string $meta = ''): string
{
    $title = trim($title);
    if ($title === '') {
        return '';
    }

    $title_html = $url !== ''
        ? '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>'
        : esc_html($title);

    $html = '<div class="iss-publication-longread__rail-cell">';
    if ($meta !== '') {
        $html .= '<span class="iss-publication-longread__rail-cell-meta">' . esc_html($meta) . '</span>';
    }
    $html .= '<span class="iss-publication-longread__rail-cell-title">' . $title_html . '</span>';
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_render_longread_rail_group(string $title, array $cells): string
{
    $cells = array_values(array_filter($cells));
    if ($cells === []) {
        return '';
    }

    return '<section class="iss-publication-longread__rail-group">'
        . '<h2 class="iss-publication-longread__rail-heading">' . esc_html($title) . '</h2>'
        . '<div class="iss-publication-longread__rail-cells">' . implode('', $cells) . '</div>'
        . '</section>';
}

function industriesalon_publications_is_sale_brochure(int $post_id): bool
{
    return $post_id > 0
        && get_post_type($post_id) === 'publication'
        && function_exists('iss_publications_get_layout')
        && function_exists('iss_publications_sale_enabled')
        && function_exists('iss_publications_get_collection_kind')
        && iss_publications_get_layout($post_id) === 'standard'
        && iss_publications_sale_enabled($post_id)
        && iss_publications_get_collection_kind($post_id) === 'brochure';
}

add_filter('template_include', function (string $template): string {
    if (!is_singular('publication')) {
        return $template;
    }

    $post_id = (int) get_queried_object_id();
    if (!industriesalon_publications_is_sale_brochure($post_id)) {
        return $template;
    }

    $brochure_template = get_stylesheet_directory() . '/single-publication-brochure.php';
    return file_exists($brochure_template) ? $brochure_template : $template;
}, 40);

function industriesalon_publications_render_brochure_fact_strip(int $post_id): string
{
    if ($post_id <= 0 || !function_exists('iss_publications_get_summary_meta')) {
        return '';
    }

    $summary_meta = iss_publications_get_summary_meta($post_id);
    if (!is_array($summary_meta) || $summary_meta === []) {
        return '';
    }

    $priority = [
        __('Autor:in', 'iss-publications'),
        __('Untertitel', 'iss-publications'),
        __('Seiten', 'iss-publications'),
        __('Preis', 'iss-publications'),
        __('Format', 'iss-publications'),
        __('Sprache', 'iss-publications'),
        __('Verlag', 'iss-publications'),
    ];

    $items = [];
    foreach ($priority as $label) {
        if (!isset($summary_meta[$label])) {
            continue;
        }

        $items[$label] = $summary_meta[$label];
        unset($summary_meta[$label]);
        if (count($items) >= 4) {
            break;
        }
    }

    foreach ($summary_meta as $label => $value) {
        if (count($items) >= 4) {
            break;
        }
        $items[(string) $label] = $value;
    }

    if ($items === []) {
        return '';
    }

    $html = '<dl class="iss-publication-brochure__facts" aria-label="' . esc_attr__('Bibliografische Daten', 'industriesalon') . '">';
    foreach ($items as $label => $value) {
        $html .= '<div class="iss-publication-brochure__fact">';
        $html .= '<dt>' . esc_html((string) $label) . '</dt>';
        $html .= '<dd>' . esc_html((string) $value) . '</dd>';
        $html .= '</div>';
    }
    $html .= '</dl>';

    return $html;
}

function industriesalon_publications_render_brochure_related_cell(string $title, string $url = '', string $meta = ''): string
{
    $title = trim($title);
    if ($title === '') {
        return '';
    }

    $title_html = $url !== ''
        ? '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>'
        : esc_html($title);

    $html = '<div class="iss-publication-brochure__related-cell">';
    if ($meta !== '') {
        $html .= '<span class="iss-publication-brochure__related-meta">' . esc_html($meta) . '</span>';
    }
    $html .= '<span class="iss-publication-brochure__related-title">' . $title_html . '</span>';
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_render_brochure_related_group(string $title, array $cells): string
{
    $cells = array_values(array_filter($cells));
    if ($cells === []) {
        return '';
    }

    return '<section class="iss-publication-brochure__related-group">'
        . '<h3 class="iss-publication-brochure__related-heading">' . esc_html($title) . '</h3>'
        . '<div class="iss-publication-brochure__related-cells">' . implode('', $cells) . '</div>'
        . '</section>';
}

function industriesalon_publications_find_profile_permalink_by_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    if (!function_exists('iss_graph_get_entity_profile_post_type')) {
        return '';
    }

    $posts = get_posts([
        'post_type' => iss_graph_get_entity_profile_post_type(),
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'title' => $name,
        'suppress_filters' => true,
    ]);

    if ($posts === []) {
        return '';
    }

    $post = $posts[0];
    return $post instanceof WP_Post ? (string) get_permalink($post) : '';
}

function industriesalon_publications_parse_person_meta_names(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/\s*(?:,|\/| und | & )\s*/u', $value);
    if (!is_array($parts)) {
        return [];
    }

    $names = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $names[] = $part;
        }
    }

    return array_values(array_unique($names));
}

function industriesalon_publications_get_brochure_person_cells(int $post_id): array
{
    $cells = [];
    $seen = [];

    if (function_exists('iss_graph_get_content_entity_relations')) {
        foreach (iss_graph_get_content_entity_relations($post_id, 'person', ['limit' => 6]) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $link = function_exists('iss_graph_get_entity_relation_public_permalink')
                ? iss_graph_get_entity_relation_public_permalink($row)
                : '';
            $relation_label = trim((string) ($row['relation_label'] ?? ''));
            $meta = $relation_label !== '' ? $relation_label : __('Person', 'industriesalon');

            $cells[] = industriesalon_publications_render_brochure_related_cell($name, $link, $meta);
            $seen[sanitize_title($name)] = true;
        }
    }

    if (!function_exists('iss_publications_get_meta')) {
        return $cells;
    }

    $fallback_fields = [
        '_iss_publication_author' => __('Autor:in', 'industriesalon'),
    ];

    foreach ($fallback_fields as $meta_key => $label) {
        foreach (industriesalon_publications_parse_person_meta_names((string) iss_publications_get_meta($post_id, $meta_key, '')) as $name) {
            $slug = sanitize_title($name);
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }

            $cells[] = industriesalon_publications_render_brochure_related_cell(
                $name,
                industriesalon_publications_find_profile_permalink_by_name($name),
                $label
            );
            $seen[$slug] = true;
        }
    }

    return $cells;
}

function industriesalon_publications_render_brochure_related(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }

    $groups = [];

    $publication_cells = [];
    $publication_posts = [];
    if (function_exists('iss_publications_get_related_publication_ids')) {
        $related_ids = iss_publications_get_related_publication_ids($post_id);
        if ($related_ids !== []) {
            $publication_posts = get_posts([
                'post_type' => 'publication',
                'post_status' => 'publish',
                'posts_per_page' => count($related_ids),
                'post__in' => $related_ids,
                'orderby' => 'post__in',
                'suppress_filters' => true,
            ]);
        }
    }
    if ($publication_posts === []) {
        $publication_posts = industriesalon_publications_get_related_rail_posts($post_id, [
            'postType' => 'publication',
            'perPage' => 3,
            'showImage' => false,
        ]);
    }

    foreach ($publication_posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $publication_cells[] = industriesalon_publications_render_brochure_related_cell(
            get_the_title($post),
            (string) get_permalink($post),
            industriesalon_publications_get_rail_cell_meta($post)
        );
    }
    $groups[] = industriesalon_publications_render_brochure_related_group(__('Weitere Publikationen', 'industriesalon'), $publication_cells);

    $place_cells = [];
    if (function_exists('iss_relations_get_related_place_items')) {
        foreach (array_slice(iss_relations_get_related_place_items($post_id), 0, 6) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $url = trim((string) ($item['permalink'] ?? ''));
            $role = sanitize_key((string) ($item['role'] ?? ''));
            $role_options = function_exists('iss_relations_get_role_options') ? iss_relations_get_role_options() : [];
            $meta = trim((string) ($item['label'] ?? ''));
            $meta = $meta !== '' ? $meta : (string) ($role_options[$role] ?? __('Ort', 'industriesalon'));

            $place_cells[] = industriesalon_publications_render_brochure_related_cell($title, $url, $meta);
        }
    }
    $groups[] = industriesalon_publications_render_brochure_related_group(__('Orte', 'industriesalon'), $place_cells);

    $person_cells = industriesalon_publications_get_brochure_person_cells($post_id);
    $groups[] = industriesalon_publications_render_brochure_related_group(__('Personen', 'industriesalon'), $person_cells);

    $groups = array_values(array_filter($groups));
    if ($groups === []) {
        return '';
    }

    return '<section class="iss-publication-brochure__related" aria-label="' . esc_attr__('Im Zusammenhang', 'industriesalon') . '">'
        . '<h2 class="iss-publication-brochure__section-title">' . esc_html__('Im Zusammenhang', 'industriesalon') . '</h2>'
        . '<div class="iss-publication-brochure__related-grid">' . implode('', $groups) . '</div>'
        . '</section>';
}

function industriesalon_publications_render_photoalbum_nav(array $sheets, array $rail_settings = []): string
{
    if ($sheets === []) {
        return '';
    }

    $html = '<nav class="iss-reading-nav iss-publication-photoalbum__nav" data-reading-nav-mode="stack" aria-label="' . esc_attr__('Albumnavigation', 'industriesalon') . '">';
    $html .= industriesalon_publications_render_publication_rail_head($rail_settings);
    $html .= '<div class="iss-reading-nav__inner iss-publication-photoalbum__nav-inner">';

    foreach ($sheets as $sheet) {
        if (!is_array($sheet)) {
            continue;
        }

        $anchor = trim((string) ($sheet['anchor'] ?? ''));
        $sheet_label = trim((string) ($sheet['sheet_label'] ?? ''));
        $title = trim((string) ($sheet['title'] ?? ''));
        if ($anchor === '' || $sheet_label === '') {
            continue;
        }

        $html .= '<a href="#' . esc_attr($anchor) . '">';
        $html .= '<span class="iss-publication-photoalbum__nav-page">' . esc_html($sheet_label) . '</span>';
        $html .= '<span class="iss-publication-photoalbum__nav-title">' . esc_html($title !== '' ? $title : $sheet_label) . '</span>';
        $html .= '</a>';
    }

    $html .= '</div></nav>';

    return $html;
}

function industriesalon_publications_render_photoalbum_item(array $sheet): string
{
    $anchor = trim((string) ($sheet['anchor'] ?? ''));
    $sheet_label = trim((string) ($sheet['sheet_label'] ?? ''));
    $title = trim((string) ($sheet['title'] ?? ''));
    $caption_text = trim((string) ($sheet['caption_text'] ?? ''));
    $media_html = trim((string) ($sheet['media_html'] ?? ''));
    $image_url = trim((string) ($sheet['image_url'] ?? ''));

    if ($anchor === '' || $media_html === '') {
        return '';
    }

    $caption_duplicate = $caption_text !== '' && $title !== ''
        && sanitize_title($caption_text) === sanitize_title($title);

    $html = '<article id="' . esc_attr($anchor) . '" class="iss-publication-photoalbum__item">';
    $html .= '<figure class="iss-publication-photoalbum__figure">';
    $html .= wp_kses_post($media_html);
    $html .= '</figure>';
    $html .= '<div class="iss-publication-photoalbum__copy">';
    if ($sheet_label !== '') {
        $html .= '<p class="iss-kicker iss-kicker--compact iss-publication-photoalbum__item-page">' . esc_html($sheet_label) . '</p>';
    }
    if ($title !== '') {
        $html .= '<h3 class="iss-publication-photoalbum__item-title">' . esc_html($title) . '</h3>';
    }
    if ($caption_text !== '' && !$caption_duplicate) {
        $html .= '<div class="iss-publication-photoalbum__item-text"><p>' . esc_html($caption_text) . '</p></div>';
    }
    if ($image_url !== '') {
        $html .= '<p class="iss-publication-photoalbum__item-actions"><a class="iss-action-link" href="' . esc_url($image_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Detailansicht oeffnen', 'industriesalon') . '</a></p>';
    }
    $html .= '</div>';
    $html .= '</article>';

    return $html;
}

function industriesalon_publications_blueprint_coordinate(int $index): string
{
    $columns = ['A', 'B', 'C', 'D'];

    return $columns[$index % 4] . (string) (int) floor($index / 4 + 1);
}

function industriesalon_publications_blueprint_source_label(array $payload): string
{
    $archive_title = trim((string) ($payload['source_archivset_title'] ?? ''));
    if ($archive_title !== '') {
        return $archive_title;
    }

    $editorial_title = trim((string) ($payload['source_editorial_set_title'] ?? ''));
    if ($editorial_title !== '') {
        return $editorial_title;
    }

    $manual_title = trim((string) ($payload['source_manual_title'] ?? ''));
    if ($manual_title !== '') {
        return $manual_title;
    }

    if (!empty($payload['source_archivset_id'])) {
        return sprintf(
            /* translators: %d: archive set ID. */
            __('Archivset %d', 'industriesalon'),
            absint($payload['source_archivset_id'])
        );
    }

    if (!empty($payload['source_editorial_set_id'])) {
        return sprintf(
            /* translators: %d: editorial set ID. */
            __('Set %d', 'industriesalon'),
            absint($payload['source_editorial_set_id'])
        );
    }

    return __('Redaktion', 'industriesalon');
}

function industriesalon_publications_render_blueprint_fact_grid(int $post_id, array $payload, array $sheets): string
{
    $format = function_exists('iss_publications_get_meta')
        ? trim((string) iss_publications_get_meta($post_id, '_iss_publication_format', ''))
        : '';
    $year = function_exists('iss_publications_get_meta')
        ? trim((string) iss_publications_get_meta($post_id, '_iss_publication_year', ''))
        : '';
    $sheet_count = count($sheets);

    $facts = [
        [
            'label' => __('Quelle', 'industriesalon'),
            'value' => industriesalon_publications_blueprint_source_label($payload),
        ],
        [
            'label' => __('Jahr', 'industriesalon'),
            'value' => $year !== '' ? $year : __('o. J.', 'industriesalon'),
        ],
        [
            'label' => __('Umfang', 'industriesalon'),
            'value' => sprintf(
                /* translators: %d: number of album sheets. */
                _n('%d Blatt', '%d Blaetter', $sheet_count, 'industriesalon'),
                $sheet_count
            ),
        ],
        [
            'label' => __('Format', 'industriesalon'),
            'value' => $format !== '' ? $format : __('Fotoalbum', 'industriesalon'),
        ],
    ];

    $html = '<dl class="iss-publication-photoalbum-blueprint__facts" aria-label="' . esc_attr__('Albumdaten', 'industriesalon') . '">';
    foreach ($facts as $fact) {
        $html .= '<div class="iss-publication-photoalbum-blueprint__fact">';
        $html .= '<dt>' . esc_html((string) $fact['label']) . '</dt>';
        $html .= '<dd>' . esc_html((string) $fact['value']) . '</dd>';
        $html .= '</div>';
    }
    $html .= '</dl>';

    return $html;
}

function industriesalon_publications_get_blueprint_sheet_media_html(array $sheet): string
{
    $title = trim((string) ($sheet['title'] ?? ''));
    $sheet_label = trim((string) ($sheet['sheet_label'] ?? ''));
    $alt_text = $title !== '' ? $title : $sheet_label;
    $image_attr = [
        'alt' => $alt_text,
        'loading' => 'lazy',
        'decoding' => 'async',
        'sizes' => '(max-width: 720px) 46vw, (max-width: 1180px) 30vw, 22vw',
    ];

    $attachment_id = absint($sheet['attachment_id'] ?? 0);
    if ($attachment_id > 0 && get_post_type($attachment_id) === 'attachment') {
        $image_html = wp_get_attachment_image($attachment_id, 'medium', false, $image_attr);
        if (is_string($image_html) && trim($image_html) !== '') {
            return trim($image_html);
        }
    }

    $archive_object_id = absint($sheet['archive_object_id'] ?? 0);
    if ($archive_object_id > 0) {
        $thumbnail_id = absint(get_post_thumbnail_id($archive_object_id));
        if ($thumbnail_id > 0) {
            $image_html = wp_get_attachment_image($thumbnail_id, 'medium', false, $image_attr);
            if (is_string($image_html) && trim($image_html) !== '') {
                return trim($image_html);
            }
        }
    }

    return trim((string) ($sheet['media_html'] ?? ''));
}

function industriesalon_publications_render_blueprint_sheet(array $sheet, int $index): string
{
    $anchor = trim((string) ($sheet['anchor'] ?? ''));
    $sheet_label = trim((string) ($sheet['sheet_label'] ?? ''));
    $title = trim((string) ($sheet['title'] ?? ''));
    $caption_text = trim((string) ($sheet['caption_text'] ?? ''));
    $media_html = industriesalon_publications_get_blueprint_sheet_media_html($sheet);
    $image_url = trim((string) ($sheet['image_url'] ?? ''));

    if ($anchor === '' || $media_html === '') {
        return '';
    }

    $coordinate = industriesalon_publications_blueprint_coordinate($index);
    $caption_duplicate = $caption_text !== '' && $title !== ''
        && sanitize_title($caption_text) === sanitize_title($title);
    $has_details = ($caption_text !== '' && !$caption_duplicate) || $image_url !== '';

    $html = '<article id="' . esc_attr($anchor) . '" class="iss-publication-photoalbum__item iss-publication-photoalbum-blueprint__cell">';
    $html .= '<div class="iss-publication-photoalbum-blueprint__plate">';
    $html .= '<span class="iss-publication-photoalbum-blueprint__coord">' . esc_html($coordinate) . '</span>';
    $html .= '<figure class="iss-publication-photoalbum__figure iss-publication-photoalbum-blueprint__figure">';
    $html .= wp_kses_post($media_html);
    $html .= '</figure>';
    $html .= '</div>';
    $html .= '<div class="iss-publication-photoalbum__copy iss-publication-photoalbum-blueprint__copy">';
    $html .= '<p class="iss-publication-photoalbum__item-page iss-publication-photoalbum-blueprint__page">' . esc_html($sheet_label !== '' ? $sheet_label : $coordinate) . '</p>';
    if ($title !== '') {
        $html .= '<h3 class="iss-publication-photoalbum__item-title iss-publication-photoalbum-blueprint__title">' . esc_html($title) . '</h3>';
    }
    $html .= '</div>';

    if ($has_details) {
        $html .= '<details class="iss-publication-photoalbum-blueprint__details">';
        $html .= '<summary>' . esc_html__('Beschreibung', 'industriesalon') . '</summary>';
        $html .= '<div class="iss-publication-photoalbum-blueprint__details-panel">';
        if ($caption_text !== '' && !$caption_duplicate) {
            $html .= '<p>' . esc_html($caption_text) . '</p>';
        }
        if ($image_url !== '') {
            $html .= '<p class="iss-publication-photoalbum-blueprint__detail-action"><a class="iss-action-link" href="' . esc_url($image_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Detailansicht oeffnen', 'industriesalon') . '</a></p>';
        }
        $html .= '</div>';
        $html .= '</details>';
    }

    $html .= '</article>';

    return $html;
}

function industriesalon_publications_render_blueprint_context_group(string $class_name, string $kicker, string $title, array $items): string
{
    $items = array_values(array_filter($items, 'is_array'));
    if ($items === []) {
        return '';
    }

    $items_html = '';
    foreach ($items as $item) {
        $item_title = trim((string) ($item['title'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        $meta = trim((string) ($item['meta'] ?? ''));
        if ($item_title === '' || $url === '') {
            continue;
        }

        $items_html .= '<li class="' . esc_attr($class_name . '__item') . '">';
        $items_html .= '<a class="' . esc_attr($class_name . '__link') . '" href="' . esc_url($url) . '">' . esc_html($item_title) . '</a>';
        if ($meta !== '') {
            $items_html .= '<span class="' . esc_attr($class_name . '__meta') . '">' . esc_html($meta) . '</span>';
        }
        $items_html .= '</li>';
    }

    if ($items_html === '') {
        return '';
    }

    $html = '<aside class="' . esc_attr($class_name) . '">';
    $html .= '<div class="' . esc_attr($class_name . '__head') . '">';
    if ($kicker !== '') {
        $html .= '<p class="' . esc_attr($class_name . '__kicker') . '">' . esc_html($kicker) . '</p>';
    }
    if ($title !== '') {
        $html .= '<h3 class="' . esc_attr($class_name . '__title') . '">' . esc_html($title) . '</h3>';
    }
    $html .= '</div>';
    $html .= '<ul class="' . esc_attr($class_name . '__list') . '">';
    $html .= $items_html;
    $html .= '</ul>';
    $html .= '</aside>';

    return $html;
}

function industriesalon_publications_render_blueprint_footer_context(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }

    $place_items = [];
    if (function_exists('iss_relations_get_related_place_items')) {
        foreach (array_slice(iss_relations_get_related_place_items($post_id), 0, 4) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $role = sanitize_key((string) ($item['role'] ?? ''));
            $role_options = function_exists('iss_relations_get_role_options') ? iss_relations_get_role_options() : [];
            $meta = trim((string) ($item['label'] ?? ''));
            $place_items[] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'url' => trim((string) ($item['permalink'] ?? '')),
                'meta' => $meta !== '' ? $meta : (string) ($role_options[$role] ?? __('Ort', 'industriesalon')),
            ];
        }
    }

    $related_items = [];
    foreach (industriesalon_publications_get_related_rail_posts($post_id, [
        'postTypes' => ['ausstellung', 'veranstaltung', 'publication', 'fuehrung', 'post', 'archivbeitrag'],
        'perPage' => 4,
        'source' => 'entity',
        'showImage' => false,
        'showExcerpt' => false,
    ]) as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $related_items[] = [
            'title' => get_the_title($post),
            'url' => (string) get_permalink($post),
            'meta' => industriesalon_publications_get_rail_cell_meta($post),
        ];
    }

    $groups = array_values(array_filter([
        industriesalon_publications_render_blueprint_context_group(
            'iss-related-place-links',
            __('Orte', 'industriesalon'),
            __('Orte im Album', 'industriesalon'),
            $place_items
        ),
        industriesalon_publications_render_blueprint_context_group(
            'iss-related-network-group',
            __('Kontext', 'industriesalon'),
            __('Verwandte Inhalte', 'industriesalon'),
            $related_items
        ),
    ], static function (string $html): bool {
        return $html !== '';
    }));

    if (!$groups) {
        return '';
    }

    return '<footer class="iss-publication-photoalbum-blueprint__context iss-publication-photoalbum-blueprint__context--footer" aria-label="' . esc_attr__('Albumkontext', 'industriesalon') . '">'
        . implode('', $groups)
        . '</footer>';
}

function industriesalon_publications_render_photoalbum_blueprint_matrix(int $post_id, array $payload): string
{
    $sheets = is_array($payload['sheets'] ?? null) ? array_values(array_filter($payload['sheets'], 'is_array')) : [];
    if ($post_id <= 0 || $sheets === []) {
        return '';
    }

    $items_html = '';
    foreach ($sheets as $index => $sheet) {
        $items_html .= industriesalon_publications_render_blueprint_sheet($sheet, (int) $index);
    }
    if ($items_html === '') {
        return '';
    }

    $post_title = get_the_title($post_id);
    $heading_kicker = trim((string) ($payload['heading_kicker'] ?? ''));
    $heading_text = trim((string) ($payload['heading_text'] ?? ''));
    $intro_html = trim((string) ($payload['intro_html'] ?? ''));
    $source_html = trim((string) ($payload['source_html'] ?? ''));

    $html = '<div class="iss-publication-photoalbum iss-publication-photoalbum--blueprint-matrix">';
    $html .= '<div class="iss-publication-photoalbum-blueprint__head">';
    $html .= '<div class="iss-publication-photoalbum-blueprint__title-block">';
    $html .= '<p class="iss-publication-photoalbum-blueprint__kicker">' . esc_html($heading_kicker !== '' ? $heading_kicker : __('Publikation / Fotobestand', 'industriesalon')) . '</p>';
    $html .= '<h2 class="iss-publication-photoalbum-blueprint__title-main">' . esc_html($post_title !== '' ? $post_title : __('Fotoalbum', 'industriesalon')) . '</h2>';
    $html .= '</div>';
    $html .= industriesalon_publications_render_blueprint_fact_grid($post_id, $payload, $sheets);
    if ($heading_text !== '' || $intro_html !== '') {
        $html .= '<div class="iss-publication-photoalbum-blueprint__intro">';
        if ($heading_text !== '') {
            $html .= '<p>' . wp_kses_post($heading_text) . '</p>';
        }
        if ($intro_html !== '') {
            $html .= wp_kses_post($intro_html);
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<div class="iss-publication-photoalbum-blueprint__axis" aria-hidden="true"><span>A</span><span>B</span><span>C</span><span>D</span></div>';
    $html .= '<div class="iss-publication-photoalbum-blueprint__matrix">' . $items_html . '</div>';
    if ($source_html !== '') {
        $html .= '<div class="iss-publication-source-note iss-publication-photoalbum-blueprint__source">' . wp_kses_post($source_html) . '</div>';
    }
    $html .= industriesalon_publications_render_blueprint_footer_context($post_id);
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_render_photoalbum_content(int $post_id, array $payload): string
{
    $sheets = is_array($payload['sheets'] ?? null) ? array_values(array_filter($payload['sheets'], 'is_array')) : [];
    if ($post_id <= 0 || $sheets === []) {
        return '';
    }

    if (industriesalon_get_editorial_publication_post_skin($post_id) === 'blueprint-matrix') {
        return industriesalon_publications_render_photoalbum_blueprint_matrix($post_id, $payload);
    }

    $items_html = '';
    foreach ($sheets as $sheet) {
        $items_html .= industriesalon_publications_render_photoalbum_item($sheet);
    }
    if ($items_html === '') {
        return '';
    }

    $intro_html = trim((string) ($payload['intro_html'] ?? ''));
    $source_html = trim((string) ($payload['source_html'] ?? ''));
    $heading_kicker = trim((string) ($payload['heading_kicker'] ?? ''));
    $heading_title = trim((string) ($payload['heading_title'] ?? ''));
    $heading_text = trim((string) ($payload['heading_text'] ?? ''));

    $html = '<div class="iss-publication-photoalbum">';
    if ($heading_kicker !== '' || $heading_title !== '' || $heading_text !== '' || $intro_html !== '') {
        $html .= '<div class="iss-heading iss-publication-photoalbum__head">';
        if ($heading_kicker !== '') {
            $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($heading_kicker) . '</p>';
        }
        if ($heading_title !== '') {
            $html .= '<h2 class="iss-heading__title">' . esc_html($heading_title) . '</h2>';
        }
        if ($heading_text !== '') {
            $html .= '<p class="iss-heading__text">' . wp_kses_post($heading_text) . '</p>';
        }
        if ($intro_html !== '') {
            $html .= '<div class="iss-heading__text">' . wp_kses_post($intro_html) . '</div>';
        }
        $html .= '</div>';
    }
    if ($source_html !== '') {
        $html .= '<div class="iss-publication-source-note">' . wp_kses_post($source_html) . '</div>';
    }
    $html .= '<div class="iss-publication-photoalbum__items">';
    $html .= $items_html;
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_get_photoalbum_featured_media_html(int $post_id): string
{
    if (
        $post_id <= 0
        || !function_exists('iss_publications_is_photoalbum')
        || !iss_publications_is_photoalbum($post_id)
        || !function_exists('iss_publications_get_photoalbum_payload')
    ) {
        return '';
    }

    $payload = iss_publications_get_photoalbum_payload($post_id);
    $sheets = is_array($payload['sheets'] ?? null) ? array_values(array_filter($payload['sheets'], 'is_array')) : [];
    foreach ($sheets as $sheet) {
        $media_html = trim((string) ($sheet['media_html'] ?? ''));
        if ($media_html !== '') {
            return $media_html;
        }

        $image_url = trim((string) ($sheet['image_url'] ?? ''));
        if ($image_url !== '') {
            $image_alt = trim((string) ($sheet['title'] ?? $sheet['sheet_label'] ?? ''));
            return '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" />';
        }
    }

    return '';
}

function industriesalon_publications_render_longread_related_rail(int $post_id, bool $show_places = true): string
{
    if ($post_id <= 0) {
        return '';
    }

    $groups = [];

    $publication_cells = [];
    $publication_posts = [];
    if (function_exists('iss_publications_get_related_publication_ids')) {
        $related_ids = iss_publications_get_related_publication_ids($post_id);
        if ($related_ids !== []) {
            $publication_posts = get_posts([
                'post_type' => 'publication',
                'post_status' => 'publish',
                'posts_per_page' => count($related_ids),
                'post__in' => $related_ids,
                'orderby' => 'post__in',
                'suppress_filters' => true,
            ]);
        }
    }
    if ($publication_posts === []) {
        $publication_posts = industriesalon_publications_get_related_rail_posts($post_id, [
            'postType' => 'publication',
            'perPage' => 3,
            'showImage' => false,
        ]);
    }

    foreach ($publication_posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $publication_cells[] = industriesalon_publications_render_longread_rail_cell(
            get_the_title($post),
            (string) get_permalink($post),
            industriesalon_publications_get_rail_cell_meta($post)
        );
    }
    $groups[] = industriesalon_publications_render_longread_rail_group(__('Weitere Publikationen', 'industriesalon'), $publication_cells);

    if ($show_places) {
        $place_cells = [];
        if (function_exists('iss_relations_get_related_place_items')) {
            foreach (array_slice(iss_relations_get_related_place_items($post_id), 0, 5) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $title = trim((string) ($item['title'] ?? ''));
                $url = trim((string) ($item['permalink'] ?? ''));
                $role = sanitize_key((string) ($item['role'] ?? ''));
                $role_options = function_exists('iss_relations_get_role_options') ? iss_relations_get_role_options() : [];
                $meta = trim((string) ($item['label'] ?? ''));
                $meta = $meta !== '' ? $meta : (string) ($role_options[$role] ?? __('Ort', 'industriesalon'));

                $place_cells[] = industriesalon_publications_render_longread_rail_cell($title, $url, $meta);
            }
        }
        $groups[] = industriesalon_publications_render_longread_rail_group(__('Orte', 'industriesalon'), $place_cells);
    }

    $person_cells = [];
    if (function_exists('iss_graph_get_content_entity_relations')) {
        foreach (iss_graph_get_content_entity_relations($post_id, 'person', ['limit' => 4]) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $link = function_exists('iss_graph_get_entity_relation_public_permalink')
                ? iss_graph_get_entity_relation_public_permalink($row)
                : '';
            if ($link === '') {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $relation_label = trim((string) ($row['relation_label'] ?? ''));
            $meta = $relation_label !== '' ? $relation_label : __('Person', 'industriesalon');

            $person_cells[] = industriesalon_publications_render_longread_rail_cell($name, $link, $meta);
        }
    }
    $groups[] = industriesalon_publications_render_longread_rail_group(__('Personen', 'industriesalon'), $person_cells);

    $groups = array_values(array_filter($groups));
    if ($groups === []) {
        return '';
    }

    return '<aside class="iss-publication-longread__relations" aria-label="' . esc_attr__('Weiterlesen', 'industriesalon') . '">'
        . '<h2 class="iss-publication-longread__relations-heading">' . esc_html__('Weiterlesen', 'industriesalon') . '</h2>'
        . implode('', $groups)
        . '</aside>';
}

function industriesalon_publications_render_longread_content(int $post_id, array $payload): string
{
    $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
    if (!$sections) {
        return '';
    }

    $source_note_html = trim((string) ($payload['source_note_html'] ?? ''));
    $intro_copy_html = trim((string) ($payload['intro_copy_html'] ?? ''));
    $lead_map_html = industriesalon_publications_transform_essay_map_html((string) ($payload['lead_map_html'] ?? ''));
    $html = '<div class="iss-publication-longread">';

    if ($source_note_html !== '') {
        $html .= $source_note_html;
    }

    if ($intro_copy_html !== '' || $lead_map_html !== '') {
        if ($lead_map_html !== '') {
            $html .= '<div class="iss-publication-longread__lead">';
            if ($intro_copy_html !== '') {
                $html .= '<div class="iss-publication-longread__intro">' . $intro_copy_html . '</div>';
            }
            $html .= '<div class="iss-publication-longread__map">' . $lead_map_html . '</div>';
            $html .= '</div>';
        } else {
            $html .= '<div class="iss-publication-longread__intro">' . $intro_copy_html . '</div>';
        }
    }

    $html .= '<div class="iss-publication-longread__chapters">';
    foreach ($sections as $index => $section) {
        $anchor = trim((string) ($section['anchor'] ?? ''));
        $title = trim((string) ($section['title'] ?? ''));
        $body = is_array($section['body'] ?? null) ? $section['body'] : [];
        if ($anchor === '' || $title === '') {
            continue;
        }

        $html .= '<section id="' . esc_attr($anchor) . '" class="iss-publication-longread__chapter">';
        $html .= '<div class="iss-publication-longread__chapter-aside">';
        $html .= '<p class="iss-publication-longread__chapter-index">' . esc_html(sprintf(__('Kapitel %02d', 'industriesalon'), $index + 1)) . '</p>';
        $html .= '<h2 class="iss-publication-longread__chapter-title">' . esc_html($title) . '</h2>';
        $html .= '</div>';
        $html .= '<div class="iss-publication-longread__chapter-body">' . implode('', $body);
        $html .= '</div>';
        $html .= '</section>';
    }
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_get_timeline_epoch_definitions(): array
{
    return [
        [
            'slug' => 'industrialisierung',
            'start' => null,
            'end' => 1918,
            'range_label' => '1880-1918',
            'title' => __('Gründung und Industrialisierung', 'industriesalon'),
            'note' => __('Werkgründung, Elektrifizierung und frühe Expansion.', 'industriesalon'),
        ],
        [
            'slug' => 'zwischenkrieg',
            'start' => 1919,
            'end' => 1945,
            'range_label' => '1919-1945',
            'title' => __('Zwischenkriegszeit und Krieg', 'industriesalon'),
            'note' => __('Konzernumbau, politische Brüche und Kriegswirtschaft.', 'industriesalon'),
        ],
        [
            'slug' => 'neuordnung',
            'start' => 1946,
            'end' => 1960,
            'range_label' => '1946-1960',
            'title' => __('Neuordnung und Verstaatlichung', 'industriesalon'),
            'note' => __('Neubeginn, Umbau und neue Eigentumsordnung.', 'industriesalon'),
        ],
        [
            'slug' => 'spaetphase',
            'start' => 1961,
            'end' => null,
            'range_label' => '1961-1989',
            'title' => __('Kombinat und Umbruch', 'industriesalon'),
            'note' => __('Ausbau, späte DDR und die Zäsur von 1989.', 'industriesalon'),
        ],
    ];
}

function industriesalon_publications_timeline_find_epoch(array $definitions, int $year): array
{
    foreach ($definitions as $definition) {
        $start = isset($definition['start']) ? (int) $definition['start'] : null;
        $end = isset($definition['end']) ? (int) $definition['end'] : null;

        if ($start !== null && $year < $start) {
            continue;
        }

        if ($end !== null && $year > $end) {
            continue;
        }

        return $definition;
    }

    return $definitions[count($definitions) - 1] ?? [];
}

function industriesalon_publications_normalize_timeline_card_html(string $card_html, string $fallback_title): string
{
    $card_html = trim($card_html);
    if ($card_html === '' || !class_exists('DOMDocument')) {
        return $card_html;
    }

    libxml_use_internal_errors(true);

    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div class="iss-publication-card-parser-root">' . $card_html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        libxml_clear_errors();
        return $card_html;
    }

    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-card-parser-root ")]')->item(0);
    if (!$root instanceof DOMElement) {
        libxml_clear_errors();
        return $card_html;
    }

    $title = trim($fallback_title);
    foreach ($xpath->query('.//img', $root) as $image) {
        if ($image instanceof DOMElement && trim($image->getAttribute('alt')) === '' && $title !== '') {
            $image->setAttribute('alt', $title);
        }
    }

    $has_title = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__title ")]', $root)->length > 0;
    if (!$has_title && $title !== '') {
        $heading = $document->createElement('h3');
        $heading->setAttribute('class', 'wp-block-heading iss-publication-chronicle__title');
        $heading->appendChild($document->createTextNode($title));

        $text_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__text ")]', $root)->item(0);
        if ($text_node instanceof DOMElement) {
            $root->insertBefore($heading, $text_node);
        } else {
            $root->appendChild($heading);
        }
    }

    $html = '';
    foreach ($root->childNodes as $child) {
        $html .= $document->saveHTML($child);
    }

    libxml_clear_errors();

    return trim($html);
}

function industriesalon_publications_get_timeline_item_variant(array $item): string
{
    $width = (int) ($item['image_width'] ?? 0);
    $height = (int) ($item['image_height'] ?? 0);
    $image_id = (int) ($item['image_id'] ?? 0);

    if (($width <= 0 || $height <= 0) && $image_id > 0) {
        $metadata = wp_get_attachment_metadata($image_id);
        if (is_array($metadata)) {
            $width = (int) ($metadata['width'] ?? 0);
            $height = (int) ($metadata['height'] ?? 0);
        }
    }

    if ($width <= 0 || $height <= 0) {
        return '';
    }

    $ratio = $width / $height;
    if ($ratio >= 1.45) {
        return 'wide';
    }

    if ($ratio <= 0.85) {
        return 'portrait';
    }

    return '';
}

function industriesalon_publications_render_timeline_card(array $item, string $fallback_title): string
{
    $card_html = trim((string) ($item['card_html'] ?? ''));
    if ($card_html !== '') {
        return wp_kses_post(industriesalon_publications_normalize_timeline_card_html($card_html, $fallback_title));
    }

    $title = trim((string) ($item['title'] ?? ''));
    $title = $title !== '' ? $title : $fallback_title;
    $image_id = (int) ($item['image_id'] ?? 0);
    $image_url = trim((string) ($item['image_url'] ?? ''));
    $image_alt = trim((string) ($item['image_alt'] ?? ''));
    $image_alt = $image_alt !== '' ? $image_alt : $title;
    $body_html = trim((string) ($item['body_html'] ?? ''));

    $html = '';
    if ($image_id > 0) {
        $image_html = wp_get_attachment_image($image_id, 'large', false, ['alt' => $image_alt]);
        if (is_string($image_html) && trim($image_html) !== '') {
            $html .= '<figure class="wp-block-image size-large iss-publication-chronicle__media">' . $image_html . '</figure>';
        }
    } elseif ($image_url !== '') {
        $html .= '<figure class="wp-block-image size-large iss-publication-chronicle__media"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '"></figure>';
    }

    if ($title !== '') {
        $html .= '<h3 class="wp-block-heading iss-publication-chronicle__title">' . esc_html($title) . '</h3>';
    }

    if ($body_html !== '') {
        $html .= '<div class="wp-block-group iss-publication-chronicle__text">' . wp_kses_post($body_html) . '</div>';
    }

    return $html;
}

function industriesalon_publications_get_rail_cell_meta(WP_Post $post): string
{
    if (function_exists('iss_relations_get_card_meta_line')) {
        return trim((string) iss_relations_get_card_meta_line($post));
    }

    $post_type_object = get_post_type_object((string) $post->post_type);
    return $post_type_object ? (string) $post_type_object->labels->singular_name : '';
}

function industriesalon_publications_render_timeline_rail_cell(string $title, string $url = '', string $meta = ''): string
{
    $title = trim($title);
    if ($title === '') {
        return '';
    }

    $title_html = $url !== ''
        ? '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>'
        : esc_html($title);

    $html = '<div class="iss-publication-timeline__rail-cell">';
    if ($meta !== '') {
        $html .= '<span class="iss-publication-timeline__rail-cell-meta">' . esc_html($meta) . '</span>';
    }
    $html .= '<span class="iss-publication-timeline__rail-cell-title">' . $title_html . '</span>';
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_render_timeline_rail_group(string $title, array $cells): string
{
    $cells = array_values(array_filter($cells));
    if ($cells === []) {
        return '';
    }

    return '<section class="iss-publication-timeline__rail-group">'
        . '<h2 class="iss-publication-timeline__rail-heading">' . esc_html($title) . '</h2>'
        . '<div class="iss-publication-timeline__rail-cells">' . implode('', $cells) . '</div>'
        . '</section>';
}

function industriesalon_publications_get_related_rail_posts(int $post_id, array $attributes): array
{
    if ($post_id <= 0 || !function_exists('iss_relations_collect_related_cards_data')) {
        return [];
    }

    $data = iss_relations_collect_related_cards_data($attributes, $post_id);
    $posts = is_array($data['posts'] ?? null) ? $data['posts'] : [];

    return array_values(array_filter($posts, static function ($post): bool {
        return $post instanceof WP_Post;
    }));
}

function industriesalon_publications_render_timeline_related_rail(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }

    $groups = [];

    $publication_cells = [];
    $publication_posts = [];
    if (function_exists('iss_publications_get_related_publication_ids')) {
        $related_ids = iss_publications_get_related_publication_ids($post_id);
        if ($related_ids !== []) {
            $publication_posts = get_posts([
                'post_type' => 'publication',
                'post_status' => 'publish',
                'posts_per_page' => count($related_ids),
                'post__in' => $related_ids,
                'orderby' => 'post__in',
                'suppress_filters' => true,
            ]);
        }
    }
    if ($publication_posts === []) {
        $publication_posts = industriesalon_publications_get_related_rail_posts($post_id, [
            'postType' => 'publication',
            'perPage' => 3,
            'showImage' => false,
        ]);
    }

    foreach ($publication_posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $publication_cells[] = industriesalon_publications_render_timeline_rail_cell(
            get_the_title($post),
            (string) get_permalink($post),
            industriesalon_publications_get_rail_cell_meta($post)
        );
    }
    $groups[] = industriesalon_publications_render_timeline_rail_group(__('Weitere Publikationen', 'industriesalon'), $publication_cells);

    $place_cells = [];
    if (function_exists('iss_relations_get_related_place_items')) {
        foreach (array_slice(iss_relations_get_related_place_items($post_id), 0, 5) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $url = trim((string) ($item['permalink'] ?? ''));
            $role = sanitize_key((string) ($item['role'] ?? ''));
            $role_options = function_exists('iss_relations_get_role_options') ? iss_relations_get_role_options() : [];
            $meta = trim((string) ($item['label'] ?? ''));
            $meta = $meta !== '' ? $meta : (string) ($role_options[$role] ?? __('Ort', 'industriesalon'));

            $place_cells[] = industriesalon_publications_render_timeline_rail_cell($title, $url, $meta);
        }
    }
    $groups[] = industriesalon_publications_render_timeline_rail_group(__('Orte', 'industriesalon'), $place_cells);

    $person_cells = [];
    if (function_exists('iss_graph_get_content_entity_relations')) {
        foreach (iss_graph_get_content_entity_relations($post_id, 'person', ['limit' => 4]) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $link = function_exists('iss_graph_get_entity_relation_public_permalink')
                ? iss_graph_get_entity_relation_public_permalink($row)
                : '';
            if ($link === '') {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $relation_label = trim((string) ($row['relation_label'] ?? ''));
            $meta = $relation_label !== '' ? $relation_label : __('Person', 'industriesalon');

            $person_cells[] = industriesalon_publications_render_timeline_rail_cell($name, $link, $meta);
        }
    }
    $groups[] = industriesalon_publications_render_timeline_rail_group(__('Personen', 'industriesalon'), $person_cells);

    $groups = array_values(array_filter($groups));
    if ($groups === []) {
        return '';
    }

    return '<aside class="iss-publication-timeline__relations" aria-label="' . esc_attr__('Weiterlesen', 'industriesalon') . '">'
        . '<h2 class="iss-publication-timeline__relations-heading">' . esc_html__('Weiterlesen', 'industriesalon') . '</h2>'
        . implode('', $groups)
        . '</aside>';
}

function industriesalon_publications_render_timeline_content(int $post_id, array $payload): string
{
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    if (!$items) {
        return '';
    }

    $definitions = industriesalon_publications_get_timeline_epoch_definitions();
    $epochs = [];
    foreach ($items as $item) {
        $year = (int) ($item['year'] ?? 0);
        if ($year <= 0) {
            continue;
        }

        $definition = industriesalon_publications_timeline_find_epoch($definitions, $year);
        $slug = sanitize_key((string) ($definition['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        if (!isset($epochs[$slug])) {
            $epochs[$slug] = [
                'definition' => $definition,
                'items' => [],
            ];
        }

        $epochs[$slug]['items'][] = $item;
    }

    if (!$epochs) {
        return '';
    }

    $years = array_values(array_filter(array_map(static function ($item): int {
        return (int) ($item['year'] ?? 0);
    }, $items)));
    $first_year = $years !== [] ? (string) min($years) : '—';
    $last_year = $years !== [] ? (string) max($years) : '—';
    $summary_labels = is_array($payload['summary_labels'] ?? null) ? $payload['summary_labels'] : [];
    $summary_label_start = trim((string) ($summary_labels['start'] ?? '')) ?: __('Auftakt', 'industriesalon');
    $summary_label_end = trim((string) ($summary_labels['end'] ?? '')) ?: __('Letzte Zäsur', 'industriesalon');
    $summary_label_items = trim((string) ($summary_labels['items'] ?? '')) ?: __('Stationen', 'industriesalon');
    $summary_label_epochs = trim((string) ($summary_labels['epochs'] ?? '')) ?: __('Epochen', 'industriesalon');
    $summary_visibility = is_array($payload['summary_visibility'] ?? null) ? $payload['summary_visibility'] : [];
    $summary_show_start = ($summary_visibility['start'] ?? true) !== false;
    $summary_show_end = ($summary_visibility['end'] ?? true) !== false;
    $summary_show_items = ($summary_visibility['items'] ?? true) !== false;
    $summary_show_epochs = ($summary_visibility['epochs'] ?? true) !== false;

    $summary_html = '';
    $summary = [];
    if ($summary_show_start) {
        $summary[] = [
                'value' => $first_year,
                'label' => $summary_label_start,
        ];
    }
    if ($summary_show_end) {
        $summary[] = [
                'value' => $last_year,
                'label' => $summary_label_end,
        ];
    }
    if ($summary_show_items) {
        $summary[] = [
                'value' => (string) count($items),
                'label' => $summary_label_items,
        ];
    }
    if ($summary_show_epochs) {
        $summary[] = [
                'value' => (string) count($epochs),
                'label' => $summary_label_epochs,
        ];
    }

    if ($summary !== []) {
        $summary_html = '<div class="iss-publication-timeline__summary" aria-label="' . esc_attr__('Rahmendaten', 'industriesalon') . '">';
        foreach ($summary as $item) {
            $summary_html .= '<p class="iss-publication-timeline__summary-item"><strong>' . esc_html((string) $item['value']) . '</strong><span>' . esc_html((string) $item['label']) . '</span></p>';
        }
        $summary_html .= '</div>';
    }

    $nav_html = '';
    $epochs_html = '';
    foreach ($epochs as $slug => $epoch_data) {
        $definition = is_array($epoch_data['definition'] ?? null) ? $epoch_data['definition'] : [];
        $slug_class = sanitize_html_class((string) $slug);
        $anchor = 'iss-publication-epoch-' . $slug_class;
        $title = (string) ($definition['title'] ?? '');
        $range_label = (string) ($definition['range_label'] ?? '');

        $nav_html .= '<a class="iss-publication-timeline__nav-link iss-publication-timeline__nav-link--' . esc_attr($slug_class) . '" href="#' . esc_attr($anchor) . '"><span>' . esc_html($range_label) . '</span>' . esc_html($title) . '</a>';

        $moments_html = '';
        foreach ((array) ($epoch_data['items'] ?? []) as $item) {
            $year = (int) ($item['year'] ?? 0);
            $item_title = trim((string) ($item['title'] ?? ''));
            $fallback_title = $item_title !== '' ? $item_title : sprintf(__('Station %d', 'industriesalon'), $year);
            $variant = sanitize_html_class(industriesalon_publications_get_timeline_item_variant(is_array($item) ? $item : []));
            $variant_class = $variant !== '' ? ' iss-publication-moment--' . $variant : '';
            $card_html = industriesalon_publications_render_timeline_card(is_array($item) ? $item : [], $fallback_title);

            $moments_html .= '<article class="iss-publication-moment' . esc_attr($variant_class) . '">';
            $moments_html .= '<p class="iss-publication-moment__year">' . esc_html((string) $year) . '</p>';
            $moments_html .= '<div class="iss-publication-moment__card">' . $card_html . '</div>';
            $moments_html .= '</article>';
        }

        $epochs_html .= '<section id="' . esc_attr($anchor) . '" class="iss-publication-epoch iss-publication-epoch--' . esc_attr($slug_class) . '">';
        $epochs_html .= '<div class="iss-publication-epoch__aside">';
        $epochs_html .= '<p class="iss-publication-epoch__range">' . esc_html($range_label) . '</p>';
        $epochs_html .= '<h2 class="iss-publication-epoch__title">' . esc_html($title) . '</h2>';
        $epochs_html .= '<p class="iss-publication-epoch__note">' . esc_html((string) ($definition['note'] ?? '')) . '</p>';
        $epochs_html .= '</div>';
        $epochs_html .= '<div class="iss-publication-epoch__body">' . $moments_html . '</div>';
        $epochs_html .= '</section>';
    }

    $html = '<div class="iss-publication-timeline">';
    $source_note = trim((string) ($payload['source_note'] ?? ''));
    if ($source_note !== '') {
        $html .= '<p class="iss-publication-source-note">' . wp_kses_post($source_note) . '</p>';
    }
    $html .= '<div class="iss-publication-timeline__content">';
    $html .= '<div class="iss-publication-timeline__rail">';
    $html .= '<nav class="iss-publication-timeline__nav" aria-label="' . esc_attr__('Kapitelnavigation', 'industriesalon') . '"><div class="iss-publication-timeline__nav-inner">' . $nav_html . '</div></nav>';
    $html .= $summary_html;
    $html .= industriesalon_publications_render_timeline_related_rail($post_id);
    $html .= '</div>';
    $html .= '<div class="iss-publication-timeline__epochs">' . $epochs_html . '</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_render_archive_card(int $post_id, array $options = []): string
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_publications_get_card_kicker')) {
        return '';
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !function_exists('iss_relations_render_generic_card')) {
        return '';
    }

    $card_label = function_exists('iss_publications_sale_enabled') && iss_publications_sale_enabled($post_id)
        ? __('Details / bestellen', 'industriesalon')
        : __('Mehr lesen', 'industriesalon');

    return iss_relations_render_generic_card($post, [
        'kicker' => iss_publications_get_card_kicker($post_id),
        'link_text' => $card_label,
    ], wp_parse_args($options, [
        'layoutVariant' => 'grid',
        'show_image' => true,
        'show_excerpt' => true,
    ]));
}

function industriesalon_publications_render_featured_publication(array $context): string
{
    $post_id = (int) ($context['post_id'] ?? 0);
    if ($post_id <= 0) {
        return '';
    }

    $permalink = (string) ($context['permalink'] ?? '');
    $excerpt = trim((string) ($context['excerpt'] ?? ''));
    $summary_meta = is_array($context['summary_meta'] ?? null) ? $context['summary_meta'] : [];

    $html = '<article class="iss-publications-feature">';
    $html .= '<div class="iss-publications-feature__cover">';
    $html .= '<a class="iss-media-card iss-media-card--cover iss-media-card--flat iss-publications-feature__media" href="' . esc_url($permalink) . '">';
    if (has_post_thumbnail($post_id)) {
        $html .= (string) get_the_post_thumbnail($post_id, 'large');
    }
    $html .= '</a>';
    $html .= '</div>';

    $html .= '<div class="iss-publications-feature__content">';
    $html .= '<div class="iss-heading iss-heading--uncaged">';
    $html .= '<p class="iss-kicker">' . esc_html__('Ausgewählte Publikation', 'industriesalon') . '</p>';
    $html .= '<h2 class="iss-heading__title">' . esc_html(get_the_title($post_id)) . '</h2>';
    if ($excerpt !== '') {
        $html .= '<p class="iss-heading__text">' . esc_html($excerpt) . '</p>';
    }
    $html .= '</div>';

    if ($summary_meta) {
        $html .= '<ul class="iss-publications-feature__meta" aria-label="' . esc_attr__('Rahmendaten', 'industriesalon') . '">';
        foreach ($summary_meta as $label => $value) {
            $html .= '<li>';
            $html .= '<span class="iss-publications-feature__meta-label">' . esc_html((string) $label) . '</span>';
            $html .= '<span class="iss-publications-feature__meta-value">' . esc_html((string) $value) . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    }

    $html .= '<p class="iss-publications-feature__action"><a class="iss-card__link" href="' . esc_url($permalink) . '">' . esc_html__('Details ansehen', 'industriesalon') . '</a></p>';
    $html .= '</div>';
    $html .= '</article>';

    return $html;
}

function industriesalon_publications_render_grid(array $posts, array $options = []): string
{
    $posts = array_values(array_filter($posts, static function ($post): bool {
        return $post instanceof WP_Post;
    }));
    if (!$posts) {
        return '';
    }

    $layout_variant = sanitize_key((string) ($options['layoutVariant'] ?? 'grid'));
    if ($layout_variant === 'carousel') {
        $layout_variant = 'strip';
    }
    if (!in_array($layout_variant, ['grid', 'strip'], true)) {
        $layout_variant = 'grid';
    }

    $columns = isset($options['columns']) ? max(1, min(6, (int) $options['columns'])) : ($layout_variant === 'strip' ? 6 : 3);
    $show_excerpt = !array_key_exists('show_excerpt', $options) || !empty($options['show_excerpt']);
    $cards = [];
    foreach ($posts as $post) {
        $cards[] = industriesalon_publications_render_archive_card((int) $post->ID, [
            'layoutVariant' => $layout_variant,
            'show_image' => true,
            'show_excerpt' => $show_excerpt,
        ]);
    }

    $cards = array_values(array_filter($cards));
    if (!$cards) {
        return '';
    }

    if (function_exists('iss_relations_render_cards_grid')) {
        return iss_relations_render_cards_grid($cards, 'publication', [
            'layoutVariant' => $layout_variant,
            'columns' => $columns,
            'show_image' => true,
            'show_excerpt' => $show_excerpt,
        ]);
    }

    $html = '<div class="iss-related-feed__grid iss-related-feed__grid--publication iss-related-feed__grid--layout-' . esc_attr($layout_variant) . ' iss-related-feed__grid--columns-' . esc_attr((string) $columns) . '">';
    foreach ($cards as $card) {
        $html .= $card;
    }
    $html .= '</div>';

    return $html;
}

function industriesalon_publications_browser_query_values(array $state): array
{
    return [
        'bezug' => (string) ($state['shared_topic'] ?? ''),
    ];
}

function industriesalon_publications_browser_target_id(): string
{
    return 'publikationen-bestand';
}

function industriesalon_publications_browser_target_url(string $url): string
{
    $url = trim($url);
    $target = industriesalon_publications_browser_target_id();

    if ($url === '') {
        return '#' . $target;
    }

    return explode('#', $url, 2)[0] . '#' . $target;
}

/* The plugin browser payload exposes semantic keys; the theme/editor contract owns public copy for those keys. */
function industriesalon_publications_browser_default_copy(): array
{
    return [
        'copy' => [
            'ariaLabel' => __('Publikationsbestand', 'industriesalon'),
            'sidebarKicker' => __('Bestand', 'industriesalon'),
            'sidebarTitle' => __('Publikationsformen', 'industriesalon'),
            'discoveryTitle' => __('Weiterentdecken', 'industriesalon'),
            'filterAllLabel' => __('Alle Themen', 'industriesalon'),
            'resultsKicker' => __('Thema', 'industriesalon'),
            'resultsTitle' => __('Themen im Bestand', 'industriesalon'),
            'emptyText' => __('Keine Publikationen passen zu dieser Auswahl.', 'industriesalon'),
            'sectionCountSingular' => __('Eintrag', 'industriesalon'),
            'sectionCountPlural' => __('Einträge', 'industriesalon'),
        ],
        'formatCopy' => [
            'standard' => [
                'label' => __('Publikationen', 'industriesalon'),
                'title' => __('Publikationen', 'industriesalon'),
                'description' => __('Broschüren, Hefte und klassische Publikationsseiten.', 'industriesalon'),
            ],
            'timeline' => [
                'label' => __('Chroniken', 'industriesalon'),
                'title' => __('Chroniken', 'industriesalon'),
                'description' => __('Zeitleisten und Entwicklungsgeschichten mit datierten Stationen.', 'industriesalon'),
            ],
            'longread' => [
                'label' => __('Longreads', 'industriesalon'),
                'title' => __('Longreads', 'industriesalon'),
                'description' => __('Kapitelbasierte Online-Publikationen und Quellengeschichten.', 'industriesalon'),
            ],
            'photoalbum' => [
                'label' => __('Fotoalben', 'industriesalon'),
                'title' => __('Fotoalben', 'industriesalon'),
                'description' => __('Bildfolgen und Albumstrecken aus dem Bestand.', 'industriesalon'),
            ],
        ],
        'filterCopy' => [
            'shared_topic' => [
                'label' => __('Thema', 'industriesalon'),
            ],
        ],
    ];
}

function industriesalon_publications_browser_clean_copy_value($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    return trim(wp_strip_all_tags((string) $value));
}

function industriesalon_publications_browser_copy_from_parsed_blocks(array $blocks): array
{
    $copy = [];
    $format_copy = [];

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $block_name = (string) ($block['blockName'] ?? '');
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        if ($block_name === 'iss/publications-browser-sidebar') {
            $kicker = industriesalon_publications_browser_clean_copy_value($attrs['kicker'] ?? '');
            $title = industriesalon_publications_browser_clean_copy_value($attrs['title'] ?? '');
            if ($kicker !== '') {
                $copy['sidebarKicker'] = $kicker;
            }
            if ($title !== '') {
                $copy['sidebarTitle'] = $title;
            }
        }

        if ($block_name === 'iss/publications-browser-results') {
            $kicker = industriesalon_publications_browser_clean_copy_value($attrs['kicker'] ?? '');
            $title = industriesalon_publications_browser_clean_copy_value($attrs['title'] ?? '');
            if ($kicker !== '') {
                $copy['resultsKicker'] = $kicker;
            }
            if ($title !== '') {
                $copy['resultsTitle'] = $title;
            }
        }

        if ($block_name === 'iss/publications-browser-format') {
            $format = sanitize_key((string) ($attrs['format'] ?? ''));
            if (in_array($format, ['standard', 'timeline', 'longread', 'photoalbum'], true)) {
                $title = industriesalon_publications_browser_clean_copy_value($attrs['title'] ?? '');
                $description = industriesalon_publications_browser_clean_copy_value($attrs['description'] ?? '');
                $format_copy[$format] = array_filter([
                    'title' => $title,
                    'description' => $description,
                ], static function (string $value): bool {
                    return $value !== '';
                });
            }
        }

        $inner_blocks = is_array($block['innerBlocks'] ?? null) ? $block['innerBlocks'] : [];
        if ($inner_blocks !== []) {
            $inner_copy = industriesalon_publications_browser_copy_from_parsed_blocks($inner_blocks);
            if (is_array($inner_copy['copy'] ?? null)) {
                $copy = array_merge($copy, $inner_copy['copy']);
            }
            if (is_array($inner_copy['formatCopy'] ?? null)) {
                $format_copy = array_merge($format_copy, $inner_copy['formatCopy']);
            }
        }
    }

    return array_filter([
        'copy' => $copy,
        'formatCopy' => $format_copy,
    ], static function (array $values): bool {
        return $values !== [];
    });
}

function industriesalon_publications_browser_copy_from_block(?WP_Block $block): array
{
    if (!$block instanceof WP_Block || !is_array($block->parsed_block['innerBlocks'] ?? null)) {
        return [];
    }

    return industriesalon_publications_browser_copy_from_parsed_blocks($block->parsed_block['innerBlocks']);
}

function industriesalon_publications_browser_merge_editor_copy(array $attributes, ?WP_Block $block = null): array
{
    $editor_copy = industriesalon_publications_browser_copy_from_block($block);
    if ($editor_copy === []) {
        return $attributes;
    }

    if (isset($editor_copy['copy']) && is_array($editor_copy['copy'])) {
        $attributes['copy'] = array_merge(
            is_array($attributes['copy'] ?? null) ? $attributes['copy'] : [],
            $editor_copy['copy']
        );
    }

    if (isset($editor_copy['formatCopy']) && is_array($editor_copy['formatCopy'])) {
        $format_copy = is_array($attributes['formatCopy'] ?? null) ? $attributes['formatCopy'] : [];
        foreach ($editor_copy['formatCopy'] as $format => $copy) {
            if (!is_string($format) || !is_array($copy)) {
                continue;
            }

            $format_copy[$format] = array_merge(
                is_array($format_copy[$format] ?? null) ? $format_copy[$format] : [],
                $copy
            );
        }
        $attributes['formatCopy'] = $format_copy;
    }

    return $attributes;
}

function industriesalon_publications_browser_copy_text(array $attributes, string $key): string
{
    $copy = is_array($attributes['copy'] ?? null) ? $attributes['copy'] : [];
    $value = industriesalon_publications_browser_clean_copy_value($copy[$key] ?? '');
    if ($value !== '') {
        return $value;
    }

    $defaults = industriesalon_publications_browser_default_copy();
    $default_copy = is_array($defaults['copy'] ?? null) ? $defaults['copy'] : [];
    return (string) ($default_copy[$key] ?? '');
}

function industriesalon_publications_browser_nested_copy_text(array $attributes, string $attribute_key, string $item_key, string $value_key): string
{
    $items = is_array($attributes[$attribute_key] ?? null) ? $attributes[$attribute_key] : [];
    $item = is_array($items[$item_key] ?? null) ? $items[$item_key] : [];
    $value = industriesalon_publications_browser_clean_copy_value($item[$value_key] ?? '');
    if ($value !== '') {
        return $value;
    }

    $defaults = industriesalon_publications_browser_default_copy();
    $default_items = is_array($defaults[$attribute_key] ?? null) ? $defaults[$attribute_key] : [];
    $default_item = is_array($default_items[$item_key] ?? null) ? $default_items[$item_key] : [];
    return (string) ($default_item[$value_key] ?? '');
}

function industriesalon_publications_browser_format_copy(string $format, array $attributes = []): array
{
    $copy = [
        'label' => industriesalon_publications_browser_nested_copy_text($attributes, 'formatCopy', $format, 'label'),
        'title' => industriesalon_publications_browser_nested_copy_text($attributes, 'formatCopy', $format, 'title'),
        'description' => industriesalon_publications_browser_nested_copy_text($attributes, 'formatCopy', $format, 'description'),
    ];

    return array_filter($copy, static function (string $value): bool {
        return $value !== '';
    });
}

function industriesalon_publications_browser_format_keys(): array
{
    $defaults = industriesalon_publications_browser_default_copy();
    $format_copy = is_array($defaults['formatCopy'] ?? null) ? $defaults['formatCopy'] : [];

    return array_keys($format_copy);
}

function industriesalon_publications_browser_topic_group(array $payload): array
{
    $filter_groups = is_array($payload['filter_groups'] ?? null) ? $payload['filter_groups'] : [];
    foreach ($filter_groups as $group) {
        if (!is_array($group)) {
            continue;
        }

        if ((string) ($group['state_key'] ?? '') === 'shared_topic' || (string) ($group['param'] ?? '') === 'bezug') {
            return $group;
        }
    }

    return [];
}

function industriesalon_publications_browser_count_text(int $count, array $attributes, string $singular_key, string $plural_key): string
{
    $label = industriesalon_publications_browser_copy_text($attributes, $count === 1 ? $singular_key : $plural_key);
    return trim((string) $count . ($label !== '' ? ' ' . $label : ''));
}

function industriesalon_publications_browser_filter_url(array $payload, string $param, string $value = ''): string
{
    $base_url = (string) ($payload['base_url'] ?? home_url('/publikationen/'));
    $state = is_array($payload['state'] ?? null) ? $payload['state'] : [];
    $params = is_array($payload['params'] ?? null) ? $payload['params'] : ['bezug'];
    $query_values = industriesalon_publications_browser_query_values($state);
    $args = [];

    foreach ($params as $key) {
        $key = (string) $key;
        if ($key !== '' && !empty($query_values[$key])) {
            $args[$key] = $query_values[$key];
        }
    }

    if ($value === '' || ((string) ($args[$param] ?? '') === $value)) {
        unset($args[$param]);
    } else {
        $args[$param] = $value;
    }

    return industriesalon_publications_browser_target_url($args ? add_query_arg($args, $base_url) : $base_url);
}

function industriesalon_publications_render_browser_chapter_nav(array $sections, array $attributes = []): string
{
    $sections_by_format = [];
    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $format = sanitize_key((string) ($section['key'] ?? ''));
        if ($format !== '') {
            $sections_by_format[$format] = $section;
        }
    }

    $html = '<nav class="iss-publications-browser__chapters" aria-label="' . esc_attr(industriesalon_publications_browser_copy_text($attributes, 'sidebarTitle')) . '">';
    foreach (industriesalon_publications_browser_format_keys() as $format) {
        $section = is_array($sections_by_format[$format] ?? null) ? $sections_by_format[$format] : [];
        $posts = is_array($section['posts'] ?? null) ? $section['posts'] : [];
        $count = (int) ($section['count'] ?? count($posts));
        $copy = industriesalon_publications_browser_format_copy($format, $attributes);
        $label = (string) ($copy['label'] ?? ($copy['title'] ?? ''));
        if ($format === '' || $label === '') {
            continue;
        }

        $classes = 'iss-publications-browser__chapter';
        if ($count <= 0) {
            $classes .= ' is-disabled';
            $html .= '<span class="' . esc_attr($classes) . '" aria-disabled="true">';
        } else {
            $html .= '<a class="' . esc_attr($classes) . '" href="#publikationen-' . esc_attr($format) . '">';
        }

        $html .= '<span>' . esc_html($label) . '</span>';
        $html .= '<small>' . esc_html((string) $count) . '</small>';
        $html .= $count <= 0 ? '</span>' : '</a>';
    }
    $html .= '</nav>';

    return $html;
}

function industriesalon_publications_browser_discovery_links(): array
{
    return [
        [
            'label' => __('Archiv', 'industriesalon'),
            'url' => home_url('/archiv/'),
        ],
        [
            'label' => __('Führungen', 'industriesalon'),
            'url' => home_url('/fuehrungen/'),
        ],
        [
            'label' => __('Sammlungen', 'industriesalon'),
            'url' => home_url('/sammlungen/'),
        ],
        [
            'label' => __('Ausstellungen', 'industriesalon'),
            'url' => home_url('/ausstellungen/'),
        ],
        [
            'label' => __('Veranstaltungen', 'industriesalon'),
            'url' => home_url('/veranstaltungen/'),
        ],
    ];
}

function industriesalon_publications_render_browser_discovery_nav(array $attributes = []): string
{
    $links = industriesalon_publications_browser_discovery_links();
    if (!$links) {
        return '';
    }

    $html = '<nav class="iss-publications-browser__discovery" aria-label="' . esc_attr(industriesalon_publications_browser_copy_text($attributes, 'discoveryTitle')) . '">';
    $html .= '<h3 class="iss-publications-browser__discovery-title">' . esc_html(industriesalon_publications_browser_copy_text($attributes, 'discoveryTitle')) . '</h3>';
    $html .= '<ul class="iss-publications-browser__discovery-list">';
    foreach ($links as $link) {
        $label = trim((string) ($link['label'] ?? ''));
        $url = trim((string) ($link['url'] ?? ''));
        if ($label === '' || $url === '') {
            continue;
        }

        $html .= '<li><a class="iss-publications-browser__discovery-link" href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
    }
    $html .= '</ul>';
    $html .= '</nav>';

    return $html;
}

function industriesalon_publications_render_browser_topic_nav(array $payload, array $attributes = []): string
{
    $group = industriesalon_publications_browser_topic_group($payload);
    $options = is_array($group['options'] ?? null) ? $group['options'] : [];
    if (!$options) {
        return '';
    }

    $param = (string) ($group['param'] ?? 'bezug');
    $label = industriesalon_publications_browser_nested_copy_text($attributes, 'filterCopy', 'shared_topic', 'label');
    if ($param === '' || $label === '') {
        return '';
    }

    $state = is_array($payload['state'] ?? null) ? $payload['state'] : [];
    $has_active = (string) ($state['shared_topic'] ?? '') !== '';

    if (function_exists('iss_relations_enqueue_related_strip_script')) {
        iss_relations_enqueue_related_strip_script();
    }

    $html = '<header class="iss-publications-browser__results-head">';
    $html .= '<div class="iss-publications-browser__topic-heading">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html(industriesalon_publications_browser_copy_text($attributes, 'resultsKicker')) . '</p>';
    $html .= '<h2>' . esc_html(industriesalon_publications_browser_copy_text($attributes, 'resultsTitle')) . '</h2>';
    $html .= '</div>';
    $html .= '<div class="iss-related-feed__carousel iss-publications-browser__topic-carousel" data-related-carousel>';
    $html .= '<nav class="iss-publications-browser__topics iss-related-feed__grid--layout-strip" aria-label="' . esc_attr($label) . '">';
    $html .= '<a class="iss-publications-browser__topic' . (!$has_active ? ' is-active' : '') . '" href="' . esc_url(industriesalon_publications_browser_filter_url($payload, $param)) . '">';
    $html .= '<span>' . esc_html(industriesalon_publications_browser_copy_text($attributes, 'filterAllLabel')) . '</span>';
    $html .= '<small>' . esc_html((string) ((int) ($payload['unfiltered_total'] ?? 0))) . '</small>';
    $html .= '</a>';

    foreach ($options as $option) {
        if (!is_array($option)) {
            continue;
        }

        $slug = (string) ($option['slug'] ?? '');
        $option_label = trim((string) ($option['label'] ?? ''));
        if ($slug === '' || $option_label === '') {
            continue;
        }

        $classes = 'iss-publications-browser__topic';
        if (!empty($option['active'])) {
            $classes .= ' is-active';
        }

        $html .= '<a class="' . esc_attr($classes) . '" href="' . esc_url(industriesalon_publications_browser_filter_url($payload, $param, $slug)) . '">';
        $html .= '<span>' . esc_html($option_label) . '</span>';
        $html .= '<small>' . esc_html((string) ((int) ($option['count'] ?? 0))) . '</small>';
        $html .= '</a>';
    }

    $html .= '</nav>';
    $html .= '<div class="iss-related-feed__carousel-controls" aria-label="' . esc_attr__('Themenkarussell steuern', 'industriesalon') . '">';
    $html .= '<button type="button" class="iss-related-feed__carousel-control iss-related-feed__carousel-control--prev" data-related-carousel-prev aria-label="' . esc_attr__('Vorherige Themen', 'industriesalon') . '" disabled>';
    $html .= '<span class="iss-related-feed__carousel-control-icon" aria-hidden="true">&#8592;</span>';
    $html .= '<span class="iss-related-feed__carousel-control-text">' . esc_html__('Zurück', 'industriesalon') . '</span>';
    $html .= '</button>';
    $html .= '<button type="button" class="iss-related-feed__carousel-control iss-related-feed__carousel-control--next" data-related-carousel-next aria-label="' . esc_attr__('Nächste Themen', 'industriesalon') . '" disabled>';
    $html .= '<span class="iss-related-feed__carousel-control-text">' . esc_html__('Weiter', 'industriesalon') . '</span>';
    $html .= '<span class="iss-related-feed__carousel-control-icon" aria-hidden="true">&#9658;</span>';
    $html .= '</button>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</header>';

    return $html;
}

function industriesalon_publications_render_browser(array $payload, array $attributes = [], ?WP_Block $block = null): string
{
    $attributes = industriesalon_publications_browser_merge_editor_copy($attributes, $block);
    $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

    $html = '<section id="' . esc_attr(industriesalon_publications_browser_target_id()) . '" class="iss-publications-browser iss-scroll-target" aria-label="' . esc_attr(industriesalon_publications_browser_copy_text($attributes, 'ariaLabel')) . '">';
    $html .= '<aside class="iss-publications-browser__sidebar">';
    $html .= '<div class="iss-publications-browser__sidebar-inner">';
    $html .= '<div class="iss-publications-browser__sidebar-head">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html(industriesalon_publications_browser_copy_text($attributes, 'sidebarKicker')) . '</p>';
    $html .= '<h2>' . esc_html(industriesalon_publications_browser_copy_text($attributes, 'sidebarTitle')) . '</h2>';
    $html .= '</div>';
    $html .= industriesalon_publications_render_browser_chapter_nav($sections, $attributes);
    $html .= industriesalon_publications_render_browser_discovery_nav($attributes);
    $html .= '</div>';
    $html .= '</aside>';
    $html .= '<div class="iss-publications-browser__results">';
    $html .= industriesalon_publications_render_browser_topic_nav($payload, $attributes);

    if (!$sections) {
        $html .= '<p class="iss-publications-browser__empty">' . esc_html(industriesalon_publications_browser_copy_text($attributes, 'emptyText')) . '</p>';
    }

    foreach ($sections as $index => $section) {
        if (!is_array($section)) {
            continue;
        }

        $posts = is_array($section['posts'] ?? null) ? $section['posts'] : [];
        if (!$posts) {
            continue;
        }

        $copy = industriesalon_publications_browser_format_copy((string) ($section['key'] ?? ''), $attributes);
        $section_title = (string) ($copy['title'] ?? ($section['title'] ?? ''));
        $section_description = (string) ($copy['description'] ?? ($section['description'] ?? ''));
        if ($section_title === '') {
            continue;
        }

        $html .= '<section id="publikationen-' . esc_attr((string) ($section['key'] ?? $index)) . '" class="iss-publications-browser__section">';
        $html .= '<div class="iss-publications-browser__section-head">';
        $html .= '<span class="iss-publications-browser__section-index">' . esc_html(str_pad((string) ((int) $index + 1), 2, '0', STR_PAD_LEFT)) . '</span>';
        $html .= '<div>';
        $html .= '<h3>' . esc_html($section_title) . '</h3>';
        if ($section_description !== '') {
            $html .= '<p>' . esc_html($section_description) . '</p>';
        }
        $html .= '</div>';
        $html .= '<span class="iss-publications-browser__section-count">' . esc_html(industriesalon_publications_browser_count_text(count($posts), $attributes, 'sectionCountSingular', 'sectionCountPlural')) . '</span>';
        $html .= '</div>';
        $html .= '<div class="iss-publications-browser__strip">' . industriesalon_publications_render_grid($posts, [
            'layoutVariant' => 'strip',
            'columns' => 6,
            'show_excerpt' => true,
        ]) . '</div>';
        $html .= '</section>';
    }

    $html .= '</div>';
    $html .= '</section>';

    return $html;
}

function industriesalon_publications_render_order_panel(array $context): string
{
    if ($context === []) {
        return '';
    }

    $html = '<aside class="iss-publication-order-panel">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Bestellung', 'industriesalon') . '</p>';
    $html .= '<h2 class="iss-info-panel__title">' . esc_html__('Publikation bestellen', 'industriesalon') . '</h2>';
    $html .= '<p class="iss-publication-order-panel__price">' . esc_html((string) ($context['price_label'] ?? '')) . '</p>';
    $html .= '<p class="iss-publication-order-panel__text">' . esc_html((string) ($context['description'] ?? '')) . '</p>';

    $button_html = trim((string) ($context['button_html'] ?? ''));
    if ($button_html !== '') {
        $html .= wp_kses_post($button_html);
    } else {
        $html .= '<p class="iss-publication-order-panel__note">' . esc_html__('Die Bestellfunktion wird im nächsten Schritt angebunden.', 'industriesalon') . '</p>';
    }

    $html .= '</aside>';

    return $html;
}

function industriesalon_publications_render_meta_panel(array $context): string
{
    $summary_meta = is_array($context['summary_meta'] ?? null) ? $context['summary_meta'] : [];
    if (!$summary_meta) {
        return '';
    }

    $is_sale_brochure = !empty($context['is_sale_enabled']) && ($context['collection_kind'] ?? '') === 'brochure';
    $classes = ['iss-publication-single__meta-note'];
    if ($is_sale_brochure) {
        $classes[] = 'iss-publication-single__meta-note--brochure';
    }

    $type_label = $is_sale_brochure
        ? __('Drucksache', 'industriesalon')
        : __('Note', 'industriesalon');
    $meta_label = $is_sale_brochure
        ? __('Bibliografische Daten', 'industriesalon')
        : __('Bibliografie', 'industriesalon');

    $html = '<div class="' . esc_attr(implode(' ', $classes)) . '">';
    $html .= '<div class="iss-publication-single__meta-note-meta">';
    $html .= '<p class="iss-publication-single__meta-note-type">' . esc_html($type_label) . '</p>';
    $html .= '</div>';
    $html .= '<div class="iss-publication-single__meta-note-copy">';
    $html .= '<p class="iss-publication-single__meta-note-label">' . esc_html($meta_label) . '</p>';
    $html .= '<ul class="iss-publication-meta">';
    foreach ($summary_meta as $label => $value) {
        $html .= '<li><strong>' . esc_html((string) $label) . ':</strong> ' . esc_html((string) $value) . '</li>';
    }
    $html .= '</ul>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

add_filter('iss_publications_render_featured_publication', function ($rendered, $post_id, $context) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return $rendered;
    }

    return industriesalon_publications_render_featured_publication(is_array($context) ? $context : []);
}, 10, 3);

add_filter('iss_publications_render_grid', function ($rendered, $posts, $attributes, $args) {
    $posts = is_array($posts) ? $posts : [];
    if (!$posts) {
        return $rendered;
    }

    return industriesalon_publications_render_grid($posts, is_array($attributes) ? $attributes : []);
}, 10, 4);

add_filter('iss_publications_render_browser', function ($rendered, $payload, $attributes, $block = null) {
    return industriesalon_publications_render_browser(
        is_array($payload) ? $payload : [],
        is_array($attributes) ? $attributes : [],
        $block instanceof WP_Block ? $block : null
    );
}, 10, 4);

add_filter('iss_publications_render_order_panel', function ($rendered, $post_id, $context) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return $rendered;
    }

    return industriesalon_publications_render_order_panel(is_array($context) ? $context : []);
}, 10, 3);

add_filter('iss_publications_render_meta_panel', function ($rendered, $post_id, $context, $attributes) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return $rendered;
    }

    return industriesalon_publications_render_meta_panel(is_array($context) ? $context : []);
}, 10, 4);

add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment, $size) {
    if (!is_singular('publication') || !is_array($attr)) {
        return $attr;
    }

    $class = (string) ($attr['class'] ?? '');
    if (!str_contains($class, 'wp-post-image') || trim((string) ($attr['alt'] ?? '')) !== '') {
        return $attr;
    }

    $title = trim((string) get_the_title());
    if ($title !== '') {
        $attr['alt'] = $title;
    }

    return $attr;
}, 10, 3);

add_filter('post_thumbnail_html', function ($html, $post_id, $post_thumbnail_id, $size, $attr) {
    if (!is_singular('publication') || !is_string($html) || trim($html) === '' || !str_contains($html, 'wp-post-image') || !str_contains($html, 'alt=""')) {
        return $html;
    }

    $title = trim((string) get_the_title((int) $post_id));
    if ($title === '') {
        return $html;
    }

    return (string) preg_replace('/alt=""/', 'alt="' . esc_attr($title) . '"', $html, 1);
}, 10, 5);

add_filter('iss_publications_render_longread_content', function ($rendered, $post_id, $payload_or_content, $content = '') {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_publications_is_chaptered_longread') || !iss_publications_is_chaptered_longread($post_id)) {
        return $rendered;
    }

    $payload = is_array($payload_or_content)
        ? $payload_or_content
        : industriesalon_publications_resolve_longread_payload_from_content($post_id, (string) $payload_or_content);
    if ($payload === []) {
        return $rendered;
    }

    return industriesalon_publications_render_longread_content($post_id, $payload);
}, 10, 4);

add_filter('iss_publications_render_timeline_content', function ($rendered, $post_id, $payload, $content) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_publications_is_timeline') || !iss_publications_is_timeline($post_id)) {
        return $rendered;
    }

    $payload = is_array($payload) ? $payload : [];
    if ($payload === []) {
        return $rendered;
    }

    return industriesalon_publications_render_timeline_content($post_id, $payload);
}, 10, 4);

add_filter('iss_publications_render_photoalbum_content', function ($rendered, $post_id, $payload, $content) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_publications_is_photoalbum') || !iss_publications_is_photoalbum($post_id)) {
        return $rendered;
    }

    $payload = is_array($payload) ? $payload : [];
    if ($payload === []) {
        return $rendered;
    }

    return industriesalon_publications_render_photoalbum_content($post_id, $payload);
}, 10, 4);

add_filter('body_class', function (array $classes): array {
    if (is_singular('publication') || is_page('publikationen')) {
        $classes[] = 'iss-scheme-blue';
    }

    if (is_singular('publication')) {
        $post_id = (int) get_queried_object_id();
        $publication_skin = industriesalon_get_editorial_publication_post_skin($post_id);
        if ($publication_skin !== '') {
            $classes[] = 'iss-publication-editorial-skin-' . sanitize_html_class($publication_skin);
        }
    }

    return array_values(array_unique($classes));
}, 20);

add_filter('render_block_core/post-featured-image', function (string $block_content, array $block, WP_Block $instance): string {
    if (!is_singular('publication')) {
        return $block_content;
    }

    $post_id = (int) ($instance->context['postId'] ?? 0);
    if ($post_id <= 0) {
        $post_id = (int) get_the_ID();
    }

    $media_html = industriesalon_publications_get_photoalbum_featured_media_html($post_id);
    if ($media_html === '') {
        return $block_content;
    }

    return '<figure class="wp-block-post-featured-image iss-publication-photoalbum-featured-image">' . wp_kses_post($media_html) . '</figure>';
}, 10, 3);

add_filter('iss_publications_render_photoalbum_nav', function ($rendered, $post_id, $payload, $attributes) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_publications_is_photoalbum') || !iss_publications_is_photoalbum($post_id)) {
        return $rendered;
    }

    $payload = is_array($payload) ? $payload : [];
    $sheets = is_array($payload['sheets'] ?? null) ? array_values(array_filter($payload['sheets'], 'is_array')) : [];
    if ($sheets === []) {
        return '';
    }

    $rail_settings = function_exists('iss_publications_get_publication_rail_settings')
        ? iss_publications_get_publication_rail_settings($post_id)
        : [];

    return industriesalon_publications_render_photoalbum_nav($sheets, $rail_settings);
}, 10, 4);

add_filter('render_block_iss-graph/entity-relations', function ($block_content) {
    if (!is_singular('publication') || !function_exists('iss_publications_is_timeline')) {
        return $block_content;
    }

    $post_id = (int) get_the_ID();
    return $post_id > 0 && iss_publications_is_timeline($post_id) ? '' : $block_content;
}, 10, 1);

add_filter('render_block_core/group', function ($block_content, $block) {
    if (!is_singular('publication') || !function_exists('iss_publications_is_timeline') || !function_exists('iss_publications_is_photoalbum')) {
        return $block_content;
    }

    $post_id = (int) get_the_ID();
    $suppress_related = iss_publications_is_timeline($post_id)
        || iss_publications_is_photoalbum($post_id)
        || (function_exists('iss_publications_is_chaptered_longread') && iss_publications_is_chaptered_longread($post_id));
    if ($post_id <= 0 || !$suppress_related || !is_array($block)) {
        return $block_content;
    }

    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $class_name = (string) ($attrs['className'] ?? '');
    if (!str_contains($class_name, 'section--soft')) {
        return $block_content;
    }

    foreach ((array) ($block['innerBlocks'] ?? []) as $inner_block) {
        if (!is_array($inner_block) || ($inner_block['blockName'] ?? '') !== 'core/group') {
            continue;
        }

        foreach ((array) ($inner_block['innerBlocks'] ?? []) as $nested_block) {
            if (is_array($nested_block) && ($nested_block['blockName'] ?? '') === 'iss/related-cards') {
                return '';
            }
        }
    }

    return $block_content;
}, 10, 2);

add_filter('iss_publications_render_longread_related', function ($rendered, $post_id, $attributes) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return $rendered;
    }

    $is_chaptered_longread = function_exists('iss_publications_is_chaptered_longread') && iss_publications_is_chaptered_longread($post_id);
    $is_photoalbum = function_exists('iss_publications_is_photoalbum') && iss_publications_is_photoalbum($post_id);
    if (!$is_chaptered_longread && !$is_photoalbum) {
        return $rendered;
    }

    $show_places = true;
    if ($is_chaptered_longread) {
        $payload = industriesalon_publications_resolve_longread_payload($post_id);
        $show_places = empty($payload['has_atlas_map'] ?? false);
    }

    return industriesalon_publications_render_longread_related_rail($post_id, $show_places);
}, 10, 3);

add_filter('iss_publications_render_longread_summary', function ($rendered, $post_id, $payload, $attributes) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_publications_is_chaptered_longread') || !iss_publications_is_chaptered_longread($post_id)) {
        return $rendered;
    }

    $payload = industriesalon_publications_resolve_longread_payload($post_id, is_array($payload) ? $payload : null);
    if ($payload === []) {
        return $rendered;
    }

    return industriesalon_publications_render_longread_summary(is_array($payload['summary'] ?? null) ? $payload['summary'] : []);
}, 10, 4);

add_filter('iss_publications_render_longread_nav', function ($rendered, $post_id, $payload, $attributes) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_publications_is_chaptered_longread') || !iss_publications_is_chaptered_longread($post_id)) {
        return $rendered;
    }

    $payload = industriesalon_publications_resolve_longread_payload($post_id, is_array($payload) ? $payload : null);
    if ($payload === []) {
        return $rendered;
    }

    $rail_settings = function_exists('iss_publications_get_publication_rail_settings')
        ? iss_publications_get_publication_rail_settings($post_id)
        : [];

    return industriesalon_publications_render_longread_nav(
        is_array($payload['nav_items'] ?? null) ? $payload['nav_items'] : [],
        $rail_settings
    );
}, 10, 4);

add_filter('iss_publications_render_longread_bridge', function ($rendered, $post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_publications_is_chaptered_longread') || !iss_publications_is_chaptered_longread($post_id)) {
        return $rendered;
    }

    return '';
}, 10, 2);
