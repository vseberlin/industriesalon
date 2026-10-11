<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Existing graph owner SQL, mechanically extracted.

trait ISS_Graph_Schema
{
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

    public function get_dirty_queue_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'iss_graph_dirty_queue';
    }

    public function maybe_install_schema(): void
    {
        $installed = (string) get_option(ISS_GRAPH_SCHEMA_OPTION, '');
        if ($installed === ISS_GRAPH_SCHEMA_VERSION) {
            return;
        }

        if (!$this->acquire_schema_install_lock()) {
            return;
        }

        try {
            $installed = (string) get_option(ISS_GRAPH_SCHEMA_OPTION, '');
            if ($installed !== ISS_GRAPH_SCHEMA_VERSION) {
                $this->install_schema();
            }
        } finally {
            $this->release_schema_install_lock();
        }
    }

    private function acquire_schema_install_lock(): bool
    {
        $now = time();
        $payload = [
            'created' => $now,
            'expires' => $now + 120,
        ];
        if (add_option('iss_graph_schema_install_lock', $payload, '', 'no')) {
            return true;
        }

        $lock = get_option('iss_graph_schema_install_lock', []);
        if (is_array($lock) && isset($lock['expires']) && (int) $lock['expires'] > $now) {
            return false;
        }

        delete_option('iss_graph_schema_install_lock');

        return add_option('iss_graph_schema_install_lock', $payload, '', 'no');
    }

    private function release_schema_install_lock(): void
    {
        delete_option('iss_graph_schema_install_lock');
    }

    public function install_schema(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        require_once dirname(__DIR__, 2) . '/iss-core/schema.php';

        $statements = $this->get_schema_statements();
        foreach ($statements as $statement) {
            dbDelta($statement);
        }
        iss_core_verify_schema($statements);
        update_option(ISS_GRAPH_SCHEMA_OPTION, ISS_GRAPH_SCHEMA_VERSION, false);
        if ((string) get_option(ISS_GRAPH_SCHEMA_OPTION, '') !== ISS_GRAPH_SCHEMA_VERSION) {
            throw new RuntimeException('Graph schema version could not be recorded.');
        }
    }

    /** Owner declarations also serve read-only verification without duplicating a schema manifest. */
    public function get_schema_statements(): array
    {
        global $wpdb;

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
        $dirty_queue_table = $this->get_dirty_queue_table_name();

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
            edge_key varchar(191) NOT NULL DEFAULT '',
            relation_label varchar(191) NOT NULL DEFAULT '',
            note text NOT NULL,
            weight int(11) NOT NULL DEFAULT 0,
            position int(11) NOT NULL DEFAULT 0,
            is_primary tinyint(1) NOT NULL DEFAULT 0,
            valid_from_year smallint(6) DEFAULT NULL,
            valid_to_year smallint(6) DEFAULT NULL,
            source_system varchar(100) NOT NULL DEFAULT '',
            source_ref varchar(191) NOT NULL DEFAULT '',
            source_field varchar(191) NOT NULL DEFAULT '',
            relation_status varchar(50) NOT NULL DEFAULT 'derived',
            confidence smallint(6) NOT NULL DEFAULT 0,
            is_public tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY from_lookup (from_entity_id),
            KEY to_lookup (to_entity_id),
            KEY family_lookup (from_entity_id, relation_family),
            KEY edge_lookup (edge_key),
            KEY type_lookup (relation_type, relation_role),
            KEY source_lookup (source_system, source_ref),
            KEY provenance_lookup (source_system, source_ref, source_field),
            KEY status_lookup (relation_status),
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

        $dirty_queue_sql = "CREATE TABLE {$dirty_queue_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            target_post_id bigint(20) unsigned NOT NULL,
            target_post_type varchar(100) NOT NULL DEFAULT '',
            reason varchar(191) NOT NULL DEFAULT '',
            source_system varchar(100) NOT NULL DEFAULT '',
            status varchar(50) NOT NULL DEFAULT 'dirty',
            attempts int(11) NOT NULL DEFAULT 0,
            last_error text NOT NULL,
            locked_at datetime DEFAULT NULL,
            last_processed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY target_post (target_post_id),
            KEY status_lookup (status),
            KEY queue_order (status, updated_at),
            KEY post_type_lookup (target_post_type)
        ) {$charset_collate};";

        return [$entity_sql, $name_sql, $identifier_sql, $relation_sql, $search_sql, $person_facts_sql, $organization_facts_sql, $evidence_sql, $editorial_signal_sql, $dirty_queue_sql];
    }

    public function table_exists(string $table_name): bool
    {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        return is_string($found) && $found === $table_name;
    }
}
