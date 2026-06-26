<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_get_price_cents($post_id) {
    return (int) iss_publications_get_meta($post_id, '_iss_publication_price_cents', 0);
}

function iss_publications_content_has_photoalbum_block(int $post_id): bool
{
    if ($post_id <= 0) {
        return false;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return false;
    }

    return has_block('iss/publication-photoalbum', $post);
}

function iss_publications_get_layout($post_id) {
    $post_id = (int) $post_id;
    $layout = sanitize_key((string) iss_publications_get_meta($post_id, '_iss_publication_layout', 'standard'));
    if (!in_array($layout, ['standard', 'longread', 'timeline', 'photoalbum'], true)) {
        $layout = 'standard';
    }

    if ($layout === 'standard' && iss_publications_content_has_photoalbum_block($post_id)) {
        return 'photoalbum';
    }

    return $layout;
}

function iss_publications_is_longread($post_id) {
    return in_array(iss_publications_get_layout($post_id), ['longread', 'timeline'], true);
}

function iss_publications_is_timeline($post_id) {
    return iss_publications_get_layout($post_id) === 'timeline';
}

function iss_publications_is_photoalbum($post_id) {
    return iss_publications_get_layout($post_id) === 'photoalbum';
}

function iss_publications_sale_enabled($post_id) {
    return !empty(get_post_meta($post_id, '_iss_publication_sale_enabled', true)) && iss_publications_get_price_cents($post_id) > 0;
}

function iss_publications_format_price($cents) {
    $cents = (int) $cents;
    if ($cents <= 0) {
        return '';
    }

    return number_format_i18n($cents / 100, 2) . ' €';
}

function iss_publications_get_type_label($post_id) {
    $terms = get_the_terms($post_id, 'publication_type');
    if (!$terms || is_wp_error($terms)) {
        return '';
    }

    $term = array_shift($terms);
    return $term instanceof WP_Term ? $term->name : '';
}

function iss_publications_get_shared_topic_taxonomy(): string
{
    if (defined('ISS_CONTENT_MODEL_TOPIC_TAXONOMY')) {
        return (string) ISS_CONTENT_MODEL_TOPIC_TAXONOMY;
    }

    return 'iss_topic';
}

function iss_publications_get_shared_topic_names($post_id): array
{
    $taxonomy = iss_publications_get_shared_topic_taxonomy();
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_the_terms((int) $post_id, $taxonomy);
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

function iss_publications_get_year_label($post_id) {
    return trim((string) iss_publications_get_meta($post_id, '_iss_publication_year', ''));
}

function iss_publications_get_card_kicker($post_id) {
    $items = [
        iss_publications_is_photoalbum($post_id)
            ? __('Fotoalbum', 'iss-publications')
            : (iss_publications_is_timeline($post_id)
                ? __('Zeitleiste', 'iss-publications')
                : (iss_publications_get_layout($post_id) === 'longread' ? __('Longread', 'iss-publications') : '')),
        iss_publications_get_type_label($post_id),
        iss_publications_get_year_label($post_id),
    ];

    return implode(' / ', array_filter($items));
}

function iss_publications_get_card_meta_items($post_id) {
    $items = [];

    $pages = (int) iss_publications_get_meta($post_id, '_iss_publication_pages', 0);
    if ($pages > 0) {
        $items[] = sprintf(__('%d Seiten', 'iss-publications'), $pages);
    }

    return $items;
}

function iss_publications_get_summary_meta($post_id) {
    $items = [
        __('Untertitel', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_subtitle', ''),
        __('Autor:in', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_author', ''),
        __('Herausgeber:in', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_editor', ''),
        __('Jahr', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_year', ''),
        __('Seiten', 'iss-publications') => (int) iss_publications_get_meta($post_id, '_iss_publication_pages', 0),
        __('Format', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_format', ''),
        __('Sprache', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_language', ''),
        __('ISBN', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_isbn', ''),
        __('Verlag', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_publisher', ''),
    ];

    $rows = [];
    foreach ($items as $label => $value) {
        if (is_int($value)) {
            if ($value > 0) {
                $rows[$label] = sprintf(__('%d Seiten', 'iss-publications'), $value);
            }
            continue;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            $rows[$label] = $value;
        }
    }

    if (iss_publications_sale_enabled($post_id)) {
        $price = iss_publications_format_price(iss_publications_get_price_cents($post_id));
        if ($price !== '') {
            $rows[__('Preis', 'iss-publications')] = $price;
        }
    }

    $topic_names = iss_publications_get_shared_topic_names($post_id);
    if (!empty($topic_names)) {
        $rows[__('Thema', 'iss-publications')] = implode(', ', $topic_names);
    }

    return $rows;
}

function iss_publications_get_archive_filter_term() {
    $supported_taxonomies = ['publication_type', 'publication_topic'];
    $shared_topic_taxonomy = iss_publications_get_shared_topic_taxonomy();
    if (taxonomy_exists($shared_topic_taxonomy)) {
        $supported_taxonomies[] = $shared_topic_taxonomy;
    }

    if (!is_tax($supported_taxonomies)) {
        return null;
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return null;
    }

    return $term;
}

function iss_publications_post_matches_archive_filter(WP_Post $post, $term): bool
{
    if (!$term instanceof WP_Term) {
        return true;
    }

    return has_term((int) $term->term_id, (string) $term->taxonomy, $post);
}

function iss_publications_post_matches_layout(WP_Post $post, string $layout): bool
{
    $layout = sanitize_key($layout);
    if ($layout === '') {
        return true;
    }

    return iss_publications_get_layout($post->ID) === $layout;
}

function iss_publications_get_featured_publication_id() {
    $posts = iss_publications_get_archive_posts();
    $fallback_id = 0;

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        if ($fallback_id <= 0) {
            $fallback_id = (int) $post->ID;
        }

        if (get_post_meta((int) $post->ID, '_iss_publication_featured', true) === '1') {
            return (int) $post->ID;
        }
    }

    return $fallback_id;
}

function iss_publications_get_featured_publication_context(int $post_id): array
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [];
    }

    return [
        'post_id' => $post_id,
        'post' => $post,
        'permalink' => (string) get_permalink($post_id),
        'excerpt' => (string) get_the_excerpt($post_id),
        'summary_meta' => iss_publications_get_summary_meta($post_id),
    ];
}

function iss_publications_get_order_panel_context(int $post_id): array
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !iss_publications_sale_enabled($post_id)) {
        return [];
    }

    $cta_label = trim((string) iss_publications_get_meta($post_id, '_iss_publication_cta_label', ''));
    if ($cta_label === '') {
        $cta_label = __('Publikation bestellen', 'iss-publications');
    }

    $description = trim((string) iss_publications_get_meta($post_id, '_iss_publication_gateway_description', ''));
    if ($description === '') {
        $description = __('Diese Publikation kann online bezahlt werden. Mit dem Kauf unterstützen Sie die Arbeit des Industriesalon Schöneweide.', 'iss-publications');
    }

    $button_html = apply_filters('iss_publications_order_button_html', '', $post_id, [
        'entity_type' => 'publication',
        'entity_id'   => $post_id,
        'title'       => get_the_title($post_id),
        'amount'      => iss_publications_get_price_cents($post_id),
        'label'       => $cta_label,
    ]);

    return [
        'post_id' => $post_id,
        'title' => (string) get_the_title($post_id),
        'cta_label' => $cta_label,
        'description' => $description,
        'price_cents' => iss_publications_get_price_cents($post_id),
        'price_label' => iss_publications_format_price(iss_publications_get_price_cents($post_id)),
        'button_html' => is_string($button_html) ? $button_html : '',
    ];
}

function iss_publications_get_meta_panel_context(int $post_id): array
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $summary_meta = iss_publications_get_summary_meta($post_id);
    if (empty($summary_meta)) {
        return [];
    }

    return [
        'post_id' => $post_id,
        'summary_meta' => $summary_meta,
        'is_timeline' => iss_publications_is_timeline($post_id),
        'is_longread' => iss_publications_is_longread($post_id),
        'is_sale_enabled' => iss_publications_sale_enabled($post_id),
        'collection_kind' => iss_publications_get_collection_kind($post_id),
    ];
}

function iss_publications_render_archive_card($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $card_label = iss_publications_sale_enabled($post_id)
        ? __('Details / bestellen', 'iss-publications')
        : __('Mehr lesen', 'iss-publications');

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return '';
    }

    if (function_exists('iss_relations_render_generic_card')) {
        return iss_relations_render_generic_card($post, [
            'kicker' => iss_publications_get_card_kicker($post_id),
            'link_text' => $card_label,
        ], [
            'layoutVariant' => 'grid',
            'show_image' => true,
            'show_excerpt' => true,
        ]);
    }

    return '';
}

function iss_publications_render_featured_publication($post_id) {
    $context = iss_publications_get_featured_publication_context((int) $post_id);
    if ($context === []) {
        return '';
    }

    $post_id = (int) $context['post_id'];
    $permalink = (string) $context['permalink'];
    $excerpt = trim((string) $context['excerpt']);
    $theme_render = apply_filters('iss_publications_render_featured_publication', '', $post_id, $context);
    if (is_string($theme_render) && trim($theme_render) !== '') {
        return $theme_render;
    }

    ob_start();
    echo '<article class="iss-publications-feature">';
    echo '<div class="iss-publications-feature__cover">';
    echo '<a class="iss-media-card iss-media-card--contain iss-media-card--soft iss-media-card--framed" href="' . esc_url($permalink) . '">';
    if (has_post_thumbnail($post_id)) {
        echo get_the_post_thumbnail($post_id, 'large');
    }
    echo '</a>';
    echo '</div>';

    echo '<div class="iss-publications-feature__content">';
    echo '<div class="iss-heading iss-heading--uncaged">';
    echo '<p class="iss-kicker">' . esc_html__('Ausgewählte Publikation', 'iss-publications') . '</p>';
    echo '<h2 class="iss-heading__title">' . esc_html(get_the_title($post_id)) . '</h2>';
    if ($excerpt !== '') {
        echo '<p class="iss-heading__text">' . esc_html($excerpt) . '</p>';
    }
    echo '</div>';

    $summary_meta = is_array($context['summary_meta'] ?? null) ? $context['summary_meta'] : [];
    if (!empty($summary_meta)) {
        echo '<ul class="iss-publication-meta">';
        foreach ($summary_meta as $label => $value) {
            echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
        }
        echo '</ul>';
    }

    echo '<p class="iss-publications-feature__action"><a class="iss-card__link" href="' . esc_url($permalink) . '">' . esc_html__('Details ansehen', 'iss-publications') . '</a></p>';
    echo '</div>';
    echo '</article>';
    return (string) ob_get_clean();
}

function iss_publications_render_order_panel($post_id) {
    $context = iss_publications_get_order_panel_context((int) $post_id);
    if ($context === []) {
        return '';
    }

    $post_id = (int) $context['post_id'];
    $theme_render = apply_filters('iss_publications_render_order_panel', '', $post_id, $context);
    if (is_string($theme_render) && trim($theme_render) !== '') {
        return $theme_render;
    }

    ob_start();
    echo '<aside class="iss-publication-order-panel">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Bestellung', 'iss-publications') . '</p>';
    echo '<h2 class="iss-info-panel__title">' . esc_html__('Publikation bestellen', 'iss-publications') . '</h2>';
    echo '<p class="iss-publication-order-panel__price">' . esc_html((string) ($context['price_label'] ?? '')) . '</p>';
    echo '<p class="iss-publication-order-panel__text">' . esc_html((string) ($context['description'] ?? '')) . '</p>';

    $button_html = (string) ($context['button_html'] ?? '');
    if (trim($button_html) !== '') {
        echo wp_kses_post($button_html);
    } else {
        echo '<p class="iss-publication-order-panel__note">' . esc_html__('Die Bestellfunktion wird im nächsten Schritt angebunden.', 'iss-publications') . '</p>';
    }

    echo '</aside>';
    return (string) ob_get_clean();
}

add_filter('body_class', function ($classes) {
    if (!is_singular(ISS_PUBLICATIONS_POST_TYPE)) {
        return $classes;
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id <= 0) {
        return $classes;
    }

    $classes[] = 'iss-publication-layout-' . sanitize_html_class(iss_publications_get_layout($post_id));
    $classes[] = 'iss-publication-kind-' . sanitize_html_class(iss_publications_get_collection_kind($post_id));
    if (iss_publications_sale_enabled($post_id)) {
        $classes[] = 'iss-publication-sale-enabled';
    }
    $has_photoalbum_payload = iss_publications_is_photoalbum($post_id) && iss_publications_get_photoalbum_payload($post_id) !== [];
    if (!has_post_thumbnail($post_id) && !$has_photoalbum_payload) {
        $classes[] = 'iss-publication-no-cover';
    }
    if (iss_publications_is_chaptered_longread($post_id)) {
        $classes[] = 'iss-publication-longread-chaptered';
    }
    if ($has_photoalbum_payload) {
        $classes[] = 'iss-publication-photoalbum-ready';
    }
    if (function_exists('iss_publications_get_publication_rail_settings')) {
        $rail_settings = iss_publications_get_publication_rail_settings($post_id);
        if (!empty($rail_settings['enabled'])) {
            $classes[] = 'iss-publication-rail-enabled';
            $variant = sanitize_html_class((string) ($rail_settings['variant'] ?? 'detailed'));
            if ($variant !== '') {
                $classes[] = 'iss-publication-rail-variant-' . $variant;
            }
        }
    }
    return $classes;
});

add_filter('the_content', function ($content) {
    if (!is_singular(ISS_PUBLICATIONS_POST_TYPE) || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id <= 0) {
        return $content;
    }

    if (iss_publications_is_timeline($post_id)) {
        if (strpos($content, 'iss-publication-chronicle') === false) {
            return $content;
        }

        $payload = iss_publications_parse_timeline_payload((string) $content);
        if ($payload === []) {
            return $content;
        }

        $theme_render = apply_filters('iss_publications_render_timeline_content', null, $post_id, $payload, (string) $content);
        return is_string($theme_render) ? $theme_render : $content;
    }

    if (iss_publications_is_photoalbum($post_id)) {
        return iss_publications_transform_album_content($post_id, (string) $content);
    }

    if (!iss_publications_is_chaptered_longread($post_id)) {
        return $content;
    }

    if (strpos((string) $content, 'iss-publication-longread') !== false) {
        return $content;
    }

    $theme_render = apply_filters('iss_publications_render_longread_content', null, $post_id, (string) $content);
    if (is_string($theme_render)) {
        return $theme_render;
    }

    return iss_publications_transform_longread_content($post_id, (string) $content);
}, 20);

function iss_publications_get_collection_kind($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 'other';
    }

    if (iss_publications_is_photoalbum($post_id)) {
        return 'album';
    }

    $type_terms = get_the_terms($post_id, 'publication_type');
    if (!empty($type_terms) && !is_wp_error($type_terms)) {
        foreach ($type_terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $slug = sanitize_title($term->slug);
            if (in_array($slug, ['buch', 'buecher', 'book', 'books'], true)) {
                return 'book';
            }

            if (in_array($slug, ['broschuere', 'broschueren', 'heft', 'hefte', 'booklet', 'booklets'], true)) {
                return 'brochure';
            }
        }
    }

    $format = sanitize_title((string) iss_publications_get_meta($post_id, '_iss_publication_format', ''));
    if ($format !== '') {
        if (str_contains($format, 'buch')) {
            return 'book';
        }

        if (str_contains($format, 'broschure') || str_contains($format, 'broschuere') || str_contains($format, 'magazin') || str_contains($format, 'heft')) {
            return 'brochure';
        }
    }

    return 'other';
}

