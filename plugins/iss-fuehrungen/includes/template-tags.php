<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_fuehrung_get_color_class($post_id) {
    $color = get_post_meta($post_id, 'tour_color', true);
    $color = $color ?: 'red';
    return 'iss-fuehrung--' . sanitize_html_class($color);
}

function iss_fuehrung_render_facts($post_id, array $args = []) {
    $items = [];
    $include_related_places = !empty($args['include_related_places']);

    foreach ([
        'Dauer' => get_post_meta($post_id, 'duration', true),
        'Treffpunkt' => get_post_meta($post_id, 'meeting_point', true),
        'Zielgruppe' => get_post_meta($post_id, 'target_group', true),
        'Preis' => get_post_meta($post_id, 'price_note', true),
    ] as $label => $value) {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }

        $items[] = [
            'label' => $label,
            'value' => $value,
            'html' => false,
        ];
    }

    if ($include_related_places && function_exists('iss_relations_get_related_place_items') && function_exists('iss_relations_render_related_place_links_html')) {
        $related_places = iss_relations_get_related_place_items($post_id);
        $related_links = iss_relations_render_related_place_links_html($post_id);

        if ($related_places && $related_links !== '') {
            $items[] = [
                'label' => count($related_places) > 1 ? __('Orte', 'iss-fuehrungen') : __('Ort', 'iss-fuehrungen'),
                'value' => $related_links,
                'html' => true,
            ];
        }
    }

    if (!$items) {
        return '';
    }

    ob_start();
    echo '<div class="iss-fuehrung-facts">';
    foreach ($items as $item) {
        $modifier = sanitize_html_class((string) sanitize_title((string) $item['label']));
        echo '<div class="iss-fuehrung-fact iss-fuehrung-fact--' . esc_attr($modifier) . '">';
        echo '<div class="iss-fuehrung-fact__label">' . esc_html((string) $item['label']) . '</div>';
        echo '<div class="iss-fuehrung-fact__value">';
        echo !empty($item['html']) ? wp_kses_post((string) $item['value']) : esc_html((string) $item['value']);
        echo '</div>';
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

    $facts = iss_fuehrung_render_facts($post_id, [
        'include_related_places' => false,
    ]);
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

function iss_fuehrung_get_route_station_title(array $item): string
{
    $title = trim((string) ($item['route_title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    $label = trim((string) ($item['label'] ?? ''));
    if ($label !== '') {
        return $label;
    }

    return trim((string) ($item['title'] ?? ''));
}

function iss_fuehrung_get_route_station_teaser(array $item): string
{
    $teaser = trim((string) ($item['route_teaser'] ?? ''));
    if ($teaser !== '') {
        return $teaser;
    }

    $place = $item['post'] ?? null;
    if (!$place instanceof WP_Post) {
        return '';
    }

    $excerpt = trim((string) get_the_excerpt($place));
    if ($excerpt !== '') {
        return $excerpt;
    }

    return wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $place->ID)), 24, '…');
}

function iss_fuehrung_get_route_station_linked_post(int $post_id): ?WP_Post
{
    if ($post_id <= 0) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return null;
    }

    if ($post->post_status !== 'publish') {
        return null;
    }

    return $post;
}

function iss_fuehrung_get_route_station_detail_label(WP_Post $post): string
{
    $post_type = (string) get_post_type($post);

    if ($post_type === 'archivobjekt') {
        return __('Objekt der Station', 'iss-fuehrungen');
    }

    if ($post_type === 'publication') {
        return __('Publikation', 'iss-fuehrungen');
    }

    if ($post_type === 'veranstaltung') {
        return __('Veranstaltung', 'iss-fuehrungen');
    }

    return __('Mehr zur Station', 'iss-fuehrungen');
}

