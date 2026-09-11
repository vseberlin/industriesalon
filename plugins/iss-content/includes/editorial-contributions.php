<?php
/** Optional reports and controlled guest contributions, sharing the existing graph and Sets. */
if (!defined('ABSPATH')) {
    exit;
}

function iss_content_report_source_types(): array
{
    return ['veranstaltung', 'ausstellung', 'projekt', 'fuehrung'];
}

add_filter('iss_graph_entity_kind_registry', static function (array $registry): array {
    $registry['rueckblick'] = ['label' => __('Rückblick', 'iss-content-model'), 'owner' => 'iss-content', 'post_types' => ['rueckblick'], 'storage_kind' => 'rueckblick', 'aliases' => [], 'public' => true];
    return $registry;
});

// Use the graph's existing save/delete/search lifecycle for this editorial CPT.
add_filter('iss_graph_content_relation_post_types', static function (array $types): array {
    $types[] = 'rueckblick';
    return array_values(array_unique($types));
});

/** Relations are authoritative; post meta never duplicates source IDs. */
function iss_content_report_connections(int $post_id, bool $public_only = false): array
{
    if (!function_exists('iss_graph_get_entity_for_post')) {
        return [];
    }
    $entity = iss_graph_get_entity_for_post($post_id);
    if (!$entity) {
        return [];
    }
    $is_report = get_post_type($post_id) === 'rueckblick';
    $args = ['source_system' => 'iss_content_rueckblick', 'relation_family' => 'reports_on'];
    $service = iss_graph_get_service();
    $rows = $is_report ? $service->get_relations_for_entity((int) $entity['id'], 'reports_on', $args)
        : $service->get_incoming_relations_for_entity((int) $entity['id'], '', $args);
    $posts = [];
    foreach ($rows as $row) {
        $post = get_post((int) ($row['post_id'] ?? 0));
        if (!$post || in_array($post->post_status, ['trash', 'auto-draft'], true)) {
            continue;
        }
        if ($public_only ? ($post->post_status !== 'publish' || $post->post_password !== '') : !current_user_can('edit_post', $post->ID)) {
            continue;
        }
        $posts[$post->ID] = $post;
    }
    return array_values($posts);
}

function iss_content_report_set_sources(int $report_id, array $source_ids): bool
{
    if (get_post_type($report_id) !== 'rueckblick' || !current_user_can('edit_post', $report_id) || !function_exists('iss_graph_get_or_create_entity_for_post')) {
        return false;
    }
    $entity = iss_graph_get_or_create_entity_for_post($report_id);
    if (!$entity) {
        return false;
    }
    $protected_rows = iss_graph_get_service()->get_relations_for_entity((int) $entity['id'], 'reports_on', ['source_system' => 'iss_content_rueckblick']);
    $rows = [];
    foreach (array_unique(array_map('absint', $source_ids)) as $id) {
        if (!in_array(get_post_type($id), iss_content_report_source_types(), true) || !current_user_can('edit_post', $id)) {
            continue;
        }
        $sets = iss_content_editorial_sets_service();
        if (!$sets->get_links_for_context((string) get_post_type($id), $id)) {
            iss_content_editorial_sets_ensure_context_set(get_post($id));
        }
        foreach ($sets->get_links_for_context((string) get_post_type($id), $id) as $link) {
            $sets->attach_context((int) $link['set_id'], 'rueckblick', $report_id, 'source_material');
        }
        $target = iss_graph_get_or_create_entity_for_post($id);
        if ($target) {
            $rows[] = ['to_entity_id' => (int) $target['id'], 'relation_type' => 'reports_on', 'relation_status' => 'accepted', 'is_public' => get_post_status($report_id) === 'publish'];
        }
    }
    foreach ($protected_rows as $row) {
        if (!current_user_can('edit_post', (int) $row['post_id'])) {
            $row['to_entity_id'] = (int) $row['entity_id'];
            $rows[] = $row;
        }
    }
    iss_graph_get_service()->replace_entity_relations_for_source((int) $entity['id'], 'reports_on', 'iss_content_rueckblick', $rows);
    return true;
}

