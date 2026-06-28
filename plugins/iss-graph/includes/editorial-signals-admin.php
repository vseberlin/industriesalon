<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_graph_get_editorial_signal_labels(): array
{
    return [
        'pin' => __('Fest oben halten', 'iss-graph'),
        'feature' => __('Hervorheben', 'iss-graph'),
        'boost' => __('Hoeher gewichten', 'iss-graph'),
        'suppress' => __('Nicht automatisch zeigen', 'iss-graph'),
    ];
}

function iss_graph_get_related_promotion_post_types(): array
{
    $post_types = (array) apply_filters('iss_graph_related_promotion_post_types', [
        'ausstellung',
        'publication',
        'fuehrung',
        'veranstaltung',
        'projekt',
        'video',
        'page',
        'post',
    ]);

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
}

function iss_graph_is_related_promotion_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_graph_get_related_promotion_post_types(), true);
}

function iss_graph_get_search_signal_post_types(): array
{
    $post_types = function_exists('iss_graph_get_public_search_post_types')
        ? iss_graph_get_public_search_post_types()
        : [];

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
}

function iss_graph_is_search_signal_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_graph_get_search_signal_post_types(), true);
}

function iss_graph_get_availability_signal_post_types(): array
{
    $post_types = (array) apply_filters('iss_graph_availability_signal_post_types', [
        'ausstellung',
    ]);

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
}

function iss_graph_is_availability_signal_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_graph_get_availability_signal_post_types(), true);
}

function iss_graph_editorial_signal_is_active(?array $signal, string $surface = ''): bool
{
    if (!$signal) {
        return false;
    }

    if ($surface !== '' && sanitize_key((string) ($signal['surface'] ?? 'related')) !== sanitize_key($surface)) {
        return false;
    }

    if (sanitize_key((string) ($signal['status'] ?? 'active')) !== 'active') {
        return false;
    }

    $expires_at = isset($signal['expires_at']) ? (string) $signal['expires_at'] : '';
    if ($expires_at !== '' && strtotime($expires_at . ' UTC') < strtotime(current_time('mysql', true) . ' UTC')) {
        return false;
    }

    return true;
}

function iss_graph_related_promotion_signal_is_active(?array $signal): bool
{
    return iss_graph_editorial_signal_is_active($signal, 'related')
        && sanitize_key((string) ($signal['signal'] ?? '')) === 'feature';
}

function iss_graph_get_related_promotion_signal(int $post_id, bool $active_only = false): ?array
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return null;
    }

    $signal = iss_graph_get_service()->get_editorial_signal_by_post_target($post_id, $post_id, 'related');
    if (!$signal || sanitize_key((string) ($signal['signal'] ?? '')) !== 'feature') {
        return null;
    }

    if ($active_only && !iss_graph_related_promotion_signal_is_active($signal)) {
        return null;
    }

    return $signal;
}

function iss_graph_get_availability_signal(int $post_id, bool $active_only = false): ?array
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return null;
    }

    $signal = iss_graph_get_service()->get_editorial_signal_by_post_target($post_id, $post_id, 'availability');
    if (!$signal) {
        return null;
    }

    if ($active_only && !iss_graph_editorial_signal_is_active($signal, 'availability')) {
        return null;
    }

    return $signal;
}

function iss_graph_get_editorial_signal_context_post_types(): array
{
    $post_types = [];

    if (function_exists('iss_relations_get_supported_post_types')) {
        $post_types = array_merge($post_types, iss_relations_get_supported_post_types());
    }

    if (function_exists('iss_graph_get_content_relation_post_types')) {
        $post_types = array_merge($post_types, iss_graph_get_content_relation_post_types());
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));

    return (array) apply_filters('iss_graph_editorial_signal_context_post_types', $post_types);
}

function iss_graph_is_editorial_signal_context_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_graph_get_editorial_signal_context_post_types(), true);
}

function iss_graph_editorial_signal_target_is_allowed(int $context_post_id, int $target_post_id): bool
{
    return iss_graph_editorial_signal_target_is_allowed_for_surface($context_post_id, $target_post_id, 'related');
}

function iss_graph_editorial_signal_target_is_allowed_for_surface(int $context_post_id, int $target_post_id, string $surface = 'related'): bool
{
    $context_post = get_post($context_post_id);
    $target_post = get_post($target_post_id);
    if (!$context_post instanceof WP_Post || !$target_post instanceof WP_Post) {
        return false;
    }

    if (in_array((string) $target_post->post_status, ['auto-draft', 'trash'], true)) {
        return false;
    }

    $surface = iss_graph_get_service()->normalize_editorial_signal_surface($surface);
    if ($surface === 'search') {
        return $context_post_id === $target_post_id
            && (string) $target_post->post_status === 'publish'
            && iss_graph_is_search_signal_post_type((string) $target_post->post_type);
    }

    if ($surface === 'availability') {
        return $context_post_id === $target_post_id
            && (string) $target_post->post_status === 'publish'
            && iss_graph_is_availability_signal_post_type((string) $target_post->post_type);
    }

    $allowed_post_types = iss_graph_get_editorial_signal_target_post_types($context_post);

    return in_array((string) $target_post->post_type, $allowed_post_types, true);
}

