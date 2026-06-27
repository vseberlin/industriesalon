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

function industriesalon_filter_editorial_tour_description(string $description_html, int $post_id): string
{
    $prefer_autosave = is_preview() && current_user_can('edit_post', $post_id);
    $document = industriesalon_get_editorial_tour_document($post_id, $prefer_autosave);
    $intro = $document ? industriesalon_editorial_tour_intro_section(industriesalon_editorial_tour_sections($document)) : [];

    if (!$intro) {
        return $description_html;
    }

    $title = trim((string) ($intro['title'] ?? ''));
    $body = trim((string) ($intro['body'] ?? ''));
    $links = is_array($intro['links'] ?? null) ? $intro['links'] : [];
    $links_html = industriesalon_render_editorial_tour_links($links);

    if ($title === '' && $body === '' && $links_html === '') {
        return $description_html;
    }

    ob_start();
    ?>
    <div class="iss-tour-editorial-intro">
        <?php if ($title !== '') : ?>
            <h3 class="iss-tour-editorial-intro__title"><?php echo esc_html($title); ?></h3>
        <?php endif; ?>
        <?php if ($body !== '') : ?>
            <div class="iss-tour-editorial-intro__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
        <?php endif; ?>
        <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links are escaped in industriesalon_render_editorial_tour_links(). ?>
    </div>
    <?php

    return trim((string) ob_get_clean());
}
add_filter('iss_fuehrung_description_html', 'industriesalon_filter_editorial_tour_description', 10, 2);

function industriesalon_get_editorial_tour_section_context(array $section, string $skin): array
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    unset($skin);

    $layouts = [
        'intro' => 'intro',
        'kapitel' => 'chapter',
        'leitfrage' => 'thesis',
        'zitat' => 'quote',
        'galerie' => 'gallery',
        'image_wall' => 'image-wall',
        'material' => 'material',
        'schluss' => 'conclusion',
    ];

    return [
        'type' => $type,
        'gesture' => $type,
        'layout' => sanitize_html_class((string) ($layouts[$type] ?? 'standard')),
    ];
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

function industriesalon_render_editorial_tour_section(array $section, bool $show_placeholders, int $rendered_index, string $skin, string $anchor): string
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    if ($type === 'intro') {
        return '';
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
        $media_html .= industriesalon_render_editorial_tour_media_reference((array) $ref, $show_placeholders);
    }

    foreach ($object_refs as $ref) {
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
    $media_layout = sanitize_key((string) ($section['media_layout'] ?? 'inline'));
    if ($media_layout === 'aside-right') {
        $section_classes[] = 'iss-tour-section--media-aside';
    }

    ob_start();
    ?>
    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr(implode(' ', array_unique($section_classes))); ?>" data-section-gesture="<?php echo esc_attr($context['gesture']); ?>">
        <div class="iss-tour-section__inner">
            <?php if ($media_html !== '' && $type !== 'galerie' && $type !== 'image_wall') : ?>
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
            <?php if ($media_html !== '' && ($type === 'galerie' || $type === 'image_wall')) : ?>
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
        $section_html = industriesalon_render_editorial_tour_section($section, $show_placeholders, $rendered_index, $skin, $anchor);
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
