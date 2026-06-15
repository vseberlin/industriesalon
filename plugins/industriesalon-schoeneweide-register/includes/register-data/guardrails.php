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

function iss_register_contract_is_list(array $payload): bool
{
    return array_values($payload) === $payload;
}

function iss_register_contract_collect_exact_key_errors(array $payload, array $expected_keys, string $path): array
{
    $actual_keys = array_values(array_keys($payload));
    if ($actual_keys === array_values($expected_keys)) {
        return [];
    }

    return [
        sprintf('%s keys expected [%s], got [%s]', $path, implode(',', $expected_keys), implode(',', $actual_keys)),
    ];
}

function iss_register_contract_collect_list_error($payload, string $path): array
{
    return is_array($payload) && iss_register_contract_is_list($payload)
        ? []
        : [sprintf('%s must be a list array.', $path)];
}

function iss_register_contract_collect_string_error(array $payload, string $key, string $path, bool $allow_empty = true): array
{
    if (!array_key_exists($key, $payload) || !is_string($payload[$key])) {
        return [sprintf('%s.%s must be a string.', $path, $key)];
    }

    if (!$allow_empty && trim($payload[$key]) === '') {
        return [sprintf('%s.%s must not be empty.', $path, $key)];
    }

    return [];
}

function iss_register_contract_collect_int_error(array $payload, string $key, string $path, int $min = 0): array
{
    if (!array_key_exists($key, $payload) || !is_int($payload[$key]) || $payload[$key] < $min) {
        return [sprintf('%s.%s must be an integer >= %d.', $path, $key, $min)];
    }

    return [];
}

function iss_register_contract_collect_numeric_error(array $payload, string $key, string $path): array
{
    if (!array_key_exists($key, $payload) || !is_numeric($payload[$key])) {
        return [sprintf('%s.%s must be numeric.', $path, $key)];
    }

    return [];
}

function iss_register_contract_collect_bool_error(array $payload, string $key, string $path): array
{
    if (!array_key_exists($key, $payload) || !is_bool($payload[$key])) {
        return [sprintf('%s.%s must be boolean.', $path, $key)];
    }

    return [];
}

function iss_register_contract_collect_array_error(array $payload, string $key, string $path): array
{
    if (!array_key_exists($key, $payload) || !is_array($payload[$key])) {
        return [sprintf('%s.%s must be an array.', $path, $key)];
    }

    return [];
}

function iss_register_contract_atlas_place_keys(): array
{
    return [
        'id',
        'post_id',
        'slug',
        'name',
        'excerpt',
        'permalink',
        'featured_image_url',
        'archive_image_url',
        'role',
        'area',
        'address',
        'status',
        'current_status',
        'current_status_label',
        'current_status_caption',
        'current_status_source',
        'current_use_type',
        'current_use_type_label',
        'current_use_type_source',
        'present_label',
        'industry_actor_keys',
        'industry_actor_labels',
        'industry_actor_relations',
        'related_publications',
        'color',
        'branche',
        'lat',
        'lng',
        'era_id',
        'era_label',
        'era_short_label',
        'era_caption',
        'era_slug',
        'era_name',
        'era_source',
        'explicit_era_slugs',
        'explicit_era_names',
        'has_epochs',
        'epoch_summaries',
        'has_tour_usage',
        'related_tours',
        'story_score',
        'summary',
        'secondary',
        'archive_summary',
        'current_summary',
        'note_text',
        'profile',
    ];
}

