<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_register_blocks(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    $archive_set_selector_path = ISS_WF_IMPORT_PATH . 'assets/js/archive-set-selector.js';
    if (file_exists($archive_set_selector_path)) {
        wp_register_script(
            'iss-wf-import-archive-set-selector',
            ISS_WF_IMPORT_URL . 'assets/js/archive-set-selector.js',
            ['wp-api-fetch'],
            (string) filemtime($archive_set_selector_path),
            true
        );
    }

    $archive_object_dir = ISS_WF_IMPORT_PATH . 'blocks/archive-object';
    if (file_exists($archive_object_dir . '/block.json')) {
        register_block_type($archive_object_dir, [
            'render_callback' => 'iss_wf_import_render_archive_object_block',
        ]);
    }

    register_block_type('iss-wf-import/featured-archive-object', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_featured_archive_object_block',
        'supports' => [
            'inserter' => false,
        ],
    ]);

    register_block_type('iss-wf-import/archive-collection', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_archive_collection_block',
        'supports' => [
            'inserter' => false,
        ],
    ]);

    register_block_type('iss-wf-import/archive-album', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_archive_album_block',
        'supports' => [
            'inserter' => false,
        ],
    ]);

    register_block_type('iss-wf-import/archive-object-media', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_archive_object_media_block',
        'supports' => [
            'inserter' => false,
        ],
    ]);

    $archive_object_browser_dir = ISS_WF_IMPORT_PATH . 'blocks/archive-object-browser';
    if (file_exists($archive_object_browser_dir . '/block.json')) {
        register_block_type($archive_object_browser_dir, [
            'render_callback' => 'iss_wf_import_render_archive_object_browser_block',
        ]);
    } else {
        register_block_type('iss-wf-import/archive-object-browser', [
            'api_version' => 2,
            'render_callback' => 'iss_wf_import_render_archive_object_browser_block',
        ]);
    }

}
add_action('init', 'iss_wf_import_register_blocks', 20);

function iss_wf_import_get_archive_image_meta_line(array $image): string
{
    $parts = [];

    foreach (['creator', 'owner', 'rights'] as $key) {
        $value = trim((string) ($image[$key] ?? ''));
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    return implode(' · ', array_slice($parts, 0, 3));
}

function iss_wf_import_get_archive_image_display(array $image, string $size = 'large'): array
{
    $attachment_id = absint($image['preview_attachment_id'] ?? 0);
    if ($attachment_id <= 0) {
        $attachment_id = absint($image['attachment_id'] ?? 0);
    }

    $href = '';
    if ($attachment_id > 0) {
        $href = (string) wp_get_attachment_url($attachment_id);
    }

    if ($href === '') {
        $href = esc_url_raw((string) ($image['source_url'] ?? ($image['preview_url'] ?? '')));
    }

    if ($attachment_id > 0) {
        $html = (string) wp_get_attachment_image($attachment_id, $size);
    } else {
        $src = esc_url((string) ($image['preview_url'] ?? ($image['source_url'] ?? '')));
        $alt = esc_attr((string) ($image['label'] ?? ''));
        $html = $src !== '' ? '<img src="' . $src . '" alt="' . $alt . '" loading="lazy" />' : '';
    }

    return [
        'html' => $html,
        'href' => $href,
    ];
}

function iss_wf_import_normalize_archive_object_member_for_render(array $item): array
{
    $post_id = absint($item['object_id'] ?? 0);
    $post = $post_id > 0 ? get_post($post_id) : null;
    $title = trim((string) ($item['title'] ?? ''));
    $caption = trim((string) ($item['caption_override'] ?? ''));
    $page_label = trim((string) ($item['page_label'] ?? ''));
    $link_url = '';
    $image_html = '';

    if ($post instanceof WP_Post && $post->post_type === ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        if ($title === '') {
            $title = get_the_title($post);
        }

        $link_url = (string) get_permalink($post);
        if (has_post_thumbnail($post)) {
            $image_html = (string) get_the_post_thumbnail($post, 'large');
        }

        if ($caption === '') {
            $projection = iss_wf_import_get_object_service()->ensure_projection_for_post((int) $post->ID);
            $caption = is_array($projection)
                ? trim((string) ($projection['creator_label'] ?? ''))
                : '';
            if ($caption === '') {
                $caption = trim((string) get_the_excerpt($post));
            }
        }
    }

    if ($title === '') {
        $title = __('Archivobjekt', 'iss-wf-import');
    }

    if ($link_url === '') {
        $link_url = esc_url_raw((string) ($item['source_url'] ?? ''));
    }

    $caption = trim((string) preg_replace('/\s+/u', ' ', $caption));

    return [
        'object_id' => $post_id,
        'post' => $post,
        'title' => $title,
        'caption' => $caption,
        'page_label' => $page_label,
        'link_url' => $link_url,
        'image_html' => $image_html,
        'source_url' => esc_url_raw((string) ($item['source_url'] ?? '')),
        'set_id' => absint($item['set_id'] ?? 0),
        'member_id' => absint($item['member_id'] ?? 0),
        'position' => max(0, absint($item['position'] ?? 0)),
    ];
}

function iss_wf_import_render_archive_object_member(array $item, string $variant = 'grid-card'): string
{
    $member = iss_wf_import_normalize_archive_object_member_for_render($item);
    $post_id = absint($member['object_id'] ?? 0);
    $post = $member['post'] ?? null;

    if ($post_id <= 0 || (!$post instanceof WP_Post && empty($member['source_url']))) {
        return '';
    }

    if ($variant === 'featured') {
        return iss_wf_import_render_featured_archive_object($post_id, [
            'title' => (string) $member['title'],
            'excerpt' => (string) $member['caption'],
            'set_id' => absint($member['set_id'] ?? 0),
            'member_id' => absint($member['member_id'] ?? 0),
            'position' => max(0, absint($member['position'] ?? 0)),
        ]);
    }

    $title = (string) $member['title'];
    $caption = (string) $member['caption'];
    $page_label = (string) $member['page_label'];
    $link_url = (string) $member['link_url'];
    $image_html = (string) $member['image_html'];

    ob_start();
    ?>
    <article class="iss-archive-card iss-archive-card--mediathek iss-archive-records__card">
        <?php if ($image_html !== '') : ?>
            <a class="iss-archive-card__media" href="<?php echo esc_url($link_url); ?>">
                <?php echo wp_kses_post($image_html); ?>
            </a>
        <?php endif; ?>
        <div class="iss-archive-card__body">
            <span class="iss-archive-card__type"><?php esc_html_e('Archivobjekt', 'iss-wf-import'); ?></span>
            <h3 class="iss-archive-card__title">
                <a class="iss-archive-card__link" href="<?php echo esc_url($link_url); ?>"><?php echo esc_html($title); ?></a>
            </h3>
            <?php if ($caption !== '') : ?>
                <p class="iss-archive-card__text"><?php echo esc_html($caption); ?></p>
            <?php endif; ?>
            <?php if ($page_label !== '') : ?>
                <div class="iss-archive-card__meta">
                    <span><?php echo esc_html($page_label); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_wf_import_render_archive_object_card(array $item): string
{
    return iss_wf_import_render_archive_object_member($item, 'grid-card');
}

function iss_wf_import_get_archivset_object_items_for_render(int $set_id, int $max_items = 0): array
{
    if ($set_id <= 0 || !function_exists('iss_wf_import_get_archivset_service')) {
        return [];
    }

    $items = [];
    foreach (iss_wf_import_get_archivset_service()->get_members($set_id) as $member) {
        if (!is_array($member) || (string) ($member['member_kind'] ?? '') !== 'object') {
            continue;
        }

        $object_id = absint($member['object_post_id'] ?? 0);
        if ($object_id <= 0) {
            continue;
        }

        $post = get_post($object_id);
        if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE || $post->post_status !== 'publish') {
            continue;
        }

        $items[] = [
            'object_id' => $object_id,
            'set_id' => $set_id,
            'member_id' => absint($member['id'] ?? 0),
            'position' => max(0, absint($member['position'] ?? 0)),
            'title' => trim((string) ($member['display_title'] ?? '')),
            'caption_override' => trim((string) ($member['caption'] ?? '')),
            'page_label' => trim((string) ($member['page_label'] ?? '')),
            'source_url' => esc_url_raw((string) ($member['member_source_url'] ?? '')),
        ];

        if ($max_items > 0 && count($items) >= $max_items) {
            break;
        }
    }

    return $items;
}

function iss_wf_import_get_archivset_object_item_for_render(int $set_id, int $member_position): array
{
    if ($set_id <= 0 || $member_position < 0) {
        return [];
    }

    foreach (iss_wf_import_get_archivset_object_items_for_render($set_id) as $item) {
        if (max(0, absint($item['position'] ?? 0)) === $member_position) {
            return $item;
        }
    }

    return [];
}

function iss_wf_import_get_attached_archivset_object_item_for_render(int $post_id, int $member_position): array
{
    if ($post_id <= 0 || $member_position < 0) {
        return [];
    }

    if (!function_exists('iss_wf_import_get_archivset_service')) {
        return [];
    }

    foreach (iss_wf_import_get_archivset_service()->get_sets_for_post($post_id) as $set) {
        if (!is_array($set)) {
            continue;
        }

        $item = iss_wf_import_get_archivset_object_item_for_render(absint($set['id'] ?? 0), $member_position);
        if ($item) {
            return $item;
        }
    }

    return [];
}

function iss_wf_import_get_public_source_label(string $slug, string $name = ''): string
{
    $slug = sanitize_title($slug);
    $name = trim($name);

    $map = [
        'museum-digital' => 'museum-digital',
        'wf-museum' => 'WF-Museum',
        'ddb' => 'Deutsche Digitale Bibliothek',
        'deutsche-digitale-bibliothek' => 'Deutsche Digitale Bibliothek',
        'europeana' => 'Europeana',
    ];

    if ($slug !== '' && isset($map[$slug])) {
        return $map[$slug];
    }

    return $name !== '' ? $name : $slug;
}

function iss_wf_import_get_public_source_names(int $post_id): array
{
    $terms = get_the_terms($post_id, ISS_WF_IMPORT_SOURCE_TAXONOMY);
    if (!is_array($terms)) {
        return [];
    }

    $names = [];
    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $label = iss_wf_import_get_public_source_label($term->slug, $term->name);
        if ($label !== '') {
            $names[] = $label;
        }
    }

    return array_values(array_unique($names));
}

