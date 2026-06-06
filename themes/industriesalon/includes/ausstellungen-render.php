<?php

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_get_ausstellung_type(int $post_id): string
{
    if ($post_id <= 0 || !function_exists('iss_content_model_get_ausstellung_type')) {
        return 'story';
    }

    return iss_content_model_get_ausstellung_type($post_id);
}

function industriesalon_get_ausstellung_source(int $post_id): string
{
    if ($post_id <= 0 || !function_exists('iss_content_model_get_ausstellung_source')) {
        return 'manual';
    }

    return iss_content_model_get_ausstellung_source($post_id);
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

function industriesalon_render_ausstellung_surface_heading(string $kicker, string $title, string $text = ''): string
{
    $html = '<div class="iss-heading iss-ausstellung-story__heading">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
    $html .= '<h2 class="iss-heading__title">' . esc_html($title) . '</h2>';
    if ($text !== '') {
        $html .= '<p class="iss-heading__text">' . esc_html($text) . '</p>';
    }
    $html .= '</div>';

    return $html;
}

function industriesalon_render_manual_ausstellung_surface(array $context, string $variant): string
{
    $body_html = trim((string) ($context['body_html'] ?? ''));
    $chapters = is_array($context['chapters'] ?? null) ? $context['chapters'] : [];
    $corpus_html = function_exists('iss_content_model_render_ausstellung_corpus_body')
        ? iss_content_model_render_ausstellung_corpus_body($chapters)
        : '';

    if ($body_html === '' && $corpus_html === '') {
        return '';
    }

    $classes = [
        'iss-ausstellung-surface',
        'iss-ausstellung-surface--' . sanitize_html_class($variant),
    ];

    $html = '<div class="' . esc_attr(implode(' ', $classes)) . '">';
    if ($body_html !== '') {
        $html .= industriesalon_render_ausstellung_surface_heading(
            __('Im Detail', 'industriesalon'),
            __('Rundgang durch die Ausstellung', 'industriesalon')
        );
        $html .= '<div class="iss-ausstellung-story__content">' . $body_html . '</div>';
    }

    if ($corpus_html !== '') {
        $html .= '<div class="iss-ausstellung-corpus">';
        $html .= '<div class="iss-heading iss-ausstellung-corpus__head">';
        $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Ausstellungspfad', 'industriesalon') . '</p>';
        $html .= '<h2 class="iss-heading__title">' . esc_html__('Die Ausstellung ordnet den Korpus als geführten Rundgang.', 'industriesalon') . '</h2>';
        $html .= '<p class="iss-heading__text">' . esc_html__('Ausgewählte Stationen führen in das Thema ein. Darunter bleibt der vollständige Korpus als lesbarer Ausstellungspfad zugänglich.', 'industriesalon') . '</p>';
        $html .= '</div>';
        $html .= $corpus_html;
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

function industriesalon_render_archive_category_ausstellung_surface(array $context): string
{
    $term_slug = sanitize_title((string) ($context['archive_term_slug'] ?? ''));
    if ($term_slug === '' || !function_exists('iss_wf_import_render_archive_exhibition_toc') || !function_exists('iss_wf_import_render_archive_exhibition_stream')) {
        return '';
    }

    $body_html = trim((string) ($context['body_html'] ?? ''));
    $toc_html = trim((string) iss_wf_import_render_archive_exhibition_toc($term_slug));
    $stream_html = trim((string) iss_wf_import_render_archive_exhibition_stream($term_slug));

    if ($toc_html === '' && $stream_html === '') {
        return '';
    }

    $html = '<div class="iss-ausstellung-surface iss-ausstellung-surface--archive-category">';
    $html .= industriesalon_render_ausstellung_surface_heading(
        __('Kapitelpfad', 'industriesalon'),
        __('Die Ausstellung im Überblick', 'industriesalon')
    );

    if ($body_html !== '') {
        $html .= '<div class="iss-ausstellung-story__content">' . $body_html . '</div>';
    }

    if ($toc_html !== '') {
        $html .= '<div class="iss-digital-exhibition__nav-band iss-ausstellung-surface__band">';
        $html .= '<div class="iss-heading iss-heading--uncaged iss-digital-exhibition__nav-intro">';
        $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Kapitel', 'industriesalon') . '</p>';
        $html .= '<h2 class="iss-heading__title">' . esc_html__('Die Ausstellung im Überblick', 'industriesalon') . '</h2>';
        $html .= '</div>';
        $html .= $toc_html;
        $html .= '</div>';
    }

    $html .= '<div class="iss-digital-exhibition__chapters iss-ausstellung-surface__chapters">';
    $html .= '<div class="iss-heading iss-heading--uncaged iss-digital-exhibition__chapters-intro">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Kapitel lesen', 'industriesalon') . '</p>';
    $html .= '<h2 class="iss-heading__title">' . esc_html__('Die vollständige Reihe', 'industriesalon') . '</h2>';
    $html .= '</div>';
    $html .= $stream_html;
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function industriesalon_render_archive_browser_ausstellung_surface(array $context): string
{
    $browser_attributes = is_array($context['archive_browser'] ?? null) ? $context['archive_browser'] : [];
    if (!function_exists('iss_wf_import_get_archive_object_browser_payload') || !function_exists('iss_wf_import_render_archive_object_browser_markup')) {
        return '';
    }

    if (trim((string) ($browser_attributes['quickKicker'] ?? '')) === '') {
        $browser_attributes['quickKicker'] = __('Objektfamilien', 'industriesalon');
    }

    if (trim((string) ($browser_attributes['quickTitle'] ?? '')) === '') {
        $browser_attributes['quickTitle'] = __('Einstiege in den Bestand', 'industriesalon');
    }

    $payload = iss_wf_import_get_archive_object_browser_payload($browser_attributes);
    $body_html = trim((string) ($context['body_html'] ?? ''));

    $html = '<div class="iss-ausstellung-surface iss-ausstellung-surface--collection iss-ausstellung-surface--source-archive-browser">';
    $html .= industriesalon_render_ausstellung_surface_heading(
        __('Sammlungsbestand', 'industriesalon'),
        __('Objekte, Gruppen und Filter', 'industriesalon'),
        __('Der Archivbrowser ist hier Materialquelle für eine Collection Exhibition.', 'industriesalon')
    );
    if ($body_html !== '') {
        $html .= '<div class="iss-ausstellung-story__content">' . $body_html . '</div>';
    }
    $html .= iss_wf_import_render_archive_object_browser_markup($payload, [
        'wrapper_tag' => 'div',
        'wrapper_class' => 'iss-archive-browser iss-archive-browser--embedded',
        'use_container' => false,
        'show_hero' => false,
        'show_stats' => false,
        'title_tag' => 'h2',
        'results_kicker' => __('Ergebnisse', 'industriesalon'),
    ]);
    $html .= '</div>';

    return $html;
}

add_filter('iss_content_model_render_ausstellung_surface', function ($rendered, $post_id, $context, $attributes) {
    $context = is_array($context) ? $context : [];
    $source = sanitize_key((string) ($context['source'] ?? industriesalon_get_ausstellung_source((int) $post_id)));
    $type = sanitize_key((string) ($context['type'] ?? industriesalon_get_ausstellung_type((int) $post_id)));

    if ($source === 'archive_category') {
        return industriesalon_render_archive_category_ausstellung_surface($context);
    }

    if ($source === 'archive_browser') {
        return industriesalon_render_archive_browser_ausstellung_surface($context);
    }

    if (!in_array($type, ['story', 'collection', 'visual_essay', 'timeline', 'map'], true)) {
        $type = 'story';
    }

    return industriesalon_render_manual_ausstellung_surface($context, $type);
}, 10, 4);

add_filter('body_class', function (array $classes): array {
    if (!is_singular(ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE)) {
        return $classes;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return $classes;
    }

    $classes[] = 'iss-ausstellung-type-' . industriesalon_get_ausstellung_type((int) $post_id);
    $classes[] = 'iss-ausstellung-source-' . industriesalon_get_ausstellung_source((int) $post_id);
    $classes[] = has_post_thumbnail($post_id) ? 'iss-ausstellung-has-thumb' : 'iss-ausstellung-no-thumb';

    return array_values(array_unique($classes));
});
