<?php
/** Shared read-only preservation checks for reviewed Place migrations. */
// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Read-only preservation evidence; identifiers are core/service-owned.
// phpcs:disable WordPress.Security.EscapeOutput -- CLI JSON and controlled diagnostics only.

function iss_register_review_require(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException(esc_html($message)); }
}

function iss_register_review_hash($value): string
{
    return hash('sha256', (string) wp_json_encode($value));
}

function iss_register_review_graph_rows(array $rows, array $targets): array
{
    foreach ($rows as &$row) {
        unset($row['id'], $row['created_at'], $row['updated_at'], $row['edge_key']);
        // iss-relations/core.php projects native relation metadata with
        // source_field=iss_relations and confidence=100. Older projections had
        // the related/0 defaults. Source metadata and every edge remain guarded.
        $post_id = str_starts_with($row['source_ref'], 'post:') ? (int) substr($row['source_ref'], 5) : 0;
        if (isset($targets[$post_id]) && $row['source_system'] === 'iss_relations_meta'
            && $row['relation_status'] === 'derived' && $row['source_field'] === 'related' && $row['confidence'] === '0') {
            $row['source_field'] = 'iss_relations';
            $row['confidence'] = '100';
        }
    }
    unset($row);
    return $rows;
}

/** Hash protected source data; normalize only regenerated projection identities. */
function iss_register_review_protected(array $targets, array $milestone_summaries = []): array
{
    global $wpdb;
    $hashes = [];
    $posts = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE post_type <> %s ORDER BY ID', $wpdb->posts, 'revision'), ARRAY_A);
    $hashes['posts'] = iss_register_review_hash($posts);
    $meta = $wpdb->get_results($wpdb->prepare('SELECT m.* FROM %i m INNER JOIN %i p ON p.ID=m.post_id WHERE p.post_type <> %s ORDER BY m.meta_id', $wpdb->postmeta, $wpdb->posts, 'revision'), ARRAY_A);
    $meta = array_values(array_filter($meta, static function (array $row) use ($targets): bool {
        return $row['meta_key'] !== '_edit_lock' && !in_array($row['meta_key'], $targets[(int) $row['post_id']] ?? [], true);
    }));
    $hashes['protected_metadata'] = iss_register_review_hash($meta);
    foreach (['users' => $wpdb->users, 'usermeta' => $wpdb->usermeta, 'taxonomy_links' => $wpdb->term_relationships,
        'graph_relations' => iss_graph_get_service()->get_relation_table_name()] as $label => $table) {
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i', $table), ARRAY_A);
        if ($label === 'graph_relations') {
            $rows = iss_register_review_graph_rows($rows, $targets);
        }
        $rows = array_map('wp_json_encode', $rows);
        sort($rows);
        $hashes[$label] = iss_register_review_hash($rows);
    }
    $security = [];
    foreach (['home', 'siteurl', 'blog_public', $wpdb->prefix . 'user_roles'] as $name) { $security[$name] = get_option($name); }
    $hashes['site_security'] = iss_register_review_hash($security);
    foreach (['epochs' => iss_register_get_epoch_service()->get_table_name(), 'states' => iss_register_get_place_state_service()->get_table_name()] as $label => $table) {
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i', $table), ARRAY_A);
        $protected = [];
        foreach ($rows as $row) {
            if ($label === 'states' && $row['state_kind'] !== 'historical') { continue; }
            if ($label === 'epochs' && in_array($row['milestone_key'] ?? '', $milestone_summaries[(int) $row['place_post_id']] ?? [], true)) {
                unset($row['summary']);
                $event = json_decode($row['milestone_json'], true, 512, JSON_THROW_ON_ERROR);
                unset($event['summary']);
                $row['milestone_json'] = wp_json_encode($event);
            }
            foreach (['id', 'state_key', 'created_at', 'updated_at'] as $key) { unset($row[$key]); }
            $protected[] = wp_json_encode($row);
        }
        sort($protected);
        $hashes[$label] = ['count' => count($protected), 'hash' => iss_register_review_hash($protected)];
    }
    iss_register_review_require($wpdb->last_error === '', 'Preservation query failed.');
    return $hashes;
}

function iss_register_review_revisions(): array
{
    global $wpdb;
    $result = [];
    foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE post_type = %s ORDER BY ID', $wpdb->posts, 'revision'), ARRAY_A) as $row) {
        $result[$row['ID']] = iss_register_review_hash([$row, get_post_meta((int) $row['ID'])]);
    }
    return $result;
}