function iss_content_create_report(int $source_id)
{
    $source = get_post($source_id);
    $type = get_post_type_object('rueckblick');
    if (!$source || !in_array($source->post_type, iss_content_report_source_types(), true) || !current_user_can('edit_post', $source_id) || !$type || !current_user_can($type->cap->create_posts)) {
        return new WP_Error('report_permission', __('Rückblick kann nicht angelegt werden.', 'iss-content-model'));
    }
    foreach (iss_content_report_connections($source_id) as $report) {
        if ($report->post_status === 'draft' && (int) $report->post_author === get_current_user_id()) {
            return $report->ID;
        }
    }
    $id = wp_insert_post(['post_type' => 'rueckblick', 'post_status' => 'draft', 'post_title' => 'Rückblick: ' . $source->post_title, 'post_author' => get_current_user_id()], true);
    if (is_wp_error($id)) {
        return $id;
    }
    iss_editorial_save_document($id, 'rueckblick', iss_editorial_get_empty_document('rueckblick'));
    iss_editorial_set_document_enabled($id, 'rueckblick', true);
    if (!iss_content_report_set_sources($id, [$source_id])) {
        wp_delete_post($id, true);
        return new WP_Error('report_relation', __('Die Verbindung konnte nicht gespeichert werden.', 'iss-content-model'));
    }
    return $id;
}

add_action('admin_post_iss_content_create_report', static function (): void {
    $id = absint($_GET['post_id'] ?? 0);
    check_admin_referer('iss_content_create_report_' . $id);
    $report = iss_content_create_report($id);
    if (is_wp_error($report)) {
        wp_die(esc_html($report->get_error_message()));
    }
    wp_safe_redirect(get_edit_post_link($report, 'raw'));
    exit;
});

/** An existing upload gesture remains enabled until explicitly closed by an editor. */
function iss_content_upload_is_open(int $post_id): bool
{
    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, array_merge(iss_content_report_source_types(), ['rueckblick']), true) || in_array($post->post_status, ['trash', 'auto-draft'], true)) {
        return false;
    }
    if (metadata_exists('post', $post_id, '_iss_upload_open')) {
        return (bool) get_post_meta($post_id, '_iss_upload_open', true);
    }
    $format = iss_editorial_get_format_for_post($post_id);
    if (!$format || !iss_editorial_document_is_enabled($post_id, $format['slug'])) {
        return false;
    }
    $document = iss_editorial_get_document($post_id, $format['slug'], false);
    return in_array('upload_intake', array_column($document['sections'] ?? [], 'type'), true);
}

function iss_content_upload_key(int $post_id): string
{
    return hash_hmac('sha256', 'editorial-upload:' . $post_id . ':' . (string) get_post_meta($post_id, '_iss_upload_generation', true), wp_salt('auth'));
}

function iss_content_upload_url(int $post_id): string
{
    return iss_content_upload_is_open($post_id) ? add_query_arg(['context' => $post_id, 'key' => iss_content_upload_key($post_id)], home_url('/event-drop/')) : '';
}

function iss_content_upload_authorized(int $post_id, string $key): bool
{
    return $key !== '' && iss_content_upload_is_open($post_id) && hash_equals(iss_content_upload_key($post_id), $key);
}

function iss_content_set_upload_open(int $post_id, bool $open): void
{
    if ($open !== iss_content_upload_is_open($post_id)) {
        update_post_meta($post_id, '_iss_upload_generation', wp_generate_password(32, false, false));
    }
    update_post_meta($post_id, '_iss_upload_open', $open ? '1' : '0');
}

