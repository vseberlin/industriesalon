<?php

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_editorial_place_is_enabled(int $post_id): bool
{
    return $post_id > 0
        && function_exists('iss_editorial_document_is_enabled')
        && iss_editorial_document_is_enabled($post_id, 'place');
}

/** Judge resolved output, excluding headings, kickers and gallery controls. */
function industriesalon_place_has_rendered_content(string $html): bool
{
    $tags = new WP_HTML_Tag_Processor($html);
    $ignored_tag = '';
    $ignored_depth = 0;
    while ($tags->next_token()) {
        if ($tags->get_token_type() === '#tag') {
            $tag = $tags->get_tag();
            if ($ignored_tag !== '') {
                if ($tag === $ignored_tag) {
                    $ignored_depth += $tags->is_tag_closer() ? -1 : 1;
                    if ($ignored_depth === 0) {
                        $ignored_tag = '';
                    }
                }
                continue;
            }
            if ($tags->is_tag_closer()) {
                continue;
            }
            if (in_array($tag, ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'SCRIPT', 'STYLE'], true)
                || $tags->has_class('iss-kicker') || $tags->has_class('iss-related-feed__carousel-controls')) {
                $ignored_tag = $tag;
                $ignored_depth = 1;
                continue;
            }
            if (in_array($tag, ['IMG', 'IFRAME', 'VIDEO', 'AUDIO', 'SOURCE'], true)
                && trim((string) $tags->get_attribute('src')) !== '') {
                return true;
            }
        } elseif ($ignored_tag === '' && $tags->get_token_type() === '#text') {
            $text = html_entity_decode($tags->get_modifiable_text(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (trim(str_replace(["\xc2\xa0", "\xe2\x80\x8b"], '', $text)) !== '') {
                return true;
            }
        }
    }
    return false;
}

/** Keep native template groups editable; omit empty public slots after their children render. */
function industriesalon_suppress_empty_place_groups(string $html, array $block): string
{
    if (is_admin() || !is_singular('register_place')) {
        return $html;
    }
    $classes = preg_split('/\s+/', (string) ($block['attrs']['className'] ?? ''));
    $optional = [
        'iss-front-banner-slot', 'iss-register-place__fact-band', 'iss-register-place__images',
        'iss-register-place__content', 'iss-register-place__network-rail',
        'iss-register-place__dossier-grid', 'iss-register-place-dossier',
        'iss-register-place-present', 'iss-register-place-present__data',
        'iss-register-place-contribution', 'iss-register-place-questions', 'iss-register-place-atlas',
        'iss-register-place__people', 'iss-register-place__collection',
    ];
    if (!array_intersect($classes, $optional)) {
        return $html;
    }
    if (in_array('iss-register-place-contribution', $classes, true)) {
        $tags = new WP_HTML_Tag_Processor($html);
        if (!$tags->next_tag(['class_name' => 'iss-register-place__upload-button'])) {
            $tags = new WP_HTML_Tag_Processor($html);
            if (!$tags->next_tag(['class_name' => 'iss-register-place__questions'])) { return ''; }
        }
    } elseif (array_intersect($classes, ['iss-register-place__people', 'iss-register-place__collection'])) {
        $tags = new WP_HTML_Tag_Processor($html);
        if (!$tags->next_tag(['class_name' => 'iss-card'])) { return ''; }
    } elseif (in_array('iss-register-place-atlas', $classes, true)) {
        $tags = new WP_HTML_Tag_Processor($html);
        if (!$tags->next_tag(['class_name' => 'iss-related-place-map__marker'])) {
            return '';
        }
    } elseif (!industriesalon_place_has_rendered_content($html)) {
        return '';
    }
    if (in_array('iss-register-place__dossier-grid', $classes, true)) {
        $tags = new WP_HTML_Tag_Processor($html);
        $has_content = $tags->next_tag(['class_name' => 'iss-register-place__content']);
        $tags = new WP_HTML_Tag_Processor($html);
        $has_rail = $tags->next_tag(['class_name' => 'iss-register-place__network-rail']);
        if (!$has_content || !$has_rail) {
            $tags = new WP_HTML_Tag_Processor($html);
            if ($tags->next_tag(['class_name' => 'iss-register-place__dossier-grid'])) {
                $tags->add_class('iss-register-place__dossier-grid--single');
                $html = $tags->get_updated_html();
            }
        }
    }
    return $html;
}
add_filter('render_block_core/group', 'industriesalon_suppress_empty_place_groups', 20, 2);

/** A slash-separated Place name supplies its existing descriptor below the heading. */
add_filter('render_block_core/post-title', static function (string $html, array $block): string {
    if (!is_singular('register_place') || ($block['attrs']['className'] ?? '') !== 'iss-register-place__title') {
        return $html;
    }
    $parts = explode(' / ', html_entity_decode(wp_strip_all_tags(get_the_title()), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 2);
    if (count($parts) !== 2) {
        return $html;
    }
    $tags = new WP_HTML_Tag_Processor($html);
    while ($tags->next_token()) {
        if ($tags->get_token_type() === '#text') {
            $tags->set_modifiable_text($parts[0]);
            return $tags->get_updated_html() . '<p class="iss-register-place__subtitle">' . esc_html($parts[1]) . '</p>';
        }
    }
    return $html;
}, 20, 2);

add_filter('render_block_core/post-excerpt', static function (string $html, array $block): string {
    if (is_singular('register_place') && ($block['attrs']['className'] ?? '') === 'iss-register-place__opening-lead'
        && !industriesalon_place_has_rendered_content($html)) {
        return '';
    }
    return $html;
}, 20, 2);

function industriesalon_editorial_place_year_label(array $section): string
{
    if (!empty($section['milestone'])) {
        return iss_register_milestone_date_label($section['milestone']);
    }

    $start = absint($section['start_year'] ?? 0);
    $end = absint($section['end_year'] ?? 0);

    if ($start > 0 && $end > 0 && $start !== $end) {
        return sprintf('%d–%d', $start, $end);
    }
    if ($start > 0 && $end === $start) {
        return (string) $start;
    }
    if ($start > 0) {
        return sprintf(__('ab %d', 'industriesalon'), $start);
    }

    return '';
}

function industriesalon_editorial_place_definition_label(string $kind, string $key): string
{
    $labels = [
        'era' => [
            'kaiserzeit' => __('Kaiserzeit', 'industriesalon'),
            'weimar' => __('Weimarer Republik', 'industriesalon'),
            'ns-zeit' => __('NS-Zeit', 'industriesalon'),
            'nachkriegszeit' => __('Nachkriegszeit', 'industriesalon'),
            'ddr' => __('DDR', 'industriesalon'),
            'nach-1990' => __('Nach 1990', 'industriesalon'),
        ],
        'function' => [
            'industrial' => __('Industrie / Produktion', 'industriesalon'),
            'commercial' => __('Gewerbe / Handel', 'industriesalon'),
            'culture' => __('Kultur', 'industriesalon'),
            'education' => __('Bildung / Forschung', 'industriesalon'),
            'community' => __('Gemeinwohl / Soziales', 'industriesalon'),
            'residential' => __('Wohnen', 'industriesalon'),
            'mixed' => __('Mischnutzung', 'industriesalon'),
            'vacant' => __('Leerstand', 'industriesalon'),
            'infrastructure' => __('Infrastruktur', 'industriesalon'),
        ],
        'source' => [
            'oral' => __('Mündliche Quelle', 'industriesalon'),
            'archive' => __('Archivquelle', 'industriesalon'),
            'publication' => __('Publikation', 'industriesalon'),
            'url' => __('Webquelle', 'industriesalon'),
        ],
    ];

    return (string) ($labels[$kind][$key] ?? '');
}

function industriesalon_render_editorial_place_media(array $section, bool $all = false): string
{
    $figures = '';
    foreach ((array) ($section['media_refs_resolved'] ?? []) as $item) {
        $resolved = is_array($item['resolved'] ?? null) ? $item['resolved'] : [];
        $reference = is_array($item['reference'] ?? null) ? $item['reference'] : [];
        $attachment_id = absint($resolved['id'] ?? 0);
        if ($attachment_id <= 0 || strpos((string) get_post_mime_type($attachment_id), 'image/') !== 0) {
            continue;
        }

        $image = wp_get_attachment_image($attachment_id, 'large', false, [
            'class' => 'iss-register-place__epoch-image',
            'loading' => 'lazy',
        ]);
        if ($image === '') {
            continue;
        }

        $caption = trim((string) ($reference['label'] ?? $resolved['title'] ?? ''));
        $html = '<figure class="iss-register-place__epoch-figure">' . $image;
        if ($caption !== '') {
            $html .= '<figcaption class="iss-register-place__epoch-caption"><span class="iss-register-place__epoch-caption-text">'
                . esc_html($caption) . '</span></figcaption>';
        }

        $figures .= $html . '</figure>';
        if (!$all) {
            break;
        }
    }

    return $figures;
}

/** One invitation contract for the JSON dossier and the legacy epoch renderer. */
function industriesalon_render_place_epoch_contribution(string $html, int $post_id, array $epoch, bool $automatic = false): string
{
    if (!empty($epoch['milestone']) || !function_exists('iss_content_upload_url')) { return $html; }
    $question = trim((string) ($epoch['contribution_question'] ?? ''));
    $title = trim((string) ($epoch['title'] ?? $epoch['phase_name'] ?? ''));
    if ($title === '' || ($question === '' && !$automatic)) { return $html; }
    if ($question === '') {
        $start = absint($epoch['start_year'] ?? 0);
        $end = absint($epoch['end_year'] ?? 0);
        if ($start > 0 && $end > 0 && $start !== $end) {
            $question = sprintf(__('Wissen Sie, was hier zwischen %1$d und %2$d geschah?', 'industriesalon'), $start, $end);
        } elseif ($start > 0 && $end === $start) {
            $question = sprintf(__('Wissen Sie, was hier im Jahr %d geschah?', 'industriesalon'), $start);
        } elseif ($start > 0) {
            $question = sprintf(!empty($epoch['is_current'])
                ? __('Wissen Sie, was hier seit %d geschieht?', 'industriesalon')
                : __('Wissen Sie, was hier ab %d geschah?', 'industriesalon'), $start);
        } elseif ($end > 0) {
            $question = sprintf(__('Wissen Sie, was hier bis %d geschah?', 'industriesalon'), $end);
        } else {
            $question = __('Wissen Sie mehr über diese Epoche?', 'industriesalon');
        }
    }
    $years = industriesalon_editorial_place_year_label($epoch);
    $subject = implode(' · ', array_filter([$years, mb_substr($title, 0, 80), $question]));
    $url = iss_content_upload_url($post_id, $subject);
    if ($url === '') { return $html; }
    return '<div class="iss-register-place__epoch-contribution"><a class="iss-register-place__research-button" href="' . esc_url($url) . '">' . esc_html($question) . '</a></div>';
}
add_filter('iss_register_place_epoch_contribution', 'industriesalon_render_place_epoch_contribution', 10, 3);

/** Accepted updates keep their dates and sources, without becoming extra epochs. */
function industriesalon_render_place_updates(array $events): string
{
    $items = '';
    foreach ($events as $event) {
        if (!is_array($event)) { continue; }
        $title = trim((string) ($event['title'] ?? ''));
        $summary = trim((string) ($event['summary'] ?? ''));
        if ($title === '' && $summary === '') { continue; }
        $items .= '<li class="iss-register-place__update"><span class="iss-register-place__update-date">' . esc_html(iss_register_milestone_date_label($event)) . '</span><div>';
        $url = esc_url((string) ($event['source_url'] ?? ''));
        $title_html = esc_html($title);
        if ($url !== '') { $title_html = '<a href="' . $url . '">' . $title_html . '</a>'; }
        $items .= '<strong>' . $title_html . '</strong>';
        if ($summary !== '') { $items .= '<p>' . esc_html($summary) . '</p>'; }
        if (!empty($event['source_summary'])) { $items .= '<details><summary>Quellenhinweis</summary><p>' . esc_html((string) $event['source_summary']) . '</p></details>'; }
        $items .= '</div></li>';
    }
    return $items === '' ? '' : '<ul class="iss-register-place__updates">' . $items . '</ul>';
}
add_filter('iss_register_place_updates_presentation', static fn(string $html, array $events): string => industriesalon_render_place_updates($events), 10, 2);

/** Public direct Place relations reuse the shared card grid and public-link policy. */
function industriesalon_render_place_people(string $html, array $people): string
{
    $cards = [];
    foreach ($people as $person) {
        if (!is_array($person) || empty($person['is_public']) || trim((string) ($person['name'] ?? '')) === '') { continue; }
        $name = esc_html((string) $person['name']);
        $url = iss_graph_get_entity_relation_public_permalink($person);
        if ($url !== '') { $name = '<a href="' . esc_url($url) . '">' . $name . '</a>'; }
        $card = '<article class="iss-card"><div class="iss-card__body">';
        $label = trim((string) ($person['relation_label'] ?? ''));
        if ($label !== '') { $card .= '<p class="iss-card__kicker">' . esc_html($label) . '</p>'; }
        $card .= '<h3 class="iss-card__title">' . $name . '</h3>';
        if (!empty($person['note'])) { $card .= '<p class="iss-card__text">' . esc_html((string) $person['note']) . '</p>'; }
        $years = iss_graph_format_fact_year_range($person['valid_from_year'] ?? null, $person['valid_to_year'] ?? null, 'seit', 'bis');
        if ($years !== '') { $card .= '<p class="iss-card__text">' . esc_html($years) . '</p>'; }
        $cards[] = $card . '</div></article>';
    }
    return $cards ? iss_relations_render_cards_grid($cards, 'person', ['columns' => 3]) : '';
}
add_filter('iss_register_place_people_presentation', 'industriesalon_render_place_people', 10, 2);

function industriesalon_render_editorial_place_epoch(array $section, int $post_id = 0): string
{
    if (!empty($section['milestone'])) { return industriesalon_render_place_updates([$section['milestone']]); }
    $title = trim((string) ($section['title'] ?? ''));
    $body = wp_kses_post((string) ($section['body'] ?? ''));
    $media = industriesalon_render_editorial_place_media($section, true);
    if ($media !== '') { $media = '<div class="iss-register-place__epoch-media">' . $media . '</div>'; }
    $updates = industriesalon_render_place_updates((array) ($section['milestones'] ?? []));
    $invitation = '';
    foreach ((array) ($section['_contribution_gestures'] ?? []) as $gesture) {
        $context = $section;
        $context['contribution_question'] = (string) ($gesture['title'] ?? '');
        $link = industriesalon_render_place_epoch_contribution('', $post_id, $context, true);
        $invitation .= industriesalon_editorial_preview_section($link, $gesture);
    }
    if ($title === '' || !industriesalon_place_has_rendered_content($body . $media . $invitation . $updates)) {
        return '';
    }

    $meta = array_values(array_filter([
        industriesalon_editorial_place_definition_label('era', sanitize_title((string) ($section['era_key'] ?? ''))),
        industriesalon_editorial_place_definition_label('function', sanitize_key((string) ($section['function_key'] ?? ''))),
    ]));
    $source_summary = trim((string) ($section['source_summary'] ?? ''));
    $source_label = industriesalon_editorial_place_definition_label('source', sanitize_key((string) ($section['source_confidence'] ?? '')));
    $source_refs = array_values(array_filter((array) ($section['source_refs'] ?? []), static function ($ref): bool {
        return is_array($ref) && esc_url((string) ($ref['url'] ?? '')) !== '';
    }));
    $classes = ['iss-register-place__epoch-row'];
    if (!empty($section['is_current'])) {
        $classes[] = 'is-current';
    }
    if ($media !== '') {
        $classes[] = 'has-media';
    }
    if ($invitation !== '') { $classes[] = 'has-question'; }

    $html = '<article class="' . esc_attr(implode(' ', $classes)) . '">';
    $html .= '<div class="iss-register-place__epoch-index"><p class="iss-register-place__epoch-years">'
        . esc_html(industriesalon_editorial_place_year_label($section)) . '</p></div>';
    $html .= '<div class="iss-register-place__epoch-body">';
    if ($meta) {
        $html .= '<p class="iss-register-place__epoch-meta">' . esc_html(implode(' · ', $meta)) . '</p>';
    }
    $html .= '<h3 class="iss-register-place__epoch-title">' . esc_html($title) . '</h3>';
    if (industriesalon_place_has_rendered_content($body)) {
        $html .= '<div class="iss-register-place__epoch-text">' . $body . '</div>';
    }
    if ($source_summary !== '' || $source_refs) {
        $html .= '<details class="iss-register-place__epoch-sources"><summary>'
            . esc_html__('Quellenhinweis', 'industriesalon') . '</summary>';
        if ($source_label !== '') {
            $html .= '<p class="iss-register-place__epoch-source-confidence">' . esc_html($source_label) . '</p>';
        }
        if ($source_summary !== '') {
            $html .= '<p class="iss-register-place__epoch-source">' . esc_html($source_summary) . '</p>';
        }
        if ($source_refs) {
            $html .= '<ul class="iss-register-place__epoch-source-links">';
            foreach ($source_refs as $source_ref) {
                if (!is_array($source_ref) || empty($source_ref['url'])) {
                    continue;
                }
                $label = trim((string) ($source_ref['label'] ?? '')) ?: __('Quelle öffnen', 'industriesalon');
                $html .= '<li><a href="' . esc_url((string) $source_ref['url']) . '">' . esc_html($label) . '</a></li>';
            }
            $html .= '</ul>';
        }
        $html .= '</details>';
    }
    $html .= $updates . $invitation;
    $html .= '</div>' . $media . '</article>';

    return industriesalon_editorial_preview_section($html, $section, ['title' => 'iss-register-place__epoch-title', 'body' => 'iss-register-place__epoch-text']);
}

/** Fixed dossier slots reuse the Place heading, prose and image components. */
function industriesalon_render_editorial_place_overview(array $section): string
{
    $intro = ($section['type'] ?? '') === 'intro';
    $title = trim((string) ($section['title'] ?? ''));
    $kicker = trim((string) ($section['kicker'] ?? ''));
    $body = wp_kses_post((string) ($section['body'] ?? ''));
    $media = industriesalon_render_editorial_place_media($section, true);
    $links = $intro ? '' : industriesalon_render_editorial_links((array) ($section['links'] ?? []));
    if (!industriesalon_place_has_rendered_content($body . $media . $links)) {
        return '';
    }
    $title = $title !== '' ? $title : ($intro ? __('Warum dieser Ort zählt', 'industriesalon') : __('Der Ort heute', 'industriesalon'));
    $kicker = $kicker !== '' ? $kicker : ($intro ? __('Warum dieser Ort zählt', 'industriesalon') : __('Gegenwart', 'industriesalon'));
    $body_class = $intro ? 'iss-register-place__lead iss-register-place__lead-primary' : 'iss-register-place__present-text';
    $html = '<section class="iss-register-place__section iss-register-place__section--' . ($intro ? 'lead' : 'present') . '">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
    $html .= '<h2 class="iss-register-place__section-title">' . esc_html($title) . '</h2>';
    if (industriesalon_place_has_rendered_content($body)) {
        $html .= '<div class="' . esc_attr($body_class) . '">' . $body . '</div>';
    }
    $html .= $media . $links . '</section>';
    return industriesalon_editorial_preview_section($html, $section, [
        'title' => 'iss-register-place__section-title',
        'kicker' => 'iss-kicker',
        'body' => $intro ? 'iss-register-place__lead-primary' : 'iss-register-place__present-text',
    ]);
}

function industriesalon_render_editorial_place_document(int $post_id, array $document, string $slot = 'all'): string
{
    $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
    $intro = '';
    $epochs = [];
    $present = '';
    $supplements = '';

    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }
        $type = (string) ($section['type'] ?? '');
        $slot_types = ['story' => ['intro', 'epoche', 'dynamic_slot'], 'present' => ['gegenwart'], 'supplements' => ['galerie', 'material'], 'contribution' => ['upload_intake']];
        if ($slot !== 'all' && !in_array($type, $slot_types[$slot] ?? [], true)) { continue; }
        if ($type === 'intro') {
            // Existing repeated slots remain visible even though new insertion is limited.
            $intro .= industriesalon_render_editorial_place_overview($section);
        } elseif ($type === 'epoche') {
            $epochs[] = $section;
        } elseif ($type === 'dynamic_slot' && ($section['slot_key'] ?? '') === 'place-epoch-contribution') {
            if ($epochs) {
                $epochs[count($epochs) - 1]['_contribution_gestures'][] = $section;
            }
        } elseif ($type === 'gegenwart') {
            $present .= industriesalon_render_editorial_place_overview($section);
        } elseif ($type === 'upload_intake') {
            // The native closing slot owns the only intake action; retain authored invitation prose.
            if ($slot === 'contribution' && iss_content_upload_is_open($post_id)) {
                $body = wp_kses_post((string) ($section['body'] ?? ''));
                if (industriesalon_place_has_rendered_content($body)) {
                    $supplements .= industriesalon_editorial_preview_section('<div class="iss-register-place-contribution__text">' . $body . '</div>', $section, ['body' => 'iss-register-place-contribution__text']);
                }
            }
        } elseif (in_array($type, ['galerie', 'material'], true)) {
            // Reuse the shared archive/media section renderer and its CSS contract.
            $rendered = industriesalon_render_editorial_ausstellung_section($section, false, 0, 'standard', 'place');
            if (industriesalon_place_has_rendered_content($rendered)) {
                $supplements .= $rendered;
            }
        }
    }

    if (in_array($slot, ['all', 'story'], true)) { $epochs = iss_register_merge_place_milestones($post_id, $epochs); }

    $epoch_html = '';
    foreach ($epochs as $epoch) {
        $epoch_html .= industriesalon_render_editorial_place_epoch($epoch, $post_id);
    }
    $html = $intro;
    if ($epoch_html !== '') {
        $html .= '<section class="iss-register-place__section iss-register-place__section--epochs">';
        $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Epochen', 'industriesalon') . '</p>';
        $html .= '<h2 class="iss-register-place__section-title">' . esc_html__('Schichten des Ortes', 'industriesalon') . '</h2>';
        $html .= '<div class="iss-register-place__epoch-rail">';
        $html .= $epoch_html;
        $html .= '</div></section>';
    }
    $html .= $present . $supplements;
    return $html === '' ? '' : '<div class="iss-register-place-editorial iss-register-place-editorial--ortsdossier">' . $html . '</div>';
}

