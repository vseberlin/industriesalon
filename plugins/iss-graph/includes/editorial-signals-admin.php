<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_graph_get_editorial_signal_labels(): array
{
    return [
        'feature' => __('Vorne zeigen', 'iss-graph'),
        'hide' => __('Nicht automatisch zeigen', 'iss-graph'),
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

function iss_graph_related_promotion_signal_is_active(?array $signal): bool
{
    if (!$signal) {
        return false;
    }

    if (sanitize_key((string) ($signal['surface'] ?? 'related')) !== 'related') {
        return false;
    }

    if (sanitize_key((string) ($signal['signal'] ?? '')) !== 'feature') {
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
    $context_post = get_post($context_post_id);
    $target_post = get_post($target_post_id);
    if (!$context_post instanceof WP_Post || !$target_post instanceof WP_Post) {
        return false;
    }

    if (in_array((string) $target_post->post_status, ['auto-draft', 'trash'], true)) {
        return false;
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

    echo '<label><span>' . esc_html__('Notiz', 'iss-graph') . '</span>';
    echo '<input type="text" class="widefat" name="' . esc_attr($prefix . '[reason]') . '" value="' . esc_attr($reason) . '">';
    echo '</label>';

    echo '<label><span>' . esc_html__('Gilt bis', 'iss-graph') . '</span>';
    echo '<input type="date" name="' . esc_attr($prefix . '[expires_at]') . '" value="' . esc_attr($expires_at) . '">';
    echo '</label>';

    echo '</div>';
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
            __('Vorne zeigen', 'iss-graph'),
            'iss_graph_render_related_promotion_meta_box',
            $post_type,
            'side',
            'high'
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

    if ($is_active && isset($signal['expires_at']) && $signal['expires_at'] !== null) {
        $expires_at = substr((string) $signal['expires_at'], 0, 10);
    }

    wp_nonce_field('iss_graph_save_related_promotion', 'iss_graph_related_promotion_nonce');

    echo '<p><label><input type="checkbox" name="iss_graph_related_promotion[enabled]" value="1" ' . checked($is_active, true, false) . '> ' . esc_html__('Vorne zeigen', 'iss-graph') . '</label></p>';
    echo '<p><label for="iss_graph_related_promotion_expires_at"><strong>' . esc_html__('Gilt bis', 'iss-graph') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_graph_related_promotion_expires_at" name="iss_graph_related_promotion[expires_at]" value="' . esc_attr($expires_at) . '"></p>';
    echo '<p class="description">' . esc_html__('Bevorzugt diesen Inhalt in automatischen Verwandte-Inhalte-Bloecken desselben Typs. Ohne Datum bleibt die Auswahl aktiv, bis sie entfernt wird.', 'iss-graph') . '</p>';
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
        'reason' => '',
        'expires_at' => sanitize_text_field((string) ($raw['expires_at'] ?? '')),
        'author_user_id' => get_current_user_id(),
        'status' => 'active',
    ]);
}
add_action('save_post', 'iss_graph_save_related_promotion_meta_box', 57, 2);

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

function iss_graph_admin_enqueue_editorial_signal_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    if (!iss_graph_current_user_can_edit_editorial_signals()) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !iss_graph_is_editorial_signal_context_post_type((string) $screen->post_type)) {
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
