<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ISS_Graph_Service
{
    private static ?ISS_Graph_Service $instance = null;

    public static function get_instance(): ISS_Graph_Service
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get_entity_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_entity_index';
    }

    public function get_name_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_entity_names';
    }

    public function get_relation_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_entity_relations';
    }

    public function get_search_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_search_index';
    }

    public function get_person_facts_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_person_facts';
    }

    public function get_organization_facts_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_organization_facts';
    }

    public function maybe_install_schema(): void
    {
        $installed = (string) get_option(ISS_GRAPH_SCHEMA_OPTION, '');
        if ($installed === ISS_GRAPH_SCHEMA_VERSION) {
            return;
        }

        $this->install_schema();
    }

    public function install_schema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $entity_table = $this->get_entity_table_name();
        $name_table = $this->get_name_table_name();
        $relation_table = $this->get_relation_table_name();
        $search_table = $this->get_search_table_name();
        $person_facts_table = $this->get_person_facts_table_name();
        $organization_facts_table = $this->get_organization_facts_table_name();

        $entity_sql = "CREATE TABLE {$entity_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_kind varchar(100) NOT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            profile_post_id bigint(20) unsigned DEFAULT NULL,
            source_system varchar(100) NOT NULL DEFAULT '',
            source_id varchar(191) NOT NULL DEFAULT '',
            canonical_slug varchar(191) NOT NULL,
            display_title text NOT NULL,
            summary text NOT NULL,
            status varchar(50) NOT NULL DEFAULT '',
            is_public tinyint(1) NOT NULL DEFAULT 0,
            has_profile tinyint(1) NOT NULL DEFAULT 0,
            search_visibility varchar(50) NOT NULL DEFAULT 'hidden',
            search_weight int(11) NOT NULL DEFAULT 0,
            sort_start int(11) NOT NULL DEFAULT 0,
            sort_end int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY kind_slug (entity_kind, canonical_slug),
            UNIQUE KEY kind_post (entity_kind, post_id),
            UNIQUE KEY kind_source (entity_kind, source_system, source_id),
            KEY source_lookup (source_system, source_id),
            KEY public_lookup (entity_kind, is_public),
            KEY search_visibility (search_visibility),
            KEY chronology (sort_start, sort_end)
        ) {$charset_collate};";

        $name_sql = "CREATE TABLE {$name_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            normalized_name varchar(191) NOT NULL DEFAULT '',
            name_type varchar(50) NOT NULL DEFAULT '',
            language varchar(20) NOT NULL DEFAULT '',
            is_primary tinyint(1) NOT NULL DEFAULT 0,
            valid_from_year smallint(6) DEFAULT NULL,
            valid_to_year smallint(6) DEFAULT NULL,
            position int(11) NOT NULL DEFAULT 0,
            source_system varchar(100) NOT NULL DEFAULT '',
            source_ref varchar(191) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY entity_lookup (entity_id),
            KEY name_lookup (normalized_name),
            KEY source_lookup (source_system, source_ref),
            KEY primary_lookup (entity_id, is_primary),
            KEY chronology (valid_from_year, valid_to_year)
        ) {$charset_collate};";

        $relation_sql = "CREATE TABLE {$relation_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            from_entity_id bigint(20) unsigned NOT NULL,
            to_entity_id bigint(20) unsigned NOT NULL,
            relation_family varchar(100) NOT NULL DEFAULT '',
            relation_type varchar(100) NOT NULL DEFAULT '',
            relation_role varchar(100) NOT NULL DEFAULT '',
            relation_label varchar(191) NOT NULL DEFAULT '',
            note text NOT NULL,
            weight int(11) NOT NULL DEFAULT 0,
            position int(11) NOT NULL DEFAULT 0,
            is_primary tinyint(1) NOT NULL DEFAULT 0,
            valid_from_year smallint(6) DEFAULT NULL,
            valid_to_year smallint(6) DEFAULT NULL,
            source_system varchar(100) NOT NULL DEFAULT '',
            source_ref varchar(191) NOT NULL DEFAULT '',
            is_public tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY from_lookup (from_entity_id),
            KEY to_lookup (to_entity_id),
            KEY family_lookup (from_entity_id, relation_family),
            KEY type_lookup (relation_type, relation_role),
            KEY source_lookup (source_system, source_ref),
            KEY public_lookup (from_entity_id, relation_family, is_public),
            KEY chronology (valid_from_year, valid_to_year)
        ) {$charset_collate};";

        $search_sql = "CREATE TABLE {$search_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned DEFAULT NULL,
            target_post_id bigint(20) unsigned NOT NULL,
            target_post_type varchar(100) NOT NULL DEFAULT '',
            search_bucket varchar(50) NOT NULL DEFAULT '',
            title text NOT NULL,
            excerpt text NOT NULL,
            search_text longtext NOT NULL,
            boost int(11) NOT NULL DEFAULT 0,
            is_public tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY target_post (target_post_id),
            KEY entity_lookup (entity_id),
            KEY bucket_lookup (is_public, search_bucket),
            KEY target_type (target_post_type)
        ) {$charset_collate};";

        $person_facts_sql = "CREATE TABLE {$person_facts_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            summary text NOT NULL,
            description longtext NOT NULL,
            website varchar(255) NOT NULL DEFAULT '',
            source_summary text NOT NULL,
            person_kind varchar(100) NOT NULL DEFAULT '',
            birth_year smallint(6) DEFAULT NULL,
            death_year smallint(6) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY entity_lookup (entity_id),
            KEY kind_lookup (person_kind),
            KEY chronology (birth_year, death_year)
        ) {$charset_collate};";

        $organization_facts_sql = "CREATE TABLE {$organization_facts_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            summary text NOT NULL,
            description longtext NOT NULL,
            website varchar(255) NOT NULL DEFAULT '',
            source_summary text NOT NULL,
            organization_kind varchar(100) NOT NULL DEFAULT '',
            organization_status varchar(100) NOT NULL DEFAULT '',
            founded_year smallint(6) DEFAULT NULL,
            dissolved_year smallint(6) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY entity_lookup (entity_id),
            KEY kind_lookup (organization_kind),
            KEY status_lookup (organization_status),
            KEY chronology (founded_year, dissolved_year)
        ) {$charset_collate};";

        dbDelta($entity_sql);
        dbDelta($name_sql);
        dbDelta($relation_sql);
        dbDelta($search_sql);
        dbDelta($person_facts_sql);
        dbDelta($organization_facts_sql);
        update_option(ISS_GRAPH_SCHEMA_OPTION, ISS_GRAPH_SCHEMA_VERSION, false);
    }

    public function table_exists(string $table_name): bool
    {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        return is_string($found) && $found === $table_name;
    }

    public function normalize_entity_kind(string $entity_kind): string
    {
        return sanitize_key($entity_kind);
    }

    public function build_canonical_slug(string $value, string $fallback = 'entity'): string
    {
        $slug = sanitize_title($value);
        if ($slug !== '') {
            return $slug;
        }

        $fallback = sanitize_title($fallback);
        if ($fallback !== '') {
            return $fallback;
        }

        return 'entity';
    }

    public function normalize_year($value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $year = (int) $value;
        if ($year === 0) {
            return null;
        }

        return max(-32768, min(32767, $year));
    }

    public function normalize_bool($value): int
    {
        return rest_sanitize_boolean($value) ? 1 : 0;
    }

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

    public function upsert_entity(array $data): ?array
    {
        global $wpdb;

        $entity_kind = $this->normalize_entity_kind((string) ($data['entity_kind'] ?? ''));
        if ($entity_kind === '') {
            return null;
        }

        $post_id = absint($data['post_id'] ?? 0);
        $profile_post_id = absint($data['profile_post_id'] ?? 0);
        $source_system = sanitize_key((string) ($data['source_system'] ?? ''));
        $source_id = sanitize_text_field((string) ($data['source_id'] ?? ''));
        $display_title = sanitize_text_field((string) ($data['display_title'] ?? ''));
        $canonical_slug_seed = (string) ($data['canonical_slug'] ?? $display_title);
        $canonical_slug = $this->build_canonical_slug($canonical_slug_seed, $entity_kind . ($post_id > 0 ? '-' . $post_id : ''));
        $summary = sanitize_textarea_field((string) ($data['summary'] ?? ''));
        $status = sanitize_key((string) ($data['status'] ?? ''));
        $search_visibility = sanitize_key((string) ($data['search_visibility'] ?? 'hidden'));
        if ($search_visibility === '') {
            $search_visibility = 'hidden';
        }

        $row = null;
        $entity_id = absint($data['id'] ?? 0);
        if ($entity_id > 0) {
            $row = $this->get_entity_by_id($entity_id);
        }

        if (!$row && $post_id > 0) {
            $row = $this->find_entity_by_post($entity_kind, $post_id);
        }

        if (!$row && $source_system !== '' && $source_id !== '') {
            $row = $this->find_entity_by_source($entity_kind, $source_system, $source_id);
        }

        if (!$row && $canonical_slug !== '') {
            $row = $this->find_entity_by_slug($entity_kind, $canonical_slug);
        }

        $timestamp = current_time('mysql', true);
        $db_data = [
            'entity_kind' => $entity_kind,
            'post_id' => $post_id > 0 ? $post_id : null,
            'profile_post_id' => $profile_post_id > 0 ? $profile_post_id : null,
            'source_system' => $source_system,
            'source_id' => $source_id,
            'canonical_slug' => $canonical_slug,
            'display_title' => $display_title,
            'summary' => $summary,
            'status' => $status,
            'is_public' => $this->normalize_bool($data['is_public'] ?? false),
            'has_profile' => $this->normalize_bool($data['has_profile'] ?? false),
            'search_visibility' => $search_visibility,
            'search_weight' => (int) ($data['search_weight'] ?? 0),
            'sort_start' => (int) ($data['sort_start'] ?? 0),
            'sort_end' => (int) ($data['sort_end'] ?? 0),
            'updated_at' => $timestamp,
        ];

        if ($row) {
            $db_data['created_at'] = (string) ($row['created_at'] ?? $timestamp);
            $wpdb->update(
                $this->get_entity_table_name(),
                $db_data,
                ['id' => (int) $row['id']],
                ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s'],
                ['%d']
            );

            return $this->get_entity_by_id((int) $row['id']);
        }

        $db_data['created_at'] = $timestamp;
        $wpdb->insert(
            $this->get_entity_table_name(),
            $db_data,
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s']
        );

        $insert_id = (int) $wpdb->insert_id;

        return $insert_id > 0 ? $this->get_entity_by_id($insert_id) : null;
    }

    public function find_or_create_named_entity(string $entity_kind, string $name, array $overrides = []): ?array
    {
        $name = sanitize_text_field($name);
        if ($name === '') {
            return null;
        }

        $entity_kind = $this->normalize_entity_kind($entity_kind);
        if ($entity_kind === '') {
            return null;
        }

        $canonical_slug = $this->build_canonical_slug($name, $entity_kind);
        $existing = $this->find_entity_by_slug($entity_kind, $canonical_slug);
        if ($existing) {
            $this->replace_entity_names_for_source((int) $existing['id'], 'entity_title', [[
                'name' => $name,
                'name_type' => 'primary',
                'is_primary' => true,
                'position' => 0,
            ]], 'entity:' . (int) $existing['id']);

            return $this->get_entity_by_id((int) $existing['id']);
        }

        $entity = $this->upsert_entity([
            'entity_kind' => $entity_kind,
            'source_system' => sanitize_key((string) ($overrides['source_system'] ?? 'manual_slug')),
            'source_id' => sanitize_text_field((string) ($overrides['source_id'] ?? $canonical_slug)),
            'canonical_slug' => $canonical_slug,
            'display_title' => sanitize_text_field((string) ($overrides['display_title'] ?? $name)),
            'summary' => sanitize_textarea_field((string) ($overrides['summary'] ?? '')),
            'status' => sanitize_key((string) ($overrides['status'] ?? 'publish')),
            'is_public' => !empty($overrides['is_public']),
            'has_profile' => !empty($overrides['has_profile']),
            'profile_post_id' => absint($overrides['profile_post_id'] ?? 0),
            'search_visibility' => sanitize_key((string) ($overrides['search_visibility'] ?? 'hidden')),
            'search_weight' => (int) ($overrides['search_weight'] ?? 0),
        ]);

        if ($entity) {
            $this->replace_entity_names_for_source((int) $entity['id'], 'entity_title', [[
                'name' => $name,
                'name_type' => 'primary',
                'is_primary' => true,
                'position' => 0,
            ]], 'entity:' . (int) $entity['id']);
        }

        return $entity ? $this->get_entity_by_id((int) $entity['id']) : null;
    }

    public function replace_entity_names_for_source(int $entity_id, string $source_system, array $rows, string $source_ref = ''): void
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
        $wpdb->delete(
            $this->get_name_table_name(),
            [
                'entity_id' => $entity_id,
                'source_system' => $source_system,
            ],
            ['%d', '%s']
        );

        if (!$rows) {
            return;
        }

        $timestamp = current_time('mysql', true);

        foreach (array_values($rows) as $index => $row) {
            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $wpdb->insert(
                $this->get_name_table_name(),
                [
                    'entity_id' => $entity_id,
                    'name' => $name,
                    'normalized_name' => $this->build_canonical_slug($name, 'name'),
                    'name_type' => sanitize_key((string) ($row['name_type'] ?? '')),
                    'language' => sanitize_key((string) ($row['language'] ?? '')),
                    'is_primary' => $this->normalize_bool($row['is_primary'] ?? false),
                    'valid_from_year' => $this->normalize_year($row['valid_from_year'] ?? null),
                    'valid_to_year' => $this->normalize_year($row['valid_to_year'] ?? null),
                    'position' => isset($row['position']) ? (int) $row['position'] : $index,
                    'source_system' => $source_system,
                    'source_ref' => $source_ref,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
            );
        }
    }

    public function replace_entity_relations_for_source(int $from_entity_id, string $relation_family, string $source_system, array $rows, string $source_ref = ''): void
    {
        global $wpdb;

        if ($from_entity_id <= 0) {
            return;
        }

        $relation_family = sanitize_key($relation_family);
        $source_system = sanitize_key($source_system);
        $source_ref = sanitize_text_field($source_ref);

        if ($relation_family === '' || $source_system === '') {
            return;
        }

        $wpdb->delete(
            $this->get_relation_table_name(),
            [
                'from_entity_id' => $from_entity_id,
                'relation_family' => $relation_family,
                'source_system' => $source_system,
            ],
            ['%d', '%s', '%s']
        );

        if (!$rows) {
            return;
        }

        $timestamp = current_time('mysql', true);

        foreach (array_values($rows) as $index => $row) {
            $to_entity_id = absint($row['to_entity_id'] ?? 0);
            if ($to_entity_id <= 0) {
                continue;
            }

            $wpdb->insert(
                $this->get_relation_table_name(),
                [
                    'from_entity_id' => $from_entity_id,
                    'to_entity_id' => $to_entity_id,
                    'relation_family' => $relation_family,
                    'relation_type' => sanitize_key((string) ($row['relation_type'] ?? '')),
                    'relation_role' => sanitize_key((string) ($row['relation_role'] ?? '')),
                    'relation_label' => sanitize_text_field((string) ($row['relation_label'] ?? '')),
                    'note' => sanitize_textarea_field((string) ($row['note'] ?? '')),
                    'weight' => (int) ($row['weight'] ?? 0),
                    'position' => isset($row['position']) ? (int) $row['position'] : $index,
                    'is_primary' => $this->normalize_bool($row['is_primary'] ?? false),
                    'valid_from_year' => $this->normalize_year($row['valid_from_year'] ?? null),
                    'valid_to_year' => $this->normalize_year($row['valid_to_year'] ?? null),
                    'source_system' => $source_system,
                    'source_ref' => $source_ref,
                    'is_public' => $this->normalize_bool($row['is_public'] ?? true),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
            );
        }
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
                'relation_label' => (string) ($row['relation_label'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
                'weight' => (int) ($row['weight'] ?? 0),
                'position' => (int) ($row['position'] ?? 0),
                'is_primary' => !empty($row['is_primary']),
                'valid_from_year' => isset($row['valid_from_year']) && $row['valid_from_year'] !== null ? (int) $row['valid_from_year'] : null,
                'valid_to_year' => isset($row['valid_to_year']) && $row['valid_to_year'] !== null ? (int) $row['valid_to_year'] : null,
                'source_system' => (string) ($row['source_system'] ?? ''),
                'source_ref' => (string) ($row['source_ref'] ?? ''),
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
                'relation_label' => (string) ($row['relation_label'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
                'weight' => (int) ($row['weight'] ?? 0),
                'position' => (int) ($row['position'] ?? 0),
                'is_primary' => !empty($row['is_primary']),
                'valid_from_year' => isset($row['valid_from_year']) && $row['valid_from_year'] !== null ? (int) $row['valid_from_year'] : null,
                'valid_to_year' => isset($row['valid_to_year']) && $row['valid_to_year'] !== null ? (int) $row['valid_to_year'] : null,
                'source_system' => (string) ($row['source_system'] ?? ''),
                'source_ref' => (string) ($row['source_ref'] ?? ''),
                'is_public' => !empty($row['is_public']),
            ];
        }, $rows));
    }

    public function get_facts_table_name_for_entity_kind(string $entity_kind): string
    {
        $entity_kind = $this->normalize_entity_kind($entity_kind);

        if ($entity_kind === 'person') {
            return $this->get_person_facts_table_name();
        }

        if ($entity_kind === 'organization') {
            return $this->get_organization_facts_table_name();
        }

        return '';
    }

    public function get_entity_facts(string $entity_kind, int $entity_id): array
    {
        $entity_kind = $this->normalize_entity_kind($entity_kind);
        $table_name = $this->get_facts_table_name_for_entity_kind($entity_kind);

        if ($entity_id <= 0 || $table_name === '') {
            return [];
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$table_name}
                WHERE entity_id = %d
                LIMIT 1",
                $entity_id
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return [];
        }

        $base = [
            'id' => (int) ($row['id'] ?? 0),
            'entity_id' => (int) ($row['entity_id'] ?? 0),
            'summary' => (string) ($row['summary'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'website' => (string) ($row['website'] ?? ''),
            'source_summary' => (string) ($row['source_summary'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];

        if ($entity_kind === 'person') {
            $base['person_kind'] = (string) ($row['person_kind'] ?? '');
            $base['birth_year'] = isset($row['birth_year']) && $row['birth_year'] !== null ? (int) $row['birth_year'] : null;
            $base['death_year'] = isset($row['death_year']) && $row['death_year'] !== null ? (int) $row['death_year'] : null;
        } elseif ($entity_kind === 'organization') {
            $base['organization_kind'] = (string) ($row['organization_kind'] ?? '');
            $base['organization_status'] = (string) ($row['organization_status'] ?? '');
            $base['founded_year'] = isset($row['founded_year']) && $row['founded_year'] !== null ? (int) $row['founded_year'] : null;
            $base['dissolved_year'] = isset($row['dissolved_year']) && $row['dissolved_year'] !== null ? (int) $row['dissolved_year'] : null;
        }

        return $base;
    }

    public function upsert_entity_facts(string $entity_kind, int $entity_id, array $data): array
    {
        $entity_kind = $this->normalize_entity_kind($entity_kind);
        $table_name = $this->get_facts_table_name_for_entity_kind($entity_kind);

        if ($entity_id <= 0 || $table_name === '') {
            return [];
        }

        $entity = $this->get_entity_by_id($entity_id);
        if (!$entity || ($entity['entity_kind'] ?? '') !== $entity_kind) {
            return [];
        }

        global $wpdb;

        $timestamp = current_time('mysql', true);
        $row = $this->get_entity_facts($entity_kind, $entity_id);
        $db_data = [
            'entity_id' => $entity_id,
            'summary' => sanitize_textarea_field((string) ($data['summary'] ?? '')),
            'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
            'website' => esc_url_raw((string) ($data['website'] ?? '')),
            'source_summary' => sanitize_textarea_field((string) ($data['source_summary'] ?? '')),
            'updated_at' => $timestamp,
        ];

        $formats = ['%d', '%s', '%s', '%s', '%s', '%s'];

        if ($entity_kind === 'person') {
            $db_data['person_kind'] = sanitize_text_field((string) ($data['person_kind'] ?? ''));
            $db_data['birth_year'] = $this->normalize_year($data['birth_year'] ?? null);
            $db_data['death_year'] = $this->normalize_year($data['death_year'] ?? null);
            $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d'];
        } elseif ($entity_kind === 'organization') {
            $db_data['organization_kind'] = sanitize_text_field((string) ($data['organization_kind'] ?? ''));
            $db_data['organization_status'] = sanitize_text_field((string) ($data['organization_status'] ?? ''));
            $db_data['founded_year'] = $this->normalize_year($data['founded_year'] ?? null);
            $db_data['dissolved_year'] = $this->normalize_year($data['dissolved_year'] ?? null);
            $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d'];
        }

        if ($row) {
            $db_data['created_at'] = (string) ($row['created_at'] ?? $timestamp);
            $formats[] = '%s';
            $wpdb->update(
                $table_name,
                $db_data,
                ['entity_id' => $entity_id],
                $formats,
                ['%d']
            );

            return $this->get_entity_facts($entity_kind, $entity_id);
        }

        $db_data['created_at'] = $timestamp;
        $formats[] = '%s';
        $wpdb->insert($table_name, $db_data, $formats);

        return $this->get_entity_facts($entity_kind, $entity_id);
    }

    public function delete_entity_facts(string $entity_kind, int $entity_id): void
    {
        $table_name = $this->get_facts_table_name_for_entity_kind($entity_kind);
        if ($entity_id <= 0 || $table_name === '') {
            return;
        }

        global $wpdb;

        $wpdb->delete($table_name, ['entity_id' => $entity_id], ['%d']);
    }

    public function get_search_row_by_post(int $post_id): ?array
    {
        if ($post_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->get_search_table_name()}
                WHERE target_post_id = %d
                LIMIT 1",
                $post_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function upsert_search_row(array $data): ?array
    {
        global $wpdb;

        $post_id = absint($data['target_post_id'] ?? 0);
        if ($post_id <= 0) {
            return null;
        }

        $row = $this->get_search_row_by_post($post_id);
        $timestamp = current_time('mysql', true);
        $db_data = [
            'entity_id' => absint($data['entity_id'] ?? 0) ?: null,
            'target_post_id' => $post_id,
            'target_post_type' => sanitize_key((string) ($data['target_post_type'] ?? get_post_type($post_id))),
            'search_bucket' => sanitize_key((string) ($data['search_bucket'] ?? '')),
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'excerpt' => sanitize_textarea_field((string) ($data['excerpt'] ?? '')),
            'search_text' => sanitize_textarea_field((string) ($data['search_text'] ?? '')),
            'boost' => (int) ($data['boost'] ?? 0),
            'is_public' => $this->normalize_bool($data['is_public'] ?? true),
            'updated_at' => $timestamp,
        ];

        if ($row) {
            $db_data['created_at'] = (string) ($row['created_at'] ?? $timestamp);
            $wpdb->update(
                $this->get_search_table_name(),
                $db_data,
                ['target_post_id' => $post_id],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'],
                ['%d']
            );

            return $this->get_search_row_by_post($post_id);
        }

        $db_data['created_at'] = $timestamp;
        $wpdb->insert(
            $this->get_search_table_name(),
            $db_data,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        return $this->get_search_row_by_post($post_id);
    }

    public function delete_search_row_by_post(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        global $wpdb;

        $wpdb->delete($this->get_search_table_name(), ['target_post_id' => $post_id], ['%d']);
    }

    public function delete_entity_by_post(string $entity_kind, int $post_id): void
    {
        $entity = $this->find_entity_by_post($entity_kind, $post_id);
        if (!$entity) {
            return;
        }

        $this->delete_entity((int) $entity['id']);
    }

    public function delete_entity(int $entity_id): void
    {
        global $wpdb;

        if ($entity_id <= 0) {
            return;
        }

        $wpdb->delete($this->get_relation_table_name(), ['from_entity_id' => $entity_id], ['%d']);
        $wpdb->delete($this->get_relation_table_name(), ['to_entity_id' => $entity_id], ['%d']);
        $wpdb->delete($this->get_name_table_name(), ['entity_id' => $entity_id], ['%d']);
        $wpdb->delete($this->get_search_table_name(), ['entity_id' => $entity_id], ['%d']);
        $wpdb->delete($this->get_person_facts_table_name(), ['entity_id' => $entity_id], ['%d']);
        $wpdb->delete($this->get_organization_facts_table_name(), ['entity_id' => $entity_id], ['%d']);
        $wpdb->delete($this->get_entity_table_name(), ['id' => $entity_id], ['%d']);
    }
}

function iss_graph_get_service(): ISS_Graph_Service
{
    return ISS_Graph_Service::get_instance();
}
