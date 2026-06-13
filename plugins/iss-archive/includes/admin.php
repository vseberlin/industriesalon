<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_add_suggestions_meta_box(): void
{
    foreach (iss_wf_import_get_suggestion_post_types() as $post_type) {
        add_meta_box(
            'iss-wf-import-suggestions',
            __('Orte prüfen', 'iss-wf-import'),
            'iss_wf_import_render_suggestions_meta_box',
            $post_type,
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'iss_wf_import_add_suggestions_meta_box');

function iss_wf_import_render_suggestions_meta_box(WP_Post $post): void
{
    wp_nonce_field('iss_wf_import_suggestions_action', 'iss_wf_import_suggestions_nonce');

    $suggestions = iss_wf_import_sanitize_place_suggestions((array) get_post_meta($post->ID, ISS_WF_IMPORT_PLACE_SUGGESTIONS_META, true));
    $generated_at = (string) get_post_meta($post->ID, ISS_WF_IMPORT_PLACE_SUGGESTED_AT_META, true);

    echo '<p>' . esc_html__('Die vorgeschlagenen Orte bleiben unverbindlich, bis sie ausdrücklich als Ortsbeziehungen übernommen werden.', 'iss-wf-import') . '</p>';

    if ($generated_at !== '') {
        echo '<p><small>' . esc_html(sprintf(__('Zuletzt aktualisiert: %s', 'iss-wf-import'), $generated_at)) . '</small></p>';
    }

    if (!$suggestions) {
        echo '<p>' . esc_html__('Aktuell sind keine belastbaren Ortvorschläge gespeichert.', 'iss-wf-import') . '</p>';
    } else {
        echo '<div class="iss-wf-suggestions">';
        foreach ($suggestions as $index => $suggestion) {
            $place_id = (int) ($suggestion['place_id'] ?? 0);
            $place = get_post($place_id);
            if (!$place instanceof WP_Post) {
                continue;
            }

            $title = get_the_title($place);
            $score = (int) ($suggestion['score'] ?? 0);
            $reasons = (array) ($suggestion['reasons'] ?? []);
            $matched_terms = (array) ($suggestion['matched_terms'] ?? []);

            echo '<div style="margin:0 0 1rem 0;padding:0 0 1rem 0;border-bottom:1px solid #ddd;">';
            echo '<label style="display:flex;gap:0.5rem;align-items:flex-start;">';
            echo '<input type="checkbox" name="iss_wf_apply_place_ids[]" value="' . esc_attr((string) $place_id) . '">';
            echo '<span>';
            echo '<strong>' . esc_html($title) . '</strong><br>';
            echo '<small>' . esc_html(sprintf(__('Trefferwert %d', 'iss-wf-import'), $score)) . '</small>';
            echo '</span>';
            echo '</label>';

            if ($matched_terms) {
                echo '<p style="margin:0.4rem 0 0 1.45rem;"><small><strong>' . esc_html__('Treffer', 'iss-wf-import') . ':</strong> ' . esc_html(implode(', ', $matched_terms)) . '</small></p>';
            }

            if ($reasons) {
                echo '<ul style="margin:0.4rem 0 0 2.2rem;list-style:disc;">';
                foreach ($reasons as $reason) {
                    echo '<li><small>' . esc_html((string) $reason) . '</small></li>';
                }
                echo '</ul>';
            }

            echo '</div>';
        }
        echo '</div>';
    }

    echo '<p>';
    submit_button(__('Ortvorschläge aktualisieren', 'iss-wf-import'), 'secondary', 'iss_wf_refresh_suggestions', false);
    echo ' ';
    submit_button(__('Ausgewählte Orte verknüpfen', 'iss-wf-import'), 'primary', 'iss_wf_apply_suggestions', false);
    echo '</p>';

    echo '<p><small>' . esc_html__('Übernommene Orte erscheinen danach in der Metabox „Verknüpfte Orte“ und im gemeinsamen Relations-Index.', 'iss-wf-import') . '</small></p>';
}

function iss_wf_import_handle_suggestions_meta_box_save(int $post_id, WP_Post $post): void
{
    if (!iss_wf_import_is_suggestion_supported_post_type($post->post_type)) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $nonce = $_POST['iss_wf_import_suggestions_nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce($nonce, 'iss_wf_import_suggestions_action')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['iss_wf_refresh_suggestions'])) {
        iss_wf_import_refresh_suggestions_for_post($post_id);
    }

    if (isset($_POST['iss_wf_apply_suggestions'])) {
        $place_ids = wp_unslash($_POST['iss_wf_apply_place_ids'] ?? []);
        iss_wf_import_apply_place_suggestions($post_id, is_array($place_ids) ? $place_ids : []);
    }
}
add_action('save_post', 'iss_wf_import_handle_suggestions_meta_box_save', 20, 2);

function iss_wf_import_get_admin_review_filters(): array
{
    return [
        'has_suggestions' => __('Mit Ortvorschlägen', 'iss-wf-import'),
        'without_suggestions' => __('Ohne Ortvorschläge', 'iss-wf-import'),
        'has_relations' => __('Mit verknüpften Orten', 'iss-wf-import'),
        'needs_review' => __('Orte prüfen', 'iss-wf-import'),
    ];
}

function iss_wf_import_get_selected_admin_review_filter(): string
{
    $value = sanitize_key((string) ($_GET['iss_wf_review'] ?? ''));
    $filters = iss_wf_import_get_admin_review_filters();

    return isset($filters[$value]) ? $value : '';
}

function iss_wf_import_render_admin_review_filter(string $post_type): void
{
    if (!iss_wf_import_is_suggestion_supported_post_type($post_type)) {
        return;
    }

    $selected = iss_wf_import_get_selected_admin_review_filter();
    $filters = iss_wf_import_get_admin_review_filters();

    echo '<label class="screen-reader-text" for="filter-by-iss-wf-review">' . esc_html__('Ortsprüfung filtern', 'iss-wf-import') . '</label>';
    echo '<select name="iss_wf_review" id="filter-by-iss-wf-review">';
    echo '<option value="">' . esc_html__('Alle Ortsbezüge', 'iss-wf-import') . '</option>';

    foreach ($filters as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
    }

    echo '</select>';
}
add_action('restrict_manage_posts', 'iss_wf_import_render_admin_review_filter');

function iss_wf_import_filter_admin_review_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $post_type = sanitize_key((string) ($query->get('post_type') ?: 'post'));
    if (!iss_wf_import_is_suggestion_supported_post_type($post_type)) {
        return;
    }

    $selected = iss_wf_import_get_selected_admin_review_filter();
    if ($selected === '') {
        return;
    }

    if ($selected === 'has_suggestions') {
        $query->set('meta_query', [[
            'key' => ISS_WF_IMPORT_PLACE_SUGGESTIONS_META,
            'compare' => 'EXISTS',
        ]]);
        return;
    }

    if ($selected === 'without_suggestions') {
        $query->set('meta_query', [[
            'key' => ISS_WF_IMPORT_PLACE_SUGGESTIONS_META,
            'compare' => 'NOT EXISTS',
        ]]);
        return;
    }

    if (!defined('ISS_RELATIONS_TAXONOMY') || !taxonomy_exists(ISS_RELATIONS_TAXONOMY)) {
        return;
    }

    if ($selected === 'has_relations') {
        $query->set('tax_query', [[
            'taxonomy' => ISS_RELATIONS_TAXONOMY,
            'operator' => 'EXISTS',
        ]]);
        return;
    }

    if ($selected === 'needs_review') {
        $query->set('meta_query', [[
            'key' => ISS_WF_IMPORT_PLACE_SUGGESTIONS_META,
            'compare' => 'EXISTS',
        ]]);
        $query->set('tax_query', [[
            'taxonomy' => ISS_RELATIONS_TAXONOMY,
            'operator' => 'NOT EXISTS',
        ]]);
    }
}
add_action('pre_get_posts', 'iss_wf_import_filter_admin_review_query');

function iss_wf_import_get_admin_place_preview(array $items, string $context, int $limit = 2): string
{
    $lines = [];
    $total = count($items);

    foreach (array_slice($items, 0, $limit) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $place_id = (int) ($item['place_id'] ?? 0);
        if ($place_id <= 0) {
            continue;
        }

        $title = trim((string) get_the_title($place_id));
        if ($title === '') {
            continue;
        }

        if ($context === 'suggestion') {
            $score = (int) ($item['score'] ?? 0);
            $title .= ' (' . sprintf(__('Trefferwert %d', 'iss-wf-import'), $score) . ')';
        }

        $lines[] = $title;
    }

    if (!$lines) {
        return '—';
    }

    if ($total > $limit) {
        $lines[] = sprintf(__('plus %d weitere Orte', 'iss-wf-import'), $total - $limit);
    }

    return implode('<br>', array_map('esc_html', $lines));
}

function iss_wf_import_filter_admin_columns(array $columns): array
{
    $result = [];

    foreach ($columns as $key => $label) {
        $result[$key] = $label;

        if ($key === 'title') {
            $result['iss_wf_suggestions'] = __('Ortvorschläge', 'iss-wf-import');
            $result['iss_wf_related_places'] = __('Verknüpfte Orte', 'iss-wf-import');
        }
    }

    return $result;
}
function iss_wf_import_render_admin_column(string $column, int $post_id): void
{
    if ($column === 'iss_wf_suggestions') {
        $suggestions = iss_wf_import_sanitize_place_suggestions((array) get_post_meta($post_id, ISS_WF_IMPORT_PLACE_SUGGESTIONS_META, true));
        if (!$suggestions) {
            echo '—';
            return;
        }

        echo wp_kses_post(iss_wf_import_get_admin_place_preview($suggestions, 'suggestion'));
        return;
    }

    if ($column === 'iss_wf_related_places') {
        $relations = function_exists('iss_relations_get_post_relations')
            ? iss_relations_get_post_relations($post_id)
            : [];

        if (!$relations) {
            echo '—';
            return;
        }

        echo wp_kses_post(iss_wf_import_get_admin_place_preview($relations, 'relation'));
    }
}

foreach (iss_wf_import_get_suggestion_post_types() as $suggestion_post_type) {
    add_filter('manage_' . $suggestion_post_type . '_posts_columns', 'iss_wf_import_filter_admin_columns');
    add_action('manage_' . $suggestion_post_type . '_posts_custom_column', 'iss_wf_import_render_admin_column', 10, 2);
}
