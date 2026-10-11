<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Existing graph owner SQL, mechanically extracted.

trait ISS_Graph_Identifiers_Evidence
{
    private function map_identifier_row(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'entity_id' => (int) ($row['entity_id'] ?? 0),
            'namespace' => (string) ($row['namespace'] ?? ''),
            'value' => (string) ($row['value'] ?? ''),
            'normalized_value' => (string) ($row['normalized_value'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'trust_level' => (string) ($row['trust_level'] ?? 'suggest_only'),
            'confidence' => (int) ($row['confidence'] ?? 0),
            'source_system' => (string) ($row['source_system'] ?? ''),
            'source_ref' => (string) ($row['source_ref'] ?? ''),
            'is_primary' => !empty($row['is_primary']),
            'status' => (string) ($row['status'] ?? 'accepted'),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function get_identifier_by_id(int $identifier_id): ?array
    {
        if ($identifier_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_identifier_table_name()}
                WHERE id = %d
                LIMIT 1",
                $identifier_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->map_identifier_row($row) : null;
    }

    public function get_identifier_by_namespace_value(string $namespace, $value): ?array
    {
        $namespace = $this->normalize_identifier_namespace($namespace);
        $normalized_value = $this->normalize_identifier_lookup_value($value);
        if ($namespace === '' || $normalized_value === '') {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_identifier_table_name()}
                WHERE namespace = %s
                  AND normalized_value = %s
                LIMIT 1",
                $namespace,
                $normalized_value
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->map_identifier_row($row) : null;
    }

    public function get_entity_by_identifier(string $namespace, $value, array $args = []): ?array
    {
        $identifier = $this->get_identifier_by_namespace_value($namespace, $value);
        if (!$identifier || (int) ($identifier['entity_id'] ?? 0) <= 0) {
            return null;
        }

        $status = sanitize_key((string) ($args['status'] ?? 'accepted'));
        if ($status !== '' && (string) ($identifier['status'] ?? '') !== $status) {
            return null;
        }

        $entity = $this->get_entity_by_id((int) $identifier['entity_id']);
        if (!$entity) {
            return null;
        }

        $entity_kind = $this->normalize_entity_kind((string) ($args['entity_kind'] ?? ''));
        if ($entity_kind !== '' && (string) ($entity['entity_kind'] ?? '') !== $entity_kind) {
            return null;
        }

        return $entity;
    }

    public function upsert_entity_identifier(int $entity_id, array $data): ?array
    {
        global $wpdb;

        if ($entity_id <= 0 || !$this->get_entity_by_id($entity_id)) {
            return null;
        }

        $namespace = $this->normalize_identifier_namespace((string) ($data['namespace'] ?? ''));
        $value = $this->normalize_identifier_value($data['value'] ?? '');
        $normalized_value = $this->normalize_identifier_lookup_value($value);
        if ($namespace === '' || $value === '' || $normalized_value === '') {
            return null;
        }

        $existing = $this->get_identifier_by_namespace_value($namespace, $value);
        if ($wpdb->last_error !== '' || ($existing && (int) ($existing['entity_id'] ?? 0) !== $entity_id)) {
            return null;
        }

        $timestamp = current_time('mysql', true);
        $db_data = [
            'entity_id' => $entity_id,
            'namespace' => $namespace,
            'value' => $this->limit_string($value, 255),
            'normalized_value' => $normalized_value,
            'url' => esc_url_raw((string) ($data['url'] ?? '')),
            'label' => sanitize_text_field((string) ($data['label'] ?? '')),
            'trust_level' => $this->normalize_identifier_trust_level($data['trust_level'] ?? ''),
            'confidence' => $this->normalize_confidence($data['confidence'] ?? 0),
            'source_system' => sanitize_key((string) ($data['source_system'] ?? '')),
            'source_ref' => sanitize_text_field((string) ($data['source_ref'] ?? '')),
            'is_primary' => $this->normalize_bool($data['is_primary'] ?? false),
            'status' => $this->normalize_evidence_status($data['status'] ?? 'accepted'),
            'updated_at' => $timestamp,
        ];

        $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s'];

        if ($existing) {
            $db_data['created_at'] = (string) ($existing['created_at'] ?? $timestamp);
            $formats[] = '%s';
            $updated = $wpdb->update(
                $this->get_identifier_table_name(),
                $db_data,
                ['id' => (int) $existing['id']],
                $formats,
                ['%d']
            );
            if ($updated === false) {
                return null;
            }

            return $this->get_identifier_by_id((int) $existing['id']);
        }

        $db_data['created_at'] = $timestamp;
        $formats[] = '%s';
        if ($wpdb->insert($this->get_identifier_table_name(), $db_data, $formats) !== 1) {
            return null;
        }

        $insert_id = (int) $wpdb->insert_id;

        return $insert_id > 0 ? $this->get_identifier_by_id($insert_id) : null;
    }

    /**
     * Replace one entity/source identifier set, including same-entity upserts.
     *
     * @throws RuntimeException When a row is rejected or a database operation fails.
     */
    public function replace_entity_identifiers_for_source(int $entity_id, string $source_system, array $rows, string $source_ref = ''): void
    {
        global $wpdb;

        if ($entity_id <= 0) {
            return;
        }

        $source_system = sanitize_key($source_system);
        if ($source_system === '') {
            return;
        }

        $source_ref = sanitize_text_field($source_ref);
        $table = $this->get_identifier_table_name();
        $this->run_atomic_write([$table], function () use ($wpdb, $table, $entity_id, $source_system, $source_ref, $rows): void {
            $deleted = $wpdb->delete(
                $table,
                [
                    'entity_id' => $entity_id,
                    'source_system' => $source_system,
                ],
                ['%d', '%s']
            );
            if ($deleted === false) {
                throw new RuntimeException('Graph identifier replacement could not delete the previous rows.');
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $row['source_system'] = $source_system;
                $row['source_ref'] = $source_ref;
                if (!$this->upsert_entity_identifier($entity_id, $row)) {
                    throw new RuntimeException('Graph identifier replacement could not store a replacement row.');
                }
            }
        }, 'Graph identifier replacement');
    }

    public function get_identifiers_for_entity(int $entity_id, array $args = []): array
    {
        if ($entity_id <= 0) {
            return [];
        }

        global $wpdb;

        $where = ['entity_id = %d'];
        $values = [$entity_id];

        $namespace = $this->normalize_identifier_namespace((string) ($args['namespace'] ?? ''));
        if ($namespace !== '') {
            $where[] = 'namespace = %s';
            $values[] = $namespace;
        }

        $source_system = sanitize_key((string) ($args['source_system'] ?? ''));
        if ($source_system !== '') {
            $where[] = 'source_system = %s';
            $values[] = $source_system;
        }

        $status = sanitize_key((string) ($args['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $limit = isset($args['limit']) ? max(1, min(500, (int) $args['limit'])) : 500;
        $values[] = $limit;

        $sql = "SELECT *
            FROM {$this->get_identifier_table_name()}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY is_primary DESC, namespace ASC, label ASC, id ASC
            LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map([$this, 'map_identifier_row'], $rows));
    }

    private function map_evidence_row(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'entity_id' => (int) ($row['entity_id'] ?? 0),
            'target_kind' => (string) ($row['target_kind'] ?? ''),
            'target_id' => isset($row['target_id']) && $row['target_id'] !== null ? (int) $row['target_id'] : null,
            'source_system' => (string) ($row['source_system'] ?? ''),
            'source_ref' => (string) ($row['source_ref'] ?? ''),
            'source_url' => (string) ($row['source_url'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
            'confidence' => (int) ($row['confidence'] ?? 0),
            'status' => (string) ($row['status'] ?? 'accepted'),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function get_evidence_ref_by_id(int $evidence_id): ?array
    {
        if ($evidence_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_evidence_table_name()}
                WHERE id = %d
                LIMIT 1",
                $evidence_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->map_evidence_row($row) : null;
    }

    public function upsert_entity_evidence_ref(int $entity_id, array $data): ?array
    {
        if ($entity_id <= 0 || !$this->get_entity_by_id($entity_id)) {
            return null;
        }

        global $wpdb;

        $timestamp = current_time('mysql', true);
        $evidence_id = absint($data['id'] ?? 0);
        $existing = $evidence_id > 0 ? $this->get_evidence_ref_by_id($evidence_id) : null;
        if (($evidence_id > 0 && $wpdb->last_error !== '') || ($existing && (int) ($existing['entity_id'] ?? 0) !== $entity_id)) {
            return null;
        }

        $target_id = absint($data['target_id'] ?? 0);
        $db_data = [
            'entity_id' => $entity_id,
            'target_kind' => sanitize_key((string) ($data['target_kind'] ?? 'entity')),
            'target_id' => $target_id > 0 ? $target_id : null,
            'source_system' => sanitize_key((string) ($data['source_system'] ?? '')),
            'source_ref' => sanitize_text_field((string) ($data['source_ref'] ?? '')),
            'source_url' => esc_url_raw((string) ($data['source_url'] ?? '')),
            'label' => sanitize_text_field((string) ($data['label'] ?? '')),
            'note' => sanitize_textarea_field((string) ($data['note'] ?? '')),
            'confidence' => $this->normalize_confidence($data['confidence'] ?? 0),
            'status' => $this->normalize_evidence_status($data['status'] ?? 'accepted'),
            'updated_at' => $timestamp,
        ];
        $formats = ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'];

        if ($existing) {
            $db_data['created_at'] = (string) ($existing['created_at'] ?? $timestamp);
            $formats[] = '%s';
            $updated = $wpdb->update(
                $this->get_evidence_table_name(),
                $db_data,
                ['id' => $evidence_id],
                $formats,
                ['%d']
            );
            if ($updated === false) {
                return null;
            }

            return $this->get_evidence_ref_by_id($evidence_id);
        }

        $db_data['created_at'] = $timestamp;
        $formats[] = '%s';
        if ($wpdb->insert($this->get_evidence_table_name(), $db_data, $formats) !== 1) {
            return null;
        }

        $insert_id = (int) $wpdb->insert_id;

        return $insert_id > 0 ? $this->get_evidence_ref_by_id($insert_id) : null;
    }

    /**
     * Replace one entity/source evidence set, including same-entity ID updates.
     *
     * @throws RuntimeException When a row is rejected or a database operation fails.
     */
    public function replace_entity_evidence_refs_for_source(int $entity_id, string $source_system, array $rows, string $source_ref = ''): void
    {
        global $wpdb;

        if ($entity_id <= 0) {
            return;
        }

        $source_system = sanitize_key($source_system);
        if ($source_system === '') {
            return;
        }

        $source_ref = sanitize_text_field($source_ref);
        $table = $this->get_evidence_table_name();
        $this->run_atomic_write([$table], function () use ($wpdb, $table, $entity_id, $source_system, $source_ref, $rows): void {
            $deleted = $wpdb->delete(
                $table,
                [
                    'entity_id' => $entity_id,
                    'source_system' => $source_system,
                ],
                ['%d', '%s']
            );
            if ($deleted === false) {
                throw new RuntimeException('Graph evidence replacement could not delete the previous rows.');
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $row['source_system'] = $source_system;
                $row['source_ref'] = $source_ref;
                if (!$this->upsert_entity_evidence_ref($entity_id, $row)) {
                    throw new RuntimeException('Graph evidence replacement could not store a replacement row.');
                }
            }
        }, 'Graph evidence replacement');
    }

    public function get_evidence_refs_for_entity(int $entity_id, array $args = []): array
    {
        if ($entity_id <= 0) {
            return [];
        }

        global $wpdb;

        $where = ['entity_id = %d'];
        $values = [$entity_id];

        $target_kind = sanitize_key((string) ($args['target_kind'] ?? ''));
        if ($target_kind !== '') {
            $where[] = 'target_kind = %s';
            $values[] = $target_kind;
        }

        $source_system = sanitize_key((string) ($args['source_system'] ?? ''));
        if ($source_system !== '') {
            $where[] = 'source_system = %s';
            $values[] = $source_system;
        }

        $status = sanitize_key((string) ($args['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $limit = isset($args['limit']) ? max(1, min(500, (int) $args['limit'])) : 500;
        $values[] = $limit;

        $sql = "SELECT *
            FROM {$this->get_evidence_table_name()}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY target_kind ASC, id ASC
            LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map([$this, 'map_evidence_row'], $rows));
    }
}
