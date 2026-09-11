<?php
/** Local integration checks; creates and removes only its own temporary records. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/post.php';
global $wpdb;
$service = iss_content_editorial_sets_service();
$user = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($user->ID);
$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
};
$posts = []; $sets = []; $items = [];
$prefix = 'sets-check-' . wp_generate_password(8, false, false);
$before_sets = $wpdb->get_results("SELECT * FROM {$service->get_sets_table_name()} ORDER BY id", ARRAY_A);
$before_items = $wpdb->get_results("SELECT * FROM {$service->get_items_table_name()} ORDER BY id", ARRAY_A);
$before_links = $wpdb->get_results("SELECT * FROM {$service->get_links_table_name()} ORDER BY id", ARRAY_A);
try {
    $image = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'numberposts' => 1])[0];
    $pdf = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit', 'numberposts' => 1])[0];
    foreach (['veranstaltung', 'projekt', 'rueckblick', 'fuehrung', 'ausstellung'] as $type) {
        $id = wp_insert_post(['post_type' => $type, 'post_status' => 'draft', 'post_title' => $prefix . ' ' . $type, 'post_name' => $prefix . '-' . $type, 'post_author' => $user->ID]);
        $posts[] = $id;
        foreach ($service->get_links_for_context($type, $id) as $link) { $sets[] = (int) $link['set_id']; }
        $format = iss_editorial_get_format_for_post($id)['slug'];
        $doc = iss_editorial_get_empty_document($format);
        if ($type === 'veranstaltung') { $doc['entity_key'] = 'event.general'; update_post_meta($id, '_iss_entity_key', 'event.general'); }
        $assert(iss_editorial_save_document($id, $format, $doc), 'Initialize ' . $type);
        iss_editorial_set_document_enabled($id, $format, true);
        $canonical = get_metadata_raw('post', $id, iss_editorial_get_document_meta_key($format), true);
        $set = $service->create_set(['title' => $prefix . ' ' . $type]); $sets[] = $set;
        $item = $service->add_item(['set_id' => $set, 'kind' => 'wp_media', 'source' => 'wp-media', 'source_id' => (string) $image->ID]); $items[] = $item;
        $args = ['base' => iss_editorial_saved_token($id, $format), 'draftToken' => ''];
        $result = iss_content_editorial_sets_promote($id, $type, [$item], $args);
        $assert(!$result['prepared'], 'Unreviewed media rejected for ' . $type);
        $assert(!$service->update_item($item, ['status' => 'approved']), 'Approval requires rights fields');
        $rights = ['attribution' => 'Fixture photographer', 'license' => 'all-rights-reserved', 'consent' => '1'];
        $assert($service->update_item($item, ['status' => 'approved', 'rights' => $rights]), 'Approve reviewed photo');
        $file = $service->add_item(['set_id' => $set, 'kind' => 'wp_media', 'source' => 'wp-media', 'source_id' => (string) $pdf->ID]); $items[] = $file;
        $assert($service->update_item($file, ['status' => 'approved', 'rights' => $rights]), 'Approve reviewed PDF');
        $result = iss_content_editorial_sets_promote($id, $type, [$item, $file], $args);
        $assert($result['prepared'] === 2 && $result['promoted'] === 0, 'Prepare mixed media as draft for ' . $type . ': ' . ($result['message'] ?? ''));
        $assert(get_metadata_raw('post', $id, iss_editorial_get_document_meta_key($format), true) === $canonical, 'Canonical content unchanged');
        $assert($service->get_item($item)['status'] === 'approved' && !$service->get_item_uses($item), 'Draft does not claim publication');
        $draft = iss_editorial_get_draft($id, $format);
        $assert(array_column($draft['document']['sections'], 'type') === ['galerie', 'material'], 'Photos and documents routed separately');
        $assert((int) $draft['document']['sections'][0]['media_refs'][0]['editorial_set_item_id'] === $item, 'Draft retains Set provenance');
        $assert(!iss_content_editorial_sets_promote($id, $type, [$item], $args)['prepared'], 'Stale draft token rejected');
        $args['draftToken'] = $draft['token'];
        $assert(iss_content_editorial_sets_promote($id, $type, [$item], $args)['prepared'] === 1, 'Repeat selection supported');
        $draft = iss_editorial_get_draft($id, $format);
        $assert(count($draft['document']['sections'][0]['media_refs']) === 1, 'Repeat selection does not duplicate references');
        $service->update_item($item, ['status' => 'rejected']);
        $assert(is_wp_error(iss_editorial_validate_document($draft['document'], $format)), 'Withdrawal after preparation blocks save and preview');
        $service->update_item($item, ['status' => 'approved', 'rights' => $rights]);

        $assert(iss_editorial_save_document($id, $format, $draft['document']), 'Save prepared document through engine');
        $assert($service->get_item($item)['status'] === 'promoted' && count($service->get_item_uses($item)) === 1, 'Actual save records use');
        if ($type === 'rueckblick') {
            $event_id = $posts[0];
            $event_doc = iss_editorial_get_document($event_id, 'veranstaltung', false);
            $event_doc['sections'] = [];
            $assert(iss_editorial_save_document($event_id, 'veranstaltung', $event_doc), 'Prepare empty second destination');
            $event_draft = iss_editorial_get_draft($event_id, 'veranstaltung');
            $args = ['base' => iss_editorial_saved_token($event_id, 'veranstaltung'), 'draftToken' => $event_draft['token']];
            // Clear an obsolete autosave just as the normal editor does after a canonical save.
            wp_delete_post($event_draft['id'], true); $args['draftToken'] = '';
            $assert(iss_content_editorial_sets_promote($event_id, 'veranstaltung', [$item], $args)['prepared'] === 1, 'Already-used material can supply another content item');
            $event_draft = iss_editorial_get_draft($event_id, 'veranstaltung');
            $assert(iss_editorial_save_document($event_id, 'veranstaltung', $event_draft['document']), 'Save reused material');
            $assert(count($service->get_item_uses($item)) === 2, 'Both content uses retained');
        }
        if (in_array($type, ['ausstellung', 'fuehrung'], true)) {
            $target = iss_content_editorial_sets_event_drop_target_context($type . '__' . get_post($id)->post_name);
            $assert($target['context_id'] === $id, 'Typed upload context resolves for ' . $type);
        }
    }
    foreach ($posts as $source_id) {
        if (!in_array(get_post_type($source_id), iss_content_report_source_types(), true)) { continue; }
        $report_id = iss_content_create_report($source_id);
        $assert(is_int($report_id) && $report_id > 0, 'Create optional report for ' . get_post_type($source_id));
        $posts[] = $report_id;
        $repeated = iss_content_create_report($source_id);
        if (is_int($repeated)) { $posts[] = $repeated; }
        $assert($repeated === $report_id, 'Repeated report action reuses own draft');
        $assert(array_column(iss_content_report_connections($source_id), 'ID') === [$report_id], 'Report linked from source');
        $assert(array_column(iss_content_report_connections($report_id), 'ID') === [$source_id], 'Source linked from report');
        $assert(!iss_content_report_connections($source_id, true), 'Unpublished report stays private');
        $assert(count($service->get_links_for_context('rueckblick', $report_id)) === count($service->get_links_for_context(get_post_type($source_id), $source_id)), 'Report shares source Sets');
        $assert(!iss_content_upload_is_open($source_id), 'New source upload closed');
        iss_content_set_upload_open($source_id, true);
        $key = iss_content_upload_key($source_id);
        $assert(iss_content_upload_authorized($source_id, $key), 'Opened link accepted');
        $assert(!iss_content_upload_authorized($report_id, $key), 'Link cannot target another post');
        iss_content_set_upload_open($source_id, false);
        $assert(!iss_content_upload_authorized($source_id, $key), 'Closed link rejected');
        iss_content_set_upload_open($source_id, true);
        $assert(!iss_content_upload_authorized($source_id, $key), 'Reopening invalidates old link');
    }
    $sources = [$posts[0], $posts[1]];
    $assert(iss_content_report_set_sources($report_id, $sources), 'Report accepts multiple sources');
    $assert(array_column(iss_content_report_connections($report_id), 'ID') === $sources, 'All report sources retained');
    // Public links follow native publication and withdrawal, independently of draft preparation.
    wp_update_post(['ID' => $report_id, 'post_status' => 'publish']);
    $assert(in_array($report_id, array_column(iss_content_report_connections($sources[0], true), 'ID'), true), 'Published report appears at its source');
    $entity = iss_graph_get_entity_for_post($report_id);
    $public_edges = iss_graph_get_service()->get_relations_for_entity((int) $entity['id'], 'reports_on', ['source_system' => 'iss_content_rueckblick', 'public_only' => true]);
    $assert(count($public_edges) === 2, 'Publishing retains both graph targets');
    wp_update_post(['ID' => $report_id, 'post_status' => 'draft']);
    $assert(!iss_content_report_connections($sources[0], true), 'Withdrawn report is hidden again');
    // Return the final report to its exhibition after the separate multi-source test above.
    $assert(iss_content_report_set_sources($report_id, [$posts[4]]), 'Restore exhibition source for public rendering fixture');
    // Real block rendering must not re-enter report-card composition while building an empty excerpt.
    $query_globals = [];
    foreach (['wp_query', 'wp_the_query', 'post'] as $key) { $query_globals[$key] = $GLOBALS[$key] ?? null; }
    $content_guard = static function ($content) {
        if (count(array_filter($GLOBALS['wp_current_filter'] ?? [], static fn($hook) => $hook === 'the_content')) > 6) {
            throw new RuntimeException('Recursive content rendering while generating a related-card excerpt');
        }
        return $content;
    };
    add_filter('the_content', $content_guard, 0);
    try {
        foreach (array_slice($posts, 0, 5) as $source_id) {
            if (!in_array(get_post_type($source_id), iss_content_report_source_types(), true)) { continue; }
            $linked_reports = iss_content_report_connections($source_id);
            $assert(count($linked_reports) === 1, 'One report connected to the rendering fixture');
            $linked_report = $linked_reports[0];
            foreach ([$source_id, $linked_report->ID] as $id) { wp_update_post(['ID' => $id, 'post_status' => 'publish', 'post_excerpt' => '']); }
            foreach ([$source_id, $linked_report->ID] as $id) {
                $query = new WP_Query(['p' => $id, 'post_type' => get_post_type($id)]);
                $GLOBALS['wp_query'] = $query; $GLOBALS['wp_the_query'] = $query;
                $query->the_post();
                $html = do_blocks('<!-- wp:post-content /-->');
                $assert(substr_count($html, '<section class="iss-related-feed iss-container section">') === 1, 'Exactly one report-link section for ' . get_post_type($id));
                $target_id = $id === $source_id ? $linked_report->ID : $source_id;
                $assert(strpos($html, esc_url(get_permalink($target_id))) !== false, 'Linked card remains visible for ' . get_post_type($id));
                $excerpt = get_the_excerpt(get_post($id));
                $assert(strpos($excerpt, 'Rückblicke') === false && strpos($excerpt, 'Dazu gehört dieser Rückblick') === false, 'Excerpt excludes page relationship navigation');
                $query->rewind_posts();
            }
        }
    } finally {
        remove_filter('the_content', $content_guard, 0);
        foreach ($query_globals as $key => $value) { $GLOBALS[$key] = $value; }
        wp_reset_postdata();
    }
    // Pagination reaches later Sets and items; protection checks every row before deletion.
    for ($i = 0; $i < 32; $i++) { $sets[] = $service->create_set(['title' => $prefix . ' page ' . $i]); }
    $page_set = end($sets);
    for ($i = 0; $i < 125; $i++) {
        $page_item = $service->add_item(['set_id' => $page_set, 'kind' => 'wp_media', 'source' => 'wp-media', 'source_id' => (string) (10000000 + $i)]);
    }
    $paged = $service->list_items(['set_id' => $page_set, 'per_page' => 60, 'page' => 3]);
    $assert($paged['total'] === 125 && count($paged['items']) === 5, 'Third item page contains final five rows');
    $assert(count($service->search_sets(['search' => $prefix, 'page' => 2, 'per_page' => 30])['items']) > 0, 'Second Set page is reachable');
    $wpdb->update($service->get_items_table_name(), ['status' => 'promoted'], ['id' => $page_item]);
    $assert(!$service->delete_set_if_safe($page_set), 'Used material blocks Set deletion beyond first page');
    $assert($service->search_sets(['per_page' => 1])['total'] > 1, 'Set pagination returns total');
    $assert($service->list_items(['per_page' => 1])['total'] > 1, 'Item pagination returns total');
    WP_CLI::log("PASS: $checks Sets assertions.");
} finally {
    $_POST = [];
    foreach ($posts as $id) {
        wp_delete_post($id, true);
        $assert(!iss_graph_get_entity_for_post($id), 'Fixture graph entity removed with its post');
    }
    foreach (array_unique($sets) as $id) {
        foreach ([$service->get_audit_table_name(), $service->get_items_table_name(), $service->get_links_table_name()] as $table) { $wpdb->delete($table, ['set_id' => $id], ['%d']); }
        $wpdb->delete($service->get_sets_table_name(), ['id' => $id], ['%d']);
    }
    $assert($before_sets === $wpdb->get_results("SELECT * FROM {$service->get_sets_table_name()} ORDER BY id", ARRAY_A), 'Existing Sets unchanged');
    $assert($before_items === $wpdb->get_results("SELECT * FROM {$service->get_items_table_name()} ORDER BY id", ARRAY_A), 'Existing items unchanged');
    $assert($before_links === $wpdb->get_results("SELECT * FROM {$service->get_links_table_name()} ORDER BY id", ARRAY_A), 'Existing links unchanged');
    WP_CLI::log('PASS: own fixtures removed; existing Sets, items and links unchanged.');
}
