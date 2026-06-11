<?php
/**
 * Plugin Name: ISS Payments Lite
 * Description: Thin booking and payment entry layer for Industriesalon domain plugins.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    if (!is_singular('publication')) {
        return;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return;
    }

    $sale_enabled = !empty(get_post_meta($post_id, '_iss_publication_sale_enabled', true));
    $price_cents = (int) get_post_meta($post_id, '_iss_publication_price_cents', true);
    if (!$sale_enabled || $price_cents <= 0) {
        return;
    }

    wp_enqueue_script(
        'iss-payments-lite-publications',
        plugin_dir_url(__FILE__) . 'assets/publication-order.js',
        [],
        filemtime(__DIR__ . '/assets/publication-order.js'),
        true
    );

    wp_localize_script('iss-payments-lite-publications', 'ISS_PUBLICATION_ORDER', [
        'orderUrl' => esc_url_raw(rest_url('iss-payments/v1/publication-order')),
    ]);
});

add_action('wp_footer', function () {
    if (!wp_script_is('iss-payments-lite-publications', 'enqueued')) {
        return;
    }

    static $rendered = false;
    if ($rendered) {
        return;
    }

    $rendered = true;

    echo '<div class="iss-publication-order-modal" data-shared-publication-order-modal="1" hidden>';
    echo '<div class="iss-publication-order-modal__overlay" data-close="1" tabindex="-1"></div>';
    echo '<div class="iss-publication-order-modal__panel" role="dialog" aria-modal="true" aria-label="' . esc_attr__('Publikation bestellen', 'iss-payments-lite') . '">';
    echo '<button type="button" class="iss-publication-order-modal__close" data-close="1" aria-label="' . esc_attr__('Schließen', 'iss-payments-lite') . '">×</button>';
    echo '<div class="iss-publication-order-modal__content"></div>';
    echo '</div>';
    echo '</div>';
}, 20);

add_action('rest_api_init', function () {
    // Public intentionally: visitors submit tour booking requests without a WordPress account.
    register_rest_route('is-tours/v1', '/book', [
        'methods'  => 'POST',
        'callback' => 'iss_payments_lite_create_tour_booking',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('iss-payments/v1', '/publication-order', [
        'methods'  => 'POST',
        'callback' => 'iss_payments_lite_create_publication_order',
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
        $resolved = '';
        if (function_exists('iss_occurrences_resolve_tag_for_source_post_id')) {
            $resolved = iss_occurrences_resolve_tag_for_source_post_id($source_post_id);
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
        if (empty($slots) && function_exists('is_tours_get_occurrence_slots')) {
            $slots = is_tours_get_occurrence_slots($tag, $source_post_id);
            if ($tag !== '' && !empty($slots) && function_exists('is_tours_set_cached_slots_by_tag') && function_exists('is_tours_set_cached_source_by_tag')) {
                is_tours_set_cached_slots_by_tag($tag, $slots, 60 * 10);
                is_tours_set_cached_source_by_tag($tag, 'occurrences', 60 * 10);
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

function iss_payments_lite_create_publication_order(WP_REST_Request $request) {
    $payload = json_decode($request->get_body(), true);
    if (!is_array($payload)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Invalid payload'], 400);
    }

    $publication_id = isset($payload['publication_id']) ? (int) $payload['publication_id'] : 0;
    $name = sanitize_text_field($payload['name'] ?? '');
    $email = sanitize_email($payload['email'] ?? '');
    $quantity = isset($payload['quantity']) ? (int) $payload['quantity'] : 0;
    $payment = sanitize_text_field($payload['payment'] ?? '');

    $errors = [];

    if ($publication_id <= 0 || get_post_type($publication_id) !== 'publication') {
        $errors[] = 'Ungültige Publikation.';
    }

    $sale_enabled = !empty(get_post_meta($publication_id, '_iss_publication_sale_enabled', true));
    $price_cents = (int) get_post_meta($publication_id, '_iss_publication_price_cents', true);
    if (!$sale_enabled || $price_cents <= 0) {
        $errors[] = 'Diese Publikation ist derzeit nicht bestellbar.';
    }

    if ($name === '') {
        $errors[] = 'Name fehlt.';
    }

    if (!is_email($email)) {
        $errors[] = 'Ungültige E-Mail.';
    }

    if ($quantity < 1) {
        $errors[] = 'Bitte mindestens 1 Exemplar.';
    }

    if (!in_array($payment, ['onsite', 'mollie'], true)) {
        $errors[] = 'Ungültige Zahlungsart.';
    }

    if (!empty($errors)) {
        return new WP_REST_Response(['ok' => false, 'error' => implode(' ', $errors)], 400);
    }

    $entry = [
        'time' => current_time('mysql'),
        'publication_id' => $publication_id,
        'title' => get_the_title($publication_id),
        'name' => $name,
        'email' => $email,
        'quantity' => $quantity,
        'payment' => $payment,
        'price_cents' => $price_cents,
        'amount_cents' => $price_cents * $quantity,
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '',
        'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
    ];

    $orders = get_option('iss_publication_order_requests', []);
    if (!is_array($orders)) {
        $orders = [];
    }
    $orders[] = $entry;
    if (count($orders) > 200) {
        $orders = array_slice($orders, -200);
    }
    update_option('iss_publication_order_requests', $orders, false);

    do_action('iss_payments_lite_publication_order_created', $entry, $request);
    do_action('iss_payments_lite_order_created', 'publication', $entry, $request);

    return new WP_REST_Response(['ok' => true], 200);
}

add_filter('iss_publications_order_button_html', function ($html, $post_id, $context) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return $html;
    }

    $label = isset($context['label']) ? trim((string) $context['label']) : '';
    if ($label === '') {
        $label = 'Publikation bestellen';
    }

    $amount = isset($context['amount']) ? (int) $context['amount'] : 0;
    $title = isset($context['title']) ? (string) $context['title'] : get_the_title($post_id);

    return sprintf(
        '<div class="iss-publication-order-panel__actions"><button type="button" class="iss-publication-order-trigger" data-publication-id="%1$d" data-title="%2$s" data-amount="%3$d" data-label="%4$s">%5$s</button></div>',
        $post_id,
        esc_attr($title),
        $amount,
        esc_attr($label),
        esc_html($label)
    );
}, 10, 3);
