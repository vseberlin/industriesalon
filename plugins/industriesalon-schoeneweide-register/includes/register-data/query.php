<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_normalize_place_query_text($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    return strtolower(trim((string) $value));
}

function iss_register_match_place_tag(array $place, string $needle): bool
{
    $tags = $place['tags'] ?? [];

    if (is_string($tags)) {
        $tags = array_map('trim', explode(',', $tags));
    }

    if (!is_array($tags)) {
        return false;
    }

    foreach ($tags as $tag) {
        if (iss_register_normalize_place_query_text($tag) === $needle) {
            return true;
        }
    }

    return false;
}

function iss_register_is_unclear_place(array $place): bool
{
    if (($place['status'] ?? '') === 'unklar') {
        return true;
    }

    return !empty($place['is_unclear']);
}

function iss_register_sort_place_entities(array &$places, string $orderby, string $order): void
{
    $status_order = [
        'aktiv' => 0,
        'entwicklung' => 1,
        'geplant' => 2,
        'unklar' => 3,
        'abzug' => 4,
        'sucht' => 5,
    ];

    usort($places, static function (array $left, array $right) use ($orderby, $order, $status_order): int {
        if ($orderby === 'name' || $orderby === 'area') {
            $result = strcasecmp((string) ($left[$orderby] ?? ''), (string) ($right[$orderby] ?? ''));
        } elseif ($orderby === 'status') {
            $left_status = $status_order[(string) ($left['status'] ?? '')] ?? 999;
            $right_status = $status_order[(string) ($right['status'] ?? '')] ?? 999;
            $result = $left_status <=> $right_status;
        } else {
            $left_id = (int) preg_replace('/\D+/', '', (string) ($left['id'] ?? '0'));
            $right_id = (int) preg_replace('/\D+/', '', (string) ($right['id'] ?? '0'));
            $result = $left_id <=> $right_id;
        }

        return $order === 'DESC' ? $result * -1 : $result;
    });
}

function iss_register_get_place_query_orderby_values(): array
{
    return ['id', 'name', 'area', 'status'];
}

function iss_register_get_place_query_order_values(): array
{
    return ['ASC', 'DESC'];
}

function iss_register_place_query_allows_orderby($value): bool
{
    if (!is_scalar($value)) {
        return false;
    }

    return in_array(sanitize_key((string) $value), iss_register_get_place_query_orderby_values(), true);
}

function iss_register_place_query_allows_order($value): bool
{
    if (!is_scalar($value)) {
        return false;
    }

    return in_array(strtoupper(trim((string) $value)), iss_register_get_place_query_order_values(), true);
}

function iss_register_normalize_place_query_args(array $args): array
{
    $unclear = $args['unclear'] ?? null;
    $has_unclear_filter = $unclear !== null && $unclear !== '';

    return [
        'search' => iss_register_normalize_place_query_text($args['search'] ?? ''),
        'area' => iss_register_normalize_place_query_text($args['area'] ?? ''),
        'status' => iss_register_normalize_place_query_text($args['status'] ?? ''),
        'role' => iss_register_normalize_place_query_text($args['role'] ?? ''),
        'tag' => iss_register_normalize_place_query_text($args['tag'] ?? ''),
        'has_unclear_filter' => $has_unclear_filter,
        'unclear' => $has_unclear_filter ? (bool) rest_sanitize_boolean($unclear) : null,
        'orderby' => sanitize_key((string) ($args['orderby'] ?? '')),
        'order' => strtoupper((string) ($args['order'] ?? '')) === 'DESC' ? 'DESC' : 'ASC',
    ];
}

function iss_register_get_place_query_args_from_request(WP_REST_Request $request): array
{
    return iss_register_normalize_place_query_args([
        'search' => $request->get_param('search'),
        'area' => $request->get_param('area'),
        'status' => $request->get_param('status'),
        'role' => $request->get_param('role'),
        'tag' => $request->get_param('tag'),
        'unclear' => $request->get_param('unclear'),
        'orderby' => $request->get_param('orderby'),
        'order' => $request->get_param('order'),
    ]);
}

function iss_register_filter_place_entities(array $places, array $query_args): array
{
    $query_args = iss_register_normalize_place_query_args($query_args);

    $places = array_values(array_filter($places, static function (array $place) use ($query_args): bool {
        if ($query_args['area'] !== '' && iss_register_normalize_place_query_text($place['area'] ?? '') !== $query_args['area']) {
            return false;
        }

        if ($query_args['status'] !== '' && iss_register_normalize_place_query_text($place['status'] ?? '') !== $query_args['status']) {
            return false;
        }

        if ($query_args['role'] !== '' && iss_register_normalize_place_query_text($place['role'] ?? '') !== $query_args['role']) {
            return false;
        }

        if ($query_args['tag'] !== '' && !iss_register_match_place_tag($place, $query_args['tag'])) {
            return false;
        }

        if ($query_args['has_unclear_filter'] && iss_register_is_unclear_place($place) !== $query_args['unclear']) {
            return false;
        }

        if ($query_args['search'] !== '') {
            $haystack = implode(' ', [
                (string) ($place['name'] ?? ''),
                (string) ($place['owner'] ?? ''),
                (string) ($place['branche'] ?? ''),
                (string) ($place['address'] ?? ''),
                (string) ($place['current'] ?? ''),
                (string) ($place['history'] ?? ''),
                (string) ($place['vornutzung'] ?? ''),
                (string) ($place['jobs'] ?? ''),
            ]);

            if (stripos($haystack, $query_args['search']) === false) {
                return false;
            }
        }

        return true;
    }));

    if (in_array($query_args['orderby'], iss_register_get_place_query_orderby_values(), true)) {
        iss_register_sort_place_entities($places, $query_args['orderby'], $query_args['order']);
    }

    return $places;
}

function iss_register_get_filtered_place_entities(array $query_args): array
{
    return iss_register_filter_place_entities(iss_register_get_place_entities(), $query_args);
}