function iss_fuehrung_render_route_station_detail_card(WP_Post $post): string
{
    $permalink = get_permalink($post);
    if (!is_string($permalink) || trim($permalink) === '') {
        return '';
    }

    $title = trim((string) get_the_title($post));
    if ($title === '') {
        return '';
    }

    $excerpt = trim((string) get_the_excerpt($post));
    if ($excerpt === '') {
        $excerpt = wp_strip_all_tags((string) get_post_field('post_content', $post->ID));
    }

    $excerpt = preg_replace('#https?://\S+#', '', $excerpt);
    $excerpt = trim((string) $excerpt);
    if ($excerpt !== '') {
        $excerpt = wp_trim_words($excerpt, 16, '…');
    }

    $label = iss_fuehrung_get_route_station_detail_label($post);
    $image = has_post_thumbnail($post) ? get_the_post_thumbnail($post, 'medium_large') : '';

    ob_start();
    echo '<article class="iss-tour-route__detail-card">';
    echo '<div class="iss-tour-route__figure-kicker">' . esc_html($label) . '</div>';

    if ($image !== '') {
        echo '<a class="iss-tour-route__detail-card-image" href="' . esc_url($permalink) . '">';
        echo $image;
        echo '</a>';
    }

    echo '<div class="iss-tour-route__detail-card-body">';
    echo '<h4 class="iss-tour-route__detail-card-title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h4>';
    if ($excerpt !== '') {
        echo '<p class="iss-tour-route__detail-card-text">' . esc_html($excerpt) . '</p>';
    }
    echo '<p class="iss-tour-route__detail-card-link"><a class="iss-action-link" href="' . esc_url($permalink) . '">' . esc_html__('Mehr zum Thema', 'iss-fuehrungen') . '</a></p>';
    echo '</div>';
    echo '</article>';

    return (string) ob_get_clean();
}

function iss_fuehrung_get_public_station_image_entry(int $place_id, string $meta_key): ?array
{
    $entries = get_post_meta($place_id, $meta_key, true);
    if (!is_array($entries) || !$entries) {
        return null;
    }

    $featured = null;
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $visibility = sanitize_key((string) ($entry['visibility'] ?? ''));
        if ($visibility !== 'public') {
            continue;
        }

        $media_id = absint($entry['media_id'] ?? 0);
        $url = trim((string) ($entry['url'] ?? ''));
        if ($media_id <= 0 && $url === '') {
            continue;
        }

        if (!empty($entry['is_featured'])) {
            $featured = $entry;
            break;
        }

        if ($featured === null) {
            $featured = $entry;
        }
    }

    return $featured;
}

function iss_fuehrung_render_station_media_figure(array $entry, string $label, string $permalink = ''): string
{
    $media_id = absint($entry['media_id'] ?? 0);
    $caption = trim((string) ($entry['caption'] ?? ''));
    $year = trim((string) ($entry['year'] ?? ''));
    $source = trim((string) ($entry['source'] ?? ''));
    $image_html = '';

    if ($media_id > 0) {
        $image_html = wp_get_attachment_image($media_id, 'large');
    }

    if ($image_html === '') {
        $url = trim((string) ($entry['url'] ?? ''));
        if ($url !== '') {
            $image_html = '<img src="' . esc_url($url) . '" alt="' . esc_attr($caption !== '' ? $caption : $label) . '">';
        }
    }

    if ($image_html === '') {
        return '';
    }

    ob_start();
    echo '<figure class="iss-tour-route__figure">';
    echo '<div class="iss-tour-route__figure-kicker">' . esc_html($label) . '</div>';
    if ($permalink !== '') {
        echo '<a class="iss-tour-route__station-image" href="' . esc_url($permalink) . '">';
        echo $image_html;
        echo '</a>';
    } else {
        echo '<div class="iss-tour-route__station-image">';
        echo $image_html;
        echo '</div>';
    }

    if ($caption !== '' || $year !== '' || $source !== '') {
        echo '<figcaption class="iss-tour-route__figure-caption">';
        if ($caption !== '') {
            echo '<span class="iss-tour-route__figure-caption-main">' . esc_html($caption) . '</span>';
        }

        $meta_parts = array_values(array_filter([$year, $source]));
        if ($meta_parts) {
            echo '<span class="iss-tour-route__figure-caption-meta">' . esc_html(implode(' · ', $meta_parts)) . '</span>';
        }
        echo '</figcaption>';
    }
    echo '</figure>';

    return (string) ob_get_clean();
}