function industriesalon_render_editorial_place_fallback(int $post_id): string
{
    $body = wp_kses_post(do_blocks((string) get_post_field('post_content', $post_id)));
    if (trim(wp_strip_all_tags($body)) === trim((string) get_post_field('post_excerpt', $post_id))) { $body = ''; }
    if (!industriesalon_place_has_rendered_content($body)) {
        $history = trim((string) get_post_meta($post_id, 'history_long', true));
        if ($history === '') { $history = trim((string) get_post_meta($post_id, 'history_short', true)); }
        if ($history === trim((string) get_post_field('post_excerpt', $post_id))) { $history = ''; }
        $body = wpautop(esc_html($history));
    }
    $html = industriesalon_render_editorial_place_overview(['type' => 'intro', 'body' => $body]);
    if (function_exists('iss_register_render_place_context')) {
        $epochs = iss_register_render_place_context(['variant' => 'epoch_rail']);
        if ($epochs !== '') {
            $html .= '<section class="iss-register-place__section iss-register-place__section--epochs">';
            $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Epochen', 'industriesalon') . '</p>';
            $html .= '<h2 class="iss-register-place__section-title">' . esc_html__('Schichten des Ortes', 'industriesalon') . '</h2>';
            $html .= $epochs . '</section>';
        }
    }

    return $html === '' ? '' : '<div class="iss-register-place-editorial iss-register-place-editorial--legacy">' . $html . '</div>';
}

