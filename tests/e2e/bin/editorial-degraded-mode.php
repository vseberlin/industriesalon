<?php
/** Run: wp --skip-plugins --skip-themes eval-file /tests/editorial-degraded-mode.php */
if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Connection-local temporary fixture tables.
global $wpdb;
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$assert(!function_exists('iss_editorial_validate_document') && !function_exists('iss_content_model_save_meta_box'), 'Run with all plugins and themes skipped');
if (!defined('DISABLE_WP_CRON')) {
    define('DISABLE_WP_CRON', true);
}
$assert(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron and external cache disabled for isolated fixtures');
require_once WP_PLUGIN_DIR . '/iss-content/iss-content.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
iss_content_model_register_post_types();

$original_user = get_current_user_id();
$original_post = $_POST;
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
$tables = [];
$fixtures = [];
$error = null;
$key = iss_content_model_veranstaltung_content_meta_key();
// Any unexpected write outside the temporary posts/meta tables fails before SQL executes.
$guard = static function (string $sql) use ($wpdb): string {
    if (preg_match('/^\s*(?:INSERT(?: IGNORE)? INTO|REPLACE INTO|UPDATE|DELETE FROM)\s+`?([^`\s]+)/i', $sql, $match)
        && !in_array($match[1], [$wpdb->posts, $wpdb->postmeta], true)) {
        throw new RuntimeException('Unexpected write outside fixture tables: ' . $match[1]);
    }
    return $sql;
};

try {
    foreach ([$wpdb->posts, $wpdb->postmeta] as $table) {
        $schema = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 1);
        $assert($wpdb->query($schema) !== false, 'Create temporary fixture table');
        $tables[] = $table;
    }
    wp_cache_flush();
    add_filter('query', $guard);
    $documents = [
        'v3' => wp_json_encode([
            'schema_version' => 3,
            'entity_key' => 'event.general',
            'skin' => 'typografisch',
            'lead' => 'Über Größe und Nähe',
            'hero_media_ref' => ['source' => 'wp-media', 'id' => 123],
            'sections' => [[
                'id' => 'retained-section', 'type' => 'kapitel', 'treatment' => 'standard',
                'title' => 'Original', 'body' => '<p>Quoted "text" and C:\\source</p>',
                'links' => [['label' => 'Material', 'url' => 'https://example.org/material']],
                'facts' => [['label' => 'Ort', 'value' => 'Berlin']],
            ]],
            'deleted_sections' => [['id' => 'retained-trash', 'type' => 'kapitel', 'title' => 'Recoverable']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'v1' => "{\n  \"schema_version\":1,\"entity_key\":\"event.general\",\"sections\":[{\"type\":\"kapitel\",\"body\":\"<p>Legacy body</p>\"}]\n}",
        'future' => '{"schema_version":999,"future_field":"Keep verbatim"}',
        'malformed' => '{"unfinished":',
        'absent' => null,
    ];
    // Seed before metadata registration, as documents already exist when the engine becomes unavailable.
    foreach ($documents as $label => $document) {
        $id = wp_insert_post(['post_type' => 'veranstaltung', 'post_status' => 'draft', 'post_title' => 'Degraded fixture ' . $label], true);
        $assert(!is_wp_error($id), 'Create event fixture');
        $fixtures[$label] = $id;
        if ($document !== null) {
            $assert((bool) add_post_meta($id, $key, wp_slash($document)), 'Seed original document');
        }
    }
    iss_content_model_register_veranstaltung_content_meta();

    foreach ($fixtures as $label => $id) {
        $original = $documents[$label];
        // Ordinary metadata save, stale v1 form, stale v3 form, empty field and malformed field.
        foreach ([null, '{"schema_version":1,"sections":[]}', $documents['v3'], '', '{broken'] as $stale_document) {
            $raw = ['iss_location' => 'Fixture location', '_iss_entity_key' => 'event.general'];
            if ($stale_document !== null) {
                $raw[$key] = $stale_document;
            }
            $_POST = wp_slash(['iss_content_model_meta_nonce' => wp_create_nonce('iss_content_model_save_meta'), 'iss_content_model' => $raw]);
            $saved = wp_update_post(['ID' => $id, 'post_title' => 'Saved event details ' . $label], true);
            $assert(!is_wp_error($saved) && get_post($id)->post_title === 'Saved event details ' . $label, 'Native event title remains editable');
            $assert(get_post_meta($id, 'iss_location', true) === 'Fixture location', 'Other event metadata remains editable');
            $assert(get_metadata_raw('post', $id, $key, true) === $original, $label . ': ordinary and stale form saves preserve exact document bytes');
        }
        $_POST = [];
        $request = new WP_REST_Request('POST', '/wp/v2/veranstaltung/' . $id);
        $request->set_param('title', 'REST event details ' . $label);
        $response = rest_do_request($request);
        $assert($response->get_status() === 200 && get_post($id)->post_title === 'REST event details ' . $label, 'Native REST event updates still work');
        $assert(!array_key_exists($key, $response->get_data()['meta'] ?? []), 'REST does not expose an editable document field');
        $assert(get_metadata_raw('post', $id, $key, true) === $original, 'Native REST event update preserves the document');
        foreach (['edit_post_meta', 'add_post_meta', 'delete_post_meta'] as $capability) {
            $assert(!current_user_can($capability, $id, $key), 'Document metadata is read-only without the engine');
        }
        foreach (['{"schema_version":1,"sections":[]}', '', null] as $replacement) {
            $request = new WP_REST_Request('POST', '/wp/v2/veranstaltung/' . $id);
            $request->set_param('meta', [$key => $replacement]);
            $response = rest_do_request($request);
            $assert($response->get_status() === 200, 'Native REST ignores metadata that is not exposed');
            $assert(get_metadata_raw('post', $id, $key, true) === $original, $label . ': REST cannot replace or delete the document');
        }
        ob_start();
        iss_content_model_render_veranstaltung_content_box(get_post($id));
        $html = ob_get_clean();
        $assert(str_contains($html, 'ISS Editorial') && !preg_match('/<(input|button|textarea|select)\b/i', $html), 'Fallback explains unavailability without editable controls');
        $assert(get_metadata_raw('post', $id, $key, true) === $original, 'Rendering the fallback never changes stored JSON');
    }

    $legacy = iss_content_model_veranstaltung_content_document($fixtures['v1']);
    $assert(($legacy['sections'][0]['body'] ?? '') === '<p>Legacy body</p>', 'Existing legacy public read remains available');
    $assert(iss_content_model_veranstaltung_content_document($fixtures['v3']) === [], 'Unsupported public read does not downgrade the document');
    $assert(get_metadata_raw('post', $fixtures['v3'], $key, true) === $documents['v3'], 'Public read preserves v3 bytes');
    set_current_screen('veranstaltung');
    do_action('admin_enqueue_scripts', 'post.php');
    $assert(!wp_script_is('iss-content-model-veranstaltung-content', 'enqueued'), 'No legacy editor script loads in degraded mode');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    remove_filter('query', $guard);
    $_POST = $original_post;
    foreach (array_reverse($tables) as $table) {
        $wpdb->query("DROP TEMPORARY TABLE `$table`");
    }
    wp_cache_flush();
    wp_set_current_user($original_user);
}
if ($error) {
    throw $error;
}
WP_CLI::success("Editorial degraded mode: $checks checks passed using temporary tables; real content unchanged.");
