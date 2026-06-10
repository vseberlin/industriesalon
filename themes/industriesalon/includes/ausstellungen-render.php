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
