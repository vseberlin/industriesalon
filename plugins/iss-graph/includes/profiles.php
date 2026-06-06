<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Profile bridge reads plugin-owned graph tables through prepared dynamic table queries.

function iss_graph_get_entity_profile_post_type(): string
{
    if (defined('ISS_CONTENT_MODEL_ENTITY_PROFILE_POST_TYPE')) {
        return ISS_CONTENT_MODEL_ENTITY_PROFILE_POST_TYPE;
    }

    return 'entity_profile';
}

function iss_graph_get_entity_profile_meta_key(): string
{
    return '_iss_graph_profile_entity_id';
}

function iss_graph_get_profile_entity_kinds(): array
{
    return ['organization', 'person'];
}

function iss_graph_get_entity_kind_label(string $entity_kind): string
{
    switch (sanitize_key($entity_kind)) {
        case 'organization':
            return __('Organisation', 'iss-graph');
        case 'person':
            return __('Person', 'iss-graph');
        case 'place':
            return __('Ort', 'iss-graph');
        default:
            return ucfirst(str_replace('_', ' ', sanitize_key($entity_kind)));
    }
}

function iss_graph_is_entity_profile_post($post): bool
{
    $post = $post instanceof WP_Post ? $post : get_post($post);

    return $post instanceof WP_Post && $post->post_type === iss_graph_get_entity_profile_post_type();
}

function iss_graph_register_profile_meta(): void
{
    $post_type = iss_graph_get_entity_profile_post_type();
    if (!post_type_exists($post_type)) {
        return;
    }

    register_post_meta($post_type, iss_graph_get_entity_profile_meta_key(), [
        'type' => 'integer',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => static function (): bool {
            return current_user_can('edit_posts');
        },
        'sanitize_callback' => 'absint',
    ]);
}
add_action('init', 'iss_graph_register_profile_meta', 25);

function iss_graph_get_profile_linked_entity_id(int $post_id): int
{
    return absint(get_post_meta($post_id, iss_graph_get_entity_profile_meta_key(), true));
}

function iss_graph_get_profile_linked_entity(int $post_id): ?array
{
    $entity_id = iss_graph_get_profile_linked_entity_id($post_id);
    if ($entity_id <= 0) {
        return null;
    }

    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !in_array((string) ($entity['entity_kind'] ?? ''), iss_graph_get_profile_entity_kinds(), true)) {
        return null;
    }

    return $entity;
}