function industriesalon_render_editorial_place_block(array $attributes = []): string
{
    if (is_admin() || !is_singular('register_place')) {
        return '';
    }

    $post_id = (int) get_queried_object_id();
    $slot = sanitize_key((string) ($attributes['slot'] ?? 'all'));
    if ($slot === 'feedback') { return industriesalon_render_feedback($post_id, false); }
    if (
        industriesalon_editorial_place_is_enabled($post_id)
        && function_exists('iss_editorial_get_read_model')
    ) {
        $document = iss_editorial_get_read_model($post_id, 'place', is_preview() && current_user_can('edit_post', $post_id));
        return industriesalon_render_editorial_place_document($post_id, $document, $slot)
            . ($slot === 'all' ? industriesalon_render_feedback($post_id, false) : '');
    }
    return in_array($slot, ['all', 'story'], true) ? industriesalon_render_editorial_place_fallback($post_id)
        . ($slot === 'all' ? industriesalon_render_feedback($post_id, false) : '') : '';
}

function industriesalon_register_editorial_place_block(): void
{
    register_block_type('industriesalon/editorial-place', [
        'api_version' => 2,
        'attributes' => ['slot' => ['type' => 'string', 'default' => 'all']],
        'render_callback' => 'industriesalon_render_editorial_place_block',
    ]);
}
add_action('init', 'industriesalon_register_editorial_place_block');