function iss_fuehrung_get_route_summary(array $items, int $post_id): string
{
    $count = count($items);
    if ($count === 0) {
        return '';
    }

    $first_item = reset($items);
    $last_item = end($items);
    $first_title = is_array($first_item) ? iss_fuehrung_get_route_station_title($first_item) : '';
    $last_title = is_array($last_item) ? iss_fuehrung_get_route_station_title($last_item) : '';
    $summary = '';

    if ($count === 1 && $first_title !== '') {
        $summary = sprintf(
            /* translators: %s is the station title. */
            __('Die Führung konzentriert sich auf die Station %s.', 'iss-fuehrungen'),
            $first_title
        );
    } elseif ($first_title !== '' && $last_title !== '') {
        $summary = sprintf(
            /* translators: 1: stop count, 2: first stop, 3: last stop. */
            __('Die Route führt über %1$d Stationen von %2$s bis %3$s.', 'iss-fuehrungen'),
            $count,
            $first_title,
            $last_title
        );
    } else {
        $summary = sprintf(
            /* translators: %d is the number of stops. */
            __('Die Route führt über %d Stationen.', 'iss-fuehrungen'),
            $count
        );
    }

    $duration = trim((string) get_post_meta($post_id, 'duration', true));
    if ($duration !== '') {
        $summary .= ' ' . sprintf(
            /* translators: %s is the duration value. */
            __('Geplante Dauer: %s.', 'iss-fuehrungen'),
            $duration
        );
    }

    return trim($summary);
}

