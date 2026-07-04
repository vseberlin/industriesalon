<?php
/**
 * Theme-owned structured Führung presentation helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_editorial_tour_is_enabled(int $post_id): bool
{
    return $post_id > 0
        && function_exists('iss_editorial_document_is_enabled')
        && iss_editorial_document_is_enabled($post_id, 'fuehrung');
}

function industriesalon_get_editorial_tour_skins(): array
{
    return [
        'route-dossier',
        'compact',
        'standard',
    ];
}

add_filter('iss_editorial_format_skins', function (array $skins, string $format_slug): array {
    if ($format_slug !== 'fuehrung') {
        return $skins;
    }

    return [
        'route-dossier' => [
            'slug' => 'route-dossier',
            'label' => __('Routendossier', 'industriesalon'),
        ],
        'compact' => [
            'slug' => 'compact',
            'label' => __('Kompakt', 'industriesalon'),
        ],
        'standard' => [
            'slug' => 'standard',
            'label' => __('Standard', 'industriesalon'),
        ],
    ];
}, 10, 2);

function industriesalon_resolve_editorial_tour_skin(array $document): string
{
    $skin = sanitize_key((string) ($document['skin'] ?? 'route-dossier'));

    return in_array($skin, industriesalon_get_editorial_tour_skins(), true) ? $skin : 'route-dossier';
}

function industriesalon_get_editorial_tour_document(int $post_id, bool $prefer_autosave = false): array
{
    if ($post_id <= 0 || !industriesalon_editorial_tour_is_enabled($post_id) || !function_exists('iss_editorial_get_read_model')) {
        return [];
    }

    $document = iss_editorial_get_read_model($post_id, 'fuehrung', $prefer_autosave);

    return is_array($document) ? $document : [];
}

function industriesalon_get_editorial_tour_post_skin(int $post_id): string
{
    $document = industriesalon_get_editorial_tour_document($post_id, false);

    return $document ? industriesalon_resolve_editorial_tour_skin($document) : '';
}

add_filter('body_class', function (array $classes): array {
    if (!is_singular('fuehrung')) {
        return $classes;
    }

    if (industriesalon_get_editorial_tour_stage((int) get_queried_object_id())) {
        $classes[] = 'iss-tour-has-stage-gesture';
    }

    $skin = industriesalon_get_editorial_tour_post_skin((int) get_queried_object_id());
    if ($skin !== '') {
        $classes[] = 'iss-tour-editorial-skin-' . sanitize_html_class($skin);
    }

    return array_values(array_unique($classes));
});

function industriesalon_editorial_tour_sections(array $document): array
{
    return array_values(array_filter((array) ($document['sections'] ?? []), 'is_array'));
}

function industriesalon_editorial_tour_intro_section(array $sections): array
{
    foreach ($sections as $section) {
        if (is_array($section) && (string) ($section['type'] ?? '') === 'intro') {
            return $section;
        }
    }

    return [];
}

function industriesalon_editorial_tour_stage_section(array $sections): array
{
    foreach ($sections as $section) {
        if (is_array($section) && (string) ($section['type'] ?? '') === 'bildbuehne') {
            return $section;
        }
    }

    return [];
}

function industriesalon_filter_editorial_tour_description(string $description_html, int $post_id): string
{
    $prefer_autosave = is_preview() && current_user_can('edit_post', $post_id);
    $document = industriesalon_get_editorial_tour_document($post_id, $prefer_autosave);
    if ($document) {
        return '';
    }

    return $description_html;
}
add_filter('iss_fuehrung_description_html', 'industriesalon_filter_editorial_tour_description', 10, 2);

function industriesalon_get_editorial_tour_section_context(array $section, string $skin): array
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    unset($skin);

    $layouts = [
        'bildbuehne' => 'stage',
        'intro' => 'intro',
        'kapitel' => 'chapter',
        'leitfrage' => 'thesis',
        'zitat' => 'quote',
        'galerie' => 'gallery',
        'image_wall' => 'image-wall',
        'atlas_map' => 'atlas-map',
        'material' => 'material',
        'schluss' => 'conclusion',
    ];
    $labels = [
        'bildbuehne' => __('Bildbühne', 'industriesalon'),
        'kapitel' => __('Tourprofil', 'industriesalon'),
        'leitfrage' => __('Leitfrage', 'industriesalon'),
        'zitat' => __('Stimme', 'industriesalon'),
        'galerie' => __('Bilder', 'industriesalon'),
        'image_wall' => __('Bilder', 'industriesalon'),
        'atlas_map' => __('Route im Atlas', 'industriesalon'),
        'material' => __('Material', 'industriesalon'),
        'schluss' => __('Abschluss', 'industriesalon'),
    ];
    if ($type === 'galerie') {
        $gallery_layout = sanitize_key((string) ($section['gallery_layout'] ?? 'grid'));
        if ($gallery_layout === 'wall') {
            $layouts[$type] = 'image-wall';
        } elseif ($gallery_layout === 'viewport') {
            $layouts[$type] = 'viewport-image';
        }
    }

    return [
        'type' => $type,
        'gesture' => $type === 'image_wall' ? 'galerie' : $type,
        'layout' => sanitize_html_class((string) ($layouts[$type] ?? 'standard')),
        'label' => (string) ($labels[$type] ?? __('Abschnitt', 'industriesalon')),
    ];
}

function industriesalon_editorial_tour_atlas_map_variant(array $section): string
{
    $treatment = strtolower((string) ($section['treatment'] ?? 'atlas-map.tour-route'));
    $treatment = (string) preg_replace('/[^a-z0-9_.-]/', '', $treatment);

    return $treatment === 'atlas-map.tour-route' ? 'tour-route' : 'tour-route';
}

function industriesalon_editorial_tour_section_anchor(array $section, int $index, array $used = []): string
{
    $anchor = sanitize_title((string) ($section['anchor'] ?? ''));
    if ($anchor === '') {
        $anchor = sanitize_title((string) ($section['title'] ?? ''));
    }
    if ($anchor === '') {
        $anchor = 'tour-abschnitt-' . (string) ($index + 1);
    }

    $base = $anchor;
    $suffix = 2;
    while (in_array($anchor, $used, true)) {
        $anchor = $base . '-' . (string) $suffix;
        ++$suffix;
    }

    return $anchor;
}

function industriesalon_render_editorial_tour_links(array $links): string
{
    $items = [];
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $label = trim((string) ($link['label'] ?? ''));
        $url = trim((string) ($link['url'] ?? ''));
        if ($label === '' || $url === '') {
            continue;
        }

        $items[] = '<a class="iss-tour-section__link iss-action-link" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }

    return $items ? '<nav class="iss-tour-section__links" aria-label="' . esc_attr__('Weiterführende Links', 'industriesalon') . '">' . implode('', $items) . '</nav>' : '';
}

function industriesalon_render_editorial_tour_media_reference(array $item, bool $show_placeholder): string
{
    $resolved = is_array($item['resolved'] ?? null) ? $item['resolved'] : [];
    $reference = is_array($item['reference'] ?? null) ? $item['reference'] : [];
    if (!$resolved) {
        if ($show_placeholder && function_exists('industriesalon_render_editorial_reference_placeholder')) {
            return industriesalon_render_editorial_reference_placeholder($reference);
        }

        return '';
    }

    $attachment_id = absint($resolved['id'] ?? 0);
    if ($attachment_id <= 0) {
        return '';
    }

    $url = (string) ($resolved['url'] ?? wp_get_attachment_url($attachment_id));
    $mime = (string) get_post_mime_type($attachment_id);
    $caption = trim((string) ($reference['label'] ?? $resolved['title'] ?? ''));

    if ($mime !== '' && strpos($mime, 'image/') !== 0) {
        $title = $caption !== '' ? $caption : (string) ($resolved['title'] ?? get_the_title($attachment_id));
        $extension = strtoupper((string) pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return '<article class="iss-tour-section__file">'
            . '<a class="iss-tour-section__file-link" href="' . esc_url($url) . '" download>'
            . '<span class="iss-tour-section__file-title">' . esc_html($title) . '</span>'
            . ($extension !== '' ? '<span class="iss-tour-section__file-meta">' . esc_html($extension) . '</span>' : '')
            . '</a></article>';
    }

    $image = wp_get_attachment_image($attachment_id, 'large', false, ['loading' => 'lazy']);
    if ($image === '') {
        return '';
    }

    ob_start();
    ?>
    <figure class="iss-tour-section__media-item">
        <?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress escapes attachment image markup. ?>
        <?php if ($caption !== '') : ?>
            <figcaption><?php echo esc_html($caption); ?></figcaption>
        <?php endif; ?>
    </figure>
    <?php

    return trim((string) ob_get_clean());
}

function industriesalon_get_editorial_tour_media_attachment_id(array $item): int
{
    $resolved = is_array($item['resolved'] ?? null) ? $item['resolved'] : [];
    $attachment_id = absint($resolved['id'] ?? 0);

    if ($attachment_id > 0) {
        return $attachment_id;
    }

    $reference = is_array($item['reference'] ?? null) ? $item['reference'] : [];

    return absint($reference['id'] ?? 0);
}

function industriesalon_editorial_tour_media_reference_is_image(array $item): bool
{
    $attachment_id = industriesalon_get_editorial_tour_media_attachment_id($item);
    if ($attachment_id <= 0) {
        return false;
    }

    return strpos((string) get_post_mime_type($attachment_id), 'image/') === 0;
}

function industriesalon_get_editorial_tour_stage(int $post_id): array
{
    $prefer_autosave = is_preview() && current_user_can('edit_post', $post_id);
    $document = industriesalon_get_editorial_tour_document($post_id, $prefer_autosave);
    $sections = $document ? industriesalon_editorial_tour_sections($document) : [];
    $stage = $sections ? industriesalon_editorial_tour_stage_section($sections) : [];

    return $stage ?: [];
}

function industriesalon_get_editorial_tour_stage_media_ids(array $stage): array
{
    $media_refs = is_array($stage['media_refs_resolved'] ?? null) ? $stage['media_refs_resolved'] : [];
    $ids = [];

    foreach ($media_refs as $ref) {
        $attachment_id = industriesalon_get_editorial_tour_media_attachment_id((array) $ref);
        if ($attachment_id > 0 && strpos((string) get_post_mime_type($attachment_id), 'image/') === 0) {
            $ids[] = $attachment_id;
        }
    }

    return array_values(array_unique($ids));
}

function industriesalon_render_editorial_tour_stage_background(array $stage): string
{
    $ids = industriesalon_get_editorial_tour_stage_media_ids($stage);
    $attachment_id = $ids[0] ?? 0;
    if ($attachment_id <= 0) {
        return '';
    }

    $image = wp_get_attachment_image($attachment_id, 'post-thumbnail', false, [
        'class' => 'iss-gesture-stage__image iss-tour-hero__stage-image',
        'data-iss-image-viewport' => '',
        'decoding' => 'async',
        'fetchpriority' => 'high',
    ]);
    if ($image === '') {
        return '';
    }

    return '<figure class="iss-gesture-stage__media iss-tour-hero__stage-media" aria-hidden="true">' . $image . '</figure>';
}

function industriesalon_render_editorial_tour_stage_gallery(array $stage): string
{
    $ids = array_slice(industriesalon_get_editorial_tour_stage_media_ids($stage), 1);
    if (!$ids) {
        return '';
    }

    if (function_exists('iss_relations_enqueue_related_strip_script')) {
        iss_relations_enqueue_related_strip_script();
    }

    $items = '';
    foreach ($ids as $index => $attachment_id) {
        $thumb = wp_get_attachment_image($attachment_id, 'medium', false, ['class' => 'iss-image-viewport-gallery__thumb-img iss-gesture-stage-gallery__thumb-img']);
        $full_url = wp_get_attachment_image_url($attachment_id, 'large');
        if (!$thumb || !$full_url) {
            continue;
        }

        $full_srcset = wp_get_attachment_image_srcset($attachment_id, 'large');
        $full_sizes = wp_get_attachment_image_sizes($attachment_id, 'large');
        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $alt = is_string($alt) ? $alt : '';

        $items .= '<button type="button" class="iss-image-viewport-gallery__choice iss-gesture-stage-gallery__thumb" data-iss-image-choice';
        $items .= ' data-iss-image-src="' . esc_url($full_url) . '"';
        if ($full_srcset) {
            $items .= ' data-iss-image-srcset="' . esc_attr($full_srcset) . '"';
        }
        if ($full_sizes) {
            $items .= ' data-iss-image-sizes="' . esc_attr($full_sizes) . '"';
        }
        $items .= ' data-iss-image-alt="' . esc_attr($alt) . '"';
        $items .= ' aria-label="' . esc_attr__('Hero-Bild anzeigen', 'industriesalon') . '">';
        $items .= $thumb;
        $items .= '</button>';
    }

    if ($items === '') {
        return '';
    }

    $html = '<div class="iss-image-viewport-gallery iss-gesture-stage__gallery iss-gesture-stage-gallery" data-iss-image-viewport-gallery data-iss-image-target=".iss-gesture-stage__media img" data-iss-strip-carousel>';
    $html .= '<div class="iss-image-viewport-gallery__track iss-gesture-stage-gallery__track" data-iss-strip-carousel-track>';
    $html .= $items;
    $html .= '</div>';
    $html .= '<div class="iss-image-viewport-gallery__controls iss-gesture-stage-gallery__controls" aria-label="' . esc_attr__('Hero-Galerie steuern', 'industriesalon') . '">';
    $html .= '<button type="button" class="iss-image-viewport-gallery__control iss-image-viewport-gallery__control--prev iss-gesture-stage-gallery__control iss-gesture-stage-gallery__control--prev" data-iss-strip-carousel-prev aria-label="' . esc_attr__('Vorherige Bilder', 'industriesalon') . '" disabled>';
    $html .= '<span aria-hidden="true">&#8592;</span>';
    $html .= '</button>';
    $html .= '<button type="button" class="iss-image-viewport-gallery__control iss-image-viewport-gallery__control--next iss-gesture-stage-gallery__control iss-gesture-stage-gallery__control--next" data-iss-strip-carousel-next aria-label="' . esc_attr__('Nächste Bilder', 'industriesalon') . '" disabled>';
    $html .= '<span aria-hidden="true">&#8594;</span>';
    $html .= '</button>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function industriesalon_render_editorial_tour_stage_title(string $block_content): string
{
    if (!is_singular('fuehrung')) {
        return $block_content;
    }

    $stage = industriesalon_get_editorial_tour_stage((int) get_queried_object_id());
    $title = trim((string) ($stage['title'] ?? ''));
    if ($title === '') {
        return $block_content;
    }

    return '<h1 class="iss-tour-hero__title iss-heading__title wp-block-post-title">' . esc_html($title) . '</h1>';
}

function industriesalon_trim_editorial_tour_hero_text(string $text): string
{
    $text = trim(wp_strip_all_tags(strip_shortcodes($text)));
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }

    $word_limited = wp_trim_words($text, 44, '…');
    if (function_exists('mb_strlen') && function_exists('mb_substr') && function_exists('mb_strrpos')) {
        if (mb_strlen($word_limited) <= 320) {
            return $word_limited;
        }

        $truncated = rtrim(mb_substr($word_limited, 0, 319));
        $space = mb_strrpos($truncated, ' ');
        if ($space !== false && $space > 220) {
            $truncated = rtrim(mb_substr($truncated, 0, $space));
        }

        return rtrim($truncated, " \t\n\r\0\x0B.,;:") . '…';
    }

    if (strlen($word_limited) <= 320) {
        return $word_limited;
    }

    return rtrim(substr($word_limited, 0, 319), " \t\n\r\0\x0B.,;:") . '…';
}

function industriesalon_get_editorial_tour_hero_text(int $post_id, array $stage = []): string
{
    $stage_body = trim((string) ($stage['body'] ?? ''));
    if ($stage_body !== '') {
        return industriesalon_trim_editorial_tour_hero_text($stage_body);
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !has_excerpt($post)) {
        return '';
    }

    return industriesalon_trim_editorial_tour_hero_text((string) $post->post_excerpt);
}

function industriesalon_render_editorial_tour_hero_text(int $post_id, array $stage = []): string
{
    $text = industriesalon_get_editorial_tour_hero_text($post_id, $stage);
    if ($text === '') {
        return '';
    }

    return '<div class="iss-tour-hero__lede iss-heading__text wp-block-post-excerpt"><p class="wp-block-post-excerpt__excerpt">' . esc_html($text) . '</p></div>';
}

function industriesalon_render_editorial_tour_archive_reference(array $item, bool $show_placeholder): string
{
    $resolved = is_array($item['resolved'] ?? null) ? $item['resolved'] : [];
    $reference = is_array($item['reference'] ?? null) ? $item['reference'] : [];
    if (!$resolved) {
        if ($show_placeholder && function_exists('industriesalon_render_editorial_reference_placeholder')) {
            return industriesalon_render_editorial_reference_placeholder($reference);
        }

        return '';
    }

    $title = trim((string) ($resolved['title'] ?? $reference['label'] ?? ''));
    if ($title === '') {
        return '';
    }

    $url = trim((string) ($resolved['url'] ?? ''));
    $thumbnail = trim((string) ($resolved['thumbnail'] ?? ''));

    ob_start();
    ?>
    <article class="iss-tour-section__archive-card">
        <?php if ($thumbnail !== '') : ?>
            <figure class="iss-tour-section__archive-media"><img src="<?php echo esc_url($thumbnail); ?>" alt=""></figure>
        <?php endif; ?>
        <div class="iss-tour-section__archive-body">
            <p class="iss-kicker iss-kicker--compact"><?php echo esc_html__('Archiv', 'industriesalon'); ?></p>
            <h3 class="iss-tour-section__archive-title"><?php echo esc_html($title); ?></h3>
            <?php if ($url !== '') : ?>
                <a class="iss-action-link" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Ansehen', 'industriesalon'); ?></a>
            <?php endif; ?>
        </div>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_tour_atlas_map_section(array $section, int $rendered_index, string $skin, string $anchor, int $post_id): string
{
    if (!function_exists('iss_relations_render_atlas_map_variant')) {
        return '';
    }

    $variant = industriesalon_editorial_tour_atlas_map_variant($section);
    $map_html = iss_relations_render_atlas_map_variant($variant, [
        'shellMode' => 'body',
    ], null, [
        'post_id' => $post_id,
    ]);

    if (trim($map_html) === '') {
        return '';
    }

    $context = industriesalon_get_editorial_tour_section_context($section, $skin);
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $links = is_array($section['links'] ?? null) ? $section['links'] : [];
    $links_html = industriesalon_render_editorial_tour_links($links);
    $section_classes = [
        'iss-tour-section',
        'iss-tour-section--gesture-' . $context['gesture'],
        'iss-tour-section--layout-' . $context['layout'],
        'iss-tour-editorial__section',
        'iss-tour-editorial__section--atlas_map',
        'iss-tour-editorial__section--skin-' . sanitize_html_class($skin),
        'has-map',
    ];
    $has_copy = $kicker !== '' || $title !== '' || $body !== '' || $links_html !== '';
    if ($has_copy) {
        $section_classes[] = 'has-copy';
    }

    ob_start();
    ?>
    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr(implode(' ', array_unique($section_classes))); ?>" data-section-gesture="<?php echo esc_attr($context['gesture']); ?>" data-section-layout="<?php echo esc_attr($context['layout']); ?>">
        <div class="iss-tour-section__inner iss-tour-section__inner--atlas-map">
            <?php if ($has_copy) : ?>
                <div class="iss-tour-section__copy">
                    <?php if ($kicker !== '') : ?>
                        <p class="iss-kicker iss-kicker--compact iss-tour-section__kicker"><?php echo esc_html($kicker); ?></p>
                    <?php endif; ?>
                    <?php if ($title !== '') : ?>
                        <h2 class="iss-tour-section__title"><?php echo esc_html($title); ?></h2>
                    <?php endif; ?>
                    <?php if ($body !== '') : ?>
                        <div class="iss-tour-section__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
                    <?php endif; ?>
                    <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links are escaped in industriesalon_render_editorial_tour_links(). ?>
                </div>
            <?php endif; ?>
            <?php echo $map_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Atlas map renderer escapes generated output. ?>
        </div>
    </section>
    <?php
    unset($rendered_index);

    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_tour_section(array $section, bool $show_placeholders, int $rendered_index, string $skin, string $anchor, int $post_id): string
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    if ($type === 'bildbuehne' || $type === 'intro') {
        return '';
    }

    if ($type === 'atlas_map') {
        return industriesalon_render_editorial_tour_atlas_map_section($section, $rendered_index, $skin, $anchor, $post_id);
    }

    $context = industriesalon_get_editorial_tour_section_context($section, $skin);
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $quote = trim((string) ($section['quote'] ?? ''));
    $attribution = trim((string) ($section['attribution'] ?? ''));
    $links = is_array($section['links'] ?? null) ? $section['links'] : [];
    $media_refs = is_array($section['media_refs_resolved'] ?? null) ? $section['media_refs_resolved'] : [];
    $object_refs = is_array($section['object_refs_resolved'] ?? null) ? $section['object_refs_resolved'] : [];
    $links_html = industriesalon_render_editorial_tour_links($links);
    $media_html = '';
    $refs_html = '';

    foreach ($media_refs as $ref) {
        if ($type === 'material' && industriesalon_editorial_tour_media_reference_is_image((array) $ref)) {
            continue;
        }
        $media_html .= industriesalon_render_editorial_tour_media_reference((array) $ref, $show_placeholders);
    }

    foreach ($object_refs as $ref) {
        if ($type === 'material') {
            continue;
        }
        $refs_html .= industriesalon_render_editorial_tour_archive_reference((array) $ref, $show_placeholders);
    }

    if ($kicker === '' && $title === '' && $body === '' && $quote === '' && $links_html === '' && $media_html === '' && $refs_html === '') {
        return '';
    }

    $section_classes = [
        'iss-tour-section',
        'iss-tour-section--gesture-' . $context['gesture'],
        'iss-tour-section--layout-' . $context['layout'],
        'iss-tour-editorial__section',
        'iss-tour-editorial__section--' . $type,
        'iss-tour-editorial__section--skin-' . sanitize_html_class($skin),
    ];
    if ($media_html !== '') {
        $section_classes[] = 'has-media';
    }
    if ($refs_html !== '') {
        $section_classes[] = 'has-refs';
    }
    if ($kicker !== '' || $title !== '' || $body !== '' || $quote !== '' || $links_html !== '') {
        $section_classes[] = 'has-copy';
    }
    if ($media_html === '' && $refs_html === '') {
        $section_classes[] = 'is-text-only';
    }
    $media_layout = sanitize_key((string) ($section['media_layout'] ?? 'inline'));
    if ($media_layout === 'aside-right') {
        $section_classes[] = 'iss-tour-section--media-aside';
    }

    ob_start();
    ?>
    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr(implode(' ', array_unique($section_classes))); ?>" data-section-gesture="<?php echo esc_attr($context['gesture']); ?>" data-section-layout="<?php echo esc_attr($context['layout']); ?>">
        <div class="iss-tour-section__inner">
            <?php if ($media_html !== '' && !in_array($context['layout'], ['gallery', 'image-wall', 'viewport-image'], true)) : ?>
                <div class="iss-tour-section__media"><?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media render through escaped helpers above. ?></div>
            <?php endif; ?>
            <?php if ($kicker !== '' || $title !== '' || $body !== '' || $quote !== '' || $links_html !== '') : ?>
                <div class="iss-tour-section__copy">
                    <?php if ($kicker !== '') : ?>
                        <p class="iss-kicker iss-kicker--compact iss-tour-section__kicker"><?php echo esc_html($kicker); ?></p>
                    <?php endif; ?>
                    <?php if ($title !== '') : ?>
                        <h2 class="iss-tour-section__title"><?php echo esc_html($title); ?></h2>
                    <?php endif; ?>
                    <?php if ($body !== '') : ?>
                        <div class="iss-tour-section__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
                    <?php endif; ?>
                    <?php if ($quote !== '') : ?>
                        <blockquote class="iss-tour-section__quote">
                            <?php echo wp_kses_post(wpautop($quote)); ?>
                            <?php if ($attribution !== '') : ?>
                                <cite><?php echo esc_html($attribution); ?></cite>
                            <?php endif; ?>
                        </blockquote>
                    <?php endif; ?>
                    <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links are escaped in industriesalon_render_editorial_tour_links(). ?>
                </div>
            <?php endif; ?>
            <?php if ($media_html !== '' && in_array($context['layout'], ['gallery', 'image-wall', 'viewport-image'], true)) : ?>
                <div class="iss-tour-section__media iss-tour-section__media--strip"><?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media render through escaped helpers above. ?></div>
            <?php endif; ?>
            <?php if ($refs_html !== '') : ?>
                <div class="iss-tour-section__refs"><?php echo $refs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Archive refs render through escaped helpers above. ?></div>
            <?php endif; ?>
        </div>
    </section>
    <?php
    unset($rendered_index);

    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_tour_content(string $content): string
{
    if (is_admin() || !is_singular('fuehrung') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = get_the_ID();
    $prefer_autosave = is_preview() && current_user_can('edit_post', (int) $post_id);
    $document = industriesalon_get_editorial_tour_document((int) $post_id, $prefer_autosave);
    $sections = $document ? industriesalon_editorial_tour_sections($document) : [];
    if (!$sections) {
        return '';
    }

    $skin = industriesalon_resolve_editorial_tour_skin($document);
    $show_placeholders = $prefer_autosave || current_user_can('edit_post', (int) $post_id);
    $html = '';
    $used_anchors = [];
    $rendered_index = 0;

    foreach ($sections as $index => $section) {
        $anchor = industriesalon_editorial_tour_section_anchor($section, (int) $index, $used_anchors);
        $used_anchors[] = $anchor;
        $section_html = industriesalon_render_editorial_tour_section($section, $show_placeholders, $rendered_index, $skin, $anchor, (int) $post_id);
        if (trim($section_html) !== '') {
            $html .= $section_html;
            ++$rendered_index;
        }
    }

    if (trim($html) === '') {
        return '';
    }

    return '<div class="iss-tour-editorial iss-tour-editorial--skin-' . esc_attr(sanitize_html_class($skin)) . '">' . $html . '</div>';
}
add_filter('the_content', 'industriesalon_render_editorial_tour_content', 12);

function industriesalon_render_editorial_tour_stage_slots(string $block_content, array $block): string
{
    if (is_admin() || !is_singular('fuehrung')) {
        return $block_content;
    }

    $post_id = (int) get_queried_object_id();
    $block_name = (string) ($block['blockName'] ?? '');
    $class_name = (string) ($block['attrs']['className'] ?? '');
    $stage = industriesalon_get_editorial_tour_stage($post_id);

    if ($block_name === 'core/post-excerpt' && preg_match('/(^|\s)iss-tour-hero__lede(\s|$)/', $class_name) === 1) {
        return industriesalon_render_editorial_tour_hero_text($post_id, $stage);
    }

    if ($block_name === 'core/group' && preg_match('/(^|\s)iss-tour-hero__description(\s|$)/', $class_name) === 1) {
        $prefer_autosave = is_preview() && current_user_can('edit_post', $post_id);
        $document = industriesalon_get_editorial_tour_document($post_id, $prefer_autosave);
        if ($document) {
            return '';
        }
    }

    if (!$stage) {
        return $block_content;
    }

    if ($block_name === 'core/group' && preg_match('/(^|\s)iss-tour-hero(\s|$)/', $class_name) === 1) {
        $stage_background = industriesalon_render_editorial_tour_stage_background($stage);
        if ($stage_background === '') {
            return $block_content;
        }

        return (string) preg_replace('/^(\s*<[a-z0-9]+\b[^>]*>)/i', '$1' . $stage_background, $block_content, 1);
    }

    if ($block_name === 'core/post-title' && preg_match('/(^|\s)iss-tour-hero__title(\s|$)/', $class_name) === 1) {
        return industriesalon_render_editorial_tour_stage_title($block_content);
    }

    if ($block_name === 'core/post-featured-image' && preg_match('/(^|\s)iss-tour-hero__featured-image(\s|$)/', $class_name) === 1) {
        return '';
    }

    if ($block_name === 'core/group' && preg_match('/(^|\s)iss-tour-hero__visual(\s|$)/', $class_name) === 1) {
        $stage_gallery = industriesalon_render_editorial_tour_stage_gallery($stage);
        if ($stage_gallery === '') {
            return $block_content;
        }

        return (string) preg_replace('/(\s*<\/[a-z0-9]+>\s*)$/i', $stage_gallery . '$1', $block_content, 1);
    }

    return $block_content;
}
add_filter('render_block', 'industriesalon_render_editorial_tour_stage_slots', 14, 2);

function industriesalon_suppress_empty_tour_editorial_section(string $block_content, array $block): string
{
    if (is_admin() || !is_singular('fuehrung') || (string) ($block['blockName'] ?? '') !== 'core/group') {
        return $block_content;
    }

    $class_name = (string) ($block['attrs']['className'] ?? '');
    if (preg_match('/(^|\s)iss-tour-editorial-section(\s|$)/', $class_name) !== 1) {
        return $block_content;
    }

    if (trim(wp_strip_all_tags($block_content)) === '' && preg_match('/<(img|figure|article|a)\b/i', $block_content) !== 1) {
        return '';
    }

    return $block_content;
}
add_filter('render_block', 'industriesalon_suppress_empty_tour_editorial_section', 20, 2);
