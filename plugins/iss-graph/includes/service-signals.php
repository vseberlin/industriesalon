<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Existing graph owner SQL, mechanically extracted.

trait ISS_Graph_Signals
{
    public function normalize_editorial_signal_surface($value): string
    {
        $surface = sanitize_key((string) $value);
        $allowed = [
            'related' => true,
            'search' => true,
            'availability' => true,
        ];

        return isset($allowed[$surface]) ? $surface : 'related';
    }

    public function normalize_editorial_signal_type($value): string
    {
        $signal = sanitize_key((string) $value);
        if ($signal === 'hide') {
            return 'suppress';
        }

        $allowed = [
            'boost' => true,
            'pin' => true,
            'suppress' => true,
            'feature' => true,
        ];

        return isset($allowed[$signal]) ? $signal : '';
    }

    public function editorial_signal_requires_metadata(string $surface, string $signal): bool
    {
        $surface = $this->normalize_editorial_signal_surface($surface);
        $signal = $this->normalize_editorial_signal_type($signal);

        if ($surface === 'related' && $signal === 'feature') {
            return false;
        }

        return $surface !== '' && $signal !== '';
    }

    public function normalize_editorial_signal_status($value): string
    {
        $status = sanitize_key((string) $value);
        $allowed = [
            'active' => true,
            'inactive' => true,
        ];

        return isset($allowed[$status]) ? $status : 'active';
    }

    public function normalize_editorial_signal_expiry($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                return null;
            }

