<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- This service owns and queries the ISS graph custom tables.

require_once __DIR__ . '/service-schema.php';
require_once __DIR__ . '/service-queue.php';
require_once __DIR__ . '/service-signals.php';
require_once __DIR__ . '/service-identifiers-evidence.php';
require_once __DIR__ . '/service-queries.php';

final class ISS_Graph_Service
{
    use ISS_Graph_Schema;
    use ISS_Graph_Queue;
    use ISS_Graph_Signals;
    use ISS_Graph_Identifiers_Evidence;
    use ISS_Graph_Queries;

    private static ?ISS_Graph_Service $instance = null;

    public static function get_instance(): ISS_Graph_Service
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function normalize_entity_kind(string $entity_kind): string
    {
        return function_exists('iss_graph_normalize_entity_kind_key')
            ? iss_graph_normalize_entity_kind_key($entity_kind)
            : sanitize_key($entity_kind);
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

    public function normalize_relation_status($value, string $source_system = ''): string
    {
        $status = str_replace('_', '-', sanitize_key((string) $value));
        $allowed = [
            'derived' => true,
            'suggested' => true,
            'accepted' => true,
            'manual-pinned' => true,
            'suppressed' => true,
            'deprecated' => true,
            'rejected' => true,
        ];

        if (isset($allowed[$status])) {
            return $status;
        }

        return $this->default_relation_status($source_system);
    }

    public function default_relation_status(string $source_system = ''): string
    {
        $source_system = sanitize_key($source_system);
        if ($source_system !== '' && strpos($source_system, 'admin') !== false) {
            return 'accepted';
        }

        return 'derived';
    }

    public function build_relation_edge_key(
        int $from_entity_id,
        int $to_entity_id,
        string $relation_family,
        string $relation_type,
        string $relation_role,
        string $source_system,
        string $source_ref,
        string $source_field
    ): string {
        $parts = [
            max(0, $from_entity_id),
            max(0, $to_entity_id),
            sanitize_key($relation_family),
            sanitize_key($relation_type),
            sanitize_key($relation_role),
            sanitize_key($source_system),
            sanitize_text_field($source_ref),
            sanitize_text_field($source_field),
        ];

        return 'rel_' . substr(sha1(implode('|', array_map('strval', $parts))), 0, 40);
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
            if ($wpdb->last_error !== '') {
                return null;
            }
        }

        if (!$row && $post_id > 0) {
            $row = $this->find_entity_by_post($entity_kind, $post_id);
            if ($wpdb->last_error !== '') {
                return null;
            }
        }

        if (!$row && $source_system !== '' && $source_id !== '') {
            $row = $this->find_entity_by_source($entity_kind, $source_system, $source_id);
            if ($wpdb->last_error !== '') {
                return null;
            }
        }

        if (!$row && $canonical_slug !== '') {
            $row = $this->find_entity_by_slug($entity_kind, $canonical_slug);
            if ($wpdb->last_error !== '') {
                return null;
            }
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
            $updated = $wpdb->update(
                $this->get_entity_table_name(),
                $db_data,
                ['id' => (int) $row['id']],
                ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s'],
                ['%d']
            );
            if ($updated === false) {
                return null;
            }

            return $this->get_entity_by_id((int) $row['id']);
        }

        $db_data['created_at'] = $timestamp;
        $inserted = $wpdb->insert(
            $this->get_entity_table_name(),
            $db_data,
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s']
        );
        if ($inserted !== 1) {
            return null;
        }

        $insert_id = (int) $wpdb->insert_id;

        return $insert_id > 0 ? $this->get_entity_by_id($insert_id) : null;
    }

    public function find_or_create_named_entity(string $entity_kind, string $name, array $overrides = []): ?array
    {
        return $this->resolve_or_create_named_entity($entity_kind, $name, $overrides);
    }

