<?php
/** Shared theme contributions to the editorial registry and authenticated previews. */
if (!defined('ABSPATH')) {
    exit;
}

add_filter('iss_editorial_formats', static function (array $formats): array {
    $skins = [
        'landing' => ['standard', 'typografisch', 'frontpage', 'dossier', 'territorial'],
        'article' => ['standard', 'typografisch', 'dossier'],
        'ausstellung' => ['standard', 'quellenbuehne', 'objektalbum', 'typografisch', 'chronik'],
        'rueckblick' => ['standard', 'quellenbuehne', 'objektalbum', 'typografisch', 'chronik'],
        'projekt' => ['dossier', 'standard', 'typografisch'],
        'fuehrung' => ['route-dossier', 'compact', 'standard'],
        'publication' => ['standard', 'bildmatrix', 'longread-poster'],
        'place' => ['standard', 'ortsdossier'],
        'veranstaltung' => ['standard', 'typografisch'],
    ];
    $labels = [
        'standard' => __('Standard', 'industriesalon'),
        'typografisch' => __('Typografisch', 'industriesalon'),
        'frontpage' => __('Startseite', 'industriesalon'),
        'dossier' => __('Dossier', 'industriesalon'),
        'territorial' => __('Territorial', 'industriesalon'),
        'quellenbuehne' => __('Quellenbühne', 'industriesalon'),
        'objektalbum' => __('Objektalbum', 'industriesalon'),
        'chronik' => __('Chronik', 'industriesalon'),
        'route-dossier' => __('Routendossier', 'industriesalon'),
        'compact' => __('Kompakt', 'industriesalon'),
        'bildmatrix' => __('Bildmatrix', 'industriesalon'),
        'longread-poster' => __('Longread Poster', 'industriesalon'),
        'ortsdossier' => __('Ortsdossier', 'industriesalon'),
    ];
    foreach ($skins as $slug => $choices) {
        if (!isset($formats[$slug])) { continue; }
        $formats[$slug]['skins'] = array_intersect_key($labels, array_flip($choices));
        // Maintain each renderer's existing choice order.
        $formats[$slug]['skins'] = array_replace(array_fill_keys($choices, ''), $formats[$slug]['skins']);
        $formats[$slug]['editor']['preview'] = true;
        $formats[$slug]['editor']['canvas'] = true;
        foreach ($formats[$slug]['sections'] as &$section) {
            if (!empty($section['portable'])) { $section['renderer'] = 'landing'; }
        }
        unset($section);
        if (in_array($slug, ['landing', 'article'], true)) {
            $formats[$slug]['renderer'] = 'landing';
        }
    }
    return $formats;
}, 100);

/** Read the effective document, including the authenticated draft shown in preview. */
function industriesalon_editorial_has_portable_sections(int $post_id): bool
{
    if (!function_exists('iss_editorial_get_format_for_post')) { return false; }
    $format = iss_editorial_get_format_for_post($post_id);
    if (!$format || !iss_editorial_document_is_enabled($post_id, $format['slug'])) { return false; }
    foreach (iss_editorial_get_read_model($post_id, $format['slug'])['sections'] ?? [] as $section) {
        if (($format['sections'][$section['type']]['renderer'] ?? '') === 'landing') { return true; }
    }
    return false;
}

/** Reuse the existing composition renderer; null leaves domain sections with their owner. */
function industriesalon_render_editorial_portable_section(array $section, int $index, string $skin, string $format, string $anchor = ''): ?string
{
    if (!function_exists('iss_editorial_get_format')) { return null; }
    $definition = iss_editorial_get_format($format)['sections'][$section['type'] ?? ''] ?? [];
    if (($definition['renderer'] ?? '') !== 'landing') { return null; }
    if ($anchor !== '') { $section['anchor'] = $anchor; }
    // Render retained identifiers through their family without rewriting stored JSON.
    if ($definition['family'] !== $section['type']) {
        $section['treatment'] = iss_editorial_resolve_treatment((string) ($section['treatment'] ?? ''), $definition);
        $section['type'] = $definition['family'];
    }
    $section['_canvas_edit'] = ($section['_text_version'] ?? 1) >= 2 && isset($section['_editorial_index']) && !empty(iss_editorial_embedded_preview()['canvas']);
    $html = industriesalon_render_editorial_landing_section($section, $index, $skin);
    if ($html === '') { return ''; }
    $html = '<div class="iss-landing-editorial iss-landing-editorial--skin-' . esc_attr(sanitize_html_class($skin)) . '">' . $html . '</div>';
    return industriesalon_editorial_preview_section($html, $section);
}

/** A renderer reads allowed skins from the same normalized registry as the editor. */
function industriesalon_editorial_skin_slugs(string $format): array
{
    return function_exists('iss_editorial_get_format_skins')
        ? array_column(iss_editorial_get_format_skins($format), 'slug') : ['standard'];
}

