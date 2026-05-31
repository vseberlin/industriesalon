<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-register contract-check', 'iss_register_wpcli_contract_check_command');
    WP_CLI::add_command('iss-register place-state-check', 'iss_register_wpcli_place_state_check_command');
}

function iss_register_wpcli_contract_check_command(array $args, array $assoc_args): void
{
    $result = iss_register_run_contract_smoke_check();

    foreach ((array) ($result['checks'] ?? []) as $check) {
        $label = (string) ($check['label'] ?? 'unknown');
        $details = trim((string) ($check['details'] ?? ''));

        if (!empty($check['passed'])) {
            WP_CLI::log(sprintf('[ok] %s', $label));
            continue;
        }

        WP_CLI::warning($details !== '' ? sprintf('%s: %s', $label, $details) : $label);
    }

    if (!empty($result['passed'])) {
        WP_CLI::success('Register contract smoke check passed.');
        return;
    }

    WP_CLI::error('Register contract smoke check failed.');
}

function iss_register_wpcli_place_state_check_command(array $args, array $assoc_args): void
{
    $report = iss_register_get_place_state_service()->get_verification_report();

    $checks = [
        [
            'label' => 'table-exists',
            'passed' => !empty($report['table_exists']),
            'details' => 'Place-state table is missing.',
        ],
        [
            'label' => 'places-covered',
            'passed' => (int) ($report['places_with_states'] ?? 0) === (int) ($report['total_places'] ?? 0),
            'details' => sprintf(
                'places=%d covered=%d',
                (int) ($report['total_places'] ?? 0),
                (int) ($report['places_with_states'] ?? 0)
            ),
        ],
        [
            'label' => 'one-current-row-per-place',
            'passed' => (int) ($report['current_rows'] ?? 0) === (int) ($report['total_places'] ?? 0),
            'details' => sprintf(
                'places=%d current_rows=%d',
                (int) ($report['total_places'] ?? 0),
                (int) ($report['current_rows'] ?? 0)
            ),
        ],
    ];

    foreach ($checks as $check) {
        if (!empty($check['passed'])) {
            WP_CLI::log(sprintf('[ok] %s', $check['label']));
            continue;
        }

        WP_CLI::warning(sprintf('%s: %s', $check['label'], $check['details']));
    }

    WP_CLI::log(sprintf('rows_total=%d historical_rows=%d sample_keys=%s',
        (int) ($report['rows_total'] ?? 0),
        (int) ($report['historical_rows'] ?? 0),
        implode(',', (array) ($report['sample_place_state_keys'] ?? []))
    ));

    $failed = array_filter($checks, static function (array $check): bool {
        return empty($check['passed']);
    });

    if (!$failed) {
        WP_CLI::success('Register place-state check passed.');
        return;
    }

    WP_CLI::error('Register place-state check failed.');
}
