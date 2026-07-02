<?php

if (!defined('ABSPATH')) {
    exit;
}

const ISS_CONTENT_FALLBACK_MODE_OPTION = 'iss_fallback_mode_enabled';
const ISS_CONTENT_FALLBACK_STATE_OPTION = 'iss_fallback_mode_state';
const ISS_CONTENT_FALLBACK_LAST_RUN_OPTION = 'iss_fallback_last_projection_run';
const ISS_CONTENT_FALLBACK_CRON_HOOK = 'iss_content_fallback_projection_sweep';
const ISS_CONTENT_FALLBACK_PROJECTOR_VERSION = '2026-07-02-v1';

function iss_content_fallback_category_terms(): array
{
    return [
        'veranstaltungen' => ['label' => __('Veranstaltungen', 'iss-content-model'), 'slug' => 'iss-veranstaltungen'],
        'fuehrungen' => ['label' => __('Führungen', 'iss-content-model'), 'slug' => 'iss-fuehrungen'],
        'ausstellungen' => ['label' => __('Ausstellungen', 'iss-content-model'), 'slug' => 'iss-ausstellungen'],
        'projekte' => ['label' => __('Projekte', 'iss-content-model'), 'slug' => 'iss-projekte'],
        'publikationen' => ['label' => __('Publikationen', 'iss-content-model'), 'slug' => 'iss-publikationen'],
        'rueckblicke' => ['label' => __('Rückblicke', 'iss-content-model'), 'slug' => 'iss-rueckblicke'],
        'aktuelles' => ['label' => __('Aktuelles', 'iss-content-model'), 'slug' => 'iss-aktuelles'],
        'seiten' => ['label' => __('Seiten', 'iss-content-model'), 'slug' => 'iss-seiten'],
    ];
}

function iss_content_fallback_category_slugs(): array
{
    return array_values(array_map(static function (array $term): string {
        return (string) $term['slug'];
    }, iss_content_fallback_category_terms()));
}

function iss_content_fallback_category_slug_for_source(string $source_type): string
{
    $map = [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE => 'iss-veranstaltungen',
        'fuehrung' => 'iss-fuehrungen',
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE => 'iss-ausstellungen',
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE => 'iss-projekte',
        ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE => 'iss-rueckblicke',
        'publication' => 'iss-publikationen',
        'page' => 'iss-seiten',
        'post' => 'iss-aktuelles',
    ];

    return (string) ($map[sanitize_key($source_type)] ?? '');
}

function iss_content_fallback_seed_categories(): void
{
    foreach (iss_content_fallback_category_terms() as $term) {
        $slug = sanitize_title((string) ($term['slug'] ?? ''));
        $label = trim((string) ($term['label'] ?? ''));
        if ($slug === '' || $label === '') {
            continue;
        }

        if (term_exists($slug, 'category')) {
            continue;
        }

        wp_insert_term($label, 'category', ['slug' => $slug]);
    }
}
add_action('init', 'iss_content_fallback_seed_categories', 35);

function iss_content_fallback_register_category_for_pages(): void
{
    register_taxonomy_for_object_type('category', 'page');
}
add_action('init', 'iss_content_fallback_register_category_for_pages', 36);

function iss_content_fallback_mode_enabled(): bool
{
    return get_option(ISS_CONTENT_FALLBACK_MODE_OPTION) === '1';
}

function iss_content_fallback_origin(int $post_id): string
{
    return sanitize_key((string) get_post_meta($post_id, '_iss_fallback_origin', true));
}

function iss_content_fallback_is_generated(int $post_id): bool
{
    return iss_content_fallback_origin($post_id) === 'generated';
}

function iss_content_fallback_is_native(int $post_id): bool
{
    return iss_content_fallback_origin($post_id) === 'fallback-native';
}

function iss_content_fallback_post_has_fallback_category(int $post_id): bool
{
    $terms = wp_get_post_terms($post_id, 'category', ['fields' => 'slugs']);
    if (is_wp_error($terms) || !$terms) {
        return false;
    }

    return (bool) array_intersect(iss_content_fallback_category_slugs(), array_map('sanitize_title', $terms));
}

