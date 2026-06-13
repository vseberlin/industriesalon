<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_register_acf_ausstellung_fields(): void
{
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group([
        'key' => 'group_iss_ausstellung_controls',
        'title' => __('Ausstellung', 'iss-content-model'),
        'fields' => [
            [
                'key' => 'field_iss_ausstellung_basis_tab',
                'label' => __('Basis', 'iss-content-model'),
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'key' => 'field_iss_start_date',
                'label' => __('Startdatum', 'iss-content-model'),
                'name' => 'iss_start_date',
                'type' => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format' => 'Y-m-d',
                'save_format' => 'Y-m-d',
                'first_day' => 1,
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_iss_end_date',
                'label' => __('Enddatum', 'iss-content-model'),
                'name' => 'iss_end_date',
                'type' => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format' => 'Y-m-d',
                'save_format' => 'Y-m-d',
                'first_day' => 1,
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_iss_public_overview_enabled',
                'label' => __('In Ausstellungsübersichten anzeigen', 'iss-content-model'),
                'name' => 'iss_public_overview_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 0,
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_iss_programme_enabled',
                'label' => __('Im Kalender / Programm anzeigen', 'iss-content-model'),
                'name' => 'iss_programme_enabled',
                'type' => 'true_false',
                'ui' => 1,
                'default_value' => 0,
                'wrapper' => ['width' => '50'],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
    ]);
}
add_action('acf/init', 'iss_content_model_register_acf_ausstellung_fields');

function iss_content_model_acf_update_ausstellung_date($value, $post_id, array $field): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{8}$/', $value) === 1) {
        $date = DateTimeImmutable::createFromFormat('!Ymd', $value, wp_timezone());
        return $date instanceof DateTimeImmutable ? $date->format('Y-m-d') : '';
    }

    return iss_content_model_sanitize_iso_date($value);
}
add_filter('acf/update_value/name=iss_start_date', 'iss_content_model_acf_update_ausstellung_date', 10, 3);
add_filter('acf/update_value/name=iss_end_date', 'iss_content_model_acf_update_ausstellung_date', 10, 3);
