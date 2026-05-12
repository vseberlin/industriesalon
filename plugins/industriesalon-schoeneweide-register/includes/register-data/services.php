<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_summary_places_contracts(array $query_args = [], array $fields = []): array
{
    $places = $query_args
        ? iss_register_get_filtered_place_entities($query_args)
        : iss_register_get_place_entities();

    return iss_register_build_summary_place_contracts($places, $fields);
}

function iss_register_get_detail_place_contract(string $place_id): ?array
{
    $place = iss_register_get_place_entity_by_id($place_id);

    if (!is_array($place)) {
        return null;
    }

    return iss_register_build_place_detail_contract($place);
}

function iss_register_get_export_places_contracts(array $query_args = []): array
{
    $places = $query_args
        ? iss_register_get_filtered_place_entities($query_args)
        : iss_register_get_place_entities();

    return array_values($places);
}

function iss_register_get_meta_contract(): array
{
    $places = iss_register_get_place_entities();
    $areas = [];
    $statuses = [];
    $roles = [];
    $current_statuses = [];
    $current_use_types = [];
    $tags = [];
    $epoch_counts = iss_register_get_epoch_service()->get_counts_by_era_and_function();

    foreach ($places as $place) {
        if (isset($place['area']) && $place['area'] !== '') {
            $areas[$place['area']] = ($areas[$place['area']] ?? 0) + 1;
        }
        if (isset($place['status']) && $place['status'] !== '') {
            $statuses[$place['status']] = ($statuses[$place['status']] ?? 0) + 1;
        }
        if (isset($place['role']) && $place['role'] !== '') {
            $roles[$place['role']] = ($roles[$place['role']] ?? 0) + 1;
        }
        $current_status = isset($place['normalized_current_status']) ? (string) $place['normalized_current_status'] : '';
        if ($current_status !== '') {
            $current_statuses[$current_status] = ($current_statuses[$current_status] ?? 0) + 1;
        }
        if (isset($place['current_use_type']) && $place['current_use_type'] !== '') {
            $current_use_types[$place['current_use_type']] = ($current_use_types[$place['current_use_type']] ?? 0) + 1;
        }

        $place_tags = $place['tags'] ?? [];
        if (is_string($place_tags)) {
            $place_tags = array_map('trim', explode(',', $place_tags));
        }

        if (is_array($place_tags)) {
            foreach ($place_tags as $tag) {
                if (!is_string($tag) || $tag === '') {
                    continue;
                }

                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
        }
    }

    return [
        'total' => count($places),
        'areas' => $areas,
        'statuses' => $statuses,
        'roles' => $roles,
        'current_statuses' => $current_statuses,
        'current_use_types' => $current_use_types,
        'tags' => $tags,
        'eras' => array_map(static function (array $definition): array {
            return [
                'slug' => (string) ($definition['slug'] ?? ''),
                'name' => (string) ($definition['name'] ?? ''),
                'caption' => (string) ($definition['caption'] ?? ''),
            ];
        }, array_values(iss_register_get_epoch_service()->get_era_definitions())),
        'epoch_functions' => array_map(static function (array $definition): array {
            return [
                'key' => (string) ($definition['key'] ?? ''),
                'label' => (string) ($definition['label'] ?? ''),
            ];
        }, array_values(iss_register_get_epoch_function_definitions())),
        'epoch_source_confidence' => array_map(static function (array $definition): array {
            return [
                'key' => (string) ($definition['key'] ?? ''),
                'label' => (string) ($definition['label'] ?? ''),
            ];
        }, array_values(iss_register_get_epoch_source_confidence_definitions())),
        'epoch_counts' => $epoch_counts,
    ];
}
