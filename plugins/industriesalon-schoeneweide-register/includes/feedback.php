<?php

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_REGISTER_FEEDBACK_POST_TYPE', 'register_feedback');

function iss_register_feedback_types(): array
{
    return [
        'correction',
        'image_contribution',
        'memory',
        'source',
        'general',
    ];
}

function iss_register_feedback_admin_statuses(): array
{
    return [
        'pending',
        'reviewed',
        'published',
        'rejected',
        'archived',
    ];
}

function iss_register_register_feedback_post_type(): void
{
    $labels = [
        'name' => 'Register Feedback',
        'singular_name' => 'Feedback',
        'menu_name' => 'Register Feedback',
        'add_new' => 'Neu hinzufügen',
        'add_new_item' => 'Feedback hinzufügen',
        'edit_item' => 'Feedback bearbeiten',
        'new_item' => 'Neues Feedback',
        'view_item' => 'Feedback ansehen',
        'search_items' => 'Feedback durchsuchen',
        'not_found' => 'Kein Feedback gefunden',
        'not_found_in_trash' => 'Kein Feedback im Papierkorb',
        'all_items' => 'Alle Feedback-Einträge',
    ];

    register_post_type(ISS_REGISTER_FEEDBACK_POST_TYPE, [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-feedback',
        'supports' => ['title', 'editor', 'revisions'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);
}

add_action('init', 'iss_register_register_feedback_post_type');

function iss_register_feedback_meta_schema(): array
{
    return [
        'related_place_id' => ['type' => 'integer', 'label' => 'Related Place ID'],
        'feedback_type' => ['type' => 'string', 'label' => 'Feedback Type'],
        'name' => ['type' => 'string', 'label' => 'Name'],
        'email' => ['type' => 'string', 'label' => 'E-Mail'],
        'message' => ['type' => 'string', 'label' => 'Message'],
        'source_url' => ['type' => 'string', 'label' => 'Source URL'],
        'image_attachment_id' => ['type' => 'integer', 'label' => 'Image Attachment ID'],
        'rights_confirmation' => ['type' => 'boolean', 'label' => 'Rights Confirmation'],
        'admin_status' => ['type' => 'string', 'label' => 'Admin Status'],
        'admin_note' => ['type' => 'string', 'label' => 'Admin Note'],
    ];
}

function iss_register_feedback_sanitize_meta(string $key, $value)
{
    $schema = iss_register_feedback_meta_schema();
    if (!isset($schema[$key])) {
        return '';
    }

    $type = $schema[$key]['type'];

    if ($type === 'integer') {
        return absint($value);
    }

    if ($type === 'boolean') {
        return rest_sanitize_boolean($value) ? 1 : 0;
    }

    if ($key === 'email') {
        return sanitize_email((string) $value);
    }

    if ($key === 'source_url') {
        return esc_url_raw((string) $value);
    }

    if ($key === 'admin_note' || $key === 'message') {
        return sanitize_textarea_field((string) $value);
    }

    return sanitize_text_field((string) $value);
}

function iss_register_register_feedback_meta(): void
{
    foreach (iss_register_feedback_meta_schema() as $key => $field) {
        register_post_meta(ISS_REGISTER_FEEDBACK_POST_TYPE, $key, [
            'single' => true,
            'type' => $field['type'] === 'integer' ? 'integer' : ($field['type'] === 'boolean' ? 'boolean' : 'string'),
            'show_in_rest' => true,
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
            'sanitize_callback' => function ($value) use ($key) {
                return iss_register_feedback_sanitize_meta($key, $value);
            },
        ]);
    }
}

add_action('init', 'iss_register_register_feedback_meta');

function iss_register_feedback_render_meta_box(WP_Post $post): void
{
    wp_nonce_field('iss_register_feedback_meta_box', 'iss_register_feedback_meta_nonce');

    $schema = iss_register_feedback_meta_schema();
    $status_options = iss_register_feedback_admin_statuses();

    echo '<table class="form-table" role="presentation"><tbody>';
    foreach ($schema as $key => $field) {
        $value = get_post_meta($post->ID, $key, true);
        $label = esc_html($field['label']);
        $input_name = 'iss_register_feedback_meta[' . esc_attr($key) . ']';

        echo '<tr>';
        echo '<th scope="row"><label for="iss-register-feedback-' . esc_attr($key) . '">' . $label . '</label></th>';
        echo '<td>';

        if ($key === 'admin_status') {
            echo '<select id="iss-register-feedback-' . esc_attr($key) . '" name="' . $input_name . '">';
            foreach ($status_options as $status) {
                echo '<option value="' . esc_attr($status) . '" ' . selected($value, $status, false) . '>' . esc_html($status) . '</option>';
            }
            echo '</select>';
        } elseif ($key === 'rights_confirmation') {
            echo '<label><input id="iss-register-feedback-' . esc_attr($key) . '" type="checkbox" name="' . $input_name . '" value="1" ' . checked((int) $value, 1, false) . '> bestätigt</label>';
        } elseif ($key === 'message' || $key === 'admin_note') {
            echo '<textarea id="iss-register-feedback-' . esc_attr($key) . '" name="' . $input_name . '" class="large-text" rows="4">' . esc_textarea((string) $value) . '</textarea>';
        } else {
            $input_type = $field['type'] === 'integer' ? 'number' : 'text';
            echo '<input id="iss-register-feedback-' . esc_attr($key) . '" type="' . esc_attr($input_type) . '" name="' . $input_name . '" class="regular-text" value="' . esc_attr((string) $value) . '">';
        }

        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'iss-register-feedback-fields',
        'Feedback Felder',
        'iss_register_feedback_render_meta_box',
        ISS_REGISTER_FEEDBACK_POST_TYPE,
        'normal',
        'high'
    );
});

function iss_register_feedback_save_meta_box(int $post_id): void
{
    if (!isset($_POST['iss_register_feedback_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_register_feedback_meta_nonce'])), 'iss_register_feedback_meta_box')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $meta = $_POST['iss_register_feedback_meta'] ?? [];
    if (!is_array($meta)) {
        $meta = [];
    }

    foreach (iss_register_feedback_meta_schema() as $key => $field) {
        $raw_value = $field['type'] === 'boolean'
            ? (isset($meta[$key]) ? 1 : 0)
            : ($meta[$key] ?? '');

        $sanitized = iss_register_feedback_sanitize_meta($key, wp_unslash($raw_value));

        if (($field['type'] === 'integer' && (int) $sanitized === 0) || ($field['type'] !== 'integer' && $sanitized === '')) {
            delete_post_meta($post_id, $key);
            continue;
        }

        update_post_meta($post_id, $key, $sanitized);
    }
}

