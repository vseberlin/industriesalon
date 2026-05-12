<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_contract_check_result(string $label, bool $passed, string $details = ''): array
{
    return [
        'label' => $label,
        'passed' => $passed,
        'details' => $details,
    ];
}

function iss_register_contract_has_exact_keys(array $payload, array $expected_keys): bool
{
    return array_values(array_keys($payload)) === array_values($expected_keys);
}

function iss_register_contract_has_keys(array $payload, array $required_keys): bool
{
    foreach ($required_keys as $key) {
        if (!array_key_exists($key, $payload)) {
            return false;
        }
    }

    return true;
}

function iss_register_contract_has_no_keys(array $payload, array $forbidden_keys): bool
{
    foreach ($forbidden_keys as $key) {
        if (array_key_exists($key, $payload)) {
            return false;
        }
    }

    return true;
}

function iss_register_validate_summary_contract_fields_request($value): bool
{
    $parsed = iss_register_parse_summary_contract_fields_request($value);

    return empty($parsed['invalid']);
}

function iss_register_run_contract_smoke_check(): array
{
    $checks = [];
    $summary_fields = iss_register_get_summary_contract_field_names();
    $detail_fields = function_exists('iss_register_get_detail_contract_field_names')
        ? iss_register_get_detail_contract_field_names()
        : [];
    $summary_places = iss_register_get_summary_places_contracts();
    $summary_place = is_array($summary_places[0] ?? null) ? $summary_places[0] : [];
    $detail_forbidden_in_summary = [
        'size',
        'investment',
        'jobs',
        'website',
        'questions',
        'vornutzung',
        'history',
        'current',
        'sources',
        'source_links',
        'related_objects',
    ];

    $checks[] = iss_register_contract_check_result(
        'summary-default-shape',
        !empty($summary_place)
            && iss_register_contract_has_exact_keys($summary_place, $summary_fields)
            && iss_register_contract_has_no_keys($summary_place, $detail_forbidden_in_summary),
        !empty($summary_place) ? '' : 'No summary places available.'
    );

    $projected_places = iss_register_get_summary_places_contracts([], ['id', 'name', 'lat', 'lng']);
    $projected_place = is_array($projected_places[0] ?? null) ? $projected_places[0] : [];

    $checks[] = iss_register_contract_check_result(
        'summary-field-projection',
        !empty($projected_place) && iss_register_contract_has_exact_keys($projected_place, ['id', 'name', 'lat', 'lng']),
        !empty($projected_place) ? '' : 'No projected summary places available.'
    );

    $entities = iss_register_get_place_entities();
    $entity = is_array($entities[0] ?? null) ? $entities[0] : [];
    $detail_place = [];

    if (!empty($entity['id'])) {
        $detail_place = (array) iss_register_get_detail_place_contract((string) $entity['id']);
    }

    $checks[] = iss_register_contract_check_result(
        'detail-shape',
        !empty($detail_place)
            && iss_register_contract_has_exact_keys($detail_place, $detail_fields)
            && iss_register_contract_has_keys($detail_place, ['size', 'history', 'current', 'source_links', 'related_objects'])
            && iss_register_contract_has_no_keys($detail_place, ['icon', 'color', 'kaufpreis', 'sort_order'])
            && is_array($detail_place['related_objects']),
        !empty($detail_place) ? '' : 'No detail place available.'
    );

    $export_places = iss_register_get_export_places_contracts();
    $export_place = is_array($export_places[0] ?? null) ? $export_places[0] : [];

    $checks[] = iss_register_contract_check_result(
        'export-shape',
        !empty($export_place)
            && iss_register_contract_has_keys($export_place, ['size', 'history', 'current', 'source_links'])
            && iss_register_contract_has_no_keys($export_place, ['summary', 'source_labels', 'related_objects']),
        !empty($export_place) ? '' : 'No export place available.'
    );

    $atlas_places = iss_register_get_atlas_places_data();
    $atlas_place = is_array($atlas_places[0] ?? null) ? $atlas_places[0] : [];

    $checks[] = iss_register_contract_check_result(
        'atlas-shape',
        !empty($atlas_place)
            && iss_register_contract_has_keys($atlas_place, ['era_id', 'era_slug', 'story_score', 'has_tour_usage', 'lat', 'lng']),
        !empty($atlas_place) ? '' : 'No atlas place available.'
    );

    $bootstrap_payload = function_exists('iss_register_build_summary_places_payload')
        ? iss_register_build_summary_places_payload()
        : [];
    $bootstrap_place = is_array($bootstrap_payload['places'][0] ?? null) ? $bootstrap_payload['places'][0] : [];

    $checks[] = iss_register_contract_check_result(
        'bootstrap-summary-shape',
        !empty($bootstrap_place)
            && iss_register_contract_has_exact_keys($bootstrap_place, $summary_fields)
            && iss_register_contract_has_no_keys($bootstrap_place, $detail_forbidden_in_summary),
        !empty($bootstrap_place) ? '' : 'No bootstrap summary place available.'
    );

    return [
        'passed' => !array_filter($checks, static function (array $check): bool {
            return empty($check['passed']);
        }),
        'checks' => $checks,
    ];
}