function iss_wf_import_render_archive_collection_card(array $item): string
{
    $post_id = absint($item['collection_id'] ?? 0);
    $post = $post_id > 0 ? get_post($post_id) : null;

    if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return '';
    }

    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        $title = get_the_title($post);
    }

    $excerpt = trim((string) get_the_excerpt($post));
    $permalink = (string) get_permalink($post);

    ob_start();
    ?>
    <article class="iss-archive-card iss-archive-card--sammlung iss-archive-records__card">
        <?php if (has_post_thumbnail($post)) : ?>
            <a class="iss-archive-card__media" href="<?php echo esc_url($permalink); ?>">
                <?php echo get_the_post_thumbnail($post, 'large'); ?>
            </a>
        <?php endif; ?>
        <div class="iss-archive-card__body">
            <span class="iss-archive-card__type"><?php esc_html_e('Archivsammlung', 'iss-wf-import'); ?></span>
            <h3 class="iss-archive-card__title">
                <a class="iss-archive-card__link" href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
            </h3>
            <?php if ($excerpt !== '') : ?>
                <p class="iss-archive-card__text"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>
        </div>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_wf_import_get_collection_items_for_render(int $post_id): array
{
    $projection = iss_wf_import_get_collection_service()->ensure_projection_for_post($post_id);
    $items = is_array($projection) ? (array) ($projection['items'] ?? []) : [];

    return array_values(array_filter($items, static function ($item): bool {
        return is_array($item) && (absint($item['object_id'] ?? 0) > 0 || !empty($item['source_url']));
    }));
}

function iss_wf_import_get_collection_children_for_render(int $post_id): array
{
    $projection = iss_wf_import_get_collection_service()->ensure_projection_for_post($post_id);
    $children = is_array($projection) ? (array) ($projection['children'] ?? []) : [];

    return array_values(array_filter($children, static function ($item): bool {
        return is_array($item) && absint($item['collection_id'] ?? 0) > 0;
    }));
}

function iss_wf_import_get_archive_album_anchor(array $item, int $index): string
{
    $page_label = sanitize_title((string) ($item['page_label'] ?? ''));
    if ($page_label !== '') {
        return 'album-' . $page_label;
    }

    $object_id = absint($item['object_id'] ?? 0);
    if ($object_id > 0) {
        $post = get_post($object_id);
        if ($post instanceof WP_Post) {
            $slug = sanitize_title($post->post_name ?: $post->post_title);
            if ($slug !== '') {
                return 'album-' . $slug;
            }
        }
    }

    return 'album-item-' . max(1, $index + 1);
}

function iss_wf_import_get_archive_album_source_links(int $post_id): array
{
    $links = [];

    $projection = iss_wf_import_get_collection_service()->get_projection_for_post($post_id);
    $source_url = is_array($projection)
        ? trim((string) (($projection['collection']['source_url'] ?? '')))
        : '';
    if ($source_url === '') {
        $source_url = trim((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_URL_META, true));
    }
    if ($source_url !== '') {
        $links[] = [
            'label' => __('Originalquelle', 'iss-wf-import'),
            'url' => $source_url,
        ];
    }

    $source_ids = is_array($projection)
        ? (array) ($projection['source_refs'] ?? [])
        : null;
    if (!is_array($source_ids) || !$source_ids) {
        $source_ids = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META, true);
    }
    if (is_array($source_ids)) {
        foreach ($source_ids as $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = trim((string) ($item['source_url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $links[] = [
                'label' => $label !== '' ? $label : __('Digitaler Nachweis', 'iss-wf-import'),
                'url' => $url,
            ];
        }
    }

    $unique = [];
    foreach ($links as $link) {
        if ($link['url'] === '') {
            continue;
        }

        $unique[$link['url']] = $link;
    }

    return array_values($unique);
}

function iss_wf_import_get_archive_album_display_title(array $item, ?WP_Post $post = null): string
{
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '' && $post instanceof WP_Post) {
        $title = get_the_title($post);
    }

    $title = trim(wp_strip_all_tags($title));
    if ($title === '') {
        return '';
    }

    $title = preg_replace('/\s+/u', ' ', $title);
    $short = preg_replace('/,\s*S\.\s*\d+\s+des\s+Fotoalbums.*$/ui', '', $title);
    $short = is_string($short) ? trim($short, " \t\n\r\0\x0B,.;:-") : '';

    return $short !== '' ? $short : $title;
}

