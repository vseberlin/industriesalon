<?php
if (!defined('ABSPATH')) exit;

function iss_programm_ausstellungen_filter_options(): array
{
    return [
        'aktuell' => __('Aktuell', 'iss-programm'),
        'dauer' => __('Dauer', 'iss-programm'),
        'digital' => __('Digital', 'iss-programm'),
        'archiv' => __('Archiv', 'iss-programm'),
    ];
}

function iss_programm_ausstellungen_normalize_filter(string $filter): string
{
    $filter = sanitize_key($filter);
    return array_key_exists($filter, iss_programm_ausstellungen_filter_options()) ? $filter : 'aktuell';
}

function iss_programm_ausstellungen_today(): string
{
    return wp_date('Y-m-d');
}

function iss_programm_ausstellungen_visibility_meta_query(): array
{
    return [
        'key' => 'iss_timeline_enabled',
        'value' => '1',
        'compare' => '=',
    ];
}

function iss_programm_ausstellungen_type_tax_query(array $slugs, string $operator = 'IN'): array
{
    $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', $slugs))));
    if (empty($slugs) || !taxonomy_exists('ausstellung_typ')) {
        return [];
    }

    return [
        'taxonomy' => 'ausstellung_typ',
        'field' => 'slug',
        'terms' => $slugs,
        'operator' => $operator,
    ];
}

function iss_programm_ausstellungen_period_meta_query(string $filter): array
{
    $today = iss_programm_ausstellungen_today();

    if ($filter === 'archiv') {
        return [
            'key' => 'iss_end_date',
            'value' => $today,
            'compare' => '<',
            'type' => 'DATE',
        ];
    }

    return [
        'relation' => 'AND',
        [
            'key' => 'iss_start_date',
            'value' => $today,
            'compare' => '<=',
            'type' => 'DATE',
        ],
        [
            'relation' => 'OR',
            [
                'key' => 'iss_end_date',
                'value' => $today,
                'compare' => '>=',
                'type' => 'DATE',
            ],
            [
                'key' => 'iss_end_date',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => 'iss_end_date',
                'value' => '',
                'compare' => '=',
            ],
        ],
    ];
}

function iss_programm_ausstellungen_query_args(string $filter, int $limit): array
{
    $filter = iss_programm_ausstellungen_normalize_filter($filter);
    $limit = $limit > 0 ? min($limit, 24) : 6;
    $date_order = $filter === 'archiv' ? 'DESC' : 'ASC';

    $args = [
        'post_type' => 'ausstellung',
        'post_status' => 'publish',
        'posts_per_page' => $filter === 'aktuell' ? 100 : $limit,
        'no_found_rows' => true,
        'orderby' => 'date',
        'order' => $date_order,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Scoped public Ausstellung visibility filter over a small editorial CPT.
        'meta_query' => [
            'relation' => 'AND',
            iss_programm_ausstellungen_visibility_meta_query(),
        ],
    ];

    $dauer_slug = 'dauerausstellung';
    $digital_slug = 'digitaleausstellungen';

    if ($filter === 'dauer') {
        $tax = iss_programm_ausstellungen_type_tax_query([$dauer_slug]);
        if (!empty($tax)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Ausstellung type is the editor-owned availability facet for this small CPT browser.
            $args['tax_query'] = [$tax];
        }
        return $args;
    }

    if ($filter === 'digital') {
        $tax = iss_programm_ausstellungen_type_tax_query([$digital_slug]);
        if (!empty($tax)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Ausstellung type is the editor-owned availability facet for this small CPT browser.
            $args['tax_query'] = [$tax];
        }
        return $args;
    }

    if ($filter === 'archiv') {
        $args['meta_query'][] = iss_programm_ausstellungen_period_meta_query('archiv');
        $tax = iss_programm_ausstellungen_type_tax_query([$dauer_slug, $digital_slug], 'NOT IN');
        if (!empty($tax)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Archive excludes availability-only Ausstellung types on the editor-owned taxonomy.
            $args['tax_query'] = [$tax];
        }
        return $args;
    }

    return $args;
}

function iss_programm_get_ausstellungen_for_filter(string $filter, int $limit): array
{
    $filter = iss_programm_ausstellungen_normalize_filter($filter);
    $limit = $limit > 0 ? min($limit, 24) : 6;
    $query = new WP_Query(iss_programm_ausstellungen_query_args($filter, $limit));
    $posts = array_values(array_filter($query->posts, static function ($post): bool {
        return $post instanceof WP_Post;
    }));

    if ($filter !== 'aktuell') {
        return $posts;
    }

    $posts = array_values(array_filter($posts, 'iss_programm_ausstellung_is_currently_available'));
    return array_slice($posts, 0, $limit);
}