add_action('save_post_' . ISS_REGISTER_FEEDBACK_POST_TYPE, 'iss_register_feedback_save_meta_box');

function iss_register_feedback_verify_nonce(WP_REST_Request $request)
{
    $nonce = $request->get_header('x_wp_nonce');
    if (!$nonce) {
        $nonce = $request->get_param('_wpnonce');
    }
    if (!$nonce) {
        $nonce = $request->get_header('x_iss_register_nonce');
    }

    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error(
            'iss_register_feedback_nonce_invalid',
            'Invalid or missing nonce.',
            ['status' => 403]
        );
    }

    return true;
}

function iss_register_rest_create_feedback(WP_REST_Request $request)
{
    $feedback_type = sanitize_key((string) $request->get_param('feedback_type'));
    $message = sanitize_textarea_field((string) $request->get_param('message'));

    if ($feedback_type === '' || !in_array($feedback_type, iss_register_feedback_types(), true)) {
        return new WP_Error('iss_register_feedback_type_invalid', 'Invalid feedback_type.', ['status' => 400]);
    }

    if ($message === '') {
        return new WP_Error('iss_register_feedback_message_required', 'Message is required.', ['status' => 400]);
    }

    $related_place_id = absint($request->get_param('related_place_id'));
    if ($related_place_id > 0 && get_post_type($related_place_id) !== ISS_REGISTER_POST_TYPE) {
        return new WP_Error('iss_register_feedback_place_invalid', 'Invalid related_place_id.', ['status' => 400]);
    }

    $name = sanitize_text_field((string) $request->get_param('name'));
    $email = sanitize_email((string) $request->get_param('email'));
    if ((string) $request->get_param('email') !== '' && $email === '') {
        return new WP_Error('iss_register_feedback_email_invalid', 'Invalid email.', ['status' => 400]);
    }

    $source_url_raw = (string) $request->get_param('source_url');
    $source_url = $source_url_raw !== '' ? esc_url_raw($source_url_raw) : '';
    if ($source_url_raw !== '' && $source_url === '') {
        return new WP_Error('iss_register_feedback_source_url_invalid', 'Invalid source_url.', ['status' => 400]);
    }

    $image_attachment_id = absint($request->get_param('image_attachment_id'));
    if ($image_attachment_id > 0 && get_post_type($image_attachment_id) !== 'attachment') {
        return new WP_Error('iss_register_feedback_attachment_invalid', 'Invalid image_attachment_id.', ['status' => 400]);
    }

    $rights_confirmation = rest_sanitize_boolean($request->get_param('rights_confirmation')) ? 1 : 0;

    if ($feedback_type === 'image_contribution') {
        if ($rights_confirmation !== 1) {
            return new WP_Error('iss_register_feedback_rights_required', 'rights_confirmation is required for image_contribution.', ['status' => 400]);
        }

        if ($image_attachment_id <= 0 && $source_url === '') {
            return new WP_Error(
                'iss_register_feedback_image_source_required',
                'image_attachment_id or source_url is required for image_contribution.',
                ['status' => 400]
            );
        }
    }

    $post_id = wp_insert_post([
        'post_type' => ISS_REGISTER_FEEDBACK_POST_TYPE,
        'post_status' => 'pending',
        'post_title' => sprintf('Feedback %s %s', strtoupper($feedback_type), wp_date('Y-m-d H:i')),
        'post_content' => $message,
    ], true);

    if (is_wp_error($post_id)) {
        return new WP_Error('iss_register_feedback_insert_failed', $post_id->get_error_message(), ['status' => 500]);
    }

    $meta = [
        'related_place_id' => $related_place_id,
        'feedback_type' => $feedback_type,
        'name' => $name,
        'email' => $email,
        'message' => $message,
        'source_url' => $source_url,
        'image_attachment_id' => $image_attachment_id,
        'rights_confirmation' => $rights_confirmation,
        'admin_status' => 'pending',
        'admin_note' => '',
    ];

    foreach ($meta as $key => $value) {
        $sanitized = iss_register_feedback_sanitize_meta($key, $value);
        if (($key === 'related_place_id' || $key === 'image_attachment_id') && (int) $sanitized === 0) {
            continue;
        }
        if (!in_array($key, ['related_place_id', 'image_attachment_id', 'rights_confirmation'], true) && $sanitized === '') {
            continue;
        }
        update_post_meta($post_id, $key, $sanitized);
    }

    if ($image_attachment_id > 0) {
        update_post_meta($image_attachment_id, '_iss_register_feedback_pending', 1);
        update_post_meta($image_attachment_id, '_iss_register_feedback_type', $feedback_type);

        if ($related_place_id > 0) {
            update_post_meta($image_attachment_id, '_iss_register_related_place_id', $related_place_id);
        }
    }

    $response = new WP_REST_Response([
        'success' => true,
        'id' => (int) $post_id,
        'admin_status' => 'pending',
    ], 201);

    return $response;
}

add_action('rest_api_init', function () {
    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/feedback', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'iss_register_rest_create_feedback',
        'permission_callback' => 'iss_register_feedback_verify_nonce',
    ]);
});
