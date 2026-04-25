<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_import_file_path(): string
{
    return ISS_REGISTER_PATH . 'data/import-source.json';
}

function iss_register_decode_import_records(string $raw_json): array
{
    $decoded = json_decode($raw_json, true);
    if (!is_array($decoded)) {
        return [];
    }

    if (array_key_exists('places', $decoded) && is_array($decoded['places'])) {
        $decoded = $decoded['places'];
    }

    return array_values(array_filter($decoded, 'is_array'));
}

function iss_register_get_history_short(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $parts = preg_split('/(?<=[.!?])\s+/', $text);
    if (!is_array($parts) || !$parts) {
        return '';
    }

    return trim((string) $parts[0]);
}

function iss_register_prepare_import_item(array $record): array
{
    $register_id = trim((string) ($record['id'] ?? ''));
    $title = trim((string) ($record['name'] ?? ''));

    $questions = $record['questions'] ?? [];
    if (!is_array($questions)) {
        $questions = [];
    }
    $questions = iss_register_sanitize_meta_array($questions);

    $source_links = [];
    $website = trim((string) ($record['website'] ?? ''));
    if ($website !== '') {
        $source_links[] = $website;
    }

    $history = trim((string) ($record['history'] ?? ''));

    $meta = [
        'register_id' => $register_id,
        'area' => trim((string) ($record['area'] ?? '')),
        'address' => trim((string) ($record['address'] ?? '')),
        'lat' => $record['lat'] ?? '',
        'lng' => $record['lng'] ?? '',
        'coordinates_accuracy' => trim((string) ($record['coordinates_accuracy'] ?? '')),
        'status' => trim((string) ($record['status'] ?? '')),
        'role' => trim((string) ($record['role'] ?? '')),
        'owner' => trim((string) ($record['owner'] ?? '')),
        'operator' => trim((string) ($record['operator'] ?? '')),
        'developer' => trim((string) ($record['developer'] ?? '')),
        'tenant' => trim((string) ($record['tenant'] ?? '')),
        'industry' => trim((string) ($record['branche'] ?? ($record['industry'] ?? ''))),
        'investment' => trim((string) ($record['investment'] ?? '')),
        'size' => trim((string) ($record['size'] ?? '')),
        'jobs' => trim((string) ($record['jobs'] ?? '')),
        'previous_use' => trim((string) ($record['vornutzung'] ?? ($record['previous_use'] ?? ''))),
        'current_use' => trim((string) ($record['current'] ?? ($record['current_use'] ?? ''))),
        'history_short' => iss_register_get_history_short($history),
        'history_long' => $history,
        'research_note' => trim((string) ($record['research_note'] ?? '')),
        'source_summary' => trim((string) ($record['sources'] ?? ($record['source_summary'] ?? ''))),
        'source_links' => $source_links,
        'tags' => iss_register_sanitize_meta_array($record['tags'] ?? []),
        'is_unclear' => (($record['status'] ?? '') === 'unklar') || !empty($record['is_unclear']) ? 1 : 0,
        'sort_order' => (int) preg_replace('/\D+/', '', $register_id),
        'legacy_icon' => trim((string) ($record['icon'] ?? '')),
        'legacy_color' => trim((string) ($record['color'] ?? '')),
        'legacy_website' => $website,
        'legacy_kaufpreis' => trim((string) ($record['kaufpreis'] ?? '')),
        'legacy_questions' => $questions,
    ];

    $normalized = [
        'register_id' => $register_id,
        'title' => $title,
        'content' => trim((string) ($record['current'] ?? '')),
        'meta' => [],
        'source_hash' => md5(wp_json_encode($record)),
    ];

    foreach ($meta as $key => $value) {
        $normalized['meta'][$key] = iss_register_sanitize_meta_value($key, $value);
    }

    return $normalized;
}

