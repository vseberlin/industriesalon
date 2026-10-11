<?php
/** Monitoring for existing owner jobs. Scheduling remains with WP-Cron/host cron. */
if (!defined('ABSPATH')) { exit; }

function iss_core_maintenance_jobs(): array
{
    return [
        'supersaas' => ['hook' => 'iss_supersaas_occurrence_sync', 'max_age' => 2 * HOUR_IN_SECONDS],
        'fallback' => ['hook' => 'iss_content_fallback_projection_sweep', 'max_age' => 26 * HOUR_IN_SECONDS],
        'set_decay' => ['hook' => 'iss_content_editorial_sets_decay', 'max_age' => 26 * HOUR_IN_SECONDS],
    ];
}

function iss_core_store_job_receipt(string $job, array $receipt): void
{
    $option = 'iss_maintenance_' . $job;
    update_option($option, $receipt, false);
    if (get_option($option) !== $receipt) {
        throw new RuntimeException('Could not persist maintenance receipt.');
    }
}

/** Run an existing owner callback; preserve its last success across failures. */
function iss_core_run_maintenance_job(string $job, callable $run)
{
    if (!isset(iss_core_maintenance_jobs()[$job])) { throw new InvalidArgumentException('Unknown maintenance owner.'); }
    $previous = get_option('iss_maintenance_' . $job, []);
    $receipt = [
        'started_at' => time(), 'finished_at' => 0, 'status' => 'running',
        'last_success_at' => (int) ($previous['last_success_at'] ?? 0), 'error' => '',
    ];
    // If monitoring cannot persist, do not start an unobservable job.
    iss_core_store_job_receipt($job, $receipt);
    try {
        $result = $run();
        $message = '';
        if (is_wp_error($result)) { $message = $result->get_error_message(); }
        elseif ($result === false) { $message = 'Owner reported failure.'; }
        elseif (is_array($result) && (!empty($result['errors']) || !empty($result['error_message']))) {
            $message = !empty($result['error_message']) ? (string) $result['error_message'] : 'Owner reported incomplete work; inspect its diagnostics.';
        }
        if ($message !== '') { throw new RuntimeException(esc_html($message)); }
        $receipt['status'] = 'ok';
        $receipt['finished_at'] = time();
        $receipt['last_success_at'] = $receipt['finished_at'];
        iss_core_store_job_receipt($job, $receipt);
        return $result;
    } catch (Throwable $error) {
        $receipt['status'] = 'failed';
        $receipt['last_success_at'] = (int) ($previous['last_success_at'] ?? 0);
        $receipt['finished_at'] = time();
        $receipt['error'] = substr(sanitize_text_field($error->getMessage()), 0, 500);
        iss_core_store_job_receipt($job, $receipt);
        throw $error;
    }
}

function iss_core_maintenance_status(?int $now = null): array
{
    $now = $now ?? time();
    $rows = [];
    foreach (iss_core_maintenance_jobs() as $job => $config) {
        if (!has_action($config['hook'])) { continue; }
        $receipt = get_option('iss_maintenance_' . $job, []);
        $success = (int) ($receipt['last_success_at'] ?? 0);
        $state = (string) ($receipt['status'] ?? 'never');
        $age = $success > 0 ? max(0, $now - $success) : null;
        $next = wp_next_scheduled($config['hook']);
        $running = $state === 'running' && (int) ($receipt['started_at'] ?? 0) > $now - 15 * MINUTE_IN_SECONDS;
        $ok = ($state === 'ok' || $running) && $age !== null && $age <= $config['max_age'] && $next !== false;
        $rows[] = [
            'owner' => $job, 'status' => $ok ? 'ok' : 'error', 'state' => $state,
            'last_success_at' => $success, 'age_seconds' => $age, 'next_run_at' => $next ?: 0,
            'error' => (string) ($receipt['error'] ?? ''),
        ];
    }
    if (function_exists('iss_graph_get_dirty_queue_stats')) {
        $queue = iss_graph_get_dirty_queue_stats();
        $rows[] = [
            'owner' => 'graph_queue',
            'status' => !empty($queue['exists']) && empty($queue['read_error']) && empty($queue['dead']) && empty($queue['last_run']['failed']) && empty($queue['last_run']['lease_lost']) && (int) ($queue['oldest_dirty_age_seconds'] ?? 0) <= HOUR_IN_SECONDS && (int) ($queue['oldest_processing_age_seconds'] ?? 0) <= 15 * MINUTE_IN_SECONDS ? 'ok' : 'error',
            'read_error' => !empty($queue['read_error']),
            'dirty' => (int) ($queue['dirty'] ?? 0), 'processing' => (int) ($queue['processing'] ?? 0),
            'dead' => (int) ($queue['dead'] ?? 0), 'oldest_age_seconds' => (int) ($queue['oldest_dirty_age_seconds'] ?? 0),
            'oldest_processing_age_seconds' => (int) ($queue['oldest_processing_age_seconds'] ?? 0),
            'last_run' => $queue['last_run'] ?? [],
        ];
    }
    $missing_hooks = []; $oldest = 0;
    foreach ((array) _get_cron_array() as $time => $hooks) {
        foreach ($hooks as $hook => $events) {
            if (!has_action($hook)) { $missing_hooks[] = $hook; continue; }
            if ((int) $time < $now) { $oldest = max($oldest, $now - (int) $time); }
        }
    }
    $rows[] = ['owner' => 'wp_cron', 'status' => $oldest > HOUR_IN_SECONDS || $missing_hooks ? 'error' : 'ok', 'oldest_overdue_seconds' => $oldest, 'unregistered_hooks' => array_values(array_unique($missing_hooks))];
    return $rows;
}

add_filter('iss_core_status_rows', static function (array $rows): array {
    foreach (iss_core_maintenance_status() as $status) {
        $rows[] = iss_core_status_row('maintenance', (string) $status['owner'], (string) $status['status'], (string) wp_json_encode($status));
    }
    return $rows;
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss maintenance-status', static function (array $args, array $assoc): void {
        $rows = iss_core_maintenance_status();
        WP_CLI::log((string) wp_json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (in_array('error', array_column($rows, 'status'), true)) {
            WP_CLI::error('Maintenance needs attention: inspect failed/overdue owners, queue retries and unregistered hooks.');
        }
        WP_CLI::success('Maintenance owners are current.');
    });
}