function iss_publications_timeline_detect_moment_variant(DOMXPath $xpath, DOMElement $item): string
{
    $image = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__media ")]//img', $item)->item(0);
    if (!$image instanceof DOMElement) {
        return '';
    }

    $width = (int) $image->getAttribute('width');
    $height = (int) $image->getAttribute('height');
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

function iss_publications_timeline_get_inner_html(DOMNode $node): string
{
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }

    return $html;
}

function iss_publications_longread_get_inner_html(DOMNode $node): string
{
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }

    return $html;
}

function iss_publications_replace_inline_css_var(string $html, string $variable, string $value): string
{
    if ($html === '' || $variable === '' || $value === '') {
        return $html;
    }

    $pattern = '/(' . preg_quote($variable, '/') . '\s*:\s*)([^;"]+)/';

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

function iss_publications_transform_essay_map_html(string $html): string
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
    $html = iss_publications_replace_inline_css_var($html, '--iss-related-place-map-scale-x', '2.8000');
    $html = iss_publications_replace_inline_css_var($html, '--iss-related-place-map-scale-y', '2.4500');
    $html = iss_publications_replace_inline_css_var($html, '--iss-related-place-map-offset-x', '-106.710%');
    $html = iss_publications_replace_inline_css_var($html, '--iss-related-place-map-offset-y', '-74.308%');

    return $html;
}

function iss_publications_is_chaptered_longread(int $post_id): bool
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || iss_publications_get_layout($post_id) !== 'longread') {
        return false;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return false;
    }

    $content = (string) $post->post_content;
    if (strpos($content, '<!-- wp:iss/publication-longread') !== false) {
        return true;
    }

    return strpos($content, '<!-- wp:list -->') !== false
        && strpos($content, '<!-- wp:heading {"level":2') !== false;
}

function iss_publications_album_get_sheet_label(string $caption_text, int $index): string
{
    if (preg_match('/(?:seite|blatt)\s+(\d{1,3})/iu', $caption_text, $matches)) {
        return sprintf(__('Seite %02d', 'iss-publications'), (int) $matches[1]);
    }

    return sprintf(__('Blatt %02d', 'iss-publications'), $index + 1);
}

function iss_publications_album_get_sheet_anchor(string $sheet_label, int $index): string
{
    $slug = sanitize_title($sheet_label);
    if ($slug === '') {
        $slug = 'blatt-' . ($index + 1);
    }

    return 'iss-publication-album-' . $slug;
}

