<?php
if (!defined('ABSPATH')) exit;

/**
 * Bulk edit: set `source_post_id` and (optionally) apply to all recurring items.
 *
 * Recurrence logic uses `series_key`, which is derived from the visible
 * SuperSaaS title (stored in `supersaas_title`).
 */

add_action('bulk_edit_custom_box', function ($column_name, $post_type) {
    if ($post_type !== ISS_CALENDAR_ITEM_POST_TYPE) return;
    if ($column_name !== 'source_post_id') return;

    wp_nonce_field('iss_calendar_bulk_edit', 'iss_calendar_bulk_nonce');

    echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col">';
    echo '<div class="inline-edit-group">';
    echo '<label class="alignleft">';
    echo '<span class="title">' . esc_html__('Linked content ID', 'saas-api') . '</span>';
    echo '<span class="input-text-wrap"><input type="number" name="iss_calendar_bulk_source_post_id" value="" min="0" step="1" /></span>';
    echo '</label>';
    echo '<label class="alignleft" style="margin-left:12px;">';
    echo '<span class="title">' . esc_html__('Apply', 'saas-api') . '</span>';
    echo '<span class="input-text-wrap"><label><input type="checkbox" name="iss_calendar_bulk_apply_series" value="1" /> ' . esc_html__('same series (recurring)', 'saas-api') . '</label></span>';
    echo '</label>';
    echo '</div>';
    echo '<p class="description" style="margin:8px 0 0;">' .
        esc_html__('Leave empty to keep unchanged. Use "same series" to link all recurring entries with the same SuperSaaS title.', 'saas-api') .
        '</p>';
    echo '</div></fieldset>';
}, 10, 2);

add_action('save_post_' . ISS_CALENDAR_ITEM_POST_TYPE, function ($post_id) {
    // Bulk edit uses AJAX inline-save.
    if (!defined('DOING_AJAX') || !DOING_AJAX) return;
    if (!isset($_POST['action']) || (string) $_POST['action'] !== 'inline-save') return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (!isset($_POST['iss_calendar_bulk_nonce']) || !wp_verify_nonce((string) $_POST['iss_calendar_bulk_nonce'], 'iss_calendar_bulk_edit')) {
        return;
    }

    if (!array_key_exists('iss_calendar_bulk_source_post_id', $_POST)) {
        return; // field not present => not our bulk edit
    }

    $raw = trim((string) wp_unslash($_POST['iss_calendar_bulk_source_post_id']));
    if ($raw === '') {
        return; // empty means "don't change"
    }

    $source_post_id = (int) $raw;
    if ($source_post_id < 0) $source_post_id = 0;

    $source_post_type = '';
    if ($source_post_id > 0) {
        $source_post_type = (string) get_post_type($source_post_id);
        if ($source_post_type === 'attachment') {
            // Never link to attachments.
            return;
        }
    }

    update_post_meta($post_id, 'source_post_id', $source_post_id);
    update_post_meta($post_id, 'source_post_type', sanitize_key($source_post_type));

    $apply_series = isset($_POST['iss_calendar_bulk_apply_series']) && (string) $_POST['iss_calendar_bulk_apply_series'] === '1';
    if (!$apply_series) return;

    $series_key = (string) get_post_meta($post_id, 'series_key', true);
    if ($series_key === '') {
        $title = (string) get_post_meta($post_id, 'supersaas_title', true);
        if ($title === '') {
            $title = get_the_title($post_id);
        }
        $item_type = (string) get_post_meta($post_id, 'item_type', true);
        if (function_exists('iss_calendar_build_series_key')) {
            $series_key = iss_calendar_build_series_key($title, $item_type);
            if ($series_key !== '') {
                update_post_meta($post_id, 'series_key', $series_key);
            }
        }
    }

    if ($series_key === '') return;

    // Apply to all items with same series key (typically recurring weekly slots).
    $q = new WP_Query([
        'post_type' => ISS_CALENDAR_ITEM_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => 'series_key',
                'value' => $series_key,
                'compare' => '=',
            ],
        ],
    ]);

    if (empty($q->posts)) return;

    foreach ($q->posts as $id) {
        $id = (int) $id;
        if ($id <= 0) continue;
        update_post_meta($id, 'source_post_id', $source_post_id);
        update_post_meta($id, 'source_post_type', sanitize_key($source_post_type));
    }
}, 20);

/**
 * Admin view: show upcoming items that still need linking.
 */
add_filter('views_edit-' . ISS_CALENDAR_ITEM_POST_TYPE, function ($views) {
    if (!is_array($views)) $views = [];

    $q = new WP_Query([
        'post_type' => ISS_CALENDAR_ITEM_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'sort_date',
                'value' => current_time('mysql'),
                'compare' => '>=',
                'type' => 'DATETIME',
            ],
            [
                'key' => 'source_post_id',
                'value' => 0,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    $count = (int) ($q->found_posts ?? 0);
    if ($count <= 0) return $views;

    $current = isset($_GET['iss_needs_linking']) && (string) $_GET['iss_needs_linking'] === '1';
    $url = add_query_arg('iss_needs_linking', '1', admin_url('edit.php?post_type=' . ISS_CALENDAR_ITEM_POST_TYPE));
    $label = sprintf(__('Needs linking <span class="count">(%d)</span>', 'saas-api'), $count);
    $views['iss_needs_linking'] = '<a href="' . esc_url($url) . '"' . ($current ? ' class="current"' : '') . '>' . $label . '</a>';
    return $views;
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-' . ISS_CALENDAR_ITEM_POST_TYPE) return;

    if (!isset($_GET['iss_needs_linking']) || (string) $_GET['iss_needs_linking'] !== '1') return;

    $mq = (array) $query->get('meta_query');
    $mq[] = [
        'key' => 'sort_date',
        'value' => current_time('mysql'),
        'compare' => '>=',
        'type' => 'DATETIME',
    ];
    $mq[] = [
        'key' => 'source_post_id',
        'value' => 0,
        'compare' => '=',
        'type' => 'NUMERIC',
    ];
    $query->set('meta_query', $mq);
});

