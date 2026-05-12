<?php

if (!defined('ABSPATH')) {
    exit;
}

class ISS_WF_Import_Assertion_Service
{
    public function get_assertion_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_assertions';
    }

    public function get_evidence_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_archive_evidence';
    }

    public function maybe_install_schema(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_ASSERTION_SCHEMA_OPTION, '');
        if ($stored === ISS_WF_IMPORT_ASSERTION_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $assertion_table = $this->get_assertion_table_name();
        $evidence_table = $this->get_evidence_table_name();

        $sql_assertions = "CREATE TABLE {$assertion_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_post_id bigint(20) unsigned NOT NULL,
            source_record_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_snapshot_id bigint(20) unsigned NOT NULL DEFAULT 0,
            assertion_key varchar(191) NOT NULL,
            field_key varchar(100) NOT NULL DEFAULT '',
            field_owner_class varchar(40) NOT NULL DEFAULT 'source_owned',
            status_key varchar(40) NOT NULL DEFAULT 'conflicted',
            confidence_key varchar(40) NOT NULL DEFAULT 'high',
            source_label varchar(191) NOT NULL DEFAULT '',
            source_url text NOT NULL,
            canonical_value_json longtext NOT NULL,
            source_value_json longtext NOT NULL,
            note text NOT NULL,
            first_detected_at datetime NOT NULL,
            last_detected_at datetime NOT NULL,
            resolved_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY object_assertion_key (object_post_id, assertion_key),
            KEY object_active (object_post_id, is_active),
            KEY field_key (field_key),
            KEY source_snapshot_id (source_snapshot_id)
        ) {$charset_collate};";

        $sql_evidence = "CREATE TABLE {$evidence_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            assertion_id bigint(20) unsigned NOT NULL,
            evidence_kind varchar(40) NOT NULL DEFAULT 'source_snapshot',
            citation_label varchar(191) NOT NULL DEFAULT '',
            citation_url text NOT NULL,
            payload_json longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY assertion_kind (assertion_id, evidence_kind),
            KEY assertion_id (assertion_id)
        ) {$charset_collate};";

        dbDelta($sql_assertions);
        dbDelta($sql_evidence);
        update_option(ISS_WF_IMPORT_ASSERTION_SCHEMA_OPTION, ISS_WF_IMPORT_ASSERTION_SCHEMA_VERSION, false);
    }

    public function maybe_backfill(): void
    {
        $stored = (string) get_option(ISS_WF_IMPORT_ASSERTION_BACKFILL_OPTION, '');
        if ($stored === ISS_WF_IMPORT_ASSERTION_BACKFILL_VERSION) {
            return;
        }

        $this->backfill_all_object_assertions();
        update_option(ISS_WF_IMPORT_ASSERTION_BACKFILL_OPTION, ISS_WF_IMPORT_ASSERTION_BACKFILL_VERSION, false);
    }

    public function backfill_all_object_assertions(): void
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
            $this->sync_object_assertions((int) $post_id);
        }
    }

    public function sync_object_assertions(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
            $this->resolve_all_active_assertions($post_id, current_time('mysql', true));
            return;
        }

        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            $this->resolve_all_active_assertions($post_id, current_time('mysql', true));
            return;
        }

        $timestamp = current_time('mysql', true);
        $expected = $this->build_expected_assertions($post_id, $timestamp);
        $seen_keys = [];

        foreach ($expected as $assertion) {
            $assertion_key = (string) ($assertion['assertion_key'] ?? '');
            if ($assertion_key === '') {
                continue;
            }

            $seen_keys[] = $assertion_key;
            $row = $this->upsert_assertion_row($post_id, $assertion, $timestamp);
            if ($row) {
                $this->replace_assertion_evidence((int) $row['id'], (array) ($assertion['evidence'] ?? []), $timestamp);
            }
        }

        $this->resolve_missing_assertions($post_id, $seen_keys, $timestamp);
    }

    public function delete_object_assertions(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        global $wpdb;

        $assertion_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id
            FROM {$this->get_assertion_table_name()}
            WHERE object_post_id = %d",
            $post_id
        ));

        foreach ((array) $assertion_ids as $assertion_id) {
            $wpdb->delete($this->get_evidence_table_name(), ['assertion_id' => (int) $assertion_id], ['%d']);
        }

        $wpdb->delete($this->get_assertion_table_name(), ['object_post_id' => $post_id], ['%d']);
    }

    public function get_active_assertions_for_post(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$this->get_assertion_table_name()}
            WHERE object_post_id = %d
              AND is_active = 1
            ORDER BY field_key ASC, assertion_key ASC",
            $post_id
        ), ARRAY_A);

        if (!is_array($rows) || !$rows) {
            return [];
        }

        $evidence_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$this->get_evidence_table_name()}
            WHERE assertion_id IN (
                SELECT id
                FROM {$this->get_assertion_table_name()}
                WHERE object_post_id = %d
                  AND is_active = 1
            )
            ORDER BY id ASC",
            $post_id
        ), ARRAY_A);

        $evidence_by_assertion = [];
        foreach ((array) $evidence_rows as $evidence_row) {
            if (!is_array($evidence_row)) {
                continue;
            }

            $assertion_id = absint($evidence_row['assertion_id'] ?? 0);
            if ($assertion_id <= 0) {
                continue;
            }

            $evidence_by_assertion[$assertion_id][] = $this->map_evidence_row($evidence_row);
        }

        $mapped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $assertion_id = absint($row['id'] ?? 0);
            $item = $this->map_assertion_row($row);
            $item['evidence'] = $evidence_by_assertion[$assertion_id] ?? [];
            $mapped[] = $item;
        }

        return $mapped;
    }

    protected function build_expected_assertions(int $post_id, string $timestamp): array
    {
        $object_row = iss_wf_import_get_object_service()->get_projection_for_post($post_id);
        $relation_projection = iss_wf_import_get_relation_service()->get_projection_for_post($post_id);
        $source_record = iss_wf_import_get_import_service()->get_source_record_projection_for_post($post_id);
        $snapshot_row = iss_wf_import_get_import_service()->get_latest_applied_snapshot_for_post($post_id);

        if (!is_array($object_row) || !is_array($source_record) || !is_array($snapshot_row)) {
            return [];
        }

        $snapshot_payload = iss_wf_import_get_import_service()->decode_snapshot_payload($snapshot_row);
        $normalized_record = is_array($snapshot_payload['normalized_record'] ?? null)
            ? (array) $snapshot_payload['normalized_record']
            : [];

        if (!$normalized_record) {
            return [];
        }

        $source_label = trim((string) ($source_record['source_label'] ?? $source_record['source_key'] ?? ''));
        $source_url = trim((string) ($source_record['record_url'] ?? $object_row['source_url'] ?? ''));
        $evidence = $this->build_snapshot_evidence($source_record, $snapshot_row, $normalized_record, $source_url);
        $assertions = [];

        $this->append_scalar_assertion(
            $assertions,
            'source-md-object-id',
            'md_object_id',
            (int) ($object_row['md_object_id'] ?? 0),
            absint($normalized_record['object_id'] ?? 0),
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            $evidence,
            $timestamp
        );

        $this->append_scalar_assertion(
            $assertions,
            'source-year-label',
            'year_label',
            trim((string) ($object_row['year_label'] ?? '')),
            trim((string) ($normalized_record['year'] ?? '')),
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            $evidence,
            $timestamp
        );

        $this->append_scalar_assertion(
            $assertions,
            'source-inventory-number',
            'inventory_number',
            trim((string) ($object_row['inventory_number'] ?? '')),
            trim((string) ($normalized_record['inventory_number'] ?? '')),
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            $evidence,
            $timestamp
        );

        $this->append_scalar_assertion(
            $assertions,
            'source-object-type',
            'object_type_label',
            trim((string) ($object_row['object_type_label'] ?? '')),
            trim((string) ($normalized_record['object_type'] ?? '')),
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            $evidence,
            $timestamp
        );

        $this->append_scalar_assertion(
            $assertions,
            'source-material',
            'material',
            trim((string) ($object_row['material'] ?? '')),
            trim((string) ($normalized_record['material_technique'] ?? '')),
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            $evidence,
            $timestamp
        );

        $this->append_scalar_assertion(
            $assertions,
            'source-dimensions',
            'dimensions',
            trim((string) ($object_row['dimensions'] ?? '')),
            trim((string) ($normalized_record['dimensions'] ?? '')),
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            $evidence,
            $timestamp
        );

        $this->append_scalar_assertion(
            $assertions,
            'source-rights-holder',
            'rights_holder',
            trim((string) ($object_row['rights_holder'] ?? '')),
            trim((string) ($normalized_record['rights_holder'] ?? '')),
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            $evidence,
            $timestamp
        );

        $this->append_scalar_assertion(
            $assertions,
            'source-rights-status',
            'rights_status',
            trim((string) ($object_row['rights_status'] ?? '')),
            trim((string) ($normalized_record['rights_status'] ?? '')),
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            $evidence,
            $timestamp
        );

        $this->append_scalar_assertion(
            $assertions,
            'source-institution-name',
            'institution_name',
            trim((string) ($object_row['institution_name'] ?? '')),
            trim((string) ($normalized_record['institution_name'] ?? '')),
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            $evidence,
            $timestamp
        );

        $canonical_places = $this->normalize_named_refs_for_compare(is_array($relation_projection['places'] ?? null) ? (array) $relation_projection['places'] : []);
        $source_places = $this->normalize_named_refs_for_compare((array) ($normalized_record['places'] ?? []));

        if ($canonical_places !== $source_places) {
            $assertions[] = $this->build_assertion_payload(
                'source-place-assignments',
                'places',
                $canonical_places,
                $source_places,
                $source_label,
                $source_url,
                $source_record,
                $snapshot_row,
                'Source place assignments differ from the last applied source snapshot.',
                $evidence,
                $timestamp
            );
        }

        return $assertions;
    }

    protected function append_scalar_assertion(
        array &$assertions,
        string $assertion_key,
        string $field_key,
        $canonical_value,
        $source_value,
        string $source_label,
        string $source_url,
        array $source_record,
        array $snapshot_row,
        array $evidence,
        string $timestamp
    ): void {
        if ($this->normalize_scalar_for_compare($canonical_value) === $this->normalize_scalar_for_compare($source_value)) {
            return;
        }

        $assertions[] = $this->build_assertion_payload(
            $assertion_key,
            $field_key,
            $canonical_value,
            $source_value,
            $source_label,
            $source_url,
            $source_record,
            $snapshot_row,
            'Current canonical value differs from the last applied source snapshot.',
            $evidence,
            $timestamp
        );
    }

    protected function build_assertion_payload(
        string $assertion_key,
        string $field_key,
        $canonical_value,
        $source_value,
        string $source_label,
        string $source_url,
        array $source_record,
        array $snapshot_row,
        string $note,
        array $evidence,
        string $timestamp
    ): array {
        return [
            'assertion_key' => $assertion_key,
            'field_key' => $field_key,
            'field_owner_class' => 'source_owned',
            'status_key' => 'conflicted',
            'confidence_key' => 'high',
            'source_label' => $source_label,
            'source_url' => $source_url,
            'source_record_id' => absint($source_record['id'] ?? 0),
            'source_snapshot_id' => absint($snapshot_row['id'] ?? 0),
            'canonical_value_json' => $this->encode_json($canonical_value),
            'source_value_json' => $this->encode_json($source_value),
            'note' => $note,
            'first_detected_at' => $timestamp,
            'last_detected_at' => $timestamp,
            'resolved_at' => '0000-00-00 00:00:00',
            'is_active' => 1,
            'evidence' => $evidence,
        ];
    }

    protected function build_snapshot_evidence(array $source_record, array $snapshot_row, array $normalized_record, string $source_url): array
    {
        $citation_url = trim((string) ($source_record['record_url'] ?? $source_url));
        $citation_label = trim((string) ($source_record['source_label'] ?? $source_record['source_key'] ?? 'Source snapshot'));
        $object_id = absint($normalized_record['object_id'] ?? 0);
        if ($object_id > 0) {
            $citation_label .= sprintf(' object %d', $object_id);
        }

        return [[
            'evidence_kind' => 'source_snapshot',
            'citation_label' => $citation_label,
            'citation_url' => $citation_url,
            'payload_json' => $this->encode_json([
                'source_record_id' => absint($source_record['id'] ?? 0),
                'record_identifier' => (string) ($source_record['record_identifier'] ?? ''),
                'source_key' => (string) ($source_record['source_key'] ?? ''),
                'source_kind' => (string) ($source_record['source_kind'] ?? ''),
                'snapshot_id' => absint($snapshot_row['id'] ?? 0),
                'snapshot_hash' => (string) ($snapshot_row['snapshot_hash'] ?? ''),
                'fetched_at' => (string) ($snapshot_row['fetched_at'] ?? ''),
                'content_modified_at' => (string) ($snapshot_row['content_modified_at'] ?? ''),
                'normalized_record' => $normalized_record,
            ]),
        ]];
    }

    protected function upsert_assertion_row(int $post_id, array $assertion, string $timestamp): ?array
    {
        global $wpdb;

        $assertion_key = $this->truncate_value((string) ($assertion['assertion_key'] ?? ''), 191);
        if ($post_id <= 0 || $assertion_key === '') {
            return null;
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT *
            FROM {$this->get_assertion_table_name()}
            WHERE object_post_id = %d
              AND assertion_key = %s
            LIMIT 1",
            $post_id,
            $assertion_key
        ), ARRAY_A);

        $data = [
            'object_post_id' => $post_id,
            'source_record_id' => absint($assertion['source_record_id'] ?? 0),
            'source_snapshot_id' => absint($assertion['source_snapshot_id'] ?? 0),
            'assertion_key' => $assertion_key,
            'field_key' => $this->truncate_value(sanitize_key((string) ($assertion['field_key'] ?? '')), 100),
            'field_owner_class' => $this->truncate_value(sanitize_key((string) ($assertion['field_owner_class'] ?? 'source_owned')), 40),
            'status_key' => $this->truncate_value(sanitize_key((string) ($assertion['status_key'] ?? 'conflicted')), 40),
            'confidence_key' => $this->truncate_value(sanitize_key((string) ($assertion['confidence_key'] ?? 'high')), 40),
            'source_label' => $this->truncate_value(sanitize_text_field((string) ($assertion['source_label'] ?? '')), 191),
            'source_url' => esc_url_raw((string) ($assertion['source_url'] ?? '')),
            'canonical_value_json' => (string) ($assertion['canonical_value_json'] ?? 'null'),
            'source_value_json' => (string) ($assertion['source_value_json'] ?? 'null'),
            'note' => sanitize_textarea_field((string) ($assertion['note'] ?? '')),
            'last_detected_at' => $timestamp,
            'resolved_at' => '0000-00-00 00:00:00',
            'is_active' => 1,
            'updated_at' => $timestamp,
        ];

        if (is_array($existing)) {
            $data['first_detected_at'] = (string) ($existing['first_detected_at'] ?? $timestamp);
            $wpdb->update(
                $this->get_assertion_table_name(),
                $data,
                ['id' => (int) $existing['id']],
                ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );

            return array_merge($existing, $data, ['id' => (int) $existing['id']]);
        }

        $data['first_detected_at'] = $timestamp;
        $data['created_at'] = $timestamp;

        $wpdb->insert(
            $this->get_assertion_table_name(),
            $data,
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return array_merge($data, ['id' => (int) $wpdb->insert_id]);
    }

    protected function replace_assertion_evidence(int $assertion_id, array $evidence_rows, string $timestamp): void
    {
        if ($assertion_id <= 0) {
            return;
        }

        global $wpdb;

        $wpdb->delete($this->get_evidence_table_name(), ['assertion_id' => $assertion_id], ['%d']);

        foreach ($evidence_rows as $evidence) {
            if (!is_array($evidence)) {
                continue;
            }

            $wpdb->insert($this->get_evidence_table_name(), [
                'assertion_id' => $assertion_id,
                'evidence_kind' => $this->truncate_value(sanitize_key((string) ($evidence['evidence_kind'] ?? 'source_snapshot')), 40),
                'citation_label' => $this->truncate_value(sanitize_text_field((string) ($evidence['citation_label'] ?? '')), 191),
                'citation_url' => esc_url_raw((string) ($evidence['citation_url'] ?? '')),
                'payload_json' => (string) ($evidence['payload_json'] ?? '{}'),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);
        }
    }

    protected function resolve_missing_assertions(int $post_id, array $seen_keys, string $timestamp): void
    {
        if ($post_id <= 0) {
            return;
        }

        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, assertion_key
            FROM {$this->get_assertion_table_name()}
            WHERE object_post_id = %d
              AND is_active = 1",
            $post_id
        ), ARRAY_A);

        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $assertion_key = (string) ($row['assertion_key'] ?? '');
            if ($assertion_key !== '' && in_array($assertion_key, $seen_keys, true)) {
                continue;
            }

            $wpdb->update(
                $this->get_assertion_table_name(),
                [
                    'is_active' => 0,
                    'resolved_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                ['id' => (int) $row['id']],
                ['%d', '%s', '%s'],
                ['%d']
            );
        }
    }

    protected function resolve_all_active_assertions(int $post_id, string $timestamp): void
    {
        if ($post_id <= 0) {
            return;
        }

        global $wpdb;

        $wpdb->update(
            $this->get_assertion_table_name(),
            [
                'is_active' => 0,
                'resolved_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'object_post_id' => $post_id,
                'is_active' => 1,
            ],
            ['%d', '%s', '%s'],
            ['%d', '%d']
        );
    }

    protected function normalize_scalar_for_compare($value): string
    {
        if (is_numeric($value)) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    protected function normalize_named_refs_for_compare(array $items): array
    {
        $clean = iss_wf_import_sanitize_archive_named_refs($items);
        $normalized = [];

        foreach ($clean as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = [
                'source_id' => trim((string) ($item['source_id'] ?? $item['id'] ?? '')),
                'name' => trim((string) ($item['name'] ?? '')),
                'type' => sanitize_key((string) ($item['type'] ?? 'place')),
                'source_url' => esc_url_raw((string) ($item['source_url'] ?? '')),
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $left_key = implode('|', [$left['source_id'], $left['name'], $left['type'], $left['source_url']]);
            $right_key = implode('|', [$right['source_id'], $right['name'], $right['type'], $right['source_url']]);

            return strcmp($left_key, $right_key);
        });

        return array_values($normalized);
    }

    protected function map_assertion_row(array $row): array
    {
        return [
            'id' => absint($row['id'] ?? 0),
            'object_post_id' => absint($row['object_post_id'] ?? 0),
            'source_record_id' => absint($row['source_record_id'] ?? 0),
            'source_snapshot_id' => absint($row['source_snapshot_id'] ?? 0),
            'assertion_key' => (string) ($row['assertion_key'] ?? ''),
            'field_key' => (string) ($row['field_key'] ?? ''),
            'field_owner_class' => (string) ($row['field_owner_class'] ?? ''),
            'status_key' => (string) ($row['status_key'] ?? ''),
            'confidence_key' => (string) ($row['confidence_key'] ?? ''),
            'source_label' => (string) ($row['source_label'] ?? ''),
            'source_url' => (string) ($row['source_url'] ?? ''),
            'canonical_value' => $this->decode_json((string) ($row['canonical_value_json'] ?? 'null')),
            'source_value' => $this->decode_json((string) ($row['source_value_json'] ?? 'null')),
            'note' => (string) ($row['note'] ?? ''),
            'first_detected_at' => (string) ($row['first_detected_at'] ?? ''),
            'last_detected_at' => (string) ($row['last_detected_at'] ?? ''),
            'resolved_at' => (string) ($row['resolved_at'] ?? ''),
            'is_active' => !empty($row['is_active']),
        ];
    }

    protected function map_evidence_row(array $row): array
    {
        return [
            'id' => absint($row['id'] ?? 0),
            'assertion_id' => absint($row['assertion_id'] ?? 0),
            'evidence_kind' => (string) ($row['evidence_kind'] ?? ''),
            'citation_label' => (string) ($row['citation_label'] ?? ''),
            'citation_url' => (string) ($row['citation_url'] ?? ''),
            'payload' => $this->decode_json((string) ($row['payload_json'] ?? '{}')),
        ];
    }

    protected function encode_json($value): string
    {
        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : 'null';
    }

    protected function decode_json(string $json)
    {
        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
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

function iss_wf_import_get_assertion_service(): ISS_WF_Import_Assertion_Service
{
    static $service = null;

    if (!$service instanceof ISS_WF_Import_Assertion_Service) {
        $service = new ISS_WF_Import_Assertion_Service();
    }

    return $service;
}

function iss_wf_import_maybe_install_assertion_schema(): void
{
    iss_wf_import_get_assertion_service()->maybe_install_schema();
}
add_action('init', 'iss_wf_import_maybe_install_assertion_schema', 35);

function iss_wf_import_maybe_backfill_assertion_tables(): void
{
    iss_wf_import_get_assertion_service()->maybe_backfill();
}
add_action('init', 'iss_wf_import_maybe_backfill_assertion_tables', 36);

function iss_wf_import_sync_object_assertions_on_save(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_wf_import_get_assertion_service()->sync_object_assertions($post_id);
}
add_action('save_post', 'iss_wf_import_sync_object_assertions_on_save', 35, 2);

function iss_wf_import_delete_object_assertions_on_before_delete(int $post_id): void
{
    if (get_post_type($post_id) !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return;
    }

    iss_wf_import_get_assertion_service()->delete_object_assertions($post_id);
}
add_action('before_delete_post', 'iss_wf_import_delete_object_assertions_on_before_delete', 3);

if (defined('WP_CLI') && WP_CLI) {
    class ISS_WF_Import_Assertion_CLI_Command
    {
        public function verify(): void
        {
            $service = iss_wf_import_get_assertion_service();
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
            $verified_assertions = 0;

            foreach ($post_ids as $post_id) {
                $post = get_post((int) $post_id);
                if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
                    continue;
                }

                $service->sync_object_assertions((int) $post_id);
                $source_record = iss_wf_import_get_import_service()->get_source_record_projection_for_post((int) $post_id);
                $snapshot_row = iss_wf_import_get_import_service()->get_latest_applied_snapshot_for_post((int) $post_id);
                $assertions = $service->get_active_assertions_for_post((int) $post_id);

                if (!$source_record || !$snapshot_row) {
                    if ($assertions) {
                        $errors[] = sprintf('Object %d has active assertions but no applied source snapshot.', (int) $post_id);
                    }
                    $verified_posts++;
                    continue;
                }

                foreach ($assertions as $assertion) {
                    if (!is_array($assertion)) {
                        continue;
                    }

                    if (($assertion['status_key'] ?? '') !== 'conflicted') {
                        $errors[] = sprintf('Object %d assertion %s has unexpected status.', (int) $post_id, (string) ($assertion['assertion_key'] ?? ''));
                        continue 2;
                    }

                    if (($assertion['confidence_key'] ?? '') === '') {
                        $errors[] = sprintf('Object %d assertion %s has no confidence key.', (int) $post_id, (string) ($assertion['assertion_key'] ?? ''));
                        continue 2;
                    }

                    if (empty($assertion['evidence']) || !is_array($assertion['evidence'])) {
                        $errors[] = sprintf('Object %d assertion %s has no evidence row.', (int) $post_id, (string) ($assertion['assertion_key'] ?? ''));
                        continue 2;
                    }
                }

                $verified_posts++;
                $verified_assertions += count($assertions);
            }

            if ($errors) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('Assertion verification failed.');
            }

            \WP_CLI::success(sprintf(
                'Verified %d archive objects with %d active source-critical assertions.',
                $verified_posts,
                $verified_assertions
            ));
        }
    }

    \WP_CLI::add_command('iss-archive assertions-verify', ['ISS_WF_Import_Assertion_CLI_Command', 'verify']);
}
