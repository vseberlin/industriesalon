<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- This service owns and queries the ISS graph custom tables.

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

    public function get_identifier_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_entity_identifiers';
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

    public function get_evidence_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_entity_evidence_refs';
    }

    public function get_editorial_signal_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_graph_editorial_signals';
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
        $identifier_table = $this->get_identifier_table_name();
        $relation_table = $this->get_relation_table_name();
        $search_table = $this->get_search_table_name();
        $person_facts_table = $this->get_person_facts_table_name();
        $organization_facts_table = $this->get_organization_facts_table_name();
        $evidence_table = $this->get_evidence_table_name();
        $editorial_signal_table = $this->get_editorial_signal_table_name();

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

        $identifier_sql = "CREATE TABLE {$identifier_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            namespace varchar(100) NOT NULL DEFAULT '',
            value varchar(255) NOT NULL DEFAULT '',
            normalized_value varchar(191) NOT NULL DEFAULT '',
            url varchar(255) NOT NULL DEFAULT '',
            label varchar(191) NOT NULL DEFAULT '',
            trust_level varchar(50) NOT NULL DEFAULT 'suggest_only',
            confidence smallint(6) NOT NULL DEFAULT 0,
            source_system varchar(100) NOT NULL DEFAULT '',
            source_ref varchar(191) NOT NULL DEFAULT '',
            is_primary tinyint(1) NOT NULL DEFAULT 0,
            status varchar(50) NOT NULL DEFAULT 'accepted',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY namespace_value (namespace, normalized_value),
            KEY entity_lookup (entity_id),
            KEY namespace_lookup (namespace),
            KEY source_lookup (source_system, source_ref),
            KEY trust_lookup (trust_level),
            KEY status_lookup (status)
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

        $evidence_sql = "CREATE TABLE {$evidence_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            target_kind varchar(50) NOT NULL DEFAULT '',
            target_id bigint(20) unsigned DEFAULT NULL,
            source_system varchar(100) NOT NULL DEFAULT '',
            source_ref varchar(191) NOT NULL DEFAULT '',
            source_url varchar(255) NOT NULL DEFAULT '',
            label varchar(191) NOT NULL DEFAULT '',
            note text NOT NULL,
            confidence smallint(6) NOT NULL DEFAULT 0,
            status varchar(50) NOT NULL DEFAULT 'accepted',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY entity_lookup (entity_id),
            KEY target_lookup (target_kind, target_id),
            KEY source_lookup (source_system, source_ref),
            KEY status_lookup (status)
        ) {$charset_collate};";

        $editorial_signal_sql = "CREATE TABLE {$editorial_signal_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            context_entity_id bigint(20) unsigned DEFAULT NULL,
            context_post_id bigint(20) unsigned NOT NULL,
            target_entity_id bigint(20) unsigned DEFAULT NULL,
            target_post_id bigint(20) unsigned NOT NULL,
            surface varchar(50) NOT NULL DEFAULT 'related',
            signal_type varchar(50) NOT NULL DEFAULT '',
            reason text NOT NULL,
            expires_at datetime DEFAULT NULL,
            author_user_id bigint(20) unsigned DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY context_post_target_surface (context_post_id, target_post_id, surface),
            KEY context_post_surface_status (context_post_id, surface, status),
            KEY context_entity_surface_status (context_entity_id, surface, status),
            KEY target_lookup (target_post_id),
            KEY expiry_lookup (status, expires_at)
        ) {$charset_collate};";

        dbDelta($entity_sql);
        dbDelta($name_sql);
        dbDelta($identifier_sql);
        dbDelta($relation_sql);
        dbDelta($search_sql);
        dbDelta($person_facts_sql);
        dbDelta($organization_facts_sql);
        dbDelta($evidence_sql);
        dbDelta($editorial_signal_sql);
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

    public function normalize_confidence($value): int
    {
        if (!is_scalar($value)) {
            return 0;
        }

        return max(0, min(100, (int) $value));
    }

    public function normalize_identifier_namespace(string $namespace): string
    {
        return sanitize_key($namespace);
    }

    public function normalize_identifier_value($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field(trim((string) $value));
    }

    public function normalize_identifier_lookup_value($value): string
    {
        $value = $this->normalize_identifier_value($value);
        if ($value === '') {
            return '';
        }

        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        return $this->limit_string($value, 191);
    }

    public function normalize_identifier_trust_level($value): string
    {
        $trust_level = sanitize_key((string) $value);
        $allowed = [
            'trusted_auto_link' => true,
            'trusted_review' => true,
            'suggest_only' => true,
        ];

        return isset($allowed[$trust_level]) ? $trust_level : 'suggest_only';
    }

    public function normalize_evidence_status($value): string
    {
        $status = sanitize_key((string) $value);
        $allowed = [
            'accepted' => true,
            'review' => true,
            'pending' => true,
            'rejected' => true,
        ];

        return isset($allowed[$status]) ? $status : 'accepted';
    }

    private function limit_string(string $value, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $limit, 'UTF-8')
            : substr($value, 0, $limit);
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
        $canonical_slug = $this->limit_string(
            $this->build_canonical_slug($canonical_slug_seed, $entity_kind . ($post_id > 0 ? '-' . $post_id : '')),
            191
        );
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
        return $this->resolve_or_create_named_entity($entity_kind, $name, $overrides);
    }

    public function resolve_or_create_named_entity(string $entity_kind, string $name, array $overrides = [], array $args = []): ?array
    {
        $name = sanitize_text_field($name);
        if ($name === '') {
            return null;
        }

        $entity_kind = $this->normalize_entity_kind($entity_kind);
        if ($entity_kind === '') {
            return null;
        }

        $name_slug = $this->build_canonical_slug($name, $entity_kind);
        $source_system = sanitize_key((string) ($overrides['source_system'] ?? 'manual_slug'));
        if ($source_system === '') {
            $source_system = 'manual_slug';
        }

        $source_id = sanitize_text_field((string) ($overrides['source_id'] ?? ''));
        if ($source_id === '') {
            $source_id = $name_slug;
        }

        $explicit_source_id = isset($overrides['source_id']) && trim((string) $overrides['source_id']) !== '';
        $create_slug_seed = (string) ($overrides['canonical_slug'] ?? ($explicit_source_id ? $source_system . '-' . $source_id : $name));
        $allow_public_create = !empty($args['allow_public_create']);
        $entity_data = [
            'entity_kind' => $entity_kind,
            'source_system' => $source_system,
            'source_id' => $source_id,
            'canonical_slug' => $create_slug_seed,
            'display_title' => sanitize_text_field((string) ($overrides['display_title'] ?? $name)),
            'summary' => sanitize_textarea_field((string) ($overrides['summary'] ?? '')),
            'status' => sanitize_key((string) ($overrides['status'] ?? 'publish')),
            'is_public' => $allow_public_create && !empty($overrides['is_public']),
            'has_profile' => !empty($overrides['has_profile']),
            'profile_post_id' => absint($overrides['profile_post_id'] ?? 0),
            'search_visibility' => $allow_public_create
                ? sanitize_key((string) ($overrides['search_visibility'] ?? 'hidden'))
                : 'hidden',
            'search_weight' => (int) ($overrides['search_weight'] ?? 0),
        ];

        if ($source_system !== '' && $source_id !== '') {
            $source_entity = $this->find_entity_by_source($entity_kind, $source_system, $source_id);
            if ($source_entity) {
                $entity_data['id'] = (int) ($source_entity['id'] ?? 0);
                $entity = $this->upsert_entity($entity_data);

                return $entity ? $this->sync_named_entity_title($entity, $name) : null;
            }

            $identifier = $this->get_identifier_by_namespace_value($source_system, $source_id);
            if ($identifier && (string) ($identifier['status'] ?? '') === 'accepted') {
                $identifier_entity = $this->get_entity_by_id((int) ($identifier['entity_id'] ?? 0));
                if ($identifier_entity && (string) ($identifier_entity['entity_kind'] ?? '') === $entity_kind) {
                    return $identifier_entity;
                }
            }
        }

        $existing = $this->find_entity_by_slug($entity_kind, $name_slug);
        if ($existing) {
            return $this->sync_named_entity_title($existing, $name);
        }

        if (function_exists('iss_graph_resolve_entity')) {
            $resolver_args = array_merge($args, [
                'entity_kind' => $entity_kind,
                'label' => $name,
            ]);
            $resolved = iss_graph_resolve_entity($resolver_args);
            $resolved_entity = $this->get_auto_linkable_resolver_entity($resolved, $entity_kind);
            if ($resolved_entity) {
                return $resolved_entity;
            }
        }

        $should_create = array_key_exists('create', $args) ? !empty($args['create']) : true;
        if (!$should_create) {
            return null;
        }

        $entity = $this->upsert_entity($entity_data);

        return $entity ? $this->sync_named_entity_title($entity, $name) : null;
    }

    private function sync_named_entity_title(array $entity, string $name): ?array
    {
        $entity_id = (int) ($entity['id'] ?? 0);
        if ($entity_id <= 0) {
            return null;
        }

        $this->replace_entity_names_for_source($entity_id, 'entity_title', [[
            'name' => $name,
            'name_type' => 'primary',
            'is_primary' => true,
            'position' => 0,
        ]], 'entity:' . $entity_id);

        if (function_exists('iss_graph_sync_entity_alias_backfill')) {
            iss_graph_sync_entity_alias_backfill($entity_id);
        }

        return $this->get_entity_by_id($entity_id);
    }

    private function get_auto_linkable_resolver_entity(array $result, string $entity_kind): ?array
    {
        if ((string) ($result['status'] ?? '') === 'matched' && !empty($result['entity']) && is_array($result['entity'])) {
            $entity = $result['entity'];

            return (string) ($entity['entity_kind'] ?? '') === $entity_kind ? $entity : null;
        }

        if ((string) ($result['status'] ?? '') !== 'candidates') {
            return null;
        }

        $candidates = [];
        foreach ((array) ($result['candidates'] ?? []) as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $entity_id = (int) ($candidate['entity_id'] ?? 0);
            $candidate_entity = !empty($candidate['entity']) && is_array($candidate['entity'])
                ? $candidate['entity']
                : null;
            if ($entity_id <= 0 || !$candidate_entity || (string) ($candidate_entity['entity_kind'] ?? '') !== $entity_kind) {
                continue;
            }

            $candidates[$entity_id][] = $candidate;
        }

        if (count($candidates) !== 1) {
            return null;
        }

        $candidate_rows = reset($candidates);
        foreach ((array) $candidate_rows as $candidate) {
            $name_type = sanitize_key((string) ($candidate['matched_name_type'] ?? ''));
            $match_type = sanitize_key((string) ($candidate['match_type'] ?? ''));
            $confidence = (int) ($candidate['confidence'] ?? 0);
            if (
                $match_type === 'exact_accepted_alias_match'
                && $confidence >= 90
                && in_array($name_type, ['primary', 'canonical', 'official'], true)
            ) {
                return $candidate['entity'];
            }
        }

        return null;
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
        if ($existing && (int) ($existing['entity_id'] ?? 0) !== $entity_id) {
            return null;
        }

        global $wpdb;

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
            $wpdb->update(
                $this->get_identifier_table_name(),
                $db_data,
                ['id' => (int) $existing['id']],
                $formats,
                ['%d']
            );

            return $this->get_identifier_by_id((int) $existing['id']);
        }

        $db_data['created_at'] = $timestamp;
        $formats[] = '%s';
        $wpdb->insert($this->get_identifier_table_name(), $db_data, $formats);

        $insert_id = (int) $wpdb->insert_id;

        return $insert_id > 0 ? $this->get_identifier_by_id($insert_id) : null;
    }

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
        $wpdb->delete(
            $this->get_identifier_table_name(),
            [
                'entity_id' => $entity_id,
                'source_system' => $source_system,
            ],
            ['%d', '%s']
        );

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $row['source_system'] = $source_system;
            $row['source_ref'] = $source_ref;
            $this->upsert_entity_identifier($entity_id, $row);
        }
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
        if ($existing && (int) ($existing['entity_id'] ?? 0) !== $entity_id) {
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
            $wpdb->update(
                $this->get_evidence_table_name(),
                $db_data,
                ['id' => $evidence_id],
                $formats,
                ['%d']
            );

            return $this->get_evidence_ref_by_id($evidence_id);
        }

        $db_data['created_at'] = $timestamp;
        $formats[] = '%s';
        $wpdb->insert($this->get_evidence_table_name(), $db_data, $formats);

        $insert_id = (int) $wpdb->insert_id;

        return $insert_id > 0 ? $this->get_evidence_ref_by_id($insert_id) : null;
    }

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
        $wpdb->delete(
            $this->get_evidence_table_name(),
            [
                'entity_id' => $entity_id,
                'source_system' => $source_system,
            ],
            ['%d', '%s']
        );

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $row['source_system'] = $source_system;
            $row['source_ref'] = $source_ref;
            $this->upsert_entity_evidence_ref($entity_id, $row);
        }
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

    public function normalize_editorial_signal_surface($value): string
    {
        $surface = sanitize_key((string) $value);
        $allowed = [
            'related' => true,
        ];

        return isset($allowed[$surface]) ? $surface : 'related';
    }

    public function normalize_editorial_signal_type($value): string
    {
        $signal = sanitize_key((string) $value);
        $allowed = [
            'feature' => true,
            'hide' => true,
        ];

        return isset($allowed[$signal]) ? $signal : '';
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
            'surface' => (string) ($row['surface'] ?? 'related'),
            'signal' => (string) ($row['signal_type'] ?? ''),
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
            ORDER BY CASE signal_type WHEN 'feature' THEN 0 WHEN 'hide' THEN 1 ELSE 2 END ASC, created_at ASC, id ASC
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
        $timestamp = current_time('mysql', true);
        $existing = $this->get_editorial_signal_by_post_target($context_post_id, $target_post_id, $surface);
        $db_data = [
            'context_entity_id' => $context_entity_id > 0 ? $context_entity_id : null,
            'context_post_id' => $context_post_id,
            'target_entity_id' => $target_entity_id > 0 ? $target_entity_id : null,
            'target_post_id' => $target_post_id,
            'surface' => $surface,
            'signal_type' => $signal,
            'reason' => sanitize_textarea_field((string) ($data['reason'] ?? '')),
            'expires_at' => $this->normalize_editorial_signal_expiry($data['expires_at'] ?? null),
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

        $deleted = $wpdb->delete(
            $this->get_editorial_signal_table_name(),
            [
                'context_post_id' => $context_post_id,
                'target_post_id' => $target_post_id,
                'surface' => $surface,
            ],
            ['%d', '%d', '%s']
        );

        return $deleted !== false;
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
        $wpdb->delete($this->get_identifier_table_name(), ['entity_id' => $entity_id], ['%d']);
        $wpdb->delete($this->get_evidence_table_name(), ['entity_id' => $entity_id], ['%d']);
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

function iss_graph_get_entity_kind_for_post_type(string $post_type): string
{
    $post_type = sanitize_key($post_type);
    if ($post_type === '') {
        return '';
    }

    $register_post_type = defined('ISS_REGISTER_POST_TYPE') ? sanitize_key((string) ISS_REGISTER_POST_TYPE) : 'register_place';
    $archive_object_post_type = defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? sanitize_key((string) ISS_WF_IMPORT_OBJECT_POST_TYPE) : 'archivobjekt';
    $archive_collection_post_type = defined('ISS_WF_IMPORT_COLLECTION_POST_TYPE') ? sanitize_key((string) ISS_WF_IMPORT_COLLECTION_POST_TYPE) : 'archivsammlung';

    $map = [
        $register_post_type => 'place',
        $archive_object_post_type => 'archive_object',
        $archive_collection_post_type => 'archive_collection',
    ];

    $map = (array) apply_filters('iss_graph_entity_kind_for_post_type_map', $map);

    return sanitize_key((string) ($map[$post_type] ?? $post_type));
}

function iss_graph_build_wp_post_identifier_rows(WP_Post $post): array
{
    $rows = [[
        'namespace' => 'wp_post',
        'value' => (string) $post->ID,
        'label' => 'WordPress post',
        'trust_level' => 'trusted_auto_link',
        'confidence' => 100,
        'is_primary' => true,
        'status' => 'accepted',
    ]];

    $slug = trim((string) $post->post_name);
    if ($slug !== '') {
        $rows[] = [
            'namespace' => 'legacy_slug',
            'value' => $post->post_type . ':' . $slug,
            'label' => 'WordPress slug',
            'trust_level' => 'trusted_review',
            'confidence' => 80,
            'status' => 'accepted',
        ];
    }

    return $rows;
}

function iss_graph_sync_wp_post_identifiers(int $entity_id, WP_Post $post, string $source_system = 'wp_post', array $extra_rows = []): void
{
    $entity_id = absint($entity_id);
    if ($entity_id <= 0) {
        return;
    }

    $rows = array_merge(iss_graph_build_wp_post_identifier_rows($post), $extra_rows);
    iss_graph_get_service()->replace_entity_identifiers_for_source(
        $entity_id,
        $source_system,
        $rows,
        'post:' . (int) $post->ID
    );
}

function iss_graph_get_entity_for_post(int $post_id): ?array
{
    return iss_graph_get_service()->find_entity_by_post_id(absint($post_id));
}

function iss_graph_get_or_create_entity_for_post(int $post_id, string $kind = ''): ?array
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return null;
    }

    $service = iss_graph_get_service();
    $kind = $service->normalize_entity_kind($kind);
    $existing = $kind !== ''
        ? $service->find_entity_by_post($kind, $post_id)
        : $service->find_entity_by_post_id($post_id);

    if ($existing) {
        return $existing;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
        return null;
    }

    $post_type = sanitize_key((string) $post->post_type);
    $register_post_type = defined('ISS_REGISTER_POST_TYPE') ? sanitize_key((string) ISS_REGISTER_POST_TYPE) : 'register_place';
    $archive_object_post_type = defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? sanitize_key((string) ISS_WF_IMPORT_OBJECT_POST_TYPE) : 'archivobjekt';

    if ($post_type === $register_post_type && function_exists('iss_graph_sync_register_place_entity')) {
        return iss_graph_sync_register_place_entity($post_id);
    }

    if ($post_type === $archive_object_post_type && function_exists('iss_graph_sync_archive_object_entity')) {
        return iss_graph_sync_archive_object_entity($post_id);
    }

    if (function_exists('iss_graph_is_content_relation_post_type') && iss_graph_is_content_relation_post_type($post_type)) {
        return iss_graph_sync_public_content_entity($post_id);
    }

    $entity_kind = $kind !== '' ? $kind : iss_graph_get_entity_kind_for_post_type($post_type);
    if ($entity_kind === '') {
        return null;
    }

    $entity = $service->upsert_entity([
        'entity_kind' => $entity_kind,
        'post_id' => $post_id,
        'source_system' => 'wp_post',
        'source_id' => (string) $post_id,
        'canonical_slug' => $post_type . '-' . $post_id . '-' . (trim((string) $post->post_name) !== '' ? (string) $post->post_name : sanitize_title((string) get_the_title($post))),
        'display_title' => get_the_title($post),
        'summary' => (string) get_post_field('post_excerpt', $post_id),
        'status' => sanitize_key((string) $post->post_status),
        'is_public' => $post->post_status === 'publish',
        'search_visibility' => $post->post_status === 'publish' ? 'public' : 'hidden',
    ]);

    if (!$entity || empty($entity['id'])) {
        return null;
    }

    $entity_id = (int) $entity['id'];
    $service->replace_entity_names_for_source($entity_id, 'wp_post_title', [[
        'name' => get_the_title($post) ?: ($post_type . ' ' . $post_id),
        'name_type' => 'primary',
        'is_primary' => true,
        'position' => 0,
    ]], 'post:' . $post_id);

    iss_graph_sync_wp_post_identifiers($entity_id, $post, 'wp_post');

    if (function_exists('iss_graph_sync_entity_alias_backfill')) {
        iss_graph_sync_entity_alias_backfill($entity_id);
    }

    return $service->get_entity_by_id($entity_id);
}