    public function resolve_or_create_named_entity(string $entity_kind, string $name, array $overrides = [], array $args = []): ?array
    {
        global $wpdb;

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
            if ($wpdb->last_error !== '') {
                return null;
            }
            if ($source_entity) {
                $entity_data['id'] = (int) ($source_entity['id'] ?? 0);
                $entity = $this->upsert_entity($entity_data);

                return $entity ? $this->sync_named_entity_title($entity, $name) : null;
            }

            $identifier = $this->get_identifier_by_namespace_value($source_system, $source_id);
            if ($wpdb->last_error !== '') {
                return null;
            }
            if ($identifier && (string) ($identifier['status'] ?? '') === 'accepted') {
                $identifier_entity = $this->get_entity_by_id((int) ($identifier['entity_id'] ?? 0));
                if ($wpdb->last_error !== '') {
                    return null;
                }
                if ($identifier_entity && (string) ($identifier_entity['entity_kind'] ?? '') === $entity_kind) {
                    return $identifier_entity;
                }
            }
        }

        $existing = $this->find_entity_by_slug($entity_kind, $name_slug);
        if ($wpdb->last_error !== '') {
            return null;
        }
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

    /**
     * Synchronize bridge-owned entities, names, identifiers and relations together.
     * Callbacks must propagate failure and keep source/cache writes outside this scope.
     *
     * @throws RuntimeException When a write or the transaction fails.
     */
    public function synchronize_entity_graph(callable $write): void
    {
        $this->run_atomic_write([
            $this->get_entity_table_name(),
            $this->get_name_table_name(),
            $this->get_identifier_table_name(),
            $this->get_relation_table_name(),
        ], $write, 'Graph entity synchronization');
    }

    /**
     * Keep profile binding metadata and graph identity in one transaction.
     * The bridge owns metadata cache cleanup and deferred search/cache updates.
     */
    public function synchronize_profile_binding(callable $write): void
    {
        global $wpdb;

        $this->run_atomic_write([
            $wpdb->posts,
            $wpdb->postmeta,
            $this->get_entity_table_name(),
            $this->get_name_table_name(),
            $this->get_identifier_table_name(),
        ], $write, 'Graph profile binding');
    }

    /** Keep transcript review sources and evidence together; the bridge defers read models. */
    public function synchronize_video_transcript_review(callable $write): void
    {
        global $wpdb;

        $this->run_atomic_write([
            $wpdb->posts,
            $wpdb->postmeta,
            $this->get_entity_table_name(),
            $this->get_name_table_name(),
            $this->get_identifier_table_name(),
            $this->get_relation_table_name(),
            $this->get_evidence_table_name(),
        ], $write, 'Video transcript review');
    }

    /**
     * Replace the supplied parts of one entity/source projection together.
     * Omitted parts and relation families retain their existing rows.
     *
     * @throws RuntimeException When any component or the transaction fails.
     */
    public function replace_entity_projection(int $entity_id, string $source_system, array $payload): void
    {
        $source_system = sanitize_key($source_system);
        $entity_id = absint($entity_id);
        if ($source_system === '' || $entity_id <= 0) {
            return;
        }

        $source_ref = sanitize_text_field((string) ($payload['source_ref'] ?? ('entity:' . $entity_id)));
        $names = $payload['names'] ?? null;
        $identifiers = $payload['identifiers'] ?? null;
        $evidence_rows = $payload['evidence_refs'] ?? ($payload['evidence'] ?? null);
        $relations = is_array($payload['relations'] ?? null) ? $payload['relations'] : [];
        $is_list = array_keys($relations) === range(0, count($relations) - 1);
        if ($is_list) {
            $grouped = [];
            foreach ($relations as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $family = sanitize_key((string) ($row['relation_family'] ?? ''));
                if ($family !== '') {
                    $grouped[$family][] = $row;
                }
            }
            $relations = $grouped;
        }

        $relation_sets = [];
        foreach ($relations as $relation_family => $rows) {
            $family = sanitize_key((string) $relation_family);
            if (is_array($rows) && $family !== '') {
                // Keep input order even when distinct keys sanitize to one family.
                $relation_sets[] = ['family' => $family, 'rows' => $rows];
            }
        }

        $tables = [];
        if (is_array($names)) {
            $tables[] = $this->get_name_table_name();
        }
        if (is_array($identifiers)) {
            $tables[] = $this->get_identifier_table_name();
        }
        if (is_array($evidence_rows)) {
            $tables[] = $this->get_evidence_table_name();
        }
        if ($relation_sets) {
            $tables[] = $this->get_relation_table_name();
        }
        if (!$tables) {
            return;
        }

        $this->run_atomic_write($tables, function () use ($entity_id, $source_system, $source_ref, $names, $identifiers, $evidence_rows, $relation_sets): void {
            if (is_array($names)) {
                $this->replace_entity_names_for_source($entity_id, $source_system, $names, $source_ref);
            }
            if (is_array($identifiers)) {
                $this->replace_entity_identifiers_for_source($entity_id, $source_system, $identifiers, $source_ref);
            }
            if (is_array($evidence_rows)) {
                $this->replace_entity_evidence_refs_for_source($entity_id, $source_system, $evidence_rows, $source_ref);
            }
            foreach ($relation_sets as $set) {
                $this->replace_entity_relations_for_source($entity_id, $set['family'], $source_system, $set['rows'], $source_ref);
            }
        }, 'Graph entity projection replacement');
    }