function iss_content_fallback_mark_native_on_save(int $post_id, WP_Post $post): void
{
    if (!in_array($post->post_type, ['post', 'page'], true) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if (iss_content_fallback_origin($post_id) !== '' || get_post_meta($post_id, '_iss_fallback_source_id', true) !== '') {
        return;
    }
    if (!iss_content_fallback_post_has_fallback_category($post_id)) {
        return;
    }

    update_post_meta($post_id, '_iss_fallback_origin', 'fallback-native');
}
add_action('save_post', 'iss_content_fallback_mark_native_on_save', 20, 2);

function iss_content_fallback_register_caps(array $capabilities): array
{
    $capabilities['iss_run_fallback_projection'] = [
        'label' => 'Run fallback projection',
        'owner' => 'iss-content',
    ];
    $capabilities['iss_manage_fallback_mode'] = [
        'label' => 'Manage fallback mode',
        'owner' => 'iss-content',
    ];

    return $capabilities;
}
add_filter('iss_register_caps', 'iss_content_fallback_register_caps');

function iss_content_fallback_sanitize_html(string $html): string
{
    $allowed = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'h2' => [],
        'h3' => [],
        'blockquote' => [],
        'figure' => [],
        'figcaption' => [],
        'img' => [
            'src' => true,
            'alt' => true,
            'width' => true,
            'height' => true,
            'loading' => true,
            'decoding' => true,
        ],
        'a' => [
            'href' => true,
        ],
    ];

    return trim(wp_kses($html, $allowed));
}

function iss_content_fallback_render_core_blocks(array $blocks): string
{
    $html = '';

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $name = (string) ($block['blockName'] ?? '');
        if ($name !== '' && (str_starts_with($name, 'iss/') || str_starts_with($name, 'industriesalon/'))) {
            continue;
        }

        if ($name === '' || str_starts_with($name, 'core/')) {
            $rendered = $name === '' ? (string) ($block['innerHTML'] ?? '') : render_block($block);
            $html .= $rendered;
            continue;
        }

        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $html .= iss_content_fallback_render_core_blocks($block['innerBlocks']);
        }
    }

    return iss_content_fallback_sanitize_html($html);
}

function iss_content_fallback_plain_html_from_content(string $content): string
{
    $content = trim($content);
    if ($content === '') {
        return '';
    }

    $blocks = parse_blocks($content);
    if ($blocks) {
        return iss_content_fallback_render_core_blocks($blocks);
    }

    return iss_content_fallback_sanitize_html(wpautop($content));
}

function iss_content_fallback_text_value($value): string
{
    if (is_array($value)) {
        $value = implode(' ', array_filter(array_map('iss_content_fallback_text_value', $value)));
    }

    return trim(wp_strip_all_tags((string) $value));
}

function iss_content_fallback_section_body_html(array $section): string
{
    foreach (['body_html', 'body', 'text', 'description', 'intro'] as $key) {
        if (!isset($section[$key])) {
            continue;
        }
        $value = $section[$key];
        if (is_string($value) && trim($value) !== '') {
            return iss_content_fallback_sanitize_html(wpautop($value));
        }
    }

    return '';
}

function iss_content_fallback_media_ref_attachment_id(array $reference): int
{
    $source = sanitize_key((string) ($reference['source'] ?? ''));
    $id = (int) ($reference['id'] ?? 0);
    if ($source === 'wp-media' && $id > 0 && get_post_type($id) === 'attachment') {
        return $id;
    }

    return 0;
}

