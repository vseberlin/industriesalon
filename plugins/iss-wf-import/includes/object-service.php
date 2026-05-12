<?php

if (!defined('ABSPATH')) {
    exit;
}

class ISS_WF_Import_Object_Service
{
    /** @var array<int, array<string, mixed>|null> */
    private array $projection_cache = [];

    public function get_object_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_objects';
    }

    public function maybe_install_schema(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_OBJECT_SCHEMA_OPTION, '');
        if ($stored === ISS_WF_IMPORT_OBJECT_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $this->get_object_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_post_id bigint(20) unsigned NOT NULL,
            object_key varchar(191) NOT NULL,
            title text NOT NULL,
            summary text NOT NULL,
            description longtext NOT NULL,
            source_system varchar(100) NOT NULL DEFAULT '',
            source_id varchar(191) NOT NULL DEFAULT '',
            source_url text NOT NULL,
            source_url_hash char(64) NOT NULL DEFAULT '',
            inventory_number varchar(191) NOT NULL DEFAULT '',
            object_type_label text NOT NULL,
            object_type_key varchar(191) NOT NULL DEFAULT '',
            creator_label text NOT NULL,
            material text NOT NULL,
            dimensions text NOT NULL,
            rights_status varchar(191) NOT NULL DEFAULT '',
            rights_holder text NOT NULL,
            json_url text NOT NULL,
            md_object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            md_manifest_url text NOT NULL,
            md_image_url text NOT NULL,
            md_image_rights varchar(191) NOT NULL DEFAULT '',
            md_image_owner text NOT NULL,
            md_metadata_rights_status varchar(191) NOT NULL DEFAULT '',
            md_metadata_rights_holder text NOT NULL,
            md_institution_id bigint(20) unsigned NOT NULL DEFAULT 0,
            institution_name text NOT NULL,
            year_label varchar(191) NOT NULL DEFAULT '',
            decade_label varchar(100) NOT NULL DEFAULT '',
            decade_key varchar(100) NOT NULL DEFAULT '',
            sort_year_start int(11) NOT NULL DEFAULT 0,
            sort_year_end int(11) NOT NULL DEFAULT 0,
            content_hash varchar(191) NOT NULL DEFAULT '',
            last_imported_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY object_post_id (object_post_id),
            UNIQUE KEY object_key (object_key),
            KEY source_lookup (source_system, source_id),
            KEY source_url_hash (source_url_hash),
            KEY inventory_lookup (inventory_number),
            KEY object_type_key (object_type_key),
            KEY decade_key (decade_key),
            KEY md_object_id (md_object_id),
            KEY year_sort (sort_year_start, sort_year_end)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(ISS_WF_IMPORT_OBJECT_SCHEMA_OPTION, ISS_WF_IMPORT_OBJECT_SCHEMA_VERSION, false);
    }

    public function maybe_backfill(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_OBJECT_BACKFILL_OPTION, '');
        if ($stored === ISS_WF_IMPORT_OBJECT_BACKFILL_VERSION) {
            return;
        }

        $this->backfill_all_objects();
        update_option(ISS_WF_IMPORT_OBJECT_BACKFILL_OPTION, ISS_WF_IMPORT_OBJECT_BACKFILL_VERSION, false);
    }

    public function backfill_all_objects(): void
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
            $this->sync_object_post((int) $post_id);
        }
    }

    public function sync_object_post(int $post_id): void
    {
        $snapshot = $this->build_legacy_snapshot($post_id);
        if (!$snapshot) {
            return;
        }

        $timestamp = current_time('mysql', true);
        $this->upsert_object_row($snapshot, $timestamp);
        unset($this->projection_cache[$post_id]);
    }

    public function delete_object_post(int $post_id): void
    {
        global $wpdb;

        $wpdb->delete($this->get_object_table_name(), ['object_post_id' => $post_id], ['%d']);
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

        $row = $this->get_object_row_by_post_id($post_id);
        $this->projection_cache[$post_id] = is_array($row) ? $row : null;

        return $this->projection_cache[$post_id];
    }

    public function find_post_id_by_md_object_id(int $md_object_id): int
    {
        if ($md_object_id <= 0) {
            return 0;
        }

        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT object_post_id
            FROM {$this->get_object_table_name()}
            WHERE md_object_id = %d
            ORDER BY id ASC
            LIMIT 1",
            $md_object_id
        ));

        return absint($post_id);
    }

    public function find_post_ids_by_inventory_number(string $inventory_number, int $limit = 10): array
    {
        $inventory_number = trim($inventory_number);
        if ($inventory_number === '') {
            return [];
        }

        global $wpdb;

        $limit = max(1, min(100, $limit));
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT object_post_id
            FROM {$this->get_object_table_name()}
            WHERE inventory_number = %s
            ORDER BY id ASC
            LIMIT %d",
            $inventory_number,
            $limit
        ));

        return array_values(array_filter(array_map('absint', is_array($rows) ? $rows : [])));
    }

    public function find_post_id_by_source_url(string $source_url): int
    {
        $source_url = esc_url_raw(trim($source_url));
        if ($source_url === '') {
            return 0;
        }

        global $wpdb;

        $hash = $this->build_source_url_hash($source_url);
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT object_post_id
            FROM {$this->get_object_table_name()}
            WHERE source_url_hash = %s
              AND source_url = %s
            ORDER BY id ASC
            LIMIT 1",
            $hash,
            $source_url
        ));

        return absint($post_id);
    }

    protected function build_legacy_snapshot(int $post_id): ?array
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
            return null;
        }

        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return null;
        }

        $source_system = sanitize_key((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_KIND_META, true));
        $source_id = sanitize_text_field((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META, true));
        $source_url = esc_url_raw((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_URL_META, true));
        $year_label = trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_YEAR_META, true));
        $decade_label = trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_DECADE_META, true));
        [$sort_year_start, $sort_year_end] = $this->parse_year_bounds($year_label, $decade_label);

        return [
            'post_id' => $post_id,
            'object_key' => $this->build_object_key($post, $source_system, $source_id),
            'title' => trim((string) $post->post_title),
            'summary' => trim((string) $post->post_excerpt),
            'description' => trim((string) $post->post_content),
            'source_system' => $source_system,
            'source_id' => $source_id,
            'source_url' => $source_url,
            'inventory_number' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_INVENTORY_META, true)),
            'object_type_label' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_TYPE_META, true)),
            'creator_label' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_CREATOR_META, true)),
            'material' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MATERIAL_META, true)),
            'dimensions' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_DIMENSIONS_META, true)),
            'rights_status' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_RIGHTS_STATUS_META, true)),
            'rights_holder' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_RIGHTS_HOLDER_META, true)),
            'json_url' => esc_url_raw((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_JSON_URL_META, true)),
            'md_object_id' => absint(get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_ID_META, true)),
            'md_manifest_url' => esc_url_raw((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_MANIFEST_URL_META, true)),
            'md_image_url' => esc_url_raw((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_URL_META, true)),
            'md_image_rights' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_RIGHTS_META, true)),
            'md_image_owner' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_OWNER_META, true)),
            'md_metadata_rights_status' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_METADATA_RIGHTS_META, true)),
            'md_metadata_rights_holder' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_METADATA_HOLDER_META, true)),
            'md_institution_id' => absint(get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_ID_META, true)),
            'institution_name' => trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_NAME_META, true)),
            'year_label' => $year_label,
            'decade_label' => $decade_label,
            'sort_year_start' => $sort_year_start,
            'sort_year_end' => $sort_year_end,
            'content_hash' => sanitize_text_field((string) get_post_meta($post_id, ISS_WF_IMPORT_HASH_META, true)),
            'last_imported_at' => $this->normalize_datetime_string((string) get_post_meta($post_id, ISS_WF_IMPORT_LAST_SYNCED_META, true)),
        ];
    }

    protected function build_object_key(WP_Post $post, string $source_system, string $source_id): string
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

    protected function upsert_object_row(array $snapshot, string $timestamp): void
    {
        global $wpdb;

        $table = $this->get_object_table_name();
        $existing = $this->get_object_row_by_post_id((int) $snapshot['post_id']);
        $object_type_label = (string) $snapshot['object_type_label'];
        $decade_label = (string) $snapshot['decade_label'];
        $data = [
            'object_post_id' => (int) $snapshot['post_id'],
            'object_key' => $this->truncate_value((string) $snapshot['object_key'], 191),
            'title' => (string) $snapshot['title'],
            'summary' => (string) $snapshot['summary'],
            'description' => (string) $snapshot['description'],
            'source_system' => $this->truncate_value((string) $snapshot['source_system'], 100),
            'source_id' => $this->truncate_value((string) $snapshot['source_id'], 191),
            'source_url' => (string) $snapshot['source_url'],
            'source_url_hash' => $this->truncate_value($this->build_source_url_hash((string) $snapshot['source_url']), 64),
            'inventory_number' => $this->truncate_value((string) $snapshot['inventory_number'], 191),
            'object_type_label' => $object_type_label,
            'object_type_key' => $this->truncate_value(sanitize_title($object_type_label), 191),
            'creator_label' => (string) $snapshot['creator_label'],
            'material' => (string) $snapshot['material'],
            'dimensions' => (string) $snapshot['dimensions'],
            'rights_status' => $this->truncate_value((string) $snapshot['rights_status'], 191),
            'rights_holder' => (string) $snapshot['rights_holder'],
            'json_url' => (string) $snapshot['json_url'],
            'md_object_id' => absint($snapshot['md_object_id'] ?? 0),
            'md_manifest_url' => (string) $snapshot['md_manifest_url'],
            'md_image_url' => (string) $snapshot['md_image_url'],
            'md_image_rights' => $this->truncate_value((string) $snapshot['md_image_rights'], 191),
            'md_image_owner' => (string) $snapshot['md_image_owner'],
            'md_metadata_rights_status' => $this->truncate_value((string) $snapshot['md_metadata_rights_status'], 191),
            'md_metadata_rights_holder' => (string) $snapshot['md_metadata_rights_holder'],
            'md_institution_id' => absint($snapshot['md_institution_id'] ?? 0),
            'institution_name' => (string) $snapshot['institution_name'],
            'year_label' => $this->truncate_value((string) $snapshot['year_label'], 191),
            'decade_label' => $this->truncate_value($decade_label, 100),
            'decade_key' => $this->truncate_value(sanitize_title($decade_label), 100),
            'sort_year_start' => (int) ($snapshot['sort_year_start'] ?? 0),
            'sort_year_end' => (int) ($snapshot['sort_year_end'] ?? 0),
            'content_hash' => $this->truncate_value((string) $snapshot['content_hash'], 191),
            'last_imported_at' => (string) $snapshot['last_imported_at'],
            'updated_at' => $timestamp,
        ];

        $formats = [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s',
            '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s',
            '%d', '%d', '%s', '%s', '%s',
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['id' => (int) $existing['id']], $formats, ['%d']);

            if (($existing['created_at'] ?? '') === '0000-00-00 00:00:00' || ($existing['created_at'] ?? '') === '') {
                $wpdb->update($table, ['created_at' => $timestamp], ['id' => (int) $existing['id']], ['%s'], ['%d']);
            }

            return;
        }

        $data['created_at'] = $timestamp;
        $formats[] = '%s';
        $wpdb->insert($table, $data, $formats);
    }

    protected function get_object_row_by_post_id(int $post_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->get_object_table_name()} WHERE object_post_id = %d LIMIT 1",
            $post_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{0:int,1:int}
     */
    protected function parse_year_bounds(string $year_label, string $decade_label): array
    {
        preg_match_all('/(?<!\d)(\d{4})(?!\d)/', $year_label, $matches);
        $years = array_values(array_map('intval', $matches[1] ?? []));

        if (count($years) >= 2) {
            $start = $years[0];
            $end = $years[1];
            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }

            return [$start, $end];
        }

        if (count($years) === 1) {
            return [$years[0], $years[0]];
        }

        if (preg_match('/(?<!\d)(\d{4})er(?!\d)/', $decade_label, $match)) {
            $start = (int) $match[1];
            return [$start, $start + 9];
        }

        return [0, 0];
    }

    protected function build_source_url_hash(string $source_url): string
    {
        $source_url = trim($source_url);
        if ($source_url === '') {
            return '';
        }

        return hash('sha256', $source_url);
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
}