function iss_wf_import_render_archive_album_item(array $item, int $index): string
{
    $object_id = absint($item['object_id'] ?? 0);
    $post = $object_id > 0 ? get_post($object_id) : null;

    if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return '';
    }

    $anchor = iss_wf_import_get_archive_album_anchor($item, $index);
    $page_label = trim((string) ($item['page_label'] ?? ''));
    $title = iss_wf_import_get_archive_album_display_title($item, $post);

    $caption = trim((string) ($item['caption_override'] ?? ''));
    if ($caption === '') {
        $caption = trim((string) get_the_excerpt($post));
    }
    $caption = trim(wp_strip_all_tags($caption));
    if ($caption !== '' && $title !== '' && iss_wf_import_get_archive_album_display_title(['title' => $caption], null) === $title) {
        $caption = '';
    }

    $permalink = (string) get_permalink($post);
    $thumbnail_id = (int) get_post_thumbnail_id($post);
    $image_html = '';
    if ($thumbnail_id > 0) {
        $image_html = (string) wp_get_attachment_image($thumbnail_id, 'large', false, [
            'loading' => 'eager',
            'decoding' => 'async',
            'fetchpriority' => $index < 2 ? 'high' : 'auto',
        ]);
    } elseif (has_post_thumbnail($post)) {
        $image_html = (string) get_the_post_thumbnail($post, 'large', [
            'loading' => 'eager',
            'decoding' => 'async',
        ]);
    }
    $source_url = trim((string) ($item['source_url'] ?? ''));

    ob_start();
    ?>
    <article id="<?php echo esc_attr($anchor); ?>" class="iss-archive-album__item">
        <?php if ($image_html !== '') : ?>
            <figure class="iss-archive-album__figure">
                <a href="<?php echo esc_url($permalink); ?>">
                    <?php echo wp_kses_post($image_html); ?>
                </a>
                <figcaption class="iss-archive-album__item-copy">
                    <?php if ($page_label !== '') : ?>
                        <p class="iss-kicker iss-kicker--compact iss-archive-album__item-page"><?php echo esc_html($page_label); ?></p>
                    <?php endif; ?>
                    <h3 class="iss-archive-album__item-title"><?php echo esc_html($title); ?></h3>
                    <?php if ($caption !== '') : ?>
                        <div class="iss-archive-album__item-text">
                            <p><?php echo esc_html($caption); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($source_url !== '') : ?>
                        <p class="iss-archive-album__item-actions">
                            <a class="iss-action-link" href="<?php echo esc_url($source_url); ?>"><?php esc_html_e('Original ansehen', 'iss-wf-import'); ?></a>
                        </p>
                    <?php endif; ?>
                </figcaption>
            </figure>
        <?php endif; ?>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_wf_import_render_archive_album_block($attributes = [], $content = '', $block = null): string
{
    $post_id = (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return '';
    }

    $items = iss_wf_import_get_collection_items_for_render($post_id);
    if (!$items) {
        return '';
    }

    $source_links = iss_wf_import_get_archive_album_source_links($post_id);
    $source_names = iss_wf_import_get_public_source_names($post_id);

    $album_items = [];
    $album_nav = [];
    foreach ($items as $index => $item) {
        $anchor = iss_wf_import_get_archive_album_anchor($item, $index);
        $page_label = trim((string) ($item['page_label'] ?? ''));
        $object_id = absint($item['object_id'] ?? 0);
        $post = $object_id > 0 ? get_post($object_id) : null;
        $title = iss_wf_import_get_archive_album_display_title($item, $post instanceof WP_Post ? $post : null);

        $rendered_item = iss_wf_import_render_archive_album_item($item, $index);
        if ($rendered_item === '') {
            continue;
        }

        $album_items[] = $rendered_item;
        $album_nav[] = sprintf(
            '<li class="iss-archive-album__nav-item"><a href="#%1$s"><span class="iss-archive-album__nav-page">%2$s</span><span class="iss-archive-album__nav-title">%3$s</span></a></li>',
            esc_attr($anchor),
            esc_html($page_label !== '' ? $page_label : 'S.' . ($index + 1)),
            esc_html($title)
        );
    }

    if (!$album_items) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section section--plain iss-archive-album',
        ])
        : 'class="section section--plain iss-archive-album"';

    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';

    $out .= '<div class="iss-archive-album__layout">';
    $out .= '<aside class="iss-archive-album__sidebar">';
    $out .= '<div class="iss-archive-album__overview">';
    $out .= '<div class="iss-archive-album__facts iss-info-panel iss-info-panel--red">';
    $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Albumdaten', 'iss-wf-import') . '</p>';
    $out .= '<h2 class="iss-info-panel__title">' . esc_html__('Bestand und Nachweise', 'iss-wf-import') . '</h2>';
    $out .= '<div class="iss-info-panel__rows">';
    $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html__('Umfang', 'iss-wf-import') . '</strong></p><p class="iss-info-row__text">' . esc_html(sprintf(_n('%d Albumblatt', '%d Albumblätter', count($album_items), 'iss-wf-import'), count($album_items))) . '</p></div></div>';
    $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html__('Bestand', 'iss-wf-import') . '</strong></p><p class="iss-info-row__text">' . esc_html__('Industriesalon Schöneweide', 'iss-wf-import') . '</p></div></div>';

    if ($source_names) {
        $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html__('Digitale Nachweise', 'iss-wf-import') . '</strong></p><p class="iss-info-row__text">' . esc_html(implode(', ', $source_names)) . '</p></div></div>';
    }

    if ($source_links) {
        $link_parts = [];
        foreach ($source_links as $link) {
            $link_parts[] = '<a href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a>';
        }
        $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html__('Original', 'iss-wf-import') . '</strong></p><p class="iss-info-row__text">' . implode(' · ', $link_parts) . '</p></div></div>';
    }

    $out .= '</div>';
    $out .= '</div>';

    $out .= '<div class="iss-archive-album__navigator">';
    $out .= '<div class="iss-heading iss-archive-album__navigator-head">';
    $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Albumfolge', 'iss-wf-import') . '</p>';
    $out .= '<h2 class="iss-heading__title">' . esc_html__('Seiten und Motive', 'iss-wf-import') . '</h2>';
    $out .= '</div>';
    $out .= '<ol class="iss-archive-album__nav-list">';
    $out .= implode('', $album_nav);
    $out .= '</ol>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</aside>';

    $out .= '<div class="iss-archive-album__main">';
    $out .= '<div class="iss-archive-album__sequence">';
    $out .= '<div class="iss-heading iss-archive-album__sequence-head">';
    $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Album lesen', 'iss-wf-import') . '</p>';
    $out .= '<h2 class="iss-heading__title">' . esc_html__('Lokale Reihenfolge des Albums', 'iss-wf-import') . '</h2>';
    $out .= '</div>';
    $out .= '<div class="iss-archive-album__items">';
    $out .= implode('', $album_items);
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';

    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_wf_import_render_archive_collection_block($attributes = [], $content = '', $block = null): string
{
    $post_id = (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return '';
    }

    $items = iss_wf_import_get_collection_items_for_render($post_id);
    $children = iss_wf_import_get_collection_children_for_render($post_id);

    if (!$items && !$children) {
        $raw_content = trim((string) get_post_field('post_content', $post_id));
        if ($raw_content === '') {
            return '';
        }

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes([
                'class' => 'section section--plain iss-archive-records iss-archive-records--fallback',
            ])
            : 'class="section section--plain iss-archive-records iss-archive-records--fallback"';

        return '<section ' . $wrapper . '><div class="iss-container"><div class="iss-post-body__content iss-post-body__content--wide">' . apply_filters('the_content', $raw_content) . '</div></div></section>';
    }

    $object_cards = [];
    foreach ($items as $item) {
        $card = iss_wf_import_render_archive_object_card($item);
        if ($card !== '') {
            $object_cards[] = $card;
        }
    }

    $child_cards = [];
    foreach ($children as $item) {
        $card = iss_wf_import_render_archive_collection_card($item);
        if ($card !== '') {
            $child_cards[] = $card;
        }
    }

    if (!$object_cards && !$child_cards) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section section--plain iss-archive-records iss-archive-records--collection',
        ])
        : 'class="section section--plain iss-archive-records iss-archive-records--collection"';

    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';

    if ($object_cards) {
        $out .= '<div class="iss-archive-records__section">';
        $out .= '<div class="iss-heading iss-archive-records__intro">';
        $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Lokale Sammlung', 'iss-wf-import') . '</p>';
        $out .= '<h2 class="iss-heading__title">' . esc_html__('Sammlungsobjekte', 'iss-wf-import') . '</h2>';
        $out .= '</div>';
        $out .= '<div class="iss-archive-grid iss-archive-records__grid">';
        $out .= implode('', $object_cards);
        $out .= '</div>';
        $out .= '</div>';
    }

    if ($child_cards) {
        $out .= '<div class="iss-archive-records__section">';
        $out .= '<div class="iss-heading iss-archive-records__intro">';
        $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Weitere Wege', 'iss-wf-import') . '</p>';
        $out .= '<h2 class="iss-heading__title">' . esc_html__('Weitere Alben und Sammlungen', 'iss-wf-import') . '</h2>';
        $out .= '</div>';
        $out .= '<div class="iss-archive-grid iss-archive-records__grid">';
        $out .= implode('', $child_cards);
        $out .= '</div>';
        $out .= '</div>';
    }

    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_wf_import_render_archive_object_media_block($attributes = [], $content = '', $block = null): string
{
    $post_id = (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return '';
    }

    $projection = iss_wf_import_get_media_service()->ensure_projection_for_post($post_id);
    $images = is_array($projection) ? (array) ($projection['media'] ?? []) : [];
    $images = is_array($images) ? array_values(array_filter($images, static function ($item): bool {
        return is_array($item) && (!empty($item['source_url']) || absint($item['attachment_id'] ?? 0) > 0 || absint($item['preview_attachment_id'] ?? 0) > 0);
    })) : [];

    if (!$images) {
        return '';
    }

    $cards = [];
    foreach ($images as $image) {
        $display = iss_wf_import_get_archive_image_display($image);
        if ($display['html'] === '') {
            continue;
        }

        $caption = trim((string) ($image['label'] ?? ''));
        $meta_line = iss_wf_import_get_archive_image_meta_line($image);

        ob_start();
        ?>
        <figure class="iss-archive-card iss-archive-card--mediathek iss-archive-records__card iss-archive-media-card">
            <a class="iss-archive-card__media" href="<?php echo esc_url($display['href']); ?>">
                <?php echo wp_kses_post((string) $display['html']); ?>
            </a>
            <?php if ($caption !== '' || $meta_line !== '') : ?>
                <figcaption class="iss-archive-card__body">
                    <?php if ($caption !== '') : ?>
                        <p class="iss-archive-card__text"><?php echo esc_html($caption); ?></p>
                    <?php endif; ?>
                    <?php if ($meta_line !== '') : ?>
                        <div class="iss-archive-card__meta">
                            <span><?php echo esc_html($meta_line); ?></span>
                        </div>
                    <?php endif; ?>
                </figcaption>
            <?php endif; ?>
        </figure>
        <?php

        $cards[] = trim((string) ob_get_clean());
    }

    if (!$cards) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section section--plain iss-archive-records iss-archive-records--object-media',
        ])
        : 'class="section section--plain iss-archive-records iss-archive-records--object-media"';

    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';
    $out .= '<div class="iss-heading iss-archive-records__intro">';
    $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Lokale Medien', 'iss-wf-import') . '</p>';
    $out .= '<h2 class="iss-heading__title">' . esc_html__('Objektbilder', 'iss-wf-import') . '</h2>';
    $out .= '</div>';
    $out .= '<div class="iss-archive-grid iss-archive-records__grid">';
    $out .= implode('', $cards);
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_wf_import_get_archive_object_browser_request(): array
{
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public archive browser filters are read-only GET parameters.
    $page = max(1, absint($_GET['seite'] ?? get_query_var('seite') ?? 0));
    if ($page <= 1) {
        $page = max(1, absint(get_query_var('paged')));
    }

    $state = [
        'source' => sanitize_title(wp_unslash((string) ($_GET['quelle'] ?? ''))),
        'field' => sanitize_title(wp_unslash((string) ($_GET['feld'] ?? ''))),
        'family' => sanitize_title(wp_unslash((string) ($_GET['familie'] ?? ''))),
        'context' => sanitize_title(wp_unslash((string) ($_GET['kontext'] ?? ''))),
        'decade' => sanitize_title(wp_unslash((string) ($_GET['dekade'] ?? ''))),
        'search' => sanitize_text_field(wp_unslash((string) ($_GET['suche'] ?? ''))),
        'page' => $page,
    ];
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    return $state;
}