function iss_publications_album_get_display_title(string $caption_text, string $sheet_label): string
{
    $caption_text = trim(wp_strip_all_tags(html_entity_decode($caption_text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($caption_text === '') {
        return $sheet_label;
    }

    $caption_text = preg_replace('/\s+/u', ' ', $caption_text);
    $caption_text = is_string($caption_text) ? trim($caption_text) : '';
    if ($caption_text === '') {
        return $sheet_label;
    }

    if (preg_match('/^Einband\b/ui', $caption_text)) {
        return __('Einband', 'iss-publications');
    }

    $title = preg_replace('/\s+Ausschnitt aus .*$/ui', '', $caption_text);
    $title = preg_replace('/\s+Foto,\s+.*$/ui', '', (string) $title);
    $title = preg_replace('/\s+Das Digitalisat wurde vom Originalalbum angefertigt\.?$/ui', '', (string) $title);
    $title = preg_replace('/\s+Quelle und Rechte:.*$/ui', '', (string) $title);
    $title = is_string($title) ? trim($title, " \t\n\r\0\x0B,.;:-") : '';

    $normalized_length = static function (string $value): int {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    };

    $is_usable_title = static function (string $value) use ($sheet_label, $normalized_length): bool {
        $value = trim($value, " \t\n\r\0\x0B,.;:-");
        if ($value === '' || $value === $sheet_label || preg_match('/^Gesamtansicht\b/ui', $value)) {
            return false;
        }

        return $normalized_length($value) <= 110 && substr_count($value, '/') <= 4;
    };

    $lead_title = '';
    if (preg_match('/^(.*?)\s+Übersetzung der Bildunterschrift:/u', $title, $matches)) {
        $candidate = trim((string) ($matches[1] ?? ''));
        if ($candidate !== '') {
            $lead_title = $candidate;
        }
    } elseif (!preg_match('/^Übersetzung der Bildunterschrift:/u', $title)) {
        $lead_title = $title;
    }

    if ($is_usable_title($lead_title) && $normalized_length($lead_title) <= 90 && substr_count($lead_title, '/') <= 2) {
        return wp_html_excerpt($lead_title, 100, '…');
    }

    if (preg_match('/Übersetzung der Bildunterschrift:\s*[„"]([^"”]+)[”"]/u', $caption_text, $matches)) {
        $translated = trim((string) ($matches[1] ?? ''));
        if ($is_usable_title($translated)) {
            return wp_html_excerpt($translated, 100, '…');
        }
    }

    if (!$is_usable_title($title)) {
        return $sheet_label;
    }

    return wp_html_excerpt($title, 120, '…');
}

function iss_publications_photoalbum_render_block_image_html(array $attributes): string
{
    $image_id = absint($attributes['imageId'] ?? 0);
    $image_url = trim((string) ($attributes['imageUrl'] ?? ''));
    $image_alt = trim((string) ($attributes['imageAlt'] ?? ''));

    if ($image_id > 0) {
        $image_html = wp_get_attachment_image($image_id, 'large', false, [
            'alt' => $image_alt,
        ]);
        if (is_string($image_html) && trim($image_html) !== '') {
            return $image_html;
        }
    }

    if ($image_url === '') {
        return '';
    }

    return '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" />';
}

function iss_publications_photoalbum_extract_first_class_inner_html(string $content, string $class_name): string
{
    $class_name = trim($class_name);
    if (trim($content) === '' || $class_name === '' || !class_exists('DOMDocument')) {
        return '';
    }

    libxml_use_internal_errors(true);

    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div class="iss-publication-photoalbum-parser-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        libxml_clear_errors();
        return '';
    }

    $xpath = new DOMXPath($document);
    $node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class_name . ' ")]')->item(0);
    if (!$node instanceof DOMElement) {
        libxml_clear_errors();
        return '';
    }

    $html = '';
    foreach ($node->childNodes as $child_node) {
        $html .= $document->saveHTML($child_node);
    }

    libxml_clear_errors();

    return trim($html);
}

function iss_publications_photoalbum_get_html_attribute(array $attributes, string $key, string $content, string $class_name): string
{
    $value = iss_publications_decode_block_html_attribute(trim((string) ($attributes[$key] ?? '')));
    if ($value !== '') {
        return $value;
    }

    return iss_publications_photoalbum_extract_first_class_inner_html($content, $class_name);
}

function iss_publications_parse_photoalbum_block_payload(int $post_id, array $attributes, string $content, $block = null): array
{
    $parsed_block = [];
    if (is_array($block)) {
        $parsed_block = $block;
    } elseif (is_object($block) && isset($block->parsed_block) && is_array($block->parsed_block)) {
        $parsed_block = $block->parsed_block;
    }

    $inner_blocks = is_array($parsed_block['innerBlocks'] ?? null) ? $parsed_block['innerBlocks'] : [];
    $sheets = [];
    foreach ($inner_blocks as $inner_block) {
        if (!is_array($inner_block) || ($inner_block['blockName'] ?? '') !== 'iss/publication-photoalbum-sheet') {
            continue;
        }

        $sheet_attributes = is_array($inner_block['attrs'] ?? null) ? $inner_block['attrs'] : [];
        $media_html = iss_publications_photoalbum_render_block_image_html($sheet_attributes);
        if ($media_html === '') {
            continue;
        }

        $index = count($sheets);
        $sheet_label = trim((string) ($sheet_attributes['sheetLabel'] ?? ''));
        if ($sheet_label === '') {
            $sheet_label = sprintf(__('Blatt %02d', 'iss-publications'), $index + 1);
        }

        $caption_text = trim(wp_strip_all_tags((string) ($sheet_attributes['caption'] ?? '')));
        $title = trim(wp_strip_all_tags((string) ($sheet_attributes['title'] ?? '')));
        if ($title === '') {
            $title = iss_publications_album_get_display_title($caption_text, $sheet_label);
        }

        $figure_html = '<figure class="wp-block-image iss-publication-photoalbum-source__sheet">' . $media_html;
        if ($caption_text !== '') {
            $figure_html .= '<figcaption>' . esc_html($caption_text) . '</figcaption>';
        }
        $figure_html .= '</figure>';

        $sheets[] = [
            'anchor' => iss_publications_album_get_sheet_anchor($sheet_label, $index),
            'sheet_label' => $sheet_label,
            'title' => $title,
            'caption_text' => $caption_text,
            'media_html' => $media_html,
            'figure_html' => $figure_html,
            'image_url' => trim((string) ($sheet_attributes['imageUrl'] ?? '')),
        ];
    }

    if ($sheets === []) {
        return [];
    }

    $topic_names = iss_publications_get_shared_topic_names($post_id);
    $source_note = trim((string) ($attributes['sourceNote'] ?? ''));
    $heading_text = iss_publications_photoalbum_get_html_attribute($attributes, 'headingText', $content, 'iss-publication-photoalbum-source__text');
    $source_html = $source_note !== ''
        ? wp_kses_post($source_note)
        : iss_publications_photoalbum_extract_first_class_inner_html($content, 'iss-publication-source-note');
    $intro_html = iss_publications_photoalbum_get_html_attribute($attributes, 'introHtml', $content, 'iss-publication-photoalbum-source__intro');

    return [
        'heading_kicker' => trim(wp_strip_all_tags((string) ($attributes['headingKicker'] ?? ''))),
        'heading_title' => trim(wp_strip_all_tags((string) ($attributes['headingTitle'] ?? ''))),
        'heading_text' => $heading_text !== '' ? wp_kses_post($heading_text) : '',
        'intro_html' => $intro_html,
        'source_html' => $source_html !== '' ? wp_kses_post($source_html) : '',
        'summary' => [
            [
                'value' => (string) count($sheets),
                'label' => __('Albumblaetter', 'iss-publications'),
            ],
            [
                'value' => (string) max(1, count($topic_names)),
                'label' => __('Themenfelder', 'iss-publications'),
            ],
            [
                'value' => trim((string) iss_publications_get_meta($post_id, '_iss_publication_year', '')) ?: __('o. J.', 'iss-publications'),
                'label' => __('Jahr', 'iss-publications'),
            ],
            [
                'value' => __('Digitalisat', 'iss-publications'),
                'label' => __('Edition', 'iss-publications'),
            ],
        ],
        'sheet_count' => count($sheets),
        'sheets' => $sheets,
    ];
}

function iss_publications_get_photoalbum_archivset_id(int $post_id): int
{
    if ($post_id <= 0 || !defined('ISS_PUBLICATIONS_PHOTOALBUM_ARCHIVSET_META_KEY')) {
        return 0;
    }

    return absint(get_post_meta($post_id, ISS_PUBLICATIONS_PHOTOALBUM_ARCHIVSET_META_KEY, true));
}

function iss_publications_photoalbum_get_archivset_image_html(int $object_post_id, string $alt_text): array
{
    if ($object_post_id <= 0) {
        return [
            'media_html' => '',
            'image_url' => '',
        ];
    }

    $thumbnail_id = absint(get_post_thumbnail_id($object_post_id));
    if ($thumbnail_id > 0) {
        $image_html = wp_get_attachment_image($thumbnail_id, 'large', false, [
            'alt' => $alt_text,
        ]);
        $image_url = (string) wp_get_attachment_image_url($thumbnail_id, 'full');

        return [
            'media_html' => is_string($image_html) ? trim($image_html) : '',
            'image_url' => $image_url,
        ];
    }

    if (has_post_thumbnail($object_post_id)) {
        return [
            'media_html' => (string) get_the_post_thumbnail($object_post_id, 'large', ['alt' => $alt_text]),
            'image_url' => (string) get_the_post_thumbnail_url($object_post_id, 'full'),
        ];
    }

    return [
        'media_html' => '',
        'image_url' => '',
    ];
}

function iss_publications_parse_archivset_photoalbum_payload(int $post_id, int $set_id, array $base_payload = []): array
{
    if (
        $post_id <= 0
        || $set_id <= 0
        || !function_exists('iss_wf_import_get_archivset_service')
        || !defined('ISS_WF_IMPORT_OBJECT_POST_TYPE')
    ) {
        return [];
    }

    $service = iss_wf_import_get_archivset_service();
    $set = $service->get_set($set_id);
    if (!is_array($set) || (string) ($set['status'] ?? '') !== 'active') {
        return [];
    }

    $members = $service->get_members($set_id);
    if ($members === []) {
        return [];
    }

    $sheets = [];
    foreach ($members as $member) {
        if (!is_array($member) || (string) ($member['member_kind'] ?? '') !== 'object') {
            continue;
        }

        $object_post_id = absint($member['object_post_id'] ?? 0);
        $object = $object_post_id > 0 ? get_post($object_post_id) : null;
        if (
            !$object instanceof WP_Post
            || $object->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE
            || !in_array((string) $object->post_status, ['publish', 'private'], true)
        ) {
            continue;
        }

        $caption_text = trim((string) ($member['caption'] ?? ''));
        if ($caption_text === '') {
            $caption_text = trim((string) ($member['display_title'] ?? $member['member_title'] ?? $object->post_title));
        }
        if ($caption_text === '') {
            $caption_text = trim((string) $object->post_title);
        }

        $index = count($sheets);
        $sheet_label = trim((string) ($member['page_label'] ?? ''));
        if ($sheet_label === '') {
            $sheet_label = iss_publications_album_get_sheet_label($caption_text, $index);
        }

        $title = iss_publications_album_get_display_title($caption_text, $sheet_label);
        $image = iss_publications_photoalbum_get_archivset_image_html($object_post_id, $title);
        $media_html = trim((string) ($image['media_html'] ?? ''));
        if ($media_html === '') {
            continue;
        }

        $figure_html = '<figure class="wp-block-image iss-publication-photoalbum-source__sheet">' . $media_html;
        if ($caption_text !== '') {
            $figure_html .= '<figcaption>' . esc_html($caption_text) . '</figcaption>';
        }
        $figure_html .= '</figure>';

        $sheets[] = [
            'anchor' => iss_publications_album_get_sheet_anchor($sheet_label, $index),
            'sheet_label' => $sheet_label,
            'title' => $title,
            'caption_text' => $caption_text,
            'media_html' => $media_html,
            'figure_html' => $figure_html,
            'image_url' => trim((string) ($image['image_url'] ?? '')),
            'archive_object_id' => $object_post_id,
            'archive_member_id' => absint($member['id'] ?? 0),
        ];
    }

    if ($sheets === []) {
        return [];
    }

    $topic_names = iss_publications_get_shared_topic_names($post_id);
    $summary = [
        [
            'value' => (string) count($sheets),
            'label' => __('Albumblaetter', 'iss-publications'),
        ],
        [
            'value' => (string) max(1, count($topic_names)),
            'label' => __('Themenfelder', 'iss-publications'),
        ],
        [
            'value' => trim((string) iss_publications_get_meta($post_id, '_iss_publication_year', '')) ?: __('o. J.', 'iss-publications'),
            'label' => __('Jahr', 'iss-publications'),
        ],
        [
            'value' => __('Archivset', 'iss-publications'),
            'label' => __('Quelle', 'iss-publications'),
        ],
    ];

    return [
        'heading_kicker' => trim((string) ($base_payload['heading_kicker'] ?? '')),
        'heading_title' => trim((string) ($base_payload['heading_title'] ?? '')),
        'heading_text' => trim((string) ($base_payload['heading_text'] ?? '')),
        'intro_html' => trim((string) ($base_payload['intro_html'] ?? '')),
        'source_html' => trim((string) ($base_payload['source_html'] ?? '')),
        'summary' => $summary,
        'sheet_count' => count($sheets),
        'sheets' => $sheets,
        'source_archivset_id' => $set_id,
        'source_archivset_title' => trim((string) ($set['title'] ?? '')),
    ];
}

function iss_publications_parse_photoalbum_text_payload(string $content): array
{
    if (trim($content) === '' || !class_exists('DOMDocument')) {
        return [];
    }

    libxml_use_internal_errors(true);

    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div class="iss-publication-album-parser-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        libxml_clear_errors();
        return [];
    }

    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-album-parser-root ")]')->item(0);
    if (!$root instanceof DOMElement) {
        libxml_clear_errors();
        return [];
    }

    $intro_nodes = [];
    $source_nodes = [];
    foreach ($root->childNodes as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($child->tagName);
        if ($tag === 'h2' || $tag === 'figure') {
            break;
        }

        if ($tag !== 'p') {
            continue;
        }

        if ($intro_nodes === []) {
            $intro_nodes[] = trim($document->saveHTML($child));
        } else {
            $source_nodes[] = trim($document->saveHTML($child));
        }
    }

    libxml_clear_errors();

    if ($intro_nodes === [] && $source_nodes === []) {
        return [];
    }

    return [
        'intro_html' => $intro_nodes !== [] ? implode('', $intro_nodes) : '',
        'source_html' => $source_nodes !== [] ? implode('', $source_nodes) : '',
    ];
}

function iss_publications_publication_section_body_html(array $section): string
{
    $body = trim((string) ($section['body'] ?? ''));
    if ($body === '') {
        return '';
    }

    if (strpos($body, '<') !== false) {
        return wp_kses_post($body);
    }

    return wpautop(esc_html($body));
}

