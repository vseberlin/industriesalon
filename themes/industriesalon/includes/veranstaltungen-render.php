<?php
/**
 * Theme-owned structured Veranstaltung presentation helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_get_structured_veranstaltung_post_ids(): array
{
    $post_ids = (array) apply_filters(
        'industriesalon_structured_veranstaltung_post_ids',
        []
    );

    return array_values(array_unique(array_filter(array_map('absint', $post_ids))));
}

function industriesalon_should_render_structured_veranstaltung(int $post_id): bool
{
    if ($post_id <= 0 || !function_exists('iss_content_model_veranstaltung_content_document')) {
        return false;
    }

    $post_ids = industriesalon_get_structured_veranstaltung_post_ids();
    if ($post_ids && !in_array($post_id, $post_ids, true)) {
        return false;
    }

    $document = iss_content_model_veranstaltung_content_document($post_id);

    return !empty($document['sections']) && is_array($document['sections']);
}

function industriesalon_render_structured_veranstaltung_media_reference(array $reference): string
{
    if ((string) ($reference['source'] ?? '') !== 'wp-media') {
        return '';
    }

    $attachment_id = absint($reference['id'] ?? 0);
    if ($attachment_id <= 0) {
        return '';
    }

    $image = wp_get_attachment_image($attachment_id, 'large', false, ['loading' => 'lazy']);
    if ($image === '') {
        return '';
    }

    $caption = trim((string) ($reference['label'] ?? get_the_title($attachment_id)));

    $html = '<figure class="iss-event-structured__media-item">';
    $html .= $image;
    if ($caption !== '') {
        $html .= '<figcaption>' . esc_html($caption) . '</figcaption>';
    }
    $html .= '</figure>';

    return $html;
}

function industriesalon_render_structured_veranstaltung_object_reference(array $reference): string
{
    if ((string) ($reference['source'] ?? '') !== 'iss-archive' || (string) ($reference['kind'] ?? '') !== 'archive_object') {
        return '';
    }

    $post_id = absint($reference['id'] ?? 0);
    $post = $post_id > 0 ? get_post($post_id) : null;
    $title = trim((string) ($reference['label'] ?? ''));
    $url = '';
    $thumb = '';

    if ($post instanceof WP_Post && $post->post_status === 'publish') {
        $title = $title !== '' ? $title : get_the_title($post);
        $url = (string) get_permalink($post);
        if (has_post_thumbnail($post)) {
            $thumb = (string) get_the_post_thumbnail($post, 'medium', ['loading' => 'lazy']);
        }
    }

    if ($title === '') {
        return '';
    }

    $html = '<article class="iss-event-structured__object-card">';
    if ($thumb !== '' && $url !== '') {
        $html .= '<a class="iss-event-structured__object-media" href="' . esc_url($url) . '">' . $thumb . '</a>';
    } elseif ($thumb !== '') {
        $html .= '<div class="iss-event-structured__object-media">' . $thumb . '</div>';
    }
    $html .= '<div class="iss-event-structured__object-body">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Archivobjekt', 'industriesalon') . '</p>';
    $html .= '<h3 class="iss-event-structured__object-title">';
    $html .= $url !== '' ? '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>' : esc_html($title);
    $html .= '</h3>';
    $html .= '</div></article>';

    return $html;
}

function industriesalon_render_structured_veranstaltung_dynamic_reference(array $reference): string
{
    if (
        (string) ($reference['source'] ?? '') !== 'industriesalon-steuerung'
        || (string) ($reference['kind'] ?? '') !== 'control_field'
        || !class_exists('Industriesalon_Steuerung')
        || !method_exists('Industriesalon_Steuerung', 'instance')
    ) {
        return '';
    }

    $key = trim((string) ($reference['key'] ?? ''));
    if ($key === '') {
        return '';
    }

    $steuerung = Industriesalon_Steuerung::instance();
    if (!is_object($steuerung) || !method_exists($steuerung, 'get_field_value')) {
        return '';
    }

    $value = trim((string) $steuerung->get_field_value($key, ''));
    if ($value === '') {
        return '';
    }

    $label = trim((string) ($reference['label'] ?? ''));

    $html = '<p class="iss-event-structured__dynamic-ref">';
    if ($label !== '') {
        $html .= '<span class="iss-event-structured__dynamic-label">' . esc_html($label) . '</span>';
    }
    $html .= '<span class="iss-event-structured__dynamic-value">' . esc_html($value) . '</span>';
    $html .= '</p>';

    return $html;
}

function industriesalon_render_structured_veranstaltung_section(array $section): string
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $quote = trim((string) ($section['quote'] ?? ''));
    $attribution = trim((string) ($section['attribution'] ?? ''));
    $items = array_values(array_filter(array_map('trim', (array) ($section['items'] ?? []))));

    $media_html = '';
    foreach ((array) ($section['media_refs'] ?? []) as $reference) {
        if (is_array($reference)) {
            $media_html .= industriesalon_render_structured_veranstaltung_media_reference($reference);
        }
    }

    $refs_html = '';
    foreach ((array) ($section['object_refs'] ?? []) as $reference) {
        if (is_array($reference)) {
            $refs_html .= industriesalon_render_structured_veranstaltung_object_reference($reference);
        }
    }

    $dynamic_html = '';
    foreach ((array) ($section['dynamic_refs'] ?? []) as $reference) {
        if (is_array($reference)) {
            $dynamic_html .= industriesalon_render_structured_veranstaltung_dynamic_reference($reference);
        }
    }

    if ($kicker === '' && $title === '' && $body === '' && $quote === '' && !$items && $media_html === '' && $refs_html === '' && $dynamic_html === '') {
        return '';
    }

    $section_classes = [
        'iss-event-format-chapter',
        'iss-event-structured__section',
        'iss-event-structured__section--' . $type,
        'iss-event-structured__section--gesture-' . $type,
    ];

    if ($type === 'material') {
        $section_classes[] = 'iss-event-materials';
    }
    if ($type === 'programm') {
        $section_classes[] = 'iss-event-program';
    }

    ob_start();
    ?>
    <section class="<?php echo esc_attr(implode(' ', array_unique($section_classes))); ?>" data-section-gesture="<?php echo esc_attr($type); ?>">
        <?php if ($kicker !== '') : ?>
            <p class="iss-kicker iss-kicker--compact iss-event-format-kicker"><?php echo esc_html($kicker); ?></p>
        <?php endif; ?>
        <?php if ($title !== '') : ?>
            <h2 class="iss-event-format-title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($body !== '') : ?>
            <div class="iss-event-format-lede iss-event-structured__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
        <?php endif; ?>
        <?php if ($quote !== '') : ?>
            <blockquote class="iss-event-structured__quote">
                <?php echo wp_kses_post(wpautop($quote)); ?>
                <?php if ($attribution !== '') : ?>
                    <cite><?php echo esc_html($attribution); ?></cite>
                <?php endif; ?>
            </blockquote>
        <?php endif; ?>
        <?php if ($items) : ?>
            <ul class="iss-event-format-list iss-event-structured__items">
                <?php foreach ($items as $item) : ?>
                    <li><?php echo esc_html($item); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($dynamic_html !== '') : ?>
            <div class="iss-event-structured__dynamic-refs"><?php echo $dynamic_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic references are escaped in the helper. ?></div>
        <?php endif; ?>
        <?php if ($media_html !== '') : ?>
            <div class="iss-event-structured__media"><?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media references render through WordPress attachment helpers. ?></div>
        <?php endif; ?>
        <?php if ($refs_html !== '') : ?>
            <div class="iss-event-structured__refs"><?php echo $refs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Object references are escaped in the helper. ?></div>
        <?php endif; ?>
    </section>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_structured_veranstaltung_content(string $content): string
{
    if (is_admin() || !is_singular('veranstaltung') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = (int) get_the_ID();
    if (!industriesalon_should_render_structured_veranstaltung($post_id)) {
        return $content;
    }

    $document = iss_content_model_veranstaltung_content_document($post_id);
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    if (!$sections) {
        return $content;
    }

    $html = '';
    foreach ($sections as $section) {
        if (is_array($section)) {
            $html .= industriesalon_render_structured_veranstaltung_section($section);
        }
    }

    return trim($html) !== ''
        ? '<div class="iss-event-format-sheet iss-event-structured" data-structured-source="_iss_content_json"><div class="iss-event-format-content iss-event-structured__content">' . $html . '</div></div>'
        : $content;
}
add_filter('the_content', 'industriesalon_render_structured_veranstaltung_content', 12);