function iss_wf_import_get_archive_object_browser_term_options(string $taxonomy, int $limit = 0): array
{
    return iss_wf_import_get_archive_browser_service()->get_term_options($taxonomy, $limit);
}

function iss_wf_import_get_archive_object_browser_selected_term(string $taxonomy, string $slug): ?WP_Term
{
    if ($slug === '' || !taxonomy_exists($taxonomy)) {
        return null;
    }

    $term = get_term_by('slug', $slug, $taxonomy);
    return $term instanceof WP_Term ? $term : null;
}

function iss_wf_import_get_archive_object_browser_terms_by_slugs(string $taxonomy, array $slugs): array
{
    return iss_wf_import_get_archive_browser_service()->get_terms_by_slugs($taxonomy, $slugs);
}

function iss_wf_import_get_archive_object_browser_stats(array $state = []): array
{
    return iss_wf_import_get_archive_browser_service()->get_stats($state);
}

function iss_wf_import_get_archive_object_browser_query(array $state, int $per_page = 18): object
{
    return iss_wf_import_get_archive_browser_service()->get_query($state, $per_page);
}

function iss_wf_import_get_archive_object_browser_target_id(): string
{
    return 'archivbestand';
}

function iss_wf_import_get_archive_object_browser_target_url(string $url): string
{
    $url = trim($url);
    $target = iss_wf_import_get_archive_object_browser_target_id();

    if ($url === '') {
        return '#' . $target;
    }

    return explode('#', $url, 2)[0] . '#' . $target;
}

function iss_wf_import_get_archive_object_browser_url(array $overrides = [], bool $reset_page = false, ?array $base_state = null, string $base_url = ''): string
{
    $state = array_merge($base_state ?? iss_wf_import_get_archive_object_browser_request(), $overrides);
    $url = $base_url !== '' ? $base_url : get_post_type_archive_link(ISS_WF_IMPORT_OBJECT_POST_TYPE);
    if (!$url) {
        $url = home_url('/archiv/');
    }

    $args = [];
    foreach ([
        'quelle' => $state['source'] ?? '',
        'feld' => $state['field'] ?? '',
        'familie' => $state['family'] ?? '',
        'kontext' => $state['context'] ?? '',
        'dekade' => $state['decade'] ?? '',
        'suche' => $state['search'] ?? '',
    ] as $key => $value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value !== '') {
            $args[$key] = $value;
        }
    }

    $page = max(1, (int) ($state['page'] ?? 1));
    if (!$reset_page && $page > 1) {
        $args['seite'] = $page;
    }

    return iss_wf_import_get_archive_object_browser_target_url($args ? add_query_arg($args, $url) : $url);
}

function iss_wf_import_get_archive_object_browser_result_meta(int $post_id): array
{
    $meta = [];

    $source_names = iss_wf_import_get_public_source_names($post_id);
    if ($source_names) {
        $meta[] = implode(', ', array_slice($source_names, 0, 2));
    }

    $projection = iss_wf_import_get_object_service()->ensure_projection_for_post($post_id);
    $year = is_array($projection)
        ? trim((string) ($projection['year_label'] ?? ''))
        : '';
    if ($year !== '') {
        $meta[] = $year;
    }

    $family_names = wp_get_post_terms($post_id, ISS_WF_IMPORT_FAMILY_TAXONOMY, ['fields' => 'names']);
    if (is_array($family_names) && $family_names) {
        $meta[] = implode(', ', array_slice(array_map('strval', $family_names), 0, 2));
    }

    return $meta;
}

function iss_wf_import_get_archive_object_browser_kicker(int $post_id): string
{
    $taxonomy_order = [
        ISS_WF_IMPORT_FAMILY_TAXONOMY,
        ISS_WF_IMPORT_FIELD_TAXONOMY,
        ISS_WF_IMPORT_CONTEXT_TAXONOMY,
    ];

    foreach ($taxonomy_order as $taxonomy) {
        $names = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
        if (is_array($names) && !empty($names[0]) && is_string($names[0])) {
            return $names[0];
        }
    }

    return __('Archivobjekt', 'iss-wf-import');
}

