<?php

if (!defined('ABSPATH')) exit;

define('ISS_CALENDAR_ITEM_POST_TYPE', 'iss_calendar_item');
define('ISS_CALENDAR_SOURCE_MAP_OPTION', 'iss_calendar_source_map');
define('ISS_CALENDAR_SOURCE_MAP_VERSION', 2);
define('ISS_CALENDAR_SERIES_MAP_OPTION', 'iss_calendar_series_map');
define('ISS_CALENDAR_SERIES_MAP_VERSION', 1);

add_action('init', function () {
    $labels = [
        'name'               => 'Calendar Items',
        'singular_name'      => 'Calendar Item',
        'add_new_item'       => 'Add Calendar Item',
        'edit_item'          => 'Edit Calendar Item',
        'new_item'           => 'New Calendar Item',
        'view_item'          => 'View Calendar Item',
        'search_items'       => 'Search Calendar Items',
        'not_found'          => 'No calendar items found',
        'not_found_in_trash' => 'No calendar items found in Trash',
        'menu_name'          => 'Calendar Items',
    ];

    register_post_type(ISS_CALENDAR_ITEM_POST_TYPE, [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'excerpt'],
        'menu_icon' => 'dashicons-calendar-alt',
        'has_archive' => false,
        'rewrite' => false,
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);

    iss_calendar_register_item_meta();
});

