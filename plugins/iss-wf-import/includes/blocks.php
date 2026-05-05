<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_register_blocks(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    register_block_type('iss-wf-import/archive-collection', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_archive_collection_block',
    ]);

    register_block_type('iss-wf-import/archive-object-media', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_archive_object_media_block',
    ]);
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

function iss_wf_import_render_archive_object_card(array $item): string
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
            $caption = trim((string) get_post_meta($post->ID, ISS_WF_IMPORT_OBJECT_CREATOR_META, true));
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

    ob_start();
    ?>
    <article class="iss-archive-card iss-archive-card--mediathek iss-archive-records__card">
        <?php if ($image_html !== '') : ?>
            <a class="iss-archive-card__media" href="<?php echo esc_url($link_url); ?>">
                <?php echo $image_html; ?>
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

function iss_wf_import_render_archive_collection_block($attributes = [], $content = '', $block = null): string
{
    $post_id = (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return '';
    }

    $items = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_ITEMS_META, true);
    $children = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_CHILDREN_META, true);

    $items = is_array($items) ? array_values(array_filter($items, static function ($item): bool {
        return is_array($item) && (absint($item['object_id'] ?? 0) > 0 || !empty($item['source_url']));
    })) : [];
    $children = is_array($children) ? array_values(array_filter($children, static function ($item): bool {
        return is_array($item) && absint($item['collection_id'] ?? 0) > 0;
    })) : [];

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

    $images = get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, true);
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
                <?php echo $display['html']; ?>
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
