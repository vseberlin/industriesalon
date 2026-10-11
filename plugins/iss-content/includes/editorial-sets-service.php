<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Editorial Sets use plugin-owned custom tables.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table names are constrained to this service.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic table names are constrained to this service.

class ISS_Content_Editorial_Sets_Service
{
    public function get_sets_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_editorial_sets';
    }

    public function get_items_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_editorial_set_items';
    }

    public function get_links_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_editorial_set_links';
    }

    public function get_audit_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_editorial_set_audit';
    }

    public function maybe_install_schema(): void
    {
        $stored = (string) get_option(ISS_CONTENT_EDITORIAL_SETS_SCHEMA_OPTION, '');
        if ($stored === ISS_CONTENT_EDITORIAL_SETS_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        require_once dirname(__DIR__, 2) . '/iss-core/schema.php';

        $statements = $this->get_schema_statements();
        foreach ($statements as $statement) {
            dbDelta($statement);
        }
        iss_core_verify_schema($statements);
        update_option(ISS_CONTENT_EDITORIAL_SETS_SCHEMA_OPTION, ISS_CONTENT_EDITORIAL_SETS_SCHEMA_VERSION, false);
        if ((string) get_option(ISS_CONTENT_EDITORIAL_SETS_SCHEMA_OPTION, '') !== ISS_CONTENT_EDITORIAL_SETS_SCHEMA_VERSION) {
            throw new RuntimeException('Schema version could not be recorded.');
        }
    }

    public function get_schema_statements(): array
    {
        global $wpdb;

        $sets_table = $this->get_sets_table_name();
        $items_table = $this->get_items_table_name();
        $links_table = $this->get_links_table_name();
        $audit_table = $this->get_audit_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sets_sql = "CREATE TABLE {$sets_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            set_key varchar(191) NOT NULL,
            title text NOT NULL,
            summary text NOT NULL,
            set_role varchar(40) NOT NULL DEFAULT 'intake',
            status varchar(30) NOT NULL DEFAULT 'working',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY set_key (set_key),
            KEY status (status),
            KEY set_role (set_role),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $items_sql = "CREATE TABLE {$items_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            set_id bigint(20) unsigned NOT NULL DEFAULT 0,
            kind varchar(40) NOT NULL DEFAULT 'wp_media',
            source varchar(80) NOT NULL DEFAULT 'wp-media',
            source_id varchar(191) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'pending',
            label text NOT NULL,
            origin varchar(80) NOT NULL DEFAULT 'manual_upload',
            provenance_json longtext NOT NULL,
            rights_json longtext NOT NULL,
            notes text NOT NULL,
            decay_at datetime NULL,
            retain tinyint(1) NOT NULL DEFAULT 0,
            retain_reason text NOT NULL,
            position int(11) NOT NULL DEFAULT 0,
            promoted_target_type varchar(60) NOT NULL DEFAULT '',
            promoted_target_id bigint(20) unsigned NOT NULL DEFAULT 0,
            promoted_at datetime NULL,
            archive_candidate_status varchar(40) NOT NULL DEFAULT '',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_in_set (set_id, kind, source, source_id),
            KEY set_status_position (set_id, status, position, id),
            KEY source_lookup (source, source_id),
            KEY status_decay (status, decay_at),
            KEY promoted_target (promoted_target_type, promoted_target_id),
            KEY archive_candidate_status (archive_candidate_status)
        ) {$charset_collate};";

        $links_sql = "CREATE TABLE {$links_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            set_id bigint(20) unsigned NOT NULL,
            context_type varchar(60) NOT NULL DEFAULT '',
            context_id bigint(20) unsigned NOT NULL DEFAULT 0,
            link_role varchar(60) NOT NULL DEFAULT 'source_material',
            position int(11) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY set_context_role (set_id, context_type, context_id, link_role),
            KEY context_lookup (context_type, context_id, link_role, position),
            KEY set_position (set_id, position)
        ) {$charset_collate};";

        $audit_sql = "CREATE TABLE {$audit_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            set_id bigint(20) unsigned NOT NULL DEFAULT 0,
            item_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(80) NOT NULL DEFAULT '',
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            message text NOT NULL,
            payload_json longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY set_id (set_id, id),
            KEY item_id (item_id, id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";

        return [$sets_sql, $items_sql, $links_sql, $audit_sql];
    }

    public function get_allowed_set_roles(): array
    {
        return [
            'intake',
            'review',
            'gallery_candidate',
            'approved_gallery',
            'rueckblick_source',
            'source_material',
            'archive_candidate',
        ];
    }

    public function get_allowed_set_statuses(): array
    {
        return ['working', 'reviewing', 'approved', 'promoted', 'retained', 'stale', 'archived'];
    }

    public function get_allowed_item_statuses(): array
    {
        return ['pending', 'reviewing', 'approved', 'rejected', 'stale', 'promoted', 'retained'];
    }

    public function get_allowed_context_types(): array
    {
        $types = [
            ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
            'rueckblick',
            'fuehrung',
            'register_place',
            ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
            ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
            'publication',
            'page',
            'archive_set',
            'archive_object',
        ];

        return array_values(array_unique(array_filter(array_map(
            'sanitize_key',
            (array) apply_filters('iss_content_editorial_set_context_types', $types)
        ))));
    }

    public function normalize_key(string $key): string
    {
        $key = sanitize_title($key);
        $key = preg_replace('/[^a-z0-9_-]+/', '-', $key);
        return trim((string) $key, '-_');
    }

    public function build_unique_set_key(string $title = ''): string
    {
        $base = $this->normalize_key($title);
        if ($base === '') {
            $base = 'set-' . gmdate('Ymd-His');
        }

        $candidate = $base;
        $index = 2;
        while ($this->get_set_by_key($candidate)) {
            $candidate = $base . '-' . $index;
            $index++;
        }
        global $wpdb;
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Editorial Set creation could not check its generated key.');
        }

        return $candidate;
    }

    public function sanitize_set_role(string $role): string
    {
        $role = sanitize_key($role);
        return in_array($role, $this->get_allowed_set_roles(), true) ? $role : 'intake';
    }

    public function sanitize_set_status(string $status): string
    {
        $status = sanitize_key($status);
        return in_array($status, $this->get_allowed_set_statuses(), true) ? $status : 'working';
    }

    public function sanitize_item_status(string $status): string
    {
        $status = sanitize_key($status);
        return in_array($status, $this->get_allowed_item_statuses(), true) ? $status : 'pending';
    }

    public function sanitize_context_type(string $context_type): string
    {
        $context_type = sanitize_key($context_type);
        return in_array($context_type, $this->get_allowed_context_types(), true) ? $context_type : '';
    }

    public function create_set(array $args): int
    {
        global $wpdb;

        $set_id = 0;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_audit_table_name()], function () use ($wpdb, $args, &$set_id): void {
            $timestamp = current_time('mysql', true);
            $title = trim(sanitize_text_field((string) ($args['title'] ?? '')));
            $set_key = $this->normalize_key((string) ($args['set_key'] ?? ''));
            if ($set_key === '') {
                $set_key = $this->build_unique_set_key($title);
            }

            $data = [
                'set_key' => $set_key,
                'title' => $title !== '' ? $title : __('Untitled set', 'iss-content-model'),
                'summary' => sanitize_textarea_field((string) ($args['summary'] ?? '')),
                'set_role' => $this->sanitize_set_role((string) ($args['set_role'] ?? 'intake')),
                'status' => $this->sanitize_set_status((string) ($args['status'] ?? 'working')),
                'created_by' => get_current_user_id(),
                'updated_by' => get_current_user_id(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            $inserted = $wpdb->insert(
                $this->get_sets_table_name(),
                $data,
                ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
            );

            if ($inserted !== 1) {
                return;
            }

            $set_id = (int) $wpdb->insert_id;
            if ($set_id <= 0 || !$this->record_audit($set_id, 0, 'set_created', __('Set created.', 'iss-content-model'), $data)) {
                throw new RuntimeException('Editorial Set creation could not record its required audit row.');
            }
        }, 'Editorial Set creation');

        return $set_id;
    }

    public function update_set(int $set_id, array $args): bool
    {
        if ($set_id <= 0) {
            return false;
        }

        global $wpdb;
        $saved = false;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_audit_table_name()], function () use ($wpdb, $set_id, $args, &$saved): void {
            $set = $this->lock_set_for_write($set_id);
            if (!$set) {
                return;
            }

            $data = [
                'title' => trim(sanitize_text_field((string) ($args['title'] ?? ''))),
                'summary' => sanitize_textarea_field((string) ($args['summary'] ?? '')),
                'set_role' => $this->sanitize_set_role((string) ($args['set_role'] ?? 'intake')),
                'status' => $this->sanitize_set_status((string) ($args['status'] ?? 'working')),
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql', true),
            ];
            if ($data['title'] === '') {
                unset($data['title']);
            }

            if ($wpdb->update($this->get_sets_table_name(), $data, ['id' => $set_id], array_fill(0, count($data), '%s'), ['%d']) === false) {
                return;
            }
            $stored = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->get_sets_table_name()} WHERE id = %d", $set_id), ARRAY_A);
            if ($wpdb->last_error !== '' || !$stored || array_diff_assoc($data, $stored)) {
                throw new RuntimeException('Editorial Set update could not verify its saved values.');
            }
            if (!$this->record_audit($set_id, 0, 'set_updated', __('Set updated.', 'iss-content-model'), $data)) {
                throw new RuntimeException('Editorial Set update could not record its required audit row.');
            }
            $saved = true;
        }, 'Editorial Set update');

        return $saved;
    }

    public function update_set_key(int $set_id, string $set_key): bool
    {
        $set_key = $this->normalize_key($set_key);
        if ($set_id <= 0 || $set_key === '') {
            return false;
        }

        global $wpdb;
        $saved = false;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_audit_table_name()], function () use ($wpdb, $set_id, $set_key, &$saved): void {
            $set = $this->lock_set_for_write($set_id);
            if (!$set) {
                return;
            }
            if ((string) $set['set_key'] === $set_key) {
                $saved = true;
                return;
            }

            $existing = $this->get_set_by_key($set_key);
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Editorial Set key update could not check its new key.');
            }
            if ($existing && (int) $existing['id'] !== $set_id) {
                return;
            }

            $data = [
                'set_key' => $set_key,
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql', true),
            ];
            if ($wpdb->update($this->get_sets_table_name(), $data, ['id' => $set_id], ['%s', '%d', '%s'], ['%d']) === false) {
                return;
            }
            $stored = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->get_sets_table_name()} WHERE id = %d", $set_id), ARRAY_A);
            if ($wpdb->last_error !== '' || !$stored || array_diff_assoc($data, $stored)) {
                throw new RuntimeException('Editorial Set key update could not verify its saved values.');
            }
            if (!$this->record_audit($set_id, 0, 'set_key_updated', __('Set key updated.', 'iss-content-model'), [
                'from' => (string) $set['set_key'],
                'to' => $set_key,
            ])) {
                throw new RuntimeException('Editorial Set key update could not record its required audit row.');
            }
            $saved = true;
        }, 'Editorial Set key update');

        return $saved;
    }

    public function get_set(int $set_id): ?array
    {
        if ($set_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*,
                (SELECT COUNT(1) FROM {$this->get_items_table_name()} i WHERE i.set_id = s.id) AS item_count,
                (SELECT COUNT(1) FROM {$this->get_links_table_name()} l WHERE l.set_id = s.id) AS link_count
            FROM {$this->get_sets_table_name()} s
            WHERE s.id = %d
            LIMIT 1",
            $set_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function get_set_by_key(string $set_key): ?array
    {
        $set_key = $this->normalize_key($set_key);
        if ($set_key === '') {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_sets_table_name()} WHERE set_key = %s LIMIT 1",
            $set_key
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function search_sets(array $args = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $values = [];
        $search = trim((string) ($args['search'] ?? ''));
        $status = sanitize_key((string) ($args['status'] ?? ''));
        $role = sanitize_key((string) ($args['set_role'] ?? ''));
        $context_type = $this->sanitize_context_type((string) ($args['context_type'] ?? ''));
        $context_id = absint($args['context_id'] ?? 0);
        $joins = [];

        if ($search !== '') {
            $where[] = '(s.title LIKE %s OR s.set_key LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $like;
            $values[] = $like;
        }
        if ($status !== '' && in_array($status, $this->get_allowed_set_statuses(), true)) {
            $where[] = 's.status = %s';
            $values[] = $status;
        }
        if ($role !== '' && in_array($role, $this->get_allowed_set_roles(), true)) {
            $where[] = 's.set_role = %s';
            $values[] = $role;
        }
        if ($context_type !== '' && $context_id > 0) {
            $joins[] = "INNER JOIN {$this->get_links_table_name()} context_link ON context_link.set_id = s.id";
            $where[] = 'context_link.context_type = %s AND context_link.context_id = %d';
            $values[] = $context_type;
            $values[] = $context_id;
        }

        $page = max(1, (int) ($args['page'] ?? 1));
        $per_page = min(100, max(1, (int) ($args['per_page'] ?? 30)));
        $offset = ($page - 1) * $per_page;
        $where_sql = implode(' AND ', $where);

        $join_sql = implode(' ', $joins);
        $sql = "SELECT DISTINCT s.*,
            (SELECT COUNT(1) FROM {$this->get_items_table_name()} i WHERE i.set_id = s.id) AS item_count,
            (SELECT COUNT(1) FROM {$this->get_links_table_name()} l WHERE l.set_id = s.id) AS link_count
            FROM {$this->get_sets_table_name()} s
            {$join_sql}
            WHERE {$where_sql}
            ORDER BY s.updated_at DESC, s.id DESC
            LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $items = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        $count_values = array_slice($values, 0, -2);
        $count_sql = "SELECT COUNT(DISTINCT s.id) FROM {$this->get_sets_table_name()} s {$join_sql} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($count_values ? $wpdb->prepare($count_sql, $count_values) : $count_sql);

        return [
            'total' => $total,
            'items' => is_array($items) ? $items : [],
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    public function add_item(array $args): int
    {
        global $wpdb;

        $set_id = absint($args['set_id'] ?? 0);
        $kind = sanitize_key((string) ($args['kind'] ?? 'wp_media'));
        $source = sanitize_key((string) ($args['source'] ?? 'wp-media'));
        $source_id = sanitize_text_field((string) ($args['id'] ?? $args['source_id'] ?? ''));

        if ($source_id === '') {
            return 0;
        }

        $item_id = 0;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_items_table_name(), $this->get_audit_table_name()], function () use ($wpdb, $args, $set_id, $kind, $source, $source_id, &$item_id): void {
            if ($set_id > 0 && !$this->lock_set_for_write($set_id)) {
                return;
            }
            $timestamp = current_time('mysql', true);
            $existing = $this->find_item($set_id, $kind, $source, $source_id);
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Editorial Set item creation could not check its existing item.');
            }
            if ($existing) {
                $item_id = (int) $existing['id'];
                return;
            }

            $data = [
                'set_id' => $set_id,
                'kind' => $kind,
                'source' => $source,
                'source_id' => $source_id,
                'status' => $this->sanitize_item_status((string) ($args['status'] ?? 'pending')),
                'label' => sanitize_textarea_field((string) ($args['label'] ?? '')),
                'origin' => sanitize_key((string) ($args['origin'] ?? 'manual_upload')),
                'provenance_json' => $this->encode_json_field($args['provenance'] ?? []),
                'rights_json' => $this->encode_json_field($args['rights'] ?? []),
                'notes' => sanitize_textarea_field((string) ($args['notes'] ?? '')),
                'decay_at' => $this->sanitize_mysql_datetime_or_null((string) ($args['decay_at'] ?? '')),
                'retain' => !empty($args['retain']) ? 1 : 0,
                'retain_reason' => sanitize_textarea_field((string) ($args['retain_reason'] ?? '')),
                'position' => (int) ($args['position'] ?? $this->next_item_position($set_id)),
                'created_by' => get_current_user_id(),
                'updated_by' => get_current_user_id(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            $inserted = $wpdb->insert(
                $this->get_items_table_name(),
                $data,
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s']
            );

            if ($inserted !== 1) {
                return;
            }

            $item_id = (int) $wpdb->insert_id;
            $this->touch_set($set_id);
            if ($item_id <= 0 || !$this->record_audit($set_id, $item_id, 'item_added', __('Item added.', 'iss-content-model'), $data)) {
                throw new RuntimeException('Editorial Set item creation could not record its required audit row.');
            }
        }, 'Editorial Set item creation');

        return $item_id;
    }

    public function source_is_registered(string $kind, string $source, string $source_id): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->get_items_table_name()} WHERE kind=%s AND source=%s AND source_id=%s LIMIT 1", $kind, $source, $source_id));
    }

    public function find_item(int $set_id, string $kind, string $source, string $source_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_items_table_name()}
            WHERE set_id = %d AND kind = %s AND source = %s AND source_id = %s
            LIMIT 1",
            $set_id,
            sanitize_key($kind),
            sanitize_key($source),
            sanitize_text_field($source_id)
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function get_item(int $item_id): ?array
    {
        if ($item_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_items_table_name()} WHERE id = %d LIMIT 1",
            $item_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function list_items(array $args = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $values = [];
        $set_id = isset($args['set_id']) ? absint($args['set_id']) : null;
        $status = sanitize_key((string) ($args['status'] ?? ''));
        $stale = !empty($args['stale']);

        if ($set_id !== null) {
            $where[] = 'i.set_id = %d';
            $values[] = $set_id;
        }
        if ($status !== '' && in_array($status, $this->get_allowed_item_statuses(), true)) {
            $where[] = 'i.status = %s';
            $values[] = $status;
        }
        if ($stale) {
            $where[] = 'i.decay_at IS NOT NULL AND i.decay_at <= %s AND i.retain = 0 AND i.status NOT IN ("promoted", "retained", "rejected")';
            $values[] = current_time('mysql', true);
        }

        $page = max(1, (int) ($args['page'] ?? 1));
        $per_page = min(120, max(1, (int) ($args['per_page'] ?? 60)));
        $offset = ($page - 1) * $per_page;
        $where_sql = implode(' AND ', $where);
        $values[] = $per_page;
        $values[] = $offset;

        $sql = "SELECT i.*, s.title AS set_title, s.set_key
            FROM {$this->get_items_table_name()} i
            LEFT JOIN {$this->get_sets_table_name()} s ON s.id = i.set_id
            WHERE {$where_sql}
            ORDER BY i.updated_at DESC, i.id DESC
            LIMIT %d OFFSET %d";

        $items = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        $count_values = array_slice($values, 0, -2);
        $count_sql = "SELECT COUNT(*) FROM {$this->get_items_table_name()} i WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($count_values ? $wpdb->prepare($count_sql, $count_values) : $count_sql);

        return [
            'total' => $total,
            'items' => is_array($items) ? $items : [],
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    public function update_item(int $item_id, array $args, array $additional_audit = []): bool
    {
        if ($item_id <= 0) {
            return false;
        }
        $saved = false;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_items_table_name(), $this->get_audit_table_name()], function () use ($item_id, $args, $additional_audit, &$saved): void {
            $item = $this->lock_items_for_write([$item_id])[$item_id] ?? null;
            if (!$item) {
                return;
            }
            $data = [
                'status' => $this->sanitize_item_status((string) ($args['status'] ?? $item['status'])),
                'label' => sanitize_textarea_field((string) ($args['label'] ?? $item['label'])),
                'notes' => sanitize_textarea_field((string) ($args['notes'] ?? $item['notes'])),
                'rights_json' => $this->encode_json_field($args['rights'] ?? $this->decode_json_field((string) $item['rights_json'])),
                'provenance_json' => $this->encode_json_field($args['provenance'] ?? $this->decode_json_field((string) $item['provenance_json'])),
                'decay_at' => $this->sanitize_mysql_datetime_or_null((string) ($args['decay_at'] ?? $item['decay_at'])),
                'retain' => !empty($args['retain'] ?? $item['retain']) ? 1 : 0,
                'retain_reason' => sanitize_textarea_field((string) ($args['retain_reason'] ?? $item['retain_reason'])),
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql', true),
            ];

            $rights = json_decode($data['rights_json'], true);
            if ($data['status'] === 'approved' && (empty($rights['attribution']) || empty($rights['license']) || (string) ($rights['consent'] ?? '') !== '1')) {
                return;
            }

            if (!$this->update_item_row($item_id, $data, ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s'])) {
                return;
            }

            $this->touch_set((int) $item['set_id']);
            if (!$this->record_audit((int) $item['set_id'], $item_id, 'item_updated', __('Item updated.', 'iss-content-model'), $data)) {
                throw new RuntimeException('Editorial Set item update could not record its required audit row.');
            }
            if ($additional_audit && (empty($additional_audit['action']) || !$this->record_audit(
                (int) $item['set_id'],
                $item_id,
                (string) $additional_audit['action'],
                (string) ($additional_audit['message'] ?? ''),
                (array) ($additional_audit['payload'] ?? [])
            ))) {
                throw new RuntimeException('Editorial Set item update could not record its integration audit row.');
            }
            $saved = true;
        }, 'Editorial Set item update');

        return $saved;
    }

    public function move_items(array $item_ids, int $set_id): int
    {
        if ($set_id < 0 || !$item_ids) {
            return 0;
        }
        $moved = 0;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_items_table_name(), $this->get_audit_table_name()], function () use ($item_ids, $set_id, &$moved): void {
            $items = $this->lock_items_for_write($item_ids, [$set_id]);
            foreach ($item_ids as $item_id) {
                $item_id = absint($item_id);
                $item = $items[$item_id] ?? null;
                if (!$item) {
                    continue;
                }
                $data = [
                    'set_id' => $set_id,
                    'position' => $this->next_item_position($set_id),
                    'updated_by' => get_current_user_id(),
                    'updated_at' => current_time('mysql', true),
                ];
                if (!$this->update_item_row($item_id, $data, ['%d', '%d', '%d', '%s'])) {
                    continue;
                }
                $this->touch_set((int) $item['set_id']);
                $this->touch_set($set_id);
                if (!$this->record_audit($set_id, $item_id, 'item_moved', __('Item moved.', 'iss-content-model'), [
                    'from_set_id' => (int) $item['set_id'],
                    'to_set_id' => $set_id,
                ])) {
                    throw new RuntimeException('Editorial Set item move could not record its required audit row.');
                }
                $items[$item_id]['set_id'] = $set_id;
                $moved++;
            }
        }, 'Editorial Set item move');

        return $moved;
    }

    public function batch_update_status(array $item_ids, string $status): int
    {
        $status = $this->sanitize_item_status($status);
        $updated = 0;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_items_table_name(), $this->get_audit_table_name()], function () use ($item_ids, $status, &$updated): void {
            $this->lock_items_for_write($item_ids);
            foreach ($item_ids as $item_id) {
                if ($this->update_item((int) $item_id, ['status' => $status])) {
                    $updated++;
                }
            }
        }, 'Editorial Set status batch');

        return $updated;
    }

    public function attach_context(int $set_id, string $context_type, int $context_id, string $link_role = 'source_material'): int
    {
        $context_type = $this->sanitize_context_type($context_type);
        $context_id = absint($context_id);
        $link_role = sanitize_key($link_role);
        if ($set_id <= 0 || $context_type === '' || $context_id <= 0 || $link_role === '') {
            return 0;
        }

        global $wpdb;

        $link_id = 0;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_links_table_name(), $this->get_audit_table_name()], function () use ($wpdb, $set_id, $context_type, $context_id, $link_role, &$link_id): void {
            if (!$this->lock_set_for_write($set_id)) {
                return;
            }
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->get_links_table_name()}
                WHERE set_id = %d AND context_type = %s AND context_id = %d AND link_role = %s
                LIMIT 1",
                $set_id,
                $context_type,
                $context_id,
                $link_role
            ), ARRAY_A);

            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Editorial Set context attachment could not check its existing link.');
            }
            if (is_array($existing)) {
                $link_id = (int) $existing['id'];
                return;
            }

            $timestamp = current_time('mysql', true);
            $inserted = $wpdb->insert(
                $this->get_links_table_name(),
                [
                    'set_id' => $set_id,
                    'context_type' => $context_type,
                    'context_id' => $context_id,
                    'link_role' => $link_role,
                    'position' => $this->next_link_position($context_type, $context_id),
                    'created_by' => get_current_user_id(),
                    'updated_by' => get_current_user_id(),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                ['%d', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s']
            );

            if ($inserted !== 1) {
                return;
            }

            $link_id = (int) $wpdb->insert_id;
            $this->touch_set($set_id);
            if ($link_id <= 0 || !$this->record_audit($set_id, 0, 'set_attached', __('Set attached to context.', 'iss-content-model'), [
                'context_type' => $context_type,
                'context_id' => $context_id,
                'link_role' => $link_role,
            ])) {
                throw new RuntimeException('Editorial Set context attachment could not record its required audit row.');
            }
        }, 'Editorial Set context attachment');

        return $link_id;
    }

    public function get_links_for_set(int $set_id): array
    {
        if ($set_id <= 0) {
            return [];
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->get_links_table_name()} WHERE set_id = %d ORDER BY position ASC, id ASC",
            $set_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function get_links_for_context(string $context_type, int $context_id): array
    {
        $context_type = $this->sanitize_context_type($context_type);
        $context_id = absint($context_id);
        if ($context_type === '' || $context_id <= 0) {
            return [];
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, s.title, s.set_key, s.status, s.set_role,
                (SELECT COUNT(1) FROM {$this->get_items_table_name()} i WHERE i.set_id = s.id) AS item_count
            FROM {$this->get_links_table_name()} l
            INNER JOIN {$this->get_sets_table_name()} s ON s.id = l.set_id
            WHERE l.context_type = %s AND l.context_id = %d
            ORDER BY l.position ASC, l.id ASC",
            $context_type,
            $context_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function mark_promoted(int $item_id, string $target_type, int $target_id): bool
    {
        if ($item_id <= 0) {
            return false;
        }
        $saved = false;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_items_table_name(), $this->get_audit_table_name()], function () use ($item_id, $target_type, $target_id, &$saved): void {
            $item = $this->lock_items_for_write([$item_id])[$item_id] ?? null;
            if (!$item) {
                return;
            }
            if ($item['status'] === 'promoted') {
                foreach ($this->get_item_uses($item_id) as $use) {
                    if ($use['id'] === $target_id && $use['type'] === $target_type) {
                        $saved = true;
                        return;
                    }
                }
            }

            $updated = $this->update_item_row(
                $item_id,
                [
                    'status' => 'promoted',
                    'promoted_target_type' => sanitize_key($target_type),
                    'promoted_target_id' => absint($target_id),
                    'promoted_at' => current_time('mysql', true),
                    'updated_by' => get_current_user_id(),
                    'updated_at' => current_time('mysql', true),
                ],
                ['%s', '%s', '%d', '%s', '%d', '%s']
            );

            if ($updated === false) {
                return;
            }

            if (!$this->record_audit((int) $item['set_id'], $item_id, 'item_promoted', __('Item promoted.', 'iss-content-model'), [
                'target_type' => $target_type,
                'target_id' => $target_id,
            ])) {
                throw new RuntimeException('Editorial Set promotion could not record its required audit row.');
            }
            $saved = true;
        }, 'Editorial Set promotion');

        return $saved;
    }

    public function mark_archive_candidate(int $item_id, array $metadata = []): bool
    {
        if ($item_id <= 0) {
            return false;
        }
        $saved = false;
        $item = null;
        $this->run_atomic_write([$this->get_sets_table_name(), $this->get_items_table_name(), $this->get_audit_table_name()], function () use ($item_id, $metadata, &$item, &$saved): void {
            $item = $this->lock_items_for_write([$item_id])[$item_id] ?? null;
            if (!$item) {
                return;
            }
            $rights = $this->decode_json_field((string) $item['rights_json']);
            $rights['archive_candidate'] = array_merge($rights['archive_candidate'] ?? [], $metadata);

            $updated = $this->update_item_row(
                $item_id,
                [
                    'archive_candidate_status' => 'candidate',
                    'rights_json' => $this->encode_json_field($rights),
                    'updated_by' => get_current_user_id(),
                    'updated_at' => current_time('mysql', true),
                ],
                ['%s', '%s', '%d', '%s']
            );

            if ($updated === false) {
                return;
            }

            if (!$this->record_audit((int) $item['set_id'], $item_id, 'archive_candidate_marked', __('Archive candidate marked.', 'iss-content-model'), $metadata)) {
                throw new RuntimeException('Editorial Set archive candidate could not record its required audit row.');
            }
            $saved = true;
        }, 'Editorial Set archive candidate');

        if ($saved) {
            do_action('iss_content_editorial_set_archive_candidate', $item, $metadata);
        }
        return $saved;
    }

    public function delete_set_if_safe(int $set_id): bool
    {
        if ($set_id <= 0) {
            return false;
        }

        global $wpdb;
        $sets = $this->get_sets_table_name();
        $items = $this->get_items_table_name();
        $links = $this->get_links_table_name();
        $deleted = false;
        $this->run_atomic_write([$sets, $items, $links], static function () use ($wpdb, $sets, $items, $links, $set_id, &$deleted): void {
            $stored_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $sets WHERE id = %d FOR UPDATE", $set_id));
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Editorial Set deletion could not lock its Set.');
            }
            if ($stored_id !== null) {
                // Preserve the SQL protection predicate and lock every child, beyond any UI page limit.
                $item_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, (status = 'promoted' OR archive_candidate_status <> '') AS is_protected
                     FROM $items WHERE set_id = %d ORDER BY id ASC FOR UPDATE",
                    $set_id
                ), ARRAY_A);
                if ($wpdb->last_error !== '' || !is_array($item_rows)) {
                    throw new RuntimeException('Editorial Set deletion could not read its protected items.');
                }
                $protected = array_filter($item_rows, static fn(array $item): bool => (int) $item['is_protected'] === 1);
                if (!$protected) {
                    $link_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $links WHERE set_id = %d ORDER BY id ASC FOR UPDATE", $set_id));
                    if ($wpdb->last_error !== '') {
                        throw new RuntimeException('Editorial Set deletion could not lock its context links.');
                    }
                    if ($wpdb->delete($items, ['set_id' => $set_id], ['%d']) !== count($item_rows)) {
                        throw new RuntimeException('Editorial Set deletion could not remove all its items.');
                    }
                    if ($wpdb->delete($links, ['set_id' => $set_id], ['%d']) !== count($link_ids)) {
                        throw new RuntimeException('Editorial Set deletion could not remove all its context links.');
                    }
                    if ($wpdb->delete($sets, ['id' => $set_id], ['%d']) !== 1) {
                        throw new RuntimeException('Editorial Set deletion could not remove its Set.');
                    }
                    $deleted = true;
                }
            }
        }, 'Editorial Set deletion');

        return $deleted;
    }

    /** Tables come only from this owner's operations; callers retain existing transactions. */
    private function run_atomic_write(array $tables, callable $write, string $operation): void
    {
        global $wpdb;

        $placeholders = implode(', ', array_fill(0, count($tables), '%s'));
        $states = $wpdb->get_results($wpdb->prepare(
            "SELECT ENGINE AS engine, @@SESSION.in_transaction AS in_transaction,
                @@SESSION.autocommit AS autocommit
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One %s placeholder per service-owned table above.
            $tables
        ), ARRAY_A);
        if ($wpdb->last_error !== '' || !$states || count($states) !== count($tables) || array_filter($states, static function (array $state): bool {
            return ($state['engine'] ?? '') !== 'InnoDB' || !isset($state['in_transaction'], $state['autocommit']);
        })) {
            throw new RuntimeException(esc_html($operation . ' requires MariaDB transaction state and InnoDB tables.'));
        }

        $owns_transaction = (int) $states[0]['in_transaction'] === 0 && (int) $states[0]['autocommit'] === 1;
        $savepoint = wp_unique_id('iss_editorial_set_write_');
        if ($wpdb->query($owns_transaction ? 'START TRANSACTION' : "SAVEPOINT $savepoint") === false) {
            throw new RuntimeException(esc_html($operation . ' could not begin its transaction.'));
        }
        try {
            $write();
            if ($wpdb->query($owns_transaction ? 'COMMIT' : "RELEASE SAVEPOINT $savepoint") === false) {
                throw new RuntimeException(esc_html($operation . ' could not finish its transaction.'));
            }
        } catch (Throwable $error) {
            if ($wpdb->query($owns_transaction ? 'ROLLBACK' : "ROLLBACK TO SAVEPOINT $savepoint") === false) {
                throw new RuntimeException(esc_html($operation . ' failed and rollback could not be confirmed.'), 0, $error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Chain the original Throwable.
            }
            if (!$owns_transaction && $wpdb->query("RELEASE SAVEPOINT $savepoint") === false) {
                throw new RuntimeException(esc_html($operation . ' rolled back but could not release its savepoint.'), 0, $error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Chain the original Throwable.
            }
            throw $error;
        }
    }

    public function preview_item(array $item): array
    {
        $kind = (string) ($item['kind'] ?? '');
        $source = (string) ($item['source'] ?? '');
        $source_id = (string) ($item['source_id'] ?? '');

        if ($kind === 'wp_media' && $source === 'wp-media') {
            $attachment_id = absint($source_id);
            $post = $attachment_id > 0 ? get_post($attachment_id) : null;
            if ($post instanceof WP_Post && $post->post_type === 'attachment') {
                $metadata = wp_get_attachment_metadata($attachment_id);
                return [
                    'title' => (string) get_the_title($post),
                    'thumbnail' => (string) wp_get_attachment_image_url($attachment_id, 'medium'),
                    'url' => (string) wp_get_attachment_url($attachment_id),
                    'mime' => (string) get_post_mime_type($attachment_id),
                    'filename' => basename((string) get_attached_file($attachment_id)),
                    'width' => is_array($metadata) ? (int) ($metadata['width'] ?? 0) : 0,
                    'height' => is_array($metadata) ? (int) ($metadata['height'] ?? 0) : 0,
                    'uploadedAt' => (string) get_post_time('c', true, $post),
                ];
            }
        }

        if ($kind === 'archive_object' && $source === 'iss-archive') {
            $post_id = absint($source_id);
            $post = $post_id > 0 ? get_post($post_id) : null;
            if ($post instanceof WP_Post) {
                return [
                    'title' => (string) get_the_title($post),
                    'thumbnail' => (string) get_the_post_thumbnail_url($post, 'medium'),
                    'url' => (string) get_permalink($post),
                    'mime' => 'archive/object',
                    'filename' => '',
                    'uploadedAt' => (string) get_post_time('c', true, $post),
                ];
            }
        }

        if ($kind === 'external_upload' && $source === 'event-drop') {
            $provenance = $this->decode_json_field((string) ($item['provenance_json'] ?? ''));
            $imported_attachment_id = absint($provenance['imported_attachment_id'] ?? 0);
            $imported_attachment = $imported_attachment_id > 0 ? get_post($imported_attachment_id) : null;
            if ($imported_attachment instanceof WP_Post && $imported_attachment->post_type === 'attachment') {
                $metadata = wp_get_attachment_metadata($imported_attachment_id);
                return [
                    'title' => (string) get_the_title($imported_attachment),
                    'thumbnail' => (string) wp_get_attachment_image_url($imported_attachment_id, 'medium'),
                    'url' => (string) wp_get_attachment_url($imported_attachment_id),
                    'mime' => (string) get_post_mime_type($imported_attachment_id),
                    'filename' => basename((string) get_attached_file($imported_attachment_id)),
                    'width' => is_array($metadata) ? (int) ($metadata['width'] ?? 0) : 0,
                    'height' => is_array($metadata) ? (int) ($metadata['height'] ?? 0) : 0,
                    'uploadedAt' => (string) get_post_time('c', true, $imported_attachment),
                ];
            }

            $path = (string) ($provenance['path'] ?? '');
            $filename = (string) ($provenance['stored_name'] ?? $source_id);
            $mime = $filename !== '' ? (string) wp_check_filetype($filename)['type'] : '';
            $media_url = '';
            if ((int) ($item['id'] ?? 0) > 0) {
                $media_url = add_query_arg(
                    [
                        'action' => 'iss_editorial_set_file_preview',
                        'item_id' => (int) $item['id'],
                        '_wpnonce' => wp_create_nonce('iss_editorial_set_file_' . (int) $item['id']),
                    ],
                    admin_url('admin-ajax.php')
                );
            }

            return [
                'title' => (string) ($item['label'] ?? $filename),
                'thumbnail' => strpos($mime, 'image/') === 0 ? $media_url : '',
                'url' => $media_url,
                'mime' => $mime,
                'filename' => $filename,
                'size' => (int) ($provenance['size_bytes'] ?? (is_file($path) ? filesize($path) : 0)),
                'uploadedAt' => (string) ($provenance['uploaded_at'] ?? ''),
            ];
        }

        return (array) apply_filters('iss_content_editorial_set_item_preview', [], $item);
    }

    public function prepare_set(array $set, bool $include_links = true): array
    {
        $item = [
            'id' => (int) ($set['id'] ?? 0),
            'setKey' => (string) ($set['set_key'] ?? ''),
            'title' => (string) ($set['title'] ?? ''),
            'summary' => (string) ($set['summary'] ?? ''),
            'setRole' => (string) ($set['set_role'] ?? ''),
            'status' => (string) ($set['status'] ?? ''),
            'itemCount' => (int) ($set['item_count'] ?? 0),
            'linkCount' => (int) ($set['link_count'] ?? 0),
            'updatedAt' => (string) ($set['updated_at'] ?? ''),
        ];

        if ($include_links) {
            $item['links'] = array_map([$this, 'prepare_link'], $this->get_links_for_set((int) ($set['id'] ?? 0)));
        }

        return $item;
    }

    public function prepare_item(array $item): array
    {
        $provenance = $this->decode_json_field((string) ($item['provenance_json'] ?? ''));

        return [
            'id' => (int) ($item['id'] ?? 0),
            'setId' => (int) ($item['set_id'] ?? 0),
            'setTitle' => (string) ($item['set_title'] ?? ''),
            'setKey' => (string) ($item['set_key'] ?? ''),
            'kind' => (string) ($item['kind'] ?? ''),
            'source' => (string) ($item['source'] ?? ''),
            'sourceId' => (string) ($item['source_id'] ?? ''),
            'status' => (string) ($item['status'] ?? ''),
            'label' => (string) ($item['label'] ?? ''),
            'origin' => (string) ($item['origin'] ?? ''),
            'storageState' => (string) ($provenance['storage_state'] ?? ''),
            'provenance' => $provenance,
            'rights' => $this->decode_json_field((string) ($item['rights_json'] ?? '')),
            'notes' => (string) ($item['notes'] ?? ''),
            'decayAt' => (string) ($item['decay_at'] ?? ''),
            'retain' => !empty($item['retain']),
            'retainReason' => (string) ($item['retain_reason'] ?? ''),
            'promotedTargetType' => (string) ($item['promoted_target_type'] ?? ''),
            'promotedTargetId' => (int) ($item['promoted_target_id'] ?? 0),
            'archiveCandidateStatus' => (string) ($item['archive_candidate_status'] ?? ''),
            'uses' => $this->get_item_uses((int) $item['id']),
            'preview' => $this->preview_item($item),
            'updatedAt' => (string) ($item['updated_at'] ?? ''),
        ];
    }

    public function prepare_link(array $link): array
    {
        return [
            'id' => (int) ($link['id'] ?? 0),
            'setId' => (int) ($link['set_id'] ?? 0),
            'contextType' => (string) ($link['context_type'] ?? ''),
            'contextId' => (int) ($link['context_id'] ?? 0),
            'title' => (string) get_the_title((int) ($link['context_id'] ?? 0)),
            'linkRole' => (string) ($link['link_role'] ?? ''),
            'position' => (int) ($link['position'] ?? 0),
        ];
    }

    /** Existing audit rows retain every confirmed destination, including reuse. */
    public function get_item_uses(int $item_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_col($wpdb->prepare("SELECT payload_json FROM {$this->get_audit_table_name()} WHERE item_id=%d AND action='item_promoted' ORDER BY id DESC", $item_id));
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Editorial Set uses could not read promotion history.');
        }
        $uses = [];
        foreach ($rows as $row) {
            $payload = json_decode($row, true);
            $id = absint($payload['target_id'] ?? 0);
            $post = $id ? get_post($id) : null;
            if ($post && current_user_can('edit_post', $id)) {
                $uses[$id] = ['id' => $id, 'type' => $post->post_type, 'title' => get_the_title($id), 'editUrl' => get_edit_post_link($id, 'raw')];
            }
        }
        return array_values($uses);
    }

    /** Report the insert result; the calling operation owns its failure policy. */
    public function record_audit(int $set_id, int $item_id, string $action, string $message, array $payload = []): bool
    {
        global $wpdb;

        return $wpdb->insert(
            $this->get_audit_table_name(),
            [
                'set_id' => $set_id,
                'item_id' => $item_id,
                'action' => sanitize_key($action),
                'actor_id' => get_current_user_id(),
                'message' => sanitize_text_field($message),
                'payload_json' => $this->encode_json_field($payload),
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%d', '%s', '%s', '%s']
        ) === 1;
    }

    /** The Set row serializes its writers with deletion; zero remains unassigned intake. */
    private function lock_set_for_write(int $set_id): ?array
    {
        global $wpdb;
        $set = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->get_sets_table_name()} WHERE id = %d FOR UPDATE", $set_id), ARRAY_A);
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Editorial Set write could not lock its Set.');
        }
        return is_array($set) ? $set : null;
    }

    /** Lock parents in ID order before children, matching Set deletion's ownership order. */
    private function lock_items_for_write(array $item_ids, array $set_ids = []): array
    {
        global $wpdb;
        $item_ids = array_values(array_unique(array_filter(array_map('absint', $item_ids))));
        if (!$item_ids) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($item_ids), '%d'));
        $sql = $wpdb->prepare("SELECT * FROM {$this->get_items_table_name()} WHERE id IN ($placeholders) ORDER BY id ASC", $item_ids); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One %d per normalized ID above.
        $initial = $wpdb->get_results($sql, ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($initial)) {
            throw new RuntimeException('Editorial Set write could not read its items.');
        }
        $set_ids = array_unique(array_merge($set_ids, array_map('intval', array_column($initial, 'set_id'))));
        sort($set_ids, SORT_NUMERIC);
        foreach ($set_ids as $set_id) {
            if ($set_id > 0 && !$this->lock_set_for_write($set_id)) {
                return [];
            }
        }
        $locked = $wpdb->get_results($sql . ' FOR UPDATE', ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($locked) || array_column($locked, 'set_id', 'id') !== array_column($initial, 'set_id', 'id')) {
            throw new RuntimeException('Editorial Set items changed ownership or could not be locked; retry the operation.');
        }
        return array_column($locked, null, 'id');
    }

    /** A zero-row update is valid only when the locked item already stores the requested values. */
    private function update_item_row(int $item_id, array $data, array $formats): bool
    {
        global $wpdb;
        if ($wpdb->update($this->get_items_table_name(), $data, ['id' => $item_id], $formats, ['%d']) === false) {
            return false;
        }
        $stored = $this->get_item($item_id);
        if ($wpdb->last_error !== '' || !$stored || array_diff_assoc($data, $stored)) {
            throw new RuntimeException('Editorial Set write could not verify its saved item.');
        }
        return true;
    }

    private function next_item_position(int $set_id): int
    {
        global $wpdb;
        $position = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->get_items_table_name()} WHERE set_id = %d",
            $set_id
        ));
        if ($wpdb->last_error !== '' || $position === null) {
            throw new RuntimeException('Editorial Set write could not read the next item position.');
        }
        return (int) $position;
    }

    private function next_link_position(string $context_type, int $context_id): int
    {
        global $wpdb;
        $position = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->get_links_table_name()} WHERE context_type = %s AND context_id = %d",
            $context_type,
            $context_id
        ));
        if ($wpdb->last_error !== '' || $position === null) {
            throw new RuntimeException('Editorial Set write could not read the next context position.');
        }
        return (int) $position;
    }

    private function touch_set(int $set_id): void
    {
        if ($set_id <= 0) {
            return;
        }

        global $wpdb;
        $data = ['updated_by' => get_current_user_id(), 'updated_at' => current_time('mysql', true)];
        if ($wpdb->update($this->get_sets_table_name(), $data, ['id' => $set_id], ['%d', '%s'], ['%d']) === false) {
            throw new RuntimeException('Editorial Set write could not update its Set timestamp.');
        }
        $stored = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->get_sets_table_name()} WHERE id = %d", $set_id), ARRAY_A);
        if ($wpdb->last_error !== '' || !$stored || array_diff_assoc($data, $stored)) {
            throw new RuntimeException('Editorial Set write could not verify its Set timestamp.');
        }
    }

    private function sanitize_mysql_datetime_or_null(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function encode_json_field($value): string
    {
        $value = is_array($value) ? $value : [];
        $encoded = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{}';
    }

    private function decode_json_field(string $value): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}

function iss_content_editorial_sets_service(): ISS_Content_Editorial_Sets_Service
{
    static $service = null;

    if (!$service instanceof ISS_Content_Editorial_Sets_Service) {
        $service = new ISS_Content_Editorial_Sets_Service();
    }

    return $service;
}
