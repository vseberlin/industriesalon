<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- This CLI verifies the ISS occurrence projection tables owned by the plugin service.

if (defined('WP_CLI') && WP_CLI) {
    final class ISS_Occurrences_CLI_Command
    {
        public function verify(array $args, array $assoc_args): void
        {
            $service = iss_occurrences_get_service();
            $service->maybe_install_schema();

            $errors = [];
            if (!$service->tables_exist()) {
                $errors[] = 'Occurrence tables are missing.';
            }

            $public_rows = $service->public_row_count();
            \WP_CLI::log(sprintf('public_occurrences=%d', $public_rows));

            if (!empty($errors)) {
                \WP_CLI::error_multi_line($errors);
                \WP_CLI::error('ISS occurrences verification failed.');
            }

            \WP_CLI::success('ISS occurrences verification passed.');
        }

        public function sync(array $args, array $assoc_args): void
        {
            $source = isset($assoc_args['source']) ? sanitize_key((string) $assoc_args['source']) : 'all';
            $service = iss_occurrences_get_service();
            $service->maybe_install_schema();

            if ($source === 'all') {
                $result = iss_occurrences_sync_all();
                $supersaas = is_array($result['supersaas'] ?? null) ? $result['supersaas'] : [];
                \WP_CLI::success(sprintf(
                    'Synced occurrences: sources=%d supersaas_created=%d supersaas_updated=%d supersaas_unlinked=%d supersaas_inactivated=%d supersaas_backfilled=%d supersaas_errors=%d.',
                    (int) $result['sources'],
                    (int) ($supersaas['created'] ?? 0),
                    (int) ($supersaas['updated'] ?? 0),
                    (int) ($supersaas['skipped_unlinked'] ?? 0),
                    (int) ($supersaas['inactivated'] ?? 0),
                    (int) ($supersaas['metadata_backfilled'] ?? 0),
                    (int) ($supersaas['errors'] ?? 0)
                ));
                return;
            }

            if ($source === 'wp') {
                $count = iss_occurrences_sync_all_sources();
                \WP_CLI::success(sprintf('Synced %d source occurrence(s).', $count));
                return;
            }

            if ($source === 'supersaas') {
                if (!function_exists('iss_supersaas_sync_occurrences')) {
                    \WP_CLI::error('SuperSaaS sync module is unavailable.');
                }
                $result = iss_supersaas_sync_occurrences();
                \WP_CLI::success(sprintf(
                    'Synced SuperSaaS occurrences: created=%d updated=%d unlinked=%d inactivated=%d backfilled=%d errors=%d.',
                    (int) ($result['created'] ?? 0),
                    (int) ($result['updated'] ?? 0),
                    (int) ($result['skipped_unlinked'] ?? 0),
                    (int) ($result['inactivated'] ?? 0),
                    (int) ($result['metadata_backfilled'] ?? 0),
                    (int) ($result['errors'] ?? 0)
                ));
                return;
            }

            if (in_array($source, iss_occurrences_supported_source_post_types(), true)) {
                $ids = get_posts([
                    'post_type' => $source,
                    'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                ]);

                $count = 0;
                foreach ((array) $ids as $post_id) {
                    $count += iss_occurrences_sync_source((int) $post_id);
                }

                \WP_CLI::success(sprintf('Synced %d %s occurrence(s).', $count, $source));
                return;
            }

            \WP_CLI::error('Invalid --source. Use all, wp, supersaas, veranstaltung, ausstellung, or projekt.');
        }

        public function backfill_occurrences(array $args, array $assoc_args): void
        {
            $this->sync($args, ['source' => 'all']);
        }

        public function drift_check(array $args, array $assoc_args): void
        {
            global $wpdb;

            $service = iss_occurrences_get_service();
            $service->maybe_install_schema();
            $table = $service->get_occurrences_table_name();
            $series_table = $service->get_series_table_name();
            $limit = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : 50;
            $errors = [];
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE visibility = %s AND status = %s ORDER BY starts_at ASC, id ASC",
                    'public',
                    'active'
                ),
                ARRAY_A
            );
            $rows = is_array($rows) ? $rows : [];

            $series_rows = $wpdb->get_results("SELECT * FROM {$series_table}", ARRAY_A);
            $series_rows = is_array($series_rows) ? $series_rows : [];
            $series_by_id = [];
            $series_by_key = [];
            $active_series_ids = [];
            foreach ($series_rows as $series_row) {
                $series_id = (int) ($series_row['id'] ?? 0);
                $series_key = isset($series_row['series_key']) ? iss_occurrences_normalize_series_key((string) $series_row['series_key']) : '';
                if ($series_id > 0) {
                    $series_by_id[$series_id] = $series_row;
                }
                if ($series_key !== '') {
                    $series_by_key[$series_key] = $series_row;
                }
            }

            foreach ($rows as $row) {
                $occurrence_id = (int) ($row['id'] ?? 0);
                $source_post_id = (int) ($row['source_post_id'] ?? 0);
                $source_post_type = sanitize_key((string) ($row['source_post_type'] ?? ''));
                $origin = sanitize_key((string) ($row['origin'] ?? ''));
                $kind = sanitize_key((string) ($row['kind'] ?? ''));
                $location_post_id = (int) ($row['location_post_id'] ?? 0);

                $post = $source_post_id > 0 ? get_post($source_post_id) : null;
                if (!$post instanceof WP_Post) {
                    $errors[] = sprintf('#%d has missing source_post_id=%d.', $occurrence_id, $source_post_id);
                    continue;
                }

                if ($post->post_status !== 'publish') {
                    $errors[] = sprintf('#%d points to non-public source #%d status=%s.', $occurrence_id, $source_post_id, $post->post_status);
                }

                if ($source_post_type !== $post->post_type) {
                    $errors[] = sprintf('#%d source type mismatch stored=%s actual=%s.', $occurrence_id, $source_post_type, $post->post_type);
                }

                if ($kind === 'tour' && $post->post_type !== 'fuehrung') {
                    $errors[] = sprintf('#%d tour source is not fuehrung.', $occurrence_id);
                }

                if ($location_post_id > 0 && !(get_post($location_post_id) instanceof WP_Post)) {
                    $errors[] = sprintf('#%d points to missing location_post_id=%d.', $occurrence_id, $location_post_id);
                }

                if ($origin === 'wp'
                    && in_array($source_post_type, iss_occurrences_supported_source_post_types(), true)
                    && !iss_occurrences_source_is_calendar_enabled($source_post_id)
                ) {
                    $errors[] = sprintf('#%d points to source #%d without explicit calendar toggle.', $occurrence_id, $source_post_id);
                }

                if ($origin === 'supersaas') {
                    $series_key = isset($row['series_key']) ? iss_occurrences_normalize_series_key((string) $row['series_key']) : '';
                    $series_id = (int) ($row['series_id'] ?? 0);
                    if ($series_id > 0) {
                        $active_series_ids[$series_id] = true;
                    }
                    if ($series_key === '') {
                        $errors[] = sprintf('#%d SuperSaaS occurrence has no series key.', $occurrence_id);
                    } elseif ($series_id <= 0) {
                        $errors[] = sprintf('#%d SuperSaaS occurrence series %s has no series_id.', $occurrence_id, $series_key);
                    } else {
                        $series_entry = $series_by_id[$series_id] ?? null;
                        if (!is_array($series_entry)) {
                            $errors[] = sprintf('#%d SuperSaaS occurrence has no series row #%d for %s.', $occurrence_id, $series_id, $series_key);
                        } else {
                            $series_row_key = isset($series_entry['series_key']) ? iss_occurrences_normalize_series_key((string) $series_entry['series_key']) : '';
                            $series_source_post_id = (int) ($series_entry['source_post_id'] ?? 0);
                            $series_source_post_type = sanitize_key((string) ($series_entry['source_post_type'] ?? ''));

                            if ($series_row_key !== $series_key) {
                                $errors[] = sprintf('#%d SuperSaaS occurrence series_id=%d key mismatch stored=%s series=%s.', $occurrence_id, $series_id, $series_key, $series_row_key);
                            } elseif ($series_source_post_id <= 0) {
                                $errors[] = sprintf('#%d SuperSaaS occurrence series %s is not linked to a parent post.', $occurrence_id, $series_key);
                            } elseif ($series_source_post_id !== $source_post_id) {
                                $errors[] = sprintf('#%d SuperSaaS occurrence series %s source mismatch stored=%d mapped=%d.', $occurrence_id, $series_key, $source_post_id, $series_source_post_id);
                            } elseif ($series_source_post_type !== '' && $series_source_post_type !== $source_post_type) {
                                $errors[] = sprintf('#%d SuperSaaS occurrence series %s source type mismatch stored=%s mapped=%s.', $occurrence_id, $series_key, $source_post_type, $series_source_post_type);
                            }
                        }
                    }
                }
            }

            foreach ($series_rows as $series_row) {
                $series_key = isset($series_row['series_key']) ? iss_occurrences_normalize_series_key((string) $series_row['series_key']) : '';
                $source_post_id = (int) ($series_row['source_post_id'] ?? 0);
                if ($series_key === '') {
                    $errors[] = sprintf('Occurrence series row #%d has no series key.', (int) ($series_row['id'] ?? 0));
                    continue;
                }
                if ($source_post_id <= 0) {
                    if (!empty($active_series_ids[(int) ($series_row['id'] ?? 0)])) {
                        $errors[] = sprintf('Occurrence series %s is not linked to a source post.', $series_key);
                    }
                    continue;
                }

                $source_post_type = sanitize_key((string) ($series_row['source_post_type'] ?? ''));
                $post = get_post($source_post_id);
                if (!$post instanceof WP_Post) {
                    $errors[] = sprintf('Occurrence series %s points to missing source post #%d.', $series_key, $source_post_id);
                } elseif ($post->post_status === 'trash' || $post->post_status === 'auto-draft') {
                    $errors[] = sprintf('Occurrence series %s points to non-editorial source post #%d status=%s.', $series_key, $source_post_id, $post->post_status);
                } elseif ($source_post_type !== '' && $source_post_type !== $post->post_type) {
                    $errors[] = sprintf('Occurrence series %s source type mismatch stored=%s actual=%s.', $series_key, $source_post_type, $post->post_type);
                }

                if (!isset($series_by_key[$series_key])) {
                    $errors[] = sprintf('Occurrence series %s was not indexed by key.', $series_key);
                }
            }

            $eligible_ids = get_posts([
                'post_type' => iss_occurrences_supported_source_post_types(),
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);

            foreach ((array) $eligible_ids as $post_id) {
                $post_id = (int) $post_id;
                if (!iss_occurrences_source_is_calendar_enabled($post_id)) {
                    continue;
                }

                $count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} WHERE source_post_id = %d AND origin = %s",
                        $post_id,
                        'wp'
                    )
                );
                if ($count <= 0) {
                    $errors[] = sprintf('Enabled source post #%d has no wp occurrence.', $post_id);
                }
            }

            $legacy_posts = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'iss_calendar_item'"
            );
            if ($legacy_posts > 0) {
                $errors[] = sprintf('Legacy iss_calendar_item posts remain: %d.', $legacy_posts);
            }

            $legacy_options = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name IN ('iss_calendar_source_map', 'iss_calendar_series_map', 'iss_calendar_cron_sync') ORDER BY option_name ASC"
            );
            if (is_array($legacy_options) && !empty($legacy_options)) {
                $errors[] = sprintf('Legacy calendar option(s) remain: %s.', implode(', ', $legacy_options));
            }

            $retired_options = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name IN (%s, %s) ORDER BY option_name ASC",
                    ISS_OCCURRENCES_RETIRED_SOURCE_MAP_OPTION,
                    ISS_OCCURRENCES_RETIRED_SERIES_MAP_OPTION
                )
            );
            if (is_array($retired_options) && !empty($retired_options)) {
                $errors[] = sprintf('Retired occurrence option(s) remain after table migration: %s.', implode(', ', $retired_options));
            }

            $legacy_occurrence_rows = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE origin = %s",
                    'legacy'
                )
            );
            if ($legacy_occurrence_rows > 0) {
                $errors[] = sprintf('Legacy occurrence origin rows remain: %d.', $legacy_occurrence_rows);
            }

            $inactive_past_supersaas_rows = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} o INNER JOIN {$wpdb->posts} p ON p.ID = o.source_post_id WHERE o.origin = %s AND o.visibility = %s AND o.status = %s AND o.source_post_type = %s AND o.starts_at < %s AND p.post_type = %s AND p.post_status = %s",
                    'supersaas',
                    'public',
                    'inactive',
                    'fuehrung',
                    current_time('mysql'),
                    'fuehrung',
                    'publish'
                )
            );
            if ($inactive_past_supersaas_rows > 0) {
                $errors[] = sprintf('Inactive past public SuperSaaS Führung occurrence rows remain: %d.', $inactive_past_supersaas_rows);
            }

            $legacy_meta_rows = $wpdb->get_results(
                "SELECT meta_key, COUNT(*) AS row_count FROM {$wpdb->postmeta} WHERE meta_key IN ('iss_timeline_item_id', '_iss_legacy_archive_term_slug', 'iss_archive_term_slug', 'iss_exhibition_source', 'iss_exhibition_type', 'iss_is_permanent') GROUP BY meta_key ORDER BY meta_key ASC",
                ARRAY_A
            );
            foreach ((array) $legacy_meta_rows as $meta_row) {
                $meta_key = isset($meta_row['meta_key']) ? (string) $meta_row['meta_key'] : '';
                $row_count = isset($meta_row['row_count']) ? (int) $meta_row['row_count'] : 0;
                if ($meta_key !== '' && $row_count > 0) {
                    $errors[] = sprintf('Legacy meta remains: %s rows=%d.', $meta_key, $row_count);
                }
            }

            if (!empty($errors)) {
                \WP_CLI::error_multi_line(array_slice($errors, 0, $limit));
                if (count($errors) > $limit) {
                    \WP_CLI::warning(sprintf('%d additional drift issue(s) hidden by --limit=%d.', count($errors) - $limit, $limit));
                }
                \WP_CLI::error(sprintf('ISS occurrences drift check failed with %d issue(s).', count($errors)));
            }

            \WP_CLI::success('ISS occurrences drift check passed.');
        }
    }

    \WP_CLI::add_command('iss-occurrences', 'ISS_Occurrences_CLI_Command');
    \WP_CLI::add_command('iss-occurrences drift-check', ['ISS_Occurrences_CLI_Command', 'drift_check']);
    \WP_CLI::add_command('iss-occurrences backfill-occurrences', ['ISS_Occurrences_CLI_Command', 'backfill_occurrences']);
}