function iss_graph_get_editorial_signal_target_post_types(WP_Post $post): array
{
    $post_type = sanitize_key((string) $post->post_type);
    $post_types = [];

    if (function_exists('iss_relations_is_related_query_post_type') && iss_relations_is_related_query_post_type($post_type)) {
        $post_types[] = $post_type;
    }

    if (!$post_types && function_exists('iss_relations_get_related_query_post_types')) {
        $post_types = iss_relations_get_related_query_post_types();
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));

    return (array) apply_filters('iss_graph_editorial_signal_target_post_types', $post_types, $post);
}

function iss_graph_get_editorial_signal_target_options(array $post_types, int $exclude_post_id = 0): array
{
    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
    if (!$post_types) {
        return [];
    }

    $posts = get_posts([
        'post_type' => count($post_types) === 1 ? $post_types[0] : $post_types,
        'post_status' => 'publish',
        'numberposts' => 200,
        'orderby' => 'title',
        'order' => 'ASC',
        'exclude' => $exclude_post_id > 0 ? [$exclude_post_id] : [],
        'suppress_filters' => true,
    ]);

    $options = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $options[] = [
            'id' => (int) $post->ID,
            'title' => (string) get_the_title($post),
            'post_type' => (string) $post->post_type,
        ];
    }

    return $options;
}

function iss_graph_render_editorial_signal_select(string $name, string $selected = '', bool $include_empty = true): void
{
    $labels = iss_graph_get_editorial_signal_labels();

    echo '<select name="' . esc_attr($name) . '">';
    if ($include_empty) {
        echo '<option value="">' . esc_html__('Keine Auswahl', 'iss-graph') . '</option>';
    }

    foreach ($labels as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($selected, $value, false) . '>' . esc_html((string) $label) . '</option>';
    }

    echo '</select>';
}

function iss_graph_render_editorial_signal_fields(string $prefix, string $signal = '', string $reason = '', string $expires_at = '', bool $include_empty_signal = false): void
{
    echo '<div class="iss-graph-editor__grid iss-graph-editor__grid--editorial-signal">';

    echo '<label><span>' . esc_html__('Auswahl', 'iss-graph') . '</span>';
    iss_graph_render_editorial_signal_select($prefix . '[signal]', $signal, $include_empty_signal);
    echo '</label>';

    echo '<label><span>' . esc_html__('Begründung', 'iss-graph') . '</span>';
    echo '<input type="text" class="widefat" name="' . esc_attr($prefix . '[reason]') . '" value="' . esc_attr($reason) . '" placeholder="' . esc_attr__('Warum ist diese Steuerung noetig?', 'iss-graph') . '">';
    echo '</label>';

    echo '<label><span>' . esc_html__('Gültig bis', 'iss-graph') . '</span>';
    echo '<input type="date" name="' . esc_attr($prefix . '[expires_at]') . '" value="' . esc_attr($expires_at) . '">';
    echo '</label>';

    echo '</div>';
    echo '<p class="description">' . esc_html__('Begründung und Gültig-bis-Datum sind für neue Signale erforderlich.', 'iss-graph') . '</p>';
}

function iss_graph_render_editorial_signal_target_option_label(array $option): string
{
    $title = trim((string) ($option['title'] ?? ''));
    if ($title === '') {
        $title = sprintf(__('Eintrag %d', 'iss-graph'), (int) ($option['id'] ?? 0));
    }

    $post_type = sanitize_key((string) ($option['post_type'] ?? ''));
    if ($post_type !== '') {
        $label = get_post_type_object($post_type);
        $post_type_label = $label && isset($label->labels->singular_name)
            ? (string) $label->labels->singular_name
            : $post_type;

        return $title . ' · ' . $post_type_label;
    }

    return $title;
}

function iss_graph_get_editorial_signal_preview_items(WP_Post $post, array $target_post_types): array
{
    if (!function_exists('iss_relations_build_related_cards_preview_payload') || !$target_post_types) {
        return [];
    }

    $payload = iss_relations_build_related_cards_preview_payload([
        'postTypes' => $target_post_types,
        'postType' => $target_post_types[0],
        'perPage' => 6,
        'source' => 'current',
    ], (int) $post->ID);

    return is_array($payload['items'] ?? null) ? (array) $payload['items'] : [];
}

