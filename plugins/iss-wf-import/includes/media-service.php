<?php

if (!defined('ABSPATH')) {
    exit;
}

class ISS_WF_Import_Media_Service
{
    /** @var array<int, array<string, mixed>|null> */
    private array $projection_cache = [];

    public function get_media_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_media';
    }

    public function maybe_install_schema(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_MEDIA_SCHEMA_OPTION, '');
        if ($stored === ISS_WF_IMPORT_MEDIA_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $this->get_media_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_id bigint(20) unsigned NOT NULL,
            position int(11) NOT NULL DEFAULT 0,
            role_key varchar(40) NOT NULL DEFAULT 'supplemental',
            storage_kind varchar(40) NOT NULL DEFAULT 'remote',
            source_media_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_url text NOT NULL,
            source_url_hash char(64) NOT NULL DEFAULT '',
            preview_url text NOT NULL,
            preview_url_hash char(64) NOT NULL DEFAULT '',
            attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            preview_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            original_filename text NOT NULL,
            label text NOT NULL,
            owner_label text NOT NULL,
            creator_label text NOT NULL,
            rights_label text NOT NULL,
            media_type varchar(40) NOT NULL DEFAULT 'image',
            is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY object_position (object_id, position, id),
            KEY role_key (role_key),
            KEY storage_kind (storage_kind),
            KEY media_type (media_type),
            KEY source_media_id (source_media_id),
            KEY source_url_hash (source_url_hash),
            KEY attachment_id (attachment_id),
            KEY preview_attachment_id (preview_attachment_id)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(ISS_WF_IMPORT_MEDIA_SCHEMA_OPTION, ISS_WF_IMPORT_MEDIA_SCHEMA_VERSION, false);
    }

    public function maybe_backfill(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_MEDIA_BACKFILL_OPTION, '');
        if ($stored === ISS_WF_IMPORT_MEDIA_BACKFILL_VERSION) {
            return;
        }

        $this->backfill_all_media();
        update_option(ISS_WF_IMPORT_MEDIA_BACKFILL_OPTION, ISS_WF_IMPORT_MEDIA_BACKFILL_VERSION, false);
    }

    public function backfill_all_media(): void
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
            $this->sync_object_media((int) $post_id);
        }
    }

    public function sync_object_media(int $post_id): void
    {
        $snapshot = $this->build_legacy_snapshot($post_id);
        if (!$snapshot) {
            return;
        }

        $object_row = $this->get_or_create_object_row($post_id);
        if (!$object_row) {
            return;
        }

        $timestamp = current_time('mysql', true);
        $this->replace_media_rows((int) $object_row['id'], $snapshot, $timestamp);
        unset($this->projection_cache[$post_id]);
    }

    public function delete_object_media(int $post_id): void
    {
        global $wpdb;

        $object_row = iss_wf_import_get_object_service()->get_projection_for_post($post_id);
        if (!is_array($object_row)) {
            unset($this->projection_cache[$post_id]);
            return;
        }

        $wpdb->delete($this->get_media_table_name(), ['object_id' => (int) $object_row['id']], ['%d']);
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

        $object_row = iss_wf_import_get_object_service()->get_projection_for_post($post_id);
        if (!is_array($object_row)) {
            $this->projection_cache[$post_id] = null;
            return null;
        }

        $media_rows = $this->get_media_rows((int) $object_row['id']);
        if (!$media_rows && $this->legacy_media_has_payload($post_id)) {
            $this->projection_cache[$post_id] = null;
            return null;
        }

        $projection = [
            'object' => $object_row,
            'media' => array_map([$this, 'map_media_row_to_legacy_image'], $media_rows),
        ];

        $this->projection_cache[$post_id] = $projection;

        return $projection;
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

        $images = get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, true);
        $images = iss_wf_import_sanitize_archive_image_sources(is_array($images) ? $images : []);

        return [
            'post_id' => $post_id,
            'images' => $images,
        ];
    }

    protected function get_or_create_object_row(int $post_id): ?array
    {
        $object_service = iss_wf_import_get_object_service();
        $object_row = $object_service->get_projection_for_post($post_id);
        if (is_array($object_row)) {
            return $object_row;
        }

        $object_service->sync_object_post($post_id);

        $object_row = $object_service->get_projection_for_post($post_id);

        return is_array($object_row) ? $object_row : null;
    }

    protected function replace_media_rows(int $object_id, array $snapshot, string $timestamp): void
    {
        global $wpdb;

        $table = $this->get_media_table_name();
        $wpdb->delete($table, ['object_id' => $object_id], ['%d']);

        foreach ((array) $snapshot['images'] as $position => $image) {
            if (!is_array($image)) {
                continue;
            }

            $role_key = !empty($image['is_main']) ? 'primary' : 'supplemental';
            $storage_kind = $this->derive_storage_kind($image);

            $wpdb->insert($table, [
                'object_id' => $object_id,
                'position' => max(0, (int) $position),
                'role_key' => $role_key,
                'storage_kind' => $storage_kind,
                'source_media_id' => absint($image['source_id'] ?? 0),
                'source_url' => esc_url_raw((string) ($image['source_url'] ?? '')),
                'source_url_hash' => $this->build_url_hash((string) ($image['source_url'] ?? '')),
                'preview_url' => esc_url_raw((string) ($image['preview_url'] ?? '')),
                'preview_url_hash' => $this->build_url_hash((string) ($image['preview_url'] ?? '')),
                'attachment_id' => absint($image['attachment_id'] ?? 0),
                'preview_attachment_id' => absint($image['preview_attachment_id'] ?? 0),
                'original_filename' => sanitize_text_field((string) ($image['filename'] ?? '')),
                'label' => sanitize_text_field((string) ($image['label'] ?? '')),
                'owner_label' => sanitize_text_field((string) ($image['owner'] ?? '')),
                'creator_label' => sanitize_text_field((string) ($image['creator'] ?? '')),
                'rights_label' => sanitize_text_field((string) ($image['rights'] ?? '')),
                'media_type' => sanitize_key((string) ($image['type'] ?? '')) ?: 'image',
                'is_primary' => !empty($image['is_main']) ? 1 : 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], [
                '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d',
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s',
            ]);
        }
    }

    protected function get_media_rows(int $object_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$this->get_media_table_name()}
            WHERE object_id = %d
            ORDER BY position ASC, id ASC",
            $object_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    protected function map_media_row_to_legacy_image(array $row): array
    {
        return [
            'source_id' => absint($row['source_media_id'] ?? 0),
            'source_url' => (string) ($row['source_url'] ?? ''),
            'preview_url' => (string) ($row['preview_url'] ?? ''),
            'attachment_id' => absint($row['attachment_id'] ?? 0),
            'preview_attachment_id' => absint($row['preview_attachment_id'] ?? 0),
            'filename' => (string) ($row['original_filename'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'owner' => (string) ($row['owner_label'] ?? ''),
            'creator' => (string) ($row['creator_label'] ?? ''),
            'rights' => (string) ($row['rights_label'] ?? ''),
            'type' => (string) ($row['media_type'] ?? ''),
            'is_main' => !empty($row['is_primary']),
        ];
    }

    protected function derive_storage_kind(array $image): string
    {
        $has_remote = !empty($image['source_url']) || !empty($image['preview_url']);
        $has_attachment = !empty($image['attachment_id']) || !empty($image['preview_attachment_id']);

        if ($has_remote && $has_attachment) {
            return 'hybrid';
        }

        if ($has_attachment) {
            return 'attachment';
        }

        return 'remote';
    }

    protected function build_url_hash(string $url): string
    {
        $url = esc_url_raw(trim($url));
        if ($url === '') {
            return '';
        }

        return hash('sha256', $url);
    }

    protected function legacy_media_has_payload(int $post_id): bool
    {
        $images = get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, true);
        $images = iss_wf_import_sanitize_archive_image_sources(is_array($images) ? $images : []);

        return !empty($images);
    }
}