function iss_register_find_place_by_register_id(string $register_id): int
{
    $posts = get_posts([
        'post_type' => ISS_REGISTER_POST_TYPE,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => 'register_id',
        'meta_value' => $register_id,
        'suppress_filters' => true,
    ]);

    if (!$posts) {
        return 0;
    }

    return (int) $posts[0];
}

function iss_register_upsert_place(array $item, bool $dry_run, array &$stats, array &$messages): void
{
    $register_id = $item['register_id'];
    if ($register_id === '') {
        $stats['skipped_invalid']++;
        $messages[] = 'Datensatz ohne register_id übersprungen.';
        return;
    }

    $existing_post_id = iss_register_find_place_by_register_id($register_id);
    $existing_hash = $existing_post_id > 0 ? (string) get_post_meta($existing_post_id, '_iss_register_source_hash', true) : '';

    if ($existing_post_id > 0 && $existing_hash !== '' && hash_equals($existing_hash, $item['source_hash'])) {
        $stats['skipped_unchanged']++;
        return;
    }

    if ($dry_run) {
        if ($existing_post_id > 0) {
            $stats['would_update']++;
        } else {
            $stats['would_create']++;
        }
        return;
    }

    $post_data = [
        'post_type' => ISS_REGISTER_POST_TYPE,
        'post_title' => $item['title'] !== '' ? $item['title'] : $register_id,
        'post_content' => $item['content'],
        'post_status' => 'publish',
    ];

    if ($existing_post_id > 0) {
        $post_data['ID'] = $existing_post_id;
        $post_id = wp_update_post($post_data, true);
    } else {
        $post_id = wp_insert_post($post_data, true);
    }

    if (is_wp_error($post_id)) {
        $stats['errors']++;
        $messages[] = 'Fehler bei ' . $register_id . ': ' . $post_id->get_error_message();
        return;
    }

    foreach ($item['meta'] as $key => $value) {
        if (is_array($value) && !$value) {
            delete_post_meta($post_id, $key);
            continue;
        }

        if (!is_array($value) && $value === '') {
            delete_post_meta($post_id, $key);
            continue;
        }

        update_post_meta($post_id, $key, $value);
    }

    update_post_meta($post_id, '_iss_register_source_hash', $item['source_hash']);

    if ($existing_post_id > 0) {
        $stats['updated']++;
    } else {
        $stats['created']++;
    }
}

function iss_register_run_import(array $records, array $options = []): array
{
    $dry_run = !empty($options['dry_run']);
    $stats = [
        'total' => count($records),
        'created' => 0,
        'updated' => 0,
        'would_create' => 0,
        'would_update' => 0,
        'skipped_unchanged' => 0,
        'skipped_invalid' => 0,
        'errors' => 0,
    ];
    $messages = [];

    foreach ($records as $record) {
        $item = iss_register_prepare_import_item($record);
        iss_register_upsert_place($item, $dry_run, $stats, $messages);
    }

    if (!$dry_run) {
        iss_register_clear_places_cache();
    }

    return [
        'dry_run' => $dry_run,
        'stats' => $stats,
        'messages' => $messages,
    ];
}

function iss_register_load_import_records_from_request(array $files): array
{
    $source = 'Bundled JSON';
    $raw = '';

    if (isset($files['import_file']) && is_array($files['import_file']) && (int) ($files['import_file']['error'] ?? 4) === UPLOAD_ERR_OK) {
        $tmp_name = (string) ($files['import_file']['tmp_name'] ?? '');
        if ($tmp_name !== '' && is_uploaded_file($tmp_name)) {
            $raw = (string) file_get_contents($tmp_name);
            $source = 'Upload: ' . sanitize_file_name((string) ($files['import_file']['name'] ?? 'import.json'));
        }
    }

    if ($raw === '') {
        $path = iss_register_get_import_file_path();
        if (!file_exists($path)) {
            return [
                'source' => $source,
                'records' => [],
                'error' => 'Bundled Import-Datei nicht gefunden.',
            ];
        }
        $raw = (string) file_get_contents($path);
    }

    $records = iss_register_decode_import_records($raw);
    if (!$records) {
        return [
            'source' => $source,
            'records' => [],
            'error' => 'JSON konnte nicht gelesen werden oder enthält keine Datensätze.',
        ];
    }

    return [
        'source' => $source,
        'records' => $records,
        'error' => '',
    ];
}