function iss_content_fallback_resolved_media_attachment_ids(array $section): array
{
    $ids = [];
    foreach (['media_refs', 'media_refs_resolved'] as $field) {
        foreach ((array) ($section[$field] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $reference = is_array($entry['reference'] ?? null) ? $entry['reference'] : $entry;
            $id = iss_content_fallback_media_ref_attachment_id($reference);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

function iss_content_fallback_render_attachment_figure(int $attachment_id): string
{
    if ($attachment_id <= 0) {
        return '';
    }

    $src = wp_get_attachment_image_src($attachment_id, 'large');
    if (!is_array($src) || empty($src[0])) {
        return '';
    }

    $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
    $caption = trim((string) wp_get_attachment_caption($attachment_id));
    $html = '<figure><img src="' . esc_url($src[0]) . '" alt="' . esc_attr($alt) . '"';
    if (!empty($src[1])) {
        $html .= ' width="' . esc_attr((string) (int) $src[1]) . '"';
    }
    if (!empty($src[2])) {
        $html .= ' height="' . esc_attr((string) (int) $src[2]) . '"';
    }
    $html .= ' loading="lazy" decoding="async">';
    if ($caption !== '') {
        $html .= '<figcaption>' . esc_html($caption) . '</figcaption>';
    }
    $html .= '</figure>';

    return $html;
}

function iss_content_fallback_render_links(array $links): string
{
    $items = [];
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }
        $url = esc_url_raw((string) ($link['url'] ?? $link['href'] ?? ''));
        $label = iss_content_fallback_text_value($link['label'] ?? $link['title'] ?? $url);
        if ($url === '' || $label === '') {
            continue;
        }
        $items[] = '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
    }

    return $items ? '<ul>' . implode('', $items) . '</ul>' : '';
}

function iss_content_fallback_render_facts(array $facts): string
{
    $items = [];
    foreach ($facts as $fact) {
        if (!is_array($fact)) {
            $value = iss_content_fallback_text_value($fact);
            if ($value !== '') {
                $items[] = '<li>' . esc_html($value) . '</li>';
            }
            continue;
        }

        $label = iss_content_fallback_text_value($fact['label'] ?? $fact['key'] ?? '');
        $value = iss_content_fallback_text_value($fact['value'] ?? $fact['text'] ?? '');
        if ($label !== '' && $value !== '') {
            $items[] = '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
        } elseif ($value !== '') {
            $items[] = '<li>' . esc_html($value) . '</li>';
        }
    }

    return $items ? '<ul>' . implode('', $items) . '</ul>' : '';
}

function iss_content_fallback_serialize_section(array $section, array $format = []): string
{
    $type = sanitize_key((string) ($section['type'] ?? ''));
    if ($type === 'dynamic_slot') {
        return '';
    }

    $parts = [];
    $title = iss_content_fallback_text_value($section['title'] ?? $section['heading'] ?? '');
    if ($title !== '') {
        $parts[] = '<h2>' . esc_html($title) . '</h2>';
    }

    $quote = iss_content_fallback_text_value($section['quote'] ?? '');
    if ($quote !== '') {
        $attribution = iss_content_fallback_text_value($section['attribution'] ?? '');
        $parts[] = '<blockquote><p>' . esc_html($quote) . '</p>' . ($attribution !== '' ? '<p>' . esc_html($attribution) . '</p>' : '') . '</blockquote>';
    }

    $body = iss_content_fallback_section_body_html($section);
    if ($body !== '') {
        $parts[] = $body;
    }

    if (!empty($section['facts']) && is_array($section['facts'])) {
        $facts = iss_content_fallback_render_facts($section['facts']);
        if ($facts !== '') {
            $parts[] = $facts;
        }
    }

    foreach (array_slice(iss_content_fallback_resolved_media_attachment_ids($section), 0, 3) as $attachment_id) {
        $figure = iss_content_fallback_render_attachment_figure((int) $attachment_id);
        if ($figure !== '') {
            $parts[] = $figure;
        }
    }

    if (!empty($section['links']) && is_array($section['links'])) {
        $links = iss_content_fallback_render_links($section['links']);
        if ($links !== '') {
            $parts[] = $links;
        }
    }

    return iss_content_fallback_sanitize_html(implode("\n\n", $parts));
}

function iss_content_fallback_serialize_editorial_read_model(int $post_id, string $format_slug): string
{
    if (!function_exists('iss_editorial_get_read_model') || !function_exists('iss_editorial_get_format')) {
        return '';
    }
    if (!iss_editorial_document_is_enabled($post_id, $format_slug)) {
        return '';
    }

    $model = iss_editorial_get_read_model($post_id, $format_slug, false);
    $format = iss_editorial_get_format($format_slug);
    $sections = is_array($model['sections'] ?? null) ? $model['sections'] : [];
    $parts = [];
    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }
        $html = iss_content_fallback_serialize_section($section, $format);
        if ($html !== '') {
            $parts[] = $html;
        }
    }

    return iss_content_fallback_sanitize_html(implode("\n\n", $parts));
}