function iss_graph_render_editorial_signals_meta_box(WP_Post $post): void
{
    $post_id = (int) $post->ID;
    $target_post_types = iss_graph_get_editorial_signal_target_post_types($post);
    $target_options = iss_graph_get_editorial_signal_target_options($target_post_types, $post_id);
    $active_signals = iss_graph_get_active_editorial_signals_for_post($post_id, 'related');
    $active_signals = array_values(array_filter($active_signals, static function (array $signal) use ($post_id): bool {
        return absint($signal['target_post_id'] ?? 0) !== $post_id;
    }));
    $active_targets = [];

    wp_nonce_field('iss_graph_save_editorial_signals_admin', 'iss_graph_editorial_signals_nonce');

    echo '<p>' . esc_html__('Diese Auswahl steuert nur automatische Verwandte-Inhalte-Bloecke auf dieser Seite. Sie aendert keine Graph-Beziehungen, Aliase, Archivprojektionen oder Suchdaten.', 'iss-graph') . '</p>';
    echo '<div class="iss-graph-editor iss-graph-editor--editorial-signals">';

    echo '<section class="iss-graph-editor__group">';
    echo '<div class="iss-graph-editor__header"><h4>' . esc_html__('Redaktionelle Auswahl', 'iss-graph') . '</h4><p class="description">' . esc_html__('Aktive Entscheidungen fuer diese Seite.', 'iss-graph') . '</p></div>';

    if (!$active_signals) {
        echo '<p class="description">' . esc_html__('Keine Auswahl aktiv.', 'iss-graph') . '</p>';
    }

    foreach ($active_signals as $signal) {
        $target_post_id = absint($signal['target_post_id'] ?? 0);
        $target_post = $target_post_id > 0 ? get_post($target_post_id) : null;
        if (!$target_post instanceof WP_Post) {
            continue;
        }

        $active_targets[$target_post_id] = true;
        $prefix = 'iss_graph_related_signals[' . $target_post_id . ']';
        $expires_at = isset($signal['expires_at']) && $signal['expires_at'] !== null ? substr((string) $signal['expires_at'], 0, 10) : '';

        echo '<div class="iss-graph-editor__row">';
        echo '<div class="iss-graph-editor__row-top">';
        echo '<strong>' . esc_html((string) get_the_title($target_post)) . '</strong>';
        echo '<label><input type="checkbox" name="' . esc_attr($prefix . '[remove]') . '" value="1"> ' . esc_html__('Auswahl entfernen', 'iss-graph') . '</label>';
        echo '</div>';
        iss_graph_render_editorial_signal_fields($prefix, sanitize_key((string) ($signal['signal'] ?? '')), (string) ($signal['reason'] ?? ''), $expires_at);
        echo '</div>';
    }

    echo '</section>';

    $preview_items = iss_graph_get_editorial_signal_preview_items($post, $target_post_types);
    echo '<section class="iss-graph-editor__group">';
    echo '<div class="iss-graph-editor__header"><h4>' . esc_html__('Automatische Vorschau', 'iss-graph') . '</h4><p class="description">' . esc_html__('Schnellauswahl aus den aktuellen automatischen Vorschlaegen.', 'iss-graph') . '</p></div>';

    if (!$preview_items) {
        echo '<p class="description">' . esc_html__('Keine automatischen Vorschlaege fuer diese Auswahl gefunden.', 'iss-graph') . '</p>';
    }

    foreach ($preview_items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $target_post_id = absint($item['id'] ?? 0);
        if ($target_post_id <= 0 || isset($active_targets[$target_post_id])) {
            continue;
        }

        $prefix = 'iss_graph_related_signal_suggestions[' . $target_post_id . ']';
        $title = trim((string) ($item['title'] ?? ''));

        echo '<div class="iss-graph-editor__row">';
        echo '<div class="iss-graph-editor__row-top"><strong>' . esc_html($title !== '' ? $title : sprintf(__('Eintrag %d', 'iss-graph'), $target_post_id)) . '</strong></div>';
        iss_graph_render_editorial_signal_fields($prefix, '', '', '', true);
        echo '</div>';
    }

    echo '</section>';

    echo '<section class="iss-graph-editor__group">';
    echo '<div class="iss-graph-editor__header"><h4>' . esc_html__('Auswahl hinzufuegen', 'iss-graph') . '</h4><p class="description">' . esc_html__('Einen veroeffentlichten Ziel-Eintrag manuell auswaehlen.', 'iss-graph') . '</p></div>';
    echo '<div class="iss-graph-editor__grid iss-graph-editor__grid--editorial-add">';
    echo '<label><span>' . esc_html__('Ziel', 'iss-graph') . '</span>';
    echo '<select name="iss_graph_related_signal_new[target_post_id]">';
    echo '<option value="">' . esc_html__('Ziel auswaehlen', 'iss-graph') . '</option>';
    foreach ($target_options as $option) {
        $target_post_id = (int) ($option['id'] ?? 0);
        if ($target_post_id <= 0 || isset($active_targets[$target_post_id])) {
            continue;
        }

        echo '<option value="' . esc_attr((string) $target_post_id) . '">' . esc_html(iss_graph_render_editorial_signal_target_option_label($option)) . '</option>';
    }
    echo '</select></label>';
    echo '</div>';
    iss_graph_render_editorial_signal_fields('iss_graph_related_signal_new', '', '', '', true);
    echo '</section>';

    echo '</div>';
}