function iss_register_contract_collect_atlas_place_errors(array $place, string $path = 'atlas[0]'): array
{
    $errors = iss_register_contract_collect_exact_key_errors($place, iss_register_contract_atlas_place_keys(), $path);

    foreach (['id', 'slug', 'name', 'permalink', 'area', 'era_id', 'era_label', 'era_slug', 'era_name'] as $key) {
        $errors = array_merge($errors, iss_register_contract_collect_string_error($place, $key, $path, false));
    }

    foreach (['excerpt', 'featured_image_url', 'archive_image_url', 'role', 'address', 'status', 'current_status', 'current_status_label', 'current_status_caption', 'current_status_source', 'current_use_type', 'current_use_type_label', 'current_use_type_source', 'present_label', 'color', 'branche', 'era_short_label', 'era_caption', 'era_source', 'summary', 'secondary', 'archive_summary', 'current_summary', 'note_text', 'profile'] as $key) {
        $errors = array_merge($errors, iss_register_contract_collect_string_error($place, $key, $path));
    }

    $errors = array_merge($errors, iss_register_contract_collect_int_error($place, 'post_id', $path, 1));
    foreach (['lat', 'lng', 'story_score'] as $key) {
        $errors = array_merge($errors, iss_register_contract_collect_numeric_error($place, $key, $path));
    }
    foreach (['has_epochs', 'has_tour_usage'] as $key) {
        $errors = array_merge($errors, iss_register_contract_collect_bool_error($place, $key, $path));
    }
    foreach (['industry_actor_keys', 'industry_actor_labels', 'industry_actor_relations', 'related_publications', 'explicit_era_slugs', 'explicit_era_names', 'epoch_summaries', 'related_tours'] as $key) {
        $errors = array_merge($errors, iss_register_contract_collect_array_error($place, $key, $path));
        if (isset($place[$key]) && is_array($place[$key])) {
            $errors = array_merge($errors, iss_register_contract_collect_list_error($place[$key], $path . '.' . $key));
        }
    }

    foreach ((array) ($place['related_publications'] ?? []) as $index => $publication) {
        if (!is_array($publication)) {
            $errors[] = sprintf('%s.related_publications[%d] must be an array.', $path, $index);
            continue;
        }
        $item_path = sprintf('%s.related_publications[%d]', $path, $index);
        $errors = array_merge($errors, iss_register_contract_collect_exact_key_errors($publication, ['id', 'title', 'permalink'], $item_path));
        $errors = array_merge($errors, iss_register_contract_collect_int_error($publication, 'id', $item_path, 1));
        $errors = array_merge($errors, iss_register_contract_collect_string_error($publication, 'title', $item_path, false));
        $errors = array_merge($errors, iss_register_contract_collect_string_error($publication, 'permalink', $item_path, false));
    }

    foreach ((array) ($place['epoch_summaries'] ?? []) as $index => $epoch) {
        if (!is_array($epoch)) {
            $errors[] = sprintf('%s.epoch_summaries[%d] must be an array.', $path, $index);
            continue;
        }
        $item_path = sprintf('%s.epoch_summaries[%d]', $path, $index);
        $errors = array_merge($errors, iss_register_contract_collect_exact_key_errors($epoch, ['id', 'era_slug', 'function_key', 'phase_name', 'summary', 'start_year', 'end_year', 'is_current'], $item_path));
        $errors = array_merge($errors, iss_register_contract_collect_int_error($epoch, 'id', $item_path, 0));
        foreach (['era_slug', 'function_key', 'phase_name', 'summary'] as $key) {
            $errors = array_merge($errors, iss_register_contract_collect_string_error($epoch, $key, $item_path));
        }
        foreach (['start_year', 'end_year'] as $key) {
            if (array_key_exists($key, $epoch) && $epoch[$key] !== null && !is_int($epoch[$key])) {
                $errors[] = sprintf('%s.%s must be an integer or null.', $item_path, $key);
            }
        }
        $errors = array_merge($errors, iss_register_contract_collect_bool_error($epoch, 'is_current', $item_path));
    }

    return $errors;
}