function iss_content_fallback_serialize_veranstaltung_content(int $post_id): string
{
    if (!function_exists('iss_content_model_veranstaltung_content_document')) {
        return '';
    }

    $document = iss_content_model_veranstaltung_content_document($post_id);
    $parts = [];
    foreach ((array) ($document['sections'] ?? []) as $section) {
        if (is_array($section)) {
            $html = iss_content_fallback_serialize_section($section);
            if ($html !== '') {
                $parts[] = $html;
            }
        }
    }

    return iss_content_fallback_sanitize_html(implode("\n\n", $parts));
}

function iss_content_fallback_build_plain_facts(array $facts): string
{
    return iss_content_fallback_render_facts($facts);
}

function iss_content_fallback_compose_body(WP_Post $post, string $format_slug = '', array $facts = []): string
{
    $parts = [];
    $content = iss_content_fallback_plain_html_from_content((string) $post->post_content);
    if ($content !== '') {
        $parts[] = $content;
    }

    if ($format_slug !== '') {
        $serialized = iss_content_fallback_serialize_editorial_read_model((int) $post->ID, $format_slug);
        if ($serialized !== '') {
            $parts[] = $serialized;
        }
    }

    if ($post->post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        $veranstaltung = iss_content_fallback_serialize_veranstaltung_content((int) $post->ID);
        if ($veranstaltung !== '') {
            $parts[] = $veranstaltung;
        }
    }

    if ($facts) {
        $facts_html = iss_content_fallback_build_plain_facts($facts);
        if ($facts_html !== '') {
            $parts[] = '<h2>' . esc_html__('Fakten', 'iss-content-model') . '</h2>' . $facts_html;
        }
    }

    return iss_content_fallback_sanitize_html(implode("\n\n", array_values(array_filter($parts))));
}

function iss_content_fallback_projection_hash(array $projection): string
{
    $keys = [
        'source_type',
        'source_id',
        'target_post_type',
        'title',
        'slug',
        'excerpt',
        'body',
        'public_status',
        'publication_date',
        'featured_image_id',
        'category_slugs',
        'canonical_url',
        'projector_version',
    ];
    $payload = [];
    foreach ($keys as $key) {
        $payload[$key] = $projection[$key] ?? null;
    }

    return md5((string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function iss_content_fallback_normalize_projection(array $projection): array
{
    $source_type = sanitize_key((string) ($projection['source_type'] ?? ''));
    $source_id = absint($projection['source_id'] ?? 0);
    $target_post_type = sanitize_key((string) ($projection['target_post_type'] ?? 'post'));
    if (!in_array($target_post_type, ['post', 'page'], true)) {
        $target_post_type = 'post';
    }

    $category_slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) ($projection['category_slugs'] ?? [])))));
    $public_status = sanitize_key((string) ($projection['public_status'] ?? 'publish'));
    if (!in_array($public_status, ['publish', 'private'], true)) {
        $public_status = 'publish';
    }

    $normalized = [
        'source_type' => $source_type,
        'source_id' => $source_id,
        'target_post_type' => $target_post_type,
        'title' => sanitize_text_field((string) ($projection['title'] ?? '')),
        'slug' => sanitize_title((string) ($projection['slug'] ?? '')),
        'excerpt' => sanitize_textarea_field((string) ($projection['excerpt'] ?? '')),
        'body' => iss_content_fallback_sanitize_html((string) ($projection['body'] ?? '')),
        'public_status' => $public_status,
        'publication_date' => trim((string) ($projection['publication_date'] ?? current_time('mysql'))),
        'featured_image_id' => absint($projection['featured_image_id'] ?? 0),
        'category_slugs' => $category_slugs,
        'canonical_url' => esc_url_raw((string) ($projection['canonical_url'] ?? '')),
        'projector_version' => sanitize_key((string) ($projection['projector_version'] ?? ISS_CONTENT_FALLBACK_PROJECTOR_VERSION)),
    ];

    if ($normalized['slug'] === '') {
        $normalized['slug'] = sanitize_title($normalized['source_type'] . '-' . (string) $normalized['source_id']);
    }
    if ($normalized['title'] === '') {
        $normalized['title'] = $normalized['slug'];
    }
    $normalized['source_hash'] = (string) ($projection['source_hash'] ?? iss_content_fallback_projection_hash($normalized));

    return $normalized;
}

