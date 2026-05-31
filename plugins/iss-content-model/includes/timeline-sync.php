<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_timeline_enabled_post_types() {
    return [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
    ];
}

function iss_content_model_sync_ausstellung_legacy_type_meta($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return;
    }

    $is_permanent = iss_content_model_ausstellung_is_permanent($post_id);

    if ($is_permanent) {
        update_post_meta($post_id, 'iss_is_permanent', '1');
        return;
    }

    delete_post_meta($post_id, 'iss_is_permanent');
}

function iss_content_model_has_managed_corpus($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return false;
    }

    $chapter_ids = function_exists('iss_content_model_get_ausstellung_corpus_chapter_ids')
        ? iss_content_model_get_ausstellung_corpus_chapter_ids($post_id)
        : [];
    if (!empty($chapter_ids)) {
        return true;
    }

    if (function_exists('iss_content_model_get_ausstellung_archive_term_slug')) {
        return iss_content_model_get_ausstellung_archive_term_slug($post_id) !== '';
    }

    return false;
}

function iss_content_model_maybe_assign_default_ausstellung_type($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return;
    }

    if (!taxonomy_exists(ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY)) {
        return;
    }

    $type_slugs = wp_get_post_terms($post_id, ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, ['fields' => 'slugs']);
    if (!empty($type_slugs) && !is_wp_error($type_slugs)) {
        return;
    }

    if (!iss_content_model_has_managed_corpus($post_id)) {
        return;
    }

    wp_set_object_terms($post_id, ['dauerausstellung'], ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, false);
}

function iss_content_model_ausstellung_is_permanent($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return false;
    }

    $type_slugs = wp_get_post_terms($post_id, ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, ['fields' => 'slugs']);
    return !is_wp_error($type_slugs) && in_array('dauerausstellung', array_map('sanitize_title', (array) $type_slugs), true);
}

function iss_content_model_timeline_item_type_for_post($post_type) {
    if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return 'event';
    }
    if ($post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return 'ausstellung';
    }
    return '';
}

function iss_content_model_should_sync_to_timeline($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return false;
    }

    if (!in_array($post->post_type, iss_content_model_timeline_enabled_post_types(), true)) {
        return false;
    }

    if ($post->post_status !== 'publish') {
        return false;
    }

    if ($post->post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return !empty(get_post_meta($post_id, 'iss_timeline_enabled', true)) && (string) get_post_meta($post_id, 'iss_start_datetime', true) !== '';
    }

    if ($post->post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        $timeline_enabled = get_post_meta($post_id, 'iss_timeline_enabled', true);
        $timeline_enabled = $timeline_enabled === '' ? true : (bool) $timeline_enabled;
        if (!$timeline_enabled) {
            return false;
        }

        if ((string) get_post_meta($post_id, 'iss_start_date', true) !== '') {
            return true;
        }

        return iss_content_model_ausstellung_is_permanent($post_id);
    }

    return false;
}

function iss_content_model_timeline_dates_for_post($post_id) {
    $post_id = (int) $post_id;
    $post_type = get_post_type($post_id);

    if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        $start = trim((string) get_post_meta($post_id, 'iss_start_datetime', true));
        $end = trim((string) get_post_meta($post_id, 'iss_end_datetime', true));
        return ['start' => $start, 'end' => $end];
    }

    if ($post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        $post = get_post($post_id);
        $start_date = trim((string) get_post_meta($post_id, 'iss_start_date', true));
        $end_date = trim((string) get_post_meta($post_id, 'iss_end_date', true));
        $is_permanent = iss_content_model_ausstellung_is_permanent($post_id);

        if ($start_date === '' && $is_permanent && $post instanceof WP_Post) {
            $start_date = mysql2date('Y-m-d', $post->post_date ?: current_time('mysql'), false);
        }

        if ($end_date === '' && $is_permanent) {
            $end_date = '2099-12-31';
        }

        $start = $start_date !== '' ? $start_date . ' 00:00:00' : '';
        $end = $end_date !== '' ? $end_date . ' 23:59:59' : '';
        return ['start' => $start, 'end' => $end];
    }

    return ['start' => '', 'end' => ''];
}