function iss_programm_ausstellung_has_type(int $post_id, array $slugs): bool
{
    if ($post_id <= 0 || !taxonomy_exists('ausstellung_typ')) {
        return false;
    }

    $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', $slugs))));
    if (empty($slugs)) {
        return false;
    }

    $type_slugs = wp_get_post_terms($post_id, 'ausstellung_typ', ['fields' => 'slugs']);
    if (is_wp_error($type_slugs) || empty($type_slugs)) {
        return false;
    }

    foreach ((array) $type_slugs as $slug) {
        if (in_array(sanitize_title((string) $slug), $slugs, true)) {
            return true;
        }
    }

    return false;
}

function iss_programm_ausstellung_is_currently_available(WP_Post $post): bool
{
    $post_id = (int) $post->ID;
    if (iss_programm_ausstellung_has_type($post_id, ['dauerausstellung', 'digitaleausstellungen'])) {
        return true;
    }

    $today = iss_programm_ausstellungen_today();
    $start = trim((string) get_post_meta($post_id, 'iss_start_date', true));
    $end = trim((string) get_post_meta($post_id, 'iss_end_date', true));

    if ($start === '' || $start > $today) {
        return false;
    }

    return $end === '' || $end >= $today;
}

function iss_programm_ausstellungen_get_period_label(int $post_id): string
{
    $start = trim((string) get_post_meta($post_id, 'iss_start_date', true));
    $end = trim((string) get_post_meta($post_id, 'iss_end_date', true));

    if ($start === '' && $end === '') {
        return '';
    }

    $format = static function (string $date): string {
        if ($date === '') {
            return '';
        }
        $timestamp = strtotime($date);
        return $timestamp ? wp_date('j. F Y', $timestamp) : $date;
    };

    if ($start !== '' && $end !== '') {
        return $format($start) . ' - ' . $format($end);
    }

    if ($start !== '') {
        return sprintf(__('seit %s', 'iss-programm'), $format($start));
    }

    return sprintf(__('bis %s', 'iss-programm'), $format($end));
}

function iss_programm_ausstellungen_get_type_label(int $post_id): string
{
    if (!taxonomy_exists('ausstellung_typ')) {
        return '';
    }

    $terms = wp_get_post_terms($post_id, 'ausstellung_typ', ['fields' => 'names']);
    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }

    return trim((string) reset($terms));
}

function iss_programm_render_ausstellungen_cards(array $posts, array $opts): string
{
    if (empty($posts)) {
        return '<p class="iss-timeline__empty">' . esc_html__('Keine Ausstellungen gefunden.', 'iss-programm') . '</p>';
    }

    $show_meta = !array_key_exists('showMeta', $opts) || (bool) $opts['showMeta'];
    $show_summary = !array_key_exists('showSummary', $opts) || (bool) $opts['showSummary'];
    $details_label = trim((string) ($opts['detailsButtonText'] ?? ''));
    if ($details_label === '') {
        $details_label = __('Mehr', 'iss-programm');
    }

    $out = '<div class="iss-card-grid iss-timeline-cards iss-ausstellungen-browser__cards">';
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $post_id = (int) $post->ID;
        $permalink = get_permalink($post_id);
        $permalink = is_string($permalink) ? $permalink : '';
        $type_label = iss_programm_ausstellungen_get_type_label($post_id);
        $period_label = iss_programm_ausstellungen_get_period_label($post_id);

        $out .= '<article class="iss-card iss-card--flat iss-card--media-wide iss-timeline-card iss-ausstellungen-browser__card">';
        if (has_post_thumbnail($post_id)) {
            $out .= '<figure class="iss-card__media iss-timeline-card__media">';
            if ($permalink !== '') {
                $out .= '<a href="' . esc_url($permalink) . '">';
            }
            $out .= get_the_post_thumbnail($post_id, 'large');
            if ($permalink !== '') {
                $out .= '</a>';
            }
            $out .= '</figure>';
        }

        $out .= '<div class="iss-card__body">';
        if ($type_label !== '') {
            $out .= '<p class="iss-kicker iss-kicker--compact iss-timeline-card__kicker">' . esc_html($type_label) . '</p>';
        }
        $out .= '<h3 class="iss-card__title iss-timeline-card__title">';
        if ($permalink !== '') {
            $out .= '<a href="' . esc_url($permalink) . '">' . esc_html(get_the_title($post_id)) . '</a>';
        } else {
            $out .= esc_html(get_the_title($post_id));
        }
        $out .= '</h3>';
        if ($show_meta && $period_label !== '') {
            $out .= '<p class="iss-card__meta iss-timeline-card__meta">' . esc_html($period_label) . '</p>';
        }
        if ($show_summary) {
            $summary = has_excerpt($post_id)
                ? get_the_excerpt($post_id)
                : (function_exists('iss_timeline_extract_teaser_text') ? iss_timeline_extract_teaser_text($post_id, 24) : '');
            $summary = trim(wp_strip_all_tags((string) $summary));
            if ($summary !== '') {
                $out .= '<p class="iss-card__text iss-timeline-card__text">' . esc_html($summary) . '</p>';
            }
        }
        if ($permalink !== '') {
            $out .= '<div class="iss-card__footer"><a class="iss-card__link" href="' . esc_url($permalink) . '">' . esc_html($details_label) . '</a></div>';
        }
        $out .= '</div></article>';
    }
    $out .= '</div>';

    return $out;
}