function iss_wf_import_render_archive_object_browser_card(WP_Post $post): string
{
    $meta = iss_wf_import_get_archive_object_browser_result_meta($post->ID);
    $kicker = iss_wf_import_get_archive_object_browser_kicker($post->ID);
    $excerpt = trim((string) get_the_excerpt($post));
    $permalink = (string) get_permalink($post);
    if ($excerpt === '') {
        $excerpt = wp_trim_words(wp_strip_all_tags((string) $post->post_content), 26, '…');
    }

    ob_start();
    ?>
    <article class="iss-card iss-card--flat iss-archive-browser__result-card">
        <?php if (has_post_thumbnail($post)) : ?>
            <a class="iss-card__media" href="<?php echo esc_url($permalink); ?>">
                <?php echo get_the_post_thumbnail($post, 'large'); ?>
            </a>
        <?php endif; ?>
        <div class="iss-card__body">
            <p class="iss-kicker iss-kicker--compact"><?php echo esc_html($kicker); ?></p>
            <h3 class="iss-card__title">
                <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
            </h3>
            <?php if ($excerpt !== '') : ?>
                <p class="iss-card__text"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>
            <?php if ($meta) : ?>
                <div class="iss-card__meta">
                    <?php foreach ($meta as $item) : ?>
                        <span><?php echo esc_html($item); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="iss-card__link" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('Objekt öffnen', 'iss-wf-import'); ?></a>
        </div>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_wf_import_get_featured_archive_object_id(): int
{
    $featured_posts = get_posts([
        'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Landing feature lookup reads one editorial marker.
        'meta_key' => '_iss_archive_object_featured',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Paired with the featured marker above.
        'meta_value' => '1',
        'suppress_filters' => true,
    ]);
    $featured_post = is_array($featured_posts) ? ($featured_posts[0] ?? null) : null;
    if ($featured_post instanceof WP_Post) {
        return (int) $featured_post->ID;
    }

    $image_posts = get_posts([
        'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Fallback selects one object known to have media.
        'meta_key' => '_thumbnail_id',
        'suppress_filters' => true,
    ]);
    $image_post = is_array($image_posts) ? ($image_posts[0] ?? null) : null;
    if ($image_post instanceof WP_Post) {
        return (int) $image_post->ID;
    }

    $fallback_posts = get_posts([
        'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => true,
    ]);
    $fallback_post = is_array($fallback_posts) ? ($fallback_posts[0] ?? null) : null;

    return $fallback_post instanceof WP_Post ? (int) $fallback_post->ID : 0;
}

function iss_wf_import_get_archive_object_feature_meta(int $post_id): array
{
    $meta = [];

    $field_names = wp_get_post_terms($post_id, ISS_WF_IMPORT_FIELD_TAXONOMY, ['fields' => 'names']);
    if (is_array($field_names) && $field_names) {
        $meta[__('Themenfeld', 'iss-wf-import')] = implode(', ', array_slice(array_map('strval', $field_names), 0, 2));
    }

    $family_names = wp_get_post_terms($post_id, ISS_WF_IMPORT_FAMILY_TAXONOMY, ['fields' => 'names']);
    if (is_array($family_names) && $family_names) {
        $meta[__('Objektfamilie', 'iss-wf-import')] = implode(', ', array_slice(array_map('strval', $family_names), 0, 2));
    }

    $projection = iss_wf_import_get_object_service()->ensure_projection_for_post($post_id);
    $year = is_array($projection)
        ? trim((string) ($projection['year_label'] ?? ''))
        : '';
    if ($year !== '') {
        $meta[__('Datierung', 'iss-wf-import')] = $year;
    }

    return $meta;
}

function iss_wf_import_get_featured_archive_object_context(int $post_id): array
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE || $post->post_status !== 'publish') {
        return [];
    }

    $excerpt = trim((string) get_the_excerpt($post));
    if ($excerpt === '') {
        $excerpt = wp_trim_words(wp_strip_all_tags((string) $post->post_content), 34, '…');
    }

    return [
        'post_id' => $post_id,
        'post' => $post,
        'permalink' => (string) get_permalink($post_id),
        'kicker' => iss_wf_import_get_archive_object_browser_kicker($post_id),
        'excerpt' => $excerpt,
        'summary_meta' => iss_wf_import_get_archive_object_feature_meta($post_id),
    ];
}

function iss_wf_import_render_featured_archive_object(int $post_id, array $overrides = []): string
{
    $context = iss_wf_import_get_featured_archive_object_context($post_id);
    if ($context === []) {
        return '';
    }

    foreach ($overrides as $key => $value) {
        if (in_array($key, ['title', 'excerpt', 'set_id', 'member_id', 'position'], true)) {
            $context[$key] = $value;
        }
    }

    $theme_render = apply_filters('iss_wf_import_render_featured_archive_object', '', $post_id, $context);
    if (is_string($theme_render) && trim($theme_render) !== '') {
        return $theme_render;
    }

    $permalink = (string) ($context['permalink'] ?? '');
    $excerpt = trim((string) ($context['excerpt'] ?? ''));
    $title = trim((string) ($context['title'] ?? ''));
    if ($title === '') {
        $title = get_the_title($post_id);
    }

    ob_start();
    ?>
    <article class="iss-archive-featured-object">
        <a class="iss-media-card iss-media-card--cover iss-media-card--flat iss-archive-featured-object__media" href="<?php echo esc_url($permalink); ?>">
            <?php if (has_post_thumbnail($post_id)) : ?>
                <?php echo get_the_post_thumbnail($post_id, 'large'); ?>
            <?php endif; ?>
        </a>
        <div class="iss-archive-featured-object__body">
            <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Ausgewähltes Archivobjekt', 'iss-wf-import'); ?></p>
            <h2 class="iss-archive-featured-object__title"><?php echo esc_html($title); ?></h2>
            <?php if ($excerpt !== '') : ?>
                <p class="iss-archive-featured-object__text"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>
            <a class="iss-card__link" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('Objekt öffnen', 'iss-wf-import'); ?></a>
        </div>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_wf_import_render_archive_object_placement_block($attributes = [], $block = null, string $wrapper_class = 'wp-block-iss-archive-object'): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $post_id = isset($attributes['postId']) ? (int) $attributes['postId'] : 0;
    $set_id = absint($attributes['setId'] ?? 0);
    $member_position = array_key_exists('memberPosition', $attributes) ? (int) $attributes['memberPosition'] : -1;
    $variant = sanitize_key((string) ($attributes['variant'] ?? 'featured'));
    if ($variant === '') {
        $variant = 'featured';
    }
    if (!in_array($variant, ['featured'], true)) {
        $variant = 'featured';
    }
    $context_post_id = absint($attributes['contextPostId'] ?? 0);
    if ($context_post_id <= 0 && $block instanceof WP_Block) {
        $context_post_id = absint($block->context['postId'] ?? 0);
    }
    if ($context_post_id <= 0) {
        $context_post_id = (int) get_the_ID();
    }
    if ($context_post_id <= 0) {
        $context_post_id = (int) get_queried_object_id();
    }
    $member_item = [];

    if ($set_id > 0 && $member_position >= 0) {
        if ($context_post_id > 0 && !iss_wf_import_get_archivset_service()->set_is_attached_to_post($set_id, $context_post_id)) {
            return '';
        }
        $member_item = iss_wf_import_get_archivset_object_item_for_render($set_id, $member_position);
    } elseif ($member_position >= 0) {
        $member_item = iss_wf_import_get_attached_archivset_object_item_for_render($context_post_id, $member_position);
    }

    if ($member_item) {
        $html = iss_wf_import_render_archive_object_member($member_item, $variant);
    } else {
        $html = '';
    }

    if ($html === '' && ($set_id > 0 || $member_position >= 0)) {
        return '';
    }

    if ($post_id <= 0 || iss_wf_import_get_featured_archive_object_context($post_id) === []) {
        $post_id = iss_wf_import_get_featured_archive_object_id();
    }

    if ($html === '') {
        $html = $post_id > 0 ? iss_wf_import_render_featured_archive_object($post_id) : '';
    }
    if ($html === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => $wrapper_class])
        : 'class="' . esc_attr($wrapper_class) . '"';

    return '<div ' . $wrapper . '>' . $html . '</div>';
}