function iss_publications_parse_editorial_photoalbum_text_payload(int $post_id): array
{
    if (
        $post_id <= 0
        || !function_exists('iss_publications_get_editorial_publication_read_model')
        || !iss_publications_editorial_document_is_enabled($post_id)
    ) {
        return [];
    }

    $document = iss_publications_get_editorial_publication_read_model($post_id);
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    if ($sections === []) {
        return [];
    }

    $payload = [
        'heading_kicker' => '',
        'heading_title' => '',
        'heading_text' => '',
        'intro_html' => '',
        'source_html' => '',
    ];

    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $type = sanitize_key((string) ($section['type'] ?? ''));
        if ($type === 'intro') {
            if ($payload['heading_kicker'] === '') {
                $payload['heading_kicker'] = trim((string) ($section['kicker'] ?? ''));
            }
            if ($payload['heading_title'] === '') {
                $payload['heading_title'] = trim((string) ($section['title'] ?? ''));
            }
            $body = iss_publications_publication_section_body_html($section);
            if ($body !== '') {
                $payload['intro_html'] .= $body;
            }
            continue;
        }

        if ($type === 'source') {
            $title = trim((string) ($section['title'] ?? ''));
            $body = iss_publications_publication_section_body_html($section);
            $links = is_array($section['links'] ?? null) ? $section['links'] : [];
            $source_html = '';
            if ($title !== '') {
                $source_html .= '<p><strong>' . esc_html($title) . '</strong></p>';
            }
            $source_html .= $body;
            if ($links !== []) {
                $source_html .= '<ul>';
                foreach ($links as $link) {
                    if (!is_array($link)) {
                        continue;
                    }
                    $label = trim((string) ($link['label'] ?? ''));
                    $url = trim((string) ($link['url'] ?? ''));
                    if ($label === '' || $url === '') {
                        continue;
                    }
                    $source_html .= '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
                }
                $source_html .= '</ul>';
            }
            if (trim($source_html) !== '') {
                $payload['source_html'] .= $source_html;
            }
        }
    }

    return array_filter($payload, static function (string $value): bool {
        return trim($value) !== '';
    });
}

function iss_publications_photoalbum_get_attachment_image_html(int $attachment_id, string $alt_text): array
{
    if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
        return [
            'media_html' => '',
            'image_url' => '',
        ];
    }

    $image_html = wp_get_attachment_image($attachment_id, 'large', false, [
        'alt' => $alt_text,
    ]);
    $image_url = (string) wp_get_attachment_image_url($attachment_id, 'full');

    return [
        'media_html' => is_string($image_html) ? trim($image_html) : '',
        'image_url' => $image_url,
    ];
}

function iss_publications_parse_editorial_photoalbum_payload(int $post_id): array
{
    if (
        $post_id <= 0
        || !function_exists('iss_publications_get_editorial_publication_read_model')
        || !iss_publications_editorial_document_is_enabled($post_id)
    ) {
        return [];
    }

    $document = iss_publications_get_editorial_publication_read_model($post_id);
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    if ($sections === []) {
        return [];
    }

    $album_section = [];
    foreach ($sections as $section) {
        if (is_array($section) && sanitize_key((string) ($section['type'] ?? '')) === 'photoalbum') {
            $album_section = $section;
            break;
        }
    }

    $sheet_sources = is_array($album_section['sheets'] ?? null) ? $album_section['sheets'] : [];
    if ($sheet_sources === []) {
        return [];
    }

    $sheets = [];
    foreach ($sheet_sources as $sheet) {
        if (!is_array($sheet) || ($sheet['visible'] ?? true) === false) {
            continue;
        }

        $index = count($sheets);
        $source_kind = sanitize_key((string) ($sheet['source_kind'] ?? ''));
        $source_id = absint($sheet['source_id'] ?? 0);
        $raw_caption = trim((string) ($sheet['caption'] ?? ''));
        $caption_override = trim((string) ($sheet['caption_override'] ?? ''));
        $caption_text = $caption_override !== '' ? $caption_override : $raw_caption;
        $sheet_label = trim((string) ($sheet['label'] ?? ''));
        if ($sheet_label === '') {
            $sheet_label = iss_publications_album_get_sheet_label($caption_text, $index);
        }
        $title = trim((string) ($sheet['nav_title'] ?? ''));
        if ($title === '') {
            $title = iss_publications_album_get_display_title($caption_text, $sheet_label);
        }

        if ($source_kind === 'archive_object') {
            $image = iss_publications_photoalbum_get_archivset_image_html($source_id, $title);
        } elseif ($source_kind === 'wp_media') {
            $image = iss_publications_photoalbum_get_attachment_image_html($source_id, $title);
        } else {
            continue;
        }

        $media_html = trim((string) ($image['media_html'] ?? ''));
        if ($media_html === '') {
            continue;
        }

        $figure_html = '<figure class="wp-block-image iss-publication-photoalbum-source__sheet">' . $media_html;
        if ($caption_text !== '') {
            $figure_html .= '<figcaption>' . esc_html($caption_text) . '</figcaption>';
        }
        $figure_html .= '</figure>';

        $payload_sheet = [
            'anchor' => iss_publications_album_get_sheet_anchor($sheet_label, $index),
            'sheet_label' => $sheet_label,
            'title' => $title,
            'caption_text' => $caption_text,
            'media_html' => $media_html,
            'figure_html' => $figure_html,
            'image_url' => trim((string) ($image['image_url'] ?? '')),
        ];

        if ($source_kind === 'archive_object') {
            $payload_sheet['archive_object_id'] = $source_id;
            $payload_sheet['archive_member_id'] = absint($sheet['member_id'] ?? 0);
        } else {
            $payload_sheet['attachment_id'] = $source_id;
            $payload_sheet['source_item_id'] = absint($sheet['source_item_id'] ?? 0);
        }

        $sheets[] = $payload_sheet;
    }

    if ($sheets === []) {
        return [];
    }

    $base_payload = iss_publications_parse_editorial_photoalbum_text_payload($post_id);
    $topic_names = iss_publications_get_shared_topic_names($post_id);
    $album_source = is_array($album_section['album_source'] ?? null) ? $album_section['album_source'] : [];
    $source_kind = sanitize_key((string) ($album_source['kind'] ?? 'manual'));
    $source_label = __('Redaktion', 'iss-publications');
    if ($source_kind === 'archive_set') {
        $source_label = __('Archivset', 'iss-publications');
    } elseif ($source_kind === 'editorial_set') {
        $source_label = __('Set', 'iss-publications');
    }

    $payload = [
        'heading_kicker' => trim((string) ($base_payload['heading_kicker'] ?? '')),
        'heading_title' => trim((string) ($base_payload['heading_title'] ?? '')),
        'heading_text' => trim((string) ($base_payload['heading_text'] ?? '')),
        'intro_html' => trim((string) ($base_payload['intro_html'] ?? '')),
        'source_html' => trim((string) ($base_payload['source_html'] ?? '')),
        'summary' => [
            [
                'value' => (string) count($sheets),
                'label' => __('Albumblaetter', 'iss-publications'),
            ],
            [
                'value' => (string) max(1, count($topic_names)),
                'label' => __('Themenfelder', 'iss-publications'),
            ],
            [
                'value' => trim((string) iss_publications_get_meta($post_id, '_iss_publication_year', '')) ?: __('o. J.', 'iss-publications'),
                'label' => __('Jahr', 'iss-publications'),
            ],
            [
                'value' => $source_label,
                'label' => __('Quelle', 'iss-publications'),
            ],
        ],
        'sheet_count' => count($sheets),
        'sheets' => $sheets,
    ];

    $source_id = absint($album_source['set_id'] ?? 0);
    if ($source_kind === 'archive_set' && $source_id > 0) {
        $payload['source_archivset_id'] = $source_id;
        $payload['source_archivset_title'] = trim((string) ($album_source['set_title'] ?? ''));
    } elseif ($source_kind === 'editorial_set' && $source_id > 0) {
        $payload['source_editorial_set_id'] = $source_id;
        $payload['source_editorial_set_title'] = trim((string) ($album_source['set_title'] ?? ''));
    }

    return $payload;
}

function iss_publications_parse_album_payload(int $post_id, string $content): array
{
    if (strpos($content, '<!-- wp:iss/publication-photoalbum') !== false) {
        $blocks = parse_blocks($content);
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['blockName'] ?? '') === 'iss/publication-photoalbum') {
                return iss_publications_parse_photoalbum_block_payload(
                    $post_id,
                    is_array($block['attrs'] ?? null) ? $block['attrs'] : [],
                    (string) ($block['innerHTML'] ?? ''),
                    $block
                );
            }
        }
    }

    if ($post_id <= 0 || trim($content) === '' || !class_exists('DOMDocument')) {
        return [];
    }

    libxml_use_internal_errors(true);

    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div class="iss-publication-album-parser-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        libxml_clear_errors();
        return [];
    }

    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-album-parser-root ")]')->item(0);
    if (!$root instanceof DOMElement) {
        libxml_clear_errors();
        return [];
    }

    $intro_nodes = [];
    $context_nodes = [];
    $figure_nodes = [];
    $after_heading = false;

    foreach ($root->childNodes as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($child->tagName);
        if ($tag === 'h2') {
            $after_heading = true;
            continue;
        }

        if (!$after_heading) {
            if ($tag === 'p' && count($intro_nodes) === 0) {
                $intro_nodes[] = trim($document->saveHTML($child));
            } elseif ($tag === 'p') {
                $context_nodes[] = trim($document->saveHTML($child));
            }
            continue;
        }

        if ($tag === 'figure') {
            $figure_nodes[] = $child;
        }
    }

    if (count($figure_nodes) < 6) {
        libxml_clear_errors();
        return [];
    }

    $topic_names = iss_publications_get_shared_topic_names($post_id);
    $summary = [
        [
            'value' => (string) count($figure_nodes),
            'label' => __('Albumblaetter', 'iss-publications'),
        ],
        [
            'value' => (string) max(1, count($topic_names)),
            'label' => __('Themenfelder', 'iss-publications'),
        ],
        [
            'value' => trim((string) iss_publications_get_meta($post_id, '_iss_publication_year', '')) ?: __('o. J.', 'iss-publications'),
            'label' => __('Jahr', 'iss-publications'),
        ],
        [
            'value' => __('Digitalisat', 'iss-publications'),
            'label' => __('Edition', 'iss-publications'),
        ],
    ];

    $sheets = [];
    foreach ($figure_nodes as $index => $figure) {
        $figure_html = trim($document->saveHTML($figure));
        $caption_text = trim((string) $xpath->evaluate('string(.//figcaption[1])', $figure));
        $sheet_label = iss_publications_album_get_sheet_label($caption_text, $index);
        $first_image = $xpath->query('.//img[1]', $figure)->item(0);
        $image_url = '';
        $media_html = '';
        if ($first_image instanceof DOMElement) {
            $image_url = trim((string) $first_image->getAttribute('src'));
        }

        foreach ($figure->childNodes as $child_node) {
            if ($child_node instanceof DOMElement && strtolower($child_node->tagName) === 'figcaption') {
                continue;
            }

            $media_html .= trim($document->saveHTML($child_node));
        }

        $sheets[] = [
            'anchor' => iss_publications_album_get_sheet_anchor($sheet_label, $index),
            'sheet_label' => $sheet_label,
            'title' => iss_publications_album_get_display_title($caption_text, $sheet_label),
            'caption_text' => $caption_text,
            'media_html' => trim($media_html) !== '' ? trim($media_html) : $figure_html,
            'figure_html' => $figure_html,
            'image_url' => $image_url,
        ];
    }

    libxml_clear_errors();

    return [
        'intro_html' => !empty($intro_nodes) ? implode('', $intro_nodes) : '',
        'source_html' => !empty($context_nodes) ? implode('', $context_nodes) : '',
        'summary' => $summary,
        'sheet_count' => count($sheets),
        'sheets' => $sheets,
    ];
}