function iss_graph_get_profile_linkable_entities(): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $name_table = $service->get_name_table_name();
    $kinds = iss_graph_get_profile_entity_kinds();
    $placeholders = implode(', ', array_fill(0, count($kinds), '%s'));

    $sql = "SELECT
            e.id,
            e.entity_kind,
            e.display_title,
            e.profile_post_id,
            pn.name AS primary_name
        FROM {$entity_table} e
        LEFT JOIN {$name_table} pn
            ON pn.entity_id = e.id
           AND pn.is_primary = 1
        WHERE e.entity_kind IN ({$placeholders})
        ORDER BY e.entity_kind ASC, COALESCE(pn.name, e.display_title) ASC, e.id ASC";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $kinds), ARRAY_A);
    if (!is_array($rows)) {
        return [];
    }

    return array_values(array_map(static function (array $row): array {
        $name = trim((string) ($row['primary_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['display_title'] ?? ''));
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'entity_kind' => (string) ($row['entity_kind'] ?? ''),
            'name' => $name,
            'profile_post_id' => (int) ($row['profile_post_id'] ?? 0),
        ];
    }, $rows));
}

function iss_graph_get_profile_entity_choices_grouped(): array
{
    $grouped = [
        'organization' => [],
        'person' => [],
    ];

    foreach (iss_graph_get_profile_linkable_entities() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $kind = sanitize_key((string) ($row['entity_kind'] ?? ''));
        if (!isset($grouped[$kind])) {
            continue;
        }

        $grouped[$kind][] = $row;
    }

    return $grouped;
}

function iss_graph_maybe_clear_register_places_cache(): void
{
    if (function_exists('iss_register_clear_places_cache')) {
        iss_register_clear_places_cache();
    }
}

function iss_graph_get_profile_fact_field_labels(string $entity_kind): array
{
    $entity_kind = sanitize_key($entity_kind);

    if ($entity_kind === 'person') {
        return [
            'summary' => __('Kurzprofil', 'iss-graph'),
            'description' => __('Beschreibung', 'iss-graph'),
            'website' => __('Website', 'iss-graph'),
            'source_summary' => __('Quellenhinweis', 'iss-graph'),
            'person_kind' => __('Rolle / Funktion', 'iss-graph'),
            'birth_year' => __('Geboren', 'iss-graph'),
            'death_year' => __('Gestorben', 'iss-graph'),
        ];
    }

    return [
        'summary' => __('Kurzprofil', 'iss-graph'),
        'description' => __('Beschreibung', 'iss-graph'),
        'website' => __('Website', 'iss-graph'),
        'source_summary' => __('Quellenhinweis', 'iss-graph'),
        'organization_kind' => __('Organisationstyp', 'iss-graph'),
        'organization_status' => __('Status', 'iss-graph'),
        'founded_year' => __('Gegründet', 'iss-graph'),
        'dissolved_year' => __('Aufgelöst', 'iss-graph'),
    ];
}

function iss_graph_get_profile_linked_entity_facts(int $post_id): array
{
    $entity = iss_graph_get_profile_linked_entity($post_id);
    if (!$entity) {
        return [];
    }

    return iss_graph_get_service()->get_entity_facts((string) ($entity['entity_kind'] ?? ''), (int) ($entity['id'] ?? 0));
}

function iss_graph_format_fact_year_range($start, $end, string $prefix_start = '', string $prefix_end = ''): string
{
    $start = is_numeric($start) ? (int) $start : 0;
    $end = is_numeric($end) ? (int) $end : 0;

    if ($start > 0 && $end > 0) {
        return $start === $end ? (string) $start : ($start . '–' . $end);
    }

    if ($start > 0) {
        return $prefix_start !== '' ? ($prefix_start . ' ' . $start) : (string) $start;
    }

    if ($end > 0) {
        return $prefix_end !== '' ? ($prefix_end . ' ' . $end) : (string) $end;
    }

    return '';
}

function iss_graph_get_entity_profile_fact_rows(array $entity, array $facts): array
{
    $entity_kind = sanitize_key((string) ($entity['entity_kind'] ?? ''));
    $labels = iss_graph_get_profile_fact_field_labels($entity_kind);
    $rows = [];

    if ($entity_kind === 'person') {
        $person_kind = trim((string) ($facts['person_kind'] ?? ''));
        if ($person_kind !== '') {
            $rows[] = [
                'label' => (string) ($labels['person_kind'] ?? __('Rolle / Funktion', 'iss-graph')),
                'value' => $person_kind,
            ];
        }

        $years = iss_graph_format_fact_year_range($facts['birth_year'] ?? null, $facts['death_year'] ?? null);
        if ($years !== '') {
            $rows[] = [
                'label' => __('Lebensdaten', 'iss-graph'),
                'value' => $years,
            ];
        }
    } elseif ($entity_kind === 'organization') {
        $organization_kind = trim((string) ($facts['organization_kind'] ?? ''));
        if ($organization_kind !== '') {
            $rows[] = [
                'label' => (string) ($labels['organization_kind'] ?? __('Organisationstyp', 'iss-graph')),
                'value' => $organization_kind,
            ];
        }

        $status = trim((string) ($facts['organization_status'] ?? ''));
        if ($status !== '') {
            $rows[] = [
                'label' => (string) ($labels['organization_status'] ?? __('Status', 'iss-graph')),
                'value' => $status,
            ];
        }

        $years = iss_graph_format_fact_year_range(
            $facts['founded_year'] ?? null,
            $facts['dissolved_year'] ?? null,
            __('seit', 'iss-graph'),
            __('bis', 'iss-graph')
        );
        if ($years !== '') {
            $rows[] = [
                'label' => __('Zeitraum', 'iss-graph'),
                'value' => $years,
            ];
        }
    }

    $website = trim((string) ($facts['website'] ?? ''));
    if ($website !== '') {
        $rows[] = [
            'label' => (string) ($labels['website'] ?? __('Website', 'iss-graph')),
            'value' => sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($website),
                esc_html(preg_replace('#^https?://#', '', untrailingslashit($website)))
            ),
            'html' => true,
        ];
    }

    $source_summary = trim((string) ($facts['source_summary'] ?? ''));
    if ($source_summary !== '') {
        $rows[] = [
            'label' => (string) ($labels['source_summary'] ?? __('Quellenhinweis', 'iss-graph')),
            'value' => $source_summary,
        ];
    }

    return $rows;
}

