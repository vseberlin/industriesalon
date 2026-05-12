<?php

if (!defined('ABSPATH')) {
    exit;
}

class ISS_WF_Import_Archive_Browser_Service
{
    /** @var array<string, array<int, stdClass>> */
    private array $term_options_cache = [];

    public function get_query(array $state, int $per_page = 18): object
    {
        $state = $this->normalize_state($state);
        return new WP_Query($this->build_query_args($state, $per_page));
    }

    public function get_stats(array $state = []): array
    {
        $state = $this->normalize_state($state);

        $stats_state = $state;
        $stats_state['family'] = '';

        return [
            'total_objects' => $this->count_objects($stats_state, [
                'include_source' => true,
                'include_field' => true,
                'include_family' => false,
                'include_context' => true,
                'include_search' => true,
                'classified_only' => false,
            ]),
            'source_counts' => $this->get_grouped_source_counts($state),
            'classified_objects' => $this->count_objects($stats_state, [
                'include_source' => true,
                'include_field' => true,
                'include_family' => false,
                'include_context' => true,
                'include_search' => true,
                'classified_only' => true,
            ]),
            'family_terms' => count($this->get_term_options(ISS_WF_IMPORT_FAMILY_TAXONOMY)),
        ];
    }

    public function get_term_options(string $taxonomy, int $limit = 0): array
    {
        if (!taxonomy_exists($taxonomy)) {
            return [];
        }

        $cache_key = $taxonomy . '|' . max(0, $limit);
        if (isset($this->term_options_cache[$cache_key])) {
            return $this->term_options_cache[$cache_key];
        }

        global $wpdb;

        $object_table = iss_wf_import_get_object_service()->get_object_table_name();
        $sql = "SELECT t.term_id, t.slug, t.name, COUNT(DISTINCT o.object_post_id) AS count
            FROM {$object_table} o
            INNER JOIN {$wpdb->posts} p ON p.ID = o.object_post_id
            INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE p.post_type = %s
              AND p.post_status = 'publish'
              AND tt.taxonomy = %s
            GROUP BY t.term_id, t.slug, t.name
            HAVING COUNT(DISTINCT o.object_post_id) > 0
            ORDER BY count DESC, t.name ASC";

        $params = [ISS_WF_IMPORT_OBJECT_POST_TYPE, $taxonomy];
        if ($limit > 0) {
            $sql .= ' LIMIT %d';
            $params[] = $limit;
        }

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        $options = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $options[] = (object) [
                'term_id' => absint($row['term_id'] ?? 0),
                'slug' => (string) ($row['slug'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        $this->term_options_cache[$cache_key] = $options;

        return $options;
    }

    public function get_terms_by_slugs(string $taxonomy, array $slugs): array
    {
        $slugs = array_values(array_filter(array_map('sanitize_title', $slugs)));
        if (!$slugs) {
            return [];
        }

        $by_slug = [];
        foreach ($this->get_term_options($taxonomy) as $term) {
            if (isset($term->slug) && is_string($term->slug) && $term->slug !== '') {
                $by_slug[$term->slug] = $term;
            }
        }

        $ordered = [];
        foreach ($slugs as $slug) {
            if (isset($by_slug[$slug])) {
                $ordered[] = $by_slug[$slug];
            }
        }

        return $ordered;
    }

    protected function get_grouped_source_counts(array $state): array
    {
        global $wpdb;

        $object_table = iss_wf_import_get_object_service()->get_object_table_name();
        [$where_sql, $params] = $this->build_where_sql($state, [
            'include_source' => false,
            'include_field' => true,
            'include_family' => true,
            'include_context' => true,
            'include_search' => true,
            'classified_only' => false,
        ]);

        $sql = "SELECT t.slug, COUNT(DISTINCT o.object_post_id) AS count
            FROM {$object_table} o
            INNER JOIN {$wpdb->posts} p ON p.ID = o.object_post_id
            INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE {$where_sql}
              AND tt.taxonomy = %s
            GROUP BY t.slug";

        $rows = $wpdb->get_results($wpdb->prepare(
            $sql,
            array_merge($params, [ISS_WF_IMPORT_SOURCE_TAXONOMY])
        ), ARRAY_A);

        $counts = [];
        foreach ($this->get_term_options(ISS_WF_IMPORT_SOURCE_TAXONOMY) as $term) {
            if (isset($term->slug) && is_string($term->slug) && $term->slug !== '') {
                $counts[$term->slug] = 0;
            }
        }

        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $counts[$slug] = (int) ($row['count'] ?? 0);
        }

        return $counts;
    }

    protected function count_objects(array $state, array $config): int
    {
        global $wpdb;

        $object_table = iss_wf_import_get_object_service()->get_object_table_name();
        [$where_sql, $params] = $this->build_where_sql($state, $config);

        $sql = "SELECT COUNT(DISTINCT o.object_post_id)
            FROM {$object_table} o
            INNER JOIN {$wpdb->posts} p ON p.ID = o.object_post_id
            WHERE {$where_sql}";

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * @return array{0:string,1:array<int|string,string|int>}
     */
    protected function build_where_sql(array $state, array $config): array
    {
        global $wpdb;

        $state = $this->normalize_state($state);
        $config = array_merge([
            'include_source' => true,
            'include_field' => true,
            'include_family' => true,
            'include_context' => true,
            'include_search' => true,
            'classified_only' => false,
        ], $config);

        $where = [
            'p.post_type = %s',
            "p.post_status = 'publish'",
        ];
        $params = [ISS_WF_IMPORT_OBJECT_POST_TYPE];

        $taxonomy_filters = [
            ISS_WF_IMPORT_SOURCE_TAXONOMY => ['enabled' => !empty($config['include_source']), 'slug' => (string) $state['source']],
            ISS_WF_IMPORT_FIELD_TAXONOMY => ['enabled' => !empty($config['include_field']), 'slug' => (string) $state['field']],
            ISS_WF_IMPORT_FAMILY_TAXONOMY => ['enabled' => !empty($config['include_family']), 'slug' => (string) $state['family']],
            ISS_WF_IMPORT_CONTEXT_TAXONOMY => ['enabled' => !empty($config['include_context']), 'slug' => (string) $state['context']],
        ];

        foreach ($taxonomy_filters as $taxonomy => $filter) {
            if (!$filter['enabled'] || $filter['slug'] === '') {
                continue;
            }

            $where[] = $this->build_taxonomy_exists_sql(true);
            $params[] = $taxonomy;
            $params[] = $filter['slug'];
        }

        if (!empty($config['classified_only'])) {
            $where[] = $this->build_taxonomy_exists_sql(false);
            $params[] = ISS_WF_IMPORT_FAMILY_TAXONOMY;
        }

        if (!empty($config['include_search']) && $state['search'] !== '') {
            $like = '%' . $wpdb->esc_like($state['search']) . '%';
            $where[] = '(o.title LIKE %s OR o.summary LIKE %s OR o.description LIKE %s)';
            array_push($params, $like, $like, $like);
        }

        return [implode(' AND ', $where), $params];
    }

    protected function build_taxonomy_exists_sql(bool $with_slug): string
    {
        global $wpdb;

        $sql = "EXISTS (
            SELECT 1
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE tr.object_id = p.ID
              AND tt.taxonomy = %s";

        if ($with_slug) {
            $sql .= ' AND t.slug = %s';
        }

        $sql .= ')';

        return $sql;
    }

    public function build_query_args(array $state, int $per_page): array
    {
        $tax_query = [];

        if ($state['source'] !== '') {
            $tax_query[] = [
                'taxonomy' => ISS_WF_IMPORT_SOURCE_TAXONOMY,
                'field' => 'slug',
                'terms' => [$state['source']],
            ];
        }

        if ($state['field'] !== '') {
            $tax_query[] = [
                'taxonomy' => ISS_WF_IMPORT_FIELD_TAXONOMY,
                'field' => 'slug',
                'terms' => [$state['field']],
            ];
        }

        if ($state['family'] !== '') {
            $tax_query[] = [
                'taxonomy' => ISS_WF_IMPORT_FAMILY_TAXONOMY,
                'field' => 'slug',
                'terms' => [$state['family']],
            ];
        }

        if ($state['context'] !== '') {
            $tax_query[] = [
                'taxonomy' => ISS_WF_IMPORT_CONTEXT_TAXONOMY,
                'field' => 'slug',
                'terms' => [$state['context']],
            ];
        }

        $args = [
            'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => max(1, (int) $state['page']),
            'orderby' => 'date',
            'order' => 'DESC',
            'ignore_sticky_posts' => true,
            'suppress_filters' => true,
        ];

        if ($state['search'] !== '') {
            $args['s'] = $state['search'];
        }

        if ($tax_query) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }

            $args['tax_query'] = $tax_query;
        }

        return $args;
    }

    public function normalize_state(array $state): array
    {
        return [
            'source' => sanitize_title((string) ($state['source'] ?? '')),
            'field' => sanitize_title((string) ($state['field'] ?? '')),
            'family' => sanitize_title((string) ($state['family'] ?? '')),
            'context' => sanitize_title((string) ($state['context'] ?? '')),
            'search' => sanitize_text_field((string) ($state['search'] ?? '')),
            'page' => max(1, (int) ($state['page'] ?? 1)),
        ];
    }
}

function iss_wf_import_get_archive_browser_service(): ISS_WF_Import_Archive_Browser_Service
{
    static $service = null;

    if (!$service instanceof ISS_WF_Import_Archive_Browser_Service) {
        $service = new ISS_WF_Import_Archive_Browser_Service();
    }

    return $service;
}

if (defined('WP_CLI') && WP_CLI) {
    class ISS_WF_Import_Archive_Browser_CLI_Command
    {
        public function verify(): void
        {
            $service = iss_wf_import_get_archive_browser_service();

            $states = [
                'default' => ['page' => 1],
                'source' => ['source' => 'museum-digital', 'page' => 1],
                'search' => ['search' => 'Thyratron', 'page' => 1],
            ];

            $field_terms = get_terms([
                'taxonomy' => ISS_WF_IMPORT_FIELD_TAXONOMY,
                'hide_empty' => true,
                'number' => 1,
                'orderby' => 'count',
                'order' => 'DESC',
            ]);
            if (is_array($field_terms) && !empty($field_terms[0]) && $field_terms[0] instanceof WP_Term) {
                $states['field'] = ['field' => $field_terms[0]->slug, 'page' => 1];
            }

            $family_terms = get_terms([
                'taxonomy' => ISS_WF_IMPORT_FAMILY_TAXONOMY,
                'hide_empty' => true,
                'number' => 1,
                'orderby' => 'count',
                'order' => 'DESC',
            ]);
            if (is_array($family_terms) && !empty($family_terms[0]) && $family_terms[0] instanceof WP_Term) {
                $states['family'] = ['family' => $family_terms[0]->slug, 'page' => 1];
            }

            $context_terms = get_terms([
                'taxonomy' => ISS_WF_IMPORT_CONTEXT_TAXONOMY,
                'hide_empty' => true,
                'number' => 1,
                'orderby' => 'count',
                'order' => 'DESC',
            ]);
            if (is_array($context_terms) && !empty($context_terms[0]) && $context_terms[0] instanceof WP_Term) {
                $states['context'] = ['context' => $context_terms[0]->slug, 'page' => 1];
            }

            $errors = [];

            foreach ([
                [ISS_WF_IMPORT_SOURCE_TAXONOMY, 0],
                [ISS_WF_IMPORT_FIELD_TAXONOMY, 0],
                [ISS_WF_IMPORT_FAMILY_TAXONOMY, 8],
                [ISS_WF_IMPORT_CONTEXT_TAXONOMY, 0],
            ] as [$taxonomy, $limit]) {
                $legacy_terms = $this->get_legacy_term_options($taxonomy, $limit);
                $canonical_terms = $service->get_term_options($taxonomy, $limit);
                $matches = $taxonomy === ISS_WF_IMPORT_SOURCE_TAXONOMY
                    ? ($this->normalize_term_slugs($legacy_terms) === $this->normalize_term_slugs($canonical_terms))
                    : ($this->normalize_term_counts($legacy_terms) === $this->normalize_term_counts($canonical_terms));

                if (!$matches) {
                    $errors[] = sprintf(
                        'Browser term options mismatch for %s (limit=%d).',
                        $taxonomy,
                        $limit
                    );
                }
            }

            foreach ($states as $label => $state) {
                $legacy = $this->run_legacy_query($state, 18);
                $canonical = $service->get_query($state, 18);

                $legacy_ids = array_values(array_map('intval', wp_list_pluck((array) $legacy->posts, 'ID')));
                $canonical_ids = array_values(array_map('intval', wp_list_pluck((array) $canonical->posts, 'ID')));

                if ((int) $legacy->found_posts !== (int) $canonical->found_posts) {
                    $errors[] = sprintf(
                        'Browser state %s count mismatch: legacy=%d canonical=%d.',
                        $label,
                        (int) $legacy->found_posts,
                        (int) $canonical->found_posts
                    );
                    continue;
                }

                if ($legacy_ids !== $canonical_ids) {
                    $errors[] = sprintf('Browser state %s first-page IDs do not match legacy query.', $label);
                }

                if ($this->normalize_stats($this->run_legacy_stats($state)) !== $this->normalize_stats($service->get_stats($state))) {
                    $errors[] = sprintf('Browser state %s stats do not match legacy behavior.', $label);
                }
            }

            if ($errors) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('Archive browser verification failed.');
            }

            \WP_CLI::success(sprintf('Verified %d browser query states against legacy query behavior.', count($states)));
        }

        protected function run_legacy_query(array $state, int $per_page): WP_Query
        {
            $state = iss_wf_import_get_archive_browser_service()->normalize_state($state);
            return new WP_Query(iss_wf_import_get_archive_browser_service()->build_query_args($state, $per_page));
        }

        protected function run_legacy_stats(array $state): array
        {
            $state = iss_wf_import_get_archive_browser_service()->normalize_state($state);
            $base_tax_query = [];

            if ($state['source'] !== '') {
                $base_tax_query[] = [
                    'taxonomy' => ISS_WF_IMPORT_SOURCE_TAXONOMY,
                    'field' => 'slug',
                    'terms' => [$state['source']],
                ];
            }

            if ($state['field'] !== '') {
                $base_tax_query[] = [
                    'taxonomy' => ISS_WF_IMPORT_FIELD_TAXONOMY,
                    'field' => 'slug',
                    'terms' => [$state['field']],
                ];
            }

            if ($state['context'] !== '') {
                $base_tax_query[] = [
                    'taxonomy' => ISS_WF_IMPORT_CONTEXT_TAXONOMY,
                    'field' => 'slug',
                    'terms' => [$state['context']],
                ];
            }

            $base_args = [
                'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => false,
                'ignore_sticky_posts' => true,
                'suppress_filters' => true,
            ];

            if ($state['search'] !== '') {
                $base_args['s'] = $state['search'];
            }

            if ($base_tax_query) {
                if (count($base_tax_query) > 1) {
                    $base_tax_query['relation'] = 'AND';
                }

                $base_args['tax_query'] = $base_tax_query;
            }

            $total_query = new WP_Query($base_args);

            $classified_args = $base_args;
            $classified_tax_query = $base_tax_query;
            $classified_tax_query[] = [
                'taxonomy' => ISS_WF_IMPORT_FAMILY_TAXONOMY,
                'operator' => 'EXISTS',
            ];

            if (count($classified_tax_query) > 1) {
                $classified_tax_query['relation'] = 'AND';
            }

            $classified_args['tax_query'] = $classified_tax_query;
            $classified_query = new WP_Query($classified_args);

            $source_counts = [];
            foreach ($this->get_legacy_term_options(ISS_WF_IMPORT_SOURCE_TAXONOMY) as $term) {
                $source_state = $state;
                $source_state['source'] = (string) $term->slug;
                $source_counts[$term->slug] = (int) $this->run_legacy_query($source_state, 1)->found_posts;
            }

            return [
                'total_objects' => (int) $total_query->found_posts,
                'source_counts' => $source_counts,
                'classified_objects' => (int) $classified_query->found_posts,
                'family_terms' => wp_count_terms([
                    'taxonomy' => ISS_WF_IMPORT_FAMILY_TAXONOMY,
                    'hide_empty' => true,
                ]),
            ];
        }

        protected function get_legacy_term_options(string $taxonomy, int $limit = 0): array
        {
            if (!taxonomy_exists($taxonomy)) {
                return [];
            }

            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
                'orderby' => 'count',
                'order' => 'DESC',
                'number' => $limit > 0 ? $limit : 0,
            ]);