function iss_graph_add_editorial_signal_meta_boxes(): void
{
    if (!iss_graph_current_user_can_edit_editorial_signals()) {
        return;
    }

    foreach (iss_graph_get_related_promotion_post_types() as $post_type) {
        add_meta_box(
            'iss-graph-related-promotion',
            __('Inhalt promoten', 'iss-graph'),
            'iss_graph_render_related_promotion_meta_box',
            $post_type,
            'side',
            'high'
        );
    }

    if (!function_exists('iss_graph_current_user_can_manage_editorial_signals') || !iss_graph_current_user_can_manage_editorial_signals()) {
        return;
    }

    foreach (iss_graph_get_search_signal_post_types() as $post_type) {
        add_meta_box(
            'iss-graph-search-signal',
            __('Suche steuern', 'iss-graph'),
            'iss_graph_render_search_signal_meta_box',
            $post_type,
            'side',
            'default'
        );
    }

    foreach (iss_graph_get_availability_signal_post_types() as $post_type) {
        add_meta_box(
            'iss-graph-availability-signal',
            __('Ausstellungsbrowser steuern', 'iss-graph'),
            'iss_graph_render_availability_signal_meta_box',
            $post_type,
            'side',
            'default'
        );
    }

    foreach (iss_graph_get_editorial_signal_context_post_types() as $post_type) {
        add_meta_box(
            'iss-graph-editorial-signals',
            __('Verwandte Inhalte steuern', 'iss-graph'),
            'iss_graph_render_editorial_signals_meta_box',
            $post_type,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'iss_graph_add_editorial_signal_meta_boxes');

function iss_graph_render_related_promotion_meta_box(WP_Post $post): void
{
    $post_id = (int) $post->ID;
    $signal = iss_graph_get_related_promotion_signal($post_id, false);
    $is_active = iss_graph_related_promotion_signal_is_active($signal);
    $expires_at = '';
    $reason = '';

    if ($is_active && isset($signal['expires_at']) && $signal['expires_at'] !== null) {
        $expires_at = substr((string) $signal['expires_at'], 0, 10);
    }
    if ($is_active) {
        $reason = (string) ($signal['reason'] ?? '');
    }

    wp_nonce_field('iss_graph_save_related_promotion', 'iss_graph_related_promotion_nonce');

    if (!current_user_can('manage_options')) {
        echo '<p><label><input type="checkbox" name="iss_graph_related_promotion[enabled]" value="1" ' . checked($is_active, true, false) . '> ' . esc_html__('Inhalt promoten', 'iss-graph') . '</label></p>';
        echo '<input type="hidden" name="iss_graph_related_promotion[reason]" value="' . esc_attr($reason) . '">';
        echo '<input type="hidden" name="iss_graph_related_promotion[expires_at]" value="' . esc_attr($expires_at) . '">';
        echo '<p class="description">' . esc_html__('Mit dieser Auswahl rückt der Post nach vorne.', 'iss-graph') . '</p>';
        return;
    }

    echo '<p><label><input type="checkbox" name="iss_graph_related_promotion[enabled]" value="1" ' . checked($is_active, true, false) . '> ' . esc_html__('Inhalt promoten', 'iss-graph') . '</label></p>';
    echo '<p><label for="iss_graph_related_promotion_reason"><strong>' . esc_html__('Begründung', 'iss-graph') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_graph_related_promotion_reason" name="iss_graph_related_promotion[reason]" value="' . esc_attr($reason) . '" placeholder="' . esc_attr__('Warum ist diese Steuerung noetig?', 'iss-graph') . '"></p>';
    echo '<p><label for="iss_graph_related_promotion_expires_at"><strong>' . esc_html__('Gültig bis', 'iss-graph') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_graph_related_promotion_expires_at" name="iss_graph_related_promotion[expires_at]" value="' . esc_attr($expires_at) . '"></p>';
    echo '<p class="description">' . esc_html__('Mit dieser Auswahl rückt der Post nach vorne.', 'iss-graph') . '</p>';
}

function iss_graph_render_search_signal_meta_box(WP_Post $post): void
{
    $post_id = (int) $post->ID;
    $signal = iss_graph_get_service()->get_editorial_signal_by_post_target($post_id, $post_id, 'search');
    $is_active = $signal
        && sanitize_key((string) ($signal['status'] ?? 'active')) === 'active'
        && (empty($signal['expires_at']) || strtotime((string) $signal['expires_at'] . ' UTC') >= strtotime(current_time('mysql', true) . ' UTC'));
    $selected = $is_active ? sanitize_key((string) ($signal['signal'] ?? '')) : '';
    $reason = $is_active ? (string) ($signal['reason'] ?? '') : '';
    $expires_at = $is_active && isset($signal['expires_at']) && $signal['expires_at'] !== null
        ? substr((string) $signal['expires_at'], 0, 10)
        : '';

    wp_nonce_field('iss_graph_save_search_signal', 'iss_graph_search_signal_nonce');

    iss_graph_render_editorial_signal_fields('iss_graph_search_signal', $selected, $reason, $expires_at, true);
    echo '<p class="description">' . esc_html__('Steuert nur die oeffentliche Suche fuer diesen Eintrag. Beziehungen, Aliase und kanonische Graphdaten bleiben unveraendert.', 'iss-graph') . '</p>';
}

function iss_graph_render_availability_signal_meta_box(WP_Post $post): void
{
    $post_id = (int) $post->ID;
    $signal = iss_graph_get_availability_signal($post_id, false);
    $is_active = iss_graph_editorial_signal_is_active($signal, 'availability');
    $selected = $is_active ? sanitize_key((string) ($signal['signal'] ?? '')) : '';
    $reason = $is_active ? (string) ($signal['reason'] ?? '') : '';
    $expires_at = $is_active && isset($signal['expires_at']) && $signal['expires_at'] !== null
        ? substr((string) $signal['expires_at'], 0, 10)
        : '';

    wp_nonce_field('iss_graph_save_availability_signal', 'iss_graph_availability_signal_nonce');

    iss_graph_render_editorial_signal_fields('iss_graph_availability_signal', $selected, $reason, $expires_at, true);
    echo '<p class="description">' . esc_html__('Steuert nur automatische Anzeige und Reihenfolge im Ausstellungsbrowser. Beziehungen, Aliase, Suche und kanonische Graphdaten bleiben unveraendert.', 'iss-graph') . '</p>';
}

function iss_graph_sanitize_posted_editorial_signal_row($row): array
{
    $row = is_array($row) ? $row : [];

    return [
        'signal' => iss_graph_get_service()->normalize_editorial_signal_type((string) ($row['signal'] ?? '')),
        'reason' => sanitize_textarea_field((string) ($row['reason'] ?? '')),
        'expires_at' => sanitize_text_field((string) ($row['expires_at'] ?? '')),
        'remove' => !empty($row['remove']),
    ];
}

function iss_graph_save_posted_editorial_signal_row(int $context_post_id, int $target_post_id, array $row): void
{
    if ($context_post_id <= 0 || $target_post_id <= 0) {
        return;
    }

    if (!iss_graph_editorial_signal_target_is_allowed($context_post_id, $target_post_id)) {
        return;
    }

    if (!empty($row['remove'])) {
        iss_graph_remove_editorial_signal_for_post($context_post_id, $target_post_id, 'related');
        return;
    }

    $signal = iss_graph_get_service()->normalize_editorial_signal_type((string) ($row['signal'] ?? ''));
    if ($signal === '') {
        return;
    }

    iss_graph_upsert_editorial_signal_for_post($context_post_id, $target_post_id, $signal, [
        'surface' => 'related',
        'reason' => (string) ($row['reason'] ?? ''),
        'expires_at' => (string) ($row['expires_at'] ?? ''),
        'author_user_id' => get_current_user_id(),
        'status' => 'active',
        'require_metadata' => true,
    ]);
}

function iss_graph_save_editorial_signals_meta_box(int $post_id, WP_Post $post): void
{
    if (!iss_graph_is_editorial_signal_context_post_type((string) $post->post_type)) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!iss_graph_current_user_can_edit_editorial_signals($post_id)) {
        return;
    }

    if (
        !isset($_POST['iss_graph_editorial_signals_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_editorial_signals_nonce'])), 'iss_graph_save_editorial_signals_admin')
    ) {
        return;
    }

    $existing = isset($_POST['iss_graph_related_signals'])
        ? (array) wp_unslash($_POST['iss_graph_related_signals'])
        : [];
    foreach ($existing as $target_post_id => $row) {
        iss_graph_save_posted_editorial_signal_row($post_id, absint($target_post_id), iss_graph_sanitize_posted_editorial_signal_row($row));
    }

    $suggestions = isset($_POST['iss_graph_related_signal_suggestions'])
        ? (array) wp_unslash($_POST['iss_graph_related_signal_suggestions'])
        : [];
    foreach ($suggestions as $target_post_id => $row) {
        iss_graph_save_posted_editorial_signal_row($post_id, absint($target_post_id), iss_graph_sanitize_posted_editorial_signal_row($row));
    }

    $new = isset($_POST['iss_graph_related_signal_new'])
        ? iss_graph_sanitize_posted_editorial_signal_row((array) wp_unslash($_POST['iss_graph_related_signal_new']))
        : [];
    $new_target_post_id = isset($_POST['iss_graph_related_signal_new']['target_post_id'])
        ? absint(wp_unslash($_POST['iss_graph_related_signal_new']['target_post_id']))
        : 0;
    if ($new_target_post_id > 0 && $new) {
        iss_graph_save_posted_editorial_signal_row($post_id, $new_target_post_id, $new);
    }
}
add_action('save_post', 'iss_graph_save_editorial_signals_meta_box', 58, 2);