function iss_content_fallback_get_projectors(): array
{
    $projectors = [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE => [
            'label' => __('Veranstaltungen', 'iss-content-model'),
            'callback' => 'iss_content_fallback_projector_veranstaltungen',
        ],
        'fuehrung' => [
            'label' => __('Führungen', 'iss-content-model'),
            'callback' => 'iss_content_fallback_projector_fuehrungen',
        ],
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE => [
            'label' => __('Ausstellungen', 'iss-content-model'),
            'callback' => 'iss_content_fallback_projector_ausstellungen',
        ],
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE => [
            'label' => __('Projekte', 'iss-content-model'),
            'callback' => 'iss_content_fallback_projector_projekte',
        ],
        ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE => [
            'label' => __('Rückblicke', 'iss-content-model'),
            'callback' => 'iss_content_fallback_projector_rueckblicke',
        ],
    ];

    $projectors = apply_filters('iss_fallback_projectors', $projectors);
    return is_array($projectors) ? $projectors : [];
}

function iss_content_fallback_source_posts(string $post_type): array
{
    if (!post_type_exists($post_type)) {
        return [];
    }

    return get_posts([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
        'suppress_filters' => true,
    ]);
}

function iss_content_fallback_default_projection_for_post(WP_Post $post, string $category_slug, string $format_slug = '', array $facts = []): array
{
    $source_type = (string) $post->post_type;
    $slug = sanitize_title('iss-' . $source_type . '-' . ($post->post_name !== '' ? $post->post_name : (string) $post->ID));

    return iss_content_fallback_normalize_projection([
        'source_type' => $source_type,
        'source_id' => (int) $post->ID,
        'target_post_type' => 'post',
        'title' => (string) $post->post_title,
        'slug' => $slug,
        'excerpt' => has_excerpt($post) ? (string) get_the_excerpt($post) : '',
        'body' => iss_content_fallback_compose_body($post, $format_slug, $facts),
        'public_status' => 'publish',
        'publication_date' => (string) $post->post_date,
        'featured_image_id' => get_post_thumbnail_id($post) ?: 0,
        'category_slugs' => [$category_slug],
        'canonical_url' => get_permalink($post),
        'projector_version' => ISS_CONTENT_FALLBACK_PROJECTOR_VERSION,
    ]);
}

function iss_content_fallback_projector_veranstaltungen(): array
{
    $items = [];
    foreach (iss_content_fallback_source_posts(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) as $post) {
        if ($post instanceof WP_Post) {
            $items[] = iss_content_fallback_default_projection_for_post($post, 'iss-veranstaltungen');
        }
    }
    return $items;
}

function iss_content_fallback_projector_fuehrungen(): array
{
    $items = [];
    foreach (iss_content_fallback_source_posts('fuehrung') as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $facts = [];
        foreach ([
            'duration' => __('Dauer', 'iss-content-model'),
            'meeting_point' => __('Treffpunkt', 'iss-content-model'),
            'target_group' => __('Zielgruppe', 'iss-content-model'),
            'price_note' => __('Preis', 'iss-content-model'),
        ] as $meta_key => $label) {
            $value = trim((string) get_post_meta((int) $post->ID, $meta_key, true));
            if ($value !== '') {
                $facts[] = ['label' => $label, 'value' => $value];
            }
        }
        $inquiry_url = esc_url_raw((string) get_post_meta((int) $post->ID, 'inquiry_url', true));
        if ($inquiry_url !== '') {
            $facts[] = ['label' => __('Buchung', 'iss-content-model'), 'value' => $inquiry_url];
        }
        $items[] = iss_content_fallback_default_projection_for_post($post, 'iss-fuehrungen', 'fuehrung', $facts);
    }
    return $items;
}

