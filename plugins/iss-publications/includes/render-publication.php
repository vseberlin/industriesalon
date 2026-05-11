<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_get_price_cents($post_id) {
    return (int) iss_publications_get_meta($post_id, '_iss_publication_price_cents', 0);
}

function iss_publications_get_layout($post_id) {
    $layout = sanitize_key((string) iss_publications_get_meta($post_id, '_iss_publication_layout', 'standard'));
    return in_array($layout, ['standard', 'longread', 'timeline'], true) ? $layout : 'standard';
}

function iss_publications_is_longread($post_id) {
    return in_array(iss_publications_get_layout($post_id), ['longread', 'timeline'], true);
}

function iss_publications_is_timeline($post_id) {
    return iss_publications_get_layout($post_id) === 'timeline';
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
        iss_publications_is_timeline($post_id)
            ? __('Zeitleiste', 'iss-publications')
            : (iss_publications_is_longread($post_id) ? __('Longread', 'iss-publications') : ''),
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

function iss_publications_get_archive_tax_query() {
    $supported_taxonomies = ['publication_type', 'publication_topic'];
    $shared_topic_taxonomy = iss_publications_get_shared_topic_taxonomy();
    if (taxonomy_exists($shared_topic_taxonomy)) {
        $supported_taxonomies[] = $shared_topic_taxonomy;
    }

    if (!is_tax($supported_taxonomies)) {
        return [];
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return [];
    }

    return [[
        'taxonomy' => $term->taxonomy,
        'field'    => 'term_id',
        'terms'    => [$term->term_id],
    ]];
}

function iss_publications_get_featured_publication_id() {
    $args = [
        'post_type'      => ISS_PUBLICATIONS_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ],
        'meta_query'     => [[
            'key'     => '_iss_publication_featured',
            'value'   => '1',
            'compare' => '=',
        ]],
        'tax_query'      => iss_publications_get_archive_tax_query(),
        'fields'         => 'ids',
    ];

    $ids = get_posts($args);
    if (!empty($ids)) {
        return (int) $ids[0];
    }

    unset($args['meta_query']);
    $ids = get_posts($args);
    return !empty($ids) ? (int) $ids[0] : 0;
}

function iss_publications_render_archive_card($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $permalink = get_permalink($post_id);
    $kicker = iss_publications_get_card_kicker($post_id);
    $meta_items = iss_publications_get_card_meta_items($post_id);

    ob_start();
    echo '<article class="iss-card iss-card--flat iss-card--info iss-publication-card">';
    if (has_post_thumbnail($post_id)) {
        echo '<a class="iss-card__media iss-publication-card__cover" href="' . esc_url($permalink) . '">';
        echo get_the_post_thumbnail($post_id, 'large');
        echo '</a>';
    }

    echo '<div class="iss-card__body">';
    if ($kicker !== '') {
        echo '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
    }
    echo '<h2 class="iss-card__title"><a href="' . esc_url($permalink) . '">' . esc_html(get_the_title($post_id)) . '</a></h2>';

    $excerpt = get_the_excerpt($post_id);
    if ($excerpt !== '') {
        echo '<p class="iss-card__text">' . esc_html($excerpt) . '</p>';
    }

    if (!empty($meta_items)) {
        echo '<div class="iss-card__meta">';
        foreach ($meta_items as $item) {
            echo '<span>' . esc_html($item) . '</span>';
        }
        echo '</div>';
    }

    $card_label = iss_publications_sale_enabled($post_id)
        ? __('Details / bestellen', 'iss-publications')
        : __('Mehr lesen', 'iss-publications');

    echo '<div class="iss-card__footer"><a class="iss-card__link" href="' . esc_url($permalink) . '">' . esc_html($card_label) . '</a></div>';
    echo '</div>';
    echo '</article>';
    return (string) ob_get_clean();
}

function iss_publications_render_featured_publication($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $permalink = get_permalink($post_id);
    $excerpt = get_the_excerpt($post_id);

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

    $summary_meta = iss_publications_get_summary_meta($post_id);
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
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !iss_publications_sale_enabled($post_id)) {
        return '';
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

    ob_start();
    echo '<aside class="iss-publication-order-panel">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Bestellung', 'iss-publications') . '</p>';
    echo '<h2 class="iss-info-panel__title">' . esc_html__('Publikation bestellen', 'iss-publications') . '</h2>';
    echo '<p class="iss-publication-order-panel__price">' . esc_html(iss_publications_format_price(iss_publications_get_price_cents($post_id))) . '</p>';
    echo '<p class="iss-publication-order-panel__text">' . esc_html($description) . '</p>';

    if (is_string($button_html) && trim($button_html) !== '') {
        echo wp_kses_post($button_html);
    } else {
        echo '<p class="iss-publication-order-panel__note">' . esc_html__('Die Bestellfunktion wird im nächsten Schritt angebunden.', 'iss-publications') . '</p>';
    }

    echo '</aside>';
    return (string) ob_get_clean();
}