function iss_programm_render_ausstellungen_filter_nav(string $active_filter): string
{
    $options = iss_programm_ausstellungen_filter_options();
    $out = '<nav class="iss-timeline__presets iss-ausstellungen-browser__filters" aria-label="' . esc_attr__('Ausstellungen filtern', 'iss-programm') . '">';
    foreach ($options as $filter => $label) {
        $url = add_query_arg('ausstellung_filter', $filter);
        $url = strtok($url, '#') . '#aktuell-archiv';
        $classes = 'iss-timeline__preset';
        if ($filter === $active_filter) {
            $classes .= ' is-active';
        }
        $out .= '<a class="' . esc_attr($classes) . '" href="' . esc_url($url) . '" aria-pressed="' . esc_attr($filter === $active_filter ? 'true' : 'false') . '">' . esc_html($label) . '</a>';
    }
    $out .= '</nav>';

    return $out;
}

function iss_programm_render_ausstellungen_browser_block($attributes = []): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    if (function_exists('iss_programm_enqueue_timeline_assets')) {
        iss_programm_enqueue_timeline_assets();
    }

    $default_filter = iss_programm_ausstellungen_normalize_filter((string) ($attributes['defaultFilter'] ?? 'aktuell'));
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only browser filter.
    $request_filter = isset($_GET['ausstellung_filter']) ? sanitize_key((string) wp_unslash($_GET['ausstellung_filter'])) : '';
    $active_filter = $request_filter !== '' ? iss_programm_ausstellungen_normalize_filter($request_filter) : $default_filter;
    $limit = isset($attributes['limit']) ? (int) $attributes['limit'] : 6;
    $title = trim((string) ($attributes['title'] ?? ''));
    $posts = iss_programm_get_ausstellungen_for_filter($active_filter, $limit);

    $classes = 'iss-timeline-query iss-ausstellungen-browser';
    $attrs = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => $classes])
        : 'class="' . esc_attr($classes) . '"';

    $out = '<div ' . $attrs . '>';
    if ($title !== '') {
        $out .= '<h3 class="iss-timeline__section-title iss-ausstellungen-browser__title">' . esc_html($title) . '</h3>';
    }
    if (!array_key_exists('showFilters', $attributes) || (bool) $attributes['showFilters']) {
        $out .= iss_programm_render_ausstellungen_filter_nav($active_filter);
    }
    $out .= iss_programm_render_ausstellungen_cards($posts, [
        'showMeta' => !array_key_exists('showMeta', $attributes) || (bool) $attributes['showMeta'],
        'showSummary' => !array_key_exists('showSummary', $attributes) || (bool) $attributes['showSummary'],
        'detailsButtonText' => (string) ($attributes['detailsButtonText'] ?? ''),
    ]);
    $out .= '</div>';

    return $out;
}

