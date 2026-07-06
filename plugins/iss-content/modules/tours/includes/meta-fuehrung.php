<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_fuehrung_get_offer_catalog_group_definitions(): array
{
    return [
        'oeffentlich' => [
            'label' => __('Öffentlich', 'iss-fuehrungen'),
            'title' => __('Öffentliche Termine', 'iss-fuehrungen'),
            'description' => __('Führungen mit sichtbaren Terminen und offenem Einstieg in Schöneweide.', 'iss-fuehrungen'),
        ],
        'gruppen' => [
            'label' => __('Gruppen', 'iss-fuehrungen'),
            'title' => __('Gruppen & Fachbesuche', 'iss-fuehrungen'),
            'description' => __('Formate für Gruppen, Schulklassen, Teams und vertiefte Besuche mit abgestimmtem Schwerpunkt.', 'iss-fuehrungen'),
        ],
        'individuell' => [
            'label' => __('Individuell', 'iss-fuehrungen'),
            'title' => __('Individuell buchbar', 'iss-fuehrungen'),
            'description' => __('Routen, die sich auf Anfrage an Anlass, Gruppe oder thematischen Fokus anpassen lassen.', 'iss-fuehrungen'),
        ],
        'familie-kinder' => [
            'label' => __('Familien & Kinder', 'iss-fuehrungen'),
            'title' => __('Familien & Kinder', 'iss-fuehrungen'),
            'description' => __('Rallyes und Formate, die Schöneweide über Mitmachen, Vergleichen und eigenes Tempo erschließen.', 'iss-fuehrungen'),
        ],
    ];
}

function iss_fuehrung_sanitize_offer_catalog_groups($value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    }

    $allowed = array_fill_keys(array_keys(iss_fuehrung_get_offer_catalog_group_definitions()), true);
    $groups = [];
    foreach ((array) $value as $group_key) {
        $group_key = sanitize_key((string) $group_key);
        if ($group_key !== '' && isset($allowed[$group_key])) {
            $groups[$group_key] = true;
        }
    }

    return array_keys($groups);
}

function iss_fuehrungen_meta_fields() {
    return [
        'duration' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'meeting_point' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'target_group' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'price_note' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'booking_price_cents' => [
            'type' => 'integer',
            'sanitize' => 'absint',
            'default' => 0,
        ],
        'booking_note' => [
            'type' => 'string',
            'sanitize' => 'sanitize_textarea_field',
            'default' => '',
        ],
        'booking_mode' => [
            'type' => 'string',
            'sanitize' => static function ($value) {
                $value = sanitize_key((string) $value);
                $allowed = ['auto', 'calendar', 'on_demand', 'hybrid'];
                return in_array($value, $allowed, true) ? $value : 'auto';
            },
            'default' => 'auto',
        ],
        'allow_on_demand_with_calendar' => [
            'type' => 'boolean',
            'sanitize' => static function ($value) {
                return !empty($value);
            },
            'default' => false,
        ],
        'inquiry_url' => [
            'type' => 'string',
            'sanitize' => static function ($value) {
                return esc_url_raw(trim((string) $value));
            },
            'default' => '',
        ],
        'inquiry_label' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'inquiry_note' => [
            'type' => 'string',
            'sanitize' => 'sanitize_textarea_field',
            'default' => '',
        ],
        'tour_badge' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'offer_catalog_groups' => [
            'type' => 'array',
            'sanitize' => 'iss_fuehrung_sanitize_offer_catalog_groups',
            'default' => [],
        ],
    ];
}

add_action('init', function () {
    foreach (iss_fuehrungen_meta_fields() as $key => $config) {
        $args = [
            'single'            => true,
            'type'              => $config['type'],
            'default'           => $config['default'],
            'sanitize_callback' => $config['sanitize'],
            'auth_callback'     => static function () {
                return current_user_can('edit_posts');
            },
        ];

        if ($config['type'] === 'array') {
            $args['show_in_rest'] = [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ];
        } else {
            $args['show_in_rest'] = true;
        }

        register_post_meta(ISS_FUEHRUNGEN_POST_TYPE, $key, $args);
    }
});

function iss_fuehrungen_get_meta($post_id, $key, $default = '') {
    $value = get_post_meta($post_id, $key, true);
    return ($value === '' || $value === null) ? $default : $value;
}
