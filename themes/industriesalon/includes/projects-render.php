<?php

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_get_editorial_project_skins(): array
{
    return [
        'standard',
        'dossier',
    ];
}

add_filter('iss_editorial_format_skins', function (array $skins, string $format_slug): array {
    if ($format_slug !== 'projekt') {
        return $skins;
    }

    return [
        'dossier' => [
            'slug' => 'dossier',
            'label' => __('Dossier', 'industriesalon'),
        ],
        'standard' => [
            'slug' => 'standard',
            'label' => __('Standard', 'industriesalon'),
        ],
    ];
}, 10, 2);

function industriesalon_resolve_editorial_project_skin(array $document): string
{
    $skin = sanitize_key((string) ($document['skin'] ?? 'dossier'));

    return in_array($skin, industriesalon_get_editorial_project_skins(), true) ? $skin : 'dossier';
}

function industriesalon_get_editorial_project_post_skin(int $post_id): string
{
    if ($post_id <= 0 || !industriesalon_editorial_project_is_enabled($post_id) || !function_exists('iss_editorial_get_read_model')) {
        return '';
    }

    return industriesalon_resolve_editorial_project_skin(iss_editorial_get_read_model($post_id, 'projekt', false));
}

function industriesalon_editorial_project_is_enabled(int $post_id): bool
{
    return $post_id > 0
        && function_exists('iss_editorial_document_is_enabled')
        && iss_editorial_document_is_enabled($post_id, 'projekt');
}

add_filter('body_class', function (array $classes): array {
    if (!is_singular('projekt')) {
        return $classes;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return $classes;
    }

    $editorial_skin = industriesalon_get_editorial_project_post_skin((int) $post_id);
    if ($editorial_skin !== '') {
        $classes[] = 'iss-project-editorial-skin-' . sanitize_html_class($editorial_skin);
    }

    return array_values(array_unique($classes));
});

function industriesalon_get_editorial_project_section_context(array $section, string $skin): array
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    $layout = 'standard';
    unset($skin);

    $layouts = [
        'kapitel' => 'chapter',
        'fliesstext' => 'essay',
        'massstab' => 'key-points',
        'projekt_rail' => 'rail',
        'galerie' => 'gallery',
        'image_wall' => 'image-wall',
        'material' => 'material',
        'schluss' => 'conclusion',
    ];

    if (isset($layouts[$type])) {
        $layout = $layouts[$type];
    }

    return [
        'type' => $type,
        'gesture' => $type,
        'layout' => sanitize_html_class($layout),
    ];
}

function industriesalon_render_editorial_project_media_reference(array $item, bool $show_placeholder): string
{
    if (function_exists('industriesalon_render_editorial_media_reference')) {
        return industriesalon_render_editorial_media_reference($item, $show_placeholder);
    }

    return '';
}

function industriesalon_render_editorial_project_archive_reference(array $item, bool $show_placeholder): string
{
    if (function_exists('industriesalon_render_editorial_archive_reference')) {
        return industriesalon_render_editorial_archive_reference($item, $show_placeholder);
    }

    return '';
}

function industriesalon_render_editorial_project_links(array $links): string
{
    if (function_exists('industriesalon_render_editorial_links')) {
        return industriesalon_render_editorial_links($links);
    }

    return '';
}

function industriesalon_render_editorial_project_facts(array $facts): string
{
    $items = [];
    foreach ($facts as $fact) {
        if (!is_array($fact)) {
            continue;
        }

        $value = trim((string) ($fact['value'] ?? ''));
        $label = trim((string) ($fact['label'] ?? ''));
        if ($value === '' && $label === '') {
            continue;
        }

        $items[] = '<div class="iss-project-section__fact">'
            . ($value !== '' ? '<dt class="iss-project-section__fact-value">' . esc_html($value) . '</dt>' : '')
            . ($label !== '' ? '<dd class="iss-project-section__fact-label">' . esc_html($label) . '</dd>' : '')
            . '</div>';
    }

    if (!$items) {
        return '';
    }

    return '<dl class="iss-project-section__facts">' . implode('', $items) . '</dl>';
}

function industriesalon_editorial_project_section_anchor(array $section, int $index, array $used = []): string
{
    $anchor = sanitize_title((string) ($section['anchor'] ?? ''));
    if ($anchor === '') {
        $basis = trim((string) ($section['title'] ?? ''));
        if ($basis === '') {
            $basis = (string) ($section['type'] ?? 'projekt-abschnitt');
        }
        $anchor = sanitize_title($basis);
    }
    if ($anchor === '') {
        $anchor = 'projekt-abschnitt-' . (string) ($index + 1);
    }

    $base = $anchor;
    $suffix = 2;
    while (in_array($anchor, $used, true)) {
        $anchor = $base . '-' . (string) $suffix;
        ++$suffix;
    }

    return $anchor;
}