function iss_publications_transform_album_content(int $post_id, string $content): string
{
    $payload = iss_publications_get_photoalbum_payload($post_id);
    if ($payload === []) {
        return $content;
    }

    $theme_render = apply_filters('iss_publications_render_photoalbum_content', null, $post_id, $payload, $content);
    if (is_string($theme_render)) {
        return $theme_render;
    }

    return $content;
}

function iss_publications_get_essay_bridge_html(int $post_id): string
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_name !== 'schoeneweide-eine-ortsgeschichte') {
        return '';
    }

    $cards = [
        [
            'kicker' => __('Atlas', 'iss-publications'),
            'title' => __('Schoneweide raeumlich erkunden', 'iss-publications'),
            'text' => __('Orte, Epochen und Umbrueche im Atlas oeffnen und denselben Raum nicht linear, sondern als Netz von Dossiers lesen.', 'iss-publications'),
            'links' => [
                [
                    'label' => __('Zum Atlas', 'iss-publications'),
                    'url' => '/schoneweide/#atlas-buehne',
                ],
                [
                    'label' => __('Zur Uebersicht', 'iss-publications'),
                    'url' => '/schoneweide/',
                ],
            ],
        ],
        [
            'kicker' => __('Register', 'iss-publications'),
            'title' => __('Alle Orte und Rollen im Register', 'iss-publications'),
            'text' => __('Wer direkt nach Werken, Strassen, Instituten oder Nachnutzungen sucht, bekommt im Register den vollstaendigen Einstieg.', 'iss-publications'),
            'links' => [
                [
                    'label' => __('Zum Register', 'iss-publications'),
                    'url' => '/register-schoneweide/',
                ],
                [
                    'label' => __('Fuehrungen vor Ort', 'iss-publications'),
                    'url' => '/fuehrungen/',
                ],
            ],
        ],
    ];

    $html = '<aside class="iss-publication-essay__atlas">';
    $html .= '<div class="iss-heading iss-heading--uncaged iss-publication-essay__atlas-intro">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Weiter erkunden', 'iss-publications') . '</p>';
    $html .= '<h2 class="iss-heading__title">' . esc_html__('Dieselbe Ortsgeschichte laesst sich auch als Atlas lesen.', 'iss-publications') . '</h2>';
    $html .= '<p class="iss-heading__text">' . esc_html__('Die Publikation fuehrt linear durch Schoneweide. Atlas und Register oeffnen denselben Raum parallel als Orte, Geschichten und Dossiers.', 'iss-publications') . '</p>';
    $html .= '</div>';
    $html .= '<div class="iss-publication-essay__atlas-grid">';

    foreach ($cards as $card) {
        $html .= '<article class="iss-publication-essay__atlas-card">';
        $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html((string) $card['kicker']) . '</p>';
        $html .= '<h3 class="iss-publication-essay__atlas-title">' . esc_html((string) $card['title']) . '</h3>';
        $html .= '<p class="iss-publication-essay__atlas-text">' . esc_html((string) $card['text']) . '</p>';
        $html .= '<p class="iss-publication-essay__atlas-links">';
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

function iss_publications_parse_longread_payload(int $post_id, string $content): array
{
    if (strpos($content, '<!-- wp:iss/publication-longread') !== false) {
        $blocks = parse_blocks($content);
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['blockName'] ?? '') === 'iss/publication-longread') {
                return iss_publications_parse_longread_block_payload(
                    $post_id,
                    is_array($block['attrs'] ?? null) ? $block['attrs'] : [],
                    (string) ($block['innerHTML'] ?? ''),
                    $block
                );
            }
        }
    }

    if ($post_id <= 0 || trim($content) === '' || !class_exists('DOMDocument')) {
        return [];
    }

    libxml_use_internal_errors(true);

    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div class="iss-publication-essay-parser-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        libxml_clear_errors();
        return [];
    }

    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-essay-parser-root ")]')->item(0);
    if (!$root instanceof DOMElement) {
        libxml_clear_errors();
        return [];
    }

    $source_note_html = '';
    $intro_nodes = [];
    $nav_node = null;
    $sections = [];
    $current_section = null;
    $lead_map_html = '';
    $subheading_count = 0;

    foreach ($root->childNodes as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }

        $class_attr = ' ' . trim((string) $child->getAttribute('class')) . ' ';
        $tag = strtolower($child->tagName);

        if (strpos($class_attr, ' iss-publication-source-note ') !== false) {
            $source_note_html = trim($document->saveHTML($child));
            continue;
        }

        if ($tag === 'ul' && $current_section === null && $nav_node === null) {
            $nav_node = $child;
            continue;
        }

        if ($tag === 'h2') {
            if ($current_section !== null) {
                $sections[] = $current_section;
            }

            $anchor = trim((string) $child->getAttribute('id'));
            if ($anchor === '') {
                $anchor = sanitize_title($child->textContent);
            }

            $current_section = [
                'anchor' => $anchor,
                'title' => trim((string) $child->textContent),
                'body' => [],
            ];
            continue;
        }

        if ($current_section === null) {
            $intro_nodes[] = trim($document->saveHTML($child));
            continue;
        }

        if ($tag === 'h3') {
            $subheading_count++;
        }

        $current_section['body'][] = trim($document->saveHTML($child));
    }

    if ($current_section !== null) {
        $sections[] = $current_section;
    }

    if (count($sections) < 3) {
        libxml_clear_errors();
        return [];
    }

    foreach ($sections as $section_index => $section) {
        $body = is_array($section['body'] ?? null) ? $section['body'] : [];
        foreach ($body as $body_index => $body_html) {
            if ($lead_map_html !== '' || strpos((string) $body_html, 'iss-related-place-map') === false) {
                continue;
            }

            $lead_map_html = trim((string) $body_html);
            unset($body[$body_index]);
            $sections[$section_index]['body'] = array_values(array_filter($body, static function ($item): bool {
                return trim((string) $item) !== '';
            }));
            break 2;
        }
    }

    $topic_names = iss_publications_get_shared_topic_names($post_id);
    $summary = [
        [
            'value' => (string) count($sections),
            'label' => __('Kapitel', 'iss-publications'),
        ],
        [
            'value' => (string) $subheading_count,
            'label' => __('Unterthemen', 'iss-publications'),
        ],
        [
            'value' => (string) max(1, count($topic_names)),
            'label' => __('Themenfelder', 'iss-publications'),
        ],
        [
            'value' => __('Ort', 'iss-publications'),
            'label' => __('Schoneweide', 'iss-publications'),
        ],
    ];

    $nav_items = [];
    if ($nav_node instanceof DOMElement) {
        foreach ($nav_node->getElementsByTagName('a') as $link) {
            $href = trim((string) $link->getAttribute('href'));
            $label = trim((string) $link->textContent);
            if ($href !== '' && $label !== '') {
                $nav_items[] = [
                    'href' => $href,
                    'label' => $label,
                ];
            }
        }
    }

    if (empty($nav_items)) {
        foreach ($sections as $section) {
            $nav_items[] = [
                'href' => '#' . $section['anchor'],
                'label' => $section['title'],
            ];
        }
    }

    $intro_copy_html = !empty($intro_nodes) ? implode('', $intro_nodes) : '';
    $intro_html = '';
    $lead_map_render_html = $lead_map_html !== '' ? iss_publications_transform_essay_map_html($lead_map_html) : '';
    if (!empty($intro_nodes)) {
        $intro_copy = '<div class="iss-publication-essay__intro">' . $intro_copy_html . '</div>';
        if ($lead_map_render_html !== '') {
            $intro_html = '<div class="iss-publication-essay__lead">' . $intro_copy . '<div class="iss-publication-essay__map">' . $lead_map_render_html . '</div></div>';
        } else {
            $intro_html = $intro_copy;
        }
    } elseif ($lead_map_render_html !== '') {
        $intro_html = '<div class="iss-publication-essay__lead"><div class="iss-publication-essay__map">' . $lead_map_render_html . '</div></div>';
    }

    libxml_clear_errors();

    return [
        'source_note_html' => $source_note_html,
        'intro_copy_html' => $intro_copy_html,
        'lead_map_html' => $lead_map_html,
        'intro_html' => $intro_html,
        'summary' => $summary,
        'nav_items' => $nav_items,
        'bridge_html' => iss_publications_get_essay_bridge_html($post_id),
        'sections' => $sections,
    ];
}

function iss_publications_count_longread_subheadings(string $html): int
{
    if ($html === '') {
        return 0;
    }

    if (preg_match_all('/<h3(?:\s|>)/i', $html, $matches)) {
        return count($matches[0]);
    }

    return 0;
}

function iss_publications_decode_block_html_attribute(string $value): string
{
    if ($value === '') {
        return '';
    }

    $decoded = str_replace(
        ['u003c', 'u003e', 'u0022', 'u0027', 'u002d'],
        ['<', '>', '"', "'", '-'],
        $value
    );

    return trim($decoded);
}