function iss_wf_import_get_object_service(): ISS_WF_Import_Object_Service
{
    static $service = null;

    if (!$service instanceof ISS_WF_Import_Object_Service) {
        $service = new ISS_WF_Import_Object_Service();
    }

    return $service;
}

function iss_wf_import_maybe_install_object_schema(): void
{
    iss_wf_import_get_object_service()->maybe_install_schema();
}
add_action('init', 'iss_wf_import_maybe_install_object_schema', 27);

function iss_wf_import_maybe_backfill_object_tables(): void
{
    iss_wf_import_get_object_service()->maybe_backfill();
}
add_action('init', 'iss_wf_import_maybe_backfill_object_tables', 28);

function iss_wf_import_sync_object_post_on_save(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_wf_import_get_object_service()->sync_object_post($post_id);
}
add_action('save_post', 'iss_wf_import_sync_object_post_on_save', 31, 2);

function iss_wf_import_delete_object_rows_on_before_delete(int $post_id): void
{
    if (get_post_type($post_id) !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    iss_wf_import_get_object_service()->delete_object_post($post_id);
}
add_action('before_delete_post', 'iss_wf_import_delete_object_rows_on_before_delete', 6);

if (defined('WP_CLI') && WP_CLI) {
    class ISS_WF_Import_Object_CLI_Command
    {
        public function verify(): void
        {
            $service = iss_wf_import_get_object_service();
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
            $verified = 0;

            foreach ($post_ids as $post_id) {
                $post = get_post((int) $post_id);
                if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
                    continue;
                }

                $row = $service->get_projection_for_post((int) $post_id);
                if (!is_array($row)) {
                    $errors[] = sprintf('Object %d has no canonical row.', (int) $post_id);
                    continue;
                }

                $year_label = trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_YEAR_META, true));
                $decade_label = trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_DECADE_META, true));
                $expected = [
                    'object_key' => '',
                    'title' => trim((string) $post->post_title),
                    'summary' => trim((string) $post->post_excerpt),
                    'description' => trim((string) $post->post_content),
                    'source_system' => sanitize_key((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_SOURCE_KIND_META, true)),
                    'source_id' => sanitize_text_field((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META, true)),
                    'source_url' => esc_url_raw((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_SOURCE_URL_META, true)),
                    'inventory_number' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_INVENTORY_META, true)),
                    'object_type_label' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_TYPE_META, true)),
                    'creator_label' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_CREATOR_META, true)),
                    'material' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MATERIAL_META, true)),
                    'dimensions' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_DIMENSIONS_META, true)),
                    'rights_status' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_RIGHTS_STATUS_META, true)),
                    'rights_holder' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_RIGHTS_HOLDER_META, true)),
                    'json_url' => esc_url_raw((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_JSON_URL_META, true)),
                    'md_object_id' => absint(get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MD_ID_META, true)),
                    'md_manifest_url' => esc_url_raw((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MD_MANIFEST_URL_META, true)),
                    'md_image_url' => esc_url_raw((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_URL_META, true)),
                    'md_image_rights' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_RIGHTS_META, true)),
                    'md_image_owner' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MD_IMAGE_OWNER_META, true)),
                    'md_metadata_rights_status' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MD_METADATA_RIGHTS_META, true)),
                    'md_metadata_rights_holder' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MD_METADATA_HOLDER_META, true)),
                    'md_institution_id' => absint(get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_ID_META, true)),
                    'institution_name' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_NAME_META, true)),
                    'year_label' => $year_label,
                    'decade_label' => $decade_label,
                    'content_hash' => sanitize_text_field((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_HASH_META, true)),
                    'last_imported_at' => trim((string) get_post_meta((int) $post_id, ISS_WF_IMPORT_LAST_SYNCED_META, true)),
                ];

                $expected['object_key'] = $expected['source_system'] !== '' && $expected['source_id'] !== ''
                    ? $expected['source_system'] . ':' . $expected['source_id']
                    : (trim((string) $post->post_name) !== '' ? 'wp-post:' . trim((string) $post->post_name) : 'wp-id:' . (int) $post_id);
                $expected['object_type_key'] = sanitize_title($expected['object_type_label']);
                $expected['decade_key'] = sanitize_title($expected['decade_label']);
                $expected['source_url_hash'] = $expected['source_url'] !== '' ? hash('sha256', $expected['source_url']) : '';
                $expected['last_imported_at'] = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expected['last_imported_at'])
                    ? $expected['last_imported_at']
                    : '0000-00-00 00:00:00';

                preg_match_all('/(?<!\d)(\d{4})(?!\d)/', $year_label, $matches);
                $years = array_values(array_map('intval', $matches[1] ?? []));
                if (count($years) >= 2) {
                    $expected['sort_year_start'] = min($years[0], $years[1]);
                    $expected['sort_year_end'] = max($years[0], $years[1]);
                } elseif (count($years) === 1) {
                    $expected['sort_year_start'] = $years[0];
                    $expected['sort_year_end'] = $years[0];
                } elseif (preg_match('/(?<!\d)(\d{4})er(?!\d)/', $decade_label, $match)) {
                    $expected['sort_year_start'] = (int) $match[1];
                    $expected['sort_year_end'] = (int) $match[1] + 9;
                } else {
                    $expected['sort_year_start'] = 0;
                    $expected['sort_year_end'] = 0;
                }

                foreach ($expected as $field => $value) {
                    $actual = $row[$field] ?? null;
                    if ((string) $actual !== (string) $value) {
                        $errors[] = sprintf('Object %d field %s does not match legacy data.', (int) $post_id, $field);
                        break;
                    }
                }

                $verified++;
            }

            if ($errors) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('Object verification failed.');
            }

            \WP_CLI::success(sprintf('Verified %d archive objects.', $verified));
        }
    }

    \WP_CLI::add_command('iss-archive objects-verify', ['ISS_WF_Import_Object_CLI_Command', 'verify']);
}
