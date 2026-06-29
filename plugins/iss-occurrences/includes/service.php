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

    public function get_supersaas_slots_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_supersaas_slots';
    }

    private function normalize_series_key(string $series_key): string
    {
        if (function_exists('iss_occurrences_normalize_series_key')) {
            return iss_occurrences_normalize_series_key($series_key);
        }

        $series_key = strtolower(trim(sanitize_text_field($series_key)));
        $series_key = preg_replace('/[^a-z0-9:_-]+/', '', $series_key);
        return trim((string) $series_key);
    }

    private function normalize_series_tag(string $tag): string
    {
        if (function_exists('iss_occurrences_normalize_tag')) {
            return iss_occurrences_normalize_tag($tag);
        }

        $tag = strtoupper(sanitize_text_field($tag));
        $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
        return trim((string) $tag);
    }

    private function build_series_key(string $title, string $kind = 'tour'): string
    {
        if (function_exists('iss_occurrences_build_series_key')) {
            return iss_occurrences_build_series_key($title, $kind);
        }

        $slug = sanitize_title($title);
        $kind = sanitize_key($kind);
        return $kind !== '' ? $kind . ':' . $slug : $slug;
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
        $supersaas_slots_table = $this->get_supersaas_slots_table_name();

        $occurrences_sql = "CREATE TABLE {$occurrences_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_post_type varchar(100) NOT NULL DEFAULT '',
            kind varchar(50) NOT NULL DEFAULT '',
            title varchar(255) NOT NULL DEFAULT '',
            starts_at datetime NOT NULL,
            ends_at datetime DEFAULT NULL,
            is_open_ended tinyint(1) unsigned NOT NULL DEFAULT 0,
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
            location_label varchar(255) NOT NULL DEFAULT '',
            availability_state varchar(50) NOT NULL DEFAULT '',
            capacity_total int(11) NOT NULL DEFAULT -1,
            capacity_available int(11) NOT NULL DEFAULT -1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY origin_external (origin, external_id),
            KEY public_date (visibility, status, starts_at),
            KEY source_lookup (source_post_type, source_post_id),
            KEY location_post_date (location_post_id, starts_at),
            KEY kind_date (kind, starts_at),
            KEY open_ended_date (is_open_ended, starts_at),
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
            supersaas_title varchar(255) NOT NULL DEFAULT '',
            tag varchar(100) NOT NULL DEFAULT '',
            fallback_url varchar(255) NOT NULL DEFAULT '',
            review_state varchar(50) NOT NULL DEFAULT '',
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

        $supersaas_slots_sql = "CREATE TABLE {$supersaas_slots_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            schedule_key varchar(100) NOT NULL DEFAULT '',
            schedule_label varchar(191) NOT NULL DEFAULT '',
            schedule_id varchar(100) NOT NULL DEFAULT '',
            source_calendar varchar(191) NOT NULL DEFAULT '',
            slot_id varchar(191) NOT NULL DEFAULT '',
            external_id varchar(191) NOT NULL DEFAULT '',
            raw_title varchar(255) NOT NULL DEFAULT '',
            clean_title varchar(255) NOT NULL DEFAULT '',
            description text NOT NULL,
            series_key varchar(191) NOT NULL DEFAULT '',
            tag varchar(100) NOT NULL DEFAULT '',
            starts_at datetime NOT NULL,
            ends_at datetime DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'seen',
            visibility varchar(50) NOT NULL DEFAULT 'private',
            is_cancelled tinyint(1) unsigned NOT NULL DEFAULT 0,
            availability_state varchar(50) NOT NULL DEFAULT '',
            capacity_total int(11) NOT NULL DEFAULT -1,
            capacity_available int(11) NOT NULL DEFAULT -1,
            location_label varchar(255) NOT NULL DEFAULT '',
            source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_post_type varchar(100) NOT NULL DEFAULT '',
            match_state varchar(50) NOT NULL DEFAULT 'unmapped',
            review_state varchar(50) NOT NULL DEFAULT '',
            last_seen_at datetime NOT NULL,
            last_synced_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY schedule_slot (schedule_key, slot_id),
            UNIQUE KEY external_lookup (external_id),
            KEY schedule_date (schedule_key, starts_at),
            KEY source_lookup (source_post_type, source_post_id),
            KEY series_lookup (series_key),
            KEY match_state_date (match_state, starts_at),
            KEY review_state_date (review_state, starts_at),
            KEY status_date (status, starts_at),
            KEY last_seen (last_seen_at)
        ) {$charset_collate};";

        dbDelta($occurrences_sql);
        dbDelta($series_sql);
        dbDelta($supersaas_slots_sql);
        $this->drop_legacy_graph_columns();
        $this->migrate_open_ended_sentinel();
        delete_option(ISS_OCCURRENCES_RETIRED_SERIES_MAP_OPTION);
        delete_option(ISS_OCCURRENCES_RETIRED_SOURCE_MAP_OPTION);
        delete_option('iss_calendar_source_map');
        delete_option('iss_calendar_series_map');
        delete_option('iss_calendar_cron_sync');
        update_option(ISS_OCCURRENCES_SCHEMA_OPTION, ISS_OCCURRENCES_SCHEMA_VERSION, false);
    }

    private function drop_legacy_graph_columns(): void
    {
        global $wpdb;

        $table = $this->get_occurrences_table_name();
        $found = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($found !== $table) {
            return;
        }

        foreach (['entity_date', 'location_entity_date'] as $index_name) {
            $index_exists = (string) $wpdb->get_var(
                $wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index_name)
            );
            if ($index_exists !== '') {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping retired service-owned graph indexes.
                $wpdb->query("ALTER TABLE {$table} DROP INDEX {$index_name}");
            }
        }

        foreach (['entity_id', 'location_entity_id'] as $column_name) {
            $column_exists = (string) $wpdb->get_var(
                $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column_name)
            );
            if ($column_exists !== '') {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping retired service-owned graph columns.
                $wpdb->query("ALTER TABLE {$table} DROP COLUMN {$column_name}");
            }
        }
    }

    private function migrate_open_ended_sentinel(): void
    {
        global $wpdb;

        $table = $this->get_occurrences_table_name();
        $found = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($found !== $table) {
            return;
        }

        $column_exists = (string) $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'is_open_ended')
        );
        if ($column_exists === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Service-owned migration from retired sentinel dates.
        $wpdb->query(
            "UPDATE {$table} SET is_open_ended = 1, ends_at = NULL WHERE ends_at IS NOT NULL AND ends_at >= '2099-12-31 00:00:00'"
        );
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

    public function upsert_series(array $row): int
    {
        global $wpdb;

        $series_key = isset($row['series_key']) ? $this->normalize_series_key((string) $row['series_key']) : '';
        if ($series_key === '') {
            return 0;
        }

        $table = $this->get_series_table_name();
        $now = current_time('mysql');
        $existing = $this->get_series_by_key($series_key);
        $source_post_id = isset($row['source_post_id']) ? max(0, (int) $row['source_post_id']) : 0;
        $source_post_type = isset($row['source_post_type']) ? sanitize_key((string) $row['source_post_type']) : '';
        $origin = isset($row['origin']) ? sanitize_key((string) $row['origin']) : 'supersaas';
        $external_id = isset($row['external_id']) ? sanitize_text_field((string) $row['external_id']) : '';
        $timezone = isset($row['timezone']) ? sanitize_text_field((string) $row['timezone']) : wp_timezone_string();
        $rule = isset($row['rule']) ? sanitize_textarea_field((string) $row['rule']) : '';
        if (isset($row['exceptions']) && is_array($row['exceptions'])) {
            $exceptions_json = wp_json_encode($row['exceptions']);
            $exceptions = is_string($exceptions_json) ? $exceptions_json : '';
        } elseif (isset($row['exceptions'])) {
            $exceptions = (string) $row['exceptions'];
        } else {
            $exceptions = '';
        }
        $supersaas_title = isset($row['supersaas_title']) ? trim((string) $row['supersaas_title']) : '';
        $tag = isset($row['tag']) ? $this->normalize_series_tag((string) $row['tag']) : '';
        $fallback_url = isset($row['fallback_url']) ? esc_url_raw((string) $row['fallback_url']) : '';
        $review_state = isset($row['review_state']) ? sanitize_key((string) $row['review_state']) : '';
        if (!in_array($review_state, ['unreviewed', 'mapped', 'ignored'], true)) {
            $review_state = '';
        }

        if ($supersaas_title === '' && isset($row['title'])) {
            $supersaas_title = sanitize_text_field((string) $row['title']);
        }
        if ($fallback_url === '' && isset($row['booking_url'])) {
            $fallback_url = esc_url_raw((string) $row['booking_url']);
        }
        if ($source_post_id <= 0 && !empty($existing['source_post_id'])) {
            $source_post_id = (int) $existing['source_post_id'];
        }
        if ($source_post_type === '' && !empty($existing['source_post_type'])) {
            $source_post_type = sanitize_key((string) $existing['source_post_type']);
        }
        if ($origin === '' && !empty($existing['origin'])) {
            $origin = sanitize_key((string) $existing['origin']);
        }
        if ($external_id === '' && !empty($existing['external_id'])) {
            $external_id = sanitize_text_field((string) $existing['external_id']);
        }
        if ($rule === '' && !empty($existing['rule'])) {
            $rule = sanitize_textarea_field((string) $existing['rule']);
        }
        if ($timezone === '' && !empty($existing['timezone'])) {
            $timezone = sanitize_text_field((string) $existing['timezone']);
        }
        if ($exceptions === '' && !empty($existing['exceptions'])) {
            $exceptions = (string) $existing['exceptions'];
        }
        if ($supersaas_title === '' && !empty($existing['supersaas_title'])) {
            $supersaas_title = trim((string) $existing['supersaas_title']);
        }
        if ($tag === '' && !empty($existing['tag'])) {
            $tag = $this->normalize_series_tag((string) $existing['tag']);
        }
        if ($fallback_url === '' && !empty($existing['fallback_url'])) {
            $fallback_url = esc_url_raw((string) $existing['fallback_url']);
        }
        if ($review_state === '' && !empty($existing['review_state'])) {
            $review_state = sanitize_key((string) $existing['review_state']);
        }
        if ($review_state === '' || !in_array($review_state, ['unreviewed', 'mapped', 'ignored'], true)) {
            $review_state = $source_post_id > 0 ? 'mapped' : 'unreviewed';
        }
        if ($source_post_id > 0 && $review_state !== 'ignored') {
            $review_state = 'mapped';
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (source_post_id, source_post_type, origin, external_id, series_key, supersaas_title, tag, fallback_url, review_state, rule, timezone, exceptions, created_at, updated_at)
                 VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    source_post_id = VALUES(source_post_id),
                    source_post_type = VALUES(source_post_type),
                    origin = VALUES(origin),
                    external_id = VALUES(external_id),
                    supersaas_title = VALUES(supersaas_title),
                    tag = VALUES(tag),
                    fallback_url = VALUES(fallback_url),
                    review_state = VALUES(review_state),
                    rule = VALUES(rule),
                    timezone = VALUES(timezone),
                    exceptions = VALUES(exceptions),
                    updated_at = VALUES(updated_at)",
                $source_post_id,
                $source_post_type,
                $origin,
                $external_id,
                $series_key,
                $supersaas_title,
                $tag,
                $fallback_url,
                $review_state,
                $rule,
                $timezone,
                $exceptions,
                $now,
                $now
            )
        );

        return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE series_key = %s LIMIT 1", $series_key));
    }

    public function clear_series_source_for_post(int $source_post_id): int
    {
        global $wpdb;

        $source_post_id = max(0, $source_post_id);
        if ($source_post_id <= 0 || !$this->tables_exist()) {
            return 0;
        }

        $table = $this->get_series_table_name();
        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET source_post_id = 0, source_post_type = '', review_state = %s, updated_at = %s WHERE source_post_id = %d",
                'unreviewed',
                current_time('mysql'),
                $source_post_id
            )
        );
    }

    public function clear_series_source_for_key(string $series_key): bool
    {
        global $wpdb;

        $series_key = $this->normalize_series_key($series_key);
        if ($series_key === '' || !$this->tables_exist()) {
            return false;
        }

        $table = $this->get_series_table_name();
        return (bool) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET source_post_id = 0, source_post_type = '', review_state = %s, updated_at = %s WHERE series_key = %s",
                'unreviewed',
                current_time('mysql'),
                $series_key
            )
        );
    }

    public function clear_series_source_for_tag(string $tag): bool
    {
        global $wpdb;

        $tag = $this->normalize_series_tag($tag);
        if ($tag === '' || !$this->tables_exist()) {
            return false;
        }

        $table = $this->get_series_table_name();
        return (bool) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET source_post_id = 0, source_post_type = '', review_state = %s, updated_at = %s WHERE tag = %s",
                'unreviewed',
                current_time('mysql'),
                $tag
            )
        );
    }

    public function set_series_review_state(string $series_key, string $review_state): bool
    {
        global $wpdb;

        $series_key = $this->normalize_series_key($series_key);
        $review_state = sanitize_key($review_state);
        if ($series_key === '' || !in_array($review_state, ['unreviewed', 'mapped', 'ignored'], true) || !$this->tables_exist()) {
            return false;
        }

        $table = $this->get_series_table_name();
        $fields = [
            'review_state' => $review_state,
            'updated_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%s'];
        if ($review_state === 'ignored') {
            $fields['source_post_id'] = 0;
            $fields['source_post_type'] = '';
            $formats[] = '%d';
            $formats[] = '%s';
        }

        $updated = $wpdb->update(
            $table,
            $fields,
            ['series_key' => $series_key],
            $formats,
            ['%s']
        );

        return $updated !== false && $updated > 0;
    }

    public function get_series_rows(): array
    {
        global $wpdb;

        if (!$this->tables_exist()) {
            return [];
        }

        $table = $this->get_series_table_name();
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY series_key ASC", ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public function get_series_by_key(string $series_key): array
    {
        global $wpdb;

        $series_key = $this->normalize_series_key($series_key);
        if ($series_key === '' || !$this->tables_exist()) {
            return [];
        }

        $table = $this->get_series_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE series_key = %s LIMIT 1", $series_key),
            ARRAY_A
        );

        return is_array($row) ? $row : [];
    }

    public function get_series_by_tag(string $tag): array
    {
        global $wpdb;

        $tag = $this->normalize_series_tag($tag);
        if ($tag === '' || !$this->tables_exist()) {
            return [];
        }

        $table = $this->get_series_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE tag = %s ORDER BY source_post_id DESC, updated_at DESC, id ASC LIMIT 1",
                $tag
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : [];
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
        $kind = isset($row['kind']) ? sanitize_key((string) $row['kind']) : '';
        $origin = isset($row['origin']) ? sanitize_key((string) $row['origin']) : 'wp';
        $external_id = isset($row['external_id']) ? sanitize_text_field((string) $row['external_id']) : '';
        if ($external_id === '' && $source_post_id > 0 && $source_post_type !== '') {
            $external_id = $origin . ':' . $source_post_type . ':' . $source_post_id;
        }
        if ($external_id === '') {
            return 0;
        }

        $series_key = isset($row['series_key']) ? $this->normalize_series_key((string) $row['series_key']) : '';
        $series_id = $series_key !== '' ? $this->upsert_series($row) : 0;
        $ends_at = isset($row['ends_at']) ? $this->normalize_datetime((string) $row['ends_at'], true) : null;
        $ends_at = $ends_at !== '' ? $ends_at : null;
        $is_open_ended = !empty($row['is_open_ended']) ? 1 : 0;
        if (!$is_open_ended && is_string($ends_at) && strpos($ends_at, '2099-12-31') === 0) {
            $is_open_ended = 1;
        }
        if ($is_open_ended) {
            $ends_at = null;
        }
        $location_post_id = isset($row['location_post_id']) ? max(0, (int) $row['location_post_id']) : 0;
        $now = current_time('mysql');
        $table = $this->get_occurrences_table_name();

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (source_post_id, source_post_type, kind, title, starts_at, ends_at, is_open_ended, date_source, status, visibility, origin, source_calendar, external_id, tag, series_key, series_id, booking_url, location_post_id, location_label, availability_state, capacity_total, capacity_available, created_at, updated_at)
                 VALUES (%d, %s, %s, %s, %s, NULLIF(%s, ''), %d, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %d, %s, %s, %d, %d, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    source_post_id = VALUES(source_post_id),
                    source_post_type = VALUES(source_post_type),
                    kind = VALUES(kind),
                    title = VALUES(title),
                    starts_at = VALUES(starts_at),
                    ends_at = VALUES(ends_at),
                    is_open_ended = VALUES(is_open_ended),
                    date_source = VALUES(date_source),
                    status = VALUES(status),
                    visibility = VALUES(visibility),
                    source_calendar = VALUES(source_calendar),
                    tag = VALUES(tag),
                    series_key = VALUES(series_key),
                    series_id = VALUES(series_id),
                    booking_url = VALUES(booking_url),
                    location_post_id = VALUES(location_post_id),
                    location_label = VALUES(location_label),
                    availability_state = VALUES(availability_state),
                    capacity_total = VALUES(capacity_total),
                    capacity_available = VALUES(capacity_available),
                    updated_at = VALUES(updated_at)",
                $source_post_id,
                $source_post_type,
                $kind,
                isset($row['title']) ? sanitize_text_field((string) $row['title']) : '',
                $starts_at,
                $ends_at ?? '',
                $is_open_ended,
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

    public function delete_inactive_origin_future(string $origin, string $source_calendar): int
    {
        global $wpdb;

        $origin = sanitize_key($origin);
        $source_calendar = sanitize_text_field($source_calendar);
        if ($origin === '' || $source_calendar === '' || !$this->tables_exist()) {
            return 0;
        }

        $table = $this->get_occurrences_table_name();
        $deleted = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table}
                WHERE origin = %s
                  AND source_calendar = %s
                  AND status = %s
                  AND starts_at >= %s",
                $origin,
                $source_calendar,
                'inactive',
                current_time('mysql')
            )
        );

        if ($deleted > 0) {
            do_action('iss_occurrences_changed', ['origin' => $origin, 'source_calendar' => $source_calendar]);
        }

        return $deleted;
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
                $series_entry = function_exists('iss_occurrences_get_series_source')
                    ? iss_occurrences_get_series_source($series_key)
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

    public function supersaas_slots_table_exists(): bool
    {
        global $wpdb;

        $table = $this->get_supersaas_slots_table_name();
        $found = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $found === $table;
    }

    public function upsert_supersaas_slot(array $row): int
    {
        global $wpdb;

        if (!$this->supersaas_slots_table_exists()) {
            return 0;
        }

        $schedule_key = isset($row['schedule_key']) ? sanitize_key((string) $row['schedule_key']) : '';
        $slot_id = isset($row['slot_id']) ? sanitize_text_field((string) $row['slot_id']) : '';
        $starts_at = isset($row['starts_at']) ? $this->normalize_datetime((string) $row['starts_at']) : '';
        if ($schedule_key === '' || $slot_id === '' || $starts_at === '') {
            return 0;
        }

        $external_id = isset($row['external_id']) ? sanitize_text_field((string) $row['external_id']) : '';
        if ($external_id === '') {
            $external_id = $schedule_key . ':' . $slot_id;
        }

        $ends_at = isset($row['ends_at']) ? $this->normalize_datetime((string) $row['ends_at'], true) : null;
        $ends_at = $ends_at !== '' ? $ends_at : null;
        $capacity_total = array_key_exists('capacity_total', $row) && $row['capacity_total'] !== null ? (int) $row['capacity_total'] : -1;
        $capacity_available = array_key_exists('capacity_available', $row) && $row['capacity_available'] !== null ? (int) $row['capacity_available'] : -1;
        $match_state = isset($row['match_state']) ? sanitize_key((string) $row['match_state']) : 'unmapped';
        $review_state = isset($row['review_state']) ? sanitize_key((string) $row['review_state']) : '';
        if (!in_array($review_state, ['unreviewed', 'mapped', 'ignored'], true)) {
            $review_state = $match_state === 'ignored' ? 'ignored' : ($match_state === 'mapped' ? 'mapped' : 'unreviewed');
        }
        $now = current_time('mysql');
        $table = $this->get_supersaas_slots_table_name();

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (schedule_key, schedule_label, schedule_id, source_calendar, slot_id, external_id, raw_title, clean_title, description, series_key, tag, starts_at, ends_at, status, visibility, is_cancelled, availability_state, capacity_total, capacity_available, location_label, source_post_id, source_post_type, match_state, review_state, last_seen_at, last_synced_at, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NULLIF(%s, ''), %s, %s, %d, %s, %d, %d, %s, %d, %s, %s, %s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    schedule_label = VALUES(schedule_label),
                    schedule_id = VALUES(schedule_id),
                    source_calendar = VALUES(source_calendar),
                    external_id = VALUES(external_id),
                    raw_title = VALUES(raw_title),
                    clean_title = VALUES(clean_title),
                    description = VALUES(description),
                    series_key = VALUES(series_key),
                    tag = VALUES(tag),
                    starts_at = VALUES(starts_at),
                    ends_at = VALUES(ends_at),
                    status = VALUES(status),
                    visibility = VALUES(visibility),
                    is_cancelled = VALUES(is_cancelled),
                    availability_state = VALUES(availability_state),
                    capacity_total = VALUES(capacity_total),
                    capacity_available = VALUES(capacity_available),
                    location_label = VALUES(location_label),
                    source_post_id = VALUES(source_post_id),
                    source_post_type = VALUES(source_post_type),
                    match_state = VALUES(match_state),
                    review_state = VALUES(review_state),
                    last_seen_at = VALUES(last_seen_at),
                    last_synced_at = VALUES(last_synced_at),
                    updated_at = VALUES(updated_at)",
                $schedule_key,
                isset($row['schedule_label']) ? sanitize_text_field((string) $row['schedule_label']) : '',
                isset($row['schedule_id']) ? sanitize_text_field((string) $row['schedule_id']) : '',
                isset($row['source_calendar']) ? sanitize_text_field((string) $row['source_calendar']) : $schedule_key,
                $slot_id,
                $external_id,
                isset($row['raw_title']) ? sanitize_text_field((string) $row['raw_title']) : '',
                isset($row['clean_title']) ? sanitize_text_field((string) $row['clean_title']) : '',
                isset($row['description']) ? sanitize_textarea_field((string) $row['description']) : '',
                isset($row['series_key']) ? $this->normalize_series_key((string) $row['series_key']) : '',
                isset($row['tag']) ? $this->normalize_series_tag((string) $row['tag']) : '',
                $starts_at,
                $ends_at ?? '',
                isset($row['status']) ? sanitize_key((string) $row['status']) : 'seen',
                isset($row['visibility']) ? sanitize_key((string) $row['visibility']) : 'private',
                !empty($row['is_cancelled']) ? 1 : 0,
                isset($row['availability_state']) ? sanitize_key((string) $row['availability_state']) : '',
                $capacity_total,
                $capacity_available,
                isset($row['location_label']) ? sanitize_text_field((string) $row['location_label']) : '',
                isset($row['source_post_id']) ? max(0, (int) $row['source_post_id']) : 0,
                isset($row['source_post_type']) ? sanitize_key((string) $row['source_post_type']) : '',
                $match_state,
                $review_state,
                isset($row['last_seen_at']) ? $this->normalize_datetime((string) $row['last_seen_at']) : $now,
                isset($row['last_synced_at']) ? $this->normalize_datetime((string) $row['last_synced_at']) : $now,
                $now,
                $now
            )
        );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE schedule_key = %s AND slot_id = %s LIMIT 1",
                $schedule_key,
                $slot_id
            )
        );
    }

    public function delete_missing_supersaas_slots(string $schedule_key, array $seen_external_ids): int
    {
        global $wpdb;

        $schedule_key = sanitize_key($schedule_key);
        if ($schedule_key === '' || !$this->supersaas_slots_table_exists()) {
            return 0;
        }

        $seen_external_ids = array_values(array_unique(array_filter(array_map(static function ($value) {
            return sanitize_text_field((string) $value);
        }, $seen_external_ids))));

        $table = $this->get_supersaas_slots_table_name();
        $where = ['schedule_key = %s'];
        $values = [$schedule_key];
        if (!empty($seen_external_ids)) {
            $placeholders = implode(', ', array_fill(0, count($seen_external_ids), '%s'));
            $where[] = "external_id NOT IN ({$placeholders})";
            foreach ($seen_external_ids as $external_id) {
                $values[] = $external_id;
            }
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $where);
        return (int) $wpdb->query($wpdb->prepare($sql, $values));
    }

    private function build_supersaas_slot_query_parts(array $args): array
    {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        $search = isset($args['search']) ? trim((string) $args['search']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(s.raw_title LIKE %s OR s.clean_title LIKE %s OR s.description LIKE %s OR s.series_key LIKE %s OR s.tag LIKE %s OR s.slot_id LIKE %s OR s.external_id LIKE %s OR p.post_title LIKE %s)';
            array_push($values, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        $schedule_key = isset($args['schedule_key']) ? sanitize_key((string) $args['schedule_key']) : '';
        if ($schedule_key !== '') {
            $where[] = 's.schedule_key = %s';
            $values[] = $schedule_key;
        }

        $match_state = isset($args['match_state']) ? sanitize_key((string) $args['match_state']) : '';
        if ($match_state === 'active') {
            $where[] = 's.match_state <> %s';
            $values[] = 'ignored';
        } elseif ($match_state !== '' && $match_state !== 'all') {
            $where[] = 's.match_state = %s';
            $values[] = $match_state;
        }

        $status = isset($args['status']) ? sanitize_key((string) $args['status']) : '';
        if ($status !== '') {
            $where[] = 's.status = %s';
            $values[] = $status;
        }

        $date_scope = isset($args['date_scope']) ? sanitize_key((string) $args['date_scope']) : '';
        if ($date_scope === 'future') {
            $where[] = 's.starts_at >= %s';
            $values[] = current_time('mysql');
        } elseif ($date_scope === 'past') {
            $where[] = 's.starts_at < %s';
            $values[] = current_time('mysql');
        }

        return [$where, $values];
    }

    public function query_supersaas_slots(array $args = []): array
    {
        global $wpdb;

        if (!$this->supersaas_slots_table_exists()) {
            return [];
        }

        [$where, $values] = $this->build_supersaas_slot_query_parts($args);
        $orderby = isset($args['orderby']) ? sanitize_key((string) $args['orderby']) : 'starts_at';
        $allowed_orderby = [
            'starts_at' => 's.starts_at',
            'schedule_key' => 's.schedule_key',
            'clean_title' => 's.clean_title',
            'match_state' => 's.match_state',
            'status' => 's.status',
            'last_seen_at' => 's.last_seen_at',
            'source_post_id' => 's.source_post_id',
        ];
        $orderby_sql = $allowed_orderby[$orderby] ?? 's.starts_at';
        $order = strtoupper((string) ($args['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $limit = isset($args['limit']) ? max(1, min(200, (int) $args['limit'])) : 50;
        $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;
        $values[] = $limit;
        $values[] = $offset;

        $table = $this->get_supersaas_slots_table_name();
        $sql = "SELECT s.*, COALESCE(p.post_title, '') AS source_post_title, COALESCE(p.post_status, '') AS source_post_status
            FROM {$table} s
            LEFT JOIN {$wpdb->posts} p ON p.ID = s.source_post_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderby_sql} {$order}, s.id ASC
            LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public function count_supersaas_slots(array $args = []): int
    {
        global $wpdb;

        if (!$this->supersaas_slots_table_exists()) {
            return 0;
        }

        [$where, $values] = $this->build_supersaas_slot_query_parts($args);
        $table = $this->get_supersaas_slots_table_name();
        $sql = "SELECT COUNT(*) FROM {$table} s
            LEFT JOIN {$wpdb->posts} p ON p.ID = s.source_post_id
            WHERE " . implode(' AND ', $where);

        return (int) $wpdb->get_var($values ? $wpdb->prepare($sql, $values) : $sql);
    }

    public function get_supersaas_slot_schedule_keys(): array
    {
        global $wpdb;

        if (!$this->supersaas_slots_table_exists()) {
            return [];
        }

        $table = $this->get_supersaas_slots_table_name();
        $rows = $wpdb->get_results("SELECT DISTINCT schedule_key, schedule_label FROM {$table} ORDER BY schedule_key ASC", ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public function get_supersaas_slot(int $id): array
    {
        global $wpdb;

        if ($id <= 0 || !$this->supersaas_slots_table_exists()) {
            return [];
        }

        $table = $this->get_supersaas_slots_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, COALESCE(p.post_title, '') AS source_post_title, COALESCE(p.post_status, '') AS source_post_status
                FROM {$table} s
                LEFT JOIN {$wpdb->posts} p ON p.ID = s.source_post_id
                WHERE s.id = %d
                LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : [];
    }

    public function get_supersaas_slot_by_external(string $external_id): array
    {
        global $wpdb;

        $external_id = sanitize_text_field($external_id);
        if ($external_id === '' || !$this->supersaas_slots_table_exists()) {
            return [];
        }

        $table = $this->get_supersaas_slots_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, COALESCE(p.post_title, '') AS source_post_title, COALESCE(p.post_status, '') AS source_post_status
                FROM {$table} s
                LEFT JOIN {$wpdb->posts} p ON p.ID = s.source_post_id
                WHERE s.external_id = %s
                LIMIT 1",
                $external_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : [];
    }

    public function set_supersaas_slot_source(int $id, int $source_post_id, string $source_post_type, string $match_state = 'mapped'): bool
    {
        global $wpdb;

        if ($id <= 0 || $source_post_id <= 0 || !$this->supersaas_slots_table_exists()) {
            return false;
        }

        $source_post_type = sanitize_key($source_post_type);
        if ($source_post_type === '') {
            $source_post_type = sanitize_key((string) get_post_type($source_post_id));
        }
        if ($source_post_type === '') {
            return false;
        }

        $match_state = sanitize_key($match_state);
        if ($match_state === '') {
            $match_state = 'mapped';
        }

        $updated = $wpdb->update(
            $this->get_supersaas_slots_table_name(),
            [
                'source_post_id' => $source_post_id,
                'source_post_type' => $source_post_type,
                'match_state' => $match_state,
                'review_state' => $match_state === 'ignored' ? 'ignored' : 'mapped',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    public function set_supersaas_slot_review_state(int $id, string $review_state): bool
    {
        global $wpdb;

        if ($id <= 0 || !$this->supersaas_slots_table_exists()) {
            return false;
        }

        $review_state = sanitize_key($review_state);
        if (!in_array($review_state, ['unreviewed', 'ignored'], true)) {
            return false;
        }

        $fields = [
            'review_state' => $review_state,
            'match_state' => $review_state === 'ignored' ? 'ignored' : 'unmapped',
            'status' => 'skipped',
            'visibility' => 'private',
            'updated_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%s', '%s', '%s', '%s'];

        if ($review_state === 'ignored') {
            $fields['source_post_id'] = 0;
            $fields['source_post_type'] = '';
            $formats[] = '%d';
            $formats[] = '%s';
        }

        $updated = $wpdb->update(
            $this->get_supersaas_slots_table_name(),
            $fields,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return $updated !== false;
    }

    public function set_supersaas_slots_series_state(string $series_key, int $source_post_id, string $source_post_type, string $match_state, string $review_state): int
    {
        global $wpdb;

        $series_key = $this->normalize_series_key($series_key);
        if ($series_key === '' || !$this->supersaas_slots_table_exists()) {
            return 0;
        }

        $source_post_id = max(0, $source_post_id);
        $source_post_type = $source_post_id > 0 ? sanitize_key($source_post_type) : '';
        if ($source_post_id > 0 && $source_post_type === '') {
            $source_post_type = sanitize_key((string) get_post_type($source_post_id));
        }

        $match_state = sanitize_key($match_state);
        if (!in_array($match_state, ['mapped', 'unmapped', 'ignored'], true)) {
            $match_state = $source_post_id > 0 ? 'mapped' : 'unmapped';
        }
        $review_state = sanitize_key($review_state);
        if (!in_array($review_state, ['unreviewed', 'mapped', 'ignored'], true)) {
            $review_state = $match_state === 'ignored' ? 'ignored' : ($match_state === 'mapped' ? 'mapped' : 'unreviewed');
        }

        return (int) $wpdb->update(
            $this->get_supersaas_slots_table_name(),
            [
                'source_post_id' => $source_post_id,
                'source_post_type' => $source_post_type,
                'match_state' => $match_state,
                'review_state' => $review_state,
                'status' => 'skipped',
                'visibility' => 'private',
                'updated_at' => current_time('mysql'),
            ],
            [
                'schedule_key' => 'salonbelegung',
                'series_key' => $series_key,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%s', '%s']
        );
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

        if (!empty($filters['location_post_ids'])) {
            $placeholders = implode(', ', array_fill(0, count($filters['location_post_ids']), '%d'));
            $where[] = "o.location_post_id IN ({$placeholders})";
            foreach ($filters['location_post_ids'] as $location_post_id) {
                $values[] = (int) $location_post_id;
            }
        }

        $time_clause = $this->build_time_where($filters, $values);
        if ($time_clause !== '') {
            $where[] = $time_clause;
        }

        $order = $filters['order'] === 'DESC' ? 'DESC' : 'ASC';
        $limit = (int) $filters['limit'];
        $offset = max(0, (int) $filters['offset']);
        $where_sql = implode(' AND ', $where);

        if ($filters['group_recurring']) {
            return $this->query_grouped($filters, $where_sql, $values, $order);
        }

        $order_values = [];
        $order_sql = $this->build_order_by($filters, $order, $order_values);

        $sql = "SELECT o.* FROM {$table} o WHERE {$where_sql} ORDER BY {$order_sql}";
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

    private function query_grouped(array $filters, string $where_sql, array $values, string $order): array
    {
        global $wpdb;

        $table = $this->get_occurrences_table_name();
        $limit = (int) $filters['limit'];
        $offset = max(0, (int) $filters['offset']);
        $group_key_sql = $this->build_group_key_sql($filters);
        $group_values = $values;
        $group_sql = "SELECT {$group_key_sql} AS group_key, MIN(o.starts_at) AS group_start, MIN(o.id) AS first_id, COUNT(*) AS row_count
            FROM {$table} o
            WHERE {$where_sql}
            GROUP BY group_key
            ORDER BY group_start {$order}, first_id {$order}";

        if ($limit > 0) {
            $group_sql .= ' LIMIT %d OFFSET %d';
            $group_values[] = $limit;
            $group_values[] = $offset;
        } elseif ($offset > 0) {
            $group_sql .= ' LIMIT 18446744073709551615 OFFSET %d';
            $group_values[] = $offset;
        }

        $group_rows = $wpdb->get_results($wpdb->prepare($group_sql, $group_values), ARRAY_A);
        $group_rows = is_array($group_rows) ? $group_rows : [];
        $group_keys = array_values(array_filter(array_map(static function (array $row): string {
            return trim((string) ($row['group_key'] ?? ''));
        }, $group_rows)));

        if (empty($group_keys)) {
            return [];
        }

        $row_values = $values;
        $key_placeholders = implode(', ', array_fill(0, count($group_keys), '%s'));
        foreach ($group_keys as $group_key) {
            $row_values[] = $group_key;
        }

        $row_sql = "SELECT o.*, {$group_key_sql} AS _occurrence_group_key
            FROM {$table} o
            WHERE {$where_sql}
              AND {$group_key_sql} IN ({$key_placeholders})
            ORDER BY o.starts_at {$order}, o.id {$order}";
        $rows = $wpdb->get_results($wpdb->prepare($row_sql, $row_values), ARRAY_A);
        $rows = is_array($rows) ? $rows : [];

        $rows_by_group = [];
        foreach ($rows as $row) {
            $group_key = trim((string) ($row['_occurrence_group_key'] ?? ''));
            if ($group_key === '') {
                continue;
            }
            $formatted = $this->format_timeline_row($row);
            $formatted['_occurrence_group_key'] = $group_key;
            $rows_by_group[$group_key][] = $formatted;
        }

        $results = [];
        foreach ($group_keys as $group_key) {
            $rows_for_group = $rows_by_group[$group_key] ?? [];
            if (empty($rows_for_group)) {
                continue;
            }
            foreach ($this->group_recurring_tour_rows($rows_for_group, $filters) as $row) {
                $results[] = $this->strip_internal_group_key($row);
            }
        }

        return $results;
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
        $location_post_ids = isset($filters['location_post_ids']) && is_array($filters['location_post_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $filters['location_post_ids']))))
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
            'location_post_ids' => $location_post_ids,
            'origin' => isset($filters['origin']) ? sanitize_key((string) $filters['origin']) : '',
            'tag' => isset($filters['tag']) ? strtoupper(sanitize_text_field((string) $filters['tag'])) : '',
            'group_recurring' => !empty($filters['group_recurring']),
            'group_recurring_by_month' => !empty($filters['group_recurring_by_month']),
            'group_recurring_by_source' => !empty($filters['group_recurring_by_source']),
        ];
    }

    private function build_group_key_sql(array $filters): string
    {
        if (!empty($filters['group_recurring_by_source'])) {
            $base = "CASE
                WHEN o.kind = 'tour' AND o.source_post_id > 0 THEN CONCAT('source:', o.source_post_id)
                WHEN o.kind = 'tour' AND o.series_id > 0 THEN CONCAT('series-id:', o.series_id)
                WHEN o.kind = 'tour' AND o.series_key <> '' THEN CONCAT('series:', o.series_key)
                ELSE CONCAT('item:', o.id)
            END";
        } else {
            $base = "CASE
                WHEN o.kind = 'tour' AND o.series_id > 0 THEN CONCAT('series-id:', o.series_id)
                WHEN o.kind = 'tour' AND o.series_key <> '' THEN CONCAT('series:', o.series_key)
                WHEN o.kind = 'tour' AND o.source_post_id > 0 THEN CONCAT('source:', o.source_post_id)
                ELSE CONCAT('item:', o.id)
            END";
        }

        if (!empty($filters['group_recurring_by_month'])) {
            return "CONCAT(({$base}), '|month:', DATE_FORMAT(o.starts_at, '%Y-%m'))";
        }

        return $base;
    }

    private function group_recurring_tour_rows(array $rows, array $filters): array
    {
        if (empty($rows)) {
            return [];
        }

        $grouped_rows = [];
        $group_indexes = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !$this->is_groupable_tour_row($row)) {
                $grouped_rows[] = $row;
                continue;
            }

            $group_key = $this->get_row_group_key($row, $filters);
            if ($group_key === '' || !isset($group_indexes[$group_key])) {
                $row['occurrences'] = [$row];
                $group_indexes[$group_key] = count($grouped_rows);
                $grouped_rows[] = $row;
                continue;
            }

            $target_index = $group_indexes[$group_key];
            if (empty($grouped_rows[$target_index]['occurrences']) || !is_array($grouped_rows[$target_index]['occurrences'])) {
                $grouped_rows[$target_index]['occurrences'] = [$grouped_rows[$target_index]];
            }
            $grouped_rows[$target_index]['occurrences'][] = $row;
        }

        foreach ($grouped_rows as &$row) {
            if (!is_array($row) || empty($row['occurrences']) || !is_array($row['occurrences'])) {
                continue;
            }

            $occurrences = array_values(array_filter($row['occurrences'], 'is_array'));
            if (count($occurrences) <= 1) {
                unset($row['occurrences']);
                continue;
            }

            $row['grouped'] = true;
            $row['occurrences'] = $occurrences;
            $row['occurrence_count'] = count($occurrences);
        }
        unset($row);

        return $grouped_rows;
    }

    private function strip_internal_group_key(array $row): array
    {
        unset($row['_occurrence_group_key']);

        if (!empty($row['occurrences']) && is_array($row['occurrences'])) {
            $row['occurrences'] = array_values(array_map(function ($occurrence): array {
                return is_array($occurrence) ? $this->strip_internal_group_key($occurrence) : [];
            }, $row['occurrences']));
        }

        return $row;
    }

    private function is_groupable_tour_row(array $row): bool
    {
        return $this->normalize_kind((string) ($row['type'] ?? '')) === 'tour';
    }

    private function get_row_group_key(array $row, array $filters): string
    {
        $series_id = (int) ($row['series_id'] ?? 0);
        $series_key = trim((string) ($row['series_key'] ?? ''));
        $source_post_id = (int) ($row['source_post_id'] ?? 0);

        if (!empty($filters['group_recurring_by_source']) && $source_post_id > 0) {
            $base_key = 'source:' . $source_post_id;
        } elseif ($series_id > 0) {
            $base_key = 'series-id:' . $series_id;
        } elseif ($series_key !== '') {
            $base_key = 'series:' . $series_key;
        } elseif ($source_post_id > 0) {
            $base_key = 'source:' . $source_post_id;
        } else {
            $item_id = (int) ($row['id'] ?? 0);
            $base_key = $item_id > 0 ? 'item:' . $item_id : '';
        }

        if ($base_key === '' || empty($filters['group_recurring_by_month'])) {
            return $base_key;
        }

        $start_raw = trim((string) ($row['start_raw'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}/', $start_raw, $matches)) {
            return $base_key;
        }

        return $base_key . '|month:' . $matches[0];
    }

    private function build_order_by(array $filters, string $order, array &$values): string
    {
        if ($order === 'ASC' && $filters['time_mode'] === 'upcoming' && !empty($filters['include_running_ranges'])) {
            $now = current_time('mysql');
            $deferred_condition = "(o.is_open_ended = 1 AND o.starts_at < %s)";
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
                $deferred_condition = "(o.starts_at < %s AND o.is_open_ended = 1)";
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
            $end = $this->get_future_horizon_end_mysql();

            if (!empty($filters['include_running_ranges'])) {
                $values[] = $now;
                $values[] = $end;
                $values[] = $now;
                $values[] = $end;
                return '((o.starts_at >= %s AND o.starts_at <= %s) OR ((o.is_open_ended = 1 OR (o.ends_at IS NOT NULL AND o.ends_at >= %s)) AND o.starts_at <= %s))';
            }

            $values[] = $now;
            $values[] = $end;
            return '(o.starts_at >= %s AND o.starts_at <= %s)';
        }

        if ($mode === 'past') {
            $values[] = $now;
            $values[] = $now;
            return '(o.is_open_ended = 0 AND ((o.ends_at IS NOT NULL AND o.ends_at < %s) OR (o.ends_at IS NULL AND o.starts_at < %s)))';
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
                return '((o.starts_at >= %s AND o.starts_at <= %s) OR ((o.is_open_ended = 1 OR (o.ends_at IS NOT NULL AND o.ends_at >= %s)) AND o.starts_at <= %s))';
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
                    return '((o.starts_at >= %s AND o.starts_at <= %s) OR ((o.is_open_ended = 1 OR (o.ends_at IS NOT NULL AND o.ends_at >= %s)) AND o.starts_at <= %s))';
                }

                $values[] = $start;
                $values[] = $end;
                return '(o.starts_at >= %s AND o.starts_at <= %s)';
            }

            if ($start !== '') {
                $values[] = $start;
                $values[] = $start;
                $values[] = $start;
                return '((o.starts_at >= %s) OR (o.is_open_ended = 1 AND o.starts_at <= %s) OR (o.ends_at IS NOT NULL AND o.ends_at >= %s))';
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
        $is_open_ended = !empty($row['is_open_ended']) || strpos($ends_at, '2099-12-31') === 0;
        if ($is_open_ended) {
            $ends_at = '';
        }
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
        $date_label = $start_ts ? $this->format_date_long_de($start_ts, wp_timezone()) : $starts_at;
        $day_label = $start_ts ? $this->format_day_short_de($start_ts, wp_timezone()) : $date_label;

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
                $time_label = sprintf(__('bis %s', 'iss-occurrences'), $this->format_date_long_de($end_ts, wp_timezone()));
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

        $slot_id = isset($row['external_id']) ? trim((string) $row['external_id']) : '';
        if (($row['origin'] ?? '') === 'supersaas' && strpos($slot_id, ':') !== false) {
            $parts = explode(':', $slot_id);
            $slot_id = isset($parts[1]) ? (string) $parts[1] : '';
        }

        $content_url = $source_post_id > 0 ? get_permalink($source_post_id) : '';
        $target_url = '';
        if ($source_post_id > 0) {
            $target_url = esc_url_raw(trim((string) get_post_meta($source_post_id, 'iss_timeline_target_url', true)));
            if ($target_url !== '') {
                $content_url = $target_url;
            }
        }

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'title' => $title,
            'start_raw' => $starts_at,
            'date_raw' => $starts_at,
            'date_label' => $date_label,
            'day_label' => $day_label,
            'time_label' => $time_label,
            'datetime_label' => $datetime_label,
            'end_raw' => $ends_at,
            'type' => isset($row['kind']) ? sanitize_key((string) $row['kind']) : '',
            'series_id' => isset($row['series_id']) ? (int) $row['series_id'] : 0,
            'series_key' => trim((string) ($row['series_key'] ?? '')),
            'summary' => $summary,
            'cta_mode' => $target_url !== '' ? 'external' : 'details',
            'cta_url' => $target_url,
            'cta_label' => __('Mehr erfahren', 'iss-occurrences'),
            'booking_url' => isset($row['booking_url']) ? (string) $row['booking_url'] : '',
            'slot_id' => $slot_id,
            'slot_start' => $starts_at,
            'source_post_id' => $source_post_id,
            'source_post_type' => $source_post_type,
            'location_post_id' => isset($row['location_post_id']) ? (int) $row['location_post_id'] : 0,
            'location_label' => isset($row['location_label']) ? (string) $row['location_label'] : '',
            'date_source' => isset($row['date_source']) ? sanitize_key((string) $row['date_source']) : '',
            'availability' => isset($row['availability_state']) ? sanitize_key((string) $row['availability_state']) : '',
            'availability_state' => isset($row['availability_state']) ? sanitize_key((string) $row['availability_state']) : '',
            'available' => array_key_exists('capacity_available', $row) && (int) $row['capacity_available'] >= 0 ? (int) $row['capacity_available'] : null,
            'capacity' => array_key_exists('capacity_total', $row) && (int) $row['capacity_total'] >= 0 ? (int) $row['capacity_total'] : null,
            'tag' => isset($row['tag']) ? strtoupper(sanitize_text_field((string) $row['tag'])) : '',
            'content_url' => $content_url,
            'year' => $is_running_open_ended ? (int) wp_date('Y', null, wp_timezone()) : ($start_ts ? (int) wp_date('Y', $start_ts, wp_timezone()) : null),
        ];
    }

    private function get_future_horizon_months(): int
    {
        $months = (int) apply_filters('iss_occurrences_future_horizon_months', 6);
        return $months > 0 ? $months : 1;
    }

    private function get_future_horizon_end_mysql(): string
    {
        try {
            $tz = wp_timezone();
            $now = new DateTimeImmutable('now', $tz);
            $end = $now->modify('+' . $this->get_future_horizon_months() . ' months');
            return $end->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return current_time('mysql');
        }
    }

    private function month_name_de(int $month): string
    {
        $names = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'März',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember',
        ];

        return $names[$month] ?? '';
    }

    private function weekday_short_de(int $weekday): string
    {
        $names = [
            1 => 'Mo.',
            2 => 'Di.',
            3 => 'Mi.',
            4 => 'Do.',
            5 => 'Fr.',
            6 => 'Sa.',
            7 => 'So.',
        ];

        return $names[$weekday] ?? '';
    }

    private function format_date_long_de(int $timestamp, ?DateTimeZone $timezone = null): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        $timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
        $day = wp_date('j', $timestamp, $timezone);
        $month_name = $this->month_name_de((int) wp_date('n', $timestamp, $timezone));
        $year = wp_date('Y', $timestamp, $timezone);

        return sprintf('%s. %s %s', $day, $month_name, $year);
    }

    private function format_day_short_de(int $timestamp, ?DateTimeZone $timezone = null): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        $timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
        $weekday = $this->weekday_short_de((int) wp_date('N', $timestamp, $timezone));
        $date_part = wp_date('d.m.', $timestamp, $timezone);

        return trim($weekday . ' ' . $date_part);
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
        $ready = iss_occurrences_get_service()->tables_exist();
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