function iss_content_fallback_projector_ausstellungen(): array
{
    $items = [];
    foreach (iss_content_fallback_source_posts(ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) as $post) {
        if ($post instanceof WP_Post) {
            $items[] = iss_content_fallback_default_projection_for_post($post, 'iss-ausstellungen', 'ausstellung');
        }
    }
    return $items;
}

function iss_content_fallback_projector_projekte(): array
{
    $items = [];
    foreach (iss_content_fallback_source_posts(ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) as $post) {
        if ($post instanceof WP_Post) {
            $items[] = iss_content_fallback_default_projection_for_post($post, 'iss-projekte', 'projekt');
        }
    }
    return $items;
}

function iss_content_fallback_projector_rueckblicke(): array
{
    $items = [];
    foreach (iss_content_fallback_source_posts(ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE) as $post) {
        if ($post instanceof WP_Post) {
            $items[] = iss_content_fallback_default_projection_for_post($post, 'iss-rueckblicke', 'rueckblick');
        }
    }
    return $items;
}

function iss_content_fallback_find_generated_object(string $source_type, int $source_id): int
{
    $posts = get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => true,
        'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Fallback source lookup is low-volume and postmeta is the durable mapping store.
            [
                'key' => '_iss_fallback_origin',
                'value' => 'generated',
            ],
            [
                'key' => '_iss_fallback_source_type',
                'value' => sanitize_key($source_type),
            ],
            [
                'key' => '_iss_fallback_source_id',
                'value' => (string) $source_id,
            ],
        ],
    ]);

    return $posts ? (int) $posts[0] : 0;
}

function iss_content_fallback_apply_projection(array $projection, bool $dry_run = false): array
{
    $projection = iss_content_fallback_normalize_projection($projection);
    $existing_id = iss_content_fallback_find_generated_object($projection['source_type'], (int) $projection['source_id']);
    $target_status = iss_content_fallback_mode_enabled() ? $projection['public_status'] : 'draft';

    $post_data = [
        'post_type' => $projection['target_post_type'],
        'post_title' => $projection['title'],
        'post_name' => $projection['slug'],
        'post_content' => $projection['body'],
        'post_excerpt' => $projection['excerpt'],
        'post_status' => $target_status,
        'post_date' => $projection['publication_date'],
        'post_date_gmt' => get_gmt_from_date($projection['publication_date']),
    ];

    if ($existing_id <= 0) {
        if ($dry_run) {
            return ['action' => 'create', 'post_id' => 0];
        }
        $post_id = wp_insert_post(wp_slash($post_data), true);
        if (is_wp_error($post_id)) {
            return ['action' => 'error', 'post_id' => 0, 'error' => $post_id->get_error_message()];
        }
    } else {
        $post_id = $existing_id;
        $stored_hash = (string) get_post_meta($post_id, '_iss_fallback_source_hash', true);
        $stored_version = (string) get_post_meta($post_id, '_iss_fallback_projector_version', true);
        $current = get_post($post_id);
        $needs_update = $stored_hash !== $projection['source_hash']
            || $stored_version !== $projection['projector_version']
            || !$current instanceof WP_Post
            || (string) $current->post_status !== $target_status;
        if (!$needs_update) {
            return ['action' => 'unchanged', 'post_id' => $post_id];
        }
        if ($dry_run) {
            return ['action' => 'update', 'post_id' => $post_id];
        }
        $post_data['ID'] = $post_id;
        $updated = wp_update_post(wp_slash($post_data), true);
        if (is_wp_error($updated)) {
            return ['action' => 'error', 'post_id' => $post_id, 'error' => $updated->get_error_message()];
        }
    }

    update_post_meta($post_id, '_iss_fallback_origin', 'generated');
    update_post_meta($post_id, '_iss_fallback_source_type', $projection['source_type']);
    update_post_meta($post_id, '_iss_fallback_source_id', (string) $projection['source_id']);
    update_post_meta($post_id, '_iss_fallback_source_hash', $projection['source_hash']);
    update_post_meta($post_id, '_iss_fallback_last_projected_at', current_time('mysql'));
    update_post_meta($post_id, '_iss_fallback_canonical_url', $projection['canonical_url']);
    update_post_meta($post_id, '_iss_fallback_projector_version', $projection['projector_version']);
    update_post_meta($post_id, '_iss_fallback_public_status', $projection['public_status']);
    delete_post_meta($post_id, '_iss_fallback_stale');

    if ($projection['featured_image_id'] > 0) {
        set_post_thumbnail($post_id, $projection['featured_image_id']);
    }
    if ($projection['category_slugs']) {
        wp_set_object_terms($post_id, $projection['category_slugs'], 'category', false);
    }

    return ['action' => $existing_id > 0 ? 'update' : 'create', 'post_id' => $post_id];
}

