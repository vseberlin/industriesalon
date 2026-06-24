<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ISS_Content_Model_Veranstaltungen_CLI_Command
{
    /**
     * Audits legacy Veranstaltung posts against the Phase 1 entity registry.
     *
     * ## OPTIONS
     *
     * [--post=<id-or-slug>]
     * : Limit the audit to one Veranstaltung.
     *
     * [--limit=<number>]
     * : Maximum number of posts to inspect. Default: all.
     *
     * [--format=<format>]
     * : Output format: table or json. Default: table.
     *
     * [--status=<status>]
     * : Limit output to safe, review, blocked, or converted rows.
     *
     * [--entity=<entity-key>]
     * : Limit output to one candidate/current entity key.
     *
     * [--missing-facts]
     * : Limit output to rows that are blocked by missing required facts.
     *
     * ## EXAMPLES
     *
     *     wp iss-content veranstaltungen-dry-run
     *     wp iss-content veranstaltungen-dry-run --format=json
     *     wp iss-content veranstaltungen-dry-run --post=25808
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $errors = iss_content_model_validate_veranstaltung_registry();
        if ($errors) {
            \WP_CLI::error('Veranstaltung registry is invalid: ' . implode(' ', $errors));
        }

        $format = sanitize_key((string) ($assoc_args['format'] ?? 'table'));
        if (!in_array($format, ['table', 'json'], true)) {
            $format = 'table';
        }

        $posts = $this->get_posts($assoc_args);
        $rows = [];
        $summary = [
            'total' => 0,
            'safe' => 0,
            'review' => 0,
            'blocked' => 0,
            'converted' => 0,
        ];

        foreach ($posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $row = $this->audit_post($post);
            if (!$this->row_matches_filters($row, $assoc_args)) {
                continue;
            }

            $rows[] = $row;
            ++$summary['total'];

            if ($row['current_entity'] !== '' && $row['status'] !== 'blocked') {
                ++$summary['converted'];
            }

            if (isset($summary[$row['status']])) {
                ++$summary[$row['status']];
            }
        }

        if ($format === 'json') {
            \WP_CLI::log((string) wp_json_encode([
                'schema_version' => 1,
                'summary' => $summary,
                'items' => $rows,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }

        \WP_CLI::log(sprintf(
            'Veranstaltungen dry-run: total=%d safe=%d review=%d blocked=%d converted=%d',
            $summary['total'],
            $summary['safe'],
            $summary['review'],
            $summary['blocked'],
            $summary['converted']
        ));
        $this->render_table($rows);
    }

    private function row_matches_filters(array $row, array $assoc_args): bool
    {
        $status = sanitize_key((string) ($assoc_args['status'] ?? ''));
        if ($status === 'converted') {
            if ((string) ($row['current_entity'] ?? '') === '') {
                return false;
            }
        } elseif ($status !== '' && $status !== (string) ($row['status'] ?? '')) {
            return false;
        }

        if (isset($assoc_args['entity'])) {
            $entity = iss_content_model_sanitize_veranstaltung_entity_key((string) $assoc_args['entity']);
            if ($entity === '' || ($entity !== (string) ($row['candidate_entity'] ?? '') && $entity !== (string) ($row['current_entity'] ?? ''))) {
                return false;
            }
        }

        if (!empty($assoc_args['missing-facts']) && strpos((string) ($row['notes'] ?? ''), 'missing ') === false) {
            return false;
        }

        return true;
    }

    private function get_posts(array $assoc_args): array
    {
        $post_token = trim((string) ($assoc_args['post'] ?? ''));
        if ($post_token !== '') {
            $post = is_numeric($post_token)
                ? get_post(absint($post_token))
                : get_page_by_path(sanitize_title($post_token), OBJECT, ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE);

            return $post instanceof \WP_Post && $post->post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE ? [$post] : [];
        }

        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : -1;
        if ($limit === 0 || $limit < -1) {
            $limit = -1;
        }

        return get_posts([
            'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => true,
        ]);
    }

    private function audit_post(\WP_Post $post): array
    {
        $post_id = (int) $post->ID;
        $current_entity = iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true));
        $stored_entity = trim((string) get_post_meta($post_id, '_iss_entity_key', true));
        $candidate = [
            'entity_key' => $current_entity,
            'confidence' => $current_entity !== '' ? 'converted' : 'blocked',
            'reason' => $current_entity !== '' ? 'stored entity key is valid' : 'no valid entity key stored',
        ];

        $entity_key = (string) ($candidate['entity_key'] ?? '');
        $notes = [];
        if ($stored_entity !== '' && $current_entity === '') {
            $notes[] = 'stored _iss_entity_key is invalid';
        }

        $missing = $this->missing_required_facts($post, $entity_key);
        foreach ($missing as $fact) {
            $notes[] = 'missing ' . $fact;
        }

        $confidence = (string) ($candidate['confidence'] ?? 'blocked');
        $reason = trim((string) ($candidate['reason'] ?? ''));
        if ($reason !== '') {
            $notes[] = $reason;
        }

        $status = $this->resolve_status($entity_key, $confidence, $missing, $stored_entity, $current_entity);
        $programme_enabled = get_post_meta($post_id, 'iss_programme_enabled', true) === '1' ? 'yes' : 'no';

        return [
            'id' => $post_id,
            'title' => get_the_title($post),
            'status' => $status,
            'current_entity' => $current_entity,
            'candidate_entity' => $entity_key,
            'confidence' => $confidence,
            'start' => trim((string) get_post_meta($post_id, 'iss_start_datetime', true)),
            'end' => trim((string) get_post_meta($post_id, 'iss_end_datetime', true)),
            'programme' => $programme_enabled,
            'notes' => implode('; ', array_values(array_unique(array_filter($notes)))),
            'set_command' => $entity_key !== ''
                ? sprintf('wp iss-content veranstaltungen-set-entity --post=%d --entity=%s', $post_id, $entity_key)
                : '',
        ];
    }

    private function missing_required_facts(\WP_Post $post, string $entity_key): array
    {
        if ($entity_key === '') {
            return ['entity_key'];
        }

        return iss_content_model_veranstaltung_missing_required_facts($post, $entity_key);
    }

    private function resolve_status(string $entity_key, string $confidence, array $missing, string $stored_entity, string $current_entity): string
    {
        if ($entity_key === '' || $missing || ($stored_entity !== '' && $current_entity === '')) {
            return 'blocked';
        }

        return $confidence === 'safe' || $confidence === 'converted' ? 'safe' : 'review';
    }

    private function render_table(array $rows): void
    {
        $columns = ['id', 'title', 'status', 'candidate_entity', 'confidence', 'start', 'end', 'programme', 'notes', 'set_command'];
        $widths = array_fill_keys($columns, 0);

        foreach ($columns as $column) {
            $widths[$column] = strlen($column);
        }

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $widths[$column] = max($widths[$column], strlen((string) ($row[$column] ?? '')));
            }
        }

        $lines = [];
        $header = [];
        foreach ($columns as $column) {
            $header[] = str_pad($column, $widths[$column]);
        }
        $lines[] = implode('  ', $header);

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = str_pad((string) ($row[$column] ?? ''), $widths[$column]);
            }
            $lines[] = implode('  ', $line);
        }

        foreach ($lines as $line) {
            \WP_CLI::log($line);
        }
    }
}

final class ISS_Content_Model_Veranstaltungen_Set_Entity_CLI_Command
{
    /**
     * Sets the semantic Veranstaltung entity key for one post.
     *
     * The command is dry-run by default. Pass --yes to write `_iss_entity_key`
     * and resync occurrences when `iss-occurrences` is available.
     *
     * ## OPTIONS
     *
     * --post=<id-or-slug>
     * : Veranstaltung post ID or slug.
     *
     * --entity=<entity-key>
     * : Entity key from the Veranstaltung registry, for example event.vortrag.
     *
     * [--yes]
     * : Apply the change. Without this flag the command only reports.
     *
     * [--force]
     * : Allow applying when required query facts are missing.
     *
     * ## EXAMPLES
     *
     *     wp iss-content veranstaltungen-set-entity --post=25808 --entity=event.festival
     *     wp iss-content veranstaltungen-set-entity --post=25808 --entity=event.festival --yes
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $post_token = trim((string) ($assoc_args['post'] ?? ''));
        $entity_key = iss_content_model_sanitize_veranstaltung_entity_key((string) ($assoc_args['entity'] ?? ''));
        $apply = !empty($assoc_args['yes']);
        $force = !empty($assoc_args['force']);

        if ($post_token === '') {
            \WP_CLI::error('Provide --post=<id-or-slug>.');
        }
        if ($entity_key === '') {
            \WP_CLI::error('Provide a valid --entity=<entity-key>.');
        }

        $post = $this->get_post($post_token);
        if (!$post instanceof \WP_Post) {
            \WP_CLI::error('Veranstaltung post not found.');
        }

        $missing = $this->missing_required_facts($post, $entity_key);
        if ($missing && $apply && !$force) {
            \WP_CLI::error('Required facts missing: ' . implode(', ', $missing) . '. Use --force only if this is intentional.');
        }

        $post_id = (int) $post->ID;
        $current = iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true));
        $entity = iss_content_model_veranstaltung_entity($entity_key);
        $label = (string) ($entity['label'] ?? $entity_key);

        \WP_CLI::log(sprintf(
            '#%d %s',
            $post_id,
            get_the_title($post)
        ));
        \WP_CLI::log(sprintf('current=%s target=%s label=%s', $current !== '' ? $current : '-', $entity_key, $label));
        if ($missing) {
            \WP_CLI::warning('Required facts missing: ' . implode(', ', $missing));
        }

        if (!$apply) {
            \WP_CLI::log('Dry-run only. Re-run with --yes to apply.');
            return;
        }

        update_post_meta($post_id, '_iss_entity_key', $entity_key);
        if (function_exists('iss_content_model_sync_veranstaltung_normalized_facts')) {
            iss_content_model_sync_veranstaltung_normalized_facts($post_id);
        }

        $synced = 0;
        if (function_exists('iss_occurrences_sync_source')) {
            $synced = (int) iss_occurrences_sync_source($post_id);
        }

        \WP_CLI::success(sprintf('Set _iss_entity_key=%s. Occurrences synced: %d.', $entity_key, $synced));
    }

    private function get_post(string $post_token): ?\WP_Post
    {
        $post = is_numeric($post_token)
            ? get_post(absint($post_token))
            : get_page_by_path(sanitize_title($post_token), OBJECT, ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE);

        return $post instanceof \WP_Post && $post->post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE ? $post : null;
    }

    private function missing_required_facts(\WP_Post $post, string $entity_key): array
    {
        return iss_content_model_veranstaltung_missing_required_facts($post, $entity_key);
    }
}

final class ISS_Content_Model_Veranstaltungen_Registry_Check_CLI_Command
{
    /**
     * Validates the Veranstaltung entity registry.
     *
     * ## EXAMPLES
     *
     *     wp iss-content veranstaltungen-registry-check
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $errors = iss_content_model_validate_veranstaltung_registry();
        if ($errors) {
            foreach ($errors as $error) {
                \WP_CLI::warning($error);
            }
            \WP_CLI::error(sprintf('Veranstaltung registry failed with %d issue(s).', count($errors)));
        }

        $registry = iss_content_model_veranstaltung_registry();
        $entities = (array) ($registry['entities'] ?? []);
        $shapes = (array) ($registry['shapes'] ?? []);
        $field_count = 0;
        foreach (array_keys($entities) as $entity_key) {
            $field_count += count(iss_content_model_veranstaltung_entity_fields((string) $entity_key));
        }

        \WP_CLI::success(sprintf(
            'Veranstaltung registry ok: schema=%d entities=%d shapes=%d fields=%d.',
            (int) ($registry['schema_version'] ?? 0),
            count($entities),
            count($shapes),
            $field_count
        ));
    }
}

final class ISS_Content_Model_Veranstaltungen_Repository_Check_CLI_Command
{
    /**
     * Checks the shape-aware Veranstaltung repository facade.
     *
     * ## EXAMPLES
     *
     *     wp iss-content veranstaltungen-repository-check
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $required_functions = [
            'iss_content_model_veranstaltungen_upcoming',
            'iss_content_model_veranstaltungen_archive',
            'iss_content_model_veranstaltungen_reports',
            'iss_content_model_veranstaltungen_homepage_teasers',
            'iss_content_model_veranstaltungen_related',
        ];

        foreach ($required_functions as $function_name) {
            if (!function_exists($function_name)) {
                \WP_CLI::error(sprintf('Missing repository function: %s.', $function_name));
            }
        }

        $timeline_keys = function_exists('iss_content_model_veranstaltung_entity_keys_for_primary_surface')
            ? iss_content_model_veranstaltung_entity_keys_for_primary_surface('timeline')
            : [];
        $archive_keys = function_exists('iss_content_model_veranstaltung_entity_keys_for_primary_surface')
            ? iss_content_model_veranstaltung_entity_keys_for_primary_surface('archive')
            : [];

        if (!$timeline_keys) {
            \WP_CLI::error('No timeline Veranstaltung entity keys resolved.');
        }
        if (!$archive_keys) {
            \WP_CLI::error('No archive Veranstaltung entity keys resolved.');
        }

        $upcoming = iss_content_model_veranstaltungen_upcoming(3);
        $archive = iss_content_model_veranstaltungen_archive(1, 3);
        $reports = iss_content_model_veranstaltungen_reports(1, 3);
        $teasers = iss_content_model_veranstaltungen_homepage_teasers(3);

        \WP_CLI::success(sprintf(
            'Veranstaltung repository ok: timeline_entities=%d archive_entities=%d upcoming=%d archive=%d reports=%d homepage_teasers=%d.',
            count($timeline_keys),
            count($archive_keys),
            count($upcoming),
            count($archive),
            count($reports),
            count($teasers)
        ));
    }
}

final class ISS_Content_Model_Veranstaltungen_Query_Audit_CLI_Command
{
    private const ALLOWED_PATHS = [
        'plugins/iss-content/includes/veranstaltungen-repository.php',
        'plugins/iss-content/includes/veranstaltungen-cli.php',
    ];

    /**
     * Audits raw Veranstaltung post queries outside the approved repository path.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Maximum number of violations to show. Default: 25.
     *
     * ## EXAMPLES
     *
     *     wp iss-content veranstaltungen-query-audit
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $limit = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : 25;
        $checked = 0;
        $allowed = 0;
        $violations = [];

        foreach ($this->php_files() as $file) {
            ++$checked;
            $relative_path = $this->relative_path($file);
            $source = (string) file_get_contents($file);

            if (!$this->contains_raw_veranstaltung_query($source)) {
                continue;
            }

            if (in_array($relative_path, self::ALLOWED_PATHS, true)) {
                ++$allowed;
                continue;
            }

            $violations[] = $relative_path;
            if (count($violations) >= $limit) {
                break;
            }
        }

        if ($violations) {
            \WP_CLI::error_multi_line(array_map(
                static fn (string $path): string => 'Raw Veranstaltung query outside repository: ' . $path,
                $violations
            ));
            \WP_CLI::error(sprintf('Veranstaltung query audit failed with %d shown violation(s).', count($violations)));
        }

        \WP_CLI::success(sprintf(
            'Veranstaltung query audit passed: checked=%d allowed_raw_queries=%d.',
            $checked,
            $allowed
        ));
    }

    private function php_files(): array
    {
        $roots = [
            trailingslashit(WP_CONTENT_DIR) . 'plugins',
            trailingslashit(get_theme_root()),
        ];
        $files = [];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative_path(string $file): string
    {
        $content_dir = trailingslashit(WP_CONTENT_DIR);
        if (strpos($file, $content_dir) === 0) {
            return substr($file, strlen($content_dir));
        }

        return $file;
    }

    private function contains_raw_veranstaltung_query(string $source): bool
    {
        $patterns = [
            '/new\s+WP_Query\s*\([^;]{0,1600}(?:[\'"]post_type[\'"]\s*=>\s*(?:[\'"]veranstaltung[\'"]|ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE))/s',
            '/get_posts\s*\([^;]{0,1600}(?:[\'"]post_type[\'"]\s*=>\s*(?:[\'"]veranstaltung[\'"]|ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE))/s',
            '/->set\s*\(\s*[\'"]post_type[\'"]\s*,\s*(?:[\'"]veranstaltung[\'"]|ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE)/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $source)) {
                return true;
            }
        }

        return false;
    }
}

final class ISS_Content_Model_Veranstaltungen_Content_Audit_CLI_Command
{
    /**
     * Audits stored structured Veranstaltung content documents.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Maximum number of invalid documents to show. Default: 25.
     *
     * ## EXAMPLES
     *
     *     wp iss-content veranstaltungen-content-audit
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $limit = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : 25;
        $meta_key = function_exists('iss_content_model_veranstaltung_content_meta_key')
            ? iss_content_model_veranstaltung_content_meta_key()
            : '_iss_content_json';
        $posts = get_posts([
            'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- WP-CLI audit intentionally scans stored structured documents.
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        $invalid = [];
        $valid = 0;
        $stored_count = 0;

        foreach ((array) $posts as $post_id) {
            $post_id = (int) $post_id;
            $stored = trim((string) get_post_meta($post_id, $meta_key, true));
            if ($stored === '') {
                continue;
            }
            ++$stored_count;

            $sanitized = function_exists('iss_content_model_sanitize_veranstaltung_content_json')
                ? iss_content_model_sanitize_veranstaltung_content_json($stored)
                : '';
            if ($sanitized === '') {
                $invalid[] = sprintf('#%d %s', $post_id, get_the_title($post_id));
                continue;
            }

            ++$valid;
        }

        if ($invalid) {
            \WP_CLI::error_multi_line(array_slice($invalid, 0, $limit));
            if (count($invalid) > $limit) {
                \WP_CLI::warning(sprintf('%d additional invalid document(s) hidden by --limit=%d.', count($invalid) - $limit, $limit));
            }
            \WP_CLI::error(sprintf('Veranstaltung content audit failed with %d invalid document(s).', count($invalid)));
        }

        \WP_CLI::success(sprintf(
            'Veranstaltung content audit passed: stored=%d valid=%d invalid=0.',
            $stored_count,
            $valid
        ));
    }
}

\WP_CLI::add_command('iss-content veranstaltungen-dry-run', 'ISS_Content_Model_Veranstaltungen_CLI_Command');
\WP_CLI::add_command('iss-content-model veranstaltungen-dry-run', 'ISS_Content_Model_Veranstaltungen_CLI_Command');
\WP_CLI::add_command('iss-content veranstaltungen-set-entity', 'ISS_Content_Model_Veranstaltungen_Set_Entity_CLI_Command');
\WP_CLI::add_command('iss-content-model veranstaltungen-set-entity', 'ISS_Content_Model_Veranstaltungen_Set_Entity_CLI_Command');
\WP_CLI::add_command('iss-content veranstaltungen-registry-check', 'ISS_Content_Model_Veranstaltungen_Registry_Check_CLI_Command');
\WP_CLI::add_command('iss-content-model veranstaltungen-registry-check', 'ISS_Content_Model_Veranstaltungen_Registry_Check_CLI_Command');
\WP_CLI::add_command('iss-content veranstaltungen-repository-check', 'ISS_Content_Model_Veranstaltungen_Repository_Check_CLI_Command');
\WP_CLI::add_command('iss-content-model veranstaltungen-repository-check', 'ISS_Content_Model_Veranstaltungen_Repository_Check_CLI_Command');
\WP_CLI::add_command('iss-content veranstaltungen-query-audit', 'ISS_Content_Model_Veranstaltungen_Query_Audit_CLI_Command');
\WP_CLI::add_command('iss-content-model veranstaltungen-query-audit', 'ISS_Content_Model_Veranstaltungen_Query_Audit_CLI_Command');
\WP_CLI::add_command('iss-content veranstaltungen-content-audit', 'ISS_Content_Model_Veranstaltungen_Content_Audit_CLI_Command');
\WP_CLI::add_command('iss-content-model veranstaltungen-content-audit', 'ISS_Content_Model_Veranstaltungen_Content_Audit_CLI_Command');