function iss_publications_parse_longread_block_payload(int $post_id, array $attributes, string $content, $block = null): array
{
    if ($post_id <= 0) {
        return [];
    }

    $parsed_block = [];
    if (is_array($block)) {
        $parsed_block = $block;
    } elseif (is_object($block) && isset($block->parsed_block) && is_array($block->parsed_block)) {
        $parsed_block = $block->parsed_block;
    }

    $inner_blocks = is_array($parsed_block['innerBlocks'] ?? null) ? $parsed_block['innerBlocks'] : [];
    $sections = [];
    $subheading_count = 0;
    $lead_map_html = '';
    $show_atlas_map = ($attributes['showAtlasMap'] ?? true) !== false;
    $has_atlas_map = false;

    foreach ($inner_blocks as $inner_block) {
        if (!is_array($inner_block) || ($inner_block['blockName'] ?? '') !== 'iss/publication-longread-chapter') {
            continue;
        }

        $chapter_attrs = is_array($inner_block['attrs'] ?? null) ? $inner_block['attrs'] : [];
        $title = trim((string) ($chapter_attrs['title'] ?? ''));
        if ($title === '') {
            continue;
        }

        $anchor = trim((string) ($chapter_attrs['anchor'] ?? ''));
        if ($anchor === '') {
            $anchor = sanitize_title($title);
        }

        $body_parts = [];
        foreach (is_array($inner_block['innerBlocks'] ?? null) ? $inner_block['innerBlocks'] : [] as $body_block) {
            if (!is_array($body_block)) {
                continue;
            }

            $block_name = (string) ($body_block['blockName'] ?? '');
            $body_piece = trim(render_block($body_block));
            if ($block_name === 'iss/related-place-map' || strpos($body_piece, 'iss-related-place-map') !== false) {
                $has_atlas_map = $show_atlas_map;
                if ($show_atlas_map && $lead_map_html === '') {
                    $lead_map_html = $body_piece;
                }
                continue;
            }

            if ($body_piece === '') {
                continue;
            }

            $body_parts[] = $body_piece;
        }
        $body_html = implode('', $body_parts);
        $subheading_count += iss_publications_count_longread_subheadings($body_html);

        $sections[] = [
            'anchor' => $anchor,
            'title' => $title,
            'body' => $body_html !== '' ? [$body_html] : [],
        ];
    }

    if ($sections === []) {
        return [];
    }

    $intro_html = iss_publications_decode_block_html_attribute(trim((string) ($attributes['introHtml'] ?? '')));
    if ($intro_html === '' && trim($content) !== '' && class_exists('DOMDocument')) {
        libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div class="iss-publication-longread-parser-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if ($loaded) {
            $xpath = new DOMXPath($document);
            $intro_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-longread-source__intro ")]')->item(0);
            if ($intro_node instanceof DOMElement) {
                $intro = '';
                foreach ($intro_node->childNodes as $child) {
                    $intro .= $document->saveHTML($child);
                }
                $intro_html = trim($intro);
            }
        }
        libxml_clear_errors();
    }
    $source_note = trim((string) ($attributes['sourceNote'] ?? ''));
    $source_note_html = $source_note !== ''
        ? '<p class="iss-publication-source-note">' . wp_kses_post($source_note) . '</p>'
        : '';

    $topic_names = iss_publications_get_shared_topic_names($post_id);
    $summary_visibility = [
        'chapters' => ($attributes['showSummaryChapters'] ?? true) !== false,
        'subheadings' => ($attributes['showSummarySubheadings'] ?? true) !== false,
        'topics' => ($attributes['showSummaryTopics'] ?? true) !== false,
        'context' => ($attributes['showSummaryContext'] ?? true) !== false,
    ];
    $summary_labels = [
        'chapters' => trim((string) ($attributes['summaryChaptersLabel'] ?? '')) ?: __('Kapitel', 'iss-publications'),
        'subheadings' => trim((string) ($attributes['summarySubheadingsLabel'] ?? '')) ?: __('Unterthemen', 'iss-publications'),
        'topics' => trim((string) ($attributes['summaryTopicsLabel'] ?? '')) ?: __('Themenfelder', 'iss-publications'),
        'context' => trim((string) ($attributes['summaryContextLabel'] ?? '')) ?: __('Kontext', 'iss-publications'),
    ];
    $summary = [];
    if ($summary_visibility['chapters']) {
        $summary[] = ['value' => (string) count($sections), 'label' => $summary_labels['chapters']];
    }
    if ($summary_visibility['subheadings']) {
        $summary[] = ['value' => (string) $subheading_count, 'label' => $summary_labels['subheadings']];
    }
    if ($summary_visibility['topics']) {
        $summary[] = ['value' => (string) max(1, count($topic_names)), 'label' => $summary_labels['topics']];
    }
    if ($summary_visibility['context']) {
        $summary[] = [
            'value' => trim((string) ($attributes['summaryContextValue'] ?? '')) ?: __('Ort', 'iss-publications'),
            'label' => $summary_labels['context'],
        ];
    }

    $nav_items = [];
    foreach ($sections as $section) {
        $nav_items[] = [
            'href' => '#' . $section['anchor'],
            'label' => $section['title'],
        ];
    }

    return [
        'source_note_html' => $source_note_html,
        'intro_copy_html' => $intro_html,
        'lead_map_html' => $lead_map_html,
        'has_atlas_map' => $has_atlas_map,
        'show_atlas_map' => $show_atlas_map,
        'intro_html' => $intro_html !== '' ? '<div class="iss-publication-longread__intro">' . $intro_html . '</div>' : '',
        'summary' => $summary,
        'nav_items' => $nav_items,
        'bridge_html' => iss_publications_get_essay_bridge_html($post_id),
        'sections' => $sections,
        'summary_visibility' => $summary_visibility,
        'summary_labels' => $summary_labels,
    ];
}

function iss_publications_get_longread_payload(int $post_id): array
{
    static $cache = [];

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [];
    }

    $cache_key = $post_id . ':' . md5((string) $post->post_content);
    if (!array_key_exists($cache_key, $cache)) {
        $cache[$cache_key] = iss_publications_parse_longread_payload($post_id, (string) $post->post_content);
    }

    return is_array($cache[$cache_key]) ? $cache[$cache_key] : [];
}

function iss_publications_get_photoalbum_payload(int $post_id): array
{
    static $cache = [];

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [];
    }

    $set_id = iss_publications_get_photoalbum_archivset_id($post_id);
    $editorial_document = '';
    if (function_exists('iss_editorial_get_document_meta_key')) {
        $stored_document = get_post_meta($post_id, iss_editorial_get_document_meta_key('publication'), true);
        $editorial_document = is_string($stored_document) ? $stored_document : '';
    }
    $cache_key = $post_id . ':' . $set_id . ':' . md5((string) $post->post_content) . ':' . md5($editorial_document);
    if (!array_key_exists($cache_key, $cache)) {
        $editorial_payload = iss_publications_parse_editorial_photoalbum_payload($post_id);
        if ($editorial_payload !== []) {
            $cache[$cache_key] = $editorial_payload;
            return $cache[$cache_key];
        }

        $base_payload = iss_publications_parse_editorial_photoalbum_text_payload($post_id);
        if ($base_payload === []) {
            $base_payload = iss_publications_parse_album_payload($post_id, (string) $post->post_content);
        }
        if ($base_payload === []) {
            $base_payload = iss_publications_parse_photoalbum_text_payload((string) $post->post_content);
        }
        $set_payload = $set_id > 0
            ? iss_publications_parse_archivset_photoalbum_payload($post_id, $set_id, $base_payload)
            : [];
        $cache[$cache_key] = $set_payload !== [] ? $set_payload : $base_payload;
    }

    return is_array($cache[$cache_key]) ? $cache[$cache_key] : [];
}

function iss_publications_render_longread_summary_html(array $summary): string
{
    if (!$summary) {
        return '';
    }

    $html = '<div class="iss-publication-essay__summary" aria-label="' . esc_attr__('Rahmendaten', 'iss-publications') . '">';
    foreach ($summary as $item) {
        $html .= '<p class="iss-publication-essay__summary-item"><strong>' . esc_html((string) ($item['value'] ?? '')) . '</strong><span>' . esc_html((string) ($item['label'] ?? '')) . '</span></p>';
    }
    $html .= '</div>';

    return $html;
}

function iss_publications_render_longread_nav_html(array $nav_items): string
{
    if (!$nav_items) {
        return '';
    }

    $html = '<nav class="iss-reading-nav iss-publication-essay__nav" data-reading-nav-mode="stack" aria-label="' . esc_attr__('Kapitelnavigation', 'iss-publications') . '"><div class="iss-reading-nav__inner iss-publication-essay__nav-inner">';
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

function iss_publications_transform_longread_content(int $post_id, string $content): string
{
    $payload = iss_publications_parse_longread_payload($post_id, $content);
    if ($payload === []) {
        return $content;
    }

    $source_note_html = (string) ($payload['source_note_html'] ?? '');
    $intro_html = (string) ($payload['intro_html'] ?? '');
    $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
    $chapters_html = '<div class="iss-publication-essay__chapters">';
    foreach ($sections as $index => $section) {
        $chapters_html .= '<section id="' . esc_attr($section['anchor']) . '" class="iss-publication-chapter">';
        $chapters_html .= '<div class="iss-publication-chapter__aside">';
        $chapters_html .= '<p class="iss-publication-chapter__index">' . esc_html(sprintf(__('Kapitel %02d', 'iss-publications'), $index + 1)) . '</p>';
        $chapters_html .= '<h2 class="iss-publication-chapter__title">' . esc_html($section['title']) . '</h2>';
        $chapters_html .= '</div>';
        $chapters_html .= '<div class="iss-publication-chapter__body">' . implode('', $section['body']);
        $chapters_html .= '<p class="iss-publication-chapter__atlas-link"><a href="' . esc_url(home_url('/schoneweide/#atlas-buehne')) . '">' . esc_html__('Im Atlas weiterlesen', 'iss-publications') . '</a></p>';
        $chapters_html .= '</div>';
        $chapters_html .= '</section>';
    }
    $chapters_html .= '</div>';

    $html = '<div class="iss-publication-essay">';
    if ($source_note_html !== '') {
        $html .= $source_note_html;
    }
    $html .= $intro_html;
    $html .= $chapters_html;
    $html .= '</div>';
    return $html;
}

function iss_publications_parse_timeline_payload(string $content): array
{
    if (trim($content) === '' || !class_exists('DOMDocument')) {
        return [];
    }

    libxml_use_internal_errors(true);

    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div class="iss-publication-timeline-parser-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        libxml_clear_errors();
        return [];
    }

    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-timeline-parser-root ")]')->item(0);
    $chronicle = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle ")]', $root)->item(0);
    if (!$root instanceof DOMElement || !$chronicle instanceof DOMElement) {
        libxml_clear_errors();
        return [];
    }

    $source_note_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-source-note ")]', $root)->item(0);
    $source_note = $source_note_node instanceof DOMElement && trim((string) $source_note_node->textContent) !== ''
        ? trim(iss_publications_timeline_get_inner_html($source_note_node))
        : '';

    $item_nodes = $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__item ")]', $chronicle);
    if (!$item_nodes || $item_nodes->length === 0) {
        libxml_clear_errors();
        return [];
    }

    $items = [];

    foreach ($item_nodes as $item) {
        if (!$item instanceof DOMElement) {
            continue;
        }

        $year_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__year ")]', $item)->item(0);
        $card_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__card ")]', $item)->item(0);
        if (!$year_node instanceof DOMElement || !$card_node instanceof DOMElement) {
            continue;
        }

        $year_text = trim($year_node->textContent);
        if (!preg_match('/\d{4}/', $year_text, $matches)) {
            continue;
        }

        $year = (int) $matches[0];
        $title = trim((string) $xpath->evaluate('string(.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__title ")][1])', $item));
        $image_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__media ")]//img', $item)->item(0);
        $text_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__text ")]', $item)->item(0);

        $items[] = [
            'year' => $year,
            'title' => $title,
            'image_id' => $image_node instanceof DOMElement && preg_match('/wp-image-(\d+)/', $image_node->getAttribute('class'), $image_matches) ? (int) $image_matches[1] : 0,
            'image_url' => $image_node instanceof DOMElement ? esc_url_raw($image_node->getAttribute('src')) : '',
            'image_alt' => $image_node instanceof DOMElement ? trim($image_node->getAttribute('alt')) : '',
            'image_width' => $image_node instanceof DOMElement ? (int) $image_node->getAttribute('width') : 0,
            'image_height' => $image_node instanceof DOMElement ? (int) $image_node->getAttribute('height') : 0,
            'body_html' => $text_node instanceof DOMElement ? trim(iss_publications_timeline_get_inner_html($text_node)) : '',
        ];
    }

    if (empty($items)) {
        libxml_clear_errors();
        return [];
    }

    libxml_clear_errors();

    return [
        'source_note' => $source_note,
        'items' => $items,
    ];
}