function iss_content_fallback_generated_ids_for_source_type(string $source_type): array
{
    return get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => true,
        'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Fallback stale checks are bounded maintenance queries over generated projection posts.
            [
                'key' => '_iss_fallback_origin',
                'value' => 'generated',
            ],
            [
                'key' => '_iss_fallback_source_type',
                'value' => sanitize_key($source_type),
            ],
        ],
    ]);
}

function iss_content_fallback_mark_stale_generated(string $source_type, array $seen_source_ids, bool $dry_run): int
{
    $seen = array_fill_keys(array_map('intval', $seen_source_ids), true);
    $count = 0;
    foreach (iss_content_fallback_generated_ids_for_source_type($source_type) as $post_id) {
        $source_id = (int) get_post_meta((int) $post_id, '_iss_fallback_source_id', true);
        if (isset($seen[$source_id])) {
            continue;
        }
        ++$count;
        if (!$dry_run) {
            wp_update_post([
                'ID' => (int) $post_id,
                'post_status' => 'draft',
            ]);
            update_post_meta((int) $post_id, '_iss_fallback_stale', '1');
        }
    }

    return $count;
}

function iss_content_fallback_project(array $args = []): array
{
    $dry_run = !empty($args['dry_run']);
    $selected_type = sanitize_key((string) ($args['type'] ?? ''));
    $limit = max(0, (int) ($args['limit'] ?? 0));
    $mark_stale = array_key_exists('mark_stale', $args) ? (bool) $args['mark_stale'] : $limit === 0;
    $projectors = iss_content_fallback_get_projectors();
    $counts = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'skipped' => 0, 'stale' => 0, 'error' => 0];
    $errors = [];
    $processed = 0;

    foreach ($projectors as $type => $projector) {
        $type = sanitize_key((string) $type);
        if ($selected_type !== '' && $selected_type !== $type) {
            continue;
        }
        $callback = $projector['callback'] ?? null;
        if (!is_callable($callback)) {
            ++$counts['skipped'];
            $errors[] = sprintf('Projector unavailable: %s', $type);
            continue;
        }

        $seen = [];
        $projections = (array) call_user_func($callback, $args);
        foreach ($projections as $projection) {
            if ($limit > 0 && $processed >= $limit) {
                break 2;
            }
            if (!is_array($projection)) {
                ++$counts['skipped'];
                continue;
            }
            $projection = iss_content_fallback_normalize_projection($projection);
            $seen[] = (int) $projection['source_id'];
            $result = iss_content_fallback_apply_projection($projection, $dry_run);
            $action = (string) ($result['action'] ?? 'error');
            if (!isset($counts[$action])) {
                $action = 'error';
            }
            ++$counts[$action];
            if (!empty($result['error'])) {
                $errors[] = (string) $result['error'];
            }
            ++$processed;
        }

        if ($mark_stale) {
            $counts['stale'] += iss_content_fallback_mark_stale_generated($type, $seen, $dry_run);
        }
    }

    $report = [
        'dry_run' => $dry_run,
        'type' => $selected_type !== '' ? $selected_type : 'all',
        'processed' => $processed,
        'counts' => $counts,
        'errors' => $errors,
        'ran_at' => current_time('mysql'),
    ];
    if (!$dry_run) {
        update_option(ISS_CONTENT_FALLBACK_LAST_RUN_OPTION, $report, false);
    }

    return $report;
}

