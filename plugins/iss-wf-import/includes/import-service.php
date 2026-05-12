<?php

if (!defined('ABSPATH')) {
    exit;
}

class ISS_WF_Import_Import_Service
{
    /** @var array<string, array<string, string|int>> */
    private array $source_cache = [];

    /** @var array<int, array<string, mixed>|null> */
    private array $record_cache = [];

    public function get_sources_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_sources';
    }

    public function get_source_records_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_source_records';
    }

    public function get_source_snapshots_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_source_snapshots';
    }

    public function get_import_runs_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_import_runs';
    }

    public function maybe_install_schema(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_IMPORT_SCHEMA_OPTION, '');
        if ($stored === ISS_WF_IMPORT_IMPORT_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sources_table = $this->get_sources_table_name();
        $source_records_table = $this->get_source_records_table_name();
        $source_snapshots_table = $this->get_source_snapshots_table_name();
        $import_runs_table = $this->get_import_runs_table_name();

        $sql_sources = "CREATE TABLE {$sources_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_key varchar(100) NOT NULL,
            label varchar(191) NOT NULL,
            source_kind varchar(100) NOT NULL DEFAULT '',
            base_url text NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_key (source_key),
            KEY source_kind (source_kind)
        ) {$charset_collate};";

        $sql_source_records = "CREATE TABLE {$source_records_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_id bigint(20) unsigned NOT NULL,
            object_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            record_identifier varchar(191) NOT NULL,
            record_url text NOT NULL,
            record_url_hash char(64) NOT NULL DEFAULT '',
            record_kind varchar(100) NOT NULL DEFAULT 'archive_object',
            source_system varchar(100) NOT NULL DEFAULT '',
            source_external_id varchar(191) NOT NULL DEFAULT '',
            source_modified_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            last_snapshot_id bigint(20) unsigned NOT NULL DEFAULT 0,
            last_import_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
            first_seen_at datetime NOT NULL,
            last_seen_at datetime NOT NULL,
            last_imported_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_record_lookup (source_id, record_identifier),
            KEY object_post_id (object_post_id),
            KEY record_kind (record_kind),
            KEY source_external_id (source_external_id),
            KEY record_url_hash (record_url_hash)
        ) {$charset_collate};";

        $sql_source_snapshots = "CREATE TABLE {$source_snapshots_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_record_id bigint(20) unsigned NOT NULL,
            snapshot_hash char(64) NOT NULL,
            payload_json longtext NOT NULL,
            parser_version varchar(100) NOT NULL DEFAULT '',
            payload_format varchar(40) NOT NULL DEFAULT 'json',
            fetched_at datetime NOT NULL,
            content_modified_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            import_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
            applied_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            applied_action varchar(40) NOT NULL DEFAULT '',
            applied_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_snapshot_lookup (source_record_id, snapshot_hash),
            KEY source_record_id (source_record_id),
            KEY import_run_id (import_run_id),
            KEY applied_post_id (applied_post_id),
            KEY fetched_at (fetched_at)
        ) {$charset_collate};";

        $sql_import_runs = "CREATE TABLE {$import_runs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_id bigint(20) unsigned NOT NULL,
            started_at datetime NOT NULL,
            finished_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            mode varchar(40) NOT NULL DEFAULT '',
            profile_key varchar(100) NOT NULL DEFAULT '',
            selection_key varchar(40) NOT NULL DEFAULT '',
            family_filter varchar(191) NOT NULL DEFAULT '',
            seed_url text NOT NULL,
            requested_object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            limit_count int(11) NOT NULL DEFAULT 0,
            offset_count int(11) NOT NULL DEFAULT 0,
            stats_json longtext NOT NULL,
            errors_json longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY source_id (source_id),
            KEY started_at (started_at),
            KEY mode (mode)
        ) {$charset_collate};";

        dbDelta($sql_sources);
        dbDelta($sql_source_records);
        dbDelta($sql_source_snapshots);
        dbDelta($sql_import_runs);

        update_option(ISS_WF_IMPORT_IMPORT_SCHEMA_OPTION, ISS_WF_IMPORT_IMPORT_SCHEMA_VERSION, false);
    }

    public function maybe_backfill(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_IMPORT_BACKFILL_OPTION, '');
        if ($stored === ISS_WF_IMPORT_IMPORT_BACKFILL_VERSION) {
            return;
        }

        $this->backfill_all_source_records();
        update_option(ISS_WF_IMPORT_IMPORT_BACKFILL_OPTION, ISS_WF_IMPORT_IMPORT_BACKFILL_VERSION, false);
    }

    public function backfill_all_source_records(): void
    {
        $post_ids = get_posts([
            'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        foreach ($post_ids as $post_id) {
            $this->sync_object_source_record((int) $post_id);
        }
    }

    public function sync_object_source_record(int $post_id): void
    {
        $snapshot = $this->build_object_source_snapshot($post_id);
        if (!$snapshot) {
            return;
        }

        $source_row = $this->ensure_source_row_for_system(
            (string) $snapshot['source_system'],
            (string) $snapshot['source_url'],
            (string) $snapshot['source_site']
        );

        $this->upsert_source_record_row($snapshot, (int) ($source_row['id'] ?? 0));
        unset($this->record_cache[$post_id]);
    }

    public function detach_object_source_records(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        global $wpdb;

        $wpdb->update(
            $this->get_source_records_table_name(),
            [
                'object_post_id' => 0,
                'updated_at' => current_time('mysql', true),
            ],
            ['object_post_id' => $post_id],
            ['%d', '%s'],
            ['%d']
        );

        unset($this->record_cache[$post_id]);
    }

    public function get_source_record_projection_for_post(int $post_id): ?array
    {
        if ($post_id <= 0) {
            return null;
        }

        if (array_key_exists($post_id, $this->record_cache)) {
            return $this->record_cache[$post_id];
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT sr.*, s.source_key, s.label AS source_label, s.source_kind, s.base_url
            FROM {$this->get_source_records_table_name()} sr
            INNER JOIN {$this->get_sources_table_name()} s ON s.id = sr.source_id
            WHERE sr.object_post_id = %d
            ORDER BY sr.id ASC
            LIMIT 1",
            $post_id
        ), ARRAY_A);

        $row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
        $this->record_cache[$post_id] = $row;

        return $row;
    }

    public function get_source_record_projection_by_identity(string $source_key, string $record_identifier): ?array
    {
        $source_key = sanitize_title($source_key);
        $record_identifier = trim($record_identifier);
        if ($source_key === '' || $record_identifier === '') {
            return null;
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT sr.*, s.source_key, s.label AS source_label, s.source_kind, s.base_url
            FROM {$this->get_source_records_table_name()} sr
            INNER JOIN {$this->get_sources_table_name()} s ON s.id = sr.source_id
            WHERE s.source_key = %s
              AND sr.record_identifier = %s
            ORDER BY sr.id ASC
            LIMIT 1",
            $source_key,
            $record_identifier
        ), ARRAY_A);

        return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
    }

    public function get_expected_source_projection_for_post(int $post_id): ?array
    {
        $snapshot = $this->build_object_source_snapshot($post_id);
        if (!$snapshot) {
            return null;
        }

        $source = $this->resolve_source_definition(
            (string) $snapshot['source_system'],
            (string) $snapshot['source_url'],
            (string) $snapshot['source_site']
        );

        return [
            'source_key' => (string) $source['source_key'],
            'source_label' => (string) $source['label'],
            'source_kind' => (string) $source['source_kind'],
            'base_url' => (string) $source['base_url'],
            'object_post_id' => (int) $snapshot['object_post_id'],
            'record_identifier' => (string) $snapshot['record_identifier'],
            'record_url' => (string) $snapshot['record_url'],
            'record_url_hash' => $this->build_url_hash((string) $snapshot['record_url']),
            'record_kind' => (string) $snapshot['record_kind'],
            'source_system' => (string) $snapshot['source_system'],
            'source_external_id' => (string) $snapshot['source_external_id'],
            'source_modified_at' => (string) $snapshot['source_modified_at'],
            'last_imported_at' => (string) $snapshot['last_imported_at'],
        ];
    }

    public function start_import_run(array $args): int
    {
        global $wpdb;

        $started_at = current_time('mysql', true);
        $source_row = $this->ensure_source_row_for_system(
            sanitize_key((string) ($args['source_system'] ?? '')),
            esc_url_raw((string) ($args['seed_url'] ?? '')),
            sanitize_text_field((string) ($args['source_site'] ?? ''))
        );

        $wpdb->insert($this->get_import_runs_table_name(), [
            'source_id' => (int) ($source_row['id'] ?? 0),
            'started_at' => $started_at,
            'finished_at' => '0000-00-00 00:00:00',
            'mode' => sanitize_key((string) ($args['mode'] ?? '')),
            'profile_key' => sanitize_key((string) ($args['profile'] ?? '')),
            'selection_key' => sanitize_key((string) ($args['selection'] ?? '')),
            'family_filter' => $this->truncate_value(sanitize_title((string) ($args['family_filter'] ?? '')), 191),
            'seed_url' => esc_url_raw((string) ($args['seed_url'] ?? '')),
            'requested_object_id' => absint($args['requested_object_id'] ?? 0),
            'limit_count' => max(0, (int) ($args['limit_count'] ?? 0)),
            'offset_count' => max(0, (int) ($args['offset_count'] ?? 0)),
            'stats_json' => '{}',
            'errors_json' => '[]',
            'created_at' => $started_at,
            'updated_at' => $started_at,
        ], [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d',
            '%d', '%s', '%s', '%s', '%s',
        ]);

        return (int) $wpdb->insert_id;
    }

    public function finish_import_run(int $run_id, array $summary, array $rows): void
    {
        if ($run_id <= 0) {
            return;
        }

        global $wpdb;

        $errors = array_values(array_filter($rows, static function ($row) {
            return is_array($row) && (($row['action'] ?? '') === 'error');
        }));

        $wpdb->update(
            $this->get_import_runs_table_name(),
            [
                'finished_at' => current_time('mysql', true),
                'stats_json' => $this->encode_json_object($summary),
                'errors_json' => $this->encode_json_array($errors),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $run_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    public function record_runtime_snapshot(array $args): array
    {
        $timestamp = $this->normalize_datetime_string((string) ($args['fetched_at'] ?? ''));
        if ($timestamp === '0000-00-00 00:00:00') {
            $timestamp = current_time('mysql', true);
        }

        $source_system = sanitize_key((string) ($args['source_system'] ?? ''));
        $source_external_id = sanitize_text_field((string) ($args['source_external_id'] ?? ''));
        $source_url = esc_url_raw((string) ($args['source_url'] ?? ''));
        $record_url = esc_url_raw((string) ($args['record_url'] ?? $source_url));
        $source_site = sanitize_text_field((string) ($args['source_site'] ?? ''));
        $record_kind = sanitize_key((string) ($args['record_kind'] ?? 'archive_object'));
        $local_post_id = absint($args['local_post_id'] ?? 0);
        $content_modified_at = $this->normalize_datetime_string((string) ($args['content_modified_at'] ?? ''));
        $run_id = absint($args['run_id'] ?? 0);
        $record_url = $this->normalize_record_url($source_system, $record_url, $source_external_id, $source_site);

        $snapshot = [
            'object_post_id' => $local_post_id,
            'source_system' => $source_system,
            'source_external_id' => $source_external_id,
            'source_url' => $source_url,
            'record_url' => $record_url,
            'record_kind' => $record_kind !== '' ? $record_kind : 'archive_object',
            'source_site' => $source_site,
            'source_modified_at' => $content_modified_at,
            'first_seen_at' => $timestamp,
            'last_seen_at' => $timestamp,
            'last_imported_at' => '0000-00-00 00:00:00',
            'record_identifier' => $this->build_record_identifier($source_external_id, $record_url, $source_url, $local_post_id),
        ];

        $source_row = $this->ensure_source_row_for_system($source_system, $source_url, $source_site);
        $record_row = $this->upsert_source_record_row($snapshot, (int) ($source_row['id'] ?? 0));

        $payload_json = $this->encode_json_object((array) ($args['payload_bundle'] ?? []));
        $snapshot_hash = hash('sha256', $payload_json);
        $snapshot_row = $this->get_snapshot_row((int) ($record_row['id'] ?? 0), $snapshot_hash);
        if (!$snapshot_row) {
            $snapshot_row = $this->insert_snapshot_row([
                'source_record_id' => (int) ($record_row['id'] ?? 0),
                'snapshot_hash' => $snapshot_hash,
                'payload_json' => $payload_json,
                'parser_version' => $this->truncate_value(sanitize_key((string) ($args['parser_version'] ?? '')), 100),
                'payload_format' => $this->truncate_value(sanitize_key((string) ($args['payload_format'] ?? 'json')), 40),
                'fetched_at' => $timestamp,
                'content_modified_at' => $content_modified_at,
                'import_run_id' => $run_id,
                'created_at' => $timestamp,
            ]);
        }

        return [
            'source_id' => (int) ($source_row['id'] ?? 0),
            'source_record_id' => (int) ($record_row['id'] ?? 0),
            'snapshot_id' => (int) ($snapshot_row['id'] ?? 0),
            'snapshot_hash' => $snapshot_hash,
            'run_id' => $run_id,
        ];
    }

    public function mark_snapshot_applied(array $capture, int $post_id, string $action, string $applied_at): void
    {
        $record_id = absint($capture['source_record_id'] ?? 0);
        $snapshot_id = absint($capture['snapshot_id'] ?? 0);
        if ($record_id <= 0 || $snapshot_id <= 0 || $post_id <= 0) {
            return;
        }

        global $wpdb;

        $applied_at = $this->normalize_datetime_string($applied_at);
        if ($applied_at === '0000-00-00 00:00:00') {
            $applied_at = current_time('mysql', true);
        }

        $run_id = absint($capture['run_id'] ?? 0);

        $wpdb->update(
            $this->get_source_records_table_name(),
            [
                'object_post_id' => $post_id,
                'last_snapshot_id' => $snapshot_id,
                'last_import_run_id' => $run_id,
                'last_seen_at' => $applied_at,
                'last_imported_at' => $applied_at,
                'updated_at' => $applied_at,
            ],
            ['id' => $record_id],
            ['%d', '%d', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        $wpdb->update(
            $this->get_source_snapshots_table_name(),
            [
                'import_run_id' => $run_id,
                'applied_post_id' => $post_id,
                'applied_action' => sanitize_key($action),
                'applied_at' => $applied_at,
            ],
            ['id' => $snapshot_id],
            ['%d', '%d', '%s', '%s'],
            ['%d']
        );

        unset($this->record_cache[$post_id]);
    }

    public function apply_post_import_state(int $post_id, string $snapshot_hash, string $applied_at): void
    {
        if ($post_id <= 0 || trim($snapshot_hash) === '') {
            return;
        }

        $applied_at = $this->normalize_datetime_string($applied_at);
        if ($applied_at === '0000-00-00 00:00:00') {
            $applied_at = current_time('mysql', true);
        }

        update_post_meta($post_id, ISS_WF_IMPORT_HASH_META, $this->truncate_value(sanitize_text_field($snapshot_hash), 191));
        update_post_meta($post_id, ISS_WF_IMPORT_LAST_SYNCED_META, $applied_at);
    }

    public function count_sources(): int
    {
        return $this->count_rows($this->get_sources_table_name());
    }

    public function count_source_records(): int
    {
        return $this->count_rows($this->get_source_records_table_name());
    }

    public function count_source_snapshots(): int
    {
        return $this->count_rows($this->get_source_snapshots_table_name());
    }

    public function count_import_runs(): int
    {
        return $this->count_rows($this->get_import_runs_table_name());
    }

    protected function build_object_source_snapshot(int $post_id): ?array
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
            return null;
        }

        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return null;
        }

        $source_system = sanitize_key((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_KIND_META, true));
        $source_external_id = sanitize_text_field((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META, true));
        $source_url = esc_url_raw((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_URL_META, true));
        $source_site = sanitize_text_field((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_SITE_META, true));
        $record_url = $source_url;
        if ($record_url === '') {
            $record_url = esc_url_raw((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_JSON_URL_META, true));
        }
        $record_url = $this->normalize_record_url($source_system, $record_url, $source_external_id, $source_site);

        if ($source_system === '' && $source_external_id === '' && $record_url === '' && $source_url === '') {
            return null;
        }

        $first_seen_at = $this->normalize_datetime_string((string) $post->post_date_gmt);
        if ($first_seen_at === '0000-00-00 00:00:00') {
            $first_seen_at = current_time('mysql', true);
        }

        return [
            'object_post_id' => $post_id,
            'source_system' => $source_system,
            'source_external_id' => $source_external_id,
            'source_url' => $source_url,
            'record_url' => $record_url,
            'record_kind' => 'archive_object',
            'source_site' => $source_site,
            'source_modified_at' => $this->normalize_datetime_string((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_MODIFIED_GMT_META, true)),
            'first_seen_at' => $first_seen_at,
            'last_seen_at' => current_time('mysql', true),
            'last_imported_at' => $this->normalize_datetime_string((string) get_post_meta($post_id, ISS_WF_IMPORT_LAST_SYNCED_META, true)),
            'record_identifier' => $this->build_record_identifier($source_external_id, $record_url, $source_url, $post_id),
        ];
    }

    /**
     * @return array<string, string|int>
     */
    protected function ensure_source_row_for_system(string $source_system, string $source_url = '', string $source_site = ''): array
    {
        global $wpdb;

        $definition = $this->resolve_source_definition($source_system, $source_url, $source_site);
        $cache_key = (string) $definition['source_key'];
        if (isset($this->source_cache[$cache_key])) {
            return $this->source_cache[$cache_key];
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT *
            FROM {$this->get_sources_table_name()}
            WHERE source_key = %s
            LIMIT 1",
            $cache_key
        ), ARRAY_A);

        $timestamp = current_time('mysql', true);
        if (!is_array($row)) {
            $wpdb->insert($this->get_sources_table_name(), [
                'source_key' => $cache_key,
                'label' => (string) $definition['label'],
                'source_kind' => (string) $definition['source_kind'],
                'base_url' => (string) $definition['base_url'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], ['%s', '%s', '%s', '%s', '%s', '%s']);

            $row = [
                'id' => (int) $wpdb->insert_id,
                'source_key' => $cache_key,
                'label' => (string) $definition['label'],
                'source_kind' => (string) $definition['source_kind'],
                'base_url' => (string) $definition['base_url'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        } else {
            $updates = [];
            if ((string) ($row['label'] ?? '') === '' && $definition['label'] !== '') {
                $updates['label'] = (string) $definition['label'];
            }
            if ((string) ($row['source_kind'] ?? '') === '' && $definition['source_kind'] !== '') {
                $updates['source_kind'] = (string) $definition['source_kind'];
            }
            if ((string) ($row['base_url'] ?? '') === '' && $definition['base_url'] !== '') {
                $updates['base_url'] = (string) $definition['base_url'];
            }

            if ($updates) {
                $updates['updated_at'] = $timestamp;
                $wpdb->update(
                    $this->get_sources_table_name(),
                    $updates,
                    ['id' => (int) $row['id']]
                );
                $row = array_merge($row, $updates);
            }
        }

        $this->source_cache[$cache_key] = is_array($row) ? $row : [];

        return $this->source_cache[$cache_key];
    }

    /**
     * @return array<string, string|int>
     */
    protected function resolve_source_definition(string $source_system, string $source_url = '', string $source_site = ''): array
    {
        $source_system = sanitize_key($source_system);
        $registry = $this->get_source_registry();
        $entry = $registry[$source_system] ?? null;

        $base_url = '';
        if (is_array($entry)) {
            $base_url = (string) ($entry['base_url'] ?? '');
        }

        if ($base_url === '') {
            $base_url = $this->derive_base_url($source_url, $source_site);
        }

        if (is_array($entry)) {
            return [
                'source_key' => (string) ($entry['source_key'] ?? $source_system),
                'label' => (string) ($entry['label'] ?? $source_system),
                'source_kind' => (string) ($entry['source_kind'] ?? 'unknown'),
                'base_url' => $base_url,
            ];
        }

        $host = wp_parse_url($base_url, PHP_URL_HOST);
        $fallback_key = $source_system !== ''
            ? sanitize_title($source_system)
            : sanitize_title((string) ($host ?: 'unknown-source'));
        $fallback_label = $host ? (string) $host : ($source_system !== '' ? $source_system : 'Unknown source');

        return [
            'source_key' => $fallback_key !== '' ? $fallback_key : 'unknown-source',
            'label' => $fallback_label,
            'source_kind' => 'unknown',
            'base_url' => $base_url,
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function get_source_registry(): array
    {
        return [
            'museum-digital-object' => [
                'source_key' => 'museum-digital',
                'label' => 'museum-digital',
                'source_kind' => 'remote_catalog',
                'base_url' => 'https://berlin.museum-digital.de',
            ],
            'museum_digital_object' => [
                'source_key' => 'museum-digital',
                'label' => 'museum-digital',
                'source_kind' => 'remote_catalog',
                'base_url' => 'https://berlin.museum-digital.de',
            ],
            'wf_gallery_object' => [
                'source_key' => 'wf-gallery',
                'label' => 'WF gallery',
                'source_kind' => 'legacy_gallery',
                'base_url' => '',
            ],
            'wf-gallery-object' => [
                'source_key' => 'wf-gallery',
                'label' => 'WF gallery',
                'source_kind' => 'legacy_gallery',
                'base_url' => '',
            ],
            'local-curated-object' => [
                'source_key' => 'local-curated',
                'label' => 'Local curated',
                'source_kind' => 'editorial_local',
                'base_url' => '',
            ],
        ];
    }

    protected function derive_base_url(string $source_url, string $source_site): string
    {
        $source_url = esc_url_raw(trim($source_url));
        if ($source_url !== '') {
            $parts = wp_parse_url($source_url);
            $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : 'https';
            $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
            if ($host !== '') {
                return $scheme . '://' . $host;
            }
        }

        $source_site = trim($source_site);
        if ($source_site === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $source_site)) {
            $parts = wp_parse_url($source_site);
            $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : 'https';
            $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
            if ($host !== '') {
                return $scheme . '://' . $host;
            }
        }

        return 'https://' . strtolower($source_site);
    }

    protected function normalize_record_url(string $source_system, string $record_url, string $source_external_id, string $source_site): string
    {
        $source_system = sanitize_key($source_system);
        $source_external_id = trim($source_external_id);
        $record_url = esc_url_raw(trim($record_url));
        $source_site = trim($source_site);

        if (in_array($source_system, ['museum-digital-object', 'museum_digital_object'], true) && $source_external_id !== '') {
            return 'https://berlin.museum-digital.de/object/' . rawurlencode($source_external_id) . '?navlang=de';
        }

        if ($record_url !== '') {
            return $record_url;
        }

        if ($source_site !== '' && $source_external_id !== '') {
            $base_url = $this->derive_base_url('', $source_site);
            if ($base_url !== '') {
                return trailingslashit($base_url) . 'object/' . rawurlencode($source_external_id);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    protected function upsert_source_record_row(array $snapshot, int $source_row_id): array
    {
        global $wpdb;

        $record_identifier = $this->truncate_value((string) ($snapshot['record_identifier'] ?? ''), 191);
        $object_post_id = absint($snapshot['object_post_id'] ?? 0);
        $record_url = esc_url_raw((string) ($snapshot['record_url'] ?? ''));
        $existing = $this->get_source_record_row($source_row_id, $record_identifier);

        if (!$existing && $object_post_id > 0) {
            $existing = $this->get_source_record_row_by_post_id($object_post_id);
        }

        $timestamp = current_time('mysql', true);
        $last_imported_at = $this->normalize_datetime_string((string) ($snapshot['last_imported_at'] ?? '0000-00-00 00:00:00'));
        $source_modified_at = $this->normalize_datetime_string((string) ($snapshot['source_modified_at'] ?? '0000-00-00 00:00:00'));
        $first_seen_at = $this->normalize_datetime_string((string) ($snapshot['first_seen_at'] ?? ''));
        if ($first_seen_at === '0000-00-00 00:00:00') {
            $first_seen_at = $timestamp;
        }
        $last_seen_at = $this->normalize_datetime_string((string) ($snapshot['last_seen_at'] ?? ''));
        if ($last_seen_at === '0000-00-00 00:00:00') {
            $last_seen_at = $timestamp;
        }

        $data = [
            'source_id' => $source_row_id,
            'object_post_id' => $object_post_id,
            'record_identifier' => $record_identifier,
            'record_url' => $record_url,
            'record_url_hash' => $this->build_url_hash($record_url),
            'record_kind' => $this->truncate_value(sanitize_key((string) ($snapshot['record_kind'] ?? 'archive_object')), 100),
            'source_system' => $this->truncate_value(sanitize_key((string) ($snapshot['source_system'] ?? '')), 100),
            'source_external_id' => $this->truncate_value(sanitize_text_field((string) ($snapshot['source_external_id'] ?? '')), 191),
            'source_modified_at' => $source_modified_at,
            'last_seen_at' => $last_seen_at,
            'updated_at' => $timestamp,
        ];

        if ($existing) {
            $data['first_seen_at'] = $this->normalize_datetime_string((string) ($existing['first_seen_at'] ?? '')) !== '0000-00-00 00:00:00'
                ? (string) $existing['first_seen_at']
                : $first_seen_at;
            $data['last_snapshot_id'] = absint($existing['last_snapshot_id'] ?? 0);
            $data['last_import_run_id'] = absint($existing['last_import_run_id'] ?? 0);
            $data['last_imported_at'] = $last_imported_at !== '0000-00-00 00:00:00'
                ? $last_imported_at
                : $this->normalize_datetime_string((string) ($existing['last_imported_at'] ?? ''));

            $wpdb->update(
                $this->get_source_records_table_name(),
                $data,
                ['id' => (int) $existing['id']],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'],
                ['%d']
            );

            $row = array_merge($existing, $data, ['id' => (int) $existing['id']]);
        } else {
            $data['first_seen_at'] = $first_seen_at;
            $data['last_snapshot_id'] = 0;
            $data['last_import_run_id'] = 0;
            $data['last_imported_at'] = $last_imported_at;
            $data['created_at'] = $timestamp;

            $wpdb->insert($this->get_source_records_table_name(), $data, [
                '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d',
                '%d', '%s', '%s', '%s', '%s',
            ]);

            $row = array_merge($data, ['id' => (int) $wpdb->insert_id]);
        }

        if ($object_post_id > 0) {
            unset($this->record_cache[$object_post_id]);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function get_source_record_row(int $source_id, string $record_identifier): ?array
    {
        if ($source_id <= 0 || $record_identifier === '') {
            return null;
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$this->get_source_records_table_name()}
            WHERE source_id = %d
              AND record_identifier = %s
            LIMIT 1",
            $source_id,
            $record_identifier
        ), ARRAY_A);

        return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function get_source_record_row_by_post_id(int $post_id): ?array
    {
        if ($post_id <= 0) {
            return null;
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$this->get_source_records_table_name()}
            WHERE object_post_id = %d
            ORDER BY id ASC
            LIMIT 1",
            $post_id
        ), ARRAY_A);

        return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function insert_snapshot_row(array $data): array
    {
        global $wpdb;

        $wpdb->insert($this->get_source_snapshots_table_name(), [
            'source_record_id' => absint($data['source_record_id'] ?? 0),
            'snapshot_hash' => $this->truncate_value(sanitize_text_field((string) ($data['snapshot_hash'] ?? '')), 64),
            'payload_json' => (string) ($data['payload_json'] ?? '{}'),
            'parser_version' => $this->truncate_value(sanitize_key((string) ($data['parser_version'] ?? '')), 100),
            'payload_format' => $this->truncate_value(sanitize_key((string) ($data['payload_format'] ?? 'json')), 40),
            'fetched_at' => $this->normalize_datetime_string((string) ($data['fetched_at'] ?? '')),
            'content_modified_at' => $this->normalize_datetime_string((string) ($data['content_modified_at'] ?? '')),
            'import_run_id' => absint($data['import_run_id'] ?? 0),
            'applied_post_id' => 0,
            'applied_action' => '',
            'applied_at' => '0000-00-00 00:00:00',
            'created_at' => $this->normalize_datetime_string((string) ($data['created_at'] ?? '')),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']);

        return [
            'id' => (int) $wpdb->insert_id,
            'source_record_id' => absint($data['source_record_id'] ?? 0),
            'snapshot_hash' => (string) ($data['snapshot_hash'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function get_snapshot_row(int $source_record_id, string $snapshot_hash): ?array
    {
        if ($source_record_id <= 0 || trim($snapshot_hash) === '') {
            return null;
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$this->get_source_snapshots_table_name()}
            WHERE source_record_id = %d
              AND snapshot_hash = %s
            LIMIT 1",
            $source_record_id,
            $snapshot_hash
        ), ARRAY_A);

        return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
    }

    protected function build_record_identifier(string $source_external_id, string $record_url, string $source_url, int $local_post_id): string
    {
        $source_external_id = trim($source_external_id);
        if ($source_external_id !== '') {
            return $this->truncate_value($source_external_id, 191);
        }

        $record_url = esc_url_raw(trim($record_url));
        if ($record_url !== '') {
            return $this->truncate_value('url:' . hash('sha256', $record_url), 191);
        }

        $source_url = esc_url_raw(trim($source_url));
        if ($source_url !== '') {
            return $this->truncate_value('source-url:' . hash('sha256', $source_url), 191);
        }

        if ($local_post_id > 0) {
            return 'post:' . $local_post_id;
        }

        return 'unresolved';
    }

    protected function build_url_hash(string $url): string
    {
        $url = esc_url_raw(trim($url));
        if ($url === '') {
            return '';
        }

        return hash('sha256', $url);
    }

    /**
     * @param array<mixed> $value
     */
    protected function encode_json_array(array $value): string
    {
        $json = wp_json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) && $json !== '' ? $json : '[]';
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function encode_json_object(array $value): string
    {
        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) && $json !== '' ? $json : '{}';
    }

    protected function normalize_datetime_string(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '0000-00-00 00:00:00';
        }

        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)
            ? $value
            : '0000-00-00 00:00:00';
    }

    protected function truncate_value(string $value, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }

    protected function count_rows(string $table_name): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }
}

function iss_wf_import_get_import_service(): ISS_WF_Import_Import_Service
{
    static $service = null;

    if (!$service instanceof ISS_WF_Import_Import_Service) {
        $service = new ISS_WF_Import_Import_Service();
    }

    return $service;
}

function iss_wf_import_maybe_install_import_schema(): void
{
    iss_wf_import_get_import_service()->maybe_install_schema();
}
add_action('init', 'iss_wf_import_maybe_install_import_schema', 33);

function iss_wf_import_maybe_backfill_import_tables(): void
{
    iss_wf_import_get_import_service()->maybe_backfill();
}
add_action('init', 'iss_wf_import_maybe_backfill_import_tables', 34);

function iss_wf_import_sync_object_source_record_on_save(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_wf_import_get_import_service()->sync_object_source_record($post_id);
}
add_action('save_post', 'iss_wf_import_sync_object_source_record_on_save', 34, 2);

function iss_wf_import_detach_object_source_records_on_before_delete(int $post_id): void
{
    if (get_post_type($post_id) !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    iss_wf_import_get_import_service()->detach_object_source_records($post_id);
}
add_action('before_delete_post', 'iss_wf_import_detach_object_source_records_on_before_delete', 3);

if (defined('WP_CLI') && WP_CLI) {
    class ISS_WF_Import_Import_CLI_Command
    {
        public function verify(): void
        {
            $service = iss_wf_import_get_import_service();
            $post_ids = get_posts([
                'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
            ]);

            $errors = [];
            $expected_by_identity = [];
            $post_ids_by_identity = [];

            foreach ($post_ids as $post_id) {
                $post = get_post((int) $post_id);
                if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
                    continue;
                }

                $expected = $service->get_expected_source_projection_for_post((int) $post_id);
                if (!$expected) {
                    continue;
                }

                $identity = (string) $expected['source_key'] . '|' . (string) $expected['record_identifier'];
                if (!isset($expected_by_identity[$identity])) {
                    $expected_by_identity[$identity] = $expected;
                }

                if (!isset($post_ids_by_identity[$identity])) {
                    $post_ids_by_identity[$identity] = [];
                }

                $post_ids_by_identity[$identity][] = (int) $post_id;
            }

            $verified_records = 0;
            $duplicate_posts = 0;

            foreach ($expected_by_identity as $identity => $expected) {
                $actual = $service->get_source_record_projection_by_identity(
                    (string) ($expected['source_key'] ?? ''),
                    (string) ($expected['record_identifier'] ?? '')
                );
                if (!$actual) {
                    $post_list = implode(',', array_map('intval', $post_ids_by_identity[$identity] ?? []));
                    $errors[] = sprintf('Source identity %s has no canonical source record. Local posts: %s.', $identity, $post_list);
                    continue;
                }

                $identity_post_ids = $post_ids_by_identity[$identity] ?? [];
                if (count($identity_post_ids) > 1) {
                    $duplicate_posts += count($identity_post_ids) - 1;
                }

                foreach ([
                    'source_key',
                    'source_kind',
                    'record_identifier',
                    'record_url',
                    'record_url_hash',
                    'record_kind',
                    'source_external_id',
                ] as $field) {
                    $expected_value = (string) ($expected[$field] ?? '');
                    $actual_value = (string) ($actual[$field] ?? '');
                    if ($expected_value !== $actual_value) {
                        $post_list = implode(',', array_map('intval', $identity_post_ids));
                        $errors[] = sprintf('Source identity %s field %s does not match canonical record. Local posts: %s.', $identity, $field, $post_list);
                        continue 2;
                    }
                }

                $verified_records++;
            }

            if ($errors) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('Source verification failed.');
            }

            \WP_CLI::success(sprintf(
                'Verified %d unique source identities across %d sources, %d snapshots, and %d import runs. Duplicate local posts: %d.',
                $verified_records,
                $service->count_sources(),
                $service->count_source_snapshots(),
                $service->count_import_runs(),
                $duplicate_posts
            ));
        }
    }

    \WP_CLI::add_command('iss-archive sources-verify', ['ISS_WF_Import_Import_CLI_Command', 'verify']);
}
