<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-register contract-check', 'iss_register_wpcli_contract_check_command');
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