function iss_content_fallback_status_report(): array
{
    $generated_ids = get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => true,
        'meta_key' => '_iss_fallback_origin', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Status report reads low-volume fallback projection metadata.
        'meta_value' => 'generated', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Status report reads low-volume fallback projection metadata.
    ]);
    $native_ids = get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => true,
        'meta_key' => '_iss_fallback_origin', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Status report reads low-volume fallback-native metadata.
        'meta_value' => 'fallback-native', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Status report reads low-volume fallback-native metadata.
    ]);

    $published = 0;
    $draft = 0;
    $stale = 0;
    foreach ($generated_ids as $post_id) {
        $status = get_post_status((int) $post_id);
        if ($status === 'publish') {
            ++$published;
        }
        if ($status === 'draft') {
            ++$draft;
        }
        if (get_post_meta((int) $post_id, '_iss_fallback_stale', true) === '1') {
            ++$stale;
        }
    }

    return [
        'mode_enabled' => iss_content_fallback_mode_enabled(),
        'generated_total' => count($generated_ids),
        'generated_published' => $published,
        'generated_draft' => $draft,
        'generated_stale' => $stale,
        'fallback_native_total' => count($native_ids),
        'last_run' => get_option(ISS_CONTENT_FALLBACK_LAST_RUN_OPTION, []),
    ];
}

function iss_content_fallback_register_core_status_rows(array $rows): array
{
    if (!function_exists('iss_core_status_row')) {
        return $rows;
    }

    $status = iss_content_fallback_status_report();
    $rows[] = iss_core_status_row(
        'fallback',
        'mode',
        !empty($status['mode_enabled']) ? 'warning' : 'ok',
        !empty($status['mode_enabled']) ? 'enabled' : 'disabled'
    );
    $rows[] = iss_core_status_row(
        'fallback',
        'projection',
        ((int) ($status['generated_stale'] ?? 0)) > 0 ? 'warning' : 'ok',
        sprintf(
            'generated=%d published=%d draft=%d stale=%d native=%d',
            (int) ($status['generated_total'] ?? 0),
            (int) ($status['generated_published'] ?? 0),
            (int) ($status['generated_draft'] ?? 0),
            (int) ($status['generated_stale'] ?? 0),
            (int) ($status['fallback_native_total'] ?? 0)
        )
    );

    return $rows;
}
add_filter('iss_core_status_rows', 'iss_content_fallback_register_core_status_rows');

function iss_content_fallback_register_core_backfill_steps(array $steps, bool $dry_run, bool $include_external): array
{
    unset($include_external);
    if (!function_exists('iss_core_backfill_step')) {
        return $steps;
    }

    $steps[] = iss_core_backfill_step('iss-content fallback projection', static function () {
        return iss_content_fallback_project(['dry_run' => false, 'mark_stale' => true]);
    }, $dry_run);

    return $steps;
}
add_filter('iss_core_backfill_steps', 'iss_content_fallback_register_core_backfill_steps', 10, 3);

function iss_content_fallback_schedule_sweep(): void
{
    if (!wp_next_scheduled(ISS_CONTENT_FALLBACK_CRON_HOOK)) {
        wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', ISS_CONTENT_FALLBACK_CRON_HOOK);
    }
}
add_action('init', 'iss_content_fallback_schedule_sweep');

function iss_content_fallback_run_sweep(): void
{
    iss_content_fallback_project([
        'dry_run' => false,
        'limit' => 25,
        'mark_stale' => false,
    ]);
}
add_action(ISS_CONTENT_FALLBACK_CRON_HOOK, 'iss_content_fallback_run_sweep');