function iss_graph_get_entity_by_identifier(string $namespace, $value): ?array
{
    return iss_graph_get_service()->get_entity_by_identifier($namespace, $value);
}

function iss_graph_search_entities(array $args): array
{
    $query = (string) ($args['query'] ?? ($args['search'] ?? ''));

    return iss_graph_get_service()->search_entities($query, $args);
}

function iss_graph_get_entity_names(int $entity_id, array $args = []): array
{
    return iss_graph_get_service()->get_names_for_entity($entity_id, $args);
}

function iss_graph_get_entity_identifiers(int $entity_id, array $args = []): array
{
    return iss_graph_get_service()->get_identifiers_for_entity($entity_id, $args);
}

function iss_graph_get_entity_relations(int $entity_id, array $args = []): array
{
    $relation_family = sanitize_key((string) ($args['relation_family'] ?? ''));

    return iss_graph_get_service()->get_relations_for_entity($entity_id, $relation_family, $args);
}

function iss_graph_get_active_editorial_signals_for_context(int $context_entity_id, string $surface = 'related', array $args = []): array
{
    return iss_graph_get_service()->get_active_editorial_signals($context_entity_id, $surface, $args);
}

function iss_graph_get_active_editorial_signals_for_post(int $context_post_id, string $surface = 'related', array $args = []): array
{
    return iss_graph_get_service()->get_active_editorial_signals_for_post($context_post_id, $surface, $args);
}