function iss_content_render_contributions_controls(WP_Post $post): void
{
    if (!in_array($post->post_type, array_merge(iss_content_report_source_types(), ['rueckblick']), true)) {
        return;
    }
    wp_nonce_field('iss_content_contributions_' . $post->ID, 'iss_content_contributions_nonce');
    echo '<hr><h4>' . esc_html($post->post_type === 'rueckblick' ? __('Bezüge', 'iss-content-model') : __('Rückblicke', 'iss-content-model')) . '</h4>';
    $connected = iss_content_report_connections($post->ID);
    foreach ($connected as $related) {
        echo '<p>';
        if ($post->post_type === 'rueckblick') {
            echo '<label><input type="checkbox" name="iss_report_sources[]" value="' . esc_attr((string) $related->ID) . '" checked> ' . esc_html__('Bezug behalten', 'iss-content-model') . '</label><br>';
        }
        echo '<a href="' . esc_url(get_edit_post_link($related->ID)) . '">' . esc_html($related->post_title) . '</a> (' . esc_html(get_post_status_object($related->post_status)->label) . ')</p>';
    }
    if ($post->post_type === 'rueckblick') {
        echo '<div class="iss-report-source-search"><label>' . esc_html__('Weiteren Bezug suchen', 'iss-content-model') . '<input class="widefat" type="search" aria-label="Bezug suchen"></label><p><button class="button" type="button" data-source-search>' . esc_html__('Suchen', 'iss-content-model') . '</button></p><select class="widefat" name="iss_report_add_source" aria-label="Bezug auswählen"><option value="">' . esc_html__('Keinen weiteren Bezug', 'iss-content-model') . '</option></select><p data-source-pages></p><p role="status" data-source-status></p></div>';
        echo '<p><label>' . esc_html__('Datum des Rückblicks (optional)', 'iss-content-model') . '<input type="date" name="iss_report_date" value="' . esc_attr((string) get_post_meta($post->ID, '_iss_report_date', true)) . '"></label></p>';
    } elseif (!in_array($post->post_status, ['auto-draft', 'trash'], true)) {
        $url = wp_nonce_url(add_query_arg(['action' => 'iss_content_create_report', 'post_id' => $post->ID], admin_url('admin-post.php')), 'iss_content_create_report_' . $post->ID);
        echo '<p><a class="button" href="' . esc_url($url) . '">' . esc_html__('Rückblick anlegen', 'iss-content-model') . '</a></p>';
    }
    echo '<hr><h4>' . esc_html__('Beiträge von Gästen', 'iss-content-model') . '</h4>';
    echo '<label><input type="checkbox" name="iss_upload_open" value="1" ' . checked(iss_content_upload_is_open($post->ID), true, false) . '> ' . esc_html__('Upload geöffnet', 'iss-content-model') . '</label>';
    echo '<p class="description">' . esc_html__('Mit „Aktualisieren“ speichern. Fotos und Dokumente bleiben bis zur redaktionellen Freigabe im privaten Set.', 'iss-content-model') . '</p>';
    $url = iss_content_upload_url($post->ID);
    if ($url !== '') {
        echo '<p><a target="_blank" rel="noopener" href="' . esc_url($url) . '">' . esc_html__('Upload-Link öffnen / teilen', 'iss-content-model') . '</a></p>';
    }
}

add_action('save_post', static function (int $post_id, WP_Post $post): void {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !current_user_can('edit_post', $post_id) || !isset($_POST['iss_content_contributions_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_content_contributions_nonce'])), 'iss_content_contributions_' . $post_id)) {
        return;
    }
    if (!in_array($post->post_type, array_merge(iss_content_report_source_types(), ['rueckblick']), true)) {
        return;
    }
    iss_content_set_upload_open($post_id, !empty($_POST['iss_upload_open']));
    if ($post->post_type === 'rueckblick') {
        $ids = array_map('absint', (array) ($_POST['iss_report_sources'] ?? []));
        $ids[] = absint($_POST['iss_report_add_source'] ?? 0);
        iss_content_report_set_sources($post_id, $ids);
        $date = sanitize_text_field(wp_unslash($_POST['iss_report_date'] ?? ''));
        update_post_meta($post_id, '_iss_report_date', preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && wp_checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4), $date) ? $date : '');
    }
}, 35, 2);

add_action('admin_enqueue_scripts', static function (): void {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'rueckblick') {
        return;
    }
    $path = ISS_CONTENT_MODEL_PATH . 'assets/admin-contributions.js';
    wp_enqueue_script('iss-content-contributions', plugins_url('../assets/admin-contributions.js', __FILE__), ['wp-api-fetch'], (string) filemtime($path), true);
});

// Keep the graph's public flag in step with native publication and withdrawal.
add_action('transition_post_status', static function (string $new, string $old, WP_Post $post): void {
    if ($post->post_type !== 'rueckblick' || $new === $old || !function_exists('iss_graph_get_entity_for_post')) {
        return;
    }
    $entity = iss_graph_get_entity_for_post($post->ID);
    if ($entity) {
        $service = iss_graph_get_service();
        $rows = $service->get_relations_for_entity((int) $entity['id'], 'reports_on', ['source_system' => 'iss_content_rueckblick']);
        foreach ($rows as &$row) {
            $row['to_entity_id'] = (int) $row['entity_id'];
            $row['is_public'] = $new === 'publish';
        }
        unset($row);
        $service->replace_entity_relations_for_source((int) $entity['id'], 'reports_on', 'iss_content_rueckblick', $rows);
    }
}, 40, 3);
