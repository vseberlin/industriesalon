<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_fuehrung_get_color_class($post_id) {
    $color = get_post_meta($post_id, 'tour_color', true);
    $color = $color ?: 'red';
    return 'iss-fuehrung--' . sanitize_html_class($color);
}

function iss_fuehrung_render_facts($post_id) {
    $items = [
        'Dauer'      => get_post_meta($post_id, 'duration', true),
        'Treffpunkt' => get_post_meta($post_id, 'meeting_point', true),
        'Zielgruppe' => get_post_meta($post_id, 'target_group', true),
        'Preis'      => get_post_meta($post_id, 'price_note', true),
    ];

    $items = array_filter($items, static function ($value) {
        return trim((string) $value) !== '';
    });

    if (!$items) {
        return '';
    }

    ob_start();
    echo '<div class="iss-fuehrung-facts">';
    foreach ($items as $label => $value) {
        echo '<div class="iss-fuehrung-fact">';
        echo '<div class="iss-fuehrung-fact__label">' . esc_html($label) . '</div>';
        echo '<div class="iss-fuehrung-fact__value">' . esc_html((string) $value) . '</div>';
        echo '</div>';
    }
    echo '</div>';
    return (string) ob_get_clean();
}

function iss_fuehrung_get_availability_label($availability) {
    $availability = sanitize_key((string) $availability);

    if ($availability === 'available') {
        return __('Plätze verfügbar', 'iss-fuehrungen');
    }
    if ($availability === 'sold_out') {
        return __('Ausgebucht', 'iss-fuehrungen');
    }
    if ($availability === 'inquiry') {
        return __('Auf Anfrage', 'iss-fuehrungen');
    }

    return '';
}