/** Mark exact source sections only in an authenticated snapshot, never by rendered position. */
function industriesalon_editorial_preview_section(string $html, array $section, array $fields = [], string $root_class = ''): string
{
    if (!isset($section['_editorial_index']) || !function_exists('iss_editorial_embedded_preview')) {
        return $html;
    }
    $context = iss_editorial_embedded_preview();
    if (!$context || trim($html) === '') { return $html; }
    $tags = new WP_HTML_Tag_Processor($html);
    if (!$tags->next_tag($root_class !== '' ? ['class_name' => $root_class] : null)) { return $html; }
    $tags->set_attribute('data-iss-preview-section', (string) $section['_editorial_index']);
    $html = $tags->get_updated_html();
    if (empty($context['canvas'])) { return $html; }
    $format = iss_editorial_get_format($context['format']);
    $format['document_version'] = $context['document']['schema_version'];
    $tags = new WP_HTML_Tag_Processor($html);
    while ($tags->next_tag()) {
        foreach ($fields as $field => $class) {
            if (!$tags->has_class($class)) { continue; }
            $source = $context['document']['sections'][$section['_editorial_index']] ?? [];
            $profile = in_array($field, ['title', 'kicker'], true) ? 'plain' : iss_editorial_text_profile($format, (string) ($source['type'] ?? ''), $field);
            if ($profile !== '') {
                $tags->set_attribute('data-iss-field', $field);
                $tags->set_attribute('data-iss-profile', $profile);
                unset($fields[$field]);
            }
        }
    }
    return $tags->get_updated_html();
}

add_filter('body_class', static function (array $classes): array {
    if (is_singular() && function_exists('iss_editorial_get_format_for_post')) {
        $format = iss_editorial_get_format_for_post(get_queried_object_id());
        if ($format && iss_editorial_document_is_enabled(get_queried_object_id(), $format['slug'])) {
            $classes[] = 'iss-editorial-document';
        }
    }
    return $classes;
});

add_action('wp_enqueue_scripts', static function (): void {
    $post_id = (int) get_queried_object_id();
    if (!is_singular() || !function_exists('iss_editorial_get_format_for_post')) { return; }
    $format = iss_editorial_get_format_for_post($post_id);
    if (!$format || !iss_editorial_document_is_enabled($post_id, $format['slug'])) { return; }
    $document = iss_editorial_get_read_model($post_id, $format['slug']);
    if (($document['schema_version'] ?? 1) < 2) { return; }
    $rules = [];
    $palette_slugs = array_column(iss_editorial_text_palette(), 'slug');
    foreach ((array) ($document['sections'] ?? []) as $section) {
        $values = [$section['body'] ?? '', $section['lead'] ?? ''];
        foreach ((array) ($section['items'] ?? []) as $item) { $values[] = $item['text'] ?? ''; }
        foreach ($values as $value) {
            $tags = new WP_HTML_Tag_Processor($value);
            while ($tags->next_tag('span')) {
                foreach (preg_split('/\s+/', (string) $tags->get_attribute('class')) as $class) {
                    if (preg_match('/^iss-(ink|mark)-([0-9a-f]{6})$/D', $class, $match)) {
                        $property = $match[1] === 'ink' ? 'color' : 'background-color';
                        $rules[$class] = '.iss-editorial-document .' . $class . '{' . $property . ':#' . $match[2] . '}';
                    } elseif (preg_match('/^iss-(ink|mark)-preset-([a-z0-9-]+)$/D', $class, $match) && in_array($match[2], $palette_slugs, true)) {
                        $property = $match[1] === 'ink' ? 'color' : 'background-color';
                        $rules[$class] = '.iss-editorial-document .' . $class . '{' . $property . ':var(--wp--preset--color--' . $match[2] . ')}';
                    }
                }
            }
        }
    }
    if ($rules) { wp_add_inline_style('industriesalon-base', implode("\n", $rules)); }
}, 30);

/** Native moderated guest comments shared by events and Places. */
function industriesalon_render_feedback(int $post_id, bool $with_invitation = true): string
{
    if (!in_array(get_post_type($post_id), ['veranstaltung', 'register_place'], true) || post_password_required($post_id) || get_post_status($post_id) !== 'publish') {
        return '';
    }
    $page = max(1, absint($_GET['feedback_page'] ?? 1)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
    $query = ['post_id' => $post_id, 'type' => 'iss_feedback', 'status' => 'approve', 'meta_key' => '_iss_feedback_consent', 'meta_value' => '1']; // phpcs:ignore WordPress.DB.SlowDBQuery -- Native low-volume comment consent metadata.
    $total = (int) get_comments(array_merge($query, ['count' => true]));
    $url = $with_invitation && function_exists('iss_content_upload_url') ? iss_content_upload_url($post_id) : '';
    if (!$total && $url === '') {
        return '';
    }
    $page = min($page, max(1, (int) ceil($total / 12)));
    $comments = get_comments(array_merge($query, ['number' => 12, 'offset' => ($page - 1) * 12, 'orderby' => ['comment_date_gmt' => 'DESC', 'comment_ID' => 'DESC']]));
    $html = '<section id="stimmen" class="iss-editorial-feedback section"><p class="iss-kicker">Erfahrungen &amp; Erinnerungen</p><h2>' . esc_html(get_post_type($post_id) === 'register_place' ? __('Erinnerungen an diesen Ort', 'industriesalon') : __('Stimmen zur Veranstaltung', 'industriesalon')) . '</h2>';
    if ($comments) {
        $html .= '<div class="iss-editorial-feedback__voices">';
        foreach ($comments as $comment) {
            $html .= '<blockquote class="iss-editorial-feedback__voice">' . wpautop(esc_html($comment->comment_content)) . '<cite>' . esc_html($comment->comment_author) . '</cite></blockquote>';
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