function iss_register_render_import_result(array $result): void
{
    $stats = $result['stats'];
    echo '<h2>Import Ergebnis</h2>';
    echo '<ul>';
    echo '<li>Total: <strong>' . esc_html((string) $stats['total']) . '</strong></li>';
    if (!empty($result['dry_run'])) {
        echo '<li>Would create: <strong>' . esc_html((string) $stats['would_create']) . '</strong></li>';
        echo '<li>Would update: <strong>' . esc_html((string) $stats['would_update']) . '</strong></li>';
    } else {
        echo '<li>Created: <strong>' . esc_html((string) $stats['created']) . '</strong></li>';
        echo '<li>Updated: <strong>' . esc_html((string) $stats['updated']) . '</strong></li>';
    }
    echo '<li>Skipped unchanged: <strong>' . esc_html((string) $stats['skipped_unchanged']) . '</strong></li>';
    echo '<li>Skipped invalid: <strong>' . esc_html((string) $stats['skipped_invalid']) . '</strong></li>';
    echo '<li>Errors: <strong>' . esc_html((string) $stats['errors']) . '</strong></li>';
    echo '</ul>';

    if (!empty($result['messages'])) {
        echo '<h3>Hinweise</h3><ul>';
        foreach ($result['messages'] as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul>';
    }
}

function iss_register_render_import_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    $result = null;
    $error = '';
    $source = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iss_register_import_action']) && $_POST['iss_register_import_action'] === 'run') {
        check_admin_referer('iss_register_run_import', 'iss_register_import_nonce');
        $dry_run = isset($_POST['dry_run']) ? (bool) rest_sanitize_boolean(wp_unslash($_POST['dry_run'])) : false;

        $loaded = iss_register_load_import_records_from_request($_FILES);
        $error = $loaded['error'];
        $source = $loaded['source'];

        if ($error === '') {
            $result = iss_register_run_import($loaded['records'], ['dry_run' => $dry_run]);
        }
    }

    $existing_count = (int) wp_count_posts(ISS_REGISTER_POST_TYPE)->publish;

    echo '<div class="wrap">';
    echo '<h1>Schöneweide Register Import</h1>';
    echo '<p>Importiert JSON in <code>' . esc_html(ISS_REGISTER_POST_TYPE) . '</code>. Bestehende Einträge werden über <code>register_id</code> aktualisiert. Es werden keine Einträge automatisch gelöscht.</p>';
    echo '<p>Aktuell veröffentlichte Register-Orte: <strong>' . esc_html((string) $existing_count) . '</strong></p>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('iss_register_run_import', 'iss_register_import_nonce');
    echo '<input type="hidden" name="iss_register_import_action" value="run">';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row"><label for="import_file">JSON Upload (optional)</label></th><td><input type="file" id="import_file" name="import_file" accept=".json,application/json"></td></tr>';
    echo '<tr><th scope="row"><label for="dry_run">Dry run</label></th><td><label><input type="checkbox" id="dry_run" name="dry_run" value="1" checked> Nur prüfen, nichts speichern</label></td></tr>';
    echo '</tbody></table>';
    submit_button('Import starten');
    echo '</form>';

    if ($source !== '') {
        echo '<p><strong>Quelle:</strong> ' . esc_html($source) . '</p>';
    }

    if ($error !== '') {
        echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
    } elseif (is_array($result)) {
        iss_register_render_import_result($result);
    }

    echo '</div>';
}

add_action('admin_menu', function () {
    add_management_page(
        'Schöneweide Register Import',
        'Schöneweide Register Import',
        'manage_options',
        ISS_REGISTER_IMPORT_PAGE_SLUG,
        'iss_register_render_import_page'
    );
});
