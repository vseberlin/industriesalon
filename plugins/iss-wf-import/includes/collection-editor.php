<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_add_collection_editor_meta_box(): void
{
    add_meta_box(
        'iss-wf-import-collection-editor',
        __('Albumfolge', 'iss-wf-import'),
        'iss_wf_import_render_collection_editor_meta_box',
        ISS_WF_IMPORT_COLLECTION_POST_TYPE,
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'iss_wf_import_add_collection_editor_meta_box');

function iss_wf_import_render_collection_editor_meta_box(WP_Post $post): void
{
    wp_nonce_field('iss_wf_import_collection_editor_action', 'iss_wf_import_collection_editor_nonce');

    $projection = iss_wf_import_get_collection_service()->ensure_projection_for_post($post->ID);
    $items = is_array($projection) ? (array) ($projection['items'] ?? []) : [];

    echo '<p>' . esc_html__('Für Album-Sammlungen gilt: Der Kurztext gehört in das Feld "Auszug". Die Albumreihenfolge, Seitentitel und Beschriftungen werden hier gepflegt.', 'iss-wf-import') . '</p>';

    if (!$items) {
        echo '<p>' . esc_html__('Für diese Sammlung sind noch keine Albumeinträge gespeichert.', 'iss-wf-import') . '</p>';
        return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th style="width:6rem;">' . esc_html__('Pos.', 'iss-wf-import') . '</th>';
    echo '<th style="width:7rem;">' . esc_html__('Seite', 'iss-wf-import') . '</th>';
    echo '<th>' . esc_html__('Objekt', 'iss-wf-import') . '</th>';
    echo '<th>' . esc_html__('Anzeigetitel', 'iss-wf-import') . '</th>';
    echo '<th>' . esc_html__('Beschriftung', 'iss-wf-import') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $object_id = absint($item['object_id'] ?? 0);
        $object_title = $object_id > 0 ? get_the_title($object_id) : '';
        $edit_link = $object_id > 0 ? get_edit_post_link($object_id, '') : '';

        echo '<tr>';
        echo '<td>';
        printf(
            '<input type="number" min="0" step="1" name="iss_archive_collection_items[%1$d][position]" value="%2$s" class="small-text">',
            (int) $index,
            esc_attr((string) ($item['position'] ?? $index))
        );
        echo '</td>';

        echo '<td>';
        printf(
            '<input type="text" name="iss_archive_collection_items[%1$d][page_label]" value="%2$s" class="regular-text">',
            (int) $index,
            esc_attr((string) ($item['page_label'] ?? ''))
        );
        echo '</td>';

        echo '<td>';
        if ($edit_link !== '' && $object_title !== '') {
            echo '<a href="' . esc_url($edit_link) . '"><strong>' . esc_html($object_title) . '</strong></a>';
        } elseif ($object_title !== '') {
            echo '<strong>' . esc_html($object_title) . '</strong>';
        } else {
            echo '—';
        }
        if (!empty($item['source_url'])) {
            echo '<br><small><a href="' . esc_url((string) $item['source_url']) . '">' . esc_html__('Original', 'iss-wf-import') . '</a></small>';
        }
        echo '</td>';

        echo '<td>';
        printf(
            '<input type="text" name="iss_archive_collection_items[%1$d][title]" value="%2$s" class="widefat">',
            (int) $index,
            esc_attr((string) ($item['title'] ?? ''))
        );
        echo '</td>';

        echo '<td>';
        printf(
            '<textarea name="iss_archive_collection_items[%1$d][caption_override]" rows="3" class="widefat">%2$s</textarea>',
            (int) $index,
            esc_textarea((string) ($item['caption_override'] ?? ''))
        );
        echo '</td>';

        printf('<input type="hidden" name="iss_archive_collection_items[%1$d][object_id]" value="%2$d">', (int) $index, $object_id);
        printf('<input type="hidden" name="iss_archive_collection_items[%1$d][source_object_id]" value="%2$s">', (int) $index, esc_attr((string) ($item['source_object_id'] ?? '')));
        printf('<input type="hidden" name="iss_archive_collection_items[%1$d][source_url]" value="%2$s">', (int) $index, esc_attr((string) ($item['source_url'] ?? '')));

        echo '</tr>';
    }

    echo '</tbody></table>';
}

function iss_wf_import_save_collection_editor_meta_box(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $nonce = $_POST['iss_wf_import_collection_editor_nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce($nonce, 'iss_wf_import_collection_editor_action')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw_items = $_POST['iss_archive_collection_items'] ?? null;
    if (!is_array($raw_items)) {
        return;
    }

    $items = iss_wf_import_sanitize_collection_items(wp_unslash($raw_items));
    iss_wf_import_get_collection_service()->save_collection_items($post_id, $items);
}
add_action('save_post', 'iss_wf_import_save_collection_editor_meta_box', 20, 2);
