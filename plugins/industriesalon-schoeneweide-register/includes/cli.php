<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-register contract-check', 'iss_register_wpcli_contract_check_command');
    WP_CLI::add_command('iss-register place-state-check', 'iss_register_wpcli_place_state_check_command');
    WP_CLI::add_command('iss-register place-editorial-check', 'iss_register_wpcli_place_editorial_check_command');
    WP_CLI::add_command('iss-register atlas-payload-check', 'iss_register_wpcli_atlas_payload_check_command');
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

function iss_register_wpcli_place_editorial_check_command(array $args, array $assoc_args): void
{
    unset($args);

    $post_id = absint($assoc_args['post'] ?? 12899);
    if (
        $post_id <= 0
        || !function_exists('iss_editorial_document_is_enabled')
        || !iss_editorial_document_is_enabled($post_id, 'place')
        || !function_exists('iss_editorial_get_document')
    ) {
        WP_CLI::error(sprintf('Place %d does not have an enabled editorial document.', $post_id));
    }

    $document = iss_editorial_get_document($post_id, 'place', false);
    $document_epochs = array_values(array_filter(
        (array) ($document['sections'] ?? []),
        static function (array $section): bool {
            return ($section['type'] ?? '') === 'epoche';
        }
    ));
    $projection_epochs = iss_register_get_epoch_service()->get_epochs_for_place($post_id);

    $normalize_document = static function (array $section): array {
        return [
            'era_slug' => (string) ($section['era_key'] ?? ''),
            'function_key' => (string) ($section['function_key'] ?? ''),
            'phase_name' => (string) ($section['title'] ?? ''),
            'summary' => trim(wp_strip_all_tags((string) ($section['body'] ?? ''))),
            'start_year' => $section['start_year'] ?? null,
            'end_year' => $section['end_year'] ?? null,
            'is_current' => !empty($section['is_current']),
        ];
    };
    $normalize_projection = static function (array $epoch): array {
        return [
            'era_slug' => (string) ($epoch['era_slug'] ?? ''),
            'function_key' => (string) ($epoch['function_key'] ?? ''),
            'phase_name' => (string) ($epoch['phase_name'] ?? ''),
            'summary' => trim((string) ($epoch['summary'] ?? '')),
            'start_year' => $epoch['start_year'] ?? null,
            'end_year' => $epoch['end_year'] ?? null,
            'is_current' => !empty($epoch['is_current']),
        ];
    };
    $document_hash = hash('sha256', (string) wp_json_encode(array_map($normalize_document, $document_epochs)));
    $projection_hash = hash('sha256', (string) wp_json_encode(array_map($normalize_projection, $projection_epochs)));

    WP_CLI::log(sprintf(
        'post=%d document_epochs=%d projection_epochs=%d document_hash=%s projection_hash=%s',
        $post_id,
        count($document_epochs),
        count($projection_epochs),
        $document_hash,
        $projection_hash
    ));

    if (!$document_epochs || $document_hash !== $projection_hash) {
        WP_CLI::error('Place editorial/projection parity check failed.');
    }

    WP_CLI::success('Place editorial/projection parity check passed.');
}

function iss_register_wpcli_atlas_payload_check_command(array $args, array $assoc_args): void
{
    unset($args, $assoc_args);

    $places = iss_register_get_atlas_summary_places_data();
    $context = iss_register_build_atlas_context_data($places);
    $context['stories'] = [];
    $json = wp_json_encode([
        'places' => $places,
        'context' => $context,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $bytes = is_string($json) ? strlen($json) : 0;
    $gzip_bytes = function_exists('gzencode') && is_string($json)
        ? strlen((string) gzencode($json, 6))
        : 0;

    WP_CLI::log(sprintf(
        'places=%d bytes=%d gzip_bytes=%d budget_bytes=61440 budget_gzip_bytes=15360',
        count($places),
        $bytes,
        $gzip_bytes
    ));

    if (!$places || $bytes <= 0 || $bytes > 61440 || ($gzip_bytes > 0 && $gzip_bytes > 15360)) {
        WP_CLI::error('Atlas payload budget check failed.');
    }

    WP_CLI::success('Atlas payload budget check passed.');
}