function iss_fuehrung_render_booking_box($post_id) {
    $mode = iss_fuehrung_get_effective_booking_mode($post_id);
    $next_event = iss_fuehrung_get_next_event($post_id);
    $booking_note = trim((string) get_post_meta($post_id, 'booking_note', true));
    $inquiry = iss_fuehrung_get_inquiry_data($post_id);
    $inquiry_url = trim((string) ($inquiry['url'] ?? ''));
    $inquiry_label = trim((string) ($inquiry['label'] ?? ''));
    $inquiry_note = trim((string) ($inquiry['note'] ?? ''));
    $archive_link = get_post_type_archive_link(ISS_FUEHRUNGEN_POST_TYPE);

    ob_start();
    echo '<aside class="iss-fuehrung-booking">';
    echo '<div class="iss-fuehrung-booking__inner">';
    echo '<p class="iss-kicker iss-kicker--compact">Buchung</p>';

    if ($mode === 'on_demand') {
        echo '<h2 class="iss-fuehrung-booking__title">' . esc_html__('Individuelle Anfrage', 'iss-fuehrungen') . '</h2>';

        if ($inquiry_note !== '') {
            echo '<p class="iss-fuehrung-booking__note">' . esc_html($inquiry_note) . '</p>';
        } elseif ($booking_note !== '') {
            echo '<p class="iss-fuehrung-booking__note">' . esc_html($booking_note) . '</p>';
        } else {
            echo '<p class="iss-fuehrung-booking__note">' . esc_html__('Diese Führung wird nach individueller Absprache angeboten.', 'iss-fuehrungen') . '</p>';
        }

        echo '<div class="iss-fuehrung-booking__actions">';
        if ($inquiry_url !== '') {
            echo '<a class="wp-element-button" href="' . esc_url($inquiry_url) . '">' . esc_html($inquiry_label) . '</a>';
        }
        if ($archive_link) {
            echo '<a class="iss-fuehrung-booking__secondary" href="' . esc_url($archive_link) . '">' . esc_html__('Alle Führungen', 'iss-fuehrungen') . '</a>';
        }
        echo '</div>';
    } elseif ($next_event instanceof WP_Post) {
        $date_label = iss_fuehrung_get_event_start_label($next_event->ID);
        $availability = trim((string) get_post_meta($next_event->ID, 'availability_state', true));
        $availability_label = iss_fuehrung_get_availability_label($availability);
        $booking_url = iss_fuehrung_get_event_booking_url($next_event->ID, $post_id);
        $slot_id = trim((string) get_post_meta($next_event->ID, 'external_id', true));
        $slot_start = trim((string) get_post_meta($next_event->ID, 'event_start', true));
        $slot_title = trim((string) get_the_title($next_event->ID));
        $is_slot_trigger = ($slot_id !== '' && $slot_start !== '');

        echo '<h2 class="iss-fuehrung-booking__title">Nächster Termin</h2>';
        echo '<p class="iss-fuehrung-next-date">' . esc_html($date_label) . '</p>';

        if ($availability_label !== '') {
            echo '<p class="iss-fuehrung-booking__status">' . esc_html($availability_label) . '</p>';
        }

        if ($booking_note !== '') {
            echo '<p class="iss-fuehrung-booking__note">' . esc_html($booking_note) . '</p>';
        }

        echo '<div class="iss-fuehrung-booking__actions">';
        if ($booking_url !== '') {
            $button_classes = 'wp-element-button';
            if ($is_slot_trigger) {
                $button_classes .= ' js-is-tour-slot-trigger';
            }

            $button_attrs = '';
            if ($is_slot_trigger) {
                $button_attrs .= ' data-slot-id="' . esc_attr($slot_id) . '"';
                $button_attrs .= ' data-start="' . esc_attr($slot_start) . '"';
                $button_attrs .= ' data-title="' . esc_attr($slot_title) . '"';
                $button_attrs .= ' data-source-post-id="' . esc_attr((string) $post_id) . '"';
                $button_attrs .= ' data-source-post-type="' . esc_attr(ISS_FUEHRUNGEN_POST_TYPE) . '"';
            }

            echo '<a class="' . esc_attr($button_classes) . '" href="' . esc_url($booking_url) . '"' . $button_attrs . '>Buchen</a>';
        }
        echo '<a class="iss-fuehrung-booking__secondary" href="#termine">Alle Termine</a>';
        if ($mode === 'hybrid' && $inquiry_url !== '') {
            echo '<a class="iss-fuehrung-booking__secondary" href="' . esc_url($inquiry_url) . '">' . esc_html($inquiry_label) . '</a>';
        }
        echo '</div>';

        if ($mode === 'hybrid' && $inquiry_note !== '') {
            echo '<p class="iss-fuehrung-booking__note">' . esc_html($inquiry_note) . '</p>';
        }
    } else {
        if ($mode === 'hybrid') {
            echo '<h2 class="iss-fuehrung-booking__title">' . esc_html__('Aktuell keine Termine online', 'iss-fuehrungen') . '</h2>';
            if ($inquiry_note !== '') {
                echo '<p class="iss-fuehrung-booking__note">' . esc_html($inquiry_note) . '</p>';
            } elseif ($booking_note !== '') {
                echo '<p class="iss-fuehrung-booking__note">' . esc_html($booking_note) . '</p>';
            } else {
                echo '<p class="iss-fuehrung-booking__note">' . esc_html__('Diese Führung ist aktuell nur auf Anfrage verfügbar.', 'iss-fuehrungen') . '</p>';
            }
        } else {
            echo '<h2 class="iss-fuehrung-booking__title">Aktuell keine Termine online</h2>';
            if ($booking_note !== '') {
                echo '<p class="iss-fuehrung-booking__note">' . esc_html($booking_note) . '</p>';
            } else {
                echo '<p class="iss-fuehrung-booking__note">Für Gruppen, Sonderformate oder Rückfragen nehmen Sie bitte Kontakt mit dem Industriesalon auf.</p>';
            }
        }

        echo '<div class="iss-fuehrung-booking__actions">';
        if ($mode === 'hybrid' && $inquiry_url !== '') {
            echo '<a class="wp-element-button" href="' . esc_url($inquiry_url) . '">' . esc_html($inquiry_label) . '</a>';
        }
        if ($archive_link) {
            echo '<a class="iss-fuehrung-booking__secondary" href="' . esc_url($archive_link) . '">Alle Führungen</a>';
        }
        echo '</div>';
    }

    echo '</div>';
    echo '</aside>';
    return (string) ob_get_clean();
}