            return is_array($terms) ? array_values(array_filter($terms, static function ($term): bool {
                return $term instanceof WP_Term;
            })) : [];
        }

        protected function normalize_term_counts(array $terms): array
        {
            $normalized = [];
            foreach ($terms as $term) {
                if (!is_object($term) || empty($term->slug)) {
                    continue;
                }

                $normalized[(string) $term->slug] = (int) ($term->count ?? 0);
            }

            ksort($normalized);

            return $normalized;
        }

        protected function normalize_term_slugs(array $terms): array
        {
            $normalized = [];
            foreach ($terms as $term) {
                if (!is_object($term) || empty($term->slug)) {
                    continue;
                }

                $normalized[] = (string) $term->slug;
            }

            sort($normalized);

            return $normalized;
        }

        protected function normalize_stats(array $stats): array
        {
            $normalized = [
                'total_objects' => (int) ($stats['total_objects'] ?? 0),
                'classified_objects' => (int) ($stats['classified_objects'] ?? 0),
                'family_terms' => (int) ($stats['family_terms'] ?? 0),
                'source_counts' => [],
            ];

            foreach ((array) ($stats['source_counts'] ?? []) as $slug => $count) {
                $slug = sanitize_title((string) $slug);
                if ($slug === '') {
                    continue;
                }

                $normalized['source_counts'][$slug] = (int) $count;
            }

            ksort($normalized['source_counts']);

            return $normalized;
        }
    }

    \WP_CLI::add_command('iss-archive browser-verify', ['ISS_WF_Import_Archive_Browser_CLI_Command', 'verify']);
}