function iss_register_contract_collect_atlas_context_errors(array $context): array
{
    $errors = iss_register_contract_collect_exact_key_errors($context, ['eras', 'actors', 'stories'], 'atlas-context');

    foreach (['eras', 'actors', 'stories'] as $key) {
        $errors = array_merge($errors, iss_register_contract_collect_array_error($context, $key, 'atlas-context'));
        if (isset($context[$key]) && is_array($context[$key])) {
            $errors = array_merge($errors, iss_register_contract_collect_list_error($context[$key], 'atlas-context.' . $key));
        }
    }

    foreach ((array) ($context['eras'] ?? []) as $index => $era) {
        if (!is_array($era)) {
            $errors[] = sprintf('atlas-context.eras[%d] must be an array.', $index);
            continue;
        }
        $item_path = sprintf('atlas-context.eras[%d]', $index);
        $errors = array_merge($errors, iss_register_contract_collect_exact_key_errors($era, ['slug', 'name', 'caption', 'legacy_id', 'legacy_label', 'legacy_short_label', 'legacy_caption', 'place_count', 'story_count'], $item_path));
        foreach (['slug', 'name', 'caption', 'legacy_id', 'legacy_label', 'legacy_short_label', 'legacy_caption'] as $key) {
            $errors = array_merge($errors, iss_register_contract_collect_string_error($era, $key, $item_path, in_array($key, ['caption', 'legacy_caption'], true)));
        }
        $errors = array_merge($errors, iss_register_contract_collect_int_error($era, 'place_count', $item_path, 0));
        $errors = array_merge($errors, iss_register_contract_collect_int_error($era, 'story_count', $item_path, 0));
    }

    foreach ((array) ($context['actors'] ?? []) as $index => $actor) {
        if (!is_array($actor)) {
            $errors[] = sprintf('atlas-context.actors[%d] must be an array.', $index);
            continue;
        }
        $item_path = sprintf('atlas-context.actors[%d]', $index);
        $errors = array_merge($errors, iss_register_contract_collect_exact_key_errors($actor, ['key', 'label', 'name', 'color', 'place_count', 'era_counts'], $item_path));
        foreach (['key', 'label', 'name', 'color'] as $key) {
            $errors = array_merge($errors, iss_register_contract_collect_string_error($actor, $key, $item_path, $key === 'color'));
        }
        $errors = array_merge($errors, iss_register_contract_collect_int_error($actor, 'place_count', $item_path, 0));
        $errors = array_merge($errors, iss_register_contract_collect_array_error($actor, 'era_counts', $item_path));
    }

    foreach ((array) ($context['stories'] ?? []) as $index => $story) {
        if (!is_array($story)) {
            $errors[] = sprintf('atlas-context.stories[%d] must be an array.', $index);
            continue;
        }
        $item_path = sprintf('atlas-context.stories[%d]', $index);
        $errors = array_merge($errors, iss_register_contract_collect_exact_key_errors($story, ['id', 'slug', 'title', 'excerpt', 'permalink', 'featured_image_url', 'era_slugs', 'era_legacy_ids', 'place_ids'], $item_path));
        $errors = array_merge($errors, iss_register_contract_collect_int_error($story, 'id', $item_path, 1));
        foreach (['slug', 'title', 'excerpt', 'permalink', 'featured_image_url'] as $key) {
            $errors = array_merge($errors, iss_register_contract_collect_string_error($story, $key, $item_path, in_array($key, ['excerpt', 'featured_image_url'], true)));
        }
        foreach (['era_slugs', 'era_legacy_ids', 'place_ids'] as $key) {
            $errors = array_merge($errors, iss_register_contract_collect_array_error($story, $key, $item_path));
            if (isset($story[$key]) && is_array($story[$key])) {
                $errors = array_merge($errors, iss_register_contract_collect_list_error($story[$key], $item_path . '.' . $key));
            }
        }
    }

    return $errors;
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
            && iss_register_contract_has_keys($detail_place, ['size', 'history', 'current', 'source_links', 'related_objects', 'current_state', 'place_states', 'names', 'organizations', 'people', 'graph_entity_id'])
            && iss_register_contract_has_no_keys($detail_place, ['icon', 'color', 'kaufpreis', 'sort_order'])
            && is_int($detail_place['graph_entity_id'])
            && is_array($detail_place['names'])
            && is_array($detail_place['organizations'])
            && is_array($detail_place['people'])
            && is_array($detail_place['related_objects'])
            && is_array($detail_place['current_state'])
            && is_array($detail_place['place_states']),
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

    $atlas_place_errors = !empty($atlas_place)
        ? iss_register_contract_collect_atlas_place_errors($atlas_place)
        : ['No atlas place available.'];
    $checks[] = iss_register_contract_check_result(
        'atlas-rest-place-schema',
        empty($atlas_place_errors),
        implode(' ', array_slice($atlas_place_errors, 0, 4))
    );

    $atlas_context = iss_register_get_atlas_context_data();
    $atlas_context_errors = iss_register_contract_collect_atlas_context_errors($atlas_context);
    $checks[] = iss_register_contract_check_result(
        'atlas-rest-context-schema',
        empty($atlas_context_errors),
        implode(' ', array_slice($atlas_context_errors, 0, 4))
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