function industriesalon_editorial_project_rail_section(array $sections): array
{
    foreach ($sections as $section) {
        if (is_array($section) && (string) ($section['type'] ?? '') === 'projekt_rail') {
            return $section;
        }
    }

    return [];
}

function industriesalon_editorial_project_sections_without_rail(array $sections): array
{
    return array_values(array_filter($sections, static function ($section): bool {
        return is_array($section) && (string) ($section['type'] ?? '') !== 'projekt_rail';
    }));
}

function industriesalon_editorial_project_nav_items(array $sections, array $anchors = []): array
{
    $items = [];
    $used = [];

    foreach ($sections as $index => $section) {
        if (!is_array($section)) {
            continue;
        }

        $type = (string) ($section['type'] ?? '');
        if (!in_array($type, ['kapitel', 'schluss'], true)) {
            continue;
        }

        $title = trim((string) ($section['kicker'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($section['title'] ?? ''));
        }
        if ($title === '') {
            $title = $type === 'schluss' ? __('Kontakt', 'industriesalon') : __('Kapitel', 'industriesalon');
        }

        $anchor = isset($anchors[$index]) ? (string) $anchors[$index] : industriesalon_editorial_project_section_anchor($section, (int) $index, $used);
        $used[] = $anchor;
        $items[] = [
            'anchor' => $anchor,
            'label' => $title,
            'type' => $type,
        ];
    }

    return $items;
}

function industriesalon_render_editorial_project_rail(array $rail_section, array $nav_items): string
{
    if (!$rail_section || !$nav_items) {
        return '';
    }

    $kicker = trim((string) ($rail_section['kicker'] ?? ''));
    $title = trim((string) ($rail_section['title'] ?? ''));
    if ($title === '') {
        $title = __('Kapitel', 'industriesalon');
    }

    ob_start();
    ?>
    <aside class="iss-project-editorial__rail" aria-label="<?php echo esc_attr__('Projekt navigation', 'industriesalon'); ?>">
        <div class="iss-project-editorial__rail-inner">
            <div class="iss-project-editorial__rail-head">
                <?php if ($kicker !== '') : ?>
                    <p class="iss-project-editorial__rail-kicker"><?php echo esc_html($kicker); ?></p>
                <?php endif; ?>
                <h2 class="iss-project-editorial__rail-title"><?php echo esc_html($title); ?></h2>
            </div>
            <nav class="iss-project-editorial__rail-nav" aria-label="<?php echo esc_attr__('Projektabschnitte', 'industriesalon'); ?>">
                <?php foreach ($nav_items as $item) : ?>
                    <a class="iss-project-editorial__rail-link iss-project-editorial__rail-link--<?php echo esc_attr((string) $item['type']); ?>" href="#<?php echo esc_attr((string) $item['anchor']); ?>"><?php echo esc_html((string) $item['label']); ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </aside>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_get_editorial_project_block_context(int $post_id, string $block_name): object
{
    return (object) [
        'name' => $block_name,
        'blockName' => $block_name,
        'context' => [
            'postId' => $post_id,
        ],
    ];
}

function industriesalon_render_editorial_project_related_place_links(int $post_id): string
{
    if ($post_id <= 0 || !function_exists('iss_relations_render_related_place_links_block')) {
        return '';
    }

    return trim((string) iss_relations_render_related_place_links_block([
        'title' => __('Orte im Projekt', 'industriesalon'),
        'kicker' => __('Orte', 'industriesalon'),
        'perPage' => 4,
        'showRole' => true,
    ], '', industriesalon_get_editorial_project_block_context($post_id, 'iss/related-place-links')));
}

function industriesalon_render_editorial_project_related_content_group(int $post_id, array $attributes): string
{
    if ($post_id <= 0 || !function_exists('iss_relations_render_related_content_block')) {
        return '';
    }

    $attributes = array_merge([
        'layoutVariant' => 'rail',
        'showImage' => false,
        'showExcerpt' => false,
        'source' => 'entity',
    ], $attributes);

    return trim((string) iss_relations_render_related_content_block(
        $attributes,
        '',
        industriesalon_get_editorial_project_block_context($post_id, 'iss/related-content')
    ));
}

function industriesalon_render_editorial_project_context_stack(int $post_id): string
{
    if (!industriesalon_editorial_project_is_enabled($post_id)) {
        return '';
    }

    $groups = array_values(array_filter([
        industriesalon_render_editorial_project_related_place_links($post_id),
        industriesalon_render_editorial_project_related_content_group($post_id, [
            'title' => __('Verwandte Inhalte', 'industriesalon'),
            'kicker' => __('Kontext', 'industriesalon'),
            'postTypes' => ['ausstellung', 'veranstaltung', 'publication', 'fuehrung', 'post', 'archivbeitrag'],
            'perPage' => 4,
        ]),
    ], static function (string $html): bool {
        return trim($html) !== '';
    }));

    if (!$groups) {
        return '';
    }

    return '<div class="iss-project-editorial__context" aria-label="' . esc_attr__('Projektkontext', 'industriesalon') . '">'
        . implode('', $groups)
        . '</div>';
}

function industriesalon_get_editorial_project_rail_context(int $post_id, bool $prefer_autosave = false): array
{
    if ($post_id <= 0 || !industriesalon_editorial_project_is_enabled($post_id) || !function_exists('iss_editorial_get_read_model')) {
        return [];
    }

    $document = iss_editorial_get_read_model($post_id, 'projekt', $prefer_autosave);
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    if (!$sections) {
        return [];
    }

    $rail_section = industriesalon_editorial_project_rail_section($sections);
    if (!$rail_section) {
        return [];
    }

    $content_sections = industriesalon_editorial_project_sections_without_rail($sections);
    $anchors = [];
    $used_anchors = [];
    foreach ($content_sections as $index => $section) {
        if (!is_array($section)) {
            continue;
        }

        $anchor = industriesalon_editorial_project_section_anchor($section, (int) $index, $used_anchors);
        $anchors[$index] = $anchor;
        $used_anchors[] = $anchor;
    }

    return [
        'rail_section' => $rail_section,
        'nav_items' => industriesalon_editorial_project_nav_items($content_sections, $anchors),
    ];
}

function industriesalon_append_editorial_project_rail_to_meta(string $block_content, array $block): string
{
    if (is_admin() || !is_singular('projekt') || (string) ($block['blockName'] ?? '') !== 'iss/content-meta') {
        return $block_content;
    }

    static $rendered = false;
    if ($rendered) {
        return $block_content;
    }

    $post_id = (int) get_queried_object_id();
    $prefer_autosave = is_preview() && current_user_can('edit_post', $post_id);
    $context = industriesalon_get_editorial_project_rail_context($post_id, $prefer_autosave);
    $rail_html = industriesalon_render_editorial_project_rail(
        is_array($context['rail_section'] ?? null) ? $context['rail_section'] : [],
        is_array($context['nav_items'] ?? null) ? $context['nav_items'] : []
    );
    $context_html = industriesalon_render_editorial_project_context_stack($post_id);

    if ($rail_html === '' && $context_html === '') {
        return $block_content;
    }

    $rendered = true;

    return $block_content
        . '<div class="iss-project-editorial__side-stack">'
        . $rail_html
        . $context_html
        . '</div>';
}
add_filter('render_block', 'industriesalon_append_editorial_project_rail_to_meta', 20, 2);

function industriesalon_suppress_legacy_project_rail_stack(string $block_content, array $block): string
{
    if (is_admin() || !is_singular('projekt') || (string) ($block['blockName'] ?? '') !== 'core/group') {
        return $block_content;
    }

    $post_id = (int) get_queried_object_id();
    if (!industriesalon_editorial_project_is_enabled($post_id)) {
        return $block_content;
    }

    $class_name = (string) ($block['attrs']['className'] ?? '');
    if (preg_match('/(^|\s)iss-project-rail-stack(\s|$)/', $class_name) !== 1) {
        return $block_content;
    }

    return '';
}
add_filter('render_block', 'industriesalon_suppress_legacy_project_rail_stack', 19, 2);

function industriesalon_render_editorial_project_section(array $section, bool $show_placeholders, int $rendered_index, string $skin, string $anchor = ''): string
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    if ($type === 'projekt_rail') {
        return '';
    }

    $context = industriesalon_get_editorial_project_section_context($section, $skin);
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $facts = is_array($section['facts'] ?? null) ? $section['facts'] : [];
    $refs = is_array($section['object_refs_resolved'] ?? null) ? $section['object_refs_resolved'] : [];
    $media_refs = is_array($section['media_refs_resolved'] ?? null) ? $section['media_refs_resolved'] : [];
    $links = is_array($section['links'] ?? null) ? $section['links'] : [];
    if ($anchor === '') {
        $anchor = industriesalon_editorial_project_section_anchor($section, $rendered_index);
    }

    $ref_html = '';
    $media_html = '';
    $links_html = industriesalon_render_editorial_project_links($links);
    $facts_html = industriesalon_render_editorial_project_facts($facts);

    foreach ($refs as $ref) {
        $ref_html .= industriesalon_render_editorial_project_archive_reference((array) $ref, $show_placeholders);
    }

    foreach ($media_refs as $ref) {
        $media_html .= industriesalon_render_editorial_project_media_reference((array) $ref, $show_placeholders);
    }

    if ($kicker === '' && $title === '' && $body === '' && $facts_html === '' && $ref_html === '' && $media_html === '' && $links_html === '') {
        return '';
    }

    $section_classes = [
        'iss-project-section',
        'iss-project-section--gesture-' . $context['gesture'],
        'iss-project-section--layout-' . $context['layout'],
        'iss-project-editorial__section',
        'iss-project-editorial__section--' . $type,
        'iss-project-editorial__section--gesture-' . $context['gesture'],
        'iss-project-editorial__section--layout-' . $context['layout'],
    ];

    ob_start();
    ?>
    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr(implode(' ', array_unique($section_classes))); ?>" data-section-gesture="<?php echo esc_attr($context['gesture']); ?>">
        <div class="iss-project-section__inner">
            <?php if ($media_html !== '') : ?>
                <div class="iss-project-section__media iss-project-editorial__media-strip"><?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media render through WordPress attachment helpers above. ?></div>
            <?php endif; ?>
            <?php if ($kicker !== '' || $title !== '' || $facts_html !== '' || $body !== '' || $links_html !== '') : ?>
                <div class="iss-project-section__copy">
                    <?php if ($kicker !== '') : ?>
                        <p class="iss-kicker iss-kicker--compact iss-project-section__kicker"><?php echo esc_html($kicker); ?></p>
                    <?php endif; ?>
                    <?php if ($title !== '') : ?>
                        <h2 class="iss-project-section__title"><?php echo esc_html($title); ?></h2>
                    <?php endif; ?>
                    <?php echo $facts_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Facts are escaped in industriesalon_render_editorial_project_facts(). ?>
                    <?php if ($body !== '') : ?>
                        <div class="iss-project-section__body iss-project-editorial__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
                    <?php endif; ?>
                    <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links are escaped in the shared helper. ?>
                </div>
            <?php endif; ?>
            <?php if ($ref_html !== '') : ?>
                <div class="iss-project-section__refs iss-project-editorial__refs"><?php echo $ref_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- References render through escaped helpers or archive-owned renderer. ?></div>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_project_content(string $content): string
{
    if (is_admin() || !is_singular('projekt') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = get_the_ID();
    if ($post_id <= 0 || !industriesalon_editorial_project_is_enabled((int) $post_id) || !function_exists('iss_editorial_get_read_model')) {
        return $content;
    }

    $prefer_autosave = is_preview() && current_user_can('edit_post', (int) $post_id);
    $document = iss_editorial_get_read_model((int) $post_id, 'projekt', $prefer_autosave);
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    if (!$sections) {
        return $content;
    }

    $skin = industriesalon_resolve_editorial_project_skin($document);
    $content_sections = industriesalon_editorial_project_sections_without_rail($sections);
    $anchors = [];
    $used_anchors = [];
    foreach ($content_sections as $index => $section) {
        if (!is_array($section)) {
            continue;
        }
        $anchor = industriesalon_editorial_project_section_anchor($section, (int) $index, $used_anchors);
        $anchors[$index] = $anchor;
        $used_anchors[] = $anchor;
    }
    $html = '';
    $rendered_index = 0;
    $show_placeholders = $prefer_autosave || current_user_can('edit_post', (int) $post_id);
    foreach ($content_sections as $section_index => $section) {
        if (is_array($section)) {
            $section_html = industriesalon_render_editorial_project_section($section, $show_placeholders, $rendered_index, $skin, (string) ($anchors[$section_index] ?? ''));
            if (trim($section_html) !== '') {
                $html .= $section_html;
                ++$rendered_index;
            }
        }
    }

    $classes = [
        'iss-project-editorial',
        'iss-project-editorial--skin-' . sanitize_html_class($skin),
    ];

    if (trim($html) === '') {
        return $content;
    }

    return '<div class="' . esc_attr(implode(' ', $classes)) . '"><div class="iss-project-editorial__main">' . $html . '</div></div>';
}
add_filter('the_content', 'industriesalon_render_editorial_project_content', 12);
