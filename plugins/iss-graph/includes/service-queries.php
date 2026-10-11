<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Existing graph owner SQL, mechanically extracted.

trait ISS_Graph_Queries
{
    public function get_entity_by_id(int $entity_id): ?array
    {
        if ($entity_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_entity_table_name()}
                WHERE id = %d
                LIMIT 1",
                $entity_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function get_entities(array $args = []): array
    {
        global $wpdb;

        $where = ['1 = 1'];
        $values = [];

        $entity_kind = $this->normalize_entity_kind((string) ($args['entity_kind'] ?? ''));
        if ($entity_kind !== '') {
            $where[] = 'entity_kind = %s';
            $values[] = $entity_kind;
        }

        $entity_kinds = $args['entity_kinds'] ?? [];
        if (is_array($entity_kinds) && $entity_kinds) {
            $entity_kinds = array_values(array_unique(array_filter(array_map([$this, 'normalize_entity_kind'], $entity_kinds))));
            if ($entity_kinds) {
                $where[] = 'entity_kind IN (' . implode(', ', array_fill(0, count($entity_kinds), '%s')) . ')';
                $values = array_merge($values, $entity_kinds);
            }
        }

        if (!empty($args['public_only'])) {
            $where[] = 'is_public = 1';
        }

        $orderby = sanitize_key((string) ($args['orderby'] ?? 'display_title'));
        if (!in_array($orderby, ['id', 'entity_kind', 'display_title', 'updated_at'], true)) {
            $orderby = 'display_title';
        }

        $order = strtoupper((string) ($args['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $limit = isset($args['limit']) ? max(1, min(50000, (int) $args['limit'])) : 500;
        $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;
        $values[] = $limit;
        $values[] = $offset;

        $sql = "SELECT *
            FROM {$this->get_entity_table_name()}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderby} {$order}, id ASC
            LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        return is_array($rows) ? array_values($rows) : [];
    }

    public function find_entity_by_post(string $entity_kind, int $post_id): ?array
    {
        $entity_kind = $this->normalize_entity_kind($entity_kind);
        if ($entity_kind === '' || $post_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_entity_table_name()}
                WHERE entity_kind = %s
                  AND post_id = %d
                LIMIT 1",
                $entity_kind,
                $post_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function find_entity_by_post_id(int $post_id): ?array
    {
        if ($post_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_entity_table_name()}
                WHERE post_id = %d
                   OR profile_post_id = %d
                ORDER BY CASE WHEN post_id = %d THEN 0 ELSE 1 END ASC, is_public DESC, id ASC
                LIMIT 1",
                $post_id,
                $post_id,
                $post_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function find_entity_by_slug(string $entity_kind, string $canonical_slug): ?array
    {
        $entity_kind = $this->normalize_entity_kind($entity_kind);
        $canonical_slug = $this->build_canonical_slug($canonical_slug, $entity_kind);
        if ($entity_kind === '' || $canonical_slug === '') {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_entity_table_name()}
                WHERE entity_kind = %s
                  AND canonical_slug = %s
                LIMIT 1",
                $entity_kind,
                $canonical_slug
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function find_entity_by_source(string $entity_kind, string $source_system, string $source_id): ?array
    {
        $entity_kind = $this->normalize_entity_kind($entity_kind);
        $source_system = sanitize_key($source_system);
        $source_id = sanitize_text_field($source_id);

        if ($entity_kind === '' || $source_system === '' || $source_id === '') {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_entity_table_name()}
                WHERE entity_kind = %s
                  AND source_system = %s
                  AND source_id = %s
                LIMIT 1",
                $entity_kind,
                $source_system,
                $source_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function get_names_for_entity(int $entity_id, array $args = []): array
    {
        global $wpdb;

        if ($entity_id <= 0) {
            return [];
        }

        $where = ['entity_id = %d'];
        $values = [$entity_id];

        $source_system = sanitize_key((string) ($args['source_system'] ?? ''));
        if ($source_system !== '') {
            $where[] = 'source_system = %s';
            $values[] = $source_system;
        }

        $limit = isset($args['limit']) ? max(1, min(500, (int) $args['limit'])) : 500;
        $values[] = $limit;

        $sql = "SELECT *
            FROM {$this->get_name_table_name()}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY is_primary DESC, position ASC, valid_from_year ASC, id ASC
            LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'entity_id' => (int) ($row['entity_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'normalized_name' => (string) ($row['normalized_name'] ?? ''),
                'name_type' => (string) ($row['name_type'] ?? ''),
                'language' => (string) ($row['language'] ?? ''),
                'is_primary' => !empty($row['is_primary']),
                'valid_from_year' => isset($row['valid_from_year']) && $row['valid_from_year'] !== null ? (int) $row['valid_from_year'] : null,
                'valid_to_year' => isset($row['valid_to_year']) && $row['valid_to_year'] !== null ? (int) $row['valid_to_year'] : null,
                'position' => (int) ($row['position'] ?? 0),
                'source_system' => (string) ($row['source_system'] ?? ''),
                'source_ref' => (string) ($row['source_ref'] ?? ''),
            ];
        }, $rows));
    }

    public function get_relations_for_entity(int $entity_id, string $relation_family = '', array $args = []): array
    {
        global $wpdb;

        if ($entity_id <= 0) {
            return [];
        }

        $relation_family = sanitize_key($relation_family);
        $where = ['r.from_entity_id = %d'];
        $values = [$entity_id];

        if ($relation_family !== '') {
            $where[] = 'r.relation_family = %s';
            $values[] = $relation_family;
        }

        $source_system = sanitize_key((string) ($args['source_system'] ?? ''));
        if ($source_system !== '') {
            $where[] = 'r.source_system = %s';
            $values[] = $source_system;
        }

        if (!empty($args['public_only'])) {
            $where[] = 'r.is_public = 1';
            if (empty($args['relation_status']) && empty($args['relation_statuses'])) {
                $where[] = "r.relation_status IN ('derived', 'accepted', 'manual-pinned')";
            }
        }

        $relation_statuses = $args['relation_statuses'] ?? ($args['relation_status'] ?? null);
        if (is_string($relation_statuses)) {
            $relation_statuses = array_filter(array_map('trim', explode(',', $relation_statuses)));
        }
        if (is_array($relation_statuses) && $relation_statuses) {
            $normalized_statuses = [];
            foreach ($relation_statuses as $status) {
                $normalized = $this->normalize_relation_status($status);
                if ($normalized !== '') {
                    $normalized_statuses[] = $normalized;
                }
            }
            $normalized_statuses = array_values(array_unique($normalized_statuses));
            if ($normalized_statuses) {
                $where[] = 'r.relation_status IN (' . implode(', ', array_fill(0, count($normalized_statuses), '%s')) . ')';
                foreach ($normalized_statuses as $status) {
                    $values[] = $status;
                }
            }
        }

        $limit = isset($args['limit']) ? max(1, min(500, (int) $args['limit'])) : 500;
        $values[] = $limit;

        $sql = "SELECT
                r.*,
                e.entity_kind AS target_entity_kind,
                e.post_id AS target_post_id,
                e.profile_post_id AS target_profile_post_id,
                e.canonical_slug AS target_canonical_slug,
                e.display_title AS target_display_title,
                pn.name AS target_primary_name
            FROM {$this->get_relation_table_name()} r
            LEFT JOIN {$this->get_entity_table_name()} e
                ON e.id = r.to_entity_id
            LEFT JOIN {$this->get_name_table_name()} pn
                ON pn.entity_id = e.id
               AND pn.is_primary = 1
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.position ASC, r.weight DESC, r.valid_from_year ASC, r.id ASC
            LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map(static function (array $row): array {
            $target_post_id = absint($row['target_post_id'] ?? 0);
            $target_profile_post_id = absint($row['target_profile_post_id'] ?? 0);
            $name = trim((string) ($row['target_primary_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['target_display_title'] ?? ''));
            }

            $permalink = '';
            if ($target_post_id > 0) {
                $permalink = (string) get_permalink($target_post_id);
            } elseif ($target_profile_post_id > 0) {
                $permalink = (string) get_permalink($target_profile_post_id);
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'entity_id' => (int) ($row['to_entity_id'] ?? 0),
                'name' => $name,
                'entity_kind' => (string) ($row['target_entity_kind'] ?? ''),
                'post_id' => $target_post_id,
                'profile_post_id' => $target_profile_post_id,
                'slug' => (string) ($row['target_canonical_slug'] ?? ''),
                'permalink' => $permalink,
                'relation_family' => (string) ($row['relation_family'] ?? ''),
                'relation_type' => (string) ($row['relation_type'] ?? ''),
                'relation_role' => (string) ($row['relation_role'] ?? ''),
                'edge_key' => (string) ($row['edge_key'] ?? ''),
                'relation_label' => (string) ($row['relation_label'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
                'weight' => (int) ($row['weight'] ?? 0),
                'position' => (int) ($row['position'] ?? 0),
                'is_primary' => !empty($row['is_primary']),
                'valid_from_year' => isset($row['valid_from_year']) && $row['valid_from_year'] !== null ? (int) $row['valid_from_year'] : null,
                'valid_to_year' => isset($row['valid_to_year']) && $row['valid_to_year'] !== null ? (int) $row['valid_to_year'] : null,
                'source_system' => (string) ($row['source_system'] ?? ''),
                'source_ref' => (string) ($row['source_ref'] ?? ''),
                'source_field' => (string) ($row['source_field'] ?? ''),
                'relation_status' => (string) ($row['relation_status'] ?? ''),
                'confidence' => (int) ($row['confidence'] ?? 0),
                'is_public' => !empty($row['is_public']),
            ];
        }, $rows));
    }

    public function get_incoming_relations_for_entity(int $entity_id, string $from_entity_kind = '', array $args = []): array
    {
        global $wpdb;

        if ($entity_id <= 0) {
            return [];
        }

        $from_entity_kind = $this->normalize_entity_kind($from_entity_kind);
        $where = ['r.to_entity_id = %d'];
        $values = [$entity_id];

        if ($from_entity_kind !== '') {
            $where[] = 'e.entity_kind = %s';
            $values[] = $from_entity_kind;
        }

        $relation_family = sanitize_key((string) ($args['relation_family'] ?? ''));
        if ($relation_family !== '') {
            $where[] = 'r.relation_family = %s';
            $values[] = $relation_family;
        }

        $source_system = sanitize_key((string) ($args['source_system'] ?? ''));
        if ($source_system !== '') {
            $where[] = 'r.source_system = %s';
            $values[] = $source_system;
        }

        if (!empty($args['public_only'])) {
            $where[] = 'r.is_public = 1';
            if (empty($args['relation_status']) && empty($args['relation_statuses'])) {
                $where[] = "r.relation_status IN ('derived', 'accepted', 'manual-pinned')";
            }
        }

        $relation_statuses = $args['relation_statuses'] ?? ($args['relation_status'] ?? null);
        if (is_string($relation_statuses)) {
            $relation_statuses = array_filter(array_map('trim', explode(',', $relation_statuses)));
        }
        if (is_array($relation_statuses) && $relation_statuses) {
            $normalized_statuses = [];
            foreach ($relation_statuses as $status) {
                $normalized = $this->normalize_relation_status($status);
                if ($normalized !== '') {
                    $normalized_statuses[] = $normalized;
                }
            }
            $normalized_statuses = array_values(array_unique($normalized_statuses));
            if ($normalized_statuses) {
                $where[] = 'r.relation_status IN (' . implode(', ', array_fill(0, count($normalized_statuses), '%s')) . ')';
                foreach ($normalized_statuses as $status) {
                    $values[] = $status;
                }
            }
        }

        $limit = isset($args['limit']) ? max(1, min(500, (int) $args['limit'])) : 500;
        $values[] = $limit;

        $sql = "SELECT
                r.*,
                e.entity_kind AS source_entity_kind,
                e.post_id AS source_post_id,
                e.profile_post_id AS source_profile_post_id,
                e.canonical_slug AS source_canonical_slug,
                e.display_title AS source_display_title,
                pn.name AS source_primary_name
            FROM {$this->get_relation_table_name()} r
            LEFT JOIN {$this->get_entity_table_name()} e
                ON e.id = r.from_entity_id
            LEFT JOIN {$this->get_name_table_name()} pn
                ON pn.entity_id = e.id
               AND pn.is_primary = 1
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.position ASC, r.weight DESC, r.valid_from_year ASC, r.id ASC
            LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map(static function (array $row): array {
            $source_post_id = absint($row['source_post_id'] ?? 0);
            $source_profile_post_id = absint($row['source_profile_post_id'] ?? 0);
            $name = trim((string) ($row['source_primary_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['source_display_title'] ?? ''));
            }

            $permalink = '';
            if ($source_post_id > 0) {
                $permalink = (string) get_permalink($source_post_id);
            } elseif ($source_profile_post_id > 0) {
                $permalink = (string) get_permalink($source_profile_post_id);
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'entity_id' => (int) ($row['from_entity_id'] ?? 0),
                'name' => $name,
                'entity_kind' => (string) ($row['source_entity_kind'] ?? ''),
                'post_id' => $source_post_id,
                'profile_post_id' => $source_profile_post_id,
                'slug' => (string) ($row['source_canonical_slug'] ?? ''),
                'permalink' => $permalink,
                'relation_family' => (string) ($row['relation_family'] ?? ''),
                'relation_type' => (string) ($row['relation_type'] ?? ''),
                'relation_role' => (string) ($row['relation_role'] ?? ''),
                'edge_key' => (string) ($row['edge_key'] ?? ''),
                'relation_label' => (string) ($row['relation_label'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
                'weight' => (int) ($row['weight'] ?? 0),
                'position' => (int) ($row['position'] ?? 0),
                'is_primary' => !empty($row['is_primary']),
                'valid_from_year' => isset($row['valid_from_year']) && $row['valid_from_year'] !== null ? (int) $row['valid_from_year'] : null,
                'valid_to_year' => isset($row['valid_to_year']) && $row['valid_to_year'] !== null ? (int) $row['valid_to_year'] : null,
                'source_system' => (string) ($row['source_system'] ?? ''),
                'source_ref' => (string) ($row['source_ref'] ?? ''),
                'source_field' => (string) ($row['source_field'] ?? ''),
                'relation_status' => (string) ($row['relation_status'] ?? ''),
                'confidence' => (int) ($row['confidence'] ?? 0),
                'is_public' => !empty($row['is_public']),
            ];
        }, $rows));
    }

    public function find_entities_by_normalized_name(string $name, array $args = []): array
    {
        $normalized_name = sanitize_title($name);
        if ($normalized_name === '') {
            return [];
        }

        global $wpdb;

        $where = ['n.normalized_name = %s'];
        $values = [$normalized_name];

        $entity_kind = $this->normalize_entity_kind((string) ($args['entity_kind'] ?? ''));
        if ($entity_kind !== '') {
            $where[] = 'e.entity_kind = %s';
            $values[] = $entity_kind;
        }

        $limit = isset($args['limit']) ? max(1, min(50, (int) $args['limit'])) : 10;
        $values[] = $limit;

        $sql = "SELECT
                e.*,
                n.id AS matched_name_id,
                n.name AS matched_name,
                n.name_type AS matched_name_type,
                n.is_primary AS matched_name_is_primary,
                n.source_system AS matched_name_source_system
            FROM {$this->get_name_table_name()} n
            INNER JOIN {$this->get_entity_table_name()} e
                ON e.id = n.entity_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY n.is_primary DESC, e.is_public DESC, e.search_weight DESC, e.display_title ASC, e.id ASC
            LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        return is_array($rows) ? array_values($rows) : [];
    }

    public function search_entities(string $query, array $args = []): array
    {
        global $wpdb;

        $query = sanitize_text_field(trim($query));
        $where = ['1 = 1'];
        $values = [];

        $entity_kind = $this->normalize_entity_kind((string) ($args['entity_kind'] ?? ''));
        if ($entity_kind !== '') {
            $where[] = 'e.entity_kind = %s';
            $values[] = $entity_kind;
        }

        $entity_kinds = $args['entity_kinds'] ?? [];
        if (is_array($entity_kinds) && $entity_kinds) {
            $entity_kinds = array_values(array_unique(array_filter(array_map([$this, 'normalize_entity_kind'], $entity_kinds))));
            if ($entity_kinds) {
                $where[] = 'e.entity_kind IN (' . implode(',', array_fill(0, count($entity_kinds), '%s')) . ')';
                $values = array_merge($values, $entity_kinds);
            }
        }

        if (!empty($args['public_only'])) {
            $where[] = 'e.is_public = 1';
        }

        if ($query !== '') {
            $like = '%' . $wpdb->esc_like($query) . '%';
            $normalized = sanitize_title($query);
            $normalized_like = '%' . $wpdb->esc_like($normalized !== '' ? $normalized : $query) . '%';
            $where[] = "(e.display_title LIKE %s
                OR e.canonical_slug LIKE %s
                OR e.source_id LIKE %s
                OR EXISTS (
                    SELECT 1
                    FROM {$this->get_name_table_name()} n
                    WHERE n.entity_id = e.id
                      AND (n.name LIKE %s OR n.normalized_name LIKE %s)
                )
                OR EXISTS (
                    SELECT 1
                    FROM {$this->get_identifier_table_name()} i
                    WHERE i.entity_id = e.id
                      AND i.status = 'accepted'
                      AND (i.value LIKE %s OR i.normalized_value LIKE %s)
                ))";
            $values = array_merge($values, [$like, $normalized_like, $like, $like, $normalized_like, $like, $normalized_like]);
        }

        $limit = isset($args['limit']) ? max(1, min(100, (int) $args['limit'])) : 20;
        $values[] = $limit;

        $sql = "SELECT e.*
            FROM {$this->get_entity_table_name()} e
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.is_public DESC, e.search_weight DESC, e.display_title ASC, e.id ASC
            LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        return is_array($rows) ? array_values($rows) : [];
    }
}
