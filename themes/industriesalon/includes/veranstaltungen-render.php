<?php
/**
 * Theme-owned structured Veranstaltung presentation helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Native moderated comments, presented in the event's theme composition. */
function industriesalon_render_event_feedback(int $post_id): string
{
    if (post_password_required($post_id) || get_post_status($post_id) !== 'publish') {
        return '';
    }
    $page = max(1, absint($_GET['feedback_page'] ?? 1)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
    $query = ['post_id' => $post_id, 'type' => 'iss_feedback', 'status' => 'approve', 'meta_key' => '_iss_feedback_consent', 'meta_value' => '1']; // phpcs:ignore WordPress.DB.SlowDBQuery -- Native low-volume comment consent metadata.
    $total = (int) get_comments(array_merge($query, ['count' => true]));
    $url = function_exists('iss_content_upload_url') ? iss_content_upload_url($post_id) : '';
    if (!$total && $url === '') {
        return '';
    }
    $page = min($page, max(1, (int) ceil($total / 12)));
    $comments = get_comments(array_merge($query, ['number' => 12, 'offset' => ($page - 1) * 12, 'orderby' => ['comment_date_gmt' => 'DESC', 'comment_ID' => 'DESC']]));
    $html = '<section id="stimmen" class="iss-event-feedback section"><p class="iss-kicker">Erfahrungen &amp; Erinnerungen</p><h2>' . esc_html__('Stimmen zur Veranstaltung', 'industriesalon') . '</h2>';
    if ($comments) {
        $html .= '<div class="iss-event-feedback__voices">';
        foreach ($comments as $comment) {
            $html .= '<blockquote class="iss-event-feedback__voice">' . wpautop(esc_html($comment->comment_content)) . '<cite>' . esc_html($comment->comment_author) . '</cite></blockquote>';
        }
        $html .= '</div>';
        if ($total > 12) {
            $html .= '<nav aria-label="' . esc_attr__('Weitere Stimmen', 'industriesalon') . '">' . paginate_links([
                'base' => add_query_arg('feedback_page', '%#%', get_permalink($post_id)) . '#stimmen',
                'format' => '', 'current' => $page, 'total' => (int) ceil($total / 12),
            ]) . '</nav>';
        }
    }
    if ($url !== '') {
        $html .= '<p>' . esc_html__('Was ist Ihnen in Erinnerung geblieben? Teilen Sie Ihre Eindrücke oder steuern Sie Fotos bei. Alle Beiträge werden vor der Veröffentlichung geprüft.', 'industriesalon') . '</p>';
        $html .= '<p><a class="iss-button" href="' . esc_url($url) . '">' . esc_html__('Erinnerung oder Fotos beitragen', 'industriesalon') . '</a></p>';
    }
    return $html . '</section>';
}

function industriesalon_should_render_structured_veranstaltung(int $post_id): bool
{
    if ($post_id <= 0 || !function_exists('iss_content_model_veranstaltung_content_document')) {
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

    $mime = (string) get_post_mime_type($attachment_id);
    if ($mime !== '' && strpos($mime, 'image/') !== 0) {
        return industriesalon_render_structured_veranstaltung_file_reference($reference);
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

function industriesalon_structured_veranstaltung_media_reference_is_download(array $reference): bool
{
    if ((string) ($reference['source'] ?? '') !== 'wp-media') {
        return false;
    }

    $attachment_id = absint($reference['id'] ?? 0);
    if ($attachment_id <= 0) {
        return false;
    }

    $mime = (string) get_post_mime_type($attachment_id);

    return $mime !== '' && strpos($mime, 'image/') !== 0;
}

function industriesalon_render_structured_veranstaltung_file_reference(array $reference): string
{
    if ((string) ($reference['source'] ?? '') !== 'wp-media') {
        return '';
    }

    $attachment_id = absint($reference['id'] ?? 0);
    if ($attachment_id <= 0) {
        return '';
    }

    $url = (string) wp_get_attachment_url($attachment_id);
    if ($url === '') {
        return '';
    }

    $caption = trim((string) ($reference['label'] ?? ''));
    $title = $caption !== '' ? $caption : (string) get_the_title($attachment_id);
    $mime = (string) get_post_mime_type($attachment_id);
    $extension = strtoupper((string) pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $meta = trim($extension . ($mime !== '' ? ' · ' . $mime : ''));
    $download_name = wp_basename((string) wp_parse_url($url, PHP_URL_PATH));

    $html = '<article class="iss-event-file">';
    $html .= '<a class="iss-event-file__link" href="' . esc_url($url) . '" download="' . esc_attr($download_name) . '">';
    $html .= '<span class="iss-event-file__title">' . esc_html($title) . '</span>';
    if ($meta !== '') {
        $html .= '<span class="iss-event-file__meta">' . esc_html($meta) . '</span>';
    }
    $html .= '</a>';
    $html .= '</article>';

    return $html;
}

function industriesalon_render_structured_veranstaltung_downloads(string $download_html): string
{
    if (trim($download_html) === '') {
        return '';
    }

    $html = '<div class="iss-event-downloads">';
    $html .= '<h3 class="iss-event-downloads__title">' . esc_html__('Herunterladen', 'industriesalon') . '</h3>';
    $html .= '<div class="iss-event-downloads__list">' . $download_html . '</div>';
    $html .= '</div>';

    return $html;
}

function industriesalon_render_structured_veranstaltung_gallery(string $media_html): string
{
    if (trim($media_html) === '') {
        return '';
    }

    if (function_exists('iss_relations_enqueue_related_strip_script')) {
        iss_relations_enqueue_related_strip_script();
    }

    $html = '<div class="iss-event-gallery__carousel" data-iss-strip-carousel>';
    $html .= '<div class="iss-event-structured__media iss-event-structured__media--gallery iss-event-gallery__track" data-iss-strip-carousel-track>';
    $html .= $media_html;
    $html .= '</div>';
    $html .= '<div class="iss-event-gallery__controls" aria-label="' . esc_attr__('Galerie-Steuerung', 'industriesalon') . '">';
    $html .= '<button type="button" class="iss-event-gallery__control iss-event-gallery__control--prev" data-iss-strip-carousel-prev aria-label="' . esc_attr__('Vorherige Bilder', 'industriesalon') . '" disabled>';
    $html .= '<span class="iss-event-gallery__control-icon" aria-hidden="true">&#8592;</span>';
    $html .= '<span class="iss-event-gallery__control-text">' . esc_html__('Zurück', 'industriesalon') . '</span>';
    $html .= '</button>';
    $html .= '<button type="button" class="iss-event-gallery__control iss-event-gallery__control--next" data-iss-strip-carousel-next aria-label="' . esc_attr__('Nächste Bilder', 'industriesalon') . '" disabled>';
    $html .= '<span class="iss-event-gallery__control-text">' . esc_html__('Weiter', 'industriesalon') . '</span>';
    $html .= '<span class="iss-event-gallery__control-icon" aria-hidden="true">&#8594;</span>';
    $html .= '</button>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function industriesalon_structured_veranstaltung_skin(array $document): string
{
    $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
        ? iss_content_model_sanitize_veranstaltung_entity_key((string) ($document['entity_key'] ?? ''))
        : sanitize_key((string) ($document['entity_key'] ?? ''));

    if ($entity_key === '' || !function_exists('iss_content_model_veranstaltung_entity_default_skin')) {
        return '';
    }

    return sanitize_html_class(iss_content_model_veranstaltung_entity_default_skin($entity_key));
}

function industriesalon_structured_veranstaltung_section_uses_flow_media(array $section, string $skin): bool
{
    if ($skin !== 'typografisch') {
        return false;
    }

    $type = sanitize_key((string) ($section['type'] ?? ''));

    return in_array($type, ['intro', 'kapitel'], true);
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

function industriesalon_structured_veranstaltung_upload_intake_url(int $post_id): string
{
    if (!function_exists('iss_content_upload_url') || !iss_content_upload_is_open($post_id)) {
        return '';
    }
    return (string) apply_filters('industriesalon_event_upload_intake_url', iss_content_upload_url($post_id), $post_id);
}

function industriesalon_render_structured_veranstaltung_upload_intake(array $section): string
{
    $post_id = (int) get_the_ID();
    $url = industriesalon_structured_veranstaltung_upload_intake_url($post_id);
    if ($url === '') {
        return '';
    }

    $items = array_values(array_filter(array_map('trim', (array) ($section['items'] ?? []))));
    $label = $items[0] ?? __('Material hochladen', 'industriesalon');
    $note = $items[1] ?? __('Uploads werden vor der Veröffentlichung redaktionell geprüft.', 'industriesalon');

    $html = '<div class="iss-upload-intake iss-event-upload-intake">';
    $html .= '<a class="iss-upload-intake__button iss-event-upload-intake__button" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    if ($note !== '') {
        $html .= '<p class="iss-upload-intake__note iss-event-upload-intake__note">' . esc_html($note) . '</p>';
    }
    $html .= '</div>';

    return $html;
}

function industriesalon_render_structured_veranstaltung_section(array $section, string $skin = ''): string
{
    $type = sanitize_html_class((string) ($section['type'] ?? 'kapitel'));
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $title = trim((string) ($section['title'] ?? ''));
    $body = trim((string) ($section['body'] ?? ''));
    $quote = trim((string) ($section['quote'] ?? ''));
    $attribution = trim((string) ($section['attribution'] ?? ''));
    $items = array_values(array_filter(array_map('trim', (array) ($section['items'] ?? []))));
    $upload_intake_html = '';
    if ($type === 'upload_intake') {
        $upload_intake_html = industriesalon_render_structured_veranstaltung_upload_intake($section);
        if ($upload_intake_html === '') {
            return '';
        }
        $items = [];
    }

    $media_html = '';
    $download_html = '';
    foreach ((array) ($section['media_refs'] ?? []) as $reference) {
        if (is_array($reference)) {
            if ($type === 'material') {
                if (industriesalon_structured_veranstaltung_media_reference_is_download($reference)) {
                    $download_html .= industriesalon_render_structured_veranstaltung_file_reference($reference);
                }
                continue;
            }
            $media_html .= industriesalon_render_structured_veranstaltung_media_reference($reference);
        }
    }
    $downloads_html = $type === 'material' ? industriesalon_render_structured_veranstaltung_downloads($download_html) : '';
    $links_html = industriesalon_render_editorial_links((array) ($section['links'] ?? []));

    $refs_html = '';
    if ($type !== 'material') {
        foreach ((array) ($section['object_refs'] ?? []) as $reference) {
            if (is_array($reference)) {
                $refs_html .= industriesalon_render_structured_veranstaltung_object_reference($reference);
            }
        }
    }

    $dynamic_html = '';
    foreach ((array) ($section['dynamic_refs'] ?? []) as $reference) {
        if (is_array($reference)) {
            $dynamic_html .= industriesalon_render_structured_veranstaltung_dynamic_reference($reference);
        }
    }

    if ($kicker === '' && $title === '' && $body === '' && $quote === '' && !$items && $media_html === '' && $downloads_html === '' && $links_html === '' && $refs_html === '' && $dynamic_html === '' && $upload_intake_html === '') {
        return '';
    }

    $uses_flow_media = $body !== ''
        && $media_html !== ''
        && industriesalon_structured_veranstaltung_section_uses_flow_media($section, $skin);

    $section_classes = [
        'iss-event-structured__section',
        'iss-event-structured__section--' . $type,
        'iss-event-structured__section--gesture-' . $type,
    ];
    if ($uses_flow_media) {
        $section_classes[] = 'iss-event-structured__section--flow-media';
    }

    if ($type === 'material') {
        $section_classes[] = 'iss-event-materials';
    }
    if ($type === 'programm') {
        $section_classes[] = 'iss-event-program';
    }
    if ($type === 'galerie') {
        $section_classes[] = 'iss-event-gallery';
    }
    if ($type === 'upload_intake') {
        $section_classes[] = 'iss-event-upload';
    }

    ob_start();
    ?>
    <section class="<?php echo esc_attr(implode(' ', array_unique($section_classes))); ?>" data-section-gesture="<?php echo esc_attr($type); ?>">
        <?php if ($kicker !== '') : ?>
            <p class="iss-kicker iss-kicker--compact iss-event-structured__kicker"><?php echo esc_html($kicker); ?></p>
        <?php endif; ?>
        <?php if ($title !== '') : ?>
            <h2 class="iss-event-structured__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($uses_flow_media) : ?>
            <div class="iss-event-structured__flow">
                <div class="iss-event-structured__media iss-event-structured__media--flow"><?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media references render through WordPress attachment helpers. ?></div>
                <div class="iss-event-structured__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
            </div>
        <?php elseif ($body !== '') : ?>
            <div class="iss-event-structured__body"><?php echo wp_kses_post(wpautop($body)); ?></div>
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
            <ul class="iss-event-structured__items">
                <?php foreach ($items as $item) : ?>
                    <li><?php echo esc_html($item); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($dynamic_html !== '') : ?>
            <div class="iss-event-structured__dynamic-refs"><?php echo $dynamic_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic references are escaped in the helper. ?></div>
        <?php endif; ?>
        <?php if ($upload_intake_html !== '') : ?>
            <?php echo $upload_intake_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Upload intake CTA is escaped in the helper. ?>
        <?php endif; ?>
        <?php if ($media_html !== '' && !$uses_flow_media && $type === 'galerie') : ?>
            <?php echo industriesalon_render_structured_veranstaltung_gallery($media_html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Gallery media renders through WordPress attachment helpers. ?>
        <?php elseif ($media_html !== '' && !$uses_flow_media) : ?>
            <div class="iss-event-structured__media"><?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media references render through WordPress attachment helpers. ?></div>
        <?php endif; ?>
        <?php if ($downloads_html !== '') : ?>
            <div class="iss-event-structured__downloads"><?php echo $downloads_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Download cards are escaped in helper functions above. ?></div>
        <?php endif; ?>
        <?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links are escaped by the shared industriesalon_render_editorial_links() helper. ?>
        <?php if ($refs_html !== '') : ?>
            <div class="iss-event-structured__refs"><?php echo $refs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Object references are escaped in the helper. ?></div>
        <?php endif; ?>
    </section>
    <?php
    return trim((string) ob_get_clean());
}

function industriesalon_render_structured_veranstaltung_content(string $content): string
{
    if (is_admin() || doing_filter('get_the_excerpt') || !is_singular('veranstaltung') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = (int) get_the_ID();
    if (!industriesalon_should_render_structured_veranstaltung($post_id)) {
        return $content;
    }

    $document = function_exists('iss_editorial_get_read_model') ? iss_editorial_get_read_model($post_id, 'veranstaltung') : iss_content_model_veranstaltung_content_document($post_id);
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    if (!$sections) {
        return $content;
    }

    $skin = industriesalon_structured_veranstaltung_skin($document);
    $state = iss_content_model_event_appointment($post_id);
    $status = $state['row']['availability_state'] ?? get_post_meta($post_id, 'iss_event_status', true);
    $closed = !empty($state['past']) || in_array($status, ['cancelled', 'sold_out'], true);
    $html = '';
    foreach ($sections as $section) {
        if (is_array($section)) {
            if ($closed && ($section['type'] ?? '') === 'material') {
                $links = (array) ($section['links'] ?? []);
                $section['links'] = array_values(array_filter($links, static fn(array $link): bool => !industriesalon_event_link_is_ticket($link)));
                if ($links && !$section['links'] && empty($section['body']) && empty($section['media_refs']) && empty($section['object_refs']) && empty($section['dynamic_refs'])) {
                    continue;
                }
            }
            $html .= industriesalon_editorial_preview_section(industriesalon_render_structured_veranstaltung_section($section, $skin), $section, ['title' => 'iss-event-structured__title', 'kicker' => 'iss-event-structured__kicker', 'body' => 'iss-event-structured__body']);
        }
    }

    return trim($html) !== ''
        ? '<div class="iss-event-structured" data-structured-source="_iss_content_json"><div class="iss-event-structured__content">' . $html . '</div></div>'
        : ($closed ? '' : $content);
}
add_filter('the_content', 'industriesalon_render_structured_veranstaltung_content', 12);

/** Date ranges are inclusive, not opening hours. */
function industriesalon_programme_date(array $row, string $now): string
{
    $start = (string) ($row['start_raw'] ?? '');
    $end = (string) ($row['end_raw'] ?? '');
    $is_range = $end !== '' && substr($start, 0, 10) !== substr($end, 0, 10);
    if (($is_range || ($row['source_post_type'] ?? '') === 'ausstellung') && $end !== '' && $start <= $now && $end >= $now) {
        return sprintf(__('Noch bis %s', 'industriesalon'), iss_programm_format_date_long_de((new DateTimeImmutable($end, wp_timezone()))->getTimestamp(), wp_timezone()));
    }
    if (!empty($row['is_open_ended']) && $start <= $now) {
        return __('Laufend zu erleben', 'industriesalon');
    }
    if (($row['source_post_type'] ?? '') === 'ausstellung') {
        return sprintf(__('Ab %s', 'industriesalon'), (string) ($row['date_label'] ?? ''));
    }
    $day = substr($start, 0, 4) === substr($now, 0, 4) ? ($row['day_label'] ?? $row['date_label'] ?? '') : ($row['date_label'] ?? '');
    if (substr($start, 0, 10) === substr($now, 0, 10)) {
        $day = __('Heute', 'industriesalon');
    }
    return trim((string) $day . ' · ' . (string) ($row['time_label'] ?? ''), ' ·');
}

/** Match the existing editorial ticket convention consistently on both surfaces. */
function industriesalon_event_link_is_ticket(array $link): bool
{
    return !empty($link['url']) && (bool) preg_match('/\bTickets?\b/iu', (string) ($link['label'] ?? ''));
}

/** One external-ticket source for programme cards and the event opening. */
function industriesalon_event_ticket_action(int $id, array $row): array
{
    if (in_array($row['availability_state'] ?? '', ['cancelled', 'sold_out'], true)) {
        return [];
    }
    $document = function_exists('iss_editorial_get_document') ? iss_editorial_get_document($id, get_post_type($id)) : [];
    foreach (($document['sections'] ?? []) as $section) {
        if (($section['type'] ?? '') !== 'material') {
            continue;
        }
        foreach (($section['links'] ?? []) as $link) {
            if (industriesalon_event_link_is_ticket($link)) {
                return ['url' => $link['url'], 'label' => __('Tickets', 'industriesalon')];
            }
        }
    }
    if (!empty($row['booking_url'])) {
        return ['url' => $row['booking_url'], 'label' => __('Buchen', 'industriesalon')];
    }
    return [];
}

/** Shared card anatomy, with feature and dated-row composition owned by the theme. */
function industriesalon_programme_entry(array $row, string $variant, string $now): string
{
    $id = (int) ($row['source_post_id'] ?? 0);
    $url = get_permalink($id);
    $title = get_the_title($id);
    $excerpt = trim((string) get_post_field('post_excerpt', $id, 'raw'));
    $location = trim((string) ($row['location_label'] ?? ''));
    $action = industriesalon_event_ticket_action($id, $row);
    $label = $action['label'] ?? __('Details', 'industriesalon');
    $action_url = $action['url'] ?? $url;
    $date = industriesalon_programme_date($row, $now);
    $date_value = !empty($row['end_raw']) && ($row['start_raw'] ?? '') <= $now && (($row['source_post_type'] ?? '') === 'ausstellung' || substr($row['start_raw'], 0, 10) !== substr($row['end_raw'], 0, 10)) ? $row['end_raw'] : ($row['start_raw'] ?? '');
    $date_html = '<time datetime="' . esc_attr(str_replace(' ', 'T', $date_value)) . '" title="' . esc_attr((string) ($row['datetime_label'] ?? $row['start_raw'] ?? '')) . '">' . esc_html($date) . '</time>';
    if (substr((string) ($row['start_raw'] ?? ''), 0, 10) === substr($now, 0, 10)) {
        $date_html .= '<span class="screen-reader-text"> (' . esc_html((string) ($row['date_label'] ?? $row['start_raw'])) . ')</span>';
    }
    $heading = $variant === 'feature' ? 'h2' : 'h3';
    $image = get_the_post_thumbnail($id, $variant === 'feature' ? 'full' : 'large', $variant === 'feature'
        ? ['loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '(max-width: 781px) 100vw, 55vw']
        : ['loading' => 'lazy']);
    $html = '<article class="iss-card iss-card--flat iss-card--programme iss-card--programme-' . esc_attr($variant) . ($image === '' ? ' iss-card--programme-no-image' : '') . '">';
    if ($variant === 'row') {
        $html .= '<p class="iss-card__date">' . $date_html . '</p>';
    }
    if ($image !== '') {
        $html .= '<figure class="iss-card__media">' . $image . '</figure>';
    }
    $html .= '<div class="iss-card__body">';
    if ($variant === 'feature') {
        $labels = ['next' => __('Als Nächstes', 'industriesalon'), 'focus' => __('Im Fokus', 'industriesalon'), 'current' => __('Jetzt zu erleben', 'industriesalon')];
        $html .= '<p class="iss-card__kicker">' . esc_html($labels[$row['feature_label'] ?? 'next']) . '</p>';
    }
    if ($variant !== 'row') {
        $html .= '<p class="iss-card__date">' . $date_html . '</p>';
    }
    $html .= '<' . $heading . ' class="iss-card__title"><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></' . $heading . '>';
    $notice = industriesalon_programme_status($row, $now);
    if ($notice !== '') {
        $html .= '<p class="iss-card__meta"><strong>' . esc_html($notice) . '</strong></p>';
    }
    if ($excerpt !== '') {
        $html .= '<p class="iss-card__text">' . esc_html(wp_strip_all_tags($excerpt)) . '</p>';
    }
    if ($location !== '') {
        $html .= '<p class="iss-card__meta">' . esc_html($location) . '</p>';
    }
    $html .= '<p class="iss-card__footer"><a class="' . ($variant === 'feature' ? 'iss-button' : 'iss-action-link') . '" href="' . esc_url($action_url) . '">' . esc_html($label) . '<span class="screen-reader-text">: ' . esc_html($title) . '</span></a></p>';
    return $html . '</div></article>';
}

/** Compose the existing programme block; no second calendar or content store. */
function industriesalon_programme_overview($html, array $items, array $attributes)
{
    if (($attributes['presentation'] ?? '') !== 'programme') {
        return $html;
    }
    $now = iss_occurrences_query_now();
    foreach ($items as &$item) {
        $item['focus_until'] = iss_programm_focus_until((int) ($item['source_post_id'] ?? 0));
    }
    unset($item);
    $sections = iss_programm_sections($items, $now);
    $html = '<div class="iss-programme-overview">' . industriesalon_programme_preview_form($now);
    $featured = iss_programm_take_feature($sections, $now);
    if ($featured) {
        $html .= industriesalon_programme_entry($featured, 'feature', $now);
    } else {
        $html .= '<p>' . esc_html__('Neue Veranstaltungstermine werden hier angekündigt.', 'industriesalon') . '</p>';
    }
    $html .= '<section class="iss-programme-overview__section"><div class="iss-programme-overview__heading"><h2>' . esc_html__('Weitere Termine', 'industriesalon') . '</h2><a class="iss-action-link" href="' . esc_url(home_url('/kalender/')) . '">' . esc_html__('Gesamter Kalender', 'industriesalon') . '</a></div>';
    foreach ($sections['dates'] as $row) {
        $html .= industriesalon_programme_entry($row, 'row', $now);
    }
    if (!$sections['dates']) {
        $html .= '<p>' . esc_html__('Weitere Termine werden angekündigt.', 'industriesalon') . '</p>';
    }
    $html .= '</section>';
    foreach ($sections['running'] as $row) {
        $html .= '<aside class="iss-programme-overview__notice"><p><strong>' . esc_html($row['title']) . '</strong> · ' . esc_html(industriesalon_programme_date($row, $now)) . ' ' . esc_html(industriesalon_programme_status($row, $now)) . '</p><a class="iss-action-link" href="' . esc_url(get_permalink($row['source_post_id'])) . '">' . esc_html__('Programm entdecken', 'industriesalon') . '</a></aside>';
    }
    foreach (['exhibitions' => __('Ausstellungen und Installationen', 'industriesalon'), 'later_exhibitions' => __('Demnächst zu sehen', 'industriesalon')] as $key => $title) {
        if (!$sections[$key]) {
            continue;
        }
        $html .= '<section class="iss-programme-overview__section"><h2>' . esc_html($title) . '</h2><div class="iss-card-grid iss-programme-overview__exhibitions">';
        foreach ($sections[$key] as $row) {
            $html .= industriesalon_programme_entry($row, 'exhibition', $now);
        }
        $html .= '</div></section>';
    }
    // Retrospectives are published editorial reports, never relabelled expired appointments.
    $reports = new WP_Query(['post_type' => 'rueckblick', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 12, 'no_found_rows' => true,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small editorial report CPT; only illustrated reports are eligible.
        'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']]]);
    $cards = [];
    if (function_exists('iss_content_report_connections') && function_exists('iss_relations_render_related_content_card')) {
        foreach ($reports->posts as $post) {
            foreach (iss_content_report_connections($post->ID, true) as $source) {
                if (in_array($source->post_type, ['veranstaltung', 'ausstellung'], true)) {
                    $cards[] = iss_relations_render_related_content_card($post, ['link_text' => __('Rückblick lesen', 'industriesalon')], []);
                    break;
                }
            }
            if (count($cards) === 3) {
                break;
            }
        }
    }
    if ($cards) {
        $html .= '<section class="iss-programme-overview__section"><h2>' . esc_html__('Rückblicke', 'industriesalon') . '</h2>'
            . iss_relations_render_cards_grid($cards, 'rueckblick', ['columns' => 3]) . '</section>';
    }
    return $html . '</div>';
}
add_filter('iss_programm_cards_presentation', 'industriesalon_programme_overview', 10, 3);

/** Public notices also appear on the event itself, including direct/search visits. */
function industriesalon_programme_status(array $row, string $now): string
{
    $status = (string) ($row['availability_state'] ?? '');
    if ($status === 'cancelled') {
        return __('Abgesagt', 'industriesalon');
    }
    if ($status === 'sold_out') {
        return __('Ausgebucht', 'industriesalon');
    }
    if (!empty($row['end_raw']) && $row['start_raw'] <= $now && $row['end_raw'] >= $now && ($row['source_post_type'] ?? '') === 'veranstaltung' && substr($row['start_raw'], 0, 10) === substr($row['end_raw'], 0, 10)) {
        return __('Findet gerade statt', 'industriesalon');
    }
    return '';
}

add_filter('the_content', static function (string $content): string {
    if (!is_singular('veranstaltung') || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    $notice = industriesalon_programme_status(['availability_state' => get_post_meta(get_the_ID(), 'iss_event_status', true)], iss_occurrences_query_now());
    return $notice !== '' ? '<p class="iss-event-status"><strong>' . esc_html($notice) . '</strong></p>' . $content : $content;
}, 13);

/** Native GET form: editor-only, nonce-protected, never cached or persisted. */
function industriesalon_programme_preview_form(string $now): string
{
    if (!current_user_can('edit_others_posts')) {
        return '';
    }
    $preview = iss_programm_preview_datetime() !== '';
    $html = '<details class="iss-programme-overview__notice"' . ($preview ? ' open' : '') . '><summary>' . esc_html__('Datumsvorschau für die Redaktion', 'industriesalon') . '</summary>';
    $html .= '<form method="get" action="' . esc_url(home_url('/veranstaltungen/')) . '"><p><label>' . esc_html__('Seite zum Datum ansehen (Berlin)', 'industriesalon') . ' <input type="datetime-local" name="programme_at" required value="' . esc_attr(str_replace(' ', 'T', substr($now, 0, 16))) . '"></label></p>';
    $html .= wp_nonce_field('iss_programme_preview', '_wpnonce', false, false);
    $html .= '<p><button type="submit">' . esc_html__('Vorschau anzeigen', 'industriesalon') . '</button> <a href="' . esc_url(home_url('/veranstaltungen/')) . '">' . esc_html__('Zur aktuellen Ansicht', 'industriesalon') . '</a></p></form>';
    if ($preview) {
        $html .= '<p><strong>' . esc_html(sprintf(__('Vorschau: %s (Berlin). Keine Termine werden geändert. Vergangene Termine zeigen hier nur die erste Seite.', 'industriesalon'), $now)) . '</strong></p>';
    }
    return $html . '</details>';
}

/** Let the native image block own its sizing attributes for both editor and frontend. */
add_filter('render_block_data', static function (array $block): array {
    if (is_singular('veranstaltung') && ($block['blockName'] ?? '') === 'core/post-featured-image' && ($block['attrs']['className'] ?? '') === 'iss-event-hero__figure') {
        $document = iss_content_model_veranstaltung_content_document((int) get_queried_object_id());
        $block['attrs']['scale'] = ($document['hero_image_fit'] ?? '') === 'cover' ? 'cover' : 'contain';
    }
    return $block;
});

/** Optional opening copy uses native blocks; the saved post identity remains intact. */
add_filter('render_block_core/post-title', static function (string $html, array $block, WP_Block $instance): string {
    $id = (int) ($instance->context['postId'] ?? 0);
    if (!is_singular('veranstaltung') || $id !== (int) get_queried_object_id() || !str_contains($block['attrs']['className'] ?? '', 'iss-event-hero__title') || post_password_required($id)) {
        return $html;
    }
    $document = iss_content_model_veranstaltung_content_document($id);
    $title = trim((string) ($document['hero_title'] ?? ''));
    $subtitle = trim((string) ($document['hero_subtitle'] ?? ''));
    if ($title !== '') {
        $html = '<h1 class="wp-block-post-title iss-event-hero__title">' . esc_html($title) . '</h1>';
    }
    return $html . ($subtitle !== '' ? '<p class="iss-event-hero__subtitle">' . esc_html($subtitle) . '</p>' : '');
}, 10, 3);

add_filter('render_block_core/paragraph', static function (string $html, array $block): string {
    if (!is_singular('veranstaltung') || ($block['attrs']['className'] ?? '') !== 'iss-event-hero__kicker iss-kicker' || post_password_required()) {
        return $html;
    }
    $document = iss_content_model_veranstaltung_content_document((int) get_queried_object_id());
    return '<p class="iss-event-hero__kicker iss-kicker">' . esc_html(($document['hero_kicker'] ?? '') ?: __('Veranstaltung', 'industriesalon')) . '</p>';
}, 10, 2);

add_filter('render_block_core/post-featured-image', static function (string $html, array $block, WP_Block $instance): string {
    $id = (int) ($instance->context['postId'] ?? 0);
    if (!is_singular('veranstaltung') || $id !== (int) get_queried_object_id() || ($block['attrs']['className'] ?? '') !== 'iss-event-hero__figure') {
        return $html;
    }
    $document = iss_content_model_veranstaltung_content_document($id);
    if (($document['hero_image_fit'] ?? '') === 'cover') {
        $tags = new WP_HTML_Tag_Processor($html);
        if ($tags->next_tag('FIGURE')) {
            $tags->add_class('iss-event-hero__figure--photo');
        }
        $html = $tags->get_updated_html();
    }
    $caption = wp_get_attachment_caption(get_post_thumbnail_id($id));
    if ($caption) {
        $html .= '<p class="iss-event-hero__caption">' . wp_kses_post($caption) . '</p>';
    }
    return $html;
}, 20, 3);

/** Theme composition of the existing metadata block, with the Atlas as address authority. */
add_filter('iss_content_meta_presentation', static function ($html, int $id, array $rows, array $attributes) {
    $presentation = $attributes['presentation'] ?? '';
    if (get_post_type($id) !== 'veranstaltung' || !in_array($presentation, ['event-summary', 'event-visit'], true)) {
        return $html;
    }
    if (post_password_required($id)) {
        return '';
    }
    $state = iss_content_model_event_appointment($id);
    $past = !empty($state['past']);
    $row = $state['row'] ?? [];
    $status = $row['availability_state'] ?? get_post_meta($id, 'iss_event_status', true);
    $row['availability_state'] = $status;
    $summary = $presentation === 'event-summary';
    $out = $summary ? '<div class="iss-event-summary">' : '<aside id="besuch" class="iss-event-visit' . ($past ? ' iss-event-visit--past' : '') . '"><h2>' . esc_html($past ? __('Die Veranstaltung', 'industriesalon') : __('Ihr Besuch', 'industriesalon')) . '</h2>';
    $out .= '<dl class="iss-event-facts">';
    foreach ($rows as $fact) {
        if ($summary && !in_array($fact['label'], ['Termin', 'Vergangener Termin', 'Beginn', 'Ende', 'Ort', 'Status'], true)) {
            continue;
        }
        $out .= '<div><dt>' . esc_html($fact['label']) . '</dt><dd>' . (!empty($fact['html']) ? wp_kses_post($fact['value']) : esc_html($fact['value'])) . '</dd></div>';
    }
    $out .= '</dl>';
    if ($summary) {
        $action = !$past ? industriesalon_event_ticket_action($id, $row) : [];
        if ($past) {
            $url = iss_content_upload_url($id);
            $action = $url !== '' ? ['url' => $url, 'label' => __('Erinnerung beitragen', 'industriesalon')] : [];
        } elseif (!$action) {
            $action = ['url' => '#besuch', 'label' => in_array($status, ['cancelled', 'sold_out'], true) ? __('Veranstaltungsdetails', 'industriesalon') : __('Besuch planen', 'industriesalon')];
        }
        $out .= '<div class="iss-event-actions">';
        if ($action) {
            $out .= '<a class="iss-button iss-button--filled" href="' . esc_url($action['url']) . '">' . esc_html($action['label']) . '</a>';
        }
        if (iss_content_report_connections($id, true)) {
            $out .= '<a class="iss-action-link" href="#rueckblicke">' . esc_html__('Zum Rückblick', 'industriesalon') . '</a>';
        }
        $out .= '</div>';
    } else {
        $place_id = iss_content_model_get_veranstaltung_primary_place_id($id, true);
        // A thematic relation is not proof of the event's venue.
        if ($place_id && get_post_status($place_id) === 'publish' && !post_password_required($place_id)) {
            $address = trim((string) get_post_meta($place_id, 'address', true));
            if ($address !== '') {
                $out .= '<p>' . esc_html($address) . '</p><p><a class="iss-action-link" href="' . esc_url('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address)) . '">' . esc_html__('Anfahrt ansehen', 'industriesalon') . '</a></p>';
            }
            $out .= '<p><a href="' . esc_url(get_permalink($place_id)) . '">' . esc_html__('Mehr zum Veranstaltungsort', 'industriesalon') . '</a></p>';
        }
        if (!$past && !in_array($status, ['cancelled', 'sold_out'], true) && get_post_meta($id, 'iss_booking_enabled', true)) {
            $out .= '<p><a class="iss-action-link" href="' . esc_url(home_url('/kalender/')) . '">' . esc_html__('Termine und Buchung im Kalender', 'industriesalon') . '</a></p>';
        }
        if ($past) {
            $out .= '<p>' . esc_html__('Diese Veranstaltung ist vergangen.', 'industriesalon') . '</p>';
        }
    }
    return $out . ($summary ? '</div>' : '</aside>');
}, 10, 4);

/** Continue through the same programme query and shared cards, once per event. */
add_filter('iss_programm_cards_presentation', static function ($html, array $items, array $attributes) {
    if (($attributes['presentation'] ?? '') !== 'event-next') {
        return $html;
    }
    $cards = '';
    $seen = [(int) get_queried_object_id()];
    foreach ($items as $row) {
        $id = (int) ($row['source_post_id'] ?? 0);
        if (!$id || in_array($id, $seen, true) || ($row['availability_state'] ?? '') === 'cancelled') {
            continue;
        }
        $seen[] = $id;
        $cards .= industriesalon_programme_entry($row, 'exhibition', iss_occurrences_query_now());
        if (count($seen) === 4) {
            break;
        }
    }
    if ($cards === '') {
        return '';
    }
    return '<section class="iss-event-next section"><div class="iss-container"><div class="iss-event-next__heading"><h2>' . esc_html__('Demnächst im Industriesalon', 'industriesalon') . '</h2><a class="iss-action-link" href="' . esc_url(home_url('/veranstaltungen/')) . '">' . esc_html__('Alle Veranstaltungen', 'industriesalon') . '</a></div><div class="iss-card-grid">' . $cards . '</div></div></section>';
}, 20, 3);