    /**
     * Replace one entity/source name set atomically, retaining caller transactions.
     *
     * @throws RuntimeException When a name write or its transaction fails.
     */
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
        $prepared_rows = [];
        $timestamp = current_time('mysql', true);

        foreach (array_values($rows) as $index => $row) {
            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $prepared_rows[] = [
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
            ];
        }

        $table = $this->get_name_table_name();
        $this->run_atomic_write([$table], static function () use ($wpdb, $table, $entity_id, $source_system, $prepared_rows): void {
            $deleted = $wpdb->delete(
                $table,
                [
                    'entity_id' => $entity_id,
                    'source_system' => $source_system,
                ],
                ['%d', '%s']
            );
            if ($deleted === false) {
                throw new RuntimeException('Graph name replacement could not delete the previous rows.');
            }

            foreach ($prepared_rows as $row) {
                $inserted = $wpdb->insert(
                    $table,
                    $row,
                    ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
                );
                if ($inserted !== 1) {
                    throw new RuntimeException('Graph name replacement could not insert a replacement row.');
                }
            }
        }, 'Graph name replacement');
    }

    /**
     * Replace one source/family atomically on the supported MariaDB/InnoDB stack.
     * An existing caller transaction retains ownership through a savepoint.
     *
     * @throws RuntimeException When transaction setup or a write fails.
     */
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

        $prepared_rows = [];
        $timestamp = current_time('mysql', true);

        foreach (array_values($rows) as $index => $row) {
            $to_entity_id = absint($row['to_entity_id'] ?? 0);
            if ($to_entity_id <= 0) {
                continue;
            }

            $relation_type = sanitize_key((string) ($row['relation_type'] ?? ''));
            $relation_role = sanitize_key((string) ($row['relation_role'] ?? ''));
            $source_field = sanitize_text_field((string) ($row['source_field'] ?? ''));
            if ($source_field === '') {
                $source_field = $relation_type !== '' ? $relation_type : $relation_family;
            }
            $source_field = $this->limit_string($source_field, 191);
            $edge_key = sanitize_text_field((string) ($row['edge_key'] ?? ''));
            if ($edge_key === '') {
                $edge_key = $this->build_relation_edge_key(
                    $from_entity_id,
                    $to_entity_id,
                    $relation_family,
                    $relation_type,
                    $relation_role,
                    $source_system,
                    $source_ref,
                    $source_field
                );
            }

            $prepared_rows[] = [
                'from_entity_id' => $from_entity_id,
                'to_entity_id' => $to_entity_id,
                'relation_family' => $relation_family,
                'relation_type' => $relation_type,
                'relation_role' => $relation_role,
                'edge_key' => $this->limit_string($edge_key, 191),
                'relation_label' => sanitize_text_field((string) ($row['relation_label'] ?? '')),
                'note' => sanitize_textarea_field((string) ($row['note'] ?? '')),
                'weight' => (int) ($row['weight'] ?? 0),
                'position' => isset($row['position']) ? (int) $row['position'] : $index,
                'is_primary' => $this->normalize_bool($row['is_primary'] ?? false),
                'valid_from_year' => $this->normalize_year($row['valid_from_year'] ?? null),
                'valid_to_year' => $this->normalize_year($row['valid_to_year'] ?? null),
                'source_system' => $source_system,
                'source_ref' => $source_ref,
                'source_field' => $source_field,
                'relation_status' => $this->normalize_relation_status($row['relation_status'] ?? ($row['status'] ?? ''), $source_system),
                'confidence' => $this->normalize_confidence($row['confidence'] ?? 0),
                'is_public' => $this->normalize_bool($row['is_public'] ?? true),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        $table = $this->get_relation_table_name();
        $this->run_atomic_write([$table], static function () use ($wpdb, $table, $from_entity_id, $relation_family, $source_system, $prepared_rows): void {
            $deleted = $wpdb->delete(
                $table,
                [
                    'from_entity_id' => $from_entity_id,
                    'relation_family' => $relation_family,
                    'source_system' => $source_system,
                ],
                ['%d', '%s', '%s']
            );
            if ($deleted === false) {
                throw new RuntimeException('Graph relation replacement could not delete the previous rows.');
            }

            foreach ($prepared_rows as $row) {
                $inserted = $wpdb->insert(
                    $table,
                    $row,
                    ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
                );
                if ($inserted !== 1) {
                    throw new RuntimeException('Graph relation replacement could not insert a replacement row.');
                }
            }
        }, 'Graph relation replacement');
    }

    /**
     * Own the transaction only when the caller has not already started one.
     * Tables are supplied exclusively by this service's write operations.
     *
     * @throws RuntimeException When the storage contract or transaction fails.
     */
    private function run_atomic_write(array $tables, callable $write, string $operation): void
    {
        global $wpdb;

        $tables = array_values(array_unique($tables));
        $placeholders = implode(', ', array_fill(0, count($tables), '%s'));
        // MariaDB exposes transactions started outside this service. Check every
        // participating table before writing so rollback covers the whole operation.
        $states = $wpdb->get_results($wpdb->prepare(
            "SELECT ENGINE AS engine, @@SESSION.in_transaction AS in_transaction,
                @@SESSION.autocommit AS autocommit
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One %s placeholder is generated above per service-owned table.
            $tables
        ), ARRAY_A);
        if (!$states || count($states) !== count($tables) || array_filter($states, static function (array $state): bool {
            return ($state['engine'] ?? '') !== 'InnoDB' || !isset($state['in_transaction'], $state['autocommit']);
        })) {
            throw new RuntimeException(esc_html($operation . ' requires MariaDB transaction state and InnoDB tables.'));
        }

        $state = $states[0];
        $owns_transaction = (int) $state['in_transaction'] === 0 && (int) $state['autocommit'] === 1;
        $savepoint = wp_unique_id('iss_graph_write_');
        if ($wpdb->query($owns_transaction ? 'START TRANSACTION' : "SAVEPOINT $savepoint") === false) {
            throw new RuntimeException(esc_html($operation . ' could not begin its transaction.'));
        }

        try {
            $write();
            if ($wpdb->query($owns_transaction ? 'COMMIT' : "RELEASE SAVEPOINT $savepoint") === false) {
                throw new RuntimeException(esc_html($operation . ' could not finish its transaction.'));
            }
        } catch (Throwable $error) {
            if ($wpdb->query($owns_transaction ? 'ROLLBACK' : "ROLLBACK TO SAVEPOINT $savepoint") === false) {
                throw new RuntimeException(esc_html($operation . ' failed and rollback could not be confirmed.'), 0, $error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Third argument chains the Throwable; it is not rendered output.
            }
            if (!$owns_transaction && $wpdb->query("RELEASE SAVEPOINT $savepoint") === false) {
                throw new RuntimeException(esc_html($operation . ' rolled back but could not release its savepoint.'), 0, $error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Third argument chains the Throwable; it is not rendered output.
            }
            throw $error;
        }
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

    /** Persist one facts row with checked reads/writes, retaining caller transactions. */
    public function upsert_entity_facts(string $entity_kind, int $entity_id, array $data): array
    {
        $entity_kind = $this->normalize_entity_kind($entity_kind);
        $table_name = $this->get_facts_table_name_for_entity_kind($entity_kind);

        if ($entity_id <= 0 || $table_name === '') {
            return [];
        }

        global $wpdb;

        $result = [];
        $this->run_atomic_write([$this->get_entity_table_name(), $table_name], function () use ($wpdb, $entity_kind, $entity_id, $table_name, $data, &$result): void {
            // Serialize first insertion and updates against the same entity.
            $entity = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->get_entity_table_name()} WHERE id = %d FOR UPDATE", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is supplied by the owning graph service.
                $entity_id
            ), ARRAY_A);
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Graph facts could not read the entity.');
            }
            if (!$entity || ($entity['entity_kind'] ?? '') !== $entity_kind) {
                return;
            }

            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT created_at FROM {$table_name} WHERE entity_id = %d FOR UPDATE", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is selected from the graph-owned facts tables.
                $entity_id
            ), ARRAY_A);
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Graph facts could not read the previous row.');
            }

            $timestamp = current_time('mysql', true);
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

            $db_data['created_at'] = (string) ($row['created_at'] ?? $timestamp);
            $formats[] = '%s';
            $written = $row
                ? $wpdb->update($table_name, $db_data, ['entity_id' => $entity_id], $formats, ['%d'])
                : $wpdb->insert($table_name, $db_data, $formats);
            if ($written === false) {
                throw new RuntimeException('Graph facts could not store the row.');
            }

            $result = $this->get_entity_facts($entity_kind, $entity_id);
            if ($wpdb->last_error !== '' || !$result) {
                throw new RuntimeException('Graph facts could not read the saved row.');
            }
            foreach ($db_data as $field => $value) {
                if ($result[$field] !== $value) {
                    throw new RuntimeException('Graph facts could not verify the saved row.');
                }
            }
        }, 'Graph facts persistence');

        return $result;
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
            $written = $wpdb->update(
                $this->get_search_table_name(),
                $db_data,
                ['target_post_id' => $post_id],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'],
                ['%d']
            );

            if ($written === false) { throw new RuntimeException('Could not update public search projection.'); }
            return $this->get_search_row_by_post($post_id);
        }

        $db_data['created_at'] = $timestamp;
        $written = $wpdb->insert(
            $this->get_search_table_name(),
            $db_data,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        if ($written === false) { throw new RuntimeException('Could not insert public search projection.'); }
        return $this->get_search_row_by_post($post_id);
    }

    public function delete_search_row_by_post(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        global $wpdb;

        if ($wpdb->delete($this->get_search_table_name(), ['target_post_id' => $post_id], ['%d']) === false) {
            throw new RuntimeException('Could not delete public search projection.');
        }
    }

    public function backfill_relation_autonomy_columns(array $args = []): array
    {
        global $wpdb;

        $table = $this->get_relation_table_name();
        $batch_size = max(1, min(5000, absint($args['batch_size'] ?? 500)));
        $dry_run = !empty($args['dry_run']);
        $stats = [
            'dry_run' => $dry_run,
            'selected' => 0,
            'updated' => 0,
            'items' => [],
        ];

        if (!$this->table_exists($table)) {
            $stats['last_error'] = 'relation_table_missing';
            return $stats;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$table}
                WHERE edge_key = ''
                   OR source_field = ''
                   OR relation_status = ''
                   OR (source_system LIKE %s AND relation_status = 'derived')
                ORDER BY id ASC
                LIMIT %d",
                '%' . $wpdb->esc_like('admin') . '%',
                $batch_size
            ),
            ARRAY_A
        );
        $rows = is_array($rows) ? $rows : [];
        $stats['selected'] = count($rows);

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $from_entity_id = (int) ($row['from_entity_id'] ?? 0);
            $to_entity_id = (int) ($row['to_entity_id'] ?? 0);
            $relation_family = sanitize_key((string) ($row['relation_family'] ?? ''));
            $relation_type = sanitize_key((string) ($row['relation_type'] ?? ''));
            $relation_role = sanitize_key((string) ($row['relation_role'] ?? ''));
            $source_system = sanitize_key((string) ($row['source_system'] ?? ''));
            $source_ref = sanitize_text_field((string) ($row['source_ref'] ?? ''));
            $source_field = sanitize_text_field((string) ($row['source_field'] ?? ''));
            if ($source_field === '') {
                $source_field = $relation_type !== '' ? $relation_type : $relation_family;
            }
            $source_field = $this->limit_string($source_field, 191);
            $stored_status = (string) ($row['relation_status'] ?? '');
            $relation_status = strpos($source_system, 'admin') !== false && ($stored_status === '' || $stored_status === 'derived')
                ? 'accepted'
                : $this->normalize_relation_status($stored_status, $source_system);
            $edge_key = sanitize_text_field((string) ($row['edge_key'] ?? ''));
            if ($edge_key === '') {
                $edge_key = $this->build_relation_edge_key(
                    $from_entity_id,
                    $to_entity_id,
                    $relation_family,
                    $relation_type,
                    $relation_role,
                    $source_system,
                    $source_ref,
                    $source_field
                );
            }

            $item = [
                'id' => $id,
                'edge_key' => $edge_key,
                'source_field' => $source_field,
                'relation_status' => $relation_status,
            ];
            $stats['items'][] = $item;

            if ($dry_run || $id <= 0) {
                continue;
            }

            $updated = $wpdb->update(
                $table,
                [
                    'edge_key' => $this->limit_string($edge_key, 191),
                    'source_field' => $source_field,
                    'relation_status' => $relation_status,
                    'updated_at' => current_time('mysql', true),
                ],
                ['id' => $id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
            if ($updated !== false) {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    public function delete_entity_by_post(string $entity_kind, int $post_id): void
    {
        $entity = $this->find_entity_by_post($entity_kind, $post_id);
        if (!$entity) {
            return;
        }

        $this->delete_entity((int) $entity['id']);
    }

    /**
     * Delete the entity and its existing graph dependents as one atomic write.
     *
     * @throws RuntimeException When a delete or its transaction fails.
     */
    public function delete_entity(int $entity_id): void
    {
        global $wpdb;

        if ($entity_id <= 0) {
            return;
        }

        $deletes = [
            [$this->get_relation_table_name(), 'from_entity_id'],
            [$this->get_relation_table_name(), 'to_entity_id'],
            [$this->get_name_table_name(), 'entity_id'],
            [$this->get_identifier_table_name(), 'entity_id'],
            [$this->get_evidence_table_name(), 'entity_id'],
            [$this->get_search_table_name(), 'entity_id'],
            [$this->get_person_facts_table_name(), 'entity_id'],
            [$this->get_organization_facts_table_name(), 'entity_id'],
            [$this->get_entity_table_name(), 'id'],
        ];
        $this->run_atomic_write(array_column($deletes, 0), static function () use ($wpdb, $deletes, $entity_id): void {
            foreach ($deletes as [$table, $column]) {
                if ($wpdb->delete($table, [$column => $entity_id], ['%d']) === false) {
                    throw new RuntimeException('Graph entity deletion could not delete a dependent row set.');
                }
            }
        }, 'Graph entity deletion');
    }
}

require_once __DIR__ . '/facade.php';