function iss_publications_render_timeline_inner_blocks(array $blocks): string
{
    if ($blocks === []) {
        return '';
    }

    $html = '';
    foreach ($blocks as $block) {
        if (is_array($block)) {
            $html .= render_block($block);
        }
    }

    return trim($html);
}

function iss_publications_parse_timeline_block_payload(array $attributes, string $content, $block = null): array
{
    $source_note = trim((string) ($attributes['sourceNote'] ?? ''));
    $items = [];
    $inner_blocks = [];

    if ($block instanceof WP_Block && is_array($block->parsed_block['innerBlocks'] ?? null)) {
        $inner_blocks = $block->parsed_block['innerBlocks'];
    }

    foreach ($inner_blocks as $inner_block) {
        if (!is_array($inner_block) || ($inner_block['blockName'] ?? '') !== 'iss/publication-timeline-item') {
            continue;
        }

        $item_attrs = is_array($inner_block['attrs'] ?? null) ? $inner_block['attrs'] : [];
        $year_text = trim((string) ($item_attrs['year'] ?? ''));
        if (!preg_match('/\d{4}/', $year_text, $matches)) {
            continue;
        }

        $items[] = [
            'year' => (int) $matches[0],
            'title' => trim((string) ($item_attrs['title'] ?? '')),
            'image_id' => (int) ($item_attrs['imageId'] ?? 0),
            'image_url' => esc_url_raw((string) ($item_attrs['imageUrl'] ?? '')),
            'image_alt' => trim((string) ($item_attrs['imageAlt'] ?? '')),
            'body_html' => iss_publications_render_timeline_inner_blocks(is_array($inner_block['innerBlocks'] ?? null) ? $inner_block['innerBlocks'] : []),
        ];
    }

    if ($items === []) {
        return iss_publications_parse_timeline_payload($content);
    }

    $summary_labels = [
        'start' => trim((string) ($attributes['summaryStartLabel'] ?? '')),
        'end' => trim((string) ($attributes['summaryEndLabel'] ?? '')),
        'items' => trim((string) ($attributes['summaryItemsLabel'] ?? '')),
        'epochs' => trim((string) ($attributes['summaryEpochsLabel'] ?? '')),
    ];

    return [
        'source_note' => $source_note,
        'summary_visibility' => [
            'start' => ($attributes['showSummaryStart'] ?? true) !== false,
            'end' => ($attributes['showSummaryEnd'] ?? true) !== false,
            'items' => ($attributes['showSummaryItems'] ?? true) !== false,
            'epochs' => ($attributes['showSummaryEpochs'] ?? true) !== false,
        ],
        'summary_labels' => $summary_labels,
        'items' => $items,
    ];
}

function iss_publications_get_timeline_payload(int $post_id): array
{
    static $cache = [];

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [];
    }

    $cache_key = $post_id . ':' . md5((string) $post->post_content);
    if (!array_key_exists($cache_key, $cache)) {
        $cache[$cache_key] = iss_publications_parse_timeline_payload((string) $post->post_content);
    }

    return is_array($cache[$cache_key]) ? $cache[$cache_key] : [];
}

function iss_publications_get_archive_posts($args = []) {
    $layout = isset($args['iss_publication_layout']) ? sanitize_key((string) $args['iss_publication_layout']) : '';
    unset($args['iss_publication_layout']);

    $defaults = [
        'post_type'      => ISS_PUBLICATIONS_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ],
    ];

    if (!in_array($layout, ['', 'standard', 'longread', 'timeline', 'photoalbum'], true)) {
        $layout = '';
    }

    $query_args = wp_parse_args($args, $defaults);
    $limit = isset($query_args['posts_per_page']) ? (int) $query_args['posts_per_page'] : -1;
    $filter_term = iss_publications_get_archive_filter_term();
    $needs_runtime_filter = $layout !== '' || $filter_term instanceof WP_Term;

    if ($needs_runtime_filter && $limit > 0) {
        $query_args['posts_per_page'] = -1;
    }

    $posts = get_posts($query_args);
    if (!$needs_runtime_filter) {
        return $posts;
    }

    $posts = array_values(array_filter($posts, static function ($post) use ($layout, $filter_term): bool {
        return $post instanceof WP_Post
            && iss_publications_post_matches_archive_filter($post, $filter_term)
            && iss_publications_post_matches_layout($post, $layout);
    }));

    return $limit > 0 ? array_slice($posts, 0, $limit) : $posts;
}

function iss_publications_get_browser_format_keys(): array
{
    return [
        'standard',
        'timeline',
        'longread',
        'photoalbum',
    ];
}

function iss_publications_get_browser_filter_params(): array
{
    return ['bezug'];
}

function iss_publications_get_browser_request_value(string $key): string
{
    $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
    if ($value === null || $value === false || is_array($value)) {
        return '';
    }

    return (string) $value;
}

function iss_publications_normalize_browser_state(array $source = []): array
{
    return [
        'shared_topic' => sanitize_title((string) ($source['bezug'] ?? $source['shared_topic'] ?? iss_publications_get_browser_request_value('bezug'))),
    ];
}

function iss_publications_get_browser_taxonomy_map(): array
{
    $shared_topic_taxonomy = iss_publications_get_shared_topic_taxonomy();
    if (taxonomy_exists($shared_topic_taxonomy)) {
        return [
            $shared_topic_taxonomy => [
                'state_key' => 'shared_topic',
                'param' => 'bezug',
            ],
        ];
    }

    return [];
}

function iss_publications_get_browser_all_posts(): array
{
    $posts = get_posts([
        'post_type' => ISS_PUBLICATIONS_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => [
            'menu_order' => 'ASC',
            'date' => 'DESC',
        ],
        'suppress_filters' => true,
    ]);

    return array_values(array_filter($posts, static function ($post): bool {
        return $post instanceof WP_Post;
    }));
}

function iss_publications_get_browser_format_key(int $post_id): string
{
    $layout = iss_publications_get_layout($post_id);
    $formats = iss_publications_get_browser_format_keys();

    return in_array($layout, $formats, true) ? $layout : 'standard';
}

function iss_publications_get_browser_taxonomy_options(array $posts, string $taxonomy): array
{
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $options = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $terms = get_the_terms($post, $taxonomy);
        if (is_wp_error($terms) || !is_array($terms)) {
            continue;
        }

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $slug = sanitize_title((string) $term->slug);
            if ($slug === '') {
                continue;
            }

            if (!isset($options[$slug])) {
                $options[$slug] = [
                    'slug' => $slug,
                    'label' => trim((string) $term->name),
                    'count' => 0,
                ];
            }

            $options[$slug]['count']++;
        }
    }

    usort($options, static function (array $left, array $right): int {
        $count_compare = ((int) $right['count']) <=> ((int) $left['count']);
        if ($count_compare !== 0) {
            return $count_compare;
        }

        return strcasecmp((string) $left['label'], (string) $right['label']);
    });

    return array_values($options);
}

function iss_publications_get_browser_filter_groups(array $all_posts, array $state): array
{
    $groups = [];

    foreach (iss_publications_get_browser_taxonomy_map() as $taxonomy => $config) {
        $state_key = (string) ($config['state_key'] ?? '');
        $options = iss_publications_get_browser_taxonomy_options($all_posts, (string) $taxonomy);

        foreach ($options as &$option) {
            $option['active'] = $state_key !== '' && (string) ($state[$state_key] ?? '') === (string) ($option['slug'] ?? '');
        }
        unset($option);

        $groups[] = [
            'key' => sanitize_key((string) $taxonomy),
            'taxonomy' => (string) $taxonomy,
            'state_key' => $state_key,
            'param' => (string) ($config['param'] ?? ''),
            'options' => $options,
        ];
    }

    return $groups;
}

function iss_publications_browser_post_matches_state(WP_Post $post, array $state): bool
{
    foreach (iss_publications_get_browser_taxonomy_map() as $taxonomy => $config) {
        $state_key = (string) ($config['state_key'] ?? '');
        $selected = $state_key !== '' ? (string) ($state[$state_key] ?? '') : '';
        if ($selected !== '' && !has_term($selected, (string) $taxonomy, $post)) {
            return false;
        }
    }

    return true;
}

function iss_publications_get_browser_payload(array $attributes = []): array
{
    $state = iss_publications_normalize_browser_state();
    $all_posts = iss_publications_get_browser_all_posts();
    $filter_groups = iss_publications_get_browser_filter_groups($all_posts, $state);
    $filtered_posts = array_values(array_filter($all_posts, static function ($post) use ($state): bool {
        return $post instanceof WP_Post && iss_publications_browser_post_matches_state($post, $state);
    }));

    $sections = [];
    foreach (iss_publications_get_browser_format_keys() as $format) {
        $section_posts = array_values(array_filter($filtered_posts, static function ($post) use ($format): bool {
            return $post instanceof WP_Post && iss_publications_get_browser_format_key((int) $post->ID) === $format;
        }));

        if (!$section_posts) {
            continue;
        }

        $sections[] = [
            'key' => $format,
            'posts' => $section_posts,
            'count' => count($section_posts),
        ];
    }

    $base_url = get_permalink((int) get_queried_object_id());
    if (!is_string($base_url) || $base_url === '') {
        $base_url = home_url('/publikationen/');
    }

    return [
        'state' => $state,
        'base_url' => $base_url,
        'params' => iss_publications_get_browser_filter_params(),
        'filter_groups' => $filter_groups,
        'sections' => $sections,
        'posts' => $filtered_posts,
        'total' => count($filtered_posts),
        'unfiltered_total' => count($all_posts),
    ];
}

function iss_publications_render_browser_fallback(array $payload): string
{
    $posts = is_array($payload['posts'] ?? null) ? $payload['posts'] : [];
    if (!$posts) {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        foreach ($sections as $section) {
            if (!is_array($section) || !is_array($section['posts'] ?? null)) {
                continue;
            }

            $posts = array_merge($posts, $section['posts']);
        }
    }

    $posts = array_values(array_filter($posts, static function ($post): bool {
        return $post instanceof WP_Post;
    }));
    if (!$posts) {
        return '';
    }

    return '<div class="iss-publications-browser">' . iss_publications_render_grid_posts($posts) . '</div>';
}

function iss_publications_partition_archive_posts($posts) {
    $groups = [
        'brochure' => [],
        'book'     => [],
        'other'    => [],
    ];

    foreach ((array) $posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $kind = iss_publications_get_collection_kind($post->ID);
        if (!isset($groups[$kind])) {
            $kind = 'other';
        }

        $groups[$kind][] = $post;
    }

    return $groups;
}

