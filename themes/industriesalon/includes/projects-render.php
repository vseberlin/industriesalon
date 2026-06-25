<?php

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_get_editorial_project_skins(): array
{
    return [
        'brief',
        'dossier',
        'field',
        'standard',
    ];
}

add_filter('iss_editorial_format_skins', function (array $skins, string $format_slug): array {
    if ($format_slug !== 'projekt') {
        return $skins;
    }

    return [
        'brief' => [
            'slug' => 'brief',
            'label' => __('Brief', 'industriesalon'),
        ],
        'dossier' => [
            'slug' => 'dossier',
            'label' => __('Dossier', 'industriesalon'),
        ],
        'field' => [
            'slug' => 'field',
            'label' => __('Field', 'industriesalon'),
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
        'upload_intake' => 'upload-intake',
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

    $caption = trim((string) ($reference['label'] ?? $resolved['title'] ?? ''));
    $image = wp_get_attachment_image($attachment_id, 'large', false, ['loading' => 'lazy']);
    if ($image !== '') {
        ob_start();
        ?>
        <figure class="iss-project-editorial__media-item iss-ausstellung-editorial__media-item">
            <?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress escapes attachment image markup. ?>
            <?php if ($caption !== '') : ?>
                <figcaption><?php echo esc_html($caption); ?></figcaption>
            <?php endif; ?>
        </figure>
        <?php

        return trim((string) ob_get_clean());
    }

    $url = (string) ($resolved['url'] ?? wp_get_attachment_url($attachment_id));
    if ($url === '') {
        return '';
    }

    $title = $caption !== '' ? $caption : (string) ($resolved['title'] ?? get_the_title($attachment_id));
    $mime = (string) get_post_mime_type($attachment_id);
    $extension = strtoupper((string) pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $meta = trim($extension . ($mime !== '' ? ' · ' . $mime : ''));

    ob_start();
    ?>
    <article class="iss-project-editorial__file-item">
        <a class="iss-project-editorial__file-link" href="<?php echo esc_url($url); ?>">
            <span class="iss-project-editorial__file-title"><?php echo esc_html($title); ?></span>
            <?php if ($meta !== '') : ?>
                <span class="iss-project-editorial__file-meta"><?php echo esc_html($meta); ?></span>
            <?php endif; ?>
        </a>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_project_gallery(string $media_html): string
{
    if (trim($media_html) === '') {
        return '';
    }

    if (function_exists('iss_relations_enqueue_related_strip_script')) {
        iss_relations_enqueue_related_strip_script();
    }

    $html = '<div class="iss-project-gallery__carousel" data-iss-strip-carousel>';
    $html .= '<div class="iss-project-editorial__media-strip iss-project-editorial__media-strip--gallery iss-project-gallery__track" data-iss-strip-carousel-track>';
    $html .= $media_html;
    $html .= '</div>';
    $html .= '<div class="iss-project-gallery__controls" aria-label="' . esc_attr__('Projektgalerie-Steuerung', 'industriesalon') . '">';
    $html .= '<button type="button" class="iss-project-gallery__control iss-project-gallery__control--prev" data-iss-strip-carousel-prev aria-label="' . esc_attr__('Vorherige Bilder', 'industriesalon') . '" disabled>';
    $html .= '<span class="iss-project-gallery__control-icon" aria-hidden="true">&#8592;</span>';
    $html .= '<span class="iss-project-gallery__control-text">' . esc_html__('Zurück', 'industriesalon') . '</span>';
    $html .= '</button>';
    $html .= '<button type="button" class="iss-project-gallery__control iss-project-gallery__control--next" data-iss-strip-carousel-next aria-label="' . esc_attr__('Nächste Bilder', 'industriesalon') . '" disabled>';
    $html .= '<span class="iss-project-gallery__control-text">' . esc_html__('Weiter', 'industriesalon') . '</span>';
    $html .= '<span class="iss-project-gallery__control-icon" aria-hidden="true">&#8594;</span>';
    $html .= '</button>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function industriesalon_editorial_project_upload_intake_url(int $post_id): string
{
    $post = $post_id > 0 ? get_post($post_id) : null;
    if (!$post instanceof WP_Post) {
        return '';
    }

    $args = ['event' => 'projekt__' . $post->post_name];
    $upload_code = trim((string) getenv('EVENT_DROP_UPLOAD_CODE'));
    if ($upload_code !== '') {
        $args['code'] = $upload_code;
    }

    $url = add_query_arg($args, home_url('/event-drop/'));

    return (string) apply_filters('industriesalon_project_upload_intake_url', $url, $post_id);
}

function industriesalon_render_editorial_project_upload_intake(array $section): string
{
    $url = industriesalon_editorial_project_upload_intake_url((int) get_the_ID());
    if ($url === '') {
        return '';
    }

    $label = trim((string) ($section['title'] ?? ''));
    if ($label === '') {
        $label = __('Material beitragen', 'industriesalon');
    }

    $note = trim((string) ($section['body'] ?? ''));
    if ($note === '') {
        $note = __('Uploads landen im Projekt-Set und werden vor der Veröffentlichung redaktionell geprüft.', 'industriesalon');
    }

    $html = '<div class="iss-project-upload-intake">';
    $html .= '<a class="iss-project-upload-intake__button" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    $html .= '<p class="iss-project-upload-intake__note">' . esc_html($note) . '</p>';
    $html .= '</div>';

    return $html;
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

function industriesalon_render_editorial_project_rail(array $rail_section, array $nav_items, string $variant = 'side'): string
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
    $variant = in_array($variant, ['horizontal', 'side'], true) ? $variant : 'side';

    ?>
    <aside class="iss-project-editorial__rail iss-project-editorial__rail--<?php echo esc_attr($variant); ?>" aria-label="<?php echo esc_attr__('Projekt navigation', 'industriesalon'); ?>">
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

function industriesalon_render_editorial_project_context_stack(int $post_id, string $variant = 'side'): string
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

    $variant = in_array($variant, ['footer', 'side'], true) ? $variant : 'side';
    $classes = [
        'iss-project-editorial__context',
        'iss-project-editorial__context--' . $variant,
    ];

    return '<div class="' . esc_attr(implode(' ', $classes)) . '" aria-label="' . esc_attr__('Projektkontext', 'industriesalon') . '">'
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
        'skin' => industriesalon_resolve_editorial_project_skin($document),
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
    $skin = sanitize_key((string) ($context['skin'] ?? 'dossier'));
    $rail_html = industriesalon_render_editorial_project_rail(
        is_array($context['rail_section'] ?? null) ? $context['rail_section'] : [],
        in_array($skin, ['brief', 'dossier'], true) ? [] : (is_array($context['nav_items'] ?? null) ? $context['nav_items'] : []),
        'side'
    );
    $context_html = $skin === 'dossier' ? '' : industriesalon_render_editorial_project_context_stack($post_id, 'side');

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

function industriesalon_render_editorial_project_dossier_spread(array $chapter, array $facts_section, bool $show_placeholders, int $rendered_index, string $chapter_anchor, string $facts_anchor = ''): string
{
    unset($show_placeholders);

    $kicker = trim((string) ($chapter['kicker'] ?? ''));
    $title = trim((string) ($chapter['title'] ?? ''));
    $body = trim((string) ($chapter['body'] ?? ''));
    $links = is_array($chapter['links'] ?? null) ? $chapter['links'] : [];
    $links_html = industriesalon_render_editorial_project_links($links);

    $facts_kicker = trim((string) ($facts_section['kicker'] ?? ''));
    $facts_title = trim((string) ($facts_section['title'] ?? ''));
    $facts_body = trim((string) ($facts_section['body'] ?? ''));
    $facts = is_array($facts_section['facts'] ?? null) ? $facts_section['facts'] : [];
    $facts_html = industriesalon_render_editorial_project_facts($facts);

    if ($chapter_anchor === '') {
        $chapter_anchor = industriesalon_editorial_project_section_anchor($chapter, $rendered_index);
    }

    $classes = [
        'iss-project-section',
        'iss-project-section--gesture-kapitel',
        'iss-project-section--layout-chapter-spread',
        'iss-project-editorial__section',
        'iss-project-editorial__section--kapitel',
        'iss-project-editorial__section--gesture-kapitel',
        'iss-project-editorial__section--layout-chapter-spread',
        'iss-project-editorial__section--skin-dossier',
        'iss-project-dossier-spread',
    ];

    ob_start();
    ?>
    <section id="<?php echo esc_attr($chapter_anchor); ?>" class="<?php echo esc_attr(implode(' ', array_unique($classes))); ?>" data-section-gesture="kapitel"<?php echo $facts_anchor !== '' ? ' data-facts-anchor="' . esc_attr($facts_anchor) . '"' : ''; ?>>
        <div class="iss-project-dossier-spread__inner">
            <div class="iss-project-dossier-spread__copy iss-project-section__copy">
                <?php if ($kicker !== '') : ?>
                    <p class="iss-kicker iss-kicker--compact iss-project-section__kicker"><?php echo esc_html($kicker); ?></p>
                <?php endif; ?>
                <?php if ($title !== '') : ?>
                    <h2 class="iss-project-section__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
                <?php if ($body !== '') : ?>
                    <div class="iss-project-section__body iss-project-editorial__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
                <?php endif; ?>
                <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links are escaped in the shared helper. ?>
            </div>
            <?php if ($facts_html !== '' || $facts_kicker !== '' || $facts_title !== '' || $facts_body !== '') : ?>
                <aside class="iss-project-dossier-spread__facts iss-project-section__fact-rail" aria-label="<?php echo esc_attr__('Projektfakten', 'industriesalon'); ?>">
                    <?php if ($facts_kicker !== '') : ?>
                        <p class="iss-kicker iss-kicker--compact iss-project-section__kicker"><?php echo esc_html($facts_kicker); ?></p>
                    <?php endif; ?>
                    <?php if ($facts_title !== '') : ?>
                        <h3 class="iss-project-dossier-spread__facts-title"><?php echo esc_html($facts_title); ?></h3>
                    <?php endif; ?>
                    <?php if ($facts_body !== '') : ?>
                        <div class="iss-project-dossier-spread__facts-body"><?php echo wp_kses_post(wpautop($facts_body)); ?></div>
                    <?php endif; ?>
                    <?php echo $facts_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Facts are escaped in industriesalon_render_editorial_project_facts(). ?>
                </aside>
            <?php endif; ?>
        </div>
    </section>
    <?php

    return trim((string) ob_get_clean());
}

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
    $upload_intake_html = $type === 'upload_intake' ? industriesalon_render_editorial_project_upload_intake($section) : '';

    foreach ($refs as $ref) {
        $ref_html .= industriesalon_render_editorial_project_archive_reference((array) $ref, $show_placeholders);
    }

    foreach ($media_refs as $ref) {
        $media_html .= industriesalon_render_editorial_project_media_reference((array) $ref, $show_placeholders);
    }

    if ($kicker === '' && $title === '' && $body === '' && $facts_html === '' && $ref_html === '' && $media_html === '' && $links_html === '' && $upload_intake_html === '') {
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
        'iss-project-editorial__section--skin-' . sanitize_html_class($skin),
    ];

    $uses_fact_rail = in_array($skin, ['dossier', 'field'], true) && $context['layout'] === 'key-points' && $facts_html !== '';

    ob_start();
    ?>
    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr(implode(' ', array_unique($section_classes))); ?>" data-section-gesture="<?php echo esc_attr($context['gesture']); ?>">
        <div class="iss-project-section__inner">
            <?php if ($media_html !== '' && $type !== 'galerie') : ?>
                <div class="iss-project-section__media iss-project-editorial__media-strip"><?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media render through WordPress attachment helpers above. ?></div>
            <?php endif; ?>
            <?php if ($kicker !== '' || $title !== '' || $facts_html !== '' || $body !== '' || $links_html !== '' || $upload_intake_html !== '') : ?>
                <div class="iss-project-section__copy">
                    <?php if ($uses_fact_rail) : ?>
                        <div class="iss-project-section__copy-main">
                            <?php if ($kicker !== '') : ?>
                                <p class="iss-kicker iss-kicker--compact iss-project-section__kicker"><?php echo esc_html($kicker); ?></p>
                            <?php endif; ?>
                            <?php if ($title !== '') : ?>
                                <h2 class="iss-project-section__title"><?php echo esc_html($title); ?></h2>
                            <?php endif; ?>
                            <?php if ($body !== '') : ?>
                                <div class="iss-project-section__body iss-project-editorial__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
                            <?php endif; ?>
                            <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links are escaped in the shared helper. ?>
                            <?php echo $upload_intake_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Upload intake markup is escaped above. ?>
                        </div>
                        <aside class="iss-project-section__fact-rail" aria-label="<?php echo esc_attr__('Projektfakten', 'industriesalon'); ?>">
                            <?php echo $facts_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Facts are escaped in industriesalon_render_editorial_project_facts(). ?>
                        </aside>
                    <?php else : ?>
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
                        <?php echo $upload_intake_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Upload intake markup is escaped above. ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($media_html !== '' && $type === 'galerie') : ?>
                <div class="iss-project-section__media iss-project-gallery"><?php echo industriesalon_render_editorial_project_gallery($media_html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Gallery media render through WordPress attachment helpers above. ?></div>
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
    $rail_context = industriesalon_get_editorial_project_rail_context((int) $post_id, $prefer_autosave);
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
    $section_count = count($content_sections);
    for ($section_index = 0; $section_index < $section_count; ++$section_index) {
        $section = $content_sections[$section_index] ?? null;
        if (is_array($section)) {
            $next_section = $content_sections[$section_index + 1] ?? null;
            $is_dossier_spread = $skin === 'dossier'
                && (string) ($section['type'] ?? '') === 'kapitel'
                && is_array($next_section)
                && (string) ($next_section['type'] ?? '') === 'massstab';

            if ($is_dossier_spread) {
                $section_html = industriesalon_render_editorial_project_dossier_spread(
                    $section,
                    $next_section,
                    $show_placeholders,
                    $rendered_index,
                    (string) ($anchors[$section_index] ?? ''),
                    (string) ($anchors[$section_index + 1] ?? '')
                );
                ++$section_index;
            } else {
                $section_html = industriesalon_render_editorial_project_section($section, $show_placeholders, $rendered_index, $skin, (string) ($anchors[$section_index] ?? ''));
            }

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

    $horizontal_rail = '';
    if ($skin === 'dossier') {
        $horizontal_rail = industriesalon_render_editorial_project_rail(
            is_array($rail_context['rail_section'] ?? null) ? $rail_context['rail_section'] : [],
            is_array($rail_context['nav_items'] ?? null) ? $rail_context['nav_items'] : [],
            'horizontal'
        );
    }
    $footer_context = $skin === 'dossier' ? industriesalon_render_editorial_project_context_stack((int) $post_id, 'footer') : '';

    return '<div class="' . esc_attr(implode(' ', $classes)) . '">'
        . $horizontal_rail
        . '<div class="iss-project-editorial__main">' . $html . '</div>'
        . $footer_context
        . '</div>';
}
add_filter('the_content', 'industriesalon_render_editorial_project_content', 12);