function iss_fuehrung_render_route_block($attributes = [], $content = '')
{
    $post_id = iss_fuehrung_block_resolve_post_id($attributes);
    if ($post_id <= 0 || !function_exists('iss_relations_get_ordered_route_items')) {
        return '';
    }

    $items = iss_relations_get_ordered_route_items($post_id);
    if (!$items) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-tour-route section section--plain iss-tour-route'])
        : 'class="wp-block-iss-tour-route section section--plain iss-tour-route"';

    ob_start();
    echo '<section ' . $wrapper . '>';
    echo '<div class="iss-container">';
    echo '<div class="iss-tour-route__overview">';
    echo '<div class="iss-heading iss-tour-route__intro">';
    echo '<p class="iss-kicker iss-kicker--compact">Auf einen Blick</p>';
    echo '<h2 class="iss-heading__title">Die Route</h2>';
    $summary = iss_fuehrung_get_route_summary($items, $post_id);
    if ($summary !== '') {
        echo '<p class="iss-heading__text">' . esc_html($summary) . '</p>';
    }
    echo '<p class="iss-tour-route__overview-link"><a class="iss-action-link" href="#tour-stations">' . esc_html__('Zur Detailroute', 'iss-fuehrungen') . '</a></p>';
    echo '</div>';
    echo '</div>';

    echo '<ol class="iss-tour-route__rail" aria-label="' . esc_attr__('Route dieser Führung', 'iss-fuehrungen') . '">';
    foreach ($items as $index => $item) {
        $title = iss_fuehrung_get_route_station_title($item);
        $number = $index + 1;
        $anchor = 'tour-stop-' . $number;

        echo '<li class="iss-tour-route__rail-stop">';
        echo '<span class="iss-tour-route__rail-index">' . esc_html((string) $number) . '</span>';
        if ($title !== '') {
            echo '<a class="iss-tour-route__rail-link" href="#' . esc_attr($anchor) . '" data-route-target="' . esc_attr($anchor) . '" aria-controls="' . esc_attr($anchor) . '">' . esc_html($title) . '</a>';
        } else {
            echo '<span class="iss-tour-route__rail-link">' . esc_html($title) . '</span>';
        }
        echo '</li>';
    }
    echo '</ol>';

    echo '<div id="tour-stations" class="iss-tour-route__carousel">';
    echo '<div class="iss-tour-route__viewport" data-route-viewport>';
    echo '<div class="iss-tour-route__stations" data-route-track>';
    foreach ($items as $index => $item) {
        $place = $item['post'] ?? null;
        if (!$place instanceof WP_Post) {
            continue;
        }

        $title = iss_fuehrung_get_route_station_title($item);
        $teaser = iss_fuehrung_get_route_station_teaser($item);
        $permalink = trim((string) ($item['permalink'] ?? ''));
        $object_post = iss_fuehrung_get_route_station_linked_post((int) ($item['station_object_id'] ?? 0));
        $story_post = iss_fuehrung_get_route_station_linked_post((int) ($item['station_story_id'] ?? 0));
        $detail_post = $object_post instanceof WP_Post ? $object_post : $story_post;
        $detail_post_id = $detail_post instanceof WP_Post ? (int) $detail_post->ID : 0;
        $archive_image = iss_fuehrung_get_public_station_image_entry((int) $place->ID, 'archive_images');
        $current_image = iss_fuehrung_get_public_station_image_entry((int) $place->ID, 'current_images');
        $media_html = '';
        $station_number = (string) ($index + 1);
        $station_anchor = 'tour-stop-' . $station_number;
        $station_title_id = $station_anchor . '-title';

        if (is_array($archive_image)) {
            $media_html .= iss_fuehrung_render_station_media_figure($archive_image, __('Damals', 'iss-fuehrungen'), $permalink);
        }

        if (is_array($current_image)) {
            $media_html .= iss_fuehrung_render_station_media_figure($current_image, __('Heute', 'iss-fuehrungen'), $permalink);
        }

        echo '<article id="' . esc_attr($station_anchor) . '" class="iss-tour-route__station" data-route-slide aria-labelledby="' . esc_attr($station_title_id) . '">';
        echo '<div class="iss-tour-route__station-body">';
        echo '<p class="iss-tour-route__station-index">' . esc_html($station_number) . '</p>';
        echo '<div class="iss-tour-route__station-copy">';
        echo '<h3 id="' . esc_attr($station_title_id) . '" class="iss-tour-route__station-title">';
        if ($permalink !== '' && $title !== '') {
            echo '<a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a>';
        } else {
            echo esc_html($title);
        }
        echo '</h3>';

        if ($teaser !== '') {
            echo '<p class="iss-tour-route__station-text">' . esc_html($teaser) . '</p>';
        }

        echo '<div class="iss-tour-route__station-links">';
        if ($permalink !== '') {
            echo '<a class="iss-action-link" href="' . esc_url($permalink) . '">' . esc_html__('Ort ansehen', 'iss-fuehrungen') . '</a>';
        }
        if ($object_post instanceof WP_Post && (int) $object_post->ID !== $detail_post_id) {
            echo '<a class="iss-action-link" href="' . esc_url((string) get_permalink($object_post)) . '">' . esc_html__('Archivobjekt', 'iss-fuehrungen') . '</a>';
        }
        if ($story_post instanceof WP_Post && (int) $story_post->ID !== $detail_post_id) {
            echo '<a class="iss-action-link" href="' . esc_url((string) get_permalink($story_post)) . '">' . esc_html__('Hintergrund', 'iss-fuehrungen') . '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="iss-tour-route__station-media">';
        $detail_card_html = $detail_post instanceof WP_Post ? iss_fuehrung_render_route_station_detail_card($detail_post) : '';
        if ($media_html !== '' || has_post_thumbnail($place) || $detail_card_html !== '') {
            echo '<div class="iss-tour-route__station-media-grid' . ($detail_card_html !== '' ? ' has-detail' : '') . '">';

            if ($media_html !== '') {
                echo '<div class="iss-tour-route__station-pair">';
                echo $media_html;
                echo '</div>';
            } elseif (has_post_thumbnail($place)) {
                echo '<div class="iss-tour-route__station-pair iss-tour-route__station-pair--single">';
                if ($permalink !== '') {
                    echo '<a class="iss-tour-route__station-image" href="' . esc_url($permalink) . '">';
                    echo get_the_post_thumbnail($place, 'large');
                    echo '</a>';
                } else {
                    echo '<div class="iss-tour-route__station-image">';
                    echo get_the_post_thumbnail($place, 'large');
                    echo '</div>';
                }
                echo '</div>';
            }

            if ($detail_card_html !== '') {
                echo $detail_card_html;
            }

            echo '</div>';
        } else {
            echo '<div class="iss-tour-route__station-placeholder" aria-hidden="true"></div>';
        }
        echo '</div>';
        echo '</article>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>';
    echo '</section>';
    return (string) ob_get_clean();
}