function iss_graph_get_active_related_promotion_post_ids(array $post_types, array $args = []): array
{
    return iss_graph_get_service()->get_active_related_promotion_post_ids($post_types, $args);
}

function iss_graph_upsert_editorial_signal_for_post(int $context_post_id, int $target_post_id, string $signal, array $args = []): ?array
{
    $args['signal'] = $signal;

    return iss_graph_get_service()->upsert_editorial_signal_for_post($context_post_id, $target_post_id, $args);
}

function iss_graph_remove_editorial_signal_for_post(int $context_post_id, int $target_post_id, string $surface = 'related'): bool
{
    return iss_graph_get_service()->remove_editorial_signal_for_post($context_post_id, $target_post_id, $surface);
}

function iss_graph_replace_entity_projection(string $source_system, int $entity_id, array $payload): void
{
    $source_system = sanitize_key($source_system);
    $entity_id = absint($entity_id);
    if ($source_system === '' || $entity_id <= 0) {
        return;
    }

    $service = iss_graph_get_service();
    $source_ref = sanitize_text_field((string) ($payload['source_ref'] ?? ('entity:' . $entity_id)));

    if (isset($payload['names']) && is_array($payload['names'])) {
        $service->replace_entity_names_for_source($entity_id, $source_system, $payload['names'], $source_ref);
    }

    if (isset($payload['identifiers']) && is_array($payload['identifiers'])) {
        $service->replace_entity_identifiers_for_source($entity_id, $source_system, $payload['identifiers'], $source_ref);
    }

    $evidence_rows = $payload['evidence_refs'] ?? ($payload['evidence'] ?? null);
    if (is_array($evidence_rows)) {
        $service->replace_entity_evidence_refs_for_source($entity_id, $source_system, $evidence_rows, $source_ref);
    }

    if (!isset($payload['relations']) || !is_array($payload['relations'])) {
        return;
    }

    $relations = $payload['relations'];
    $is_list = array_keys($relations) === range(0, count($relations) - 1);
    if ($is_list) {
        $grouped = [];
        foreach ($relations as $row) {
            if (!is_array($row)) {
                continue;
            }

            $family = sanitize_key((string) ($row['relation_family'] ?? ''));
            if ($family === '') {
                continue;
            }

            $grouped[$family][] = $row;
        }
        $relations = $grouped;
    }

    foreach ($relations as $relation_family => $rows) {
        if (!is_array($rows)) {
            continue;
        }

        $service->replace_entity_relations_for_source($entity_id, sanitize_key((string) $relation_family), $source_system, $rows, $source_ref);
    }
}