            return function_exists('get_gmt_from_date')
                ? get_gmt_from_date($value . ' 23:59:59')
                : $value . ' 23:59:59';
        }

        $timestamp = strtotime(str_replace('T', ' ', $value));
        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function map_editorial_signal_row(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'context_entity_id' => !empty($row['context_entity_id']) ? (int) $row['context_entity_id'] : null,
            'context_post_id' => (int) ($row['context_post_id'] ?? 0),
            'target_entity_id' => !empty($row['target_entity_id']) ? (int) $row['target_entity_id'] : null,
            'target_post_id' => (int) ($row['target_post_id'] ?? 0),
            'surface' => $this->normalize_editorial_signal_surface($row['surface'] ?? 'related'),
            'signal' => $this->normalize_editorial_signal_type($row['signal_type'] ?? ''),
            'reason' => (string) ($row['reason'] ?? ''),
            'expires_at' => isset($row['expires_at']) && $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
            'author_user_id' => !empty($row['author_user_id']) ? (int) $row['author_user_id'] : null,
            'status' => (string) ($row['status'] ?? 'active'),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function get_editorial_signal_by_post_target(int $context_post_id, int $target_post_id, string $surface = 'related'): ?array
    {
        $context_post_id = absint($context_post_id);
        $target_post_id = absint($target_post_id);
        $surface = $this->normalize_editorial_signal_surface($surface);

        if ($context_post_id <= 0 || $target_post_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_editorial_signal_table_name()}
                WHERE context_post_id = %d
                  AND target_post_id = %d
                  AND surface = %s
                LIMIT 1",
                $context_post_id,
                $target_post_id,
                $surface
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->map_editorial_signal_row($row) : null;
    }

    public function get_active_editorial_signals(int $context_entity_id, string $surface = 'related', array $args = []): array
    {
        $context_entity_id = absint($context_entity_id);
        $context_post_id = absint($args['context_post_id'] ?? 0);
        $surface = $this->normalize_editorial_signal_surface($surface);

        if ($context_entity_id <= 0 && $context_post_id <= 0) {
            return [];
        }

        global $wpdb;

        $where = [
            'surface = %s',
            'status = %s',
            '(expires_at IS NULL OR expires_at >= %s)',
        ];
        $values = [
            $surface,
            'active',
            current_time('mysql', true),
        ];

        if ($context_entity_id > 0 && $context_post_id > 0) {
            $where[] = '(context_entity_id = %d OR context_post_id = %d)';
            $values[] = $context_entity_id;
            $values[] = $context_post_id;
        } elseif ($context_entity_id > 0) {
            $where[] = 'context_entity_id = %d';
            $values[] = $context_entity_id;
        } else {
            $where[] = 'context_post_id = %d';
            $values[] = $context_post_id;
        }

        $limit = isset($args['limit']) ? max(1, min(500, (int) $args['limit'])) : 100;
        $values[] = $limit;

        $sql = "SELECT *
            FROM {$this->get_editorial_signal_table_name()}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY CASE signal_type
                WHEN 'pin' THEN 0
                WHEN 'feature' THEN 1
                WHEN 'boost' THEN 2
                WHEN 'suppress' THEN 3
                WHEN 'hide' THEN 3
                ELSE 4
            END ASC, created_at ASC, id ASC
            LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $mapped = array_map([$this, 'map_editorial_signal_row'], $rows);
        $seen = [];

        return array_values(array_filter($mapped, static function (array $row) use (&$seen): bool {
            $target_post_id = (int) ($row['target_post_id'] ?? 0);
            if ($target_post_id <= 0 || isset($seen[$target_post_id])) {
                return false;
            }

            $seen[$target_post_id] = true;

            return true;
        }));
    }

    public function get_active_editorial_signals_for_post(int $context_post_id, string $surface = 'related', array $args = []): array
    {
        $context_post_id = absint($context_post_id);
        if ($context_post_id <= 0) {
            return [];
        }

        $entity = $this->find_entity_by_post_id($context_post_id);
        $context_entity_id = $entity ? (int) ($entity['id'] ?? 0) : 0;
        $args['context_post_id'] = $context_post_id;

        return $this->get_active_editorial_signals($context_entity_id, $surface, $args);
    }

    public function get_active_related_promotion_post_ids(array $post_types, array $args = []): array
    {
        $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
        if (!$post_types) {
            return [];
        }

        global $wpdb;

        $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
        $where = [
            's.context_post_id = s.target_post_id',
            's.surface = %s',
            's.signal_type = %s',
            's.status = %s',
            '(s.expires_at IS NULL OR s.expires_at >= %s)',
            "p.post_type IN ({$type_placeholders})",
        ];
        $values = array_merge([
            'related',
            'feature',
            'active',
            current_time('mysql', true),
        ], $post_types);

        $post_status = $args['post_status'] ?? 'publish';
        if (is_array($post_status)) {
            $post_statuses = array_values(array_unique(array_filter(array_map('sanitize_key', $post_status))));
            if ($post_statuses) {
                $status_placeholders = implode(', ', array_fill(0, count($post_statuses), '%s'));
                $where[] = "p.post_status IN ({$status_placeholders})";
                $values = array_merge($values, $post_statuses);
            }
        } elseif (is_string($post_status) && $post_status !== '') {
            $where[] = 'p.post_status = %s';
            $values[] = sanitize_key($post_status);
        }

        $exclude_post_id = absint($args['exclude_post_id'] ?? 0);
        if ($exclude_post_id > 0) {
            $where[] = 'p.ID <> %d';
            $values[] = $exclude_post_id;
        }

        $limit = isset($args['limit']) ? max(1, min(5000, (int) $args['limit'])) : 100;
        $values[] = $limit;

        $sql = "SELECT s.target_post_id
            FROM {$this->get_editorial_signal_table_name()} s
            INNER JOIN {$wpdb->posts} p
                ON p.ID = s.target_post_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.updated_at DESC, p.post_title ASC, p.ID DESC
            LIMIT %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Editorial signal lookups use the plugin-owned signal table and are request-scoped by callers.
        $post_ids = $wpdb->get_col($wpdb->prepare($sql, $values));

        return array_values(array_unique(array_filter(array_map('intval', is_array($post_ids) ? $post_ids : []))));
    }

    public function upsert_editorial_signal_for_post(int $context_post_id, int $target_post_id, array $data): ?array
    {
        $context_post_id = absint($context_post_id);
        $target_post_id = absint($target_post_id);
        if ($context_post_id <= 0 || $target_post_id <= 0) {
            return null;
        }

        $context_post = get_post($context_post_id);
        $target_post = get_post($target_post_id);
        if (
            !$context_post instanceof WP_Post
            || !$target_post instanceof WP_Post
            || in_array($context_post->post_status, ['auto-draft', 'trash'], true)
            || in_array($target_post->post_status, ['auto-draft', 'trash'], true)
        ) {
            return null;
        }

        $signal = $this->normalize_editorial_signal_type($data['signal'] ?? '');
        if ($signal === '') {
            return null;
        }

        $surface = $this->normalize_editorial_signal_surface($data['surface'] ?? 'related');
        $context_entity_id = absint($data['context_entity_id'] ?? 0);
        if ($context_entity_id <= 0) {
            $context_entity = $this->find_entity_by_post_id($context_post_id);
            $context_entity_id = $context_entity ? (int) ($context_entity['id'] ?? 0) : 0;
        }

        $target_entity_id = absint($data['target_entity_id'] ?? 0);
        if ($target_entity_id <= 0) {
            $target_entity = $this->find_entity_by_post_id($target_post_id);
            $target_entity_id = $target_entity ? (int) ($target_entity['id'] ?? 0) : 0;
        }

        $author_user_id = absint($data['author_user_id'] ?? get_current_user_id());
        $reason = sanitize_textarea_field((string) ($data['reason'] ?? ''));
        $expires_at = $this->normalize_editorial_signal_expiry($data['expires_at'] ?? null);
        if (
            !empty($data['require_metadata'])
            && $this->editorial_signal_requires_metadata($surface, $signal)
            && ($reason === '' || $expires_at === null || $author_user_id <= 0)
        ) {
            return null;
        }

        $timestamp = current_time('mysql', true);
        $existing = $this->get_editorial_signal_by_post_target($context_post_id, $target_post_id, $surface);
        $db_data = [
            'context_entity_id' => $context_entity_id > 0 ? $context_entity_id : null,
            'context_post_id' => $context_post_id,
            'target_entity_id' => $target_entity_id > 0 ? $target_entity_id : null,
            'target_post_id' => $target_post_id,
            'surface' => $surface,
            'signal_type' => $signal,
            'reason' => $reason,
            'expires_at' => $expires_at,
            'author_user_id' => $author_user_id > 0 ? $author_user_id : null,
            'status' => $this->normalize_editorial_signal_status($data['status'] ?? 'active'),
            'updated_at' => $timestamp,
        ];
        $formats = ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s'];

        global $wpdb;

        if ($existing) {
            $db_data['created_at'] = (string) ($existing['created_at'] ?? $timestamp);
            $formats[] = '%s';
            $wpdb->update(
                $this->get_editorial_signal_table_name(),
                $db_data,
                ['id' => (int) $existing['id']],
                $formats,
                ['%d']
            );

            return $this->get_editorial_signal_by_post_target($context_post_id, $target_post_id, $surface);
        }

        $db_data['created_at'] = $timestamp;
        $formats[] = '%s';
        $wpdb->insert($this->get_editorial_signal_table_name(), $db_data, $formats);

        $insert_id = (int) $wpdb->insert_id;
        if ($insert_id <= 0) {
            return null;
        }

        return $this->get_editorial_signal_by_post_target($context_post_id, $target_post_id, $surface);
    }

    public function remove_editorial_signal_for_post(int $context_post_id, int $target_post_id, string $surface = 'related'): bool
    {
        $context_post_id = absint($context_post_id);
        $target_post_id = absint($target_post_id);
        $surface = $this->normalize_editorial_signal_surface($surface);

        if ($context_post_id <= 0 || $target_post_id <= 0) {
            return false;
        }

        global $wpdb;

        $updated = $wpdb->update(
            $this->get_editorial_signal_table_name(),
            [
                'status' => 'inactive',
                'updated_at' => current_time('mysql', true),
            ],
            [
                'context_post_id' => $context_post_id,
                'target_post_id' => $target_post_id,
                'surface' => $surface,
            ],
            ['%s', '%s'],
            ['%d', '%d', '%s']
        );

        return $updated !== false;
    }
}
