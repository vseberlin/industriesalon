<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Existing graph owner SQL, mechanically extracted.

trait ISS_Graph_Queue
{
    public function mark_post_dirty(int $post_id, string $reason = '', string $source_system = 'iss_graph'): bool
    {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return false;
        }

        if (!$this->table_exists($this->get_dirty_queue_table_name())) {
            return false;
        }

        global $wpdb;

        $post_type = sanitize_key((string) get_post_type($post_id));
        $reason = $this->limit_string(sanitize_text_field($reason), 191);
        $source_system = sanitize_key($source_system);
        $timestamp = current_time('mysql', true);
        // A new dirty signal revokes an in-flight claim, including same-second
        // saves, and starts a fresh retry budget. The unique post key coalesces it.
        return $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->get_dirty_queue_table_name()}
                (target_post_id, target_post_type, reason, source_system, status, attempts,
                 last_error, locked_at, created_at, updated_at, last_processed_at)
             VALUES (%d, %s, %s, %s, 'dirty', 0, '', NULL, %s, %s, NULL)
             ON DUPLICATE KEY UPDATE target_post_type = VALUES(target_post_type),
                reason = VALUES(reason), source_system = VALUES(source_system),
                status = 'dirty', attempts = 0, last_error = '', locked_at = NULL,
                updated_at = VALUES(updated_at)",
            $post_id,
            $post_type,
            $reason,
            $source_system,
            $timestamp,
            $timestamp
        )) !== false;
    }

    public function get_dirty_queue_stats(): array
    {
        global $wpdb;

        $table = $this->get_dirty_queue_table_name();
        if (!$this->table_exists($table)) {
            return [
                'exists' => false,
                'dirty' => 0,
                'processing' => 0,
                'dead' => 0,
                'clean' => 0,
                'oldest_dirty_at' => '',
                'oldest_dirty_age_seconds' => 0,
                'last_run' => get_option('iss_graph_dirty_queue_last_run', []),
            ];
        }

        $counts = [
            'dirty' => 0,
            'processing' => 0,
            'dead' => 0,
            'clean' => 0,
        ];
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS count FROM {$table} GROUP BY status", ARRAY_A);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $status = sanitize_key((string) ($row['status'] ?? ''));
            if (isset($counts[$status])) {
                $counts[$status] = (int) ($row['count'] ?? 0);
            }
        }

        $oldest_dirty_at = (string) $wpdb->get_var("SELECT MIN(updated_at) FROM {$table} WHERE status = 'dirty'");
        $oldest_age = 0;
        if ($oldest_dirty_at !== '') {
            $oldest_age = max(0, strtotime(current_time('mysql', true) . ' UTC') - strtotime($oldest_dirty_at . ' UTC'));
        }

        return array_merge([
            'exists' => true,
            'oldest_dirty_at' => $oldest_dirty_at,
            'oldest_dirty_age_seconds' => $oldest_age,
            'last_run' => get_option('iss_graph_dirty_queue_last_run', []),
        ], $counts);
    }

    public function drain_dirty_queue(array $args = []): array
    {
        global $wpdb;

        $table = $this->get_dirty_queue_table_name();
        $batch_size = max(1, min(500, absint($args['batch_size'] ?? 50)));
        $max_runtime = max(1, min(900, absint($args['max_runtime'] ?? 30)));
        $max_attempts = max(1, min(20, absint($args['max_attempts'] ?? 3)));
        $dry_run = !empty($args['dry_run']);
        $post_ids = array_key_exists('post_ids', $args)
            ? array_values(array_unique(array_filter(array_map('absint', is_array($args['post_ids']) ? $args['post_ids'] : [])))) : null;
        $started = microtime(true);
        $deadline = $started + $max_runtime;
        $stats = [
            'dry_run' => $dry_run,
            'locked' => false,
            'batch_size' => $batch_size,
            'max_runtime' => $max_runtime,
            'max_attempts' => $max_attempts,
            'selected' => 0,
            'processed' => 0,
            'failed' => 0,
            'dead' => 0,
            'skipped' => 0,
            'recovered' => 0,
            'superseded' => 0,
            'lease_lost' => false,
            'items' => [],
            'duration_seconds' => 0.0,
            'completed_at' => '',
        ];

        if ($post_ids === []) { return $stats; }

        if (!$this->table_exists($table)) {
            $stats['last_error'] = 'dirty_queue_table_missing';
            return $stats;
        }

        $lock = null;
        if (!$dry_run) {
            // Each short claim must commit before reconciliation is called.
            $state = $wpdb->get_row('SELECT @@SESSION.in_transaction AS active, @@SESSION.autocommit AS autocommit', ARRAY_A);
            if (!$state || (int) $state['active'] !== 0 || (int) $state['autocommit'] !== 1) {
                throw new RuntimeException('Graph queue draining requires an independent autocommit connection.');
            }
            $lock = $this->acquire_dirty_queue_lock($max_runtime + 60);
            if ($lock === null) {
                $stats['locked'] = true;
                return $stats;
            }
        }

        try {
            // With our own lease, processing rows belong to abandoned runs.
            // Recovery shares the batch limit instead of using an unbounded sweep.
            $scope = '';
            $selection_values = [$dry_run ? 'dirty' : 'processing'];
            if ($post_ids !== null) {
                $scope = ' AND target_post_id IN (' . implode(', ', array_fill(0, count($post_ids), '%d')) . ')';
                $selection_values = array_merge($selection_values, $post_ids);
            }
            $selection_values[] = $batch_size;
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values include the optional prepared ID scope.
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status IN ('dirty', %s){$scope}
                 ORDER BY updated_at ASC, id ASC LIMIT %d",
                $selection_values
            ), ARRAY_A);
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Could not select graph queue work.');
            }
            $stats['selected'] = count($rows);

            if ($dry_run) {
                foreach ($rows as $row) {
                    $stats['items'][] = [
                        'id' => (int) $row['id'],
                        'post_id' => (int) $row['target_post_id'],
                        'post_type' => (string) $row['target_post_type'],
                        'reason' => (string) $row['reason'],
                        'attempts' => (int) $row['attempts'],
                        'would_reconcile' => (int) $row['attempts'] < $max_attempts,
                    ];
                }
                $stats['duration_seconds'] = round(microtime(true) - $started, 4);
                return $stats;
            }

            foreach ($rows as $row) {
                if (microtime(true) >= $deadline) {
                    $stats['skipped']++;
                    continue;
                }

                $queue_id = (int) $row['id'];
                $post_id = (int) $row['target_post_id'];
                $claim = null;
                $owned = $this->with_dirty_queue_lock($lock, function () use ($wpdb, $table, $queue_id, $max_attempts, &$claim, &$stats): void {
                    $current = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table} WHERE id = %d FOR UPDATE",
                        $queue_id
                    ), ARRAY_A);
                    if ($wpdb->last_error !== '') {
                        throw new RuntimeException('Could not read a graph queue claim.');
                    }
                    if (!$current || !in_array($current['status'], ['dirty', 'processing'], true)) {
                        return;
                    }
                    if ($current['status'] === 'processing') {
                        $stats['recovered']++;
                    }
                    $timestamp = current_time('mysql', true);
                    if ((int) $current['attempts'] >= $max_attempts) {
                        if ($wpdb->update($table, [
                            'status' => 'dead',
                            'locked_at' => null,
                            'last_error' => $current['last_error'] !== '' ? $current['last_error'] : 'max_attempts_exceeded',
                            'updated_at' => $timestamp,
                        ], ['id' => $queue_id]) !== 1) {
                            throw new RuntimeException('Could not retire an exhausted graph queue item.');
                        }
                        $stats['dead']++;
                        return;
                    }
                    $claim = [
                        'status' => 'processing',
                        'attempts' => (int) $current['attempts'] + 1,
                        'locked_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                    if ($wpdb->update($table, $claim, ['id' => $queue_id]) !== 1) {
                        throw new RuntimeException('Could not claim a graph queue item.');
                    }
                });
                if (!$owned) {
                    $stats['lease_lost'] = true;
                    break;
                }
                if ($claim === null) {
                    $stats['skipped']++;
                    continue;
                }

                try {
                    $result = function_exists('iss_graph_reconcile_post')
                        ? iss_graph_reconcile_post($post_id)
                        : ['status' => 'dead', 'message' => 'reconcile_function_missing'];
                    $status = sanitize_key((string) ($result['status'] ?? ''));
                    $message = sanitize_textarea_field((string) ($result['message'] ?? ''));
                } catch (Throwable $throwable) {
                    $status = 'failed';
                    $message = $throwable->getMessage();
                }

                $next_status = $status === 'clean' ? 'clean' : ($claim['attempts'] >= $max_attempts ? 'dead' : 'dirty');
                $finished = 0;
                // A source save can revoke this claim while reconciliation runs;
                // a replacement lease also prevents this worker from finishing it.
                $owned = $this->with_dirty_queue_lock($lock, function () use ($wpdb, $table, $queue_id, $claim, $next_status, $message, &$finished): void {
                    $data = [
                        'status' => $next_status,
                        'last_error' => $next_status === 'clean' ? '' : ($message !== '' ? $message : 'reconcile_failed'),
                        'locked_at' => null,
                        'updated_at' => current_time('mysql', true),
                    ];
                    if ($next_status === 'clean') {
                        $data['last_processed_at'] = current_time('mysql', true);
                    }
                    $finished = $wpdb->update($table, $data, [
                        'id' => $queue_id,
                        'status' => 'processing',
                        'attempts' => $claim['attempts'],
                        'locked_at' => $claim['locked_at'],
                    ]);
                    if ($finished === false) {
                        throw new RuntimeException('Could not finish a graph queue item.');
                    }
                });
                if (!$owned) {
                    $stats['lease_lost'] = true;
                    break;
                }
                if ($finished === 0) {
                    $stats['superseded']++;
                    $stats['items'][] = ['post_id' => $post_id, 'status' => 'superseded'];
                } elseif ($next_status === 'clean') {
                    $stats['processed']++;
                    $stats['items'][] = ['post_id' => $post_id, 'status' => 'clean'];
                } else {
                    $stats['failed']++;
                    $stats['dead'] += $next_status === 'dead' ? 1 : 0;
                    $stats['items'][] = ['post_id' => $post_id, 'status' => $next_status, 'message' => $message];
                }
            }

            $stats['duration_seconds'] = round(microtime(true) - $started, 4);
            $stats['completed_at'] = current_time('mysql', true);
            if (!$stats['lease_lost']) {
                $stats['lease_lost'] = !$this->with_dirty_queue_lock($lock, static function () use ($wpdb, $stats): void {
                    // Publish run status under the lease; cache only after commit.
                    if ($wpdb->query($wpdb->prepare(
                        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')
                         ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'no'",
                        'iss_graph_dirty_queue_last_run',
                        maybe_serialize($stats)
                    )) === false) {
                        throw new RuntimeException('Could not record the graph queue run.');
                    }
                });
                wp_cache_delete('iss_graph_dirty_queue_last_run', 'options');
                wp_cache_delete('notoptions', 'options');
                wp_cache_delete('alloptions', 'options');
            }

            return $stats;
        } finally {
            if ($lock !== null) {
                $this->release_dirty_queue_lock($lock);
            }
        }
    }

    /** Protect short queue state changes with the current lease, never reconciliation. */
    private function with_dirty_queue_lock(string $lock, callable $write): bool
    {
        global $wpdb;

        $owned = false;
        $expires = (int) (maybe_unserialize($lock)['expires'] ?? 0);
        $this->run_atomic_write([$wpdb->options, $this->get_dirty_queue_table_name()], static function () use ($wpdb, $lock, $expires, $write, &$owned): void {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
                'iss_graph_dirty_queue_lock'
            ), ARRAY_A);
            if ($wpdb->last_error !== '') {
                throw new RuntimeException('Could not verify graph queue lock ownership.');
            }
            if ($expires <= time() || !$row || $row['option_value'] !== $lock) {
                return;
            }
            $write();
            $owned = true;
        }, 'Graph queue state change');

        return $owned;
    }

    private function acquire_dirty_queue_lock(int $ttl): ?string
    {
        global $wpdb;

        // Read the database directly: cached options cannot arbitrate workers.
        $option = 'iss_graph_dirty_queue_lock';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $option
        ), ARRAY_A);
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Could not read the graph dirty queue lock.');
        }

        $previous = $row['option_value'] ?? null;
        $now = time();
        $lock = maybe_unserialize($previous);
        if (is_array($lock) && isset($lock['expires']) && (int) $lock['expires'] > $now) {
            return null;
        }

        $value = maybe_serialize([
            'token' => wp_generate_uuid4(),
            'created' => $now,
            'expires' => $now + max(60, $ttl),
        ]);
        // The unique option name arbitrates first acquisition; exact-value CAS
        // prevents a stale reader from overwriting a replacement lease.
        if ($previous === null) {
            $changed = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                $option,
                $value
            ));
        } else {
            $changed = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no'
                WHERE option_name = %s AND BINARY option_value = BINARY %s",
                $value,
                $option,
                $previous
            ));
        }
        if ($changed === false) {
            throw new RuntimeException('Could not acquire the graph dirty queue lock.');
        }

        wp_cache_delete($option, 'options');
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete('alloptions', 'options');

        return $changed === 1 ? $value : null;
    }

    private function release_dirty_queue_lock(string $lock): void
    {
        global $wpdb;

        $option = 'iss_graph_dirty_queue_lock';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = BINARY %s",
            $option,
            $lock
        ));
        if ($deleted === false) {
            throw new RuntimeException('Could not release the graph dirty queue lock.');
        }

        wp_cache_delete($option, 'options');
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete('alloptions', 'options');
    }
}
