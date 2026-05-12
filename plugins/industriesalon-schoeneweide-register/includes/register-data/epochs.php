<?php

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_REGISTER_EPOCH_SCHEMA_VERSION', '2026-05-11-v2');
define('ISS_REGISTER_EPOCH_SCHEMA_OPTION', 'iss_register_epoch_schema_version');
define('ISS_REGISTER_EPOCH_SNAPSHOT_META_KEY', '_iss_register_epoch_snapshot_latest');
define('ISS_REGISTER_EPOCH_SNAPSHOTS_META_KEY', '_iss_register_epoch_snapshots');
define('ISS_REGISTER_EPOCH_MIGRATED_META_KEY', '_iss_register_epoch_seeded');

final class ISS_Register_Place_Epoch_Service
{
    private static ?ISS_Register_Place_Epoch_Service $instance = null;

    public static function get_instance(): ISS_Register_Place_Epoch_Service
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_register_place_epochs';
    }

    public function maybe_install_schema(): void
    {
        $installed = (string) get_option(ISS_REGISTER_EPOCH_SCHEMA_OPTION, '');
        if ($installed === ISS_REGISTER_EPOCH_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            place_post_id bigint(20) unsigned NOT NULL,
            era_slug varchar(100) NOT NULL,
            function_key varchar(100) NOT NULL,
            phase_name varchar(255) NOT NULL,
            summary text NOT NULL,
            start_year smallint(6) DEFAULT NULL,
            end_year smallint(6) DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            is_current tinyint(1) NOT NULL DEFAULT 0,
            source_confidence varchar(40) NOT NULL DEFAULT 'unknown',
            source_summary text NOT NULL,
            source_links_json longtext NOT NULL,
            media_ids_json longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY place_post_id (place_post_id),
            KEY era_function (era_slug, function_key),
            KEY place_sort (place_post_id, sort_order),
            KEY place_current (place_post_id, is_current),
            KEY chronology (start_year, end_year)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(ISS_REGISTER_EPOCH_SCHEMA_OPTION, ISS_REGISTER_EPOCH_SCHEMA_VERSION, false);
    }

    public function get_function_definitions(): array
    {
        return [
            'industrial' => ['key' => 'industrial', 'label' => 'Industrie / Produktion'],
            'commercial' => ['key' => 'commercial', 'label' => 'Gewerbe / Handel'],
            'culture' => ['key' => 'culture', 'label' => 'Kultur'],
            'education' => ['key' => 'education', 'label' => 'Bildung / Forschung'],
            'community' => ['key' => 'community', 'label' => 'Gemeinwohl / Soziales'],
            'residential' => ['key' => 'residential', 'label' => 'Wohnen'],
            'mixed' => ['key' => 'mixed', 'label' => 'Mischnutzung'],
            'vacant' => ['key' => 'vacant', 'label' => 'Leerstand'],
            'infrastructure' => ['key' => 'infrastructure', 'label' => 'Infrastruktur'],
        ];
    }

    public function get_source_confidence_definitions(): array
    {
        return [
            'unknown' => ['key' => 'unknown', 'label' => 'Unbekannt / nicht bewertet'],
            'oral' => ['key' => 'oral', 'label' => 'Mündliche Quelle'],
            'archive' => ['key' => 'archive', 'label' => 'Archivquelle'],
            'publication' => ['key' => 'publication', 'label' => 'Publikation'],
            'url' => ['key' => 'url', 'label' => 'Webquelle'],
        ];
    }

    public function get_era_definitions(): array
    {
        return function_exists('iss_register_get_atlas_era_definitions')
            ? iss_register_get_atlas_era_definitions()
            : [];
    }

    public function get_epochs_for_place(int $place_post_id): array
    {
        $epochs = $this->get_epochs_for_places([$place_post_id]);

        return $epochs[$place_post_id] ?? [];
    }

    public function get_epochs_for_places(array $place_post_ids): array
    {
        global $wpdb;

        $place_post_ids = array_values(array_filter(array_map('absint', $place_post_ids)));
        if (!$place_post_ids) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($place_post_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name()}
                WHERE place_post_id IN ({$placeholders})
                ORDER BY place_post_id ASC, start_year ASC, sort_order ASC, id ASC",
                $place_post_ids
            ),
            ARRAY_A
        );

        $grouped = [];
        foreach ($place_post_ids as $place_post_id) {
            $grouped[$place_post_id] = [];
        }

        foreach ((array) $rows as $row) {
            $place_post_id = (int) ($row['place_post_id'] ?? 0);
            if ($place_post_id <= 0) {
                continue;
            }

            $grouped[$place_post_id][] = $this->normalize_db_row($row);
        }

        return $grouped;
    }

    public function delete_epochs_for_place(int $place_post_id): void
    {
        global $wpdb;

        if ($place_post_id <= 0) {
            return;
        }

        $wpdb->delete($this->get_table_name(), ['place_post_id' => $place_post_id], ['%d']);
        delete_post_meta($place_post_id, ISS_REGISTER_EPOCH_SNAPSHOT_META_KEY);
        delete_post_meta($place_post_id, ISS_REGISTER_EPOCH_SNAPSHOTS_META_KEY);
    }

    public function save_epochs_for_place(int $place_post_id, array $rows, array $context = [])
    {
        global $wpdb;

        if ($place_post_id <= 0) {
            return new WP_Error('iss_register_epoch_invalid_place', 'Invalid place ID.');
        }

        $existing_rows = [];
        foreach ($this->get_epochs_for_place($place_post_id) as $existing_row) {
            $existing_rows[(int) ($existing_row['id'] ?? 0)] = $existing_row;
        }

        $sanitized_rows = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $sanitized = $this->sanitize_epoch_row($place_post_id, $row, $index);
            if (is_wp_error($sanitized)) {
                return $sanitized;
            }

            $sanitized_rows[] = $sanitized;
        }

        $current_rows = array_values(array_filter($sanitized_rows, static function (array $row): bool {
            return !empty($row['is_current']);
        }));
        if (count($current_rows) > 1) {
            return new WP_Error('iss_register_epoch_multiple_current', 'Only one current epoch is allowed per place.');
        }

        usort($sanitized_rows, static function (array $left, array $right): int {
            $left_year = $left['start_year'] === null ? PHP_INT_MAX : (int) $left['start_year'];
            $right_year = $right['start_year'] === null ? PHP_INT_MAX : (int) $right['start_year'];

            if ($left_year !== $right_year) {
                return $left_year <=> $right_year;
            }

            if ((int) $left['sort_order'] !== (int) $right['sort_order']) {
                return (int) $left['sort_order'] <=> (int) $right['sort_order'];
            }

            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });

        $seen_ids = [];
        foreach ($sanitized_rows as $position => $row) {
            $db_data = $this->prepare_row_for_db($place_post_id, $row, $position);
            $row_id = (int) ($row['id'] ?? 0);

            if ($row_id > 0 && isset($existing_rows[$row_id])) {
                $db_data['created_at'] = (string) ($existing_rows[$row_id]['created_at'] ?? current_time('mysql', true));
                $db_data['updated_at'] = current_time('mysql', true);
                $wpdb->update(
                    $this->get_table_name(),
                    $db_data,
                    ['id' => $row_id, 'place_post_id' => $place_post_id],
                    ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'],
                    ['%d', '%d']
                );
                $seen_ids[] = $row_id;
                continue;
            }

            $wpdb->insert(
                $this->get_table_name(),
                $db_data,
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            $new_id = (int) $wpdb->insert_id;
            if ($new_id > 0) {
                $seen_ids[] = $new_id;
            }
        }

        foreach (array_keys($existing_rows) as $existing_id) {
            if (in_array((int) $existing_id, $seen_ids, true)) {
                continue;
            }

            $wpdb->delete($this->get_table_name(), ['id' => (int) $existing_id, 'place_post_id' => $place_post_id], ['%d', '%d']);
        }

        $saved_rows = $this->get_epochs_for_place($place_post_id);
        $this->write_snapshot_meta($place_post_id, $saved_rows, $context);
        iss_register_clear_places_cache();

        return $saved_rows;
    }

    public function get_counts_by_era_and_function(): array
    {
        global $wpdb;

        $era_counts = [];
        $function_counts = [];

        $era_rows = $wpdb->get_results(
            "SELECT era_slug, COUNT(*) AS total FROM {$this->get_table_name()} GROUP BY era_slug",
            ARRAY_A
        );
        foreach ((array) $era_rows as $row) {
            $slug = sanitize_title((string) ($row['era_slug'] ?? ''));
            if ($slug !== '') {
                $era_counts[$slug] = (int) ($row['total'] ?? 0);
            }
        }

        $function_rows = $wpdb->get_results(
            "SELECT function_key, COUNT(*) AS total FROM {$this->get_table_name()} GROUP BY function_key",
            ARRAY_A
        );
        foreach ((array) $function_rows as $row) {
            $key = sanitize_key((string) ($row['function_key'] ?? ''));
            if ($key !== '') {
                $function_counts[$key] = (int) ($row['total'] ?? 0);
            }
        }

        return [
            'eras' => $era_counts,
            'functions' => $function_counts,
        ];
    }

    public function get_place_ids_for_filters(array $filters): array
    {
        global $wpdb;

        $era_slug = sanitize_title((string) ($filters['era_slug'] ?? ''));
        $function_key = sanitize_key((string) ($filters['function_key'] ?? ''));

        if ($era_slug === '' && $function_key === '') {
            return [];
        }

        $where = [];
        $values = [];

        if ($era_slug !== '') {
            $where[] = 'era_slug = %s';
            $values[] = $era_slug;
        }

        if ($function_key !== '') {
            $where[] = 'function_key = %s';
            $values[] = $function_key;
        }

        $sql = "SELECT DISTINCT place_post_id FROM {$this->get_table_name()} WHERE " . implode(' AND ', $where);
        $prepared = $values ? $wpdb->prepare($sql, $values) : $sql;

        return array_values(array_filter(array_map('absint', (array) $wpdb->get_col($prepared))));
    }

    public function build_seed_epoch_rows(array $place): array
    {
        $history_short = trim((string) ($place['history_short'] ?? $place['excerpt'] ?? ''));
        $history_long = trim((string) ($place['history'] ?? ''));
        $previous_use = trim((string) ($place['vornutzung'] ?? ''));
        $source_summary = trim((string) ($place['sources'] ?? ''));
        $source_links = is_array($place['source_links'] ?? null) ? $place['source_links'] : [];

        $signal = trim(implode("\n", array_filter([$history_short, $history_long, $previous_use, $source_summary])));
        if ($signal === '') {
            return [];
        }

        $era = function_exists('iss_register_detect_atlas_era')
            ? iss_register_detect_atlas_era($place)
            : [];
        $era_slug = sanitize_title((string) ($era['slug'] ?? ''));
        if ($era_slug === '' || !isset($this->get_era_definitions()[$era_slug])) {
            return [];
        }

        return [[
            'id' => 0,
            'era_slug' => $era_slug,
            'function_key' => $this->infer_function_key($place),
            'phase_name' => trim((string) ($place['name'] ?? '')) !== '' ? (string) $place['name'] : 'Historische Phase',
            'summary' => $history_short !== '' ? $history_short : $history_long,
            'start_year' => $this->extract_first_year($signal),
            'end_year' => null,
            'sort_order' => 0,
            'is_current' => 0,
            'source_confidence' => 'unknown',
            'source_summary' => $source_summary,
            'source_links' => $source_links,
            'media_ids' => [],
        ]];
    }

    public function run_seed_migration(array $args = []): array
    {
        $stats = [
            'selected' => 0,
            'seeded' => 0,
            'skipped_existing' => 0,
            'skipped_empty' => 0,
            'errors' => 0,
        ];
        $messages = [];

        $places = function_exists('iss_register_get_place_entities')
            ? iss_register_get_place_entities()
            : [];

        foreach ($places as $place) {
            $post_id = (int) ($place['post_id'] ?? 0);
            if ($post_id <= 0) {
                continue;
            }

            $stats['selected']++;

            if (metadata_exists('post', $post_id, ISS_REGISTER_EPOCH_MIGRATED_META_KEY) || $this->get_epochs_for_place($post_id)) {
                $stats['skipped_existing']++;
                continue;
            }

            $rows = $this->build_seed_epoch_rows($place);
            if (!$rows) {
                $stats['skipped_empty']++;
                continue;
            }

            $saved = $this->save_epochs_for_place($post_id, $rows, [
                'source' => 'seed_migration',
            ]);

            if (is_wp_error($saved)) {
                $stats['errors']++;
                $messages[] = sprintf('Ort %d konnte nicht migriert werden: %s', $post_id, $saved->get_error_message());
                continue;
            }

            update_post_meta($post_id, ISS_REGISTER_EPOCH_MIGRATED_META_KEY, gmdate('c'));
            $stats['seeded']++;
        }

        return [
            'stats' => $stats,
            'messages' => $messages,
        ];
    }

    public function build_export_document(array $places): array
    {
        $place_post_ids = array_values(array_filter(array_map(static function (array $place): int {
            return (int) ($place['post_id'] ?? 0);
        }, $places)));
        $epochs_by_place = $this->get_epochs_for_places($place_post_ids);
        $epochs = [];

        foreach ($place_post_ids as $place_post_id) {
            foreach ((array) ($epochs_by_place[$place_post_id] ?? []) as $epoch) {
                $epochs[] = $epoch;
            }
        }

        return [
            'generatedAt' => gmdate('c'),
            'schemaVersion' => ISS_REGISTER_EPOCH_SCHEMA_VERSION,
            'totalPlaces' => count($places),
            'totalEpochs' => count($epochs),
            'places' => array_values($places),
            'epochs' => array_values($epochs),
        ];
    }

    public function import_export_document(array $document)
    {
        $epochs = isset($document['epochs']) && is_array($document['epochs']) ? $document['epochs'] : [];
        if (!$epochs) {
            return true;
        }

        $grouped = [];
        foreach ($epochs as $epoch) {
            if (!is_array($epoch)) {
                continue;
            }

            $place_post_id = absint($epoch['place_post_id'] ?? 0);
            if ($place_post_id <= 0) {
                continue;
            }

            if (!isset($grouped[$place_post_id])) {
                $grouped[$place_post_id] = [];
            }

            $grouped[$place_post_id][] = $epoch;
        }

        foreach ($grouped as $place_post_id => $rows) {
            $result = $this->save_epochs_for_place((int) $place_post_id, $rows, [
                'source' => 'import',
            ]);

            if (is_wp_error($result)) {
                return $result;
            }
        }

        return true;
    }

    public function get_latest_snapshot(int $place_post_id): array
    {
        $snapshot = get_post_meta($place_post_id, ISS_REGISTER_EPOCH_SNAPSHOT_META_KEY, true);

        return is_array($snapshot) ? $snapshot : [];
    }

    private function sanitize_epoch_row(int $place_post_id, array $row, int $index)
    {
        $era_slug = sanitize_title((string) ($row['era_slug'] ?? ''));
        if ($era_slug === '' || !isset($this->get_era_definitions()[$era_slug])) {
            return new WP_Error('iss_register_epoch_invalid_era', sprintf('Epoch %d has an invalid era.', $index + 1));
        }

        $function_key = sanitize_key((string) ($row['function_key'] ?? ''));
        if ($function_key === '' || !isset($this->get_function_definitions()[$function_key])) {
            return new WP_Error('iss_register_epoch_invalid_function', sprintf('Epoch %d has an invalid function.', $index + 1));
        }

        $phase_name = sanitize_text_field((string) ($row['phase_name'] ?? ''));
        if ($phase_name === '') {
            return new WP_Error('iss_register_epoch_missing_name', sprintf('Epoch %d is missing a phase name.', $index + 1));
        }

        $start_year = $this->normalize_year($row['start_year'] ?? null);
        $end_year = $this->normalize_year($row['end_year'] ?? null);
        if ($start_year !== null && $end_year !== null && $end_year < $start_year) {
            return new WP_Error('iss_register_epoch_invalid_year_range', sprintf('Epoch %d has an end year before its start year.', $index + 1));
        }

        $source_confidence = sanitize_key((string) ($row['source_confidence'] ?? 'unknown'));
        if (!isset($this->get_source_confidence_definitions()[$source_confidence])) {
            $source_confidence = 'unknown';
        }

        return [
            'id' => absint($row['id'] ?? 0),
            'place_post_id' => $place_post_id,
            'era_slug' => $era_slug,
            'function_key' => $function_key,
            'phase_name' => $phase_name,
            'summary' => sanitize_textarea_field((string) ($row['summary'] ?? '')),
            'start_year' => $start_year,
            'end_year' => $end_year,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_current' => !empty($row['is_current']) ? 1 : 0,
            'source_confidence' => $source_confidence,
            'source_summary' => sanitize_textarea_field((string) ($row['source_summary'] ?? '')),
            'source_links' => $this->sanitize_source_links($row['source_links'] ?? []),
            'media_ids' => $this->sanitize_media_ids($row['media_ids'] ?? []),
        ];
    }

    private function prepare_row_for_db(int $place_post_id, array $row, int $position): array
    {
        $now = current_time('mysql', true);

        return [
            'place_post_id' => $place_post_id,
            'era_slug' => (string) $row['era_slug'],
            'function_key' => (string) $row['function_key'],
            'phase_name' => (string) $row['phase_name'],
            'summary' => (string) $row['summary'],
            'start_year' => $row['start_year'],
            'end_year' => $row['end_year'],
            'sort_order' => $row['sort_order'] !== 0 ? (int) $row['sort_order'] : $position,
            'is_current' => !empty($row['is_current']) ? 1 : 0,
            'source_confidence' => (string) $row['source_confidence'],
            'source_summary' => (string) $row['source_summary'],
            'source_links_json' => wp_json_encode(array_values($row['source_links']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'media_ids_json' => wp_json_encode(array_values($row['media_ids']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function normalize_db_row(array $row): array
    {
        $source_links = json_decode((string) ($row['source_links_json'] ?? '[]'), true);
        $media_ids = json_decode((string) ($row['media_ids_json'] ?? '[]'), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'place_post_id' => (int) ($row['place_post_id'] ?? 0),
            'era_slug' => sanitize_title((string) ($row['era_slug'] ?? '')),
            'function_key' => sanitize_key((string) ($row['function_key'] ?? '')),
            'phase_name' => (string) ($row['phase_name'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'start_year' => isset($row['start_year']) && $row['start_year'] !== null ? (int) $row['start_year'] : null,
            'end_year' => isset($row['end_year']) && $row['end_year'] !== null ? (int) $row['end_year'] : null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_current' => !empty($row['is_current']),
            'source_confidence' => sanitize_key((string) ($row['source_confidence'] ?? 'unknown')),
            'source_summary' => (string) ($row['source_summary'] ?? ''),
            'source_links' => $this->sanitize_source_links(is_array($source_links) ? $source_links : []),
            'media_ids' => $this->sanitize_media_ids(is_array($media_ids) ? $media_ids : []),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function sanitize_source_links($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\r\n|\r|\n|,/', $value);
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $url = esc_url_raw(trim((string) $item));
            if ($url !== '' && wp_http_validate_url($url)) {
                $clean[$url] = $url;
            }
        }

        return array_values($clean);
    }

    private function sanitize_media_ids($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\r\n|\r|\n|,/', $value);
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $media_id = absint($item);
            if ($media_id <= 0 || get_post_type($media_id) !== 'attachment') {
                continue;
            }

            $clean[$media_id] = $media_id;
        }

        return array_values($clean);
    }

    private function normalize_year($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $year = (int) $value;
        if ($year <= 0) {
            return null;
        }

        return $year;
    }

    private function write_snapshot_meta(int $place_post_id, array $rows, array $context = []): void
    {
        $snapshot = [
            'saved_at' => gmdate('c'),
            'saved_by' => get_current_user_id(),
            'source' => sanitize_key((string) ($context['source'] ?? 'editor')),
            'count' => count($rows),
            'rows' => array_values($rows),
        ];

        update_post_meta($place_post_id, ISS_REGISTER_EPOCH_SNAPSHOT_META_KEY, $snapshot);

        $history = get_post_meta($place_post_id, ISS_REGISTER_EPOCH_SNAPSHOTS_META_KEY, true);
        if (!is_array($history)) {
            $history = [];
        }

        $history[] = $snapshot;
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }

        update_post_meta($place_post_id, ISS_REGISTER_EPOCH_SNAPSHOTS_META_KEY, array_values($history));
    }

    private function infer_function_key(array $place): string
    {
        $signal = strtolower(trim(implode(' ', array_filter([
            (string) ($place['vornutzung'] ?? ''),
            (string) ($place['history'] ?? ''),
            (string) ($place['current'] ?? ''),
            (string) ($place['branche'] ?? ''),
        ]))));

        $patterns = [
            'industrial' => '/produktion|industrie|fabrik|werk|fertigung/u',
            'commercial' => '/handel|gewerbe|buero|buro|dienstleistung/u',
            'culture' => '/museum|kultur|kunst|ausstellung|atelier/u',
            'education' => '/schule|bildung|hochschule|forschung/u',
            'community' => '/verein|sozial|community|jugend|nachbarschaft/u',
            'residential' => '/wohnen|wohn/u',
            'infrastructure' => '/bahn|strom|versorgung|infrastruktur/u',
            'vacant' => '/leerstand|brach/u',
            'mixed' => '/misch/u',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $signal)) {
                return $key;
            }
        }

        return 'mixed';
    }

    private function extract_first_year(string $text): ?int
    {
        if (!preg_match_all('/\b(18\d{2}|19\d{2}|20\d{2})\b/u', $text, $matches)) {
            return null;
        }

        $years = array_map('intval', (array) ($matches[1] ?? []));

        return $years ? min($years) : null;
    }
}

function iss_register_get_epoch_service(): ISS_Register_Place_Epoch_Service
{
    return ISS_Register_Place_Epoch_Service::get_instance();
}

function iss_register_get_epoch_function_definitions(): array
{
    return iss_register_get_epoch_service()->get_function_definitions();
}

function iss_register_get_epoch_source_confidence_definitions(): array
{
    return iss_register_get_epoch_service()->get_source_confidence_definitions();
}

function iss_register_enrich_place_with_epochs(array $place, array $epochs): array
{
    $era_slugs = array_map(static function (array $epoch): string {
        return sanitize_title((string) ($epoch['era_slug'] ?? ''));
    }, $epochs);
    $function_keys = array_map(static function (array $epoch): string {
        return sanitize_key((string) ($epoch['function_key'] ?? ''));
    }, $epochs);
    $phase_labels = array_map(static function (array $epoch): string {
        return trim((string) ($epoch['phase_name'] ?? ''));
    }, $epochs);

    $place['epochs'] = array_values($epochs);
    $place['has_epochs'] = !empty($epochs);
    $place['available_era_slugs'] = array_values(array_unique(array_filter($era_slugs)));
    $place['available_function_keys'] = array_values(array_unique(array_filter($function_keys)));
    $place['historical_phase_labels'] = array_values(array_filter($phase_labels));
    $place['primary_historical_era_slug'] = $place['available_era_slugs'][0] ?? '';

    return $place;
}

function iss_register_delete_place_epochs_on_before_delete(int $post_id): void
{
    if (get_post_type($post_id) !== ISS_REGISTER_POST_TYPE) {
        return;
    }

    iss_register_get_epoch_service()->delete_epochs_for_place($post_id);
    iss_register_clear_places_cache();
}

add_action('init', static function (): void {
    iss_register_get_epoch_service()->maybe_install_schema();
}, 20);

add_action('before_delete_post', 'iss_register_delete_place_epochs_on_before_delete', 5);