function iss_publications_block_resolve_post_id($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];

    if (isset($attributes['postId'])) {
        $post_id = (int) $attributes['postId'];
        if ($post_id > 0) {
            return $post_id;
        }
    }

    $post_id = (int) get_the_ID();
    return $post_id > 0 ? $post_id : 0;
}

function iss_publications_render_featured_block($attributes = [], $content = '') {
    $post_id = isset($attributes['postId']) ? (int) $attributes['postId'] : 0;
    if ($post_id <= 0) {
        $post_id = iss_publications_get_featured_publication_id();
    }

    $html = iss_publications_render_featured_publication($post_id);
    if ($html === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-featured-publication'])
        : 'class="wp-block-iss-featured-publication"';

    return '<div ' . $wrapper . '>' . $html . '</div>';
}

function iss_publications_render_grid_posts($posts) {
    $posts = array_values(array_filter((array) $posts, static function ($post) {
        return $post instanceof WP_Post;
    }));

    if (empty($posts)) {
        return '';
    }

    ob_start();
    echo '<div class="iss-related-feed__grid iss-related-feed__grid--publication iss-related-feed__grid--layout-grid iss-related-feed__grid--columns-3">';
    foreach ($posts as $post) {
        echo wp_kses_post(iss_publications_render_archive_card($post->ID));
    }
    echo '</div>';

    return (string) ob_get_clean();
}

function iss_publications_render_grid_block($attributes = [], $content = '') {
    $limit = isset($attributes['limit']) ? max(1, (int) $attributes['limit']) : 6;
    $exclude_featured = !empty($attributes['excludeFeatured']);
    $layout = isset($attributes['layout']) ? sanitize_key((string) $attributes['layout']) : '';
    $include_ids = isset($attributes['includeIds']) && is_array($attributes['includeIds']) ? array_values(array_filter(array_map('absint', $attributes['includeIds']))) : [];
    $exclude_ids = isset($attributes['excludeIds']) && is_array($attributes['excludeIds']) ? array_values(array_filter(array_map('absint', $attributes['excludeIds']))) : [];
    $args = [
        'posts_per_page' => $limit,
    ];

    if (!empty($include_ids)) {
        $args['post__in'] = $include_ids;
        $args['orderby'] = 'post__in';
        $args['posts_per_page'] = count($include_ids);
    }

    if (!empty($exclude_ids)) {
        $args['post__not_in'] = $exclude_ids;
    }

    if ($exclude_featured) {
        $featured_id = iss_publications_get_featured_publication_id();
        if ($featured_id > 0) {
            $args['post__not_in'] = array_values(array_unique(array_merge($args['post__not_in'] ?? [], [$featured_id])));
        }
    }

    if ($layout !== '') {
        $args['iss_publication_layout'] = $layout;
    }

    $posts = iss_publications_get_archive_posts($args);
    $theme_render = apply_filters('iss_publications_render_grid', '', $posts, $attributes, $args);
    $html = (is_string($theme_render) && trim($theme_render) !== '')
        ? $theme_render
        : iss_publications_render_grid_posts($posts);
    if ($html === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-publications-grid'])
        : 'class="wp-block-iss-publications-grid"';

    return '<div ' . $wrapper . '>' . $html . '</div>';
}

function iss_publications_render_browser_block($attributes = [], $content = '', $block = null) {
    $attributes = is_array($attributes) ? $attributes : [];
    $payload = iss_publications_get_browser_payload($attributes);
    $theme_render = apply_filters('iss_publications_render_browser', '', $payload, $attributes, $block);
    $html = (is_string($theme_render) && trim($theme_render) !== '')
        ? $theme_render
        : iss_publications_render_browser_fallback($payload);

    if ($html === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-publications-browser'])
        : 'class="wp-block-iss-publications-browser"';

    return '<div ' . $wrapper . '>' . $html . '</div>';
}

function iss_publications_render_order_panel_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $panel = iss_publications_render_order_panel($post_id);
    if ($panel === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-publication-order-panel'])
        : 'class="wp-block-iss-publication-order-panel"';

    return '<div ' . $wrapper . '>' . $panel . '</div>';
}

function iss_publications_render_meta_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $context = iss_publications_get_meta_panel_context($post_id);
    if ($context === []) {
        return '';
    }

    $classes = 'wp-block-iss-publication-meta';
    if (!empty($context['is_timeline'])) {
        $classes .= ' wp-block-iss-publication-meta--timeline';
    } elseif (!empty($context['is_longread'])) {
        $classes .= ' wp-block-iss-publication-meta--longread';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => $classes])
        : 'class="' . esc_attr($classes) . '"';

    $theme_render = apply_filters('iss_publications_render_meta_panel', '', $post_id, $context, $attributes);
    if (is_string($theme_render) && trim($theme_render) !== '') {
        return '<div ' . $wrapper . '>' . $theme_render . '</div>';
    }

    ob_start();
    echo '<div class="iss-publication-single__panel">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Bibliografie', 'iss-publications') . '</p>';
    echo '<ul class="iss-publication-meta">';
    foreach ((array) ($context['summary_meta'] ?? []) as $label => $value) {
        echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
    }
    echo '</ul>';
    echo '</div>';

    return '<div ' . $wrapper . '>' . (string) ob_get_clean() . '</div>';
}

function iss_publications_render_timeline_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_publications_parse_timeline_block_payload(is_array($attributes) ? $attributes : [], (string) $content, $block);
    if ($payload === []) {
        return (string) $content;
    }

    $post_id = (int) get_the_ID();
    $theme_render = apply_filters('iss_publications_render_timeline_content', null, $post_id, $payload, (string) $content);
    if (is_string($theme_render)) {
        return $theme_render;
    }

    return (string) $content;
}

function iss_publications_render_longread_block($attributes = [], $content = '', $block = null): string
{
    $post_id = 0;
    if ($block instanceof WP_Block && isset($block->context['postId'])) {
        $post_id = (int) $block->context['postId'];
    }
    if ($post_id <= 0) {
        $post_id = (int) get_the_ID();
    }
    if ($post_id <= 0) {
        $post_id = (int) get_queried_object_id();
    }

    $payload = iss_publications_parse_longread_block_payload($post_id, is_array($attributes) ? $attributes : [], (string) $content, $block);
    if ($payload === []) {
        return (string) $content;
    }

    $theme_render = apply_filters('iss_publications_render_longread_content', null, $post_id, $payload, (string) $content);
    if (is_string($theme_render)) {
        return $theme_render;
    }

    return iss_publications_transform_longread_content($post_id, (string) $content);
}

function iss_publications_render_photoalbum_block($attributes = [], $content = '', $block = null): string
{
    $post_id = 0;
    if ($block instanceof WP_Block && isset($block->context['postId'])) {
        $post_id = (int) $block->context['postId'];
    }
    if ($post_id <= 0) {
        $post_id = (int) get_the_ID();
    }
    if ($post_id <= 0) {
        $post_id = (int) get_queried_object_id();
    }

    $payload = iss_publications_parse_photoalbum_block_payload($post_id, is_array($attributes) ? $attributes : [], (string) $content, $block);
    if ($payload === []) {
        return (string) $content;
    }

    $theme_render = apply_filters('iss_publications_render_photoalbum_content', null, $post_id, $payload, (string) $content);
    return is_string($theme_render) ? $theme_render : (string) $content;
}

function iss_publications_render_longread_related_block($attributes = [], $content = ''): string
{
    $post_id = iss_publications_block_resolve_post_id(is_array($attributes) ? $attributes : []);
    if ($post_id <= 0 || (!iss_publications_is_chaptered_longread($post_id) && !iss_publications_is_photoalbum($post_id))) {
        return '';
    }
    if (function_exists('iss_publications_publication_rail_allows') && !iss_publications_publication_rail_allows($post_id, 'show_related')) {
        return '';
    }

    $theme_render = apply_filters('iss_publications_render_longread_related', null, $post_id, is_array($attributes) ? $attributes : []);
    return is_string($theme_render) ? $theme_render : '';
}

function iss_publications_render_essay_summary_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0 || !iss_publications_is_chaptered_longread($post_id)) {
        return '';
    }
    if (function_exists('iss_publications_publication_rail_allows') && !iss_publications_publication_rail_allows($post_id, 'show_summary')) {
        return '';
    }

    $payload = iss_publications_get_longread_payload($post_id);
    if ($payload === []) {
        return '';
    }

    $theme_render = apply_filters('iss_publications_render_longread_summary', null, $post_id, $payload, $attributes);
    if (is_string($theme_render)) {
        return $theme_render;
    }

    return iss_publications_render_longread_summary_html(is_array($payload['summary'] ?? null) ? $payload['summary'] : []);
}

function iss_publications_render_essay_nav_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }
    if (function_exists('iss_publications_publication_rail_allows') && !iss_publications_publication_rail_allows($post_id, 'show_nav')) {
        return '';
    }

    if (iss_publications_is_photoalbum($post_id)) {
        $payload = iss_publications_get_photoalbum_payload($post_id);
        if ($payload === []) {
            return '';
        }

        $theme_render = apply_filters('iss_publications_render_photoalbum_nav', null, $post_id, $payload, $attributes);
        return is_string($theme_render) ? $theme_render : '';
    }

    if (!iss_publications_is_chaptered_longread($post_id)) {
        return '';
    }

    $payload = iss_publications_get_longread_payload($post_id);
    if ($payload === []) {
        return '';
    }

    $theme_render = apply_filters('iss_publications_render_longread_nav', null, $post_id, $payload, $attributes);
    if (is_string($theme_render)) {
        return $theme_render;
    }

    return iss_publications_render_longread_nav_html(is_array($payload['nav_items'] ?? null) ? $payload['nav_items'] : []);
}

function iss_publications_render_essay_bridge_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0 || !iss_publications_is_chaptered_longread($post_id)) {
        return '';
    }

    $payload = iss_publications_get_longread_payload($post_id);
    if ($payload === []) {
        return '';
    }

    $theme_render = apply_filters('iss_publications_render_longread_bridge', null, $post_id, $payload, $attributes);
    if (is_string($theme_render)) {
        return $theme_render;
    }

    return (string) ($payload['bridge_html'] ?? '');
}

add_shortcode('iss_featured_publication', function ($atts = []) {
    $atts = shortcode_atts(['id' => 0], $atts);
    return iss_publications_render_featured_block(['postId' => (int) $atts['id']], '');
});

add_shortcode('iss_publications_grid', function ($atts = []) {
    $atts = shortcode_atts([
        'limit' => 6,
        'exclude_featured' => 'false',
    ], $atts);

    return iss_publications_render_grid_block([
        'limit' => (int) $atts['limit'],
        'excludeFeatured' => filter_var($atts['exclude_featured'], FILTER_VALIDATE_BOOLEAN),
    ], '');
});

add_shortcode('iss_publication_order_panel', function ($atts = []) {
    $atts = shortcode_atts(['id' => 0], $atts);
    return iss_publications_render_order_panel_block(['postId' => (int) $atts['id']], '');
});