function iss_graph_register_profile_blocks(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    register_block_type('iss-graph/profile-facts', [
        'api_version' => 3,
        'title' => __('Profile Facts', 'iss-graph'),
        'category' => 'widgets',
        'icon' => 'id-alt',
        'description' => __('Renders shared graph-backed facts for curated entity profiles.', 'iss-graph'),
        'supports' => [
            'html' => false,
        ],
        'render_callback' => 'iss_graph_render_profile_facts_block',
    ]);
}
add_action('init', 'iss_graph_register_profile_blocks', 30);

function iss_graph_render_profile_facts_block(array $attributes = [], string $content = '', $block = null): string
{
    unset($attributes, $content);

    $post = get_post();
    if (!$post instanceof WP_Post || $post->post_type !== iss_graph_get_entity_profile_post_type()) {
        return '';
    }

    $entity = iss_graph_get_profile_linked_entity((int) $post->ID);
    if (!$entity || empty($entity['id']) || empty($entity['entity_kind'])) {
        return '';
    }

    $facts = iss_graph_get_service()->get_entity_facts((string) $entity['entity_kind'], (int) $entity['id']);
    if (!$facts) {
        return '';
    }

    $summary = trim((string) ($facts['summary'] ?? ''));
    $description = trim((string) ($facts['description'] ?? ''));
    $rows = iss_graph_get_entity_profile_fact_rows($entity, $facts);

    if ($summary === '' && $description === '' && !$rows) {
        return '';
    }

    $heading_title = sanitize_key((string) ($entity['entity_kind'] ?? '')) === 'person'
        ? __('Zur Person', 'iss-graph')
        : __('Zur Organisation', 'iss-graph');
    $wrapper_attrs = (function_exists('get_block_wrapper_attributes') && ($block instanceof WP_Block))
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-graph-profile-facts'])
        : 'class="wp-block-iss-graph-profile-facts"';

    $out = '<div ' . $wrapper_attrs . '>';

    if ($rows) {
        $out .= '<div class="iss-info-panel">';
        $out .= '<div class="wp-block-columns iss-info-panel__grid">';
        $out .= '<div class="wp-block-column iss-info-panel__title-col">';
        $out .= '<div class="iss-heading iss-heading--uncaged iss-info-panel__heading">';
        $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Profilfakten', 'iss-graph') . '</p>';
        $out .= '<h2 class="iss-heading__title iss-info-panel__title">' . esc_html($heading_title) . '</h2>';
        if ($summary !== '') {
            $out .= '<p class="iss-heading__text iss-info-panel__intro">' . esc_html($summary) . '</p>';
        }
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<div class="wp-block-column iss-info-panel__rows-col">';
        $out .= '<div class="iss-info-panel__rows">';

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['value'])) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $value = (string) ($row['value'] ?? '');
            $allow_html = !empty($row['html']);

            $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text">';
            if ($label !== '') {
                $out .= '<strong>' . esc_html($label) . '</strong><br>';
            }
            $out .= $allow_html ? wp_kses_post($value) : esc_html($value);
            $out .= '</p></div></div>';
        }

        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
    } else {
        $out .= '<div class="iss-heading iss-heading--uncaged">';
        $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Profilfakten', 'iss-graph') . '</p>';
        $out .= '<h2 class="iss-heading__title">' . esc_html($heading_title) . '</h2>';
        if ($summary !== '') {
            $out .= '<p class="iss-heading__text">' . esc_html($summary) . '</p>';
        }
        $out .= '</div>';
    }

    if ($description !== '') {
        $out .= '<div class="wp-block-group iss-entity-profile-facts__description">';
        $out .= wpautop(esc_html($description));
        $out .= '</div>';
    }

    $out .= '</div>';

    return $out;
}

