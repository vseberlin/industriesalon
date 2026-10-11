<?php

if (!defined('ABSPATH')) { exit; }

function iss_graph_get_editorial_signal_labels(): array
{
    return [
        'pin' => __('Fest oben halten', 'iss-graph'),
        'feature' => __('Hervorheben', 'iss-graph'),
        'boost' => __('Hoeher gewichten', 'iss-graph'),
        'suppress' => __('Nicht automatisch zeigen', 'iss-graph'),
    ];
}

function iss_graph_get_related_promotion_post_types(): array
{
    $post_types = (array) apply_filters('iss_graph_related_promotion_post_types', [
        'ausstellung',
        'publication',
        'fuehrung',
        'veranstaltung',
        'projekt',
        'video',
        'page',
        'post',
    ]);

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
}

function iss_graph_is_related_promotion_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_graph_get_related_promotion_post_types(), true);
}

function iss_graph_get_search_signal_post_types(): array
{
    $post_types = function_exists('iss_graph_get_public_search_post_types')
        ? iss_graph_get_public_search_post_types()
        : [];

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
}

function iss_graph_is_search_signal_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_graph_get_search_signal_post_types(), true);
}

function iss_graph_get_availability_signal_post_types(): array
{
    $post_types = (array) apply_filters('iss_graph_availability_signal_post_types', [
        'ausstellung',
    ]);

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
}

function iss_graph_is_availability_signal_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_graph_get_availability_signal_post_types(), true);
}

function iss_graph_editorial_signal_is_active(?array $signal, string $surface = ''): bool
{
    if (!$signal) {
        return false;
    }

    if ($surface !== '' && sanitize_key((string) ($signal['surface'] ?? 'related')) !== sanitize_key($surface)) {
        return false;
    }

    if (sanitize_key((string) ($signal['status'] ?? 'active')) !== 'active') {
        return false;
    }

    $expires_at = isset($signal['expires_at']) ? (string) $signal['expires_at'] : '';
    if ($expires_at !== '' && strtotime($expires_at . ' UTC') < strtotime(current_time('mysql', true) . ' UTC')) {
        return false;
    }

    return true;
}

function iss_graph_related_promotion_signal_is_active(?array $signal): bool
{
    return iss_graph_editorial_signal_is_active($signal, 'related')
        && sanitize_key((string) ($signal['signal'] ?? '')) === 'feature';
}

function iss_graph_get_related_promotion_signal(int $post_id, bool $active_only = false): ?array
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return null;
    }

    $signal = iss_graph_get_service()->get_editorial_signal_by_post_target($post_id, $post_id, 'related');
    if (!$signal || sanitize_key((string) ($signal['signal'] ?? '')) !== 'feature') {
        return null;
    }

    if ($active_only && !iss_graph_related_promotion_signal_is_active($signal)) {
        return null;
    }

    return $signal;
}

function iss_graph_get_availability_signal(int $post_id, bool $active_only = false): ?array
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return null;
    }

    $signal = iss_graph_get_service()->get_editorial_signal_by_post_target($post_id, $post_id, 'availability');
    if (!$signal) {
        return null;
    }

    if ($active_only && !iss_graph_editorial_signal_is_active($signal, 'availability')) {
        return null;
    }

    return $signal;
}

function iss_graph_get_editorial_signal_context_post_types(): array
{
    $post_types = [];

    if (function_exists('iss_relations_get_supported_post_types')) {
        $post_types = array_merge($post_types, iss_relations_get_supported_post_types());
    }

    if (function_exists('iss_graph_get_content_relation_post_types')) {
        $post_types = array_merge($post_types, iss_graph_get_content_relation_post_types());
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));

    return (array) apply_filters('iss_graph_editorial_signal_context_post_types', $post_types);
}

function iss_graph_is_editorial_signal_context_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_graph_get_editorial_signal_context_post_types(), true);
}

function iss_graph_editorial_signal_target_is_allowed(int $context_post_id, int $target_post_id): bool
{
    return iss_graph_editorial_signal_target_is_allowed_for_surface($context_post_id, $target_post_id, 'related');
}

function iss_graph_editorial_signal_target_is_allowed_for_surface(int $context_post_id, int $target_post_id, string $surface = 'related'): bool
{
    $context_post = get_post($context_post_id);
    $target_post = get_post($target_post_id);
    if (!$context_post instanceof WP_Post || !$target_post instanceof WP_Post) {
        return false;
    }

    if (in_array((string) $target_post->post_status, ['auto-draft', 'trash'], true)) {
        return false;
    }

    $surface = iss_graph_get_service()->normalize_editorial_signal_surface($surface);
    if ($surface === 'search') {
        return $context_post_id === $target_post_id
            && (string) $target_post->post_status === 'publish'
            && iss_graph_is_search_signal_post_type((string) $target_post->post_type);
    }

    if ($surface === 'availability') {
        return $context_post_id === $target_post_id
            && (string) $target_post->post_status === 'publish'
            && iss_graph_is_availability_signal_post_type((string) $target_post->post_type);
    }

    $allowed_post_types = iss_graph_get_editorial_signal_target_post_types($context_post);

    return in_array((string) $target_post->post_type, $allowed_post_types, true);
}
