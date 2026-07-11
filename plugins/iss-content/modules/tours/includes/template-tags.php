<?php

if (!defined('ABSPATH')) {
    exit;
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

function iss_fuehrung_render_inquiry_trigger($post_id, string $label, string $classes = 'wp-element-button'): string {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $label = trim($label);
    if ($label === '') {
        $label = __('Anfrage senden', 'iss-fuehrungen');
    }

    $classes = trim($classes . ' js-iss-tour-inquiry-trigger');
    $attrs = ' data-calendar-mode="inquiry"';
    $attrs .= ' data-title="' . esc_attr(get_the_title($post_id)) . '"';
    $attrs .= ' data-source-post-id="' . esc_attr((string) $post_id) . '"';
    $attrs .= ' data-source-post-type="' . esc_attr(ISS_FUEHRUNGEN_POST_TYPE) . '"';
    $attrs .= ' data-item-type="tour"';

    return '<a class="' . esc_attr($classes) . '" href="#tour-anfrage"' . $attrs . '>' . esc_html($label) . '</a>';
}

function iss_fuehrung_render_booking_box($post_id) {
    $mode = iss_fuehrung_get_effective_booking_mode($post_id);
    $next_event = iss_fuehrung_get_next_event($post_id);
    $booking_note = trim((string) get_post_meta($post_id, 'booking_note', true));
    $inquiry = iss_fuehrung_get_inquiry_data($post_id);
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
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns fully escaped inquiry trigger markup.
        echo iss_fuehrung_render_inquiry_trigger($post_id, $inquiry_label);
        if ($archive_link) {
            echo '<a class="iss-fuehrung-booking__secondary" href="' . esc_url($archive_link) . '">' . esc_html__('Alle Führungen', 'iss-fuehrungen') . '</a>';
        }
        echo '</div>';

        if (function_exists('iss_programm_enqueue_calendar_assets')) {
            iss_programm_enqueue_calendar_assets();
        }
    } elseif ($next_event instanceof WP_Post || is_array($next_event)) {
        $date_label = iss_fuehrung_get_event_start_label($next_event);
        $availability = $next_event instanceof WP_Post
            ? trim((string) get_post_meta($next_event->ID, 'availability_state', true))
            : trim((string) ($next_event['availability_state'] ?? ''));
        $availability_label = iss_fuehrung_get_availability_label($availability);
        $booking_url = iss_fuehrung_get_event_booking_url($next_event, $post_id);
        if ($next_event instanceof WP_Post) {
            $slot_title = trim((string) get_the_title($next_event->ID));
        } else {
            $slot_title = trim((string) ($next_event['title'] ?? get_the_title($post_id)));
        }
        $should_enqueue_calendar_assets = false;

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
            $button_classes = 'wp-element-button js-iss-occurrence-calendar-trigger';
            $should_enqueue_calendar_assets = true;

            $button_attrs = '';
            $calendar_title = trim((string) get_the_title($post_id));
            $button_attrs .= ' data-title="' . esc_attr($calendar_title !== '' ? $calendar_title : $slot_title) . '"';
            $button_attrs .= ' data-source-post-id="' . esc_attr((string) $post_id) . '"';
            $button_attrs .= ' data-source-post-type="' . esc_attr(ISS_FUEHRUNGEN_POST_TYPE) . '"';
            $button_attrs .= ' data-item-type="tour"';

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute string is built from escaped values above.
            echo '<a class="' . esc_attr($button_classes) . '" href="' . esc_url($booking_url) . '"' . $button_attrs . '>Buchen</a>';
        }
        if ($mode === 'hybrid') {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns fully escaped inquiry trigger markup.
            echo iss_fuehrung_render_inquiry_trigger($post_id, $inquiry_label, 'iss-fuehrung-booking__secondary');
            $should_enqueue_calendar_assets = true;
        }
        echo '</div>';

        if ($mode === 'hybrid' && $inquiry_note !== '') {
            echo '<p class="iss-fuehrung-booking__note">' . esc_html($inquiry_note) . '</p>';
        }

        if ($should_enqueue_calendar_assets && function_exists('iss_programm_enqueue_calendar_assets')) {
            iss_programm_enqueue_calendar_assets();
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
        if ($mode === 'hybrid') {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns fully escaped inquiry trigger markup.
            echo iss_fuehrung_render_inquiry_trigger($post_id, $inquiry_label);
            if (function_exists('iss_programm_enqueue_calendar_assets')) {
                iss_programm_enqueue_calendar_assets();
            }
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
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return '';
    }

    if (function_exists('iss_relations_render_generic_card')) {
        return iss_relations_render_generic_card($post, [
            'kicker' => __('Führung', 'iss-fuehrungen'),
            'link_text' => __('Mehr', 'iss-fuehrungen'),
        ], [
            'layoutVariant' => 'grid',
            'show_image' => true,
            'show_excerpt' => true,
        ]);
    }

    $permalink = get_permalink($post_id);

    ob_start();
    echo '<article class="iss-card iss-card--flat">';
    if (has_post_thumbnail($post_id)) {
        echo '<a class="iss-card__media" href="' . esc_url($permalink) . '">';
        echo get_the_post_thumbnail($post_id, 'large');
        echo '</a>';
    }
    echo '<div class="iss-card__body">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Führung', 'iss-fuehrungen') . '</p>';
    echo '<h3 class="iss-card__title"><a href="' . esc_url($permalink) . '">' . esc_html(get_the_title($post_id)) . '</a></h3>';
    $excerpt = get_the_excerpt($post_id);
    if ($excerpt !== '') {
        echo '<p class="iss-card__text">' . esc_html($excerpt) . '</p>';
    }
    echo '<div class="iss-card__footer"><a class="iss-card__link" href="' . esc_url($permalink) . '">' . esc_html__('Mehr', 'iss-fuehrungen') . '</a></div>';
    echo '</div></article>';

    return (string) ob_get_clean();
}