function iss_programm_ausstellungen_audit_findings(): array
{
    $findings = [];
    $visible_ids = get_posts([
        'post_type' => 'ausstellung',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI audit over small editorial Ausstellung CPT.
        'meta_query' => [
            iss_programm_ausstellungen_visibility_meta_query(),
        ],
    ]);

    foreach ((array) $visible_ids as $post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            continue;
        }

        $title = get_the_title($post_id);
        $has_type = taxonomy_exists('ausstellung_typ') && has_term('', 'ausstellung_typ', $post_id);
        if (!$has_type) {
            $findings[] = [
                'severity' => 'warning',
                'code' => 'visible_without_type',
                'post_id' => $post_id,
                'title' => $title,
                'message' => 'Visible Ausstellung has no ausstellung_typ term.',
            ];
        }

        $is_availability_only = iss_programm_ausstellung_has_type($post_id, ['dauerausstellung', 'digitaleausstellungen']);
        $start = trim((string) get_post_meta($post_id, 'iss_start_date', true));
        $end = trim((string) get_post_meta($post_id, 'iss_end_date', true));
        if (!$is_availability_only && $start !== '' && $end === '') {
            $findings[] = [
                'severity' => 'warning',
                'code' => 'temporary_without_end_date',
                'post_id' => $post_id,
                'title' => $title,
                'message' => 'Temporary Ausstellung has a start date but no end date; it will remain current, not archived.',
            ];
        }
    }

    return array_merge($findings, iss_programm_ausstellungen_audit_occurrence_findings());
}

function iss_programm_ausstellungen_audit_occurrence_findings(): array
{
    global $wpdb;

    if (!function_exists('iss_occurrences_get_service')) {
        return [];
    }

    $service = iss_occurrences_get_service();
    if (!method_exists($service, 'tables_exist') || !$service->tables_exist()) {
        return [];
    }

    $table = $service->get_occurrences_table_name();
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only CLI audit over service-owned occurrence table and WordPress taxonomy tables.
    $rows = $wpdb->get_results(
        "SELECT o.id, o.source_post_id, p.post_title, t.slug
        FROM {$table} o
        INNER JOIN {$wpdb->posts} p ON p.ID = o.source_post_id
        INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
        INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
        WHERE o.origin = 'wp'
          AND o.source_post_type = 'ausstellung'
          AND tt.taxonomy = 'ausstellung_typ'
          AND t.slug IN ('dauerausstellung', 'digitaleausstellungen')
        ORDER BY o.id ASC",
        ARRAY_A
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    $findings = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $findings[] = [
            'severity' => 'error',
            'code' => 'availability_type_has_occurrence',
            'post_id' => (int) ($row['source_post_id'] ?? 0),
            'title' => (string) ($row['post_title'] ?? ''),
            'message' => sprintf(
                'Availability-only Ausstellung type %s has occurrence row #%d.',
                (string) ($row['slug'] ?? ''),
                (int) ($row['id'] ?? 0)
            ),
        ];
    }

    return $findings;
}

if (defined('WP_CLI') && WP_CLI) {
    final class ISS_Programm_CLI_Command
    {
        public function ausstellungen_audit(array $args, array $assoc_args): void
        {
            $strict = !empty($assoc_args['strict']);
            $format = isset($assoc_args['format']) ? sanitize_key((string) $assoc_args['format']) : 'table';
            $findings = iss_programm_ausstellungen_audit_findings();

            if ($format === 'json') {
                \WP_CLI::log((string) wp_json_encode($findings));
            } elseif (!empty($findings)) {
                \WP_CLI::log('severity	code	post_id	title	message');
                foreach ($findings as $finding) {
                    \WP_CLI::log(implode("\t", [
                        (string) ($finding['severity'] ?? ''),
                        (string) ($finding['code'] ?? ''),
                        (string) ($finding['post_id'] ?? ''),
                        (string) ($finding['title'] ?? ''),
                        (string) ($finding['message'] ?? ''),
                    ]));
                }
            }

            $errors = array_values(array_filter($findings, static function (array $finding) use ($strict): bool {
                if (($finding['severity'] ?? '') === 'error') {
                    return true;
                }

                return $strict && ($finding['severity'] ?? '') === 'warning';
            }));

            if (!empty($errors)) {
                \WP_CLI::error(sprintf('Ausstellung availability audit found %d blocking issue(s).', count($errors)));
            }

            \WP_CLI::success(sprintf(
                'Ausstellung availability audit passed with %d warning(s).',
                count($findings)
            ));
        }
    }

    \WP_CLI::add_command('iss-programm ausstellungen-audit', ['ISS_Programm_CLI_Command', 'ausstellungen_audit']);
}