function iss_publications_get_source_ausstellung_id($post_id): int
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0;
    }

    $source_id = (int) iss_publications_get_meta($post_id, '_iss_publication_source_ausstellung_id', 0);
    if ($source_id > 0) {
        return $source_id;
    }

    if (!post_type_exists('ausstellung')) {
        return 0;
    }

    $posts = get_posts([
        'post_type' => 'ausstellung',
        'post_status' => ['publish', 'private', 'future', 'draft', 'pending'],
        'posts_per_page' => 1,
        'meta_key' => 'iss_companion_publication_id',
        'meta_value' => $post_id,
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);

    return !empty($posts) ? (int) $posts[0] : 0;
}

function iss_publications_render_corpus_stream_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $source_ausstellung_id = iss_publications_get_source_ausstellung_id($post_id);
    if ($source_ausstellung_id <= 0 || !function_exists('iss_content_model_get_ausstellung_corpus_chapters')) {
        return '';
    }

    $chapters = iss_content_model_get_ausstellung_corpus_chapters($source_ausstellung_id);
    if (!$chapters) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-publication-corpus'])
        : 'class="wp-block-iss-publication-corpus"';
    $shell_mode = sanitize_key((string) ($attributes['shellMode'] ?? 'section'));
    $use_section_shell = ($shell_mode !== 'body');

    ob_start();
    echo '<div ' . $wrapper . '>';
    echo $use_section_shell ? '<section class="iss-publication-corpus">' : '<div class="iss-publication-corpus">';
    if ($use_section_shell) {
        echo '<div class="iss-heading iss-publication-corpus__head">';
        echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Kapitelpfad', 'iss-publications') . '</p>';
        echo '<h2 class="iss-heading__title">' . esc_html__('Diese Publikation liest denselben Korpus linear.', 'iss-publications') . '</h2>';
        echo '<p class="iss-heading__text">' . esc_html__('Die Ausstellung bietet Überblick und thematische Einstiege. Hier laufen dieselben Kapitel als fortlaufender Lesepfad untereinander.', 'iss-publications') . '</p>';
        echo '</div>';
    }

    echo '<div class="iss-publication-corpus__topline">';
    echo '<p class="iss-publication-corpus__backlink"><a class="iss-action-link" href="' . esc_url(get_permalink($source_ausstellung_id)) . '">' . esc_html__('Zur Ausstellung', 'iss-publications') . '</a></p>';
    echo '<ol class="iss-publication-corpus__nav">';
    foreach ($chapters as $index => $chapter) {
        $anchor = 'publikation-kapitel-' . ($index + 1);
        echo '<li><a href="#' . esc_attr($anchor) . '">' . esc_html(get_the_title($chapter)) . '</a></li>';
    }
    echo '</ol>';
    echo '</div>';

    echo '<div class="iss-publication-corpus__stream">';
    foreach ($chapters as $index => $chapter) {
        $anchor = 'publikation-kapitel-' . ($index + 1);
        $content_html = apply_filters('the_content', $chapter->post_content);

        echo '<article id="' . esc_attr($anchor) . '" class="iss-publication-corpus__chapter">';
        echo '<p class="iss-kicker iss-kicker--compact">' . esc_html(sprintf(__('Kapitel %02d', 'iss-publications'), $index + 1)) . '</p>';
        echo '<h3 class="iss-publication-corpus__chapter-title">' . esc_html(get_the_title($chapter)) . '</h3>';
        echo '<div class="iss-publication-corpus__chapter-content">' . $content_html . '</div>';
        echo '<p class="iss-publication-corpus__chapter-link"><a class="iss-action-link" href="' . esc_url(get_permalink($chapter)) . '">' . esc_html__('Kapitel einzeln öffnen', 'iss-publications') . '</a></p>';
        echo '</article>';
    }
    echo '</div>';
    echo $use_section_shell ? '</section>' : '</div>';
    echo '</div>';

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
    if (!has_post_thumbnail($post_id)) {
        $classes[] = 'iss-publication-no-cover';
    }
    if (iss_publications_is_chaptered_longread($post_id)) {
        $classes[] = 'iss-publication-longread-chaptered';
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

        return iss_publications_transform_timeline_content((string) $content);
    }

    if (iss_publications_is_album($post_id)) {
        return iss_publications_transform_album_content($post_id, (string) $content);
    }

    if (!iss_publications_is_chaptered_longread($post_id)) {
        return $content;
    }

    return iss_publications_transform_longread_content($post_id, (string) $content);
}, 20);

