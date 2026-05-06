<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_meta_schema(): array
{
    return [
        'register_id' => ['type' => 'string', 'label' => 'Register ID', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'area' => ['type' => 'string', 'label' => 'Gebiet', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'address' => ['type' => 'string', 'label' => 'Adresse', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'lat' => ['type' => 'number', 'label' => 'Lat', 'input' => 'number', 'show_in_rest' => true, 'admin' => true],
        'lng' => ['type' => 'number', 'label' => 'Lng', 'input' => 'number', 'show_in_rest' => true, 'admin' => true],
        'coordinates_accuracy' => ['type' => 'string', 'label' => 'Koordinaten-Genauigkeit', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'status' => ['type' => 'string', 'label' => 'Status', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'role' => ['type' => 'string', 'label' => 'Rolle', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'owner' => ['type' => 'string', 'label' => 'Eigentümer', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'operator' => ['type' => 'string', 'label' => 'Operator', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'developer' => ['type' => 'string', 'label' => 'Developer', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'tenant' => ['type' => 'string', 'label' => 'Tenant', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'industry' => ['type' => 'string', 'label' => 'Branche', 'input' => 'textarea', 'show_in_rest' => true, 'admin' => true],
        'investment' => ['type' => 'string', 'label' => 'Investition', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'size' => ['type' => 'string', 'label' => 'Fläche', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'jobs' => ['type' => 'string', 'label' => 'Jobs / Nutzung', 'input' => 'text', 'show_in_rest' => true, 'admin' => true],
        'previous_use' => ['type' => 'string', 'label' => 'Vorherige Nutzung', 'input' => 'textarea', 'show_in_rest' => true, 'admin' => true],
        'current_use' => ['type' => 'string', 'label' => 'Aktuelle Nutzung', 'input' => 'textarea', 'show_in_rest' => true, 'admin' => true],
        'history_short' => ['type' => 'string', 'label' => 'Historie kurz', 'input' => 'textarea', 'show_in_rest' => true, 'admin' => true],
        'history_long' => ['type' => 'string', 'label' => 'Historie lang', 'input' => 'textarea', 'show_in_rest' => true, 'admin' => true],
        'research_note' => ['type' => 'string', 'label' => 'Research Note', 'input' => 'textarea', 'show_in_rest' => true, 'admin' => true],
        'source_summary' => ['type' => 'string', 'label' => 'Quellen (Zusammenfassung)', 'input' => 'textarea', 'show_in_rest' => true, 'admin' => true],
        'source_links' => ['type' => 'array', 'label' => 'Quellen-Links', 'input' => 'array_textarea', 'show_in_rest' => true, 'admin' => true],
        'tags' => ['type' => 'array', 'label' => 'Tags', 'input' => 'array_textarea', 'show_in_rest' => true, 'admin' => true],
        'archive_images' => ['type' => 'array', 'label' => 'Archivbilder', 'input' => 'image_group', 'show_in_rest' => true, 'admin' => true],
        'current_images' => ['type' => 'array', 'label' => 'Aktuelle Bilder', 'input' => 'image_group', 'show_in_rest' => true, 'admin' => true],
        'document_images' => ['type' => 'array', 'label' => 'Dokumentbilder', 'input' => 'image_group', 'show_in_rest' => true, 'admin' => true],
        'is_unclear' => ['type' => 'boolean', 'label' => 'Unklar', 'input' => 'checkbox', 'show_in_rest' => true, 'admin' => true],
        'sort_order' => ['type' => 'integer', 'label' => 'Sortierung', 'input' => 'number', 'show_in_rest' => true, 'admin' => true],
        'legacy_icon' => ['type' => 'string', 'label' => 'Legacy Icon', 'input' => 'text', 'show_in_rest' => false, 'admin' => true],
        'legacy_color' => ['type' => 'string', 'label' => 'Legacy Color', 'input' => 'text', 'show_in_rest' => false, 'admin' => true],
        'legacy_website' => ['type' => 'string', 'label' => 'Website', 'input' => 'text', 'show_in_rest' => false, 'admin' => true],
        'legacy_kaufpreis' => ['type' => 'string', 'label' => 'Kaufpreis', 'input' => 'text', 'show_in_rest' => false, 'admin' => true],
        'legacy_questions' => ['type' => 'array', 'label' => 'Fragen', 'input' => 'array_textarea', 'show_in_rest' => false, 'admin' => true],
    ];
}

function iss_register_image_group_fields(): array
{
    return ['archive_images', 'current_images', 'document_images'];
}

function iss_register_is_image_group_field(string $field): bool
{
    return in_array($field, iss_register_image_group_fields(), true);
}

function iss_register_image_visibility_options(): array
{
    return ['public', 'private', 'pending'];
}

function iss_register_sanitize_meta_array($value): array
{
    if (is_string($value)) {
        $value = preg_split('/\r\n|\r|\n/', $value);
    }

    if (!is_array($value)) {
        return [];
    }

    $sanitized = [];
    foreach ($value as $item) {
        if (!is_scalar($item)) {
            continue;
        }
        $clean = sanitize_text_field((string) $item);
        if ($clean !== '') {
            $sanitized[] = $clean;
        }
    }

    return array_values(array_unique($sanitized));
}

function iss_register_sanitize_image_group($value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        }
    }

    if (!is_array($value)) {
        return [];
    }

    $visibility_options = iss_register_image_visibility_options();
    $normalized = [];

    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $media_id = absint($item['media_id'] ?? 0);
        if ($media_id <= 0) {
            continue;
        }

        $attachment_url = wp_get_attachment_url($media_id);
        $url = is_string($attachment_url) && $attachment_url !== ''
            ? $attachment_url
            : esc_url_raw((string) ($item['url'] ?? ''));

        if ($url === '') {
            continue;
        }

        $visibility = sanitize_key((string) ($item['visibility'] ?? 'private'));
        if (!in_array($visibility, $visibility_options, true)) {
            $visibility = 'private';
        }

        $normalized[] = [
            'media_id' => $media_id,
            'url' => $url,
            'caption' => sanitize_text_field((string) ($item['caption'] ?? '')),
            'year' => sanitize_text_field((string) ($item['year'] ?? '')),
            'source' => sanitize_text_field((string) ($item['source'] ?? '')),
            'photographer' => sanitize_text_field((string) ($item['photographer'] ?? '')),
            'rights' => sanitize_text_field((string) ($item['rights'] ?? '')),
            'is_featured' => rest_sanitize_boolean($item['is_featured'] ?? false),
            'visibility' => $visibility,
        ];
    }

    return array_values($normalized);
}

function iss_register_image_group_rest_schema(): array
{
    return [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'media_id' => ['type' => 'integer'],
                'url' => ['type' => 'string'],
                'caption' => ['type' => 'string'],
                'year' => ['type' => 'string'],
                'source' => ['type' => 'string'],
                'photographer' => ['type' => 'string'],
                'rights' => ['type' => 'string'],
                'is_featured' => ['type' => 'boolean'],
                'visibility' => ['type' => 'string'],
            ],
        ],
    ];
}

function iss_register_sanitize_meta_value(string $field, $value)
{
    $schema = iss_register_get_meta_schema();
    if (!isset($schema[$field])) {
        return '';
    }

    $type = $schema[$field]['type'];

    if ($type === 'array') {
        if (iss_register_is_image_group_field($field)) {
            return iss_register_sanitize_image_group($value);
        }
        return iss_register_sanitize_meta_array($value);
    }

    if ($type === 'boolean') {
        return rest_sanitize_boolean($value) ? 1 : 0;
    }

    if ($type === 'integer') {
        return (int) $value;
    }

    if ($type === 'number') {
        if ($value === '' || $value === null) {
            return '';
        }
        return (float) $value;
    }

    return sanitize_textarea_field((string) $value);
}

function iss_register_register_meta_fields(): void
{
    foreach (iss_register_get_meta_schema() as $key => $field) {
        $show_in_rest = !empty($field['show_in_rest']);
        if ($show_in_rest && iss_register_is_image_group_field($key)) {
            $show_in_rest = [
                'schema' => iss_register_image_group_rest_schema(),
            ];
        }

        register_post_meta(ISS_REGISTER_POST_TYPE, $key, [
            'type' => $field['type'] === 'array' ? 'array' : ($field['type'] === 'boolean' ? 'boolean' : ($field['type'] === 'integer' ? 'integer' : ($field['type'] === 'number' ? 'number' : 'string'))),
            'single' => true,
            'show_in_rest' => $show_in_rest,
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
            'sanitize_callback' => function ($value) use ($key) {
                return iss_register_sanitize_meta_value($key, $value);
            },
        ]);
    }
}

add_action('init', 'iss_register_register_meta_fields');

function iss_register_render_meta_box(WP_Post $post): void
{
    iss_register_render_fields_table($post, array_keys(iss_register_get_meta_schema()));
}

function iss_register_get_meta_box_groups(): array
{
    return [
        'atlas_basics' => [
            'title' => __('Atlas Pflichtangaben', 'industriesalon-schoeneweide-register'),
            'context' => 'normal',
            'priority' => 'high',
            'description' => __('Für einen nutzbaren Atlas-Ort reichen der Titel oben, Adresse, Koordinaten und mindestens ein kurzer öffentlicher Text. Alles andere kann später ergänzt werden.', 'industriesalon-schoeneweide-register'),
            'fields' => ['area', 'address', 'lat', 'lng', 'coordinates_accuracy', 'status', 'role', 'is_unclear', 'sort_order'],
        ],
        'atlas_public' => [
            'title' => __('Öffentliche Atlas-Texte', 'industriesalon-schoeneweide-register'),
            'context' => 'normal',
            'priority' => 'default',
            'description' => __('Diese Angaben speisen Atlas-Karten, Vorschauteaser und Dossiers. Wenn kein manueller Auszug gepflegt ist, wird er automatisch aus „Historie kurz“, „Aktuelle Nutzung“ oder dem Hauptinhalt gebildet.', 'industriesalon-schoeneweide-register'),
            'fields' => ['history_short', 'current_use', 'industry', 'source_summary', 'tags'],
        ],
        'atlas_media' => [
            'title' => __('Medien', 'industriesalon-schoeneweide-register'),
            'context' => 'normal',
            'priority' => 'default',
            'description' => __('Öffentliche Atlas-Bilder müssen in einer Bildgruppe auf „public“ stehen. Bildvorschläge werden in der separaten Metabox verwaltet.', 'industriesalon-schoeneweide-register'),
            'fields' => ['archive_images', 'current_images', 'document_images'],
        ],
        'atlas_research' => [
            'title' => __('Dossier und Forschung', 'industriesalon-schoeneweide-register'),
            'context' => 'normal',
            'priority' => 'default',
            'description' => __('Diese Angaben bleiben wichtig für Recherche und längere Dossiers, sind aber nicht nötig, um einen Ort zuerst im Atlas sichtbar zu machen.', 'industriesalon-schoeneweide-register'),
            'fields' => ['register_id', 'owner', 'operator', 'developer', 'tenant', 'investment', 'size', 'jobs', 'previous_use', 'history_long', 'research_note', 'source_links', 'legacy_website', 'legacy_kaufpreis', 'legacy_questions', 'legacy_icon', 'legacy_color'],
        ],
    ];
}

function iss_register_render_fields_table(WP_Post $post, array $field_keys): void
{
    $schema = iss_register_get_meta_schema();

    echo '<table class="form-table" role="presentation"><tbody>';
    foreach ($field_keys as $key) {
        if (!isset($schema[$key]) || empty($schema[$key]['admin'])) {
            continue;
        }

        $field = $schema[$key];
        $value = get_post_meta($post->ID, $key, true);
        $label = esc_html($field['label']);
        $input_name = 'iss_register_meta[' . esc_attr($key) . ']';

        echo '<tr>';
        echo '<th scope="row"><label for="iss-register-' . esc_attr($key) . '">' . $label . '</label></th>';
        echo '<td>';

        if ($field['input'] === 'textarea') {
            echo '<textarea id="iss-register-' . esc_attr($key) . '" name="' . $input_name . '" class="large-text" rows="3">' . esc_textarea((string) $value) . '</textarea>';
        } elseif ($field['input'] === 'array_textarea') {
            $array_value = is_array($value) ? $value : [];
            echo '<textarea id="iss-register-' . esc_attr($key) . '" name="' . $input_name . '" class="large-text" rows="4">' . esc_textarea(implode("\n", $array_value)) . '</textarea>';
        } elseif ($field['input'] === 'image_group') {
            $images = iss_register_sanitize_image_group($value);
            $json_value = wp_json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json_value) || $json_value === '') {
                $json_value = '[]';
            }
            echo '<div class="iss-register-image-group" data-field="' . esc_attr($key) . '">';
            echo '<div class="iss-register-image-group__rows"></div>';
            echo '<p><button type="button" class="button iss-register-image-group__add">Bild hinzufügen</button></p>';
            echo '</div>';
            echo '<textarea id="iss-register-' . esc_attr($key) . '" name="' . $input_name . '" class="iss-register-image-group__input" rows="2" style="display:none;">' . esc_textarea($json_value) . '</textarea>';
            echo '<p class="description">Nur Bilder mit Sichtbarkeit <code>public</code> werden im öffentlichen REST-Output ausgegeben.</p>';
        } elseif ($field['input'] === 'checkbox') {
            echo '<label><input id="iss-register-' . esc_attr($key) . '" type="checkbox" name="' . $input_name . '" value="1" ' . checked((int) $value, 1, false) . '> Ja</label>';
        } else {
            $input_type = $field['input'] === 'number' ? 'number' : 'text';
            echo '<input id="iss-register-' . esc_attr($key) . '" type="' . esc_attr($input_type) . '" name="' . $input_name . '" class="regular-text" value="' . esc_attr((string) $value) . '">';
        }

        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function iss_register_render_group_meta_box(WP_Post $post, array $box): void
{
    $args = isset($box['args']) && is_array($box['args']) ? $box['args'] : [];
    $groups = iss_register_get_meta_box_groups();
    $group_key = sanitize_key((string) ($args['group_key'] ?? ''));

    if (!isset($groups[$group_key])) {
        iss_register_render_meta_box($post);
        return;
    }

    wp_nonce_field('iss_register_save_meta_box', 'iss_register_meta_nonce');

    $group = $groups[$group_key];
    $description = trim((string) ($group['description'] ?? ''));
    if ($description !== '') {
        echo '<p>' . esc_html($description) . '</p>';
    }

    iss_register_render_fields_table($post, (array) ($group['fields'] ?? []));
}

add_action('add_meta_boxes', function () {
    foreach (iss_register_get_meta_box_groups() as $group_key => $group) {
        add_meta_box(
            'iss-register-' . $group_key,
            (string) ($group['title'] ?? __('Register Felder', 'industriesalon-schoeneweide-register')),
            'iss_register_render_group_meta_box',
            ISS_REGISTER_POST_TYPE,
            (string) ($group['context'] ?? 'normal'),
            (string) ($group['priority'] ?? 'default'),
            [
                'group_key' => $group_key,
            ]
        );
    }
});

function iss_register_save_meta_box(int $post_id): void
{
    if (!isset($_POST['iss_register_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_register_meta_nonce'])), 'iss_register_save_meta_box')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $meta_input = $_POST['iss_register_meta'] ?? [];
    if (!is_array($meta_input)) {
        $meta_input = [];
    }

    $schema = iss_register_get_meta_schema();
    foreach ($schema as $key => $field) {
        if (empty($field['admin'])) {
            continue;
        }

        if ($field['type'] === 'boolean') {
            $raw_value = isset($meta_input[$key]) ? 1 : 0;
        } else {
            $raw_value = $meta_input[$key] ?? '';
        }

        $sanitized = iss_register_sanitize_meta_value($key, wp_unslash($raw_value));

        if (($field['type'] === 'array' && empty($sanitized)) || ($field['type'] !== 'array' && $sanitized === '')) {
            delete_post_meta($post_id, $key);
            continue;
        }

        update_post_meta($post_id, $key, $sanitized);
    }
}

add_action('save_post_' . ISS_REGISTER_POST_TYPE, 'iss_register_save_meta_box');

function iss_register_admin_enqueue_image_group_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== ISS_REGISTER_POST_TYPE) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'iss-register-image-groups-admin',
        ISS_REGISTER_URL . 'assets/js/register-image-groups-admin.js',
        [],
        ISS_REGISTER_VERSION,
        true
    );
    wp_enqueue_style(
        'iss-register-image-groups-admin',
        ISS_REGISTER_URL . 'assets/css/register-image-groups-admin.css',
        [],
        ISS_REGISTER_VERSION
    );
}

add_action('admin_enqueue_scripts', 'iss_register_admin_enqueue_image_group_assets');