function iss_content_model_find_timeline_item_id($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0;
    }

    $stored = (int) get_post_meta($post_id, 'iss_timeline_item_id', true);
    if ($stored > 0 && get_post($stored) instanceof WP_Post) {
        return $stored;
    }

    $post_type = function_exists('iss_timeline_get_post_type') ? iss_timeline_get_post_type() : (defined('ISS_CALENDAR_ITEM_POST_TYPE') ? ISS_CALENDAR_ITEM_POST_TYPE : '');
    if ($post_type === '' || !post_type_exists($post_type)) {
        return 0;
    }

    $query = new WP_Query([
        'post_type' => $post_type,
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => 'source_post_id',
                'value' => $post_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    $found = !empty($query->posts) ? (int) $query->posts[0] : 0;
    if ($found > 0) {
        update_post_meta($post_id, 'iss_timeline_item_id', $found);
    }

    return $found;
}

function iss_content_model_disable_timeline_item_for_post($post_id) {
    $item_id = iss_content_model_find_timeline_item_id($post_id);
    if ($item_id <= 0) {
        delete_post_meta($post_id, 'iss_timeline_item_id');
        return;
    }

    wp_update_post([
        'ID' => $item_id,
        'post_status' => 'draft',
    ]);
    delete_post_meta($post_id, 'iss_timeline_item_id');
}

function iss_content_model_upsert_timeline_item_for_post($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0;
    }

    if (!iss_content_model_should_sync_to_timeline($post_id)) {
        iss_content_model_disable_timeline_item_for_post($post_id);
        return 0;
    }

    $timeline_post_type = function_exists('iss_timeline_get_post_type') ? iss_timeline_get_post_type() : (defined('ISS_CALENDAR_ITEM_POST_TYPE') ? ISS_CALENDAR_ITEM_POST_TYPE : '');
    if ($timeline_post_type === '' || !post_type_exists($timeline_post_type)) {
        return 0;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return 0;
    }

    $item_id = iss_content_model_find_timeline_item_id($post_id);
    $dates = iss_content_model_timeline_dates_for_post($post_id);
    if ($dates['start'] === '') {
        iss_content_model_disable_timeline_item_for_post($post_id);
        return 0;
    }

    $item_type = iss_content_model_timeline_item_type_for_post($post->post_type);
    $title = trim((string) get_the_title($post_id));
    if ($title === '') {
        $title = '(' . $post->post_type . ')';
    }

    $postarr = [
        'post_type' => $timeline_post_type,
        'post_status' => 'publish',
        'post_title' => $title,
        'post_excerpt' => (string) $post->post_excerpt,
    ];

    if ($item_id > 0) {
        $postarr['ID'] = $item_id;
        $result = wp_update_post($postarr, true);
    } else {
        $result = wp_insert_post($postarr, true);
    }

    if (is_wp_error($result)) {
        return 0;
    }

    $item_id = (int) $result;

    update_post_meta($item_id, 'event_start', $dates['start']);
    update_post_meta($item_id, 'event_end', $dates['end']);
    update_post_meta($item_id, 'sort_date', $dates['start']);
    update_post_meta($item_id, 'item_type', $item_type);
    update_post_meta($item_id, 'source_system', 'wp');
    update_post_meta($item_id, 'origin_mode', 'wp');
    update_post_meta($item_id, 'sync_status', 'local');
    update_post_meta($item_id, 'source_post_id', $post_id);
    update_post_meta($item_id, 'source_post_type', $post->post_type);
    update_post_meta($item_id, 'is_public', 1);
    update_post_meta($item_id, 'is_visible', 1);
    update_post_meta($item_id, 'cta_mode', 'details');
    update_post_meta($item_id, 'last_synced_at', current_time('mysql'));

    $location = trim((string) get_post_meta($post_id, 'iss_location', true));
    if ($location !== '') {
        update_post_meta($item_id, 'location', $location);
    } else {
        delete_post_meta($item_id, 'location');
    }

    update_post_meta($post_id, 'iss_timeline_item_id', $item_id);

    return $item_id;
}

add_action('save_post', function ($post_id, $post) {
    if (!$post instanceof WP_Post) {
        return;
    }

    if (!in_array($post->post_type, iss_content_model_timeline_enabled_post_types(), true)) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }

    if ($post->post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        iss_content_model_maybe_assign_default_ausstellung_type($post_id);
        iss_content_model_sync_ausstellung_legacy_type_meta($post_id);
    }

    iss_content_model_upsert_timeline_item_for_post($post_id);
}, 30, 2);

add_action('set_object_terms', function ($object_id, $terms, $tt_ids, $taxonomy) {
    if ($taxonomy !== ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY) {
        return;
    }

    iss_content_model_sync_ausstellung_legacy_type_meta((int) $object_id);
    iss_content_model_upsert_timeline_item_for_post((int) $object_id);
}, 10, 4);

add_action('trashed_post', function ($post_id) {
    $post_type = get_post_type($post_id);
    if (!in_array($post_type, iss_content_model_timeline_enabled_post_types(), true)) {
        return;
    }

    iss_content_model_disable_timeline_item_for_post($post_id);
});
