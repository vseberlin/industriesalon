<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Archivsets use plugin-owned custom tables.
// phpcs:disable WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- Dynamic table names and filters are constrained to this service.

class ISS_WF_Import_Archivset_Service
{
    public function get_set_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_sets';
    }

    public function get_member_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_set_members';
    }

    public function get_link_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_set_links';
    }

    public function maybe_install_schema(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_ARCHIVSET_SCHEMA_OPTION, '');
        if ($stored === ISS_WF_IMPORT_ARCHIVSET_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sets_table = $this->get_set_table_name();
        $members_table = $this->get_member_table_name();
        $links_table = $this->get_link_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sets_sql = "CREATE TABLE {$sets_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            set_key varchar(191) NOT NULL,
            title text NOT NULL,
            summary text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            source_system varchar(100) NOT NULL DEFAULT '',
            source_id varchar(191) NOT NULL DEFAULT '',
            source_url text NOT NULL,
            source_refs_json longtext NOT NULL,
            legacy_collection_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY set_key (set_key),
            KEY status (status),
            KEY source_lookup (source_system, source_id),
            KEY legacy_collection_post_id (legacy_collection_post_id),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $members_sql = "CREATE TABLE {$members_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            set_id bigint(20) unsigned NOT NULL,
            member_kind varchar(20) NOT NULL DEFAULT 'object',
            object_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            related_set_id bigint(20) unsigned NOT NULL DEFAULT 0,
            legacy_collection_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            member_source_id varchar(191) NOT NULL DEFAULT '',
            member_source_url text NOT NULL,
            member_title text NOT NULL,
            member_slug varchar(200) NOT NULL DEFAULT '',
            display_title text NOT NULL,
            caption text NOT NULL,
            page_label varchar(100) NOT NULL DEFAULT '',
            position int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY set_id (set_id),
            KEY set_position (set_id, position, id),
            KEY object_post (object_post_id),
            KEY related_set (related_set_id),
            KEY legacy_collection_post_id (legacy_collection_post_id),
            KEY member_source (member_source_id),
            KEY member_kind (member_kind)
        ) {$charset_collate};";

        $links_sql = "CREATE TABLE {$links_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            set_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            post_type varchar(40) NOT NULL DEFAULT '',
            link_role varchar(40) NOT NULL DEFAULT 'archive_material',
            position int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY set_post_role (set_id, post_id, link_role),
            KEY post_lookup (post_id, link_role, position),
            KEY set_id (set_id),
            KEY post_type (post_type)
        ) {$charset_collate};";

        dbDelta($sets_sql);
        dbDelta($members_sql);
        dbDelta($links_sql);

        update_option(ISS_WF_IMPORT_ARCHIVSET_SCHEMA_OPTION, ISS_WF_IMPORT_ARCHIVSET_SCHEMA_VERSION, false);
    }

    public function create_set(array $args): int
    {
        global $wpdb;

        $timestamp = current_time('mysql', true);
        $data = $this->normalize_set_data($args, $timestamp, true);
        if ($data['set_key'] === '') {
            $data['set_key'] = $this->build_unique_manual_set_key();
        }

        $formats = [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%d', '%d', '%s', '%d', '%s',
        ];

        $wpdb->insert($this->get_set_table_name(), $data, $formats);

        return (int) $wpdb->insert_id;
    }

    public function update_set(int $set_id, array $args): bool
    {
        if ($set_id <= 0 || !$this->get_set($set_id)) {
            return false;
        }

        global $wpdb;

        $timestamp = current_time('mysql', true);
        $data = $this->normalize_set_data($args, $timestamp, false);
        unset($data['set_key'], $data['created_by'], $data['created_at']);

        $updated = $wpdb->update(
            $this->get_set_table_name(),
            $data,
            ['id' => $set_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    public function upsert_legacy_collection_set(int $collection_post_id): int
    {
        $post = get_post($collection_post_id);
        if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
            return 0;
        }

        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return 0;
        }

        $source_refs = get_post_meta($collection_post_id, ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META, true);
        $source_refs = iss_wf_import_sanitize_collection_source_ids(is_array($source_refs) ? $source_refs : []);
        $args = [
            'set_key' => $this->build_legacy_set_key($collection_post_id),
            'title' => trim((string) $post->post_title),
            'summary' => trim((string) $post->post_excerpt),
            'status' => 'active',
            'source_system' => sanitize_key((string) get_post_meta($collection_post_id, ISS_WF_IMPORT_SOURCE_KIND_META, true)),
            'source_id' => sanitize_text_field((string) get_post_meta($collection_post_id, ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META, true)),
            'source_url' => esc_url_raw((string) get_post_meta($collection_post_id, ISS_WF_IMPORT_SOURCE_URL_META, true)),
            'source_refs' => $source_refs,
            'legacy_collection_post_id' => $collection_post_id,
        ];

        $existing = $this->get_set_by_legacy_collection_post_id($collection_post_id);
        if (!$existing) {
            $existing = $this->get_set_by_key((string) $args['set_key']);
        }

        if ($existing) {
            $this->update_set((int) $existing['id'], $args);
            return (int) $existing['id'];
        }

        return $this->create_set($args);
    }

    public function delete_set(int $set_id): bool
    {
        if ($set_id <= 0) {
            return false;
        }

        global $wpdb;

        $wpdb->delete($this->get_member_table_name(), ['set_id' => $set_id], ['%d']);
        $wpdb->delete($this->get_link_table_name(), ['set_id' => $set_id], ['%d']);
        $deleted = $wpdb->delete($this->get_set_table_name(), ['id' => $set_id], ['%d']);

        return $deleted !== false;
    }

    public function get_set(int $set_id): ?array
    {
        if ($set_id <= 0) {
            return null;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivsets are plugin-owned custom tables.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*,
                (SELECT COUNT(1) FROM {$this->get_member_table_name()} m WHERE m.set_id = s.id) AS member_count,
                (SELECT COUNT(1) FROM {$this->get_link_table_name()} l WHERE l.set_id = s.id) AS link_count
            FROM {$this->get_set_table_name()} s
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

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivsets are plugin-owned custom tables.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_set_table_name()} WHERE set_key = %s LIMIT 1",
            $set_key
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function get_set_by_legacy_collection_post_id(int $collection_post_id): ?array
    {
        if ($collection_post_id <= 0) {
            return null;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivsets are plugin-owned custom tables.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_set_table_name()} WHERE legacy_collection_post_id = %d LIMIT 1",
            $collection_post_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function search_sets(array $args = []): array
    {
        global $wpdb;

        $search = sanitize_text_field((string) ($args['search'] ?? ''));
        $status = sanitize_key((string) ($args['status'] ?? ''));
        $page = max(1, (int) ($args['page'] ?? 1));
        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;

        $where = ['1 = 1'];
        $params = [];

        if ($status !== '' && isset($this->get_status_labels()[$status])) {
            $where[] = 's.status = %s';
            $params[] = $status;
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(s.title LIKE %s OR s.summary LIKE %s OR s.source_system LIKE %s OR s.source_id LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(1) FROM {$this->get_set_table_name()} s WHERE {$where_sql}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivset workbench reads plugin-owned custom tables.
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);

        $query_params = array_merge($params, [$per_page, $offset]);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivset workbench reads plugin-owned custom tables.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*,
                (SELECT COUNT(1) FROM {$this->get_member_table_name()} m WHERE m.set_id = s.id) AS member_count,
                (SELECT COUNT(1) FROM {$this->get_link_table_name()} l WHERE l.set_id = s.id) AS link_count
            FROM {$this->get_set_table_name()} s
            WHERE {$where_sql}
            ORDER BY s.updated_at DESC, s.id DESC
            LIMIT %d OFFSET %d",
            $query_params
        ), ARRAY_A);

        return [
            'items' => is_array($rows) ? $rows : [],
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    public function get_members(int $set_id): array
    {
        if ($set_id <= 0) {
            return [];
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivset members are plugin-owned custom tables.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$this->get_member_table_name()}
            WHERE set_id = %d
            ORDER BY position ASC, id ASC",
            $set_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function replace_members(int $set_id, array $members): void
    {
        if ($set_id <= 0) {
            return;
        }

        global $wpdb;

        $wpdb->delete($this->get_member_table_name(), ['set_id' => $set_id], ['%d']);
        foreach ($members as $index => $member) {
            if (!is_array($member)) {
                continue;
            }

            if (!isset($member['position'])) {
                $member['position'] = $index;
            }

            $this->insert_member($set_id, $member);
        }

        $this->touch_set($set_id);
    }

    public function add_member(int $set_id, array $args): int
    {
        if ($set_id <= 0 || !$this->get_set($set_id)) {
            return 0;
        }

        $object_post_id = absint($args['object_post_id'] ?? $args['objectPostId'] ?? 0);
        if ($object_post_id > 0) {
            $existing = $this->get_object_member($set_id, $object_post_id);
            if ($existing) {
                return (int) $existing['id'];
            }
        }

        if (!isset($args['position'])) {
            $args['position'] = $this->get_next_member_position($set_id);
        }

        $member_id = $this->insert_member($set_id, $args);
        if ($member_id > 0) {
            $this->touch_set($set_id);
        }

        return $member_id;
    }

    public function update_member(int $set_id, int $member_id, array $args): bool
    {
        $member = $this->get_member($set_id, $member_id);
        if (!$member) {
            return false;
        }

        global $wpdb;

        $data = [
            'display_title' => sanitize_text_field((string) ($args['display_title'] ?? $args['displayTitle'] ?? $member['display_title'] ?? '')),
            'caption' => sanitize_textarea_field((string) ($args['caption'] ?? $member['caption'] ?? '')),
            'page_label' => $this->truncate_value(sanitize_text_field((string) ($args['page_label'] ?? $args['pageLabel'] ?? $member['page_label'] ?? '')), 100),
            'member_source_url' => esc_url_raw((string) ($args['source_url'] ?? $args['sourceUrl'] ?? $member['member_source_url'] ?? '')),
            'position' => max(0, absint($args['position'] ?? $member['position'] ?? 0)),
            'updated_at' => current_time('mysql', true),
        ];

        $updated = $wpdb->update(
            $this->get_member_table_name(),
            $data,
            ['id' => $member_id, 'set_id' => $set_id],
            ['%s', '%s', '%s', '%s', '%d', '%s'],
            ['%d', '%d']
        );

        if ($updated !== false) {
            $this->touch_set($set_id);
        }

        return $updated !== false;
    }

    public function remove_member(int $set_id, int $member_id): bool
    {
        if ($set_id <= 0 || $member_id <= 0) {
            return false;
        }

        global $wpdb;

        $deleted = $wpdb->delete($this->get_member_table_name(), [
            'id' => $member_id,
            'set_id' => $set_id,
        ], ['%d', '%d']);

        if ($deleted !== false) {
            $this->touch_set($set_id);
        }

        return $deleted !== false;
    }

    public function reorder_members(int $set_id, array $members): bool
    {
        if ($set_id <= 0 || !$this->get_set($set_id)) {
            return false;
        }

        foreach ($members as $index => $member) {
            if (!is_array($member)) {
                continue;
            }

            $member_id = absint($member['id'] ?? 0);
            if ($member_id <= 0) {
                continue;
            }

            $member['position'] = max(0, absint($member['position'] ?? $index));
            $this->update_member($set_id, $member_id, $member);
        }

        $this->touch_set($set_id);

        return true;
    }

    public function attach_set(int $set_id, int $post_id, array $args = []): bool
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || !$this->get_set($set_id)) {
            return false;
        }

        global $wpdb;

        $timestamp = current_time('mysql', true);
        $role = $this->normalize_link_role((string) ($args['link_role'] ?? $args['linkRole'] ?? 'archive_material'));
        $existing = $this->get_link($set_id, $post_id, $role);
        $position = isset($args['position'])
            ? max(0, absint($args['position']))
            : $this->get_next_link_position($post_id, $role);

        $data = [
            'set_id' => $set_id,
            'post_id' => $post_id,
            'post_type' => $this->truncate_value((string) $post->post_type, 40),
            'link_role' => $role,
            'position' => $position,
            'updated_at' => $timestamp,
        ];

        if ($existing) {
            $updated = $wpdb->update(
                $this->get_link_table_name(),
                $data,
                ['id' => (int) $existing['id']],
                ['%d', '%d', '%s', '%s', '%d', '%s'],
                ['%d']
            );

            return $updated !== false;
        }

        $data['created_at'] = $timestamp;
        $inserted = $wpdb->insert(
            $this->get_link_table_name(),
            $data,
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s']
        );

        return $inserted !== false;
    }

    public function detach_set(int $set_id, int $post_id, string $role = 'archive_material'): bool
    {
        if ($set_id <= 0 || $post_id <= 0) {
            return false;
        }

        global $wpdb;

        $deleted = $wpdb->delete($this->get_link_table_name(), [
            'set_id' => $set_id,
            'post_id' => $post_id,
            'link_role' => $this->normalize_link_role($role),
        ], ['%d', '%d', '%s']);

        return $deleted !== false;
    }

    public function get_sets_for_post(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivset links are plugin-owned custom tables.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, l.position AS link_position, l.link_role,
                (SELECT COUNT(1) FROM {$this->get_member_table_name()} m WHERE m.set_id = s.id) AS member_count,
                (SELECT COUNT(1) FROM {$this->get_link_table_name()} lx WHERE lx.set_id = s.id) AS link_count
            FROM {$this->get_link_table_name()} l
            INNER JOIN {$this->get_set_table_name()} s ON s.id = l.set_id
            WHERE l.post_id = %d
              AND l.link_role = %s
            ORDER BY l.position ASC, s.title ASC, s.id ASC",
            $post_id,
            'archive_material'
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function set_is_attached_to_post(int $set_id, int $post_id): bool
    {
        if ($set_id <= 0 || $post_id <= 0) {
            return false;
        }

        foreach ($this->get_sets_for_post($post_id) as $set) {
            if (absint($set['id'] ?? 0) === $set_id) {
                return true;
            }
        }

        return false;
    }

    public function import_legacy_collections(bool $dry_run = false): array
    {
        $post_ids = $this->get_legacy_collection_post_ids();
        $legacy_to_set = [];
        $result = [
            'collections' => 0,
            'sets_created_or_updated' => 0,
            'object_members' => 0,
            'child_references' => 0,
            'dry_run' => $dry_run,
        ];

        foreach ($post_ids as $post_id) {
            $result['collections']++;
            if ($dry_run) {
                continue;
            }

            $set_id = $this->upsert_legacy_collection_set((int) $post_id);
            if ($set_id > 0) {
                $legacy_to_set[(int) $post_id] = $set_id;
                $result['sets_created_or_updated']++;
            }
        }

        foreach ($post_ids as $post_id) {
            $members = $this->build_legacy_collection_members((int) $post_id, $legacy_to_set);
            foreach ($members as $member) {
                if (($member['member_kind'] ?? '') === 'legacy_collection') {
                    $result['child_references']++;
                } else {
                    $result['object_members']++;
                }
            }

            if ($dry_run) {
                continue;
            }

            $set_id = $legacy_to_set[(int) $post_id] ?? 0;
            if ($set_id > 0) {
                $this->replace_members($set_id, $members);
            }
        }

        return $result;
    }

    public function verify_legacy_import(): array
    {
        $post_ids = $this->get_legacy_collection_post_ids();
        $errors = [];
        $verified = 0;
        $object_members = 0;
        $child_references = 0;

        foreach ($post_ids as $post_id) {
            $set = $this->get_set_by_legacy_collection_post_id((int) $post_id);
            if (!$set) {
                $errors[] = sprintf('Legacy collection %d has no Archivset.', (int) $post_id);
                continue;
            }

            $projection = iss_wf_import_get_collection_service()->ensure_projection_for_post((int) $post_id);
            if (!is_array($projection)) {
                $errors[] = sprintf('Legacy collection %d has no collection projection.', (int) $post_id);
                continue;
            }

            $expected_source_refs = iss_wf_import_sanitize_collection_source_ids((array) ($projection['source_refs'] ?? []));
            $actual_source_refs = iss_wf_import_sanitize_collection_source_ids($this->decode_json_array((string) ($set['source_refs_json'] ?? '')));
            if ($expected_source_refs !== $actual_source_refs) {
                $errors[] = sprintf('Archivset %d source refs do not match legacy collection %d.', (int) $set['id'], (int) $post_id);
            }

            $expected_items = iss_wf_import_sanitize_collection_items((array) ($projection['items'] ?? []));
            $expected_children = iss_wf_import_sanitize_collection_children((array) ($projection['children'] ?? []));
            $members = $this->get_members((int) $set['id']);
            $actual_items = [];
            $actual_children = [];

            foreach ($members as $member) {
                if (($member['member_kind'] ?? '') === 'legacy_collection') {
                    $actual_children[] = [
                        'collection_id' => absint($member['legacy_collection_post_id'] ?? 0),
                        'position' => max(0, absint($member['position'] ?? 0)),
                        'title' => (string) ($member['member_title'] ?? ''),
                        'slug' => (string) ($member['member_slug'] ?? ''),
                        'source_external_id' => (string) ($member['member_source_id'] ?? ''),
                        'source_url' => (string) ($member['member_source_url'] ?? ''),
                    ];
                    continue;
                }

                $actual_items[] = [
                    'object_id' => absint($member['object_post_id'] ?? 0),
                    'position' => max(0, absint($member['position'] ?? 0)),
                    'caption_override' => (string) ($member['caption'] ?? ''),
                    'page_label' => (string) ($member['page_label'] ?? ''),
                    'title' => (string) ($member['display_title'] ?? ''),
                    'source_object_id' => (string) ($member['member_source_id'] ?? ''),
                    'source_url' => (string) ($member['member_source_url'] ?? ''),
                ];
            }

            $actual_items = iss_wf_import_sanitize_collection_items($actual_items);
            $actual_children = iss_wf_import_sanitize_collection_children($actual_children);
            $object_members += count($actual_items);
            $child_references += count($actual_children);

            if ($expected_items !== $actual_items) {
                $errors[] = sprintf('Archivset %d object members do not match legacy collection %d.', (int) $set['id'], (int) $post_id);
            }

            if ($expected_children !== $actual_children) {
                $errors[] = sprintf('Archivset %d child references do not match legacy collection %d.', (int) $set['id'], (int) $post_id);
            }

            $verified++;
        }

        return [
            'errors' => $errors,
            'collections' => count($post_ids),
            'verified' => $verified,
            'object_members' => $object_members,
            'child_references' => $child_references,
        ];
    }

    public function get_status_labels(): array
    {
        return [
            'active' => __('Aktiv', 'iss-wf-import'),
            'draft' => __('Entwurf', 'iss-wf-import'),
            'archived' => __('Archiviert', 'iss-wf-import'),
        ];
    }

    protected function normalize_set_data(array $args, string $timestamp, bool $creating): array
    {
        $status = sanitize_key((string) ($args['status'] ?? 'active'));
        if (!isset($this->get_status_labels()[$status])) {
            $status = 'active';
        }

        $source_refs = $args['source_refs'] ?? $args['sourceRefs'] ?? [];
        if (is_string($source_refs)) {
            $source_refs = $this->decode_json_array($source_refs);
        }

        $data = [
            'set_key' => $this->normalize_key((string) ($args['set_key'] ?? $args['setKey'] ?? '')),
            'title' => sanitize_text_field((string) ($args['title'] ?? __('Neues Archivset', 'iss-wf-import'))),
            'summary' => sanitize_textarea_field((string) ($args['summary'] ?? '')),
            'status' => $status,
            'source_system' => $this->truncate_value(sanitize_key((string) ($args['source_system'] ?? $args['sourceSystem'] ?? '')), 100),
            'source_id' => $this->truncate_value(sanitize_text_field((string) ($args['source_id'] ?? $args['sourceId'] ?? '')), 191),
            'source_url' => esc_url_raw((string) ($args['source_url'] ?? $args['sourceUrl'] ?? '')),
            'source_refs_json' => $this->encode_json_array(iss_wf_import_sanitize_collection_source_ids(is_array($source_refs) ? $source_refs : [])),
            'legacy_collection_post_id' => absint($args['legacy_collection_post_id'] ?? $args['legacyCollectionPostId'] ?? 0),
            'updated_by' => get_current_user_id(),
            'updated_at' => $timestamp,
        ];

        if ($data['title'] === '') {
            $data['title'] = __('Neues Archivset', 'iss-wf-import');
        }

        if ($creating) {
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = $timestamp;
        }

        return $data;
    }

    protected function insert_member(int $set_id, array $args): int
    {
        global $wpdb;

        $timestamp = current_time('mysql', true);
        $data = $this->normalize_member_data($set_id, $args, $timestamp);
        if (!$data) {
            return 0;
        }

        $wpdb->insert($this->get_member_table_name(), $data, [
            '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%d', '%s', '%s',
        ]);

        return (int) $wpdb->insert_id;
    }

    protected function normalize_member_data(int $set_id, array $args, string $timestamp): ?array
    {
        $member_kind = sanitize_key((string) ($args['member_kind'] ?? $args['memberKind'] ?? 'object'));
        if (!in_array($member_kind, ['object', 'set', 'legacy_collection'], true)) {
            $member_kind = 'object';
        }

        $object_post_id = absint($args['object_post_id'] ?? $args['objectPostId'] ?? 0);
        $related_set_id = absint($args['related_set_id'] ?? $args['relatedSetId'] ?? 0);
        $legacy_collection_post_id = absint($args['legacy_collection_post_id'] ?? $args['legacyCollectionPostId'] ?? 0);
        $member_post = $object_post_id > 0 ? get_post($object_post_id) : null;
        $preserve_source = !empty($args['preserve_source'] ?? $args['preserveSource'] ?? false);

        $source_id = sanitize_text_field((string) ($args['source_object_id'] ?? $args['sourceObjectId'] ?? $args['member_source_id'] ?? $args['memberSourceId'] ?? ''));
        $source_url = esc_url_raw((string) ($args['source_url'] ?? $args['sourceUrl'] ?? $args['member_source_url'] ?? $args['memberSourceUrl'] ?? ''));
        if (!$preserve_source && $member_kind === 'object' && $member_post instanceof WP_Post && $member_post->post_type === ISS_WF_IMPORT_OBJECT_POST_TYPE) {
            if ($source_id === '') {
                $source_id = sanitize_text_field((string) get_post_meta($object_post_id, ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META, true));
            }

            if ($source_url === '') {
                $source_url = esc_url_raw((string) get_post_meta($object_post_id, ISS_WF_IMPORT_SOURCE_URL_META, true));
            }
        }

        if ($member_kind === 'object') {
            $display_title = sanitize_text_field((string) ($args['display_title'] ?? $args['displayTitle'] ?? $args['title'] ?? ''));
            if (
                (!$member_post instanceof WP_Post || $member_post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE)
                && $source_id === ''
                && $source_url === ''
                && $display_title === ''
            ) {
                return null;
            }

            $related_set_id = 0;
            $legacy_collection_post_id = 0;
        }

        $member_title = sanitize_text_field((string) ($args['member_title'] ?? $args['memberTitle'] ?? ''));
        if ($member_title === '' && $member_post instanceof WP_Post) {
            $member_title = trim((string) $member_post->post_title);
        }

        $member_slug = sanitize_title((string) ($args['member_slug'] ?? $args['memberSlug'] ?? ''));
        if ($member_slug === '' && $member_post instanceof WP_Post) {
            $member_slug = sanitize_title((string) $member_post->post_name);
        }

        return [
            'set_id' => $set_id,
            'member_kind' => $member_kind,
            'object_post_id' => $object_post_id,
            'related_set_id' => $related_set_id,
            'legacy_collection_post_id' => $legacy_collection_post_id,
            'member_source_id' => $this->truncate_value($source_id, 191),
            'member_source_url' => $source_url,
            'member_title' => $member_title,
            'member_slug' => $this->truncate_value($member_slug, 200),
            'display_title' => sanitize_text_field((string) ($args['display_title'] ?? $args['displayTitle'] ?? $args['title'] ?? '')),
            'caption' => sanitize_textarea_field((string) ($args['caption'] ?? $args['caption_override'] ?? $args['captionOverride'] ?? '')),
            'page_label' => $this->truncate_value(sanitize_text_field((string) ($args['page_label'] ?? $args['pageLabel'] ?? '')), 100),
            'position' => max(0, absint($args['position'] ?? 0)),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    protected function get_member(int $set_id, int $member_id): ?array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivset members are plugin-owned custom tables.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_member_table_name()} WHERE id = %d AND set_id = %d LIMIT 1",
            $member_id,
            $set_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    protected function get_object_member(int $set_id, int $object_post_id): ?array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivset members are plugin-owned custom tables.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_member_table_name()}
            WHERE set_id = %d
              AND member_kind = %s
              AND object_post_id = %d
            LIMIT 1",
            $set_id,
            'object',
            $object_post_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    protected function get_link(int $set_id, int $post_id, string $role): ?array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivset links are plugin-owned custom tables.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_link_table_name()}
            WHERE set_id = %d
              AND post_id = %d
              AND link_role = %s
            LIMIT 1",
            $set_id,
            $post_id,
            $role
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    protected function get_next_member_position(int $set_id): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivset members are plugin-owned custom tables.
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), -1) + 1 FROM {$this->get_member_table_name()} WHERE set_id = %d",
            $set_id
        ));
    }

    protected function get_next_link_position(int $post_id, string $role): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Archivset links are plugin-owned custom tables.
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), -1) + 1 FROM {$this->get_link_table_name()} WHERE post_id = %d AND link_role = %s",
            $post_id,
            $role
        ));
    }

    protected function touch_set(int $set_id): void
    {
        global $wpdb;

        $wpdb->update(
            $this->get_set_table_name(),
            [
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $set_id],
            ['%d', '%s'],
            ['%d']
        );
    }

    protected function get_legacy_collection_post_ids(): array
    {
        $post_ids = get_posts([
            'post_type' => ISS_WF_IMPORT_COLLECTION_POST_TYPE,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        $clean = [];
        foreach ($post_ids as $post_id) {
            $post = get_post((int) $post_id);
            if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
                continue;
            }

            $clean[] = (int) $post_id;
        }

        return $clean;
    }

    protected function build_legacy_collection_members(int $collection_post_id, array $legacy_to_set): array
    {
        $projection = iss_wf_import_get_collection_service()->ensure_projection_for_post($collection_post_id);
        if (!is_array($projection)) {
            return [];
        }

        $members = [];
        foreach (iss_wf_import_sanitize_collection_items((array) ($projection['items'] ?? [])) as $item) {
            $members[] = [
                'member_kind' => 'object',
                'object_post_id' => absint($item['object_id'] ?? 0),
                'position' => max(0, absint($item['position'] ?? 0)),
                'page_label' => (string) ($item['page_label'] ?? ''),
                'display_title' => (string) ($item['title'] ?? ''),
                'caption' => (string) ($item['caption_override'] ?? ''),
                'source_object_id' => (string) ($item['source_object_id'] ?? ''),
                'source_url' => (string) ($item['source_url'] ?? ''),
                'preserve_source' => true,
            ];
        }

        foreach (iss_wf_import_sanitize_collection_children((array) ($projection['children'] ?? [])) as $child) {
            $legacy_collection_post_id = absint($child['collection_id'] ?? 0);
            $members[] = [
                'member_kind' => 'legacy_collection',
                'related_set_id' => $legacy_to_set[$legacy_collection_post_id] ?? 0,
                'legacy_collection_post_id' => $legacy_collection_post_id,
                'position' => max(0, absint($child['position'] ?? 0)),
                'member_title' => (string) ($child['title'] ?? ''),
                'member_slug' => (string) ($child['slug'] ?? ''),
                'source_object_id' => (string) ($child['source_external_id'] ?? ''),
                'source_url' => (string) ($child['source_url'] ?? ''),
            ];
        }

        usort($members, static function (array $a, array $b): int {
            if ((int) ($a['position'] ?? 0) === (int) ($b['position'] ?? 0)) {
                return strcmp((string) ($a['member_kind'] ?? ''), (string) ($b['member_kind'] ?? ''));
            }

            return (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0);
        });

        return array_values($members);
    }

    protected function build_legacy_set_key(int $collection_post_id): string
    {
        return 'legacy-archivsammlung:' . $collection_post_id;
    }

    protected function build_unique_manual_set_key(): string
    {
        do {
            $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('', true);
            $key = $this->normalize_key('manual:' . $uuid);
        } while ($this->get_set_by_key($key));

        return $key;
    }

    protected function normalize_key(string $value): string
    {
        $value = sanitize_text_field(trim($value));
        $value = (string) preg_replace('/[^A-Za-z0-9._:-]+/', '-', $value);

        return $this->truncate_value(trim($value, '-'), 191);
    }

    protected function normalize_link_role(string $role): string
    {
        $role = sanitize_key($role);

        return $role !== '' ? $this->truncate_value($role, 40) : 'archive_material';
    }

    protected function encode_json_array(array $value): string
    {
        $json = wp_json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '[]';
    }

    protected function decode_json_array(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
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
}

function iss_wf_import_get_archivset_service(): ISS_WF_Import_Archivset_Service
{
    static $service = null;

    if (!$service instanceof ISS_WF_Import_Archivset_Service) {
        $service = new ISS_WF_Import_Archivset_Service();
    }

    return $service;
}

function iss_wf_import_maybe_install_archivset_schema(): void
{
    iss_wf_import_get_archivset_service()->maybe_install_schema();
}
add_action('init', 'iss_wf_import_maybe_install_archivset_schema', 29);

if (defined('WP_CLI') && WP_CLI) {
    class ISS_WF_Import_Archivset_CLI_Command
    {
        /**
         * Create or update Archivsets from legacy archivsammlung posts.
         *
         * ## OPTIONS
         *
         * [--dry-run]
         * : Count the import without writing Archivset rows.
         */
        public function import_legacy(array $args, array $assoc_args): void
        {
            $dry_run = !empty($assoc_args['dry-run']);
            iss_wf_import_get_archivset_service()->install_schema();
            $result = iss_wf_import_get_archivset_service()->import_legacy_collections($dry_run);

            \WP_CLI::success(sprintf(
                '%s %d legacy collections, %d Archivsets, %d object members, %d child references.',
                $dry_run ? 'Checked' : 'Imported',
                (int) $result['collections'],
                (int) $result['sets_created_or_updated'],
                (int) $result['object_members'],
                (int) $result['child_references']
            ));
        }

        public function verify(): void
        {
            iss_wf_import_get_archivset_service()->install_schema();
            $result = iss_wf_import_get_archivset_service()->verify_legacy_import();
            $errors = (array) ($result['errors'] ?? []);

            if ($errors) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('Archivset verification failed.');
            }

            \WP_CLI::success(sprintf(
                'Verified %d Archivsets from %d legacy collections, %d object members, %d child references.',
                (int) $result['verified'],
                (int) $result['collections'],
                (int) $result['object_members'],
                (int) $result['child_references']
            ));
        }
    }

    \WP_CLI::add_command('iss-archive sets-import-legacy', ['ISS_WF_Import_Archivset_CLI_Command', 'import_legacy']);
    \WP_CLI::add_command('iss-archive sets-verify', ['ISS_WF_Import_Archivset_CLI_Command', 'verify']);
}