function iss_graph_map_resolver_candidate(array $entity, string $match_type, int $confidence, string $reason = '', array $extra = []): array
{
    return array_merge([
        'entity' => $entity,
        'entity_id' => (int) ($entity['id'] ?? 0),
        'entity_kind' => (string) ($entity['entity_kind'] ?? ''),
        'display_title' => (string) ($entity['display_title'] ?? ''),
        'match_type' => $match_type,
        'confidence' => max(0, min(100, $confidence)),
        'reason' => $reason,
    ], $extra);
}

function iss_graph_resolver_result(string $status, string $match_type, ?array $entity = null, array $candidates = [], int $confidence = 0, string $reason = ''): array
{
    return [
        'status' => $status,
        'match_type' => $match_type,
        'entity' => $entity,
        'entity_id' => $entity ? (int) ($entity['id'] ?? 0) : 0,
        'candidates' => $candidates,
        'confidence' => max(0, min(100, $confidence)),
        'reason' => $reason,
        'should_create' => $status === 'no_match',
    ];
}

function iss_graph_resolve_or_create_named_entity(string $entity_kind, string $name, array $overrides = [], array $args = []): ?array
{
    return iss_graph_get_service()->resolve_or_create_named_entity($entity_kind, $name, $overrides, $args);
}

function iss_graph_resolve_entity(array $args): array
{
    $service = iss_graph_get_service();
    $entity_kind = $service->normalize_entity_kind((string) ($args['entity_kind'] ?? ($args['kind'] ?? '')));
    $entity_id = absint($args['entity_id'] ?? 0);
    if ($entity_id > 0) {
        $entity = $service->get_entity_by_id($entity_id);
        if ($entity && ($entity_kind === '' || (string) ($entity['entity_kind'] ?? '') === $entity_kind)) {
            return iss_graph_resolver_result('matched', 'exact_entity_id_match', $entity, [], 100, 'Exact graph entity ID match.');
        }
    }

    $namespace = $service->normalize_identifier_namespace((string) ($args['namespace'] ?? ''));
    $identifier_value = $args['identifier'] ?? ($args['value'] ?? '');
    if ($namespace !== '' && is_scalar($identifier_value) && trim((string) $identifier_value) !== '') {
        $identifier = $service->get_identifier_by_namespace_value($namespace, $identifier_value);
        if ($identifier && (string) ($identifier['status'] ?? '') === 'accepted') {
            $entity = $service->get_entity_by_id((int) ($identifier['entity_id'] ?? 0));
            if ($entity && ($entity_kind === '' || (string) ($entity['entity_kind'] ?? '') === $entity_kind)) {
                $trust_level = (string) ($identifier['trust_level'] ?? 'suggest_only');
                if ($trust_level === 'trusted_auto_link') {
                    return iss_graph_resolver_result('matched', 'exact_trusted_identifier_match', $entity, [], (int) ($identifier['confidence'] ?? 100), 'Exact trusted identifier match.');
                }

                $match_type = $trust_level === 'trusted_review' ? 'exact_trusted_review_identifier_match' : 'exact_suggested_identifier_match';
                return iss_graph_resolver_result('candidates', $match_type, null, [
                    iss_graph_map_resolver_candidate($entity, $match_type, (int) ($identifier['confidence'] ?? 80), 'Identifier requires review.', [
                        'identifier' => $identifier,
                    ]),
                ], (int) ($identifier['confidence'] ?? 80), 'Identifier match requires review.');
            }
        }
    }

    $post_id = absint($args['post_id'] ?? 0);
    if ($post_id > 0) {
        $entity = iss_graph_get_entity_for_post($post_id);
        if ($entity && ($entity_kind === '' || (string) ($entity['entity_kind'] ?? '') === $entity_kind)) {
            return iss_graph_resolver_result('matched', 'exact_post_match', $entity, [], 100, 'Exact WordPress post match.');
        }
    }

    $label = sanitize_text_field((string) ($args['label'] ?? ($args['raw_label'] ?? '')));
    if ($label !== '') {
        $exact_rows = $service->find_entities_by_normalized_name($label, [
            'entity_kind' => $entity_kind,
            'limit' => 10,
        ]);

        if ($exact_rows) {
            $candidates = array_map(static function (array $row): array {
                $name_type = sanitize_key((string) ($row['matched_name_type'] ?? ''));
                $match_type = in_array($name_type, ['primary', 'canonical', 'official'], true)
                    ? 'exact_accepted_alias_match'
                    : 'historical_or_source_label_match';

                return iss_graph_map_resolver_candidate($row, $match_type, $match_type === 'exact_accepted_alias_match' ? 90 : 75, 'Exact normalized name match.', [
                    'matched_name' => (string) ($row['matched_name'] ?? ''),
                    'matched_name_type' => $name_type,
                    'matched_name_id' => (int) ($row['matched_name_id'] ?? 0),
                ]);
            }, $exact_rows);

            return iss_graph_resolver_result('candidates', $candidates[0]['match_type'] ?? 'exact_accepted_alias_match', null, $candidates, (int) ($candidates[0]['confidence'] ?? 85), 'Alias/name matches require review.');
        }

        $search_rows = $service->search_entities($label, [
            'entity_kind' => $entity_kind,
            'limit' => isset($args['limit']) ? (int) $args['limit'] : 10,
        ]);

        if ($search_rows) {
            $candidates = array_map(static function (array $row): array {
                return iss_graph_map_resolver_candidate($row, 'fuzzy_candidate', 50, 'Search candidate.');
            }, $search_rows);

            return iss_graph_resolver_result('candidates', 'fuzzy_candidate', null, $candidates, 50, 'Search candidates require review.');
        }
    }

    return iss_graph_resolver_result('no_match', 'no_match', null, [], 0, 'No safe entity match found.');
}