function iss_graph_add_profile_meta_box(): void
{
    $post_type = iss_graph_get_entity_profile_post_type();
    if (!post_type_exists($post_type)) {
        return;
    }

    add_meta_box(
        'iss-graph-entity-profile-link',
        __('Graph-Verknüpfung', 'iss-graph'),
        'iss_graph_render_profile_meta_box',
        $post_type,
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'iss_graph_add_profile_meta_box');

function iss_graph_render_profile_meta_box(WP_Post $post): void
{
    $linked_entity = iss_graph_get_profile_linked_entity((int) $post->ID);
    $selected_id = (int) ($linked_entity['id'] ?? 0);
    $choices = iss_graph_get_profile_entity_choices_grouped();

    wp_nonce_field('iss_graph_profile_link', 'iss_graph_profile_link_nonce');

    echo '<p>' . esc_html__('Dieses Profil bleibt ein kuratierter öffentlicher Text. Die eigentliche Person- oder Organisationsbeziehung bleibt im Graphen.', 'iss-graph') . '</p>';
    echo '<p><label for="iss-graph-profile-entity"><strong>' . esc_html__('Graph-Eintrag', 'iss-graph') . '</strong></label></p>';
    echo '<select id="iss-graph-profile-entity" name="iss_graph_profile_entity_id" style="width:100%">';
    echo '<option value="0">' . esc_html__('Keine Verknüpfung', 'iss-graph') . '</option>';

    foreach ($choices as $kind => $rows) {
        if (!$rows) {
            continue;
        }

        echo '<optgroup label="' . esc_attr(iss_graph_get_entity_kind_label($kind)) . '">';
        foreach ($rows as $row) {
            $entity_id = (int) ($row['id'] ?? 0);
            $label = trim((string) ($row['name'] ?? ''));
            if ($label === '') {
                $label = sprintf(__('Eintrag %d', 'iss-graph'), $entity_id);
            }

            $suffix = '';
            $profile_post_id = (int) ($row['profile_post_id'] ?? 0);
            if ($profile_post_id > 0 && $profile_post_id !== (int) $post->ID) {
                $suffix = ' ' . sprintf(__('(bereits Profil %d)', 'iss-graph'), $profile_post_id);
            }

            echo '<option value="' . esc_attr((string) $entity_id) . '"' . selected($selected_id, $entity_id, false) . '>' . esc_html($label . $suffix) . '</option>';
        }
        echo '</optgroup>';
    }

    echo '</select>';

    if ($linked_entity) {
        echo '<p class="description">';
        echo esc_html(sprintf(
            __('Aktuell verknüpft mit %s #%d.', 'iss-graph'),
            iss_graph_get_entity_kind_label((string) ($linked_entity['entity_kind'] ?? '')),
            (int) ($linked_entity['id'] ?? 0)
        ));
        echo '</p>';
    }
}

function iss_graph_add_profile_facts_meta_box(): void
{
    $post_type = iss_graph_get_entity_profile_post_type();
    if (!post_type_exists($post_type)) {
        return;
    }

    add_meta_box(
        'iss-graph-entity-profile-facts',
        __('Profilfakten', 'iss-graph'),
        'iss_graph_render_profile_facts_meta_box',
        $post_type,
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'iss_graph_add_profile_facts_meta_box');

function iss_graph_add_profile_aliases_meta_box(): void
{
    $post_type = iss_graph_get_entity_profile_post_type();
    if (!post_type_exists($post_type)) {
        return;
    }

    add_meta_box(
        'iss-graph-entity-profile-aliases',
        __('Weitere Namen / Aliase', 'iss-graph'),
        'iss_graph_render_profile_aliases_meta_box',
        $post_type,
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'iss_graph_add_profile_aliases_meta_box');

function iss_graph_render_profile_facts_meta_box(WP_Post $post): void
{
    $entity = iss_graph_get_profile_linked_entity((int) $post->ID);

    wp_nonce_field('iss_graph_profile_facts', 'iss_graph_profile_facts_nonce');

    if (!$entity) {
        echo '<p>' . esc_html__('Zuerst einen Personen- oder Organisationseintrag im Feld „Graph-Verknüpfung“ auswählen und speichern. Danach können hier die strukturierten Profildaten gepflegt werden.', 'iss-graph') . '</p>';
        return;
    }

    $entity_kind = (string) ($entity['entity_kind'] ?? '');
    $facts = iss_graph_get_service()->get_entity_facts($entity_kind, (int) ($entity['id'] ?? 0));
    $labels = iss_graph_get_profile_fact_field_labels($entity_kind);

    echo '<p class="description">' . esc_html(sprintf(
        __('Diese Felder werden direkt am verknüpften %s-Eintrag im Graphen gespeichert und können von Profilseite und Suche gemeinsam genutzt werden.', 'iss-graph'),
        strtolower(iss_graph_get_entity_kind_label($entity_kind))
    )) . '</p>';

    echo '<table class="form-table" role="presentation"><tbody>';

    foreach (['summary', 'description', 'website', 'source_summary'] as $field) {
        if (!isset($labels[$field])) {
            continue;
        }

        echo '<tr>';
        echo '<th scope="row"><label for="iss-graph-profile-facts-' . esc_attr($field) . '">' . esc_html($labels[$field]) . '</label></th>';
        echo '<td>';

        if (in_array($field, ['summary', 'description', 'source_summary'], true)) {
            $rows = $field === 'description' ? 5 : 3;
            echo '<textarea class="widefat" rows="' . esc_attr((string) $rows) . '" id="iss-graph-profile-facts-' . esc_attr($field) . '" name="iss_graph_profile_facts[' . esc_attr($field) . ']">' . esc_textarea((string) ($facts[$field] ?? '')) . '</textarea>';
        } else {
            echo '<input class="regular-text" type="url" id="iss-graph-profile-facts-' . esc_attr($field) . '" name="iss_graph_profile_facts[' . esc_attr($field) . ']" value="' . esc_attr((string) ($facts[$field] ?? '')) . '">';
        }

        echo '</td>';
        echo '</tr>';
    }

    if ($entity_kind === 'person') {
        foreach (['person_kind', 'birth_year', 'death_year'] as $field) {
            echo '<tr>';
            echo '<th scope="row"><label for="iss-graph-profile-facts-' . esc_attr($field) . '">' . esc_html($labels[$field]) . '</label></th>';
            echo '<td>';

            if (in_array($field, ['birth_year', 'death_year'], true)) {
                echo '<input class="small-text" type="number" step="1" id="iss-graph-profile-facts-' . esc_attr($field) . '" name="iss_graph_profile_facts[' . esc_attr($field) . ']" value="' . esc_attr((string) ($facts[$field] ?? '')) . '">';
            } else {
                echo '<input class="regular-text" type="text" id="iss-graph-profile-facts-' . esc_attr($field) . '" name="iss_graph_profile_facts[' . esc_attr($field) . ']" value="' . esc_attr((string) ($facts[$field] ?? '')) . '">';
            }

            echo '</td>';
            echo '</tr>';
        }
    } else {
        foreach (['organization_kind', 'organization_status', 'founded_year', 'dissolved_year'] as $field) {
            echo '<tr>';
            echo '<th scope="row"><label for="iss-graph-profile-facts-' . esc_attr($field) . '">' . esc_html($labels[$field]) . '</label></th>';
            echo '<td>';

            if (in_array($field, ['founded_year', 'dissolved_year'], true)) {
                echo '<input class="small-text" type="number" step="1" id="iss-graph-profile-facts-' . esc_attr($field) . '" name="iss_graph_profile_facts[' . esc_attr($field) . ']" value="' . esc_attr((string) ($facts[$field] ?? '')) . '">';
            } else {
                echo '<input class="regular-text" type="text" id="iss-graph-profile-facts-' . esc_attr($field) . '" name="iss_graph_profile_facts[' . esc_attr($field) . ']" value="' . esc_attr((string) ($facts[$field] ?? '')) . '">';
            }

            echo '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
}

function iss_graph_render_profile_aliases_meta_box(WP_Post $post): void
{
    $entity = iss_graph_get_profile_linked_entity((int) $post->ID);

    wp_nonce_field('iss_graph_profile_aliases', 'iss_graph_profile_aliases_nonce');

    if (!$entity || empty($entity['id'])) {
        echo '<p>' . esc_html__('Zuerst einen Personen- oder Organisationseintrag im Feld „Graph-Verknüpfung“ auswählen und speichern. Danach können hier alternative Namen und Schreibweisen gepflegt werden.', 'iss-graph') . '</p>';
        return;
    }

    $entity_id = (int) $entity['id'];
    $service = iss_graph_get_service();
    $source_system = function_exists('iss_graph_get_profile_alias_source_system')
        ? iss_graph_get_profile_alias_source_system()
        : 'entity_profile_admin';
    $names = array_map('iss_graph_map_name_row_for_editor', $service->get_names_for_entity($entity_id, [
        'source_system' => $source_system,
    ]));
    $generated = function_exists('iss_graph_get_alias_backfill_source_system')
        ? $service->get_names_for_entity($entity_id, [
            'source_system' => iss_graph_get_alias_backfill_source_system(),
            'limit' => 20,
        ])
        : [];

    $name_rows = wp_json_encode($names, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($name_rows) || $name_rows === '') {
        $name_rows = '[]';
    }

    echo '<p class="description">' . esc_html__('Diese Namen werden direkt am verknüpften Graph-Eintrag gespeichert. Automatisch erzeugte Schreibvarianten bleiben getrennt und werden hier nur als Hinweis angezeigt.', 'iss-graph') . '</p>';

    if ($generated) {
        $labels = array_values(array_filter(array_map(static function (array $row): string {
            return trim((string) ($row['name'] ?? ''));
        }, $generated)));

        if ($labels) {
            echo '<p class="description"><strong>' . esc_html__('Automatisch:', 'iss-graph') . '</strong> ' . esc_html(implode(', ', $labels)) . '</p>';
        }
    }

    echo '<div class="iss-graph-editor"';
    echo ' data-name-types="' . esc_attr(wp_json_encode(iss_graph_get_entity_name_type_options(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '">';

    echo '<section class="iss-graph-editor__group" data-group="names" data-input-id="iss-graph-profile-name-rows">';
    echo '<div class="iss-graph-editor__header"><h4>' . esc_html__('Namen', 'iss-graph') . '</h4><p class="description">' . esc_html__('Historische Namen, Abkürzungen, Schreibvarianten und bekannte Quellenformen.', 'iss-graph') . '</p></div>';
    echo '<div class="iss-graph-editor__rows"></div>';
    echo '<p><button type="button" class="button button-secondary iss-graph-editor__add">' . esc_html__('Namen hinzufügen', 'iss-graph') . '</button></p>';
    echo '<textarea id="iss-graph-profile-name-rows" name="iss_graph_profile_name_rows" rows="6" style="display:none;">' . esc_textarea($name_rows) . '</textarea>';
    echo '</section>';

    echo '</div>';
}

function iss_graph_clear_profile_binding_for_entity_id(int $entity_id): void
{
    if ($entity_id <= 0) {
        return;
    }

    $service = iss_graph_get_service();
    $entity = $service->get_entity_by_id($entity_id);
    if (!$entity) {
        return;
    }

    $service->upsert_entity(array_merge($entity, [
        'has_profile' => false,
        'profile_post_id' => 0,
        'is_public' => false,
        'search_visibility' => 'hidden',
    ]));

    iss_graph_maybe_clear_register_places_cache();
}

function iss_graph_sync_profile_binding_for_post(int $post_id, int $previous_entity_id = 0): void
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== iss_graph_get_entity_profile_post_type()) {
        return;
    }

    $service = iss_graph_get_service();
    $entity_id = iss_graph_get_profile_linked_entity_id($post_id);
    $entity = $entity_id > 0 ? $service->get_entity_by_id($entity_id) : null;

    if (!$entity || !in_array((string) ($entity['entity_kind'] ?? ''), iss_graph_get_profile_entity_kinds(), true)) {
        if ($entity_id > 0) {
            delete_post_meta($post_id, iss_graph_get_entity_profile_meta_key());
        }
        $entity_id = 0;
    }

    if ($previous_entity_id > 0 && $previous_entity_id !== $entity_id) {
        iss_graph_clear_profile_binding_for_entity_id($previous_entity_id);
    }

    if ($entity_id <= 0 || !$entity) {
        return;
    }

    $other_profile_post_id = absint($entity['profile_post_id'] ?? 0);
    if ($other_profile_post_id > 0 && $other_profile_post_id !== $post_id) {
        delete_post_meta($other_profile_post_id, iss_graph_get_entity_profile_meta_key());
        if (function_exists('iss_graph_sync_public_search_post')) {
            iss_graph_sync_public_search_post($other_profile_post_id);
        }
    }

    $is_public = $post->post_status === 'publish';
    $has_profile = !in_array($post->post_status, ['trash', 'auto-draft'], true);

    $service->upsert_entity(array_merge($entity, [
        'profile_post_id' => $post_id,
        'has_profile' => $has_profile,
        'is_public' => $is_public,
        'search_visibility' => $is_public ? 'public' : 'hidden',
    ]));

    iss_graph_sync_wp_post_identifiers((int) $entity_id, $post, 'entity_profile');

    if (function_exists('iss_graph_sync_entity_alias_backfill')) {
        iss_graph_sync_entity_alias_backfill((int) $entity_id);
    }

    if (function_exists('iss_graph_sync_public_search_post')) {
        iss_graph_sync_public_search_post($post_id);
    }

    iss_graph_maybe_clear_register_places_cache();
}

function iss_graph_maybe_backfill_entity_profile_bindings(): void
{
    if (!post_type_exists(iss_graph_get_entity_profile_post_type())) {
        return;
    }

    $stored = (string) get_option(ISS_GRAPH_PROFILE_BACKFILL_OPTION, '');
    if ($stored === ISS_GRAPH_PROFILE_BACKFILL_VERSION) {
        return;
    }

    iss_graph_backfill_entity_profile_bindings();
    update_option(ISS_GRAPH_PROFILE_BACKFILL_OPTION, ISS_GRAPH_PROFILE_BACKFILL_VERSION, false);
}

function iss_graph_backfill_entity_profile_bindings(): int
{
    if (!post_type_exists(iss_graph_get_entity_profile_post_type())) {
        return 0;
    }

    $post_ids = get_posts([
        'post_type' => iss_graph_get_entity_profile_post_type(),
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    $count = 0;
    foreach ($post_ids as $post_id) {
        $entity_id = iss_graph_get_profile_linked_entity_id((int) $post_id);
        if ($entity_id <= 0) {
            continue;
        }

        iss_graph_sync_profile_binding_for_post((int) $post_id);
        $count++;
    }

    return $count;
}

function iss_graph_save_entity_profile_link(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== iss_graph_get_entity_profile_post_type()) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (!isset($_POST['iss_graph_profile_link_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_profile_link_nonce'])), 'iss_graph_profile_link')) {
        return;
    }

    $previous_entity_id = iss_graph_get_profile_linked_entity_id($post_id);
    $entity_id = isset($_POST['iss_graph_profile_entity_id']) ? absint(wp_unslash($_POST['iss_graph_profile_entity_id'])) : 0;

    if ($entity_id > 0) {
        update_post_meta($post_id, iss_graph_get_entity_profile_meta_key(), $entity_id);
    } else {
        delete_post_meta($post_id, iss_graph_get_entity_profile_meta_key());
    }

    iss_graph_sync_profile_binding_for_post($post_id, $previous_entity_id);
}
add_action('save_post', 'iss_graph_save_entity_profile_link', 40, 2);

function iss_graph_refresh_entity_profile_binding(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== iss_graph_get_entity_profile_post_type()) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_graph_sync_profile_binding_for_post($post_id);
}
add_action('save_post', 'iss_graph_refresh_entity_profile_binding', 65, 2);

function iss_graph_save_entity_profile_facts(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== iss_graph_get_entity_profile_post_type()) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (!isset($_POST['iss_graph_profile_facts_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_profile_facts_nonce'])), 'iss_graph_profile_facts')) {
        return;
    }

    $entity = iss_graph_get_profile_linked_entity($post_id);
    if (!$entity || empty($entity['id']) || empty($entity['entity_kind'])) {
        return;
    }

    $raw = $_POST['iss_graph_profile_facts'] ?? [];
    if (!is_array($raw)) {
        return;
    }

    iss_graph_get_service()->upsert_entity_facts((string) $entity['entity_kind'], (int) $entity['id'], [
        'summary' => wp_unslash($raw['summary'] ?? ''),
        'description' => wp_unslash($raw['description'] ?? ''),
        'website' => wp_unslash($raw['website'] ?? ''),
        'source_summary' => wp_unslash($raw['source_summary'] ?? ''),
        'person_kind' => wp_unslash($raw['person_kind'] ?? ''),
        'birth_year' => wp_unslash($raw['birth_year'] ?? ''),
        'death_year' => wp_unslash($raw['death_year'] ?? ''),
        'organization_kind' => wp_unslash($raw['organization_kind'] ?? ''),
        'organization_status' => wp_unslash($raw['organization_status'] ?? ''),
        'founded_year' => wp_unslash($raw['founded_year'] ?? ''),
        'dissolved_year' => wp_unslash($raw['dissolved_year'] ?? ''),
    ]);

    if (function_exists('iss_graph_sync_public_search_post')) {
        iss_graph_sync_public_search_post($post_id);
    }
}
add_action('save_post', 'iss_graph_save_entity_profile_facts', 55, 2);

function iss_graph_save_entity_profile_aliases(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== iss_graph_get_entity_profile_post_type()) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (!isset($_POST['iss_graph_profile_aliases_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['iss_graph_profile_aliases_nonce'])), 'iss_graph_profile_aliases')) {
        return;
    }

    $entity = iss_graph_get_profile_linked_entity($post_id);
    if (!$entity || empty($entity['id'])) {
        return;
    }

    $source_system = function_exists('iss_graph_get_profile_alias_source_system')
        ? iss_graph_get_profile_alias_source_system()
        : 'entity_profile_admin';

    iss_graph_get_service()->replace_entity_names_for_source(
        (int) $entity['id'],
        $source_system,
        iss_graph_sanitize_entity_name_rows(iss_graph_decode_posted_rows('iss_graph_profile_name_rows'), iss_graph_get_entity_name_type_options()),
        'profile:' . $post_id
    );

    if (function_exists('iss_graph_sync_entity_alias_backfill')) {
        iss_graph_sync_entity_alias_backfill((int) $entity['id']);
    }

    if (function_exists('iss_graph_sync_public_search_post')) {
        iss_graph_sync_public_search_post($post_id);
    }
}
add_action('save_post', 'iss_graph_save_entity_profile_aliases', 58, 2);

function iss_graph_admin_enqueue_profile_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== iss_graph_get_entity_profile_post_type()) {
        return;
    }

    wp_enqueue_script(
        'iss-graph-profile-admin',
        ISS_GRAPH_URL . 'assets/js/register-place-graph-admin.js',
        [],
        ISS_GRAPH_VERSION,
        true
    );
    wp_enqueue_style(
        'iss-graph-profile-admin',
        ISS_GRAPH_URL . 'assets/css/register-place-graph-admin.css',
        [],
        ISS_GRAPH_VERSION
    );
}
add_action('admin_enqueue_scripts', 'iss_graph_admin_enqueue_profile_assets');