function iss_fuehrung_render_archive_card($post_id) {
    $permalink = get_permalink($post_id);
    $badge = trim((string) get_post_meta($post_id, 'tour_badge', true));
    $meta = iss_fuehrung_get_card_meta($post_id);
    $next_event = iss_fuehrung_get_next_event($post_id);
    $mode = iss_fuehrung_get_effective_booking_mode($post_id);
    $color_class = iss_fuehrung_get_color_class($post_id);

    ob_start();
    echo '<article class="iss-card iss-fuehrung-card ' . esc_attr($color_class) . '">';

    if (has_post_thumbnail($post_id)) {
        echo '<a class="iss-card__media" href="' . esc_url($permalink) . '">';
        echo get_the_post_thumbnail($post_id, 'large');
        echo '</a>';
    }

    echo '<div class="iss-card__body">';
    if ($badge !== '') {
        echo '<p class="iss-kicker iss-kicker--compact">' . esc_html($badge) . '</p>';
    }

    echo '<h2 class="iss-card__title"><a href="' . esc_url($permalink) . '">' . esc_html(get_the_title($post_id)) . '</a></h2>';

    $excerpt = get_the_excerpt($post_id);
    if ($excerpt !== '') {
        echo '<p class="iss-card__text">' . esc_html($excerpt) . '</p>';
    }

    if ($meta) {
        echo '<p class="iss-fuehrung-card__meta">' . esc_html(implode(' · ', $meta)) . '</p>';
    }

    if ($next_event instanceof WP_Post) {
        echo '<p class="iss-fuehrung-card__date"><strong>Nächster Termin:</strong> ' . esc_html(iss_fuehrung_get_event_start_label($next_event->ID)) . '</p>';
    } elseif ($mode === 'on_demand') {
        echo '<p class="iss-fuehrung-card__date"><strong>Status:</strong> ' . esc_html__('Auf Anfrage buchbar', 'iss-fuehrungen') . '</p>';
    } elseif ($mode === 'hybrid') {
        echo '<p class="iss-fuehrung-card__date"><strong>Status:</strong> ' . esc_html__('Aktuell keine Termine online · Anfrage möglich', 'iss-fuehrungen') . '</p>';
    } else {
        echo '<p class="iss-fuehrung-card__date"><strong>Status:</strong> Aktuell keine Termine online</p>';
    }

    echo '<div class="iss-card__footer">';
    echo '<a class="iss-card__link" href="' . esc_url($permalink) . '">Mehr</a>';
    echo '</div>';
    echo '</div>';
    echo '</article>';

    return (string) ob_get_clean();
}

function iss_fuehrung_block_resolve_post_id($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];

    if (isset($attributes['postId'])) {
        $post_id = (int) $attributes['postId'];
        if ($post_id > 0) {
            return $post_id;
        }
    }

    $post_id = (int) get_the_ID();
    return $post_id > 0 ? $post_id : 0;
}

function iss_fuehrung_render_facts_block($attributes = [], $content = '') {
    $post_id = iss_fuehrung_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $facts = iss_fuehrung_render_facts($post_id);
    if ($facts === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-tour-facts'])
        : 'class="wp-block-iss-tour-facts"';

    return '<div ' . $wrapper . '>' . $facts . '</div>';
}

function iss_fuehrung_render_booking_panel_block($attributes = [], $content = '') {
    $post_id = iss_fuehrung_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $panel = iss_fuehrung_render_booking_box($post_id);
    if ($panel === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-tour-booking-panel'])
        : 'class="wp-block-iss-tour-booking-panel"';

    return '<div ' . $wrapper . '>' . $panel . '</div>';
}

function iss_fuehrung_get_hero_gallery_ids($post_id) {
    $raw = (string) get_post_meta($post_id, 'hero_gallery_ids', true);
    if ($raw === '') {
        return [];
    }

    $ids = array_filter(array_map('absint', preg_split('/\s*,\s*/', $raw)));
    if (!$ids) {
        return [];
    }

    return array_values(array_unique($ids));
}

function iss_fuehrung_render_hero_gallery_block($attributes = [], $content = '') {
    $post_id = iss_fuehrung_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $ids = iss_fuehrung_get_hero_gallery_ids($post_id);
    if (!$ids) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-tour-hero-gallery iss-tour-hero-gallery'])
        : 'class="wp-block-iss-tour-hero-gallery iss-tour-hero-gallery"';

    ob_start();
    echo '<div ' . $wrapper . '>';

    foreach ($ids as $index => $attachment_id) {
        $thumb = wp_get_attachment_image($attachment_id, 'medium', false, ['class' => 'iss-tour-hero-gallery__thumb-img']);
        $full_url = wp_get_attachment_image_url($attachment_id, 'large');

        if (!$thumb || !$full_url) {
            continue;
        }

        $full_srcset = wp_get_attachment_image_srcset($attachment_id, 'large');
        $full_sizes = wp_get_attachment_image_sizes($attachment_id, 'large');
        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $alt = is_string($alt) ? $alt : '';

        echo '<button type="button" class="iss-tour-hero-gallery__thumb' . ($index === 0 ? ' is-active' : '') . '"';
        echo ' data-hero-src="' . esc_url($full_url) . '"';
        if ($full_srcset) {
            echo ' data-hero-srcset="' . esc_attr($full_srcset) . '"';
        }
        if ($full_sizes) {
            echo ' data-hero-sizes="' . esc_attr($full_sizes) . '"';
        }
        echo ' data-hero-alt="' . esc_attr($alt) . '"';
        echo ' aria-label="' . esc_attr__('Hero-Bild anzeigen', 'iss-fuehrungen') . '">';
        echo $thumb;
        echo '</button>';
    }

    echo '</div>';
    return (string) ob_get_clean();
}