function iss_wf_import_get_media_service(): ISS_WF_Import_Media_Service
{
    static $service = null;

    if (!$service instanceof ISS_WF_Import_Media_Service) {
        $service = new ISS_WF_Import_Media_Service();
    }

    return $service;
}

function iss_wf_import_maybe_install_media_schema(): void
{
    iss_wf_import_get_media_service()->maybe_install_schema();
}
add_action('init', 'iss_wf_import_maybe_install_media_schema', 29);

function iss_wf_import_maybe_backfill_media_tables(): void
{
    iss_wf_import_get_media_service()->maybe_backfill();
}
add_action('init', 'iss_wf_import_maybe_backfill_media_tables', 30);

function iss_wf_import_sync_object_media_on_save(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_wf_import_get_media_service()->sync_object_media($post_id);
}
add_action('save_post', 'iss_wf_import_sync_object_media_on_save', 32, 2);

function iss_wf_import_delete_object_media_on_before_delete(int $post_id): void
{
    if (get_post_type($post_id) !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    iss_wf_import_get_media_service()->delete_object_media($post_id);
}
add_action('before_delete_post', 'iss_wf_import_delete_object_media_on_before_delete', 5);

if (defined('WP_CLI') && WP_CLI) {
    class ISS_WF_Import_Media_CLI_Command
    {
        public function verify(): void
        {
            $service = iss_wf_import_get_media_service();
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
            $verified_posts = 0;
            $verified_items = 0;

            foreach ($post_ids as $post_id) {
                $post = get_post((int) $post_id);
                if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
                    continue;
                }

                $legacy_images = get_post_meta((int) $post_id, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, true);
                $legacy_images = iss_wf_import_sanitize_archive_image_sources(is_array($legacy_images) ? $legacy_images : []);

                $projection = $service->get_projection_for_post((int) $post_id);
                if ($legacy_images && !is_array($projection)) {
                    $errors[] = sprintf('Object %d has legacy media but no canonical projection.', (int) $post_id);
                    continue;
                }

                $canonical_images = is_array($projection)
                    ? iss_wf_import_sanitize_archive_image_sources((array) ($projection['media'] ?? []))
                    : [];

                if ($legacy_images !== $canonical_images) {
                    $errors[] = sprintf('Object %d media rows do not match legacy data.', (int) $post_id);
                    continue;
                }

                if ($canonical_images) {
                    $verified_posts++;
                    $verified_items += count($canonical_images);
                }
            }

            if ($errors) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('Media verification failed.');
            }

            \WP_CLI::success(sprintf(
                'Verified %d archive objects with %d media rows.',
                $verified_posts,
                $verified_items
            ));
        }
    }

    \WP_CLI::add_command('iss-archive media-verify', ['ISS_WF_Import_Media_CLI_Command', 'verify']);
}
