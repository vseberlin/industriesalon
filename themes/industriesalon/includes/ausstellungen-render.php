<?php

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_is_retired_wf_legacy_path(): bool
{
    if (is_admin()) {
        return false;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
    $path = $request_uri !== '' ? (string) wp_parse_url($request_uri, PHP_URL_PATH) : '';
    $slug = trim($path, '/');

    if ($slug === '' || str_contains($slug, '/')) {
        return false;
    }

    return in_array($slug, [
        'kinder-in-wf',
        'menschen-im-wf',
        'roehren-und-halbleiter',
        'anlagen-automaten-arbeitsplaetze',
        'telekommunikation-sende-und-fernsehtechnik',
        'diverses-gebaeude-schaltbilder-etc',
        'geraete-einschuebe-bauteile',
    ], true);
}

add_filter('do_redirect_guess_404_permalink', function (bool $do_redirect): bool {
    // These deleted root pages must stay retired instead of being guessed into Ausstellung URLs.
    return $do_redirect && !industriesalon_is_retired_wf_legacy_path();
});

add_filter('body_class', function (array $classes): array {
    if (!is_singular(ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE)) {
        return $classes;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return $classes;
    }

    $classes[] = has_post_thumbnail($post_id) ? 'iss-ausstellung-has-thumb' : 'iss-ausstellung-no-thumb';

    $post_name = (string) get_post_field('post_name', $post_id);
    if ($post_name === 'kinder-im-werk') {
        $classes[] = 'iss-ausstellung-skin-care';
    }

    $editorial_skin = industriesalon_get_editorial_ausstellung_post_skin($post_id);
    if ($editorial_skin !== '') {
        $classes[] = 'iss-ausstellung-editorial-skin-' . sanitize_html_class($editorial_skin);
        if (function_exists('iss_content_model_editorial_canonical_skin')) {
            $canonical_skin = iss_content_model_editorial_canonical_skin($editorial_skin);
            if ($canonical_skin !== '' && $canonical_skin !== $editorial_skin) {
                $classes[] = 'iss-ausstellung-editorial-skin-' . sanitize_html_class($canonical_skin);
            }
        }
    }

    return array_values(array_unique($classes));
});

function industriesalon_get_editorial_ausstellung_skins(): array
{
    return [
        'standard',
        'frauen-im-werk',
        'kinder-im-werk',
        'quellenbuehne',
        'objektalbum',
        'typografisch',
        'chronik',
        'industrieakte',
    ];
}

add_filter('iss_editorial_format_skins', function (array $skins, string $format_slug): array {
    if ($format_slug !== 'ausstellung') {
        return $skins;
    }

    return [
        'standard' => [
            'slug' => 'standard',
            'label' => __('Standard', 'industriesalon'),
        ],
        'frauen-im-werk' => [
            'slug' => 'frauen-im-werk',
            'label' => __('Frauen im Werk', 'industriesalon'),
        ],
        'kinder-im-werk' => [
            'slug' => 'kinder-im-werk',
            'label' => __('Kinder im Werk', 'industriesalon'),
        ],
        'quellenbuehne' => [
            'slug' => 'quellenbuehne',
            'label' => __('Quellenbühne', 'industriesalon'),
        ],
        'objektalbum' => [
            'slug' => 'objektalbum',
            'label' => __('Objektalbum', 'industriesalon'),
        ],
        'typografisch' => [
            'slug' => 'typografisch',
            'label' => __('Typografisch', 'industriesalon'),
        ],
        'chronik' => [
            'slug' => 'chronik',
            'label' => __('Chronik', 'industriesalon'),
        ],
        'industrieakte' => [
            'slug' => 'industrieakte',
            'label' => __('Industrieakte', 'industriesalon'),
        ],
    ];
}, 10, 2);

function industriesalon_resolve_editorial_ausstellung_skin(array $document): string
{
    $skin = sanitize_key((string) ($document['skin'] ?? 'standard'));

    return in_array($skin, industriesalon_get_editorial_ausstellung_skins(), true) ? $skin : 'standard';
}

function industriesalon_get_editorial_ausstellung_legacy_skin(string $skin): string
{
    $skin = sanitize_key($skin);
    if ($skin === 'quellenbuehne') {
        return 'frauen-im-werk';
    }
    if ($skin === 'objektalbum') {
        return 'kinder-im-werk';
    }

    return $skin;
}

function industriesalon_get_editorial_ausstellung_post_skin(int $post_id): string
{
    if ($post_id <= 0 || !function_exists('iss_editorial_document_is_enabled') || !function_exists('iss_editorial_get_read_model')) {
        return '';
    }

    if (!iss_editorial_document_is_enabled($post_id, 'ausstellung')) {
        return '';
    }

    return industriesalon_resolve_editorial_ausstellung_skin(iss_editorial_get_read_model($post_id, 'ausstellung', false));
}

function industriesalon_render_editorial_reference_placeholder(array $reference): string
{
    $label = trim((string) ($reference['label'] ?? ''));
    if ($label === '') {
        $label = __('Nicht aufgelöste Referenz', 'industriesalon');
    }

    return '<article class="iss-ausstellung-editorial__placeholder"><strong>' . esc_html($label) . '</strong><span>' . esc_html__('Diese Referenz konnte nicht aufgelöst werden.', 'industriesalon') . '</span></article>';
}

function industriesalon_render_editorial_archive_reference(array $item, bool $show_placeholder): string
{
    $resolved = is_array($item['resolved'] ?? null) ? $item['resolved'] : [];
    $reference = is_array($item['reference'] ?? null) ? $item['reference'] : [];
    if (!$resolved) {
        return $show_placeholder ? industriesalon_render_editorial_reference_placeholder($reference) : '';
    }

    $post_id = absint($resolved['id'] ?? 0);
    if ($post_id > 0 && function_exists('iss_wf_import_render_featured_archive_object')) {
        return iss_wf_import_render_featured_archive_object($post_id);
    }

    $title = trim((string) ($resolved['title'] ?? $reference['label'] ?? ''));
    if ($title === '') {
        return '';
    }

    $url = trim((string) ($resolved['url'] ?? ''));
    $thumbnail = trim((string) ($resolved['thumbnail'] ?? ''));

    ob_start();
    ?>
    <article class="iss-ausstellung-editorial__archive-card">
        <?php if ($thumbnail !== '') : ?>
            <figure class="iss-ausstellung-editorial__archive-media"><img src="<?php echo esc_url($thumbnail); ?>" alt=""></figure>
        <?php endif; ?>
        <div class="iss-ausstellung-editorial__archive-body">
            <h3><?php echo esc_html($title); ?></h3>
            <?php if ($url !== '') : ?>
                <a href="<?php echo esc_url($url); ?>"><?php esc_html_e('Archivobjekt ansehen', 'industriesalon'); ?></a>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_media_reference(array $item, bool $show_placeholder): string
{
    $resolved = is_array($item['resolved'] ?? null) ? $item['resolved'] : [];
    $reference = is_array($item['reference'] ?? null) ? $item['reference'] : [];
    if (!$resolved) {
        return $show_placeholder ? industriesalon_render_editorial_reference_placeholder($reference) : '';
    }

    $attachment_id = absint($resolved['id'] ?? 0);
    if ($attachment_id <= 0) {
        return '';
    }

    $caption = trim((string) ($reference['label'] ?? $resolved['title'] ?? ''));
    ob_start();
    ?>
    <figure class="iss-ausstellung-editorial__media-item">
        <?php echo wp_get_attachment_image($attachment_id, 'large', false, ['loading' => 'lazy']); ?>
        <?php if ($caption !== '') : ?>
            <figcaption><?php echo esc_html($caption); ?></figcaption>
        <?php endif; ?>
    </figure>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_links(array $links): string
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

        $items[] = '<a class="iss-ausstellung-section__link" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }

    return $items ? '<nav class="iss-ausstellung-section__links iss-ausstellung-editorial__links" aria-label="' . esc_attr__('Weiterführende Links', 'industriesalon') . '">' . implode('', $items) . '</nav>' : '';
}

function industriesalon_get_editorial_ausstellung_section_context(array $section, int $rendered_index, string $skin): array
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    $layout = 'standard';
    $skin = industriesalon_get_editorial_ausstellung_legacy_skin($skin);
    unset($rendered_index);

    if ($skin !== 'standard') {
        $layouts = [
            'leitfrage' => 'thesis',
            'objektfokus' => 'object-grid',
            'quellenauszug' => 'source-focus',
            'zitat' => 'quote',
            'vollbild' => 'viewport-image',
            'massstab' => 'stat',
            'fliesstext' => 'essay',
            'bildstrecke' => 'album',
            'galerie' => 'album',
            'image_wall' => 'image-wall',
            'aside' => 'aside',
            'schluss' => 'conclusion',
            'kapitel' => 'chapter',
        ];

        if (isset($layouts[$type])) {
            $layout = $layouts[$type];
        }
        if ($type === 'galerie') {
            $gallery_layout = sanitize_key((string) ($section['gallery_layout'] ?? 'sequence'));
            if ($gallery_layout === 'wall') {
                $layout = 'image-wall';
            }
        }
        if ($type === 'quellenauszug' && sanitize_key((string) ($section['orientation'] ?? '')) === 'media-right') {
            $layout = 'source-focus-reverse';
        }
    }

    return [
        'type' => $type,
        'gesture' => $type,
        'layout' => sanitize_html_class($layout),
    ];
}

function industriesalon_locate_editorial_ausstellung_section_partial(string $skin, array $context): string
{
    $skin = sanitize_key(industriesalon_get_editorial_ausstellung_legacy_skin($skin));
    $type = sanitize_file_name((string) ($context['type'] ?? ''));
    $candidates = [];

    if ($skin !== 'standard' && $type !== '') {
        $candidates[] = 'sections/' . $skin . '/part-' . $type . '.php';
    }
    if ($type !== '') {
        $candidates[] = 'sections/part-' . $type . '.php';
    }

    foreach ($candidates as $candidate) {
        $located = locate_template($candidate, false, false);
        if (is_string($located) && $located !== '') {
            return $located;
        }
    }

    return '';
}

function industriesalon_render_editorial_ausstellung_section(array $section, bool $show_placeholders, int $rendered_index, string $skin): string
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    $context = industriesalon_get_editorial_ausstellung_section_context($section, $rendered_index, $skin);
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $quote = trim((string) ($section['quote'] ?? ''));
    $attribution = trim((string) ($section['attribution'] ?? ''));
    $refs = is_array($section['object_refs_resolved'] ?? null) ? $section['object_refs_resolved'] : [];
    $media_refs = is_array($section['media_refs_resolved'] ?? null) ? $section['media_refs_resolved'] : [];
    $links = is_array($section['links'] ?? null) ? $section['links'] : [];
    if ($context['layout'] === 'viewport-image') {
        $media_refs = array_slice($media_refs, 0, 1);
    }
    $ref_html = '';
    $media_html = '';
    $links_html = industriesalon_render_editorial_links($links);

    foreach ($refs as $ref) {
        $ref_html .= industriesalon_render_editorial_archive_reference((array) $ref, $show_placeholders);
    }

    foreach ($media_refs as $ref) {
        $media_html .= industriesalon_render_editorial_media_reference((array) $ref, $show_placeholders);
    }

    if ($kicker === '' && $title === '' && $body === '' && $quote === '' && $ref_html === '' && $media_html === '' && $links_html === '') {
        return '';
    }

    $section_classes = [
        'iss-ausstellung-section',
        'iss-ausstellung-section--gesture-' . $context['gesture'],
        'iss-ausstellung-section--layout-' . $context['layout'],
        'iss-ausstellung-editorial__section',
        'iss-ausstellung-editorial__section--' . $type,
        'iss-ausstellung-editorial__section--gesture-' . $context['gesture'],
        'iss-ausstellung-editorial__section--layout-' . $context['layout'],
    ];
    $partial = industriesalon_locate_editorial_ausstellung_section_partial($skin, $context);

    if ($partial !== '') {
        ob_start();
        include $partial;
        return trim((string) ob_get_clean());
    }

    ob_start();
    ?>
    <section class="<?php echo esc_attr(implode(' ', array_unique($section_classes))); ?>" data-section-gesture="<?php echo esc_attr($context['gesture']); ?>">
        <div class="iss-container iss-ausstellung-section__inner">
            <?php if ($media_html !== '') : ?>
                <div class="iss-ausstellung-section__media iss-ausstellung-editorial__media-strip"><?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media render through WordPress attachment helpers above. ?></div>
            <?php endif; ?>
            <?php if ($kicker !== '' || $title !== '' || $body !== '' || $quote !== '' || $links_html !== '') : ?>
                <div class="iss-ausstellung-section__copy">
                    <?php if ($kicker !== '') : ?>
                        <p class="iss-kicker iss-kicker--compact iss-ausstellung-section__kicker"><?php echo esc_html($kicker); ?></p>
                    <?php endif; ?>
                    <?php if ($title !== '') : ?>
                        <h2 class="iss-ausstellung-section__title"><?php echo esc_html($title); ?></h2>
                    <?php endif; ?>
                    <?php if ($body !== '') : ?>
                        <div class="iss-ausstellung-section__body iss-ausstellung-editorial__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
                    <?php endif; ?>
                    <?php if ($quote !== '') : ?>
                        <blockquote class="iss-ausstellung-section__quote iss-ausstellung-editorial__quote">
                            <?php echo wp_kses_post(wpautop($quote)); ?>
                            <?php if ($attribution !== '') : ?>
                                <cite><?php echo esc_html($attribution); ?></cite>
                            <?php endif; ?>
                        </blockquote>
                    <?php endif; ?>
                    <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links are escaped in industriesalon_render_editorial_links(). ?>
                </div>
            <?php endif; ?>
            <?php if ($ref_html !== '') : ?>
                <div class="iss-ausstellung-section__refs iss-ausstellung-editorial__refs"><?php echo $ref_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- References render through escaped helpers above or archive-owned renderer. ?></div>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_editorial_ausstellung_content(string $content): string
{
    if (is_admin() || !is_singular(ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = get_the_ID();
    if ($post_id <= 0 || !function_exists('iss_editorial_document_is_enabled') || !function_exists('iss_editorial_get_read_model')) {
        return $content;
    }

    if (!iss_editorial_document_is_enabled((int) $post_id, 'ausstellung')) {
        return $content;
    }

    $prefer_autosave = is_preview() && current_user_can('edit_post', (int) $post_id);
    $document = iss_editorial_get_read_model((int) $post_id, 'ausstellung', $prefer_autosave);
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    if (!$sections) {
        return $content;
    }

    $skin = industriesalon_resolve_editorial_ausstellung_skin($document);
    $html = '';
    $rendered_index = 0;
    $show_placeholders = $prefer_autosave || current_user_can('edit_post', (int) $post_id);
    foreach ($sections as $section) {
        if (is_array($section)) {
            $section_html = industriesalon_render_editorial_ausstellung_section($section, $show_placeholders, $rendered_index, $skin);
            if (trim($section_html) !== '') {
                $html .= $section_html;
                ++$rendered_index;
            }
        }
    }

    $classes = [
        'iss-ausstellung-editorial',
        'iss-ausstellung-editorial--skin-' . sanitize_html_class($skin),
    ];
    $legacy_skin = industriesalon_get_editorial_ausstellung_legacy_skin($skin);
    if ($legacy_skin !== '' && $legacy_skin !== $skin) {
        $classes[] = 'iss-ausstellung-editorial--skin-' . sanitize_html_class($legacy_skin);
    }

    return trim($html) !== '' ? '<div class="' . esc_attr(implode(' ', $classes)) . '">' . $html . '</div>' : $content;
}
add_filter('the_content', 'industriesalon_render_editorial_ausstellung_content', 12);