function iss_publications_get_related_posts($post_id, $limit = 3) {
    $post_id = (int) $post_id;
    $types = wp_get_post_terms($post_id, 'publication_type', ['fields' => 'ids']);

    $args = [
        'post_type'      => ISS_PUBLICATIONS_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => max(1, (int) $limit),
        'post__not_in'   => [$post_id],
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ],
    ];

    if (!empty($types) && !is_wp_error($types)) {
        $args['tax_query'] = [[
            'taxonomy' => 'publication_type',
            'field'    => 'term_id',
            'terms'    => array_map('absint', $types),
        ]];
    }

    return get_posts($args);
}

function iss_publications_get_image_sequence_profile(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [
            'images' => 0,
            'paragraphs' => 0,
            'headings' => 0,
            'lists' => 0,
        ];
    }

    $content = (string) $post->post_content;
    return [
        'images' => substr_count($content, '<!-- wp:image'),
        'paragraphs' => substr_count($content, '<!-- wp:paragraph'),
        'headings' => substr_count($content, '<!-- wp:heading'),
        'lists' => substr_count($content, '<!-- wp:list'),
    ];
}

function iss_publications_is_album(int $post_id): bool
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || iss_publications_get_layout($post_id) !== 'longread') {
        return false;
    }

    $needles = [
        sanitize_title((string) get_the_title($post_id)),
        sanitize_title((string) iss_publications_get_meta($post_id, '_iss_publication_subtitle', '')),
        sanitize_title((string) iss_publications_get_meta($post_id, '_iss_publication_format', '')),
    ];

    foreach ($needles as $needle) {
        if ($needle === '') {
            continue;
        }

        if (str_contains($needle, 'fotoalbum') || str_contains($needle, 'album') || str_contains($needle, 'betriebsfotoalbum')) {
            return true;
        }
    }

    $profile = iss_publications_get_image_sequence_profile($post_id);
    return $profile['images'] >= 12
        && $profile['paragraphs'] <= 5
        && $profile['headings'] <= 2
        && $profile['lists'] === 0;
}