function iss_graph_save_related_promotion_meta_box(int $post_id, WP_Post $post): void
{
    if (!iss_graph_is_related_promotion_post_type((string) $post->post_type)) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!iss_graph_current_user_can_edit_editorial_signals($post_id)) {
        return;
    }

    if (
        !isset($_POST['iss_graph_related_promotion_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_related_promotion_nonce'])), 'iss_graph_save_related_promotion')
    ) {
        return;
    }

    $raw = isset($_POST['iss_graph_related_promotion']) && is_array($_POST['iss_graph_related_promotion'])
        ? (array) wp_unslash($_POST['iss_graph_related_promotion'])
        : [];

    if (empty($raw['enabled'])) {
        iss_graph_remove_editorial_signal_for_post($post_id, $post_id, 'related');
        return;
    }

    iss_graph_upsert_editorial_signal_for_post($post_id, $post_id, 'feature', [
        'surface' => 'related',
        'reason' => sanitize_textarea_field((string) ($raw['reason'] ?? '')),
        'expires_at' => sanitize_text_field((string) ($raw['expires_at'] ?? '')),
        'author_user_id' => get_current_user_id(),
        'status' => 'active',
        'require_metadata' => false,
    ]);
}
add_action('save_post', 'iss_graph_save_related_promotion_meta_box', 57, 2);