function iss_fuehrung_get_offer_catalog_groups() {
    return function_exists('iss_fuehrung_get_offer_catalog_group_definitions')
        ? iss_fuehrung_get_offer_catalog_group_definitions()
        : [];
}

function iss_fuehrung_offer_catalog_contains_any($haystack, array $needles) {
    foreach ($needles as $needle) {
        $needle = trim((string) $needle);
        if ($needle === '') {
            continue;
        }

        if (strpos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function iss_fuehrung_get_offer_catalog_group_keys($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $explicit_groups = function_exists('iss_fuehrung_sanitize_offer_catalog_groups')
        ? iss_fuehrung_sanitize_offer_catalog_groups(get_post_meta($post_id, 'offer_catalog_groups', true))
        : [];
    if ($explicit_groups) {
        return $explicit_groups;
    }

    $mode = iss_fuehrung_get_effective_booking_mode($post_id);
    $stored_mode = iss_fuehrung_get_booking_mode($post_id);
    $next_event = iss_fuehrung_get_next_event($post_id);
    $target_group = trim((string) get_post_meta($post_id, 'target_group', true));
    $booking_note = trim((string) get_post_meta($post_id, 'booking_note', true));
    $inquiry = iss_fuehrung_get_inquiry_data($post_id);
    $haystack = strtolower(remove_accents(implode(' ', array_filter([
        get_the_title($post_id),
        $target_group,
        trim((string) get_post_field('post_excerpt', $post_id, 'raw')),
        trim((string) get_post_meta($post_id, 'tour_badge', true)),
        $booking_note,
        trim((string) ($inquiry['note'] ?? '')),
    ]))));
    $groups = [];

    if ((function_exists('iss_fuehrung_is_calendar_event') && iss_fuehrung_is_calendar_event($next_event)) || in_array($stored_mode, ['calendar', 'hybrid'], true)) {
        $groups['oeffentlich'] = true;
    }

    if (iss_fuehrung_offer_catalog_contains_any($haystack, [
        'famil',
        'kinder',
        'kind',
        'schul',
        'schuel',
        'jugend',
    ])) {
        $groups['familie-kinder'] = true;
    }

    if (iss_fuehrung_offer_catalog_contains_any($haystack, [
        'gruppe',
        'gruppen',
        'team',
        'teamevent',
        'schul',
        'schuel',
        'klasse',
        'hochschule',
        'fachpublikum',
        'gaeste',
        'gäste',
    ])) {
        $groups['gruppen'] = true;
    }

    if (iss_fuehrung_offer_catalog_contains_any($haystack, [
        'individ',
        'anfrage',
        'buchbar',
        'sonderformat',
        'sonderroute',
        'gutschein',
    ]) || in_array($mode, ['hybrid', 'on_demand'], true)) {
        $groups['individuell'] = true;
    }

    if (!$groups) {
        $groups[(function_exists('iss_fuehrung_is_calendar_event') && iss_fuehrung_is_calendar_event($next_event)) ? 'oeffentlich' : 'individuell'] = true;
    }

    return array_keys($groups);
}

function iss_fuehrung_group_offer_catalog_posts(array $posts) {
    $groups = [];
    foreach (array_keys(iss_fuehrung_get_offer_catalog_groups()) as $group_key) {
        $groups[$group_key] = [];
    }

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        foreach (iss_fuehrung_get_offer_catalog_group_keys($post->ID) as $group_key) {
            if (!isset($groups[$group_key])) {
                continue;
            }

            $groups[$group_key][] = $post;
        }
    }

    return $groups;
}

function iss_fuehrung_get_offer_catalog_item_state($post_id) {
    $mode = iss_fuehrung_get_effective_booking_mode($post_id);
    $next_event = iss_fuehrung_get_next_event($post_id);
    $booking_note = trim((string) get_post_meta($post_id, 'booking_note', true));
    $inquiry = iss_fuehrung_get_inquiry_data($post_id);
    $inquiry_label = trim((string) ($inquiry['label'] ?? ''));
    $inquiry_note = trim((string) ($inquiry['note'] ?? ''));
    $primary_action = null;
    $secondary_action = [
        'url' => get_permalink($post_id),
        'label' => __('Mehr', 'iss-fuehrungen'),
    ];

    if (function_exists('iss_fuehrung_is_calendar_event') && iss_fuehrung_is_calendar_event($next_event)) {
        $booking_url = iss_fuehrung_get_event_booking_url($next_event, $post_id);
        $availability = $next_event instanceof WP_Post
            ? trim((string) get_post_meta($next_event->ID, 'availability_state', true))
            : trim((string) ($next_event['availability_state'] ?? ''));
        $availability_label = iss_fuehrung_get_availability_label($availability);
        $note_parts = [];

        if ($availability_label !== '') {
            $note_parts[] = $availability_label;
        }

        if ($mode === 'hybrid') {
            $note_parts[] = __('Auch auf Anfrage buchbar', 'iss-fuehrungen');
        } elseif ($booking_note !== '') {
            $note_parts[] = $booking_note;
        }

        if ($booking_url !== '') {
            $primary_action = [
                'url' => $booking_url,
                'label' => __('Buchen', 'iss-fuehrungen'),
            ];
        }

        return [
            'mode' => $mode,
            'label' => __('Nächster Termin', 'iss-fuehrungen'),
            'value' => iss_fuehrung_get_event_start_label($next_event),
            'note' => implode(' · ', array_filter($note_parts)),
            'primary_action' => $primary_action,
            'secondary_action' => $secondary_action,
        ];
    }

    if ($mode === 'on_demand') {
        $primary_action = [
            'url' => get_permalink($post_id),
            'label' => $inquiry_label,
        ];

        return [
            'mode' => $mode,
            'label' => __('Buchung', 'iss-fuehrungen'),
            'value' => __('Auf Anfrage', 'iss-fuehrungen'),
            'note' => $inquiry_note !== '' ? $inquiry_note : ($booking_note !== '' ? $booking_note : __('Termin und Schwerpunkt werden individuell abgestimmt.', 'iss-fuehrungen')),
            'primary_action' => $primary_action,
            'secondary_action' => $secondary_action,
        ];
    }

    if ($mode === 'hybrid') {
        $primary_action = [
            'url' => get_permalink($post_id),
            'label' => $inquiry_label,
        ];

        return [
            'mode' => $mode,
            'label' => __('Status', 'iss-fuehrungen'),
            'value' => __('Aktuell keine Termine online', 'iss-fuehrungen'),
            'note' => $inquiry_note !== '' ? $inquiry_note : ($booking_note !== '' ? $booking_note : __('Diese Führung bleibt individuell anfragbar.', 'iss-fuehrungen')),
            'primary_action' => $primary_action,
            'secondary_action' => $secondary_action,
        ];
    }

    return [
        'mode' => $mode,
        'label' => __('Status', 'iss-fuehrungen'),
        'value' => __('Aktuell keine Termine online', 'iss-fuehrungen'),
        'note' => $booking_note,
        'primary_action' => $primary_action,
        'secondary_action' => $secondary_action,
    ];
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

function iss_fuehrung_get_description_leaf_blocks(): array
{
    return [
        'core/paragraph',
        'core/list',
        'core/quote',
    ];
}

function iss_fuehrung_get_description_container_blocks(): array
{
    return [
        'core/group',
        'core/columns',
        'core/column',
    ];
}

function iss_fuehrung_collect_description_markup(array $blocks): array
{
    $markup = [];
    $leaf_blocks = iss_fuehrung_get_description_leaf_blocks();
    $container_blocks = iss_fuehrung_get_description_container_blocks();

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $block_name = (string) ($block['blockName'] ?? '');

        if ($block_name === '') {
            $html = trim((string) ($block['innerHTML'] ?? ''));
            if ($html !== '') {
                $markup[] = wp_kses_post($html);
            }
            continue;
        }

        if (in_array($block_name, $leaf_blocks, true)) {
            $markup[] = render_block($block);
            continue;
        }

        if (in_array($block_name, $container_blocks, true) && !empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            $markup = array_merge($markup, iss_fuehrung_collect_description_markup($block['innerBlocks']));
        }
    }

    return $markup;
}

function iss_fuehrung_render_description_html(int $post_id): string
{
    $content = trim((string) get_post_field('post_content', $post_id));
    if ($content === '') {
        return '';
    }

    if (!function_exists('parse_blocks') || !function_exists('render_block')) {
        return wpautop(wp_kses_post($content));
    }

    $blocks = parse_blocks($content);
    if (!$blocks) {
        return wpautop(wp_kses_post($content));
    }

    $markup = array_filter(iss_fuehrung_collect_description_markup($blocks));
    if ($markup) {
        return implode('', $markup);
    }

    return wpautop(wp_kses_post($content));
}

function iss_fuehrung_render_description_block($attributes = [], $content = '')
{
    $post_id = iss_fuehrung_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $description_html = iss_fuehrung_render_description_html($post_id);
    $description_html = (string) apply_filters('iss_fuehrung_description_html', $description_html, $post_id, $attributes);
    if ($description_html === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-tour-description'])
        : 'class="wp-block-iss-tour-description"';

    return '<div ' . $wrapper . '>' . $description_html . '</div>';
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