function iss_publications_get_collection_kind($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 'other';
    }

    if (iss_publications_is_album($post_id)) {
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

function iss_publications_timeline_get_epoch_definitions(): array
{
    return [
        [
            'slug' => 'industrialisierung',
            'start' => null,
            'end' => 1918,
            'range_label' => '1880-1918',
            'title' => __('Gruendung und Industrialisierung', 'iss-publications'),
            'note' => __('Werkgruendung, Elektrifizierung und fruehe Expansion.', 'iss-publications'),
            'accent' => 'var(--iss-red)',
        ],
        [
            'slug' => 'zwischenkrieg',
            'start' => 1919,
            'end' => 1945,
            'range_label' => '1919-1945',
            'title' => __('Zwischenkriegszeit und Krieg', 'iss-publications'),
            'note' => __('Konzernumbau, politische Brueche und Kriegswirtschaft.', 'iss-publications'),
            'accent' => 'var(--iss-brown)',
        ],
        [
            'slug' => 'neuordnung',
            'start' => 1946,
            'end' => 1960,
            'range_label' => '1946-1960',
            'title' => __('Neuordnung und Verstaatlichung', 'iss-publications'),
            'note' => __('Neubeginn, Umbau und neue Eigentumsordnung.', 'iss-publications'),
            'accent' => 'var(--iss-blue)',
        ],
        [
            'slug' => 'spaetphase',
            'start' => 1961,
            'end' => null,
            'range_label' => '1961-1989',
            'title' => __('Kombinat und Umbruch', 'iss-publications'),
            'note' => __('Ausbau, spaete DDR und die Zaesur von 1989.', 'iss-publications'),
            'accent' => 'var(--iss-green)',
        ],
    ];
}

function iss_publications_timeline_find_epoch(array $definitions, int $year): array
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

    return $definitions[count($definitions) - 1];
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
    if ($post_id <= 0 || iss_publications_get_layout($post_id) !== 'longread' || iss_publications_is_album($post_id)) {
        return false;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return false;
    }

    $content = (string) $post->post_content;
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

function iss_publications_transform_album_content(int $post_id, string $content): string
{
    if ($post_id <= 0 || trim($content) === '' || !class_exists('DOMDocument')) {
        return $content;
    }

    libxml_use_internal_errors(true);

    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div class="iss-publication-album-parser-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        libxml_clear_errors();
        return $content;
    }

    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-album-parser-root ")]')->item(0);
    if (!$root instanceof DOMElement) {
        libxml_clear_errors();
        return $content;
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
        return $content;
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

    $summary_html = '<div class="iss-publication-album__summary" aria-label="' . esc_attr__('Rahmendaten', 'iss-publications') . '">';
    foreach ($summary as $item) {
        $summary_html .= '<p class="iss-publication-album__summary-item"><strong>' . esc_html($item['value']) . '</strong><span>' . esc_html($item['label']) . '</span></p>';
    }
    $summary_html .= '</div>';

    $source_html = '';
    if (!empty($context_nodes)) {
        $source_html = '<div class="iss-publication-source-note iss-publication-album__source">' . implode('', $context_nodes) . '</div>';
    }

    $sheets_html = '<div class="iss-publication-album__grid">';
    foreach ($figure_nodes as $index => $figure) {
        $figure_html = trim($document->saveHTML($figure));
        $caption_text = trim((string) $xpath->evaluate('string(.//figcaption[1])', $figure));
        $sheet_label = iss_publications_album_get_sheet_label($caption_text, $index);
        $sheets_html .= '<article class="iss-publication-album__sheet">';
        $sheets_html .= '<p class="iss-kicker iss-kicker--compact iss-publication-album__sheet-label">' . esc_html($sheet_label) . '</p>';
        $sheets_html .= $figure_html;
        $sheets_html .= '</article>';
    }
    $sheets_html .= '</div>';

    $html = '<div class="iss-publication-album">';
    if (!empty($intro_nodes)) {
        $html .= '<div class="iss-publication-album__intro">' . implode('', $intro_nodes) . '</div>';
    }
    $html .= $summary_html;
    $html .= $source_html;
    $html .= '<div class="iss-publication-album__body">';
    $html .= '<div class="iss-heading iss-publication-album__head">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Albumblaetter', 'iss-publications') . '</p>';
    $html .= '<h2 class="iss-heading__title">' . esc_html__('Digitale Blaetter der Edition', 'iss-publications') . '</h2>';
    $html .= '<p class="iss-heading__text">' . esc_html__('Die Scans werden als fortlaufende Edition gezeigt. Jede Albumseite bleibt mit ihrer Beschriftung und Materialspur lesbar.', 'iss-publications') . '</p>';
    $html .= '</div>';
    $html .= $sheets_html;
    $html .= '</div>';
    $html .= '</div>';

    libxml_clear_errors();
    return $html;
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

            $lead_map_html = iss_publications_transform_essay_map_html((string) $body_html);
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

    $intro_html = '';
    if (!empty($intro_nodes)) {
        $intro_copy = '<div class="iss-publication-essay__intro">' . implode('', $intro_nodes) . '</div>';
        if ($lead_map_html !== '') {
            $intro_html = '<div class="iss-publication-essay__lead">' . $intro_copy . '<div class="iss-publication-essay__map">' . $lead_map_html . '</div></div>';
        } else {
            $intro_html = $intro_copy;
        }
    } elseif ($lead_map_html !== '') {
        $intro_html = '<div class="iss-publication-essay__lead"><div class="iss-publication-essay__map">' . $lead_map_html . '</div></div>';
    }

    libxml_clear_errors();

    return [
        'source_note_html' => $source_note_html,
        'intro_html' => $intro_html,
        'summary' => $summary,
        'nav_items' => $nav_items,
        'bridge_html' => iss_publications_get_essay_bridge_html($post_id),
        'sections' => $sections,
    ];
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

    $html = '<nav class="iss-reading-nav iss-publication-essay__nav" aria-label="' . esc_attr__('Kapitelnavigation', 'iss-publications') . '"><div class="iss-reading-nav__inner iss-publication-essay__nav-inner">';
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
        $chapters_html .= '<div class="iss-publication-chapter__body">' . implode('', $section['body']) . '</div>';
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

function iss_publications_transform_timeline_content(string $content): string
{
    if (trim($content) === '' || !class_exists('DOMDocument')) {
        return $content;
    }

    libxml_use_internal_errors(true);

    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div class="iss-publication-timeline-parser-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        libxml_clear_errors();
        return $content;
    }

    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-timeline-parser-root ")]')->item(0);
    $chronicle = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle ")]', $root)->item(0);
    if (!$root instanceof DOMElement || !$chronicle instanceof DOMElement) {
        libxml_clear_errors();
        return $content;
    }

    $source_note_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-source-note ")]', $root)->item(0);
    $source_note_html = $source_note_node instanceof DOMElement ? trim($document->saveHTML($source_note_node)) : '';

    $item_nodes = $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__item ")]', $chronicle);
    if (!$item_nodes || $item_nodes->length === 0) {
        libxml_clear_errors();
        return $content;
    }

    $definitions = iss_publications_timeline_get_epoch_definitions();
    $epochs = [];
    $total_items = 0;
    $first_year = null;
    $last_year = null;

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
        $epoch = iss_publications_timeline_find_epoch($definitions, $year);
        $slug = (string) $epoch['slug'];

        if (!isset($epochs[$slug])) {
            $epochs[$slug] = [
                'definition' => $epoch,
                'items' => [],
            ];
        }

        $epochs[$slug]['items'][] = [
            'year' => $year,
            'title' => trim((string) $xpath->evaluate('string(.//*[contains(concat(" ", normalize-space(@class), " "), " iss-publication-chronicle__title ")][1])', $item)),
            'variant' => iss_publications_timeline_detect_moment_variant($xpath, $item),
            'card_html' => trim(iss_publications_timeline_get_inner_html($card_node)),
        ];

        $total_items++;
        $first_year = $first_year === null ? $year : min($first_year, $year);
        $last_year = $last_year === null ? $year : max($last_year, $year);
    }

    if ($total_items === 0 || empty($epochs)) {
        libxml_clear_errors();
        return $content;
    }

    $epoch_count = count($epochs);
    $summary = [
        [
            'value' => $first_year !== null ? (string) $first_year : '—',
            'label' => __('Auftakt', 'iss-publications'),
        ],
        [
            'value' => $last_year !== null ? (string) $last_year : '—',
            'label' => __('Letzte Zaesur', 'iss-publications'),
        ],
        [
            'value' => (string) $total_items,
            'label' => __('Stationen', 'iss-publications'),
        ],
        [
            'value' => (string) $epoch_count,
            'label' => __('Epochen', 'iss-publications'),
        ],
    ];

    $nav_html = '';
    $epochs_html = '';
    $index = 0;
    foreach ($epochs as $slug => $epoch_data) {
        $index++;
        $definition = $epoch_data['definition'];
        $anchor = 'iss-publication-epoch-' . sanitize_html_class($slug);

        $nav_html .= '<a href="#' . esc_attr($anchor) . '"><span>' . esc_html((string) $definition['range_label']) . '</span>' . esc_html((string) $definition['title']) . '</a>';

        $moments_html = '';
        foreach ($epoch_data['items'] as $item) {
            $variant_class = $item['variant'] !== '' ? ' iss-publication-moment--' . sanitize_html_class($item['variant']) : '';
            $moments_html .= '<article class="iss-publication-moment' . $variant_class . '">';
            $moments_html .= '<p class="iss-publication-moment__year">' . esc_html((string) $item['year']) . '</p>';
            $moments_html .= '<div class="iss-publication-moment__card">' . $item['card_html'] . '</div>';
            $moments_html .= '</article>';
        }

        $epochs_html .= '<section id="' . esc_attr($anchor) . '" class="iss-publication-epoch" style="--iss-publication-epoch-accent:' . esc_attr((string) $definition['accent']) . ';">';
        $epochs_html .= '<div class="iss-publication-epoch__aside">';
        $epochs_html .= '<p class="iss-publication-epoch__range">' . esc_html((string) $definition['range_label']) . '</p>';
        $epochs_html .= '<h2 class="iss-publication-epoch__title">' . esc_html((string) $definition['title']) . '</h2>';
        $epochs_html .= '<p class="iss-publication-epoch__note">' . esc_html((string) $definition['note']) . '</p>';
        $epochs_html .= '</div>';
        $epochs_html .= '<div class="iss-publication-epoch__body">' . $moments_html . '</div>';
        $epochs_html .= '</section>';
    }

    $summary_html = '<div class="iss-publication-timeline__summary" aria-label="' . esc_attr__('Rahmendaten', 'iss-publications') . '">';
    foreach ($summary as $item) {
        $summary_html .= '<p class="iss-publication-timeline__summary-item"><strong>' . esc_html($item['value']) . '</strong><span>' . esc_html($item['label']) . '</span></p>';
    }
    $summary_html .= '</div>';

    $timeline_html = '<div class="iss-publication-timeline">';
    if ($source_note_html !== '') {
        $timeline_html .= $source_note_html;
    }
    $timeline_html .= $summary_html;
    $timeline_html .= '<nav class="iss-publication-timeline__nav" aria-label="' . esc_attr__('Kapitelnavigation', 'iss-publications') . '"><div class="iss-publication-timeline__nav-inner">' . $nav_html . '</div></nav>';
    $timeline_html .= '<div class="iss-publication-timeline__epochs">' . $epochs_html . '</div>';
    $timeline_html .= '</div>';

    libxml_clear_errors();
    return $timeline_html;
}