function iss_graph_save_search_signal_meta_box(int $post_id, WP_Post $post): void
{
    if (!iss_graph_is_search_signal_post_type((string) $post->post_type)) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!function_exists('iss_graph_current_user_can_manage_editorial_signals') || !iss_graph_current_user_can_manage_editorial_signals($post_id)) {
        return;
    }

    if (
        !isset($_POST['iss_graph_search_signal_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_search_signal_nonce'])), 'iss_graph_save_search_signal')
    ) {
        return;
    }

    $raw = isset($_POST['iss_graph_search_signal']) && is_array($_POST['iss_graph_search_signal'])
        ? (array) wp_unslash($_POST['iss_graph_search_signal'])
        : [];
    $signal = iss_graph_get_service()->normalize_editorial_signal_type((string) ($raw['signal'] ?? ''));

    if ($signal === '') {
        iss_graph_remove_editorial_signal_for_post($post_id, $post_id, 'search');
        return;
    }

    if (!iss_graph_editorial_signal_target_is_allowed_for_surface($post_id, $post_id, 'search')) {
        return;
    }

    iss_graph_upsert_editorial_signal_for_post($post_id, $post_id, $signal, [
        'surface' => 'search',
        'reason' => sanitize_textarea_field((string) ($raw['reason'] ?? '')),
        'expires_at' => sanitize_text_field((string) ($raw['expires_at'] ?? '')),
        'author_user_id' => get_current_user_id(),
        'status' => 'active',
        'require_metadata' => true,
    ]);
}
add_action('save_post', 'iss_graph_save_search_signal_meta_box', 56, 2);

function iss_graph_save_availability_signal_meta_box(int $post_id, WP_Post $post): void
{
    if (!iss_graph_is_availability_signal_post_type((string) $post->post_type)) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!function_exists('iss_graph_current_user_can_manage_editorial_signals') || !iss_graph_current_user_can_manage_editorial_signals($post_id)) {
        return;
    }

    if (
        !isset($_POST['iss_graph_availability_signal_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_availability_signal_nonce'])), 'iss_graph_save_availability_signal')
    ) {
        return;
    }

    $raw = isset($_POST['iss_graph_availability_signal']) && is_array($_POST['iss_graph_availability_signal'])
        ? (array) wp_unslash($_POST['iss_graph_availability_signal'])
        : [];
    $signal = iss_graph_get_service()->normalize_editorial_signal_type((string) ($raw['signal'] ?? ''));

    if ($signal === '') {
        iss_graph_remove_editorial_signal_for_post($post_id, $post_id, 'availability');
        return;
    }

    if (!iss_graph_editorial_signal_target_is_allowed_for_surface($post_id, $post_id, 'availability')) {
        return;
    }

    iss_graph_upsert_editorial_signal_for_post($post_id, $post_id, $signal, [
        'surface' => 'availability',
        'reason' => sanitize_textarea_field((string) ($raw['reason'] ?? '')),
        'expires_at' => sanitize_text_field((string) ($raw['expires_at'] ?? '')),
        'author_user_id' => get_current_user_id(),
        'status' => 'active',
        'require_metadata' => true,
    ]);
}
add_action('save_post', 'iss_graph_save_availability_signal_meta_box', 55, 2);

