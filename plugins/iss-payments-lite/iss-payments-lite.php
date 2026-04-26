<?php
/**
 * Plugin Name: ISS Payments Lite
 * Description: Thin booking and payment entry layer for Industriesalon domain plugins.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    // Public intentionally: visitors submit tour booking requests without a WordPress account.
    register_rest_route('is-tours/v1', '/book', [
        'methods'  => 'POST',
        'callback' => 'iss_payments_lite_create_tour_booking',
        'permission_callback' => '__return_true',
    ]);
});

function iss_payments_lite_create_tour_booking(WP_REST_Request $request) {
    $payload = json_decode($request->get_body(), true);
    if (!is_array($payload)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Invalid payload'], 400);
    }

    $name = sanitize_text_field($payload['name'] ?? '');
    $email = sanitize_email($payload['email'] ?? '');
    $tickets = isset($payload['tickets']) ? (int) $payload['tickets'] : 0;
    $slot_id = isset($payload['slot_id']) ? trim((string) $payload['slot_id']) : '';
    $payment = sanitize_text_field($payload['payment'] ?? '');

    $payload_tag = isset($payload['tag']) ? strtoupper(sanitize_text_field((string) $payload['tag'])) : '';
    $start = sanitize_text_field($payload['start'] ?? '');
    $title = sanitize_text_field($payload['title'] ?? '');
    $source_post_id = isset($payload['source_post_id']) ? (int) $payload['source_post_id'] : 0;
    $source_post_type = sanitize_key($payload['source_post_type'] ?? '');

    $tag = $payload_tag;
    if ($source_post_id > 0) {
        $resolved = strtoupper(sanitize_text_field((string) get_post_meta($source_post_id, 'calendar_tag', true)));
        if ($resolved === '' && function_exists('iss_calendar_resolve_tag_for_source_post_id')) {
            $resolved = iss_calendar_resolve_tag_for_source_post_id($source_post_id);
        }

        if ($tag === '' && $resolved !== '') {
            $tag = $resolved;
        } elseif ($tag !== '' && $resolved !== '' && $tag !== $resolved) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Zuordnung.'], 400);
        }
    }

    $errors = [];
    if ($name === '') {
        $errors[] = 'Name fehlt.';
    }
    if (!is_email($email)) {
        $errors[] = 'Ungültige E-Mail.';
    }
    if ($tickets < 1) {
        $errors[] = 'Bitte mindestens 1 Ticket.';
    }
    if ($slot_id === '') {
        $errors[] = 'Slot fehlt.';
    }
    if (!in_array($payment, ['onsite', 'mollie'], true)) {
        $errors[] = 'Ungültige Zahlungsart.';
    }
    if ($tag === '' && $source_post_id <= 0) {
        $errors[] = 'Keine Zuordnung vorhanden.';
    }

    if ($tag !== '' || $source_post_id > 0) {
        $slots = [];
        if ($tag !== '' && function_exists('is_tours_get_cached_slots_by_tag')) {
            $slots = is_tours_get_cached_slots_by_tag($tag);
        }
        if (empty($slots) && function_exists('is_tours_get_cpt_slots')) {
            $slots = is_tours_get_cpt_slots($tag, $source_post_id);
            if ($tag !== '' && !empty($slots) && function_exists('is_tours_set_cached_slots_by_tag') && function_exists('is_tours_set_cached_source_by_tag')) {
                is_tours_set_cached_slots_by_tag($tag, $slots, 60 * 10);
                is_tours_set_cached_source_by_tag($tag, 'cpt', 60 * 10);
            }
        }

        $found = null;
        foreach ($slots as $slot) {
            if (is_array($slot) && (string) ($slot['id'] ?? '') === (string) $slot_id) {
                $found = $slot;
                break;
            }
        }

        if ($found !== null) {
            if (array_key_exists('available', $found) && $found['available'] !== null && (int) $found['available'] <= 0) {
                $errors[] = 'Slot ist ausgebucht.';
            }
        } else {
            $errors[] = 'Ungültiger Slot.';
        }
    }

    if (!empty($errors)) {
        return new WP_REST_Response(['ok' => false, 'error' => implode(' ', $errors)], 400);
    }

    $entry = [
        'time' => current_time('mysql'),
        'name' => $name,
        'email' => $email,
        'tickets' => $tickets,
        'slot_id' => $slot_id,
        'payment' => $payment,
        'tag' => $tag,
        'start' => $start,
        'title' => $title,
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '',
        'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
    ];

    $requests = get_option('is_tours_booking_requests', []);
    if (!is_array($requests)) {
        $requests = [];
    }
    $requests[] = $entry;
    if (count($requests) > 200) {
        $requests = array_slice($requests, -200);
    }
    update_option('is_tours_booking_requests', $requests, false);

    /**
     * Compatibility hook for existing booking consumers.
     *
     * @param array $entry Sanitized booking entry.
     * @param WP_REST_Request $request Original REST request.
     */
    do_action('is_tours_booking_created', $entry, $request);

    /**
     * New domain-neutral hook for thin booking/payment flows.
     *
     * @param array $entry Sanitized booking entry.
     * @param WP_REST_Request $request Original REST request.
     */
    do_action('iss_payments_lite_booking_created', $entry, $request);

    return new WP_REST_Response(['ok' => true], 200);
}
