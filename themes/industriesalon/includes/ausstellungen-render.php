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

    return array_values(array_unique($classes));
});

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

function industriesalon_render_editorial_ausstellung_section(array $section, bool $show_placeholders): string
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $quote = trim((string) ($section['quote'] ?? ''));
    $attribution = trim((string) ($section['attribution'] ?? ''));
    $refs = is_array($section['object_refs_resolved'] ?? null) ? $section['object_refs_resolved'] : [];
    $media_refs = is_array($section['media_refs_resolved'] ?? null) ? $section['media_refs_resolved'] : [];
    $ref_html = '';
    $media_html = '';

    foreach ($refs as $ref) {
        $ref_html .= industriesalon_render_editorial_archive_reference((array) $ref, $show_placeholders);
    }

    foreach ($media_refs as $ref) {
        $media_html .= industriesalon_render_editorial_media_reference((array) $ref, $show_placeholders);
    }

    if ($title === '' && $body === '' && $quote === '' && $ref_html === '' && $media_html === '') {
        return '';
    }

    ob_start();
    ?>
    <section class="iss-ausstellung-editorial__section iss-ausstellung-editorial__section--<?php echo esc_attr($type); ?>">
        <div class="iss-container">
            <?php if ($title !== '') : ?>
                <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <?php if ($body !== '') : ?>
                <div class="iss-ausstellung-editorial__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
            <?php endif; ?>
            <?php if ($quote !== '') : ?>
                <blockquote class="iss-ausstellung-editorial__quote">
                    <?php echo wp_kses_post(wpautop($quote)); ?>
                    <?php if ($attribution !== '') : ?>
                        <cite><?php echo esc_html($attribution); ?></cite>
                    <?php endif; ?>
                </blockquote>
            <?php endif; ?>
            <?php if ($ref_html !== '') : ?>
                <div class="iss-ausstellung-editorial__refs"><?php echo $ref_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- References render through escaped helpers above or archive-owned renderer. ?></div>
            <?php endif; ?>
            <?php if ($media_html !== '') : ?>
                <div class="iss-ausstellung-editorial__media-strip"><?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media render through WordPress attachment helpers above. ?></div>
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

    $html = '';
    $show_placeholders = $prefer_autosave || current_user_can('edit_post', (int) $post_id);
    foreach ($sections as $section) {
        if (is_array($section)) {
            $html .= industriesalon_render_editorial_ausstellung_section($section, $show_placeholders);
        }
    }

    return trim($html) !== '' ? '<div class="iss-ausstellung-editorial">' . $html . '</div>' : $content;
}
add_filter('the_content', 'industriesalon_render_editorial_ausstellung_content', 8);