function iss_graph_get_selected_related_promotion_admin_filter(): string
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list-table filter value.
    if (!isset($_GET['iss_graph_related_promotion'])) {
        return '';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list-table filter value.
    $selected = sanitize_key((string) wp_unslash($_GET['iss_graph_related_promotion']));

    return $selected === 'active' ? $selected : '';
}

function iss_graph_render_related_promotion_admin_filter(): void
{
    global $typenow;

    if (!iss_graph_current_user_can_edit_editorial_signals()) {
        return;
    }

    $post_type = sanitize_key((string) $typenow);
    if (!iss_graph_is_related_promotion_post_type($post_type)) {
        return;
    }

    $selected = iss_graph_get_selected_related_promotion_admin_filter();

    echo '<label class="screen-reader-text" for="filter-by-iss-graph-related-promotion">' . esc_html__('Vorne gezeigt filtern', 'iss-graph') . '</label>';
    echo '<select name="iss_graph_related_promotion" id="filter-by-iss-graph-related-promotion">';
    echo '<option value="">' . esc_html__('Alle Sichtbarkeiten', 'iss-graph') . '</option>';
    echo '<option value="active"' . selected($selected, 'active', false) . '>' . esc_html__('Vorne gezeigt', 'iss-graph') . '</option>';
    echo '</select>';
}
add_action('restrict_manage_posts', 'iss_graph_render_related_promotion_admin_filter');

function iss_graph_filter_related_promotion_admin_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!iss_graph_current_user_can_edit_editorial_signals()) {
        return;
    }

    $query_post_type = $query->get('post_type') ?: 'post';
    $post_type = is_array($query_post_type)
        ? sanitize_key((string) reset($query_post_type))
        : sanitize_key((string) $query_post_type);
    if (!iss_graph_is_related_promotion_post_type($post_type) || iss_graph_get_selected_related_promotion_admin_filter() !== 'active') {
        return;
    }

    $post_ids = function_exists('iss_graph_get_active_related_promotion_post_ids')
        ? iss_graph_get_active_related_promotion_post_ids([$post_type], [
            'post_status' => '',
            'limit' => 5000,
        ])
        : [];

    $existing = $query->get('post__in');
    if (is_array($existing) && $existing) {
        $post_ids = array_values(array_intersect(array_map('intval', $existing), $post_ids));
    }

    $query->set('post__in', $post_ids ?: [0]);
}
add_action('pre_get_posts', 'iss_graph_filter_related_promotion_admin_query');

function iss_graph_register_related_promotion_list_table_hooks(): void
{
    foreach (iss_graph_get_related_promotion_post_types() as $post_type) {
        add_filter("manage_{$post_type}_posts_columns", 'iss_graph_add_related_promotion_column');
        add_action("manage_{$post_type}_posts_custom_column", 'iss_graph_render_related_promotion_column', 10, 2);
        add_filter("bulk_actions-edit-{$post_type}", 'iss_graph_add_related_promotion_bulk_action');
        add_filter("handle_bulk_actions-edit-{$post_type}", 'iss_graph_handle_related_promotion_bulk_action', 10, 3);
    }
}
add_action('admin_init', 'iss_graph_register_related_promotion_list_table_hooks');

function iss_graph_add_related_promotion_column(array $columns): array
{
    if (!iss_graph_current_user_can_edit_editorial_signals()) {
        return $columns;
    }

    $next = [];
    foreach ($columns as $key => $label) {
        $next[$key] = $label;
        if ($key === 'title') {
            $next['iss_graph_related_promotion'] = __('Vorne', 'iss-graph');
        }
    }

    if (!isset($next['iss_graph_related_promotion'])) {
        $next['iss_graph_related_promotion'] = __('Vorne', 'iss-graph');
    }

    return $next;
}

function iss_graph_render_related_promotion_column(string $column, int $post_id): void
{
    if ($column !== 'iss_graph_related_promotion') {
        return;
    }

    if (!iss_graph_current_user_can_edit_editorial_signals($post_id)) {
        echo '&mdash;';
        return;
    }

    $signal = iss_graph_get_related_promotion_signal($post_id, true);
    if (!$signal) {
        echo '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">' . esc_html__('Nicht vorne gezeigt', 'iss-graph') . '</span>';
        return;
    }

    $expires_at = trim((string) ($signal['expires_at'] ?? ''));
    echo '<strong>' . esc_html__('Vorne gezeigt', 'iss-graph') . '</strong>';
    if ($expires_at !== '') {
        echo '<br><span class="description">' . esc_html(sprintf(__('bis %s', 'iss-graph'), mysql2date(get_option('date_format'), $expires_at))) . '</span>';
    }
}

