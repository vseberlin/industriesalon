<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- This service owns and queries the ISS occurrence projection tables.

final class ISS_Occurrences_Service
{
    private static ?ISS_Occurrences_Service $instance = null;

    public static function get_instance(): ISS_Occurrences_Service
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get_occurrences_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_occurrences';
    }

    public function get_series_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_occurrence_series';
    }

    public function maybe_install_schema(): void
    {
        $installed = (string) get_option(ISS_OCCURRENCES_SCHEMA_OPTION, '');
        if ($installed === ISS_OCCURRENCES_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $occurrences_table = $this->get_occurrences_table_name();
        $series_table = $this->get_series_table_name();

        $occurrences_sql = "CREATE TABLE {$occurrences_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_post_type varchar(100) NOT NULL DEFAULT '',
            kind varchar(50) NOT NULL DEFAULT '',
            title varchar(255) NOT NULL DEFAULT '',
            starts_at datetime NOT NULL,
            ends_at datetime DEFAULT NULL,
            date_source varchar(50) NOT NULL DEFAULT 'explicit',
            status varchar(50) NOT NULL DEFAULT 'active',
            visibility varchar(50) NOT NULL DEFAULT 'public',
            origin varchar(50) NOT NULL DEFAULT 'wp',
            source_calendar varchar(191) NOT NULL DEFAULT '',
            external_id varchar(191) NOT NULL DEFAULT '',
            tag varchar(100) NOT NULL DEFAULT '',
            series_key varchar(191) NOT NULL DEFAULT '',
            series_id bigint(20) unsigned NOT NULL DEFAULT 0,
            booking_url varchar(255) NOT NULL DEFAULT '',
            location_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            location_entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
            location_label varchar(255) NOT NULL DEFAULT '',
            availability_state varchar(50) NOT NULL DEFAULT '',
            capacity_total int(11) NOT NULL DEFAULT -1,
            capacity_available int(11) NOT NULL DEFAULT -1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY origin_external (origin, external_id),
            KEY public_date (visibility, status, starts_at),
            KEY entity_date (entity_id, starts_at),
            KEY source_lookup (source_post_type, source_post_id),
            KEY location_entity_date (location_entity_id, starts_at),
            KEY kind_date (kind, starts_at),
            KEY series_date (series_key, starts_at),
            KEY tag_date (tag, starts_at),
            KEY external_lookup (external_id, origin)
        ) {$charset_collate};";

        $series_sql = "CREATE TABLE {$series_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_post_type varchar(100) NOT NULL DEFAULT '',
            origin varchar(50) NOT NULL DEFAULT 'supersaas',
            external_id varchar(191) NOT NULL DEFAULT '',
            series_key varchar(191) NOT NULL DEFAULT '',
            rule text NOT NULL,
            timezone varchar(100) NOT NULL DEFAULT '',
            exceptions longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY series_key (series_key),
            KEY source_lookup (source_post_type, source_post_id),
            KEY external_lookup (origin, external_id)
        ) {$charset_collate};";

        dbDelta($occurrences_sql);
        dbDelta($series_sql);
        update_option(ISS_OCCURRENCES_SCHEMA_OPTION, ISS_OCCURRENCES_SCHEMA_VERSION, false);
    }

    public function tables_exist(): bool
    {
        global $wpdb;

        $occurrences_table = $this->get_occurrences_table_name();
        $series_table = $this->get_series_table_name();

        $occurrences_found = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $occurrences_table));
        $series_found = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $series_table));

        return $occurrences_found === $occurrences_table && $series_found === $series_table;
    }

    public function public_row_count(): int
    {
        global $wpdb;

        if (!$this->tables_exist()) {
            return 0;
        }

        $table = $this->get_occurrences_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE visibility = %s AND status = %s",
                'public',
                'active'
            )
        );
    }

    public function public_graph_link_count(): int
    {
        global $wpdb;

        if (!$this->tables_exist()) {
            return 0;
        }

        $table = $this->get_occurrences_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE visibility = %s AND status = %s AND entity_id > 0",
                'public',
                'active'
            )
        );
    }

    public function resolve_graph_entity_id_for_post(int $post_id, bool $create = false): int
    {
        $post_id = max(0, $post_id);
        if ($post_id <= 0) {
            return 0;
        }

        if ($create && function_exists('iss_graph_get_or_create_entity_for_post')) {
            $entity = iss_graph_get_or_create_entity_for_post($post_id);
            if (is_array($entity) && (int) ($entity['id'] ?? 0) > 0) {
                return (int) $entity['id'];
            }
        }

        if (function_exists('iss_graph_get_entity_for_post')) {
            $entity = iss_graph_get_entity_for_post($post_id);
            if (is_array($entity) && (int) ($entity['id'] ?? 0) > 0) {
                return (int) $entity['id'];
            }
        }

        return 0;
    }

    public function backfill_graph_entities(): int
    {
        global $wpdb;

        if (!$this->tables_exist()) {
            return 0;
        }

        $table = $this->get_occurrences_table_name();
        $rows = $wpdb->get_results(
            "SELECT id, entity_id, source_post_id, location_entity_id, location_post_id
            FROM {$table}
            WHERE source_post_id > 0
               OR location_post_id > 0",
            ARRAY_A
        );

        $updated = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $occurrence_id = (int) ($row['id'] ?? 0);
            if ($occurrence_id <= 0) {
                continue;
            }

            $fields = [];
            $formats = [];
            $source_post_id = (int) ($row['source_post_id'] ?? 0);
            $entity_id = $source_post_id > 0 ? $this->resolve_graph_entity_id_for_post($source_post_id, true) : 0;
            if ($entity_id > 0 && $entity_id !== (int) ($row['entity_id'] ?? 0)) {
                $fields['entity_id'] = $entity_id;
                $formats[] = '%d';
            }

            $location_post_id = (int) ($row['location_post_id'] ?? 0);
            $location_entity_id = $location_post_id > 0 ? $this->resolve_graph_entity_id_for_post($location_post_id, true) : 0;
            if ($location_entity_id > 0 && $location_entity_id !== (int) ($row['location_entity_id'] ?? 0)) {
                $fields['location_entity_id'] = $location_entity_id;
                $formats[] = '%d';
            }

            if (empty($fields)) {
                continue;
            }

            $fields['updated_at'] = current_time('mysql');
            $formats[] = '%s';

            $result = $wpdb->update(
                $table,
                $fields,
                ['id' => $occurrence_id],
                $formats,
                ['%d']
            );

            if ($result !== false && $result > 0) {
                $updated++;
            }
        }

        if ($updated > 0) {
            do_action('iss_occurrences_changed', ['origin' => 'graph_backfill']);
        }

        return $updated;
    }

    public function upsert_series(array $row): int
    {
        global $wpdb;

        $series_key = isset($row['series_key']) ? sanitize_text_field((string) $row['series_key']) : '';
        if ($series_key === '') {
            return 0;
        }

        $table = $this->get_series_table_name();
        $now = current_time('mysql');
        $source_post_id = isset($row['source_post_id']) ? max(0, (int) $row['source_post_id']) : 0;
        $source_post_type = isset($row['source_post_type']) ? sanitize_key((string) $row['source_post_type']) : '';
        $origin = isset($row['origin']) ? sanitize_key((string) $row['origin']) : 'supersaas';
        $external_id = isset($row['external_id']) ? sanitize_text_field((string) $row['external_id']) : '';
        $timezone = isset($row['timezone']) ? sanitize_text_field((string) $row['timezone']) : wp_timezone_string();
        $rule = isset($row['rule']) ? sanitize_textarea_field((string) $row['rule']) : '';
        $exceptions = isset($row['exceptions']) ? wp_json_encode($row['exceptions']) : '';
        $exceptions = is_string($exceptions) ? $exceptions : '';

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (source_post_id, source_post_type, origin, external_id, series_key, rule, timezone, exceptions, created_at, updated_at)
                 VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    source_post_id = VALUES(source_post_id),
                    source_post_type = VALUES(source_post_type),
                    origin = VALUES(origin),
                    external_id = VALUES(external_id),
                    rule = VALUES(rule),
                    timezone = VALUES(timezone),
                    exceptions = VALUES(exceptions),
                    updated_at = VALUES(updated_at)",
                $source_post_id,
                $source_post_type,
                $origin,
                $external_id,
                $series_key,
                $rule,
                $timezone,
                $exceptions,
                $now,
                $now
            )
        );

        return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE series_key = %s LIMIT 1", $series_key));
    }

    public function upsert_occurrence(array $row): int
    {
        global $wpdb;

        $starts_at = isset($row['starts_at']) ? $this->normalize_datetime((string) $row['starts_at']) : '';
        if ($starts_at === '') {
            return 0;
        }

        $source_post_id = isset($row['source_post_id']) ? max(0, (int) $row['source_post_id']) : 0;
        $source_post_type = isset($row['source_post_type']) ? sanitize_key((string) $row['source_post_type']) : '';
        $entity_id = isset($row['entity_id']) ? max(0, (int) $row['entity_id']) : 0;
        $kind = isset($row['kind']) ? sanitize_key((string) $row['kind']) : '';
        $origin = isset($row['origin']) ? sanitize_key((string) $row['origin']) : 'wp';
        $external_id = isset($row['external_id']) ? sanitize_text_field((string) $row['external_id']) : '';
        if ($external_id === '' && $source_post_id > 0 && $source_post_type !== '') {
            $external_id = $origin . ':' . $source_post_type . ':' . $source_post_id;
        }
        if ($external_id === '') {
            return 0;
        }

        $series_key = isset($row['series_key']) ? sanitize_text_field((string) $row['series_key']) : '';
        $series_id = $series_key !== '' ? $this->upsert_series($row) : 0;
        $ends_at = isset($row['ends_at']) ? $this->normalize_datetime((string) $row['ends_at'], true) : null;
        $ends_at = $ends_at !== '' ? $ends_at : null;
        $location_post_id = isset($row['location_post_id']) ? max(0, (int) $row['location_post_id']) : 0;
        $location_entity_id = isset($row['location_entity_id']) ? max(0, (int) $row['location_entity_id']) : 0;
        $now = current_time('mysql');
        $table = $this->get_occurrences_table_name();

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (entity_id, source_post_id, source_post_type, kind, title, starts_at, ends_at, date_source, status, visibility, origin, source_calendar, external_id, tag, series_key, series_id, booking_url, location_post_id, location_entity_id, location_label, availability_state, capacity_total, capacity_available, created_at, updated_at)
                 VALUES (%d, %d, %s, %s, %s, %s, NULLIF(%s, ''), %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %d, %d, %s, %s, %d, %d, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    entity_id = VALUES(entity_id),
                    source_post_id = VALUES(source_post_id),
                    source_post_type = VALUES(source_post_type),
                    kind = VALUES(kind),
                    title = VALUES(title),
                    starts_at = VALUES(starts_at),
                    ends_at = VALUES(ends_at),
                    date_source = VALUES(date_source),
                    status = VALUES(status),
                    visibility = VALUES(visibility),
                    source_calendar = VALUES(source_calendar),
                    tag = VALUES(tag),
                    series_key = VALUES(series_key),
                    series_id = VALUES(series_id),
                    booking_url = VALUES(booking_url),
                    location_post_id = VALUES(location_post_id),
                    location_entity_id = VALUES(location_entity_id),
                    location_label = VALUES(location_label),
                    availability_state = VALUES(availability_state),
                    capacity_total = VALUES(capacity_total),
                    capacity_available = VALUES(capacity_available),
                    updated_at = VALUES(updated_at)",
                $entity_id,
                $source_post_id,
                $source_post_type,
                $kind,
                isset($row['title']) ? sanitize_text_field((string) $row['title']) : '',
                $starts_at,
                $ends_at ?? '',
                isset($row['date_source']) ? sanitize_key((string) $row['date_source']) : 'explicit',
                isset($row['status']) ? sanitize_key((string) $row['status']) : 'active',
                isset($row['visibility']) ? sanitize_key((string) $row['visibility']) : 'public',
                $origin,
                isset($row['source_calendar']) ? sanitize_text_field((string) $row['source_calendar']) : '',
                $external_id,
                isset($row['tag']) ? strtoupper(sanitize_text_field((string) $row['tag'])) : '',
                $series_key,
                $series_id,
                isset($row['booking_url']) ? esc_url_raw((string) $row['booking_url']) : '',
                $location_post_id,
                $location_entity_id,
                isset($row['location_label']) ? sanitize_text_field((string) $row['location_label']) : '',
                isset($row['availability_state']) ? sanitize_key((string) $row['availability_state']) : '',
                array_key_exists('capacity_total', $row) && $row['capacity_total'] !== null ? (int) $row['capacity_total'] : -1,
                array_key_exists('capacity_available', $row) && $row['capacity_available'] !== null ? (int) $row['capacity_available'] : -1,
                $now,
                $now
            )
        );

        do_action('iss_occurrences_changed', ['origin' => $origin, 'source_post_id' => $source_post_id]);

        return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE origin = %s AND external_id = %s LIMIT 1", $origin, $external_id));
    }

    public function get_occurrence_by_external(string $origin, string $external_id): array
    {
        global $wpdb;

        $origin = sanitize_key($origin);
        $external_id = sanitize_text_field($external_id);
        if ($origin === '' || $external_id === '' || !$this->tables_exist()) {
            return [];
        }

        $table = $this->get_occurrences_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE origin = %s AND external_id = %s LIMIT 1",
                $origin,
                $external_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : [];
    }

    public function delete_source_occurrences(int $source_post_id, string $source_post_type = '', string $origin = ''): int
    {
        global $wpdb;

        $source_post_id = max(0, $source_post_id);
        if ($source_post_id <= 0) {
            return 0;
        }

        $table = $this->get_occurrences_table_name();
        $where = ['source_post_id = %d'];
        $values = [$source_post_id];

        $source_post_type = sanitize_key($source_post_type);
        if ($source_post_type !== '') {
            $where[] = 'source_post_type = %s';
            $values[] = $source_post_type;
        }

        $origin = sanitize_key($origin);
        if ($origin !== '') {
            $where[] = 'origin = %s';
            $values[] = $origin;
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $where);
        $deleted = (int) $wpdb->query($wpdb->prepare($sql, $values));

        if ($deleted > 0) {
            do_action('iss_occurrences_changed', ['source_post_id' => $source_post_id]);
        }

        return $deleted;
    }

    public function delete_occurrence_by_external(string $origin, string $external_id): int
    {
        global $wpdb;

        $origin = sanitize_key($origin);
        $external_id = sanitize_text_field($external_id);
        if ($origin === '' || $external_id === '') {
            return 0;
        }

        $table = $this->get_occurrences_table_name();
        $deleted = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE origin = %s AND external_id = %s",
                $origin,
                $external_id
            )
        );

        if ($deleted > 0) {
            do_action('iss_occurrences_changed', ['origin' => $origin, 'external_id' => $external_id]);
        }

        return $deleted;
    }

    public function mark_missing_origin_future_inactive(string $origin, string $source_calendar, array $seen_external_ids): int
    {
        global $wpdb;

        $origin = sanitize_key($origin);
        $source_calendar = sanitize_text_field($source_calendar);
        if ($origin === '' || $source_calendar === '' || !$this->tables_exist()) {
            return 0;
        }

        $seen_external_ids = array_values(array_unique(array_filter(array_map(static function ($value) {
            return sanitize_text_field((string) $value);
        }, $seen_external_ids))));

        $table = $this->get_occurrences_table_name();
        $where = ['origin = %s', 'source_calendar = %s', 'starts_at >= %s'];
        $values = [$origin, $source_calendar, current_time('mysql')];

        if (!empty($seen_external_ids)) {
            $placeholders = implode(', ', array_fill(0, count($seen_external_ids), '%s'));
            $where[] = "external_id NOT IN ({$placeholders})";
            foreach ($seen_external_ids as $external_id) {
                $values[] = $external_id;
            }
        }

        $values[] = 'inactive';
        $values[] = current_time('mysql');
        $sql = "UPDATE {$table} SET status = %s, updated_at = %s WHERE " . implode(' AND ', $where);
        $update_values = array_merge(array_slice($values, -2), array_slice($values, 0, -2));
        $updated = (int) $wpdb->query($wpdb->prepare($sql, $update_values));

        if ($updated > 0) {
            do_action('iss_occurrences_changed', ['origin' => $origin, 'source_calendar' => $source_calendar]);
        }

        return $updated;
    }

    public function mark_origin_past_active(string $origin, string $source_calendar): int
    {
        global $wpdb;

        $origin = sanitize_key($origin);
        $source_calendar = sanitize_text_field($source_calendar);
        if ($origin === '' || $source_calendar === '' || !$this->tables_exist()) {
            return 0;
        }

        $table = $this->get_occurrences_table_name();
        $updated = (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} o INNER JOIN {$wpdb->posts} p ON p.ID = o.source_post_id SET o.status = %s, o.updated_at = %s WHERE o.origin = %s AND o.source_calendar = %s AND o.visibility = %s AND o.source_post_type = %s AND o.starts_at < %s AND o.status <> %s AND p.post_type = %s AND p.post_status = %s",
                'active',
                current_time('mysql'),
                $origin,
                $source_calendar,
                'public',
                'fuehrung',
                current_time('mysql'),
                'active',
                'fuehrung',
                'publish'
            )
        );

        if ($updated > 0) {
            do_action('iss_occurrences_changed', ['origin' => $origin, 'source_calendar' => $source_calendar]);
        }

        return $updated;
    }

    public function backfill_supersaas_metadata(string $source_calendar): int
    {
        global $wpdb;

        $source_calendar = sanitize_text_field($source_calendar);
        if (!$this->tables_exist()) {
            return 0;
        }

        $table = $this->get_occurrences_table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, source_post_id, source_post_type, title, source_calendar, tag, series_key
                 FROM {$table}
                 WHERE origin = %s AND kind = %s",
                'supersaas',
                'tour'
            ),
            ARRAY_A
        );
        $rows = is_array($rows) ? $rows : [];

        $updated = 0;
        foreach ($rows as $row) {
            $occurrence_id = (int) ($row['id'] ?? 0);
            $source_post_id = (int) ($row['source_post_id'] ?? 0);
            if ($occurrence_id <= 0 || $source_post_id <= 0) {
                continue;
            }

            $post = get_post($source_post_id);
            if (!$post instanceof WP_Post || $post->post_type !== 'fuehrung') {
                continue;
            }

            $fields = [];
            $formats = [];

            if (trim((string) ($row['title'] ?? '')) === '') {
                $fields['title'] = get_the_title($source_post_id);
                $formats[] = '%s';
            }

            if ($source_calendar !== '' && trim((string) ($row['source_calendar'] ?? '')) === '') {
                $fields['source_calendar'] = $source_calendar;
                $formats[] = '%s';
            }

            if (trim((string) ($row['tag'] ?? '')) === '') {
                $tag = '';
                $series_key = isset($row['series_key']) ? (string) $row['series_key'] : '';
                $series_entry = function_exists('iss_occurrences_get_series_map_entry')
                    ? iss_occurrences_get_series_map_entry($series_key)
                    : null;
                if (is_array($series_entry) && !empty($series_entry['tag'])) {
                    $tag = (string) $series_entry['tag'];
                }
                if ($tag === '' && function_exists('iss_occurrences_resolve_tag_for_source_post_id')) {
                    $tag = iss_occurrences_resolve_tag_for_source_post_id($source_post_id);
                }
                $tag = function_exists('iss_occurrences_normalize_tag')
                    ? iss_occurrences_normalize_tag($tag)
                    : strtoupper(sanitize_text_field((string) $tag));

                if ($tag !== '') {
                    $fields['tag'] = $tag;
                    $formats[] = '%s';
                }
            }

            if (empty($fields)) {
                continue;
            }

            $fields['updated_at'] = current_time('mysql');
            $formats[] = '%s';

            $result = $wpdb->update(
                $table,
                $fields,
                ['id' => $occurrence_id],
                $formats,
                ['%d']
            );
            if ($result !== false && $result > 0) {
                $updated++;
            }
        }

        if ($updated > 0) {
            do_action('iss_occurrences_changed', ['origin' => 'supersaas']);
        }

        return $updated;
    }

    public function query(array $filters = []): array
    {
        global $wpdb;

        if (!$this->tables_exist()) {
            return [];
        }

        $filters = $this->normalize_query_filters($filters);
        $table = $this->get_occurrences_table_name();
        $where = ['o.visibility = %s', 'o.status = %s'];
        $values = ['public', 'active'];

        if (!empty($filters['kinds'])) {
            $placeholders = implode(', ', array_fill(0, count($filters['kinds']), '%s'));
            $where[] = "o.kind IN ({$placeholders})";
            foreach ($filters['kinds'] as $kind) {
                $values[] = $kind;
            }
        }

        if (!empty($filters['post_types'])) {
            $placeholders = implode(', ', array_fill(0, count($filters['post_types']), '%s'));
            $where[] = "o.source_post_type IN ({$placeholders})";
            foreach ($filters['post_types'] as $post_type) {
                $values[] = $post_type;
            }
        }

        if (!empty($filters['origin'])) {
            $where[] = 'o.origin = %s';
            $values[] = $filters['origin'];
        }

        if (!empty($filters['tag'])) {
            $where[] = 'o.tag = %s';
            $values[] = $filters['tag'];
        }

        if (!empty($filters['source_post_ids'])) {
            $placeholders = implode(', ', array_fill(0, count($filters['source_post_ids']), '%d'));
            $where[] = "o.source_post_id IN ({$placeholders})";
            foreach ($filters['source_post_ids'] as $source_post_id) {
                $values[] = (int) $source_post_id;
            }
        }

        if (!empty($filters['entity_ids'])) {
            $placeholders = implode(', ', array_fill(0, count($filters['entity_ids']), '%d'));
            $where[] = "o.entity_id IN ({$placeholders})";
            foreach ($filters['entity_ids'] as $entity_id) {
                $values[] = (int) $entity_id;
            }
        }

        if (!empty($filters['location_entity_ids'])) {
            $placeholders = implode(', ', array_fill(0, count($filters['location_entity_ids']), '%d'));
            $where[] = "o.location_entity_id IN ({$placeholders})";
            foreach ($filters['location_entity_ids'] as $location_entity_id) {
                $values[] = (int) $location_entity_id;
            }
        }

        $time_clause = $this->build_time_where($filters, $values);
        if ($time_clause !== '') {
            $where[] = $time_clause;
        }

        $order = $filters['order'] === 'DESC' ? 'DESC' : 'ASC';
        $limit = (int) $filters['limit'];
        $offset = max(0, (int) $filters['offset']);
        $order_values = [];
        $order_sql = $this->build_order_by($filters, $order, $order_values);

        $sql = "SELECT o.* FROM {$table} o WHERE " . implode(' AND ', $where) . " ORDER BY {$order_sql}";
        foreach ($order_values as $order_value) {
            $values[] = $order_value;
        }
        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $values[] = $limit;
            $values[] = $offset;
        } elseif ($offset > 0) {
            $sql .= ' LIMIT 18446744073709551615 OFFSET %d';
            $values[] = $offset;
        }

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        $rows = is_array($rows) ? $rows : [];

        return array_values(array_map([$this, 'format_timeline_row'], $rows));
    }

    public function normalize_datetime(string $value, bool $date_end = false): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value .= $date_end ? ' 23:59:59' : ' 00:00:00';
        }

        try {
            $dt = new DateTimeImmutable($value, wp_timezone());
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function normalize_query_filters(array $filters): array
    {
        $order = strtoupper(sanitize_text_field((string) ($filters['order'] ?? 'ASC')));
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $time_mode = sanitize_key((string) ($filters['time_mode'] ?? 'all'));
        if (!in_array($time_mode, ['all', 'upcoming', 'past', 'month', 'range'], true)) {
            $time_mode = 'all';
        }

        $kinds = [];
        $item_types = isset($filters['item_types']) && is_array($filters['item_types']) ? $filters['item_types'] : [];
        if (!empty($filters['item_type'])) {
            $item_types[] = $filters['item_type'];
        }
        foreach ($item_types as $item_type) {
            $kind = $this->normalize_kind((string) $item_type);
            if ($kind !== '') {
                $kinds[] = $kind;
            }
        }

        $source_post_ids = isset($filters['source_post_ids']) && is_array($filters['source_post_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $filters['source_post_ids']))))
            : [];
        $entity_ids = isset($filters['entity_ids']) && is_array($filters['entity_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $filters['entity_ids']))))
            : [];
        $location_entity_ids = isset($filters['location_entity_ids']) && is_array($filters['location_entity_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $filters['location_entity_ids']))))
            : [];

        $taxonomy_ids = $this->resolve_source_ids_for_taxonomy_filters($filters);
        if (!empty($taxonomy_ids)) {
            $source_post_ids = empty($source_post_ids)
                ? $taxonomy_ids
                : array_values(array_intersect($source_post_ids, $taxonomy_ids));
        } elseif (!empty($filters['source_taxonomy_filters'])) {
            $source_post_ids = [-1];
        }

        return [
            'limit' => isset($filters['limit']) ? (int) $filters['limit'] : 50,
            'offset' => isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0,
            'order' => $order,
            'time_mode' => $time_mode,
            'month' => isset($filters['month']) ? preg_replace('/[^0-9\-]/', '', (string) $filters['month']) : '',
            'date_start' => isset($filters['date_start']) ? sanitize_text_field((string) $filters['date_start']) : '',
            'date_end' => isset($filters['date_end']) ? sanitize_text_field((string) $filters['date_end']) : '',
            'include_running_ranges' => !empty($filters['include_running_ranges']),
            'kinds' => array_values(array_unique($kinds)),
            'post_types' => isset($filters['post_types']) && is_array($filters['post_types'])
                ? array_values(array_unique(array_filter(array_map('sanitize_key', $filters['post_types']))))
                : [],
            'source_post_ids' => $source_post_ids,
            'entity_ids' => $entity_ids,
            'location_entity_ids' => $location_entity_ids,
            'origin' => isset($filters['origin']) ? sanitize_key((string) $filters['origin']) : '',
            'tag' => isset($filters['tag']) ? strtoupper(sanitize_text_field((string) $filters['tag'])) : '',
        ];
    }

    private function build_order_by(array $filters, string $order, array &$values): string
    {
        if ($order === 'ASC' && $filters['time_mode'] === 'upcoming' && !empty($filters['include_running_ranges'])) {
            $now = current_time('mysql');
            $deferred_condition = "(o.ends_at IS NOT NULL AND o.ends_at >= '2099-12-31 00:00:00' AND o.starts_at < %s)";
            $fallback_condition = "({$deferred_condition} AND o.date_source = 'fallback_post_date')";
            $values[] = $now;
            $values[] = $now;
            $values[] = $now;
            $values[] = $now;

            return "CASE WHEN {$deferred_condition} THEN 1 ELSE 0 END ASC, "
                . "CASE WHEN {$fallback_condition} THEN 1 ELSE 0 END ASC, "
                . "CASE WHEN {$deferred_condition} THEN NULL ELSE o.starts_at END ASC, "
                . "CASE WHEN {$deferred_condition} THEN o.starts_at ELSE NULL END DESC, "
                . 'o.id ASC';
        }

        if ($order === 'ASC' && in_array($filters['time_mode'], ['month', 'range'], true) && !empty($filters['include_running_ranges'])) {
            $range = $this->get_period_range_for_filters($filters);
            if (is_array($range)) {
                $start = (string) $range['start'];
                $deferred_condition = "(o.starts_at < %s AND o.ends_at IS NOT NULL AND o.ends_at >= '2099-12-31 00:00:00')";
                $fallback_condition = "({$deferred_condition} AND o.date_source = 'fallback_post_date')";
                $values[] = $start;
                $values[] = $start;
                $values[] = $start;
                $values[] = $start;

                return "CASE WHEN {$deferred_condition} THEN 1 ELSE 0 END ASC, "
                    . "CASE WHEN {$fallback_condition} THEN 1 ELSE 0 END ASC, "
                    . "CASE WHEN {$deferred_condition} THEN NULL ELSE o.starts_at END ASC, "
                    . "CASE WHEN {$deferred_condition} THEN o.starts_at ELSE NULL END DESC, "
                    . 'o.id ASC';
            }
        }

        return "o.starts_at {$order}, o.id {$order}";
    }

    private function get_period_range_for_filters(array $filters): ?array
    {
        if ($filters['time_mode'] === 'month') {
            return $this->month_to_range((string) $filters['month']);
        }

        if ($filters['time_mode'] === 'range') {
            $start = $this->normalize_datetime((string) $filters['date_start']);
            $end = $this->normalize_datetime((string) $filters['date_end'], true);

            if ($start !== '' && $end !== '') {
                return [
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        return null;
    }

    private function normalize_kind(string $value): string
    {
        $value = sanitize_key($value);
        if ($value === '' || $value === 'all') {
            return '';
        }

        if (in_array($value, ['fuehrung', 'fuehrungen', 'tour'], true)) {
            return 'tour';
        }
        if (in_array($value, ['veranstaltung', 'veranstaltungen', 'event'], true)) {
            return 'event';
        }
        if (in_array($value, ['ausstellung', 'ausstellungen', 'exhibition'], true)) {
            return 'ausstellung';
        }
        if (in_array($value, ['projekt', 'projekte', 'project'], true)) {
            return 'project';
        }

        return $value;
    }

    private function resolve_source_ids_for_taxonomy_filters(array $filters): array
    {
        $tax_filters = isset($filters['source_taxonomy_filters']) && is_array($filters['source_taxonomy_filters'])
            ? $filters['source_taxonomy_filters']
            : [];
        if (empty($tax_filters)) {
            return [];
        }

        $post_types = isset($filters['post_types']) && is_array($filters['post_types'])
            ? array_values(array_unique(array_filter(array_map('sanitize_key', $filters['post_types']))))
            : [];

        if (empty($post_types)) {
            foreach ($tax_filters as $filter) {
                $taxonomy = isset($filter['taxonomy']) ? sanitize_key((string) $filter['taxonomy']) : '';
                if ($taxonomy === '') {
                    continue;
                }
                $taxonomy_obj = get_taxonomy($taxonomy);
                if (!$taxonomy_obj || empty($taxonomy_obj->object_type) || !is_array($taxonomy_obj->object_type)) {
                    continue;
                }
                foreach ($taxonomy_obj->object_type as $post_type) {
                    $post_type = sanitize_key((string) $post_type);
                    if ($post_type !== '' && post_type_exists($post_type)) {
                        $post_types[] = $post_type;
                    }
                }
            }
            $post_types = array_values(array_unique($post_types));
        }

        if (empty($post_types)) {
            return [];
        }

        $tax_query = [];
        foreach ($tax_filters as $filter) {
            if (!is_array($filter) || empty($filter['taxonomy']) || empty($filter['terms'])) {
                continue;
            }
            $tax_query[] = [
                'taxonomy' => sanitize_key((string) $filter['taxonomy']),
                'field' => isset($filter['field']) ? sanitize_key((string) $filter['field']) : 'slug',
                'terms' => is_array($filter['terms']) ? $filter['terms'] : [$filter['terms']],
                'operator' => isset($filter['operator']) ? sanitize_text_field((string) $filter['operator']) : 'IN',
            ];
        }

        if (empty($tax_query)) {
            return [];
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        $ids = get_posts([
            'post_type' => $post_types,
            'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Source taxonomy filters are an explicit public programme filter.
            'tax_query' => $tax_query,
        ]);

        return array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
    }

    private function build_time_where(array $filters, array &$values): string
    {
        $mode = $filters['time_mode'];
        $now = current_time('mysql');

        if ($mode === 'upcoming') {
            $end = function_exists('iss_timeline_get_future_horizon_end_mysql')
                ? iss_timeline_get_future_horizon_end_mysql()
                : (new DateTimeImmutable('now', wp_timezone()))->modify('+6 months')->format('Y-m-d H:i:s');

            if (!empty($filters['include_running_ranges'])) {
                $values[] = $now;
                $values[] = $end;
                $values[] = $now;
                $values[] = $end;
                return '((o.starts_at >= %s AND o.starts_at <= %s) OR (o.ends_at IS NOT NULL AND o.ends_at >= %s AND o.starts_at <= %s))';
            }

            $values[] = $now;
            $values[] = $end;
            return '(o.starts_at >= %s AND o.starts_at <= %s)';
        }

        if ($mode === 'past') {
            $values[] = $now;
            $values[] = $now;
            return '((o.ends_at IS NOT NULL AND o.ends_at < %s) OR (o.ends_at IS NULL AND o.starts_at < %s))';
        }

        if ($mode === 'month') {
            $range = $this->month_to_range((string) $filters['month']);
            if (!is_array($range)) {
                return '';
            }

            if (!empty($filters['include_running_ranges'])) {
                $values[] = $range['start'];
                $values[] = $range['end'];
                $values[] = $range['start'];
                $values[] = $range['end'];
                return '((o.starts_at >= %s AND o.starts_at <= %s) OR (o.ends_at IS NOT NULL AND o.ends_at >= %s AND o.starts_at <= %s))';
            }

            $values[] = $range['start'];
            $values[] = $range['end'];
            return '(o.starts_at >= %s AND o.starts_at <= %s)';
        }

        if ($mode === 'range') {
            $start = $this->normalize_datetime((string) $filters['date_start']);
            $end = $this->normalize_datetime((string) $filters['date_end'], true);

            if ($start !== '' && $end !== '') {
                if (!empty($filters['include_running_ranges'])) {
                    $values[] = $start;
                    $values[] = $end;
                    $values[] = $start;
                    $values[] = $end;
                    return '((o.starts_at >= %s AND o.starts_at <= %s) OR (o.ends_at IS NOT NULL AND o.ends_at >= %s AND o.starts_at <= %s))';
                }

                $values[] = $start;
                $values[] = $end;
                return '(o.starts_at >= %s AND o.starts_at <= %s)';
            }

            if ($start !== '') {
                $values[] = $start;
                $values[] = $start;
                return '((o.starts_at >= %s) OR (o.ends_at IS NOT NULL AND o.ends_at >= %s))';
            }

            if ($end !== '') {
                $values[] = $end;
                return '(o.starts_at <= %s)';
            }
        }

        return '';
    }

    private function month_to_range(string $ym): ?array
    {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return null;
        }

        try {
            $start = new DateTimeImmutable($ym . '-01 00:00:00', wp_timezone());
            $end = $start->modify('+1 month')->modify('-1 second');
            return [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    public function format_timeline_row(array $row): array
    {
        $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
        $source_post_type = isset($row['source_post_type']) ? sanitize_key((string) $row['source_post_type']) : '';
        $starts_at = isset($row['starts_at']) ? (string) $row['starts_at'] : '';
        $ends_at = isset($row['ends_at']) ? (string) $row['ends_at'] : '';
        if ($ends_at === '0000-00-00 00:00:00') {
            $ends_at = '';
        }
        $is_open_ended = strpos($ends_at, '2099-12-31') === 0;
        $start_ts = null;
        $end_ts = null;

        try {
            if ($starts_at !== '') {
                $start_ts = (new DateTimeImmutable($starts_at, wp_timezone()))->getTimestamp();
            }
        } catch (Throwable $e) {
            $start_ts = null;
        }

        try {
            if ($ends_at !== '' && !$is_open_ended) {
                $end_ts = (new DateTimeImmutable($ends_at, wp_timezone()))->getTimestamp();
            }
        } catch (Throwable $e) {
            $end_ts = null;
        }

        $is_running_open_ended = $is_open_ended && $starts_at !== '' && $starts_at <= current_time('mysql');
        $date_label = $start_ts && function_exists('iss_programm_format_date_long_de')
            ? iss_programm_format_date_long_de($start_ts, wp_timezone())
            : $starts_at;
        $day_label = $start_ts && function_exists('iss_programm_format_day_short_de')
            ? iss_programm_format_day_short_de($start_ts, wp_timezone())
            : $date_label;

        $time_label = '';
        if ($is_running_open_ended && $date_label !== '') {
            $time_label = sprintf(
                /* translators: %s exhibition start date */
                __('seit %s', 'iss-occurrences'),
                $date_label
            );
            $date_label = sprintf(
                /* translators: %s exhibition start date */
                __('Laufend seit %s', 'iss-occurrences'),
                $date_label
            );
            $day_label = __('Laufend', 'iss-occurrences');
        } elseif ($start_ts) {
            $start_date_key = wp_date('Y-m-d', $start_ts, wp_timezone());
            $start_time_key = wp_date('H:i', $start_ts, wp_timezone());
            $end_date_key = $end_ts ? wp_date('Y-m-d', $end_ts, wp_timezone()) : '';
            $end_time_key = $end_ts ? wp_date('H:i', $end_ts, wp_timezone()) : '';

            if ($end_ts && $start_date_key !== $end_date_key) {
                $time_label = function_exists('iss_programm_format_date_long_de')
                    ? sprintf(__('bis %s', 'iss-occurrences'), iss_programm_format_date_long_de($end_ts, wp_timezone()))
                    : '';
            } elseif ($start_time_key === '00:00' && ($end_time_key === '' || $end_time_key === '23:59' || $end_time_key === '00:00')) {
                $time_label = '';
            } else {
                $time_label = $start_time_key;
                if ($end_ts) {
                    $time_label .= ' – ' . $end_time_key;
                }
                $time_label .= ' Uhr';
            }
        }

        $datetime_label = $date_label;
        if (!$is_running_open_ended && $date_label !== '' && $time_label !== '') {
            $datetime_label = $date_label . ', ' . $time_label;
        }

        $title = $source_post_id > 0 ? trim((string) get_the_title($source_post_id)) : '';
        if ($title === '') {
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
        }
        if ($title === '') {
            $title = trim((string) ($row['kind'] ?? ''));
        }

        $summary = '';
        if ($source_post_id > 0 && function_exists('iss_timeline_extract_teaser_text')) {
            $summary = iss_timeline_extract_teaser_text($source_post_id, 30);
        }

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'entity_id' => isset($row['entity_id']) ? (int) $row['entity_id'] : 0,
            'title' => $title,
            'start_raw' => $starts_at,
            'date_raw' => $starts_at,
            'date_label' => $date_label,
            'day_label' => $day_label,
            'time_label' => $time_label,
            'datetime_label' => $datetime_label,
            'end_raw' => $ends_at,
            'type' => isset($row['kind']) ? sanitize_key((string) $row['kind']) : '',
            'series_key' => trim((string) ($row['series_key'] ?? '')),
            'summary' => $summary,
            'cta_mode' => 'details',
            'cta_url' => '',
            'cta_label' => __('Mehr erfahren', 'iss-occurrences'),
            'booking_url' => isset($row['booking_url']) ? (string) $row['booking_url'] : '',
            'slot_id' => isset($row['external_id']) ? trim((string) $row['external_id']) : '',
            'slot_start' => $starts_at,
            'source_post_id' => $source_post_id,
            'source_post_type' => $source_post_type,
            'location_entity_id' => isset($row['location_entity_id']) ? (int) $row['location_entity_id'] : 0,
            'location_label' => isset($row['location_label']) ? (string) $row['location_label'] : '',
            'date_source' => isset($row['date_source']) ? sanitize_key((string) $row['date_source']) : '',
            'availability' => isset($row['availability_state']) ? sanitize_key((string) $row['availability_state']) : '',
            'availability_state' => isset($row['availability_state']) ? sanitize_key((string) $row['availability_state']) : '',
            'available' => array_key_exists('capacity_available', $row) && (int) $row['capacity_available'] >= 0 ? (int) $row['capacity_available'] : null,
            'capacity' => array_key_exists('capacity_total', $row) && (int) $row['capacity_total'] >= 0 ? (int) $row['capacity_total'] : null,
            'tag' => isset($row['tag']) ? strtoupper(sanitize_text_field((string) $row['tag'])) : '',
            'content_url' => $source_post_id > 0 ? get_permalink($source_post_id) : '',
            'year' => $is_running_open_ended ? (int) wp_date('Y', null, wp_timezone()) : ($start_ts ? (int) wp_date('Y', $start_ts, wp_timezone()) : null),
        ];
    }
}

function iss_occurrences_get_service(): ISS_Occurrences_Service
{
    return ISS_Occurrences_Service::get_instance();
}

function iss_occurrences_public_query_ready(): bool
{
    static $ready = null;

    if ($ready === null) {
        $ready = iss_occurrences_get_service()->public_row_count() > 0;
    }

    return (bool) apply_filters('iss_occurrences_public_query_ready', $ready);
}

function iss_occurrences_query(array $filters): array
{
    return iss_occurrences_get_service()->query($filters);
}

function iss_occurrences_get_next_dates(int $source_post_id, int $limit = 4): array
{
    $source_post_id = max(0, $source_post_id);
    if ($source_post_id <= 0) {
        return [];
    }

    return iss_occurrences_query([
        'limit' => max(1, $limit),
        'order' => 'ASC',
        'time_mode' => 'upcoming',
        'source_post_ids' => [$source_post_id],
    ]);
}