function iss_publications_get_archive_posts($args = []) {
    $defaults = [
        'post_type'      => ISS_PUBLICATIONS_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ],
        'tax_query'      => iss_publications_get_archive_tax_query(),
    ];

    return get_posts(wp_parse_args($args, $defaults));
}

function iss_publications_append_layout_filter(array $args, string $layout): array
{
    $layout = sanitize_key($layout);
    if (!in_array($layout, ['standard', 'longread', 'timeline'], true)) {
        return $args;
    }

    $meta_query = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];

    if ($layout === 'standard') {
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key' => '_iss_publication_layout',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_iss_publication_layout',
                'value' => 'standard',
                'compare' => '=',
            ],
        ];
    } else {
        $meta_query[] = [
            'key' => '_iss_publication_layout',
            'value' => $layout,
            'compare' => '=',
        ];
    }

    $args['meta_query'] = $meta_query;

    return $args;
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
    echo '<div class="iss-card-grid iss-publications-grid">';
    foreach ($posts as $post) {
        echo iss_publications_render_archive_card($post->ID);
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
        $args = iss_publications_append_layout_filter($args, $layout);
    }

    $html = iss_publications_render_grid_posts(iss_publications_get_archive_posts($args));
    if ($html === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-publications-grid'])
        : 'class="wp-block-iss-publications-grid"';

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

    $summary_meta = iss_publications_get_summary_meta($post_id);
    if (empty($summary_meta)) {
        return '';
    }

    ob_start();
    echo '<div class="iss-publication-single__panel">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Bibliografie', 'iss-publications') . '</p>';
    echo '<ul class="iss-publication-meta">';
    foreach ($summary_meta as $label => $value) {
        echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
    }
    echo '</ul>';
    echo '</div>';

    $classes = 'wp-block-iss-publication-meta';
    if (iss_publications_is_timeline($post_id)) {
        $classes .= ' wp-block-iss-publication-meta--timeline';
    } elseif (iss_publications_is_longread($post_id)) {
        $classes .= ' wp-block-iss-publication-meta--longread';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => $classes])
        : 'class="' . esc_attr($classes) . '"';

    return '<div ' . $wrapper . '>' . (string) ob_get_clean() . '</div>';
}

function iss_publications_render_essay_summary_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0 || !iss_publications_is_chaptered_longread($post_id)) {
        return '';
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return '';
    }

    $payload = iss_publications_parse_longread_payload($post_id, (string) $post->post_content);
    if ($payload === []) {
        return '';
    }

    return iss_publications_render_longread_summary_html(is_array($payload['summary'] ?? null) ? $payload['summary'] : []);
}

function iss_publications_render_essay_nav_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0 || !iss_publications_is_chaptered_longread($post_id)) {
        return '';
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return '';
    }

    $payload = iss_publications_parse_longread_payload($post_id, (string) $post->post_content);
    if ($payload === []) {
        return '';
    }

    return iss_publications_render_longread_nav_html(is_array($payload['nav_items'] ?? null) ? $payload['nav_items'] : []);
}

function iss_publications_render_essay_bridge_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0 || !iss_publications_is_chaptered_longread($post_id)) {
        return '';
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return '';
    }

    $payload = iss_publications_parse_longread_payload($post_id, (string) $post->post_content);
    if ($payload === []) {
        return '';
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