function iss_graph_add_related_promotion_bulk_action(array $actions): array
{
    if (!iss_graph_current_user_can_edit_editorial_signals()) {
        return $actions;
    }

    $actions['iss_graph_disable_related_promotion'] = __('Vorne zeigen ausschalten', 'iss-graph');

    return $actions;
}

function iss_graph_handle_related_promotion_bulk_action(string $redirect_url, string $action, array $post_ids): string
{
    if ($action !== 'iss_graph_disable_related_promotion') {
        return $redirect_url;
    }

    $changed = 0;
    foreach (array_map('absint', $post_ids) as $post_id) {
        if ($post_id <= 0 || !iss_graph_current_user_can_edit_editorial_signals($post_id)) {
            continue;
        }

        if (iss_graph_remove_editorial_signal_for_post($post_id, $post_id, 'related')) {
            $changed++;
        }
    }

    return add_query_arg('iss_graph_related_promotion_disabled', $changed, $redirect_url);
}

function iss_graph_add_related_promotion_row_action(array $actions, WP_Post $post): array
{
    if (!iss_graph_is_related_promotion_post_type((string) $post->post_type)) {
        return $actions;
    }

    if (!iss_graph_current_user_can_edit_editorial_signals((int) $post->ID)) {
        return $actions;
    }

    if (!iss_graph_get_related_promotion_signal((int) $post->ID, true)) {
        return $actions;
    }

    $url = wp_nonce_url(
        add_query_arg([
            'action' => 'iss_graph_disable_related_promotion',
            'post_id' => (int) $post->ID,
        ], admin_url('admin-post.php')),
        'iss_graph_disable_related_promotion_' . (int) $post->ID
    );
    $actions['iss_graph_disable_related_promotion'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url($url),
        esc_html__('Vorne aus', 'iss-graph')
    );

    return $actions;
}
add_filter('post_row_actions', 'iss_graph_add_related_promotion_row_action', 10, 2);
add_filter('page_row_actions', 'iss_graph_add_related_promotion_row_action', 10, 2);

function iss_graph_disable_related_promotion_admin_action(): void
{
    $post_id = absint($_GET['post_id'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is checked below before mutation.
    if ($post_id <= 0) {
        wp_die(esc_html__('Ungueltiger Beitrag.', 'iss-graph'), '', ['response' => 400]);
    }

    check_admin_referer('iss_graph_disable_related_promotion_' . $post_id);

    if (!iss_graph_current_user_can_edit_editorial_signals($post_id)) {
        wp_die(esc_html__('Nicht erlaubt.', 'iss-graph'), '', ['response' => 403]);
    }

    iss_graph_remove_editorial_signal_for_post($post_id, $post_id, 'related');

    $redirect = wp_get_referer();
    if (!$redirect) {
        $redirect = get_edit_post_link($post_id, 'raw');
    }

    wp_safe_redirect(add_query_arg('iss_graph_related_promotion_disabled', 1, $redirect));
    exit;
}
add_action('admin_post_iss_graph_disable_related_promotion', 'iss_graph_disable_related_promotion_admin_action');

function iss_graph_related_promotion_admin_notice(): void
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice value.
    if (!isset($_GET['iss_graph_related_promotion_disabled'])) {
        return;
    }

    $count = absint($_GET['iss_graph_related_promotion_disabled']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice value.
    if ($count <= 0) {
        return;
    }

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html(sprintf(_n('%d Inhalt wird nicht mehr vorne gezeigt.', '%d Inhalte werden nicht mehr vorne gezeigt.', $count, 'iss-graph'), $count))
    );
}
add_action('admin_notices', 'iss_graph_related_promotion_admin_notice');

function iss_graph_admin_enqueue_editorial_signal_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    if (!iss_graph_current_user_can_edit_editorial_signals()) {
        return;
    }

    $screen = get_current_screen();
    if (
        !$screen
        || (
            !iss_graph_is_editorial_signal_context_post_type((string) $screen->post_type)
            && !iss_graph_is_search_signal_post_type((string) $screen->post_type)
            && !iss_graph_is_availability_signal_post_type((string) $screen->post_type)
        )
    ) {
        return;
    }

    wp_enqueue_style(
        'iss-graph-content-relations-admin',
        ISS_GRAPH_URL . 'assets/css/register-place-graph-admin.css',
        [],
        ISS_GRAPH_VERSION
    );
}
add_action('admin_enqueue_scripts', 'iss_graph_admin_enqueue_editorial_signal_assets');