function iss_wf_import_render_archive_object_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    if (!isset($attributes['variant'])) {
        $attributes['variant'] = 'featured';
    }

    return iss_wf_import_render_archive_object_placement_block(
        $attributes,
        $block,
        'wp-block-iss-archive-object wp-block-iss-archive-object--featured wp-block-iss-featured-archive-object'
    );
}

function iss_wf_import_render_featured_archive_object_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $attributes['variant'] = 'featured';

    return iss_wf_import_render_archive_object_placement_block(
        $attributes,
        $block,
        'wp-block-iss-featured-archive-object'
    );
}

function iss_wf_import_normalize_archive_object_browser_discovery_links($links): array
{
    if (!is_array($links)) {
        return [];
    }

    $normalized = [];
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $label = sanitize_text_field((string) ($link['label'] ?? ''));
        $url = esc_url_raw((string) ($link['url'] ?? ''));
        if ($label === '' || $url === '') {
            continue;
        }

        $normalized[] = [
            'label' => $label,
            'url' => $url,
        ];
    }

    return $normalized;
}

function iss_wf_import_normalize_archive_object_browser_attributes(array $attributes = []): array
{
    return [
        'defaultSource' => sanitize_title((string) ($attributes['defaultSource'] ?? '')),
        'lockSource' => !empty($attributes['lockSource']),
        'defaultField' => sanitize_title((string) ($attributes['defaultField'] ?? '')),
        'lockField' => !empty($attributes['lockField']),
        'showHero' => array_key_exists('showHero', $attributes) ? !empty($attributes['showHero']) : true,
        'showStats' => array_key_exists('showStats', $attributes) ? !empty($attributes['showStats']) : true,
        'showSourceFilter' => array_key_exists('showSourceFilter', $attributes) ? !empty($attributes['showSourceFilter']) : true,
        'showContextFilter' => array_key_exists('showContextFilter', $attributes) ? !empty($attributes['showContextFilter']) : true,
        'showDecadeFilter' => array_key_exists('showDecadeFilter', $attributes) ? !empty($attributes['showDecadeFilter']) : true,
        'kicker' => sanitize_text_field((string) ($attributes['kicker'] ?? __('Archiv', 'iss-wf-import'))),
        'title' => sanitize_text_field((string) ($attributes['title'] ?? __('Archivobjekte', 'iss-wf-import'))),
        'introText' => sanitize_text_field((string) ($attributes['introText'] ?? __('Lokale Objektkopien, Findbuchfotos und technische Bestände mit Quellenbezug. Das größte Segment stammt aktuell aus museum-digital; Themenfelder und Objektfamilien werden schrittweise für editorische Zugänge ergänzt.', 'iss-wf-import'))),
        'quickKicker' => sanitize_text_field((string) ($attributes['quickKicker'] ?? __('Einstiege', 'iss-wf-import'))),
        'quickTitle' => sanitize_text_field((string) ($attributes['quickTitle'] ?? __('Nachweise und strukturierte Zugänge', 'iss-wf-import'))),
        'showSourceCards' => array_key_exists('showSourceCards', $attributes) ? !empty($attributes['showSourceCards']) : true,
        'quickFamilySlugs' => is_array($attributes['quickFamilySlugs'] ?? null) ? array_values(array_map('sanitize_title', (array) $attributes['quickFamilySlugs'])) : [],
        'discoveryTitle' => sanitize_text_field((string) ($attributes['discoveryTitle'] ?? __('Weiterentdecken', 'iss-wf-import'))),
        'discoveryLinks' => iss_wf_import_normalize_archive_object_browser_discovery_links($attributes['discoveryLinks'] ?? []),
        'useCurrentUrl' => !empty($attributes['useCurrentUrl']),
    ];
}

function iss_wf_import_get_archive_object_browser_payload(array $attributes = []): array
{
    $config = iss_wf_import_normalize_archive_object_browser_attributes($attributes);
    $state = iss_wf_import_get_archive_object_browser_request();

    if ($config['defaultSource'] !== '' && ($config['lockSource'] || $state['source'] === '')) {
        $state['source'] = $config['defaultSource'];
    }

    if ($config['defaultField'] !== '' && ($config['lockField'] || $state['field'] === '')) {
        $state['field'] = $config['defaultField'];
    }

    if (empty($config['showSourceFilter']) && empty($config['lockSource'])) {
        $state['source'] = '';
    }

    if (empty($config['showContextFilter'])) {
        $state['context'] = '';
    }

    if (empty($config['showDecadeFilter'])) {
        $state['decade'] = '';
    }

    $base_url = '';
    if ($config['useCurrentUrl']) {
        $queried_id = get_queried_object_id();
        if ($queried_id > 0) {
            $base_url = (string) get_permalink($queried_id);
        }
    }

    if ($base_url === '') {
        $base_url = (string) (get_post_type_archive_link(ISS_WF_IMPORT_OBJECT_POST_TYPE) ?: home_url('/archiv/'));
    }

    $selected_terms = [
        'source' => (!empty($config['showSourceFilter']) || !empty($config['lockSource']))
            ? iss_wf_import_get_archive_object_browser_selected_term(ISS_WF_IMPORT_SOURCE_TAXONOMY, $state['source'])
            : null,
        'field' => iss_wf_import_get_archive_object_browser_selected_term(ISS_WF_IMPORT_FIELD_TAXONOMY, $state['field']),
        'family' => iss_wf_import_get_archive_object_browser_selected_term(ISS_WF_IMPORT_FAMILY_TAXONOMY, $state['family']),
        'context' => !empty($config['showContextFilter'])
            ? iss_wf_import_get_archive_object_browser_selected_term(ISS_WF_IMPORT_CONTEXT_TAXONOMY, $state['context'])
            : null,
        'decade' => !empty($config['showDecadeFilter'])
            ? iss_wf_import_get_archive_object_browser_selected_term(ISS_WF_IMPORT_DECADE_TAXONOMY, $state['decade'])
            : null,
    ];

    $query = iss_wf_import_get_archive_object_browser_query($state, 18);
    $stats = !empty($config['showStats']) ? iss_wf_import_get_archive_object_browser_stats($state) : [];
    $source_options = (!empty($config['showSourceFilter']) || !empty($config['showSourceCards']) || !empty($config['showStats']))
        ? iss_wf_import_get_archive_object_browser_term_options(ISS_WF_IMPORT_SOURCE_TAXONOMY)
        : [];
    $field_options = iss_wf_import_get_archive_object_browser_term_options(ISS_WF_IMPORT_FIELD_TAXONOMY);
    $family_options = iss_wf_import_get_archive_object_browser_term_options(ISS_WF_IMPORT_FAMILY_TAXONOMY, 8);
    $context_options = !empty($config['showContextFilter'])
        ? iss_wf_import_get_archive_object_browser_term_options(ISS_WF_IMPORT_CONTEXT_TAXONOMY)
        : [];
    $decade_options = !empty($config['showDecadeFilter'])
        ? iss_wf_import_get_archive_object_browser_term_options(ISS_WF_IMPORT_DECADE_TAXONOMY)
        : [];
    $quick_families = $config['quickFamilySlugs']
        ? iss_wf_import_get_archive_object_browser_terms_by_slugs(ISS_WF_IMPORT_FAMILY_TAXONOMY, $config['quickFamilySlugs'])
        : array_slice($family_options, 0, 4);
    $active_filters = array_values(array_filter($selected_terms));
    $cards = [];

    foreach ($query->posts as $post) {
        if ($post instanceof WP_Post) {
            $cards[] = iss_wf_import_render_archive_object_browser_card($post);
        }
    }

    $results_label = sprintf(
        _n('%d Archivobjekt', '%d Archivobjekte', (int) $query->found_posts, 'iss-wf-import'),
        (int) $query->found_posts
    );

    $pagination = paginate_links([
        'base' => add_query_arg('seite', '%#%', iss_wf_import_get_archive_object_browser_url([], true, $state, $base_url)),
        'format' => '',
        'current' => max(1, (int) $state['page']),
        'total' => max(1, (int) $query->max_num_pages),
        'type' => 'list',
        'prev_text' => __('Zurück', 'iss-wf-import'),
        'next_text' => __('Weiter', 'iss-wf-import'),
    ]);

    return [
        'config' => $config,
        'state' => $state,
        'base_url' => $base_url,
        'selected_terms' => $selected_terms,
        'query' => $query,
        'stats' => $stats,
        'source_options' => $source_options,
        'field_options' => $field_options,
        'family_options' => $family_options,
        'context_options' => $context_options,
        'decade_options' => $decade_options,
        'quick_families' => $quick_families,
        'active_filters' => $active_filters,
        'cards' => $cards,
        'results_label' => $results_label,
        'pagination' => $pagination,
    ];
}