function iss_calendar_register_item_meta() {
    $fields = [
        'event_start' => ['type' => 'string'],
        'event_end' => ['type' => 'string'],
        'item_type' => ['type' => 'string'],
        'supersaas_title' => ['type' => 'string'],
        'series_key' => ['type' => 'string'],
        'source_system' => ['type' => 'string'],
        'source_calendar' => ['type' => 'string'],
        'external_id' => ['type' => 'string'],
        'source_post_id' => ['type' => 'integer'],
        'source_post_type' => ['type' => 'string'],
        'booking_url' => ['type' => 'string'],
        'location' => ['type' => 'string'],
        'availability_state' => ['type' => 'string'],
        'capacity_total' => ['type' => 'integer'],
        'capacity_available' => ['type' => 'integer'],
        'is_public' => ['type' => 'integer'],
        'sync_status' => ['type' => 'string'],
        'last_synced_at' => ['type' => 'string'],
        'last_seen_at' => ['type' => 'string'],
        'origin_mode' => ['type' => 'string'],
        'public_note' => ['type' => 'string'],
        'sort_date' => ['type' => 'string'],
    ];

    foreach ($fields as $key => $cfg) {
        register_post_meta(ISS_CALENDAR_ITEM_POST_TYPE, $key, [
            'type' => $cfg['type'],
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'iss_calendar_sanitize_meta_value',
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
}

function iss_calendar_sanitize_meta_value($value, $meta_key, $meta_type) {
    if (is_array($value) || is_object($value)) {
        return null;
    }

    if (in_array($meta_key, ['source_post_id', 'capacity_total', 'capacity_available', 'is_public'], true)) {
        return (int) $value;
    }

    return sanitize_text_field((string) $value);
}

/**
 * Stable identifier for a "recurring series" of imported items.
 *
 * This deliberately does NOT rely on TAG markers. It is title-driven
 * because that's what editors see in SuperSaaS.
 */
function iss_calendar_build_series_key($title, $item_type = '') {
    $slug = sanitize_title((string) $title);
    $item_type = sanitize_key((string) $item_type);
    if ($item_type !== '') {
        return $item_type . ':' . $slug;
    }
    return $slug;
}

/**
 * Save (tag => source page) mapping for later sync.
 *
 * This is intentionally automatic to avoid editor involvement:
 * the act of rendering a page containing the calendar is enough
 * to remember which WP page corresponds to which SuperSaaS tag.
 */
function iss_calendar_remember_source_mapping($tag, $fallback_url, $source_post_id, $source_post_type) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') return;

    $source_post_id = $source_post_id ? (int) $source_post_id : 0;
    $source_post_type = sanitize_key((string) $source_post_type);
    $fallback_url = esc_url_raw((string) $fallback_url);

    $map = get_option(ISS_CALENDAR_SOURCE_MAP_OPTION, []);
    if (!is_array($map)) {
        $map = [];
    }

    $prev = isset($map[$tag]) && is_array($map[$tag]) ? $map[$tag] : [];
    $prev_source_post_id = isset($prev['source_post_id']) ? (int) $prev['source_post_id'] : 0;
    $prev_source_post_type = isset($prev['source_post_type']) ? sanitize_key((string) $prev['source_post_type']) : '';
    $prev_fallback_url = isset($prev['fallback_url']) ? esc_url_raw((string) $prev['fallback_url']) : '';
    $prev_supersaas_title = isset($prev['supersaas_title']) ? trim((string) $prev['supersaas_title']) : '';

    // Never downgrade an existing strong mapping with a weak/empty update.
    if ($source_post_id <= 0 && $prev_source_post_id > 0) {
        $source_post_id = $prev_source_post_id;
    }
    if ($source_post_type === '' && $prev_source_post_type !== '') {
        $source_post_type = $prev_source_post_type;
    }
    if ($fallback_url === '' && $prev_fallback_url !== '') {
        $fallback_url = $prev_fallback_url;
    }

    $supersaas_title = $prev_supersaas_title;
    if ($supersaas_title === '' && $source_post_id > 0) {
        $source_title = trim((string) get_the_title($source_post_id));
        if ($source_title !== '') {
            $source_title = preg_replace('/(?:\\s|-)*(tour|fuehrung|führung)$/iu', '', $source_title);
            $source_title = trim((string) $source_title);
            if ($source_title !== '') {
                $supersaas_title = $source_title;
            }
        }
    }

    $next = [
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'fallback_url' => $fallback_url,
        'supersaas_title' => $supersaas_title,
        'version' => ISS_CALENDAR_SOURCE_MAP_VERSION,
        'last_seen_at' => current_time('mysql'),
    ];

    if ($prev === $next) return;

    $map[$tag] = $next;
    update_option(ISS_CALENDAR_SOURCE_MAP_OPTION, $map, false);
}

function iss_calendar_get_source_map() {
    $map = get_option(ISS_CALENDAR_SOURCE_MAP_OPTION, []);
    if (!is_array($map)) return [];

    // Backfill missing keys for older entries.
    foreach ($map as $tag => $entry) {
        if (!is_array($entry)) continue;
        if (!array_key_exists('supersaas_title', $entry)) {
            $entry['supersaas_title'] = '';
        }
        if (!array_key_exists('version', $entry)) {
            $entry['version'] = ISS_CALENDAR_SOURCE_MAP_VERSION;
        }
        $map[$tag] = $entry;
    }

    return $map;
}

function iss_calendar_get_source_map_entry($tag) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') return null;
    $map = iss_calendar_get_source_map();
    if (!isset($map[$tag]) || !is_array($map[$tag])) return null;
    return $map[$tag];
}

function iss_calendar_normalize_series_key($series_key) {
    $series_key = strtolower(trim(sanitize_text_field((string) $series_key)));
    if ($series_key === '') {
        return '';
    }

    $series_key = preg_replace('/[^a-z0-9:_-]+/', '', $series_key);
    $series_key = trim((string) $series_key);
    if ($series_key === '') {
        return '';
    }

    return $series_key;
}

function iss_calendar_get_series_map() {
    $map = get_option(ISS_CALENDAR_SERIES_MAP_OPTION, []);
    if (!is_array($map)) {
        return [];
    }

    $normalized = [];
    foreach ($map as $series_key => $entry) {
        $series_key = iss_calendar_normalize_series_key($series_key);
        if ($series_key === '') {
            continue;
        }

        $entry = is_array($entry) ? $entry : [];
        $tag = isset($entry['tag']) ? strtoupper(sanitize_text_field((string) $entry['tag'])) : '';
        $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
        $tag = trim((string) $tag);

        $normalized[$series_key] = [
            'source_post_id' => isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0,
            'source_post_type' => isset($entry['source_post_type']) ? sanitize_key((string) $entry['source_post_type']) : '',
            'supersaas_title' => isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '',
            'tag' => $tag,
            'fallback_url' => isset($entry['fallback_url']) ? esc_url_raw((string) $entry['fallback_url']) : '',
            'version' => isset($entry['version']) ? (int) $entry['version'] : ISS_CALENDAR_SERIES_MAP_VERSION,
            'last_seen_at' => isset($entry['last_seen_at']) ? (string) $entry['last_seen_at'] : '',
        ];
    }

    ksort($normalized);
    return $normalized;
}

function iss_calendar_get_series_map_entry($series_key) {
    $series_key = iss_calendar_normalize_series_key($series_key);
    if ($series_key === '') {
        return null;
    }

    $map = iss_calendar_get_series_map();
    if (!isset($map[$series_key]) || !is_array($map[$series_key])) {
        return null;
    }

    return $map[$series_key];
}

function iss_calendar_remember_series_mapping($series_key, $source_post_id, $source_post_type, $supersaas_title = '', $tag = '', $fallback_url = '') {
    $series_key = iss_calendar_normalize_series_key($series_key);
    if ($series_key === '') {
        return false;
    }

    $source_post_id = (int) $source_post_id;
    $source_post_type = sanitize_key((string) $source_post_type);
    $supersaas_title = trim((string) $supersaas_title);
    $fallback_url = esc_url_raw((string) $fallback_url);
    $tag = strtoupper(sanitize_text_field((string) $tag));
    $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
    $tag = trim((string) $tag);

    $map = iss_calendar_get_series_map();
    $prev = isset($map[$series_key]) && is_array($map[$series_key]) ? $map[$series_key] : [];

    $prev_source_post_id = isset($prev['source_post_id']) ? (int) $prev['source_post_id'] : 0;
    $prev_source_post_type = isset($prev['source_post_type']) ? sanitize_key((string) $prev['source_post_type']) : '';
    $prev_supersaas_title = isset($prev['supersaas_title']) ? trim((string) $prev['supersaas_title']) : '';
    $prev_tag = isset($prev['tag']) ? strtoupper(sanitize_text_field((string) $prev['tag'])) : '';
    $prev_fallback_url = isset($prev['fallback_url']) ? esc_url_raw((string) $prev['fallback_url']) : '';

    if ($source_post_id <= 0 && $prev_source_post_id > 0) {
        $source_post_id = $prev_source_post_id;
    }
    if ($source_post_type === '' && $prev_source_post_type !== '') {
        $source_post_type = $prev_source_post_type;
    }
    if ($supersaas_title === '' && $prev_supersaas_title !== '') {
        $supersaas_title = $prev_supersaas_title;
    }
    if ($tag === '' && $prev_tag !== '') {
        $tag = $prev_tag;
    }
    if ($fallback_url === '' && $prev_fallback_url !== '') {
        $fallback_url = $prev_fallback_url;
    }

    if ($supersaas_title === '' && $source_post_id > 0) {
        $source_title = trim((string) get_the_title($source_post_id));
        if ($source_title !== '') {
            $source_title = preg_replace('/(?:\\s|-)*(tour|fuehrung|führung)$/iu', '', $source_title);
            $source_title = trim((string) $source_title);
            if ($source_title !== '') {
                $supersaas_title = $source_title;
            }
        }
    }

    $next = [
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'supersaas_title' => $supersaas_title,
        'tag' => $tag,
        'fallback_url' => $fallback_url,
        'version' => ISS_CALENDAR_SERIES_MAP_VERSION,
        'last_seen_at' => current_time('mysql'),
    ];

    if ($prev === $next) {
        return false;
    }

    $map[$series_key] = $next;
    update_option(ISS_CALENDAR_SERIES_MAP_OPTION, $map, false);
    return true;
}

function iss_calendar_clear_series_mapping_for_post($source_post_id) {
    $source_post_id = (int) $source_post_id;
    if ($source_post_id <= 0) {
        return 0;
    }

    $map = iss_calendar_get_series_map();
    if (empty($map)) {
        return 0;
    }

    $changed = 0;
    foreach ($map as $series_key => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((int) ($entry['source_post_id'] ?? 0) !== $source_post_id) {
            continue;
        }

        $entry['source_post_id'] = 0;
        $entry['source_post_type'] = '';
        $entry['last_seen_at'] = current_time('mysql');
        $map[$series_key] = $entry;
        $changed++;
    }

    if ($changed > 0) {
        update_option(ISS_CALENDAR_SERIES_MAP_OPTION, $map, false);
    }

    return $changed;
}

function iss_calendar_clear_series_mapping_for_key($series_key) {
    $series_key = iss_calendar_normalize_series_key($series_key);
    if ($series_key === '') {
        return false;
    }

    $map = iss_calendar_get_series_map();
    if (!isset($map[$series_key]) || !is_array($map[$series_key])) {
        return false;
    }

    $entry = $map[$series_key];
    $entry['source_post_id'] = 0;
    $entry['source_post_type'] = '';
    $entry['last_seen_at'] = current_time('mysql');
    $map[$series_key] = $entry;

    return (bool) update_option(ISS_CALENDAR_SERIES_MAP_OPTION, $map, false);
}

function iss_calendar_resolve_source_by_series_key($series_key) {
    $entry = iss_calendar_get_series_map_entry($series_key);
    if (!is_array($entry)) {
        return ['source_post_id' => 0, 'source_post_type' => ''];
    }

    $source_post_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0;
    $source_post_type = isset($entry['source_post_type']) ? sanitize_key((string) $entry['source_post_type']) : '';
    if ($source_post_id <= 0 || !(get_post($source_post_id) instanceof WP_Post)) {
        return ['source_post_id' => 0, 'source_post_type' => ''];
    }

    if ($source_post_type === '') {
        $source_post_type = sanitize_key((string) get_post_type($source_post_id));
    }

    return [
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
    ];
}

function iss_calendar_resolve_series_keys_for_source_post_id($source_post_id) {
    $source_post_id = (int) $source_post_id;
    if ($source_post_id <= 0) {
        return [];
    }

    $map = iss_calendar_get_series_map();
    if (empty($map)) {
        return [];
    }

    $series_keys = [];
    foreach ($map as $series_key => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((int) ($entry['source_post_id'] ?? 0) !== $source_post_id) {
            continue;
        }
        $series_keys[] = iss_calendar_normalize_series_key($series_key);
    }

    $series_keys = array_values(array_unique(array_filter($series_keys)));
    sort($series_keys);
    return $series_keys;
}

/**
 * Best-effort: resolve a calendar tag for a given source post id.
 *
 * @param int $source_post_id
 * @return string
 */
function iss_calendar_resolve_tag_for_source_post_id($source_post_id) {
    $source_post_id = (int) $source_post_id;
    if ($source_post_id <= 0) return '';

    $map = iss_calendar_get_source_map();
    foreach ($map as $tag => $entry) {
        if (!is_array($entry)) continue;
        if ((int) ($entry['source_post_id'] ?? 0) === $source_post_id) {
            return strtoupper(sanitize_text_field((string) $tag));
        }
    }

    return '';
}

function iss_calendar_get_item_source_permalink($item_id) {
    $item_id = (int) $item_id;
    if ($item_id <= 0) {
        return '';
    }

    $source_post_id = (int) get_post_meta($item_id, 'source_post_id', true);
    if ($source_post_id <= 0) {
        return '';
    }

    $source_link = get_permalink($source_post_id);
    if (!is_string($source_link) || trim($source_link) === '') {
        return '';
    }

    return $source_link;
}

add_filter('manage_' . ISS_CALENDAR_ITEM_POST_TYPE . '_posts_columns', function ($cols) {
    $out = [];
    foreach ($cols as $key => $label) {
        $out[$key] = $label;
        if ($key === 'title') {
            $out['event_start'] = 'Start';
            $out['availability_state'] = 'Status';
            $out['source_post_id'] = 'Source';
            $out['sync_status'] = 'Sync';
        }
    }
    return $out;
});

add_action('manage_' . ISS_CALENDAR_ITEM_POST_TYPE . '_posts_custom_column', function ($col, $post_id) {
    if ($col === 'event_start') {
        echo esc_html((string) get_post_meta($post_id, 'event_start', true));
        return;
    }
    if ($col === 'availability_state') {
        echo esc_html((string) get_post_meta($post_id, 'availability_state', true));
        return;
    }
    if ($col === 'source_post_id') {
        $id = (int) get_post_meta($post_id, 'source_post_id', true);
        if ($id > 0) {
            $title = get_the_title($id);
            $link = get_edit_post_link($id);
            if ($link) {
                printf('<a href="%s">#%d %s</a>', esc_url($link), $id, esc_html($title));
                return;
            }
            echo esc_html('#' . $id . ' ' . $title);
            return;
        }
        echo '—';
        return;
    }
    if ($col === 'sync_status') {
        echo esc_html((string) get_post_meta($post_id, 'sync_status', true));
        return;
    }
}, 10, 2);
