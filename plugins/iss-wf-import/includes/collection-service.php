<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This service owns and queries iss-wf-import custom archive collection tables.

class ISS_WF_Import_Collection_Service
{
    /** @var array<int, array<string, mixed>|null> */
    private array $projection_cache = [];

    public function get_collection_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_collections';
    }

    public function get_member_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_collection_members';
    }

    public function maybe_install_schema(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_COLLECTION_SCHEMA_OPTION, '');
        if ($stored === ISS_WF_IMPORT_COLLECTION_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collections_table = $this->get_collection_table_name();
        $members_table = $this->get_member_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $collections_sql = "CREATE TABLE {$collections_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            collection_post_id bigint(20) unsigned NOT NULL,
            collection_key varchar(191) NOT NULL,
            collection_type varchar(40) NOT NULL DEFAULT 'collection',
            title text NOT NULL,
            summary text NOT NULL,
            source_system varchar(100) NOT NULL DEFAULT '',
            source_id varchar(191) NOT NULL DEFAULT '',
            source_url text NOT NULL,
            source_refs_json longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY collection_post_id (collection_post_id),
            UNIQUE KEY collection_key (collection_key),
            KEY collection_type (collection_type),
            KEY source_lookup (source_system, source_id)
        ) {$charset_collate};";

        $members_sql = "CREATE TABLE {$members_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            collection_id bigint(20) unsigned NOT NULL,
            member_kind varchar(20) NOT NULL,
            member_role varchar(40) NOT NULL,
            member_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            member_source_id varchar(191) NOT NULL DEFAULT '',
            member_source_url text NOT NULL,
            member_title text NOT NULL,
            member_slug varchar(200) NOT NULL DEFAULT '',
            title_override text NOT NULL,
            caption_override text NOT NULL,
            page_label varchar(100) NOT NULL DEFAULT '',
            position int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY collection_id (collection_id),
            KEY collection_position (collection_id, position, id),
            KEY member_post (member_kind, member_post_id),
            KEY member_source (member_kind, member_source_id),
            KEY member_role (member_role)
        ) {$charset_collate};";

        dbDelta($collections_sql);
        dbDelta($members_sql);
        $this->ensure_text_column($collections_table, 'title');
        $this->ensure_text_column($members_table, 'member_title');
        $this->ensure_text_column($members_table, 'title_override');

        update_option(ISS_WF_IMPORT_COLLECTION_SCHEMA_OPTION, ISS_WF_IMPORT_COLLECTION_SCHEMA_VERSION, false);
    }

    public function maybe_backfill(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_COLLECTION_BACKFILL_OPTION, '');
        if ($stored === ISS_WF_IMPORT_COLLECTION_BACKFILL_VERSION) {
            return;
        }

        $this->backfill_all_collections();
        update_option(ISS_WF_IMPORT_COLLECTION_BACKFILL_OPTION, ISS_WF_IMPORT_COLLECTION_BACKFILL_VERSION, false);
    }

    public function backfill_all_collections(): void
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

        foreach ($post_ids as $post_id) {
            $this->sync_collection_post((int) $post_id);
        }
    }

    public function sync_collection_post(int $post_id): void
    {
        $snapshot = $this->build_snapshot($post_id);
        if (!$snapshot) {
            return;
        }

        $timestamp = current_time('mysql', true);
        $collection_id = $this->upsert_collection_row($snapshot, $timestamp);
        if ($collection_id <= 0) {
            return;
        }

        $this->replace_member_rows($collection_id, $snapshot, $timestamp);
        unset($this->projection_cache[$post_id]);
    }

    public function delete_collection_post(int $post_id): void
    {
        global $wpdb;

        $row = $this->get_collection_row_by_post_id($post_id);
        if (!$row) {
            return;
        }

        $collection_id = (int) $row['id'];
        $wpdb->delete($this->get_member_table_name(), ['collection_id' => $collection_id], ['%d']);
        $wpdb->delete($this->get_collection_table_name(), ['id' => $collection_id], ['%d']);

        unset($this->projection_cache[$post_id]);
    }

    public function get_projection_for_post(int $post_id): ?array
    {
        if ($post_id <= 0) {
            return null;
        }

        if (array_key_exists($post_id, $this->projection_cache)) {
            return $this->projection_cache[$post_id];
        }

        $row = $this->get_collection_row_by_post_id($post_id);
        if (!$row) {
            $this->projection_cache[$post_id] = null;
            return null;
        }

        $member_rows = $this->get_member_rows((int) $row['id']);
        if (!$member_rows && $this->collection_has_member_payload($post_id)) {
            $this->sync_collection_post($post_id);
            $member_rows = $this->get_member_rows((int) $row['id']);
        }

        $items = [];
        $children = [];
        foreach ($member_rows as $member_row) {
            if (($member_row['member_kind'] ?? '') === 'collection') {
                $children[] = $this->map_member_row_to_child_item($member_row);
                continue;
            }

            $items[] = $this->map_member_row_to_object_item($member_row);
        }

        $projection = [
            'collection' => $row,
            'items' => $items,
            'children' => $children,
            'source_refs' => iss_wf_import_sanitize_collection_source_ids(
                $this->decode_json_array((string) ($row['source_refs_json'] ?? ''))
            ),
        ];

        $this->projection_cache[$post_id] = $projection;

        return $projection;
    }

    public function ensure_projection_for_post(int $post_id): ?array
    {
        $projection = $this->get_projection_for_post($post_id);
        if (is_array($projection)) {
            return $projection;
        }

        $this->sync_collection_post($post_id);

        return $this->get_projection_for_post($post_id);
    }

    public function save_collection_items(int $post_id, array $items): bool
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
            return false;
        }

        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return false;
        }

        $items = iss_wf_import_sanitize_collection_items($items);
        $snapshot = $this->build_snapshot($post_id);
        if (!$snapshot) {
            return false;
        }

        $snapshot['items'] = $items;
        $snapshot['collection_type'] = $this->derive_collection_type(
            $items,
            is_array($snapshot['children'] ?? null) ? (array) $snapshot['children'] : []
        );

        $timestamp = current_time('mysql', true);
        $collection_id = $this->upsert_collection_row($snapshot, $timestamp);
        if ($collection_id <= 0) {
            return false;
        }

        $this->replace_member_rows($collection_id, $snapshot, $timestamp);
        $this->project_snapshot_to_meta($post_id, $snapshot);
        unset($this->projection_cache[$post_id]);

        return true;
    }

    protected function build_snapshot(int $post_id): ?array
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
            return null;
        }

        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return null;
        }

        $items = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_ITEMS_META, true);
        $children = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_CHILDREN_META, true);
        $source_refs = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META, true);

        $items = iss_wf_import_sanitize_collection_items(is_array($items) ? $items : []);
        $children = iss_wf_import_sanitize_collection_children(is_array($children) ? $children : []);
        $source_refs = iss_wf_import_sanitize_collection_source_ids(is_array($source_refs) ? $source_refs : []);

        $source_system = sanitize_key((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_KIND_META, true));
        $source_id = sanitize_text_field((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META, true));
        $source_url = esc_url_raw((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_URL_META, true));

        return [
            'post_id' => $post_id,
            'collection_key' => $this->build_collection_key($post, $source_system, $source_id),
            'collection_type' => $this->derive_collection_type($items, $children),
            'title' => trim((string) $post->post_title),
            'summary' => trim((string) $post->post_excerpt),
            'source_system' => $source_system,
            'source_id' => $source_id,
            'source_url' => $source_url,
            'source_refs' => $source_refs,
            'items' => $items,
            'children' => $children,
        ];
    }

    protected function project_snapshot_to_meta(int $post_id, array $snapshot): void
    {
        update_post_meta(
            $post_id,
            ISS_WF_IMPORT_COLLECTION_ITEMS_META,
            iss_wf_import_sanitize_collection_items(is_array($snapshot['items'] ?? null) ? (array) $snapshot['items'] : [])
        );
        update_post_meta(
            $post_id,
            ISS_WF_IMPORT_COLLECTION_CHILDREN_META,
            iss_wf_import_sanitize_collection_children(is_array($snapshot['children'] ?? null) ? (array) $snapshot['children'] : [])
        );
        update_post_meta(
            $post_id,
            ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META,
            iss_wf_import_sanitize_collection_source_ids(is_array($snapshot['source_refs'] ?? null) ? (array) $snapshot['source_refs'] : [])
        );
    }

    protected function build_collection_key(WP_Post $post, string $source_system, string $source_id): string
    {
        if ($source_system !== '' && $source_id !== '') {
            return $source_system . ':' . $source_id;
        }

        $slug = trim((string) $post->post_name);
        if ($slug !== '') {
            return 'wp-post:' . $slug;
        }

        return 'wp-id:' . (int) $post->ID;
    }

    protected function derive_collection_type(array $items, array $children): string
    {
        $item_count = count($items);
        $child_count = count($children);

        if ($item_count > 0 && $child_count > 0) {
            return 'hybrid';
        }

        if ($item_count > 0) {
            return 'album';
        }

        return 'collection';
    }

    protected function upsert_collection_row(array $snapshot, string $timestamp): int
    {
        global $wpdb;

        $table = $this->get_collection_table_name();
        $existing = $this->get_collection_row_by_post_id((int) $snapshot['post_id']);

        $data = [
            'collection_post_id' => (int) $snapshot['post_id'],
            'collection_key' => $this->truncate_value((string) $snapshot['collection_key'], 191),
            'collection_type' => $this->truncate_value((string) $snapshot['collection_type'], 40),
            'title' => (string) $snapshot['title'],
            'summary' => (string) $snapshot['summary'],
            'source_system' => $this->truncate_value((string) $snapshot['source_system'], 100),
            'source_id' => $this->truncate_value((string) $snapshot['source_id'], 191),
            'source_url' => (string) $snapshot['source_url'],
            'source_refs_json' => $this->encode_json_array((array) $snapshot['source_refs']),
            'updated_at' => $timestamp,
        ];

        $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        if ($existing) {
            $wpdb->update($table, $data, ['id' => (int) $existing['id']], $formats, ['%d']);
            return (int) $existing['id'];
        }

        $data['created_at'] = $timestamp;
        $formats[] = '%s';

        $wpdb->insert($table, $data, $formats);

        return (int) $wpdb->insert_id;
    }

    protected function replace_member_rows(int $collection_id, array $snapshot, string $timestamp): void
    {
        global $wpdb;

        $table = $this->get_member_table_name();
        $wpdb->delete($table, ['collection_id' => $collection_id], ['%d']);

        foreach ((array) $snapshot['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $object_id = absint($item['object_id'] ?? 0);
            $member_post = $object_id > 0 ? get_post($object_id) : null;

            $wpdb->insert($table, [
                'collection_id' => $collection_id,
                'member_kind' => 'object',
                'member_role' => 'album_item',
                'member_post_id' => $object_id,
                'member_source_id' => $this->truncate_value(sanitize_text_field((string) ($item['source_object_id'] ?? '')), 191),
                'member_source_url' => esc_url_raw((string) ($item['source_url'] ?? '')),
                'member_title' => $member_post instanceof WP_Post ? trim((string) $member_post->post_title) : '',
                'member_slug' => $member_post instanceof WP_Post ? trim((string) $member_post->post_name) : '',
                'title_override' => sanitize_text_field((string) ($item['title'] ?? '')),
                'caption_override' => sanitize_textarea_field((string) ($item['caption_override'] ?? '')),
                'page_label' => $this->truncate_value(sanitize_text_field((string) ($item['page_label'] ?? '')), 100),
                'position' => max(0, absint($item['position'] ?? 0)),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);
        }

        foreach ((array) $snapshot['children'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $child_id = absint($item['collection_id'] ?? 0);
            $child_post = $child_id > 0 ? get_post($child_id) : null;
            $member_title = trim((string) ($item['title'] ?? ''));
            if ($member_title === '' && $child_post instanceof WP_Post) {
                $member_title = trim((string) $child_post->post_title);
            }

            $member_slug = sanitize_title((string) ($item['slug'] ?? ''));
            if ($member_slug === '' && $child_post instanceof WP_Post) {
                $member_slug = sanitize_title((string) $child_post->post_name);
            }

            $wpdb->insert($table, [
                'collection_id' => $collection_id,
                'member_kind' => 'collection',
                'member_role' => 'child_collection',
                'member_post_id' => $child_id,
                'member_source_id' => $this->truncate_value(sanitize_text_field((string) ($item['source_external_id'] ?? '')), 191),
                'member_source_url' => esc_url_raw((string) ($item['source_url'] ?? '')),
                'member_title' => $member_title,
                'member_slug' => $this->truncate_value($member_slug, 200),
                'title_override' => '',
                'caption_override' => '',
                'page_label' => '',
                'position' => max(0, absint($item['position'] ?? 0)),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);
        }
    }

    protected function get_collection_row_by_post_id(int $post_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_collection_table_name()} WHERE collection_post_id = %d LIMIT 1",
            $post_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    protected function get_member_rows(int $collection_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$this->get_member_table_name()}
            WHERE collection_id = %d
            ORDER BY position ASC, id ASC",
            $collection_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    protected function map_member_row_to_object_item(array $row): array
    {
        return [
            'object_id' => absint($row['member_post_id'] ?? 0),
            'position' => max(0, absint($row['position'] ?? 0)),
            'caption_override' => (string) ($row['caption_override'] ?? ''),
            'page_label' => (string) ($row['page_label'] ?? ''),
            'title' => (string) ($row['title_override'] ?? ''),
            'source_object_id' => (string) ($row['member_source_id'] ?? ''),
            'source_url' => (string) ($row['member_source_url'] ?? ''),
        ];
    }

    protected function map_member_row_to_child_item(array $row): array
    {
        return [
            'collection_id' => absint($row['member_post_id'] ?? 0),
            'position' => max(0, absint($row['position'] ?? 0)),
            'title' => (string) ($row['member_title'] ?? ''),
            'slug' => (string) ($row['member_slug'] ?? ''),
            'source_external_id' => (string) ($row['member_source_id'] ?? ''),
            'source_url' => (string) ($row['member_source_url'] ?? ''),
        ];
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

    protected function ensure_text_column(string $table_name, string $column_name): void
    {
        global $wpdb;

        $column = $wpdb->get_row(
            $wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $column_name),
            ARRAY_A
        );

        if (!is_array($column)) {
            return;
        }

        $column_type = strtolower((string) ($column['Type'] ?? ''));
        if ($column_type === 'text') {
            return;
        }

        $wpdb->query("ALTER TABLE {$table_name} MODIFY {$column_name} text NOT NULL");
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

    protected function collection_has_member_payload(int $post_id): bool
    {
        $items = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_ITEMS_META, true);
        if (is_array($items) && $items) {
            return true;
        }

        $children = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_CHILDREN_META, true);

        return is_array($children) && $children;
    }
}

function iss_wf_import_get_collection_service(): ISS_WF_Import_Collection_Service
{
    static $service = null;

    if (!$service instanceof ISS_WF_Import_Collection_Service) {
        $service = new ISS_WF_Import_Collection_Service();
    }

    return $service;
}

function iss_wf_import_maybe_install_collection_schema(): void
{
    iss_wf_import_get_collection_service()->maybe_install_schema();
}
add_action('init', 'iss_wf_import_maybe_install_collection_schema', 25);

function iss_wf_import_maybe_backfill_collection_tables(): void
{
    iss_wf_import_get_collection_service()->maybe_backfill();
}

function iss_wf_import_sync_collection_post_on_save(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_wf_import_get_collection_service()->sync_collection_post($post_id);
}
add_action('save_post', 'iss_wf_import_sync_collection_post_on_save', 30, 2);

function iss_wf_import_delete_collection_rows_on_before_delete(int $post_id): void
{
    if (get_post_type($post_id) !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return;
    }

    iss_wf_import_get_collection_service()->delete_collection_post($post_id);
}
add_action('before_delete_post', 'iss_wf_import_delete_collection_rows_on_before_delete', 5);

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Lightweight phase-1 verifier for collection table parity.
     */
    class ISS_WF_Import_Collection_CLI_Command
    {
        public function sync(): void
        {
            iss_wf_import_get_collection_service()->backfill_all_collections();
            update_option(ISS_WF_IMPORT_COLLECTION_BACKFILL_OPTION, ISS_WF_IMPORT_COLLECTION_BACKFILL_VERSION, false);

            \WP_CLI::success('Synced archive collection projection tables.');
        }

        public function verify(): void
        {
            $service = iss_wf_import_get_collection_service();
            $post_ids = get_posts([
                'post_type' => ISS_WF_IMPORT_COLLECTION_POST_TYPE,
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
            ]);

            $errors = [];
            $total_item_rows = 0;
            $total_child_rows = 0;

            foreach ($post_ids as $post_id) {
                $projected_items = get_post_meta((int) $post_id, ISS_WF_IMPORT_COLLECTION_ITEMS_META, true);
                $projected_children = get_post_meta((int) $post_id, ISS_WF_IMPORT_COLLECTION_CHILDREN_META, true);

                $projected_items = iss_wf_import_sanitize_collection_items(is_array($projected_items) ? $projected_items : []);
                $projected_children = iss_wf_import_sanitize_collection_children(is_array($projected_children) ? $projected_children : []);

                $projection = $service->get_projection_for_post((int) $post_id);
                if (!is_array($projection)) {
                    $errors[] = sprintf('Collection %d has no canonical projection row.', (int) $post_id);
                    continue;
                }

                $canonical_items = iss_wf_import_sanitize_collection_items((array) ($projection['items'] ?? []));
                $canonical_children = iss_wf_import_sanitize_collection_children((array) ($projection['children'] ?? []));

                $total_item_rows += count($canonical_items);
                $total_child_rows += count($canonical_children);

                if ($projected_items !== $canonical_items) {
                    $errors[] = sprintf('Collection %d object members do not match the projected collection state.', (int) $post_id);
                }

                if ($projected_children !== $canonical_children) {
                    $errors[] = sprintf('Collection %d child members do not match the projected collection state.', (int) $post_id);
                }
            }

            if ($errors) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('Collection verification failed.');
            }

            \WP_CLI::success(sprintf(
                'Verified %d collections, %d object members, %d child members.',
                count($post_ids),
                $total_item_rows,
                $total_child_rows
            ));
        }
    }

    \WP_CLI::add_command('iss-archive collections-sync', ['ISS_WF_Import_Collection_CLI_Command', 'sync']);
    \WP_CLI::add_command('iss-archive collections-verify', ['ISS_WF_Import_Collection_CLI_Command', 'verify']);
}