function iss_wf_import_render_archive_object_browser_markup(array $payload, array $options = []): string
{
    $defaults = [
        'wrapper_tag' => 'section',
        'wrapper_class' => 'section iss-archive-browser',
        'wrapper_attributes' => '',
        'use_container' => true,
        'show_hero' => true,
        'show_stats' => true,
        'title_tag' => 'h1',
        'results_kicker' => __('Ergebnisse', 'iss-wf-import'),
        'results_title' => '',
    ];
    $options = array_merge($defaults, $options);

    $config = is_array($payload['config'] ?? null) ? $payload['config'] : [];
    $state = is_array($payload['state'] ?? null) ? $payload['state'] : [];
    $base_url = (string) ($payload['base_url'] ?? '');
    $stats = is_array($payload['stats'] ?? null) ? $payload['stats'] : [];
    $source_options = is_array($payload['source_options'] ?? null) ? $payload['source_options'] : [];
    $field_options = is_array($payload['field_options'] ?? null) ? $payload['field_options'] : [];
    $family_options = is_array($payload['family_options'] ?? null) ? $payload['family_options'] : [];
    $context_options = is_array($payload['context_options'] ?? null) ? $payload['context_options'] : [];
    $decade_options = is_array($payload['decade_options'] ?? null) ? $payload['decade_options'] : [];
    $quick_families = is_array($payload['quick_families'] ?? null) ? $payload['quick_families'] : [];
    $active_filters = is_array($payload['active_filters'] ?? null) ? $payload['active_filters'] : [];
    $cards = is_array($payload['cards'] ?? null) ? $payload['cards'] : [];
    $pagination = (string) ($payload['pagination'] ?? '');
    $results_label = trim((string) ($options['results_title'] ?: ($payload['results_label'] ?? '')));
    $title_tag = in_array($options['title_tag'], ['h1', 'h2', 'h3'], true) ? $options['title_tag'] : 'h2';

    $wrapper_attributes = trim((string) $options['wrapper_attributes']);
    if ($wrapper_attributes === '') {
        $wrapper_class = trim((string) $options['wrapper_class']);
        $wrapper_attributes = $wrapper_class !== '' ? 'class="' . esc_attr($wrapper_class) . '"' : '';
    }

    ob_start();
    ?>
    <<?php echo esc_html($options['wrapper_tag']); ?> <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper attributes come from get_block_wrapper_attributes() or esc_attr() above. ?>>
        <?php if (!empty($options['use_container'])) : ?>
        <div class="iss-container">
        <?php endif; ?>
            <?php if (!empty($options['show_hero'])) : ?>
                <div class="iss-archive-browser__hero">
                    <div class="iss-heading iss-archive-browser__heading">
                        <p class="iss-kicker iss-kicker--compact"><?php echo esc_html((string) ($config['kicker'] ?? '')); ?></p>
                        <<?php echo esc_html($title_tag); ?> class="iss-heading__title"><?php echo esc_html((string) ($config['title'] ?? '')); ?></<?php echo esc_html($title_tag); ?>>
                        <p class="iss-heading__text"><?php echo esc_html((string) ($config['introText'] ?? '')); ?></p>
                    </div>

                    <?php if (!empty($options['show_stats'])) : ?>
                        <div class="iss-archive-browser__stats">
                            <article class="iss-archive-browser__stat">
                                <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Gesamt', 'iss-wf-import'); ?></p>
                                <h2><?php echo esc_html(number_format_i18n((int) ($stats['total_objects'] ?? 0))); ?></h2>
                                <p><?php esc_html_e('veröffentlichte Archivobjekte', 'iss-wf-import'); ?></p>
                            </article>
                            <article class="iss-archive-browser__stat">
                                <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Digital nachgewiesen', 'iss-wf-import'); ?></p>
                                <h2><?php echo esc_html(number_format_i18n((int) ($stats['source_counts']['museum-digital'] ?? 0))); ?></h2>
                                <p><?php esc_html_e('Objekte mit externem Plattformnachweis', 'iss-wf-import'); ?></p>
                            </article>
                            <article class="iss-archive-browser__stat">
                                <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Typisiert', 'iss-wf-import'); ?></p>
                                <h2><?php echo esc_html(number_format_i18n((int) ($stats['classified_objects'] ?? 0))); ?></h2>
                                <p><?php esc_html_e('Objekte mit neuer Familienzuordnung', 'iss-wf-import'); ?></p>
                            </article>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="iss-archive-browser__quick">
                <div class="iss-heading iss-archive-browser__section-head">
                    <p class="iss-kicker iss-kicker--compact"><?php echo esc_html((string) ($config['quickKicker'] ?? '')); ?></p>
                    <h2 class="iss-heading__title"><?php echo esc_html((string) ($config['quickTitle'] ?? '')); ?></h2>
                </div>
                <div class="iss-archive-entry-grid iss-archive-browser__quick-grid">
                    <?php if (!empty($config['showSourceCards'])) : ?>
                        <?php foreach (array_slice($source_options, 0, 2) as $term) : ?>
                            <a class="iss-archive-entry iss-archive-entry--sammlung" href="<?php echo esc_url(iss_wf_import_get_archive_object_browser_url([
                                'source' => $term->slug,
                                'field' => '',
                                'family' => '',
                                'context' => '',
                                'decade' => '',
                                'search' => '',
                                'page' => 1,
                            ], true, $state, $base_url)); ?>">
                                <p class="iss-archive-entry__eyebrow"><?php esc_html_e('Nachweis', 'iss-wf-import'); ?></p>
                                <h3 class="iss-archive-entry__title"><?php echo esc_html(iss_wf_import_get_public_source_label($term->slug, $term->name)); ?></h3>
                                <p class="iss-archive-entry__text"><?php esc_html_e('Archivobjekte mit diesem digitalen Nachweis direkt im lokalen Bestand durchsuchen.', 'iss-wf-import'); ?></p>
                                <div class="iss-archive-entry__meta"><span><?php echo esc_html(sprintf(_n('%d Objekt', '%d Objekte', (int) $term->count, 'iss-wf-import'), (int) $term->count)); ?></span></div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php foreach ($quick_families as $term) : ?>
                        <a class="iss-archive-entry iss-archive-entry--mediathek" href="<?php echo esc_url(iss_wf_import_get_archive_object_browser_url([
                            'source' => !empty($config['lockSource']) ? ($state['source'] ?? '') : '',
                            'field' => '',
                            'family' => $term->slug,
                            'context' => '',
                            'decade' => '',
                            'search' => '',
                            'page' => 1,
                        ], true, $state, $base_url)); ?>">
                            <p class="iss-archive-entry__eyebrow"><?php esc_html_e('Objektfamilie', 'iss-wf-import'); ?></p>
                            <h3 class="iss-archive-entry__title"><?php echo esc_html($term->name); ?></h3>
                            <p class="iss-archive-entry__text"><?php esc_html_e('Bereits editorisch typisierte Objekte dieser Familie direkt öffnen.', 'iss-wf-import'); ?></p>
                            <div class="iss-archive-entry__meta"><span><?php echo esc_html(sprintf(_n('%d Objekt', '%d Objekte', (int) $term->count, 'iss-wf-import'), (int) $term->count)); ?></span></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="<?php echo esc_attr(iss_wf_import_get_archive_object_browser_target_id()); ?>" class="iss-archive-browser__layout iss-scroll-target">
                <aside class="iss-archive-browser__sidebar">
                    <div class="iss-archive-browser__filters">
                        <div class="iss-heading iss-archive-browser__section-head">
                            <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Filter', 'iss-wf-import'); ?></p>
                            <h2 class="iss-heading__title"><?php esc_html_e('Archiv durchsuchen', 'iss-wf-import'); ?></h2>
                        </div>
                        <form class="iss-archive-browser__form" method="get" action="<?php echo esc_url(iss_wf_import_get_archive_object_browser_target_url($base_url)); ?>">
                            <label class="iss-archive-browser__field">
                                <span><?php esc_html_e('Suche', 'iss-wf-import'); ?></span>
                                <input class="iss-archive-browser__input" type="search" name="suche" value="<?php echo esc_attr((string) ($state['search'] ?? '')); ?>" />
                            </label>

                            <?php if (!empty($config['lockSource'])) : ?>
                                <input type="hidden" name="quelle" value="<?php echo esc_attr((string) ($state['source'] ?? '')); ?>" />
                            <?php elseif (!empty($config['showSourceFilter'])) : ?>
                                <label class="iss-archive-browser__field">
                                    <span><?php esc_html_e('Digitaler Nachweis', 'iss-wf-import'); ?></span>
                                    <select class="iss-archive-browser__select" name="quelle">
                                        <option value=""><?php esc_html_e('Alle Nachweise', 'iss-wf-import'); ?></option>
                                        <?php foreach ($source_options as $term) : ?>
                                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected((string) ($state['source'] ?? ''), $term->slug); ?>><?php echo esc_html(iss_wf_import_get_public_source_label($term->slug, $term->name) . ' (' . $term->count . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>

                            <?php if (!empty($config['lockField'])) : ?>
                                <input type="hidden" name="feld" value="<?php echo esc_attr((string) ($state['field'] ?? '')); ?>" />
                            <?php else : ?>
                                <label class="iss-archive-browser__field">
                                    <span><?php esc_html_e('Themenfeld', 'iss-wf-import'); ?></span>
                                    <select class="iss-archive-browser__select" name="feld">
                                        <option value=""><?php esc_html_e('Alle Themenfelder', 'iss-wf-import'); ?></option>
                                        <?php foreach ($field_options as $term) : ?>
                                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected((string) ($state['field'] ?? ''), $term->slug); ?>><?php echo esc_html($term->name . ' (' . $term->count . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>

                            <label class="iss-archive-browser__field">
                                <span><?php esc_html_e('Objektfamilie', 'iss-wf-import'); ?></span>
                                <select class="iss-archive-browser__select" name="familie">
                                    <option value=""><?php esc_html_e('Alle Objektfamilien', 'iss-wf-import'); ?></option>
                                    <?php foreach ($family_options as $term) : ?>
                                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected((string) ($state['family'] ?? ''), $term->slug); ?>><?php echo esc_html($term->name . ' (' . $term->count . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <?php if (!empty($config['showDecadeFilter'])) : ?>
                                <label class="iss-archive-browser__field">
                                    <span><?php esc_html_e('Dekade', 'iss-wf-import'); ?></span>
                                    <select class="iss-archive-browser__select" name="dekade">
                                        <option value=""><?php esc_html_e('Alle Dekaden', 'iss-wf-import'); ?></option>
                                        <?php foreach ($decade_options as $term) : ?>
                                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected((string) ($state['decade'] ?? ''), $term->slug); ?>><?php echo esc_html($term->name . ' (' . $term->count . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>

                            <?php if (!empty($config['showContextFilter'])) : ?>
                                <label class="iss-archive-browser__field">
                                    <span><?php esc_html_e('Kontext', 'iss-wf-import'); ?></span>
                                    <select class="iss-archive-browser__select" name="kontext">
                                        <option value=""><?php esc_html_e('Alle Kontexte', 'iss-wf-import'); ?></option>
                                        <?php foreach ($context_options as $term) : ?>
                                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected((string) ($state['context'] ?? ''), $term->slug); ?>><?php echo esc_html($term->name . ' (' . $term->count . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>

                            <div class="iss-archive-browser__actions">
                                <button class="wp-element-button" type="submit"><?php esc_html_e('Filtern', 'iss-wf-import'); ?></button>
                                <a class="iss-action-link" href="<?php echo esc_url(iss_wf_import_get_archive_object_browser_url([
                                    'source' => !empty($config['lockSource']) ? (string) ($state['source'] ?? '') : '',
                                    'field' => !empty($config['lockField']) ? (string) ($state['field'] ?? '') : '',
                                    'family' => '',
                                    'context' => '',
                                    'decade' => '',
                                    'search' => '',
                                    'page' => 1,
                                ], true, $state, $base_url)); ?>"><?php esc_html_e('Filter zurücksetzen', 'iss-wf-import'); ?></a>
                            </div>
                        </form>

                        <?php if (!empty($config['discoveryLinks'])) : ?>
                            <?php $discovery_title = trim((string) ($config['discoveryTitle'] ?? __('Weiterentdecken', 'iss-wf-import'))); ?>
                            <nav class="iss-archive-browser__discovery" aria-label="<?php echo esc_attr($discovery_title !== '' ? $discovery_title : __('Weiterentdecken', 'iss-wf-import')); ?>">
                                <?php if ($discovery_title !== '') : ?>
                                    <h3 class="iss-archive-browser__discovery-title"><?php echo esc_html($discovery_title); ?></h3>
                                <?php endif; ?>
                                <ul class="iss-archive-browser__discovery-list">
                                    <?php foreach ($config['discoveryLinks'] as $link) : ?>
                                        <li><a class="iss-archive-browser__discovery-link" href="<?php echo esc_url((string) ($link['url'] ?? '')); ?>"><?php echo esc_html((string) ($link['label'] ?? '')); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </aside>

                <div class="iss-archive-browser__results">
                    <div class="iss-archive-browser__results-head">
                        <div>
                            <p class="iss-kicker iss-kicker--compact"><?php echo esc_html((string) $options['results_kicker']); ?></p>
                            <h2 class="iss-heading__title"><?php echo esc_html($results_label); ?></h2>
                        </div>
                        <?php if ($active_filters) : ?>
                            <div class="iss-archive-browser__active">
                                <?php foreach ($active_filters as $term) : ?>
                                    <span><?php echo esc_html($term->name); ?></span>
                                <?php endforeach; ?>
                                <?php if ((string) ($state['search'] ?? '') !== '') : ?>
                                    <span><?php echo esc_html((string) $state['search']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php elseif ((string) ($state['search'] ?? '') !== '') : ?>
                            <div class="iss-archive-browser__active"><span><?php echo esc_html((string) $state['search']); ?></span></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($cards) : ?>
                        <div class="iss-card-grid iss-archive-browser__grid">
                            <?php echo wp_kses_post(implode('', $cards)); ?>
                        </div>
                        <?php if ($pagination) : ?>
                            <nav class="iss-archive-browser__pagination" aria-label="<?php esc_attr_e('Archivobjekt Seiten', 'iss-wf-import'); ?>">
                                <?php echo wp_kses_post($pagination); ?>
                            </nav>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="iss-archive-browser__empty">
                            <p><?php esc_html_e('Für diese Kombination wurden noch keine veröffentlichten Archivobjekte gefunden.', 'iss-wf-import'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php if (!empty($options['use_container'])) : ?>
        </div>
        <?php endif; ?>
    </<?php echo esc_html($options['wrapper_tag']); ?>>
    <?php

    wp_reset_postdata();

    return trim((string) ob_get_clean());
}

function iss_wf_import_render_archive_object_browser_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_wf_import_get_archive_object_browser_payload(is_array($attributes) ? $attributes : []);
    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section iss-archive-browser',
        ])
        : 'class="section iss-archive-browser"';

    return iss_wf_import_render_archive_object_browser_markup($payload, [
        'wrapper_tag' => 'section',
        'wrapper_attributes' => $wrapper,
        'use_container' => true,
        'show_hero' => !empty($payload['config']['showHero']),
        'show_stats' => !empty($payload['config']['showStats']),
        'title_tag' => 'h1',
    ]);
}