function iss_graph_handle_profile_post_status_change(int $post_id): void
{
    if (!iss_graph_is_entity_profile_post($post_id)) {
        return;
    }

    iss_graph_sync_profile_binding_for_post($post_id);
}
add_action('trashed_post', 'iss_graph_handle_profile_post_status_change');
add_action('untrashed_post', 'iss_graph_handle_profile_post_status_change');

function iss_graph_clear_profile_binding_on_delete(int $post_id): void
{
    if (!iss_graph_is_entity_profile_post($post_id)) {
        return;
    }

    $entity_id = iss_graph_get_profile_linked_entity_id($post_id);
    if ($entity_id > 0) {
        iss_graph_clear_profile_binding_for_entity_id($entity_id);
    }
}
add_action('before_delete_post', 'iss_graph_clear_profile_binding_on_delete', 8);

function iss_graph_get_profile_place_relations(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== iss_graph_get_entity_profile_post_type()) {
        return [];
    }

    $entity = iss_graph_get_profile_linked_entity($post_id);
    if (!$entity || empty($entity['id'])) {
        return [];
    }

    $rows = iss_graph_get_service()->get_incoming_relations_for_entity((int) $entity['id'], 'place', [
        'public_only' => true,
        'relation_family' => (string) ($entity['entity_kind'] ?? ''),
        'limit' => 100,
    ]);

    if (!$rows) {
        return [];
    }

    $mapped = [];
    $seen = [];

    foreach (array_values($rows) as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        $place_id = absint($row['post_id'] ?? 0);
        if ($place_id <= 0 || isset($seen[$place_id])) {
            continue;
        }

        $seen[$place_id] = true;
        $label = trim((string) ($row['relation_label'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($row['relation_type'] ?? ''));
        }

        $mapped[] = [
            'place_id' => $place_id,
            'role' => 'related',
            'weight' => isset($row['weight']) && (int) $row['weight'] !== 0 ? (int) $row['weight'] : max(10, 100 - ($index * 10)),
            'label' => $label,
            'route_title' => '',
            'route_teaser' => '',
            'station_object_id' => 0,
            'station_story_id' => 0,
        ];
    }

    return $mapped;
}

function iss_graph_filter_external_post_relations($relations, int $post_id, array $stored_relations)
{
    unset($stored_relations);

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== iss_graph_get_entity_profile_post_type()) {
        return $relations;
    }

    return iss_graph_get_profile_place_relations($post_id);
}
add_filter('iss_relations_external_post_relations', 'iss_graph_filter_external_post_relations', 10, 3);
